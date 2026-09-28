@extends('layouts.app')

@section('content')
<!-- OVERLAY BACKDROP OSCURO PARA SIDEBAR -->
<div id="sidebar-backdrop" onclick="toggleAdminSidebar(false)" class="fixed inset-0 bg-black/70 backdrop-blur-xs z-40 hidden transition-opacity duration-300"></div>

<div class="min-h-screen flex relative">
    <!-- SIDEBAR DE ADMINISTRACIÓN RETRÁCTIL (AUTO-ESCONDIDO) -->
    <aside id="admin-sidebar" class="w-72 max-w-[85vw] glass-panel border-r border-obsidian-border flex flex-col h-screen fixed inset-y-0 left-0 z-50 transform -translate-x-full transition-transform duration-300 ease-in-out shadow-2xl bg-[#07172b]/98">
        <!-- CABECERA DE SIDEBAR (FIJA) -->
        <div class="h-14 px-5 flex items-center justify-between border-b border-obsidian-border shrink-0">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-obsidian-cyan/20 to-obsidian-purple/30 border border-obsidian-cyan/40 flex items-center justify-center text-obsidian-cyan glow-cyan p-1 shadow-lg shadow-cyan-500/20">
                    <img src="{{ asset('img/logo.png') }}" alt="Logo Corporativo" class="w-full h-full object-contain filter drop-shadow-[0_0_6px_rgba(34,211,238,0.6)]" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                    <span class="material-symbols-outlined text-lg text-obsidian-cyan hidden">monitoring</span>
                </div>
                <div>
                    <h2 class="text-xs sm:text-sm font-bold text-white tracking-tight">PANEL DE CONTROL</h2>
                    <p class="text-[10px] font-mono text-obsidian-cyan">ATIT VALLE SECO</p>
                </div>
            </div>
            <!-- BOTÓN CERRAR SIDEBAR -->
            <button type="button" onclick="toggleAdminSidebar(false)" class="p-1 rounded-lg text-obsidian-muted hover:text-white hover:bg-obsidian-panel transition leading-none text-xl cursor-pointer" title="Ocultar Menú">
                &times;
            </button>
        </div>

        <!-- ENLACES DE NAVEGACIÓN (SCROLLABLE INDEPENDIENTE) -->
        <nav class="flex-1 min-h-0 overflow-y-auto px-3.5 py-2.5 space-y-1 font-mono text-xs custom-scroll">
            <!-- DASHBOARD -->
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.dashboard') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                <span class="material-symbols-outlined text-lg">dashboard</span>
                Dashboard
            </a>

            <!-- MI PERFIL (Para Administradores y Operadores) -->
            <a href="{{ route('admin.profile.show') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.profile.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                <span class="material-symbols-outlined text-lg">account_circle</span>
                Mi Perfil
            </a>

            <!-- ============================================================= -->
            <!-- SECCIÓN: GESTIÓN OPERATIVA & MONITOREO                        -->
            <!-- ============================================================= -->
            <div class="pt-3 pb-1 px-3 flex items-center justify-between text-[10px] uppercase tracking-wider font-bold text-cyan-400 border-t border-obsidian-border/50 mt-1">
                <span class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[13px] text-cyan-400">tune</span>
                    GESTIÓN
                </span>
                <span class="text-[9px] font-mono text-obsidian-muted/60 lowercase">operación</span>
            </div>

            <!-- TOPOLOGÍA DE RED -->
            <a href="{{ route('admin.topology.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.topology.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Diagrama y mapa interactivo de topología">
                <span class="material-symbols-outlined text-lg">hub</span>
                Topología de Red
            </a>

            <!-- SERVICIOS & CONECTIVIDAD -->
            <a href="{{ route('admin.services.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.services.*', 'admin.proxies.*', 'admin.ssl.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Servicios, Proxies y Certificados SSL/TLS">
                <span class="material-symbols-outlined text-lg">dns</span>
                Servicios & Conectividad
            </a>

            <!-- SEDES & ENLACES -->
            <a href="{{ route('admin.sites.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.sites.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Sedes remotas y enlaces de fibra/radio">
                <span class="material-symbols-outlined text-lg">domain</span>
                Sedes & Enlaces
            </a>

            <!-- EQUIPOS & HARDWARE -->
            <a href="{{ route('admin.devices.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.devices.*', 'admin.lifecycle.*', 'admin.wol.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Switches, Routers, Ciclo de Vida y WoL">
                <span class="material-symbols-outlined text-lg">router</span>
                Equipos & Hardware
            </a>

            <!-- RADAR & DESCUBRIMIENTO -->
            <a href="{{ route('admin.discovery.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.discovery.*', 'admin.netradar.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Auto-Discovery y NET Radar de tráfico">
                <span class="material-symbols-outlined text-lg">radar</span>
                Radar & Descubrimiento
            </a>

            <!-- CONSOLA SNMP 360° -->
            <a href="{{ route('admin.snmp.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.snmp.*', 'admin.traps.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Telemetría SNMP, OIDs y Trampas Push (UDP 162)">
                <span class="material-symbols-outlined text-lg">sensors</span>
                Consola SNMP 360°
            </a>

            <!-- TRÁFICO & LOGS DE RED -->
            <a href="{{ route('admin.netflow.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.netflow.*', 'admin.syslog.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="NetFlow v5/v9 y Syslog en vivo">
                <span class="material-symbols-outlined text-lg">swap_vert</span>
                Tráfico & Logs de Red
            </a>

            <!-- CENTRO DE ALERTAS & IA -->
            <a href="{{ route('admin.alerts.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.alerts.*', 'admin.predictive.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Correlación de incidentes e IA predictiva">
                <span class="material-symbols-outlined text-lg">notifications_active</span>
                Centro de Alertas & IA
            </a>

            <!-- RESPALDOS Y CONFIGS (NCM) -->
            <a href="{{ route('admin.configs.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.configs.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Gestión de configuraciones (NCM) y respaldos">
                <span class="material-symbols-outlined text-lg">settings_backup_restore</span>
                Respaldos y Configs
            </a>

            <!-- ENERGÍA & UPS -->
            <a href="{{ route('admin.ups.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.ups.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Supervisión de energía, respaldo eléctrico y telemetría de UPS">
                <span class="material-symbols-outlined text-lg">battery_charging_full</span>
                Energía & UPS
            </a>

            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('security.audit') || auth()->user()->hasPermission('telegram.templates') || auth()->user()->hasPermission('telegram.commands'))
            <!-- ============================================================= -->
            <!-- SECCIÓN: SISTEMA & GOBERNANZA (EXCLUSIVO ADMINISTRADOR)       -->
            <!-- ============================================================= -->
            <div class="pt-4 pb-1 px-3 flex items-center justify-between text-[10px] uppercase tracking-wider font-bold text-purple-400 border-t border-obsidian-border/50 mt-2">
                <span class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[13px] text-purple-400">shield_person</span>
                    SISTEMA
                </span>
                <span class="text-[9px] font-mono text-obsidian-muted/60 lowercase">admin</span>
            </div>
            @endif

            @if(auth()->user()->isAdmin())
            <!-- USUARIOS & ROLES -->
            <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.users.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Control de cuentas, roles y permisos LDAP/Locales">
                <span class="material-symbols-outlined text-lg">group</span>
                Usuarios & Roles
            </a>
            @endif

            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('security.audit'))
            <!-- AUDITORÍA DEL SISTEMA -->
            <a href="{{ route('admin.audit.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.audit.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Pistas de auditoría y bitácora forense">
                <span class="material-symbols-outlined text-lg">policy</span>
                Auditoría del Sistema
            </a>
            @endif

            @if(auth()->user()->isAdmin())
            <!-- BANEOS & SEGURIDAD -->
            <a href="{{ route('admin.bans.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.bans.*') ? 'bg-red-500 text-white shadow-lg shadow-red-500/20' : 'text-red-400 hover:text-white hover:bg-red-950/40' }}" title="Bloqueo preventivo de IPs y jaulas Fail2Ban">
                <span class="material-symbols-outlined text-lg">gavel</span>
                Baneos & Seguridad
            </a>

            <!-- TÉRMINOS DE USO -->
            <a href="{{ route('admin.terms.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.terms.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Políticas corporativas y firmas de aceptación">
                <span class="material-symbols-outlined text-lg">verified_user</span>
                Términos de Uso
            </a>
            @endif

            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('telegram.templates') || auth()->user()->hasPermission('telegram.commands'))
            <!-- COMANDOS & PLANTILLAS BOT -->
            <a href="{{ route('admin.bot.commands.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.bot.commands.*', 'admin.bot.templates.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Gestor de comandos y plantillas de Telegram">
                <span class="material-symbols-outlined text-lg">terminal</span>
                Comandos & Plantillas Bot
            </a>
            @endif

            @if(auth()->user()->isAdmin())
            <!-- CONFIGURACIÓN AVANZADA -->
            <a href="{{ route('admin.settings.advanced') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold transition {{ request()->routeIs('admin.settings.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}" title="Ajustes de cluster, umbrales y timeouts">
                <span class="material-symbols-outlined text-lg">tune</span>
                Configuración Avanzada
            </a>
            @endif
        </nav>

        <!-- PIE DE SIDEBAR (FIJO) -->
        <div class="p-3 border-t border-obsidian-border space-y-2 shrink-0 bg-[#07172b]/95">
            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.scan_now'))
            <!-- BOTÓN ESCANEAR AHORA -->
            <button onclick="triggerImmediateScan()" id="btn-scan-now" class="w-full py-2 px-3 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base" id="icon-scan-now">bolt</span>
                <span id="text-scan-now">Escanear Ahora</span>
            </button>
            @endif

            <!-- VER SITIO PÚBLICO -->
            <a href="{{ route('home') }}" target="_blank" class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-mono text-obsidian-muted hover:text-white hover:bg-obsidian-panel transition">
                <span class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">visibility</span>
                    Ver Sitio Público
                </span>
                <span class="material-symbols-outlined text-sm">open_in_new</span>
            </a>

            <!-- CERRAR SESIÓN -->
            <form action="{{ route('logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="w-full flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-mono text-red-400 hover:bg-red-950/40 hover:text-red-300 transition cursor-pointer">
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
                    <img src="{{ asset('img/logo.png') }}" alt="Logo Corporativo" class="w-full h-full object-contain filter drop-shadow-[0_0_6px_rgba(34,211,238,0.6)]" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                    <span class="material-symbols-outlined text-lg text-obsidian-cyan hidden">monitoring</span>
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
            <!-- SECCIÓN DERECHA: ROL CLÚSTER, ALERTAS, BATERÍA UPS, ASISTENTE IA, ESTADO GLOBAL, RELOJ & PERFIL -->
            <div class="flex items-center space-x-1.5 sm:space-x-2 shrink-0">
                <!-- BADGE INTERACTIVO DE ROL DE CLÚSTER (SOLO ICONO CON TOOLTIP HUD) -->
                <div class="relative group">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full border cursor-pointer transition-all duration-200 {{ $isClusterSlave ? 'bg-amber-950/60 text-amber-300 border-amber-500/50 hover:bg-amber-900/60 shadow-sm shadow-amber-500/20' : 'bg-cyan-950/60 text-cyan-300 border-cyan-500/50 hover:bg-cyan-900/60 shadow-sm shadow-cyan-500/20' }}"
                         title="{{ $isClusterSlave ? 'MODO ESCLAVO (RÉPLICA): Sincroniza desde el Master (' . $clusterConfig['master_api_url'] . ')' : 'MODO MAESTRO (MASTER): Ejecuta escaneos oficiales y sirve API de telemetría' }}">
                        <span class="material-symbols-outlined text-base {{ $isClusterSlave ? 'text-amber-400' : 'text-cyan-400' }}">
                            {{ $isClusterSlave ? 'cloud_sync' : 'dns' }}
                        </span>
                    </div>

                    <!-- HUD TOOLTIP EN HOVER -->
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

                <!-- BADGE DE ALERTAS CRÍTICAS / EMERGENCIA (SOLO ICONO CON CONTADOR FLOTANTE) -->
                @php
                    $criticalAlertsCount = \App\Models\Alert::whereIn('status', ['firing', 'acknowledged'])
                        ->whereIn('severity', ['critical', 'emergency'])->count();
                @endphp
                <a href="{{ route('admin.alerts.index', ['tab' => 'active', 'severity' => 'critical']) }}" 
                   class="relative flex items-center justify-center w-8 h-8 rounded-full transition {{ $criticalAlertsCount > 0 ? 'bg-red-950/60 border border-red-500/60 text-red-400 hover:bg-red-900/60 shadow-md shadow-red-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-slate-400 hover:text-white hover:border-slate-500' }}"
                   title="{{ $criticalAlertsCount > 0 ? $criticalAlertsCount . ' incidentes críticos o de emergencia activos' : 'Sin alertas críticas activas' }}">
                    <span class="material-symbols-outlined text-base {{ $criticalAlertsCount > 0 ? 'text-red-400 animate-pulse' : 'text-slate-400' }}">
                        {{ $criticalAlertsCount > 0 ? 'notifications_active' : 'notifications' }}
                    </span>
                    @if($criticalAlertsCount > 0)
                        <span class="absolute -top-1 -right-1 flex h-4 min-w-4 px-1 items-center justify-center rounded-full bg-red-600 text-white text-[9px] font-bold font-mono shadow-sm">
                            {{ $criticalAlertsCount }}
                        </span>
                    @endif
                </a>

                <!-- BADGE DE BATERÍA / UPS (SOLO ICONO CON VISTA PREVIA HUD AL POSICIONAR EL MOUSE) -->
                @php
                    $hUps = $headerUpsDevice ?? null;
                    $hIsOnBat = $hUps && $hUps->is_on_battery;
                    $hBatPct = $hUps ? ($hUps->battery_percent ?? 100) : 100;
                    $hBatV = $hUps ? ($hUps->battery_voltage ?? 2.25) : 2.25;
                    $hInV = $hUps ? ($hUps->input_voltage ?? 225.1) : 225.1;
                    $hOutV = $hUps ? ($hUps->output_voltage ?? 208.3) : 208.3;
                    $hLoadPct = $hUps ? ($hUps->load_percent ?? 8) : 8;
                    $hLoadWatts = round(($hLoadPct / 100) * 6000);
                    $hTempC = $hUps ? ($hUps->temperature_c ?? 43.0) : 43.0;
                    $hRuntimeMin = round(($hBatPct / 100) * 135);

                    // Mini Gráfica SVG Sparkline
                    $hHist = $headerUpsHistories ?? collect();
                    $inSparkPoints = [];
                    $outSparkPoints = [];
                    if ($hHist->isNotEmpty()) {
                        $allSparkV = [];
                        foreach ($hHist as $hItem) {
                            $allSparkV[] = (float) $hItem->input_voltage;
                            $allSparkV[] = (float) $hItem->output_voltage;
                        }
                        $minSparkV = !empty($allSparkV) ? min($allSparkV) - 2 : 200;
                        $maxSparkV = !empty($allSparkV) ? max($allSparkV) + 2 : 230;
                        $sparkRange = max(1, $maxSparkV - $minSparkV);
                        $sparkCount = $hHist->count();

                        foreach ($hHist as $idx => $hItem) {
                            $px = round(5 + ($idx / max(1, $sparkCount - 1)) * 310, 1);
                            $pyIn = round(55 - (((float)$hItem->input_voltage - $minSparkV) / $sparkRange) * 45, 1);
                            $pyOut = round(55 - (((float)$hItem->output_voltage - $minSparkV) / $sparkRange) * 45, 1);
                            $inSparkPoints[] = "{$px},{$pyIn}";
                            $outSparkPoints[] = "{$px},{$pyOut}";
                        }
                    }
                @endphp
                <div class="relative group" id="topbar-ups-container">
                    <a href="{{ route('admin.ups.index') }}" 
                       id="topbar-ups-btn"
                       class="relative flex items-center justify-center w-8 h-8 rounded-full border cursor-pointer transition-all duration-200 {{ $hIsOnBat ? 'bg-red-950/80 text-red-400 border-red-500/60 animate-pulse shadow-md shadow-red-500/30' : ($hBatPct < 25 ? 'bg-amber-950/80 text-amber-400 border-amber-500/60 shadow-md shadow-amber-500/30' : 'bg-cyan-950/60 text-cyan-300 border-cyan-500/50 hover:bg-cyan-900/60 hover:text-white shadow-sm shadow-cyan-500/20') }}"
                       title="UPS ZTG LV6KL: {{ $hIsOnBat ? 'MODO BATERÍA (CORTE ELÉCTRICO)' : 'RED NORMAL (' . $hBatPct . '%)' }}">
                        <span id="topbar-ups-icon" class="material-symbols-outlined text-base {{ $hIsOnBat ? 'text-red-400' : 'text-cyan-400' }}">
                            {{ $hIsOnBat ? 'battery_alert' : ($hBatPct >= 95 ? 'battery_charging_full' : 'battery_std') }}
                        </span>
                        @if($hIsOnBat)
                            <span class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                            </span>
                        @endif
                    </a>

                    <!-- HUD TOOLTIP EN HOVER: VISTA PREVIA DETALLADA DE ADMIN/UPS -->
                    <div class="absolute right-0 top-full mt-2 hidden group-hover:block z-50 w-80 sm:w-96 p-4 rounded-xl bg-[#06111f]/95 border border-cyan-500/60 shadow-2xl shadow-cyan-950/60 backdrop-blur-xl text-xs font-mono transition-all duration-200">
                        <a href="{{ route('admin.ups.index') }}" class="block">
                            <!-- HEADER DE LA VISTA PREVIA -->
                            <div class="flex items-center justify-between pb-2.5 mb-3 border-b border-obsidian-border/70">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-lg {{ $hIsOnBat ? 'text-red-400 animate-pulse' : 'text-cyan-400' }}">
                                        {{ $hIsOnBat ? 'power_off' : 'bolt' }}
                                    </span>
                                    <div>
                                        <div class="font-bold text-white text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                                            <span>UPS ZTG LV6KL</span>
                                            <span class="text-[9px] px-1.5 py-0.2 rounded bg-cyan-950 text-cyan-300 border border-cyan-500/40">6 kVA</span>
                                        </div>
                                        <div class="text-[9px] text-obsidian-muted">Sede Valle Seco • Rack Principal</div>
                                    </div>
                                </div>
                                <span id="topbar-ups-status-badge" class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider {{ $hIsOnBat ? 'bg-red-950 text-red-400 border border-red-500/60 animate-pulse' : 'bg-emerald-950 text-emerald-400 border border-emerald-500/60' }}">
                                    {{ $hIsOnBat ? 'Modo Batería' : 'Red Normal' }}
                                </span>
                            </div>

                            <!-- CUADRÍCULA DE MÉTRICAS BÁSICAS -->
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <!-- BATERÍA & AUTONOMÍA -->
                                <div class="p-2 rounded-lg bg-[#040c17] border border-obsidian-border/60">
                                    <div class="flex items-center justify-between text-[9px] text-obsidian-muted uppercase font-bold">
                                        <span>Batería</span>
                                        <span id="topbar-ups-runtime" class="text-cyan-300">~{{ $hRuntimeMin }} min</span>
                                    </div>
                                    <div class="flex items-baseline gap-1 my-1">
                                        <span id="topbar-ups-bat-pct" class="text-base font-black {{ $hBatPct < 25 ? 'text-red-400' : ($hBatPct < 60 ? 'text-amber-400' : 'text-emerald-400') }}">
                                            {{ $hBatPct }}%
                                        </span>
                                        <span class="text-[9px] text-obsidian-muted">({{ number_format($hBatV, 2) }} V/c)</span>
                                    </div>
                                    <div class="w-full bg-slate-900 h-1.5 rounded-full overflow-hidden border border-obsidian-border/50">
                                        <div id="topbar-ups-bat-bar" class="h-full rounded-full transition-all {{ $hBatPct < 25 ? 'bg-red-500' : ($hBatPct < 60 ? 'bg-amber-400' : 'bg-emerald-500') }}" style="width: {{ $hBatPct }}%"></div>
                                    </div>
                                </div>

                                <!-- CONSUMO DE CARGA -->
                                <div class="p-2 rounded-lg bg-[#040c17] border border-obsidian-border/60">
                                    <div class="flex items-center justify-between text-[9px] text-obsidian-muted uppercase font-bold">
                                        <span>Carga Rack</span>
                                        <span id="topbar-ups-temp" class="text-amber-300">{{ number_format($hTempC, 1) }} °C</span>
                                    </div>
                                    <div class="flex items-baseline gap-1 my-1">
                                        <span id="topbar-ups-load-pct" class="text-base font-black text-purple-300">
                                            {{ $hLoadPct }}%
                                        </span>
                                        <span id="topbar-ups-load-watts" class="text-[9px] text-obsidian-muted">~{{ $hLoadWatts }} W</span>
                                    </div>
                                    <div class="w-full bg-slate-900 h-1.5 rounded-full overflow-hidden border border-obsidian-border/50">
                                        <div id="topbar-ups-load-bar" class="h-full rounded-full bg-purple-500 transition-all" style="width: {{ $hLoadPct }}%"></div>
                                    </div>
                                </div>

                                <!-- VOLTAJE ENTRADA -->
                                <div class="p-2 rounded-lg bg-[#040c17] border border-obsidian-border/60">
                                    <span class="text-[9px] text-obsidian-muted uppercase font-bold block">Entrada (Red)</span>
                                    <span id="topbar-ups-in-v" class="text-xs font-bold text-white font-mono mt-0.5 block">
                                        {{ number_format($hInV, 1) }} <span class="text-[9px] text-obsidian-muted">VAC</span>
                                    </span>
                                </div>

                                <!-- VOLTAJE SALIDA -->
                                <div class="p-2 rounded-lg bg-[#040c17] border border-obsidian-border/60">
                                    <span class="text-[9px] text-obsidian-muted uppercase font-bold block">Salida (UPS)</span>
                                    <span id="topbar-ups-out-v" class="text-xs font-bold text-cyan-300 font-mono mt-0.5 block">
                                        {{ number_format($hOutV, 1) }} <span class="text-[9px] text-obsidian-muted">VAC</span>
                                    </span>
                                </div>
                            </div>

                            <!-- MINI GRÁFICA DE TELEMETRÍA (SPARKLINE VECTORIAL) -->
                            <div class="p-2.5 rounded-lg bg-[#040c17] border border-obsidian-border/60 mb-2.5">
                                <div class="flex items-center justify-between text-[9px] font-bold text-obsidian-muted mb-1.5">
                                    <span class="uppercase tracking-wider flex items-center gap-1">
                                        <span class="material-symbols-outlined text-xs text-cyan-400">show_chart</span>
                                        <span>Telemetría Reciente (VAC)</span>
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span class="flex items-center gap-1 text-cyan-400">
                                            <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span> Entrada
                                        </span>
                                        <span class="flex items-center gap-1 text-emerald-400">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Salida
                                        </span>
                                    </div>
                                </div>
                                <div class="h-14 w-full relative">
                                    @if(!empty($inSparkPoints))
                                    <svg viewBox="0 0 320 60" class="w-full h-full overflow-visible" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="gradTopUpsIn" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#00f3ff" stop-opacity="0.25"/>
                                                <stop offset="100%" stop-color="#00f3ff" stop-opacity="0.0"/>
                                            </linearGradient>
                                        </defs>
                                        <line x1="5" y1="8" x2="315" y2="8" stroke="#132438" stroke-dasharray="2,2" />
                                        <line x1="5" y1="30" x2="315" y2="30" stroke="#132438" stroke-dasharray="2,2" />
                                        <line x1="5" y1="52" x2="315" y2="52" stroke="#132438" />

                                        <polygon points="5,55 {{ implode(' ', $inSparkPoints) }} 315,55" fill="url(#gradTopUpsIn)" />
                                        <polyline points="{{ implode(' ', $inSparkPoints) }}" fill="none" stroke="#00f3ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <polyline points="{{ implode(' ', $outSparkPoints) }}" fill="none" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    @else
                                    <div class="flex items-center justify-center h-full text-[10px] text-obsidian-muted">
                                        Sin datos históricos disponibles
                                    </div>
                                    @endif
                                </div>
                            </div>

                            <!-- FOOTER / CALL TO ACTION -->
                            <div class="flex items-center justify-between pt-2 border-t border-obsidian-border/50 text-[10px] text-cyan-300 font-bold hover:text-cyan-200 transition">
                                <span>Ver Diagnóstico & Alertas</span>
                                <span class="material-symbols-outlined text-xs">arrow_forward</span>
                            </div>
                        </a>
                    </div>
                </div>

                @if(request()->routeIs('admin.dashboard'))
                    <!-- BOTÓN ASISTENTE IA -->
                    <button type="button" 
                            id="btn-open-ai-chat" 
                            onclick="handleAiChatClick()" 
                            title="{{ ($isAiEnabled ?? true) ? 'Asistente Virtual IA - Sede Valle Seco' : 'Asistente Virtual IA - Desactivado por el módulo administrativo' }}" 
                            class="flex items-center justify-center w-8 h-8 rounded-full border {{ ($isAiEnabled ?? true) ? 'border-cyan-500/50 bg-cyan-950/40 text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black shadow-cyan-500/25' : 'border-slate-700/60 bg-slate-900/60 text-slate-500 opacity-60 hover:border-slate-600 hover:text-slate-400' }} font-mono transition-all duration-200 shadow-sm hover:scale-105 cursor-pointer group">
                        <span class="material-symbols-outlined text-base {{ ($isAiEnabled ?? true) ? 'group-hover:rotate-12 transition-transform' : '' }}">smart_toy</span>
                    </button>

                    <!-- BADGE GLOBAL (SOLO ICONO CON TOOLTIP E INDICADOR) -->
                    @php
                        $gStatus = $latestSnapshot ? $latestSnapshot->global_status : 'OPERACIONAL';
                        $gIcon = ($gStatus === 'OPERACIONAL') ? 'check_circle' : (($gStatus === 'DEGRADADO') ? 'warning' : 'error');
                        $gColor = ($gStatus === 'OPERACIONAL') ? 'bg-emerald-950/60 text-emerald-400 border-emerald-500/50 glow-green' : (($gStatus === 'DEGRADADO') ? 'bg-amber-950/60 text-amber-400 border-amber-500/50' : 'bg-red-950/60 text-red-400 border-red-500/50 glow-red');
                    @endphp
                    <div id="global-status-badge" 
                         class="relative flex items-center justify-center w-8 h-8 rounded-full border cursor-pointer transition-all duration-200 {{ $gColor }}"
                         title="Estado Global: {{ $gStatus }}"
                         data-tech-title="ESTADO GLOBAL DE INFRAESTRUCTURA"
                         data-tech-type="SISTEMA"
                         data-tech-ip="Red Corporativa Nacional"
                         data-tech-protocol="Orquestador Asíncrono Python"
                         data-tech-latency="< 2.5s ciclo"
                         data-tech-status="{{ $gStatus }}"
                         data-tech-details="Chequeo continuo en tiempo real de servicios y sedes regionales.">
                        <span id="global-status-icon" class="material-symbols-outlined text-base">
                            {{ $gIcon }}
                        </span>
                        <span id="global-status-text" class="sr-only">{{ $gStatus }}</span>
                    </div>

                    <!-- RELOJ & SINCRONIZACIÓN -->
                    <div class="hidden xl:flex flex-col text-right font-mono text-[10px] text-obsidian-muted">
                        <span class="text-[8px] uppercase tracking-wider text-obsidian-cyan">Último Escaneo</span>
                        <span id="last-sync-time" class="text-white font-bold">{{ $latestSnapshot ? $latestSnapshot->created_at->timezone('America/Caracas')->format('h:i:s A') : '--:--:--' }}</span>
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

                <!-- BOTÓN DE ASISTENCIA Y ACERCA DE (?) -->
                <div class="relative shrink-0" id="help-menu-container">
                    <button type="button" 
                            onclick="toggleHelpDropdown(event)" 
                            id="btn-help-dropdown" 
                            class="w-8 h-8 rounded-full bg-obsidian-panel/80 hover:bg-obsidian-border border border-obsidian-border hover:border-obsidian-cyan/60 text-obsidian-muted hover:text-obsidian-cyan transition shadow-sm flex items-center justify-center cursor-pointer focus:outline-none focus:ring-1 focus:ring-obsidian-cyan/50" 
                            title="Ayuda del Sistema y Acerca">
                        <span class="material-symbols-outlined text-[19px] leading-none">help</span>
                    </button>

                    <!-- MENÚ POPUP FLOTANTE (DROPDOWN) -->
                    <div id="help-dropdown-menu" class="hidden absolute right-0 mt-2 w-56 rounded-2xl bg-[#07172b]/95 border border-obsidian-border/80 shadow-[0_10px_35px_rgba(0,0,0,0.6)] backdrop-blur-xl z-50 py-2 divide-y divide-obsidian-border/50 animate-in fade-in zoom-in-95 duration-150">
                        <div class="px-4 py-2">
                            <p class="text-[10px] font-mono uppercase tracking-wider text-obsidian-cyan font-bold">Centro de Asistencia</p>
                            <p class="text-[11px] text-obsidian-muted">Recursos y documentación</p>
                        </div>
                        
                        <div class="py-1">
                            <!-- OPCIÓN 1: MANUAL DE AYUDA -->
                            <a href="{{ route('admin.help.index') }}" class="flex items-center gap-3 px-4 py-2.5 text-xs text-slate-200 hover:text-obsidian-cyan hover:bg-obsidian-panel/80 transition group">
                                <span class="w-7 h-7 rounded-lg bg-cyan-950/60 border border-cyan-500/40 text-cyan-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                    <span class="material-symbols-outlined text-base">menu_book</span>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <span class="font-bold block leading-tight">Ayuda</span>
                                    <span class="text-[10px] font-mono text-obsidian-muted block truncate">Manual de usuario</span>
                                </div>
                            </a>

                            <!-- OPCIÓN 2: ACERCA -->
                            <button type="button" onclick="openAboutSystemModal(); toggleHelpDropdown();" class="w-full flex items-center gap-3 px-4 py-2.5 text-xs text-slate-200 hover:text-purple-300 hover:bg-obsidian-panel/80 transition text-left cursor-pointer group">
                                <span class="w-7 h-7 rounded-lg bg-purple-950/60 border border-purple-500/40 text-purple-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                    <span class="material-symbols-outlined text-base">info</span>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <span class="font-bold block leading-tight">Acerca</span>
                                    <span class="text-[10px] font-mono text-obsidian-muted block truncate">Versión y autoría</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- CONTENIDO DE LA PÁGINA -->
        <main class="flex-1 p-4 sm:p-6 space-y-6">
            <!-- ALERTAS FLASH -->
            @if(session('success'))
                <div class="flash-alert p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/50 text-emerald-400 text-xs font-mono flex items-center justify-between transition-all duration-500 shadow-lg shadow-emerald-950/30" data-autohide="5000">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-base text-emerald-400">check_circle</span>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button type="button" onclick="dismissFlashAlert(this.closest('.flash-alert'))" class="text-emerald-400 hover:text-white text-lg leading-none p-1 transition" title="Cerrar">&times;</button>
                </div>
            @endif

            @if(session('error'))
                <div class="flash-alert p-4 rounded-xl bg-red-950/60 border border-red-500/50 text-red-400 text-xs font-mono flex items-center justify-between transition-all duration-500 shadow-lg shadow-red-950/30" data-autohide="8000">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-base text-red-400">error</span>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button type="button" onclick="dismissFlashAlert(this.closest('.flash-alert'))" class="text-red-400 hover:text-white text-lg leading-none p-1 transition" title="Cerrar">&times;</button>
                </div>
            @endif

            @if($errors->any())
                <div class="flash-alert p-4 rounded-xl bg-red-950/60 border border-red-500/50 text-red-400 text-xs font-mono space-y-1 transition-all duration-500 shadow-lg shadow-red-950/30" data-autohide="10000">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 font-bold">
                            <span class="material-symbols-outlined text-base text-red-400">warning</span>
                            <span>Por favor corrige los siguientes errores:</span>
                        </div>
                        <button type="button" onclick="dismissFlashAlert(this.closest('.flash-alert'))" class="text-red-400 hover:text-white text-lg leading-none p-1 transition" title="Cerrar">&times;</button>
                    </div>
                    @foreach($errors->all() as $err)
                        <p class="pl-6 text-red-300">• {{ $err }}</p>
                    @endforeach
                </div>
            @endif

            @yield('admin_content')
        </main>
    </div>
