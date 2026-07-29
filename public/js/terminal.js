/**
 * DockerPanel — Terminal Module (xterm.js)
 */

const TerminalModule = {
    terminal: null,
    ws: null,
    fitAddon: null,

    init(containerId) {
        const termEl = document.getElementById('terminal-view');
        if (!termEl) return;

        // Инициализация xterm.js
        this.terminal = new Terminal({
            cursorBlink: true,
            cursorStyle: 'bar',
            fontSize: 14,
            fontFamily: "'JetBrains Mono', 'Fira Code', monospace",
            theme: {
                background: '#0d0d0d',
                foreground: '#e0e0e0',
                cursor: '#00d4ff',
                cursorAccent: '#0d0d0d',
                selectionBackground: 'rgba(0, 212, 255, 0.3)',
                black: '#1a1a2e',
                red: '#ef4444',
                green: '#10b981',
                yellow: '#f59e0b',
                blue: '#3b82f6',
                magenta: '#8b5cf6',
                cyan: '#00d4ff',
                white: '#e0e0e0',
                brightBlack: '#555577',
                brightRed: '#f87171',
                brightGreen: '#34d399',
                brightYellow: '#fbbf24',
                brightBlue: '#60a5fa',
                brightMagenta: '#a78bfa',
                brightCyan: '#22d3ee',
                brightWhite: '#ffffff',
            },
            scrollback: 5000,
            allowProposedApi: true,
        });

        // FitAddon
        this.fitAddon = new FitAddon.FitAddon();
        this.terminal.loadAddon(this.fitAddon);

        // WebLinks addon
        if (typeof WebLinksAddon !== 'undefined') {
            this.terminal.loadAddon(new WebLinksAddon.WebLinksAddon());
        }

        this.terminal.open(termEl);
        this.fitAddon.fit();

        // Подключение WebSocket
        this.connect(containerId);

        // Обработка ввода
        this.terminal.onData(data => {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(data);
            }
        });

        // Resize
        window.addEventListener('resize', () => {
            this.fitAddon.fit();
            this.sendResize();
        });

        this.terminal.onResize(() => {
            this.sendResize();
        });

        // Clipboard
        this.terminal.attachCustomKeyEventHandler((e) => {
            // Ctrl+Shift+C — copy
            if (e.ctrlKey && e.shiftKey && e.key === 'C') {
                const selection = this.terminal.getSelection();
                if (selection) navigator.clipboard.writeText(selection);
                return false;
            }
            // Ctrl+Shift+V — paste
            if (e.ctrlKey && e.shiftKey && e.key === 'V') {
                navigator.clipboard.readText().then(text => {
                    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                        this.ws.send(text);
                    }
                });
                return false;
            }
            return true;
        });
    },

    connect(containerId) {
        const wsHost = window.location.hostname;
        const wsPort = 8765;
        const wsUrl = `ws://${wsHost}:${wsPort}`;

        this.terminal.writeln('\x1b[36mПодключение к контейнеру...\x1b[0m\r\n');

        this.ws = new WebSocket(wsUrl);

        this.ws.onopen = () => {
            // Отправляем конфигурацию
            this.ws.send(JSON.stringify({
                container_id: containerId,
            }));
            this.terminal.writeln('\x1b[32mПодключено!\x1b[0m\r\n');
            this.sendResize();
        };

        this.ws.onmessage = (event) => {
            this.terminal.write(event.data);
        };

        this.ws.onerror = (error) => {
            this.terminal.writeln('\r\n\x1b[31mОшибка подключения WebSocket\x1b[0m');
        };

        this.ws.onclose = () => {
            this.terminal.writeln('\r\n\x1b[33mСоединение закрыто\x1b[0m');
        };
    },

    sendResize() {
        if (this.ws && this.ws.readyState === WebSocket.OPEN && this.terminal) {
            const rows = this.terminal.rows;
            const cols = this.terminal.cols;
            this.ws.send(`\x1b[RESIZE:${rows}:${cols}`);
        }
    },

    toggleFullscreen() {
        const container = document.querySelector('.terminal-container');
        if (container) {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                container.requestFullscreen();
            }
        }
    },

    disconnect() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        if (this.terminal) {
            this.terminal.dispose();
            this.terminal = null;
        }
    }
};
