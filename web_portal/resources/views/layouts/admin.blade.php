@extends('layouts.app')

@section('content')
<!-- OVERLAY BACKDROP OSCURO PARA SIDEBAR -->
<div id="sidebar-backdrop" onclick="toggleAdminSidebar(false)" class="fixed inset-0 bg-black/70 backdrop-blur-xs z-40 hidden transition-opacity duration-300"></div>

<div class="min-h-screen flex relative">
    <!-- SIDEBAR DE ADMINISTRACIÓN RETRÁCTIL (AUTO-ESCONDIDO) -->
    <aside id="admin-sidebar" class="w-64 sm:w-72 glass-panel border-r border-obsidian-border flex flex-col justify-between shrink-0 z-50 fixed inset-y-0 left-0 transform -translate-x-full transition-transform duration-300 ease-in-out shadow-2xl bg-[#07172b]/98">
        <div>
            <!-- CABECERA DE SIDEBAR -->
            <div class="h-14 px-5 flex items-center justify-between border-b border-obsidian-border">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-obsidian-cyan/20 to-obsidian-purple/30 border border-obsidian-cyan/40 flex items-center justify-center text-obsidian-cyan glow-cyan p-1 shadow-lg shadow-cyan-500/20">
                        <img src="{{ asset('img/logo.png') }}" alt="Logo Corporativo" class="w-full h-full object-contain filter drop-shadow-[0_0_6px_rgba(34,211,238,0.6)]">
                    </div>
                    <div>
                        <h2 class="text-xs sm:text-sm font-bold text-white tracking-tight">PANEL DE CONTROL</h2>
                        <p class="text-[10px] font-mono text-obsidian-cyan">ATIT VALLE SECO</p>
                    </div>
                </div>
                <!-- BOTÓN CERRAR SIDEBAR -->
                <button type="button" onclick="toggleAdminSidebar(false)" class="p-1 rounded-lg text-obsidian-muted hover:text-white hover:bg-obsidian-panel transition leading-none text-xl" title="Ocultar Menú">
                    &times;
                </button>
            </div>

            <!-- ENLACES DE NAVEGACIÓN -->
            <nav class="p-4 space-y-1.5 font-mono text-xs">
                <!-- DASHBOARD -->
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold transition {{ request()->routeIs('admin.dashboard') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">dashboard</span>
                    Dashboard
                </a>

                <!-- MI PERFIL (Para Administradores y Operadores) -->
                <a href="{{ route('admin.profile.show') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold transition {{ request()->routeIs('admin.profile.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">account_circle</span>
                    Mi Perfil
                </a>

                @if(auth()->user()->isAdmin())
                <!-- GESTIÓN DE USUARIOS (Solo Administrador) -->
                <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold transition {{ request()->routeIs('admin.users.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">group</span>
                    Usuarios
                </a>

                <!-- BANEOS & SEGURIDAD (Solo Administrador) -->
                <a href="{{ route('admin.bans.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold transition {{ request()->routeIs('admin.bans.*') ? 'bg-red-500 text-white shadow-lg shadow-red-500/20' : 'text-red-400 hover:text-white hover:bg-red-950/40' }}">
                    <span class="material-symbols-outlined text-lg">gavel</span>
                    Baneos & Seguridad
                </a>

                <!-- PLANTILLAS DE MENSAJERÍA BOT (Solo Administrador) -->
                <a href="{{ route('admin.bot.templates.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold transition {{ request()->routeIs('admin.bot.templates.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">edit_note</span>
                    Plantillas Bot
                </a>

                <!-- COMANDOS DEL BOT (Solo Administrador) -->
                <a href="{{ route('admin.bot.commands.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold transition {{ request()->routeIs('admin.bot.commands.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">terminal</span>
                    Comandos Bot
                </a>

                <!-- CONFIGURACIÓN AVANZADA (Solo Administrador) -->
                <a href="{{ route('admin.settings.advanced') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold transition {{ request()->routeIs('admin.settings.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">tune</span>
                    Configuración Avanzada
                </a>
                @endif

                <div class="pt-4 pb-1 px-4 text-[10px] uppercase tracking-wider text-obsidian-muted/60">
                    Infraestructura Monitoreada
                </div>

                <!-- SERVICIOS -->
                <a href="{{ route('admin.services.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-semibold transition {{ request()->routeIs('admin.services.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">dns</span>
                    Servicios
                </a>

                <!-- SEDES -->
                <a href="{{ route('admin.sites.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-semibold transition {{ request()->routeIs('admin.sites.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">domain</span>
                    Sedes & Enlaces
                </a>

                <!-- DISPOSITIVOS DE RED -->
                <a href="{{ route('admin.devices.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-semibold transition {{ request()->routeIs('admin.devices.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">router</span>
                    Dispositivos de Red
                </a>

                <!-- PROXIES -->
                <a href="{{ route('admin.proxies.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-semibold transition {{ request()->routeIs('admin.proxies.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">public</span>
                    Proxies
                </a>
            </nav>
        </div>

        <!-- PIE DE SIDEBAR -->
        <div class="p-4 border-t border-obsidian-border space-y-2">
            @if(auth()->user()->isAdmin())
            <!-- BOTÓN ESCANEAR AHORA (Solo Administrador) -->
            <button onclick="triggerImmediateScan()" id="btn-scan-now" class="w-full py-2.5 px-3 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-xs font-bold transition flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-base" id="icon-scan-now">bolt</span>
                <span id="text-scan-now">Escanear Ahora</span>
            </button>
            @endif

            <!-- VER SITIO PÚBLICO -->
            <a href="{{ route('home') }}" target="_blank" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-mono text-obsidian-muted hover:text-white hover:bg-obsidian-panel transition">
                <span class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">visibility</span>
                    Ver Sitio Público
                </span>
                <span class="material-symbols-outlined text-sm">open_in_new</span>
            </a>

            <!-- CERRAR SESIÓN -->
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-mono text-red-400 hover:bg-red-950/40 hover:text-red-300 transition">
                    <span class="material-symbols-outlined text-base">logout</span>
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </aside>

    <!-- CONTENEDOR PRINCIPAL DERECHO (PANTALLA COMPLETA) -->
    <div class="flex-1 w-full flex flex-col min-w-0">
        @php
            $latestSnapshot = $latestSnapshot ?? \App\Models\MonitoringSnapshot::latest()->first();
        @endphp
        <!-- TOPBAR ADMINISTRATIVA UNIFICADA -->
        <header class="h-14 glass-panel border-b border-obsidian-border sticky top-0 z-30 px-3 sm:px-5 flex items-center justify-between shadow-md gap-2 sm:gap-4">
            <!-- SECCIÓN IZQUIERDA: BOTÓN TOGGLE & MARCA INSTITUCIONAL -->
            <div class="flex items-center space-x-2.5 sm:space-x-3 shrink-0">
                <!-- BOTÓN REFERENCIAL TOGGLE PARA DESPLEGAR EL MENÚ LATERAL (SOLO ÍCONO) -->
                <button type="button" onclick="toggleAdminSidebar()" id="btn-toggle-sidebar" class="w-9 h-9 rounded-xl bg-obsidian-cyan/15 border border-obsidian-cyan/50 text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition flex items-center justify-center font-mono shadow-lg shadow-cyan-500/10 cursor-pointer shrink-0" title="Desplegar Menú Lateral">
                    <span class="material-symbols-outlined text-lg" id="icon-toggle-sidebar">menu</span>
                </button>

                <!-- LOGO INSTITUCIONAL -->
                <a href="{{ route('admin.dashboard') }}" class="w-8 h-8 rounded-lg bg-gradient-to-tr from-obsidian-cyan/20 to-obsidian-purple/30 border border-obsidian-cyan/40 flex items-center justify-center text-obsidian-cyan glow-cyan shrink-0 p-1 shadow-md hover:scale-105 transition" title="Ir al Dashboard Principal">
                    <img src="{{ asset('img/logo.png') }}" alt="Logo Corporativo" class="w-full h-full object-contain filter drop-shadow-[0_0_6px_rgba(34,211,238,0.6)]">
                </a>

                <!-- TÍTULO INSTITUCIONAL (EN DASHBOARD) O BREADCRUMB CON BOTÓN DE REGRESO (EN OTRAS VISTAS) -->
                @if(request()->routeIs('admin.dashboard'))
                    <div class="hidden lg:block">
                        <h1 class="text-xs sm:text-sm font-bold tracking-tight text-white flex items-center gap-1.5">
                            ATIT • Monitoreo Valle Seco
                        </h1>
                        <p class="text-[9px] font-mono text-obsidian-cyan flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-obsidian-cyan pulse-dot"></span>
                            SUPERVISIÓN EN TIEMPO REAL
                        </p>
                    </div>
                @else
                    <div class="flex items-center space-x-2 sm:space-x-3">
                        <!-- BOTÓN DIRECTO PARA REGRESAR AL DASHBOARD SIN ABRIR EL MENÚ LATERAL -->
                        <a href="{{ route('admin.dashboard') }}" 
                           class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl bg-obsidian-cyan/15 border border-obsidian-cyan/40 text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-xs font-bold transition-all shadow-sm hover:shadow-cyan-500/20 group shrink-0" 
                           title="Regresar al Dashboard Principal">
                            <span class="material-symbols-outlined text-base group-hover:-translate-x-1 transition-transform">arrow_back</span>
                            <span>Dashboard</span>
                        </a>

                        <div class="flex items-center space-x-1.5 min-w-0">
                            <span class="text-obsidian-border hidden sm:inline">/</span>
                            <h1 class="text-xs sm:text-sm font-bold text-white truncate max-w-[140px] sm:max-w-[240px] md:max-w-none">@yield('page_title', 'Admin')</h1>
                        </div>
                    </div>
                @endif
            </div>

            <!-- SECCIÓN CENTRAL: BUSCADOR EN VIVO (EN DASHBOARD) O ESPACIADOR -->
            @if(request()->routeIs('admin.dashboard'))
                <div class="flex-1 max-w-xs md:max-w-md mx-1 sm:mx-4">
                    <div class="relative w-full">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-obsidian-muted text-sm">search</span>
                        <input type="text" id="live-search-input" onkeyup="filterLiveItems()" placeholder="Buscar servicio o sede..." class="w-full bg-[#051424]/90 border border-obsidian-border rounded-lg pl-8 pr-3 py-1.5 text-xs text-white placeholder-obsidian-muted focus:outline-none focus:border-obsidian-cyan font-mono transition"/>
                    </div>
                </div>
            @else
                <div class="flex-1"></div>
            @endif

            <!-- SECCIÓN DERECHA: ROL CLÚSTER, ASISTENTE IA, BADGE GLOBAL, RELOJ & PERFIL -->
            <div class="flex items-center space-x-2 sm:space-x-3 shrink-0">
                <!-- BADGE INTERACTIVO DE ROL DE CLÚSTER (MAESTRO / ESCLAVO) CON TOOLTIP HUD -->
                <div class="relative group">
                    <div class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1 rounded-full border text-xs font-mono font-bold cursor-pointer transition-all duration-200 {{ $isClusterSlave ? 'bg-amber-950/60 text-amber-300 border-amber-500/50 hover:bg-amber-900/60 shadow-sm shadow-amber-500/20' : 'bg-cyan-950/60 text-cyan-300 border-cyan-500/50 hover:bg-cyan-900/60 shadow-sm shadow-cyan-500/20' }}"
                         title="{{ $isClusterSlave ? 'MODO ESCLAVO (RÉPLICA): Este servidor sincroniza su telemetría web desde el Master (' . $clusterConfig['master_api_url'] . '). La configuración de infraestructura es de solo lectura. Para modificar servicios, sedes o proxies, ingrese al servidor Master.' : 'MODO MAESTRO (MASTER): Este servidor ejecuta los escaneos periódicos oficiales a la infraestructura y pfSense, gestiona la configuración y sirve la API de telemetría.' }}">
                        <span class="material-symbols-outlined text-sm {{ $isClusterSlave ? 'text-amber-400' : 'text-cyan-400' }}">
                            {{ $isClusterSlave ? 'cloud_sync' : 'dns' }}
                        </span>
                        <span class="hidden sm:inline">{{ $isClusterSlave ? 'ESCLAVO' : 'MAESTRO' }}</span>
                    </div>

                    <!-- HUD TOOLTIP EN HOVER (AL POSICIONAR EL MOUSE) -->
                    <div class="absolute left-0 sm:left-auto sm:right-0 top-full mt-2 hidden group-hover:block z-50 w-80 sm:w-96 p-3.5 rounded-xl bg-[#06111f] border {{ $isClusterSlave ? 'border-amber-500/60 shadow-amber-500/20' : 'border-cyan-500/60 shadow-cyan-500/20' }} shadow-2xl backdrop-blur-xl text-xs font-mono transition-all duration-200 pointer-events-none">
                        <div class="flex items-center gap-2 pb-2 mb-2 border-b {{ $isClusterSlave ? 'border-amber-500/30 text-amber-400' : 'border-cyan-500/30 text-cyan-300' }} font-bold">
                            <span class="material-symbols-outlined text-base">{{ $isClusterSlave ? 'cloud_sync' : 'dns' }}</span>
                            <span>{{ $isClusterSlave ? 'MODO ESCLAVO (RÉPLICA)' : 'MODO MAESTRO (MASTER)' }}</span>
                        </div>
                        <p class="text-[11px] text-gray-200 leading-relaxed">
                            @if($isClusterSlave)
                                Este servidor sincroniza su telemetría web desde el Master (<code class="text-amber-300">{{ $clusterConfig['master_api_url'] }}</code>). La configuración de infraestructura es de solo lectura. Para modificar servicios, sedes o proxies, ingrese al servidor Master.
                            @else
                                Este servidor ejecuta los escaneos periódicos oficiales a la red y al firewall pfSense. Gestiona las configuraciones oficiales y sirve la API interna hacia los nodos réplica.
                            @endif
                        </p>
                        <div class="mt-2.5 pt-2 border-t border-obsidian-border/60 flex items-center justify-between text-[10px] text-obsidian-muted">
                            <span>Estado: <strong class="{{ $isClusterSlave ? ($clusterConfig['cluster_last_sync_status'] === 'ok' ? 'text-emerald-400' : 'text-amber-400') : 'text-cyan-300' }}">{{ $clusterConfig['cluster_last_sync_status'] === 'master_active' ? 'Master Activo' : ($clusterConfig['cluster_last_sync_status'] === 'ok' ? 'Sincronizado' : 'Pendiente') }}</strong></span>
                            @if($isClusterSlave)
                                <span>Cadencia: <strong class="text-amber-300">Cada {{ $clusterConfig['slave_sync_interval_minutes'] ?? 2 }} min</strong></span>
                            @endif
                        </div>
                    </div>
                </div>

                @if(request()->routeIs('admin.dashboard'))
                    <!-- BOTÓN ASISTENTE IA -->
                    <button type="button" 
                            id="btn-open-ai-chat" 
                            onclick="handleAiChatClick()" 
                            title="Asistente Virtual IA - Sede Valle Seco" 
                            class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1 rounded-full border border-cyan-500/50 bg-cyan-950/40 text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-xs font-semibold transition-all duration-200 shadow-sm hover:shadow-cyan-500/25 hover:scale-[1.02] cursor-pointer group">
                        <span class="material-symbols-outlined text-sm group-hover:rotate-12 transition-transform">smart_toy</span>
                        <span class="hidden sm:inline">IA</span>
                        <span class="flex h-2 w-2 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-400"></span>
                        </span>
                    </button>

                    <!-- BADGE GLOBAL -->
                    <div id="global-status-badge" class="hidden sm:flex items-center gap-2 px-2.5 sm:px-3 py-1 rounded-full border text-xs font-mono font-semibold {{ ($latestSnapshot && $latestSnapshot->global_status == 'OPERACIONAL') ? 'bg-emerald-950/60 text-emerald-400 border-emerald-500/50 glow-green' : (($latestSnapshot && $latestSnapshot->global_status == 'DEGRADADO') ? 'bg-amber-950/60 text-amber-400 border-amber-500/50' : 'bg-red-950/60 text-red-400 border-red-500/50 glow-red') }}"
                         data-tech-title="ESTADO GLOBAL DE INFRAESTRUCTURA"
                         data-tech-type="SISTEMA"
                         data-tech-ip="Red Corporativa Nacional"
                         data-tech-protocol="Orquestador Asíncrono Python"
                         data-tech-latency="< 2.5s ciclo"
                         data-tech-status="{{ $latestSnapshot ? $latestSnapshot->global_status : 'OPERACIONAL' }}"
                         data-tech-details="Chequeo continuo en tiempo real de servicios y sedes regionales.">
                        <span class="w-2 h-2 rounded-full {{ ($latestSnapshot && $latestSnapshot->global_status == 'OPERACIONAL') ? 'bg-emerald-400 pulse-dot' : (($latestSnapshot && $latestSnapshot->global_status == 'DEGRADADO') ? 'bg-amber-400' : 'bg-red-400 pulse-dot') }}"></span>
                        <span id="global-status-text">{{ $latestSnapshot ? $latestSnapshot->global_status : 'OPERACIONAL' }}</span>
                    </div>

                    <!-- RELOJ & SINCRONIZACIÓN -->
                    <div class="hidden xl:flex flex-col text-right font-mono text-[10px] text-obsidian-muted">
                        <span class="text-[8px] uppercase tracking-wider text-obsidian-cyan">Último Escaneo</span>
                        <span id="last-sync-time" class="text-white font-bold">{{ $latestSnapshot ? $latestSnapshot->created_at->format('H:i:s') : '--:--:--' }}</span>
                    </div>

                    <div class="h-6 w-px bg-obsidian-border hidden sm:block"></div>
                @endif

                <!-- USUARIO EN SESIÓN (Click abre ventana para cerrar sesión y perfil) -->
                <button type="button" 
                        onclick="openUserSessionModal()" 
                        id="btn-user-session-trigger" 
                        class="flex items-center space-x-2 sm:space-x-2.5 hover:opacity-95 transition group shrink-0 focus:outline-none p-1.5 rounded-xl hover:bg-obsidian-panel/80 border border-transparent hover:border-obsidian-border cursor-pointer text-left" 
                        title="Opciones de cuenta y Cerrar Sesión">
                    <div class="text-right hidden sm:block">
                        <span class="text-xs font-bold text-white block group-hover:text-obsidian-cyan transition truncate max-w-[130px]">{{ Auth::user()->name }}</span>
                        <span class="text-[9px] font-mono text-obsidian-cyan uppercase flex items-center justify-end gap-0.5">
                            <span>{{ Auth::user()->role === 'admin' ? 'Administrador' : 'Operador' }}</span>
                            <span class="material-symbols-outlined text-[13px] text-obsidian-cyan/70 group-hover:text-obsidian-cyan group-hover:translate-y-0.5 transition-transform">expand_more</span>
                        </span>
                    </div>
                    <div class="relative w-8 h-8 rounded-full bg-gradient-to-tr from-obsidian-cyan/20 to-obsidian-purple/30 border border-obsidian-cyan/40 text-obsidian-cyan font-bold flex items-center justify-center text-xs shadow-md overflow-hidden shrink-0 group-hover:border-obsidian-cyan transition ring-1 ring-cyan-500/20">
                        @if(Auth::user()->avatar_url)
                            <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                        @endif
                        <span class="absolute bottom-0 right-0 w-2 h-2 rounded-full bg-emerald-400 border border-black" title="En línea"></span>
                    </div>
                </button>
            </div>
        </header>

        <!-- CONTENIDO DE LA PÁGINA -->
        <main class="flex-1 p-4 sm:p-6 space-y-6">
            <!-- ALERTAS FLASH -->
            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/50 text-emerald-400 text-xs font-mono flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">check_circle</span>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 rounded-xl bg-red-950/60 border border-red-500/50 text-red-400 text-xs font-mono flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">error</span>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-white">&times;</button>
                </div>
            @endif

            @if($errors->any())
                <div class="p-4 rounded-xl bg-red-950/60 border border-red-500/50 text-red-400 text-xs font-mono space-y-1">
                    <div class="flex items-center gap-2 font-bold">
                        <span class="material-symbols-outlined text-base">warning</span>
                        <span>Por favor corrige los siguientes errores:</span>
                    </div>
                    @foreach($errors->all() as $err)
                        <p class="pl-6">• {{ $err }}</p>
                    @endforeach
                </div>
            @endif

            @yield('admin_content')
        </main>
    </div>
</div>

<script>
    function toggleAdminSidebar(forceState) {
        const sidebar = document.getElementById('admin-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const icon = document.getElementById('icon-toggle-sidebar');
        if (!sidebar) return;

        const isClosed = sidebar.classList.contains('-translate-x-full');
        const shouldOpen = (typeof forceState === 'boolean') ? forceState : isClosed;

        if (shouldOpen) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            if (backdrop) backdrop.classList.remove('hidden');
            if (icon) icon.innerText = 'menu_open';
        } else {
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            if (backdrop) backdrop.classList.add('hidden');
            if (icon) icon.innerText = 'menu';
        }
    }

    // Cerrar sidebar al presionar Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            toggleAdminSidebar(false);
        }
    });

    async function triggerImmediateScan() {
        const btn = document.getElementById('btn-scan-now');
        const icon = document.getElementById('icon-scan-now');
        const txt = document.getElementById('text-scan-now');
        
        btn.disabled = true;
        icon.classList.add('animate-spin');
        txt.innerText = 'Escaneando...';

        try {
            const res = await fetch('{{ route("admin.sync.scan") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                }
            });
            const data = await res.json();
            if (data.success) {
                alert('⚡ Escaneo completado exitosamente.\n' + data.output);
                window.location.reload();
            } else {
                alert('⚠️ Error durante el escaneo: ' + (data.error || data.message));
            }
        } catch (err) {
            alert('Error de red al disparar escaneo: ' + err);
        } finally {
            btn.disabled = false;
            icon.classList.remove('animate-spin');
            txt.innerText = 'Escanear Ahora';
        }
    }
</script>
@endsection
