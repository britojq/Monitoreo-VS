@extends('layouts.admin')

@section('page_title', 'Monitoreo y Telemetría SNMP')

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
    <!-- PESTAÑAS CONSOLA SNMP 360° -->
    <div class="flex flex-wrap items-center gap-2 border-b border-obsidian-border/80 pb-3">
        <a href="{{ route('admin.snmp.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.snmp.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">sensors</span>
            <span>Dispositivos & Métricas SNMP</span>
        </a>
        <a href="{{ route('admin.traps.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.traps.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">forward_to_inbox</span>
            <span>SNMP Traps (UDP 162)</span>
        </a>
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
                <span class="text-[10.5px] font-mono text-obsidian-muted">Telemetría de Red</span>
            </div>
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-xl">router</span>
                Monitoreo y Telemetría SNMP Integral
            </h2>
            <p class="text-xs font-mono text-obsidian-muted">Supervisión de métricas OID, estados operacionales de puertos y ancho de banda en switches, routers y firewalls</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="px-2.5 py-1 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 text-[10.5px] font-mono flex items-center gap-1.5 shadow-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                Fuente: SERVIDOR MAESTRO
            </span>

            @if(auth()->user()->isAdmin() && !$isClusterSlave)
            <button type="button" onclick="openRemoteActivationModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-obsidian-panel border border-amber-500/40 text-amber-300 hover:bg-amber-600 hover:text-white transition text-xs font-mono font-bold shadow-xs cursor-pointer">
                <span class="material-symbols-outlined text-sm">bolt</span>
                <span>⚡ Activación Remota</span>
            </button>
            <button type="button" onclick="openNewDeviceModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-obsidian-panel border border-purple-500/40 text-purple-300 hover:bg-purple-600 hover:text-white transition text-xs font-mono font-bold shadow-xs cursor-pointer">
                <span class="material-symbols-outlined text-sm">add_circle</span>
                <span>+ Dispositivo SNMP</span>
            </button>
            <button type="button" onclick="pollAllDevices()" id="btn-poll-all" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black transition text-xs font-mono font-bold shadow-md shadow-cyan-500/20 cursor-pointer">
                <span class="material-symbols-outlined text-sm">sync</span>
                <span>Sondear Todo</span>
            </button>
            @elseif($isClusterSlave)
            <span class="px-2.5 py-1.5 rounded-lg bg-gray-900 border border-gray-800 text-gray-500 font-mono text-xs inline-flex items-center gap-1.5" title="Modificaciones restringidas al Servidor Master">
                <span class="material-symbols-outlined text-xs">lock</span>
                <span>Solo Lectura (Modo Esclavo)</span>
            </span>
            @else
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-muted text-xs font-mono">
                <span class="material-symbols-outlined text-sm text-cyan-400">visibility</span>
                <span>Modo Consulta</span>
            </span>
            @endif
        </div>
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

    <!-- HUD STATS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- TOTAL EQUIPOS -->
        <div class="p-3.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80 flex items-center gap-3.5 shadow-sm">
            <div class="w-10 h-10 rounded-lg bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                <span class="material-symbols-outlined text-xl">dns</span>
            </div>
            <div>
                <p class="text-[11px] font-mono uppercase text-obsidian-muted">Total Equipos SNMP</p>
                <p class="text-xl font-bold font-mono text-white">{{ $totalDevices }}</p>
            </div>
        </div>

        <!-- RESPONDIENDO / ONLINE -->
        <div class="p-3.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80 flex items-center gap-3.5 shadow-sm">
            <div class="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                <span class="material-symbols-outlined text-xl">sensors</span>
            </div>
            <div>
                <p class="text-[11px] font-mono uppercase text-emerald-300">Respondiendo (UP)</p>
                <p class="text-xl font-bold font-mono text-emerald-400">{{ $onlineDevices }}</p>
            </div>
        </div>

        <!-- CON FALLAS / TIMEOUT -->
        <div class="p-3.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80 flex items-center gap-3.5 shadow-sm">
            <div class="w-10 h-10 rounded-lg bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-400">
                <span class="material-symbols-outlined text-xl">warning</span>
            </div>
            <div>
                <p class="text-[11px] font-mono uppercase text-red-300">Con Fallas / Timeout</p>
                <p class="text-xl font-bold font-mono text-red-400">{{ $failingDevices }}</p>
            </div>
        </div>

        <!-- INTERFACES -->
        <div class="p-3.5 rounded-xl bg-obsidian-panel border border-obsidian-border/80 flex items-center gap-3.5 shadow-sm">
            <div class="w-10 h-10 rounded-lg bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-purple-400">
                <span class="material-symbols-outlined text-xl">cable</span>
            </div>
            <div>
                <p class="text-[11px] font-mono uppercase text-purple-300">Puertos de Red</p>
                <p class="text-xl font-bold font-mono text-purple-400">{{ $monitoredInterfaces }} <span class="text-xs text-obsidian-muted font-normal">/ {{ $totalInterfaces }}</span></p>
            </div>
        </div>
    </div>

    <!-- FILTROS Y BÚSQUEDA -->
    <div class="p-3 rounded-xl bg-obsidian-panel border border-obsidian-border/80">
        <form method="GET" action="{{ route('admin.snmp.index') }}" class="flex flex-wrap items-center gap-2.5">
            <div class="flex-1 min-w-[200px] relative">
                <span class="material-symbols-outlined text-obsidian-muted absolute left-3 top-2.5 text-base">search</span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por IP, nombre, fabricante o sysName..." class="w-full pl-9 pr-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono placeholder-obsidian-muted focus:outline-none focus:border-cyan-400">
            </div>

            <select name="status" class="px-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-400">
                <option value="">Todos los Estados</option>
                <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Respondiendo (Éxito)</option>
                <option value="timeout" {{ request('status') === 'timeout' ? 'selected' : '' }}>Timeout / Sin Respuesta</option>
                <option value="auth_error" {{ request('status') === 'auth_error' ? 'selected' : '' }}>Error de Autenticación</option>
            </select>

            <select name="device_type" class="px-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-400">
                <option value="">Todos los Tipos</option>
                <option value="router" {{ request('device_type') === 'router' ? 'selected' : '' }}>Router</option>
                <option value="switch" {{ request('device_type') === 'switch' ? 'selected' : '' }}>Switch</option>
                <option value="firewall" {{ request('device_type') === 'firewall' ? 'selected' : '' }}>Firewall</option>
                <option value="server" {{ request('device_type') === 'server' ? 'selected' : '' }}>Servidor</option>
                <option value="ups" {{ request('device_type') === 'ups' ? 'selected' : '' }}>UPS</option>
            </select>

            <select name="site_id" class="px-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-400">
                <option value="">Todas las Sedes</option>
                @foreach($sites as $site)
                <option value="{{ $site->id }}" {{ request('site_id') == $site->id ? 'selected' : '' }}>{{ $site->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-obsidian-card border border-cyan-500/40 text-cyan-400 hover:bg-cyan-500 hover:text-black transition text-xs font-mono font-bold cursor-pointer">
                Filtrar
            </button>

            @if(request()->anyFilled(['search', 'status', 'device_type', 'site_id']))
            <a href="{{ route('admin.snmp.index') }}" class="px-2.5 py-1.5 text-xs font-mono text-obsidian-muted hover:text-white transition">
                Limpiar
            </a>
            @endif
        </form>
    </div>

    <!-- TABLA DE DISPOSITIVOS SNMP -->
    <div class="rounded-xl bg-obsidian-panel border border-obsidian-border/80 overflow-hidden shadow-sm">
        <div class="custom-table-scroll">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-obsidian-bg border-b border-obsidian-border text-obsidian-muted text-[11px] uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3">Dispositivo / IP</th>
                        <th class="px-4 py-3">Tipo / Sede</th>
                        <th class="px-4 py-3">SysName & Ubicación</th>
                        <th class="px-4 py-3">Uptime</th>
                        <th class="px-4 py-3 text-center">Estado Poll</th>
                        <th class="px-4 py-3 text-center">Puertos (Interfaces)</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/40">
                    @forelse($devices as $dev)
                    <tr class="hover:bg-obsidian-card/50 transition">
                        <!-- DISPOSITIVO / IP -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-obsidian-bg border border-obsidian-border flex items-center justify-center text-cyan-400 shrink-0">
                                    @if($dev->device_type === 'router')
                                    <span class="material-symbols-outlined text-base">router</span>
                                    @elseif($dev->device_type === 'switch')
                                    <span class="material-symbols-outlined text-base">hub</span>
                                    @elseif($dev->device_type === 'firewall')
                                    <span class="material-symbols-outlined text-base">shield</span>
                                    @elseif($dev->device_type === 'ups')
                                    <span class="material-symbols-outlined text-base">battery_charging_full</span>
                                    @else
                                    <span class="material-symbols-outlined text-base">dns</span>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-bold text-white flex items-center gap-1.5">
                                        <span>{{ $dev->name }}</span>
                                        @if(!$dev->is_active)
                                        <span class="px-1 py-0.2 rounded bg-obsidian-bg border border-obsidian-border text-obsidian-muted text-[9px]">PAUSADO</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-cyan-400/90 flex items-center gap-1">
                                        <span>{{ $dev->ip_address }}</span>
                                        <span class="text-obsidian-muted">• v{{ $dev->snmp_version }}</span>
                                    </div>
                                    @if($dev->vendor || $dev->model)
                                    <div class="text-[10px] text-obsidian-muted">{{ $dev->vendor }} {{ $dev->model }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <!-- TIPO / SEDE -->
                        <td class="px-4 py-3">
                            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-obsidian-bg border border-obsidian-border text-[10.5px] uppercase text-obsidian-muted mb-1">
                                {{ $dev->device_type_label }}
                            </div>
                            <div class="text-white text-[11px] flex items-center gap-1">
                                <span class="material-symbols-outlined text-[13px] text-obsidian-cyan">domain</span>
                                <span>{{ $dev->site ? $dev->site->name : 'Valle Seco (Principal)' }}</span>
                            </div>
                        </td>

                        <!-- SYSNAME & UBICACIÓN -->
                        <td class="px-4 py-3">
                            <div class="text-white font-medium truncate max-w-[180px]" title="{{ $dev->sys_name }}">
                                {{ $dev->sys_name ?: '—' }}
                            </div>
                            <div class="text-[10.5px] text-obsidian-muted truncate max-w-[180px]" title="{{ $dev->sys_location }}">
                                {{ $dev->sys_location ?: ($dev->sys_description ? Str::limit($dev->sys_description, 25) : 'Sin ubicación') }}
                            </div>
                        </td>

                        <!-- UPTIME -->
                        <td class="px-4 py-3">
                            @if($dev->sys_uptime)
                                @php
                                    $seconds = floor($dev->sys_uptime / 100);
                                    $days = floor($seconds / 86400);
                                    $hours = floor(($seconds % 86400) / 3600);
                                    $mins = floor(($seconds % 3600) / 60);
                                @endphp
                                <div class="text-emerald-400 font-bold flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">schedule</span>
                                    <span>{{ $days }}d {{ $hours }}h {{ $mins }}m</span>
                                </div>
                                <div class="text-[10px] text-obsidian-muted">Activo continuo</div>
                            @else
                                <span class="text-obsidian-muted">—</span>
                            @endif
                        </td>

                        <!-- ESTADO POLL -->
                        <td class="px-4 py-3 text-center">
                            @if($dev->last_poll_status === 'success')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10.5px] font-bold" title="Sondeo SNMP completado con éxito">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Respondiendo
                            </span>
                            <div class="text-[9.5px] text-obsidian-muted mt-1" title="{{ $dev->last_poll_at ? $dev->last_poll_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'Sin registro' }}">
                                {{ $dev->last_poll_at ? $dev->last_poll_at->diffForHumans() : 'Reciente' }}
                            </div>
                            @elseif($dev->last_poll_status === 'timeout')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-500/10 border border-red-500/30 text-red-400 text-[10.5px] font-bold" title="Sin respuesta UDP 161 tras reintentos">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                Sin Respuesta ({{ $dev->consecutive_failures }}x)
                            </span>
                            <div class="text-[9.5px] text-red-400/80 mt-1" title="{{ $dev->last_poll_at ? $dev->last_poll_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'Sin registro' }}">
                                Sin respuesta UDP 161
                            </div>
                            @elseif($dev->last_poll_status === 'auth_error')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[10.5px] font-bold" title="Error de autenticación o comunidad incorrecta">
                                Error Autenticación
                            </span>
                            <div class="text-[9.5px] text-amber-400/80 mt-1" title="{{ $dev->last_poll_at ? $dev->last_poll_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'Sin registro' }}">
                                Comunidad / Auth inválida
                            </div>
                            @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-obsidian-bg border border-obsidian-border text-obsidian-muted text-[10.5px]">
                                Pendiente
                            </span>
                            @endif
                        </td>

                        <!-- INTERFACES -->
                        <td class="px-4 py-3 text-center">
                            @php $ifCount = $dev->interfaces->count(); @endphp
                            <button type="button" onclick="openInterfacesModal({{ $dev->id }}, '{{ $dev->name }}', '{{ $dev->ip_address }}')" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-obsidian-bg hover:bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 transition text-[11px] font-bold cursor-pointer">
                                <span class="material-symbols-outlined text-xs">cable</span>
                                <span>{{ $ifCount }} Puertos</span>
                            </button>
                        </td>

                        <!-- ACCIONES -->
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-1.5">
                                <button type="button" onclick="openInterfacesModal({{ $dev->id }}, '{{ $dev->name }}', '{{ $dev->ip_address }}')" class="p-1.5 rounded-lg bg-obsidian-bg hover:bg-cyan-500 hover:text-black border border-obsidian-border text-cyan-400 transition cursor-pointer" title="Ver Interfaces y Gráfica de Tráfico">
                                    <span class="material-symbols-outlined text-sm">show_chart</span>
                                </button>
                                
                                <button type="button" onclick="openDeviceDetailModal({{ $dev->id }})" class="p-1.5 rounded-lg bg-obsidian-bg hover:bg-obsidian-card border border-obsidian-border text-obsidian-muted hover:text-white transition cursor-pointer" title="Detalles del Dispositivo">
                                    <span class="material-symbols-outlined text-sm">info</span>
                                </button>

                                @if(auth()->user()->isAdmin())
                                    @if($isClusterSlave)
                                        <span class="p-1 rounded bg-gray-900 border border-gray-800 text-gray-500 text-[10px] font-mono inline-flex items-center gap-1" title="Modificaciones restringidas al Servidor Master">
                                            <span class="material-symbols-outlined text-[12px]">lock</span>
                                            <span>Solo Lectura</span>
                                        </span>
                                    @else
                                        <button type="button" onclick="triggerDevicePoll({{ $dev->id }})" class="p-1.5 rounded-lg bg-obsidian-bg hover:bg-emerald-500 hover:text-black border border-obsidian-border text-emerald-400 transition cursor-pointer" title="Sondear SNMP Ahora">
                                            <span class="material-symbols-outlined text-sm">refresh</span>
                                        </button>

                                        <button type="button" onclick="triggerInterfaceDiscovery({{ $dev->id }})" class="p-1.5 rounded-lg bg-obsidian-bg hover:bg-purple-500 hover:text-white border border-obsidian-border text-purple-400 transition cursor-pointer" title="Descubrir Puertos (ifTable)">
                                            <span class="material-symbols-outlined text-sm">travel_explore</span>
                                        </button>

                                        <button type="button" onclick="openEditDeviceModal({{ $dev->id }})" class="p-1.5 rounded-lg bg-obsidian-bg hover:bg-cyan-500 hover:text-black border border-obsidian-border text-cyan-400 transition cursor-pointer" title="Editar Dispositivo">
                                            <span class="material-symbols-outlined text-sm">edit</span>
                                        </button>

                                        <form method="POST" action="{{ route('admin.snmp.destroy', $dev->id) }}" class="inline" onsubmit="return confirm('¿Está seguro de eliminar el dispositivo {{ $dev->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg bg-obsidian-bg hover:bg-red-500 hover:text-white border border-obsidian-border text-red-400 transition cursor-pointer" title="Eliminar Dispositivo">
                                                <span class="material-symbols-outlined text-sm">delete</span>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-obsidian-muted">
                            <span class="material-symbols-outlined text-3xl mb-1 text-obsidian-border">router</span>
                            <p>No se encontraron dispositivos SNMP configurados o coincidentes con los filtros.</p>
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
</div>

<!-- ========================================================================= -->
<!-- MODAL DE INTERFACES Y GRÁFICO DE TRÁFICO (CHART.JS) -->
<!-- ========================================================================= -->
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
        <div class="p-6 overflow-y-auto space-y-6 custom-scroll">
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

<!-- ========================================================================= -->
<!-- MODAL NUEVO DISPOSITIVO SNMP (ADMIN) -->
<!-- ========================================================================= -->
@if(auth()->user()->isAdmin() && !$isClusterSlave)
<div id="newDeviceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs hidden">
    <div class="w-full max-w-lg rounded-2xl bg-obsidian-card border border-obsidian-border shadow-2xl overflow-hidden">
        <div class="px-6 py-4 bg-obsidian-panel border-b border-obsidian-border flex items-center justify-between">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-400 text-lg">add_circle</span>
                Registrar Nuevo Dispositivo SNMP
            </h3>
            <button type="button" onclick="closeNewDeviceModal()" class="text-obsidian-muted hover:text-white text-xl leading-none">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.snmp.store') }}" class="p-6 space-y-4">
            @csrf
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
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="closeNewDeviceModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-obsidian-bg">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black text-xs font-mono font-bold">Guardar Dispositivo</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL EDITAR DISPOSITIVO SNMP (ADMIN) -->
<!-- ========================================================================= -->
<div id="editDeviceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs hidden">
    <div class="w-full max-w-lg rounded-2xl bg-obsidian-card border border-obsidian-border shadow-2xl overflow-hidden">
        <div class="px-6 py-4 bg-obsidian-panel border-b border-obsidian-border flex items-center justify-between">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-lg">edit</span>
                <span>Editar Dispositivo SNMP</span>
                <span id="edit-modal-ip-badge" class="ml-2 px-2 py-0.5 rounded bg-obsidian-bg border border-obsidian-border text-[11px] font-mono text-cyan-400"></span>
            </h3>
            <button type="button" onclick="closeEditDeviceModal()" class="text-obsidian-muted hover:text-white text-xl leading-none cursor-pointer">&times;</button>
        </div>
        <form id="editDeviceForm" method="POST" action="" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            
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
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeEditDeviceModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-obsidian-bg cursor-pointer">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black text-xs font-mono font-bold transition cursor-pointer flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">save</span>
                        <span>Actualizar Cambios</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL ACTIVACIÓN REMOTA SNMP (SSH / TELNET CISCO & PFSENSE API) -->
<!-- ========================================================================= -->
<div id="remoteActivationModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs hidden">
    <div class="w-full max-w-xl rounded-2xl bg-obsidian-card border border-obsidian-border shadow-2xl overflow-hidden">
        <div class="px-6 py-4 bg-obsidian-panel border-b border-obsidian-border flex items-center justify-between">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-400 text-lg">bolt</span>
                Habilitación Remota de SNMP (Cisco & pfSense)
            </h3>
            <button type="button" onclick="closeRemoteActivationModal()" class="text-obsidian-muted hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="p-6 space-y-4">
            <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-mono flex items-start gap-2">
                <span class="material-symbols-outlined text-base shrink-0 mt-0.5">security</span>
                <span>Configura automáticamente <code class="text-white">snmp-server community RO</code> y habilita traps mediante sesión autenticada con registro estricto en auditoría.</span>
            </div>

            <form id="remoteActivationForm" onsubmit="submitRemoteActivation(event)" class="space-y-3">
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

                <div id="activation-result" class="hidden p-3 rounded-lg border text-xs font-mono max-h-40 overflow-y-auto whitespace-pre-wrap"></div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeRemoteActivationModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-obsidian-bg">Cerrar</button>
                    <button type="submit" id="btn-submit-activation" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-black text-xs font-mono font-bold flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">bolt</span>
                        <span>Ejecutar Activación</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- ========================================================================= -->
<!-- JAVASCRIPT & CHART.JS ENGINE -->
<!-- ========================================================================= -->
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

        // Reset Chart
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

        const isAdmin = {{ auth()->user()->isAdmin() ? 'true' : 'false' }};
        let html = '';
        interfaces.forEach(i => {
            const operBadge = i.if_oper_status === 'up'
                ? '<span class="px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] font-bold">Activo (UP)</span>'
                : '<span class="px-2 py-0.5 rounded-full bg-red-500/10 border border-red-500/30 text-red-400 text-[10px]">Caído (DOWN)</span>';
            
            const adminBadge = i.if_admin_status === 'up'
                ? '<span class="text-emerald-400 text-[10px]">Habilitado</span>'
                : '<span class="text-red-400 text-[10px]">Deshabilitado</span>';

            const speed = i.if_speed ? (i.if_speed >= 1000000000 ? `${(i.if_speed / 1000000000).toFixed(0)} Gbps` : `${(i.if_speed / 1000000).toFixed(0)} Mbps`) : '—';
            
            const monitorControl = isAdmin
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

    function openDeviceDetailModal(deviceId) {
        fetch(`/admin/snmp/${deviceId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const d = data.device;
                    alert(`INFORMACIÓN SNMP:\n\nNombre: ${d.name}\nIP: ${d.ip_address}\nSysName: ${d.sys_name || 'N/A'}\nSysLocation: ${d.sys_location || 'N/A'}\nSysDescr: ${d.sys_description || 'N/A'}\nFabricante: ${d.vendor || 'N/A'} ${d.model || ''}`);
                }
            });
    }
</script>
@endsection
