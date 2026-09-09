<!DOCTYPE html>
<html class="dark" lang="es">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <title>@yield('title', 'ATIT • Monitoreo de Infraestructura - Valle Seco')</title>
    
    <!-- Favicons & App Icons -->
    <link rel="apple-touch-icon" sizes="57x57" href="{{ asset('img/ico/apple-icon-57x57.png') }}">
    <link rel="apple-touch-icon" sizes="60x60" href="{{ asset('img/ico/apple-icon-60x60.png') }}">
    <link rel="apple-touch-icon" sizes="72x72" href="{{ asset('img/ico/apple-icon-72x72.png') }}">
    <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('img/ico/apple-icon-76x76.png') }}">
    <link rel="apple-touch-icon" sizes="114x114" href="{{ asset('img/ico/apple-icon-114x114.png') }}">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('img/ico/apple-icon-120x120.png') }}">
    <link rel="apple-touch-icon" sizes="144x144" href="{{ asset('img/ico/apple-icon-144x144.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('img/ico/apple-icon-152x152.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/ico/apple-icon-180x180.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('img/ico/android-icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/ico/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('img/ico/favicon-96x96.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('img/ico/favicon-16x16.png') }}">
    <link rel="shortcut icon" href="{{ asset('img/ico/favicon.ico') }}" type="image/x-icon">
    <link rel="manifest" href="{{ asset('img/ico/manifest.json') }}">
    <meta name="msapplication-TileColor" content="#051424">
    <meta name="msapplication-TileImage" content="{{ asset('img/ico/ms-icon-144x144.png') }}">
    <meta name="theme-color" content="#051424">
    
    <!-- Activos Locales 100% Autónomos (Cero CDNs Externos) -->
    <link rel="stylesheet" href="{{ asset('vendor/fonts/fonts.css') }}"/>
    <link rel="stylesheet" href="{{ asset('vendor/material-symbols/material-symbols.css') }}"/>
    <script src="{{ asset('vendor/tailwindcss/tailwind.min.js') }}"></script>
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>

    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "obsidian-bg": "#051424",
                        "obsidian-card": "#0b1726",
                        "obsidian-panel": "#101f33",
                        "obsidian-border": "#1c2e47",
                        "obsidian-cyan": "#22d3ee",
                        "obsidian-purple": "#8b5cf6",
                        "obsidian-green": "#10b981",
                        "obsidian-red": "#ef4444",
                        "obsidian-amber": "#f59e0b",
                        "obsidian-text": "#d4e4fa",
                        "obsidian-muted": "#8295b0",
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        };
    </script>
    
    <style>
        body {
            background-color: #051424;
            color: #d4e4fa;
            font-family: 'Inter', sans-serif;
        }
        .glass-panel {
            background: rgba(11, 23, 38, 0.75);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(28, 46, 71, 0.8);
        }
        .glass-card {
            background: rgba(16, 31, 51, 0.65);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(39, 63, 94, 0.6);
            transition: all 0.25s ease-in-out;
        }
        .glass-card:hover {
            border-color: rgba(34, 211, 238, 0.5);
            box-shadow: 0 0 20px rgba(34, 211, 238, 0.15);
            transform: translateY(-2px);
        }
        .glow-cyan {
            box-shadow: 0 0 15px rgba(34, 211, 238, 0.35);
        }
        .glow-green {
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.35);
        }
        .glow-red {
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.35);
        }
        .glow-amber {
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.35);
        }
        .pulse-dot {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .4; }
        }
        /* Custom Scrollbar for sleek Obsidian panels */
        .custom-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scroll::-webkit-scrollbar-track {
            background: rgba(2, 6, 23, 0.4);
        }
        .custom-scroll::-webkit-scrollbar-thumb {
            background: rgba(34, 211, 238, 0.25);
            border-radius: 4px;
        }
        .custom-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(34, 211, 238, 0.6);
        }
        /* Floating Tooltip HUD */
        #tech-tooltip {
            position: fixed;
            z-index: 99999;
            pointer-events: none;
            opacity: 0;
            transform: scale(0.95) translateY(5px);
            transition: opacity 0.18s cubic-bezier(0.16, 1, 0.3, 1), transform 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        }
        #tech-tooltip.show {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
        #tech-tooltip a, #tech-tooltip button {
            pointer-events: auto;
        }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen flex flex-col bg-obsidian-bg text-obsidian-text antialiased selection:bg-obsidian-cyan selection:text-black">
    @yield('content')

    @auth
    <!-- ========================================================================= -->
    <!-- VENTANA MODAL: SESIÓN DE USUARIO Y CIERRE DE SESIÓN -->
    <!-- ========================================================================= -->
    <div id="user-session-modal" class="fixed inset-0 z-50 hidden transition-all duration-300">
        <!-- Backdrop oscuro interactivo -->
        <div onclick="closeUserSessionModal()" class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity cursor-pointer"></div>

        <!-- Contenedor del Modal Centrado -->
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto pointer-events-none">
            <div class="w-full max-w-sm glass-panel rounded-2xl border border-obsidian-border bg-[#071629]/95 p-6 shadow-2xl relative text-center space-y-5 animate-in fade-in zoom-in-95 duration-200 pointer-events-auto border-cyan-500/20">
                <!-- Botón Cerrar en esquina -->
                <button type="button" onclick="closeUserSessionModal()" class="absolute top-4 right-4 text-obsidian-muted hover:text-white transition text-xl leading-none cursor-pointer" title="Cerrar ventana">
                    &times;
                </button>

                <!-- Avatar & Perfil del Usuario -->
                <div class="flex flex-col items-center pt-1">
                    <div class="relative w-20 h-20 rounded-full bg-gradient-to-tr from-obsidian-cyan/20 to-obsidian-purple/30 border-2 border-obsidian-cyan/70 text-obsidian-cyan font-bold flex items-center justify-center text-2xl shadow-xl overflow-hidden mb-3 ring-4 ring-cyan-500/10">
                        @if(Auth::user()->avatar_url)
                            <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                        @endif
                        <span class="absolute bottom-1 right-1 w-3.5 h-3.5 rounded-full bg-emerald-400 border-2 border-[#071629]" title="Sesión activa"></span>
                    </div>
                    <h3 class="text-base font-bold text-white tracking-tight">{{ Auth::user()->name }}</h3>
                    <p class="text-xs font-mono text-obsidian-muted">{{ Auth::user()->email ?: Auth::user()->username }}</p>
                    <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-mono font-semibold {{ Auth::user()->role === 'admin' ? 'bg-red-950/60 text-red-400 border border-red-500/40' : 'bg-cyan-950/60 text-cyan-300 border border-cyan-500/40' }}">
                        <span class="material-symbols-outlined text-[12px]">{{ Auth::user()->role === 'admin' ? 'shield_person' : 'person' }}</span>
                        <span>{{ Auth::user()->role === 'admin' ? 'ADMINISTRADOR' : 'OPERADOR CORPORATIVO' }}</span>
                    </div>
                </div>

                <!-- Detalles de Seguridad de la Sesión -->
                <div class="bg-obsidian-panel/80 rounded-xl p-3 border border-obsidian-border/70 text-left font-mono text-[11px] space-y-1.5 text-obsidian-muted">
                    <div class="flex justify-between items-center">
                        <span>IP de Conexión:</span>
                        <span class="text-white font-semibold">{{ request()->ip() }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Cierre Automático:</span>
                        <span class="text-obsidian-cyan font-semibold">5 min inactividad</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Estado:</span>
                        <span class="text-emerald-400 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse-dot"></span>
                            Activo
                        </span>
                    </div>
                </div>

                <!-- Opciones / Botones de Acción -->
                <div class="space-y-2 pt-1">
                    <!-- Administrar Perfil -->
                    <a href="{{ route('admin.profile.show') }}" class="w-full py-2.5 px-4 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-text hover:bg-obsidian-cyan/20 hover:text-white hover:border-obsidian-cyan/50 font-mono text-xs font-semibold transition flex items-center justify-center gap-2 group">
                        <span class="material-symbols-outlined text-base group-hover:scale-110 transition-transform text-obsidian-cyan">account_circle</span>
                        <span>Ver y Editar Mi Perfil</span>
                    </a>

                    <!-- Botón Cerrar Sesión (Destacado) -->
                    <form action="{{ route('logout') }}" method="POST" class="w-full m-0">
                        @csrf
                        <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-red-950/40 border border-red-500/60 text-red-400 hover:bg-red-600 hover:text-white font-mono text-xs font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-red-500/10 cursor-pointer">
                            <span class="material-symbols-outlined text-base">logout</span>
                            <span>Cerrar Sesión</span>
                        </button>
                    </form>

                    <!-- Cancelar -->
                    <button type="button" onclick="closeUserSessionModal()" class="w-full py-1.5 text-center text-xs font-mono text-obsidian-muted hover:text-white transition cursor-pointer">
                        Permanecer en el Sistema
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- VENTANA MODAL: ADVERTENCIA DE INACTIVIDAD (5 MINUTOS) -->
    <!-- ========================================================================= -->
    <div id="inactivity-warning-modal" class="fixed inset-0 z-[60] hidden transition-all duration-300">
        <div class="fixed inset-0 bg-black/85 backdrop-blur-md"></div>
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="w-full max-w-sm glass-panel rounded-2xl border border-amber-500/50 bg-[#0d1829]/95 p-6 shadow-2xl relative text-center space-y-4">
                <div class="w-14 h-14 mx-auto rounded-full bg-amber-500/20 border border-amber-500/60 flex items-center justify-center text-amber-400 text-3xl shadow-lg shadow-amber-500/20 animate-bounce">
                    <span class="material-symbols-outlined text-3xl">timer</span>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-white tracking-tight">¿Sigues ahí?</h3>
                    <p class="text-xs text-obsidian-muted">
                        Por motivos de seguridad, tu sesión se cerrará automáticamente en:
                    </p>
                </div>

                <!-- Contador regresivo grande -->
                <div class="py-2">
                    <span id="inactivity-countdown-number" class="font-mono text-4xl font-extrabold text-amber-400 glow-amber">30</span>
                    <span class="block text-[10px] font-mono text-obsidian-muted uppercase mt-1">Segundos restantes</span>
                </div>

                <div class="space-y-2 pt-2">
                    <button type="button" onclick="resetInactivityTimer(true)" class="w-full py-2.5 px-4 rounded-xl bg-obsidian-cyan text-black font-mono text-xs font-bold transition hover:bg-cyan-300 flex items-center justify-center gap-2 shadow-lg shadow-cyan-500/20 cursor-pointer">
                        <span class="material-symbols-outlined text-base">refresh</span>
                        <span>Mantener Sesión Activa</span>
                    </button>
                    
                    <a href="{{ route('logout') }}?reason=inactivity" class="w-full py-2 px-4 rounded-xl text-red-400 hover:text-red-300 font-mono text-xs transition flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">logout</span>
                        <span>Cerrar Sesión Ahora</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MOTOR DE SESIÓN & CONTROL DE INACTIVIDAD (5 MINUTOS) -->
    <!-- ========================================================================= -->
    <script>
        function openUserSessionModal() {
            const modal = document.getElementById('user-session-modal');
            if (modal) modal.classList.remove('hidden');
        }

        function closeUserSessionModal() {
            const modal = document.getElementById('user-session-modal');
            if (modal) modal.classList.add('hidden');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeUserSessionModal();
            }
        });

        (function() {
            const INACTIVITY_LIMIT_MS = 5 * 60 * 1000; // 5 minutos (300,000 ms)
            const WARNING_DURATION_MS = 30 * 1000;      // 30 segundos de aviso
            const WARNING_THRESHOLD_MS = INACTIVITY_LIMIT_MS - WARNING_DURATION_MS; // 4 min 30 s
            const LOGOUT_URL = "{{ route('logout') }}?reason=inactivity";

            let lastActivity = Date.now();
            try {
                const stored = localStorage.getItem('portal_last_activity');
                if (stored && !isNaN(parseInt(stored))) {
                    const parsed = parseInt(stored);
                    if (Date.now() - parsed < INACTIVITY_LIMIT_MS) {
                        lastActivity = parsed;
                    }
                }
            } catch (e) {}

            let warningModal = null;
            let countdownElement = null;
            let isWarningShown = false;

            function recordActivity() {
                const now = Date.now();
                lastActivity = now;
                try {
                    localStorage.setItem('portal_last_activity', now.toString());
                } catch (e) {}

                if (isWarningShown) {
                    hideWarning();
                }
            }

            function showWarning(secondsLeft) {
                if (!warningModal) warningModal = document.getElementById('inactivity-warning-modal');
                if (!countdownElement) countdownElement = document.getElementById('inactivity-countdown-number');
                if (warningModal) warningModal.classList.remove('hidden');
                if (countdownElement) countdownElement.innerText = Math.max(1, Math.ceil(secondsLeft));
                isWarningShown = true;
            }

            function hideWarning() {
                if (!warningModal) warningModal = document.getElementById('inactivity-warning-modal');
                if (warningModal) warningModal.classList.add('hidden');
                isWarningShown = false;
            }

            window.resetInactivityTimer = function(explicitClick) {
                recordActivity();
                if (explicitClick) {
                    hideWarning();
                }
            };

            function performAutoLogout() {
                try {
                    localStorage.setItem('portal_force_logout', Date.now().toString());
                } catch (e) {}
                window.location.href = LOGOUT_URL;
            }

            // Chequeo periódico cada 1 segundo
            setInterval(function() {
                const now = Date.now();
                try {
                    const stored = localStorage.getItem('portal_last_activity');
                    if (stored) {
                        const parsed = parseInt(stored);
                        if (parsed > lastActivity) lastActivity = parsed;
                    }
                } catch (e) {}

                const idleMs = now - lastActivity;

                if (idleMs >= INACTIVITY_LIMIT_MS) {
                    performAutoLogout();
                } else if (idleMs >= WARNING_THRESHOLD_MS) {
                    const remainingSecs = (INACTIVITY_LIMIT_MS - idleMs) / 1000;
                    showWarning(remainingSecs);
                } else if (isWarningShown) {
                    hideWarning();
                }
            }, 1000);

            // Escuchar eventos de interacción humana
            let throttleTimer = null;
            const activityEvents = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'];
            
            function handleUserActivity() {
                if (!throttleTimer) {
                    throttleTimer = setTimeout(function() {
                        recordActivity();
                        throttleTimer = null;
                    }, 1000);
                }
            }

            activityEvents.forEach(function(evt) {
                window.addEventListener(evt, handleUserActivity, { passive: true });
            });

            // Sincronización entre pestañas del navegador
            window.addEventListener('storage', function(e) {
                if (e.key === 'portal_last_activity' && e.newValue) {
                    const parsed = parseInt(e.newValue);
                    if (parsed > lastActivity) {
                        lastActivity = parsed;
                        if (isWarningShown) hideWarning();
                    }
                } else if (e.key === 'portal_force_logout') {
                    window.location.href = LOGOUT_URL;
                }
            });
        })();
    </script>
    @endauth

    @stack('scripts')
</body>
</html>
