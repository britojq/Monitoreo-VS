@extends('layouts.admin')

@section('page_title', 'Dispositivos de Red y Equipos Monitoreados')

@section('admin_content')
<style>
    /* Barra de desplazamiento horizontal visible, resaltada en cyan y fácil de ver/arrastrar */
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
        background: #06b6d4; /* cyan-500 */
        border-radius: 5px;
        border: 2px solid #040b15;
    }
    .custom-table-scroll::-webkit-scrollbar-thumb:hover {
        background: #22d3ee; /* cyan-400 glow */
    }
</style>

<div class="space-y-3.5">
    <!-- CABECERA -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-[10.5px] font-mono font-semibold group shadow-xs">
                    <span class="material-symbols-outlined text-xs group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-[10.5px] font-mono text-obsidian-muted">Inventario Centralizado</span>
            </div>
            <h2 class="text-base font-bold text-white flex items-center gap-1.5">
                <span class="material-symbols-outlined text-cyan-400 text-lg">router</span>
                Dispositivos de Red y Equipos Monitoreados
            </h2>
            <p class="text-[10.5px] font-mono text-obsidian-muted">Supervisión técnica de Switches, Routers, Servidores y Equipos LAN (15 por página)</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="px-2.5 py-1 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 text-[10.5px] font-mono flex items-center gap-1.5 shadow-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                Fuente: SERVIDOR MAESTRO
            </span>

            @if(auth()->user()->isAdmin() && !$isClusterSlave)
                <button type="button" onclick="openCreateSiteModal()" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-obsidian-panel border border-purple-500/40 text-purple-300 hover:bg-purple-600 hover:text-white transition text-xs font-mono font-bold shadow-xs cursor-pointer" title="Agregar Nueva Sede">
                    <span class="material-symbols-outlined text-sm">domain_add</span>
                    <span>+ Sede</span>
                </button>
                <button type="button" onclick="openDeviceCreateModal()" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black transition text-xs font-mono font-bold shadow-md shadow-cyan-500/20 cursor-pointer" title="Agregar Nuevo Dispositivo a una Sede">
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    <span>+ Dispositivo</span>
                </button>
            @endif
        </div>
    </div>

    <!-- BARRA DE FILTRO POR SEDE Y ESTADÍSTICAS COMPACTA -->
    <div class="glass-card rounded-xl px-3.5 py-2 border border-obsidian-border/80 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
        <form action="{{ route('admin.devices.index') }}" method="GET" class="flex flex-wrap items-center gap-2 font-mono text-xs">
            <label class="text-obsidian-muted flex items-center gap-1 text-[10.5px] font-semibold">
                <span class="material-symbols-outlined text-sm text-cyan-400">filter_alt</span>
                Sede:
            </label>
            <select name="site_id" onchange="this.form.submit()" class="px-2 py-1 rounded bg-obsidian-panel border border-obsidian-border text-white text-[10.5px] font-mono focus:border-cyan-400 focus:outline-hidden">
                <option value="">-- Todas las Sedes ({{ $totalCount ?? $devices->total() }}) --</option>
                @foreach($sites as $s)
                    <option value="{{ $s->id }}" {{ $siteFilter == $s->id ? 'selected' : '' }}>
                        {{ $s->name }}
                    </option>
                @endforeach
            </select>
            @if($siteFilter)
                <a href="{{ route('admin.devices.index') }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition flex items-center gap-0.5 text-[10px]" title="Quitar filtro">
                    <span class="material-symbols-outlined text-xs">clear</span>
                    Todos
                </a>
            @endif
        </form>

        <div class="flex items-center gap-3 font-mono text-[10.5px]">
            <div>
                <span class="text-obsidian-muted text-[10px] uppercase">Total:</span>
                <span class="text-white font-bold ml-1">{{ $totalCount ?? $devices->total() }}</span>
            </div>
            <div class="text-obsidian-border">|</div>
            <div>
                <span class="text-obsidian-muted text-[10px] uppercase">Activos:</span>
                <span class="text-emerald-400 font-bold ml-1">{{ $activeCount ?? 0 }}</span>
            </div>
            <div class="text-obsidian-border">|</div>
            <div>
                <span class="text-obsidian-muted text-[10px] uppercase">Inactivos:</span>
                <span class="text-obsidian-muted font-bold ml-1">{{ $inactiveCount ?? 0 }}</span>
            </div>
        </div>
    </div>

    <!-- TABLA DE DISPOSITIVOS COMPACTA Y ULTRA-AJUSTADA -->
    <div class="glass-card rounded-xl overflow-hidden shadow-xl border border-obsidian-border/80">
        <div class="custom-table-scroll">
            <table class="w-full text-left font-mono">
                <thead class="bg-obsidian-panel border-b border-obsidian-border text-obsidian-muted uppercase text-[9.5px] tracking-wider">
                    <tr>
                        <th class="px-2.5 py-2 whitespace-nowrap">ID</th>
                        <th class="px-2.5 py-2 whitespace-nowrap">Sede Asignada</th>
                        <th class="px-2.5 py-2 whitespace-nowrap">Dispositivo</th>
                        <th class="px-2.5 py-2 whitespace-nowrap">IP & MAC</th>
                        <th class="px-2.5 py-2 whitespace-nowrap">Modelo & Serial</th>
                        <th class="px-2.5 py-2 whitespace-nowrap">Acceso</th>
                        <th class="px-2.5 py-2 whitespace-nowrap text-center">Monitoreo</th>
                        <th class="px-2.5 py-2 whitespace-nowrap text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody id="devices-table-body" class="divide-y divide-obsidian-border/60">
                    @forelse($devices as $d)
                        @php
                            $snap = $snapshotDevices->get($d->id);
                            $isUp = $snap ? ($snap['is_up'] ?? false) : true;
                            $lat = $snap ? ($snap['latency_ms'] ?? 0) : 0;
                            $latStr = $lat > 0 ? ($lat . ' ms') : '< 1 ms';
                            $accType = strtoupper(trim($d->access_type ?? 'SIN SOPORTE'));
                            $accPort = $d->access_port ?: ($accType === 'SSH' ? 22 : ($accType === 'TELNET' ? 23 : ($accType === 'WEB' ? 80 : 5900)));
                            $siteName = $d->site ? $d->site->name : 'Sede Principal';
                        @endphp
                        <tr class="hover:bg-obsidian-panel/40 transition text-xs">
                            <!-- SLOT / ID -->
                            <td class="px-2.5 py-1.5 font-bold text-obsidian-cyan whitespace-nowrap text-[10px]">
                                #{{ $d->device_number ?: $d->id }}
                            </td>

                            <!-- SEDE ASIGNADA -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                @if($d->site)
                                    <a href="{{ route('admin.devices.index', ['site_id' => $d->site->id]) }}" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-purple-950/70 border border-purple-500/30 text-purple-300 hover:bg-purple-900 transition text-[9.5px] font-semibold" title="Filtrar por esta sede">
                                        <span class="material-symbols-outlined text-[11px]">domain</span>
                                        <span class="truncate max-w-[110px]">{{ $d->site->name }}</span>
                                    </a>
                                @else
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-muted text-[9.5px]">
                                        Principal
                                    </span>
                                @endif
                            </td>

                            <!-- DISPOSITIVO & FABRICANTE -->
                            <td class="px-2.5 py-1.5">
                                <div class="flex items-center gap-1.5 min-w-[120px]">
                                    <span class="w-2 h-2 rounded-full shrink-0 {{ $isUp ? 'bg-emerald-400 glow-green' : 'bg-red-500' }}" title="{{ $isUp ? 'Online (ICMP OK)' : 'Offline' }}"></span>
                                    <div>
                                        <div class="font-sans font-bold text-white text-[11.5px] truncate max-w-[140px]" title="{{ $d->name }}">
                                            {{ $d->name }}
                                        </div>
                                        @if($d->vendor_data)
                                            <span class="text-[8.5px] text-obsidian-muted font-mono block truncate max-w-[130px]">{{ $d->vendor_data }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- IP & MAC -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <div class="font-semibold text-white text-[11px]">{{ $d->ip }}</div>
                                <div class="text-[9px] text-obsidian-muted">MAC: {{ $d->mac ?: '--' }}</div>
                            </td>

                            <!-- MODELO & SERIAL & PUERTOS -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                @if($d->model)
                                    <span class="px-1 py-0.2 rounded text-[9px] font-bold uppercase bg-cyan-950/70 border border-cyan-500/30 text-cyan-300">
                                        {{ $d->model }}
                                    </span>
                                @else
                                    <span class="text-obsidian-muted text-[9px]">--</span>
                                @endif
                                @if($d->serial)
                                    <div class="text-[8.5px] text-obsidian-muted font-mono mt-0.5">S/N: {{ $d->serial }}</div>
                                @endif
                            </td>

                            <!-- PROTOCOLO DE ACCESO / CONEXIÓN DIRECTA -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap text-center">
                                @if($accType === 'SSH')
                                    <div class="inline-flex items-center gap-1">
                                        <button type="button" 
                                                onclick="openSshTerminal('{{ $d->ip }}', {{ $accPort }}, '{{ addslashes($d->name) }}', '{{ addslashes($siteName) }}')" 
                                                class="px-2 py-0.5 rounded bg-emerald-950/90 border border-emerald-500/50 text-emerald-300 hover:bg-emerald-500 hover:text-black transition text-[9px] font-bold inline-flex items-center gap-1 cursor-pointer shadow-xs" 
                                                title="Consola SSH Web ({{ $d->ip }}:{{ $accPort }})">
                                            <span class="material-symbols-outlined text-[10px]">terminal</span>
                                            <span>SSH:{{ $accPort }}</span>
                                        </button>
                                        @if($d->has_ssh_credentials)
                                            <span class="text-[11px] text-emerald-400 cursor-help" title="Credenciales SSH cifradas listas para respaldos GitOps">🔑</span>
                                        @endif
                                    </div>
                                @elseif($accType === 'TELNET')
                                    <button type="button" 
                                            onclick="openTelnetTerminal('{{ $d->ip }}', {{ $accPort }}, '{{ addslashes($d->name) }}', '{{ addslashes($siteName) }}')" 
                                            class="px-2 py-0.5 rounded bg-cyan-950/90 border border-cyan-500/50 text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[9px] font-bold inline-flex items-center gap-1 cursor-pointer shadow-xs" 
                                            title="Consola Telnet ({{ $d->ip }}:{{ $accPort }})">
                                        <span class="material-symbols-outlined text-[10px]">terminal</span>
                                        <span>TEL:{{ $accPort }}</span>
                                    </button>
                                @elseif($accType === 'WEB')
                                    <a href="http://{{ $d->ip }}:{{ $accPort }}" target="_blank" 
                                       class="px-2 py-0.5 rounded bg-blue-950/90 border border-blue-500/50 text-blue-300 hover:bg-blue-500 hover:text-black transition text-[9px] font-bold inline-flex items-center gap-1 shadow-xs" 
                                       title="Interfaz Web ({{ $d->ip }}:{{ $accPort }})">
                                        <span class="material-symbols-outlined text-[10px]">open_in_browser</span>
                                        <span>WEB:{{ $accPort }}</span>
                                    </a>
                                @elseif($accType === 'VNC')
                                    <button type="button" 
                                            onclick="openVncViewer('{{ $d->ip }}', '{{ addslashes($d->name) }}', '{{ addslashes($siteName) }}')" 
                                            class="px-2 py-0.5 rounded bg-purple-950/90 border border-purple-500/50 text-purple-300 hover:bg-purple-500 hover:text-white transition text-[9px] font-bold inline-flex items-center gap-1 cursor-pointer shadow-xs" 
                                            title="Conectar Escritorio Remoto VNC ({{ $d->ip }}:{{ $accPort }})">
                                        <span class="material-symbols-outlined text-[10px]">desktop_windows</span>
                                        <span>VNC:{{ $accPort }}</span>
                                    </button>
                                @else
                                    <span class="px-1.5 py-0.5 rounded text-[8.5px] bg-obsidian-panel border border-obsidian-border text-obsidian-muted inline-flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-[10px]">power_off</span>
                                        <span>SIN ACCESO</span>
                                    </span>
                                @endif
                            </td>

                            <!-- ESTADO DE MONITOREO -->
                            <td class="px-2.5 py-1.5 text-center whitespace-nowrap">
                                @if(auth()->user()->isAdmin() && !$isClusterSlave)
                                    <form action="{{ route('admin.devices.toggle', $d->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9.5px] font-bold transition {{ $d->is_active ? 'bg-emerald-950/70 text-emerald-400 border border-emerald-500/40' : 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border' }}" title="Click para alternar estado">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $d->is_active ? 'bg-emerald-400' : 'bg-obsidian-muted' }}"></span>
                                            {{ $d->is_active ? 'Activo' : 'Off' }}
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9.5px] font-bold {{ $d->is_active ? 'bg-emerald-950/70 text-emerald-400 border border-emerald-500/40' : 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $d->is_active ? 'bg-emerald-400' : 'bg-obsidian-muted' }}"></span>
                                        {{ $d->is_active ? 'Activo' : 'Off' }}
                                    </span>
                                @endif
                            </td>

                            <!-- ACCIONES (GESTIÓN DE DISPOSITIVO) -->
                            <td class="px-2.5 py-1.5 text-right space-x-1 whitespace-nowrap">

                                <!-- BOTÓN: FICHA TÉCNICA DETALLADA (ICONO COMPACTO) -->
                                <button type="button" 
                                        onclick="openDeviceDetailsModal({{ json_encode($d) }}, '{{ addslashes($siteName) }}')" 
                                        class="p-1 rounded bg-obsidian-panel border border-obsidian-cyan/40 text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition cursor-pointer inline-flex items-center" 
                                        title="Ver Ficha Técnica Completa">
                                    <span class="material-symbols-outlined text-[13px]">visibility</span>
                                </button>

                                <!-- BOTÓN: HISTÓRICO DE LATENCIA Y UPTIME (ICONO COMPACTO) -->
                                <button type="button" 
                                        onclick="openDeviceHistoryModal({{ $d->id }}, '{{ addslashes($d->name) }}', '{{ $d->ip }}', '{{ $d->device_number ?: $d->id }}')" 
                                        class="p-1 rounded bg-obsidian-panel border border-obsidian-purple/40 text-obsidian-purple hover:bg-obsidian-purple hover:text-white transition cursor-pointer shadow-xs inline-flex items-center" 
                                        title="Ver Histórico de Conexión y Latencia">
                                    <span class="material-symbols-outlined text-[13px]">show_chart</span>
                                </button>

                                @if(auth()->user()->isAdmin())
                                    @if($isClusterSlave)
                                        <span class="px-1 py-0.5 rounded bg-gray-900 border border-gray-800 text-gray-500 text-[8.5px] font-mono inline-flex items-center gap-0.5" title="Modificaciones restringidas al Servidor Master">
                                            <span class="material-symbols-outlined text-[10px]">lock</span>
                                        </span>
                                    @else
                                        <!-- BOTÓN: EDITAR DISPOSITIVO -->
                                        <button type="button" 
                                                onclick="openDeviceEditModal({{ json_encode($d) }})" 
                                                class="p-1 rounded bg-obsidian-panel border border-amber-500/40 text-amber-300 hover:bg-amber-500 hover:text-black transition cursor-pointer inline-flex items-center" 
                                                title="Editar Parámetros Técnicos">
                                            <span class="material-symbols-outlined text-[13px]">edit</span>
                                        </button>

                                        <!-- BOTÓN: ELIMINAR DISPOSITIVO -->
                                        <form action="{{ route('admin.devices.destroy', $d->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Está seguro de eliminar el dispositivo [{{ addslashes($d->name) }}] ({{ $d->ip }})? Esta acción se sincronizará inmediatamente en las sedes.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 rounded bg-obsidian-panel border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition inline-flex items-center" title="Eliminar Dispositivo">
                                                <span class="material-symbols-outlined text-[13px]">delete</span>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-6 text-center text-obsidian-muted font-mono text-xs">
                                No se encontraron dispositivos para los criterios seleccionados.
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
                    Mostrando <span class="text-white font-bold">{{ $devices->firstItem() }}</span> a <span class="text-white font-bold">{{ $devices->lastItem() }}</span> de <span class="text-cyan-400 font-bold">{{ $devices->total() }}</span> dispositivos (15 por página)
                </div>
                <div class="flex items-center gap-1">
                    {{-- Anterior --}}
                    @if($devices->onFirstPage())
                        <span class="px-2 py-0.5 rounded bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[10.5px]">
                            &laquo; Anterior
                        </span>
                    @else
                        <a href="{{ $devices->previousPageUrl() }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[10.5px]">
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
                            <a href="{{ $url }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white transition text-[10.5px]">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach

                    {{-- Siguiente --}}
                    @if($devices->hasMorePages())
                        <a href="{{ $devices->nextPageUrl() }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[10.5px]">
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
<!-- MODAL 1: FICHA TÉCNICA DETALLADA (OPERADORES Y ADMINISTRADORES)          -->
<!-- ========================================================================= -->
<div id="modal-device-details" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel w-full max-w-2xl rounded-2xl border border-cyan-500/50 flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95 animate-in fade-in zoom-in-95 duration-200">
        <!-- CABECERA -->
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between bg-[#061527]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/80 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shadow-md">
                    <span class="material-symbols-outlined text-2xl">router</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white font-mono flex items-center gap-2">
                        <span id="det-device-name">--</span>
                        <span id="det-device-slot" class="px-2 py-0.5 rounded text-[10px] bg-obsidian-panel border border-obsidian-border text-obsidian-cyan font-mono">--</span>
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs text-purple-400">domain</span>
                        <span id="det-device-site" class="text-purple-300 font-semibold">--</span>
                        <span class="text-obsidian-border">|</span>
                        <span id="det-device-vendor">--</span>
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeDeviceDetailsModal()" class="text-obsidian-muted hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- CONTENIDO -->
        <div class="p-6 space-y-5 overflow-y-auto max-h-[75vh]">
            <!-- DATOS BÁSICOS EN GRID -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 font-mono text-xs">
                <div class="bg-obsidian-panel/80 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1">
                    <span class="text-obsidian-muted text-[10px] uppercase font-bold tracking-wider">Dirección IP</span>
                    <p class="text-white font-semibold text-sm" id="det-device-ip">--</p>
                </div>
                <div class="bg-obsidian-panel/80 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1">
                    <span class="text-obsidian-muted text-[10px] uppercase font-bold tracking-wider">Dirección MAC</span>
                    <p class="text-obsidian-cyan font-semibold" id="det-device-mac">--</p>
                </div>
                <div class="bg-obsidian-panel/80 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1">
                    <span class="text-obsidian-muted text-[10px] uppercase font-bold tracking-wider">Modelo de Hardware</span>
                    <p class="text-cyan-300 font-semibold" id="det-device-model">--</p>
                </div>
                <div class="bg-obsidian-panel/80 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1">
                    <span class="text-obsidian-muted text-[10px] uppercase font-bold tracking-wider">Número de Serie (Serial)</span>
                    <p class="text-amber-300 font-semibold" id="det-device-serial">--</p>
                </div>
                <div class="bg-obsidian-panel/80 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1">
                    <span class="text-obsidian-muted text-[10px] uppercase font-bold tracking-wider">Cantidad / Tipo de Puertos</span>
                    <p class="text-white font-semibold" id="det-device-ports">--</p>
                </div>
                <div class="bg-obsidian-panel/80 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1">
                    <span class="text-obsidian-muted text-[10px] uppercase font-bold tracking-wider">Protocolo de Acceso Remoto</span>
                    <p class="text-emerald-400 font-semibold" id="det-device-access">--</p>
                </div>
            </div>

            <!-- BLOQUE: NOTAS TÉCNICAS Y MAPEO DE PUERTOS -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-mono font-bold text-cyan-400 flex items-center gap-1.5 uppercase tracking-wider">
                        <span class="material-symbols-outlined text-sm">description</span>
                        Notas Técnicas & Distribución de Puertos
                    </label>
                    <span class="text-[10px] font-mono text-obsidian-muted">Configuración Registrada</span>
                </div>
                <div id="det-device-notes" class="whitespace-pre-line font-mono text-xs text-cyan-200 bg-[#020b14]/90 p-4 rounded-xl border border-cyan-500/30 break-words leading-relaxed max-h-56 overflow-y-auto scrollbar-thin">
                    Sin notas técnicas registradas.
                </div>
            </div>
        </div>

        <!-- PIE DEL MODAL -->
        <div class="p-4 px-6 border-t border-obsidian-border bg-[#061527] flex items-center justify-between gap-3">
            <div id="det-device-connect-btn"></div>
            <button type="button" onclick="closeDeviceDetailsModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-white/10 transition">
                Cerrar
            </button>
        </div>
    </div>
</div>

@if(auth()->user()->isAdmin())
<!-- ========================================================================= -->
<!-- MODAL 2: CREACIÓN DE DISPOSITIVO (SOLO ADMINISTRADORES)                   -->
<!-- ========================================================================= -->
<div id="modal-device-create" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel w-full max-w-2xl rounded-2xl border border-cyan-500/50 flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95 animate-in fade-in zoom-in-95 duration-200">
        <!-- CABECERA -->
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between bg-[#061527]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/80 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shadow-md">
                    <span class="material-symbols-outlined text-2xl">add_circle</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white font-mono">
                        Agregar Nuevo Dispositivo
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono">Registro e integración con supervisión en tiempo real</p>
                </div>
            </div>
            <button type="button" onclick="closeDeviceCreateModal()" class="text-obsidian-muted hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- FORMULARIO -->
        <form action="{{ route('admin.devices.store') }}" method="POST" class="flex flex-col flex-1 overflow-hidden">
            @csrf
            <div class="p-6 space-y-4 overflow-y-auto max-h-[70vh]">
                <!-- SELECCIÓN DE SEDE -->
                <div class="bg-purple-950/30 p-3 rounded-xl border border-purple-500/30">
                    <label class="block text-xs font-mono text-purple-300 mb-1 font-semibold uppercase flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">domain</span>
                        Asignar a Sede Regional *
                    </label>
                    <select name="monitored_site_id" required class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-purple-500/50 text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                        <option value="">-- Seleccione una Sede --</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}" {{ $siteFilter == $s->id ? 'selected' : '' }}>
                                {{ $s->name }} (IP: {{ $s->ip ?: 'Sin Enlace' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- NOMBRE -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Nombre del Dispositivo *</label>
                        <input type="text" name="name" required placeholder="Ej: SWITCH CORE PRINCIPAL / ATU 01" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- IP -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Dirección IP *</label>
                        <input type="text" name="ip" required placeholder="Ej: 10.20.23.10" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- MAC -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Dirección MAC</label>
                        <input type="text" name="mac" placeholder="00:00:00:00:00:00" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- FABRICANTE -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Fabricante / Vendor</label>
                        <input type="text" name="vendor_data" placeholder="Ej: Cisco Systems, Inc" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- MODELO -->
                    <div>
                        <label class="block text-xs font-mono text-cyan-400 mb-1 font-semibold uppercase">Modelo de Hardware</label>
                        <input type="text" name="model" placeholder="Ej: Catalyst 2960-X / PowerEdge" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-200 text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- SERIAL -->
                    <div>
                        <label class="block text-xs font-mono text-amber-400 mb-1 font-semibold uppercase">Número de Serie (Serial)</label>
                        <input type="text" name="serial" placeholder="Ej: FOC1234X5YZ" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-amber-500/40 text-amber-200 text-xs font-mono focus:border-amber-400 focus:outline-hidden">
                    </div>

                    <!-- PUERTOS -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Especificación de Puertos</label>
                        <input type="text" name="ports" placeholder="Ej: SW: 48 puertos Gigabit" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- TIPO Y PUERTO DE ACCESO -->
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Acceso</label>
                            <select name="access_type" id="create-access-type" onchange="handleCreateAccessChange(this.value)" class="w-full px-2 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                                <option value="SSH">SSH</option>
                                <option value="TELNET">TELNET</option>
                                <option value="WEB">WEB</option>
                                <option value="VNC">VNC</option>
                                <option value="SIN SOPORTE">SIN SOPORTE</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Puerto</label>
                            <input type="number" name="access_port" id="create-access-port" value="22" min="1" max="65535" class="w-full px-2 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                        </div>
                    </div>

                    <!-- CREDENCIALES SSH DE GESTIÓN Y RESPALDO (GITOPS) -->
                    <div class="col-span-1 sm:col-span-2 p-3.5 rounded-xl bg-[#030d1a] border border-emerald-500/30 space-y-2.5">
                        <div class="flex items-center justify-between border-b border-emerald-500/20 pb-2">
                            <div class="flex items-center gap-1.5 text-xs font-mono font-bold text-emerald-400">
                                <span class="material-symbols-outlined text-base">key</span>
                                <span>Credenciales SSH / Respaldo Automático (Cifrado AES-256)</span>
                            </div>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-300 border border-emerald-500/30">GitOps Ready</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <div>
                                <label class="block text-[11px] font-mono text-slate-300 mb-1">Usuario SSH</label>
                                <input type="text" name="ssh_username" placeholder="Ej: admin / cisco" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-hidden">
                            </div>
                            <div>
                                <label class="block text-[11px] font-mono text-slate-300 mb-1">Contraseña SSH</label>
                                <div class="relative">
                                    <input type="password" name="ssh_password" placeholder="Contraseña de acceso" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-hidden pr-8">
                                    <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-2 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-mono text-slate-300 mb-1">Enable Secret (Cisco)</label>
                                <div class="relative">
                                    <input type="password" name="ssh_enable_secret" placeholder="Clave privilegiada (opcional)" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-hidden pr-8">
                                    <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-2 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- GUÍA TÉCNICA CISCO IOS: HABILITACIÓN DE SSH (PUERTO 22) -->
                        <div class="rounded-xl bg-[#01060e] border border-cyan-500/25 overflow-hidden">
                            <div class="px-3 py-1.5 bg-cyan-950/40 border-b border-cyan-500/20 flex items-center justify-between">
                                <span class="text-[11px] font-mono text-cyan-300 font-semibold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm text-cyan-400">terminal</span>
                                    <span>Comandos Cisco IOS para habilitar SSH (Puerto 22)</span>
                                </span>
                                <button type="button" onclick="copyCiscoSshCommands('cisco-ssh-create-code', 'copy-btn-create-text')" class="text-[10px] font-mono text-cyan-300 hover:text-white px-2 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/30 flex items-center gap-1 transition cursor-pointer">
                                    <span class="material-symbols-outlined text-xs">content_copy</span>
                                    <span id="copy-btn-create-text">Copiar</span>
                                </button>
                            </div>
                            <div class="p-2.5 bg-black/50">
                                <pre id="cisco-ssh-create-code" class="text-[10.5px] font-mono text-cyan-200/90 leading-relaxed overflow-x-auto select-all"><code>configure terminal
! 1. Definir nombre y dominio (obligatorios para generar llaves)
hostname SW-VALLE-SECO
ip domain-name empresa.gob.ve

! 2. Generar el par de llaves criptográficas RSA
crypto key generate rsa modulus 2048

! 3. Forzar versión 2 de SSH (más segura)
ip ssh version 2

! 4. Habilitar SSH en las líneas virtuales de acceso
line vty 0 15
 transport input ssh telnet
 login local
exit</code></pre>
                            </div>
                        </div>

                        <p class="text-[10px] font-mono text-slate-400 leading-tight">
                            🔐 Las claves se almacenan cifradas en base de datos. Se utilizan exclusivamente para la extracción de 'show running-config' y auditoría de cambios.
                        </p>
                    </div>
                </div>

                <!-- NOTAS TÉCNICAS (MULTILÍNEA) -->
                <div>
                    <label class="block text-xs font-mono text-cyan-400 mb-1 font-semibold uppercase flex items-center justify-between">
                        <span>Notas Técnicas & Conexión de Puertos (Multilínea)</span>
                        <span class="text-[10px] text-obsidian-muted normal-case font-normal">Saltos de línea permitidos</span>
                    </label>
                    <textarea name="notes" rows="4" placeholder="Detalles de cascadas, puertos de enlace, conexiones directas..." class="w-full px-3.5 py-2.5 rounded-xl bg-[#020b14] border border-cyan-500/40 text-cyan-200 text-xs font-mono focus:border-cyan-400 focus:outline-hidden scrollbar-thin leading-relaxed"></textarea>
                </div>

                <!-- ACTIVO -->
                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" name="is_active" id="create-is-active" value="1" checked class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan focus:ring-0">
                    <label for="create-is-active" class="text-xs font-mono text-white cursor-pointer select-none">
                        Monitoreo activo por ping ICMP continuo
                    </label>
                </div>
            </div>

            <!-- PIE -->
            <div class="p-4 px-6 border-t border-obsidian-border bg-[#061527] flex items-center justify-end gap-3">
                <button type="button" onclick="closeDeviceCreateModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-white/10 transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20">
                    <span class="material-symbols-outlined text-base">save</span>
                    Registrar Dispositivo
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 3: EDICIÓN DE DISPOSITIVO (SOLO ADMINISTRADORES)                     -->
<!-- ========================================================================= -->
<div id="modal-device-edit" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel w-full max-w-2xl rounded-2xl border border-amber-500/50 flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95 animate-in fade-in zoom-in-95 duration-200">
        <!-- CABECERA -->
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between bg-[#1f1406]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-950/80 border border-amber-500/40 flex items-center justify-center text-amber-300 shadow-md">
                    <span class="material-symbols-outlined text-2xl">edit_note</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white font-mono flex items-center gap-2">
                        <span>Editar Dispositivo de Red</span>
                        <span id="edit-device-slot" class="px-2 py-0.5 rounded text-[10px] bg-amber-950 border border-amber-500/40 text-amber-300 font-mono">--</span>
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono">Modificación de especificaciones y sincronización directa</p>
                </div>
            </div>
            <button type="button" onclick="closeDeviceEditModal()" class="text-obsidian-muted hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- FORMULARIO -->
        <form id="form-device-edit" method="POST" action="" class="flex flex-col flex-1 overflow-hidden">
            @csrf
            @method('PUT')
            <div class="p-6 space-y-4 overflow-y-auto max-h-[70vh]">
                <!-- REASIGNAR SEDE -->
                <div class="bg-purple-950/30 p-3 rounded-xl border border-purple-500/30">
                    <label class="block text-xs font-mono text-purple-300 mb-1 font-semibold uppercase flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">domain</span>
                        Sede Asignada
                    </label>
                    <select name="monitored_site_id" id="edit-site-id" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-purple-500/50 text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- NOMBRE -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Nombre del Dispositivo *</label>
                        <input type="text" name="name" id="edit-name" required class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- IP -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Dirección IP *</label>
                        <input type="text" name="ip" id="edit-ip" required class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- MAC -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Dirección MAC</label>
                        <input type="text" name="mac" id="edit-mac" placeholder="00:00:00:00:00:00" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- FABRICANTE -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Fabricante / Vendor</label>
                        <input type="text" name="vendor_data" id="edit-vendor-data" placeholder="Ej: Cisco Systems, Inc" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- MODELO -->
                    <div>
                        <label class="block text-xs font-mono text-cyan-400 mb-1 font-semibold uppercase">Modelo de Hardware</label>
                        <input type="text" name="model" id="edit-model" placeholder="Ej: Catalyst 2960 / Catalyst XXXXX" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-200 text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- SERIAL -->
                    <div>
                        <label class="block text-xs font-mono text-amber-400 mb-1 font-semibold uppercase">Número de Serie (Serial)</label>
                        <input type="text" name="serial" id="edit-serial" placeholder="Ej: XXXXXX-XXXX" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-amber-500/40 text-amber-200 text-xs font-mono focus:border-amber-400 focus:outline-hidden">
                    </div>

                    <!-- PUERTOS -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Especificación de Puertos</label>
                        <input type="text" name="ports" id="edit-ports" placeholder="Ej: SW: 48 puertos" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- TIPO Y PUERTO DE ACCESO -->
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Acceso</label>
                            <select name="access_type" id="edit-access-type" onchange="handleEditAccessChange(this.value)" class="w-full px-2 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                                <option value="SSH">SSH</option>
                                <option value="TELNET">TELNET</option>
                                <option value="WEB">WEB</option>
                                <option value="VNC">VNC</option>
                                <option value="SIN SOPORTE">SIN SOPORTE</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Puerto</label>
                            <input type="number" name="access_port" id="edit-access-port" placeholder="22" min="1" max="65535" class="w-full px-2 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                        </div>
                    </div>

                    <!-- CREDENCIALES SSH DE GESTIÓN Y RESPALDO (GITOPS) -->
                    <div class="col-span-1 sm:col-span-2 p-3.5 rounded-xl bg-[#030d1a] border border-emerald-500/30 space-y-2.5">
                        <div class="flex items-center justify-between border-b border-emerald-500/20 pb-2">
                            <div class="flex items-center gap-1.5 text-xs font-mono font-bold text-emerald-400">
                                <span class="material-symbols-outlined text-base">key</span>
                                <span>Credenciales SSH / Respaldo Automático (Cifrado AES-256)</span>
                            </div>
                            <div id="edit-ssh-cred-status">
                                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-500/20 text-slate-400 border border-slate-500/30">Sin Credenciales Guardadas</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <div>
                                <label class="block text-[11px] font-mono text-slate-300 mb-1">Usuario SSH</label>
                                <input type="text" name="ssh_username" id="edit-ssh-username" placeholder="Ej: admin / cisco" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-hidden">
                            </div>
                            <div>
                                <label class="block text-[11px] font-mono text-slate-300 mb-1">Nueva Contraseña SSH</label>
                                <div class="relative">
                                    <input type="password" name="ssh_password" id="edit-ssh-password" placeholder="En blanco = mantener actual" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-hidden pr-8">
                                    <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-2 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-mono text-slate-300 mb-1">Nuevo Enable Secret (Cisco)</label>
                                <div class="relative">
                                    <input type="password" name="ssh_enable_secret" id="edit-ssh-enable-secret" placeholder="En blanco = mantener actual" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-hidden pr-8">
                                    <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-2 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- GUÍA TÉCNICA CISCO IOS: HABILITACIÓN DE SSH (PUERTO 22) -->
                        <div class="rounded-xl bg-[#01060e] border border-cyan-500/25 overflow-hidden">
                            <div class="px-3 py-1.5 bg-cyan-950/40 border-b border-cyan-500/20 flex items-center justify-between">
                                <span class="text-[11px] font-mono text-cyan-300 font-semibold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm text-cyan-400">terminal</span>
                                    <span>Comandos Cisco IOS para habilitar SSH (Puerto 22)</span>
                                </span>
                                <button type="button" onclick="copyCiscoSshCommands('cisco-ssh-edit-code', 'copy-btn-edit-text')" class="text-[10px] font-mono text-cyan-300 hover:text-white px-2 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/30 flex items-center gap-1 transition cursor-pointer">
                                    <span class="material-symbols-outlined text-xs">content_copy</span>
                                    <span id="copy-btn-edit-text">Copiar</span>
                                </button>
                            </div>
                            <div class="p-2.5 bg-black/50">
                                <pre id="cisco-ssh-edit-code" class="text-[10.5px] font-mono text-cyan-200/90 leading-relaxed overflow-x-auto select-all"><code>configure terminal
! 1. Definir nombre y dominio (obligatorios para generar llaves)
hostname SW-VALLE-SECO
ip domain-name empresa.gob.ve

! 2. Generar el par de llaves criptográficas RSA
crypto key generate rsa modulus 2048

! 3. Forzar versión 2 de SSH (más segura)
ip ssh version 2

! 4. Habilitar SSH en las líneas virtuales de acceso
line vty 0 15
 transport input ssh telnet
 login local
exit</code></pre>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <p class="text-[10px] font-mono text-slate-400 leading-tight">
                                🔐 Cifrado AES-256. Deje los campos de contraseña en blanco si no desea modificarlas.
                            </p>
                            <label class="inline-flex items-center gap-1.5 text-[10.5px] font-mono text-rose-400 cursor-pointer select-none">
                                <input type="checkbox" name="clear_ssh_credentials" value="1" class="rounded bg-obsidian-panel border-rose-500/40 text-rose-500 focus:ring-0">
                                <span>Eliminar credenciales</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- NOTAS TÉCNICAS (MULTILÍNEA) -->
                <div>
                    <label class="block text-xs font-mono text-cyan-400 mb-1 font-semibold uppercase flex items-center justify-between">
                        <span>Notas Técnicas & Conexión de Puertos (Multilínea)</span>
                        <span class="text-[10px] text-obsidian-muted normal-case font-normal">Saltos de línea permitidos</span>
                    </label>
                    <textarea name="notes" id="edit-notes" rows="5" placeholder="Detalles técnicos..." class="w-full px-3.5 py-2.5 rounded-xl bg-[#020b14] border border-cyan-500/40 text-cyan-200 text-xs font-mono focus:border-cyan-400 focus:outline-hidden scrollbar-thin leading-relaxed"></textarea>
                </div>

                <!-- ACTIVO -->
                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" name="is_active" id="edit-is-active" value="1" class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan focus:ring-0">
                    <label for="edit-is-active" class="text-xs font-mono text-white cursor-pointer select-none">
                        Monitoreo activo por ping ICMP continuo
                    </label>
                </div>
            </div>

            <!-- PIE -->
            <div class="p-4 px-6 border-t border-obsidian-border bg-[#1f1406] flex items-center justify-end gap-3">
                <button type="button" onclick="closeDeviceEditModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-white/10 transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-black font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-lg shadow-amber-500/20">
                    <span class="material-symbols-outlined text-base">save</span>
                    Guardar Cambios & Sincronizar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 4: CREACIÓN RÁPIDA DE SEDE (SOLO ADMINISTRADORES)                   -->
<!-- ========================================================================= -->
<div id="modal-create-site" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-lg w-full rounded-2xl p-6 border border-purple-500/40 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto bg-[#040d1a]/95">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <h3 class="text-sm font-bold text-white uppercase font-mono flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-400 text-base">domain_add</span>
                Agregar Nueva Sede Regional
            </h3>
            <button type="button" onclick="closeCreateSiteModal()" class="text-obsidian-muted hover:text-white text-xl p-1 rounded hover:bg-obsidian-panel leading-none">&times;</button>
        </div>
        <form action="{{ route('admin.sites.store') }}" method="POST" class="space-y-4 font-mono text-xs">
            @csrf
            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Nombre de la Sede *</label>
                <input type="text" name="name" required placeholder="ej. CIAU Morón / CIAU Pto Cabello" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">IP Gateway / Enlace:</label>
                    <input type="text" name="ip" placeholder="ej. 10.20.106.193" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Dirección Física:</label>
                    <input type="text" name="address" placeholder="Av. Principal, Edif..." class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Teléfono Principal:</label>
                    <input type="text" name="phone_1" placeholder="ej. 0242-3600000" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Teléfono Secundario:</label>
                    <input type="text" name="phone_2" placeholder="ej. 0414-0000000" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" id="create-site-active" name="is_active" value="1" checked class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan"/>
                <label for="create-site-active" class="text-obsidian-muted cursor-pointer select-none">Habilitar monitoreo de sede inmediatamente</label>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-obsidian-border">
                <button type="button" onclick="closeCreateSiteModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">save</span>
                    Guardar Sede
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- ========================================================================= -->
<!-- MODAL 5: HISTÓRICO & TELEMETRÍA (OPERADORES Y ADMINISTRADORES)            -->
<!-- ========================================================================= -->
<div id="modal-device-history" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel w-full max-w-4xl rounded-2xl border border-obsidian-border flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95 animate-in fade-in zoom-in-95 duration-200">
        <!-- CABECERA -->
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between bg-[#061527]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-950/80 border border-purple-500/40 flex items-center justify-center text-purple-300 shadow-md">
                    <span class="material-symbols-outlined text-2xl">show_chart</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white font-mono flex items-center gap-2">
                        <span id="hist-device-name">--</span>
                        <span id="hist-device-slot" class="px-2 py-0.5 rounded text-[10px] bg-obsidian-panel border border-obsidian-border text-purple-300 font-mono">--</span>
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono" id="hist-device-ip">--</p>
                </div>
            </div>
            <button type="button" onclick="closeDeviceHistoryModal()" class="text-obsidian-muted hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- CONTENIDO -->
        <div class="p-6 space-y-6 overflow-y-auto max-h-[75vh]">
            <!-- SELECTOR DE RANGO Y STATS -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-1.5 p-1 bg-obsidian-panel rounded-lg border border-obsidian-border font-mono text-xs">
                    <button type="button" onclick="loadDeviceHistory('6h')" class="hist-range-btn px-2.5 py-1 rounded transition font-semibold" data-range="6h">6 Horas</button>
                    <button type="button" onclick="loadDeviceHistory('24h')" class="hist-range-btn px-2.5 py-1 rounded transition font-semibold bg-obsidian-cyan text-black" data-range="24h">24 Horas</button>
                    <button type="button" onclick="loadDeviceHistory('7d')" class="hist-range-btn px-2.5 py-1 rounded transition font-semibold text-obsidian-muted hover:text-white" data-range="7d">7 Días</button>
                    <button type="button" onclick="loadDeviceHistory('30d')" class="hist-range-btn px-2.5 py-1 rounded transition font-semibold text-obsidian-muted hover:text-white" data-range="30d">30 Días</button>
                </div>
                <div class="flex items-center gap-4 font-mono text-xs">
                    <div class="text-right">
                        <span class="text-obsidian-muted text-[10px] uppercase">Disponibilidad</span>
                        <div class="text-emerald-400 font-bold text-sm" id="hist-stat-uptime">--%</div>
                    </div>
                    <div class="text-right">
                        <span class="text-obsidian-muted text-[10px] uppercase">Latencia Prom.</span>
                        <div class="text-white font-bold text-sm" id="hist-stat-avg">-- ms</div>
                    </div>
                </div>
            </div>

            <!-- GRÁFICO CHART.JS -->
            <div class="glass-card p-4 rounded-xl border border-obsidian-border/80 relative min-h-[260px] flex items-center justify-center">
                <canvas id="deviceHistoryChart" class="w-full h-64"></canvas>
                <div id="chart-loading-overlay" class="absolute inset-0 flex items-center justify-center bg-obsidian-bg/80 backdrop-blur-xs rounded-xl hidden">
                    <div class="flex items-center gap-2 text-obsidian-cyan font-mono text-xs">
                        <span class="material-symbols-outlined text-lg animate-spin">progress_activity</span>
                        <span>Cargando datos históricos...</span>
                    </div>
                </div>
            </div>

            <!-- REGISTRO DE INCIDENTES -->
            <div class="space-y-2">
                <h4 class="text-xs font-mono font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-red-400">warning</span>
                    Registro de Incidentes y Caídas
                </h4>
                <div class="overflow-x-auto rounded-xl border border-obsidian-border/80">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-obsidian-panel border-b border-obsidian-border text-obsidian-muted text-[10px] uppercase">
                            <tr>
                                <th class="px-4 py-2.5">Fecha y Hora</th>
                                <th class="px-4 py-2.5">Tiempo Transcurrido</th>
                                <th class="px-4 py-2.5">Diagnóstico Reportado</th>
                            </tr>
                        </thead>
                        <tbody id="hist-incidents-tbody" class="divide-y divide-obsidian-border/60">
                            <tr>
                                <td colspan="3" class="px-4 py-4 text-center text-obsidian-muted">
                                    Sin incidentes registrados en este período.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentDeviceId = null;
    let currentDeviceRange = '24h';
    let deviceChart = null;

    // --- ACCESO REMOTO HELPERS ---
    function openSshTerminal(ip, port = 22, name = '', site = '') {
        const url = `/admin/ssh/terminal?ip=${encodeURIComponent(ip)}&port=${port}&name=${encodeURIComponent(name)}&site=${encodeURIComponent(site)}`;
        const w = 1100;
        const h = 700;
        const left = (screen.width/2)-(w/2);
        const top = (screen.height/2)-(h/2);
        window.open(url, `ssh_${ip.replace(/\./g, '_')}`, `width=${w},height=${h},top=${top},left=${left},resizable=yes,scrollbars=no,status=no`);
    }

    async function openTelnetTerminal(ip, port = 23, name = '', site = '') {
        try {
            const res = await fetch("{{ route('admin.telnet.session') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ ip, port, name, site })
            });
            const data = await res.json();
            if (data.success && data.viewer_url) {
                const w = 1100;
                const h = 700;
                const left = (screen.width/2)-(w/2);
                const top = (screen.height/2)-(h/2);
                window.open(data.viewer_url, `telnet_${ip.replace(/\./g, '_')}`, `width=${w},height=${h},top=${top},left=${left},resizable=yes,scrollbars=no,status=no`);
            } else {
                alert('No se pudo inicializar la sesión Telnet: ' + (data.message || 'Error desconocido'));
            }
        } catch (err) {
            console.error('Error Telnet:', err);
            alert('Error de comunicación con el proxy Telnet.');
        }
    }

    async function openVncViewer(ip, name = '', site = '') {
        try {
            const res = await fetch("{{ route('admin.vnc.session') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ ip, name, site })
            });
            const data = await res.json();
            if (data.success && data.viewer_url) {
                const w = 1280;
                const h = 800;
                const left = (screen.width/2)-(w/2);
                const top = (screen.height/2)-(h/2);
                window.open(data.viewer_url, `vnc_${ip.replace(/\./g, '_')}`, `width=${w},height=${h},top=${top},left=${left},resizable=yes,scrollbars=no,status=no`);
            } else {
                alert('No se pudo inicializar la sesión VNC: ' + (data.message || 'Error desconocido'));
            }
        } catch (err) {
            console.error('Error VNC:', err);
            alert('Error de comunicación con el servicio VNC.');
        }
    }

    // --- AUTODETECT DE PUERTOS POR PROTOCOLO ---
    function handleCreateAccessChange(type) {
        const pInput = document.getElementById('create-access-port');
        if (!pInput) return;
        if (type === 'SSH') pInput.value = 22;
        else if (type === 'TELNET') pInput.value = 23;
        else if (type === 'WEB') pInput.value = 80;
        else if (type === 'VNC') pInput.value = 5900;
        else pInput.value = '';
    }

    function handleEditAccessChange(type) {
        const pInput = document.getElementById('edit-access-port');
        if (!pInput) return;
        if (type === 'SSH') pInput.value = 22;
        else if (type === 'TELNET') pInput.value = 23;
        else if (type === 'WEB') pInput.value = 80;
        else if (type === 'VNC') pInput.value = 5900;
        else pInput.value = '';
    }

    // --- MODAL DE DETALLES TÉCNICOS ---
    function openDeviceDetailsModal(dev, siteName = '') {
        document.getElementById('det-device-name').innerText = dev.name || 'Dispositivo';
        document.getElementById('det-device-slot').innerText = 'SLOT #' + (dev.device_number || dev.id || '--');
        document.getElementById('det-device-site').innerText = siteName || (dev.site ? dev.site.name : 'Sede Principal');
        document.getElementById('det-device-vendor').innerText = dev.vendor_data ? ('Fabricante: ' + dev.vendor_data) : 'Equipo de Infraestructura LAN';
        document.getElementById('det-device-ip').innerText = dev.ip || '--';
        document.getElementById('det-device-mac').innerText = dev.mac || 'No especificada';
        document.getElementById('det-device-model').innerText = dev.model || 'No especificado';
        document.getElementById('det-device-serial').innerText = dev.serial || 'No especificado';
        document.getElementById('det-device-ports').innerText = dev.ports || 'No especificado';
        
        const acc = (dev.access_type || 'SIN SOPORTE').toUpperCase();
        const port = dev.access_port || (acc === 'SSH' ? 22 : (acc === 'TELNET' ? 23 : (acc === 'WEB' ? 80 : 5900)));
        document.getElementById('det-device-access').innerText = acc !== 'SIN SOPORTE' ? (acc + ' (Puerto ' + port + ')') : 'SIN ACCESO REMOTO';

        const notesEl = document.getElementById('det-device-notes');
        if (dev.notes && dev.notes.trim().length > 0) {
            notesEl.innerText = dev.notes;
            notesEl.classList.remove('text-obsidian-muted', 'italic');
            notesEl.classList.add('text-cyan-200');
        } else {
            notesEl.innerText = 'Sin notas técnicas o asignaciones de puertos registradas.';
            notesEl.classList.add('text-obsidian-muted', 'italic');
            notesEl.classList.remove('text-cyan-200');
        }

        // Botón conectar en el footer del modal
        const connectContainer = document.getElementById('det-device-connect-btn');
        if (acc === 'SSH') {
            connectContainer.innerHTML = `
                <button type="button" onclick="openSshTerminal('${dev.ip}', ${port}, '${encodeURIComponent(dev.name || '')}', '${encodeURIComponent(siteName || '')}')" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-md cursor-pointer">
                    <span class="material-symbols-outlined text-sm">terminal</span>
                    Conectar SSH (${port})
                </button>
            `;
        } else if (acc === 'TELNET') {
            connectContainer.innerHTML = `
                <button type="button" onclick="openTelnetTerminal('${dev.ip}', ${port}, '${encodeURIComponent(dev.name || '')}', '${encodeURIComponent(siteName || '')}')" class="px-3 py-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-black font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-md cursor-pointer">
                    <span class="material-symbols-outlined text-sm">terminal</span>
                    Conectar Telnet (${port})
                </button>
            `;
        } else if (acc === 'WEB') {
            connectContainer.innerHTML = `
                <a href="http://${dev.ip}:${port}" target="_blank" class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-md">
                    <span class="material-symbols-outlined text-sm">open_in_browser</span>
                    Abrir Web (${port})
                </a>
            `;
        } else if (acc === 'VNC') {
            connectContainer.innerHTML = `
                <button type="button" onclick="openVncViewer('${dev.ip}', '${encodeURIComponent(dev.name || '')}', '${encodeURIComponent(siteName || '')}')" class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-md cursor-pointer">
                    <span class="material-symbols-outlined text-sm">desktop_windows</span>
                    Abrir VNC (${port})
                </button>
            `;
        } else {
            connectContainer.innerHTML = '';
        }

        const m = document.getElementById('modal-device-details');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeDeviceDetailsModal() {
        const m = document.getElementById('modal-device-details');
        m.classList.remove('flex');
        m.classList.add('hidden');
    }

    // --- MODAL DE CREACIÓN DE DISPOSITIVO ---
    function openDeviceCreateModal() {
        const m = document.getElementById('modal-device-create');
        if (m) {
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
    }

    function closeDeviceCreateModal() {
        const m = document.getElementById('modal-device-create');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    // --- MODAL DE EDICIÓN (SOLO ADMINISTRADOR) ---
    function openDeviceEditModal(dev) {
        document.getElementById('form-device-edit').action = '/admin/devices/' + dev.id;
        document.getElementById('edit-device-slot').innerText = 'SLOT #' + (dev.device_number || dev.id || '--');
        
        const siteSelect = document.getElementById('edit-site-id');
        if (siteSelect) {
            siteSelect.value = dev.monitored_site_id || '';
        }

        document.getElementById('edit-name').value = dev.name || '';
        document.getElementById('edit-ip').value = dev.ip || '';
        document.getElementById('edit-mac').value = dev.mac || '';
        document.getElementById('edit-vendor-data').value = dev.vendor_data || '';
        document.getElementById('edit-model').value = dev.model || '';
        document.getElementById('edit-serial').value = dev.serial || '';
        document.getElementById('edit-ports').value = dev.ports || '';
        
        const acc = (dev.access_type || 'SIN SOPORTE').toUpperCase();
        document.getElementById('edit-access-type').value = acc;
        document.getElementById('edit-access-port').value = dev.access_port || (acc === 'SSH' ? 22 : (acc === 'TELNET' ? 23 : (acc === 'WEB' ? 80 : (acc === 'VNC' ? 5900 : ''))));
        document.getElementById('edit-notes').value = dev.notes || '';
        document.getElementById('edit-is-active').checked = !!dev.is_active;

        document.getElementById('edit-ssh-username').value = dev.ssh_username || '';
        document.getElementById('edit-ssh-password').value = '';
        document.getElementById('edit-ssh-enable-secret').value = '';
        const credBadge = document.getElementById('edit-ssh-cred-status');
        if (credBadge) {
            if (dev.has_ssh_credentials) {
                credBadge.innerHTML = '<span class="inline-flex items-center gap-1 text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/40"><span class="material-symbols-outlined text-xs">verified_user</span> Credenciales Activas</span>';
            } else {
                credBadge.innerHTML = '<span class="inline-flex items-center gap-1 text-[10px] font-mono px-2 py-0.5 rounded bg-slate-500/20 text-slate-400 border border-slate-500/30">Sin Credenciales Guardadas</span>';
            }
        }

        const m = document.getElementById('modal-device-edit');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function togglePasswordVisibility(btn) {
        const input = btn.parentElement.querySelector('input');
        const icon = btn.querySelector('.material-symbols-outlined');
        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = 'visibility_off';
        } else {
            input.type = 'password';
            icon.textContent = 'visibility';
        }
    }

    function copyCiscoSshCommands(elementId = 'cisco-ssh-create-code', btnTextId = 'copy-btn-create-text') {
        const pre = document.getElementById(elementId);
        if (!pre) return;
        const text = pre.innerText;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                const btnText = document.getElementById(btnTextId);
                if (btnText) {
                    btnText.textContent = '¡Copiado!';
                    setTimeout(() => { btnText.textContent = 'Copiar'; }, 2000);
                }
            }).catch(() => {
                fallbackCopyText(text, btnTextId);
            });
        } else {
            fallbackCopyText(text, btnTextId);
        }
    }

    function fallbackCopyText(text, btnTextId) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            const btnText = document.getElementById(btnTextId);
            if (btnText) {
                btnText.textContent = '¡Copiado!';
                setTimeout(() => { btnText.textContent = 'Copiar'; }, 2000);
            }
        } catch (err) {
            console.error('Fallback copy error:', err);
        }
        document.body.removeChild(ta);
    }

    function closeDeviceEditModal() {
        const m = document.getElementById('modal-device-edit');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    // --- MODAL DE CREACIÓN DE SEDE ---
    function openCreateSiteModal() {
        const m = document.getElementById('modal-create-site');
        if (m) {
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
    }

    function closeCreateSiteModal() {
        const m = document.getElementById('modal-create-site');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    // --- MODAL DE HISTÓRICO & TELEMETRÍA ---
    function openDeviceHistoryModal(id, name, ip, slot) {
        currentDeviceId = id;
        document.getElementById('hist-device-name').innerText = name;
        document.getElementById('hist-device-slot').innerText = 'SLOT #' + slot;
        document.getElementById('hist-device-ip').innerText = 'Host IP: ' + ip;

        const m = document.getElementById('modal-device-history');
        m.classList.remove('hidden');
        m.classList.add('flex');

        loadDeviceHistory('24h');
    }

    function closeDeviceHistoryModal() {
        const m = document.getElementById('modal-device-history');
        m.classList.remove('flex');
        m.classList.add('hidden');
        if (deviceChart) {
            deviceChart.destroy();
            deviceChart = null;
        }
    }

    async function loadDeviceHistory(range) {
        if (!currentDeviceId) return;
        currentDeviceRange = range;

        // Actualizar botones de rango
        document.querySelectorAll('.hist-range-btn').forEach(btn => {
            if (btn.getAttribute('data-range') === range) {
                btn.className = 'hist-range-btn px-2.5 py-1 rounded transition font-semibold bg-obsidian-cyan text-black';
            } else {
                btn.className = 'hist-range-btn px-2.5 py-1 rounded transition font-semibold text-obsidian-muted hover:text-white';
            }
        });

        const overlay = document.getElementById('chart-loading-overlay');
        if (overlay) overlay.classList.remove('hidden');

        try {
            const res = await fetch(`/admin/devices/${currentDeviceId}/history?range=${range}`);
            const data = await res.json();

            if (!data.success) {
                console.error('Error cargando historial:', data);
                return;
            }

            document.getElementById('hist-stat-uptime').innerText = (data.stats.uptime_percentage ?? 100) + '%';
            document.getElementById('hist-stat-avg').innerText = (data.stats.avg_latency ?? 0) + ' ms';

            // Renderizar incidentes
            const tbody = document.getElementById('hist-incidents-tbody');
            if (data.incidents && data.incidents.length > 0) {
                tbody.innerHTML = data.incidents.map(inc => `
                    <tr class="hover:bg-red-950/20 text-red-300">
                        <td class="px-3 py-2 font-bold">${inc.time}</td>
                        <td class="px-3 py-2 text-obsidian-muted">${inc.time_human}</td>
                        <td class="px-3 py-2">${inc.status_message}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="3" class="px-3 py-3 text-center text-obsidian-muted">
                            Sin incidentes registrados en este período.
                        </td>
                    </tr>
                `;
            }

            // Renderizar gráfico
            renderDeviceChart(data.labels, data.latencies, data.statuses);

        } catch (err) {
            console.error('Error de red cargando telemetría:', err);
        } finally {
            if (overlay) overlay.classList.add('hidden');
        }
    }

    function renderDeviceChart(labels, latencies, statuses) {
        const ctx = document.getElementById('deviceHistoryChart').getContext('2d');
        if (deviceChart) {
            deviceChart.destroy();
            deviceChart = null;
        }

        const isAllDown = statuses.length > 0 && statuses.every(s => s === 0);
        const lineColor = isAllDown ? '#ef4444' : '#22d3ee';

        const gradient = ctx.createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, isAllDown ? 'rgba(239, 68, 68, 0.35)' : 'rgba(34, 211, 238, 0.35)');
        gradient.addColorStop(1, 'rgba(0, 0, 0, 0.0)');

        deviceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Latencia (ms)',
                    data: latencies,
                    borderColor: lineColor,
                    borderWidth: 2,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.25,
                    pointRadius: labels.length > 40 ? 0 : 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: statuses.map(s => s === 1 ? '#22d3ee' : '#ef4444'),
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                const val = context.parsed.y;
                                const status = statuses[context.dataIndex] === 1 ? 'OPERATIVO' : 'TIMEOUT';
                                return `Latencia: ${val} ms (${status})`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(28, 46, 71, 0.4)' },
                        ticks: { color: '#64748b', font: { family: 'monospace', size: 10 } }
                    },
                    y: {
                        grid: { color: 'rgba(28, 46, 71, 0.4)' },
                        ticks: { color: '#64748b', font: { family: 'monospace', size: 10 } },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Escuchar Escape para cerrar modales
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeviceDetailsModal();
            closeDeviceCreateModal();
            closeDeviceEditModal();
            closeCreateSiteModal();
            closeDeviceHistoryModal();
        }
    });
</script>
@endsection
