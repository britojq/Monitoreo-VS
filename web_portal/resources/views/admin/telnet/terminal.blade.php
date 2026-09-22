<!DOCTYPE html>
<html lang="es" class="dark h-full bg-[#030712]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TELNET: {{ $name }} ({{ $ip }}:{{ $port }}) - {{ $site }} | Monitoreo ATIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="/vendor/xterm/xterm.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/vendor/xterm/xterm.min.js"></script>
    <script src="/vendor/xterm/xterm-addon-fit.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        obsidian: {
                            bg: '#030712',
                            card: '#080e1e',
                            panel: '#0d1527',
                            border: '#1e293b',
                            cyan: '#06b6d4',
                            purple: '#a855f7',
                            muted: '#94a3b8'
                        }
                    },
                    fontFamily: {
                        mono: ['"JetBrains Mono"', 'monospace'],
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            background-color: #030712;
        }
        .glass-header {
            background: rgba(8, 14, 30, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(30, 41, 59, 0.8);
        }
        #terminal-container {
            width: 100%;
            height: calc(100% - 52px);
            padding: 8px 12px;
            box-sizing: border-box;
            background-color: #050b14;
        }
        .xterm {
            height: 100%;
        }
        .xterm-viewport {
            overflow-y: auto !important;
        }
    </style>
