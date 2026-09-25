@extends('layouts.admin')

@section('page_title', 'Auto-Discovery y Detección Anti-Rogue')

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
    <!-- PESTAÑAS RADAR & DESCUBRIMIENTO -->
    <div class="flex flex-wrap items-center gap-2 border-b border-obsidian-border/80 pb-3">
        <a href="{{ route('admin.discovery.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.discovery.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">radar</span>
            <span>Auto-Discovery de Red</span>
        </a>
        @if(!auth()->user() || auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('netradar.view')))
        <a href="{{ route('admin.netradar.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.netradar.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">troubleshoot</span>
            <span>NET Radar (Tráfico & Hosts)</span>
        </a>
        @endif
    </div>

    <!-- CABECERA -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-[10.5px] font-mono font-semibold group shadow-xs">
                    <span class="material-symbols-outlined text-xs group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-[10.5px] font-mono text-obsidian-muted">Seguridad y Red</span>
            </div>
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-xl">radar</span>
                Auto-Discovery de Red y Detección Anti-Rogue
            </h2>
            <p class="text-xs font-mono text-obsidian-muted">Supervisión continua de subredes, inventario dinámico de MACs y detección de equipos intrusos</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="px-2.5 py-1 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 text-[10.5px] font-mono flex items-center gap-1.5 shadow-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                Fuente: SERVIDOR MAESTRO
            </span>

            @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('discovery.authorize')) && !$isClusterSlave)
            <button type="button" onclick="openSubnetModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-obsidian-panel border border-purple-500/40 text-purple-300 hover:bg-purple-600 hover:text-white transition text-xs font-mono font-bold shadow-xs cursor-pointer" title="Registrar una nueva subred CIDR para escaneo y supervisión continua">
                <span class="material-symbols-outlined text-sm">add_circle</span>
                <span>+ Subred</span>
            </button>
            @endif

            @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('discovery.scan')) && !$isClusterSlave)
            <button type="button" onclick="openScanModal()" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black transition text-xs font-mono font-bold shadow-md shadow-cyan-500/20 cursor-pointer" title="Ejecutar barrido ARP y descubrimiento inmediato en las subredes vigentes">
                <span class="material-symbols-outlined text-sm">travel_explore</span>
                <span>Escanear Red Ahora</span>
            </button>
            @endif

            @if($isClusterSlave)
            <span class="px-2.5 py-1.5 rounded-lg bg-gray-900 border border-gray-800 text-gray-500 font-mono text-xs inline-flex items-center gap-1.5" title="Modificaciones restringidas al Servidor Master">
                <span class="material-symbols-outlined text-xs">lock</span>
                <span>Solo Lectura (Modo Esclavo)</span>
            </span>
            @elseif(!auth()->user()->isAdmin() && !auth()->user()->hasPermission('discovery.scan') && !auth()->user()->hasPermission('discovery.authorize'))
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-muted text-xs font-mono" title="Acceso de Operador: Vista de solo consulta, operaciones mutantes deshabilitadas">
                <span class="material-symbols-outlined text-sm text-cyan-400">visibility</span>
                <span>Modo Consulta</span>
            </span>
            @endif
        </div>
    </div>

    <!-- HUD STATS -->
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

    <!-- FILTROS Y BÚSQUEDA -->
    <form method="GET" action="{{ route('admin.discovery.index') }}" class="flex flex-wrap items-center gap-2.5 p-2.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80">
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
            <a href="{{ route('admin.discovery.index') }}" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-red-400 hover:bg-red-950/40 transition text-xs font-mono cursor-pointer" title="Limpiar Filtros">
                ✕
            </a>
        @endif
    </form>

    <!-- TABLA DE DISPOSITIVOS -->
    <div class="rounded-xl bg-obsidian-panel border border-obsidian-border overflow-hidden shadow-sm">
        <div class="custom-table-scroll">
            <table class="w-full text-left text-xs font-mono whitespace-nowrap">
                <thead class="bg-[#040d1a] border-b border-obsidian-border text-obsidian-muted uppercase text-[10px]">
                    <tr>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Estado de clasificación y autorización de red">Estado / Seguridad</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Dirección IP asignada o detectada">Dirección IP</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Dirección física MAC de capa de enlace">Dirección MAC</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Fabricante de hardware identificado por prefijo OUI IEEE">Fabricante (OUI)</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Nombre de host resuelto por DNS o alias asignado">Hostname / Alias</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Tipo de dispositivo clasificado (Router, Switch, Servidor, PC, etc.)">Tipo</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Sede o localidad geográfica de la subred">Sede</th>
                        <th class="px-2.5 py-2 font-mono font-bold tracking-wider" title="Marca de tiempo del último paquete o barrido ARP recibido">Último Visto</th>
                        <th class="px-2.5 py-2 text-right font-mono font-bold tracking-wider" title="Acciones de gestión, autorización y auditoría">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/50">
                    @forelse($devices as $dev)
                        <tr class="hover:bg-cyan-950/20 transition-colors {{ $dev->classification_status === 'rogue' ? 'bg-red-950/20' : '' }}">
                            <!-- ESTADO / SEGURIDAD -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                @if($dev->classification_status === 'rogue')
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-red-950/80 border border-red-500/40 text-red-400 text-[9.5px] font-bold" title="ALERTA: Equipo clasificado como INTRUSO (Rogue) no autorizado en la red">
                                        <span class="material-symbols-outlined text-[11px]">gpp_maybe</span>
                                        INTRUSO
                                    </span>
                                @elseif($dev->is_authorized)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-emerald-950/80 border border-emerald-500/40 text-emerald-300 text-[9.5px] font-bold" title="Dispositivo verificado y AUTORIZADO en el inventario corporativo">
                                        <span class="material-symbols-outlined text-[11px]">verified_user</span>
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
                                <span class="text-gray-300 text-[10px] max-w-[170px] truncate block" title="Fabricante de hardware: {{ $dev->vendor ?: 'No identificado en IEEE OUI' }}">
                                    {{ $dev->vendor ?: 'Desconocido' }}
                                </span>
                            </td>

                            <!-- HOSTNAME -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <span class="text-obsidian-muted text-[10px] max-w-[150px] truncate block" title="Hostname resuelto por DNS: {{ $dev->hostname ?: 'Sin resolución inversa (PTR)' }}">
                                    {{ $dev->hostname ?: '-' }}
                                </span>
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

                            <!-- ACCIONES (COMPACTAS Y CON TOOLTIPS) -->
                            <td class="px-2.5 py-1.5 text-right space-x-1 whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('discovery.authorize'))
                                        @if($isClusterSlave)
                                            <span class="p-1 rounded bg-gray-900 border border-gray-800 text-gray-500 text-[9.5px] font-mono inline-flex items-center" title="Modificaciones restringidas al Servidor Master">
                                                <span class="material-symbols-outlined text-[11px]">lock</span>
                                            </span>
                                        @else
                                            <!-- APROBAR -->
                                            @if(!$dev->is_authorized)
                                                <form method="POST" action="{{ route('admin.discovery.authorize', $dev->id) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="p-1 rounded bg-obsidian-panel border border-emerald-500/40 text-emerald-400 hover:bg-emerald-500 hover:text-black transition cursor-pointer shadow-xs inline-flex items-center" title="Autorizar Dispositivo (Agregar al inventario confiable)">
                                                        <span class="material-symbols-outlined text-[13px]">check_circle</span>
                                                    </button>
                                                </form>
                                            @endif

                                            <!-- MARCAR ROGUE -->
                                            @if($dev->classification_status !== 'rogue')
                                                <form method="POST" action="{{ route('admin.discovery.rogue', $dev->id) }}" class="inline" onsubmit="return confirm('¿Confirmas marcar este dispositivo como INTRUSO (Rogue)?');">
                                                    @csrf
                                                    <button type="submit" class="p-1 rounded bg-obsidian-panel border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition cursor-pointer shadow-xs inline-flex items-center" title="Marcar como INTRUSO (Rogue) - Alerta de red">
                                                        <span class="material-symbols-outlined text-[13px]">gpp_bad</span>
                                                    </button>
                                                </form>
                                            @endif

                                            <!-- EDITAR / CLASIFICAR -->
                                            <button type="button" onclick="openClassifyModal({{ json_encode($dev) }})" class="p-1 rounded bg-obsidian-panel border border-amber-500/40 text-amber-300 hover:bg-amber-500 hover:text-black transition cursor-pointer shadow-xs inline-flex items-center" title="Editar Clasificación, Tipo de Equipo y Sede">
                                                <span class="material-symbols-outlined text-[13px]">edit</span>
                                            </button>
                                        @endif
                                    @endif

                                    <!-- HISTORIAL (Accesible para todos los usuarios) -->
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

        <!-- PAGINACIÓN OBSIDIAN DARK -->
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

    @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('discovery.scan') || auth()->user()->hasPermission('discovery.authorize')) && !$isClusterSlave)
    <!-- MODAL 1: ESCANEO MANUAL -->
    <div id="modal-scan" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden items-center justify-center p-4">
        <div class="w-full max-w-md p-5 rounded-2xl bg-[#0b121e] border border-cyan-500/40 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
                <h3 class="text-sm font-bold font-mono text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-cyan-400">travel_explore</span>
                    Lanzar Escaneo de Auto-Discovery
                </h3>
                <button type="button" onclick="closeScanModal()" class="text-obsidian-muted hover:text-white text-lg cursor-pointer">✕</button>
            </div>
            <form method="POST" action="{{ route('admin.discovery.scan') }}" class="space-y-4">
                @csrf
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
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeScanModal()" class="px-3 py-1.5 rounded-lg border border-obsidian-border text-xs font-mono text-gray-400 hover:text-white cursor-pointer">Cancelar</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-bold font-mono text-xs shadow-md shadow-cyan-500/20 cursor-pointer">Iniciar Escaneo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: AGREGAR SUBRED -->
    <div id="modal-subnet" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden items-center justify-center p-4">
        <div class="w-full max-w-md p-5 rounded-2xl bg-[#0b121e] border border-purple-500/40 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
                <h3 class="text-sm font-bold font-mono text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-purple-400">add_circle</span>
                    Registrar Nueva Subred para Vigilancia
                </h3>
                <button type="button" onclick="closeSubnetModal()" class="text-obsidian-muted hover:text-white text-lg cursor-pointer">✕</button>
            </div>
            <form method="POST" action="{{ route('admin.discovery.subnet.store') }}" class="space-y-3">
                @csrf
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
                <div class="flex justify-end gap-2 pt-3">
                    <button type="button" onclick="closeSubnetModal()" class="px-3 py-1.5 rounded-lg border border-obsidian-border text-xs font-mono text-gray-400 hover:text-white cursor-pointer">Cancelar</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold font-mono text-xs cursor-pointer">Guardar Subred</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: EDITAR / CLASIFICAR -->
    <div id="modal-classify" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden items-center justify-center p-4">
        <div class="w-full max-w-lg p-5 rounded-2xl bg-[#0b121e] border border-cyan-500/40 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
                <h3 class="text-sm font-bold font-mono text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-cyan-400">edit_note</span>
                    Clasificación y Seguridad de Dispositivo
                </h3>
                <button type="button" onclick="closeClassifyModal()" class="text-obsidian-muted hover:text-white text-lg cursor-pointer">✕</button>
            </div>
            <form id="form-classify" method="POST" action="" class="space-y-3">
                @csrf
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
                        Dispositivo Aprobado y Autorizado en la Red Institucional
                    </label>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-obsidian-border">
                    <button type="button" onclick="closeClassifyModal()" class="px-3 py-1.5 rounded-lg border border-obsidian-border text-xs font-mono text-gray-400 hover:text-white cursor-pointer">Cancelar</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-bold font-mono text-xs cursor-pointer">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- MODAL 4: HISTORIAL DE EVENTOS -->
    <div id="modal-history" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden items-center justify-center p-4">
        <div class="w-full max-w-lg p-5 rounded-2xl bg-[#0b121e] border border-purple-500/40 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
                <div>
                    <h3 class="text-sm font-bold font-mono text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-purple-400">history</span>
                        Bitácora de Eventos del Dispositivo
                    </h3>
                    <p id="history-header-device" class="text-[11px] font-mono text-obsidian-muted mt-0.5"></p>
                </div>
                <button type="button" onclick="closeHistoryModal()" class="text-obsidian-muted hover:text-white text-lg cursor-pointer">✕</button>
            </div>

            <div id="history-container" class="max-h-72 overflow-y-auto space-y-2 pr-1">
                <p class="text-xs font-mono text-obsidian-muted py-4 text-center">Cargando bitácora de eventos...</p>
            </div>

            <div class="flex justify-end pt-2 border-t border-obsidian-border">
                <button type="button" onclick="closeHistoryModal()" class="px-4 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-xs font-mono text-white hover:bg-white/10 cursor-pointer">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openScanModal() {
        const m = document.getElementById('modal-scan');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }
    function closeScanModal() {
        const m = document.getElementById('modal-scan');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    function openSubnetModal() {
        const m = document.getElementById('modal-subnet');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }
    function closeSubnetModal() {
        const m = document.getElementById('modal-subnet');
        m.classList.add('hidden');
        m.classList.remove('flex');
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
        m.classList.remove('hidden');
        m.classList.add('flex');
    }
    function closeClassifyModal() {
        const m = document.getElementById('modal-classify');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    function openHistoryModal(id, ip, mac) {
        document.getElementById('history-header-device').textContent = `${ip} (${mac})`;
        const container = document.getElementById('history-container');
        container.innerHTML = '<p class="text-xs font-mono text-obsidian-muted py-4 text-center">Cargando bitácora de eventos...</p>';

        const m = document.getElementById('modal-history');
        m.classList.remove('hidden');
        m.classList.add('flex');

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
        m.classList.add('hidden');
        m.classList.remove('flex');
    }
</script>
@endsection