</div>

<script>
    // Cierre suave de alertas flash del sistema
    function dismissFlashAlert(el) {
        if (!el) return;
        el.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        el.style.opacity = '0';
        el.style.transform = 'translateY(-10px)';
        el.style.maxHeight = '0px';
        el.style.paddingTop = '0px';
        el.style.paddingBottom = '0px';
        el.style.marginTop = '0px';
        el.style.marginBottom = '0px';
        el.style.overflow = 'hidden';
        setTimeout(() => el.remove(), 400);
    }

    // Inicializar auto-ocultamiento de alertas flash
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.flash-alert[data-autohide]').forEach(function(alertEl) {
            const delay = parseInt(alertEl.getAttribute('data-autohide'), 10) || 5000;
            let timeoutId = setTimeout(function() {
                dismissFlashAlert(alertEl);
            }, delay);

            // Pausar si el usuario coloca el ratón encima (para leer con calma)
            alertEl.addEventListener('mouseenter', function() {
                clearTimeout(timeoutId);
            });

            // Reanudar con 2.5 segundos de gracia al retirar el ratón
            alertEl.addEventListener('mouseleave', function() {
                timeoutId = setTimeout(function() {
                    dismissFlashAlert(alertEl);
                }, 2500);
            });
        });
    });

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

@if(auth()->check() && !auth()->user()->hasAcceptedTerms())
<!-- ========================================================================= -->
<!-- MODAL OBLIGATORIO: ACEPTACIÓN DE TÉRMINOS DE USO & ADVERTENCIA LEGAL -->
<!-- ========================================================================= -->
<div id="mandatory-terms-modal" class="fixed inset-0 z-[99999] flex items-center justify-center p-3 sm:p-5 bg-black/85 backdrop-blur-md">
    <div class="w-full max-w-2xl glass-panel rounded-2xl border-2 border-cyan-500/50 bg-[#061426]/98 p-5 sm:p-7 shadow-[0_0_50px_rgba(34,211,238,0.25)] relative text-left space-y-5 animate-in fade-in zoom-in-95 duration-300 max-h-[95vh] flex flex-col">
        
        <!-- CABECERA DE IMPACTO VISUAL -->
        <div class="flex items-center gap-3 pb-3 border-b border-obsidian-border shrink-0">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-cyan-500/20 to-blue-500/30 border border-cyan-400/60 flex items-center justify-center text-cyan-300 glow-cyan shrink-0 shadow-lg shadow-cyan-500/20">
                <span class="material-symbols-outlined text-3xl animate-pulse">security</span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-mono font-bold bg-amber-950/80 text-amber-400 border border-amber-500/40 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span>
                        CONFIRMACIÓN OBLIGATORIA
                    </span>
                    <span class="text-[10px] font-mono text-cyan-400/80 hidden sm:inline">ATIT VALLE SECO</span>
                </div>
                <h2 class="text-base sm:text-lg font-bold text-white tracking-tight leading-tight mt-0.5">
                    LINEAMIENTOS DE SEGURIDAD & MARCO LEGAL
                </h2>
                <p class="text-[11px] font-mono text-obsidian-muted">
                    Custodia de registros y políticas de confidencialidad institucional
                </p>
            </div>
        </div>

        <!-- CUERPO DE LINEAMIENTOS (SCROLLABLE ELEGANTE) -->
        <div class="flex-1 min-h-0 overflow-y-auto space-y-4 font-mono text-xs leading-relaxed custom-scroll pr-2">
            
            <!-- TARJETA 1: ADVERTENCIA DE SEGURIDAD -->
            <div class="p-4 rounded-xl bg-amber-950/30 border border-amber-500/40 shadow-inner space-y-2 relative overflow-hidden">
                <div class="flex items-center gap-2 text-amber-400 font-bold text-xs sm:text-sm">
                    <span class="material-symbols-outlined text-xl">warning</span>
                    <span>ADVERTENCIA DE SEGURIDAD</span>
                </div>
                <p class="text-amber-100 font-semibold text-xs leading-relaxed">
                    Este BOT y Sistema de Monitoreo están protegidos por un <span class="text-amber-300 underline font-bold">Custodio de Registros</span>.
                </p>
                <p class="text-amber-200/90 text-[11px] leading-relaxed">
                    Toda la información contenida y procesada por este bot y plataforma es de carácter <b>Estrictamente Confidencial</b> y se encuentra amparada bajo rigurosos protocolos de privacidad y protección de datos institucionales.
                </p>
            </div>

            <!-- TARJETA 2: AVISO LEGAL & LEY DE DELITOS INFORMÁTICOS -->
            <div class="p-4 rounded-xl bg-red-950/25 border border-red-500/40 shadow-inner space-y-2.5 relative overflow-hidden">
                <div class="flex items-center gap-2 text-red-400 font-bold text-xs sm:text-sm">
                    <span class="material-symbols-outlined text-xl">gavel</span>
                    <span>AVISO LEGAL & RESPONSABILIDAD PENAL</span>
                </div>
                <p class="text-gray-200 text-[11px] leading-relaxed">
                    Se registran y almacenan los datos (<b>ID de Usuario, Nombre, Dirección IP, Fecha, Hora y acciones/mensajes enviados</b>) en nuestros servidores en caso de utilizar el sistema o bot sin autorización, esto con fines de auditoría y seguridad.
                </p>
                
                <div class="p-3 rounded-lg bg-[#030914]/90 border border-red-500/30 text-[11px] text-red-200 space-y-1">
                    <p class="font-bold flex items-center gap-1.5 text-red-400">
                        <span class="material-symbols-outlined text-base">policy</span>
                        LEY ESPECIAL CONTRA LOS DELITOS INFORMÁTICOS
                    </p>
                    <p class="leading-relaxed">
                        Cualquier <b>ACCESO NO AUTORIZADO</b>, intento de intrusión o uso indebido de la información será sancionado conforme a lo establecido en la Ley Contra los Delitos Informáticos, <b>Capítulos I y II, artículos 6, 7, 8, 9, 10, 11 y 13</b>.
                    </p>
                </div>

                <p class="text-red-300 font-semibold text-[11px]">
                    ⚠️ Si usted no cuenta con autorización para acceder a este bot o utilizar sus servicios, desconéctese y elimine inmediatamente.
                </p>
                
                <p class="text-obsidian-cyan text-[11px] italic font-semibold border-t border-red-500/20 pt-2">
                    La permanencia en este sistema constituye la aceptación plena e incondicional de los términos aquí expuestos.
                </p>
            </div>

        </div>

        <!-- PIE DEL MODAL: CHECKBOX Y BOTONES -->
        <div class="shrink-0 pt-3 border-t border-obsidian-border space-y-3">
            <!-- CHECKBOX DE CONFIRMACIÓN -->
            <label class="flex items-start gap-3 p-3 rounded-xl bg-obsidian-panel/80 border border-cyan-500/30 hover:border-cyan-400 transition cursor-pointer select-none">
                <input type="checkbox" id="chk-accept-terms" onchange="toggleAcceptButtonState()" class="mt-0.5 w-4 h-4 rounded border-cyan-500 text-obsidian-cyan focus:ring-obsidian-cyan bg-[#040c17] cursor-pointer">
                <span class="text-xs text-cyan-200 font-mono leading-relaxed">
                    He leído íntegramente, comprendo y acepto formalmente los <b>Lineamientos de Seguridad</b>, la <b>Custodia de Registros</b> y el <b>Aviso Legal</b> expuestos.
                </span>
            </label>

            <!-- ACCIONES -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-1">
                <!-- BOTÓN SALIR / CERRAR SESIÓN -->
                <form action="{{ route('logout') }}" method="POST" class="w-full sm:w-auto m-0">
                    @csrf
                    <button type="submit" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-red-950/40 border border-red-500/50 text-red-400 hover:bg-red-950 hover:text-white font-mono text-xs font-semibold transition flex items-center justify-center gap-2 cursor-pointer">
                        <span class="material-symbols-outlined text-base">logout</span>
                        <span>Rechazar y Cerrar Sesión</span>
                    </button>
                </form>

                <!-- BOTÓN ACEPTAR FORMALMENTE -->
                <button type="button" id="btn-submit-terms" onclick="submitTermsAcceptance()" disabled 
                        class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-500 text-black font-mono text-xs font-bold transition-all duration-200 flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/20 disabled:opacity-40 disabled:grayscale disabled:cursor-not-allowed cursor-pointer hover:shadow-cyan-500/30 hover:scale-[1.02]">
                    <span class="material-symbols-outlined text-base" id="icon-submit-terms">verified</span>
                    <span id="text-submit-terms">ACEPTAR LINEAMIENTOS Y CONTINUAR</span>
                </button>
            </div>
        </div>

    </div>
