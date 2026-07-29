/**
 * DockerPanel — Terminal Module (xterm.js)
 */

const TerminalModule = {
    terminal: null,
    ws: null,
    fitAddon: null,
    currentContainerId: null,

    showModal(containerId) {
        document.getElementById('term-host').textContent = containerId.substring(0, 12);
        document.getElementById('terminal-modal').classList.add('active');
        
        if (this.currentContainerId !== containerId) {
            this.init(containerId);
        } else {
            // Если тот же контейнер, просто подгоняем размер окна
            setTimeout(() => {
                if (this.fitAddon) this.fitAddon.fit();
                if (this.terminal) this.terminal.focus();
            }, 100);
        }
    },

    hideModal() {
        document.getElementById('terminal-modal').classList.remove('active');
    },

    init(containerId) {
        this.currentContainerId = containerId;
        const termEl = document.getElementById('terminal-view');
        if (!termEl) return;

        termEl.innerHTML = '';
        if (this.terminal) {
            this.terminal.dispose();
        }

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
        const protocol = window.location.protocol === 'https:' ? 'wss' : 'ws';
        const wsUrl = `${protocol}://${wsHost}/ws/`;

        this.terminal.writeln('\x1b[36mПодключение к контейнеру...\x1b[0m\r\n');

        if (this.ws) {
            this.ws.close();
        }

        const ws = new WebSocket(wsUrl);
        this.ws = ws;

        ws.onopen = () => {
            if (this.ws !== ws) return;
            // Отправляем конфигурацию
            ws.send(JSON.stringify({
                container_id: containerId,
            }));
            this.terminal.writeln('\x1b[32mПодключено!\x1b[0m\r\n');
            this.sendResize();
        };

        ws.onmessage = (event) => {
            if (this.ws !== ws) return;
            this.terminal.write(event.data);
        };

        ws.onerror = (error) => {
            if (this.ws !== ws) return;
            this.terminal.writeln('\r\n\x1b[31mОшибка подключения WebSocket\x1b[0m');
        };

        ws.onclose = () => {
            if (this.ws !== ws) return;
            this.terminal.writeln('\r\n\x1b[33mСоединение закрыто\x1b[0m');
            this.ws = null;
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
