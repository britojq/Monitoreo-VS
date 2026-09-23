@extends('layouts.admin')

@section('page_title', 'NET Radar & Monitoreo de Tráfico')

@section('admin_content')
<style>
    .custom-table-scroll {
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: #06b6d4 #040b15;
    }
    .custom-table-scroll::-webkit-scrollbar {
        height: 8px;
    }
    .custom-table-scroll::-webkit-scrollbar-track {
        background: #040b15;
        border-radius: 4px;
        border: 1px solid #1e293b;
    }
    .custom-table-scroll::-webkit-scrollbar-thumb {
        background: #06b6d4;
        border-radius: 4px;
        border: 2px solid #040b15;
    }
    .custom-table-scroll::-webkit-scrollbar-thumb:hover {
        background: #22d3ee;
    }
    @keyframes pulse-amber {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(1.05); }
    }
    .pulse-amber {
        animation: pulse-amber 2s infinite ease-in-out;
    }
    @keyframes pulse-purple {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(1.05); }
    }
    .pulse-purple {
        animation: pulse-purple 2s infinite ease-in-out;
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

    <!-- CABECERA PRINCIPAL -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-xs font-mono font-semibold group shadow-xs">
                    <span class="material-symbols-outlined text-sm group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-xs font-mono text-obsidian-muted">Análisis y Tráfico de Red</span>
            </div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan">troubleshoot</span>
                NET Radar & Monitoreo de Tráfico
            </h2>
            <p class="text-xs font-mono text-obsidian-muted">Inspección pasiva de flujo de red, ancho de banda y detección especializada de actualizaciones (Windows Update / Repositorios Linux)</p>
        </div>

        <div class="flex items-center gap-2.5">
            <!-- BOTÓN AUTO-REFRESH -->
            <button id="btnAutoRefresh" type="button" onclick="toggleAutoRefresh()" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border hover:border-obsidian-cyan/70 text-obsidian-muted hover:text-white text-xs font-mono font-semibold flex items-center gap-1.5 transition shadow-xs cursor-pointer">
                <span id="refreshDot" class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span id="refreshText">Auto-Refresco: ON (10s)</span>
            </button>

            @if(Auth::user() && (Auth::user()->role === 'admin' || (method_exists(Auth::user(), 'hasPermission') && Auth::user()->hasPermission('netradar.scan'))))
            <!-- BOTÓN ESCANEAR / CAPTURAR EN VIVO -->
            <button type="button" id="btnScanNow" onclick="triggerScanNow()" class="px-3 py-1.5 rounded-lg bg-obsidian-cyan/15 hover:bg-obsidian-cyan/25 border border-obsidian-cyan/40 text-obsidian-cyan text-xs font-mono font-semibold flex items-center gap-1.5 transition shadow-xs cursor-pointer">
                <span id="scanIcon" class="material-symbols-outlined text-base">sensors</span>
                <span id="scanText">Captura en Vivo</span>
            </button>
            @endif

            @if(Auth::user() && (Auth::user()->role === 'admin' || (method_exists(Auth::user(), 'hasPermission') && Auth::user()->hasPermission('netradar.export'))))
            <!-- BOTÓN EXPORTAR REPORTE MD -->
            <a href="{{ route('admin.netradar.exportReport') }}" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border hover:border-obsidian-cyan/70 text-white text-xs font-mono font-semibold flex items-center gap-1.5 transition shadow-xs cursor-pointer group">
                <span class="material-symbols-outlined text-base text-cyan-400 group-hover:scale-110 transition-transform">download</span>
                <span>Exportar Reporte (.md)</span>
            </a>
            @endif
        </div>
    </div>

    <!-- NOTIFICACIÓN / ALERTA DE ACTUALIZACIONES ACTIVAS (SI EXISTEN) -->
    @if($windowsUpdating > 0 || $linuxUpdating > 0)
    <div class="p-3 rounded-xl bg-amber-950/30 border border-amber-500/40 flex items-start gap-3 shadow-lg">
        <span class="material-symbols-outlined text-amber-400 text-xl shrink-0 mt-0.5">warning</span>
        <div class="flex-1">
            <h4 class="text-xs font-bold text-amber-300 uppercase tracking-wider font-mono">
                Alerta de Consumo de Red: Actualizaciones en Curso
            </h4>
            <p class="text-xs text-amber-200/90 mt-0.5">
                Se han detectado 
                @if($windowsUpdating > 0)
                    <strong class="text-amber-300 font-mono">{{ $windowsUpdating }}</strong> host(s) buscando o descargando <strong class="text-amber-300">Windows Update</strong>
                @endif
                @if($windowsUpdating > 0 && $linuxUpdating > 0) y @endif
                @if($linuxUpdating > 0)
                    <strong class="text-purple-300 font-mono">{{ $linuxUpdating }}</strong> host(s) sincronizando <strong class="text-purple-300">Repositorios Linux</strong>
                @endif
                . Revise los equipos resaltados en la tabla para prevenir congestión del enlace.
            </p>
        </div>
    </div>
    @endif

    <!-- HUD DE MÉTRICAS KPI -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <!-- CARD 1: TOTAL HOSTS -->
        <div class="p-3.5 rounded-xl bg-obsidian-card border border-obsidian-border hover:border-obsidian-cyan/40 transition shadow-xs">
            <div class="flex items-center justify-between text-obsidian-muted mb-1">
                <span class="text-[11px] font-mono uppercase tracking-wider">Total Hosts</span>
                <span class="material-symbols-outlined text-base text-obsidian-cyan">devices</span>
            </div>
            <div class="text-xl font-bold font-mono text-white" id="kpiTotalHosts">{{ number_format($totalHosts) }}</div>
            <div class="text-[10px] font-mono text-obsidian-muted mt-0.5">Identificados en LAN</div>
        </div>

        <!-- CARD 2: HOSTS ACTIVOS -->
        <div class="p-3.5 rounded-xl bg-obsidian-card border border-obsidian-border hover:border-emerald-500/40 transition shadow-xs">
            <div class="flex items-center justify-between text-obsidian-muted mb-1">
                <span class="text-[11px] font-mono uppercase tracking-wider text-emerald-400">Hosts Activos</span>
                <span class="material-symbols-outlined text-base text-emerald-400">wifi_tethering</span>
            </div>
            <div class="text-xl font-bold font-mono text-emerald-300 flex items-center gap-1.5" id="kpiActiveHosts">
                <span>{{ number_format($activeHosts) }}</span>
                <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-ping"></span>
            </div>
            <div class="text-[10px] font-mono text-emerald-500/80 mt-0.5">Vistos en últimos 15m</div>
        </div>

        <!-- CARD 3: WINDOWS UPDATE -->
        <div class="p-3.5 rounded-xl bg-obsidian-card border border-obsidian-border hover:border-amber-500/40 transition shadow-xs">
            <div class="flex items-center justify-between text-obsidian-muted mb-1">
                <span class="text-[11px] font-mono uppercase tracking-wider text-amber-400">Windows Update</span>
                <span class="material-symbols-outlined text-base text-amber-400">grid_view</span>
            </div>
            <div class="text-xl font-bold font-mono text-amber-300 flex items-center gap-1.5" id="kpiWindowsUpdating">
                <span>{{ number_format($windowsUpdating) }}</span>
                @if($windowsUpdating > 0)
                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 pulse-amber">ACTIVO</span>
                @endif
            </div>
            <div class="text-[10px] font-mono text-amber-500/80 mt-0.5">Peticiones o descargas</div>
        </div>

        <!-- CARD 4: LINUX REPOS -->
        <div class="p-3.5 rounded-xl bg-obsidian-card border border-obsidian-border hover:border-purple-500/40 transition shadow-xs">
            <div class="flex items-center justify-between text-obsidian-muted mb-1">
                <span class="text-[11px] font-mono uppercase tracking-wider text-purple-400">Linux Repos</span>
                <span class="material-symbols-outlined text-base text-purple-400">terminal</span>
            </div>
            <div class="text-xl font-bold font-mono text-purple-300 flex items-center gap-1.5" id="kpiLinuxUpdating">
                <span>{{ number_format($linuxUpdating) }}</span>
                @if($linuxUpdating > 0)
                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30 pulse-purple">ACTIVO</span>
                @endif
            </div>
            <div class="text-[10px] font-mono text-purple-400/80 mt-0.5">Debian / Ubuntu / APT</div>
        </div>

        <!-- CARD 5: VOLUMEN DE TRÁFICO -->
        <div class="p-3.5 rounded-xl bg-obsidian-card border border-obsidian-border hover:border-cyan-500/40 transition shadow-xs col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between text-obsidian-muted mb-1">
                <span class="text-[11px] font-mono uppercase tracking-wider text-cyan-400">Tráfico Total</span>
                <span class="material-symbols-outlined text-base text-cyan-400">swap_vert</span>
            </div>
            <div class="text-lg font-bold font-mono text-white" id="kpiTotalTraffic">{{ $fmtTotalTraffic }}</div>
            <div class="text-[10px] font-mono text-obsidian-muted mt-0.5 flex justify-between">
                <span class="text-emerald-400">↓ {{ $fmtBytesIn }}</span>
                <span class="text-blue-400">↑ {{ $fmtBytesOut }}</span>
            </div>
        </div>
    </div>

    <!-- BARRA DE FILTROS Y BÚSQUEDA -->
    <div class="p-3.5 rounded-xl bg-obsidian-card border border-obsidian-border shadow-xs">
        <form method="GET" action="{{ route('admin.netradar.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center">
            <!-- BÚSQUEDA POR TEXTO -->
            <div class="sm:col-span-5 relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-obsidian-muted text-sm">search</span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por IP, MAC, Nombre, Fabricante, SO..." 
                    class="w-full pl-9 pr-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-obsidian-cyan focus:outline-hidden transition">
            </div>

            <!-- FILTRO POR ESTADO / TIPO -->
            <div class="sm:col-span-3">
                <select name="status" class="w-full px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-obsidian-cyan focus:outline-hidden transition">
                    <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Todos los Hosts</option>
                    <option value="updating" {{ $statusFilter === 'updating' ? 'selected' : '' }}>🚨 Con Actualizaciones (Win/Linux)</option>
                    <option value="windows_update" {{ $statusFilter === 'windows_update' ? 'selected' : '' }}>🪟 Solo Windows Update</option>
                    <option value="linux_repo" {{ $statusFilter === 'linux_repo' ? 'selected' : '' }}>🐧 Solo Repositorios Linux</option>
                    <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>🟢 Solo Activos (&lt; 15 min)</option>
                    <option value="inactive" {{ $statusFilter === 'inactive' ? 'selected' : '' }}>⚪ Solo Inactivos</option>
                </select>
            </div>

            <!-- ORDENAMIENTO -->
            <div class="sm:col-span-2">
                <select name="sort" class="w-full px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-obsidian-cyan focus:outline-hidden transition">
                    <option value="total_bytes" {{ $sort === 'total_bytes' ? 'selected' : '' }}>Mayor Tráfico</option>
                    <option value="bytes_in" {{ $sort === 'bytes_in' ? 'selected' : '' }}>Mayor Bajada (RX)</option>
                    <option value="bytes_out" {{ $sort === 'bytes_out' ? 'selected' : '' }}>Mayor Subida (TX)</option>
                    <option value="update_bytes" {{ $sort === 'update_bytes' ? 'selected' : '' }}>Tráfico Updates</option>
                    <option value="last_seen_at" {{ $sort === 'last_seen_at' ? 'selected' : '' }}>Visto Recientemente</option>
                    <option value="ip" {{ $sort === 'ip' ? 'selected' : '' }}>Dirección IP</option>
                </select>
            </div>

            <!-- BOTONES ACCIÓN -->
            <div class="sm:col-span-2 flex items-center gap-1.5">
                <button type="submit" class="flex-1 px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border hover:border-obsidian-cyan/70 text-white text-xs font-mono font-semibold flex items-center justify-center gap-1 transition cursor-pointer">
                    <span class="material-symbols-outlined text-sm text-cyan-400">filter_alt</span>
                    <span>Filtrar</span>
                </button>
                @if(!empty($search) || $statusFilter !== 'all' || $sort !== 'total_bytes')
                <a href="{{ route('admin.netradar.index') }}" title="Limpiar filtros" class="p-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border hover:text-red-400 text-obsidian-muted transition flex items-center justify-center">
                    <span class="material-symbols-outlined text-sm">close</span>
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- TABLA PRINCIPAL DE HOSTS Y TRÁFICO -->
    <div class="rounded-xl bg-obsidian-card border border-obsidian-border shadow-md overflow-hidden">
        <div class="p-3.5 border-b border-obsidian-border flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-lg">radar</span>
                <h3 class="text-xs font-bold text-white uppercase tracking-wider font-mono">
                    Inventario de Hosts y Tráfico en Vivo
                </h3>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-obsidian-panel border border-obsidian-border text-obsidian-cyan">
                    {{ $hosts->total() }} registros
                </span>
            </div>
            <div class="text-[11px] font-mono text-obsidian-muted">
                Página {{ $hosts->currentPage() }} de {{ $hosts->lastPage() }}
            </div>
        </div>

        <div class="custom-table-scroll">
            <table class="w-full text-left text-xs border-collapse font-mono whitespace-nowrap">
                <thead>
                    <tr class="border-b border-obsidian-border bg-[#040b15] text-[11px] text-cyan-400 font-semibold tracking-wider uppercase">
                        <th class="px-3.5 py-2">Host / IP</th>
                        <th class="px-3.5 py-2">MAC / Fabricante</th>
                        <th class="px-3.5 py-2">Sistema Operativo</th>
                        <th class="px-3.5 py-2">Tráfico (Rx / Tx)</th>
                        <th class="px-3.5 py-2">Total Transferido</th>
                        <th class="px-3.5 py-2">Estado Actualizaciones</th>
                        <th class="px-3.5 py-2">Última Actividad</th>
                        <th class="px-3.5 py-2 text-center">Forense</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/50 text-white">
                    @forelse($hosts as $h)
                    <tr class="hover:bg-obsidian-panel/60 transition {{ $h->update_status !== 'none' ? 'bg-amber-950/10' : '' }}">
                        <!-- HOST / IP -->
                        <td class="px-3.5 py-2">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ $h->is_active ? 'bg-emerald-400 shadow-sm shadow-emerald-400/50' : 'bg-slate-600' }}"></span>
                                <div>
                                    <div class="font-bold text-cyan-300">{{ $h->ip }}</div>
                                    @if($h->hostname)
                                    <div class="text-[10px] text-obsidian-muted truncate max-w-[140px]">{{ $h->hostname }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <!-- MAC & FABRICANTE -->
                        <td class="px-3.5 py-2">
                            <div class="font-mono text-xs text-slate-300">{{ $h->mac ?: 'N/A' }}</div>
                            <div class="text-[10px] text-obsidian-muted truncate max-w-[150px]">
                                {{ $h->vendor ?: 'Desconocido' }}
                            </div>
                        </td>

                        <!-- SISTEMA OPERATIVO -->
                        <td class="px-3.5 py-2">
                            @php
                                $os = strtolower($h->os_detected ?? '');
                            @endphp
                            @if(str_contains($os, 'windows'))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-500/15 text-blue-300 border border-blue-500/30">
                                    <span class="material-symbols-outlined text-[12px]">grid_view</span>
                                    {{ $h->os_detected }}
                                </span>
                            @elseif(str_contains($os, 'linux') || str_contains($os, 'debian') || str_contains($os, 'ubuntu'))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                    <span class="material-symbols-outlined text-[12px]">terminal</span>
                                    {{ $h->os_detected }}
                                </span>
                            @elseif(str_contains($os, 'switch') || str_contains($os, 'router') || str_contains($os, 'cisco'))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-cyan-500/15 text-cyan-300 border border-cyan-500/30">
                                    <span class="material-symbols-outlined text-[12px]">router</span>
                                    {{ $h->os_detected }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] text-obsidian-muted bg-obsidian-panel border border-obsidian-border">
                                    <span class="material-symbols-outlined text-[12px]">help_outline</span>
                                    {{ $h->os_detected ?: 'Desconocido' }}
                                </span>
                            @endif
                        </td>

                        <!-- TRÁFICO (RX / TX) -->
                        <td class="px-3.5 py-2">
                            <div class="flex items-center gap-2">
                                <span class="text-emerald-400 font-mono text-[11px]" title="Bytes RX (Bajada)">↓ {{ $h->formatted_bytes_in }}</span>
                                <span class="text-obsidian-muted text-[10px]">/</span>
                                <span class="text-blue-400 font-mono text-[11px]" title="Bytes TX (Subida)">↑ {{ $h->formatted_bytes_out }}</span>
                            </div>
                            <div class="text-[9px] text-obsidian-muted">{{ number_format($h->packet_count) }} paquetes</div>
                        </td>

                        <!-- TOTAL TRANSFERIDO -->
                        <td class="px-3.5 py-2 font-bold text-white font-mono">
                            {{ $h->formatted_total_bytes }}
                        </td>

                        <!-- ESTADO ACTUALIZACIONES -->
                        <td class="px-3.5 py-2">
                            @if($h->update_status === 'downloading' && $h->last_update_type === 'windows_update')
                                <div class="inline-flex flex-col gap-0.5">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 pulse-amber">
                                        <span class="material-symbols-outlined text-[12px] animate-spin">sync</span>
                                        Windows Update (Descarga)
                                    </span>
                                    <span class="text-[9px] font-mono text-amber-400/80 truncate max-w-[180px]" title="{{ $h->last_update_target }}">
                                        {{ $h->last_update_target ?: 'Microsoft Update' }} ({{ $h->formatted_update_bytes }})
                                    </span>
                                </div>
                            @elseif($h->update_status === 'checking' && $h->last_update_type === 'windows_update')
                                <div class="inline-flex flex-col gap-0.5">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/30">
                                        <span class="material-symbols-outlined text-[12px]">search</span>
                                        Windows Update (Búsqueda)
                                    </span>
                                    <span class="text-[9px] font-mono text-obsidian-muted truncate max-w-[180px]" title="{{ $h->last_update_target }}">
                                        {{ $h->last_update_target ?: 'Microsoft Update' }}
                                    </span>
                                </div>
                            @elseif($h->update_status === 'downloading' && $h->last_update_type === 'linux_repo')
                                <div class="inline-flex flex-col gap-0.5">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/40 pulse-purple">
                                        <span class="material-symbols-outlined text-[12px] animate-spin">download</span>
                                        Linux Repo (Descarga)
                                    </span>
                                    <span class="text-[9px] font-mono text-purple-300/80 truncate max-w-[180px]" title="{{ $h->last_update_target }}">
                                        {{ $h->last_update_target ?: 'Repositorio APT' }} ({{ $h->formatted_update_bytes }})
                                    </span>
                                </div>
                            @elseif($h->update_status === 'checking' && $h->last_update_type === 'linux_repo')
                                <div class="inline-flex flex-col gap-0.5">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-500/10 text-purple-400 border border-purple-500/30">
                                        <span class="material-symbols-outlined text-[12px]">search</span>
                                        Linux Repo (Consultando)
                                    </span>
                                    <span class="text-[9px] font-mono text-obsidian-muted truncate max-w-[180px]" title="{{ $h->last_update_target }}">
                                        {{ $h->last_update_target ?: 'Repositorio APT' }}
                                    </span>
                                </div>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] text-obsidian-muted bg-obsidian-panel/50 border border-obsidian-border/50">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                                    Normal / Sin actividad
                                </span>
                            @endif
                        </td>

                        <!-- ÚLTIMA ACTIVIDAD -->
                        <td class="px-3.5 py-2 text-obsidian-muted text-[11px]">
                            @if($h->last_seen_at)
                                <div class="text-white">{{ $h->last_seen_at->format('H:i:s') }}</div>
                                <div class="text-[10px] text-obsidian-muted">{{ $h->last_seen_at->diffForHumans() }}</div>
                            @else
                                <span class="text-obsidian-muted">Desconocido</span>
                            @endif
                        </td>

                        <!-- ACCIONES / FORENSE -->
                        <td class="px-3.5 py-2 text-center">
                            <button type="button" onclick="openHostModal('{{ $h->ip }}', '{{ $h->hostname ?: $h->ip }}', '{{ $h->vendor }}', '{{ $h->os_detected }}')" 
                                class="p-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border hover:border-obsidian-cyan hover:text-obsidian-cyan text-obsidian-muted transition shadow-xs cursor-pointer"
                                title="Ver bitácora forense de tráfico de este host">
                                <span class="material-symbols-outlined text-sm">manage_search</span>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-obsidian-muted">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-3xl text-obsidian-border">troubleshoot</span>
                                <p class="text-xs font-mono">No se encontraron hosts que coincidan con los filtros aplicados.</p>
                                <a href="{{ route('admin.netradar.index') }}" class="text-xs text-obsidian-cyan hover:underline font-mono">Limpiar filtros</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN -->
        @if($hosts->hasPages())
        <div class="p-3 border-t border-obsidian-border bg-[#030912]">
            {{ $hosts->links('vendor.pagination.obsidian') }}
        </div>
        @endif
    </div>

    <!-- SECCIÓN INFERIOR: EVENTOS FORENSES RECIENTES -->
    <div class="rounded-xl bg-obsidian-card border border-obsidian-border p-4 shadow-sm">
        <div class="flex items-center justify-between mb-3 border-b border-obsidian-border pb-2">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider font-mono flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-400 text-base">shield</span>
                Bitácora de Eventos Forenses Recientes (Actualizaciones & Alertas)
            </h3>
            <span class="text-[10px] font-mono text-obsidian-muted">Últimas detecciones del radar</span>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
            @forelse($recentEvents as $rev)
            <div class="p-2.5 rounded-lg bg-obsidian-panel/80 border border-obsidian-border flex items-start gap-2.5">
                <span class="material-symbols-outlined text-base mt-0.5 {{ $rev->event_type === 'windows_update' ? 'text-amber-400' : ($rev->event_type === 'linux_repo' ? 'text-purple-400' : 'text-cyan-400') }}">
                    {{ $rev->event_type === 'windows_update' ? 'grid_view' : ($rev->event_type === 'linux_repo' ? 'terminal' : 'info') }}
                </span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between text-[10px] font-mono mb-0.5">
                        <span class="font-bold text-cyan-300">{{ $rev->host_ip }}</span>
                        <span class="text-obsidian-muted">{{ $rev->created_at->format('H:i:s') }}</span>
                    </div>
                    <p class="text-xs text-slate-200 line-clamp-2">{{ $rev->description }}</p>
                    @if($rev->target_domain)
                    <div class="text-[10px] font-mono text-obsidian-muted truncate mt-1">
                        Destino: <span class="text-amber-300/80">{{ $rev->target_domain }}</span>
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <div class="col-span-2 text-center py-4 text-xs font-mono text-obsidian-muted">
                No hay eventos forenses registrados aún. Los eventos aparecerán cuando se detecte tráfico de actualizaciones.
            </div>
            @endforelse
        </div>
    </div>