</div>

<script>
    function toggleAcceptButtonState() {
        const chk = document.getElementById('chk-accept-terms');
        const btn = document.getElementById('btn-submit-terms');
        if (chk && btn) {
            btn.disabled = !chk.checked;
        }
    }

    async function submitTermsAcceptance() {
        const btn = document.getElementById('btn-submit-terms');
        const icon = document.getElementById('icon-submit-terms');
        const txt = document.getElementById('text-submit-terms');
        const modal = document.getElementById('mandatory-terms-modal');

        btn.disabled = true;
        icon.innerText = 'progress_activity';
        icon.classList.add('animate-spin');
        txt.innerText = 'Registrando Aceptación...';

        try {
            const response = await fetch('{{ route("admin.terms.accept") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ version: '1.0' })
            });

            const data = await response.json();
            if (data.success) {
                modal.classList.add('opacity-0', 'scale-95', 'transition-all', 'duration-300');
                setTimeout(() => {
                    modal.remove();
                }, 300);
            } else {
                alert('⚠️ Error al registrar consentimiento: ' + (data.message || 'Error desconocido'));
                btn.disabled = false;
                icon.innerText = 'verified';
                icon.classList.remove('animate-spin');
                txt.innerText = 'ACEPTAR LINEAMIENTOS Y CONTINUAR';
            }
        } catch (error) {
            alert('Error de red al registrar aceptación: ' + error);
            btn.disabled = false;
            icon.innerText = 'verified';
            icon.classList.remove('animate-spin');
            txt.innerText = 'ACEPTAR LINEAMIENTOS Y CONTINUAR';
        }
    }
