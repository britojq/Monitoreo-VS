@extends('layouts.admin')

@section('page_title', ($activeTab ?? 'radar') === 'snmp' ? 'Telemetría SNMP Integral' : 'Auto-Discovery y Detección Anti-Rogue')

@section('admin_content')
<style>
    .custom-table-scroll {
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: #06b6d4 #040b15;
    }
    .custom-table-scroll::-webkit-scrollbar {
        height: 10px;
    }
    .custom-table-scroll::-webkit-scrollbar-track {
        background: #040b15;
        border-radius: 5px;
        border: 1px solid #1e293b;
    }
    .custom-table-scroll::-webkit-scrollbar-thumb {
        background: #06b6d4;
        border-radius: 5px;
        border: 2px solid #040b15;
    }
    .custom-table-scroll::-webkit-scrollbar-thumb:hover {
        background: #22d3ee;
    }
</style>

<div class="space-y-4">
    <!-- CABECERA -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-[10.5px] font-mono font-semibold group shadow-xs">
                    <span class="material-symbols-outlined text-xs group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-[10.5px] font-mono text-obsidian-muted">Supervisión &amp; Telemetría de Red</span>
            </div>
            <h1 class="text-xl font-black text-white tracking-wide flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-2xl">{{ ($activeTab ?? 'radar') === 'snmp' ? 'sensors' : 'radar' }}</span>
                {{ ($activeTab ?? 'radar') === 'snmp' ? 'Monitoreo y Telemetría SNMP' : 'Radar & Auto-Discovery de Red' }}
            </h1>
            <p class="text-xs font-mono text-obsidian-muted">
                {{ ($activeTab ?? 'radar') === 'snmp' ? 'Supervisión de métricas OID, estados operacionales de puertos y ancho de banda en switches, routers y firewalls' : 'Supervisión continua de subredes CIDR, inventario dinámico de MACs y detección de equipos intrusos' }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($isClusterSlave ?? false)
                <div class="relative group">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-amber-950/80 border border-amber-500/40 text-amber-300 transition shadow-xs cursor-help">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                        <span class="material-symbols-outlined text-base text-amber-400">dns</span>
                    </span>
                    <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-amber-500/50 shadow-xl shadow-amber-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-xs text-amber-400">dns</span>
                            <span class="font-bold text-amber-200">Fuente: Servidor Esclavo (Solo Lectura)</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="relative group">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 transition shadow-xs cursor-help">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        <span class="material-symbols-outlined text-base text-cyan-400">dns</span>
                    </span>
                    <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-xs text-cyan-400">dns</span>
                            <span class="font-bold text-cyan-200">Fuente: Servidor Maestro</span>
                        </div>
                    </div>
                </div>
            @endif

            @if(($activeTab ?? 'radar') === 'snmp')
                @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('snmp.manage')) && !($isClusterSlave ?? false))
                    <div class="relative group">
                        <button type="button" onclick="openRemoteActivationModal()" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-obsidian-panel border border-amber-500/40 text-amber-300 hover:bg-amber-600 hover:text-white transition text-xs font-mono font-bold shadow-xs cursor-pointer">
                            <span class="material-symbols-outlined text-base">bolt</span>
                        </button>
                        <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                            <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-amber-500/50 shadow-xl shadow-amber-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-xs text-amber-400">bolt</span>
                                <span class="font-bold text-amber-200">Activación Remota SNMP</span>
                            </div>
                        </div>
                    </div>
                    <div class="relative group">
                        <button type="button" onclick="openNewDeviceModal()" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-obsidian-panel border border-purple-500/40 text-purple-300 hover:bg-purple-600 hover:text-white transition text-xs font-mono font-bold shadow-xs cursor-pointer">
                            <span class="material-symbols-outlined text-base">sensors</span>
                            <span class="font-bold text-sm leading-none">+</span>
                        </button>
                        <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                            <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-purple-500/50 shadow-xl shadow-purple-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-xs text-purple-400">sensors</span>
                                <span class="font-bold text-purple-200">Agregar Dispositivo SNMP</span>
                            </div>
                        </div>
                    </div>
                @endif

                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('snmp.poll'))
                    <div class="relative group">
                        <button type="button" onclick="pollAllDevices()" id="btn-poll-all" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black transition text-xs font-mono font-bold shadow-md shadow-cyan-500/20 cursor-pointer">
                            <span class="material-symbols-outlined text-base">sync</span>
                        </button>
                        <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                            <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-xs text-cyan-400">sync</span>
                                <span class="font-bold text-cyan-200">Sondear Todo Ahora</span>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('discovery.authorize')) && !($isClusterSlave ?? false))
                    <div class="relative group">
                        <button type="button" onclick="openSubnetModal()" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-obsidian-panel border border-purple-500/40 text-purple-300 hover:bg-purple-600 hover:text-white transition text-xs font-mono font-bold shadow-xs cursor-pointer">
                            <span class="material-symbols-outlined text-base">hub</span>
                            <span class="font-bold text-sm leading-none">+</span>
                        </button>
                        <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                            <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-purple-500/50 shadow-xl shadow-purple-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-xs text-purple-400">hub</span>
                                <span class="font-bold text-purple-200">Registrar Subred CIDR</span>
                            </div>
                        </div>
                    </div>
                @endif

                @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('discovery.scan')) && !($isClusterSlave ?? false))
                    <div class="relative group">
                        <button type="button" onclick="openScanModal()" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black transition text-xs font-mono font-bold shadow-md shadow-cyan-500/20 cursor-pointer">
                            <span class="material-symbols-outlined text-base">travel_explore</span>
                        </button>
                        <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                            <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-xs text-cyan-400">travel_explore</span>
                                <span class="font-bold text-cyan-200">Escanear Red Ahora</span>
                            </div>
                        </div>
                    </div>
                @endif
            @endif

            @if($isClusterSlave ?? false)
                <div class="relative group">
                    <span class="px-2.5 py-1.5 rounded-lg bg-gray-900 border border-gray-800 text-gray-500 font-mono text-xs inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs">lock</span>
                    </span>
                    <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-gray-700 shadow-xl text-gray-300 text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-xs text-gray-400">lock</span>
                            <span>Solo Lectura (Modo Esclavo)</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- PESTAÑAS RADAR & TELEMETRÍA DE RED (PANEL DE NAVEGACIÓN COMPACTO) -->
    <div class="flex flex-wrap items-center gap-1.5 border-b border-obsidian-border/80 pb-2.5">
        <a href="{{ route('admin.discovery.index', ['tab' => 'radar']) }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ ($activeTab ?? 'radar') === 'radar' ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Auto-Discovery de Red y Detección Anti-Rogue">
            <span class="material-symbols-outlined text-sm">radar</span>
            <span>Auto-Discovery &amp; Anti-Rogue</span>
            @if(isset($radarPendingDevices) && $radarPendingDevices > 0)
                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ ($activeTab ?? 'radar') === 'radar' ? 'bg-black text-amber-300' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30' }}">{{ $radarPendingDevices }}</span>
            @endif
        </a>
        <a href="{{ route('admin.discovery.index', ['tab' => 'snmp']) }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ ($activeTab ?? 'radar') === 'snmp' ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Monitoreo y Telemetría SNMP">
            <span class="material-symbols-outlined text-sm">sensors</span>
            <span>Telemetría SNMP</span>
            @if(isset($snmpTotalDevices) && $snmpTotalDevices > 0)
                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ ($activeTab ?? 'radar') === 'snmp' ? 'bg-black text-cyan-300' : 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' }}">{{ $snmpTotalDevices }}</span>
            @endif
        </a>
        @if(!auth()->user() || auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('netradar.view')))
        <a href="{{ route('admin.netradar.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.netradar.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="NET Radar (Tráfico y Hosts)">
            <span class="material-symbols-outlined text-sm">troubleshoot</span>
            <span>NET Radar (Tráfico &amp; Hosts)</span>
        </a>
        @endif
        <a href="{{ route('admin.traps.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.traps.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Alertas SNMP y Eventos UDP 162">
            <span class="material-symbols-outlined text-sm">forward_to_inbox</span>
            <span>Alertas SNMP (Eventos)</span>
        </a>
    </div>

    <!-- NOTIFICACIONES FLASH -->
    @if(session('status'))
    <div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-mono flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-obsidian-muted hover:text-white">&times;</button>
    </div>
    @endif
    @if(session('success'))
    <div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-mono flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-obsidian-muted hover:text-white">&times;</button>
    </div>
    @endif
    @if(session('warning'))
    <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-mono flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">warning</span>
            <span>{{ session('warning') }}</span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-obsidian-muted hover:text-white">&times;</button>
    </div>
    @endif
    @if(session('error'))
    <div class="p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-mono flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">error</span>
            <span>{{ session('error') }}</span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-obsidian-muted hover:text-white">&times;</button>
    </div>
    @endif

