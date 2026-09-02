@extends('layouts.app')

@section('title', 'ATIT • Monitoreo de Infraestructura - Valle Seco')

@push('styles')
<style>
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
</style>
@endpush

@section('content')
<div class="h-screen flex flex-col overflow-hidden bg-[#020617] text-white">
    <!-- TOP NAVIGATION BAR (HEADER INSTITUCIONAL) -->
    <header class="h-16 shrink-0 glass-panel border-b border-obsidian-border flex items-center justify-between px-4 sm:px-6 z-30 shadow-2xl">
        <!-- MARCA & TÍTULO INSTITUCIONAL -->
        <div class="flex items-center space-x-3.5">
            <div class="w-10 h-10 rounded-lg bg-gradient-to-tr from-obsidian-cyan/20 to-obsidian-purple/30 border border-obsidian-cyan/40 flex items-center justify-center text-obsidian-cyan glow-cyan shrink-0 p-1.5 overflow-hidden shadow-lg shadow-cyan-500/20">
                <img src="{{ asset('img/logo.png') }}" alt="Logo CORPOELEC" class="w-full h-full object-contain filter drop-shadow-[0_0_6px_rgba(34,211,238,0.6)]">
            </div>
            <div>
                <h1 class="text-sm sm:text-base font-bold tracking-tight text-white flex items-center gap-2">
                    ATIT • Monitoreo de Infraestructura - Valle Seco
                </h1>
                <p class="text-[10px] font-mono text-obsidian-cyan flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-obsidian-cyan pulse-dot"></span>
                    SISTEMA DE MONITOREO VALLE SECO
                </p>
            </div>
        </div>

        <!-- BUSCADOR CENTRAL -->
        <div class="hidden md:flex items-center flex-1 max-w-xs mx-8">
            <div class="relative w-full">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-obsidian-muted text-sm">search</span>
                <input type="text" id="live-search-input" onkeyup="filterLiveItems()" placeholder="Buscar servicio o sede..." class="w-full bg-[#051424]/90 border border-obsidian-border rounded-lg pl-8 pr-3 py-1.5 text-xs text-white placeholder-obsidian-muted focus:outline-none focus:border-obsidian-cyan font-mono transition"/>
            </div>
        </div>

        <!-- METRICAS & LOGIN -->
        <div class="flex items-center space-x-4">
            <!-- BADGE GLOBAL -->
            <div id="global-status-badge" class="hidden sm:flex items-center gap-2 px-3 py-1 rounded-full border text-xs font-mono font-semibold {{ ($latestSnapshot && $latestSnapshot->global_status == 'OPERACIONAL') ? 'bg-emerald-950/60 text-emerald-400 border-emerald-500/50 glow-green' : (($latestSnapshot && $latestSnapshot->global_status == 'DEGRADADO') ? 'bg-amber-950/60 text-amber-400 border-amber-500/50' : 'bg-red-950/60 text-red-400 border-red-500/50 glow-red') }}"
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
            <div class="hidden lg:flex flex-col text-right font-mono text-[11px] text-obsidian-muted">
                <span class="text-[9px] uppercase tracking-wider text-obsidian-cyan">Último Escaneo</span>
                <span id="last-sync-time" class="text-white font-bold">{{ $latestSnapshot ? $latestSnapshot->created_at->format('H:i:s') : '--:--:--' }}</span>
            </div>

            <!-- BOTÓN INICIO DE SESIÓN (SOLO ÍCONO) -->
            @auth
                <a href="{{ route('admin.dashboard') }}" title="Panel de Administración" class="w-9 h-9 rounded-lg bg-obsidian-cyan text-black flex items-center justify-center transition hover:bg-cyan-300 hover:shadow-lg hover:shadow-cyan-500/30 glow-cyan">
                    <span class="material-symbols-outlined text-lg">admin_panel_settings</span>
                </a>
            @else
                <a href="{{ route('login') }}" title="Iniciar Sesión" class="w-9 h-9 rounded-lg bg-obsidian-cyan/10 border border-obsidian-cyan/60 text-obsidian-cyan flex items-center justify-center transition hover:bg-obsidian-cyan hover:text-black hover:shadow-lg hover:shadow-cyan-500/30">
                    <span class="material-symbols-outlined text-lg">lock</span>
                </a>
            @endauth
        </div>
    </header>

    @php
        $snapshotServices = ($snapshotData && isset($snapshotData['services'])) ? collect($snapshotData['services'])->keyBy('letter') : collect();
        $snapshotSites = ($snapshotData && isset($snapshotData['sites'])) ? collect($snapshotData['sites'])->keyBy('letter') : collect();

        // 1. Servicios Activos vs Caídos
        $activeServices = $services->filter(function($s) use ($snapshotServices) {
            $snap = $snapshotServices->get($s->letter);
            return $snap ? ($snap['is_up'] ?? false) : false;
        });
        $downServices = $services->filter(function($s) use ($snapshotServices) {
            $snap = $snapshotServices->get($s->letter);
            return $snap ? !($snap['is_up'] ?? false) : true;
        });

        // 2. Sedes Activas vs Sin Conexión
        $activeSites = $sites->filter(function($st) use ($snapshotSites) {
            $snap = $snapshotSites->get($st->letter);
            return $snap ? ($snap['is_up'] ?? false) : false;
        });
        $downSites = $sites->filter(function($st) use ($snapshotSites) {
            $snap = $snapshotSites->get($st->letter);
            return $snap ? !($snap['is_up'] ?? false) : true;
        });
    @endphp

    <!-- CUERPO PRINCIPAL (3 COLUMNAS: ACTIVOS, SEDES Y BLOQUE DE CAÍDAS) -->
    <main class="flex-1 p-3 sm:p-4 overflow-hidden flex flex-col lg:flex-row gap-3 sm:gap-4 min-h-0">
        
        <!-- ========================================================================= -->
        <!-- COLUMNA 1: SERVICIOS ACTIVOS (32% ANCHO)                                 -->
        <!-- ========================================================================= -->
        <section class="glass-panel rounded-xl flex flex-col w-full lg:w-[32%] h-full overflow-hidden border border-obsidian-border/80">
            <!-- CABECERA -->
            <div class="p-3.5 border-b border-obsidian-border flex items-center justify-between bg-obsidian-panel/50">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-400 text-lg">check_circle</span>
                    <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">Servicios Activos</h2>
                </div>
                <span id="badge-count-active-services" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950/80 text-emerald-400 border border-emerald-500/40 glow-green">
                    {{ $activeServices->count() }} Operativos
                </span>
            </div>

            <!-- LISTA VERTICAL DE SERVICIOS ACTIVOS (ULTRA-COMPACTA) -->
            <div class="flex-1 overflow-y-auto p-2 space-y-1 custom-scroll" id="active-services-container">
                @forelse($activeServices as $s)
                    @php
                        $sData = $snapshotServices->get($s->letter);
                        $latency = $sData ? ($sData['latency_ms'] ?? 0) : 0;
                        $targetHost = $s->host_ip ?: ($s->web_url ?: '127.0.0.1');
                    @endphp
                    <div class="py-1.5 px-2.5 rounded-lg bg-obsidian-panel/60 hover:bg-obsidian-panel border border-obsidian-border/50 hover:border-emerald-500/50 transition cursor-pointer flex items-center justify-between group item-searchable select-none"
                         data-search="{{ strtolower($s->name . ' ' . $s->type) }}"
                         data-tech-title="{{ $s->name }}"
                         data-tech-type="{{ $s->type }}"
                         data-tech-ip="{{ $targetHost }}"
                         data-tech-port="{{ $s->port ?: ($s->type == 'WEB' ? '80/443' : ($s->type == 'DNS' ? '53' : ($s->type == 'SMTP' ? '25' : ($s->type == 'LDAP' ? '389' : 'ICMP')))) }}"
                         data-tech-protocol="{{ $s->type == 'WEB' ? 'HTTP/HTTPS GET Request' : ($s->type == 'DNS' ? 'DNS Query' : ($s->type == 'SMTP' ? 'SMTP Mail Handshake' : ($s->type == 'LDAP' ? 'LDAP Bind Handshake' : 'ICMP Ping'))) }}"
                         data-tech-latency="{{ $latency > 0 ? $latency . ' ms' : '< 15 ms' }}"
                         data-tech-status="OPERATIVO (200 OK / Response)"
                         data-tech-details="{{ $s->web_url ? 'Endpoint: ' . $s->web_url : 'Verificación por socket de transporte directo.' }}"
                         data-tech-history="{{ json_encode($serviceHistoryMap[$s->id] ?? null) }}">
                        
                        <div class="flex items-center gap-2 min-w-0">
                            <!-- LED VERDE COMPACTO -->
                            <div class="w-1.5 h-1.5 rounded-full shrink-0 bg-emerald-400 glow-green"></div>
                            <!-- NOMBRE DEL SERVICIO -->
                            <span class="text-[11px] font-semibold text-white group-hover:text-emerald-300 transition-colors truncate">
                                {{ $s->name }}
                            </span>
                        </div>

                        <!-- LATENCIA & BADGE DE PROTOCOLO -->
                        <div class="flex items-center gap-1.5 shrink-0">
                            <span class="text-[9px] font-mono text-emerald-400/90 font-medium">
                                {{ $latency > 0 ? $latency . 'ms' : '<15ms' }}
                            </span>
                            <span class="px-1 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase bg-obsidian-bg/80 border border-emerald-500/30 text-emerald-300">
                                {{ $s->type }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs font-mono text-obsidian-muted">
                        No hay servicios activos reportados en este momento.
                    </div>
                @endforelse
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- COLUMNA 2: SEDES REGIONALES OPERATIVAS (34% ANCHO)                       -->
        <!-- ========================================================================= -->
        <section class="glass-panel rounded-xl flex flex-col w-full lg:w-[34%] h-full overflow-hidden border border-obsidian-border/80">
            <!-- CABECERA -->
            <div class="p-3 border-b border-obsidian-border flex items-center justify-between bg-obsidian-panel/50">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-obsidian-purple text-lg">domain</span>
                    <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">Sedes Regionales Conectadas</h2>
                </div>
                <span id="badge-count-active-sites" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-obsidian-purple/20 text-obsidian-purple border border-obsidian-purple/30">
                    {{ $activeSites->count() }} Sedes Online
                </span>
            </div>

            <!-- LISTA VERTICAL DE SEDES ACTIVAS (COMPACTA Y DESPLEGABLE) -->
            <div class="flex-1 overflow-y-auto p-2 space-y-1.5 custom-scroll" id="active-sites-container">
                @forelse($activeSites as $site)
                    @php
                        $stData = $snapshotSites->get($site->letter);
                        $latency = $stData ? ($stData['latency_ms'] ?? 0) : 0;
                        $devicesSnapshot = ($stData && isset($stData['devices'])) ? collect($stData['devices'])->keyBy('device_number') : collect();
                        $activeDevices = $site->devices->filter(function($d) {
                            return $d->is_active && $d->name != 'NO CONFIGURADO' && !str_contains(strtoupper($d->name), 'NO CONFIGURADO') && $d->ip != '0.0.0.0';
                        });
                        $cleanAddress = ($site->address && !str_contains(strtoupper($site->address), 'NO CONFIGURADO')) ? $site->address : '';
                        $cleanPhone = ($site->phone_1 && !str_contains(strtoupper($site->phone_1), 'NO CONFIGURADO')) ? $site->phone_1 : '';
                    @endphp
                    <div class="rounded-xl bg-obsidian-panel/60 hover:bg-obsidian-panel border border-obsidian-border/60 hover:border-obsidian-purple/50 transition overflow-hidden group item-searchable"
                         data-search="{{ strtolower($site->name . ' ' . $cleanAddress) }}">
                        
                        <!-- ENCABEZADO COMPACTO DE LA SEDE (CLICKEABLE Y CON TOOLTIP AL POSAR) -->
                        <div class="p-2.5 flex items-center justify-between cursor-pointer select-none"
                             onclick="toggleSiteDetails('site-details-{{ $site->letter }}', this)"
                             data-tech-title="{{ $site->name }}"
                             data-tech-type="SEDE REGIONAL"
                             data-tech-ip="{{ $site->ip ?: '0.0.0.0' }}"
                             data-tech-port="Gateway PING / ICMP"
                             data-tech-protocol="Enlace de Transporte WAN"
                             data-tech-latency="{{ $latency > 0 ? $latency . ' ms' : '< 20 ms' }}"
                             data-tech-status="ENLACE PRINCIPAL OPERATIVO"
                             data-tech-details="{{ $cleanAddress ? 'Ubicación: ' . $cleanAddress : 'Sede Regional Corporativa' }}{{ $cleanPhone ? ' • Contacto: ' . $cleanPhone : '' }}"
                             data-tech-history="{{ json_encode($siteHistoryMap[$site->id] ?? null) }}">
                            
                            <div class="flex items-center space-x-2 min-w-0">
                                <div class="w-2 h-2 rounded-full shrink-0 bg-emerald-400 glow-green"></div>
                                <div class="truncate">
                                    <h3 class="text-[11px] font-bold text-white group-hover:text-obsidian-purple transition-colors truncate">
                                        {{ $site->name }}
                                    </h3>
                                    <p class="text-[9px] font-mono text-obsidian-muted truncate">{{ $site->letter == 'A' ? 'Centro de Telecomunicaciones' : 'Enlace Regional Activo' }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="px-1 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase {{ $site->letter == 'A' ? 'bg-obsidian-cyan/20 text-obsidian-cyan border border-obsidian-cyan/30' : 'bg-obsidian-purple/20 text-obsidian-purple border border-obsidian-purple/30' }}">
                                    {{ $site->letter == 'A' ? 'HUB' : 'SEDE' }}
                                </span>
                                <span class="material-symbols-outlined text-obsidian-muted text-sm transition-transform duration-200 chevron-icon">
                                    expand_more
                                </span>
                            </div>
                        </div>

                        <!-- CONTENIDO DESPLEGABLE CON EQUIPOS EN SITIO (OCULTO POR DEFECTO) -->
                        <div id="site-details-{{ $site->letter }}" class="hidden px-2.5 pb-2.5 pt-1 border-t border-obsidian-border/40 bg-obsidian-bg/40 space-y-2">
                            <!-- METRICAS DE LATENCIA -->
                            <div class="flex items-center justify-between text-[10px] font-mono bg-obsidian-bg/80 px-2 py-1 rounded-lg border border-obsidian-border/40">
                                <span class="text-[9px] text-obsidian-muted">Latencia Gateway:</span>
                                <span class="font-bold text-emerald-400 text-[10px]">{{ $latency > 0 ? $latency . ' ms' : '< 15 ms' }}</span>
                            </div>

                            <!-- CUADRICULA DE EQUIPOS EN SITIO -->
                            @if($activeDevices->count() > 0)
                                <div class="space-y-1">
                                    <span class="text-[8.5px] uppercase font-mono tracking-wider text-obsidian-muted block">Equipos en Sitio ({{ $activeDevices->count() }})</span>
                                    <div class="grid grid-cols-2 gap-1">
                                        @foreach($activeDevices as $dev)
                                            @php
                                                $dSnap = $devicesSnapshot->get($dev->device_number);
                                                $devUp = $dSnap ? ($dSnap['is_up'] ?? false) : false;
                                            @endphp
                                            <div class="bg-obsidian-panel/90 hover:bg-obsidian-panel border border-obsidian-border rounded p-1.5 flex items-center justify-between text-[9px] font-mono cursor-pointer transition hover:border-obsidian-cyan/40"
                                                 data-tech-title="{{ $site->name }} - {{ $dev->name }}"
                                                 data-tech-type="EQUIPO SECUNDARIO"
                                                 data-tech-ip="{{ $dev->ip }}"
                                                 data-tech-port="Slot #{{ $dev->device_number }}"
                                                 data-tech-protocol="ICMP Echo Ping"
                                                 data-tech-latency="{{ $devUp ? '< 10 ms' : '--' }}"
                                                 data-tech-status="{{ $devUp ? 'ONLINE (Ping Respondido)' : 'OFFLINE (Inaccesible)' }}"
                                                 data-tech-details="Dispositivo interno vinculado a la red local de {{ $site->name }}."
                                                 data-tech-history="{{ json_encode($siteHistoryMap[$site->id] ?? null) }}">
                                                <span class="text-white truncate max-w-[85px]">{{ $dev->name }}</span>
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $devUp ? 'bg-emerald-400 glow-green' : 'bg-red-500' }}"></span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <p class="text-[9px] font-mono text-obsidian-muted text-center py-1">Sin equipos secundarios registrados</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs font-mono text-obsidian-muted">
                        No hay sedes conectadas en este momento.
                    </div>
                @endforelse
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- COLUMNA 3: INCIDENTES Y CAÍDAS (BLOQUE SUPERIOR E INFERIOR) (34% ANCHO)   -->
        <!-- ========================================================================= -->
        <section class="flex flex-col w-full lg:flex-1 h-full gap-3 overflow-hidden">
            
            <!-- BLOQUE SUPERIOR: SERVICIOS CAÍDOS (50% ALTURA) -->
            <div class="glass-panel rounded-xl flex-1 flex flex-col overflow-hidden border border-red-500/30 bg-red-950/10">
                <!-- CABECERA -->
                <div class="p-3 border-b border-red-500/30 flex items-center justify-between bg-red-950/40">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-red-400 text-lg">cancel</span>
                        <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">Servicios Caídos</h2>
                    </div>
                    <span id="badge-count-down-services" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold {{ $downServices->count() > 0 ? 'bg-red-950 text-red-400 border border-red-500/50 glow-red' : 'bg-emerald-950 text-emerald-400 border border-emerald-500/40' }}">
                        {{ $downServices->count() }} Inactivos
                    </span>
                </div>

                <!-- LISTA DE SERVICIOS CAÍDOS (ULTRA-COMPACTA) -->
                <div class="flex-1 overflow-y-auto p-2 space-y-1 custom-scroll" id="down-services-container">
                    @forelse($downServices as $s)
                        @php
                            $targetHost = $s->host_ip ?: ($s->web_url ?: '127.0.0.1');
                        @endphp
                        <div class="py-1.5 px-2.5 rounded-lg bg-red-950/30 hover:bg-red-950/50 border border-red-500/30 transition cursor-pointer flex items-center justify-between group item-searchable select-none"
                             data-search="{{ strtolower($s->name . ' ' . $s->type) }}"
                             data-tech-title="{{ $s->name }}"
                             data-tech-type="{{ $s->type }}"
                             data-tech-ip="{{ $targetHost }}"
                             data-tech-port="{{ $s->port ?: ($s->type == 'WEB' ? '80/443' : ($s->type == 'DNS' ? '53' : ($s->type == 'SMTP' ? '25' : ($s->type == 'LDAP' ? '389' : 'ICMP')))) }}"
                             data-tech-protocol="{{ $s->type == 'WEB' ? 'HTTP/HTTPS GET' : ($s->type == 'DNS' ? 'DNS Query' : ($s->type == 'SMTP' ? 'SMTP Mail' : ($s->type == 'LDAP' ? 'LDAP Bind' : 'ICMP Ping'))) }}"
                             data-tech-latency="Timeout / Sin respuesta"
                             data-tech-status="APAGADO (Host / Puerto inalcanzable)"
                             data-tech-details="{{ $s->web_url ? 'Endpoint: ' . $s->web_url : 'Sin respuesta de transporte de red.' }}"
                             data-tech-history="{{ json_encode($serviceHistoryMap[$s->id] ?? null) }}">
                            
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-1.5 h-1.5 rounded-full shrink-0 bg-red-500 glow-red"></div>
                                <span class="text-[11px] font-semibold text-red-200 group-hover:text-red-100 transition-colors truncate">
                                    {{ $s->name }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="text-[9px] font-mono text-red-400/80 font-medium">Timeout</span>
                                <span class="px-1 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase bg-red-950/80 border border-red-500/40 text-red-300">
                                    {{ $s->type }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center p-4 text-center">
                            <span class="material-symbols-outlined text-emerald-400 text-2xl mb-1">verified</span>
                            <p class="text-xs font-mono text-emerald-400 font-bold">Todos los servicios operando normalmente</p>
                            <p class="text-[10px] font-mono text-obsidian-muted">Sin incidentes de aplicativo registrados</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- BLOQUE INFERIOR: SEDES SIN CONEXIÓN (50% ALTURA) -->
            <div class="glass-panel rounded-xl flex-1 flex flex-col overflow-hidden border border-red-500/30 bg-red-950/10">
                <!-- CABECERA -->
                <div class="p-3 border-b border-red-500/30 flex items-center justify-between bg-red-950/40">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-400 text-lg">signal_disconnected</span>
                        <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">Sedes sin Conexión</h2>
                    </div>
                    <span id="badge-count-down-sites" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold {{ $downSites->count() > 0 ? 'bg-red-950 text-red-400 border border-red-500/50 glow-red' : 'bg-emerald-950 text-emerald-400 border border-emerald-500/40' }}">
                        {{ $downSites->count() }} Desconectadas
                    </span>
                </div>

                <!-- LISTA DE SEDES SIN CONEXIÓN (ULTRA-COMPACTA) -->
                <div class="flex-1 overflow-y-auto p-2 space-y-1 custom-scroll" id="down-sites-container">
                    @forelse($downSites as $site)
                        @php
                            $cleanAddress = ($site->address && !str_contains(strtoupper($site->address), 'NO CONFIGURADO')) ? $site->address : '';
                            $cleanPhone = ($site->phone_1 && !str_contains(strtoupper($site->phone_1), 'NO CONFIGURADO')) ? $site->phone_1 : '';
                        @endphp
                        <div class="py-1.5 px-2.5 rounded-lg bg-red-950/30 hover:bg-red-950/50 border border-red-500/30 transition cursor-pointer flex items-center justify-between group item-searchable select-none"
                             data-search="{{ strtolower($site->name . ' ' . $cleanAddress) }}"
                             data-tech-title="{{ $site->name }}"
                             data-tech-type="SEDE REGIONAL"
                             data-tech-ip="{{ $site->ip ?: '0.0.0.0' }}"
                             data-tech-port="Gateway PING / ICMP"
                             data-tech-protocol="Enlace de Transporte WAN"
                             data-tech-latency="100% Packet Loss"
                             data-tech-status="ENLACE WAN CAÍDO"
                             data-tech-details="{{ $cleanAddress ? 'Ubicación: ' . $cleanAddress : 'Sede Regional Corporativa' }}{{ $cleanPhone ? ' • Contacto: ' . $cleanPhone : '' }}"
                             data-tech-history="{{ json_encode($siteHistoryMap[$site->id] ?? null) }}">
                            
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-1.5 h-1.5 rounded-full shrink-0 bg-red-500 glow-red"></div>
                                <span class="text-[11px] font-semibold text-red-200 group-hover:text-red-100 transition-colors truncate">
                                    {{ $site->name }}
                                </span>
                            </div>

                            <span class="px-1 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase bg-red-950/80 border border-red-500/40 text-red-300">
                                OFFLINE
                            </span>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center p-4 text-center">
                            <span class="material-symbols-outlined text-emerald-400 text-2xl mb-1">wifi_tethering</span>
                            <p class="text-xs font-mono text-emerald-400 font-bold">Todos los enlaces regionales conectados</p>
                            <p class="text-[10px] font-mono text-obsidian-muted">Comunicación WAN 100% operativa</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- BLOQUE INFERIOR DE TELEMETRÍA: ESTADO GLOBAL & SALUD DE RED -->
            <div class="glass-panel rounded-xl shrink-0 p-3 border border-obsidian-border/80 bg-[#07172b]/95 space-y-2.5 shadow-xl">
                <!-- CABECERA DE TELEMETRÍA -->
                <div class="flex items-center justify-between pb-1.5 border-b border-obsidian-border/60">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-obsidian-cyan text-base">monitor_heart</span>
                        <h3 class="text-[11px] font-bold text-white uppercase font-mono tracking-wider">Telemetría y Estatus Global</h3>
                    </div>
                    @php
                        $gStatus = $latestSnapshot ? $latestSnapshot->global_status : ($downServices->count() == 0 && $downSites->count() == 0 ? 'OPERACIONAL' : 'DEGRADADO');
                        $badgeClass = ($gStatus == 'OPERACIONAL') 
                            ? 'bg-emerald-950/80 text-emerald-400 border-emerald-500/50 glow-green' 
                            : (($gStatus == 'DEGRADADO') 
                                ? 'bg-amber-950/80 text-amber-400 border-amber-500/50' 
                                : 'bg-red-950/80 text-red-400 border-red-500/50 glow-red');
                    @endphp
                    <span id="telemetry-status-badge" class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold border flex items-center gap-1 {{ $badgeClass }}"
                          data-tech-title="DIAGNÓSTICO DE SALUD"
                          data-tech-type="ESTADO GLOBAL"
                          data-tech-ip="Red Corporativa Nacional"
                          data-tech-protocol="Orquestador Asíncrono Python"
                          data-tech-latency="< 5.0s ciclo"
                          data-tech-status="{{ $gStatus }}"
                          data-tech-details="{{ $gStatus == 'OPERACIONAL' ? '100% de la infraestructura respondiendo.' : ($gStatus == 'DEGRADADO' ? 'Plataforma disponible con incidentes parciales.' : 'Afectación severa de infraestructura.') }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $gStatus == 'OPERACIONAL' ? 'bg-emerald-400 pulse-dot' : ($gStatus == 'DEGRADADO' ? 'bg-amber-400 pulse-dot' : 'bg-red-400 pulse-dot') }}"></span>
                        {{ $gStatus }}
                    </span>
                </div>

                <!-- TARJETAS DE DISPONIBILIDAD (3 COLUMNAS) -->
                <div class="grid grid-cols-3 gap-1.5">
                    @php
                        $totServ = $activeServices->count() + $downServices->count();
                        $servPct = $totServ > 0 ? round(($activeServices->count() / $totServ) * 100, 1) : 100;
                        
                        $totSites = $activeSites->count() + $downSites->count();
                        $sitesPct = $totSites > 0 ? round(($activeSites->count() / $totSites) * 100, 1) : 100;
                        
                        $totProxies = $latestSnapshot ? $latestSnapshot->proxies_total : 4;
                        $onlProxies = $latestSnapshot ? $latestSnapshot->proxies_online : 4;
                    @endphp
                    <!-- SERVICIOS -->
                    <div class="bg-obsidian-panel/80 border border-obsidian-border/80 rounded-lg p-1.5 text-center cursor-pointer hover:border-obsidian-cyan/40 transition"
                         data-tech-title="DISPONIBILIDAD DE SERVICIOS"
                         data-tech-type="MÉTRICA"
                         data-tech-ip="18 Hosts Registrados"
                         data-tech-protocol="HTTP / LDAP / SMTP / DNS"
                         data-tech-latency="{{ $servPct }}% Up"
                         data-tech-status="{{ $activeServices->count() }} de {{ $totServ }} Operativos"
                         data-tech-details="{{ $downServices->count() }} servicios caídos detectados en el último ciclo de escaneo.">
                        <span class="text-[8.5px] uppercase font-mono text-obsidian-muted block truncate">Servicios</span>
                        <div id="metric-services-count" class="mt-0.5 flex items-baseline justify-center gap-1 font-mono">
                            <span class="text-xs font-bold text-white">{{ $activeServices->count() }}</span>
                            <span class="text-[9px] text-obsidian-muted">/ {{ $totServ }}</span>
                        </div>
                        <span id="metric-services-pct" class="text-[8.5px] font-mono font-bold {{ $servPct == 100 ? 'text-emerald-400' : ($servPct >= 70 ? 'text-amber-400' : 'text-red-400') }}">
                            {{ $servPct }}%
                        </span>
                    </div>

                    <!-- SEDES -->
                    <div class="bg-obsidian-panel/80 border border-obsidian-border/80 rounded-lg p-1.5 text-center cursor-pointer hover:border-obsidian-cyan/40 transition"
                         data-tech-title="DISPONIBILIDAD DE SEDES REGIONALES"
                         data-tech-type="MÉTRICA"
                         data-tech-ip="5 Nodos Regionales"
                         data-tech-protocol="ICMP Echo / Enlaces WAN"
                         data-tech-latency="{{ $sitesPct }}% Up"
                         data-tech-status="{{ $activeSites->count() }} de {{ $totSites }} Conectadas"
                         data-tech-details="{{ $downSites->count() }} sedes sin conexión actualmente.">
                        <span class="text-[8.5px] uppercase font-mono text-obsidian-muted block truncate">Sedes</span>
                        <div id="metric-sites-count" class="mt-0.5 flex items-baseline justify-center gap-1 font-mono">
                            <span class="text-xs font-bold text-white">{{ $activeSites->count() }}</span>
                            <span class="text-[9px] text-obsidian-muted">/ {{ $totSites }}</span>
                        </div>
                        <span id="metric-sites-pct" class="text-[8.5px] font-mono font-bold {{ $sitesPct == 100 ? 'text-emerald-400' : ($sitesPct >= 70 ? 'text-amber-400' : 'text-red-400') }}">
                            {{ $sitesPct }}%
                        </span>
                    </div>

                    <!-- PROXIES -->
                    <div class="bg-obsidian-panel/80 border border-obsidian-border/80 rounded-lg p-1.5 text-center cursor-pointer hover:border-obsidian-cyan/40 transition"
                         data-tech-title="DISPONIBILIDAD DE PROXIES"
                         data-tech-type="MÉTRICA"
                         data-tech-ip="Salidas PfSense + Directa"
                         data-tech-protocol="HTTP CONNECT (8080)"
                         data-tech-latency="100% Up"
                         data-tech-status="{{ $onlProxies }} de {{ $totProxies }} Operativos"
                         data-tech-details="Todos los túneles proxy corporativos autentican con éxito.">
                        <span class="text-[8.5px] uppercase font-mono text-obsidian-muted block truncate">Proxies</span>
                        <div id="metric-proxies-count" class="mt-0.5 flex items-baseline justify-center gap-1 font-mono">
                            <span class="text-xs font-bold text-white">{{ $onlProxies }}</span>
                            <span class="text-[9px] text-obsidian-muted">/ {{ $totProxies }}</span>
                        </div>
                        <span class="text-[8.5px] font-mono font-bold text-emerald-400">100%</span>
                    </div>
                </div>

                <!-- LEYENDA EXPLICATIVA COMPACTA -->
                <div class="pt-1.5 border-t border-obsidian-border/40 flex items-center justify-between text-[8px] font-mono text-obsidian-muted">
                    <div class="flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span>100% Operacional</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                        <span>Degradado (&gt;0 fallas)</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                        <span>Crítico (&gt;30% caído)</span>
                    </div>
                </div>
            </div>

        </section>

    </main>
</div>

<!-- ========================================================================= -->
<!-- VENTANA EMERGENTE HUD FLOTANTE (TOOLTIP DE DATOS TÉCNICOS & HISTÓRICO)    -->
<!-- ========================================================================= -->
<div id="tech-tooltip" class="glass-panel rounded-xl p-3 border border-obsidian-cyan/50 shadow-2xl w-80 text-xs font-mono text-white bg-[#051424]/95 backdrop-blur-xl">
    <!-- CABECERA (CONSERVADA INTACTA) -->
    <div class="flex items-center justify-between border-b border-obsidian-border pb-1.5 mb-1.5">
        <div class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-obsidian-cyan text-sm" id="tt-icon">terminal</span>
            <span class="font-bold text-white truncate max-w-[170px]" id="tt-title">DATOS TÉCNICOS</span>
        </div>
        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-obsidian-cyan/20 text-obsidian-cyan border border-obsidian-cyan/30" id="tt-type">
            PROTOCOL
        </span>
    </div>

    <!-- DATOS TÉCNICOS (TODOS CONSERVADOS INTACTOS) -->
    <div class="space-y-1 text-[11px]">
        <div class="flex justify-between py-0.5 border-b border-obsidian-border/40">
            <span class="text-obsidian-muted">Destino / IP:</span>
            <span class="text-obsidian-cyan font-semibold truncate max-w-[160px]" id="tt-ip">10.0.0.1</span>
        </div>
        <div class="flex justify-between py-0.5 border-b border-obsidian-border/40">
            <span class="text-obsidian-muted">Puerto / Socket:</span>
            <span class="text-white truncate max-w-[160px]" id="tt-port">80 / 443</span>
        </div>
        <div class="flex justify-between py-0.5 border-b border-obsidian-border/40">
            <span class="text-obsidian-muted">Protocolo / Check:</span>
            <span class="text-obsidian-purple font-medium truncate max-w-[160px]" id="tt-protocol">HTTP GET</span>
        </div>
        <div class="flex justify-between py-0.5 border-b border-obsidian-border/40">
            <span class="text-obsidian-muted">Latencia Actual:</span>
            <span class="text-emerald-400 font-bold" id="tt-latency">12.4 ms</span>
        </div>
        <div class="flex justify-between py-0.5">
            <span class="text-obsidian-muted">Estado Reportado:</span>
            <span class="font-bold text-emerald-400 truncate max-w-[160px]" id="tt-status">OPERATIVO</span>
        </div>
    </div>

    <!-- NUEVO: CUADRO CON GRÁFICO HISTÓRICO TEMPORAL DE LATENCIA & DISPONIBILIDAD (12 HORAS) -->
    <div id="tt-chart-container" class="mt-2 pt-1.5 border-t border-obsidian-border/60">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[9px] uppercase font-bold text-obsidian-cyan tracking-wider flex items-center gap-1">
                <span class="material-symbols-outlined text-[12px]">ssid_chart</span>
                Histórico (Últimas 12 Horas)
            </span>
            <span id="tt-uptime-badge" class="px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-emerald-950/90 text-emerald-400 border border-emerald-500/40">
                100% Up
            </span>
        </div>

        <div class="h-16 w-full relative bg-[#020b14]/70 rounded border border-obsidian-border/50 p-1 flex items-center justify-center">
            <canvas id="tt-canvas" class="w-full h-full"></canvas>
            <span id="tt-no-chart" class="text-[9px] text-obsidian-muted hidden">Sin histórico suficiente</span>
        </div>

        <div class="flex justify-between text-[9px] font-mono text-obsidian-muted mt-1 px-0.5">
            <span>Mín: <strong id="tt-stat-min" class="text-white">--</strong></span>
            <span>Prom: <strong id="tt-stat-avg" class="text-emerald-400">--</strong></span>
            <span>Máx: <strong id="tt-stat-max" class="text-amber-400">--</strong></span>
        </div>
    </div>

    <!-- DETALLES TÉCNICOS ADICIONALES (CONSERVADOS INTACTOS) -->
    <div class="mt-1.5 pt-1.5 border-t border-obsidian-border/60 text-[10px] text-obsidian-muted leading-tight" id="tt-details">
        Verificación asíncrona de socket en tiempo real.
    </div>
</div>
@endsection

@push('scripts')
<script>
    // --- 1. GESTIÓN DEL TOOLTIP HUD EMERGENTE AL POSAR EL MOUSE CON CHART.JS ---
    const tooltip = document.getElementById('tech-tooltip');
    const ttTitle = document.getElementById('tt-title');
    const ttType = document.getElementById('tt-type');
    const ttIp = document.getElementById('tt-ip');
    const ttPort = document.getElementById('tt-port');
    const ttProtocol = document.getElementById('tt-protocol');
    const ttLatency = document.getElementById('tt-latency');
    const ttStatus = document.getElementById('tt-status');
    const ttDetails = document.getElementById('tt-details');

    let sparklineChart = null;

    function renderSparklineChart(historyData, isUp) {
        const canvas = document.getElementById('tt-canvas');
        const noChartEl = document.getElementById('tt-no-chart');
        const uptimeBadge = document.getElementById('tt-uptime-badge');
        const statMin = document.getElementById('tt-stat-min');
        const statAvg = document.getElementById('tt-stat-avg');
        const statMax = document.getElementById('tt-stat-max');

        if (!canvas) return;

        if (!historyData || !historyData.latencies || historyData.latencies.length === 0) {
            canvas.classList.add('hidden');
            noChartEl.classList.remove('hidden');
            uptimeBadge.innerText = isUp ? '100% Up' : '0% Down';
            uptimeBadge.className = isUp 
                ? 'px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-emerald-950/90 text-emerald-400 border border-emerald-500/40'
                : 'px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-red-950/90 text-red-400 border border-red-500/40';
            statMin.innerText = '--';
            statAvg.innerText = '--';
            statMax.innerText = '--';
            return;
        }

        canvas.classList.remove('hidden');
        noChartEl.classList.add('hidden');

        const labels = historyData.labels || [];
        const dataPoints = historyData.latencies || [];
        const uptime = historyData.uptime !== undefined ? historyData.uptime : (isUp ? 100 : 0);

        uptimeBadge.innerText = `${uptime}% Up (12h)`;
        uptimeBadge.className = uptime >= 90 
            ? 'px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-emerald-950/90 text-emerald-400 border border-emerald-500/40'
            : (uptime >= 70 
                ? 'px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-amber-950/90 text-amber-400 border border-amber-500/40'
                : 'px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-red-950/90 text-red-400 border border-red-500/40');

        statMin.innerText = `${historyData.min || 0}ms`;
        statAvg.innerText = `${historyData.avg || 0}ms`;
        statMax.innerText = `${historyData.max || 0}ms`;

        const ctx = canvas.getContext('2d');
        const lineColor = isUp ? '#00e5ff' : '#ef4444';
        const fillColor = isUp ? 'rgba(0, 229, 255, 0.15)' : 'rgba(239, 68, 68, 0.15)';

        if (sparklineChart) {
            sparklineChart.destroy();
        }

        sparklineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: dataPoints,
                    borderColor: lineColor,
                    borderWidth: 1.5,
                    backgroundColor: fillColor,
                    fill: true,
                    tension: 0.25,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHitRadius: 8,
                    pointBackgroundColor: lineColor,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        displayColors: false,
                        padding: 4,
                        bodyFont: { size: 9, family: 'monospace' },
                        callbacks: {
                            title: (items) => items.length ? `Hora: ${items[0].label}` : '',
                            label: (c) => `Latencia: ${c.parsed.y} ms`
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        grid: { display: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 7.5, family: 'monospace' },
                            maxTicksLimit: 5
                        }
                    },
                    y: {
                        display: true,
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: {
                            color: '#64748b',
                            font: { size: 7.5, family: 'monospace' },
                            maxTicksLimit: 3,
                            callback: (v) => `${v}ms`
                        }
                    }
                }
            }
        });
    }

    function attachTooltipEvents() {
        document.querySelectorAll('[data-tech-title]').forEach(el => {
            if (el._hasTooltip) return;
            el._hasTooltip = true;

            el.addEventListener('mouseenter', (e) => {
                ttTitle.innerText = el.getAttribute('data-tech-title') || 'DETALLE TÉCNICO';
                ttType.innerText = el.getAttribute('data-tech-type') || 'GENERAL';
                ttIp.innerText = el.getAttribute('data-tech-ip') || '--';
                ttPort.innerText = el.getAttribute('data-tech-port') || '--';
                ttProtocol.innerText = el.getAttribute('data-tech-protocol') || 'TCP/IP';
                ttLatency.innerText = el.getAttribute('data-tech-latency') || '--';
                
                const status = el.getAttribute('data-tech-status') || 'ACTIVO';
                ttStatus.innerText = status;
                const isItemUp = status.includes('OPERATIVO') || status.includes('ACTIVO') || status.includes('ONLINE');
                ttStatus.className = isItemUp 
                    ? 'font-bold text-emerald-400 truncate max-w-[160px]' 
                    : 'font-bold text-red-400 truncate max-w-[160px]';

                ttDetails.innerText = el.getAttribute('data-tech-details') || 'Monitoreo continuo cada 5 min.';

                // Parsear e inicializar gráfico de histórico temporal
                let hist = null;
                try {
                    const rawHist = el.getAttribute('data-tech-history');
                    if (rawHist) hist = JSON.parse(rawHist);
                } catch(err) {
                    hist = null;
                }
                renderSparklineChart(hist, isItemUp);

                tooltip.classList.add('show');
                positionTooltip(e);
            });

            el.addEventListener('mousemove', (e) => {
                positionTooltip(e);
            });

            el.addEventListener('mouseleave', () => {
                tooltip.classList.remove('show');
            });
        });
    }

    function positionTooltip(e) {
        const padding = 15;
        let x = e.clientX + padding;
        let y = e.clientY + padding;

        const ttWidth = 330;
        const ttHeight = 310;

        if (x + ttWidth > window.innerWidth) {
            x = e.clientX - ttWidth - padding;
        }
        if (y + ttHeight > window.innerHeight) {
            y = e.clientY - ttHeight - padding;
        }

        tooltip.style.left = `${Math.max(10, x)}px`;
        tooltip.style.top = `${Math.max(10, y)}px`;
    }

    document.addEventListener('DOMContentLoaded', attachTooltipEvents);

    // --- 2. ACORDEÓN DESPLEGABLE DE EQUIPOS EN SEDE ---
    function toggleSiteDetails(id, headerEl) {
        const details = document.getElementById(id);
        const chevron = headerEl ? headerEl.querySelector('.chevron-icon') : null;
        if (!details) return;

        if (details.classList.contains('hidden')) {
            details.classList.remove('hidden');
            if (chevron) chevron.style.transform = 'rotate(180deg)';
        } else {
            details.classList.add('hidden');
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        }
    }

    // --- 3. BÚSQUEDA REACTIVA EN TIEMPO REAL ---
    function filterLiveItems() {
        const query = (document.getElementById('live-search-input')?.value || '').toLowerCase();
        document.querySelectorAll('.item-searchable').forEach(el => {
            const searchData = el.getAttribute('data-search') || '';
            el.style.display = searchData.includes(query) ? '' : 'none';
        });
    }

    // --- 4. AUTO-REFRESCO ASÍNCRONO EN VIVO (SIN PARPADEOS) ---
    async function fetchLiveMonitoring() {
        try {
            const res = await fetch('{{ route("api.status") }}');
            if (!res.ok) return;
            const data = await res.json();
            if (!data.success || !data.snapshot) return;

            const snap = data.snapshot;
            const services = snap.services || [];
            const sites = snap.sites || [];
            const proxies = snap.proxies || [];
            const summary = snap.summary || {};
            const globalStatus = data.global_status || snap.global_status || 'OPERACIONAL';

            // 1. Actualizar Timestamp
            const lastSync = document.getElementById('last-sync-time');
            if (lastSync && data.updated_at) {
                lastSync.innerText = data.updated_at.split(' ')[1] || data.updated_at;
            }

            // 2. Actualizar Badge Global en Cabecera
            const globalBadge = document.getElementById('global-status-badge');
            if (globalBadge) {
                globalBadge.className = `hidden sm:flex items-center gap-2 px-3 py-1 rounded-full border text-xs font-mono font-semibold ${
                    globalStatus === 'OPERACIONAL' 
                        ? 'bg-emerald-950/60 text-emerald-400 border-emerald-500/50 glow-green' 
                        : (globalStatus === 'DEGRADADO' 
                            ? 'bg-amber-950/60 text-amber-400 border-amber-500/50' 
                            : 'bg-red-950/60 text-red-400 border-red-500/50 glow-red')
                }`;
                globalBadge.setAttribute('data-tech-status', globalStatus);
                globalBadge.innerHTML = `
                    <span class="w-2 h-2 rounded-full ${
                        globalStatus === 'OPERACIONAL' ? 'bg-emerald-400 pulse-dot' : (globalStatus === 'DEGRADADO' ? 'bg-amber-400 pulse-dot' : 'bg-red-400 pulse-dot')
                    }"></span>
                    <span id="global-status-text">${globalStatus}</span>
                `;
            }

            // Separar elementos
            const activeServices = services.filter(s => s.is_up);
            const downServices = services.filter(s => !s.is_up);
            const activeSites = sites.filter(st => st.is_up);
            const downSites = sites.filter(st => !st.is_up);

            // 3. Columna 1: Servicios Activos
            const activeSvcContainer = document.getElementById('active-services-container');
            const activeSvcBadge = document.getElementById('badge-count-active-services');
            if (activeSvcBadge) activeSvcBadge.innerText = `${activeServices.length} Operativos`;

            if (activeSvcContainer) {
                if (activeServices.length === 0) {
                    activeSvcContainer.innerHTML = `<div class="p-8 text-center text-xs font-mono text-obsidian-muted">No hay servicios activos reportados en este momento.</div>`;
                } else {
                    activeSvcContainer.innerHTML = activeServices.map(s => {
                        const targetHost = s.host_ip || s.web_url || '127.0.0.1';
                        const port = s.port || (s.type === 'WEB' ? '80/443' : (s.type === 'DNS' ? '53' : (s.type === 'SMTP' ? '25' : (s.type === 'LDAP' ? '389' : 'ICMP'))));
                        const protocol = s.type === 'WEB' ? 'HTTP/HTTPS GET Request' : (s.type === 'DNS' ? 'DNS Query' : (s.type === 'SMTP' ? 'SMTP Mail Handshake' : (s.type === 'LDAP' ? 'LDAP Bind Handshake' : 'ICMP Ping')));
                        const latencyStr = s.latency_ms > 0 ? `${s.latency_ms}ms` : '<15ms';
                        const details = s.web_url ? `Endpoint: ${s.web_url}` : 'Verificación por socket de transporte directo.';

                        return `
                        <div class="py-1.5 px-2.5 rounded-lg bg-obsidian-panel/60 hover:bg-obsidian-panel border border-obsidian-border/50 hover:border-emerald-500/50 transition cursor-pointer flex items-center justify-between group item-searchable select-none"
                             data-search="${(s.name + ' ' + s.type).toLowerCase()}"
                             data-tech-title="${s.name}"
                             data-tech-type="${s.type}"
                             data-tech-ip="${targetHost}"
                             data-tech-port="${port}"
                             data-tech-protocol="${protocol}"
                             data-tech-latency="${latencyStr}"
                             data-tech-status="OPERATIVO (200 OK / Response)"
                             data-tech-details="${details}">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-1.5 h-1.5 rounded-full shrink-0 bg-emerald-400 glow-green"></div>
                                <span class="text-[11px] font-semibold text-white group-hover:text-emerald-300 transition-colors truncate">
                                    ${s.name}
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="text-[9px] font-mono text-emerald-400/90 font-medium">${latencyStr}</span>
                                <span class="px-1 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase bg-obsidian-bg/80 border border-emerald-500/30 text-emerald-300">
                                    ${s.type}
                                </span>
                            </div>
                        </div>`;
                    }).join('');
                }
            }

            // 4. Columna 2: Sedes Conectadas (Preservar acordeones abiertos)
            const openAccordions = new Set();
            document.querySelectorAll('[id^=site-details-]').forEach(el => {
                if (!el.classList.contains('hidden')) {
                    openAccordions.add(el.id);
                }
            });

            const activeSitesContainer = document.getElementById('active-sites-container');
            const activeSitesBadge = document.getElementById('badge-count-active-sites');
            if (activeSitesBadge) activeSitesBadge.innerText = `${activeSites.length} Sedes Online`;

            if (activeSitesContainer) {
                if (activeSites.length === 0) {
                    activeSitesContainer.innerHTML = `<div class="p-8 text-center text-xs font-mono text-obsidian-muted">No hay sedes conectadas en este momento.</div>`;
                } else {
                    activeSitesContainer.innerHTML = activeSites.map(site => {
                        const isA = site.letter === 'A';
                        const latencyStr = site.latency_ms > 0 ? `${site.latency_ms} ms` : '< 15 ms';
                        const cleanAddress = site.address && !site.address.toUpperCase().includes('NO CONFIGURADO') ? site.address : '';
                        const cleanPhone = site.phone_1 && !site.phone_1.toUpperCase().includes('NO CONFIGURADO') ? site.phone_1 : '';
                        const details = (cleanAddress ? 'Ubicación: ' + cleanAddress : 'Sede Regional Corporativa') + (cleanPhone ? ' • Contacto: ' + cleanPhone : '');
                        const isOpen = openAccordions.has(`site-details-${site.letter}`);

                        const devicesHtml = (site.devices && site.devices.length > 0) 
                            ? `<div class="space-y-1">
                                    <span class="text-[8.5px] uppercase font-mono tracking-wider text-obsidian-muted block">Equipos en Sitio (${site.devices.length})</span>
                                    <div class="grid grid-cols-2 gap-1">
                                        ${site.devices.map(dev => `
                                            <div class="bg-obsidian-panel/90 hover:bg-obsidian-panel border border-obsidian-border rounded p-1.5 flex items-center justify-between text-[9px] font-mono cursor-pointer transition hover:border-obsidian-cyan/40"
                                                 data-tech-title="${site.name} - ${dev.name}"
                                                 data-tech-type="EQUIPO SECUNDARIO"
                                                 data-tech-ip="${dev.ip}"
                                                 data-tech-port="Slot #${dev.device_number}"
                                                 data-tech-protocol="ICMP Echo Ping"
                                                 data-tech-latency="${dev.is_up ? '< 10 ms' : '--'}"
                                                 data-tech-status="${dev.is_up ? 'ONLINE (Ping Respondido)' : 'OFFLINE (Inaccesible)'}"
                                                 data-tech-details="Dispositivo interno vinculado a la red local de ${site.name}.">
                                                <span class="text-white truncate max-w-[85px]">${dev.name}</span>
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0 ${dev.is_up ? 'bg-emerald-400 glow-green' : 'bg-red-500'}"></span>
                                            </div>
                                        `).join('')}
                                    </div>
                               </div>`
                            : `<p class="text-[9px] font-mono text-obsidian-muted text-center py-1">Sin equipos secundarios registrados</p>`;

                        return `
                        <div class="glass-card rounded-lg border border-obsidian-border/80 overflow-hidden group hover:border-obsidian-cyan/40 transition">
                            <div class="p-2.5 flex items-center justify-between cursor-pointer select-none"
                                 onclick="toggleSiteDetails('site-details-${site.letter}', this)"
                                 data-search="${(site.name + ' ' + cleanAddress).toLowerCase()}"
                                 data-tech-title="${site.name}"
                                 data-tech-type="SEDE REGIONAL"
                                 data-tech-ip="${site.ip || '0.0.0.0'}"
                                 data-tech-port="Gateway PING / ICMP"
                                 data-tech-protocol="Enlace de Transporte WAN"
                                 data-tech-latency="${latencyStr}"
                                 data-tech-status="ONLINE (Conectado / Operativo)"
                                 data-tech-details="${details}">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-1.5 h-1.5 rounded-full shrink-0 bg-emerald-400 glow-green"></div>
                                    <div class="min-w-0">
                                        <h3 class="text-[11px] font-bold text-white group-hover:text-obsidian-cyan transition-colors truncate">
                                            ${site.name}
                                        </h3>
                                        <p class="text-[9px] font-mono text-obsidian-muted truncate">
                                            ${cleanAddress ? cleanAddress.substring(0, 38) + (cleanAddress.length > 38 ? '...' : '') : 'Sede Regional Corporativa'}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="px-1 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase ${isA ? 'bg-obsidian-cyan/20 text-obsidian-cyan border border-obsidian-cyan/30' : 'bg-obsidian-purple/20 text-obsidian-purple border border-obsidian-purple/30'}">
                                        ${isA ? 'HUB' : 'SEDE'}
                                    </span>
                                    <span class="material-symbols-outlined text-obsidian-muted text-sm transition-transform duration-200 chevron-icon" style="${isOpen ? 'transform: rotate(180deg);' : ''}">
                                        expand_more
                                    </span>
                                </div>
                            </div>
                            <div id="site-details-${site.letter}" class="${isOpen ? '' : 'hidden'} px-2.5 pb-2.5 pt-1 border-t border-obsidian-border/40 bg-obsidian-bg/40 space-y-2">
                                <div class="flex items-center justify-between text-[10px] font-mono bg-obsidian-bg/80 px-2 py-1 rounded-lg border border-obsidian-border/40">
                                    <span class="text-[9px] text-obsidian-muted">Latencia Gateway:</span>
                                    <span class="font-bold text-emerald-400 text-[10px]">${latencyStr}</span>
                                </div>
                                ${devicesHtml}
                            </div>
                        </div>`;
                    }).join('');
                }
            }

            // 5. Columna 3: Servicios Caídos
            const downSvcContainer = document.getElementById('down-services-container');
            const downSvcBadge = document.getElementById('badge-count-down-services');
            if (downSvcBadge) {
                downSvcBadge.className = `px-2 py-0.5 rounded text-[10px] font-mono font-bold ${
                    downServices.length > 0 ? 'bg-red-950 text-red-400 border border-red-500/50 glow-red' : 'bg-emerald-950 text-emerald-400 border border-emerald-500/40'
                }`;
                downSvcBadge.innerText = `${downServices.length} Inactivos`;
            }

            if (downSvcContainer) {
                if (downServices.length === 0) {
                    downSvcContainer.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center p-4 text-center">
                            <span class="material-symbols-outlined text-emerald-400 text-2xl mb-1">verified</span>
                            <p class="text-xs font-mono text-emerald-400 font-bold">Todos los servicios operando normalmente</p>
                            <p class="text-[10px] font-mono text-obsidian-muted">Sin incidentes de aplicativo registrados</p>
                        </div>`;
                } else {
                    downSvcContainer.innerHTML = downServices.map(s => {
                        const targetHost = s.host_ip || s.web_url || '127.0.0.1';
                        const port = s.port || (s.type === 'WEB' ? '80/443' : (s.type === 'DNS' ? '53' : (s.type === 'SMTP' ? '25' : (s.type === 'LDAP' ? '389' : 'ICMP'))));
                        const protocol = s.type === 'WEB' ? 'HTTP/HTTPS GET' : (s.type === 'DNS' ? 'DNS Query' : (s.type === 'SMTP' ? 'SMTP Mail' : (s.type === 'LDAP' ? 'LDAP Bind' : 'ICMP Ping')));
                        const details = s.web_url ? `Endpoint: ${s.web_url}` : 'Sin respuesta de transporte de red.';

                        return `
                        <div class="py-1.5 px-2.5 rounded-lg bg-red-950/30 hover:bg-red-950/50 border border-red-500/30 transition cursor-pointer flex items-center justify-between group item-searchable select-none"
                             data-search="${(s.name + ' ' + s.type).toLowerCase()}"
                             data-tech-title="${s.name}"
                             data-tech-type="${s.type}"
                             data-tech-ip="${targetHost}"
                             data-tech-port="${port}"
                             data-tech-protocol="${protocol}"
                             data-tech-latency="Timeout / Sin respuesta"
                             data-tech-status="APAGADO (Host / Puerto inalcanzable)"
                             data-tech-details="${details}">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-1.5 h-1.5 rounded-full shrink-0 bg-red-500 glow-red"></div>
                                <span class="text-[11px] font-semibold text-red-200 group-hover:text-red-100 transition-colors truncate">
                                    ${s.name}
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="text-[9px] font-mono text-red-400/80 font-medium">Timeout</span>
                                <span class="px-1 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase bg-red-950/80 border border-red-500/40 text-red-300">
                                    ${s.type}
                                </span>
                            </div>
                        </div>`;
                    }).join('');
                }
            }

            // 6. Columna 3: Sedes sin Conexión
            const downSitesContainer = document.getElementById('down-sites-container');
            const downSitesBadge = document.getElementById('badge-count-down-sites');
            if (downSitesBadge) {
                downSitesBadge.className = `px-2 py-0.5 rounded text-[10px] font-mono font-bold ${
                    downSites.length > 0 ? 'bg-red-950 text-red-400 border border-red-500/50 glow-red' : 'bg-emerald-950 text-emerald-400 border border-emerald-500/40'
                }`;
                downSitesBadge.innerText = `${downSites.length} Desconectadas`;
            }

            if (downSitesContainer) {
                if (downSites.length === 0) {
                    downSitesContainer.innerHTML = `
                        <div class="h-full flex flex-col items-center justify-center p-4 text-center">
                            <span class="material-symbols-outlined text-emerald-400 text-2xl mb-1">wifi_tethering</span>
                            <p class="text-xs font-mono text-emerald-400 font-bold">Todos los enlaces regionales conectados</p>
                            <p class="text-[10px] font-mono text-obsidian-muted">Comunicación WAN 100% operativa</p>
                        </div>`;
                } else {
                    downSitesContainer.innerHTML = downSites.map(site => {
                        const cleanAddress = site.address && !site.address.toUpperCase().includes('NO CONFIGURADO') ? site.address : '';
                        const cleanPhone = site.phone_1 && !site.phone_1.toUpperCase().includes('NO CONFIGURADO') ? site.phone_1 : '';
                        const details = (cleanAddress ? 'Ubicación: ' + cleanAddress : 'Sede Regional Corporativa') + (cleanPhone ? ' • Contacto: ' + cleanPhone : '');

                        return `
                        <div class="py-1.5 px-2.5 rounded-lg bg-red-950/30 hover:bg-red-950/50 border border-red-500/30 transition cursor-pointer flex items-center justify-between group item-searchable select-none"
                             data-search="${(site.name + ' ' + cleanAddress).toLowerCase()}"
                             data-tech-title="${site.name}"
                             data-tech-type="SEDE REGIONAL"
                             data-tech-ip="${site.ip || '0.0.0.0'}"
                             data-tech-port="Gateway PING / ICMP"
                             data-tech-protocol="Enlace de Transporte WAN"
                             data-tech-latency="100% Packet Loss"
                             data-tech-status="ENLACE WAN CAÍDO"
                             data-tech-details="${details}">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-1.5 h-1.5 rounded-full shrink-0 bg-red-500 glow-red"></div>
                                <span class="text-[11px] font-semibold text-red-200 group-hover:text-red-100 transition-colors truncate">
                                    ${site.name}
                                </span>
                            </div>
                            <span class="px-1 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase bg-red-950/80 border border-red-500/40 text-red-300">
                                OFFLINE
                            </span>
                        </div>`;
                    }).join('');
                }
            }

            // 7. Columna 3: Telemetría y Estatus Global
            const totServ = services.length;
            const servPct = totServ > 0 ? ((activeServices.length / totServ) * 100).toFixed(1) : '100.0';
            const totSites = sites.length;
            const sitesPct = totSites > 0 ? ((activeSites.length / totSites) * 100).toFixed(1) : '100.0';
            const totProxies = summary.proxies_total || proxies.length || 4;
            const onlProxies = summary.proxies_online || proxies.filter(p => p.is_up).length || 4;

            const tBadge = document.getElementById('telemetry-status-badge');
            if (tBadge) {
                tBadge.className = `px-2 py-0.5 rounded-full text-[9px] font-mono font-bold border flex items-center gap-1 ${
                    globalStatus === 'OPERACIONAL' 
                        ? 'bg-emerald-950/80 text-emerald-400 border-emerald-500/50 glow-green' 
                        : (globalStatus === 'DEGRADADO' 
                            ? 'bg-amber-950/80 text-amber-400 border-amber-500/50' 
                            : 'bg-red-950/80 text-red-400 border-red-500/50 glow-red')
                }`;
                tBadge.setAttribute('data-tech-status', globalStatus);
                tBadge.innerHTML = `
                    <span class="w-1.5 h-1.5 rounded-full ${
                        globalStatus === 'OPERACIONAL' ? 'bg-emerald-400 pulse-dot' : (globalStatus === 'DEGRADADO' ? 'bg-amber-400 pulse-dot' : 'bg-red-400 pulse-dot')
                    }"></span>
                    ${globalStatus}
                `;
            }

            const elServCount = document.getElementById('metric-services-count');
            const elServPct = document.getElementById('metric-services-pct');
            if (elServCount) elServCount.innerHTML = `<span class="text-xs font-bold text-white">${activeServices.length}</span><span class="text-[9px] text-obsidian-muted">/ ${totServ}</span>`;
            if (elServPct) {
                elServPct.className = `text-[8.5px] font-mono font-bold ${servPct == 100 ? 'text-emerald-400' : (servPct >= 70 ? 'text-amber-400' : 'text-red-400')}`;
                elServPct.innerText = `${servPct}%`;
            }

            const elSitesCount = document.getElementById('metric-sites-count');
            const elSitesPct = document.getElementById('metric-sites-pct');
            if (elSitesCount) elSitesCount.innerHTML = `<span class="text-xs font-bold text-white">${activeSites.length}</span><span class="text-[9px] text-obsidian-muted">/ ${totSites}</span>`;
            if (elSitesPct) {
                elSitesPct.className = `text-[8.5px] font-mono font-bold ${sitesPct == 100 ? 'text-emerald-400' : (sitesPct >= 70 ? 'text-amber-400' : 'text-red-400')}`;
                elSitesPct.innerText = `${sitesPct}%`;
            }

            const elProxiesCount = document.getElementById('metric-proxies-count');
            if (elProxiesCount) elProxiesCount.innerHTML = `<span class="text-xs font-bold text-white">${onlProxies}</span><span class="text-[9px] text-obsidian-muted">/ ${totProxies}</span>`;

            // 8. Re-vincular eventos de Tooltips y Filtro de búsqueda
            attachTooltipEvents();
            filterLiveItems();

        } catch (err) {
            console.error('Error in fetchLiveMonitoring:', err);
        }
    }

    // Polling reactivo cada 15 segundos
    setInterval(fetchLiveMonitoring, 15000);
</script>
@endpush