</script>
@endif

<!-- ========================================================================= -->
<!-- MODAL: ACERCA DEL SISTEMA                                                  -->
<!-- ========================================================================= -->
<div id="about-system-modal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center p-3 sm:p-5 bg-black/80 backdrop-blur-md transition-all duration-300">
    <div class="w-full max-w-4xl lg:max-w-5xl glass-panel rounded-2xl border border-obsidian-border bg-[#07172b]/98 p-5 sm:p-6 shadow-[0_0_60px_rgba(0,0,0,0.85)] relative text-left space-y-4 animate-in fade-in zoom-in-95 duration-200">
        
        <!-- CABECERA -->
        <div class="flex items-center justify-between pb-3 border-b border-obsidian-border shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-obsidian-cyan/20 to-purple-500/30 border border-obsidian-cyan/50 flex items-center justify-center text-obsidian-cyan glow-cyan shrink-0 p-2 shadow-lg shadow-cyan-500/20">
                    <img src="{{ asset('img/logo.png') }}" alt="Logo" class="w-full h-full object-contain filter drop-shadow-[0_0_6px_rgba(34,211,238,0.6)]" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                    <span class="material-symbols-outlined text-2xl text-obsidian-cyan hidden">info</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[9px] font-mono font-bold bg-cyan-950/80 text-obsidian-cyan border border-cyan-500/40">
                            v3.0.0
                        </span>
                        <span class="px-2 py-0.5 rounded text-[9px] font-mono font-bold {{ config('monitoring.cluster.role') === 'master' ? 'bg-amber-950/80 text-amber-300 border border-amber-500/40' : 'bg-blue-950/80 text-blue-300 border border-blue-500/40' }}">
                            MODO {{ strtoupper(config('monitoring.cluster.role', 'slave')) }}
                        </span>
                    </div>
                    <h2 class="text-base sm:text-lg font-bold text-white tracking-tight leading-tight mt-0.5">
                        Plataforma Integral Valle Seco
                    </h2>
                    <p class="text-[11px] font-mono text-obsidian-muted">
                        Centro de Operaciones y Monitoreo de Infraestructura de Red
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeAboutSystemModal()" class="text-obsidian-muted hover:text-white text-2xl leading-none p-1 transition cursor-pointer" title="Cerrar">&times;</button>
        </div>

        <!-- CUERPO HORIZONTAL EN 2 COLUMNAS (TODO VISIBLE SIN DESPLAZAMIENTO) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 font-mono text-xs">
            <!-- COLUMNA IZQUIERDA: DATOS INSTITUCIONALES (5 cols) -->
            <div class="lg:col-span-5 flex flex-col justify-between space-y-3">
                <!-- TARJETA DE CRÉDITOS Y DESARROLLO -->
                <div class="p-3.5 rounded-xl bg-obsidian-panel/80 border border-obsidian-border space-y-2.5">
                    <div class="text-[10px] font-mono uppercase tracking-wider text-obsidian-cyan font-bold pb-1 border-b border-obsidian-border/40 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">engineering</span>
                        Desarrollo & Créditos
                    </div>
                    <div class="space-y-1.5 text-[11px]">
                        <div class="flex justify-between items-center">
                            <span class="text-obsidian-muted">Desarrollado por:</span>
                            <span class="text-white font-bold">Operador ATIT</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-obsidian-muted">Usuario GitHub:</span>
                            <a href="https://github.com/britojab" target="_blank" class="text-obsidian-cyan hover:underline font-bold">@britojab</a>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-obsidian-muted">Organización:</span>
                            <span class="text-white font-semibold">ATIT Valle Seco</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-obsidian-muted">Período de Desarrollo:</span>
                            <span class="text-amber-300 font-bold">2018 – 2026</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-obsidian-muted">Versión del Sistema:</span>
                            <span class="text-obsidian-cyan font-bold">v3.0.0</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-obsidian-muted">Módulos de la Plataforma:</span>
                            <span class="inline-flex items-center gap-1 font-bold text-slate-200">
                                <span class="px-1.5 py-0.5 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-500/40 text-[10px]">1 / 5</span>
                                <span>Activo</span>
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-obsidian-muted">Módulo Actual:</span>
                            <span class="text-white font-bold">Sistema de Monitoreo</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-obsidian-muted">Licencia:</span>
                            <span class="text-emerald-400 font-semibold">GNU AGPLv3</span>
                        </div>
                    </div>
                </div>

                <!-- AVISO INSTITUCIONAL -->
                <div class="p-3 rounded-xl bg-cyan-950/20 border border-cyan-500/30 text-[10px] text-cyan-200 leading-relaxed font-mono flex items-start gap-2">
                    <span class="material-symbols-outlined text-base text-obsidian-cyan shrink-0 mt-0.5">verified_user</span>
                    <span>Módulo concebido y optimizado para supervisar la continuidad operativa e infraestructura crítica de la red corporativa.</span>
                </div>
            </div>

            <!-- COLUMNA DERECHA: FUNCIONES PRINCIPALES (7 cols) -->
            <div class="lg:col-span-7 space-y-2">
                <div class="text-[10px] font-mono uppercase tracking-wider text-obsidian-cyan font-bold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">hub</span>
                    Funciones Principales del Sistema
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                    <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                        <div class="font-bold text-white flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-cyan-400">monitoring</span>
                            Telemetría & Conectividad
                        </div>
                        <p class="text-obsidian-muted text-[10px] leading-tight">
                            Monitoreo 24/7 de servicios web, DNS, ICMP, sedes remotas y proxies Squid.
                        </p>
                    </div>

                    <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                        <div class="font-bold text-white flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-purple-400">hub</span>
                            Topología & Descubrimiento
                        </div>
                        <p class="text-obsidian-muted text-[10px] leading-tight">
                            Mapeo interactivo de enlaces, detección de anomalías ARP y equipos no autorizados.
                        </p>
                    </div>

                    <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                        <div class="font-bold text-white flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-amber-400">power_settings_new</span>
                            Energía & Control Remoto
                        </div>
                        <p class="text-obsidian-muted text-[10px] leading-tight">
                            Despacho de paquetes Wake-on-LAN (WoL) y terminales web seguras SSH, Telnet y VNC.
                        </p>
                    </div>

                    <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                        <div class="font-bold text-white flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-emerald-400">psychology</span>
                            Motor Local de IA
                        </div>
                        <p class="text-obsidian-muted text-[10px] leading-tight">
                            Asistencia diagnóstica, análisis predictivo de saturación y consultas en lenguaje natural.
                        </p>
                    </div>

                    <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                        <div class="font-bold text-white flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-blue-400">dns</span>
                            SNMP 360° & NCM
                        </div>
                        <p class="text-obsidian-muted text-[10px] leading-tight">
                            Recolección de métricas por interfaces, recepción de trampas UDP 162 y respaldos de configuración.
                        </p>
                    </div>

                    <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                        <div class="font-bold text-white flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-red-400">security</span>
                            Blindaje & Clúster
                        </div>
                        <p class="text-obsidian-muted text-[10px] leading-tight">
                            Replicación Master/Slave, trazabilidad forense, alertas PAM y autenticación LDAP.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- PIE DEL MODAL -->
        <div class="shrink-0 pt-3 border-t border-obsidian-border flex items-center justify-between">
            <a href="{{ route('admin.help.index') }}" class="text-xs font-mono text-obsidian-cyan hover:underline flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">menu_book</span>
                <span>Ir al Manual de Ayuda</span>
            </a>
            <button type="button" onclick="closeAboutSystemModal()" class="px-6 py-2 rounded-xl bg-obsidian-panel border border-obsidian-border text-white hover:bg-obsidian-border text-xs font-mono font-semibold transition cursor-pointer">
                Cerrar
            </button>
        </div>

    </div>
