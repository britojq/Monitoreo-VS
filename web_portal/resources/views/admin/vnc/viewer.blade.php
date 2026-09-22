<!DOCTYPE html>
<html lang="es" class="dark h-full bg-[#030712]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VNC: {{ $name }} ({{ $ip }}) - {{ $site }} | Monitoreo ATIT</title>
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
    </style>
</head>
<body class="h-full flex flex-col select-none font-sans text-white">

    <!-- TOP CONTROL BAR -->
    <header class="glass-header h-13 px-4 py-2 flex items-center justify-between shrink-0 z-30">
        <!-- Izquierda: Regresar e Información de la Estación -->
        <div class="flex items-center space-x-3 min-w-0">
            <button onclick="handleExit()" 
                    class="p-1.5 rounded-lg bg-obsidian-panel/80 hover:bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white transition flex items-center gap-1 text-xs cursor-pointer"
                    title="Salir de la consola remota">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                <span class="hidden sm:inline font-mono">Volver</span>
            </button>

            <div class="h-5 w-px bg-obsidian-border/60"></div>

            <div class="flex items-center space-x-2 min-w-0">
                <div class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-pulse shadow-sm shadow-cyan-400"></div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="text-xs font-bold text-white font-mono truncate tracking-wide">
                            {{ $name }}
                        </h1>
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase bg-cyan-950/80 border border-cyan-500/40 text-cyan-300">
                            {{ $ip }}:5900
                        </span>
                        <span class="hidden md:inline px-1.5 py-0.5 rounded text-[9px] font-mono uppercase bg-purple-950/60 border border-purple-500/40 text-purple-300 truncate max-w-[200px]">
                            {{ $site }}
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

            <!-- Botón de Reconexión -->
            <button onclick="reconnectIframe()" 
                    class="px-2.5 py-1 rounded-lg bg-obsidian-panel hover:bg-obsidian-panel/80 border border-obsidian-border hover:border-cyan-500/40 text-obsidian-muted hover:text-cyan-300 text-xs font-mono transition flex items-center gap-1 cursor-pointer"
                    title="Reiniciar conexión WebSocket / VNC">
                <span class="material-symbols-outlined text-sm">refresh</span>
                <span class="hidden sm:inline text-[11px]">Reconectar</span>
            </button>

            <!-- Botón Pantalla Completa -->
            <button onclick="toggleFullscreen()" 
                    class="px-2.5 py-1 rounded-lg bg-obsidian-panel hover:bg-obsidian-panel/80 border border-obsidian-border hover:border-cyan-500/40 text-obsidian-muted hover:text-cyan-300 text-xs font-mono transition flex items-center gap-1 cursor-pointer"
                    title="Pantalla completa">
                <span class="material-symbols-outlined text-sm" id="fs-icon">fullscreen</span>
                <span class="hidden sm:inline text-[11px]">Pantalla Completa</span>
            </button>

            <!-- Botón Cerrar -->
            <button onclick="handleExit()" 
                    class="px-2.5 py-1 rounded-lg bg-red-950/40 hover:bg-red-900/60 border border-red-500/40 text-red-300 text-xs font-mono transition flex items-center gap-1 cursor-pointer"
                    title="Cerrar sesión">
                <span class="material-symbols-outlined text-sm">close</span>
                <span class="hidden sm:inline text-[11px]">Cerrar</span>
            </button>
        </div>
    </header>

    <!-- AREA DEL VISOR VNC (IFRAME CON NOVNC) -->
    <main class="flex-1 w-full h-full relative bg-black overflow-hidden" id="vnc-container">
        <iframe id="vnc-frame" 
                src="{{ $novncSrc }}" 
                class="w-full h-full border-0 block" 
                allowfullscreen 
                tabindex="0">
        </iframe>
    </main>

    <script>
        function reconnectIframe() {
            const frame = document.getElementById('vnc-frame');
            if (frame) {
                frame.src = frame.src;
            }
        }

        function toggleFullscreen() {
            const el = document.getElementById('vnc-container');
            const icon = document.getElementById('fs-icon');
            if (!document.fullscreenElement) {
                if (el.requestFullscreen) {
                    el.requestFullscreen();
                } else if (el.webkitRequestFullscreen) {
                    el.webkitRequestFullscreen();
                }
                if (icon) icon.innerText = 'fullscreen_exit';
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
                if (icon) icon.innerText = 'fullscreen';
            }
        }

        function handleExit() {
            if (window.opener) {
                window.close();
            } else {
                window.location.href = "{{ route('home') }}";
            }
        }
    </script>
</body>
</html>
