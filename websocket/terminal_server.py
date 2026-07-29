#!/usr/bin/env python3
"""
DockerPanel — WebSocket Terminal Server

Provides a WebSocket interface for interactive terminal sessions
inside Docker containers. Uses docker exec with PTY for full
terminal emulation.
"""

import asyncio
import json
import os
import signal
import struct
import subprocess
import sys
import logging

try:
    import websockets
except ImportError:
    print("Installing websockets...")
    subprocess.check_call([sys.executable, '-m', 'pip', 'install', 'websockets'])
    import websockets

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler('/var/www/dockerpanel/storage/logs/terminal.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger('terminal')

# Active sessions
sessions = {}

class TerminalSession:
    """Manages a single terminal session to a Docker container."""
    
    def __init__(self, container_id, websocket):
        self.container_id = container_id
        self.websocket = websocket
        self.process = None
        self.master_fd = None
        self.slave_fd = None
        self.running = False
    
    async def start(self):
        """Start the docker exec process with PTY."""
        import pty
        import fcntl
        import termios
        
        # Create PTY
        self.master_fd, self.slave_fd = pty.openpty()
        
        # Set terminal size (default 80x24)
        winsize = struct.pack('HHHH', 24, 80, 0, 0)
        fcntl.ioctl(self.slave_fd, termios.TIOCSWINSZ, winsize)
        
        # Determine shell
        shell = await self._detect_shell()
        
        # Start docker exec
        cmd = ['docker', 'exec', '-it', self.container_id, shell]
        
        self.process = subprocess.Popen(
            cmd,
            stdin=self.slave_fd,
            stdout=self.slave_fd,
            stderr=self.slave_fd,
            preexec_fn=os.setsid,
            close_fds=True
        )
        
        os.close(self.slave_fd)
        self.slave_fd = None
        self.running = True
        
        # Make master_fd non-blocking
        import fcntl
        flags = fcntl.fcntl(self.master_fd, fcntl.F_GETFL)
        fcntl.fcntl(self.master_fd, fcntl.F_SETFL, flags | os.O_NONBLOCK)
        
        logger.info(f"Terminal session started for container {self.container_id[:12]}")
        
        # Start reading output
        asyncio.create_task(self._read_output())
    
    async def _detect_shell(self):
        """Detect available shell in the container."""
        for shell in ['/bin/bash', '/bin/sh']:
            result = subprocess.run(
                ['docker', 'exec', self.container_id, 'which', shell],
                capture_output=True, text=True
            )
            if result.returncode == 0:
                return shell
        return '/bin/sh'
    
    async def _read_output(self):
        """Read output from the PTY and send to WebSocket."""
        loop = asyncio.get_event_loop()
        
        while self.running:
            try:
                data = await loop.run_in_executor(None, self._read_master)
                if data:
                    try:
                        await self.websocket.send(data.decode('utf-8', errors='replace'))
                    except websockets.exceptions.ConnectionClosed:
                        break
                else:
                    await asyncio.sleep(0.01)
            except (OSError, IOError):
                break
            except Exception as e:
                logger.error(f"Read error: {e}")
                break
        
        self.running = False
    
    def _read_master(self):
        """Blocking read from master fd."""
        import select
        try:
            r, _, _ = select.select([self.master_fd], [], [], 0.1)
            if r:
                return os.read(self.master_fd, 4096)
        except (OSError, ValueError):
            self.running = False
        return None
    
    async def write(self, data):
        """Write input to the PTY."""
        if self.master_fd is not None and self.running:
            try:
                os.write(self.master_fd, data.encode('utf-8'))
            except (OSError, IOError) as e:
                logger.error(f"Write error: {e}")
                self.running = False
    
    async def resize(self, rows, cols):
        """Resize the terminal."""
        if self.master_fd is not None:
            import fcntl
            import termios
            winsize = struct.pack('HHHH', rows, cols, 0, 0)
            try:
                fcntl.ioctl(self.master_fd, termios.TIOCSWINSZ, winsize)
            except (OSError, IOError):
                pass
    
    async def close(self):
        """Close the terminal session."""
        self.running = False
        
        if self.process:
            try:
                self.process.terminate()
                self.process.wait(timeout=5)
            except:
                try:
                    self.process.kill()
                except:
                    pass
        
        if self.master_fd is not None:
            try:
                os.close(self.master_fd)
            except:
                pass
            self.master_fd = None
        
        if self.slave_fd is not None:
            try:
                os.close(self.slave_fd)
            except:
                pass
            self.slave_fd = None
        
        logger.info(f"Terminal session closed for container {self.container_id[:12]}")


async def handle_connection(websocket, path=None):
    """Handle a new WebSocket connection."""
    session = None
    
    try:
        # First message should be connection config
        config_msg = await asyncio.wait_for(websocket.recv(), timeout=10)
        config = json.loads(config_msg)
        
        container_id = config.get('container_id', '')
        # Optional: token for auth validation
        # token = config.get('token', '')
        
        if not container_id:
            await websocket.send(json.dumps({'error': 'container_id required'}))
            return
        
        # Verify container exists and is running
        result = subprocess.run(
            ['docker', 'inspect', '--format', '{{.State.Running}}', container_id],
            capture_output=True, text=True
        )
        
        if result.returncode != 0 or result.stdout.strip() != 'true':
            await websocket.send(json.dumps({'error': 'Container is not running'}))
            return
        
        # Create terminal session
        session = TerminalSession(container_id, websocket)
        sessions[id(websocket)] = session
        
        await session.start()
        
        # Send ready signal
        await websocket.send('\r\n')
        
        # Handle incoming messages
        async for message in websocket:
            if not session.running:
                break
            
            # Check for control messages
            if message.startswith('\x1b[RESIZE:'):
                # Custom resize command: \x1b[RESIZE:rows:cols
                try:
                    parts = message.replace('\x1b[RESIZE:', '').rstrip().split(':')
                    rows = int(parts[0])
                    cols = int(parts[1])
                    await session.resize(rows, cols)
                except:
                    pass
            else:
                await session.write(message)
    
    except websockets.exceptions.ConnectionClosed:
        logger.info("WebSocket connection closed")
    except asyncio.TimeoutError:
        logger.warning("Connection timeout — no config received")
    except Exception as e:
        logger.error(f"Connection error: {e}")
    finally:
        if session:
            await session.close()
        sessions.pop(id(websocket) if websocket else None, None)


async def main():
    """Start the WebSocket server."""
    host = os.environ.get('WS_HOST', '0.0.0.0')
    port = int(os.environ.get('WS_PORT', 8765))
    
    logger.info(f"Starting WebSocket Terminal Server on {host}:{port}")
    
    # For websockets >= 10.x
    try:
        async with websockets.serve(handle_connection, host, port, 
                                     max_size=10 * 1024 * 1024,
                                     ping_interval=30,
                                     ping_timeout=10):
            logger.info(f"Server running on ws://{host}:{port}")
            await asyncio.Future()  # Run forever
    except TypeError:
        # Fallback for older websockets versions
        server = await websockets.serve(handle_connection, host, port)
        logger.info(f"Server running on ws://{host}:{port}")
        await server.wait_closed()


if __name__ == '__main__':
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.info("Server stopped by user")
    except Exception as e:
        logger.error(f"Fatal error: {e}")
        sys.exit(1)