</div>

<script>
    function toggleHelpDropdown(event) {
        if (event) {
            event.stopPropagation();
        }
        const menu = document.getElementById('help-dropdown-menu');
        if (menu) {
            menu.classList.toggle('hidden');
        }
    }

    function openAboutSystemModal() {
        const modal = document.getElementById('about-system-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeAboutSystemModal() {
        const modal = document.getElementById('about-system-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    // Cerrar dropdown al hacer click afuera
    document.addEventListener('click', function(event) {
        const container = document.getElementById('help-menu-container');
        const menu = document.getElementById('help-dropdown-menu');
        if (container && menu && !container.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });

    // Sondeo de telemetría de UPS para actualizar el icono y tooltip del Topbar
    function pollTopBarUps() {
        fetch('{{ route("admin.ups.live") }}', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || !data.success || !data.device) return;
            var dev = data.device;
            var comp = data.computed || {};
            var isBat = !!dev.is_on_battery;
            var batPct = Number(dev.battery_percent || 100);

            var btn = document.getElementById('topbar-ups-btn');
            var icon = document.getElementById('topbar-ups-icon');
            if (btn && icon) {
                if (isBat) {
                    btn.className = 'relative flex items-center justify-center w-8 h-8 rounded-full border cursor-pointer transition-all duration-200 bg-red-950/80 text-red-400 border-red-500/60 animate-pulse shadow-md shadow-red-500/30';
                    icon.innerText = 'battery_alert';
                    icon.className = 'material-symbols-outlined text-base text-red-400';
                } else {
                    btn.className = 'relative flex items-center justify-center w-8 h-8 rounded-full border cursor-pointer transition-all duration-200 ' + (
                        batPct < 25 ? 'bg-amber-950/80 text-amber-400 border-amber-500/60 shadow-md shadow-amber-500/30' : 'bg-cyan-950/60 text-cyan-300 border-cyan-500/50 hover:bg-cyan-900/60 hover:text-white shadow-sm shadow-cyan-500/20'
                    );
                    icon.innerText = batPct >= 95 ? 'battery_charging_full' : 'battery_std';
                    icon.className = 'material-symbols-outlined text-base text-cyan-400';
                }
            }

            var elStatusBadge = document.getElementById('topbar-ups-status-badge');
            if (elStatusBadge) {
                elStatusBadge.innerText = isBat ? 'Modo Batería' : 'Red Normal';
                elStatusBadge.className = 'px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider ' + (isBat ? 'bg-red-950 text-red-400 border border-red-500/60 animate-pulse' : 'bg-emerald-950 text-emerald-400 border border-emerald-500/60');
            }
            var elBatPct = document.getElementById('topbar-ups-bat-pct');
            if (elBatPct) elBatPct.innerText = batPct + '%';
            var elBatBar = document.getElementById('topbar-ups-bat-bar');
            if (elBatBar) elBatBar.style.width = batPct + '%';
            var elRuntime = document.getElementById('topbar-ups-runtime');
            if (elRuntime) elRuntime.innerText = '~' + (comp.estimated_runtime_human || comp.estimated_runtime_minutes + ' min');
            var elInV = document.getElementById('topbar-ups-in-v');
            if (elInV) elInV.innerHTML = Number(dev.input_voltage || 0).toFixed(1) + ' <span class="text-[9px] text-obsidian-muted">VAC</span>';
            var elOutV = document.getElementById('topbar-ups-out-v');
            if (elOutV) elOutV.innerHTML = Number(dev.output_voltage || 0).toFixed(1) + ' <span class="text-[9px] text-obsidian-muted">VAC</span>';
            var elLoadPct = document.getElementById('topbar-ups-load-pct');
            if (elLoadPct) elLoadPct.innerText = (dev.load_percent || 0) + '%';
            var elLoadWatts = document.getElementById('topbar-ups-load-watts');
            if (elLoadWatts) elLoadWatts.innerText = '~' + (comp.load_watts || 0) + ' W';
            var elTemp = document.getElementById('topbar-ups-temp');
            if (elTemp) elTemp.innerText = Number(dev.temperature_c || 40).toFixed(1) + ' °C';
        })
        .catch(function() {});
    }

    document.addEventListener('DOMContentLoaded', function() {
        setInterval(pollTopBarUps, 15000);
    });
</script>
@endsection