</head>
<body class="h-full flex flex-col select-none font-sans text-white">

    <!-- TOP CONTROL BAR -->
    <header class="glass-header h-13 px-4 py-2 flex items-center justify-between shrink-0 z-30">
        <!-- Izquierda: Regresar e Información del Dispositivo -->
        <div class="flex items-center space-x-3 min-w-0">
            <button onclick="handleExit()" 
                    class="p-1.5 rounded-lg bg-obsidian-panel/80 hover:bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white transition flex items-center gap-1 text-xs cursor-pointer"
                    title="Cerrar consola Telnet">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                <span class="hidden sm:inline font-mono">Volver</span>
            </button>

            <div class="h-5 w-px bg-obsidian-border/60"></div>

            <div class="flex items-center space-x-2 min-w-0">
                <div id="connection-status-dot" class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-pulse shadow-sm shadow-cyan-400"></div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="text-xs font-bold text-white font-mono truncate tracking-wide">
                            {{ $name }}
                        </h1>
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase bg-cyan-950/80 border border-cyan-500/40 text-cyan-300">
                            {{ $ip }}:{{ $port }}
                        </span>
                        <span class="hidden md:inline px-1.5 py-0.5 rounded text-[9px] font-mono uppercase bg-purple-950/60 border border-purple-500/40 text-purple-300 truncate max-w-[200px]">
                            {{ $site }}
                        </span>
                        <span id="connection-status-text" class="text-[9px] font-mono font-bold text-cyan-400">
                            CONECTANDO...
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Derecha: Estado de Conexión y Herramientas -->
        <div class="flex items-center space-x-2 shrink-0">
            <!-- Indicador de Operador -->
            <div class="hidden lg:flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-obsidian-panel/60 border border-obsidian-border text-[10px] font-mono text-obsidian-muted">
                <span class="material-symbols-outlined text-xs text-obsidian-cyan">shield_person</span>
                <span>{{ Auth::user()->name }}</span>
                <span class="text-[9px] uppercase font-bold text-obsidian-cyan">({{ Auth::user()->role }})</span>
            </div>

            <!-- Botón Limpiar Pantalla -->
            <button onclick="clearTerminal()" 
                    class="px-2.5 py-1 rounded-lg bg-obsidian-panel hover:bg-obsidian-panel/80 border border-obsidian-border hover:border-cyan-500/40 text-obsidian-muted hover:text-cyan-300 text-xs font-mono transition flex items-center gap-1 cursor-pointer"
                    title="Limpiar pantalla (Ctrl+L)">
                <span class="material-symbols-outlined text-sm">cleaning_services</span>
                <span class="hidden sm:inline text-[11px]">Limpiar</span>
            </button>

            <!-- Botón de Reconexión -->
            <button onclick="reconnectTerminal()" 
                    class="px-2.5 py-1 rounded-lg bg-obsidian-panel hover:bg-obsidian-panel/80 border border-obsidian-border hover:border-cyan-500/40 text-obsidian-muted hover:text-cyan-300 text-xs font-mono transition flex items-center gap-1 cursor-pointer"
                    title="Reiniciar conexión Telnet">
                <span class="material-symbols-outlined text-sm">refresh</span>
                <span class="hidden sm:inline text-[11px]">Reconectar</span>
            </button>

            <!-- Pantalla Completa -->
            <button onclick="toggleFullScreen()" 
                    id="fullscreen-btn"
                    class="p-1.5 rounded-lg bg-obsidian-panel hover:bg-obsidian-panel/80 border border-obsidian-border hover:border-cyan-500/40 text-obsidian-muted hover:text-white transition cursor-pointer"
                    title="Pantalla Completa">
                <span class="material-symbols-outlined text-sm" id="fullscreen-icon">fullscreen</span>
            </button>
        </div>
    </header>

    <!-- AREA DE TERMINAL XTERM -->
    <main class="flex-1 w-full bg-[#050b14] overflow-hidden relative">
        <div id="terminal-container"></div>
    </main>

    <script>
        const token = @json($token);
        const targetIp = @json($ip);
        const targetPort = @json($port);
        const targetName = @json($name);

        let term = null;
        let fitAddon = null;
        let telnetClient = null;

        class TelnetClient {
            constructor(wsUrl, termInstance) {
                this.wsUrl = wsUrl;
                this.term = termInstance;
                this.ws = null;
                this.connected = false;
            }

            connect() {
                this.term.write('\r\n\x1b[1;36m[ATIT-TELNET]\x1b[0m Iniciando conexión a ' + targetName + ' (' + targetIp + ':' + targetPort + ')...\r\n');
                try {
                    this.ws = new WebSocket(this.wsUrl);
                    this.ws.binaryType = 'arraybuffer';

                    this.ws.onopen = () => {
                        this.connected = true;
                        this.term.write('\x1b[1;32m[ATIT-TELNET]\x1b[0m Túnel WebSocket establecido. Negociando terminal...\r\n\r\n');
                        const dot = document.getElementById('connection-status-dot');
                        const txt = document.getElementById('connection-status-text');
                        if (dot) dot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-sm shadow-emerald-400 animate-pulse';
                        if (txt) {
                            txt.innerText = 'CONECTADO';
                            txt.className = 'text-[9px] font-mono font-bold text-emerald-400';
                        }
                    };

                    this.ws.onmessage = (event) => {
                        const buf = new Uint8Array(event.data);
                        this.handleIncomingBytes(buf);
                    };

                    this.ws.onclose = (e) => {
                        this.connected = false;
                        this.term.write('\r\n\x1b[1;31m[ATIT-TELNET]\x1b[0m Conexión cerrada por el host remoto o el proxy.\r\n');
                        const dot = document.getElementById('connection-status-dot');
                        const txt = document.getElementById('connection-status-text');
                        if (dot) dot.className = 'w-2.5 h-2.5 rounded-full bg-red-500';
                        if (txt) {
                            txt.innerText = 'DESCONECTADO';
                            txt.className = 'text-[9px] font-mono font-bold text-red-400';
                        }
                    };

                    this.ws.onerror = (err) => {
                        this.term.write('\r\n\x1b[1;31m[ATIT-TELNET]\x1b[0m Error de comunicación en el socket.\r\n');
                    };
                } catch (err) {
                    this.term.write('\r\n\x1b[1;31m[ATIT-TELNET] Error:\x1b[0m ' + err.message + '\r\n');
                }
            }

            handleIncomingBytes(buf) {
                const out = [];
                const replies = [];
                let i = 0;
                const IAC = 255;
                const DONT = 254;
                const DO = 253;
                const WONT = 252;
                const WILL = 251;
                const SB = 250;
                const SE = 240;

                while (i < buf.length) {
                    const b = buf[i];
                    if (b === IAC && i + 1 < buf.length) {
                        const cmd = buf[i + 1];
                        if (cmd === WILL || cmd === WONT || cmd === DO || cmd === DONT) {
                            if (i + 2 < buf.length) {
                                const opt = buf[i + 2];
                                if (cmd === WILL) {
                                    if (opt === 1 || opt === 3) {
                                        replies.push(IAC, DO, opt);
                                    } else {
                                        replies.push(IAC, DONT, opt);
                                    }
                                } else if (cmd === DO) {
                                    if (opt === 3) { // SGA
                                        replies.push(IAC, WILL, opt);
                                    } else {
                                        replies.push(IAC, WONT, opt);
                                    }
                                }
                                i += 3;
                                continue;
                            }
                        } else if (cmd === SB) {
                            let j = i + 2;
                            while (j < buf.length && !(buf[j] === IAC && buf[j + 1] === SE)) {
                                j++;
                            }
                            i = j + 2;
                            continue;
                        } else if (cmd === IAC) {
                            out.push(IAC);
                            i += 2;
                            continue;
                        }
                        i += 2;
                        continue;
                    } else {
                        out.push(b);
                        i++;
                    }
                }

                if (replies.length > 0 && this.ws && this.ws.readyState === WebSocket.OPEN) {
                    this.ws.send(new Uint8Array(replies));
                }

                if (out.length > 0) {
                    const text = new TextDecoder('latin1').decode(new Uint8Array(out));
                    this.term.write(text);
                }
            }

            send(data) {
                if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                    this.ws.send(new TextEncoder().encode(data));
                }
            }

            disconnect() {
                if (this.ws) {
                    this.ws.close();
                }
            }
        }

        function initTerminal() {
            const container = document.getElementById('terminal-container');
            container.innerHTML = '';

            term = new Terminal({
                cursorBlink: true,
                cursorStyle: 'block',
                fontSize: 14,
                fontFamily: '"JetBrains Mono", monospace',
                theme: {
                    background: '#050b14',
                    foreground: '#e2e8f0',
                    cursor: '#06b6d4',
                    cursorAccent: '#050b14',
                    selectionBackground: 'rgba(6, 182, 212, 0.3)',
                    black: '#0f172a',
                    red: '#f87171',
                    green: '#4ade80',
                    yellow: '#facc15',
                    blue: '#38bdf8',
                    magenta: '#c084fc',
                    cyan: '#22d3ee',
                    white: '#f1f5f9',
                    brightBlack: '#475569',
                    brightRed: '#ef4444',
                    brightGreen: '#22c55e',
                    brightYellow: '#eab308',
                    brightBlue: '#0ea5e9',
                    brightMagenta: '#a855f7',
                    brightCyan: '#06b6d4',
                    brightWhite: '#ffffff'
                }
            });

            fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
            term.open(container);
            fitAddon.fit();

            const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const wsUrl = wsProtocol + '//' + window.location.host + '/websockify?token=' + encodeURIComponent(token);

            telnetClient = new TelnetClient(wsUrl, term);
            telnetClient.connect();

            term.onData(data => {
                telnetClient.send(data);
            });

            window.addEventListener('resize', () => {
                if (fitAddon) fitAddon.fit();
            });
        }

        function clearTerminal() {
            if (term) term.clear();
        }

        function reconnectTerminal() {
            if (telnetClient) {
                telnetClient.disconnect();
            }
            initTerminal();
        }

        function handleExit() {
            if (telnetClient) {
                telnetClient.disconnect();
            }
            if (window.opener && !window.opener.closed) {
                window.close();
            } else {
                window.location.href = '/';
            }
        }

        function toggleFullScreen() {
            const icon = document.getElementById('fullscreen-icon');
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().then(() => {
                    if (icon) icon.innerText = 'fullscreen_exit';
                    setTimeout(() => fitAddon && fitAddon.fit(), 200);
                }).catch(err => console.error(err));
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen().then(() => {
                        if (icon) icon.innerText = 'fullscreen';
                        setTimeout(() => fitAddon && fitAddon.fit(), 200);
                    }).catch(err => console.error(err));
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initTerminal();
        });
    </script>
</body>
</html>