@if(($activeTab ?? 'radar') === 'snmp')
    <!-- ===================================================================== -->
    <!-- CONTENIDO PESTAÑA: TELEMETRÍA SNMP INTEGRAL                           -->
    <!-- ===================================================================== -->

    <!-- HUD STATS SNMP -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- TOTAL SNMP -->
        <div class="p-3.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80 flex items-center gap-3.5 shadow-sm" title="Total de dispositivos gestionados vía SNMP">
            <div class="w-10 h-10 rounded-lg bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                <span class="material-symbols-outlined text-xl">router</span>
            </div>
            <div>
                <p class="text-[11px] font-mono uppercase text-obsidian-muted">Total Dispositivos</p>
                <p class="text-xl font-bold font-mono text-white">{{ $snmpTotalDevices }}</p>
            </div>
        </div>

        <!-- ONLINE SNMP -->
        <div class="p-3.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80 flex items-center gap-3.5 shadow-sm" title="Equipos respondiendo sondeos SNMP exitosamente">
            <div class="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                <span class="material-symbols-outlined text-xl">check_circle</span>
            </div>
            <div>
                <p class="text-[11px] font-mono uppercase text-emerald-300">Equipos Online</p>
                <p class="text-xl font-bold font-mono text-emerald-400">{{ $onlineDevices }}</p>
            </div>
        </div>

        <!-- CON FALLAS SNMP -->
        <div class="p-3.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80 flex items-center gap-3.5 shadow-sm" title="Equipos con fallos consecutivos de sondeo SNMP">
            <div class="w-10 h-10 rounded-lg bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-400">
                <span class="material-symbols-outlined text-xl">error</span>
            </div>
            <div>
                <p class="text-[11px] font-mono uppercase text-red-300">Con Fallas / Timeout</p>
                <p class="text-xl font-bold font-mono text-red-400">{{ $failingDevices }}</p>
            </div>
        </div>

        <!-- INTERFACES MONITOREADAS -->
        <div class="p-3.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80 flex items-center gap-3.5 shadow-sm" title="Interfaces y puertos de red bajo telemetría continua">
            <div class="w-10 h-10 rounded-lg bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-purple-400">
                <span class="material-symbols-outlined text-xl">settings_ethernet</span>
            </div>
            <div>
                <p class="text-[11px] font-mono uppercase text-purple-300">Puertos Monitoreados</p>
                <p class="text-xl font-bold font-mono text-purple-400">{{ $monitoredInterfaces }} <span class="text-xs font-normal text-obsidian-muted">/ {{ $totalInterfaces }}</span></p>
            </div>
        </div>
    </div>

    <!-- FILTROS Y BÚSQUEDA SNMP -->
    <div class="p-3 rounded-xl bg-obsidian-panel border border-obsidian-border">
        <form method="GET" action="{{ route('admin.discovery.index') }}" class="flex flex-wrap items-center gap-2.5">
            <input type="hidden" name="tab" value="snmp">

            <div class="flex-1 min-w-[200px] relative">
                <span class="material-symbols-outlined absolute left-3 top-2 text-obsidian-muted text-sm">search</span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por Nombre, IP, Fabricante o SysName..." class="w-full pl-9 pr-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
            </div>

            <select name="status" class="px-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                <option value="">Todos los Estados</option>
                <option value="success" {{ $statusFilter == 'success' ? 'selected' : '' }}>✅ Online (Éxito)</option>
                <option value="failed" {{ $statusFilter == 'failed' ? 'selected' : '' }}>❌ Fallo de Sondeo</option>
                <option value="unreachable" {{ $statusFilter == 'unreachable' ? 'selected' : '' }}>⚠️ Inaccesible</option>
                <option value="pending" {{ $statusFilter == 'pending' ? 'selected' : '' }}>⏳ Pendiente</option>
            </select>

            <select name="device_type" class="px-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                <option value="">Todos los Tipos</option>
                <option value="switch" {{ $typeFilter == 'switch' ? 'selected' : '' }}>Switch</option>
                <option value="router" {{ $typeFilter == 'router' ? 'selected' : '' }}>Router</option>
                <option value="firewall" {{ $typeFilter == 'firewall' ? 'selected' : '' }}>Firewall</option>
                <option value="server" {{ $typeFilter == 'server' ? 'selected' : '' }}>Servidor</option>
                <option value="ups" {{ $typeFilter == 'ups' ? 'selected' : '' }}>UPS</option>
                <option value="unknown" {{ $typeFilter == 'unknown' ? 'selected' : '' }}>Otro</option>
            </select>

            <select name="site_id" class="px-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                <option value="">Todas las Sedes</option>
                @foreach($sites as $site)
                <option value="{{ $site->id }}" {{ $siteFilter == $site->id ? 'selected' : '' }}>{{ $site->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-cyan-950/60 transition text-xs font-mono font-bold cursor-pointer">
                Filtrar
            </button>

            @if($search || $statusFilter || $typeFilter || $siteFilter)
            <a href="{{ route('admin.discovery.index', ['tab' => 'snmp']) }}" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-red-400 hover:bg-red-950/40 transition text-xs font-mono cursor-pointer" title="Limpiar Filtros">
                ✕
            </a>
            @endif
        </form>
    </div>

    <!-- TABLA DE DISPOSITIVOS SNMP -->
    <div class="rounded-xl bg-obsidian-panel border border-obsidian-border overflow-hidden shadow-sm">
        <div class="custom-table-scroll">
            <table class="w-full text-left text-xs font-mono whitespace-nowrap">
                <thead class="bg-[#040d1a] border-b border-obsidian-border text-obsidian-muted uppercase text-[10px]">
                    <tr>
                        <th class="px-2.5 py-2.5 font-mono font-bold tracking-wider" title="Estado de conectividad y sondeo SNMP">Estado / Polling</th>
                        <th class="px-2.5 py-2.5 font-mono font-bold tracking-wider" title="Nombre identificador del equipo">Dispositivo</th>
                        <th class="px-2.5 py-2.5 font-mono font-bold tracking-wider" title="Dirección IP de gestión">Dirección IP</th>
                        <th class="px-2.5 py-2.5 font-mono font-bold tracking-wider" title="Tipo de equipo">Tipo</th>
                        <th class="px-2.5 py-2.5 text-center font-mono font-bold tracking-wider" title="Puertos físicos detectados y en monitoreo">Puertos</th>
                        <th class="px-2.5 py-2.5 font-mono font-bold tracking-wider" title="Sede física asignada">Sede</th>
                        <th class="px-2.5 py-2.5 font-mono font-bold tracking-wider" title="Marca de tiempo del último sondeo">Último Sondeo</th>
                        <th class="px-2.5 py-2.5 text-right font-mono font-bold tracking-wider" title="Acciones de telemetría y configuración">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/50">
                    @forelse($devices as $dev)
                    <tr class="hover:bg-cyan-950/20 transition-colors {{ !$dev->is_active ? 'opacity-60 bg-gray-950/30' : '' }}">
                        <!-- ESTADO -->
                        <td class="px-2.5 py-2 whitespace-nowrap">
                            @if(!$dev->is_active)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-gray-800 border border-gray-700 text-gray-400 text-[10px]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span>
                                    <span>Pausado</span>
                                </span>
                            @elseif($dev->last_poll_status === 'success')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    <span>Online ({{ round($dev->last_response_time_ms, 1) }}ms)</span>
                                </span>
                            @elseif($dev->last_poll_status === 'failed')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-red-500/10 border border-red-500/30 text-red-400 text-[10px] font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                    <span>Fallo Sondeo ({{ $dev->consecutive_failures }})</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[10px]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                    <span>Pendiente</span>
                                </span>
                            @endif
                        </td>

                        <!-- DISPOSITIVO -->
                        <td class="px-2.5 py-2 whitespace-nowrap">
                            <div class="flex items-center gap-1.5">
                                <span class="text-white font-bold text-xs">{{ $dev->name }}</span>
                                @if($dev->sys_name && $dev->sys_name !== $dev->name)
                                    <span class="text-[10px] text-obsidian-muted font-normal" title="Hostname: {{ $dev->sys_name }}">({{ $dev->sys_name }})</span>
                                @endif
                            </div>
                        </td>

                        <!-- DIRECCIÓN IP -->
                        <td class="px-2.5 py-2 whitespace-nowrap">
                            <span class="text-cyan-300 font-mono text-xs" title="Puerto UDP: {{ $dev->snmp_port }} ({{ $dev->snmp_version }})">{{ $dev->ip_address }}</span>
                        </td>

                        <!-- TIPO -->
                        <td class="px-2.5 py-2 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded text-[9.5px] font-bold uppercase bg-cyan-950/70 border border-cyan-500/30 text-cyan-300 inline-block" title="{{ trim(($dev->vendor ?? '') . ' ' . ($dev->model ?? '')) }}">
                                {{ strtoupper($dev->device_type) }}
                            </span>
                        </td>

                        <!-- PUERTOS -->
                        <td class="px-2.5 py-2 text-center whitespace-nowrap">
                            @php
                                $totalIfaces = $dev->interfaces ? $dev->interfaces->count() : 0;
                                $monitoredIfaces = $dev->interfaces ? $dev->interfaces->where('is_monitored', true)->count() : 0;
                            @endphp
                            <span class="px-2 py-0.5 rounded bg-obsidian-bg border border-obsidian-border text-cyan-300 text-[10px]" title="Puertos en monitoreo">
                                {{ $monitoredIfaces }} de {{ $totalIfaces }}
                            </span>
                        </td>

                        <!-- SEDE -->
                        <td class="px-2.5 py-2 whitespace-nowrap">
                            @if($dev->site)
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-purple-950/70 border border-purple-500/30 text-purple-300 text-[9.5px] font-semibold">
                                    <span class="material-symbols-outlined text-[10px]">domain</span>
                                    <span>{{ $dev->site->name }}</span>
                                </span>
                            @else
                                <span class="text-obsidian-muted text-[10px]">Valle Seco</span>
                            @endif
                        </td>

                        <!-- ÚLTIMO SONDEO -->
                        <td class="px-2.5 py-2 whitespace-nowrap">
                            <span class="text-[10px] text-obsidian-muted" title="{{ $dev->last_poll_at ? $dev->last_poll_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'Nunca' }}">
                                {{ $dev->last_poll_at ? $dev->last_poll_at->diffForHumans() : 'Nunca' }}
                            </span>
                        </td>

                        <!-- ACCIONES -->
                        <td class="px-2.5 py-2 text-right whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-1">
                                <!-- VER INTERFACES & GRÁFICO -->
                                <button type="button" onclick="openInterfacesModal({{ $dev->id }}, '{{ $dev->name }}', '{{ $dev->ip_address }}')" class="p-1 rounded bg-obsidian-panel border border-cyan-500/40 text-cyan-400 hover:bg-cyan-500 hover:text-black transition cursor-pointer shadow-xs inline-flex items-center" title="Ver Interfaces &amp; Gráfico de Tráfico">
                                    <span class="material-symbols-outlined text-[13px]">cable</span>
                                </button>

                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('snmp.poll'))
                                    <!-- SONDEO INMEDIATO -->
                                    <button type="button" onclick="triggerDevicePoll({{ $dev->id }})" class="p-1 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-cyan-300 hover:border-cyan-500/40 transition cursor-pointer shadow-xs inline-flex items-center" title="Sondear Ahora (SNMP GET)">
                                        <span class="material-symbols-outlined text-[13px]">sync</span>
                                    </button>

                                    <!-- DESCUBRIR PUERTOS -->
                                    <button type="button" onclick="triggerInterfaceDiscovery({{ $dev->id }})" class="p-1 rounded bg-obsidian-panel border border-purple-500/40 text-purple-300 hover:bg-purple-600 hover:text-white transition cursor-pointer shadow-xs inline-flex items-center" title="Descubrir Puertos (ifTable)">
                                        <span class="material-symbols-outlined text-[13px]">travel_explore</span>
                                    </button>
                                @endif

                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('snmp.manage'))
                                    @if($isClusterSlave ?? false)
                                        <span class="p-1 rounded bg-gray-900 border border-gray-800 text-gray-500 text-[10px] font-mono inline-flex items-center" title="Modificaciones restringidas al Servidor Master">
                                            <span class="material-symbols-outlined text-[11px]">lock</span>
                                        </span>
                                    @else
                                        <!-- EDITAR -->
                                        <button type="button" onclick="openEditDeviceModal({{ $dev->id }})" class="p-1 rounded bg-obsidian-panel border border-amber-500/40 text-amber-300 hover:bg-amber-500 hover:text-black transition cursor-pointer shadow-xs inline-flex items-center" title="Editar Dispositivo SNMP">
                                            <span class="material-symbols-outlined text-[13px]">edit</span>
                                        </button>

                                        <!-- ELIMINAR -->
                                        <form method="POST" action="{{ route('admin.snmp.destroy', $dev->id) }}" class="inline" onsubmit="return confirm('¿Está seguro de eliminar el dispositivo {{ $dev->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 rounded bg-obsidian-panel border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition cursor-pointer shadow-xs inline-flex items-center" title="Eliminar Dispositivo">
                                                <span class="material-symbols-outlined text-[13px]">delete</span>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-obsidian-muted font-mono text-xs">
                            <span class="material-symbols-outlined text-3xl mb-1 text-obsidian-border block">router</span>
                            No se encontraron dispositivos SNMP configurados o coincidentes con los filtros.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN SNMP -->
        @if($devices->hasPages())
            <div class="px-3.5 py-2.5 bg-obsidian-panel border-t border-obsidian-border/80 flex flex-col sm:flex-row items-center justify-between gap-2.5 text-xs font-mono">
                <div class="text-obsidian-muted text-[10.5px]">
                    Mostrando <span class="text-white font-bold">{{ $devices->firstItem() }}</span> a <span class="text-white font-bold">{{ $devices->lastItem() }}</span> de <span class="text-cyan-400 font-bold">{{ $devices->total() }}</span> dispositivos SNMP (15 por página)
                </div>
                <div class="flex items-center gap-1">
                    {{-- Anterior --}}
                    @if($devices->onFirstPage())
                        <span class="px-2 py-0.5 rounded bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[10.5px]">
                            &laquo; Anterior
                        </span>
                    @else
                        <a href="{{ $devices->previousPageUrl() }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[10.5px]" title="Página Anterior">
                            &laquo; Anterior
                        </a>
                    @endif

                    {{-- Páginas --}}
                    @foreach($devices->getUrlRange(1, $devices->lastPage()) as $page => $url)
                        @if($page == $devices->currentPage())
                            <span class="px-2 py-0.5 rounded bg-cyan-500 text-black font-bold text-[10.5px]">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white transition text-[10.5px]" title="Ir a la página {{ $page }}">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach

                    {{-- Siguiente --}}
                    @if($devices->hasMorePages())
                        <a href="{{ $devices->nextPageUrl() }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[10.5px]" title="Página Siguiente">
                            Siguiente &raquo;
                        </a>
                    @else
                        <span class="px-2 py-0.5 rounded bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[10.5px]">
                            Siguiente &raquo;
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- ===================================================================== -->
    <!-- MODALES SNMP                                                          -->
    <!-- ===================================================================== -->

    <!-- MODAL DE INTERFACES Y GRÁFICO DE TRÁFICO -->
    <div id="interfacesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs hidden">
        <div class="w-full max-w-5xl rounded-2xl bg-obsidian-card border border-obsidian-border shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
            <!-- CABECERA MODAL -->
            <div class="px-6 py-4 bg-obsidian-panel border-b border-obsidian-border flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                        <span class="material-symbols-outlined text-xl">cable</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white flex items-center gap-2">
                            <span id="modal-dev-name">Interfaces de Red</span>
                            <span id="modal-dev-ip" class="px-2 py-0.5 rounded bg-obsidian-bg border border-obsidian-border text-xs font-mono text-cyan-400">0.0.0.0</span>
                        </h3>
                        <p class="text-xs font-mono text-obsidian-muted">Inventario de interfaces físicas y lógicas con telemetría de tráfico en tiempo real</p>
                    </div>
                </div>
                <button type="button" onclick="closeInterfacesModal()" class="p-1.5 rounded-lg text-obsidian-muted hover:text-white hover:bg-obsidian-bg transition text-xl cursor-pointer leading-none">
                    &times;
                </button>
            </div>

            <!-- CUERPO MODAL CON SCROLL -->
            <div class="p-6 overflow-y-auto space-y-6 custom-table-scroll">
                <!-- SECCIÓN GRÁFICO DE TRÁFICO CHART.JS -->
                <div id="trafficChartCard" class="glass-card p-4 rounded-xl border border-obsidian-border/80 bg-obsidian-panel/60 relative">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h4 class="text-xs font-mono font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm text-cyan-400">trending_up</span>
                                <span>Tráfico de Ancho de Banda: <span id="chart-interface-title" class="text-cyan-400">Seleccione una interfaz</span></span>
                            </h4>
                            <p class="text-[11px] font-mono text-obsidian-muted">Métricas de entrada y salida registradas en las últimas 24 horas</p>
                        </div>
                        <div id="chart-stat-badges" class="hidden flex items-center gap-2 text-xs font-mono">
                            <span class="px-2 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">Entrada (bps)</span>
                            <span class="px-2 py-0.5 rounded bg-purple-500/10 border border-purple-500/30 text-purple-400">Salida (bps)</span>
                        </div>
                    </div>

                    <div class="relative min-h-[220px] flex items-center justify-center">
                        <canvas id="interfaceTrafficChart" class="w-full h-56"></canvas>
                        <div id="chart-empty-message" class="absolute inset-0 flex flex-col items-center justify-center text-obsidian-muted text-xs font-mono">
                            <span class="material-symbols-outlined text-3xl mb-1 text-obsidian-border">query_stats</span>
                            <span>Haga clic en el botón "Ver Gráfico" de cualquier interfaz para cargar su curva de ancho de banda.</span>
                        </div>
                        <div id="chart-loading-overlay" class="absolute inset-0 flex items-center justify-center bg-obsidian-bg/80 backdrop-blur-xs rounded-xl hidden">
                            <div class="flex items-center gap-2 text-cyan-400 font-mono text-xs">
                                <span class="material-symbols-outlined text-lg animate-spin">progress_activity</span>
                                <span>Cargando telemetría de interfaz...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLA DE INTERFACES -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-mono font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-purple-400">list_alt</span>
                            <span>Puertos Detectados en el Equipo</span>
                        </h4>
                        <span id="modal-interfaces-count" class="text-xs font-mono text-cyan-400">0 puertos</span>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-obsidian-border/80">
                        <table class="w-full text-left text-xs font-mono">
                            <thead class="bg-obsidian-panel border-b border-obsidian-border text-obsidian-muted text-[10px] uppercase">
                                <tr>
                                    <th class="px-3 py-2.5">Idx</th>
                                    <th class="px-3 py-2.5">Nombre / Alias</th>
                                    <th class="px-3 py-2.5">Descripción</th>
                                    <th class="px-3 py-2.5">Velocidad</th>
                                    <th class="px-3 py-2.5 text-center">Estado Admin</th>
                                    <th class="px-3 py-2.5 text-center">Estado Oper</th>
                                    <th class="px-3 py-2.5 text-center">Monitoreo</th>
                                    <th class="px-3 py-2.5 text-right">Telemetría</th>
                                </tr>
                            </thead>
                            <tbody id="interfaces-table-body" class="divide-y divide-obsidian-border/30">
                                <!-- Inyectado vía JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PIE MODAL -->
            <div class="px-6 py-3 bg-obsidian-panel border-t border-obsidian-border flex items-center justify-end shrink-0">
                <button type="button" onclick="closeInterfacesModal()" class="px-4 py-1.5 rounded-lg bg-obsidian-panel hover:bg-obsidian-bg border border-obsidian-border text-white transition text-xs font-mono cursor-pointer">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL NUEVO DISPOSITIVO SNMP -->
    @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('snmp.manage')) && !($isClusterSlave ?? false))
    <div id="newDeviceModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-xs hidden">
        <div class="w-full max-w-lg rounded-2xl bg-obsidian-card border border-obsidian-border shadow-2xl overflow-hidden flex flex-col max-h-[85vh] sm:max-h-[88vh]">
            <div class="px-6 py-4 bg-obsidian-panel border-b border-obsidian-border flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-purple-400 text-lg">add_circle</span>
                    Registrar Nuevo Dispositivo SNMP
                </h3>
                <button type="button" onclick="closeNewDeviceModal()" class="text-obsidian-muted hover:text-white text-xl leading-none cursor-pointer">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.snmp.store') }}" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                @csrf
                <div class="p-6 space-y-4 overflow-y-auto flex-1 custom-table-scroll">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Nombre Identificador *</label>
                        <input type="text" name="name" required placeholder="Ej. Switch-Core-C3750" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1">Dirección IP *</label>
                            <input type="text" name="ip_address" required placeholder="10.20.23.X" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1">Versión SNMP *</label>
                            <select name="snmp_version" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                                <option value="v2c">v2c (Recomendado)</option>
                                <option value="v3">v3 (Cifrado USM)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1">Comunidad SNMP *</label>
                            <input type="text" name="community" value="public" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1">Tipo de Equipo *</label>
                            <select name="device_type" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                                <option value="switch">Switch</option>
                                <option value="router">Router</option>
                                <option value="firewall">Firewall</option>
                                <option value="server">Servidor</option>
                                <option value="ups">UPS</option>
                                <option value="unknown">Otro</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1">Sede Asignada</label>
                            <select name="site_id" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                                <option value="">Valle Seco (Principal)</option>
                                @foreach($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1">Intervalo de Sondeo (seg)</label>
                            <input type="number" name="poll_interval_seconds" value="60" min="10" max="3600" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Notas u Observaciones</label>
                        <textarea name="notes" rows="2" placeholder="Detalles de ubicación de rack, VLANs..." class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none"></textarea>
                    </div>
                </div>
                <div class="px-6 py-3.5 bg-obsidian-panel/80 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0">
                    <button type="button" onclick="closeNewDeviceModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-obsidian-bg cursor-pointer">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black text-xs font-mono font-bold cursor-pointer">Guardar Dispositivo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDITAR DISPOSITIVO SNMP -->
    <div id="editDeviceModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-xs hidden">
        <div class="w-full max-w-lg rounded-2xl bg-obsidian-card border border-obsidian-border shadow-2xl overflow-hidden flex flex-col max-h-[85vh] sm:max-h-[88vh]">
            <div class="px-6 py-4 bg-obsidian-panel border-b border-obsidian-border flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-cyan-400 text-lg">edit</span>
                    <span>Editar Dispositivo SNMP</span>
                    <span id="edit-modal-ip-badge" class="ml-2 px-2 py-0.5 rounded bg-obsidian-bg border border-obsidian-border text-[11px] font-mono text-cyan-400"></span>
                </h3>
                <button type="button" onclick="closeEditDeviceModal()" class="text-obsidian-muted hover:text-white text-xl leading-none cursor-pointer">&times;</button>
            </div>
            <form id="editDeviceForm" method="POST" action="" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                @csrf
                @method('PUT')
                
                <div class="p-6 space-y-4 overflow-y-auto flex-1 custom-table-scroll">
                    <div id="editModalLoading" class="hidden py-8 text-center text-obsidian-muted">
                        <div class="flex items-center justify-center gap-2 text-cyan-400 font-mono text-xs">
                            <span class="material-symbols-outlined text-xl animate-spin">progress_activity</span>
                            <span>Cargando datos del dispositivo...</span>
                        </div>
                    </div>

                    <div id="editModalFields" class="space-y-4">
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1">Nombre Identificador *</label>
                            <input type="text" id="edit_name" name="name" required placeholder="Ej. Switch-Core-C3750" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Dirección IP *</label>
                                <input type="text" id="edit_ip_address" name="ip_address" required placeholder="10.20.23.X" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Puerto SNMP *</label>
                                <input type="number" id="edit_snmp_port" name="snmp_port" value="161" min="1" max="65535" required class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Versión SNMP *</label>
                                <select id="edit_snmp_version" name="snmp_version" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                                    <option value="v2c">v2c (Recomendado)</option>
                                    <option value="v3">v3 (Cifrado USM)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Comunidad SNMP *</label>
                                <input type="text" id="edit_community" name="community" placeholder="public" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Tipo de Equipo *</label>
                                <select id="edit_device_type" name="device_type" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                                    <option value="switch">Switch</option>
                                    <option value="router">Router</option>
                                    <option value="firewall">Firewall</option>
                                    <option value="server">Servidor</option>
                                    <option value="ups">UPS</option>
                                    <option value="unknown">Otro</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Sede Asignada</label>
                                <select id="edit_site_id" name="site_id" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                                    <option value="">Valle Seco (Principal)</option>
                                    @foreach($sites as $site)
                                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Intervalo de Sondeo (seg)</label>
                                <input type="number" id="edit_poll_interval_seconds" name="poll_interval_seconds" min="10" max="3600" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div class="flex items-center pt-6">
                                <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-mono text-white">
                                    <input type="checkbox" id="edit_is_active" name="is_active" value="1" class="rounded bg-obsidian-bg border-obsidian-border text-cyan-500 focus:ring-0">
                                    <span>Dispositivo Activo</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1">Notas u Observaciones</label>
                            <textarea id="edit_notes" name="notes" rows="2" placeholder="Detalles de ubicación de rack, VLANs..." class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none"></textarea>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-3.5 bg-obsidian-panel/80 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0">
                    <button type="button" onclick="closeEditDeviceModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-obsidian-bg cursor-pointer">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black text-xs font-mono font-bold transition cursor-pointer flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">save</span>
                        <span>Actualizar Cambios</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL ACTIVACIÓN REMOTA SNMP -->
    <div id="remoteActivationModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-xs hidden">
        <div class="w-full max-w-xl rounded-2xl bg-obsidian-card border border-obsidian-border shadow-2xl overflow-hidden flex flex-col max-h-[85vh] sm:max-h-[88vh]">
            <div class="px-6 py-4 bg-obsidian-panel border-b border-obsidian-border flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-400 text-lg">bolt</span>
                    Habilitación Remota de SNMP (Cisco &amp; pfSense)
                </h3>
                <button type="button" onclick="closeRemoteActivationModal()" class="text-obsidian-muted hover:text-white text-xl leading-none cursor-pointer">&times;</button>
            </div>
            <form id="remoteActivationForm" onsubmit="submitRemoteActivation(event)" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                <div class="p-6 space-y-4 overflow-y-auto flex-1 custom-table-scroll">
                    <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-mono flex items-start gap-2">
                        <span class="material-symbols-outlined text-base shrink-0 mt-0.5">security</span>
                        <span>Configura automáticamente <code class="text-white">snmp-server community RO</code> y habilita traps mediante sesión autenticada con registro estricto en auditoría.</span>
                    </div>

                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Dirección IP Destino *</label>
                                <input type="text" id="act-ip" required placeholder="10.20.23.X" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Método de Conexión *</label>
                                <select id="act-method" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                                    <option value="ssh">Cisco IOS (SSH)</option>
                                    <option value="telnet">Cisco IOS Legacy (Telnet)</option>
                                    <option value="pfsense">pfSense Firewall (API REST)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Usuario / API Key *</label>
                                <input type="text" id="act-user" required placeholder="admin" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Contraseña / API Secret *</label>
                                <input type="password" id="act-pass" required placeholder="••••••••" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Enable Secret (Cisco)</label>
                                <input type="password" id="act-secret" placeholder="Opcional" class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-mono text-obsidian-muted mb-1">Comunidad a Establecer *</label>
                                <input type="text" id="act-comm" value="public" required class="w-full px-3 py-2 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                        </div>

                        <div id="activation-result" class="hidden p-3 rounded-lg border text-xs font-mono max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all"></div>
                    </div>
                </div>

                <div class="px-6 py-3.5 bg-obsidian-panel/80 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0">
                    <button type="button" onclick="closeRemoteActivationModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-obsidian-bg cursor-pointer">Cerrar</button>
                    <button type="submit" id="btn-submit-activation" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-black text-xs font-mono font-bold flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">bolt</span>
                        <span>Ejecutar Activación</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- JAVASCRIPT SNMP -->
    <script>
        let trafficChart = null;

        function openInterfacesModal(deviceId, devName, devIp) {
            document.getElementById('modal-dev-name').innerText = devName;
            document.getElementById('modal-dev-ip').innerText = devIp;
            document.getElementById('interfaces-table-body').innerHTML = `
                <tr>
                    <td colspan="8" class="px-4 py-6 text-center text-obsidian-muted">
                        <div class="flex items-center justify-center gap-2 text-cyan-400 font-mono text-xs">
                            <span class="material-symbols-outlined text-lg animate-spin">progress_activity</span>
                            <span>Cargando interfaces del equipo...</span>
                        </div>
                    </td>
                </tr>
            `;
            document.getElementById('interfacesModal').classList.remove('hidden');

            if (trafficChart) {
                trafficChart.destroy();
                trafficChart = null;
            }
            document.getElementById('chart-empty-message').classList.remove('hidden');
            document.getElementById('chart-stat-badges').classList.add('hidden');
            document.getElementById('chart-interface-title').innerText = 'Seleccione una interfaz';

            fetch(`/admin/snmp/${deviceId}/interfaces`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) throw new Error('Error al cargar interfaces');
                    renderInterfacesTable(data.interfaces, deviceId);
                })
                .catch(err => {
                    document.getElementById('interfaces-table-body').innerHTML = `
                        <tr><td colspan="8" class="px-4 py-4 text-center text-red-400">Error cargando interfaces: ${err.message}</td></tr>
                    `;
                });
        }

        function closeInterfacesModal() {
            document.getElementById('interfacesModal').classList.add('hidden');
            if (trafficChart) {
                trafficChart.destroy();
                trafficChart = null;
            }
        }

        function renderInterfacesTable(interfaces, deviceId) {
            document.getElementById('modal-interfaces-count').innerText = `${interfaces.length} puertos detectados`;
            const tbody = document.getElementById('interfaces-table-body');
            if (!interfaces.length) {
                tbody.innerHTML = `
                    <tr><td colspan="8" class="px-4 py-6 text-center text-obsidian-muted">No se encontraron interfaces en este dispositivo. Ejecute "Descubrir Puertos".</td></tr>
                `;
                return;
            }

            const canManageSnmp = {{ (auth()->user()->isAdmin() || auth()->user()->hasPermission('snmp.manage')) ? 'true' : 'false' }};
            let html = '';
            interfaces.forEach(i => {
                const operBadge = i.if_oper_status === 'up'
                    ? '<span class="px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] font-bold">Activo (UP)</span>'
                    : '<span class="px-2 py-0.5 rounded-full bg-red-500/10 border border-red-500/30 text-red-400 text-[10px]">Caído (DOWN)</span>';
                
                const adminBadge = i.if_admin_status === 'up'
                    ? '<span class="text-emerald-400 text-[10px]">Habilitado</span>'
                    : '<span class="text-red-400 text-[10px]">Deshabilitado</span>';

                const speed = i.if_speed ? (i.if_speed >= 1000000000 ? `${(i.if_speed / 1000000000).toFixed(0)} Gbps` : `${(i.if_speed / 1000000).toFixed(0)} Mbps`) : '—';
                
                const monitorControl = canManageSnmp
                    ? `<button type="button" onclick="toggleInterfaceMonitoring(${i.id}, this)" class="px-2 py-0.5 rounded border text-[10px] font-mono cursor-pointer transition ${i.is_monitored ? 'bg-cyan-500/20 border-cyan-500/40 text-cyan-300' : 'bg-obsidian-bg border-obsidian-border text-obsidian-muted hover:text-white'}">${i.is_monitored ? 'Activo' : 'Pausado'}</button>`
                    : `<span class="text-[10px] ${i.is_monitored ? 'text-cyan-400' : 'text-obsidian-muted'}">${i.is_monitored ? 'Sí' : 'No'}</span>`;

                html += `
                    <tr class="hover:bg-obsidian-bg/40 transition">
                        <td class="px-3 py-2 text-obsidian-muted">${i.if_index}</td>
                        <td class="px-3 py-2 text-white font-bold">${i.if_name || 'Puerto ' + i.if_index}</td>
                        <td class="px-3 py-2 text-obsidian-muted truncate max-w-[200px]" title="${i.if_description || ''}">${i.if_description || '—'}</td>
                        <td class="px-3 py-2 text-obsidian-cyan">${speed}</td>
                        <td class="px-3 py-2 text-center">${adminBadge}</td>
                        <td class="px-3 py-2 text-center">${operBadge}</td>
                        <td class="px-3 py-2 text-center">${monitorControl}</td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" onclick="loadInterfaceTrafficChart(${i.id}, '${i.if_name || 'Puerto ' + i.if_index}')" class="px-2.5 py-1 rounded-lg bg-obsidian-bg hover:bg-cyan-500 hover:text-black border border-cyan-500/30 text-cyan-400 transition text-[10.5px] cursor-pointer flex items-center gap-1 ml-auto">
                                <span class="material-symbols-outlined text-xs">analytics</span>
                                <span>Ver Gráfico</span>
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function loadInterfaceTrafficChart(interfaceId, ifName) {
            document.getElementById('chart-empty-message').classList.add('hidden');
            document.getElementById('chart-loading-overlay').classList.remove('hidden');
            document.getElementById('chart-interface-title').innerText = ifName;

            fetch(`/admin/snmp/interfaces/${interfaceId}/metrics`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('chart-loading-overlay').classList.add('hidden');
                    if (!data.success) throw new Error('Error al obtener métricas');
                    renderTrafficChart(data.labels, data.in_bps, data.out_bps);
                    document.getElementById('chart-stat-badges').classList.remove('hidden');
                })
                .catch(err => {
                    document.getElementById('chart-loading-overlay').classList.add('hidden');
                    alert('No hay métricas registradas suficientes para esta interfaz aún.');
                });
        }

        function renderTrafficChart(labels, inBps, outBps) {
            const ctx = document.getElementById('interfaceTrafficChart').getContext('2d');
            if (trafficChart) {
                trafficChart.destroy();
                trafficChart = null;
            }

            trafficChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels.length ? labels : ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                    datasets: [
                        {
                            label: 'Tráfico Entrante (bps)',
                            data: inBps.length ? inBps : [0, 0, 0, 0, 0, 0],
                            borderColor: '#22d3ee',
                            backgroundColor: 'rgba(34, 211, 238, 0.1)',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                        },
                        {
                            label: 'Tráfico Saliente (bps)',
                            data: outBps.length ? outBps : [0, 0, 0, 0, 0, 0],
                            borderColor: '#a855f7',
                            backgroundColor: 'rgba(168, 85, 247, 0.1)',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            labels: { color: '#94a3b8', font: { family: 'monospace', size: 10 } }
                        },
                        tooltip: {
                            backgroundColor: '#051424',
                            borderColor: '#1c2e47',
                            borderWidth: 1,
                            titleColor: '#22d3ee',
                            bodyColor: '#ffffff',
                            callbacks: {
                                label: function(ctx) {
                                    const val = ctx.parsed.y;
                                    if (val >= 1000000) return `${ctx.dataset.label}: ${(val / 1000000).toFixed(2)} Mbps`;
                                    if (val >= 1000) return `${ctx.dataset.label}: ${(val / 1000).toFixed(2)} Kbps`;
                                    return `${ctx.dataset.label}: ${val} bps`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(28, 46, 71, 0.4)' },
                            ticks: { color: '#64748b', font: { family: 'monospace', size: 9 } }
                        },
                        y: {
                            grid: { color: 'rgba(28, 46, 71, 0.4)' },
                            ticks: {
                                color: '#64748b',
                                font: { family: 'monospace', size: 9 },
                                callback: function(val) {
                                    if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
                                    if (val >= 1000) return (val / 1000).toFixed(1) + 'K';
                                    return val;
                                }
                            }
                        }
                    }
                }
            });
        }

        function toggleInterfaceMonitoring(interfaceId, btn) {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch(`/admin/snmp/${interfaceId}/toggle-interface`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.is_monitored) {
                        btn.className = 'px-2 py-0.5 rounded border text-[10px] font-mono cursor-pointer transition bg-cyan-500/20 border-cyan-500/40 text-cyan-300';
                        btn.innerText = 'Activo';
                    } else {
                        btn.className = 'px-2 py-0.5 rounded border text-[10px] font-mono cursor-pointer transition bg-obsidian-bg border-obsidian-border text-obsidian-muted hover:text-white';
                        btn.innerText = 'Pausado';
                    }
                }
            });
        }

        function triggerDevicePoll(deviceId) {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch(`/admin/snmp/${deviceId}/poll`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
            });
        }

        function triggerInterfaceDiscovery(deviceId) {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch(`/admin/snmp/${deviceId}/discover-interfaces`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
            });
        }

        function pollAllDevices() {
            const btn = document.getElementById('btn-poll-all');
            btn.disabled = true;
            btn.innerHTML = `<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span><span>Sondeando...</span>`;
            
            fetch(`/admin/snmp/1/poll`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .finally(() => {
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = `<span class="material-symbols-outlined text-sm">sync</span><span>Sondear Todo</span>`;
                    window.location.reload();
                }, 2500);
            });
        }

        function openNewDeviceModal() { document.getElementById('newDeviceModal').classList.remove('hidden'); }
        function closeNewDeviceModal() { document.getElementById('newDeviceModal').classList.add('hidden'); }
        function openEditDeviceModal(deviceId) {
            const modal = document.getElementById('editDeviceModal');
            const form = document.getElementById('editDeviceForm');
            const loading = document.getElementById('editModalLoading');
            const fields = document.getElementById('editModalFields');
            const ipBadge = document.getElementById('edit-modal-ip-badge');

            if (!modal) return;

            modal.classList.remove('hidden');
            loading.classList.remove('hidden');
            fields.classList.add('hidden');
            form.action = `/admin/snmp/${deviceId}`;

            fetch(`/admin/snmp/${deviceId}`)
                .then(res => res.json())
                .then(data => {
                    loading.classList.add('hidden');
                    fields.classList.remove('hidden');

                    if (data.success && data.device) {
                        const dev = data.device;
                        ipBadge.textContent = dev.ip_address || '';
                        document.getElementById('edit_name').value = dev.name || '';
                        document.getElementById('edit_ip_address').value = dev.ip_address || '';
                        document.getElementById('edit_snmp_port').value = dev.snmp_port || 161;
                        document.getElementById('edit_snmp_version').value = dev.snmp_version || 'v2c';
                        document.getElementById('edit_community').value = dev.community || '';
                        document.getElementById('edit_device_type').value = dev.device_type || 'switch';
                        document.getElementById('edit_site_id').value = dev.site_id || '';
                        document.getElementById('edit_poll_interval_seconds').value = dev.poll_interval_seconds || 60;
                        document.getElementById('edit_is_active').checked = !!dev.is_active;
                        document.getElementById('edit_notes').value = dev.notes || '';
                    } else {
                        alert('No se pudieron obtener los datos del dispositivo.');
                        closeEditDeviceModal();
                    }
                })
                .catch(err => {
                    loading.classList.add('hidden');
                    alert('Error al conectar con el servidor: ' + err);
                    closeEditDeviceModal();
                });
        }

        function closeEditDeviceModal() {
            const modal = document.getElementById('editDeviceModal');
            if (modal) modal.classList.add('hidden');
        }
        function openRemoteActivationModal() { document.getElementById('remoteActivationModal').classList.remove('hidden'); }
        function closeRemoteActivationModal() { document.getElementById('remoteActivationModal').classList.add('hidden'); }

        function submitRemoteActivation(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit-activation');
            const resDiv = document.getElementById('activation-result');
            btn.disabled = true;
            btn.innerHTML = `<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span><span>Activando...</span>`;
            resDiv.classList.add('hidden');

            const payload = {
                ip_address: document.getElementById('act-ip').value,
                method: document.getElementById('act-method').value,
                username: document.getElementById('act-user').value,
                password: document.getElementById('act-pass').value,
                secret: document.getElementById('act-secret').value || null,
                community: document.getElementById('act-comm').value,
            };

            fetch(`/admin/snmp/activate`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = `<span class="material-symbols-outlined text-sm">bolt</span><span>Ejecutar Activación</span>`;
                resDiv.classList.remove('hidden');
                if (data.success) {
                    resDiv.className = 'p-3 rounded-lg border bg-emerald-500/10 border-emerald-500/30 text-emerald-400 text-xs font-mono max-h-40 overflow-y-auto whitespace-pre-wrap';
                    resDiv.innerText = `✅ ÉXITO: ${data.message}\n\n${data.output || ''}`;
                } else {
                    resDiv.className = 'p-3 rounded-lg border bg-red-500/10 border-red-500/30 text-red-400 text-xs font-mono max-h-40 overflow-y-auto whitespace-pre-wrap';
                    resDiv.innerText = `❌ ERROR: ${data.message}\n${data.error || ''}\n\n${data.output || ''}`;
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = `<span class="material-symbols-outlined text-sm">bolt</span><span>Ejecutar Activación</span>`;
                resDiv.classList.remove('hidden');
                resDiv.className = 'p-3 rounded-lg border bg-red-500/10 border-red-500/30 text-red-400 text-xs font-mono max-h-40 overflow-y-auto whitespace-pre-wrap';
                resDiv.innerText = `❌ Error de red o servidor: ${err.message}`;
            });
        }
    </script>

@else
    <!-- ===================================================================== -->
    <!-- CONTENIDO PESTAÑA: AUTO-DISCOVERY & ANTI-ROGUE (POR DEFECTO)          -->
    <!-- ===================================================================== -->

    <!-- HUD STATS AUTO-DISCOVERY -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- TOTAL -->
        <div class="p-3.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80 flex items-center gap-3.5 shadow-sm" title="Total de direcciones MAC y dispositivos detectados en las subredes corporativas">
            <div class="w-10 h-10 rounded-lg bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                <span class="material-symbols-outlined text-xl">devices</span>
            </div>
            <div>
                <p class="text-[11px] font-mono uppercase text-obsidian-muted">Total Detectados</p>
                <p class="text-xl font-bold font-mono text-white">{{ $totalDevices }}</p>
            </div>
        </div>

        <!-- PENDIENTES -->
        <div class="p-3.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80 flex items-center gap-3.5 shadow-sm" title="Dispositivos detectados recientemente que aún no han sido aprobados ni clasificados por el Administrador">
            <div class="w-10 h-10 rounded-lg bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400">
                <span class="material-symbols-outlined text-xl">help_outline</span>
            </div>
            <div>
                <p class="text-[11px] font-mono uppercase text-amber-300">Pendientes Clasificar</p>
                <p class="text-xl font-bold font-mono text-amber-400">{{ $pendingDevices }}</p>
            </div>
        </div>

        <!-- AUTORIZADOS -->
        <div class="p-3.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80 flex items-center gap-3.5 shadow-sm" title="Equipos corporativos legítimos, aprobados y autorizados para operar en la red">
            <div class="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                <span class="material-symbols-outlined text-xl">verified_user</span>
            </div>
            <div>
                <p class="text-[11px] font-mono uppercase text-emerald-300">Equipos Autorizados</p>
                <p class="text-xl font-bold font-mono text-emerald-400">{{ $authorizedDevices }}</p>
            </div>
        </div>

        <!-- INTRUSOS / ROGUE -->
        <div class="p-3.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80 flex items-center gap-3.5 shadow-sm" title="ALERTA: Equipos no autorizados, clandestinos o detectados en segmentos indebidos">
            <div class="w-10 h-10 rounded-lg bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-400">
                <span class="material-symbols-outlined text-xl">gpp_maybe</span>
            </div>
            <div>
                <p class="text-[11px] font-mono uppercase text-red-300">Intrusos / Rogue</p>
                <p class="text-xl font-bold font-mono text-red-400">{{ $rogueDevices }}</p>
            </div>
        </div>
    </div>

    <!-- SUBREDES AUDITADAS -->
    <div class="p-3 rounded-xl bg-obsidian-panel border border-obsidian-border/60">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-mono font-bold text-gray-300 uppercase flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm text-cyan-400">hub</span>
                Subredes en Vigilancia Continua:
            </h3>
            <span class="text-[10px] font-mono text-obsidian-muted">{{ $subnets->count() }} configuradas</span>
        </div>
        <div class="flex flex-wrap gap-2">
            @forelse($subnets as $sub)
                <div class="flex items-center gap-2 px-2.5 py-1 rounded-lg bg-[#040d1a] border border-obsidian-border text-xs font-mono">
                    <span class="w-1.5 h-1.5 rounded-full {{ $sub->is_active ? 'bg-emerald-400 animate-pulse' : 'bg-gray-600' }}"></span>
                    <span class="text-white font-bold">{{ $sub->subnet }}</span>
                    @if($sub->site)
                        <span class="text-[10px] text-cyan-400">({{ $sub->site->name }})</span>
                    @elseif($sub->subnet === '10.20.0.0/24')
                        <span class="text-[10px] text-blue-400 font-bold">(Red Central / Servidores)</span>
                    @else
                        <span class="text-[10px] text-obsidian-muted">(Sin Asignar)</span>
                    @endif
                    <span class="text-[10px] text-obsidian-muted">
                        {{ $sub->last_scan_at ? $sub->last_scan_at->diffForHumans() : 'Sin escaneo' }}
                    </span>
                </div>
            @empty
                <p class="text-xs font-mono text-obsidian-muted">No hay subredes configuradas.</p>
            @endforelse
        </div>
    </div>

    <!-- FILTROS Y BÚSQUEDA AUTO-DISCOVERY -->
    <form method="GET" action="{{ route('admin.discovery.index') }}" class="flex flex-wrap items-center gap-2.5 p-2.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80">
        <input type="hidden" name="tab" value="radar">

        <div class="flex-1 min-w-[200px] relative">
            <span class="material-symbols-outlined absolute left-3 top-2.5 text-obsidian-muted text-sm">search</span>
            <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por IP, MAC, hostname o fabricante..."
                class="w-full bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg pl-8 pr-3 py-1.5 focus:outline-none focus:border-obsidian-cyan">
        </div>

        <select name="status" class="bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-obsidian-cyan">
            <option value="">Todos los Estados</option>
            <option value="pendiente" {{ $statusFilter === 'pendiente' ? 'selected' : '' }}>⏳ Pendientes</option>
            <option value="clasificado" {{ $statusFilter === 'clasificado' ? 'selected' : '' }}>✅ Clasificados</option>
            <option value="rogue" {{ $statusFilter === 'rogue' ? 'selected' : '' }}>🚫 Rogue / Intrusos</option>
            <option value="byod" {{ $statusFilter === 'byod' ? 'selected' : '' }}>📱 BYOD / Móviles</option>
            <option value="ignorado" {{ $statusFilter === 'ignorado' ? 'selected' : '' }}>⚪ Ignorados</option>
        </select>

        <select name="site_id" class="bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-obsidian-cyan">
            <option value="">Todas las Sedes</option>
            @foreach($sites as $s)
                <option value="{{ $s->id }}" {{ (string)$siteFilter === (string)$s->id ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
        </select>

        <button type="submit" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-cyan-950/60 transition text-xs font-mono font-bold cursor-pointer">
            Filtrar
        </button>

        @if($search || $statusFilter || $siteFilter)
            <a href="{{ route('admin.discovery.index', ['tab' => 'radar']) }}" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-red-400 hover:bg-red-950/40 transition text-xs font-mono cursor-pointer" title="Limpiar Filtros">
                ✕
            </a>
        @endif
    </form>

    <!-- TABLA DE DISPOSITIVOS DESCUBIERTOS -->
    <div class="rounded-xl bg-obsidian-panel border border-obsidian-border overflow-hidden shadow-sm">
        <div class="custom-table-scroll">
            <table class="w-full text-left text-xs font-mono whitespace-nowrap">
                <thead class="bg-[#040d1a] border-b border-obsidian-border text-obsidian-muted uppercase text-[10px]">
                    <tr>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Estado de clasificación y autorización de red">Estado / Seguridad</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Dirección IP asignada o detectada">Dirección IP</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Dirección física MAC de capa de enlace">Dirección MAC</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Fabricante de hardware identificado por prefijo OUI IEEE o Inventario">Fabricante</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Identidad en inventario ITAM y hostname resuelto por DNS">Identidad / Hostname</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Tipo de dispositivo clasificado (Router, Switch, Servidor, PC, etc.)">Tipo</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Sede o localidad geográfica de la subred">Sede</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Marca de tiempo del último paquete o barrido ARP recibido">Último Visto</th>
                        <th class="px-2.5 py-2 text-right font-mono font-bold tracking-wider" title="Acciones de gestión, autorización y auditoría">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/50">
                    @forelse($devices as $dev)
                        @php
                            $assetObj = $dev->inventoryAsset ?? $dev->asset;
                        @endphp
                        <tr class="hover:bg-cyan-950/20 transition-colors {{ $dev->classification_status === 'rogue' ? 'bg-red-950/20' : '' }}">
                            <!-- ESTADO / SEGURIDAD -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                @if($dev->classification_status === 'rogue')
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-red-950/80 border border-red-500/40 text-red-400 text-[9.5px] font-bold" title="ALERTA: Equipo clasificado como INTRUSO (Rogue) no autorizado en la red">
                                        <span class="material-symbols-outlined text-[11px]">gpp_maybe</span>
                                        INTRUSO
                                    </span>
                                @elseif($assetObj)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-emerald-950/80 border border-emerald-500/40 text-emerald-300 text-[9.5px] font-bold" title="Activo verificado y registrado en el Inventario ITAM: {{ $assetObj->display_identity }}">
                                        <span class="material-symbols-outlined text-[11px]">verified_user</span>
                                        EN INVENTARIO
                                    </span>
                                @elseif($dev->is_authorized)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-teal-950/80 border border-teal-500/40 text-teal-300 text-[9.5px] font-bold" title="Dispositivo verificado y AUTORIZADO en la red corporativa">
                                        <span class="material-symbols-outlined text-[11px]">check_circle</span>
                                        AUTORIZADO
                                    </span>
                                @elseif($dev->classification_status === 'pendiente')
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-950/80 border border-amber-500/40 text-amber-300 text-[9.5px] font-bold" title="Dispositivo detectado recientemente: Pendiente de clasificación por el Administrador">
                                        <span class="material-symbols-outlined text-[11px]">help</span>
                                        PENDIENTE
                                    </span>
                                @elseif($dev->classification_status === 'byod')
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-purple-950/80 border border-purple-500/40 text-purple-300 text-[9.5px] font-bold" title="Dispositivo personal / móvil registrado (BYOD)">
                                        <span class="material-symbols-outlined text-[11px]">devices_other</span>
                                        BYOD
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-gray-800 border border-gray-600 text-gray-300 text-[9.5px]" title="Estado: {{ strtoupper($dev->classification_status) }}">
                                        {{ strtoupper($dev->classification_status) }}
                                    </span>
                                @endif
                            </td>

                            <!-- IP -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <span class="font-semibold text-white text-[11px]" title="Dirección IP detectada: {{ $dev->ip_address }}">{{ $dev->ip_address }}</span>
                            </td>

                            <!-- MAC -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <span class="font-mono text-cyan-300 text-[9.5px]" title="Dirección física MAC: {{ $dev->mac_address }} (OUI: {{ $dev->vendor ?: 'Desconocido' }})">{{ $dev->mac_address }}</span>
                            </td>

                            <!-- FABRICANTE -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <span class="text-gray-300 text-[10px] max-w-[170px] truncate block" title="Fabricante de hardware: {{ ($assetObj && $assetObj->brand) ? $assetObj->brand : ($dev->vendor ?: 'No identificado en IEEE OUI') }}">
                                    {{ ($assetObj && $assetObj->brand) ? $assetObj->brand : ($dev->vendor ?: 'Desconocido') }}
                                </span>
                            </td>

                            <!-- IDENTIDAD / HOSTNAME -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                @if($assetObj)
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-white text-[11px]" title="Nombre Etiqueta (Identidad ITAM): {{ $assetObj->display_identity }}">
                                            {{ $assetObj->display_identity }}
                                        </span>
                                        <a href="{{ route('admin.devices.index', ['search' => $assetObj->ip_address ?: $assetObj->name]) }}" 
                                           class="inline-flex items-center text-[10px] text-cyan-400 hover:text-cyan-200 transition" 
                                           title="Ver ficha técnica completa en Inventario ITAM">
                                            <span class="material-symbols-outlined text-[13px]">inventory_2</span>
                                        </a>
                                    </div>
                                    @if($dev->hostname && $dev->hostname !== $assetObj->display_identity)
                                        <span class="text-obsidian-muted text-[9.5px] block font-mono" title="Hostname técnico: {{ $dev->hostname }}">
                                            {{ $dev->hostname }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-obsidian-muted text-[10px] max-w-[150px] truncate block" title="Hostname resuelto por DNS: {{ $dev->hostname ?: 'Sin resolución inversa (PTR)' }}">
                                        {{ $dev->hostname ?: '-' }}
                                    </span>
                                @endif
                            </td>

                            <!-- TIPO -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-cyan-950/70 border border-cyan-500/30 text-cyan-300 inline-block" title="Tipo de hardware clasificado: {{ $dev->device_type_label }}">
                                    {{ $dev->device_type_label }}
                                </span>
                            </td>

                            <!-- SEDE -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                @if($dev->site)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-purple-950/70 border border-purple-500/30 text-purple-300 text-[9.5px] font-semibold" title="Sede física asignada: {{ $dev->site->name }}">
                                        <span class="material-symbols-outlined text-[10px]">domain</span>
                                        <span>{{ $dev->site->name }}</span>
                                    </span>
                                @elseif($assetObj && $assetObj->site)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-purple-950/70 border border-purple-500/30 text-purple-300 text-[9.5px] font-semibold" title="Sede física asignada en ITAM: {{ $assetObj->site->name }}">
                                        <span class="material-symbols-outlined text-[10px]">domain</span>
                                        <span>{{ $assetObj->site->name }}</span>
                                    </span>
                                @elseif(str_starts_with($dev->ip_address, '10.20.0.'))
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-blue-950/70 border border-blue-500/30 text-blue-300 text-[9.5px] font-semibold" title="Segmento de Red Central / Servidores Corporativos">
                                        <span class="material-symbols-outlined text-[10px]">dns</span>
                                        <span>Red Central / Servidores</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-gray-900 border border-gray-700/60 text-gray-400 text-[9.5px] font-mono" title="Dispositivo sin sede física asignada">
                                        <span class="material-symbols-outlined text-[10px]">help_outline</span>
                                        <span>Sede Desconocida / Sin Asignar</span>
                                    </span>
                                @endif
                            </td>

                            <!-- ÚLTIMO VISTO -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <span class="text-[9.5px] text-obsidian-muted" title="Detectado el: {{ $dev->last_seen ? $dev->last_seen->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'Sin registro' }}">
                                    {{ $dev->last_seen ? $dev->last_seen->diffForHumans() : '-' }}
                                </span>
                            </td>

                            <!-- ACCIONES -->
                            <td class="px-2.5 py-1.5 text-right space-x-1 whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('discovery.authorize'))
                                        @if($isClusterSlave ?? false)
                                            <span class="p-1 rounded bg-gray-900 border border-gray-800 text-gray-500 text-[9.5px] font-mono inline-flex items-center" title="Modificaciones restringidas al Servidor Master">
                                                <span class="material-symbols-outlined text-[11px]">lock</span>
                                            </span>
                                        @else
                                            <!-- APROBAR (Sólo para dispositivos no inventariados) -->
                                            @if(!$dev->is_authorized && !$assetObj)
                                                <form method="POST" action="{{ route('admin.discovery.authorize', $dev->id) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="p-1 rounded bg-obsidian-panel border border-emerald-500/40 text-emerald-400 hover:bg-emerald-500 hover:text-black transition cursor-pointer shadow-xs inline-flex items-center" title="Autorizar Dispositivo (Agregar al inventario confiable)">
                                                        <span class="material-symbols-outlined text-[13px]">check_circle</span>
                                                    </button>
                                                </form>
                                            @endif

                                            <!-- MARCAR ROGUE (Sólo para dispositivos no registrados) -->
                                            @if($dev->classification_status !== 'rogue' && !$assetObj)
                                                <form method="POST" action="{{ route('admin.discovery.rogue', $dev->id) }}" class="inline" onsubmit="return confirm('¿Confirmas marcar este dispositivo como INTRUSO (Rogue)?');">
                                                    @csrf
                                                    <button type="submit" class="p-1 rounded bg-obsidian-panel border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition cursor-pointer shadow-xs inline-flex items-center" title="Marcar como INTRUSO (Rogue) - Alerta de red">
                                                        <span class="material-symbols-outlined text-[13px]">gpp_bad</span>
                                                    </button>
                                                </form>
                                            @endif

                                            <!-- ACCESO A INVENTARIO ITAM -->
                                            @if($assetObj)
                                                <a href="{{ route('admin.devices.index', ['search' => $assetObj->ip_address ?: $assetObj->name]) }}" 
                                                   class="p-1 rounded bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black transition cursor-pointer shadow-xs inline-flex items-center" 
                                                   title="Abrir ficha técnica de este equipo en Inventario ITAM">
                                                    <span class="material-symbols-outlined text-[13px]">open_in_new</span>
                                                </a>
                                            @endif

                                            <!-- EDITAR / CLASIFICAR -->
                                            <button type="button" onclick="openClassifyModal({{ json_encode($dev) }})" class="p-1 rounded bg-obsidian-panel border border-amber-500/40 text-amber-300 hover:bg-amber-500 hover:text-black transition cursor-pointer shadow-xs inline-flex items-center" title="Editar Clasificación, Tipo de Equipo y Sede">
                                                <span class="material-symbols-outlined text-[13px]">edit</span>
                                            </button>
                                        @endif
                                    @endif

                                    <!-- HISTORIAL -->
                                    <button type="button" onclick="openHistoryModal({{ $dev->id }}, '{{ $dev->ip_address }}', '{{ $dev->mac_address }}')" class="p-1 rounded bg-obsidian-panel border border-obsidian-purple/40 text-obsidian-purple hover:bg-obsidian-purple hover:text-white transition cursor-pointer shadow-xs inline-flex items-center" title="Ver Historial de Conexiones y Cambios de IP">
                                        <span class="material-symbols-outlined text-[13px]">history</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-6 text-center text-obsidian-muted font-mono text-xs">
                                No se encontraron dispositivos descubiertos con los criterios seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN AUTO-DISCOVERY -->
        @if($devices->hasPages())
            <div class="px-3.5 py-2.5 bg-obsidian-panel border-t border-obsidian-border/80 flex flex-col sm:flex-row items-center justify-between gap-2.5 text-xs font-mono">
                <div class="text-obsidian-muted text-[10.5px]">
                    Mostrando <span class="text-white font-bold">{{ $devices->firstItem() }}</span> a <span class="text-white font-bold">{{ $devices->lastItem() }}</span> de <span class="text-cyan-400 font-bold">{{ $devices->total() }}</span> dispositivos detectados (15 por página)
                </div>
                <div class="flex items-center gap-1">
                    {{-- Anterior --}}
                    @if($devices->onFirstPage())
                        <span class="px-2 py-0.5 rounded bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[10.5px]">
                            &laquo; Anterior
                        </span>
                    @else
                        <a href="{{ $devices->previousPageUrl() }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[10.5px]" title="Página Anterior">
                            &laquo; Anterior
                        </a>
                    @endif

                    {{-- Páginas --}}
                    @foreach($devices->getUrlRange(1, $devices->lastPage()) as $page => $url)
                        @if($page == $devices->currentPage())
                            <span class="px-2 py-0.5 rounded bg-cyan-500 text-black font-bold text-[10.5px]">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white transition text-[10.5px]" title="Ir a la página {{ $page }}">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach

                    {{-- Siguiente --}}
                    @if($devices->hasMorePages())
                        <a href="{{ $devices->nextPageUrl() }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[10.5px]" title="Página Siguiente">
                            Siguiente &raquo;
                        </a>
                    @else
                        <span class="px-2 py-0.5 rounded bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[10.5px]">
                            Siguiente &raquo;
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- ===================================================================== -->
    <!-- MODALES AUTO-DISCOVERY                                                -->
    <!-- ===================================================================== -->

    @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('discovery.scan') || auth()->user()->hasPermission('discovery.authorize')) && !($isClusterSlave ?? false))
    <!-- MODAL 1: ESCANEO MANUAL -->
    <!-- MODAL 1: ESCANEO MANUAL -->
    <div id="modal-scan" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden items-center justify-center p-3 sm:p-4">
        <div class="w-full max-w-md rounded-2xl bg-[#0b121e] border border-cyan-500/40 shadow-2xl overflow-hidden flex flex-col max-h-[85vh] sm:max-h-[88vh]">
            <div class="flex items-center justify-between border-b border-obsidian-border p-4 shrink-0 bg-[#06101c]">
                <h3 class="text-sm font-bold font-mono text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-cyan-400">travel_explore</span>
                    Lanzar Escaneo de Auto-Discovery
                </h3>
                <button type="button" onclick="closeScanModal()" class="text-obsidian-muted hover:text-white text-lg cursor-pointer">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.discovery.scan') }}" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                @csrf
                <div class="p-5 space-y-4 overflow-y-auto flex-1 custom-table-scroll">
                    <div class="space-y-1.5">
                        <label class="text-xs font-mono text-gray-300 block">Seleccione el Alcance del Escaneo:</label>
                        <select name="subnet" class="w-full bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-3 py-2 focus:border-cyan-400">
                            <option value="">Todas las subredes activas</option>
                            @foreach($subnets as $sub)
                                <option value="{{ $sub->subnet }}">{{ $sub->subnet }} ({{ $sub->site?->name ?? 'Local' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-[11px] font-mono text-obsidian-muted leading-relaxed">
                        ℹ️ El escaneo se ejecutará en segundo plano sin interrumpir el monitoreo en vivo ni sobrecargar la red.
                    </p>
                </div>
                <div class="p-4 bg-[#06101c]/80 border-t border-obsidian-border flex justify-end gap-2 shrink-0">
                    <button type="button" onclick="closeScanModal()" class="px-3 py-1.5 rounded-lg border border-obsidian-border text-xs font-mono text-gray-400 hover:text-white cursor-pointer">Cancelar</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-bold font-mono text-xs shadow-md shadow-cyan-500/20 cursor-pointer">Iniciar Escaneo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: AGREGAR SUBRED -->
    <div id="modal-subnet" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden items-center justify-center p-3 sm:p-4">
        <div class="w-full max-w-md rounded-2xl bg-[#0b121e] border border-purple-500/40 shadow-2xl overflow-hidden flex flex-col max-h-[85vh] sm:max-h-[88vh]">
            <div class="flex items-center justify-between border-b border-obsidian-border p-4 shrink-0 bg-[#06101c]">
                <h3 class="text-sm font-bold font-mono text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-purple-400">add_circle</span>
                    Registrar Nueva Subred para Vigilancia
                </h3>
                <button type="button" onclick="closeSubnetModal()" class="text-obsidian-muted hover:text-white text-lg cursor-pointer">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.discovery.subnet.store') }}" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                @csrf
                <div class="p-5 space-y-3 overflow-y-auto flex-1 custom-table-scroll">
                    <div class="space-y-1">
                        <label class="text-xs font-mono text-gray-300 block">Subred (Formato CIDR):</label>
                        <input type="text" name="subnet" placeholder="10.20.23.0/24" required
                            class="w-full bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-3 py-2 focus:border-purple-400">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-mono text-gray-300 block">Sede Asociada:</label>
                        <select name="site_id" class="w-full bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-3 py-2">
                            <option value="">Sede Principal / Sin Asignar</option>
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="text-xs font-mono text-gray-300 block">Intervalo (Minutos):</label>
                            <input type="number" name="scan_interval_minutes" value="15" min="5" max="1440" required
                                class="w-full bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-3 py-2">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-mono text-gray-300 block">Límite PPS:</label>
                            <input type="number" name="rate_limit_pps" value="150" min="10" max="500" required
                                class="w-full bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-3 py-2">
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-[#06101c]/80 border-t border-obsidian-border flex justify-end gap-2 shrink-0">
                    <button type="button" onclick="closeSubnetModal()" class="px-3 py-1.5 rounded-lg border border-obsidian-border text-xs font-mono text-gray-400 hover:text-white cursor-pointer">Cancelar</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold font-mono text-xs cursor-pointer">Guardar Subred</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: EDITAR / CLASIFICAR -->
    <div id="modal-classify" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden items-center justify-center p-3 sm:p-4">
        <div class="w-full max-w-lg rounded-2xl bg-[#0b121e] border border-cyan-500/40 shadow-2xl overflow-hidden flex flex-col max-h-[85vh] sm:max-h-[88vh]">
            <div class="flex items-center justify-between border-b border-obsidian-border p-4 shrink-0 bg-[#06101c]">
                <h3 class="text-sm font-bold font-mono text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-cyan-400">edit_note</span>
                    Clasificación y Seguridad de Dispositivo
                </h3>
                <button type="button" onclick="closeClassifyModal()" class="text-obsidian-muted hover:text-white text-lg cursor-pointer">&times;</button>
            </div>
            <form id="form-classify" method="POST" action="" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                @csrf
                <div class="p-5 space-y-3 overflow-y-auto flex-1 custom-table-scroll">
                    <div class="grid grid-cols-2 gap-2 text-xs font-mono bg-[#040d1a] p-2.5 rounded-lg border border-obsidian-border">
                        <div>
                            <span class="text-obsidian-muted block text-[10px]">IP:</span>
                            <span id="classify-ip" class="text-white font-bold"></span>
                        </div>
                        <div>
                            <span class="text-obsidian-muted block text-[10px]">MAC:</span>
                            <span id="classify-mac" class="text-cyan-300 font-bold"></span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-obsidian-muted block text-[10px]">Fabricante:</span>
                            <span id="classify-vendor" class="text-gray-300"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-xs font-mono text-gray-300 block">Tipo de Dispositivo:</label>
                            <select name="device_type" id="classify-type" class="w-full bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-2.5 py-1.5">
                                <option value="router">Router</option>
                                <option value="switch">Switch</option>
                                <option value="firewall">Firewall</option>
                                <option value="server">Servidor</option>
                                <option value="workstation">Estación de Trabajo (PC)</option>
                                <option value="printer">Impresora</option>
                                <option value="ap">Access Point (WiFi)</option>
                                <option value="camera">Cámara IP</option>
                                <option value="ups">UPS / Respaldo Eléctrico</option>
                                <option value="unknown">Desconocido</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-mono text-gray-300 block">Estado de Clasificación:</label>
                            <select name="classification_status" id="classify-status" class="w-full bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-2.5 py-1.5">
                                <option value="pendiente">Pendiente</option>
                                <option value="clasificado">Clasificado</option>
                                <option value="rogue">Rogue / Intruso</option>
                                <option value="byod">BYOD / Dispositivo Personal</option>
                                <option value="ignorado">Ignorado</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-xs font-mono text-gray-300 block">Hostname / Nombre del Equipo:</label>
                            <input type="text" name="hostname" id="classify-hostname" placeholder="Ej: PC-TAQUILLA-01"
                                class="w-full bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-3 py-1.5">
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-mono text-gray-300 block">Sede Asignada:</label>
                            <select name="site_id" id="classify-site" class="w-full bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-2.5 py-1.5">
                                <option value="">-- Sin Asignar / Desconocida --</option>
                                @foreach($sites as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-mono text-gray-300 block">Notas de Auditoría:</label>
                        <textarea name="notes" id="classify-notes" rows="2" placeholder="Detalles de ubicación o usuario asignado..."
                            class="w-full bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-3 py-1.5"></textarea>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" name="is_authorized" id="classify-is-authorized" value="1"
                            class="text-cyan-500 rounded border-obsidian-border focus:ring-0">
                        <label for="classify-is-authorized" class="text-xs font-mono text-white cursor-pointer">
                            Dispositivo Aprobado y Autorizado en la Red Corporativa
                        </label>
                    </div>
                </div>

                <div class="p-4 bg-[#06101c]/80 border-t border-obsidian-border flex justify-end gap-2 shrink-0">
                    <button type="button" onclick="closeClassifyModal()" class="px-3 py-1.5 rounded-lg border border-obsidian-border text-xs font-mono text-gray-400 hover:text-white cursor-pointer">Cancelar</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-bold font-mono text-xs cursor-pointer">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- MODAL 4: HISTORIAL DE EVENTOS -->
    <div id="modal-history" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden items-center justify-center p-3 sm:p-4">
        <div class="w-full max-w-lg rounded-2xl bg-[#0b121e] border border-purple-500/40 shadow-2xl overflow-hidden flex flex-col max-h-[85vh] sm:max-h-[88vh]">
            <div class="flex items-center justify-between border-b border-obsidian-border p-4 shrink-0 bg-[#06101c]">
                <div>
                    <h3 class="text-sm font-bold font-mono text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-purple-400">history</span>
                        Bitácora de Eventos del Dispositivo
                    </h3>
                    <p id="history-header-device" class="text-[11px] font-mono text-obsidian-muted mt-0.5"></p>
                </div>
                <button type="button" onclick="closeHistoryModal()" class="text-obsidian-muted hover:text-white text-lg cursor-pointer">&times;</button>
            </div>

            <div id="history-container" class="p-4 overflow-y-auto flex-1 space-y-2 pr-1 custom-table-scroll">
                <p class="text-xs font-mono text-obsidian-muted py-4 text-center">Cargando bitácora de eventos...</p>
            </div>

            <div class="p-3.5 bg-[#06101c]/80 border-t border-obsidian-border flex justify-end shrink-0">
                <button type="button" onclick="closeHistoryModal()" class="px-4 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-xs font-mono text-white hover:bg-white/10 cursor-pointer">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT AUTO-DISCOVERY -->
    <script>
        function openScanModal() {
            const m = document.getElementById('modal-scan');
            if (m) {
                m.classList.remove('hidden');
                m.classList.add('flex');
            }
        }
        function closeScanModal() {
            const m = document.getElementById('modal-scan');
            if (m) {
                m.classList.add('hidden');
                m.classList.remove('flex');
            }
        }

        function openSubnetModal() {
            const m = document.getElementById('modal-subnet');
            if (m) {
                m.classList.remove('hidden');
                m.classList.add('flex');
            }
        }
        function closeSubnetModal() {
            const m = document.getElementById('modal-subnet');
            if (m) {
                m.classList.add('hidden');
                m.classList.remove('flex');
            }
        }

        function openClassifyModal(dev) {
            const ipEl = document.getElementById('classify-ip');
            if (!ipEl) return;
            ipEl.textContent = dev.ip_address || '-';
            document.getElementById('classify-mac').textContent = dev.mac_address || '-';
            document.getElementById('classify-vendor').textContent = dev.vendor || 'Desconocido';
            document.getElementById('classify-type').value = dev.device_type || 'unknown';
            document.getElementById('classify-status').value = dev.classification_status || 'pendiente';
            document.getElementById('classify-hostname').value = dev.hostname || '';
            document.getElementById('classify-site').value = dev.site_id || '';
            document.getElementById('classify-notes').value = dev.notes || '';
            document.getElementById('classify-is-authorized').checked = !!dev.is_authorized;

            document.getElementById('form-classify').action = `{{ url('/admin/discovery/update') }}/${dev.id}`;

            const m = document.getElementById('modal-classify');
            if (m) {
                m.classList.remove('hidden');
                m.classList.add('flex');
            }
        }
        function closeClassifyModal() {
            const m = document.getElementById('modal-classify');
            if (m) {
                m.classList.add('hidden');
                m.classList.remove('flex');
            }
        }

        function openHistoryModal(id, ip, mac) {
            document.getElementById('history-header-device').textContent = `${ip} (${mac})`;
            const container = document.getElementById('history-container');
            container.innerHTML = '<p class="text-xs font-mono text-obsidian-muted py-4 text-center">Cargando bitácora de eventos...</p>';

            const m = document.getElementById('modal-history');
            if (m) {
                m.classList.remove('hidden');
                m.classList.add('flex');
            }

            fetch(`{{ url('/admin/discovery/history') }}/${id}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.history || data.history.length === 0) {
                        container.innerHTML = '<p class="text-xs font-mono text-obsidian-muted py-4 text-center">No hay eventos registrados para este equipo.</p>';
                        return;
                    }
                    let html = '';
                    data.history.forEach(h => {
                        const prev = h.previous_value ? `<span class="text-obsidian-muted line-through">${h.previous_value}</span> ➔ ` : '';
                        const next = `<span class="text-white">${h.new_value || '-'}</span>`;
                        html += `
                            <div class="p-2.5 rounded-lg bg-[#040d1a] border border-obsidian-border text-xs font-mono space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-cyan-300 uppercase text-[10.5px]">${h.event_type_label || h.event_type}</span>
                                    <span class="text-[10px] text-obsidian-muted" title="${h.occurred_at}">${h.occurred_at_human ? h.occurred_at_human + ' (' + h.occurred_at + ')' : h.occurred_at}</span>
                                </div>
                                <div class="text-gray-300 text-[11px]">
                                    ${prev}${next}
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                })
                .catch(err => {
                    container.innerHTML = `<p class="text-xs font-mono text-red-400 py-4 text-center">Error cargando bitácora: ${err}</p>`;
                });
        }
        function closeHistoryModal() {
            const m = document.getElementById('modal-history');
            if (m) {
                m.classList.add('hidden');
                m.classList.remove('flex');
            }
        }
    </script>
@endif

</div>
@endsection
