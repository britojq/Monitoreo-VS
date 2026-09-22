<!DOCTYPE html>
<html lang="es" class="dark h-full bg-[#030712]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSH: {{ $name }} ({{ $ip }}:{{ $port }}) - {{ $site }} | Monitoreo ATIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="https://cdn.tailwindcss.com"></script>
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
                            emerald: '#10b981',
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
        #ssh-frame-container {
            width: 100%;
            height: calc(100% - 52px);
            background-color: #000000;
        }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: #000000;
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
                    title="Cerrar consola SSH">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                <span class="hidden sm:inline font-mono">Volver</span>
            </button>

            <div class="h-5 w-px bg-obsidian-border/60"></div>

            <div class="flex items-center space-x-2 min-w-0">
                <div id="connection-status-dot" class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse shadow-sm shadow-emerald-400"></div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="text-xs font-bold text-white font-mono truncate tracking-wide">
                            {{ $name }}
                        </h1>
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase bg-emerald-950/80 border border-emerald-500/40 text-emerald-300">
                            SSH : {{ $ip }}:{{ $port }}
                        </span>
                        <span class="hidden md:inline px-1.5 py-0.5 rounded text-[9px] font-mono uppercase bg-purple-950/60 border border-purple-500/40 text-purple-300 truncate max-w-[200px]">
                            {{ $site }}
                        </span>
                        <span id="connection-status-text" class="text-[9px] font-mono font-bold text-emerald-400">
                            TERMINAL SSH ACTIVA
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Derecha: Acciones de Consola -->
        <div class="flex items-center space-x-2 shrink-0">
            <button onclick="reloadSsh()" 
                    class="p-1.5 rounded-lg bg-obsidian-panel hover:bg-obsidian-panel/80 border border-obsidian-border hover:border-emerald-500/40 text-obsidian-muted hover:text-white transition cursor-pointer"
                    title="Reiniciar Conexión">
                <span class="material-symbols-outlined text-sm">refresh</span>
            </button>
            <button onclick="toggleFullscreen()" 
                    id="fullscreen-btn"
                    class="p-1.5 rounded-lg bg-obsidian-panel hover:bg-obsidian-panel/80 border border-obsidian-border hover:border-cyan-500/40 text-obsidian-muted hover:text-white transition cursor-pointer"
                    title="Pantalla Completa">
                <span class="material-symbols-outlined text-sm" id="fullscreen-icon">fullscreen</span>
            </button>
        </div>
    </header>

    <!-- AREA DE TERMINAL SSH -->
    <main id="ssh-frame-container" class="flex-1 w-full bg-black overflow-hidden relative">
        <iframe id="ssh-frame" src="/wssh/?hostname={{ urlencode($ip) }}&port={{ urlencode($port) }}" allow="clipboard-read; clipboard-write"></iframe>
    </main>

    <script>
        function handleExit() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.close();
            }
        }

        const frame = document.getElementById('ssh-frame');
        if (frame) {
            frame.addEventListener('load', function() {
                try {
                    const doc = frame.contentDocument || frame.contentWindow.document;
                    if (doc) {
                        const hInput = doc.getElementById('hostname');
                        const pInput = doc.getElementById('port');
                        if (hInput && !hInput.value) hInput.value = "{{ $ip }}";
                        if (pInput && !pInput.value) pInput.value = "{{ $port }}";
                        const uInput = doc.getElementById('username');
                        if (uInput) uInput.focus();
                    }
                } catch (e) {
                    console.warn('Same-origin iframe helper:', e);
                }
            });
        }

        function reloadSsh() {
            const frame = document.getElementById('ssh-frame');
            if (frame) {
                frame.src = frame.src;
            }
        }

        function toggleFullscreen() {
            const btn = document.getElementById('fullscreen-btn');
            const icon = document.getElementById('fullscreen-icon');
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().then(() => {
                    if (icon) icon.innerText = 'fullscreen_exit';
                }).catch(err => {
                    console.error('Error pantalla completa: ', err);
                });
            } else {
                document.exitFullscreen().then(() => {
                    if (icon) icon.innerText = 'fullscreen';
                }).catch(err => {
                    console.error('Error saliendo pantalla completa: ', err);
                });
            }
        }
    </script>
</body>
</html>