</div>

<!-- MODAL FORENSE DE DETALLE DE HOST -->
<div id="hostModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-[#071321] border border-obsidian-border rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
        <!-- HEADER MODAL -->
        <div class="p-4 border-b border-obsidian-border bg-obsidian-panel/50 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-obsidian-cyan text-xl">manage_search</span>
                <div>
                    <h3 class="text-sm font-bold text-white font-mono flex items-center gap-2">
                        <span>Forense de Host:</span>
                        <span id="modalHostIp" class="text-obsidian-cyan">10.20.23.X</span>
                    </h3>
                    <div id="modalHostSubtitle" class="text-[11px] font-mono text-obsidian-muted">Cargando información...</div>
                </div>
            </div>
            <button type="button" onclick="closeHostModal()" class="p-1 rounded-lg hover:bg-obsidian-panel text-obsidian-muted hover:text-white transition">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <!-- CONTENIDO MODAL -->
        <div class="p-4 max-h-[60vh] overflow-y-auto space-y-3" id="modalEventsContainer">
            <div class="flex items-center justify-center py-8">
                <span class="material-symbols-outlined text-2xl text-obsidian-cyan animate-spin">progress_activity</span>
            </div>
        </div>

        <!-- FOOTER MODAL -->
        <div class="p-3 border-t border-obsidian-border bg-obsidian-panel/30 flex justify-end">
            <button type="button" onclick="closeHostModal()" class="px-4 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono font-semibold hover:border-obsidian-cyan transition cursor-pointer">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
    let autoRefreshTimer = null;
    let isAutoRefreshActive = true;

    function toggleAutoRefresh() {
        isAutoRefreshActive = !isAutoRefreshActive;
        const dot = document.getElementById('refreshDot');
        const text = document.getElementById('refreshText');
        
        if (isAutoRefreshActive) {
            dot.className = 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse';
            text.textContent = 'Auto-Refresco: ON (10s)';
            startPolling();
        } else {
            dot.className = 'w-2 h-2 rounded-full bg-slate-500';
            text.textContent = 'Auto-Refresco: OFF';
            if (autoRefreshTimer) clearInterval(autoRefreshTimer);
        }
    }

    function startPolling() {
        if (autoRefreshTimer) clearInterval(autoRefreshTimer);
        autoRefreshTimer = setInterval(pollNetRadarData, 10000);
    }

    async function pollNetRadarData() {
        try {
            const resp = await fetch("{{ route('admin.netradar.data') }}");
            if (!resp.ok) return;
            const res = await resp.json();
            if (res.success && res.kpis) {
                document.getElementById('kpiTotalHosts').textContent = Number(res.kpis.total_hosts).toLocaleString();
                const activeEl = document.getElementById('kpiActiveHosts');
                if (activeEl) {
                    activeEl.innerHTML = `<span>${Number(res.kpis.active_hosts).toLocaleString()}</span><span class="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-ping"></span>`;
                }
                document.getElementById('kpiWindowsUpdating').textContent = Number(res.kpis.windows_updating).toLocaleString();
                document.getElementById('kpiLinuxUpdating').textContent = Number(res.kpis.linux_updating).toLocaleString();
                document.getElementById('kpiTotalTraffic').textContent = res.kpis.fmt_total;
            }
        } catch (e) {
            console.error("Aviso en sondeo NET Radar:", e);
        }
    }

    async function triggerScanNow() {
        const btn = document.getElementById('btnScanNow');
        const icon = document.getElementById('scanIcon');
        const text = document.getElementById('scanText');

        btn.disabled = true;
        btn.classList.add('opacity-70');
        icon.classList.add('animate-spin');
        icon.textContent = 'sync';
        text.textContent = 'Escaneando...';

        try {
            const resp = await fetch("{{ route('admin.netradar.scan') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                }
            });
            const data = await resp.json();
            alert(data.message || 'Escaneo iniciado en segundo plano');
            setTimeout(() => {
                window.location.reload();
            }, 3000);
        } catch (e) {
            alert('Error al disparar escaneo: ' + e);
        } finally {
            btn.disabled = false;
            btn.classList.remove('opacity-70');
            icon.classList.remove('animate-spin');
            icon.textContent = 'sensors';
            text.textContent = 'Captura en Vivo';
        }
    }

    async function openHostModal(ip, hostname, vendor, os) {
        document.getElementById('modalHostIp').textContent = ip;
        document.getElementById('modalHostSubtitle').textContent = `${hostname} • ${vendor || 'Fabricante Desconocido'} • SO: ${os || 'N/A'}`;
        const container = document.getElementById('modalEventsContainer');
        container.innerHTML = `
            <div class="flex items-center justify-center py-8">
                <span class="material-symbols-outlined text-2xl text-obsidian-cyan animate-spin">progress_activity</span>
            </div>
        `;
        document.getElementById('hostModal').classList.remove('hidden');
        document.getElementById('hostModal').classList.add('flex');

        try {
            const resp = await fetch(`{{ route('admin.netradar.events') }}?ip=${encodeURIComponent(ip)}`);
            const data = await resp.json();
            if (data.success && data.events && data.events.length > 0) {
                let html = '<div class="space-y-2">';
                data.events.forEach(ev => {
                    const sevColor = ev.severity === 'critical' ? 'text-red-400 border-red-500/30' : 
                                    (ev.severity === 'warning' ? 'text-amber-400 border-amber-500/30' : 'text-cyan-400 border-cyan-500/30');
                    html += `
                        <div class="p-3 rounded-lg bg-obsidian-panel border border-obsidian-border text-xs">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-mono font-bold ${sevColor}">${ev.event_type.toUpperCase()}</span>
                                <span class="text-obsidian-muted font-mono text-[10px]">${ev.created_at}</span>
                            </div>
                            <p class="text-slate-200">${ev.description}</p>
                            ${ev.target_domain ? `<div class="text-[10px] font-mono text-amber-300/80 mt-1">Objetivo: ${ev.target_domain}</div>` : ''}
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="text-center py-8 text-obsidian-muted font-mono text-xs">
                        No se registran eventos forenses específicos para la IP ${ip}.
                    </div>
                `;
            }
        } catch (e) {
            container.innerHTML = `
                <div class="text-center py-8 text-red-400 font-mono text-xs">
                    Error al cargar eventos: ${e}
                </div>
            `;
        }
    }

    function closeHostModal() {
        document.getElementById('hostModal').classList.add('hidden');
        document.getElementById('hostModal').classList.remove('flex');
    }

    document.addEventListener('DOMContentLoaded', () => {
        startPolling();
    });
</script>
@endsection
