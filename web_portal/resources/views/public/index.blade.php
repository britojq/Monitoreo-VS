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
        $snapshotNetDevices = ($snapshotData && isset($snapshotData['network_devices'])) ? collect($snapshotData['network_devices'])->keyBy('ip') : collect();

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

        // 3. Dispositivos en Red Valle Seco
        $activeNetDevices = ($networkDevices ?? collect())->map(function($d) use ($snapshotNetDevices) {
            $snap = $snapshotNetDevices->get($d->ip);
            $d->is_up_evaluated = $snap ? ($snap['is_up'] ?? false) : false;
            $d->latency_evaluated = $snap ? ($snap['latency_ms'] ?? 0) : 0;
            return $d;
        });
        $netDevicesOnlineCount = $activeNetDevices->where('is_up_evaluated', true)->count();
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
                         data-tech-id="{{ $s->id }}"
                         data-tech-kind="service">
                        
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
        <!-- COLUMNA 2: SEDES REGIONALES Y DISPOSITIVOS EN RED VALLE SECO (34% ANCHO) -->
        <!-- ========================================================================= -->
        <section class="flex flex-col w-full lg:w-[34%] h-full gap-3 overflow-hidden">

            <!-- BLOQUE SUPERIOR: SEDES REGIONALES CONECTADAS (50% ALTURA) -->
            <div class="glass-panel rounded-xl flex-1 flex flex-col overflow-hidden border border-obsidian-border/80">
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
                                 data-tech-id="{{ $site->id }}"
                                 data-tech-kind="site">
                                
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
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                            @foreach($activeDevices as $dev)
                                                @php
                                                    $dSnap = $devicesSnapshot->get($dev->device_number);
                                                    $devUp = $dSnap ? ($dSnap['is_up'] ?? false) : false;
                                                    $canVnc = Auth::check() && in_array(Auth::user()->role, ['admin', 'operator']);
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
                                                     data-tech-id="{{ $site->id }}"
                                                     data-tech-kind="site">
                                                    <div class="flex items-center space-x-1.5 min-w-0 pr-1 truncate">
                                                        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $devUp ? 'bg-emerald-400 glow-green' : 'bg-red-500' }}"></span>
                                                        <span class="text-white truncate" title="{{ $dev->name }}">{{ $dev->name }}</span>
                                                    </div>

                                                    <!-- BOTÓN VNC CON CONDICIÓN DE ROL Y LOGIN -->
                                                    @if($canVnc)
                                                        <button type="button"
                                                                onclick="event.stopPropagation(); openVncModal('{{ $dev->ip }}', '{{ addslashes($dev->name) }}', '{{ addslashes($site->name) }}', true)"
                                                                class="px-1.5 py-0.5 rounded text-[8px] font-mono font-bold bg-cyan-950/90 hover:bg-obsidian-cyan hover:text-black border border-cyan-500/50 text-cyan-300 transition flex items-center gap-0.5 shrink-0 shadow-sm shadow-cyan-950 cursor-pointer"
                                                                title="Conectar Escritorio Remoto VNC ({{ $dev->ip }})">
                                                            <span class="material-symbols-outlined text-[10px]">desktop_windows</span>
                                                            <span>VNC</span>
                                                        </button>
                                                    @else
                                                        <button type="button"
                                                                onclick="event.stopPropagation(); openVncModal('{{ $dev->ip }}', '{{ addslashes($dev->name) }}', '{{ addslashes($site->name) }}', false)"
                                                                class="px-1.5 py-0.5 rounded text-[8px] font-mono bg-obsidian-card/90 hover:bg-amber-950/40 border border-obsidian-border hover:border-amber-500/40 text-obsidian-muted hover:text-amber-300 transition flex items-center gap-0.5 shrink-0 cursor-pointer"
                                                                title="Debe iniciar sesión para conectar por VNC">
                                                            <span class="material-symbols-outlined text-[10px] text-amber-400/80">lock</span>
                                                            <span>VNC</span>
                                                        </button>
                                                    @endif
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
            </div>

            <!-- BLOQUE INFERIOR: DISPOSITIVOS EN RED VALLE SECO (50% ALTURA) -->
            <div class="glass-panel rounded-xl flex-1 flex flex-col overflow-hidden border border-obsidian-border/80 bg-obsidian-panel/20">
                <!-- CABECERA -->
                <div class="p-3 border-b border-obsidian-border flex items-center justify-between bg-obsidian-panel/50">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-obsidian-cyan text-lg">lan</span>
                        <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">DISPOSITIVOS EN RED VALLE SECO</h2>
                    </div>
                    <span id="badge-count-valle-seco-devices" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-obsidian-cyan/20 text-obsidian-cyan border border-obsidian-cyan/30">
                        {{ $netDevicesOnlineCount }} / {{ $activeNetDevices->count() }} Online
                    </span>
                </div>

                <!-- LISTA DE DISPOSITIVOS EN RED VALLE SECO -->
                <div class="flex-1 overflow-y-auto p-2 space-y-1.5 custom-scroll" id="valle-seco-devices-container">
                    @forelse($activeNetDevices as $netDev)
                        @php
                            $isUp = $netDev->is_up_evaluated ?? false;
                            $latStr = ($netDev->latency_evaluated ?? 0) > 0 ? ($netDev->latency_evaluated . ' ms') : '< 1 ms';
                            $canRemote = Auth::check() && in_array(Auth::user()->role, ['admin', 'operator']);
                            $accessType = strtoupper(trim($netDev->access_type ?? 'SIN SOPORTE'));
                            $accessPort = $netDev->access_port ?: ($accessType === 'TELNET' ? 23 : ($accessType === 'WEB' ? 80 : 5900));
                        @endphp
                        <div class="py-1.5 px-2.5 rounded-lg bg-obsidian-panel/60 hover:bg-obsidian-panel border border-obsidian-border/60 hover:border-obsidian-cyan/50 transition cursor-pointer flex items-center justify-between group item-searchable select-none"
                             data-search="{{ strtolower($netDev->name . ' ' . $netDev->ip . ' ' . ($netDev->vendor_data ?? '')) }}"
                             data-tech-title="{{ $netDev->name }}"
                             data-tech-type="DISPOSITIVO LAN VALLE SECO"
                             data-tech-ip="{{ $netDev->ip }}"
                             data-tech-port="MAC: {{ $netDev->mac ?: 'No disponible' }}{{ $accessType !== 'SIN SOPORTE' ? ' • ' . $accessType . ':' . $accessPort : '' }}"
                             data-tech-protocol="ICMP Ping Directo"
                             data-tech-latency="{{ $isUp ? $latStr : 'Timeout / Sin respuesta' }}"
                             data-tech-status="{{ $isUp ? 'OPERATIVO (Enlace Local LAN Activo)' : 'OFFLINE (Dispositivo no responde en LAN)' }}"
                             data-tech-details="{{ $netDev->vendor_data ? 'Fabricante / Info: ' . $netDev->vendor_data : 'Equipo de red local Valle Seco.' }}{{ $accessType !== 'SIN SOPORTE' ? ' • Acceso: ' . $accessType : '' }}"
                             data-tech-id="{{ $netDev->id }}"
                             data-tech-kind="device">
                            
                            <div class="flex items-center gap-2 min-w-0 pr-2">
                                <div class="w-2 h-2 rounded-full shrink-0 {{ $isUp ? 'bg-emerald-400 glow-green' : 'bg-red-500' }}"></div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 truncate">
                                        <h3 class="text-[11px] font-bold text-white group-hover:text-obsidian-cyan transition-colors truncate">
                                            {{ $netDev->name }}
                                        </h3>
                                        @if($netDev->vendor_data)
                                            <span class="px-1 py-0.2 rounded text-[7.5px] font-mono text-obsidian-muted bg-obsidian-bg/80 border border-obsidian-border/40 truncate shrink-0">
                                                {{ $netDev->vendor_data }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-[9px] font-mono text-obsidian-muted truncate">
                                        IP: {{ $netDev->ip }} @if($netDev->mac) • MAC: <span class="text-obsidian-cyan/70">{{ $netDev->mac }}</span>@endif
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="text-[9px] font-mono {{ $isUp ? 'text-emerald-400/90' : 'text-red-400' }} font-medium">
                                    {{ $isUp ? $latStr : 'Down' }}
                                </span>

                                @if($accessType === 'TELNET')
                                    @if($canRemote)
                                        <button type="button"
                                                onclick="event.stopPropagation(); openAccessModal('TELNET', '{{ $netDev->ip }}', {{ $accessPort }}, '{{ addslashes($netDev->name) }}', 'Red Valle Seco', true)"
                                                class="px-1.5 py-0.5 rounded text-[8px] font-mono font-bold bg-cyan-950/90 hover:bg-obsidian-cyan hover:text-black border border-cyan-500/50 text-cyan-300 transition flex items-center gap-0.5 shrink-0 shadow-sm shadow-cyan-950 cursor-pointer"
                                                title="Abrir Terminal Telnet ({{ $netDev->ip }}:{{ $accessPort }})">
                                            <span class="material-symbols-outlined text-[10px]">terminal</span>
                                            <span>TELNET</span>
                                        </button>
                                    @else
                                        <button type="button"
                                                onclick="event.stopPropagation(); openAccessModal('TELNET', '{{ $netDev->ip }}', {{ $accessPort }}, '{{ addslashes($netDev->name) }}', 'Red Valle Seco', false)"
                                                class="px-1.5 py-0.5 rounded text-[8px] font-mono bg-obsidian-card/90 hover:bg-amber-950/40 border border-obsidian-border hover:border-amber-500/40 text-obsidian-muted hover:text-amber-300 transition flex items-center gap-0.5 shrink-0 cursor-pointer"
                                                title="Debe iniciar sesión para acceder por Telnet">
                                            <span class="material-symbols-outlined text-[10px] text-amber-400/80">lock</span>
                                            <span>TELNET</span>
                                        </button>
                                    @endif
                                @elseif($accessType === 'WEB')
                                    @if($canRemote)
                                        <button type="button"
                                                onclick="event.stopPropagation(); openAccessModal('WEB', '{{ $netDev->ip }}', {{ $accessPort }}, '{{ addslashes($netDev->name) }}', 'Red Valle Seco', true)"
                                                class="px-1.5 py-0.5 rounded text-[8px] font-mono font-bold bg-cyan-950/90 hover:bg-obsidian-cyan hover:text-black border border-cyan-500/50 text-cyan-300 transition flex items-center gap-0.5 shrink-0 shadow-sm shadow-cyan-950 cursor-pointer"
                                                title="Abrir Panel Web (http://{{ $netDev->ip }}:{{ $accessPort }})">
                                            <span class="material-symbols-outlined text-[10px]">language</span>
                                            <span>WEB</span>
                                        </button>
                                    @else
                                        <button type="button"
                                                onclick="event.stopPropagation(); openAccessModal('WEB', '{{ $netDev->ip }}', {{ $accessPort }}, '{{ addslashes($netDev->name) }}', 'Red Valle Seco', false)"
                                                class="px-1.5 py-0.5 rounded text-[8px] font-mono bg-obsidian-card/90 hover:bg-amber-950/40 border border-obsidian-border hover:border-amber-500/40 text-obsidian-muted hover:text-amber-300 transition flex items-center gap-0.5 shrink-0 cursor-pointer"
                                                title="Debe iniciar sesión para acceder al panel web">
                                            <span class="material-symbols-outlined text-[10px] text-amber-400/80">lock</span>
                                            <span>WEB</span>
                                        </button>
                                    @endif
                                @elseif($accessType === 'VNC')
                                    @if($canRemote)
                                        <button type="button"
                                                onclick="event.stopPropagation(); openAccessModal('VNC', '{{ $netDev->ip }}', 5900, '{{ addslashes($netDev->name) }}', 'Red Valle Seco', true)"
                                                class="px-1.5 py-0.5 rounded text-[8px] font-mono font-bold bg-cyan-950/90 hover:bg-obsidian-cyan hover:text-black border border-cyan-500/50 text-cyan-300 transition flex items-center gap-0.5 shrink-0 shadow-sm shadow-cyan-950 cursor-pointer"
                                                title="Conectar Escritorio Remoto VNC ({{ $netDev->ip }})">
                                            <span class="material-symbols-outlined text-[10px]">desktop_windows</span>
                                            <span>VNC</span>
                                        </button>
                                    @else
                                        <button type="button"
                                                onclick="event.stopPropagation(); openAccessModal('VNC', '{{ $netDev->ip }}', 5900, '{{ addslashes($netDev->name) }}', 'Red Valle Seco', false)"
                                                class="px-1.5 py-0.5 rounded text-[8px] font-mono bg-obsidian-card/90 hover:bg-amber-950/40 border border-obsidian-border hover:border-amber-500/40 text-obsidian-muted hover:text-amber-300 transition flex items-center gap-0.5 shrink-0 cursor-pointer"
                                                title="Debe iniciar sesión para conectar por VNC">
                                            <span class="material-symbols-outlined text-[10px] text-amber-400/80">lock</span>
                                            <span>VNC</span>
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs font-mono text-obsidian-muted">
                            No hay dispositivos registrados en red Valle Seco.
                        </div>
                    @endforelse
                </div>
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
                             data-tech-id="{{ $s->id }}"
                             data-tech-kind="service">
                            
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
                             data-tech-id="{{ $site->id }}"
                             data-tech-kind="site">
                            
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

    <!-- NUEVO: CUADRO CON GRÁFICO HISTÓRICO TEMPORAL DE LATENCIA & DISPONIBILIDAD (24 HORAS) -->
    <div id="tt-chart-container" class="mt-2 pt-1.5 border-t border-obsidian-border/60">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[9px] uppercase font-bold text-obsidian-cyan tracking-wider flex items-center gap-1">
                <span class="material-symbols-outlined text-[12px]">ssid_chart</span>
                Histórico (Últimas 24 Horas)
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

<!-- ========================================================================= -->
<!-- MODAL AVISO DE INICIO DE SESIÓN REQUERIDO (USUARIO NO LOGUEADO)           -->
<!-- ========================================================================= -->
<div id="modal-access-login-prompt" class="fixed inset-0 z-[100000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-md w-full rounded-2xl p-6 border border-amber-500/40 shadow-2xl space-y-4 font-mono">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-amber-950/80 border border-amber-500/40 text-amber-400 flex items-center justify-center shadow-lg shadow-amber-950">
                    <span class="material-symbols-outlined text-xl">lock</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Acceso Restringido</h3>
                    <p class="text-[10px] text-obsidian-muted" id="access-prompt-service">Consola de Red y Monitoreo</p>
                </div>
            </div>
            <button onclick="closeAccessPromptModal()" class="text-obsidian-muted hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
            <div class="p-3.5 rounded-xl bg-amber-950/30 border border-amber-500/30 text-amber-300 text-[11px] space-y-1.5">
                <p class="font-bold flex items-center gap-1.5 text-white">
                    <span class="material-symbols-outlined text-sm text-amber-400">shield_person</span>
                    <span>Autenticación Requerida</span>
                </p>
                <p class="leading-relaxed">
                    Para acceder <span id="access-prompt-action">al servicio</span> del equipo <strong id="access-prompt-device" class="text-white"></strong> (<code id="access-prompt-ip" class="text-obsidian-cyan"></code>) en <strong id="access-prompt-site" class="text-white"></strong>, debe iniciar sesión con una cuenta autorizada de <strong>Operador</strong> o <strong>Administrador</strong>.
                </p>
            </div>
            <p class="text-[10px] text-obsidian-muted leading-relaxed">
                🔒 El acceso a consolas remotas (VNC, Telnet) y paneles de configuración web está reservado exclusivamente al personal técnico autorizado de Corpoelec para labores de soporte y monitoreo.
            </p>
        </div>

        <div class="pt-3 border-t border-obsidian-border flex items-center justify-end gap-2">
            <button type="button" onclick="closeAccessPromptModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white text-xs">
                Cancelar
            </button>
            <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold text-xs flex items-center gap-1.5 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20">
                <span class="material-symbols-outlined text-sm">login</span>
                Iniciar Sesión
            </a>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL LANZADOR DE SESIÓN VNC (USUARIO CON ROL ADMINISTRADOR U OPERADOR)   -->
<!-- ========================================================================= -->
<div id="modal-vnc-session" class="fixed inset-0 z-[100000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-lg w-full rounded-2xl p-6 border border-cyan-500/40 shadow-2xl space-y-4 font-mono">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 flex items-center justify-center shadow-lg shadow-cyan-950">
                    <span class="material-symbols-outlined text-xl">desktop_windows</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Escritorio Remoto VNC</h3>
                    <p class="text-[10px] text-obsidian-cyan">Conexión Gráfica en Tiempo Real (RFB)</p>
                </div>
            </div>
            <button onclick="closeVncSessionModal()" class="text-obsidian-muted hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
            <div class="p-3.5 rounded-xl bg-cyan-950/40 border border-cyan-500/40 text-white text-[11px] space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Equipo Destino:</span>
                    <strong id="vnc-session-device" class="text-cyan-300"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Sede / Ubicación:</span>
                    <span id="vnc-session-site" class="text-white"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Dirección IP:</span>
                    <code id="vnc-session-ip" class="text-obsidian-cyan font-bold"></code>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Puerto de Servicio:</span>
                    <span class="text-white font-mono">5900 (VNC RFB)</span>
                </div>
            </div>

            <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border text-[11px] text-obsidian-muted space-y-1">
                <p class="text-white font-semibold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-obsidian-cyan">verified_user</span>
                    <span>Acceso Habilitado</span>
                </p>
                <p>
                    Sesión autorizada para <strong class="text-white">{{ Auth::user() ? Auth::user()->name : 'Operador' }}</strong> (Rol: <code class="text-obsidian-cyan uppercase">{{ Auth::user() ? Auth::user()->role : 'operador' }}</code>).
                </p>
            </div>
        </div>

        <div class="pt-3 border-t border-obsidian-border flex items-center justify-between">
            <a id="vnc-native-link" href="#" class="text-[11px] text-obsidian-muted hover:text-cyan-300 transition flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">open_in_new</span>
                <span>Visor Local (vnc://)</span>
            </a>
            <div class="flex items-center gap-2">
                <button type="button" onclick="closeVncSessionModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white text-xs">
                    Cerrar
                </button>
                <button id="btn-launch-vnc-web" type="button" onclick="launchWebVnc()" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold text-xs flex items-center gap-1.5 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">tv</span>
                    <span>Abrir Visor Web</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL LANZADOR DE TERMINAL TELNET (USUARIO CON ROL ADMIN U OPERADOR)      -->
<!-- ========================================================================= -->
<div id="modal-telnet-session" class="fixed inset-0 z-[100000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-lg w-full rounded-2xl p-6 border border-cyan-500/40 shadow-2xl space-y-4 font-mono">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 flex items-center justify-center shadow-lg shadow-cyan-950">
                    <span class="material-symbols-outlined text-xl">terminal</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Terminal Telnet CLI</h3>
                    <p class="text-[10px] text-obsidian-cyan">Consola de Red en Tiempo Real (RFC 854)</p>
                </div>
            </div>
            <button onclick="closeTelnetSessionModal()" class="text-obsidian-muted hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
            <div class="p-3.5 rounded-xl bg-cyan-950/40 border border-cyan-500/40 text-white text-[11px] space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Equipo Destino:</span>
                    <strong id="telnet-session-device" class="text-cyan-300"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Sede / Ubicación:</span>
                    <span id="telnet-session-site" class="text-white"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Dirección IP:</span>
                    <code id="telnet-session-ip" class="text-obsidian-cyan font-bold"></code>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Puerto de Servicio:</span>
                    <span id="telnet-session-port" class="text-white font-mono">23 (Telnet RFC 854)</span>
                </div>
            </div>

            <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border text-[11px] text-obsidian-muted space-y-1">
                <p class="text-white font-semibold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-obsidian-cyan">verified_user</span>
                    <span>Acceso Habilitado</span>
                </p>
                <p>
                    Sesión autorizada para <strong class="text-white">{{ Auth::user() ? Auth::user()->name : 'Operador' }}</strong> (Rol: <code class="text-obsidian-cyan uppercase">{{ Auth::user() ? Auth::user()->role : 'operador' }}</code>).
                </p>
            </div>
        </div>

        <div class="pt-3 border-t border-obsidian-border flex items-center justify-between">
            <a id="telnet-native-link" href="#" class="text-[11px] text-obsidian-muted hover:text-cyan-300 transition flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">open_in_new</span>
                <span>Cliente Local (telnet://)</span>
            </a>
            <div class="flex items-center gap-2">
                <button type="button" onclick="closeTelnetSessionModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white text-xs">
                    Cerrar
                </button>
                <button id="btn-launch-telnet-web" type="button" onclick="launchWebTelnet()" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold text-xs flex items-center gap-1.5 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">terminal</span>
                    <span>Abrir Terminal Web</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL LANZADOR DE PANEL WEB (USUARIO CON ROL ADMIN U OPERADOR)           -->
<!-- ========================================================================= -->
<div id="modal-web-session" class="fixed inset-0 z-[100000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-lg w-full rounded-2xl p-6 border border-cyan-500/40 shadow-2xl space-y-4 font-mono">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 flex items-center justify-center shadow-lg shadow-cyan-950">
                    <span class="material-symbols-outlined text-xl">language</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Panel Web Administrativo</h3>
                    <p class="text-[10px] text-obsidian-cyan">Interfaz de Configuración del Dispositivo</p>
                </div>
            </div>
            <button onclick="closeWebSessionModal()" class="text-obsidian-muted hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
            <div class="p-3.5 rounded-xl bg-cyan-950/40 border border-cyan-500/40 text-white text-[11px] space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Equipo Destino:</span>
                    <strong id="web-session-device" class="text-cyan-300"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Sede / Ubicación:</span>
                    <span id="web-session-site" class="text-white"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Dirección IP:</span>
                    <code id="web-session-ip" class="text-obsidian-cyan font-bold"></code>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Puerto / Protocolo:</span>
                    <span id="web-session-port" class="text-white font-mono">80 (HTTP)</span>
                </div>
                <div class="flex justify-between items-center pt-1 border-t border-cyan-500/20">
                    <span class="text-obsidian-muted">URL Directa:</span>
                    <a id="web-session-url" href="#" target="_blank" class="text-obsidian-cyan hover:underline truncate max-w-[280px]"></a>
                </div>
            </div>

            <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border text-[11px] text-obsidian-muted space-y-1">
                <p class="text-white font-semibold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-obsidian-cyan">verified_user</span>
                    <span>Acceso Habilitado</span>
                </p>
                <p>
                    Sesión autorizada para <strong class="text-white">{{ Auth::user() ? Auth::user()->name : 'Operador' }}</strong> (Rol: <code class="text-obsidian-cyan uppercase">{{ Auth::user() ? Auth::user()->role : 'operador' }}</code>).
                </p>
            </div>
        </div>

        <div class="pt-3 border-t border-obsidian-border flex items-center justify-end gap-2">
            <button type="button" onclick="closeWebSessionModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white text-xs">
                Cerrar
            </button>
            <button id="btn-launch-web-admin" type="button" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold text-xs flex items-center gap-1.5 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20 cursor-pointer">
                <span class="material-symbols-outlined text-sm">open_in_new</span>
                <span>Abrir Panel Web</span>
            </button>
        </div>
    </div>
</div>

<script>
    function openAccessModal(type, ip, port, name, siteName, isAuth) {
        var tooltip = document.getElementById('tech-tooltip');
        if (tooltip) {
            tooltip.classList.remove('show');
        }

        type = (type || 'VNC').toUpperCase();
        port = port || (type === 'TELNET' ? 23 : (type === 'WEB' ? 80 : 5900));

        if (!isAuth) {
            const svcTitle = type === 'TELNET' ? 'Terminal Telnet CLI' : (type === 'WEB' ? 'Panel Web Administrativo' : 'Control Remoto VNC');
            const actionText = type === 'TELNET' ? 'a la consola de comandos Telnet' : (type === 'WEB' ? 'al panel de administración web' : 'al escritorio remoto VNC');
            
            const pSvc = document.getElementById('access-prompt-service');
            if (pSvc) pSvc.innerText = svcTitle;
            const pAct = document.getElementById('access-prompt-action');
            if (pAct) pAct.innerText = actionText;
            const pDev = document.getElementById('access-prompt-device');
            if (pDev) pDev.innerText = name;
            const pSite = document.getElementById('access-prompt-site');
            if (pSite) pSite.innerText = siteName;
            const pIp = document.getElementById('access-prompt-ip');
            if (pIp) pIp.innerText = ip + (port ? ':' + port : '');

            const m = document.getElementById('modal-access-login-prompt');
            if (m) {
                m.classList.remove('hidden');
                m.classList.add('flex');
            }
            return;
        }

        if (type === 'TELNET') {
            document.getElementById('telnet-session-device').innerText = name;
            document.getElementById('telnet-session-site').innerText = siteName;
            document.getElementById('telnet-session-ip').innerText = ip;
            document.getElementById('telnet-session-port').innerText = port + ' (Telnet RFC 854)';
            document.getElementById('telnet-native-link').href = 'telnet://' + ip + ':' + port;
            window._currentTelnetTarget = { ip: ip, port: port, name: name, site: siteName };
            const m = document.getElementById('modal-telnet-session');
            if (m) {
                m.classList.remove('hidden');
                m.classList.add('flex');
            }
        } else if (type === 'WEB') {
            const proto = (port === 443 || port === 8443) ? 'https' : 'http';
            const url = proto + '://' + ip + ((port === 80 || port === 443) ? '' : ':' + port);
            document.getElementById('web-session-device').innerText = name;
            document.getElementById('web-session-site').innerText = siteName;
            document.getElementById('web-session-ip').innerText = ip;
            document.getElementById('web-session-port').innerText = port + ' (' + proto.toUpperCase() + ')';
            const aUrl = document.getElementById('web-session-url');
            if (aUrl) {
                aUrl.innerText = url;
                aUrl.href = url;
            }
            const btnWeb = document.getElementById('btn-launch-web-admin');
            if (btnWeb) {
                btnWeb.onclick = function() {
                    window.open(url, '_blank');
                    closeWebSessionModal();
                };
            }
            const m = document.getElementById('modal-web-session');
            if (m) {
                m.classList.remove('hidden');
                m.classList.add('flex');
            }
        } else { // VNC
            document.getElementById('vnc-session-device').innerText = name;
            document.getElementById('vnc-session-site').innerText = siteName;
            document.getElementById('vnc-session-ip').innerText = ip;
            document.getElementById('vnc-native-link').href = 'vnc://' + ip + ':5900';
            window._currentVncTarget = { ip: ip, port: 5900, name: name, site: siteName };
            const m = document.getElementById('modal-vnc-session');
            if (m) {
                m.classList.remove('hidden');
                m.classList.add('flex');
            }
        }
    }

    function openVncModal(ip, name, siteName, isAuth) {
        openAccessModal('VNC', ip, 5900, name, siteName, isAuth);
    }

    function closeAccessPromptModal() {
        const m = document.getElementById('modal-access-login-prompt');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    function closeVncPromptModal() {
        closeAccessPromptModal();
    }

    function closeVncSessionModal() {
        const m = document.getElementById('modal-vnc-session');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    function closeTelnetSessionModal() {
        const m = document.getElementById('modal-telnet-session');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    function closeWebSessionModal() {
        const m = document.getElementById('modal-web-session');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    async function launchWebVnc() {
        if (!window._currentVncTarget) return;

        const btn = document.getElementById('btn-launch-vnc-web');
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> <span>Iniciando...</span>';
        }

        try {
            const res = await fetch("{{ route('admin.vnc.session') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    ip: window._currentVncTarget.ip,
                    name: window._currentVncTarget.name,
                    site: window._currentVncTarget.site
                })
            });

            const data = await res.json();
            if (data.success && data.viewer_url) {
                closeVncSessionModal();
                window.open(data.viewer_url, '_blank', 'width=1280,height=800,menubar=no,status=no,toolbar=no');
            } else {
                alert('No se pudo inicializar la sesión VNC: ' + (data.message || 'Error desconocido'));
            }
        } catch (err) {
            console.error('Error iniciando sesión VNC:', err);
            alert('Error de red al intentar conectar con el proxy VNC.');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }

    async function launchWebTelnet() {
        if (!window._currentTelnetTarget) return;

        const btn = document.getElementById('btn-launch-telnet-web');
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> <span>Iniciando...</span>';
        }

        try {
            const res = await fetch("{{ route('admin.telnet.session') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    ip: window._currentTelnetTarget.ip,
                    port: window._currentTelnetTarget.port,
                    name: window._currentTelnetTarget.name,
                    site: window._currentTelnetTarget.site
                })
            });

            const data = await res.json();
            if (data.success && data.viewer_url) {
                closeTelnetSessionModal();
                window.open(data.viewer_url, '_blank', 'width=1280,height=800,menubar=no,status=no,toolbar=no');
            } else {
                alert('No se pudo inicializar la sesión Telnet: ' + (data.message || 'Error desconocido'));
            }
        } catch (err) {
            console.error('Error iniciando sesión Telnet:', err);
            alert('Error de red al intentar conectar con el proxy Telnet.');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }
</script>
@endsection

@push('scripts')
<script>
    window._userCanRemote = {{ (Auth::check() && in_array(Auth::user()->role, ['admin', 'operator'])) ? 'true' : 'false' }};
    window._userCanVnc = window._userCanRemote;
</script>
<script id="monit-payload" type="application/json">{"s":@json($serviceHistoryMap),"t":@json($siteHistoryMap)}</script>
<script src="{{ asset('js/monitoring-app.min.js') }}" defer></script>
@endpush
