@extends('layouts.admin')

@section('page_title', 'Centro de Operaciones de Red + Gestión de Activos de TI')

@section('admin_content')
<style>
    /* Barra de desplazamiento horizontal visible, resaltada en cyan */
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
    .accordion-content {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
</style>

@php
    $assetsMaintenanceMap = $devices->mapWithKeys(function ($a) {
        return [$a->id => [
            'id' => $a->id,
            'name' => $a->name,
            'tag' => $a->asset_tag,
            'serial' => $a->serial,
            'new_ticket_url' => route('admin.inventory.maintenance.create', ['asset_id' => $a->id]),
            'tickets' => $a->maintenanceTickets->map(function ($t) {
                return [
                    'id' => $t->id,
                    'code' => $t->ticket_code,
                    'type' => $t->type,
                    'type_label' => ucfirst($t->type),
                    'status' => $t->status,
                    'status_label' => ucfirst($t->status),
                    'status_class' => match ($t->status) {
                        'completed' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40',
                        'in_progress' => 'bg-amber-500/20 text-amber-300 border-amber-500/40',
                        'scheduled' => 'bg-blue-500/20 text-blue-300 border-blue-500/40',
                        'pending_parts' => 'bg-purple-500/20 text-purple-300 border-purple-500/40',
                        'cancelled' => 'bg-red-500/20 text-red-300 border-red-500/40',
                        default => 'bg-slate-500/20 text-slate-300 border-slate-500/40',
                    },
                    'priority' => $t->priority,
                    'title' => $t->title,
                    'description' => $t->description,
                    'technician' => $t->technician ? $t->technician->name : 'No asignado',
                    'date' => $t->created_at ? $t->created_at->format('d/m/Y') : '-',
                    'pdf_url' => route('admin.inventory.maintenance.pdf', $t->id),
                    'show_url' => route('admin.inventory.maintenance.show', $t->id),
                    'edit_url' => route('admin.inventory.maintenance.edit', $t->id),
                ];
            }),
        ]];
    });
@endphp

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
                <span class="text-[10.5px] font-mono text-obsidian-muted">Centro de Operaciones de Red + Gestión de Activos de TI</span>
            </div>
            <h1 class="text-xl font-black text-white tracking-wide flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-2xl">devices</span>
                Equipos y Activos de Red
            </h1>
            <p class="text-xs font-mono text-obsidian-muted">Supervisión técnica de Switches, Routers, Servidores, PCs LAN, fichas técnicas, Asignacion de Activos y mantenimiento</p>
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
                            <span class="font-bold text-amber-200">Fuente: Servidor Esclavo (Réplica)</span>
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

            @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.manage_sites')) && !($isClusterSlave ?? false))
                <div class="relative group">
                    <button type="button" onclick="openCreateSiteModal()" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-obsidian-panel border border-purple-500/40 text-purple-300 hover:bg-purple-600 hover:text-white transition text-xs font-mono font-bold shadow-xs cursor-pointer">
                        <span class="material-symbols-outlined text-base">domain</span>
                        <span class="font-bold text-sm leading-none">+</span>
                    </button>
                    <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-purple-500/50 shadow-xl shadow-purple-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-xs text-purple-400">domain_add</span>
                            <span class="font-bold text-purple-200">Agregar Sede</span>
                        </div>
                    </div>
                </div>
            @endif
            @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.manage_devices')) && !($isClusterSlave ?? false))
                <div class="relative group">
                    <button type="button" onclick="openDeviceCreateModal()" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black transition text-xs font-mono font-bold shadow-md shadow-cyan-500/20 cursor-pointer">
                        <span class="material-symbols-outlined text-base">devices</span>
                        <span class="font-bold text-sm leading-none">+</span>
                    </button>
                    <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-xs text-cyan-400">devices</span>
                            <span class="font-bold text-cyan-200">Agregar Dispositivo</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if(session('bulk_mobilization_pdf'))
        <div class="p-4 rounded-2xl bg-amber-950/40 border border-amber-500/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xl">
            <div class="flex items-center gap-3">
                <span class="p-2.5 rounded-xl bg-amber-500/20 text-amber-400 border border-amber-500/30">
                    <span class="material-symbols-outlined text-2xl">local_shipping</span>
                </span>
                <div>
                    <h4 class="text-sm font-bold text-white flex items-center gap-2">
                        <span>Pase de Movilización Generado: {{ session('bulk_mobilization_code') }}</span>
                    </h4>
                    <p class="text-xs text-amber-300/80 font-mono">El traslado colectivo y acta formal han sido registrados exitosamente en el sistema.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ session('bulk_mobilization_pdf') }}" target="_blank" class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-amber-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-base">print</span>
                    <span>Ver / Imprimir Pase</span>
                </a>
                <a href="{{ session('bulk_mobilization_pdf') }}?download=1" class="px-3.5 py-1.5 rounded-xl bg-obsidian-panel border border-amber-500/40 text-amber-300 hover:text-white font-mono text-xs transition flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-base">download</span>
                    <span>Descargar PDF</span>
                </a>
            </div>
        </div>
    @endif

    <!-- PESTAÑAS EQUIPOS & HARDWARE (PANEL DE NAVEGACIÓN COMPACTO) -->
    <div class="flex flex-wrap items-center gap-1.5 border-b border-obsidian-border/80 pb-2.5">
        <a href="{{ route('admin.devices.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.devices.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Equipos y Activos de Red Monitoreados">
            <span class="material-symbols-outlined text-sm">devices</span>
            <span>Equipos &amp; Activos</span>
        </a>
        <a href="{{ route('admin.inventory.peripherals.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.inventory.peripherals.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Inventario de Periféricos y Stock de Repuestos">
            <span class="material-symbols-outlined text-sm">keyboard</span>
            <span>Periféricos &amp; Stock</span>
        </a>
        <a href="{{ route('admin.inventory.deploy') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.inventory.deploy*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Despliegue Remoto Agente GLPI">
            <span class="material-symbols-outlined text-sm">send_to_mobile</span>
            <span>Despliegue GLPI</span>
        </a>
        <a href="{{ route('admin.wol.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.wol.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Encendido Remoto Wake-on-LAN">
            <span class="material-symbols-outlined text-sm">power</span>
            <span>Wake-on-LAN (WoL)</span>
        </a>
        <a href="{{ route('admin.ups.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.ups.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Monitoreo Serial de Energía & UPS">
            <span class="material-symbols-outlined text-sm">battery_charging_full</span>
            <span>Monitoreo UPS</span>
        </a>
    </div>

    <!-- TARJETAS KPIS SUPERIORES -->
    <div id="devices-kpi-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- TOTAL EQUIPOS -->
        <a href="{{ route('admin.devices.index') }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border hover:border-cyan-500/50 transition group shadow-xs">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">Total Dispositivos</span>
                <span class="material-symbols-outlined text-lg text-cyan-400 group-hover:scale-110 transition-transform">database</span>
            </div>
            <div class="text-2xl font-black text-white font-mono">{{ $totalCount }}</div>
            <div class="text-[10px] font-mono text-cyan-400/80 mt-1">Dispositivos registrados</div>
        </a>

        <!-- EN LÍNEA / ACTIVOS -->
        <a href="{{ route('admin.devices.index', ['status' => 'active']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'active' ? 'border-emerald-500 bg-emerald-950/10' : 'border-obsidian-border hover:border-emerald-500/50' }} transition group shadow-xs">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">Monitoreo Activo</span>
                <span class="material-symbols-outlined text-lg text-emerald-400 group-hover:scale-110 transition-transform">check_circle</span>
            </div>
            <div class="text-2xl font-black text-emerald-400 font-mono">{{ $activeCount }}</div>
            <div class="text-[10px] font-mono text-emerald-400/80 mt-1">Supervisión ICMP activa</div>
        </a>

        <!-- CON ASIGNACIÓN -->
        <a href="{{ route('admin.devices.index', ['status' => 'assigned']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'assigned' ? 'border-cyan-500 bg-cyan-950/10' : 'border-obsidian-border hover:border-cyan-500/50' }} transition group shadow-xs">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">Con Asignación</span>
                <span class="material-symbols-outlined text-lg text-cyan-400 group-hover:scale-110 transition-transform">assignment_turned_in</span>
            </div>
            <div class="text-2xl font-black text-cyan-400 font-mono">{{ $withAssignmentCount }}</div>
            <div class="text-[10px] font-mono text-cyan-400/80 mt-1">Acta formalizada</div>
        </a>

        <!-- SIN ASIGNACIÓN -->
        <a href="{{ route('admin.devices.index', ['status' => 'unassigned']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'unassigned' ? 'border-amber-500 bg-amber-950/10' : 'border-obsidian-border hover:border-amber-500/50' }} transition group shadow-xs">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">Sin Asignación</span>
                <span class="material-symbols-outlined text-lg text-amber-400 group-hover:scale-110 transition-transform">assignment_late</span>
            </div>
            <div class="text-2xl font-black text-amber-400 font-mono">{{ $withoutAssignmentCount }}</div>
            <div class="text-[10px] font-mono text-amber-400/80 mt-1">Pendiente de acta</div>
        </a>

        <!-- EN MANTENIMIENTO -->
        <a href="{{ route('admin.devices.index', ['status' => 'maintenance']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'maintenance' ? 'border-orange-500 bg-orange-950/10' : 'border-obsidian-border hover:border-orange-500/50' }} transition group shadow-xs">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">Mantenimiento</span>
                <span class="material-symbols-outlined text-lg text-amber-400 group-hover:scale-110 transition-transform">engineering</span>
            </div>
            <div class="text-2xl font-black text-amber-400 font-mono">{{ $maintenanceCount }}</div>
            <div class="text-[10px] font-mono text-amber-400/80 mt-1">En taller o revisión</div>
        </a>

        <!-- INACTIVOS / PAUSADOS -->
        <a href="{{ route('admin.devices.index', ['status' => 'inactive']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'inactive' ? 'border-slate-500 bg-slate-900/30' : 'border-obsidian-border hover:border-slate-400' }} transition group shadow-xs">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">Pausados / Off</span>
                <span class="material-symbols-outlined text-lg text-obsidian-muted group-hover:scale-110 transition-transform">pause_circle</span>
            </div>
            <div class="text-2xl font-black text-obsidian-muted font-mono">{{ $inactiveCount }}</div>
            <div class="text-[10px] font-mono text-obsidian-muted/80 mt-1">Monitoreo suspendido</div>
        </a>
    </div>

    <!-- BARRA DE FILTROS & BÚSQUEDA REACTIVA -->
    <div class="p-3.5 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border shadow-xs">
        <form id="device-filter-form" method="GET" action="{{ route('admin.devices.index') }}" onsubmit="event.preventDefault(); fetchDeviceResults();" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
            <!-- BUSCADOR EN VIVO (LIVE SEARCH) -->
            <div class="lg:col-span-4 relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-obsidian-muted text-lg pointer-events-none">search</span>
                <input type="text" id="device-search-input" name="search" value="{{ $search }}" autocomplete="off"
                       placeholder="Buscar por nombre, IP, MAC, serial, tag o asignación..." 
                       oninput="onSearchInput(event)" onkeyup="onSearchInput(event)"
                       class="w-full pl-9 pr-8 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white placeholder-obsidian-muted focus:border-cyan-500 focus:outline-none transition">
                <button type="button" id="btn-clear-search-x" onclick="clearSearchInput()" class="{{ empty($search) ? 'hidden' : '' }} absolute right-2.5 top-1/2 -translate-y-1/2 text-obsidian-muted hover:text-white transition p-0.5 cursor-pointer" title="Borrar término de búsqueda">
                    <span class="material-symbols-outlined text-sm leading-none">close</span>
                </button>
            </div>

            <!-- FILTRO DE SEDE (AUTO-TRIGGER) -->
            <div class="lg:col-span-3">
                <select id="device-site-select" name="site_id" onchange="onFilterChange()" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none transition cursor-pointer">
                    <option value="">Todas las Sedes ({{ $totalCount }})</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" {{ (string)$siteFilter === (string)$site->id ? 'selected' : '' }}>
                            {{ $site->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- FILTRO DE TIPO DE HARDWARE (AUTO-TRIGGER) -->
            <div class="lg:col-span-2">
                <select id="device-type-select" name="type" onchange="onFilterChange()" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none transition cursor-pointer">
                    <option value="">Todos los Tipos</option>
                    <option value="desktop" {{ $typeFilter === 'desktop' ? 'selected' : '' }}>Desktop / PC</option>
                    <option value="laptop" {{ $typeFilter === 'laptop' ? 'selected' : '' }}>Laptop / Portátil</option>
                    <option value="router" {{ $typeFilter === 'router' ? 'selected' : '' }}>Router Gateway</option>
                    <option value="switch" {{ $typeFilter === 'switch' ? 'selected' : '' }}>Switch de Red</option>
                    <option value="server" {{ $typeFilter === 'server' ? 'selected' : '' }}>Servidor</option>
                    <option value="printer" {{ $typeFilter === 'printer' ? 'selected' : '' }}>Impresora</option>
                    <option value="ip_phone" {{ $typeFilter === 'ip_phone' ? 'selected' : '' }}>Teléfono IP</option>
                    <option value="ups" {{ $typeFilter === 'ups' ? 'selected' : '' }}>Energía / UPS</option>
                </select>
            </div>

            <!-- FILTRO DE ESTADO (AUTO-TRIGGER) -->
            <div class="lg:col-span-2">
                <select id="device-status-select" name="status" onchange="onFilterChange()" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none transition cursor-pointer">
                    <option value="">Todos los Estados</option>
                    <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Monitoreo Activo</option>
                    <option value="inactive" {{ $statusFilter === 'inactive' ? 'selected' : '' }}>Monitoreo Suspendido</option>
                    <option value="assigned" {{ $statusFilter === 'assigned' ? 'selected' : '' }}>Con Asignación (Con Acta)</option>
                    <option value="unassigned" {{ $statusFilter === 'unassigned' ? 'selected' : '' }}>Sin Asignación (Pendiente)</option>
                    <option value="in_stock" {{ $statusFilter === 'in_stock' ? 'selected' : '' }}>En Stock / Almacén</option>
                    <option value="maintenance" {{ $statusFilter === 'maintenance' ? 'selected' : '' }}>En Mantenimiento</option>
                    <option value="damaged" {{ $statusFilter === 'damaged' ? 'selected' : '' }}>Dañado / Para Baja</option>
                </select>
            </div>

            <!-- ESTADO DEL FILTRADO & ACCIÓN RÁPIDA (SIN BOTÓN MANUAL DE FILTRAR) -->
            <div class="lg:col-span-1 flex items-center gap-1.5">
                <button type="button" id="btn-clear-filters" onclick="clearAllDeviceFilters()" class="{{ ($search || $statusFilter || $typeFilter || $siteFilter) ? '' : 'hidden' }} w-full py-2 px-2.5 bg-red-950/60 border border-red-500/40 text-red-300 hover:bg-red-600 hover:text-white rounded-xl text-xs font-mono font-bold transition flex items-center justify-center gap-1 shadow-xs cursor-pointer" title="Limpiar todos los filtros y restaurar censo completo">
                    <span class="material-symbols-outlined text-base">filter_alt_off</span>
                    <span>Limpiar</span>
                </button>
                <div id="filter-idle-indicator" class="{{ ($search || $statusFilter || $typeFilter || $siteFilter) ? 'hidden' : '' }} w-full py-2 px-2.5 rounded-xl bg-obsidian-panel/60 border border-obsidian-border text-obsidian-muted/60 text-xs font-mono flex items-center justify-center gap-1 cursor-default select-none" title="Filtro reactivo activo (actualiza automáticamente al escribir o seleccionar)">
                    <span class="material-symbols-outlined text-base text-cyan-400/70 transition-transform" id="filter-status-icon">tune</span>
                </div>
            </div>
        </form>
    </div>

    <!-- TABLA DE DISPOSITIVOS CON ACORDEÓN DESPLEGABLE ITAM -->
    <div id="devices-table-container" class="glass-card rounded-2xl overflow-hidden shadow-xl border border-obsidian-border/80">
        <div class="custom-table-scroll">
            <table class="w-full text-left font-mono border-collapse min-w-[1250px]">
                <thead class="bg-[#040b15]/95 border-b border-obsidian-border text-obsidian-muted uppercase text-[9.5px] tracking-wider">
                    <tr>
                        <th class="py-2.5 px-3 w-8 text-center">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="w-4 h-4 rounded border-obsidian-border bg-[#040b15] text-cyan-500 focus:ring-0 cursor-pointer" title="Seleccionar todos los equipos de la página">
                        </th>
                        <th class="py-2.5 px-2 w-8 text-center" title="Ficha Técnica Desplegable (Hardware, Periféricos y Red)">Info</th>
                        <th class="py-2.5 px-3 whitespace-nowrap" title="Sede y Ubicación Física">Sede Asignada</th>
                        <th class="py-2.5 px-3 whitespace-nowrap" title="Nombre Etiqueta (Operativo) y Nombre de Equipo (Hostname)">Identidad</th>
                        <th class="py-2.5 px-3 whitespace-nowrap text-center" title="Tipo de Hardware / Categoría de Dispositivo">Tipo</th>
                        <th class="py-2.5 px-3 whitespace-nowrap" title="Dirección IP de Red">IP</th>
                        <th class="py-2.5 px-3 whitespace-nowrap text-center" title="Protocolo de Gestión Remota (SSH, Telnet, Web, VNC)">Acceso Remoto</th>
                        <th class="py-2.5 px-3 whitespace-nowrap text-center" title="Supervisión en Tiempo Real ICMP (En Línea / Offline)">Monitoreo</th>
                        <th class="py-2.5 px-3 whitespace-nowrap text-center" title="Acta de Asignación Formalizada UINF-4.05">Acta de Asignación</th>
                        <th class="py-2.5 px-3 whitespace-nowrap text-center" title="Historial y Órdenes de Mantenimiento Técnico">Mantenimiento</th>
                        <th class="py-2.5 px-3 whitespace-nowrap text-center" title="Telemetría e Inventario de Agente GLPI">GLPI</th>
                        <th class="py-2.5 px-4 whitespace-nowrap text-right" title="Acciones y Operaciones de Gestión">Acciones</th>
                    </tr>
                </thead>
                <tbody id="devices-table-body" class="divide-y divide-obsidian-border/60 text-xs">
                    @forelse($devices as $d)
                        @php
                            $snap = $snapshotDevices->get($d->id);
                            $isUp = $snap ? ($snap['is_up'] ?? false) : true;
                            $lat = $snap ? ($snap['latency_ms'] ?? 0) : 0;
                            $latStr = $lat > 0 ? ($lat . ' ms') : '< 1 ms';
                            $accType = strtoupper(trim($d->access_type ?? 'SIN SOPORTE'));
                            $accPort = $d->access_port ?: ($accType === 'SSH' ? 22 : ($accType === 'TELNET' ? 23 : ($accType === 'WEB' ? 80 : 5900)));
                            $sshPort = $d->ssh_port ?: (($accType === 'SSH' || $accType === 'SSH+VNC') ? ($d->access_port ?: 22) : 22);
                            $vncPort = $d->vnc_port ?: (($accType === 'VNC' || $accType === 'SSH+VNC') ? ($d->access_port ?: 5900) : 5900);
                            $webPort = $d->web_port ?: ($accType === 'WEB' ? ($d->access_port ?: 80) : 80);
                            $telnetPort = $d->telnet_port ?: ($accType === 'TELNET' ? ($d->access_port ?: 23) : 23);
                            $siteName = $d->site ? $d->site->name : 'Sede Principal';

                            $isPc = $d->is_pc;
                            $cpu = $d->components->firstWhere('type', 'cpu');
                            $rams = $d->components->where('type', 'ram');
                            $disks = $d->components->where('type', 'disk');
                            $baseTypesSeen = ['monitor' => false, 'keyboard' => false, 'mouse' => false, 'protector' => false];
                            $monitor = null;
                            $keyboard = null;
                            $mouse = null;
                            $protector = null;
                            $extraPeriphs = collect();

                            foreach ($d->peripherals as $p) {
                                if ($p->type === 'monitor' && !$baseTypesSeen['monitor']) {
                                    $baseTypesSeen['monitor'] = true;
                                    $monitor = $p;
                                } elseif ($p->type === 'keyboard' && !$baseTypesSeen['keyboard']) {
                                    $baseTypesSeen['keyboard'] = true;
                                    $keyboard = $p;
                                } elseif ($p->type === 'mouse' && !$baseTypesSeen['mouse']) {
                                    $baseTypesSeen['mouse'] = true;
                                    $mouse = $p;
                                } elseif (in_array($p->type, ['surge_protector', 'ups']) && !$baseTypesSeen['protector']) {
                                    $baseTypesSeen['protector'] = true;
                                    $protector = $p;
                                } else {
                                    $extraPeriphs->push($p);
                                }
                            }

                            $periphSearch = $d->peripherals->map(fn($p) => "{$p->brand} {$p->model} {$p->serial_number} {$p->asset_tag} {$p->type}")->implode(' ');
                            $activeAssignment = $d->assignments->firstWhere('status', 'active') ?? $d->assignments->first();
                            $activeMaint = $d->maintenanceTickets->whereIn('status', ['scheduled', 'in_progress', 'pending_parts'])->first();
                            $maintCount = $d->maintenanceTickets->count();
                            $searchHaystack = strtolower(implode(' ', array_filter([
                                $d->display_identity,
                                $d->name,
                                $d->technical_subtitle,
                                $d->ip,
                                $d->mac,
                                $d->serial,
                                $d->asset_tag,
                                $d->inventory_tag,
                                $siteName,
                                $d->type,
                                $accType,
                                $activeAssignment ? $activeAssignment->custodian_name : null,
                                $activeAssignment ? $activeAssignment->custodian_department : null,
                                $activeAssignment ? $activeAssignment->custodian_id_number : null,
                                $d->brand,
                                $d->model,
                                $periphSearch,
                            ])));
                        @endphp
                        <tr id="asset-row-{{ $d->id }}" 
                            class="hover:bg-cyan-500/[0.03] transition-colors group cursor-pointer device-row item-searchable" 
                            onclick="toggleAssetAccordion({{ $d->id }}, event)"
                            data-search="{{ $searchHaystack }}"
                            data-site-id="{{ $d->monitored_site_id ?? '' }}"
                            data-type="{{ strtolower($d->type ?? '') }}"
                            data-status="{{ $d->is_active ? 'active' : 'inactive' }} {{ strtolower($d->status ?? '') }}">
                            <!-- CHECKBOX DE SELECCIÓN MASIVA -->
                            <td class="py-2 px-3 text-center" onclick="event.stopPropagation()">
                                <input type="checkbox" value="{{ $d->id }}" onchange="onAssetCheckChanged()" class="asset-checkbox w-4 h-4 rounded border-obsidian-border bg-[#040b15] text-cyan-500 focus:ring-0 cursor-pointer" title="Seleccionar [{{ $d->display_identity }}]">
                            </td>

                            <!-- BOTÓN DESPLEGABLE ACORDEÓN -->
                            <td class="py-2 px-2 text-center" onclick="event.stopPropagation()">
                                <button type="button" onclick="toggleAssetAccordion({{ $d->id }}, event)" class="p-1 rounded-md text-obsidian-muted hover:text-cyan-400 hover:bg-cyan-500/10 transition" title="Ver Ficha Técnica Desplegable ({{ $d->display_identity }})">
                                    <span class="material-symbols-outlined text-base transition-transform" id="chevron-{{ $d->id }}">expand_more</span>
                                </button>
                            </td>

                            <!-- SEDE ASIGNADA -->
                            <td class="py-2 px-3 whitespace-nowrap" onclick="event.stopPropagation()">
                                @if($d->site)
                                    <a href="{{ route('admin.devices.index', ['site_id' => $d->site->id]) }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-purple-950/70 border border-purple-500/30 text-purple-300 hover:bg-purple-900 transition text-[9.5px] font-semibold" title="Sede: {{ $d->site->name }} (Clic para filtrar)">
                                        <span class="material-symbols-outlined text-[11px]">domain</span>
                                        <span class="truncate max-w-[110px]">{{ $d->site->name }}</span>
                                    </a>
                                @else
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-muted text-[9.5px]" title="Sede Principal (Sin sede específica)">
                                        Principal
                                    </span>
                                @endif
                            </td>

                            <!-- IDENTIDAD & PING -->
                            <td class="py-2 px-3">
                                <div class="flex items-center gap-2 min-w-[130px]">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $isUp ? 'bg-emerald-400 glow-green' : 'bg-red-500' }}" title="{{ $isUp ? ('Online (ICMP OK - ' . $latStr . ')') : 'Offline (Sin respuesta)' }}"></span>
                                    <div class="truncate max-w-[180px]">
                                        <div class="font-sans font-bold text-white text-[12px] truncate" title="{{ $d->display_identity }}">{{ $d->display_identity }}</div>
                                        @if($d->technical_subtitle && $d->technical_subtitle !== $d->display_identity)
                                            <div class="text-[9.5px] font-mono text-obsidian-muted truncate" title="Nombre de Equipo (Hostname): {{ $d->technical_subtitle }}">Host: {{ $d->technical_subtitle }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- TIPO -->
                            <td class="py-2 px-3 whitespace-nowrap text-center cursor-help" title="Tipo de Dispositivo: {{ $d->type_label }}">
                                <span class="p-1 rounded-lg inline-flex items-center justify-center bg-cyan-950/70 text-cyan-300 border border-cyan-500/40 shadow-xs cursor-help" title="Tipo de Dispositivo: {{ $d->type_label }}">
                                    <span class="material-symbols-outlined text-[17px] leading-none block pointer-events-none" title="Tipo de Dispositivo: {{ $d->type_label }}">{{ $d->type_icon }}</span>
                                </span>
                            </td>

                            <!-- IP -->
                            <td class="py-2 px-3 whitespace-nowrap" title="Dirección IP: {{ $d->ip }}">
                                <span class="font-semibold text-white text-[11px] font-mono" title="Dirección IP: {{ $d->ip }}">{{ $d->ip }}</span>
                            </td>

                            <!-- PROTOCOLO DE ACCESO REMOTO DIRECTO -->
                            <td class="py-2 px-3 whitespace-nowrap text-center" onclick="event.stopPropagation()">
                                @if($accType === 'SSH+VNC')
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button" 
                                                onclick="openSshTerminal('{{ $d->ip }}', {{ $sshPort }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($siteName) }}')" 
                                                class="px-2 py-0.5 rounded bg-emerald-950/90 border border-emerald-500/50 text-emerald-300 hover:bg-emerald-500 hover:text-black transition text-[9.5px] font-bold inline-flex items-center gap-1 cursor-pointer shadow-xs" 
                                                title="Consola SSH Web ({{ $d->ip }}:{{ $sshPort }})">
                                            <span class="material-symbols-outlined text-[11px]">terminal</span>
                                            <span>SSH</span>
                                        </button>
                                        @if($d->has_ssh_credentials)
                                            <span class="text-[11px] text-emerald-400 cursor-help" title="Credenciales SSH cifradas listas para respaldos GitOps">🔑</span>
                                        @endif
                                        <button type="button" 
                                                onclick="openVncViewer('{{ $d->ip }}', '{{ addslashes($d->display_identity) }}', '{{ addslashes($siteName) }}', {{ $vncPort }})" 
                                                class="px-2 py-0.5 rounded bg-purple-950/90 border border-purple-500/50 text-purple-300 hover:bg-purple-500 hover:text-white transition text-[9.5px] font-bold inline-flex items-center gap-1 cursor-pointer shadow-xs" 
                                                title="Conectar Escritorio Remoto VNC ({{ $d->ip }}:{{ $vncPort }})">
                                            <span class="material-symbols-outlined text-[11px]">desktop_windows</span>
                                            <span>VNC</span>
                                        </button>
                                        @if($d->has_vnc_credentials)
                                            <span class="text-[11px] text-purple-400 cursor-help" title="Clave de autenticación VNC cifrada configurada">🔒</span>
                                        @endif
                                    </div>
                                @elseif($accType === 'SSH')
                                    <div class="inline-flex items-center gap-1">
                                        <button type="button" 
                                                onclick="openSshTerminal('{{ $d->ip }}', {{ $sshPort }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($siteName) }}')" 
                                                class="px-2 py-0.5 rounded bg-emerald-950/90 border border-emerald-500/50 text-emerald-300 hover:bg-emerald-500 hover:text-black transition text-[9.5px] font-bold inline-flex items-center gap-1 cursor-pointer shadow-xs" 
                                                title="Consola SSH Web ({{ $d->ip }}:{{ $sshPort }})">
                                            <span class="material-symbols-outlined text-[11px]">terminal</span>
                                            <span>SSH</span>
                                        </button>
                                        @if($d->has_ssh_credentials)
                                            <span class="text-[11px] text-emerald-400 cursor-help" title="Credenciales SSH cifradas listas para respaldos GitOps">🔑</span>
                                        @endif
                                    </div>
                                @elseif($accType === 'TELNET')
                                    <button type="button" 
                                            onclick="openTelnetTerminal('{{ $d->ip }}', {{ $telnetPort }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($siteName) }}')" 
                                            class="px-2 py-0.5 rounded bg-cyan-950/90 border border-cyan-500/50 text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[9.5px] font-bold inline-flex items-center gap-1 cursor-pointer shadow-xs" 
                                            title="Consola Telnet ({{ $d->ip }}:{{ $telnetPort }})">
                                        <span class="material-symbols-outlined text-[11px]">terminal</span>
                                        <span>TELNET</span>
                                    </button>
                                @elseif($accType === 'WEB')
                                    @php
                                        $webProto = ($webPort == 443 || $webPort == 8443) ? 'https' : 'http';
                                    @endphp
                                    <a href="{{ $webProto }}://{{ $d->ip }}:{{ $webPort }}" target="_blank" 
                                       class="px-2 py-0.5 rounded bg-blue-950/90 border border-blue-500/50 text-blue-300 hover:bg-blue-500 hover:text-black transition text-[9.5px] font-bold inline-flex items-center gap-1 shadow-xs" 
                                       title="Interfaz Web {{ strtoupper($webProto) }} ({{ $d->ip }}:{{ $webPort }})">
                                        <span class="material-symbols-outlined text-[11px]">open_in_browser</span>
                                        <span>WEB</span>
                                    </a>
                                @elseif($accType === 'VNC')
                                    <div class="inline-flex items-center gap-1">
                                        <button type="button" 
                                                onclick="openVncViewer('{{ $d->ip }}', '{{ addslashes($d->display_identity) }}', '{{ addslashes($siteName) }}', {{ $vncPort }})" 
                                                class="px-2 py-0.5 rounded bg-purple-950/90 border border-purple-500/50 text-purple-300 hover:bg-purple-500 hover:text-white transition text-[9.5px] font-bold inline-flex items-center gap-1 cursor-pointer shadow-xs" 
                                                title="Conectar Escritorio Remoto VNC ({{ $d->ip }}:{{ $vncPort }})">
                                            <span class="material-symbols-outlined text-[11px]">desktop_windows</span>
                                            <span>VNC</span>
                                        </button>
                                        @if($d->has_vnc_credentials)
                                            <span class="text-[11px] text-purple-400 cursor-help" title="Clave de autenticación VNC cifrada configurada">🔒</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="px-1.5 py-0.5 rounded text-[8.5px] bg-obsidian-panel border border-obsidian-border text-obsidian-muted inline-flex items-center gap-0.5" title="Sin protocolo de acceso remoto configurado">
                                        <span class="material-symbols-outlined text-[10px]">power_off</span>
                                        <span>SIN ACCESO</span>
                                    </span>
                                @endif
                            </td>

                            <!-- ESTADO DE MONITOREO (ÍCONO DINÁMICO) -->
                            <td class="py-2 px-3 text-center whitespace-nowrap" onclick="event.stopPropagation()">
                                @php
                                    $monStatusText = $d->is_active 
                                        ? ('Monitoreo Activo • ' . ($isUp ? ('Online (ICMP OK - ' . $latStr . ')') : 'Offline (Sin respuesta ICMP)') . ((auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.manage_devices')) && !($isClusterSlave ?? false) ? ' • Clic para pausar monitoreo' : ''))
                                        : ('Monitoreo Pausado / Desactivado' . ((auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.manage_devices')) && !($isClusterSlave ?? false) ? ' • Clic para activar monitoreo' : ''));
                                @endphp
                                @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.manage_devices')) && !($isClusterSlave ?? false))
                                    <form action="{{ route('admin.devices.toggle', $d->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" 
                                                class="p-1 rounded-lg transition inline-flex items-center justify-center {{ $d->is_active ? 'bg-emerald-950/70 text-emerald-400 border border-emerald-500/40 hover:bg-emerald-900/80 shadow-xs shadow-emerald-500/20' : 'bg-red-950/70 text-red-400 border border-red-500/40 hover:bg-red-900/80 shadow-xs shadow-red-500/20' }}" 
                                                title="{{ $monStatusText }}">
                                            <span class="material-symbols-outlined text-[17px] leading-none block">{{ $d->is_active ? 'check_circle' : 'cancel' }}</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="p-1 rounded-lg inline-flex items-center justify-center {{ $d->is_active ? 'bg-emerald-950/70 text-emerald-400 border border-emerald-500/40' : 'bg-red-950/70 text-red-400 border border-red-500/40' }}" 
                                          title="{{ $monStatusText }}">
                                        <span class="material-symbols-outlined text-[17px] leading-none block">{{ $d->is_active ? 'check_circle' : 'cancel' }}</span>
                                    </span>
                                @endif
                                @if($d->telegram_alert_enabled)
                                    <div class="mt-0.5">
                                        <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[7.5px] font-bold font-mono {{ $d->telegram_alert_target === 'group' ? 'bg-purple-950/80 text-purple-300 border border-purple-500/40' : 'bg-cyan-950/80 text-cyan-300 border border-cyan-500/40' }}" title="Notificaciones Telegram: {{ $d->telegram_alert_target === 'group' ? 'Grupo Corporativo' : 'Administrador Privado' }}">
                                            <span class="material-symbols-outlined text-[8.5px]">{{ $d->telegram_alert_target === 'group' ? 'group' : 'notifications_active' }}</span>
                                            <span>{{ $d->telegram_alert_target === 'group' ? 'Grupo' : 'Owner' }}</span>
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <!-- ACTA DE ASIGNACIÓN / UINF-4.05 -->
                            <td class="py-2 px-3 text-center whitespace-nowrap" onclick="event.stopPropagation()">
                                @if($activeAssignment)
                                    <div class="inline-flex items-center justify-center gap-1">
                                        @if($d->has_outdated_assignment && (auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage')))
                                            <a href="{{ route('admin.inventory.assignments.create', ['asset_id' => $d->id, 'return_to' => url()->full()]) }}" 
                                               class="p-1 rounded bg-amber-500/20 border border-amber-500/50 text-amber-300 hover:bg-amber-500 hover:text-black transition animate-pulse" 
                                               title="Alerta de Auditoría: Hardware o periféricos modificados posterior al acta. Clic para renovar planilla UINF-4.05">
                                                <span class="material-symbols-outlined text-[12px]">warning</span>
                                            </a>
                                        @endif
                                        @if($activeAssignment->document_pdf_path)
                                            <!-- Ver Hoja Física Digitalizada -->
                                            <a href="{{ route('admin.inventory.assignments.view-scan', $activeAssignment->id) }}" target="_blank" 
                                               class="p-1 rounded bg-emerald-950/70 border border-emerald-500/60 text-emerald-300 hover:bg-emerald-500 hover:text-black transition" 
                                               title="Acta Validada (Soporte Digital): Clic para abrir documento escaneado de {{ $activeAssignment->custodian_name }}">
                                                <span class="material-symbols-outlined text-[12px]">verified</span>
                                            </a>
                                        @else
                                            <!-- Subir Hoja Física Escaneada si falta -->
                                            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                                                @if($activeAssignment->scan_compliance_status === 'overdue')
                                                <button type="button" 
                                                        onclick="openUploadPhysicalScanModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}')"
                                                        class="p-1 rounded bg-rose-950/70 border border-rose-500/60 text-rose-300 hover:bg-rose-500 hover:text-white transition cursor-pointer" 
                                                        title="Soporte Digital Pendiente: Sin digitalizar soporte de {{ $activeAssignment->custodian_name }}">
                                                    <span class="material-symbols-outlined text-[12px]">emergency</span>
                                                </button>
                                                @else
                                                <button type="button" 
                                                        onclick="openUploadPhysicalScanModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}')"
                                                        class="p-1 rounded bg-amber-950/50 border border-amber-600/60 text-amber-300 hover:bg-amber-500 hover:text-black transition cursor-pointer" 
                                                        title="Pendiente de soporte físico: Clic para subir documento escaneado ({{ $activeAssignment->custodian_name }})">
                                                    <span class="material-symbols-outlined text-[12px]">upload_file</span>
                                                </button>
                                                @endif
                                            @endif
                                        @endif

                                        @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                            <button type="button" 
                                                    onclick="openReconcileLdapModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}', {{ $activeAssignment->document_pdf_path ? 'true' : 'false' }})"
                                                    class="p-1 rounded bg-teal-500/20 border border-teal-500/40 text-teal-300 hover:bg-teal-500 hover:text-black transition cursor-pointer" 
                                                    title="Conciliar responsable con Directorio Activo (LDAP)">
                                                <span class="material-symbols-outlined text-[12px]">manage_accounts</span>
                                            </button>
                                        @endif

                                        <!-- Descargar Acta Digital UINF-4.05 generada por sistema -->
                                        <a href="{{ route('admin.inventory.assignments.pdf', $activeAssignment->id) }}" target="_blank" 
                                           class="p-1 rounded bg-red-950/40 border border-red-800/60 text-red-400 hover:bg-red-500 hover:text-white transition" 
                                           title="Descargar Acta UINF-4.05 (PDF): {{ $activeAssignment->assignment_code }} • Asignado a: {{ $activeAssignment->custodian_name }}">
                                            <span class="material-symbols-outlined text-[12px]">picture_as_pdf</span>
                                        </a>

                                        <!-- Ver Ficha de Detalle de Asignación / Movilización -->
                                        <a href="{{ route('admin.inventory.assignments.show', $activeAssignment->id) }}" 
                                           class="p-1 rounded bg-cyan-950/60 border border-cyan-800/60 text-cyan-300 hover:bg-cyan-500 hover:text-black transition" 
                                           title="Ver Ficha de Asignación: {{ $activeAssignment->assignment_code }} • Asignado a: {{ $activeAssignment->custodian_name }}">
                                            <span class="material-symbols-outlined text-[12px]">badge</span>
                                        </a>

                                        @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                                        <!-- Reasignar / Transferir con LDAP -->
                                        <button type="button" 
                                                onclick="openQuickAssignModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($d->ip ?? '') }}', '{{ addslashes($d->serial_number ?? $d->serial ?? '') }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}')" 
                                                class="p-1 rounded bg-blue-950/60 border border-blue-600/60 text-blue-300 hover:bg-blue-500 hover:text-black transition cursor-pointer" 
                                                title="Reasignar o Transferir con Directorio LDAP (Actualmente: {{ $activeAssignment->custodian_name }})">
                                            <span class="material-symbols-outlined text-[12px]">swap_horiz</span>
                                        </button>
                                        @endif
                                    </div>
                                @elseif($d->custodian_name)
                                    <div class="inline-flex items-center justify-center gap-1">
                                        @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                                            <button type="button" 
                                                    onclick="openUploadPhysicalScanModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($d->custodian_name) }}', '{{ addslashes($d->custodian_id_number ?? '') }}', '{{ addslashes($d->custodian_department ?? '') }}')"
                                                    class="p-1 rounded bg-amber-500/20 border border-amber-500/40 text-amber-300 hover:bg-amber-500 hover:text-black transition cursor-pointer" 
                                                    title="Subir Hoja Física Escaneada de {{ $d->custodian_name }}">
                                                <span class="material-symbols-outlined text-[12px]">upload_file</span>
                                            </button>
                                            <button type="button" 
                                                    onclick="openReconcileLdapModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($d->custodian_name) }}', '{{ addslashes($d->custodian_id_number ?? '') }}', '{{ addslashes($d->custodian_department ?? '') }}', true)"
                                                    class="p-1 rounded bg-teal-500/20 border border-teal-500/40 text-teal-300 hover:bg-teal-500 hover:text-black transition cursor-pointer" 
                                                    title="Conciliar responsable con Directorio Activo (LDAP)">
                                                <span class="material-symbols-outlined text-[12px]">manage_accounts</span>
                                            </button>
                                            <button type="button" 
                                                    onclick="openQuickAssignModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($d->ip ?? '') }}', '{{ addslashes($d->serial_number ?? $d->serial ?? '') }}', '{{ addslashes($d->custodian_name) }}', '{{ addslashes($d->custodian_id_number ?? '') }}', '{{ addslashes($d->custodian_department ?? '') }}')" 
                                                    class="p-1 rounded bg-cyan-500/20 border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black transition cursor-pointer" 
                                                    title="Formalizar / Reasignar con LDAP (Actualmente: {{ $d->custodian_name }})">
                                                <span class="material-symbols-outlined text-[12px]">person_add</span>
                                            </button>
                                        @else
                                            <span class="p-1 rounded bg-cyan-950/60 border border-cyan-500/40 text-cyan-300" title="Asignado a: {{ $d->custodian_name }}">
                                                <span class="material-symbols-outlined text-[12px]">badge</span>
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <div class="inline-flex items-center justify-center gap-1" title="Sin Asignación">
                                        <span class="p-1 rounded bg-slate-800/80 border border-slate-700/60 text-obsidian-muted" title="Sin Asignación">
                                            <span class="material-symbols-outlined text-[12px]">person_off</span>
                                        </span>
                                        @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                            <button type="button" 
                                                    onclick="openUploadPhysicalScanModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}')" 
                                                    class="p-1 rounded bg-amber-500/15 border border-amber-500/30 text-amber-400 hover:bg-amber-500 hover:text-black transition cursor-pointer" 
                                                    title="Cargar Hoja Física de Asignación Escaneada">
                                                <span class="material-symbols-outlined text-[12px]">upload_file</span>
                                            </button>
                                            <button type="button" 
                                                    onclick="openReconcileLdapModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '', '', '', false)" 
                                                    class="p-1 rounded bg-teal-500/15 border border-teal-500/30 text-teal-400 hover:bg-teal-500 hover:text-black transition cursor-pointer" 
                                                    title="Conciliar responsable con Directorio Activo (LDAP)">
                                                <span class="material-symbols-outlined text-[12px]">manage_accounts</span>
                                            </button>
                                            <button type="button" 
                                                    onclick="openQuickAssignModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($d->ip ?? '') }}', '{{ addslashes($d->serial_number ?? $d->serial ?? '') }}')" 
                                                    class="p-1 rounded bg-cyan-500/20 border border-cyan-500/50 text-cyan-300 hover:bg-cyan-500 hover:text-black transition cursor-pointer shadow-xs" 
                                                    title="Asignar Responsable desde LDAP (Acta UINF-4.05)">
                                                <span class="material-symbols-outlined text-[12px]">person_add</span>
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <!-- MANTENIMIENTO TI -->
                            <td class="py-2 px-3 text-center whitespace-nowrap" onclick="event.stopPropagation()">
                                <div class="inline-flex items-center gap-1">
                                    @if($activeMaint)
                                        <button type="button" onclick="openMaintenanceModal({{ $d->id }})" 
                                                class="p-1 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-500/40 hover:bg-amber-500 hover:text-black transition flex items-center gap-0.5" 
                                                title="Mantenimiento en curso ({{ $activeMaint->status === 'scheduled' ? 'Programado' : 'En Curso' }}): {{ $activeMaint->title }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                            <span class="material-symbols-outlined text-[13px]">engineering</span>
                                        </button>
                                    @else
                                        <span class="p-1 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 inline-flex items-center" 
                                              title="Mantenimiento al día{{ $maintCount > 0 ? " ({$maintCount} órdenes previas)" : '' }}">
                                            <span class="material-symbols-outlined text-[13px]">check_circle</span>
                                        </span>
                                        @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                        <button type="button" onclick="openSingleMaintenanceModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}')" 
                                                class="p-1 rounded bg-amber-500/15 border border-amber-500/30 text-amber-400 hover:bg-amber-500 hover:text-black transition inline-flex items-center cursor-pointer" 
                                                title="Planificar Mantenimiento">
                                            <span class="material-symbols-outlined text-[13px]">calendar_add_on</span>
                                        </button>
                                        <button type="button" onclick="openSingleMobilizationModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($d->asset_tag ?? '') }}')" 
                                                class="p-1 rounded bg-cyan-500/15 border border-cyan-500/30 text-cyan-400 hover:bg-cyan-500 hover:text-black transition inline-flex items-center cursor-pointer" 
                                                title="Movilizar Equipo por Punto de Control">
                                            <span class="material-symbols-outlined text-[13px]">local_shipping</span>
                                        </button>
                                        @endif
                                    @endif

                                    @if($maintCount > 0)
                                        <button type="button" onclick="openMaintenanceModal({{ $d->id }})" 
                                                class="p-1 rounded bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 hover:bg-cyan-500 hover:text-black transition inline-flex items-center" 
                                                title="Ver Historial de Mantenimientos ({{ $maintCount }} órdenes)">
                                            <span class="material-symbols-outlined text-[13px]">history</span>
                                        </button>
                                    @endif
                                </div>
                            </td>

                            <!-- ESTADO Y DESPLIEGUE GLPI -->
                            <td class="py-2 px-3 text-center whitespace-nowrap" onclick="event.stopPropagation()">
                                @php
                                    $isGlpiEligible = $d->is_pc || in_array($d->type, ['desktop', 'laptop', 'server', 'workstation'], true);
                                    $hasGlpi = $d->last_glpi_sync_at !== null;
                                    $glpiTooltip = $hasGlpi 
                                        ? ('GLPI instalado • Último reporte: ' . $d->last_glpi_sync_at->timezone('America/Caracas')->format('d/m/Y h:i A'))
                                        : 'Sin agente GLPI';
                                @endphp
                                <div class="inline-flex items-center gap-1.5 justify-center">
                                    @if(!$isGlpiEligible)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono text-obsidian-muted bg-white/5 border border-obsidian-border/40 cursor-default" title="No aplica agente GLPI para este tipo de dispositivo">
                                            N/A
                                        </span>
                                    @elseif($hasGlpi)
                                        <span class="p-1 rounded-lg inline-flex items-center justify-center bg-emerald-950/70 text-emerald-400 border border-emerald-500/40 shadow-xs shadow-emerald-500/20" 
                                              title="{{ $glpiTooltip }}">
                                            <span class="material-symbols-outlined text-[17px] leading-none block">check_circle</span>
                                        </span>
                                    @else
                                        <span class="p-1 rounded-lg inline-flex items-center justify-center bg-amber-950/70 text-amber-400 border border-amber-500/40 shadow-xs shadow-amber-500/20" 
                                              title="{{ $glpiTooltip }}">
                                            <span class="material-symbols-outlined text-[17px] leading-none block">warning</span>
                                        </span>
                                        <a href="{{ route('admin.inventory.deploy', ['search' => $d->ip]) }}" 
                                           class="p-1 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black transition inline-flex items-center justify-center shadow-xs cursor-pointer" 
                                           title="Instalar GLPI en equipo ({{ $d->ip }})">
                                            <span class="material-symbols-outlined text-[16px] leading-none block">bolt</span>
                                        </a>
                                    @endif
                                </div>
                            </td>

                            <!-- ACCIONES (GESTIÓN DE DISPOSITIVO) -->
                            <td class="py-2 px-4 text-right space-x-1 whitespace-nowrap" onclick="event.stopPropagation()">
                                <!-- BOTÓN: HISTÓRICO DE LATENCIA Y UPTIME -->
                                <button type="button" 
                                        onclick="openDeviceHistoryModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ $d->ip }}', '{{ $d->device_number ?: $d->id }}')" 
                                        class="p-1 rounded bg-obsidian-panel border border-obsidian-purple/40 text-obsidian-purple hover:bg-obsidian-purple hover:text-white transition cursor-pointer shadow-xs inline-flex items-center" 
                                        title="Ver Histórico de Conexión y Latencia">
                                    <span class="material-symbols-outlined text-[13px]">show_chart</span>
                                </button>

                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.manage_devices'))
                                    @if($isClusterSlave ?? false)
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
                                        <form action="{{ route('admin.devices.destroy', $d->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Está seguro de eliminar el dispositivo [{{ addslashes($d->display_identity) }}] ({{ $d->ip }})? Esta acción se sincronizará inmediatamente en las sedes.');">
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

                        <!-- ============================================================= -->
                        <!-- FILA EXPANDIBLE: ACORDEÓN FICHA TÉCNICA ITAM              -->
                        <!-- ============================================================= -->
                        <tr id="accordion-row-{{ $d->id }}" class="hidden bg-[#030812] border-b border-cyan-500/30">
                            <td colspan="12" class="p-4 sm:p-5 space-y-4">
                                @if($isPc)
                                    <!-- CASO PC: ESPECIFICACIONES GLPI, 4 PERIFÉRICOS, RESPONSABLE E HISTORIAL -->
                                    <div>
                                        <!-- CINTA DE ESPECIFICACIONES TÉCNICAS (4 BLOQUES ESTRUCTURADOS CON KEY-VALUE) -->
                                        @php
                                            $cleanDisks = $disks->filter(function($dk) {
                                                $n = strtolower($dk->name ?? '');
                                                return !str_starts_with($n, '/dev/loop') 
                                                    && !str_starts_with($n, 'efivarfs') 
                                                    && !preg_match('#^/dev/sd[a-z][0-9]#', $n)
                                                    && ($dk->capacity || $dk->serial_number);
                                            });
                                            if ($cleanDisks->isEmpty()) {
                                                $cleanDisks = $disks->filter(function($dk) {
                                                    $n = strtolower($dk->name ?? '');
                                                    return !str_starts_with($n, '/dev/loop') && !str_starts_with($n, 'efivarfs');
                                                });
                                            }
                                            $ramSerials = $rams->pluck('serial_number')->filter()->values();
                                        @endphp
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs font-mono mb-4">
                                            <!-- 1. CHASIS & IDENTIFICACIÓN -->
                                            <div class="p-3 rounded-xl bg-[#060e1c] border border-obsidian-border/80 flex flex-col justify-between">
                                                <div>
                                                    <div class="text-[10.5px] text-cyan-400 uppercase font-bold flex items-center gap-1.5 pb-2 border-b border-obsidian-border/50 mb-2">
                                                        <span class="material-symbols-outlined text-sm text-cyan-400">branding_watermark</span>
                                                        <span>Chasis &amp; Identidad</span>
                                                    </div>
                                                    <div class="space-y-1.5 text-[11px]">
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">Marca:</span>
                                                            <span class="text-white font-medium text-right truncate max-w-[155px]" title="{{ $d->brand ?: ($d->vendor_data ?: 'Fabricante no especificado') }}">
                                                                {{ $d->brand ?: ($d->vendor_data ?: 'Estándar / Genérico') }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">Modelo:</span>
                                                            <span class="text-cyan-300 font-semibold text-right truncate max-w-[155px]" title="{{ $d->model ?: 'Sin modelo' }}">
                                                                {{ $d->model ?: 'Sin modelo' }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">Serial:</span>
                                                            <span class="text-amber-300 font-mono font-medium text-right truncate max-w-[155px]" title="{{ $d->serial ?: ($d->serial_number ?: 'Sin Serial') }}">
                                                                {{ $d->serial ?: ($d->serial_number ?: 'Sin Serial') }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">Etiqueta:</span>
                                                            <span class="text-slate-300 text-right truncate max-w-[155px]">
                                                                #{{ $d->device_number ?: $d->id }} • {{ $d->asset_tag ?: 'S/TAG' }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 2. CPU & SISTEMA OPERATIVO -->
                                            <div class="p-3 rounded-xl bg-[#060e1c] border border-obsidian-border/80 flex flex-col justify-between">
                                                <div>
                                                    <div class="text-[10.5px] text-cyan-400 uppercase font-bold flex items-center gap-1.5 pb-2 border-b border-obsidian-border/50 mb-2">
                                                        <span class="material-symbols-outlined text-sm text-cyan-400">memory</span>
                                                        <span>CPU &amp; Sistema</span>
                                                    </div>
                                                    <div class="space-y-1.5 text-[11px]">
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">Procesador:</span>
                                                            <span class="text-white font-medium text-right truncate max-w-[155px]" title="{{ $cpu?->name ?? 'Intel / AMD' }}">
                                                                {{ $cpu?->name ?? 'Intel / AMD (Base)' }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">Frecuencia:</span>
                                                            <span class="text-slate-300 text-right truncate max-w-[155px]">
                                                                {{ $cpu?->capacity ?? 'Velocidad base' }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">S.O.:</span>
                                                            <span class="text-white text-right truncate max-w-[155px]" title="{{ $d->operating_system ?? 'Linux / Windows' }}">
                                                                {{ $d->operating_system ?? 'Linux / Windows' }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">Red / IP:</span>
                                                            <span class="text-cyan-300 font-mono text-right truncate max-w-[155px]">
                                                                {{ $d->ip }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">MAC:</span>
                                                            <span class="text-slate-400 font-mono text-right text-[10px] truncate max-w-[155px]" title="Dirección MAC: {{ $d->mac ?: 'No asignada' }}">
                                                                {{ $d->mac ?: 'No asignada' }}
                                                            </span>
                                                        </div>
                                                        @if($d->networkInterfaces->count() > 1)
                                                        <div class="flex items-baseline justify-between gap-1 pt-1 border-t border-obsidian-border/40">
                                                            <span class="text-obsidian-muted shrink-0">Interfaces:</span>
                                                            <span class="text-emerald-400 font-medium text-right text-[10.5px] cursor-help" title="{{ $d->networkInterfaces->map(fn($n) => $n->interface_name . ($n->is_primary ? ' [Principal]' : '') . ': ' . ($n->ip_address ?: 'sin IP') . ' (' . ($n->mac_address ?: 'S/MAC') . ')')->implode(' | ') }}">
                                                                {{ $d->networkInterfaces->count() }} NICs configuradas
                                                            </span>
                                                        </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 3. MEMORIA RAM -->
                                            <div class="p-3 rounded-xl bg-[#060e1c] border border-obsidian-border/80 flex flex-col justify-between">
                                                <div>
                                                    <div class="text-[10.5px] text-cyan-400 uppercase font-bold flex items-center gap-1.5 pb-2 border-b border-obsidian-border/50 mb-2">
                                                        <span class="material-symbols-outlined text-sm text-cyan-400">developer_board</span>
                                                        <span>Memoria RAM</span>
                                                    </div>
                                                    <div class="space-y-1.5 text-[11px]">
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">Capacidad:</span>
                                                            <span class="text-white font-semibold text-right truncate max-w-[155px]">
                                                                {{ $rams->isNotEmpty() ? $rams->pluck('capacity')->filter()->implode(' + ') : 'RAM no especificada' }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">Bancos:</span>
                                                            <span class="text-slate-300 text-right">
                                                                {{ $rams->count() > 0 ? $rams->count() . ' banco(s) detectado(s)' : '1 banco' }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">Serial:</span>
                                                            <span class="text-amber-300 font-mono text-right truncate max-w-[155px]" title="{{ $ramSerials->isNotEmpty() ? $ramSerials->implode(', ') : 'No reportado por agente' }}">
                                                                {{ $ramSerials->isNotEmpty() ? $ramSerials->implode(', ') : 'No reportado' }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-baseline justify-between gap-1">
                                                            <span class="text-obsidian-muted shrink-0">Tipo:</span>
                                                            <span class="text-slate-300 text-right truncate max-w-[155px]">
                                                                {{ $rams->first()?->brand ?: ($rams->first()?->model ?: 'DDR3 / DDR4') }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 4. ALMACENAMIENTO (DISCOS) -->
                                            <div class="p-3 rounded-xl bg-[#060e1c] border border-obsidian-border/80 flex flex-col justify-between">
                                                <div>
                                                    <div class="text-[10.5px] text-cyan-400 uppercase font-bold flex items-center gap-1.5 pb-2 border-b border-obsidian-border/50 mb-2">
                                                        <span class="material-symbols-outlined text-sm text-cyan-400">hard_drive</span>
                                                        <span>Almacenamiento</span>
                                                    </div>
                                                    @if($cleanDisks->count() === 1)
                                                        @php 
                                                            $disk = $cleanDisks->first(); 
                                                            $cleanDiskName = trim(str_replace('(Unidades de disco estándar)', '', $disk->name ?? 'Disco rígido'));
                                                        @endphp
                                                        <div class="space-y-1.5 text-[11px]">
                                                            <div class="flex items-baseline justify-between gap-1">
                                                                <span class="text-obsidian-muted shrink-0">Capacidad:</span>
                                                                <span class="text-white font-semibold text-right">{{ $disk->capacity ?: '500 GB' }}</span>
                                                            </div>
                                                            <div class="flex items-baseline justify-between gap-1">
                                                                <span class="text-obsidian-muted shrink-0">Modelo:</span>
                                                                <span class="text-cyan-300 font-medium text-right truncate max-w-[155px]" title="{{ $cleanDiskName }}">{{ $cleanDiskName }}</span>
                                                            </div>
                                                            <div class="flex items-baseline justify-between gap-1">
                                                                <span class="text-obsidian-muted shrink-0">Serial:</span>
                                                                <span class="text-amber-300 font-mono text-right truncate max-w-[155px]" title="{{ $disk->serial_number ?: 'Sin Serial' }}">
                                                                    {{ $disk->serial_number ?: 'No reportado' }}
                                                                </span>
                                                            </div>
                                                            <div class="flex items-baseline justify-between gap-1">
                                                                <span class="text-obsidian-muted shrink-0">Unidades:</span>
                                                                <span class="text-slate-300 text-right">1 unidad física</span>
                                                            </div>
                                                        </div>
                                                    @elseif($cleanDisks->count() > 1)
                                                        <div class="space-y-2 text-[11px]">
                                                            @foreach($cleanDisks->take(2) as $idx => $dk)
                                                                @php $cleanDiskName = trim(str_replace('(Unidades de disco estándar)', '', $dk->name ?? 'Disco ' . ($idx + 1))); @endphp
                                                                <div class="{{ !$loop->last ? 'pb-1.5 border-b border-obsidian-border/40' : '' }}">
                                                                    <div class="flex items-baseline justify-between text-[10.5px]">
                                                                        <span class="text-white font-semibold truncate max-w-[130px]" title="{{ $cleanDiskName }}">{{ $cleanDiskName }}</span>
                                                                        <span class="text-cyan-300 font-bold">{{ $dk->capacity ?: '500 GB' }}</span>
                                                                    </div>
                                                                    <div class="flex items-baseline justify-between text-[10px] mt-0.5">
                                                                        <span class="text-obsidian-muted">Serial:</span>
                                                                        <span class="text-amber-300 font-mono truncate max-w-[140px]" title="{{ $dk->serial_number ?: 'Sin Serial' }}">
                                                                            {{ $dk->serial_number ?: 'No reportado' }}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                            @if($cleanDisks->count() > 2)
                                                                <div class="text-[9.5px] text-obsidian-muted italic text-center">+ {{ $cleanDisks->count() - 2 }} unidad(es) adicional(es)</div>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <div class="space-y-1.5 text-[11px]">
                                                            <div class="flex items-baseline justify-between gap-1">
                                                                <span class="text-obsidian-muted shrink-0">Capacidad:</span>
                                                                <span class="text-white font-medium text-right">Estándar</span>
                                                            </div>
                                                            <div class="flex items-baseline justify-between gap-1">
                                                                <span class="text-obsidian-muted shrink-0">Modelo:</span>
                                                                <span class="text-cyan-300 text-right truncate max-w-[155px]">Disco rígido</span>
                                                            </div>
                                                            <div class="flex items-baseline justify-between gap-1">
                                                                <span class="text-obsidian-muted shrink-0">Serial:</span>
                                                                <span class="text-amber-300 font-mono text-right">No reportado</span>
                                                            </div>
                                                            <div class="flex items-baseline justify-between gap-1">
                                                                <span class="text-obsidian-muted shrink-0">Unidades:</span>
                                                                <span class="text-slate-300 text-right">1 unidad</span>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        @if($d->notes)
                                            <!-- NOTAS TÉCNICAS & OBSERVACIONES DEL PUESTO -->
                                            <div class="p-3 rounded-xl bg-[#040a17] border border-cyan-500/30 mb-4 space-y-1.5 font-mono text-xs">
                                                <div class="flex items-center justify-between pb-1 border-b border-obsidian-border/50">
                                                    <span class="text-[10.5px] uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                                                        <span class="material-symbols-outlined text-sm">sticky_note_2</span>
                                                        <span>Notas Técnicas &amp; Observaciones</span>
                                                    </span>
                                                    <span class="text-[9.5px] text-obsidian-muted">Metadatos de Puesto / NetBIOS</span>
                                                </div>
                                                <div class="p-2.5 rounded-lg bg-black/40 border border-obsidian-border/40 text-slate-200 whitespace-pre-line leading-relaxed text-[11px]">{!! nl2br(e($d->notes)) !!}</div>
                                            </div>
                                        @endif

                                        <!-- CUADRO DE 4 PERIFÉRICOS ASOCIADOS AL PUESTO DE TRABAJO -->
                                        <div class="space-y-2 mb-4">
                                            <div class="flex items-center justify-between">
                                                <h4 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                                                    <span class="material-symbols-outlined text-base">desktop_mac</span>
                                                    <span>Periféricos de Puesto de Trabajo (Monitor, Teclado, Mouse, Regulador/UPS)</span>
                                                </h4>
                                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.manage_devices'))
                                                    <button type="button" 
                                                            onclick="openDeviceEditModal({{ json_encode($d) }}, 'peripherals')" 
                                                            class="px-2.5 py-1 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-300 hover:text-black border border-amber-500/30 text-[10.5px] font-mono font-bold transition inline-flex items-center gap-1 shadow-xs cursor-pointer"
                                                            title="Editar puesto y configurar periféricos en modal central">
                                                        <span class="material-symbols-outlined text-xs">edit</span>
                                                        <span>Gestionar Puesto &amp; Periféricos</span>
                                                    </button>
                                                @else
                                                    <span class="text-[10px] text-obsidian-muted font-mono">Dispositivos vinculados a esta estación</span>
                                                @endif
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                                <!-- 1. MONITOR -->
                                                <div class="p-3 rounded-xl border {{ $monitor ? 'bg-[#060e1c] border-cyan-500/40' : 'bg-[#040914] border-dashed border-obsidian-border' }} relative transition">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <div class="flex items-center gap-1.5 text-xs font-bold {{ $monitor ? 'text-cyan-300' : 'text-obsidian-muted' }}">
                                                            <span class="material-symbols-outlined text-base">monitor</span>
                                                            <span>Monitor</span>
                                                            @if($extraPeriphs->where('type', 'monitor')->count() > 0)
                                                                <span class="px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-cyan-950 text-cyan-300 border border-cyan-800" title="Configuración multimonitor: Pantalla principal">Principal</span>
                                                            @endif
                                                        </div>
                                                        @if($monitor)
                                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-800">
                                                                {{ $monitor->status_label }}
                                                            </span>
                                                        @else
                                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-900 text-slate-500 border border-slate-800">
                                                                No Asignado
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if($monitor)
                                                        <div class="space-y-1 text-xs font-mono">
                                                            <div class="text-white font-semibold truncate">{{ $monitor->brand }} {{ $monitor->model }}</div>
                                                            <div class="text-[10px] text-slate-400 truncate">S/N: <code>{{ $monitor->serial_number ?: 'Sin Serial' }}</code></div>
                                                            <div class="text-[9.5px] text-obsidian-muted truncate">Tag: {{ $monitor->asset_tag }}</div>
                                                        </div>
                                                    @else
                                                        <div class="text-center py-2 text-obsidian-muted text-xs">
                                                            <p class="text-[10.5px] italic">Sin monitor asociado</p>
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- 2. TECLADO -->
                                                <div class="p-3 rounded-xl border {{ $keyboard ? 'bg-[#060e1c] border-cyan-500/40' : 'bg-[#040914] border-dashed border-obsidian-border' }} relative transition">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <div class="flex items-center gap-1.5 text-xs font-bold {{ $keyboard ? 'text-cyan-300' : 'text-obsidian-muted' }}">
                                                            <span class="material-symbols-outlined text-base">keyboard</span>
                                                            <span>Teclado</span>
                                                        </div>
                                                        @if($keyboard)
                                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-800">
                                                                {{ $keyboard->status_label }}
                                                            </span>
                                                        @else
                                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-900 text-slate-500 border border-slate-800">
                                                                No Asignado
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if($keyboard)
                                                        <div class="space-y-1 text-xs font-mono">
                                                            <div class="text-white font-semibold truncate">{{ $keyboard->brand }} {{ $keyboard->model ?: 'Estándar USB' }}</div>
                                                            <div class="text-[10px] text-slate-400 truncate">S/N: <code>{{ $keyboard->serial_number ?: 'Sin Serial' }}</code></div>
                                                            <div class="text-[9.5px] text-obsidian-muted truncate">Tag: {{ $keyboard->asset_tag }}</div>
                                                        </div>
                                                    @else
                                                        <div class="text-center py-2 text-obsidian-muted text-xs">
                                                            <p class="text-[10.5px] italic">Sin teclado asociado</p>
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- 3. MOUSE -->
                                                <div class="p-3 rounded-xl border {{ $mouse ? 'bg-[#060e1c] border-cyan-500/40' : 'bg-[#040914] border-dashed border-obsidian-border' }} relative transition">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <div class="flex items-center gap-1.5 text-xs font-bold {{ $mouse ? 'text-cyan-300' : 'text-obsidian-muted' }}">
                                                            <span class="material-symbols-outlined text-base">mouse</span>
                                                            <span>Mouse / Ratón</span>
                                                        </div>
                                                        @if($mouse)
                                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-800">
                                                                {{ $mouse->status_label }}
                                                            </span>
                                                        @else
                                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-900 text-slate-500 border border-slate-800">
                                                                No Asignado
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if($mouse)
                                                        <div class="space-y-1 text-xs font-mono">
                                                            <div class="text-white font-semibold truncate">{{ $mouse->brand }} {{ $mouse->model ?: 'Óptico USB' }}</div>
                                                            <div class="text-[10px] text-slate-400 truncate">S/N: <code>{{ $mouse->serial_number ?: 'Sin Serial' }}</code></div>
                                                            <div class="text-[9.5px] text-obsidian-muted truncate">Tag: {{ $mouse->asset_tag }}</div>
                                                        </div>
                                                    @else
                                                        <div class="text-center py-2 text-obsidian-muted text-xs">
                                                            <p class="text-[10.5px] italic">Sin mouse asociado</p>
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- 4. PROTECTOR / UPS -->
                                                <div class="p-3 rounded-xl border {{ $protector ? 'bg-[#060e1c] border-cyan-500/40' : 'bg-[#040914] border-dashed border-obsidian-border' }} relative transition">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <div class="flex items-center gap-1.5 text-xs font-bold {{ $protector ? 'text-cyan-300' : 'text-obsidian-muted' }}">
                                                            <span class="material-symbols-outlined text-base">bolt</span>
                                                            <span>{{ $protector && $protector->type === 'ups' ? 'Sistema UPS' : 'Protector / Regulador' }}</span>
                                                        </div>
                                                        @if($protector)
                                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-800">
                                                                {{ $protector->status_label }}
                                                            </span>
                                                        @else
                                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-900 text-slate-500 border border-slate-800">
                                                                No Asignado
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if($protector)
                                                        <div class="space-y-1 text-xs font-mono">
                                                            <div class="text-white font-semibold truncate">{{ $protector->brand }} {{ $protector->model ?: 'Regulador de Voltaje' }}</div>
                                                            <div class="text-[10px] text-slate-400 truncate">S/N: <code>{{ $protector->serial_number ?: 'Sin Serial' }}</code></div>
                                                            <div class="text-[9.5px] text-obsidian-muted truncate">Tag: {{ $protector->asset_tag }}</div>
                                                        </div>
                                                        @if($protector->type === 'ups')
                                                            <div class="mt-2.5 pt-2 border-t border-obsidian-border/50 flex items-center justify-end">
                                                                <a href="{{ route('admin.ups.index') }}" class="text-[10px] text-cyan-400 hover:text-cyan-300 hover:underline font-mono inline-flex items-center gap-1 font-bold" title="Ver Monitoreo y Telemetría en Vivo del UPS">
                                                                    <span class="material-symbols-outlined text-xs">battery_charging_full</span>
                                                                    <span>Ver Telemetría UPS</span>
                                                                </a>
                                                            </div>
                                                        @endif
                                                    @else
                                                        <div class="text-center py-2 text-obsidian-muted text-xs">
                                                            <p class="text-[10.5px] italic">Sin protector asociado</p>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            @if($extraPeriphs->isNotEmpty())
                                                <!-- HARDWARE COMPLEMENTARIO & PERIFÉRICOS ADICIONALES -->
                                                <div class="mt-3 pt-3 border-t border-obsidian-border/40">
                                                    <div class="text-[11px] font-mono uppercase font-bold text-amber-400/90 flex items-center justify-between mb-2">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="material-symbols-outlined text-sm text-amber-400">devices_other</span>
                                                            <span>Hardware Complementario &amp; Periféricos Adicionales</span>
                                                        </div>
                                                        <span class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-amber-950/80 text-amber-300 border border-amber-800">
                                                            {{ $extraPeriphs->count() }} adicional(es)
                                                        </span>
                                                    </div>
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                                        @foreach($extraPeriphs as $ep)
                                                            @php
                                                                $epIcon = match($ep->type) {
                                                                    'monitor' => 'monitor',
                                                                    'printer' => 'print',
                                                                    'scanner' => 'document_scanner',
                                                                    'headset' => 'headphones',
                                                                    'webcam' => 'videocam',
                                                                    'keyboard' => 'keyboard',
                                                                    'mouse' => 'mouse',
                                                                    default => 'devices_other'
                                                                };
                                                                $epTitle = match($ep->type) {
                                                                    'monitor' => 'Monitor Secundario',
                                                                    'printer' => 'Impresora / Multifuncional',
                                                                    'scanner' => 'Escáner / Lector',
                                                                    'headset' => 'Auriculares / Headset',
                                                                    'webcam' => 'Cámara Web',
                                                                    'keyboard' => 'Teclado Adicional',
                                                                    'mouse' => 'Mouse Adicional',
                                                                    default => 'Periférico Adicional'
                                                                };
                                                            @endphp
                                                            <div class="p-3 rounded-xl border bg-[#060e1c] border-amber-500/30 relative transition">
                                                                <div class="flex items-center justify-between mb-2">
                                                                    <div class="flex items-center gap-1.5 text-xs font-bold text-amber-300">
                                                                        <span class="material-symbols-outlined text-base">{{ $epIcon }}</span>
                                                                        <span class="truncate max-w-[130px]">{{ $epTitle }}</span>
                                                                    </div>
                                                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-950/80 text-amber-300 border border-amber-800">
                                                                        {{ $ep->status_label ?? 'Asignado' }}
                                                                    </span>
                                                                </div>
                                                                <div class="space-y-1 text-xs font-mono">
                                                                    <div class="text-white font-semibold truncate" title="{{ $ep->brand }} {{ $ep->model }}">
                                                                        {{ $ep->brand }} {{ $ep->model ?: 'Genérico' }}
                                                                    </div>
                                                                    <div class="text-[10px] text-slate-400 truncate">
                                                                        S/N: <code>{{ $ep->serial_number ?: 'Sin Serial' }}</code>
                                                                    </div>
                                                                    <div class="text-[9.5px] text-obsidian-muted truncate">
                                                                        Tag: {{ $ep->asset_tag }}
                                                                    </div>
                                                                    @if($ep->notes)
                                                                        <div class="text-[9px] text-amber-300/80 truncate font-mono" title="{{ $ep->notes }}">
                                                                            Obs: {{ $ep->notes }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- ALERTA DE AUDITORÍA: HARDWARE MODIFICADO POSTERIOR AL ACTA -->
                                        @if($d->has_outdated_assignment)
                                        <div class="p-3.5 rounded-xl bg-amber-950/40 border border-amber-500/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-md mb-3">
                                            <div class="flex items-start gap-2.5">
                                                <span class="p-1.5 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                                    <span class="material-symbols-outlined text-lg">warning</span>
                                                </span>
                                                <div>
                                                    <div class="text-xs font-mono font-bold text-amber-300">
                                                        Alerta de Auditoría: Hardware Modificado Posterior al Acta
                                                    </div>
                                                    <p class="text-[11px] text-slate-300 font-mono mt-0.5">
                                                        Los componentes o periféricos de este puesto fueron modificados tras emitir {{ $activeAssignment?->assignment_code ?? 'el acta activa' }}. Debe generarse una nueva planilla UINF-4.05 para formalizar los cambios.
                                                    </p>
                                                </div>
                                            </div>
                                            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                                            <a href="{{ route('admin.inventory.assignments.create', ['asset_id' => $d->id, 'return_to' => url()->full()]) }}" 
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-mono text-xs font-bold transition shadow-sm cursor-pointer whitespace-nowrap">
                                                <span class="material-symbols-outlined text-base">post_add</span>
                                                <span>Renovar Acta UINF-4.05</span>
                                            </a>
                                            @endif
                                        </div>
                                        @endif

                                        <!-- RESGUARDO Y ACTA DE ASIGNACIÓN (UINF-4.05) -->
                                        <div class="p-3.5 rounded-xl border {{ $activeAssignment && $activeAssignment->document_pdf_path ? 'bg-[#051410] border-emerald-500/40' : ($activeAssignment && $activeAssignment->scan_compliance_status === 'overdue' ? 'bg-[#1e0a0a] border-rose-500/50' : ($activeAssignment ? 'bg-[#151205] border-amber-500/40' : 'bg-[#040914] border-obsidian-border')) }} flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs font-mono mb-3">
                                            <div class="flex items-start gap-3">
                                                <span class="p-2 rounded-lg {{ $activeAssignment && $activeAssignment->document_pdf_path ? 'bg-emerald-500/20 text-emerald-400' : ($activeAssignment && $activeAssignment->scan_compliance_status === 'overdue' ? 'bg-rose-500/20 text-rose-300' : ($activeAssignment ? 'bg-amber-500/20 text-amber-400' : 'bg-slate-800 text-slate-400')) }}">
                                                    <span class="material-symbols-outlined text-xl">
                                                        {{ $activeAssignment && $activeAssignment->document_pdf_path ? 'verified' : ($activeAssignment && $activeAssignment->scan_compliance_status === 'overdue' ? 'emergency' : ($activeAssignment ? 'schedule' : 'person_off')) }}
                                                    </span>
                                                </span>
                                                <div>
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span class="font-bold uppercase tracking-wider {{ $activeAssignment && $activeAssignment->document_pdf_path ? 'text-emerald-300' : ($activeAssignment && $activeAssignment->scan_compliance_status === 'overdue' ? 'text-rose-300' : ($activeAssignment ? 'text-amber-300' : 'text-slate-300')) }}">
                                                            @if($activeAssignment && $activeAssignment->document_pdf_path)
                                                                Hoja Física Escaneada &amp; Resguardada
                                                            @elseif($activeAssignment && $activeAssignment->scan_compliance_status === 'overdue')
                                                                Soporte Digital Pendiente de Carga
                                                            @elseif($activeAssignment)
                                                                Asignación Activa (Soporte Digital Pendiente)
                                                            @else
                                                                Activo sin Acta de Asignación registrada
                                                            @endif
                                                        </span>
                                                        @if($activeAssignment)
                                                            <span class="px-2 py-0.2 rounded text-[9.5px] font-mono font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                                {{ $activeAssignment->assignment_code }}
                                                            </span>
                                                            @if($activeAssignment->has_signed_scan)
                                                                <span class="px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-emerald-950 text-emerald-300 border border-emerald-800">
                                                                    SOPORTE VALIDADO
                                                                </span>
                                                            @elseif($activeAssignment->scan_compliance_status === 'overdue')
                                                                <span class="px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-rose-950 text-rose-300 border border-rose-800">
                                                                    DIGITALIZACIÓN PENDIENTE
                                                                </span>
                                                            @endif
                                                        @endif
                                                    </div>
                                                    @if($activeAssignment)
                                                        <div class="text-slate-300 mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px]">
                                                            <span>Asignado a: <strong class="text-white">{{ $activeAssignment->custodian_name }}</strong></span>
                                                            @if($activeAssignment->custodian_id_number)
                                                                <span>• C.I: <span class="text-slate-400">{{ $activeAssignment->custodian_id_number }}</span></span>
                                                            @endif
                                                            @if($activeAssignment->custodian_department)
                                                                <span>• Dpto: <span class="text-slate-400">{{ $activeAssignment->custodian_department }}</span></span>
                                                            @endif
                                                            <span>• Fecha: <span class="text-cyan-400">{{ $activeAssignment->assigned_at ? $activeAssignment->assigned_at->format('d/m/Y') : 'N/A' }}</span></span>
                                                            <span>• Días transcurridos: <span class="text-slate-400">{{ $activeAssignment->days_since_assigned }} días</span></span>
                                                        </div>
                                                    @else
                                                        <p class="text-[11px] text-obsidian-muted mt-0.5">
                                                            Este equipo se encuentra en inventario disponible y no posee Acta de Asignación registrada.
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>

                                             <!-- ACCIONES DE ASIGNACIÓN Y DOCUMENTO -->
                                             <div class="flex items-center gap-2 flex-wrap">
                                                 @if($activeAssignment && $activeAssignment->document_pdf_path)
                                                     <a href="{{ route('admin.inventory.assignments.view-scan', $activeAssignment->id) }}" target="_blank" 
                                                        class="px-3 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black font-bold transition flex items-center gap-1.5 text-xs shadow-sm">
                                                         <span class="material-symbols-outlined text-base">visibility</span>
                                                         <span>Ver Hoja Escaneada</span>
                                                     </a>
                                                     <a href="{{ route('admin.inventory.assignments.pdf', $activeAssignment->id) }}" target="_blank" 
                                                        class="px-2.5 py-1.5 rounded-xl bg-red-950/40 border border-red-800/60 text-red-400 hover:bg-red-500 hover:text-white transition flex items-center gap-1 text-xs" title="Descargar Acta UINF-4.05 (PDF)">
                                                         <span class="material-symbols-outlined text-base">picture_as_pdf</span>
                                                         <span>Acta Digital</span>
                                                     </a>
                                                     @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                                     <button type="button" 
                                                             onclick="openReconcileLdapModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}', true)"
                                                             class="px-2.5 py-1.5 rounded-xl bg-cyan-950/40 border border-cyan-800/60 text-cyan-300 hover:bg-cyan-500 hover:text-black font-semibold transition flex items-center gap-1 text-xs" title="Conciliar responsable con Directorio Activo (LDAP)">
                                                         <span class="material-symbols-outlined text-base">manage_accounts</span>
                                                         <span>Conciliar LDAP</span>
                                                     </button>
                                                     <button type="button" 
                                                             onclick="openUploadPhysicalScanModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}')"
                                                             class="px-2.5 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition flex items-center gap-1 text-xs" title="Reemplazar archivo digitalizado">
                                                         <span class="material-symbols-outlined text-base">sync</span>
                                                         <span>Reemplazar</span>
                                                     </button>
                                                     @endif
                                                 @elseif($activeAssignment)
                                                     @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                                     <button type="button" 
                                                             onclick="openUploadPhysicalScanModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}')"
                                                             class="px-3 py-1.5 rounded-xl {{ $activeAssignment->scan_compliance_status === 'overdue' ? 'bg-rose-500 hover:bg-rose-400 text-white' : 'bg-amber-500 hover:bg-amber-400 text-black' }} font-bold transition flex items-center gap-1.5 text-xs shadow-sm">
                                                         <span class="material-symbols-outlined text-base">upload_file</span>
                                                         <span>{{ $activeAssignment->scan_compliance_status === 'overdue' ? 'Subir Soporte Escaneado' : 'Subir Hoja Física Escaneada' }}</span>
                                                     </button>
                                                     <button type="button" 
                                                             onclick="openReconcileLdapModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}', false)"
                                                             class="px-2.5 py-1.5 rounded-xl bg-cyan-950/40 border border-cyan-800/60 text-cyan-300 hover:bg-cyan-500 hover:text-black font-semibold transition flex items-center gap-1 text-xs" title="Conciliar responsable con Directorio Activo (LDAP)">
                                                         <span class="material-symbols-outlined text-base">manage_accounts</span>
                                                         <span>Conciliar LDAP</span>
                                                     </button>
                                                     @endif
                                                     <a href="{{ route('admin.inventory.assignments.pdf', $activeAssignment->id) }}" target="_blank" 
                                                         class="px-2.5 py-1.5 rounded-xl bg-red-950/40 border border-red-800/60 text-red-400 hover:bg-red-500 hover:text-white transition flex items-center gap-1 text-xs">
                                                         <span class="material-symbols-outlined text-base">picture_as_pdf</span>
                                                         <span>Acta Digital UINF-4.05</span>
                                                     </a>
                                                 @else
                                                     @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                                                     <button type="button" 
                                                             onclick="openUploadPhysicalScanModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}')"
                                                             class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold transition flex items-center gap-1.5 text-xs shadow-sm">
                                                         <span class="material-symbols-outlined text-base">upload_file</span>
                                                         <span>Subir Hoja Física Existente</span>
                                                     </button>
                                                     <button type="button" 
                                                             onclick="openReconcileLdapModal({{ $d->id }}, '{{ addslashes($d->display_identity) }}', '{{ addslashes($d->assigned_to ?? '') }}', '', '', false)"
                                                             class="px-2.5 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 hover:bg-cyan-500 hover:text-black font-semibold transition flex items-center gap-1 text-xs" title="Conciliar responsable con Directorio Activo (LDAP)">
                                                         <span class="material-symbols-outlined text-base">manage_accounts</span>
                                                         <span>Conciliar LDAP</span>
                                                     </button>
                                                     <a href="{{ route('admin.inventory.assignments.create', ['asset_id' => $d->id, 'return_to' => url()->full()]) }}" 
                                                         class="px-3 py-1.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500 border border-cyan-500/40 text-cyan-300 hover:text-black font-bold transition flex items-center gap-1.5 text-xs">
                                                         <span class="material-symbols-outlined text-base">assignment_add</span>
                                                         <span>Emitir Acta en Sistema</span>
                                                     </a>
                                                     @endif
                                                 @endif
                                             </div>
                                         </div>

                                        <!-- HISTÓRICO DE ACTAS ASIGNACION/MOVILIZACION -->
                                        @php
                                            $historicalAssignments = $d->assignments->where('id', '!=', $activeAssignment?->id);
                                        @endphp
                                        @if($historicalAssignments->isNotEmpty())
                                        <div class="p-4 rounded-xl bg-[#040914] border border-obsidian-border space-y-3 mb-3">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-cyan-400 text-lg">history_edu</span>
                                                    <h4 class="text-xs font-mono uppercase font-bold text-white">
                                                        Histórico de Actas Asignacion/Movilizacion
                                                    </h4>
                                                    <span class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                        {{ $historicalAssignments->count() }} actas
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="overflow-x-auto max-h-56 overflow-y-auto rounded-lg border border-obsidian-border/50 custom-scrollbar">
                                                <table class="w-full text-left text-xs font-mono">
                                                    <thead class="bg-[#02050c] text-[10px] uppercase text-obsidian-muted border-b border-obsidian-border sticky top-0 z-10">
                                                        <tr>
                                                            <th class="py-2 px-3 whitespace-nowrap">Acta / Código</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Tipo de Acta</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Usuario / Responsable</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Fecha y Hora</th>
                                                            <th class="py-2 px-3 text-right whitespace-nowrap">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-obsidian-border/30">
                                                        @foreach($historicalAssignments as $hAsg)
                                                        <tr class="hover:bg-cyan-950/20 transition-colors">
                                                            <td class="py-2 px-3 whitespace-nowrap">
                                                                <span class="text-cyan-300 font-bold">{{ $hAsg->assignment_code }}</span>
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap">
                                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                                    <span class="text-slate-300 font-semibold">{{ $hAsg->movement_type_label }}</span>
                                                                    <span class="px-1.5 py-0.2 rounded text-[8.5px] font-bold {{ $hAsg->status === 'returned' ? 'bg-blue-950 text-blue-300 border border-blue-800' : 'bg-slate-800 text-slate-400 border border-slate-700' }}">
                                                                        {{ strtoupper($hAsg->status === 'superseded' ? 'HISTÓRICA' : $hAsg->status) }}
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap">
                                                                <span class="text-slate-200">{{ $hAsg->custodian_name }}</span>
                                                                @if($hAsg->custodian_department)
                                                                    <span class="text-obsidian-muted text-[10px] block">{{ $hAsg->custodian_department }}</span>
                                                                @endif
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap text-slate-300">
                                                                @if($hAsg->assigned_at)
                                                                    <div class="text-[11px]">{{ $hAsg->assigned_at->format('d/m/Y') }}</div>
                                                                    <div class="text-[9.5px] text-obsidian-muted">{{ $hAsg->assigned_at->format('h:i A') }}</div>
                                                                @else
                                                                    <span class="text-obsidian-muted text-[10px]">N/A</span>
                                                                @endif
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap text-right">
                                                                <div class="inline-flex items-center gap-1.5">
                                                                    @if($hAsg->document_pdf_path)
                                                                    <a href="{{ route('admin.inventory.assignments.view-scan', $hAsg->id) }}" target="_blank" class="p-1 rounded bg-purple-950/70 border border-purple-500/60 text-purple-300 hover:bg-purple-500 hover:text-white transition inline-flex items-center justify-center" title="Ver Hoja Escaneada">
                                                                        <span class="material-symbols-outlined text-[13px]">file_present</span>
                                                                    </a>
                                                                    @endif
                                                                    <a href="{{ route('admin.inventory.assignments.pdf', $hAsg->id) }}" target="_blank" class="p-1 rounded bg-emerald-950/70 border border-emerald-500/60 text-emerald-300 hover:bg-emerald-500 hover:text-white transition inline-flex items-center justify-center" title="Ver Acta">
                                                                        <span class="material-symbols-outlined text-[13px]">description</span>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        @endif

                                        <!-- TABLA: HISTÓRICO DE MODIFICACIONES Y CAMBIOS EN EL EQUIPO -->
                                        <div class="p-4 rounded-xl bg-[#040914] border border-obsidian-border space-y-3">
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                                <div class="flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-cyan-400 text-lg">history</span>
                                                    <h4 class="text-xs font-mono uppercase font-bold text-white">
                                                        Histórico de Modificaciones y Componentes
                                                    </h4>
                                                    <span class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                        {{ $d->componentHistories->count() }} eventos
                                                    </span>
                                                </div>

                                                <!-- FILTROS POR TIPO DE COMPONENTE -->
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="text-[10px] text-obsidian-muted font-mono">Filtrar:</span>
                                                    <select onchange="filterAssetHistory({{ $d->id }}, this.value)" class="px-2 py-1 bg-[#020610] border border-obsidian-border rounded-lg text-[10px] font-mono text-cyan-300 focus:outline-none">
                                                        <option value="all">Todos los cambios</option>
                                                        <option value="monitor">Monitores</option>
                                                        <option value="keyboard">Teclados</option>
                                                        <option value="mouse">Mouse / Ratones</option>
                                                        <option value="surge_protector">Protectores / Reguladores</option>
                                                        <option value="ram">Memoria RAM</option>
                                                        <option value="disk">Discos / Almacenamiento</option>
                                                        <option value="hostname">Nombre de Equipo</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="overflow-x-auto rounded-lg border border-obsidian-border/50">
                                                <table class="w-full text-left text-xs font-mono" id="history-table-{{ $d->id }}">
                                                    <thead class="bg-[#02050c] text-[10px] uppercase text-obsidian-muted border-b border-obsidian-border">
                                                        <tr>
                                                            <th class="py-2 px-3">Fecha y Hora</th>
                                                            <th class="py-2 px-3">Componente</th>
                                                            <th class="py-2 px-3">Acción</th>
                                                            <th class="py-2 px-3">Valor Anterior</th>
                                                            <th class="py-2 px-3">Nuevo Valor / Instalado</th>
                                                            <th class="py-2 px-3">Motivo / Registrado Por</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="history-tbody-{{ $d->id }}" class="divide-y divide-obsidian-border/30">
                                                        @forelse($d->componentHistories as $h)
                                                        <tr class="history-item-{{ $d->id }} hover:bg-white/[0.01]" data-type="{{ strtolower($h->component_type) }}">
                                                            <td class="py-2 px-3 text-[10px] text-obsidian-muted whitespace-nowrap">
                                                                {{ $h->recorded_at ? $h->recorded_at->format('d/m/Y H:i') : '-' }}
                                                            </td>
                                                            <td class="py-2 px-3">
                                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-slate-800 text-slate-300 border border-slate-700">
                                                                    {{ $h->component_type }}
                                                                </span>
                                                            </td>
                                                            <td class="py-2 px-3">
                                                                @if($h->action === 'added')
                                                                    <span class="text-emerald-400 font-bold text-[10px] flex items-center gap-0.5">
                                                                        <span class="material-symbols-outlined text-xs">add_circle</span> Agregado
                                                                    </span>
                                                                @elseif($h->action === 'removed')
                                                                    <span class="text-red-400 font-bold text-[10px] flex items-center gap-0.5">
                                                                        <span class="material-symbols-outlined text-xs">remove_circle</span> Removido
                                                                    </span>
                                                                @else
                                                                    <span class="text-cyan-400 font-bold text-[10px] flex items-center gap-0.5">
                                                                        <span class="material-symbols-outlined text-xs">sync</span> Modificado
                                                                    </span>
                                                                @endif
                                                            </td>
                                                            @php
                                                                $oldValText = $h->old_value ? (is_array($h->old_value) ? ($h->old_value['model'] ?? ($h->old_value['name'] ?? json_encode($h->old_value, JSON_UNESCAPED_UNICODE))) : $h->old_value) : '-';
                                                                $newValText = $h->new_value ? (is_array($h->new_value) ? ($h->new_value['model'] ?? ($h->new_value['name'] ?? json_encode($h->new_value, JSON_UNESCAPED_UNICODE))) : $h->new_value) : '-';
                                                            @endphp
                                                            <td class="py-2 px-3 text-obsidian-muted truncate max-w-[140px]" title="{{ $oldValText }}">
                                                                {{ $oldValText }}
                                                            </td>
                                                            <td class="py-2 px-3 text-slate-200 truncate max-w-[180px]" title="{{ $newValText }}">
                                                                {{ $newValText }}
                                                            </td>
                                                            <td class="py-2 px-3 text-obsidian-muted text-[10.5px]">
                                                                {{ $h->reason ?: 'Actualización automática' }}
                                                            </td>
                                                        </tr>
                                                        @empty
                                                        <tr>
                                                            <td colspan="6" class="py-4 text-center text-obsidian-muted italic text-[11px]">
                                                                Sin modificaciones registradas (Equipo en configuración base).
                                                            </td>
                                                        </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>

                                            <!-- PAGINACIÓN CLIENTE -->
                                            <div id="history-pagination-{{ $d->id }}" class="flex items-center justify-between pt-1 text-[10px] font-mono text-obsidian-muted">
                                                <span id="history-counter-{{ $d->id }}">Mostrando registros...</span>
                                                <div class="flex items-center gap-1.5">
                                                    <button type="button" onclick="changeAssetHistoryPage({{ $d->id }}, -1)" class="px-2 py-0.5 rounded bg-[#02050c] border border-obsidian-border hover:border-cyan-500/50 text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed" id="history-prev-{{ $d->id }}">
                                                        &larr; Anterior
                                                    </button>
                                                    <span id="history-page-info-{{ $d->id }}" class="text-cyan-300 font-bold px-1.5">1</span>
                                                    <button type="button" onclick="changeAssetHistoryPage({{ $d->id }}, 1)" class="px-2 py-0.5 rounded bg-[#02050c] border border-obsidian-border hover:border-cyan-500/50 text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed" id="history-next-{{ $d->id }}">
                                                        Siguiente &rarr;
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- TABLA: HISTÓRICO DE MANTENIMIENTO -->
                                        <div class="p-4 rounded-xl bg-[#040914] border border-obsidian-border space-y-3 mb-3">
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                                <div class="flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-cyan-400 text-lg">build</span>
                                                    <h4 class="text-xs font-mono uppercase font-bold text-white">
                                                        Histórico de Mantenimiento
                                                    </h4>
                                                    <span class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                        {{ $d->maintenanceTickets->count() }} órdenes
                                                    </span>
                                                </div>

                                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    @if($d->status === 'maintenance' || $d->maintenanceTickets->whereIn('status', ['scheduled', 'in_progress'])->isNotEmpty())
                                                    <button type="button" onclick="openSingleCompleteMaintenanceModal({{ $d->id }}, '{{ addslashes($d->display_identity ?? $d->name) }}')" 
                                                            class="px-2.5 py-1 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-300 hover:text-white text-xs font-mono font-bold transition flex items-center gap-1.5 cursor-pointer">
                                                        <span class="material-symbols-outlined text-sm">task_alt</span>
                                                        <span>Finalizar Mantenimiento</span>
                                                    </button>
                                                    @endif
                                                    <button type="button" onclick="openSingleMaintenanceModal({{ $d->id }}, '{{ addslashes($d->display_identity ?? $d->name) }}')" 
                                                            class="px-2.5 py-1 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 text-amber-300 hover:text-white text-xs font-mono font-bold transition flex items-center gap-1.5 cursor-pointer">
                                                        <span class="material-symbols-outlined text-sm">calendar_month</span>
                                                        <span>Planificar Mantenimiento</span>
                                                    </button>
                                                </div>
                                                @endif
                                            </div>

                                            @if($d->maintenanceTickets->isNotEmpty())
                                            <div class="overflow-x-auto max-h-56 overflow-y-auto rounded-lg border border-obsidian-border/50 custom-scrollbar">
                                                <table class="w-full text-left text-xs font-mono">
                                                    <thead class="bg-[#02050c] text-[10px] uppercase text-obsidian-muted border-b border-obsidian-border sticky top-0 z-10">
                                                        <tr>
                                                            <th class="py-2 px-3 whitespace-nowrap">Ticket / Código</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Tipo</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Estado</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Asunto / Motivo</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Fecha</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Técnico Asignado</th>
                                                            <th class="py-2 px-3 text-right whitespace-nowrap">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-obsidian-border/30">
                                                        @foreach($d->maintenanceTickets as $mTicket)
                                                        <tr class="hover:bg-cyan-950/20 transition-colors">
                                                            <td class="py-2 px-3 whitespace-nowrap">
                                                                <span class="text-cyan-300 font-bold">{{ $mTicket->ticket_code }}</span>
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap">
                                                                <span class="px-1.5 py-0.2 rounded text-[8.5px] font-bold {{ $mTicket->type === 'corrective' ? 'bg-red-950 text-red-300 border border-red-800' : 'bg-cyan-950 text-cyan-300 border border-cyan-800' }}">
                                                                    {{ $mTicket->type_label }}
                                                                </span>
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap">
                                                                <span class="px-1.5 py-0.2 rounded text-[8.5px] font-bold {{ $mTicket->status === 'completed' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : ($mTicket->status === 'in_progress' ? 'bg-amber-950 text-amber-300 border border-amber-800' : 'bg-slate-800 text-slate-400 border border-slate-700') }}">
                                                                    {{ $mTicket->status_label }}
                                                                </span>
                                                            </td>
                                                            <td class="py-2 px-3 max-w-[200px] truncate text-slate-200" title="{{ $mTicket->title }}">
                                                                {{ $mTicket->title }}
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap text-slate-300">
                                                                @if($mTicket->completed_at)
                                                                    <div class="text-[11px]">{{ $mTicket->completed_at->format('d/m/Y') }}</div>
                                                                    <div class="text-[9.5px] text-obsidian-muted">{{ $mTicket->completed_at->format('h:i A') }}</div>
                                                                @elseif($mTicket->scheduled_at)
                                                                    <div class="text-[11px]">{{ $mTicket->scheduled_at->format('d/m/Y') }}</div>
                                                                    <div class="text-[9.5px] text-amber-400/80">Programado</div>
                                                                @else
                                                                    <span class="text-obsidian-muted text-[10px]">N/A</span>
                                                                @endif
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap text-slate-300">
                                                                {{ $mTicket->technician?->name ?? 'No asignado' }}
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap text-right">
                                                                <div class="inline-flex items-center gap-1.5">
                                                                    <a href="{{ route('admin.inventory.maintenance.pdf', $mTicket->id) }}" target="_blank" class="p-1 rounded bg-emerald-950/70 border border-emerald-500/60 text-emerald-300 hover:bg-emerald-500 hover:text-white transition inline-flex items-center justify-center" title="Descargar Orden en PDF">
                                                                        <span class="material-symbols-outlined text-[13px]">description</span>
                                                                    </a>
                                                                    <a href="{{ route('admin.inventory.maintenance.show', $mTicket->id) }}" class="p-1 rounded bg-cyan-950/70 border border-cyan-500/60 text-cyan-300 hover:bg-cyan-500 hover:text-white transition inline-flex items-center justify-center" title="Ver Detalle del Ticket">
                                                                        <span class="material-symbols-outlined text-[13px]">visibility</span>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            @else
                                            <div class="p-3 rounded-lg bg-[#020610] border border-obsidian-border text-center text-obsidian-muted italic text-[11px]">
                                                Sin registros de mantenimiento preventivo o correctivo para este puesto de trabajo.
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <!-- CASO DISPOSITIVO DE RED (ROUTER, SWITCH, SERVIDOR, IMPRESORA, TELÉFONO) -->
                                    <div class="p-4 rounded-xl bg-[#060e1c] border border-obsidian-border space-y-4">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-obsidian-border/60 gap-3">
                                            <div class="flex items-center gap-3">
                                                <span class="p-2.5 rounded-xl bg-purple-500/10 border border-purple-500/30 text-purple-400">
                                                    <span class="material-symbols-outlined text-2xl">{{ $d->type_icon }}</span>
                                                </span>
                                                <div>
                                                    <h4 class="text-sm font-bold text-white font-mono flex items-center gap-2">
                                                        {{ $d->display_identity ?? $d->name }}
                                                        <span class="px-2 py-0.2 rounded text-[10px] font-bold uppercase bg-purple-950 text-purple-300 border border-purple-800">
                                                            {{ $d->type }}
                                                        </span>
                                                    </h4>
                                                    <p class="text-xs text-obsidian-muted font-mono">{{ $d->model ?? 'Dispositivo de infraestructura de red' }} • Sede {{ $d->site?->name ?? 'General' }}</p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                                <button type="button" onclick="openSingleMaintenanceModal({{ $d->id }}, '{{ addslashes($d->display_identity ?? $d->name) }}')" 
                                                        class="px-3 py-1.5 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-300 hover:bg-amber-500 hover:text-black text-xs font-mono font-bold transition flex items-center gap-1.5 cursor-pointer">
                                                    <span class="material-symbols-outlined text-sm">calendar_month</span>
                                                    <span>Planificar Mantenimiento</span>
                                                </button>
                                                <button type="button" onclick="openSingleMobilizationModal({{ $d->id }}, '{{ addslashes($d->display_identity ?? $d->name) }}', '{{ addslashes($d->asset_tag ?? '') }}')" 
                                                        class="px-3 py-1.5 rounded-xl bg-cyan-500/20 border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black text-xs font-mono font-bold transition flex items-center gap-1.5 cursor-pointer">
                                                    <span class="material-symbols-outlined text-sm">local_shipping</span>
                                                    <span>Movilizar</span>
                                                </button>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- GRID DE PARÁMETROS TÉCNICOS (ESTRUCTURA KEY-VALUE ORDENADA) -->
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs font-mono">
                                            <!-- MARCA, MODELO & SERIAL -->
                                            <div class="p-3 rounded-xl bg-[#040914] border border-cyan-500/40">
                                                <div class="text-[10px] text-cyan-400 uppercase font-bold flex items-center gap-1 pb-1.5 border-b border-obsidian-border/50 mb-2">
                                                    <span class="material-symbols-outlined text-xs">branding_watermark</span>
                                                    <span>Fabricante &amp; Hardware</span>
                                                </div>
                                                <div class="space-y-1.5 text-[11px]">
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Marca:</span>
                                                        <span class="text-white font-medium text-right truncate max-w-[125px]" title="{{ $d->brand ?: ($d->vendor_data ?: 'Fabricante no especificado') }}">
                                                            {{ $d->brand ?: ($d->vendor_data ?: 'Estándar') }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Modelo:</span>
                                                        <span class="text-cyan-300 font-semibold text-right truncate max-w-[125px]" title="{{ $d->model ?: 'Sin modelo' }}">
                                                            {{ $d->model ?: 'Estándar' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Serial:</span>
                                                        <span class="text-amber-300 font-mono text-right truncate max-w-[125px]" title="{{ $d->serial ?: ($d->serial_number ?: 'Sin Serial') }}">
                                                            {{ $d->serial ?: ($d->serial_number ?: 'Sin Serial') }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- RED & CONECTIVIDAD -->
                                            <div class="p-3 rounded-xl bg-[#040914] border border-obsidian-border/50">
                                                <div class="text-[10px] text-obsidian-muted uppercase font-bold flex items-center gap-1 pb-1.5 border-b border-obsidian-border/50 mb-2">
                                                    <span class="material-symbols-outlined text-xs text-cyan-400">lan</span>
                                                    <span>Red &amp; Direccionamiento</span>
                                                </div>
                                                <div class="space-y-1.5 text-[11px]">
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">IP:</span>
                                                        <span class="text-cyan-300 font-mono font-bold text-right">{{ $d->ip }}</span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">MAC:</span>
                                                        <span class="text-slate-300 font-mono text-right text-[10px] truncate max-w-[120px]">{{ $d->mac ?? 'No asignada' }}</span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Tag:</span>
                                                        <span class="text-slate-400 text-right text-[10px]">#{{ $d->device_number ?: $d->id }} • {{ $d->asset_tag ?: 'S/TAG' }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- PUERTOS E INTERFACES -->
                                            <div class="p-3 rounded-xl bg-[#040914] border border-obsidian-border/50">
                                                <div class="text-[10px] text-obsidian-muted uppercase font-bold flex items-center gap-1 pb-1.5 border-b border-obsidian-border/50 mb-2">
                                                    <span class="material-symbols-outlined text-xs text-cyan-400">hub</span>
                                                    <span>Puertos &amp; Conmutación</span>
                                                </div>
                                                <div class="space-y-1.5 text-[11px]">
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Puertos:</span>
                                                        <span class="text-white font-medium text-right">{{ $d->ports ?? '1x GE' }}</span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Capacidad:</span>
                                                        <span class="text-slate-400 text-right text-[10px]">GigaEthernet</span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Tipo:</span>
                                                        <span class="text-purple-300 text-right uppercase text-[10px]">{{ $d->type }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- PROTOCOLO DE ACCESO -->
                                            <div class="p-3 rounded-xl bg-[#040914] border border-obsidian-border/50">
                                                <div class="text-[10px] text-obsidian-muted uppercase font-bold flex items-center gap-1 pb-1.5 border-b border-obsidian-border/50 mb-2">
                                                    <span class="material-symbols-outlined text-xs text-cyan-400">terminal</span>
                                                    <span>Acceso Remoto</span>
                                                </div>
                                                <div class="space-y-1.5 text-[11px]">
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Protocolo:</span>
                                                        <span class="text-white font-semibold text-right">{{ $accType }} : {{ $accPort }}</span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Estado:</span>
                                                        <span class="text-emerald-400 text-right text-[10px]">{{ $d->has_ssh_credentials ? 'Credenciales 🔑' : 'Acceso Estándar' }}</span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Gestión:</span>
                                                        <span class="text-slate-400 text-right text-[10px]">Línea de Comandos</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- UBICACIÓN & SEDE -->
                                            <div class="p-3 rounded-xl bg-[#040914] border border-obsidian-border/50">
                                                <div class="text-[10px] text-obsidian-muted uppercase font-bold flex items-center gap-1 pb-1.5 border-b border-obsidian-border/50 mb-2">
                                                    <span class="material-symbols-outlined text-xs text-cyan-400">domain</span>
                                                    <span>Ubicación &amp; Sede</span>
                                                </div>
                                                <div class="space-y-1.5 text-[11px]">
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Sede:</span>
                                                        <span class="text-purple-300 font-medium text-right truncate max-w-[125px]">{{ $d->site?->name ?? 'Sede Principal' }}</span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Detalle:</span>
                                                        <span class="text-slate-400 text-right text-[10px] truncate max-w-[125px]">{{ $d->location_detail ?? 'Rack Central' }}</span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Uso:</span>
                                                        <span class="text-slate-400 text-right text-[10px]">Infraestructura</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        @if($d->notes)
                                            <!-- NOTAS TÉCNICAS & MAPEO DE PUERTOS FÍSICOS -->
                                            <div class="p-3.5 rounded-xl bg-[#040a17] border border-cyan-500/40 space-y-2 mt-3">
                                                <div class="flex items-center justify-between pb-1.5 border-b border-obsidian-border/50">
                                                    <h5 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                                                        <span class="material-symbols-outlined text-sm">hub</span>
                                                        <span>Notas Técnicas &amp; Mapeo de Puertos Físicos</span>
                                                    </h5>
                                                    <span class="text-[10px] font-mono text-cyan-400/80">Distribución de Cableado &amp; Enlaces de Sede</span>
                                                </div>
                                                <div class="p-3 rounded-lg bg-black/60 border border-cyan-500/20 font-mono text-xs text-slate-200 whitespace-pre-line leading-relaxed shadow-inner">{!! nl2br(e($d->notes)) !!}</div>
                                            </div>
                                        @endif

                                        <!-- RESGUARDO CORPORATIVO / ACTA DE ASIGNACIÓN PARA EQUIPO DE RED -->
                                        <div class="p-3.5 rounded-xl border {{ $activeAssignment ? 'bg-[#051410] border-emerald-500/40' : 'bg-[#040914] border-obsidian-border' }} flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs font-mono">
                                            <div class="flex items-center gap-3">
                                                <span class="p-2 rounded-lg {{ $activeAssignment ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-800 text-slate-400' }}">
                                                    <span class="material-symbols-outlined text-xl">
                                                        {{ $activeAssignment ? 'assignment_turned_in' : 'security' }}
                                                    </span>
                                                </span>
                                                <div>
                                                    <span class="font-bold uppercase tracking-wider {{ $activeAssignment ? 'text-emerald-300' : 'text-slate-300' }}">
                                                        {{ $activeAssignment ? 'Asignado a: ' . $activeAssignment->custodian_name : 'Equipo de Infraestructura (Uso de Sede ATIT - Sin Acta Individual)' }}
                                                    </span>
                                                    <p class="text-[10.5px] text-obsidian-muted mt-0.5">
                                                        {{ $activeAssignment ? 'Asignado formalmente el ' . ($activeAssignment->assigned_at ? $activeAssignment->assigned_at->format('d/m/Y') : 'N/A') : 'Bajo resguardo del departamento de Telecomunicaciones.' }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                @if($activeAssignment)
                                                    <a href="{{ route('admin.inventory.assignments.pdf', $activeAssignment->id) }}" target="_blank" class="px-2.5 py-1.5 rounded-lg bg-red-950/40 border border-red-800/60 text-red-400 hover:bg-red-500 hover:text-white transition flex items-center gap-1 text-xs">
                                                        <span class="material-symbols-outlined text-base">picture_as_pdf</span>
                                                        <span>Acta UINF-4.05</span>
                                                    </a>
                                                @elseif(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                                                    <a href="{{ route('admin.inventory.assignments.create', ['asset_id' => $d->id, 'return_to' => url()->full()]) }}" class="px-2.5 py-1.5 rounded-lg bg-cyan-500/20 hover:bg-cyan-500 border border-cyan-500/40 text-cyan-300 hover:text-black font-bold transition flex items-center gap-1 text-xs">
                                                        <span class="material-symbols-outlined text-base">assignment_add</span>
                                                        <span>Emitir Acta de Asignación</span>
                                                    </a>
                                                @endif
                                            </div>
                                        </div>

                                        @if($d->peripherals->isNotEmpty())
                                            <!-- PERIFÉRICOS & HARDWARE ASOCIADO AL DISPOSITIVO -->
                                            <div class="p-3.5 rounded-xl bg-[#040914] border border-cyan-500/30 space-y-2 mt-3">
                                                <div class="flex items-center justify-between pb-1.5 border-b border-obsidian-border/50">
                                                    <h5 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                                                        <span class="material-symbols-outlined text-sm">devices_other</span>
                                                        <span>Periféricos &amp; Hardware Asociado ({{ $d->peripherals->count() }})</span>
                                                    </h5>
                                                    <span class="text-[10px] font-mono text-obsidian-muted">Periféricos Vinculados</span>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                                    @foreach($d->peripherals as $ep)
                                                        @php
                                                            $epIcon = match($ep->type) {
                                                                'monitor' => 'monitor',
                                                                'ups', 'surge_protector' => 'bolt',
                                                                'printer' => 'print',
                                                                'scanner' => 'document_scanner',
                                                                'keyboard' => 'keyboard',
                                                                'mouse' => 'mouse',
                                                                default => 'devices_other'
                                                            };
                                                            $epTitle = match($ep->type) {
                                                                'monitor' => 'Monitor',
                                                                'ups' => 'Sistema UPS',
                                                                'surge_protector' => 'Protector / Regulador',
                                                                'printer' => 'Impresora / Multifuncional',
                                                                default => 'Periférico Asociado'
                                                            };
                                                        @endphp
                                                        <div class="p-2.5 rounded-lg border bg-[#020710] border-cyan-500/30">
                                                            <div class="flex items-center justify-between mb-1.5">
                                                                <span class="text-xs font-bold text-cyan-300 flex items-center gap-1">
                                                                    <span class="material-symbols-outlined text-sm">{{ $epIcon }}</span>
                                                                    <span>{{ $epTitle }}</span>
                                                                </span>
                                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                                    {{ $ep->status_label ?? 'Asignado' }}
                                                                </span>
                                                            </div>
                                                            <div class="space-y-0.5 text-xs font-mono">
                                                                <div class="text-white font-medium truncate">{{ $ep->brand }} {{ $ep->model ?: 'Estándar' }}</div>
                                                                <div class="text-[10px] text-slate-400 truncate">S/N: <code>{{ $ep->serial_number ?: 'Sin Serial' }}</code></div>
                                                                <div class="text-[9.5px] text-obsidian-muted truncate">Tag: {{ $ep->asset_tag }}</div>
                                                                @if($ep->notes)
                                                                    <div class="text-[9px] text-cyan-300/80 truncate font-mono" title="{{ $ep->notes }}">
                                                                        Obs: {{ $ep->notes }}
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <!-- TABLA: HISTÓRICO DE MANTENIMIENTO (INFRAESTRUCTURA) -->
                                        <div class="p-4 rounded-xl bg-[#040914] border border-obsidian-border space-y-3 mt-3">
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                                <div class="flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-cyan-400 text-lg">build</span>
                                                    <h4 class="text-xs font-mono uppercase font-bold text-white">
                                                        Histórico de Mantenimiento
                                                    </h4>
                                                    <span class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                        {{ $d->maintenanceTickets->count() }} órdenes
                                                    </span>
                                                </div>

                                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    @if($d->status === 'maintenance' || $d->maintenanceTickets->whereIn('status', ['scheduled', 'in_progress'])->isNotEmpty())
                                                    <button type="button" onclick="openSingleCompleteMaintenanceModal({{ $d->id }}, '{{ addslashes($d->display_identity ?? $d->name) }}')" 
                                                            class="px-2.5 py-1 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-300 hover:text-white text-xs font-mono font-bold transition flex items-center gap-1.5 cursor-pointer">
                                                        <span class="material-symbols-outlined text-sm">task_alt</span>
                                                        <span>Finalizar Mantenimiento</span>
                                                    </button>
                                                    @endif
                                                    <button type="button" onclick="openSingleMaintenanceModal({{ $d->id }}, '{{ addslashes($d->display_identity ?? $d->name) }}')" 
                                                            class="px-2.5 py-1 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 text-amber-300 hover:text-white text-xs font-mono font-bold transition flex items-center gap-1.5 cursor-pointer">
                                                        <span class="material-symbols-outlined text-sm">calendar_month</span>
                                                        <span>Planificar Mantenimiento</span>
                                                    </button>
                                                </div>
                                                @endif
                                            </div>

                                            @if($d->maintenanceTickets->isNotEmpty())
                                            <div class="overflow-x-auto max-h-56 overflow-y-auto rounded-lg border border-obsidian-border/50 custom-scrollbar">
                                                <table class="w-full text-left text-xs font-mono">
                                                    <thead class="bg-[#02050c] text-[10px] uppercase text-obsidian-muted border-b border-obsidian-border sticky top-0 z-10">
                                                        <tr>
                                                            <th class="py-2 px-3 whitespace-nowrap">Ticket / Código</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Tipo</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Estado</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Asunto / Motivo</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Fecha</th>
                                                            <th class="py-2 px-3 whitespace-nowrap">Técnico Asignado</th>
                                                            <th class="py-2 px-3 text-right whitespace-nowrap">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-obsidian-border/30">
                                                        @foreach($d->maintenanceTickets as $mTicket)
                                                        <tr class="hover:bg-cyan-950/20 transition-colors">
                                                            <td class="py-2 px-3 whitespace-nowrap">
                                                                <span class="text-cyan-300 font-bold">{{ $mTicket->ticket_code }}</span>
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap">
                                                                <span class="px-1.5 py-0.2 rounded text-[8.5px] font-bold {{ $mTicket->type === 'corrective' ? 'bg-red-950 text-red-300 border border-red-800' : 'bg-cyan-950 text-cyan-300 border border-cyan-800' }}">
                                                                    {{ $mTicket->type_label }}
                                                                </span>
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap">
                                                                <span class="px-1.5 py-0.2 rounded text-[8.5px] font-bold {{ $mTicket->status === 'completed' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : ($mTicket->status === 'in_progress' ? 'bg-amber-950 text-amber-300 border border-amber-800' : 'bg-slate-800 text-slate-400 border border-slate-700') }}">
                                                                    {{ $mTicket->status_label }}
                                                                </span>
                                                            </td>
                                                            <td class="py-2 px-3 max-w-[200px] truncate text-slate-200" title="{{ $mTicket->title }}">
                                                                {{ $mTicket->title }}
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap text-slate-300">
                                                                @if($mTicket->completed_at)
                                                                    <div class="text-[11px]">{{ $mTicket->completed_at->format('d/m/Y') }}</div>
                                                                    <div class="text-[9.5px] text-obsidian-muted">{{ $mTicket->completed_at->format('h:i A') }}</div>
                                                                @elseif($mTicket->scheduled_at)
                                                                    <div class="text-[11px]">{{ $mTicket->scheduled_at->format('d/m/Y') }}</div>
                                                                    <div class="text-[9.5px] text-amber-400/80">Programado</div>
                                                                @else
                                                                    <span class="text-obsidian-muted text-[10px]">N/A</span>
                                                                @endif
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap text-slate-300">
                                                                {{ $mTicket->technician?->name ?? 'No asignado' }}
                                                            </td>
                                                            <td class="py-2 px-3 whitespace-nowrap text-right">
                                                                <div class="inline-flex items-center gap-1.5">
                                                                    <a href="{{ route('admin.inventory.maintenance.pdf', $mTicket->id) }}" target="_blank" class="p-1 rounded bg-emerald-950/70 border border-emerald-500/60 text-emerald-300 hover:bg-emerald-500 hover:text-white transition inline-flex items-center justify-center" title="Descargar Orden en PDF">
                                                                        <span class="material-symbols-outlined text-[13px]">description</span>
                                                                    </a>
                                                                    <a href="{{ route('admin.inventory.maintenance.show', $mTicket->id) }}" class="p-1 rounded bg-cyan-950/70 border border-cyan-500/60 text-cyan-300 hover:bg-cyan-500 hover:text-white transition inline-flex items-center justify-center" title="Ver Detalle del Ticket">
                                                                        <span class="material-symbols-outlined text-[13px]">visibility</span>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            @else
                                            <div class="p-3 rounded-lg bg-[#020610] border border-obsidian-border text-center text-obsidian-muted italic text-[11px]">
                                                Sin registros de mantenimiento preventivo o correctivo para este dispositivo de infraestructura.
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                <!-- BLOQUE INTEGRADO DE ECOSISTEMA: TELEMETRÍA, RADAR, SNMP, TRÁFICO Y WOL -->
                                <div class="p-3.5 rounded-xl bg-[#040d1a] border border-cyan-500/30 font-mono text-xs mt-3">
                                    <div class="flex items-center justify-between pb-2 border-b border-obsidian-border/60 mb-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="material-symbols-outlined text-cyan-400 text-sm">hub</span>
                                            <span class="text-xs font-bold uppercase tracking-wider text-white">Ecosistema &amp; Telemetría en Red</span>
                                            <span class="px-2 py-0.2 rounded text-[9.5px] font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                Identidad Centralizada Sincronizada
                                            </span>
                                        </div>
                                        <span class="text-[10px] text-obsidian-muted">
                                            IP: <strong class="text-cyan-300 font-mono">{{ $d->ip }}</strong> • MAC: <strong class="text-slate-300 font-mono">{{ $d->mac ?: 'Sin MAC' }}</strong>
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                                        <!-- 1. RADAR & DESCUBRIMIENTO -->
                                        <div class="p-2.5 rounded-lg bg-[#020610] border border-obsidian-border/80 flex flex-col justify-between">
                                            <div>
                                                <div class="flex items-center justify-between text-[10.5px] pb-1.5 border-b border-obsidian-border/40 mb-1.5">
                                                    <span class="text-cyan-400 font-bold flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-xs">radar</span>
                                                        <span>Radar &amp; ARP</span>
                                                    </span>
                                                    @if($d->discoveredDevice)
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-950 text-emerald-300 border border-emerald-800">
                                                            AUTORIZADO
                                                        </span>
                                                    @else
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-gray-900 text-gray-400 border border-gray-700">
                                                            NO DETECTADO
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="space-y-1 text-[10.5px]">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-obsidian-muted">Último Visto:</span>
                                                        <span class="text-white font-medium">
                                                            {{ $d->discoveredDevice && $d->discoveredDevice->last_seen ? $d->discoveredDevice->last_seen->diffForHumans() : 'Sin barrido reciente' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-obsidian-muted">Avistamientos:</span>
                                                        <span class="text-slate-300">
                                                            {{ $d->discoveredDevice ? $d->discoveredDevice->seen_count . ' veces' : '0' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-2 pt-1.5 border-t border-obsidian-border/40 text-right">
                                                <a href="{{ route('admin.discovery.index', ['tab' => 'radar', 'search' => $d->ip]) }}" class="text-[10px] text-cyan-400 hover:underline inline-flex items-center gap-0.5">
                                                    <span>Ver en Radar</span>
                                                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                                </a>
                                            </div>
                                        </div>

                                        <!-- 2. TELEMETRÍA SNMP -->
                                        <div class="p-2.5 rounded-lg bg-[#020610] border border-obsidian-border/80 flex flex-col justify-between">
                                            <div>
                                                <div class="flex items-center justify-between text-[10.5px] pb-1.5 border-b border-obsidian-border/40 mb-1.5">
                                                    <span class="text-purple-400 font-bold flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-xs">router</span>
                                                        <span>Métricas SNMP</span>
                                                    </span>
                                                    @if($d->snmpDevice)
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-purple-950 text-purple-300 border border-purple-800">
                                                            ACTIVO v{{ $d->snmpDevice->snmp_version }}
                                                        </span>
                                                    @else
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-gray-900 text-gray-400 border border-gray-700">
                                                            NO APROVISIONADO
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="space-y-1 text-[10.5px]">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-obsidian-muted">Interfaces:</span>
                                                        <span class="text-white font-medium">
                                                            {{ $d->snmpDevice ? ($d->snmpDevice->interfaces->count() . ' puertos') : 'N/A' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-obsidian-muted">Perfil:</span>
                                                        <span class="text-slate-300 truncate max-w-[110px]" title="{{ $d->snmpDevice ? $d->snmpDevice->name : 'N/A' }}">
                                                            {{ $d->snmpDevice ? ($d->snmpDevice->vendor ?: 'Cisco / pfSense') : 'Sin perfil' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-2 pt-1.5 border-t border-obsidian-border/40 text-right">
                                                <a href="{{ route('admin.discovery.index', ['tab' => 'snmp', 'search' => $d->ip]) }}" class="text-[10px] text-purple-400 hover:underline inline-flex items-center gap-0.5">
                                                    <span>{{ $d->snmpDevice ? 'Ver Interfaces' : 'Aprovisionar SNMP' }}</span>
                                                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                                </a>
                                            </div>
                                        </div>

                                        <!-- 3. TRÁFICO NETRADAR -->
                                        <div class="p-2.5 rounded-lg bg-[#020610] border border-obsidian-border/80 flex flex-col justify-between">
                                            <div>
                                                <div class="flex items-center justify-between text-[10.5px] pb-1.5 border-b border-obsidian-border/40 mb-1.5">
                                                    <span class="text-cyan-400 font-bold flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-xs">traffic</span>
                                                        <span>Tráfico de Red</span>
                                                    </span>
                                                    @if($d->netRadarHost)
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                            CAPTURADO
                                                        </span>
                                                    @else
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-gray-900 text-gray-400 border border-gray-700">
                                                            SIN FLUJO
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="space-y-1 text-[10.5px]">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-obsidian-muted">Volumen Total:</span>
                                                        <span class="text-white font-medium">
                                                            @if($d->netRadarHost && $d->netRadarHost->total_bytes > 0)
                                                                {{ number_format($d->netRadarHost->total_bytes / (1024 * 1024), 2) }} MB
                                                            @else
                                                                0 MB
                                                            @endif
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-obsidian-muted">Paquetes:</span>
                                                        <span class="text-slate-300">
                                                            {{ $d->netRadarHost ? number_format($d->netRadarHost->packet_count) : '0' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-2 pt-1.5 border-t border-obsidian-border/40 text-right">
                                                <span class="text-[10px] text-obsidian-muted">
                                                    {{ $d->netRadarHost && $d->netRadarHost->last_seen_at ? 'Activo ' . $d->netRadarHost->last_seen_at->diffForHumans() : 'Monitoreo pasivo' }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- 4. ACCIÓN WAKE-ON-LAN -->
                                        <div class="p-2.5 rounded-lg bg-[#020610] border border-obsidian-border/80 flex flex-col justify-between">
                                            <div>
                                                <div class="flex items-center justify-between text-[10.5px] pb-1.5 border-b border-obsidian-border/40 mb-1.5">
                                                    <span class="text-amber-400 font-bold flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-xs">power_settings_new</span>
                                                        <span>Encendido Remoto</span>
                                                    </span>
                                                    @if($d->mac && !str_starts_with($d->mac, 'IP:'))
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-950 text-amber-300 border border-amber-800">
                                                            LISTO
                                                        </span>
                                                    @else
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-gray-900 text-gray-400 border border-gray-700">
                                                            REQUIERE MAC
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="space-y-1 text-[10.5px]">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-obsidian-muted">MAC Objetivo:</span>
                                                        <span class="text-amber-300 font-mono text-[10px] truncate max-w-[110px]" title="{{ $d->mac ?: 'Sin MAC física' }}">
                                                            {{ $d->mac ?: 'No asignada' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-obsidian-muted">Estado WoL:</span>
                                                        <span class="text-slate-300">
                                                            {{ $d->wolDevice && $d->wolDevice->last_woken_at ? 'Encendido ' . $d->wolDevice->last_woken_at->diffForHumans() : 'Disponible' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-2 pt-1.5 border-t border-obsidian-border/40 text-right">
                                                @if($d->mac && !str_starts_with($d->mac, 'IP:'))
                                                    <a href="{{ route('admin.wol.index', ['search' => $d->mac]) }}" class="text-[10px] text-amber-400 hover:underline inline-flex items-center gap-0.5">
                                                        <span>Gestión WoL</span>
                                                        <span class="material-symbols-outlined text-xs">bolt</span>
                                                    </a>
                                                @else
                                                    <span class="text-[10px] text-obsidian-muted">Asignar MAC en ITAM</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr id="local-empty-row" class="hidden">
                            <td colspan="12" class="px-5 py-8 text-center text-obsidian-muted font-mono text-xs">
                                <div class="flex flex-col items-center justify-center gap-1.5 py-4">
                                    <span class="material-symbols-outlined text-3xl text-cyan-400/60 animate-pulse">search</span>
                                    <span class="text-white font-semibold">No se encontraron dispositivos en esta página</span>
                                    <span class="text-[11px] text-obsidian-muted">Buscando coincidencias globales en el censo...</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="empty-devices-row">
                            <td colspan="12" class="px-5 py-8 text-center text-obsidian-muted font-mono text-xs">
                                <div class="flex flex-col items-center justify-center gap-1.5 py-4">
                                    <span class="material-symbols-outlined text-3xl text-obsidian-muted/60">devices_off</span>
                                    <span class="text-white font-semibold">No se encontraron dispositivos</span>
                                    <span class="text-[11px] text-obsidian-muted">No hay equipos que coincidan con la búsqueda o filtros aplicados.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN OBSIDIAN DARK -->
        <div id="devices-pagination-wrapper">
        @if($devices->hasPages())
            <div class="px-4 py-3 bg-obsidian-panel border-t border-obsidian-border/80 flex flex-col sm:flex-row items-center justify-between gap-2.5 text-xs font-mono">
                <div class="text-obsidian-muted text-[10.5px]">
                    Mostrando <span class="text-white font-bold">{{ $devices->firstItem() }}</span> a <span class="text-white font-bold">{{ $devices->lastItem() }}</span> de <span class="text-cyan-400 font-bold">{{ $devices->total() }}</span> dispositivos (15 por página)
                </div>
                <div class="flex items-center gap-1">
                    {{-- Anterior --}}
                    @if($devices->onFirstPage())
                        <span class="px-2.5 py-1 rounded-lg bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[10.5px]">
                            &laquo; Anterior
                        </span>
                    @else
                        <a href="{{ $devices->previousPageUrl() }}" class="px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[10.5px]">
                            &laquo; Anterior
                        </a>
                    @endif

                    {{-- Páginas --}}
                    @foreach($devices->getUrlRange(1, $devices->lastPage()) as $page => $url)
                        @if($page == $devices->currentPage())
                            <span class="px-2.5 py-1 rounded-lg bg-cyan-500 text-black font-bold text-[10.5px]">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" class="px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white transition text-[10.5px]">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach

                    {{-- Siguiente --}}
                    @if($devices->hasMorePages())
                        <a href="{{ $devices->nextPageUrl() }}" class="px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[10.5px]">
                            Siguiente &raquo;
                        </a>
                    @else
                        <span class="px-2.5 py-1 rounded-lg bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[10.5px]">
                            Siguiente &raquo;
                        </span>
                    @endif
                </div>
            </div>
        @endif
        </div>
        <script id="devices-maintenance-data" type="application/json">@json($assetsMaintenanceMap)</script>
    </div>
</div>

<!-- ========================================================================= -->
<!-- BARRA FLOTANTE DE ACCIONES MASIVAS                                        -->
<!-- ========================================================================= -->
<div id="bulkActionBar" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-[#091528]/95 border border-cyan-500/50 backdrop-blur-md px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-4 transition-all duration-300 translate-y-32 opacity-0 pointer-events-none">
    <div class="flex items-center gap-2 font-mono text-xs">
        <span class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-pulse"></span>
        <span class="text-white font-bold" id="bulkSelectedCount">0</span>
        <span class="text-obsidian-muted">seleccionados</span>
    </div>

    <div class="h-4 w-px bg-obsidian-border"></div>

    <div class="flex items-center gap-2">
        <button type="button" onclick="executeBulkMobilization()" class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-amber-500/20 cursor-pointer">
            <span class="material-symbols-outlined text-base">local_shipping</span>
            <span>Movilizar</span>
        </button>

        <button type="button" onclick="openBulkMaintenanceModal()" class="px-3.5 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20 cursor-pointer">
            <span class="material-symbols-outlined text-base">calendar_month</span>
            <span>Planificar Mantenimiento</span>
        </button>

        <button type="button" onclick="openBulkCompleteMaintenanceModal()" class="px-3.5 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-emerald-500/20 cursor-pointer">
            <span class="material-symbols-outlined text-base">task_alt</span>
            <span>Finalizar Mantenimiento</span>
        </button>

        <button type="button" onclick="deselectAll()" class="p-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white hover:border-slate-500 transition cursor-pointer" title="Deseleccionar todos">
            <span class="material-symbols-outlined text-base">close</span>
        </button>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 1: FICHA TÉCNICA DETALLADA (OPERADORES Y ADMINISTRADORES)          -->
<!-- ========================================================================= -->
<div id="modal-device-details" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="glass-panel w-full max-w-2xl rounded-2xl border border-cyan-500/50 flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95 animate-in fade-in zoom-in-95 duration-200 max-h-[85vh] sm:max-h-[88vh]">
        <!-- CABECERA -->
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between bg-[#061527] shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/80 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shadow-md">
                    <span class="material-symbols-outlined text-2xl">router</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white font-mono flex items-center gap-2">
                        <span id="det-device-name">--</span>
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
        <div class="p-6 space-y-5 overflow-y-auto flex-1 custom-scroll">
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

            <!-- BLOQUE: NOTAS TÉCNICAS -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-mono font-bold text-cyan-400 flex items-center gap-1.5 uppercase tracking-wider">
                        <span class="material-symbols-outlined text-sm">description</span>
                        Notas Técnicas &amp; Distribución de Puertos
                    </label>
                    <span class="text-[10px] font-mono text-obsidian-muted">Configuración Registrada</span>
                </div>
                <div id="det-device-notes" class="whitespace-pre-line font-mono text-xs text-cyan-200 bg-[#020b14]/90 p-4 rounded-xl border border-cyan-500/30 break-words leading-relaxed max-h-56 overflow-y-auto scrollbar-thin">
                    Sin notas técnicas registradas.
                </div>
            </div>
        </div>

        <!-- PIE DEL MODAL -->
        <div class="p-4 px-6 border-t border-obsidian-border bg-[#061527] flex items-center justify-between gap-3 shrink-0">
            <div id="det-device-connect-btn"></div>
            <button type="button" onclick="closeDeviceDetailsModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-white/10 transition">
                Cerrar
            </button>
        </div>
    </div>
</div>

@if((auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.manage_devices') || auth()->user()->hasPermission('infra.manage_sites')) && !($isClusterSlave ?? false))
<!-- ========================================================================= -->
<!-- MODAL 2: CREACIÓN DE DISPOSITIVO (SOLO ADMINISTRADORES)                   -->
<!-- ========================================================================= -->
<!-- MODAL 2: CREACIÓN DE DISPOSITIVO (SOLO ADMINISTRADORES)                    -->
<!-- ========================================================================= -->
<div id="modal-device-create" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-5">
    <div class="glass-panel w-full max-w-5xl rounded-2xl border border-cyan-500/50 flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95 animate-in fade-in zoom-in-95 duration-200 max-h-[85vh] sm:max-h-[88vh]">
        <!-- CABECERA -->
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between bg-[#061527] shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/80 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shadow-md">
                    <span class="material-symbols-outlined text-2xl">devices</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white font-mono flex items-center gap-2">
                        <span>Registrar Nuevo Dispositivo / Activo</span>
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono">Incorporación al censo unificado, ficha técnica, periféricos y supervisión en tiempo real</p>
                </div>
            </div>
            <button type="button" onclick="closeDeviceCreateModal()" class="text-obsidian-muted hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer" title="Cerrar ventana">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- FORMULARIO -->
        <form action="{{ route('admin.devices.store') }}" method="POST" class="flex flex-col flex-1 min-h-0 overflow-hidden" id="form-device-create">
            @csrf
            <input type="hidden" name="has_peripherals_form" value="1">
            <div class="p-5 sm:p-6 overflow-y-auto flex-1 custom-table-scroll space-y-5">
                
                <!-- ========================================================================= -->
                <!-- BLOQUE 1: IDENTIDAD OPERATIVA & SEDE REGIONAL                              -->
                <!-- ========================================================================= -->
                <div class="rounded-2xl bg-[#030914]/90 border border-purple-500/30 p-4 sm:p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-purple-500/20 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-purple-950/80 border border-purple-500/40 text-purple-300 material-symbols-outlined text-lg">domain</span>
                            <div>
                                <h4 class="text-xs font-bold font-mono text-purple-200 uppercase tracking-wide">1. Identidad Operativa &amp; Sede Regional</h4>
                                <p class="text-[10.5px] font-mono text-obsidian-muted">Ubicación física en el cluster y rol operativo asignado</p>
                            </div>
                        </div>
                        <span class="text-[9.5px] font-mono font-bold px-2 py-0.5 rounded-full bg-purple-950 text-purple-300 border border-purple-500/40">Obligatorio</span>
                    </div>

                    <!-- SEDE REGIONAL CON BOTÓN DE NUEVA SEDE -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[11px] font-mono text-purple-300 font-semibold uppercase flex items-center gap-1.5">
                                <span>Sede Regional Asignada *</span>
                            </label>
                            <button type="button" onclick="openQuickSiteModal()" class="px-2.5 py-0.5 rounded-lg bg-purple-950/80 border border-purple-500/40 text-purple-200 hover:bg-purple-600 hover:text-white transition text-[10px] font-mono font-bold flex items-center gap-1 cursor-pointer shadow-xs" title="Crear una nueva sede regional sin salir del formulario">
                                <span class="material-symbols-outlined text-xs">add_circle</span>
                                <span>+ Nueva Sede</span>
                            </button>
                        </div>
                        <select name="monitored_site_id" id="create-monitored-site-id" required class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-purple-500/40 text-white text-xs font-mono focus:border-cyan-400 focus:outline-none transition cursor-pointer">
                            <option value="">-- Seleccione la Sede Regional --</option>
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}" {{ (string)$siteFilter === (string)$s->id ? 'selected' : '' }}>
                                    {{ $s->name }} (IP Enlace: {{ $s->ip ?: 'Sin Enlace' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- NOMBRE ETIQUETA -->
                        <div>
                            <label class="block text-[11px] font-mono text-cyan-400 mb-1 font-semibold uppercase flex items-center justify-between">
                                <span>Nombre Etiqueta *</span>
                                <span class="text-[9.5px] text-cyan-400/90 font-bold">Identidad Operativa</span>
                            </label>
                            <input type="text" name="name" id="create-name" required placeholder="Ej: JEFE DE OFICINA / TAQUILLA 01" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-cyan-500/40 text-white text-xs font-mono placeholder-obsidian-muted/60 focus:border-cyan-400 focus:outline-none transition">
                            <span class="text-[9.5px] font-mono text-obsidian-muted mt-1 block">Rol funcional visible principal en monitoreo e inventario</span>
                        </div>

                        <!-- TIPO DE ACTIVO / EQUIPO AGRUPADO -->
                        <div>
                            <label class="block text-[11px] font-mono text-cyan-400 mb-1 font-semibold uppercase">Tipo de Activo / Equipo</label>
                            <select name="type" id="create-device-type" onchange="handleCreateDeviceTypeChange(this.value)" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-cyan-500/40 text-cyan-200 text-xs font-mono focus:border-cyan-400 focus:outline-none transition cursor-pointer">
                                <optgroup label="💻 Cómputo &amp; Servidores">
                                    <option value="desktop" selected>🖥️ Desktop / PC de Escritorio</option>
                                    <option value="laptop">💻 Laptop / Portátil</option>
                                    <option value="server">🖳 Servidor Central</option>
                                </optgroup>
                                <optgroup label="🌐 Equipos de Red (NOC)">
                                    <option value="router">🌐 Router Gateway</option>
                                    <option value="switch">🔀 Switch de Red</option>
                                    <option value="firewall">🛡️ Firewall / Seguridad</option>
                                    <option value="access_point">📡 Access Point Wi-Fi</option>
                                    <option value="printer">🖨️ Impresora de Red</option>
                                    <option value="ip_phone">📞 Teléfono IP VoIP</option>
                                </optgroup>
                                <optgroup label="🔋 Energía &amp; Respaldo">
                                    <option value="ups">🔋 Energía / UPS</option>
                                    <option value="surge_protector">⚡ Protector de Voltaje / Regleta</option>
                                </optgroup>
                                <optgroup label="🖱️ Periféricos &amp; Accesorios (ITAM)">
                                    <option value="monitor">🖥️ Monitor / Pantalla</option>
                                    <option value="keyboard">⌨️ Teclado</option>
                                    <option value="mouse">🖱️ Mouse / Ratón</option>
                                    <option value="headset">🎧 Auriculares / Headset</option>
                                    <option value="scanner">📠 Escáner / Lector</option>
                                    <option value="other_peripheral">📦 Otro Periférico / Accesorio</option>
                                </optgroup>
                            </select>
                            <span class="text-[9.5px] font-mono text-obsidian-muted mt-1 block">Clasificación en censo de activos</span>
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- BLOQUE 2: HARDWARE, CHASIS & ESPECIFICACIONES DE RED                       -->
                <!-- ========================================================================= -->
                <div class="rounded-2xl bg-[#030914]/90 border border-emerald-500/30 p-4 sm:p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-emerald-500/20 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-emerald-950/80 border border-emerald-500/40 text-emerald-300 material-symbols-outlined text-lg">lan</span>
                            <div>
                                <h4 class="text-xs font-bold font-mono text-emerald-200 uppercase tracking-wide">2. Hardware, Chasis &amp; Red</h4>
                                <p class="text-[10.5px] font-mono text-obsidian-muted">Direccionamiento de red, serial de máquina y fabricante</p>
                            </div>
                        </div>
                        <span class="text-[9.5px] font-mono font-bold px-2 py-0.5 rounded-full bg-emerald-950 text-emerald-300 border border-emerald-500/40" id="badge-connectivity">Conectividad</span>
                    </div>

                    <!-- AVISO DE ACTIVO PASIVO / PERIFÉRICO -->
                    <div id="create-passive-notice" class="hidden p-3 rounded-xl bg-purple-950/30 border border-purple-500/40 text-purple-200 text-xs font-mono flex items-center gap-2.5 animate-in fade-in duration-200">
                        <span class="material-symbols-outlined text-purple-400 text-xl">info</span>
                        <div>
                            <span class="font-bold text-white">Periférico / Hardware independiente sin interfaz de red.</span>
                            <p class="text-[10.5px] text-obsidian-muted mt-0.5">Los campos de Dirección IP y MAC se ocultan automáticamente. Este equipo se registrará en el inventario físico con su serial, marca y modelo listo para asignación o stock.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5" id="create-ip-mac-wrapper">
                        <!-- IP -->
                        <div>
                            <label class="block text-[11px] font-mono text-white mb-1 font-semibold uppercase flex items-center justify-between">
                                <span>Dirección IP <span id="create-ip-req">*</span></span>
                                <span class="text-[9px] text-emerald-400 font-mono" id="create-ip-badge">Ping ICMP</span>
                            </label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-obsidian-muted text-sm">wifi</span>
                                <input type="text" name="ip" id="create-ip" required placeholder="Ej: 192.168.1.10" class="w-full pl-9 pr-3 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono placeholder-obsidian-muted/60 focus:border-cyan-400 focus:outline-none transition">
                            </div>
                        </div>

                        <!-- MAC -->
                        <div>
                            <label class="block text-[11px] font-mono text-obsidian-muted mb-1 font-semibold uppercase flex items-center justify-between">
                                <span>Dirección MAC</span>
                                <span class="text-[9px] text-obsidian-muted">WoL / ARP</span>
                            </label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-obsidian-muted text-sm">fingerprint</span>
                                <input type="text" name="mac" id="create-mac" placeholder="00:1A:2B:3C:4D:5E" class="w-full pl-9 pr-3 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono placeholder-obsidian-muted/60 focus:border-cyan-400 focus:outline-none transition">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <!-- FABRICANTE -->
                        <div>
                            <label class="block text-[11px] font-mono text-obsidian-muted mb-1 font-semibold uppercase">Fabricante / Vendor</label>
                            <input type="text" name="vendor_data" id="create-vendor-data" placeholder="Ej: HP, Dell, Cisco, Lenovo" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono placeholder-obsidian-muted/60 focus:border-cyan-400 focus:outline-none transition">
                        </div>

                        <!-- MODELO -->
                        <div>
                            <label class="block text-[11px] font-mono text-cyan-400 mb-1 font-semibold uppercase">Modelo de Hardware</label>
                            <input type="text" name="model" id="create-model" placeholder="Ej: ProDesk 400 G4 / Catalyst 2960" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-cyan-500/40 text-cyan-200 text-xs font-mono placeholder-obsidian-muted/60 focus:border-cyan-400 focus:outline-none transition">
                        </div>

                        <!-- SERIAL -->
                        <div>
                            <label class="block text-[11px] font-mono text-amber-400 mb-1 font-semibold uppercase">Número de Serie (Serial)</label>
                            <input type="text" name="serial" id="create-serial" placeholder="Ej: MXL7241ABC / FOC1234X5YZ" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-amber-500/40 text-amber-200 text-xs font-mono placeholder-obsidian-muted/60 focus:border-amber-400 focus:outline-none transition">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- CONDICIÓN / ESTADO OPERATIVO -->
                        <div>
                            <label class="block text-[11px] font-mono text-emerald-400 mb-1 font-semibold uppercase flex items-center justify-between">
                                <span>Condición / Estado Operativo Inicial *</span>
                                <span class="text-[9.5px] font-mono text-emerald-400/90 font-bold">Censo de Inventario</span>
                            </label>
                            <select name="status" id="create-status" onchange="handleCreateStatusChange(this.value)" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-emerald-500/40 text-emerald-200 text-xs font-mono focus:border-cyan-400 focus:outline-none transition cursor-pointer">
                                <option value="assigned" selected>🟢 Asignado (En Uso / Operativo)</option>
                                <option value="in_stock">🔵 En Stock (Disponible / Almacén)</option>
                                <option value="in_maintenance">🟡 En Mantenimiento (En Taller / Diagnóstico)</option>
                                <option value="in_transit">🟣 En Movilización / Tránsito (Acta UINF-4.05)</option>
                                <option value="damaged">🔴 Dañado / Falla Pendiente</option>
                                <option value="decommissioned">⚫ Desincorporado / Baja Definitiva</option>
                            </select>
                        </div>

                        <!-- PUERTOS / INTERFACES -->
                        <div>
                            <label class="block text-[11px] font-mono text-obsidian-muted mb-1 font-semibold uppercase">Especificación de Puertos</label>
                            <input type="text" name="ports" id="create-ports" placeholder="Ej: 1x GbE LAN / 48x GbE PoE + 4x SFP+" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono placeholder-obsidian-muted/60 focus:border-cyan-400 focus:outline-none transition">
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- BLOQUE 3: PERIFÉRICOS DE PUESTO DE TRABAJO (BASE Y ADICIONALES)           -->
                <!-- ========================================================================= -->
                <div id="create-section-peripherals" class="rounded-2xl bg-[#030d1c]/90 border border-cyan-500/40 p-4 sm:p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-cyan-500/20 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 material-symbols-outlined text-lg">desktop_mac</span>
                            <div>
                                <h4 class="text-xs font-bold font-mono text-white uppercase tracking-wide">3. Periféricos de Puesto de Trabajo</h4>
                                <p class="text-[10.5px] font-mono text-obsidian-muted">Equipos complementarios vinculados a esta estación física (Monitor, Teclado, Mouse, Regulador y extras)</p>
                            </div>
                        </div>
                        <span class="text-[9.5px] font-mono font-bold px-2 py-0.5 rounded-full bg-cyan-950 text-cyan-300 border border-cyan-500/40">Puesto de Trabajo</span>
                    </div>

                    <!-- GRID DE 4 PERIFÉRICOS BASE (ESTILO ACORDEÓN) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs font-mono">
                        <!-- 1. MONITOR -->
                        <div class="p-3 rounded-xl bg-[#040e1e] border border-cyan-500/30 space-y-2">
                            <div class="flex items-center justify-between pb-1 border-b border-obsidian-border/50">
                                <div class="flex items-center gap-1.5 text-cyan-300 font-bold text-xs">
                                    <span class="material-symbols-outlined text-sm">monitor</span>
                                    <span>Monitor Principal</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Marca</label>
                                <input type="text" name="monitor_brand" id="create-monitor-brand" placeholder="ej. HP / Dell / LG" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Modelo</label>
                                <input type="text" name="monitor_model" id="create-monitor-model" placeholder="ej. V193b 18.5&quot;" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-amber-400/90 mb-0.5">Serial</label>
                                <input type="text" name="monitor_serial" id="create-monitor-serial" placeholder="ej. 3CQ51323GC" class="w-full px-2 py-1 rounded bg-[#020710] border border-amber-500/30 text-amber-200 text-[11px] font-mono focus:border-amber-400 focus:outline-none">
                            </div>
                        </div>

                        <!-- 2. TECLADO -->
                        <div class="p-3 rounded-xl bg-[#040e1e] border border-cyan-500/30 space-y-2">
                            <div class="flex items-center justify-between pb-1 border-b border-obsidian-border/50">
                                <div class="flex items-center gap-1.5 text-cyan-300 font-bold text-xs">
                                    <span class="material-symbols-outlined text-sm">keyboard</span>
                                    <span>Teclado</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Marca</label>
                                <input type="text" name="keyboard_brand" id="create-keyboard-brand" placeholder="ej. Genius / HP / Logitech" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Modelo</label>
                                <input type="text" name="keyboard_model" id="create-keyboard-model" placeholder="ej. KB-110 USB" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-amber-400/90 mb-0.5">Serial</label>
                                <input type="text" name="keyboard_serial" id="create-keyboard-serial" placeholder="ej. SN-KBD-098" class="w-full px-2 py-1 rounded bg-[#020710] border border-amber-500/30 text-amber-200 text-[11px] font-mono focus:border-amber-400 focus:outline-none">
                            </div>
                        </div>

                        <!-- 3. MOUSE -->
                        <div class="p-3 rounded-xl bg-[#040e1e] border border-cyan-500/30 space-y-2">
                            <div class="flex items-center justify-between pb-1 border-b border-obsidian-border/50">
                                <div class="flex items-center gap-1.5 text-cyan-300 font-bold text-xs">
                                    <span class="material-symbols-outlined text-sm">mouse</span>
                                    <span>Mouse / Ratón</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Marca</label>
                                <input type="text" name="mouse_brand" id="create-mouse-brand" placeholder="ej. Logitech / Dell / Genius" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Modelo</label>
                                <input type="text" name="mouse_model" id="create-mouse-model" placeholder="ej. M100 Óptico USB" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-amber-400/90 mb-0.5">Serial</label>
                                <input type="text" name="mouse_serial" id="create-mouse-serial" placeholder="ej. SN-MSE-441" class="w-full px-2 py-1 rounded bg-[#020710] border border-amber-500/30 text-amber-200 text-[11px] font-mono focus:border-amber-400 focus:outline-none">
                            </div>
                        </div>

                        <!-- 4. PROTECTOR / UPS -->
                        <div class="p-3 rounded-xl bg-[#040e1e] border border-cyan-500/30 space-y-2">
                            <div class="flex items-center justify-between pb-1 border-b border-obsidian-border/50">
                                <div class="flex items-center gap-1.5 text-cyan-300 font-bold text-xs">
                                    <span class="material-symbols-outlined text-sm">bolt</span>
                                    <span>Protector / UPS</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Tipo</label>
                                <select name="protector_type" id="create-protector-type" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                                    <option value="surge_protector">Regulador de Voltaje</option>
                                    <option value="ups">Sistema UPS</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Marca y Modelo</label>
                                <div class="grid grid-cols-2 gap-1">
                                    <input type="text" name="protector_brand" id="create-protector-brand" placeholder="Forza" class="w-full px-1.5 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[10.5px] font-mono focus:border-cyan-400 focus:outline-none">
                                    <input type="text" name="protector_model" id="create-protector-model" placeholder="FVR-1211" class="w-full px-1.5 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[10.5px] font-mono focus:border-cyan-400 focus:outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-amber-400/90 mb-0.5">Serial</label>
                                <input type="text" name="protector_serial" id="create-protector-serial" placeholder="ej. SN-PWR-001" class="w-full px-2 py-1 rounded bg-[#020710] border border-amber-500/30 text-amber-200 text-[11px] font-mono focus:border-amber-400 focus:outline-none">
                            </div>
                        </div>
                    </div>

                    <!-- SUB-SECCIÓN: PERIFÉRICOS ADICIONALES (IMPRESORA, ESCÁNER, HEADSET, ETC.) -->
                    <div class="pt-3 border-t border-cyan-500/20 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-xs font-mono font-bold text-cyan-300">
                                <span class="material-symbols-outlined text-sm">print</span>
                                <span>Periféricos Adicionales Asignados (Impresoras, Escáneres, Auriculares, etc.)</span>
                            </div>
                            <button type="button" onclick="addCreateExtraPeripheral()" class="px-3 py-1 rounded-lg bg-cyan-950/80 hover:bg-cyan-600 border border-cyan-500/40 text-cyan-200 hover:text-white transition text-[11px] font-mono font-bold flex items-center gap-1.5 cursor-pointer shadow-xs">
                                <span class="material-symbols-outlined text-xs">add</span>
                                <span>+ Añadir Periférico Adicional</span>
                            </button>
                        </div>
                        <div id="create-extra-peripherals-list" class="space-y-2.5">
                            <!-- Inyectado dinámicamente con JS -->
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- BLOQUE 4: RESPONSABLE & ASIGNACIÓN (LDAP / UINF-4.05)         -->
                <!-- ========================================================================= -->
                <div id="create-assignment-wrapper" class="rounded-2xl bg-[#030d1c]/90 border border-cyan-500/30 p-4 sm:p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-cyan-500/20 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 material-symbols-outlined text-lg">badge</span>
                            <div>
                                <h4 class="text-xs font-bold font-mono text-cyan-200 uppercase tracking-wide">4. Responsable &amp; Asignación (Acta UINF-4.05)</h4>
                                <p class="text-[10.5px] font-mono text-obsidian-muted">Asignación directa a trabajador mediante búsqueda en Directorio Activo LDAP</p>
                            </div>
                        </div>
                        <span class="text-[9.5px] font-mono font-bold px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-300 border border-cyan-500/30">Opcional</span>
                    </div>

                    <x-ldap-user-picker 
                        id="create-device-picker" 
                        name="custodian" 
                        label="Buscar Trabajador en Directorio Activo LDAP:" 
                        :show-details="true"
                        :can-release="false" />
                </div>

                <!-- ========================================================================= -->
                <!-- BLOQUE 5: CONECTIVIDAD AVANZADA, MONITOREO & ALERTAS                      -->
                <!-- ========================================================================= -->
                <div id="create-section-monitoring" class="rounded-2xl bg-[#030914]/90 border border-cyan-500/30 p-4 sm:p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-cyan-500/20 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 material-symbols-outlined text-lg">monitoring</span>
                            <div>
                                <h4 class="text-xs font-bold font-mono text-cyan-200 uppercase tracking-wide">5. Supervisión, Alertas &amp; Acceso Remoto</h4>
                                <p class="text-[10.5px] font-mono text-obsidian-muted">Monitoreo continuo ICMP, despachos por Telegram y consola de gestión</p>
                            </div>
                        </div>
                        <span class="text-[9.5px] font-mono font-bold px-2 py-0.5 rounded-full bg-cyan-950 text-cyan-300 border border-cyan-500/40">Operaciones</span>
                    </div>

                    <!-- PROTOCOLO Y PUERTO -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5" id="create-access-wrapper">
                        <div>
                            <label class="block text-[11px] font-mono text-obsidian-muted mb-1 font-semibold uppercase">Protocolo de Acceso</label>
                            <select name="access_type" id="create-access-type" onchange="handleCreateAccessChange(this.value)" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none transition cursor-pointer">
                                <option value="SSH">Terminal SSH</option>
                                <option value="TELNET">Terminal Telnet</option>
                                <option value="WEB">Interfaz Web (HTTP/HTTPS)</option>
                                <option value="VNC">Escritorio Remoto VNC</option>
                                <option value="SSH+VNC">Dual: SSH (22) + VNC (5900)</option>
                                <option value="SIN SOPORTE">Sin Acceso Remoto</option>
                            </select>
                        </div>
                        <div id="create-single-port-wrapper">
                            <label class="block text-[11px] font-mono text-obsidian-muted mb-1 font-semibold uppercase" id="create-access-port-label">Puerto de Conexión</label>
                            <input type="number" name="access_port" id="create-access-port" value="22" min="1" max="65535" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- PUERTOS DUALES DEDICADOS (SSH + VNC) -->
                    <div id="create-dual-ports-wrapper" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-[11px] font-mono text-emerald-400 mb-1 font-semibold uppercase">Puerto SSH</label>
                            <input type="number" name="ssh_port" id="create-ssh-port" value="22" min="1" max="65535" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-emerald-500/40 text-white text-xs font-mono focus:border-emerald-400 focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-[11px] font-mono text-purple-400 mb-1 font-semibold uppercase">Puerto VNC</label>
                            <input type="number" name="vnc_port" id="create-vnc-port" value="5900" min="1" max="65535" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-purple-500/40 text-white text-xs font-mono focus:border-purple-400 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- CREDENCIALES VNC (ESCRITORIO REMOTO) -->
                    <div id="create-vnc-wrapper" class="hidden p-3.5 rounded-xl bg-[#020610] border border-purple-500/20 space-y-2.5 font-mono">
                        <div class="flex items-center justify-between border-b border-purple-500/20 pb-2">
                            <div class="flex items-center gap-1.5 text-xs font-mono font-bold text-purple-400">
                                <span class="material-symbols-outlined text-sm">desktop_windows</span>
                                <span>Contraseña VNC (Cifrado AES-256)</span>
                            </div>
                            <span class="text-[9px] font-mono px-2 py-0.5 rounded bg-purple-500/10 text-purple-300 border border-purple-500/30">Auto-login noVNC</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-[10.5px] font-mono text-slate-300 mb-0.5">Contraseña VNC</label>
                                <div class="relative">
                                    <input type="password" name="vnc_password" id="create-vnc-password" placeholder="Contraseña de escritorio remoto" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-purple-400 focus:outline-none pr-7">
                                    <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                        <span class="material-symbols-outlined text-xs">visibility</span>
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-end pb-1.5">
                                <p class="text-[10px] font-mono text-slate-400 leading-tight">
                                    Si el host tiene clave VNC, se guardará cifrada para iniciar sesión automáticamente al abrir la consola web.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- CREDENCIALES SSH DE GESTIÓN Y RESPALDO -->
                    <div id="create-ssh-wrapper" class="p-3.5 rounded-xl bg-[#020610] border border-emerald-500/20 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-xs font-mono font-bold text-emerald-400">
                                <span class="material-symbols-outlined text-sm">key</span>
                                <span>Credenciales de Gestión SSH (Cifrado AES-256)</span>
                            </div>
                            <span class="text-[9px] font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-300 border border-emerald-500/30">GitOps Ready</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <div>
                                <label class="block text-[10.5px] font-mono text-slate-300 mb-0.5">Usuario SSH</label>
                                <input type="text" name="ssh_username" placeholder="admin / cisco" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10.5px] font-mono text-slate-300 mb-0.5">Contraseña</label>
                                <div class="relative">
                                    <input type="password" name="ssh_password" placeholder="Contraseña SSH" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-none pr-7">
                                    <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                        <span class="material-symbols-outlined text-xs">visibility</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10.5px] font-mono text-slate-300 mb-0.5">Enable Secret</label>
                                <div class="relative">
                                    <input type="password" name="ssh_enable_secret" placeholder="Clave Cisco" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-none pr-7">
                                    <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                        <span class="material-symbols-outlined text-xs">visibility</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DESPLIEGUE REMOTO DE AGENTE GLPI (SOLO DESKTOP / PC / LAPTOP) -->
                    <div id="create-glpi-wrapper" class="p-3.5 rounded-xl bg-[#040f1f] border border-cyan-500/40 space-y-3 transition duration-200 shadow-sm">
                        <div class="flex items-center justify-between pb-2 border-b border-cyan-500/20">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-cyan-400 text-base">smart_toy</span>
                                <div>
                                    <h5 class="text-xs font-bold font-mono text-cyan-200">Despliegue Remoto de Agente GLPI</h5>
                                    <p class="text-[10px] font-mono text-obsidian-muted">Instalación automatizada y censo de hardware por red</p>
                                </div>
                            </div>
                            <label class="flex items-center gap-1.5 cursor-pointer select-none px-2.5 py-1 rounded-lg bg-cyan-950/80 border border-cyan-500/40 hover:border-cyan-400 transition">
                                <input type="checkbox" name="deploy_glpi" id="create-deploy-glpi" value="1" onchange="toggleGlpiFields(this.checked)" class="w-4 h-4 rounded bg-[#020710] border-cyan-500/50 text-cyan-400 focus:ring-0 cursor-pointer">
                                <span class="text-[10.5px] font-mono text-cyan-300 font-bold">Instalar GLPI</span>
                            </label>
                        </div>

                        <div id="create-glpi-fields" class="space-y-2.5 hidden pt-1">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="text-[10.5px] font-mono text-cyan-300">Sistema Operativo *</label>
                                        <button type="button" onclick="detectGlpiOs()" class="text-[9.5px] font-mono text-cyan-400 hover:text-white underline flex items-center gap-0.5 cursor-pointer" title="Sondear puertos de red para detectar SO">
                                            <span class="material-symbols-outlined text-[11px]">travel_explore</span>
                                            <span>Auto-Detectar</span>
                                        </button>
                                    </div>
                                    <select name="glpi_os" id="create-glpi-os" onchange="handleGlpiOsChange(this.value)" class="w-full px-2.5 py-1.5 rounded-lg bg-[#020710] border border-cyan-500/40 text-white text-xs font-mono focus:outline-none">
                                        <option value="windows" selected>🪟 Microsoft Windows (PowerShell)</option>
                                        <option value="linux">🐧 Linux (SSH / Bash)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10.5px] font-mono text-cyan-300 mb-1">Servidor Inventario Destino</label>
                                    <select name="glpi_target" class="w-full px-2.5 py-1.5 rounded-lg bg-[#020710] border border-cyan-500/40 text-white text-xs font-mono focus:outline-none">
                                        <option value="dev" selected>Servidor Local Desarrollo (192.168.1.220)</option>
                                        <option value="prod">Servidor Producción (192.168.1.250)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-[10.5px] font-mono text-slate-300 mb-0.5">Usuario Admin/SUDO</label>
                                    <input type="text" name="glpi_user" id="create-glpi-user" value="Administrador" placeholder="Administrador / soporte" class="w-full px-2.5 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10.5px] font-mono text-slate-300 mb-0.5">Contraseña</label>
                                    <div class="relative">
                                        <input type="password" name="glpi_password" id="create-glpi-password" placeholder="Clave remota" class="w-full px-2.5 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none pr-7">
                                        <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                            <span class="material-symbols-outlined text-xs">visibility</span>
                                        </button>
                                    </div>
                                </div>
                                <div id="create-glpi-domain-wrapper">
                                    <label class="block text-[10.5px] font-mono text-slate-300 mb-0.5">Dominio AD (Windows)</label>
                                    <input type="text" name="glpi_domain" id="create-glpi-domain" placeholder="Ej: empresa" class="w-full px-2.5 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                                </div>
                            </div>
                            <div id="glpi-detect-status" class="text-[10px] font-mono text-cyan-300 hidden p-1.5 rounded bg-cyan-950/40 border border-cyan-500/20"></div>
                        </div>
                    </div>

                    <!-- MONITOREO ICMP & ALERTAS TELEGRAM -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- MONITOREO ICMP ACTIVO -->
                        <div class="flex items-center justify-between p-3.5 rounded-xl bg-[#020710] border border-obsidian-border hover:border-emerald-500/40 transition">
                            <div class="flex items-center gap-2.5">
                                <span class="material-symbols-outlined text-emerald-400 text-xl">check_circle</span>
                                <div>
                                    <label for="create-is-active" class="text-xs font-mono text-white font-semibold cursor-pointer select-none block">
                                        Monitoreo Activo (Ping ICMP)
                                    </label>
                                    <span class="text-[10px] font-mono text-obsidian-muted">Supervisión continua en tiempo real</span>
                                </div>
                            </div>
                            <input type="checkbox" name="is_active" id="create-is-active" value="1" checked class="w-4 h-4 rounded bg-obsidian-panel border-obsidian-border text-emerald-400 cursor-pointer focus:ring-0">
                        </div>

                        <!-- NOTIFICACIONES TELEGRAM -->
                        <div class="p-3.5 rounded-xl bg-[#020710] border border-cyan-500/30 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm text-cyan-400">notifications_active</span>
                                    <div>
                                        <label for="create-device-telegram-alert" class="text-white text-xs font-mono font-semibold cursor-pointer select-none block">
                                            Notificaciones Telegram
                                        </label>
                                        <span class="text-[10px] font-mono text-obsidian-muted">Despacho de alertas de caídas</span>
                                    </div>
                                </div>
                                <input type="hidden" name="telegram_alert_enabled" value="0">
                                <input type="checkbox" id="create-device-telegram-alert" name="telegram_alert_enabled" value="1" onchange="document.getElementById('create-device-target-wrapper').classList.toggle('hidden', !this.checked)" class="w-4 h-4 rounded bg-obsidian-panel border-obsidian-border text-cyan-400 cursor-pointer focus:ring-0"/>
                            </div>
                            <div id="create-device-target-wrapper" class="hidden pt-1.5 border-t border-cyan-500/20">
                                <label class="block text-cyan-300 mb-1 text-[10px] uppercase font-bold font-mono">Destino de la Alerta:</label>
                                <select name="telegram_alert_target" class="w-full bg-[#051424] border border-cyan-500/40 rounded-lg px-2.5 py-1 text-white text-xs font-mono focus:outline-none">
                                    <option value="owner">👤 Chat Privado del Administrador (Owner)</option>
                                    <option value="group">👥 Grupo Corporativo General</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- NOTAS TÉCNICAS (MULTILÍNEA) -->
                    <div>
                        <label class="block text-[11px] font-mono text-cyan-400 mb-1 font-semibold uppercase flex items-center justify-between">
                            <span>Notas Técnicas &amp; Conexión de Puertos</span>
                            <span class="text-[9.5px] text-obsidian-muted font-normal">Saltos de línea permitidos</span>
                        </label>
                        <textarea name="notes" rows="3" placeholder="Detalles de cascadas, puertos de enlace troncal, conexiones directas, VLANs..." class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-cyan-500/40 text-cyan-200 text-xs font-mono placeholder-obsidian-muted/60 focus:border-cyan-400 focus:outline-none scrollbar-thin leading-relaxed"></textarea>
                    </div>
                </div>

            </div>

            <!-- PIE -->
            <div class="p-4 px-6 border-t border-obsidian-border bg-[#061527] flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
                <div class="text-[11px] font-mono text-obsidian-muted flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-cyan-400">info</span>
                    <span>Campos marcados con (*) son obligatorios para el censo y monitoreo.</span>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeDeviceCreateModal()" class="px-4 py-2 rounded-xl bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-white/10 transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20 cursor-pointer">
                        <span class="material-symbols-outlined text-base">save</span>
                        Registrar Dispositivo
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MINI-MODAL: CREACIÓN RÁPIDA DE SEDE REGIONAL (IN-LINE AJAX)                -->
<!-- ========================================================================= -->
<div id="modal-quick-site" class="fixed inset-0 z-60 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="glass-panel w-full max-w-md rounded-2xl border border-purple-500/50 flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/98 animate-in fade-in zoom-in-95 duration-150 max-h-[85vh] sm:max-h-[88vh]">
        <!-- CABECERA -->
        <div class="p-3.5 px-5 border-b border-obsidian-border flex items-center justify-between bg-[#0e0720] shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-purple-950/90 border border-purple-500/40 flex items-center justify-center text-purple-300">
                    <span class="material-symbols-outlined text-lg">domain_add</span>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-white font-mono uppercase tracking-wider">Nueva Sede Regional</h4>
                    <p class="text-[10px] text-obsidian-muted font-mono">Incorporación instantánea sin salir del formulario</p>
                </div>
            </div>
            <button type="button" onclick="closeQuickSiteModal()" class="text-obsidian-muted hover:text-white p-1 rounded-lg hover:bg-white/10 transition cursor-pointer">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <!-- FORMULARIO RÁPIDO -->
        <form id="form-quick-site" onsubmit="submitQuickSite(event)" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            <div class="p-4 space-y-3.5 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-[11px] font-mono text-purple-300 mb-1 font-semibold uppercase">Nombre de la Sede *</label>
                    <input type="text" id="quick-site-name" required placeholder="Ej: CIAU VALLE SECO / CENTRO DE SERVICIO" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-purple-500/40 text-white text-xs font-mono placeholder-obsidian-muted/60 focus:border-purple-400 focus:outline-none transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10.5px] font-mono text-obsidian-muted mb-1 font-semibold uppercase">IP Enlace / Gateway</label>
                        <input type="text" id="quick-site-ip" placeholder="Ej: 192.168.1.1" class="w-full px-3 py-1.5 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono placeholder-obsidian-muted/60 focus:border-purple-400 focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[10.5px] font-mono text-obsidian-muted mb-1 font-semibold uppercase">Teléfono Contacto</label>
                        <input type="text" id="quick-site-phone" placeholder="Ej: 0242-2210000" class="w-full px-3 py-1.5 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono placeholder-obsidian-muted/60 focus:border-purple-400 focus:outline-none transition">
                    </div>
                </div>

                <div id="quick-site-error" class="hidden text-xs font-mono text-rose-400 p-2.5 rounded-lg bg-rose-950/40 border border-rose-500/40 max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all block"></div>
            </div>

            <div class="p-3 px-5 flex items-center justify-end gap-2 border-t border-obsidian-border/60 shrink-0 bg-[#070514]">
                <button type="button" onclick="closeQuickSiteModal()" class="px-3 py-1.5 rounded-xl bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-white/10 transition cursor-pointer">
                    Cancelar
                </button>
                <button type="submit" id="btn-quick-site-submit" class="px-4 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-md shadow-purple-600/30 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">save</span>
                    <span>Guardar Sede</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 3: EDICIÓN DE DISPOSITIVO (SOLO ADMINISTRADORES)                     -->
<!-- ========================================================================= -->
<div id="modal-device-edit" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-5">
    <div class="glass-panel w-full max-w-5xl rounded-2xl border border-amber-500/50 flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95 animate-in fade-in zoom-in-95 duration-200 max-h-[85vh] sm:max-h-[88vh]">
        <!-- CABECERA -->
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between bg-[#1f1406] shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-950/80 border border-amber-500/40 flex items-center justify-center text-amber-300 shadow-md">
                    <span class="material-symbols-outlined text-2xl">edit_note</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white font-mono flex items-center gap-2">
                        <span>Editar Parámetros Técnicos &amp; Puesto</span>
                        <span id="edit-glpi-badge" class="hidden w-6 h-6 rounded-md bg-cyan-950 text-cyan-300 border border-cyan-700/60 inline-flex items-center justify-center cursor-help" title="Agente GLPI Activo en este dispositivo">
                            <span class="material-symbols-outlined text-xs">bolt</span>
                        </span>
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono flex items-center gap-1.5 flex-wrap">
                        <span class="text-amber-300 font-bold" id="edit-device-name-badge">--</span>
                        <span>&bull;</span>
                        <span>Gestión unificada de hardware, periféricos, red, resguardo y asignación</span>
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeDeviceEditModal()" class="text-obsidian-muted hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer" title="Cerrar ventana">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- FORMULARIO -->
        <form id="form-device-edit" method="POST" action="" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            @csrf
            @method('PUT')
            <input type="hidden" name="has_peripherals_form" value="1">
            <div class="p-5 sm:p-6 overflow-y-auto flex-1 custom-table-scroll space-y-5">

                <!-- ========================================================================= -->
                <!-- BLOQUE 1: IDENTIDAD OPERATIVA & SEDE REGIONAL                              -->
                <!-- ========================================================================= -->
                <div class="rounded-2xl bg-[#030914]/90 border border-purple-500/30 p-4 sm:p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-purple-500/20 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-purple-950/80 border border-purple-500/40 text-purple-300 material-symbols-outlined text-lg">domain</span>
                            <div>
                                <h4 class="text-xs font-bold font-mono text-purple-200 uppercase tracking-wide">1. Identidad Operativa &amp; Sede Regional</h4>
                                <p class="text-[10.5px] font-mono text-obsidian-muted">Ubicación física en el cluster y rol operativo asignado</p>
                            </div>
                        </div>
                        <span class="text-[9.5px] font-mono font-bold px-2 py-0.5 rounded-full bg-purple-950 text-purple-300 border border-purple-500/40">Obligatorio</span>
                    </div>

                    <!-- SEDE REGIONAL CON BOTÓN DE NUEVA SEDE -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[11px] font-mono text-purple-300 font-semibold uppercase flex items-center gap-1.5">
                                <span>Sede Regional Asignada *</span>
                            </label>
                            <button type="button" onclick="openQuickSiteModal()" class="px-2.5 py-0.5 rounded-lg bg-purple-950/80 border border-purple-500/40 text-purple-200 hover:bg-purple-600 hover:text-white transition text-[10px] font-mono font-bold flex items-center gap-1 cursor-pointer shadow-xs" title="Crear una nueva sede regional sin salir del formulario">
                                <span class="material-symbols-outlined text-xs">add_circle</span>
                                <span>+ Nueva Sede</span>
                            </button>
                        </div>
                        <select name="monitored_site_id" id="edit-site-id" required class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-purple-500/40 text-white text-xs font-mono focus:border-cyan-400 focus:outline-none transition cursor-pointer">
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} (IP Enlace: {{ $s->ip ?: 'Sin Enlace' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- NOMBRE ETIQUETA -->
                        <div>
                            <label class="block text-[11px] font-mono text-cyan-400 mb-1 font-semibold uppercase flex items-center justify-between">
                                <span>Nombre Etiqueta *</span>
                                <span class="text-[9.5px] text-cyan-400/90 font-bold">Identidad Operativa</span>
                            </label>
                            <input type="text" name="name" id="edit-name" required placeholder="Ej: JEFE DE OFICINA / TAQUILLA 01" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-cyan-500/40 text-white text-xs font-mono placeholder-obsidian-muted/60 focus:border-cyan-400 focus:outline-none transition">
                            <span class="text-[9.5px] font-mono text-obsidian-muted mt-1 block">Rol funcional visible principal en NOC e inventario</span>
                        </div>

                        <!-- TIPO DE ACTIVO / EQUIPO -->
                        <div>
                            <label class="block text-[11px] font-mono text-cyan-400 mb-1 font-semibold uppercase">Tipo de Activo / Equipo</label>
                            <select name="type" id="edit-type" onchange="handleEditDeviceTypeChange(this.value)" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-cyan-500/40 text-cyan-200 text-xs font-mono focus:border-cyan-400 focus:outline-none transition cursor-pointer">
                                <optgroup label="💻 Cómputo &amp; Servidores">
                                    <option value="desktop">🖥️ Desktop / Computadora PC</option>
                                    <option value="laptop">💻 Laptop / Portátil</option>
                                    <option value="server">🖳 Servidor Central</option>
                                </optgroup>
                                <optgroup label="🌐 Equipos de Red (NOC)">
                                    <option value="router">🌐 Router Gateway</option>
                                    <option value="switch">🔀 Switch de Red</option>
                                    <option value="firewall">🛡️ Firewall / Seguridad</option>
                                    <option value="access_point">📡 Access Point Wi-Fi</option>
                                    <option value="printer">🖨️ Impresora de Red</option>
                                    <option value="ip_phone">📞 Teléfono IP VoIP</option>
                                </optgroup>
                                <optgroup label="🔋 Energía &amp; Respaldo">
                                    <option value="ups">🔋 Energía / UPS</option>
                                    <option value="surge_protector">⚡ Protector de Voltaje / Regleta</option>
                                </optgroup>
                                <optgroup label="🖱️ Periféricos &amp; Accesorios (ITAM)">
                                    <option value="monitor">🖥️ Monitor / Pantalla</option>
                                    <option value="keyboard">⌨️ Teclado</option>
                                    <option value="mouse">🖱️ Mouse / Ratón</option>
                                    <option value="headset">🎧 Auriculares / Headset</option>
                                    <option value="scanner">📠 Escáner / Lector</option>
                                    <option value="other_peripheral">📦 Otro Periférico / Accesorio</option>
                                </optgroup>
                            </select>
                            <span class="text-[9.5px] font-mono text-obsidian-muted mt-1 block">Clasificación en censo de activos</span>
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- BLOQUE 2: HARDWARE, CHASIS & ESPECIFICACIONES DE RED                       -->
                <!-- ========================================================================= -->
                <div class="rounded-2xl bg-[#030914]/90 border border-emerald-500/30 p-4 sm:p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-emerald-500/20 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-emerald-950/80 border border-emerald-500/40 text-emerald-300 material-symbols-outlined text-lg">lan</span>
                            <div>
                                <h4 class="text-xs font-bold font-mono text-emerald-200 uppercase tracking-wide">2. Hardware, Chasis &amp; Red</h4>
                                <p class="text-[10.5px] font-mono text-obsidian-muted">Direccionamiento de red, serial de máquina y fabricante</p>
                            </div>
                        </div>
                        <span class="text-[9.5px] font-mono font-bold px-2 py-0.5 rounded-full bg-emerald-950 text-emerald-300 border border-emerald-500/40">Conectividad</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5" id="edit-ip-mac-wrapper">
                        <!-- IP -->
                        <div>
                            <label class="block text-[11px] font-mono text-white mb-1 font-semibold uppercase flex items-center justify-between">
                                <span>Dirección IP *</span>
                                <span class="text-[9px] text-emerald-400 font-mono">Ping ICMP</span>
                            </label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-obsidian-muted text-sm">wifi</span>
                                <input type="text" name="ip" id="edit-ip" required placeholder="Ej: 192.168.1.10" class="w-full pl-9 pr-3 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none transition">
                            </div>
                        </div>

                        <!-- MAC -->
                        <div>
                            <label class="block text-[11px] font-mono text-obsidian-muted mb-1 font-semibold uppercase flex items-center justify-between">
                                <span>Dirección MAC</span>
                                <span class="text-[9px] text-obsidian-muted">WoL / ARP</span>
                            </label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-obsidian-muted text-sm">fingerprint</span>
                                <input type="text" name="mac" id="edit-mac" placeholder="00:1A:2B:3C:4D:5E" class="w-full pl-9 pr-3 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none transition">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <!-- FABRICANTE -->
                        <div>
                            <label class="block text-[11px] font-mono text-obsidian-muted mb-1 font-semibold uppercase">Fabricante / Vendor</label>
                            <input type="text" name="vendor_data" id="edit-vendor-data" placeholder="Ej: HP, Dell, Cisco Systems" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none transition">
                        </div>

                        <!-- MODELO -->
                        <div>
                            <label class="block text-[11px] font-mono text-cyan-400 mb-1 font-semibold uppercase">Modelo de Hardware</label>
                            <input type="text" name="model" id="edit-model" placeholder="Ej: ProDesk 400 G4 / Catalyst 2960" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-cyan-500/40 text-cyan-200 text-xs font-mono focus:border-cyan-400 focus:outline-none transition">
                        </div>

                        <!-- SERIAL (CON ALERTA REACTIVA GLPI) -->
                        <div>
                            <label class="block text-[11px] font-mono text-amber-400 mb-1 font-semibold uppercase">Número de Serie (Serial)</label>
                            <input type="text" name="serial" id="edit-serial" placeholder="Ej: MXL7241ABC / FOC1234X5YZ" oninput="handleEditSerialInput(this.value)" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-amber-500/40 text-amber-200 text-xs font-mono focus:border-amber-400 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- BANNER REACTIVO DE ADVERTENCIA GLPI -->
                    <div id="edit-glpi-serial-warning" class="hidden p-3 rounded-xl bg-amber-950/80 border border-amber-500/60 text-amber-200 text-xs font-mono flex items-start gap-2.5 animate-in fade-in duration-150 shadow-md">
                        <span class="material-symbols-outlined text-amber-400 text-lg shrink-0 mt-0.5">warning</span>
                        <div class="space-y-0.5">
                            <div class="font-bold text-amber-300">⚠️ Advertencia de Sincronización Automática GLPI:</div>
                            <p class="text-[11px] text-amber-200/90 leading-tight">
                                Este equipo cuenta con agente GLPI activo (<span id="edit-glpi-sync-time" class="font-semibold text-white">--</span>). Si modifica el serial manualmente, <strong>será actualizado/sobrescrito por el agente GLPI</strong> en la próxima sincronización automática con el serial reportado por la BIOS/Motherboard.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- CONDICIÓN / ESTADO OPERATIVO -->
                        <div>
                            <label class="block text-[11px] font-mono text-emerald-400 mb-1 font-semibold uppercase flex items-center justify-between">
                                <span>Condición / Estado Operativo *</span>
                                <span class="text-[9.5px] font-mono text-emerald-400/90 font-bold">Censo de Inventario</span>
                            </label>
                            <select name="status" id="edit-status" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-emerald-500/40 text-emerald-200 text-xs font-mono focus:border-cyan-400 focus:outline-none transition cursor-pointer">
                                <option value="assigned">🟢 Asignado (En Uso / Operativo)</option>
                                <option value="in_stock">🔵 En Stock (Disponible / Almacén)</option>
                                <option value="in_maintenance">🟡 En Mantenimiento (En Taller / Diagnóstico)</option>
                                <option value="in_transit">🟣 En Movilización / Tránsito (Acta UINF-4.05)</option>
                                <option value="damaged">🔴 Dañado / Falla Pendiente</option>
                                <option value="decommissioned">⚫ Desincorporado / Baja Definitiva</option>
                            </select>
                        </div>

                        <!-- PUERTOS / INTERFACES -->
                        <div>
                            <label class="block text-[11px] font-mono text-obsidian-muted mb-1 font-semibold uppercase">Especificación de Puertos / Interfaces</label>
                            <input type="text" name="ports" id="edit-ports" placeholder="Ej: 1x GbE LAN / 48x GbE PoE + 4x SFP+" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none transition">
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- BLOQUE 3: PERIFÉRICOS DE PUESTO DE TRABAJO (BASE Y ADICIONALES)           -->
                <!-- ========================================================================= -->
                <div id="edit-section-peripherals" class="rounded-2xl bg-[#030d1c]/90 border border-cyan-500/40 p-4 sm:p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-cyan-500/20 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 material-symbols-outlined text-lg">desktop_mac</span>
                            <div>
                                <h4 class="text-xs font-bold font-mono text-white uppercase tracking-wide">3. Periféricos de Puesto de Trabajo</h4>
                                <p class="text-[10.5px] font-mono text-obsidian-muted">Equipos complementarios vinculados a esta estación física (Monitor, Teclado, Mouse, Regulador y extras)</p>
                            </div>
                        </div>
                        <span class="text-[9.5px] font-mono font-bold px-2 py-0.5 rounded-full bg-cyan-950 text-cyan-300 border border-cyan-500/40">Puesto de Trabajo</span>
                    </div>

                    <!-- GRID DE 4 PERIFÉRICOS BASE (ESTILO ACORDEÓN) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs font-mono">
                        <!-- 1. MONITOR -->
                        <div class="p-3 rounded-xl bg-[#040e1e] border border-cyan-500/30 space-y-2">
                            <div class="flex items-center justify-between pb-1 border-b border-obsidian-border/50">
                                <div class="flex items-center gap-1.5 text-cyan-300 font-bold text-xs">
                                    <span class="material-symbols-outlined text-sm">monitor</span>
                                    <span>Monitor Principal</span>
                                </div>
                                <label class="inline-flex items-center gap-1 text-[10px] text-rose-400 cursor-pointer select-none">
                                    <input type="checkbox" name="remove_monitor" id="edit-remove-monitor" value="1" class="rounded bg-obsidian-panel border-rose-500/40 text-rose-500 focus:ring-0">
                                    <span>Quitar</span>
                                </label>
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Marca</label>
                                <input type="text" name="monitor_brand" id="edit-monitor-brand" placeholder="ej. HP / Dell / LG" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Modelo</label>
                                <input type="text" name="monitor_model" id="edit-monitor-model" placeholder="ej. V193b 18.5&quot;" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-amber-400/90 mb-0.5">Serial</label>
                                <input type="text" name="monitor_serial" id="edit-monitor-serial" placeholder="ej. 3CQ51323GC" class="w-full px-2 py-1 rounded bg-[#020710] border border-amber-500/30 text-amber-200 text-[11px] font-mono focus:border-amber-400 focus:outline-none">
                            </div>
                        </div>

                        <!-- 2. TECLADO -->
                        <div class="p-3 rounded-xl bg-[#040e1e] border border-cyan-500/30 space-y-2">
                            <div class="flex items-center justify-between pb-1 border-b border-obsidian-border/50">
                                <div class="flex items-center gap-1.5 text-cyan-300 font-bold text-xs">
                                    <span class="material-symbols-outlined text-sm">keyboard</span>
                                    <span>Teclado</span>
                                </div>
                                <label class="inline-flex items-center gap-1 text-[10px] text-rose-400 cursor-pointer select-none">
                                    <input type="checkbox" name="remove_keyboard" id="edit-remove-keyboard" value="1" class="rounded bg-obsidian-panel border-rose-500/40 text-rose-500 focus:ring-0">
                                    <span>Quitar</span>
                                </label>
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Marca</label>
                                <input type="text" name="keyboard_brand" id="edit-keyboard-brand" placeholder="ej. Genius / HP / Logitech" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Modelo</label>
                                <input type="text" name="keyboard_model" id="edit-keyboard-model" placeholder="ej. KB-110 USB" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-amber-400/90 mb-0.5">Serial</label>
                                <input type="text" name="keyboard_serial" id="edit-keyboard-serial" placeholder="ej. SN-KBD-098" class="w-full px-2 py-1 rounded bg-[#020710] border border-amber-500/30 text-amber-200 text-[11px] font-mono focus:border-amber-400 focus:outline-none">
                            </div>
                        </div>

                        <!-- 3. MOUSE -->
                        <div class="p-3 rounded-xl bg-[#040e1e] border border-cyan-500/30 space-y-2">
                            <div class="flex items-center justify-between pb-1 border-b border-obsidian-border/50">
                                <div class="flex items-center gap-1.5 text-cyan-300 font-bold text-xs">
                                    <span class="material-symbols-outlined text-sm">mouse</span>
                                    <span>Mouse / Ratón</span>
                                </div>
                                <label class="inline-flex items-center gap-1 text-[10px] text-rose-400 cursor-pointer select-none">
                                    <input type="checkbox" name="remove_mouse" id="edit-remove-mouse" value="1" class="rounded bg-obsidian-panel border-rose-500/40 text-rose-500 focus:ring-0">
                                    <span>Quitar</span>
                                </label>
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Marca</label>
                                <input type="text" name="mouse_brand" id="edit-mouse-brand" placeholder="ej. Logitech / Dell / Genius" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Modelo</label>
                                <input type="text" name="mouse_model" id="edit-mouse-model" placeholder="ej. M100 Óptico USB" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-amber-400/90 mb-0.5">Serial</label>
                                <input type="text" name="mouse_serial" id="edit-mouse-serial" placeholder="ej. SN-MSE-441" class="w-full px-2 py-1 rounded bg-[#020710] border border-amber-500/30 text-amber-200 text-[11px] font-mono focus:border-amber-400 focus:outline-none">
                            </div>
                        </div>

                        <!-- 4. PROTECTOR / UPS -->
                        <div class="p-3 rounded-xl bg-[#040e1e] border border-cyan-500/30 space-y-2">
                            <div class="flex items-center justify-between pb-1 border-b border-obsidian-border/50">
                                <div class="flex items-center gap-1.5 text-cyan-300 font-bold text-xs">
                                    <span class="material-symbols-outlined text-sm">bolt</span>
                                    <span>Protector / UPS</span>
                                </div>
                                <label class="inline-flex items-center gap-1 text-[10px] text-rose-400 cursor-pointer select-none">
                                    <input type="checkbox" name="remove_protector" id="edit-remove-protector" value="1" class="rounded bg-obsidian-panel border-rose-500/40 text-rose-500 focus:ring-0">
                                    <span>Quitar</span>
                                </label>
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Tipo</label>
                                <select name="protector_type" id="edit-protector-type" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                                    <option value="surge_protector">Regulador de Voltaje</option>
                                    <option value="ups">Sistema UPS</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] text-obsidian-muted mb-0.5">Marca y Modelo</label>
                                <div class="grid grid-cols-2 gap-1">
                                    <input type="text" name="protector_brand" id="edit-protector-brand" placeholder="Forza" class="w-full px-1.5 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[10.5px] font-mono focus:border-cyan-400 focus:outline-none">
                                    <input type="text" name="protector_model" id="edit-protector-model" placeholder="FVR-1211" class="w-full px-1.5 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[10.5px] font-mono focus:border-cyan-400 focus:outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-amber-400/90 mb-0.5">Serial</label>
                                <input type="text" name="protector_serial" id="edit-protector-serial" placeholder="ej. SN-PWR-001" class="w-full px-2 py-1 rounded bg-[#020710] border border-amber-500/30 text-amber-200 text-[11px] font-mono focus:border-amber-400 focus:outline-none">
                            </div>
                        </div>
                    </div>

                    <!-- SUB-SECCIÓN: PERIFÉRICOS ADICIONALES (IMPRESORA, ESCÁNER, HEADSET, ETC.) -->
                    <div class="pt-3 border-t border-cyan-500/20 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-xs font-mono font-bold text-cyan-300">
                                <span class="material-symbols-outlined text-sm">print</span>
                                <span>Periféricos Adicionales Asignados (Impresoras, Escáneres, Auriculares, etc.)</span>
                            </div>
                            <button type="button" onclick="addEditExtraPeripheral()" class="px-3 py-1 rounded-lg bg-cyan-950/80 hover:bg-cyan-600 border border-cyan-500/40 text-cyan-200 hover:text-white transition text-[11px] font-mono font-bold flex items-center gap-1.5 cursor-pointer shadow-xs">
                                <span class="material-symbols-outlined text-xs">add</span>
                                <span>+ Añadir Periférico Adicional</span>
                            </button>
                        </div>
                        <div id="edit-extra-peripherals-list" class="space-y-2.5">
                            <!-- Inyectado dinámicamente con JS -->
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- BLOQUE 4: RESPONSABLE & ASIGNACIÓN (LDAP / UINF-4.05)         -->
                <!-- ========================================================================= -->
                <div id="edit-assignment-wrapper" class="rounded-2xl bg-[#030d1c]/90 border border-cyan-500/30 p-4 sm:p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-cyan-500/20 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 material-symbols-outlined text-lg">badge</span>
                            <div>
                                <h4 class="text-xs font-bold font-mono text-cyan-200 uppercase tracking-wide">4. Responsable &amp; Asignación (Acta UINF-4.05)</h4>
                                <p class="text-[10.5px] font-mono text-obsidian-muted">Resguardo patrimonial y control de entregas patrimoniales mediante LDAP</p>
                            </div>
                        </div>
                        <span id="edit-assignment-badge" class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono">--</span>
                    </div>

                    <x-ldap-user-picker 
                        id="edit-device-picker" 
                        name="custodian" 
                        label="Buscar Trabajador en Directorio Activo LDAP:" 
                        :show-details="true"
                        :can-release="true" />
                </div>

                <!-- ========================================================================= -->
                <!-- BLOQUE 5: CONECTIVIDAD AVANZADA, MONITOREO & ALERTAS                      -->
                <!-- ========================================================================= -->
                <div id="edit-section-monitoring" class="rounded-2xl bg-[#030914]/90 border border-cyan-500/30 p-4 sm:p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-cyan-500/20 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 material-symbols-outlined text-lg">monitoring</span>
                            <div>
                                <h4 class="text-xs font-bold font-mono text-cyan-200 uppercase tracking-wide">5. Supervisión, Alertas &amp; Acceso Remoto</h4>
                                <p class="text-[10.5px] font-mono text-obsidian-muted">Monitoreo continuo ICMP, despachos por Telegram y consola de gestión</p>
                            </div>
                        </div>
                        <span class="text-[9.5px] font-mono font-bold px-2 py-0.5 rounded-full bg-cyan-950 text-cyan-300 border border-cyan-500/40">Operaciones</span>
                    </div>

                    <!-- PROTOCOLO Y PUERTO -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5" id="edit-access-wrapper">
                        <div>
                            <label class="block text-[11px] font-mono text-obsidian-muted mb-1 font-semibold uppercase">Protocolo de Acceso</label>
                            <select name="access_type" id="edit-access-type" onchange="handleEditAccessChange(this.value)" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none transition cursor-pointer">
                                <option value="SSH">Terminal SSH</option>
                                <option value="TELNET">Terminal Telnet</option>
                                <option value="WEB">Interfaz Web (HTTP/HTTPS)</option>
                                <option value="VNC">Escritorio Remoto VNC</option>
                                <option value="SSH+VNC">Dual: SSH (22) + VNC (5900)</option>
                                <option value="SIN SOPORTE">Sin Acceso Remoto</option>
                            </select>
                        </div>
                        <div id="edit-single-port-wrapper">
                            <label class="block text-[11px] font-mono text-obsidian-muted mb-1 font-semibold uppercase" id="edit-access-port-label">Puerto de Conexión</label>
                            <input type="number" name="access_port" id="edit-access-port" placeholder="22" min="1" max="65535" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- PUERTOS DUALES DEDICADOS (SSH + VNC) -->
                    <div id="edit-dual-ports-wrapper" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-[11px] font-mono text-emerald-400 mb-1 font-semibold uppercase">Puerto SSH</label>
                            <input type="number" name="ssh_port" id="edit-ssh-port" placeholder="22" min="1" max="65535" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-emerald-500/40 text-white text-xs font-mono focus:border-emerald-400 focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-[11px] font-mono text-purple-400 mb-1 font-semibold uppercase">Puerto VNC</label>
                            <input type="number" name="vnc_port" id="edit-vnc-port" placeholder="5900" min="1" max="65535" class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-purple-500/40 text-white text-xs font-mono focus:border-purple-400 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- CREDENCIALES VNC (ESCRITORIO REMOTO) -->
                    <div id="edit-vnc-wrapper" class="hidden p-3.5 rounded-xl bg-[#020610] border border-purple-500/20 space-y-2.5 font-mono">
                        <div class="flex items-center justify-between border-b border-purple-500/20 pb-2">
                            <div class="flex items-center gap-1.5 text-xs font-mono font-bold text-purple-400">
                                <span class="material-symbols-outlined text-sm">desktop_windows</span>
                                <span>Contraseña VNC (Cifrado AES-256)</span>
                            </div>
                            <div id="edit-vnc-cred-status">
                                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-500/20 text-slate-400 border border-slate-500/30">Sin Contraseña Guardada</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-[10.5px] font-mono text-slate-300 mb-0.5">Nueva Contraseña VNC</label>
                                <div class="relative">
                                    <input type="password" name="vnc_password" id="edit-vnc-password" placeholder="En blanco = mantener" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-purple-400 focus:outline-none pr-7">
                                    <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                        <span class="material-symbols-outlined text-xs">visibility</span>
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-col justify-end">
                                <label class="inline-flex items-center gap-1.5 text-[10.5px] font-mono text-rose-400 cursor-pointer select-none pb-2">
                                    <input type="checkbox" name="clear_vnc_credentials" id="edit-clear-vnc-credentials" value="1" class="rounded bg-obsidian-panel border-rose-500/40 text-rose-500 focus:ring-0">
                                    <span>Eliminar clave VNC</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- CREDENCIALES SSH DE GESTIÓN Y RESPALDO -->
                    <div id="edit-ssh-wrapper" class="p-3.5 rounded-xl bg-[#020610] border border-emerald-500/20 space-y-2.5 font-mono">
                        <div class="flex items-center justify-between border-b border-emerald-500/20 pb-2">
                            <div class="flex items-center gap-1.5 text-xs font-mono font-bold text-emerald-400">
                                <span class="material-symbols-outlined text-sm">key</span>
                                <span>Credenciales SSH / Respaldo Automático (Cifrado AES-256)</span>
                            </div>
                            <div id="edit-ssh-cred-status">
                                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-500/20 text-slate-400 border border-slate-500/30">Sin Credenciales Guardadas</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <div>
                                <label class="block text-[10.5px] font-mono text-slate-300 mb-0.5">Usuario SSH</label>
                                <input type="text" name="ssh_username" id="edit-ssh-username" placeholder="admin / cisco" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10.5px] font-mono text-slate-300 mb-0.5">Nueva Contraseña</label>
                                <div class="relative">
                                    <input type="password" name="ssh_password" id="edit-ssh-password" placeholder="En blanco = mantener" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-none pr-7">
                                    <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                        <span class="material-symbols-outlined text-xs">visibility</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10.5px] font-mono text-slate-300 mb-0.5">Enable Secret</label>
                                <div class="relative">
                                    <input type="password" name="ssh_enable_secret" id="edit-ssh-enable-secret" placeholder="En blanco = mantener" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-emerald-400 focus:outline-none pr-7">
                                    <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-1.5 top-1.5 text-slate-400 hover:text-white" tabindex="-1">
                                        <span class="material-symbols-outlined text-xs">visibility</span>
                                    </button>
                                </div>
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

                    <!-- MONITOREO ICMP & ALERTAS TELEGRAM -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 font-mono">
                        <!-- MONITOREO ICMP ACTIVO -->
                        <div class="flex items-center justify-between p-3.5 rounded-xl bg-[#020710] border border-obsidian-border hover:border-emerald-500/40 transition">
                            <div class="flex items-center gap-2.5">
                                <span class="material-symbols-outlined text-emerald-400 text-xl">check_circle</span>
                                <div>
                                    <label for="edit-is-active" class="text-xs font-mono text-white font-semibold cursor-pointer select-none block">
                                        Monitoreo Activo (Ping ICMP)
                                    </label>
                                    <span class="text-[10px] font-mono text-obsidian-muted">Supervisión continua en tiempo real</span>
                                </div>
                            </div>
                            <input type="checkbox" name="is_active" id="edit-is-active" value="1" class="w-4 h-4 rounded bg-obsidian-panel border-obsidian-border text-emerald-400 cursor-pointer focus:ring-0">
                        </div>

                        <!-- NOTIFICACIONES TELEGRAM -->
                        <div class="p-3.5 rounded-xl bg-[#020710] border border-cyan-500/30 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm text-cyan-400">notifications_active</span>
                                    <div>
                                        <label for="edit-device-telegram-alert" class="text-white text-xs font-mono font-semibold cursor-pointer select-none block">
                                            Notificaciones Telegram
                                        </label>
                                        <span class="text-[10px] font-mono text-obsidian-muted">Avisos de caídas y recuperaciones</span>
                                    </div>
                                </div>
                                <input type="hidden" name="telegram_alert_enabled" value="0">
                                <input type="checkbox" id="edit-device-telegram-alert" name="telegram_alert_enabled" value="1" onchange="document.getElementById('edit-device-target-wrapper').classList.toggle('hidden', !this.checked)" class="w-4 h-4 rounded bg-obsidian-panel border-obsidian-border text-cyan-400 cursor-pointer focus:ring-0"/>
                            </div>
                            <div id="edit-device-target-wrapper" class="hidden pt-1.5 border-t border-cyan-500/20">
                                <label class="block text-cyan-300 mb-1 text-[10px] uppercase font-bold font-mono">Destino de la Alerta:</label>
                                <select id="edit-device-telegram-target" name="telegram_alert_target" class="w-full bg-[#051424] border border-cyan-500/40 rounded-lg px-2.5 py-1 text-white text-xs font-mono focus:outline-none">
                                    <option value="owner">👤 Chat Privado del Administrador (Owner)</option>
                                    <option value="group">👥 Grupo Corporativo General</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- NOTAS TÉCNICAS (MULTILÍNEA) -->
                    <div>
                        <label class="block text-[11px] font-mono text-cyan-400 mb-1 font-semibold uppercase flex items-center justify-between">
                            <span>Notas Técnicas &amp; Conexión de Puertos</span>
                            <span class="text-[9.5px] text-obsidian-muted font-normal">Saltos de línea permitidos</span>
                        </label>
                        <textarea name="notes" id="edit-notes" rows="3" placeholder="Detalles técnicos, conexión de cascadas, puertos troncales, VLANs..." class="w-full px-3 py-2 rounded-xl bg-[#020710] border border-cyan-500/40 text-cyan-200 text-xs font-mono placeholder-obsidian-muted/60 focus:border-cyan-400 focus:outline-none scrollbar-thin leading-relaxed"></textarea>
                    </div>
                </div>

            </div>

            <!-- PIE -->
            <div class="p-4 px-6 border-t border-obsidian-border bg-[#1f1406] flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
                <div class="text-[11px] font-mono text-obsidian-muted flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-amber-400">info</span>
                    <span>Modificaciones se sincronizan automáticamente con NOC, ITAM y MariaDB.</span>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeDeviceEditModal()" class="px-4 py-2 rounded-xl bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-white/10 transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-lg shadow-amber-500/20 cursor-pointer">
                        <span class="material-symbols-outlined text-base">save</span>
                        Guardar Cambios &amp; Sincronizar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 4: CREACIÓN RÁPIDA DE SEDE (SOLO ADMINISTRADORES)                   -->
<!-- ========================================================================= -->
<div id="modal-create-site" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="glass-panel max-w-lg w-full rounded-2xl border border-purple-500/40 shadow-2xl flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden bg-[#040d1a]/95">
        <div class="flex items-center justify-between border-b border-obsidian-border p-4 shrink-0 bg-[#0b0816]">
            <h3 class="text-sm font-bold text-white uppercase font-mono flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-400 text-base">domain_add</span>
                Agregar Nueva Sede Regional
            </h3>
            <button type="button" onclick="closeCreateSiteModal()" class="text-obsidian-muted hover:text-white text-xl p-1 rounded hover:bg-obsidian-panel leading-none">&times;</button>
        </div>
        <form action="{{ route('admin.sites.store') }}" method="POST" class="flex flex-col flex-1 min-h-0 overflow-hidden font-mono text-xs">
            @csrf
            <div class="p-4 sm:p-5 space-y-4 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Nombre de la Sede *</label>
                    <input type="text" name="name" required placeholder="ej. CIAU Morón / Sede Paseo Mariño" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">IP Gateway / Enlace:</label>
                        <input type="text" name="ip" placeholder="ej. 192.168.106.193" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
                    </div>
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Dirección Física:</label>
                        <input type="text" name="address" placeholder="Av. Principal, Edif..." class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Teléfono Principal:</label>
                        <input type="text" name="phone_1" placeholder="ej. 0242-3000000" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
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
            </div>

            <div class="flex justify-end gap-2 p-3.5 border-t border-obsidian-border shrink-0 bg-[#060c18]">
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
<div id="modal-device-history" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="glass-panel w-full max-w-4xl rounded-2xl border border-obsidian-border flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95 animate-in fade-in zoom-in-95 duration-200 max-h-[85vh] sm:max-h-[88vh]">
        <!-- CABECERA -->
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between bg-[#061527] shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-950/80 border border-purple-500/40 flex items-center justify-center text-purple-300 shadow-md">
                    <span class="material-symbols-outlined text-2xl">show_chart</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white font-mono flex items-center gap-2">
                        <span id="hist-device-name">--</span>
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono" id="hist-device-ip">--</p>
                </div>
            </div>
            <button type="button" onclick="closeDeviceHistoryModal()" class="text-obsidian-muted hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- CONTENIDO -->
        <div class="p-6 space-y-6 overflow-y-auto flex-1 custom-scroll">
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

<!-- ========================================================================= -->
<!-- MODAL 6: ASOCIAR / CAMBIAR PERIFÉRICO                                     -->
<!-- ========================================================================= -->
<div id="peripheralModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-[#0b1322] border border-cyan-500/40 rounded-2xl max-w-md w-full shadow-2xl relative flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-obsidian-border shrink-0">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400" id="periphModalIcon">devices</span>
                <span id="periphModalTitle">Asociar Periférico al Equipo</span>
            </h3>
            <button type="button" onclick="closePeripheralModal()" class="text-obsidian-muted hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="peripheralForm" method="POST" action="" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            @csrf
            <input type="hidden" name="type" id="periphInputType" value="monitor">

            <div class="p-4 sm:p-5 space-y-3.5 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Equipo Receptor</label>
                    <input type="text" id="periphAssetName" disabled class="w-full px-3 py-1.5 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-slate-300">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Marca / Fabricante</label>
                    <input type="text" name="brand" id="periphInputBrand" placeholder="Ej. HP, Genius, Marsriva" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Modelo Comercial</label>
                    <input type="text" name="model" id="periphInputModel" placeholder="Ej. V193b, KB-110, MR-AVR600" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Número de Serie</label>
                    <input type="text" name="serial_number" id="periphInputSerial" placeholder="Serial de fábrica (si aplica)" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Estado del Periférico</label>
                    <select name="status" id="periphInputStatus" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        <option value="assigned">Asignado (En Uso en este equipo)</option>
                        <option value="in_stock">En Stock (Disponible)</option>
                        <option value="in_maintenance">En Mantenimiento</option>
                        <option value="damaged">Dañado</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Motivo de la Modificación / Cambio</label>
                    <input type="text" name="reason" placeholder="Ej. Reemplazo por falla, dotación inicial" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-3.5 bg-[#070e1a] border-t border-obsidian-border shrink-0">
                <button type="button" onclick="closePeripheralModal()" class="px-3 py-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20">
                    <span class="material-symbols-outlined text-base">save</span>
                    <span>Guardar Periférico</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 7: PLANIFICAR MANTENIMIENTO INDIVIDUAL / MASIVO                    -->
<!-- ========================================================================= -->
<div id="bulkMaintenanceModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-[#0b1322] border border-amber-500/40 rounded-2xl max-w-md w-full shadow-2xl relative flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-obsidian-border shrink-0">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-400">calendar_month</span>
                <span id="maintPlanModalTitle">Planificar Mantenimiento</span>
            </h3>
            <button type="button" onclick="closeBulkMaintenanceModal()" class="text-obsidian-muted hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.inventory.assets.bulk-maintenance') }}" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            @csrf
            <div id="maintPlanHiddenAssetInputs"></div>

            <div class="p-4 sm:p-5 space-y-3.5 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Equipos Seleccionados</label>
                    <div id="maintPlanAssetSummary" class="p-2.5 rounded-xl bg-[#040b15] border border-obsidian-border text-xs font-mono text-cyan-300 max-h-24 overflow-y-auto">
                        <!-- Inyectado dinámicamente -->
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Fecha Programada <span class="text-amber-400">*</span></label>
                        <input type="date" name="scheduled_at" required value="{{ date('Y-m-d', strtotime('+7 days')) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Tipo de Servicio <span class="text-amber-400">*</span></label>
                        <select name="type" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none">
                            <option value="preventive">Preventivo</option>
                            <option value="corrective">Correctivo</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Prioridad</label>
                        <select name="priority" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none">
                            <option value="medium">Media</option>
                            <option value="low">Baja</option>
                            <option value="high">Alta</option>
                            <option value="critical">Crítica</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Técnico Asignado</label>
                        <select name="technician_id" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none">
                            <option value="">Por Asignar</option>
                            @foreach($technicians as $tech)
                                <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Notas / Alcance del Mantenimiento</label>
                    <textarea name="notes" rows="2" placeholder="Ej. Limpieza general, cambio de pasta térmica, revisión de sistema" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none"></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-3.5 bg-[#070e1a] border-t border-obsidian-border shrink-0">
                <button type="button" onclick="closeBulkMaintenanceModal()" class="px-3 py-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-amber-500/20">
                    <span class="material-symbols-outlined text-base">check_circle</span>
                    <span>Confirmar Planificación</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: FINALIZAR MANTENIMIENTO MASIVO                                    -->
<!-- ========================================================================= -->
<div id="bulkCompleteMaintenanceModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-[#0b1322] border border-emerald-500/40 rounded-2xl max-w-md w-full shadow-2xl relative flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-obsidian-border shrink-0">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-400">task_alt</span>
                <span id="maintCompleteModalTitle">Finalizar Mantenimiento</span>
            </h3>
            <button type="button" onclick="closeBulkCompleteMaintenanceModal()" class="text-obsidian-muted hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.inventory.assets.bulk-complete-maintenance') }}" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            @csrf
            <div id="maintCompleteHiddenAssetInputs"></div>

            <div class="p-4 sm:p-5 space-y-3.5 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Equipos a Finalizar</label>
                    <div id="maintCompleteAssetSummary" class="p-2.5 rounded-xl bg-[#040b15] border border-obsidian-border text-xs font-mono text-emerald-300 max-h-24 overflow-y-auto">
                        <!-- Inyectado dinámicamente -->
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Fecha de Culminación <span class="text-emerald-400">*</span></label>
                        <input type="date" name="completed_at" required value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Técnico Ejecutor <span class="text-emerald-400">*</span></label>
                        <select name="technician_id" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-emerald-500 focus:outline-none">
                            <option value="">Seleccione Técnico</option>
                            @foreach($technicians as $tech)
                                <option value="{{ $tech->id }}" {{ auth()->id() === $tech->id ? 'selected' : '' }}>{{ $tech->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Observaciones / Bitácora Técnica de Resolución <span class="text-emerald-400">*</span></label>
                    <textarea name="resolution_notes" rows="3" required placeholder="Ej. Mantenimiento preventivo completado con éxito: soplado de fuentes, limpieza de ventiladores, cambio de pasta térmica y pruebas operativas OK." class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-emerald-500 focus:outline-none"></textarea>
                </div>

                <div class="p-2.5 rounded-xl bg-emerald-950/20 border border-emerald-500/30 text-[11px] font-mono text-emerald-300 flex items-center gap-2">
                    <span class="material-symbols-outlined text-base text-emerald-400 shrink-0">info</span>
                    <span>Los equipos saldrán del estado "En Mantenimiento" y regresarán automáticamente a estado Asignado o Activo.</span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-3.5 bg-[#070e1a] border-t border-obsidian-border shrink-0">
                <button type="button" onclick="closeBulkCompleteMaintenanceModal()" class="px-3 py-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-emerald-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    <span>Confirmar Cierre</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 9: MOVILIZACIÓN MASIVA DE EQUIPOS POR PUNTOS DE CONTROL             -->
<!-- ========================================================================= -->
<div id="bulkMobilizationModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-[#0b1322] border border-amber-500/40 rounded-2xl max-w-lg w-full shadow-2xl relative flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-obsidian-border shrink-0">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-400">local_shipping</span>
                <span id="bulkMobilizationModalTitle">Movilización Masiva de Equipos</span>
            </h3>
            <button type="button" onclick="closeBulkMobilizationModal()" class="text-obsidian-muted hover:text-white cursor-pointer" title="Cerrar modal">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.inventory.assets.bulk-mobilize') }}" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            @csrf
            <div id="bulkMovHiddenAssetInputs"></div>

            <div class="p-4 sm:p-5 space-y-3.5 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Equipos Seleccionados a Movilizar</label>
                    <div id="bulkMovAssetSummary" class="p-2.5 rounded-xl bg-[#040b15] border border-obsidian-border text-xs font-mono text-amber-300 max-h-24 overflow-y-auto flex flex-wrap gap-1">
                        <!-- Inyectado dinámicamente -->
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Sede de Destino <span class="text-amber-400">*</span></label>
                        <select name="monitored_site_id" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none cursor-pointer">
                            <option value="">-- Seleccionar Sede de Destino --</option>
                            @foreach($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Funcionario Responsable del Traslado <span class="text-amber-400">*</span></label>
                        <input type="text" name="custodian_name" required placeholder="Nombres y Apellidos de quien traslada" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Cédula de Identidad</label>
                        <input type="text" name="custodian_id_number" placeholder="V-12345678" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Gerencia / Departamento</label>
                        <input type="text" name="custodian_department" placeholder="Ej: ATIT / Operaciones" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Punto de Control / Garita</label>
                        <input type="text" name="checkpoint_name" value="Garita Principal Valle Seco" placeholder="Ej: Garita Principal" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Operador de Garita / Vigilante</label>
                        <input type="text" name="gate_operator_name" placeholder="Nombre del receptor / vigilante" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Fecha de Movilización</label>
                        <input type="date" name="assigned_at" value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Hora de Salida</label>
                        <input type="time" name="departure_time" value="{{ date('H:i') }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Motivo / Observaciones del Traslado</label>
                        <textarea name="observations" rows="2" placeholder="Motivo de la movilización, destino específico u orden técnica..." class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-amber-500 focus:outline-none"></textarea>
                    </div>

                    <div class="sm:col-span-2 p-2.5 rounded-xl bg-cyan-950/30 border border-cyan-800/40">
                        <label class="flex items-center gap-2.5 cursor-pointer text-xs font-mono text-cyan-300">
                            <input type="checkbox" name="include_standard_kit" value="1" checked class="w-4 h-4 rounded border-obsidian-border bg-[#040b15] text-amber-500 focus:ring-0 cursor-pointer">
                            <span>Anexar kit operativo básico (Teclado, Mouse, Protector) para puestos sin serial registrado</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-3.5 bg-[#070e1a] border-t border-obsidian-border shrink-0">
                <button type="button" onclick="closeBulkMobilizationModal()" class="px-3 py-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition cursor-pointer">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-amber-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-base">local_shipping</span>
                    <span>Registrar Movilización y Generar Pase</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 8: HISTORIAL COMPLETO DE MANTENIMIENTO TI                           -->
<!-- ========================================================================= -->
<div id="maintenanceModal" class="fixed inset-0 z-50 bg-black/85 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="bg-[#0b1322] border border-cyan-500/40 rounded-2xl max-w-4xl w-full p-6 shadow-2xl relative max-h-[90vh] flex flex-col">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-obsidian-border gap-3">
            <div class="flex items-center gap-3">
                <span class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400">
                    <span class="material-symbols-outlined text-2xl">build_circle</span>
                </span>
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">Historial de Mantenimiento TI</h3>
                    <p id="maintModalAssetTitle" class="text-xs font-mono text-cyan-300 mt-0.5"></p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                <a id="maintModalNewTicketBtn" href="#" class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-amber-500/20">
                    <span class="material-symbols-outlined text-base">add_task</span>
                    <span>Nueva Orden</span>
                </a>
                @endif
                <button type="button" onclick="closeMaintenanceModal()" class="p-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
        </div>

        <div class="overflow-y-auto my-4 pr-1 flex-1">
            <div id="maintTableContainer" class="hidden rounded-xl border border-obsidian-border/80 overflow-hidden bg-[#040b15]/60">
                <table class="w-full text-left border-collapse text-xs font-mono">
                    <thead>
                        <tr class="border-b border-obsidian-border bg-[#020610] text-[10px] text-obsidian-muted uppercase tracking-wider">
                            <th class="py-2.5 px-3">Código / Fecha</th>
                            <th class="py-2.5 px-3">Tipo</th>
                            <th class="py-2.5 px-3">Título &amp; Notas</th>
                            <th class="py-2.5 px-3">Estado</th>
                            <th class="py-2.5 px-3">Técnico</th>
                            <th class="py-2.5 px-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="maintTicketsBody" class="divide-y divide-obsidian-border/40"></tbody>
                </table>
            </div>

            <div id="maintEmptyState" class="hidden py-12 text-center text-obsidian-muted border border-dashed border-obsidian-border rounded-xl bg-[#040b15]/30">
                <span class="material-symbols-outlined text-5xl text-obsidian-border mb-2 block">build</span>
                <p class="text-sm font-semibold text-slate-300">Sin historial de mantenimiento</p>
                <p class="text-xs text-obsidian-muted mt-1 max-w-sm mx-auto">Este equipo aún no tiene órdenes de servicio técnico registradas.</p>
            </div>
        </div>

        <div class="pt-3 border-t border-obsidian-border flex items-center justify-between text-xs font-mono">
            <span class="text-obsidian-muted text-[11px]">Sistema de Control Integral NOC + ITAM</span>
            <button type="button" onclick="closeMaintenanceModal()" class="px-4 py-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white transition">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 9: SUBIR HOJA FÍSICA ESCANEADA (PDF O IMAGEN)                       -->
<!-- ========================================================================= -->
<div id="uploadPhysicalScanModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-[#0b1322] border border-cyan-500/40 rounded-2xl max-w-lg w-full shadow-2xl relative flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-obsidian-border shrink-0">
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400">
                    <span class="material-symbols-outlined text-xl">upload_file</span>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-white">Subir Hoja Física de Asignación</h3>
                    <p class="text-[11px] font-mono text-obsidian-muted">Digitalizar y adjuntar documento físico firmado</p>
                </div>
            </div>
            <button type="button" onclick="closeUploadPhysicalScanModal()" class="text-obsidian-muted hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="uploadPhysicalScanForm" method="POST" action="" enctype="multipart/form-data" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            @csrf
            <div class="p-4 sm:p-5 space-y-3.5 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Equipo Destino</label>
                    <input type="text" id="scanModalAssetName" disabled class="w-full px-3 py-1.5 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-slate-300">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Nombre del Funcionario Asignado <span class="text-cyan-400">*</span></label>
                        <input type="text" name="custodian_name" id="scanModalCustodianName" required placeholder="Ej. Juan Pérez" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Cédula de Identidad</label>
                        <input type="text" name="custodian_id_number" id="scanModalCustodianId" placeholder="Ej. V-12.345.678" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Departamento / Gerencia</label>
                        <input type="text" name="custodian_department" id="scanModalCustodianDept" placeholder="Ej. Transmisión / Operaciones" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Fecha de Firma Físico <span class="text-cyan-400">*</span></label>
                        <input type="date" name="assigned_at" value="{{ date('Y-m-d') }}" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">N° de Control / Acta Físico (Opcional)</label>
                    <input type="text" name="control_number" placeholder="Ej. UINF-4.05-2024-0012 o N° correlativo" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Documento Escaneado (PDF o Imagen) <span class="text-cyan-400">*</span></label>
                    <input type="file" name="scan_file" required accept=".pdf,image/jpeg,image/png" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-mono file:bg-cyan-500/20 file:text-cyan-300 hover:file:bg-cyan-500 hover:file:text-black">
                    <span class="text-[10px] text-obsidian-muted mt-1 block">Formatos permitidos: PDF, JPG, PNG (máx. 20 MB).</span>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Observaciones</label>
                    <textarea name="observations" rows="2" placeholder="Detalles de resguardo, estado de la hoja física, etc." class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none"></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-3.5 bg-[#070e1a] border-t border-obsidian-border shrink-0">
                <button type="button" onclick="closeUploadPhysicalScanModal()" class="px-3 py-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-amber-500/20">
                    <span class="material-symbols-outlined text-base">cloud_upload</span>
                    <span>Subir y Asignar</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE ASIGNACIÓN RÁPIDA DESDE DIRECTORIO LDAP (UINF-4.05) -->
<div id="modal-quick-assign" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="glass-panel w-full max-w-xl rounded-2xl border border-cyan-500/50 flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95 animate-in fade-in zoom-in-95 duration-200 max-h-[85vh] sm:max-h-[88vh]">
        <!-- CABECERA -->
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between bg-[#041224] shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/80 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shadow-md">
                    <span class="material-symbols-outlined text-2xl">badge</span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white font-mono flex items-center gap-2">
                        <span>Asignación de Responsable</span>
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono" id="quick-assign-device-name">Cargando datos del equipo...</p>
                </div>
            </div>
            <button type="button" onclick="closeQuickAssignModal()" class="text-obsidian-muted hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- FORMULARIO DE ASIGNACIÓN -->
        <form id="form-quick-assign" onsubmit="submitQuickAssign(event)" class="flex flex-col flex-1 min-h-0 overflow-hidden font-mono text-xs">
            @csrf
            <input type="hidden" name="inventory_asset_id" id="quick-assign-asset-id" value="">
            <input type="hidden" name="movement_type" id="quick-assign-movement-type" value="assignment">
            <input type="hidden" name="user_id" id="quick-assign-user-id" value="">

            <div class="p-5 space-y-4 overflow-y-auto flex-1 custom-scroll">
                <!-- ALERTA DE ESTADO ACTUAL (SI YA ESTÁ ASIGNADO) -->
                <div id="quick-assign-current-status-box" class="hidden p-3 rounded-xl bg-[#0d1e33] border border-cyan-500/30 flex items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-cyan-400 text-lg">person</span>
                        <div>
                            <span class="text-obsidian-muted text-[10px] uppercase font-bold block">Asignado actualmente a:</span>
                            <span class="text-white font-semibold" id="quick-assign-current-custodian">--</span>
                        </div>
                    </div>
                    <button type="button" onclick="releaseDeviceToStock()" class="px-2.5 py-1 rounded-lg bg-red-950/60 border border-red-500/40 text-red-300 hover:bg-red-600 hover:text-white transition text-[11px] font-bold flex items-center gap-1 shadow-xs cursor-pointer" title="Liberar este equipo y devolverlo a stock disponible">
                        <span class="material-symbols-outlined text-xs">person_off</span>
                        <span>Devolver a Stock</span>
                    </button>
                </div>

                <!-- BUSCADOR REACTIVO LDAP -->
                <div class="p-3.5 rounded-xl bg-[#030a16] border border-cyan-500/30 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="block text-cyan-300 font-bold uppercase text-[11px] flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm">search</span>
                            <span>Buscar Trabajador en Directorio Activo (LDAP)</span>
                        </label>
                        <span class="text-[9px] px-1.5 py-0.2 rounded bg-cyan-950 border border-cyan-500/30 text-cyan-300">192.168.0.22</span>
                    </div>
                    <div class="relative">
                        <input type="text" id="quick-assign-ldap-search" 
                               placeholder="Escriba Cédula, Usuario o Nombre (ej: 15482314, U1234567, jperez)..." 
                               class="w-full pl-9 pr-9 py-2 bg-[#020710] border border-cyan-500/40 rounded-xl text-xs font-mono text-white placeholder-obsidian-muted/60 focus:border-cyan-400 focus:outline-none transition"
                               autocomplete="off" oninput="handleQuickAssignLdapInput(this.value)">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-obsidian-muted text-base">badge</span>
                        <span id="quick-assign-ldap-spinner" class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-cyan-400 text-base animate-spin hidden">progress_activity</span>
                    </div>

                    <!-- DROPDOWN DE RESULTADOS LDAP -->
                    <div id="quick-assign-ldap-dropdown" class="hidden max-h-52 overflow-y-auto rounded-xl bg-[#040e1e] border border-cyan-500/50 shadow-2xl p-1.5 space-y-1 divide-y divide-obsidian-border/30"></div>
                </div>

                <!-- FICHA DE USUARIO SELECCIONADO (PREVIEW) -->
                <div id="quick-assign-selected-card" class="hidden p-3 rounded-xl bg-cyan-950/40 border border-cyan-500/50 flex items-center justify-between gap-3 animate-in fade-in duration-150">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-lg bg-cyan-500/20 text-cyan-300 flex items-center justify-center font-bold material-symbols-outlined text-lg">verified_user</span>
                        <div>
                            <div class="font-bold text-white text-xs flex items-center gap-1.5">
                                <span id="quick-assign-card-name">--</span>
                                <span class="px-1.5 py-0.2 rounded text-[8.5px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 font-mono">LDAP OK</span>
                            </div>
                            <div class="text-[10px] text-obsidian-muted flex items-center gap-2 mt-0.5">
                                <span id="quick-assign-card-cedula">C.I: --</span>
                                <span>•</span>
                                <span id="quick-assign-card-dept">Dpto: --</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="clearQuickAssignSelection()" class="text-obsidian-muted hover:text-red-400 p-1 rounded-lg" title="Limpiar selección">
                        <span class="material-symbols-outlined text-base">close</span>
                    </button>
                </div>

                <!-- CAMPOS EDITABLES / DETALLES PARA EL ACTA -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div>
                        <label class="block text-obsidian-muted mb-1 font-semibold uppercase text-[10px]">Nombre del Responsable *</label>
                        <input type="text" name="custodian_name" id="quick-assign-custodian-name" required placeholder="Nombre completo" class="w-full px-3 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-obsidian-muted mb-1 font-semibold uppercase text-[10px]">Cédula de Identidad</label>
                        <input type="text" name="custodian_id_number" id="quick-assign-custodian-id" placeholder="V-12.345.678" class="w-full px-3 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-obsidian-muted mb-1 font-semibold uppercase text-[10px]">Departamento / Gerencia</label>
                        <input type="text" name="custodian_department" id="quick-assign-custodian-dept" placeholder="Ej: ATIT Carabobo" class="w-full px-3 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-obsidian-muted mb-1 font-semibold uppercase text-[10px]">Cargo / Rol</label>
                        <input type="text" name="custodian_role" id="quick-assign-custodian-role" placeholder="Ej: Analista de Sistemas" class="w-full px-3 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none">
                    </div>
                </div>

                <!-- OBSERVACIONES -->
                <div>
                    <label class="block text-obsidian-muted mb-1 font-semibold uppercase text-[10px]">Observaciones del Acta (Opcional)</label>
                    <textarea name="observations" id="quick-assign-observations" rows="2" placeholder="Notas de entrega, estado físico, seriales de periféricos..." class="w-full px-3 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-none"></textarea>
                </div>

                <div id="quick-assign-error" class="hidden p-2.5 rounded-xl bg-red-950/60 border border-red-500/50 text-red-300 text-xs max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all block"></div>
                <div id="quick-assign-success" class="hidden p-2.5 rounded-xl bg-emerald-950/60 border border-emerald-500/50 text-emerald-300 text-xs max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all block"></div>
            </div>

            <!-- PIE DE ACCIONES -->
            <div class="p-3.5 px-6 border-t border-obsidian-border flex items-center justify-between gap-3 shrink-0 bg-[#041224]">
                <button type="button" onclick="closeQuickAssignModal()" class="px-4 py-2 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white text-xs transition">
                    Cancelar
                </button>
                <button type="submit" id="quick-assign-submit-btn" class="px-5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-bold text-xs transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">assignment_turned_in</span>
                    <span id="quick-assign-submit-text">Formalizar y Generar Acta UINF-4.05</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL REUTILIZABLE: CONCILIACIÓN CON DIRECTORIO ACTIVO (LDAP) --}}
@include('admin.inventory.partials.reconcile_ldap_modal')

<script>
    let currentDeviceId = null;
    let currentDeviceRange = '24h';
    let deviceChart = null;
    const assetsMaintenanceMap = @json($assetsMaintenanceMap);
    const historyState = {};

    // =========================================================================
    // FILTRADO REACTIVO EN TIEMPO REAL & BÚSQUEDA EN VIVO (LIVE SEARCH)
    // =========================================================================
    let deviceSearchDebounceTimer = null;
    let deviceFetchAbortController = null;

    // 1. Filtrado instantáneo local en el DOM (0ms de latencia, idéntico al Dashboard)
    function filterLocalRows() {
        const searchInput = document.getElementById('device-search-input');
        const searchVal = (searchInput?.value || '').toLowerCase().trim();
        const siteVal = document.getElementById('device-site-select')?.value || '';
        const typeVal = (document.getElementById('device-type-select')?.value || '').toLowerCase().trim();
        const statusVal = (document.getElementById('device-status-select')?.value || '').toLowerCase().trim();

        // Controlar visibilidad del botón 'X' de borrado rápido dentro del input
        const clearBtnX = document.getElementById('btn-clear-search-x');
        if (clearBtnX) {
            if (searchVal.length > 0) {
                clearBtnX.classList.remove('hidden');
            } else {
                clearBtnX.classList.add('hidden');
            }
        }

        const rows = document.querySelectorAll('.device-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const searchData = (row.getAttribute('data-search') || '').toLowerCase();
            const rowSiteId = row.getAttribute('data-site-id') || '';
            const rowType = (row.getAttribute('data-type') || '').toLowerCase();
            const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
            const assetId = row.id.replace('asset-row-', '');
            const accordionRow = document.getElementById('accordion-row-' + assetId);

            const matchesSearch = !searchVal || searchData.includes(searchVal);
            const matchesSite = !siteVal || rowSiteId === siteVal;
            const matchesType = !typeVal || rowType === typeVal;
            let matchesStatus = true;
            if (statusVal) {
                if (statusVal === 'active') {
                    matchesStatus = rowStatus.includes('active');
                } else if (statusVal === 'inactive') {
                    matchesStatus = rowStatus.includes('inactive');
                } else {
                    matchesStatus = rowStatus.includes(statusVal);
                }
            }

            const isVisible = matchesSearch && matchesSite && matchesType && matchesStatus;
            row.style.display = isVisible ? '' : 'none';
            if (accordionRow && !isVisible) {
                accordionRow.style.display = 'none';
                accordionRow.classList.add('hidden');
                const chevron = document.getElementById('chevron-' + assetId);
                if (chevron) chevron.classList.remove('rotate-180', 'text-cyan-400');
            }
            if (isVisible) visibleCount++;
        });

        // Manejar fila de aviso "sin resultados locales"
        const localEmptyRow = document.getElementById('local-empty-row');
        if (localEmptyRow) {
            if (visibleCount === 0 && rows.length > 0) {
                localEmptyRow.classList.remove('hidden');
            } else {
                localEmptyRow.classList.add('hidden');
            }
        }
    }

    // 2. Disparador instantáneo al cambiar cualquier dropdown (Sede, Tipo, Estado)
    function onFilterChange() {
        filterLocalRows();
        fetchDeviceResults();
    }

    // 3. Disparador en vivo al escribir en el buscador
    function onSearchInput(event) {
        if (event && event.key === 'Escape') {
            clearSearchInput();
            return;
        }
        filterLocalRows();
        if (deviceSearchDebounceTimer) {
            clearTimeout(deviceSearchDebounceTimer);
        }
        deviceSearchDebounceTimer = setTimeout(() => {
            fetchDeviceResults();
        }, 220);
    }

    // 4. Limpiar solo el texto de búsqueda
    function clearSearchInput() {
        const input = document.getElementById('device-search-input');
        if (input) {
            input.value = '';
            input.focus();
        }
        filterLocalRows();
        fetchDeviceResults();
    }

    // 5. Limpiar todos los filtros (búsqueda y desplegables)
    function clearAllDeviceFilters() {
        const searchInput = document.getElementById('device-search-input');
        const siteSelect = document.getElementById('device-site-select');
        const typeSelect = document.getElementById('device-type-select');
        const statusSelect = document.getElementById('device-status-select');

        if (searchInput) searchInput.value = '';
        if (siteSelect) siteSelect.value = '';
        if (typeSelect) typeSelect.value = '';
        if (statusSelect) statusSelect.value = '';

        filterLocalRows();
        fetchDeviceResults('{{ route("admin.devices.index") }}');
    }

    // 6. Consulta AJAX reactiva en segundo plano
    async function fetchDeviceResults(customUrl = null) {
        if (deviceFetchAbortController) {
            deviceFetchAbortController.abort();
        }
        deviceFetchAbortController = new AbortController();

        const searchInput = document.getElementById('device-search-input');
        const siteSelect = document.getElementById('device-site-select');
        const typeSelect = document.getElementById('device-type-select');
        const statusSelect = document.getElementById('device-status-select');
        const statusIcon = document.getElementById('filter-status-icon');

        const searchVal = (searchInput?.value || '').trim();
        const siteVal = siteSelect?.value || '';
        const typeVal = typeSelect?.value || '';
        const statusVal = statusSelect?.value || '';

        let targetUrl = customUrl;
        if (!targetUrl) {
            const params = new URLSearchParams();
            if (searchVal) params.set('search', searchVal);
            if (siteVal) params.set('site_id', siteVal);
            if (typeVal) params.set('type', typeVal);
            if (statusVal) params.set('status', statusVal);
            const queryStr = params.toString();
            targetUrl = '{{ route("admin.devices.index") }}' + (queryStr ? ('?' + queryStr) : '');
        }

        // Feedback visual inmediato: activar spinner
        if (statusIcon) {
            statusIcon.innerText = 'sync';
            statusIcon.classList.add('animate-spin', 'text-cyan-400');
            statusIcon.classList.remove('text-cyan-400/70');
        }

        try {
            const response = await fetch(targetUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                },
                signal: deviceFetchAbortController.signal
            });

            if (response.redirected && response.url.includes('/login')) {
                window.location.href = '/login';
                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');

            // Reemplazo del cuerpo de la tabla
            const newTableBody = doc.getElementById('devices-table-body');
            const currentTableBody = document.getElementById('devices-table-body');
            if (newTableBody && currentTableBody) {
                currentTableBody.innerHTML = newTableBody.innerHTML;
            }

            // Reemplazo de paginación
            const newPagination = doc.getElementById('devices-pagination-wrapper');
            const currentPagination = document.getElementById('devices-pagination-wrapper');
            if (newPagination && currentPagination) {
                currentPagination.innerHTML = newPagination.innerHTML;
            }

            // Reemplazo de contadores KPIs superiores
            const newKpiGrid = doc.getElementById('devices-kpi-grid');
            const currentKpiGrid = document.getElementById('devices-kpi-grid');
            if (newKpiGrid && currentKpiGrid) {
                currentKpiGrid.innerHTML = newKpiGrid.innerHTML;
            }

            // Re-sincronizar datos de tickets/mantenimiento
            const newMaintenanceData = doc.getElementById('devices-maintenance-data');
            if (newMaintenanceData) {
                try {
                    const parsed = JSON.parse(newMaintenanceData.textContent);
                    Object.assign(assetsMaintenanceMap, parsed);
                } catch(e) {}
            }

            // Actualizar URL en barra de direcciones de forma silenciosa
            window.history.replaceState({}, '', targetUrl);

            // Gestionar visibilidad de botones de acción
            const hasActiveFilters = Boolean(searchVal || siteVal || typeVal || statusVal);
            const btnClearFilters = document.getElementById('btn-clear-filters');
            const filterIdleIndicator = document.getElementById('filter-idle-indicator');
            if (btnClearFilters) {
                if (hasActiveFilters) {
                    btnClearFilters.classList.remove('hidden');
                } else {
                    btnClearFilters.classList.add('hidden');
                }
            }
            if (filterIdleIndicator) {
                if (hasActiveFilters) {
                    filterIdleIndicator.classList.add('hidden');
                } else {
                    filterIdleIndicator.classList.remove('hidden');
                }
            }

            // Deseleccionar checkbox de selección múltiple si estaba activo
            const selectAll = document.getElementById('selectAllCheckbox');
            if (selectAll) selectAll.checked = false;
            if (typeof onAssetCheckChanged === 'function') {
                onAssetCheckChanged();
            }

            // Sincronizar estado local en caso de que el usuario haya escrito durante la petición
            filterLocalRows();

        } catch (err) {
            if (err.name !== 'AbortError') {
                console.error('Error al actualizar dispositivos vía AJAX:', err);
            }
        } finally {
            if (statusIcon) {
                statusIcon.innerText = 'tune';
                statusIcon.classList.remove('animate-spin', 'text-cyan-400');
                statusIcon.classList.add('text-cyan-400/70');
            }
        }
    }

    // 1. DESPLIEGUE EN ACORDEÓN TIPO "SEDES REGIONALES"
    function toggleAssetAccordion(assetId, event) {
        if (event) {
            event.stopPropagation();
        }
        const row = document.getElementById('accordion-row-' + assetId);
        const chevron = document.getElementById('chevron-' + assetId);
        const parentRow = document.getElementById('asset-row-' + assetId);

        if (!row) return;

        if (row.classList.contains('hidden')) {
            row.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-180', 'text-cyan-400');
            if (parentRow) parentRow.classList.add('bg-cyan-500/[0.04]', 'border-cyan-500/40');
            initAssetHistory(assetId);
        } else {
            row.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-180', 'text-cyan-400');
            if (parentRow) parentRow.classList.remove('bg-cyan-500/[0.04]', 'border-cyan-500/40');
        }
    }

    // 2. CONTROL DE HISTÓRICO CON PAGINACIÓN CLIENTE (5 ITEMS)
    function initAssetHistory(assetId) {
        if (!historyState[assetId]) {
            historyState[assetId] = {
                page: 1,
                pageSize: 5,
                filter: 'all'
            };
        }
        renderAssetHistory(assetId);
    }

    function filterAssetHistory(assetId, filterType) {
        if (!historyState[assetId]) historyState[assetId] = { pageSize: 5 };
        historyState[assetId].filter = filterType;
        historyState[assetId].page = 1;
        renderAssetHistory(assetId);
    }

    function changeAssetHistoryPage(assetId, delta) {
        if (!historyState[assetId]) return;
        historyState[assetId].page += delta;
        renderAssetHistory(assetId);
    }

    function renderAssetHistory(assetId) {
        const state = historyState[assetId] || { page: 1, pageSize: 5, filter: 'all' };
        const rows = Array.from(document.querySelectorAll('.history-item-' + assetId));
        if (rows.length === 0) return;

        const filtered = rows.filter(r => {
            if (state.filter === 'all') return true;
            return r.getAttribute('data-type') === state.filter;
        });

        const total = filtered.length;
        const totalPages = Math.max(1, Math.ceil(total / state.pageSize));
        if (state.page > totalPages) state.page = totalPages;
        if (state.page < 1) state.page = 1;

        const startIdx = (state.page - 1) * state.pageSize;
        const endIdx = startIdx + state.pageSize;

        rows.forEach(r => r.style.display = 'none');
        filtered.slice(startIdx, endIdx).forEach(r => r.style.display = '');

        const counter = document.getElementById('history-counter-' + assetId);
        const pageInfo = document.getElementById('history-page-info-' + assetId);
        const prevBtn = document.getElementById('history-prev-' + assetId);
        const nextBtn = document.getElementById('history-next-' + assetId);

        if (counter) {
            counter.innerText = total > 0 
                ? `Mostrando ${startIdx + 1} a ${Math.min(endIdx, total)} de ${total} registros` 
                : 'Sin registros para este filtro';
        }
        if (pageInfo) pageInfo.innerText = `${state.page} / ${totalPages}`;
        if (prevBtn) prevBtn.disabled = (state.page <= 1);
        if (nextBtn) nextBtn.disabled = (state.page >= totalPages);
    }

    // 3. SELECCIÓN MASIVA CON CHECKBOXES Y BARRA FLOTANTE
    function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll('.asset-checkbox');
        checkboxes.forEach(cb => cb.checked = master.checked);
        onAssetCheckChanged();
    }

    function onAssetCheckChanged() {
        const checked = document.querySelectorAll('.asset-checkbox:checked');
        const count = checked.length;
        const bar = document.getElementById('bulkActionBar');
        const counter = document.getElementById('bulkSelectedCount');

        if (counter) counter.innerText = count;

        if (count > 0) {
            bar.classList.remove('translate-y-32', 'opacity-0', 'pointer-events-none');
        } else {
            bar.classList.add('translate-y-32', 'opacity-0', 'pointer-events-none');
            const master = document.getElementById('selectAllCheckbox');
            if (master) master.checked = false;
        }
    }

    function deselectAll() {
        document.querySelectorAll('.asset-checkbox').forEach(cb => cb.checked = false);
        const master = document.getElementById('selectAllCheckbox');
        if (master) master.checked = false;
        onAssetCheckChanged();
    }

    function executeBulkMobilization() {
        const checked = Array.from(document.querySelectorAll('.asset-checkbox:checked'));
        if (checked.length === 0) return;

        const container = document.getElementById('bulkMovHiddenAssetInputs');
        const summary = document.getElementById('bulkMovAssetSummary');
        container.innerHTML = '';
        summary.innerHTML = '';

        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'asset_ids[]';
            input.value = cb.value;
            container.appendChild(input);

            const badge = document.createElement('div');
            badge.className = 'inline-block mr-1 mb-1 px-2 py-0.5 rounded bg-amber-950/80 text-amber-300 border border-amber-800 text-[10px]';
            const info = assetsMaintenanceMap[cb.value];
            badge.innerText = info ? (info.tag ? `[${info.tag}] ${info.name}` : info.name) : `ID #${cb.value}`;
            summary.appendChild(badge);
        });

        document.getElementById('bulkMobilizationModalTitle').innerText = `Movilización Masiva (${checked.length} equipos)`;
        document.getElementById('bulkMobilizationModal').classList.remove('hidden');
    }

    function openSingleMobilizationModal(assetId, assetName, assetTag = '') {
        const container = document.getElementById('bulkMovHiddenAssetInputs');
        const summary = document.getElementById('bulkMovAssetSummary');
        container.innerHTML = '';
        summary.innerHTML = '';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'asset_ids[]';
        input.value = assetId;
        container.appendChild(input);

        const badge = document.createElement('div');
        badge.className = 'inline-block px-2 py-0.5 rounded bg-amber-950/80 text-amber-300 border border-amber-800 text-[10px]';
        badge.innerText = assetTag ? `[${assetTag}] ${assetName}` : assetName;
        summary.appendChild(badge);

        document.getElementById('bulkMobilizationModalTitle').innerText = `Movilizar Equipo: ${assetName}`;
        document.getElementById('bulkMobilizationModal').classList.remove('hidden');
    }

    function closeBulkMobilizationModal() {
        document.getElementById('bulkMobilizationModal').classList.add('hidden');
    }

    function openBulkMaintenanceModal() {
        const checked = Array.from(document.querySelectorAll('.asset-checkbox:checked'));
        if (checked.length === 0) return;

        const container = document.getElementById('maintPlanHiddenAssetInputs');
        const summary = document.getElementById('maintPlanAssetSummary');
        container.innerHTML = '';
        summary.innerHTML = '';

        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'asset_ids[]';
            input.value = cb.value;
            container.appendChild(input);

            const badge = document.createElement('div');
            badge.className = 'inline-block mr-1 mb-1 px-2 py-0.5 rounded bg-cyan-950/80 text-cyan-300 border border-cyan-800 text-[10px]';
            const info = assetsMaintenanceMap[cb.value];
            badge.innerText = info ? info.name : `ID #${cb.value}`;
            summary.appendChild(badge);
        });

        document.getElementById('maintPlanModalTitle').innerText = `Planificar Mantenimiento (${checked.length} equipos)`;
        document.getElementById('bulkMaintenanceModal').classList.remove('hidden');
    }

    function openSingleMaintenanceModal(assetId, assetName) {
        const container = document.getElementById('maintPlanHiddenAssetInputs');
        const summary = document.getElementById('maintPlanAssetSummary');
        container.innerHTML = '';
        summary.innerHTML = '';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'asset_ids[]';
        input.value = assetId;
        container.appendChild(input);

        const badge = document.createElement('div');
        badge.className = 'inline-block px-2 py-0.5 rounded bg-cyan-950/80 text-cyan-300 border border-cyan-800 text-[10px]';
        badge.innerText = assetName;
        summary.appendChild(badge);

        document.getElementById('maintPlanModalTitle').innerText = `Planificar Mantenimiento: ${assetName}`;
        document.getElementById('bulkMaintenanceModal').classList.remove('hidden');
    }

    function closeBulkMaintenanceModal() {
        document.getElementById('bulkMaintenanceModal').classList.add('hidden');
    }

    function openBulkCompleteMaintenanceModal() {
        const checked = document.querySelectorAll('.asset-checkbox:checked');
        if (checked.length === 0) return;

        const container = document.getElementById('maintCompleteHiddenAssetInputs');
        const summary = document.getElementById('maintCompleteAssetSummary');
        container.innerHTML = '';
        summary.innerHTML = '';

        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'asset_ids[]';
            input.value = cb.value;
            container.appendChild(input);

            const badge = document.createElement('div');
            badge.className = 'inline-block mr-1 mb-1 px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-300 border border-emerald-800 text-[10px]';
            const info = assetsMaintenanceMap[cb.value];
            badge.innerText = info ? info.name : `ID #${cb.value}`;
            summary.appendChild(badge);
        });

        document.getElementById('maintCompleteModalTitle').innerText = `Finalizar Mantenimiento (${checked.length} equipos)`;
        document.getElementById('bulkCompleteMaintenanceModal').classList.remove('hidden');
    }

    function openSingleCompleteMaintenanceModal(assetId, assetName) {
        const container = document.getElementById('maintCompleteHiddenAssetInputs');
        const summary = document.getElementById('maintCompleteAssetSummary');
        container.innerHTML = '';
        summary.innerHTML = '';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'asset_ids[]';
        input.value = assetId;
        container.appendChild(input);

        const badge = document.createElement('div');
        badge.className = 'inline-block px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-300 border border-emerald-800 text-[10px]';
        badge.innerText = assetName;
        summary.appendChild(badge);

        document.getElementById('maintCompleteModalTitle').innerText = `Finalizar Mantenimiento: ${assetName}`;
        document.getElementById('bulkCompleteMaintenanceModal').classList.remove('hidden');
    }

    function closeBulkCompleteMaintenanceModal() {
        document.getElementById('bulkCompleteMaintenanceModal').classList.add('hidden');
    }

    // 4. MODAL PERIFÉRICOS
    function openPeripheralModal(assetId, assetName, periphType, brand = '', model = '', serial = '') {
        const form = document.getElementById('peripheralForm');
        form.action = `/admin/inventory/assets/${assetId}/peripherals`;

        document.getElementById('periphAssetName').value = assetName;
        document.getElementById('periphInputType').value = periphType;
        document.getElementById('periphInputBrand').value = brand;
        document.getElementById('periphInputModel').value = model;
        document.getElementById('periphInputSerial').value = serial;

        const titleMap = {
            'monitor': 'Configurar Monitor',
            'keyboard': 'Configurar Teclado',
            'mouse': 'Configurar Mouse / Ratón',
            'surge_protector': 'Configurar Protector / Regulador / UPS'
        };

        const iconMap = {
            'monitor': 'monitor',
            'keyboard': 'keyboard',
            'mouse': 'mouse',
            'surge_protector': 'bolt'
        };

        document.getElementById('periphModalTitle').innerText = titleMap[periphType] || 'Configurar Periférico';
        document.getElementById('periphModalIcon').innerText = iconMap[periphType] || 'devices';
        document.getElementById('peripheralModal').classList.remove('hidden');
    }

    function closePeripheralModal() {
        document.getElementById('peripheralModal').classList.add('hidden');
    }

    // 5. MODAL HISTORIAL MANTENIMIENTO
    function openMaintenanceModal(assetId) {
        const info = assetsMaintenanceMap[assetId];
        if (!info) return;

        document.getElementById('maintModalAssetTitle').innerText = `${info.name} • S/N: ${info.serial || 'Sin Serial'} • Tag: ${info.tag || 'Sin Tag'}`;
        const newTicketBtn = document.getElementById('maintModalNewTicketBtn');
        if (newTicketBtn) {
            newTicketBtn.href = info.new_ticket_url;
        }

        const tbody = document.getElementById('maintTicketsBody');
        const container = document.getElementById('maintTableContainer');
        const empty = document.getElementById('maintEmptyState');
        tbody.innerHTML = '';

        if (info.tickets && info.tickets.length > 0) {
            info.tickets.forEach(t => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-white/[0.02]';
                tr.innerHTML = `
                    <td class="py-2.5 px-3">
                        <div class="font-bold text-white">${t.code}</div>
                        <div class="text-[10px] text-obsidian-muted">${t.date}</div>
                    </td>
                    <td class="py-2.5 px-3">
                        <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold uppercase bg-slate-800 text-slate-300 border border-slate-700">
                            ${t.type_label}
                        </span>
                    </td>
                    <td class="py-2.5 px-3">
                        <div class="text-slate-200 font-medium">${t.title}</div>
                        <div class="text-[10px] text-obsidian-muted truncate max-w-xs">${t.description || '-'}</div>
                    </td>
                    <td class="py-2.5 px-3">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border ${t.status_class}">
                            ${t.status_label}
                        </span>
                    </td>
                    <td class="py-2.5 px-3 text-slate-300 text-[11px]">
                        ${t.technician}
                    </td>
                    <td class="py-2.5 px-3 text-right space-x-1">
                        <a href="${t.pdf_url}" target="_blank" class="p-1 rounded bg-red-950/40 border border-red-800/60 text-red-400 hover:bg-red-500 hover:text-white transition inline-block" title="Descargar Orden (PDF)">
                            <span class="material-symbols-outlined text-[13px]">picture_as_pdf</span>
                        </a>
                        <a href="${t.show_url}" class="p-1 rounded bg-cyan-950/60 border border-cyan-800/60 text-cyan-300 hover:bg-cyan-500 hover:text-black transition inline-block" title="Ver Detalle de Orden">
                            <span class="material-symbols-outlined text-[13px]">visibility</span>
                        </a>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            container.classList.remove('hidden');
            empty.classList.add('hidden');
        } else {
            container.classList.add('hidden');
            empty.classList.remove('hidden');
        }

        document.getElementById('maintenanceModal').classList.remove('hidden');
    }

    function closeMaintenanceModal() {
        document.getElementById('maintenanceModal').classList.add('hidden');
    }

    // 6. MODAL SUBIR ESCANEO FÍSICO
    function openUploadPhysicalScanModal(assetId, assetName, custodianName = '', custodianId = '', custodianDept = '') {
        const form = document.getElementById('uploadPhysicalScanForm');
        form.action = '/admin/inventory/assets/' + assetId + '/upload-assignment-scan';
        document.getElementById('scanModalAssetName').value = assetName;
        document.getElementById('scanModalCustodianName').value = custodianName || '';
        document.getElementById('scanModalCustodianId').value = custodianId || '';
        document.getElementById('scanModalCustodianDept').value = custodianDept || '';
        document.getElementById('uploadPhysicalScanModal').classList.remove('hidden');
    }

    function closeUploadPhysicalScanModal() {
        document.getElementById('uploadPhysicalScanModal').classList.add('hidden');
    }

    // 7. TERMINALES REMOTAS HELPERS
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

    async function openVncViewer(ip, name = '', site = '', port = null) {
        try {
            const payload = { ip, name, site };
            if (port) {
                payload.port = port;
            }
            const res = await fetch("{{ route('admin.vnc.session') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
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

    function handleCreateAccessChange(type) {
        const singleWrapper = document.getElementById('create-single-port-wrapper');
        const dualWrapper = document.getElementById('create-dual-ports-wrapper');
        const pInput = document.getElementById('create-access-port');
        const pLabel = document.getElementById('create-access-port-label');
        const vncWrapper = document.getElementById('create-vnc-wrapper');
        const sshWrapper = document.getElementById('create-ssh-wrapper');

        const isDual = (type === 'SSH+VNC');
        const isVnc = (type === 'VNC');
        const isSsh = (type === 'SSH');

        if (singleWrapper) singleWrapper.classList.toggle('hidden', isDual);
        if (dualWrapper) dualWrapper.classList.toggle('hidden', !isDual);
        if (vncWrapper) vncWrapper.classList.toggle('hidden', !(isVnc || isDual));
        if (sshWrapper) sshWrapper.classList.toggle('hidden', !(isSsh || isDual));

        if (!isDual && pInput) {
            if (type === 'SSH') { pInput.value = 22; if (pLabel) pLabel.textContent = 'Puerto SSH'; }
            else if (type === 'TELNET') { pInput.value = 23; if (pLabel) pLabel.textContent = 'Puerto Telnet'; }
            else if (type === 'WEB') { pInput.value = 80; if (pLabel) pLabel.textContent = 'Puerto Web HTTP/S'; }
            else if (type === 'VNC') { pInput.value = 5900; if (pLabel) pLabel.textContent = 'Puerto VNC'; }
            else { pInput.value = ''; if (pLabel) pLabel.textContent = 'Puerto de Conexión'; }
        }
    }

    function handleEditAccessChange(type) {
        const singleWrapper = document.getElementById('edit-single-port-wrapper');
        const dualWrapper = document.getElementById('edit-dual-ports-wrapper');
        const pInput = document.getElementById('edit-access-port');
        const pLabel = document.getElementById('edit-access-port-label');
        const vncWrapper = document.getElementById('edit-vnc-wrapper');
        const sshWrapper = document.getElementById('edit-ssh-wrapper');

        const isDual = (type === 'SSH+VNC');
        const isVnc = (type === 'VNC');
        const isSsh = (type === 'SSH');

        if (singleWrapper) singleWrapper.classList.toggle('hidden', isDual);
        if (dualWrapper) dualWrapper.classList.toggle('hidden', !isDual);
        if (vncWrapper) vncWrapper.classList.toggle('hidden', !(isVnc || isDual));
        if (sshWrapper) sshWrapper.classList.toggle('hidden', !(isSsh || isDual));

        if (!isDual && pInput) {
            if (type === 'SSH') { if (!pInput.value) pInput.value = 22; if (pLabel) pLabel.textContent = 'Puerto SSH'; }
            else if (type === 'TELNET') { if (!pInput.value) pInput.value = 23; if (pLabel) pLabel.textContent = 'Puerto Telnet'; }
            else if (type === 'WEB') { if (!pInput.value) pInput.value = 80; if (pLabel) pLabel.textContent = 'Puerto Web HTTP/S'; }
            else if (type === 'VNC') { if (!pInput.value) pInput.value = 5900; if (pLabel) pLabel.textContent = 'Puerto VNC'; }
            else { pInput.value = ''; if (pLabel) pLabel.textContent = 'Puerto de Conexión'; }
        }
    }

    // 8. MODAL FICHA TÉCNICA DETALLADA
    function openDeviceDetailsModal(dev, siteName = '') {
        const devDisplayName = dev.display_identity || dev.name || 'Dispositivo';
        document.getElementById('det-device-name').innerText = devDisplayName;
        document.getElementById('det-device-site').innerText = siteName || (dev.site ? dev.site.name : 'Sede Principal');
        document.getElementById('det-device-vendor').innerText = dev.vendor_data ? ('Fabricante: ' + dev.vendor_data) : 'Equipo de Infraestructura LAN';
        document.getElementById('det-device-ip').innerText = dev.ip || dev.ip_address || '--';
        document.getElementById('det-device-mac').innerText = dev.mac || dev.mac_address || 'No especificada';
        document.getElementById('det-device-model').innerText = dev.model || 'No especificado';
        document.getElementById('det-device-serial').innerText = dev.serial || dev.serial_number || 'No especificado';
        document.getElementById('det-device-ports').innerText = dev.ports || 'No especificado';
        
        const acc = (dev.access_type || 'SIN SOPORTE').toUpperCase();
        const sshPort = dev.ssh_port || dev.access_port || 22;
        const vncPort = dev.vnc_port || 5900;
        const telnetPort = dev.telnet_port || dev.access_port || 23;
        const webPort = dev.web_port || dev.access_port || 80;

        if (acc === 'SSH+VNC') {
            document.getElementById('det-device-access').innerText = `DUAL: SSH (${sshPort}) + VNC (${vncPort})`;
        } else if (acc === 'SSH') {
            document.getElementById('det-device-access').innerText = `SSH (Puerto ${sshPort})`;
        } else if (acc === 'VNC') {
            document.getElementById('det-device-access').innerText = `VNC (Puerto ${vncPort})`;
        } else if (acc === 'TELNET') {
            document.getElementById('det-device-access').innerText = `TELNET (Puerto ${telnetPort})`;
        } else if (acc === 'WEB') {
            const webProto = (webPort == 443 || webPort == 8443) ? 'https' : 'http';
            document.getElementById('det-device-access').innerText = `WEB ${webProto.toUpperCase()} (Puerto ${webPort})`;
        } else {
            document.getElementById('det-device-access').innerText = 'SIN ACCESO REMOTO';
        }

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

        const connectContainer = document.getElementById('det-device-connect-btn');
        const devIp = dev.ip || dev.ip_address || '';
        if (acc === 'SSH+VNC') {
            connectContainer.innerHTML = `
                <div class="flex items-center gap-2">
                    <button type="button" onclick="openSshTerminal('${devIp}', ${sshPort}, '${encodeURIComponent(devDisplayName)}', '${encodeURIComponent(siteName || '')}')" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-md cursor-pointer">
                        <span class="material-symbols-outlined text-sm">terminal</span>
                        Conectar SSH (${sshPort})
                    </button>
                    <button type="button" onclick="openVncViewer('${devIp}', '${encodeURIComponent(devDisplayName)}', '${encodeURIComponent(siteName || '')}', ${vncPort})" class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-md cursor-pointer">
                        <span class="material-symbols-outlined text-sm">desktop_windows</span>
                        Abrir VNC (${vncPort})
                    </button>
                </div>
            `;
        } else if (acc === 'SSH') {
            connectContainer.innerHTML = `
                <button type="button" onclick="openSshTerminal('${devIp}', ${sshPort}, '${encodeURIComponent(devDisplayName)}', '${encodeURIComponent(siteName || '')}')" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-md cursor-pointer">
                    <span class="material-symbols-outlined text-sm">terminal</span>
                    Conectar SSH (${sshPort})
                </button>
            `;
        } else if (acc === 'TELNET') {
            connectContainer.innerHTML = `
                <button type="button" onclick="openTelnetTerminal('${devIp}', ${telnetPort}, '${encodeURIComponent(devDisplayName)}', '${encodeURIComponent(siteName || '')}')" class="px-3 py-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-black font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-md cursor-pointer">
                    <span class="material-symbols-outlined text-sm">terminal</span>
                    Conectar Telnet (${telnetPort})
                </button>
            `;
        } else if (acc === 'WEB') {
            const webProto = (webPort == 443 || webPort == 8443) ? 'https' : 'http';
            connectContainer.innerHTML = `
                <a href="${webProto}://${devIp}:${webPort}" target="_blank" class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-md">
                    <span class="material-symbols-outlined text-sm">open_in_browser</span>
                    Abrir Web (${webPort})
                </a>
            `;
        } else if (acc === 'VNC') {
            connectContainer.innerHTML = `
                <button type="button" onclick="openVncViewer('${devIp}', '${encodeURIComponent(devDisplayName)}', '${encodeURIComponent(siteName || '')}', ${vncPort})" class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-md cursor-pointer">
                    <span class="material-symbols-outlined text-sm">desktop_windows</span>
                    Abrir VNC (${vncPort})
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

    // =========================================================================
    // CONTROLADOR CENTRALIZADO DE VISIBILIDAD REACTIVA POR TIPO DE ACTIVO
    // (APLICA DE FORMA UNIFICADA TANTO A CREAR COMO A EDITAR DISPOSITIVO)
    // =========================================================================
    function applyDeviceTypeVisibility(context, type) {
        if (!type) type = 'desktop';

        const isComputer = ['desktop', 'laptop', 'all_in_one', 'workstation', 'server'].includes(type);
        const isNetwork = ['router', 'switch', 'firewall', 'access_point'].includes(type);
        const isEnergy = ['ups', 'inverter', 'pdu', 'generator'].includes(type);
        const isPassive = ['monitor', 'keyboard', 'mouse', 'headset', 'scanner', 'surge_protector', 'other_peripheral'].includes(type);
        const isPrinter = type === 'printer';

        // 1. Bloque 3: Periféricos de Puesto de Trabajo (Solo para computadoras)
        const periphSection = document.getElementById(context + '-section-peripherals');
        if (periphSection) {
            periphSection.classList.toggle('hidden', !isComputer);
        }

        // 2. Bloque 4: Responsable & Asignación (Oculto para red y energía pura en infraestructura)
        const assignSection = document.getElementById(context + '-assignment-wrapper');
        if (assignSection) {
            assignSection.classList.toggle('hidden', isNetwork || isEnergy);
        }

        // 3. Bloque 5: Supervisión, Alertas & Acceso Remoto (Oculto para periféricos pasivos)
        const monSection = document.getElementById(context + '-section-monitoring');
        if (monSection) {
            monSection.classList.toggle('hidden', isPassive);
        }

        // 4. Sub-bloque Despliegue GLPI (Solo en creación y solo para computadoras)
        if (context === 'create') {
            const glpiWrapper = document.getElementById('create-glpi-wrapper');
            if (glpiWrapper) {
                glpiWrapper.classList.toggle('hidden', !isComputer);
                if (!isComputer) {
                    const deployCheck = document.getElementById('create-deploy-glpi');
                    if (deployCheck) {
                        deployCheck.checked = false;
                        toggleGlpiFields(false);
                    }
                }
            }
        }

        // 5. Ajustes de IP y Monitoreo Activo (Bloque 2)
        const ipMacWrapper = document.getElementById(context + '-ip-mac-wrapper');
        if (ipMacWrapper) {
            ipMacWrapper.classList.toggle('hidden', isPassive);
        }

        const ipInput = document.getElementById(context + '-ip');
        const ipReq = document.getElementById(context + '-ip-req');
        const ipBadge = document.getElementById(context + '-ip-badge');
        const activeCheck = document.getElementById(context + '-is-active');
        const statusSelect = document.getElementById(context + '-status');

        if (isPassive) {
            if (ipInput) {
                ipInput.removeAttribute('required');
                ipInput.value = '';
            }
            if (ipReq) ipReq.classList.add('hidden');
            if (ipBadge) {
                ipBadge.textContent = 'Sin Red / Periférico';
                ipBadge.className = 'text-[9px] text-purple-400 font-mono';
            }
            if (activeCheck) {
                activeCheck.checked = false;
                activeCheck.disabled = true;
            }
            if (statusSelect && statusSelect.value === 'assigned') {
                statusSelect.value = 'in_stock';
            }
        } else {
            if (ipInput) {
                ipInput.setAttribute('required', 'required');
                ipInput.placeholder = 'Ej: 192.168.1.10';
            }
            if (ipReq) ipReq.classList.remove('hidden');
            if (ipBadge) {
                ipBadge.textContent = 'Ping ICMP';
                ipBadge.className = 'text-[9px] text-emerald-400 font-mono';
            }
            if (activeCheck) {
                activeCheck.disabled = false;
            }
        }

        // 6. Aviso pasivo en creación y preselección inteligente de protocolo
        if (context === 'create') {
            const passiveNotice = document.getElementById('create-passive-notice');
            if (passiveNotice) {
                passiveNotice.classList.toggle('hidden', !isPassive);
            }

            const accSelect = document.getElementById('create-access-type');
            if (accSelect) {
                if (isPrinter) {
                    if (accSelect.value !== 'WEB') {
                        accSelect.value = 'WEB';
                        handleCreateAccessChange('WEB');
                    }
                } else if (isComputer) {
                    if (!accSelect.value || accSelect.value === 'WEB' || accSelect.value === 'TELNET') {
                        accSelect.value = 'SSH+VNC';
                        handleCreateAccessChange('SSH+VNC');
                    }
                } else if (isNetwork) {
                    if (!accSelect.value || accSelect.value === 'VNC' || accSelect.value === 'SSH+VNC') {
                        accSelect.value = 'SSH';
                        handleCreateAccessChange('SSH');
                    }
                }
            }
        }
    }

    function handleCreateDeviceTypeChange(type) {
        applyDeviceTypeVisibility('create', type);
    }

    function handleEditDeviceTypeChange(type) {
        applyDeviceTypeVisibility('edit', type);
    }

    function toggleGlpiFields(checked) {
        const fields = document.getElementById('create-glpi-fields');
        if (fields) {
            fields.classList.toggle('hidden', !checked);
        }
    }

    function handleGlpiOsChange(os) {
        const domWrap = document.getElementById('create-glpi-domain-wrapper');
        const userInp = document.getElementById('create-glpi-user');
        if (os === 'windows') {
            if (domWrap) domWrap.classList.remove('hidden');
            if (userInp && (!userInp.value || userInp.value === 'soporte' || userInp.value === 'root')) {
                userInp.value = 'Administrador';
            }
        } else {
            if (domWrap) domWrap.classList.add('hidden');
            if (userInp && (!userInp.value || userInp.value === 'Administrador')) {
                userInp.value = 'soporte';
            }
        }
    }

    async function detectGlpiOs() {
        const ip = document.getElementById('create-ip')?.value?.trim();
        const statusBox = document.getElementById('glpi-detect-status');
        if (!ip) {
            alert('Por favor ingrese primero la dirección IP para auto-detectar el Sistema Operativo.');
            return;
        }
        if (statusBox) {
            statusBox.classList.remove('hidden');
            statusBox.innerHTML = '<span class="animate-pulse">Sondeando puertos de red (445/3389 vs 22)...</span>';
        }
        try {
            const res = await fetch(`/admin/inventory/deploy/quick-status?ip=${encodeURIComponent(ip)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            const osSelect = document.getElementById('create-glpi-os');
            if (data && data.os) {
                if (data.os === 'windows' || data.os === 'linux') {
                    if (osSelect) {
                        osSelect.value = data.os;
                        handleGlpiOsChange(data.os);
                    }
                    if (statusBox) {
                        statusBox.innerHTML = `<strong class="text-emerald-400">✓ Detectado:</strong> ${data.os === 'windows' ? 'Microsoft Windows' : 'Linux'} • Estado: ${data.status || 'Activo'}`;
                    }
                } else {
                    if (statusBox) statusBox.innerHTML = `<span>Respuesta recibida: ${data.message || 'Sistema no identificado con certeza'}</span>`;
                }
            } else {
                if (statusBox) statusBox.innerHTML = '<span class="text-amber-300">Host sin respuesta en puertos conocidos. Seleccione el SO manualmente.</span>';
            }
        } catch (err) {
            if (statusBox) statusBox.innerHTML = '<span class="text-rose-400">No se pudo sondear el host automáticamente.</span>';
        }
    }

    // =========================================================================
    // CREACIÓN RÁPIDA DE SEDE REGIONAL VÍA AJAX
    // =========================================================================
    function openQuickSiteModal() {
        const m = document.getElementById('modal-quick-site');
        const form = document.getElementById('form-quick-site');
        const err = document.getElementById('quick-site-error');
        if (form) form.reset();
        if (err) { err.classList.add('hidden'); err.textContent = ''; }
        if (m) {
            m.classList.remove('hidden');
            m.classList.add('flex');
            setTimeout(() => document.getElementById('quick-site-name')?.focus(), 50);
        }
    }

    function closeQuickSiteModal() {
        const m = document.getElementById('modal-quick-site');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    async function submitQuickSite(e) {
        e.preventDefault();
        const name = document.getElementById('quick-site-name')?.value?.trim();
        const ip = document.getElementById('quick-site-ip')?.value?.trim();
        const phone = document.getElementById('quick-site-phone')?.value?.trim();
        const errBox = document.getElementById('quick-site-error');
        const btn = document.getElementById('btn-quick-site-submit');

        if (!name) return;

        if (errBox) { errBox.classList.add('hidden'); errBox.textContent = ''; }
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="animate-spin material-symbols-outlined text-sm">sync</span> Guardando...'; }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                || document.querySelector('input[name="_token"]')?.value;

            const res = await fetch("{{ route('admin.sites.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    name: name,
                    ip: ip || null,
                    phone_1: phone || null,
                    is_active: 1
                })
            });

            const data = await res.json();

            if (res.ok && data.success && data.site) {
                // Inyectar en el selector de sedes del modal de creación
                const createSiteSelect = document.getElementById('create-monitored-site-id');
                if (createSiteSelect) {
                    const opt = document.createElement('option');
                    opt.value = data.site.id;
                    opt.textContent = `${data.site.name} (IP Enlace: ${data.site.ip || 'Sin Enlace'})`;
                    opt.selected = true;
                    createSiteSelect.appendChild(opt);
                }

                // Inyectar también en el selector de sedes del modal de edición
                const editSiteSelect = document.getElementById('edit-site-id');
                if (editSiteSelect) {
                    const opt2 = document.createElement('option');
                    opt2.value = data.site.id;
                    opt2.textContent = `${data.site.name} (IP Enlace: ${data.site.ip || 'Sin Enlace'})`;
                    editSiteSelect.appendChild(opt2);
                    const editModal = document.getElementById('modal-device-edit');
                    if (editModal && !editModal.classList.contains('hidden')) {
                        editSiteSelect.value = data.site.id;
                    }
                }
                closeQuickSiteModal();
            } else {
                const msg = data.message || (data.errors ? Object.values(data.errors).flat().join('<br>') : 'Error al registrar la sede.');
                if (errBox) {
                    errBox.innerHTML = msg;
                    errBox.classList.remove('hidden');
                }
            }
        } catch (err) {
            console.error('Error al crear sede:', err);
            if (errBox) {
                errBox.textContent = 'Error de conexión al servidor al intentar guardar la sede.';
                errBox.classList.remove('hidden');
            }
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined text-sm">save</span><span>Guardar Sede</span>'; }
        }
    }

    // =========================================================================
    // GESTIÓN DINÁMICA DE PERIFÉRICOS EXTRAS (MODALES CREAR Y EDITAR)
    // =========================================================================
    let createExtraPeripheralCounter = 0;
    let editExtraPeripheralCounter = 0;

    // Periféricos Adicionales en Modal de Creación (Opción 1)
    function addCreateExtraPeripheral(initialData = null) {
        const container = document.getElementById('create-extra-peripherals-list');
        if (!container) return;
        const idx = createExtraPeripheralCounter++;
        const card = document.createElement('div');
        card.className = 'extra-peripheral-card p-3 rounded-xl bg-[#040e1e] border border-cyan-500/30 space-y-2 relative animate-in fade-in duration-150';
        card.id = `create-extra-periph-${idx}`;

        const pType = initialData?.type || 'printer';
        const pBrand = initialData?.brand || '';
        const pModel = initialData?.model || '';
        const pSerial = initialData?.serial || '';

        card.innerHTML = `
            <div class="flex items-center justify-between pb-1 border-b border-obsidian-border/50">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-cyan-400">devices_other</span>
                    <select name="extra_peripherals[${idx}][type]" class="px-2 py-1 rounded bg-[#020710] border border-cyan-500/40 text-cyan-200 text-xs font-mono font-bold focus:outline-none cursor-pointer">
                        <option value="printer" ${pType === 'printer' ? 'selected' : ''}>🖨️ Impresora / Multifuncional</option>
                        <option value="scanner" ${pType === 'scanner' ? 'selected' : ''}>📠 Escáner / Lector</option>
                        <option value="headset" ${pType === 'headset' ? 'selected' : ''}>🎧 Auriculares / Headset</option>
                        <option value="monitor" ${pType === 'monitor' ? 'selected' : ''}>🖥️ Monitor Secundario</option>
                        <option value="webcam" ${pType === 'webcam' ? 'selected' : ''}>📷 Cámara Web</option>
                        <option value="mouse" ${pType === 'mouse' ? 'selected' : ''}>🖱️ Mouse Adicional</option>
                        <option value="keyboard" ${pType === 'keyboard' ? 'selected' : ''}>⌨️ Teclado Adicional</option>
                        <option value="other_peripheral" ${pType === 'other_peripheral' ? 'selected' : ''}>📦 Otro Periférico</option>
                    </select>
                </div>
                <button type="button" onclick="removeCreateExtraPeripheral(this)" class="p-1 rounded text-rose-400 hover:text-white hover:bg-rose-950/60 transition cursor-pointer" title="Eliminar este periférico">
                    <span class="material-symbols-outlined text-sm">delete</span>
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div>
                    <label class="block text-[10px] text-obsidian-muted mb-0.5">Marca</label>
                    <input type="text" name="extra_peripherals[${idx}][brand]" value="${pBrand}" placeholder="ej. HP / Epson" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-[10px] text-obsidian-muted mb-0.5">Modelo</label>
                    <input type="text" name="extra_peripherals[${idx}][model]" value="${pModel}" placeholder="ej. LaserJet Pro" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-cyan-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-[10px] text-amber-400/90 mb-0.5">Número de Serie (Serial)</label>
                    <input type="text" name="extra_peripherals[${idx}][serial]" value="${pSerial}" placeholder="ej. CNB12345" class="w-full px-2 py-1 rounded bg-[#020710] border border-amber-500/30 text-amber-200 text-[11px] font-mono focus:border-amber-400 focus:outline-none">
                </div>
            </div>
        `;
        container.appendChild(card);
    }

    function removeCreateExtraPeripheral(btn) {
        const card = btn.closest('.extra-peripheral-card');
        if (card) card.remove();
    }

    // Periféricos Adicionales en Modal de Edición
    function addEditExtraPeripheral(initialData = null) {
        const container = document.getElementById('edit-extra-peripherals-list');
        if (!container) return;
        const idx = editExtraPeripheralCounter++;
        const card = document.createElement('div');
        card.className = 'extra-peripheral-card p-3 rounded-xl bg-[#040e1e] border border-amber-500/30 space-y-2 relative animate-in fade-in duration-150';
        card.id = `edit-extra-periph-${idx}`;

        const pId = initialData?.id || '';
        const pType = initialData?.type || 'printer';
        const pBrand = initialData?.brand || '';
        const pModel = initialData?.model || '';
        const pSerial = initialData?.serial_number || initialData?.serial || '';

        card.innerHTML = `
            <input type="hidden" name="extra_peripherals[${idx}][id]" value="${pId}">
            <input type="hidden" name="extra_peripherals[${idx}][remove]" id="edit-extra-remove-${idx}" value="0">
            <div class="flex items-center justify-between pb-1 border-b border-obsidian-border/50">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-amber-400">devices_other</span>
                    <select name="extra_peripherals[${idx}][type]" class="px-2 py-1 rounded bg-[#020710] border border-amber-500/40 text-amber-200 text-xs font-mono font-bold focus:outline-none cursor-pointer">
                        <option value="printer" ${pType === 'printer' ? 'selected' : ''}>🖨️ Impresora / Multifuncional</option>
                        <option value="scanner" ${pType === 'scanner' ? 'selected' : ''}>📠 Escáner / Lector</option>
                        <option value="headset" ${pType === 'headset' ? 'selected' : ''}>🎧 Auriculares / Headset</option>
                        <option value="monitor" ${pType === 'monitor' ? 'selected' : ''}>🖥️ Monitor Secundario</option>
                        <option value="webcam" ${pType === 'webcam' ? 'selected' : ''}>📷 Cámara Web</option>
                        <option value="mouse" ${pType === 'mouse' ? 'selected' : ''}>🖱️ Mouse Adicional</option>
                        <option value="keyboard" ${pType === 'keyboard' ? 'selected' : ''}>⌨️ Teclado Adicional</option>
                        <option value="other_peripheral" ${pType === 'other_peripheral' ? 'selected' : ''}>📦 Otro Periférico</option>
                    </select>
                    ${pId ? '<span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-amber-950 text-amber-300 border border-amber-500/40">Existente</span>' : '<span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-emerald-950 text-emerald-300 border border-emerald-500/40">Nuevo</span>'}
                </div>
                <button type="button" onclick="removeEditExtraPeripheral(this, '${idx}')" class="p-1 rounded text-rose-400 hover:text-white hover:bg-rose-950/60 transition cursor-pointer" title="Desvincular o eliminar">
                    <span class="material-symbols-outlined text-sm">delete</span>
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div>
                    <label class="block text-[10px] text-obsidian-muted mb-0.5">Marca</label>
                    <input type="text" name="extra_peripherals[${idx}][brand]" value="${pBrand}" placeholder="ej. HP / Epson" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-amber-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-[10px] text-obsidian-muted mb-0.5">Modelo</label>
                    <input type="text" name="extra_peripherals[${idx}][model]" value="${pModel}" placeholder="ej. LaserJet Pro" class="w-full px-2 py-1 rounded bg-[#020710] border border-obsidian-border text-white text-[11px] font-mono focus:border-amber-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-[10px] text-amber-400/90 mb-0.5">Número de Serie (Serial)</label>
                    <input type="text" name="extra_peripherals[${idx}][serial]" value="${pSerial}" placeholder="ej. CNB12345" class="w-full px-2 py-1 rounded bg-[#020710] border border-amber-500/30 text-amber-200 text-[11px] font-mono focus:border-amber-400 focus:outline-none">
                </div>
            </div>
        `;
        container.appendChild(card);
    }

    function removeEditExtraPeripheral(btn, idx) {
        const card = btn.closest('.extra-peripheral-card');
        if (!card) return;
        const removeInput = document.getElementById(`edit-extra-remove-${idx}`);
        const hasExistingId = card.querySelector(`input[name="extra_peripherals[${idx}][id]"]`)?.value;
        if (removeInput && hasExistingId) {
            removeInput.value = '1';
            card.classList.add('hidden');
        } else {
            card.remove();
        }
    }

    // 9. MODAL CREACIÓN DISPOSITIVO
    function openDeviceCreateModal() {
        const form = document.getElementById('form-device-create');
        if (form) form.reset();
        const wrapper = document.getElementById('create-device-target-wrapper');
        if (wrapper) wrapper.classList.add('hidden');
        handleCreateDeviceTypeChange('desktop');
        handleCreateAccessChange(document.getElementById('create-access-type')?.value || 'SSH');
        const vncPassInput = document.getElementById('create-vnc-password');
        if (vncPassInput) vncPassInput.value = '';
        if (typeof window['create-device-picker_reset'] === 'function') {
            window['create-device-picker_reset']();
        }
        const statusSelect = document.getElementById('create-status');
        if (statusSelect) {
            statusSelect.value = 'assigned';
            handleCreateStatusChange('assigned');
        }

        // Limpiar lista de periféricos extras
        const extrasContainer = document.getElementById('create-extra-peripherals-list');
        if (extrasContainer) extrasContainer.innerHTML = '';
        createExtraPeripheralCounter = 0;

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

    // 10. MODAL EDICIÓN DISPOSITIVO
    function openDeviceEditModal(dev, section = null) {
        document.getElementById('form-device-edit').action = '/admin/devices/' + dev.id;
        const editBadge = document.getElementById('edit-device-name-badge');
        if (editBadge) {
            editBadge.innerText = dev.display_identity || dev.name || 'Dispositivo';
        }
        
        const siteSelect = document.getElementById('edit-site-id');
        if (siteSelect) siteSelect.value = dev.monitored_site_id || '';

        document.getElementById('edit-name').value = dev.name || '';
        const typeSelect = document.getElementById('edit-type');
        if (typeSelect) typeSelect.value = dev.type || 'desktop';
        applyDeviceTypeVisibility('edit', dev.type || 'desktop');

        document.getElementById('edit-ip').value = dev.ip || dev.ip_address || '';
        document.getElementById('edit-mac').value = dev.mac || dev.mac_address || '';
        document.getElementById('edit-vendor-data').value = dev.vendor_data || dev.brand || '';
        document.getElementById('edit-model').value = dev.model || '';

        const statusSelect = document.getElementById('edit-status');
        if (statusSelect) statusSelect.value = dev.status || 'assigned';

        // Serial con detección reactiva GLPI
        const serialInput = document.getElementById('edit-serial');
        const glpiBadge = document.getElementById('edit-glpi-badge');
        const glpiWarning = document.getElementById('edit-glpi-serial-warning');
        const glpiSyncTime = document.getElementById('edit-glpi-sync-time');
        const hasGlpi = !!dev.last_glpi_sync_at;

        if (serialInput) {
            serialInput.value = dev.serial || dev.serial_number || '';
            serialInput.dataset.originalSerial = dev.serial || dev.serial_number || '';
            serialInput.dataset.hasGlpi = hasGlpi ? '1' : '0';
        }

        if (glpiBadge) glpiBadge.classList.toggle('hidden', !hasGlpi);
        if (glpiWarning) {
            glpiWarning.classList.add('hidden');
            if (hasGlpi && glpiSyncTime) {
                try {
                    const d = new Date(dev.last_glpi_sync_at);
                    glpiSyncTime.innerText = d.toLocaleString('es-VE');
                } catch(e) {
                    glpiSyncTime.innerText = dev.last_glpi_sync_at || 'Reciente';
                }
            }
        }

        // Carga de Periféricos de Puesto (Monitor, Teclado, Mouse, Protector/UPS) y Extras
        const periphs = dev.peripherals || [];
        const baseTypesSeen = { monitor: false, keyboard: false, mouse: false, protector: false };

        let monitor = null;
        let keyboard = null;
        let mouse = null;
        let protector = null;
        const extraPeriphs = [];

        periphs.forEach(p => {
            if (p.type === 'monitor' && !baseTypesSeen.monitor) {
                baseTypesSeen.monitor = true;
                monitor = p;
            } else if (p.type === 'keyboard' && !baseTypesSeen.keyboard) {
                baseTypesSeen.keyboard = true;
                keyboard = p;
            } else if (p.type === 'mouse' && !baseTypesSeen.mouse) {
                baseTypesSeen.mouse = true;
                mouse = p;
            } else if ((p.type === 'surge_protector' || p.type === 'ups') && !baseTypesSeen.protector) {
                baseTypesSeen.protector = true;
                protector = p;
            } else {
                extraPeriphs.push(p);
            }
        });

        const monBrand = document.getElementById('edit-monitor-brand');
        const monModel = document.getElementById('edit-monitor-model');
        const monSerial = document.getElementById('edit-monitor-serial');
        const monRem = document.getElementById('edit-remove-monitor');
        if (monBrand) monBrand.value = monitor ? (monitor.brand || '') : '';
        if (monModel) monModel.value = monitor ? (monitor.model || '') : '';
        if (monSerial) monSerial.value = monitor ? (monitor.serial_number || '') : '';
        if (monRem) monRem.checked = false;

        const kbdBrand = document.getElementById('edit-keyboard-brand');
        const kbdModel = document.getElementById('edit-keyboard-model');
        const kbdSerial = document.getElementById('edit-keyboard-serial');
        const kbdRem = document.getElementById('edit-remove-keyboard');
        if (kbdBrand) kbdBrand.value = keyboard ? (keyboard.brand || '') : '';
        if (kbdModel) kbdModel.value = keyboard ? (keyboard.model || '') : '';
        if (kbdSerial) kbdSerial.value = keyboard ? (keyboard.serial_number || '') : '';
        if (kbdRem) kbdRem.checked = false;

        const mseBrand = document.getElementById('edit-mouse-brand');
        const mseModel = document.getElementById('edit-mouse-model');
        const mseSerial = document.getElementById('edit-mouse-serial');
        const mseRem = document.getElementById('edit-remove-mouse');
        if (mseBrand) mseBrand.value = mouse ? (mouse.brand || '') : '';
        if (mseModel) mseModel.value = mouse ? (mouse.model || '') : '';
        if (mseSerial) mseSerial.value = mouse ? (mouse.serial_number || '') : '';
        if (mseRem) mseRem.checked = false;

        const protType = document.getElementById('edit-protector-type');
        const protBrand = document.getElementById('edit-protector-brand');
        const protModel = document.getElementById('edit-protector-model');
        const protSerial = document.getElementById('edit-protector-serial');
        const protRem = document.getElementById('edit-remove-protector');
        if (protType) protType.value = protector ? (protector.type || 'surge_protector') : 'surge_protector';
        if (protBrand) protBrand.value = protector ? (protector.brand || '') : '';
        if (protModel) protModel.value = protector ? (protector.model || '') : '';
        if (protSerial) protSerial.value = protector ? (protector.serial_number || '') : '';
        if (protRem) protRem.checked = false;

        // Cargar periféricos extras en su lista dinámica
        const editExtrasContainer = document.getElementById('edit-extra-peripherals-list');
        if (editExtrasContainer) editExtrasContainer.innerHTML = '';
        editExtraPeripheralCounter = 0;
        extraPeriphs.forEach(ep => addEditExtraPeripheral(ep));

        document.getElementById('edit-ports').value = dev.ports || '';
        
        const acc = (dev.access_type || 'SIN SOPORTE').toUpperCase();
        document.getElementById('edit-access-type').value = acc;
        handleEditAccessChange(acc);
        if (acc === 'SSH+VNC') {
            document.getElementById('edit-ssh-port').value = dev.ssh_port || 22;
            document.getElementById('edit-vnc-port').value = dev.vnc_port || 5900;
        } else {
            const singlePortVal = dev.access_port || ((acc === 'SSH') ? (dev.ssh_port || 22) : (acc === 'TELNET' ? (dev.telnet_port || 23) : (acc === 'WEB' ? (dev.web_port || 80) : (acc === 'VNC' ? (dev.vnc_port || 5900) : ''))));
            document.getElementById('edit-access-port').value = singlePortVal;
        }
        document.getElementById('edit-notes').value = dev.notes || '';
        document.getElementById('edit-is-active').checked = !!dev.is_active;

        const alertCheck = document.getElementById('edit-device-telegram-alert');
        const alertTarget = document.getElementById('edit-device-telegram-target');
        const alertWrapper = document.getElementById('edit-device-target-wrapper');
        if (alertCheck) {
            alertCheck.checked = !!dev.telegram_alert_enabled;
            if (alertTarget) alertTarget.value = dev.telegram_alert_target || 'owner';
            if (alertWrapper) alertWrapper.classList.toggle('hidden', !dev.telegram_alert_enabled);
        }

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

        document.getElementById('edit-vnc-password').value = '';
        const vncClearCheck = document.getElementById('edit-clear-vnc-credentials');
        if (vncClearCheck) vncClearCheck.checked = false;
        const vncCredBadge = document.getElementById('edit-vnc-cred-status');
        if (vncCredBadge) {
            if (dev.has_vnc_credentials) {
                vncCredBadge.innerHTML = '<span class="inline-flex items-center gap-1 text-[10px] font-mono px-2 py-0.5 rounded bg-purple-500/20 text-purple-400 border border-purple-500/40"><span class="material-symbols-outlined text-xs">verified_user</span> Clave Configurada</span>';
            } else {
                vncCredBadge.innerHTML = '<span class="inline-flex items-center gap-1 text-[10px] font-mono px-2 py-0.5 rounded bg-slate-500/20 text-slate-400 border border-slate-500/30">Sin Contraseña Guardada</span>';
            }
        }

        // Configuración de Asignación / LDAP en Edición con componente ldap-user-picker
        if (typeof window['edit-device-picker_populate'] === 'function') {
            if (dev.custodian_name) {
                window['edit-device-picker_populate']({
                    name: dev.custodian_name,
                    id_number: dev.custodian_id_number,
                    department: dev.custodian_department,
                    role: dev.custodian_role,
                    user_id: dev.current_user_id
                });
            } else {
                window['edit-device-picker_reset']();
            }
        }

        const editAssignBadge = document.getElementById('edit-assignment-badge');
        if (editAssignBadge) {
            if (dev.custodian_name) {
                editAssignBadge.className = 'px-2.5 py-0.5 rounded text-[10px] font-bold bg-emerald-950 text-emerald-300 border border-emerald-500/40';
                editAssignBadge.textContent = 'Asignado';
            } else {
                editAssignBadge.className = 'px-2.5 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700';
                editAssignBadge.textContent = 'En Stock / Sin Asignación';
            }
        }

        const m = document.getElementById('modal-device-edit');
        if (m) {
            m.classList.remove('hidden');
            m.classList.add('flex');
        }

        // Si se invocó específicamente para periféricos, desplazamiento suave al Bloque 3
        if (section === 'peripherals') {
            setTimeout(() => {
                const sec = document.getElementById('edit-section-peripherals');
                if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
        }
    }

    function handleEditSerialInput(val) {
        const serialInput = document.getElementById('edit-serial');
        const glpiWarning = document.getElementById('edit-glpi-serial-warning');
        if (!glpiWarning || !serialInput) return;
        const hasGlpi = serialInput.dataset.hasGlpi === '1';
        const orig = serialInput.dataset.originalSerial || '';
        if (hasGlpi && val.trim() !== orig.trim()) {
            glpiWarning.classList.remove('hidden');
        } else {
            glpiWarning.classList.add('hidden');
        }
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

    function closeDeviceEditModal() {
        const m = document.getElementById('modal-device-edit');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    // 11. MODAL CREACIÓN SEDE
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

    // 12. MODAL HISTÓRICO & TELEMETRÍA (CHART.JS)
    function openDeviceHistoryModal(id, name, ip, devNum = null) {
        currentDeviceId = id;
        document.getElementById('hist-device-name').innerText = name;
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

    // =========================================================================
    // SERVICIO REACTIVO DE ASIGNACIÓN LDAP & GENERACIÓN DE ACTAS UINF-4.05
    // =========================================================================
    let ldapSearchAbortController = null;
    let quickAssignLastResults = [];
    let quickAssignDebounceTimer = null;
    let createLdapDebounceTimer = null;
    let createLdapLastResults = [];
    let editLdapDebounceTimer = null;
    let editLdapLastResults = [];

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function fetchLdapUsers(query) {
        if (ldapSearchAbortController) {
            ldapSearchAbortController.abort();
        }
        ldapSearchAbortController = new AbortController();
        try {
            const res = await fetch(`/admin/users/ldap/search?q=${encodeURIComponent(query)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: ldapSearchAbortController.signal
            });
            if (!res.ok) return [];
            const data = await res.json();
            return (data && data.success && Array.isArray(data.results)) ? data.results : [];
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.warn('Búsqueda LDAP cancelada o fallida:', err);
            }
            return [];
        }
    }

    // --- MODAL DE ASIGNACIÓN RÁPIDA (COLUMNA "ACTA DE ASIGNACIÓN") ---
    function openQuickAssignModal(assetId, deviceName, ip = '', serial = '', custodianName = '', custodianId = '', custodianDept = '') {
        const form = document.getElementById('form-quick-assign');
        if (form) form.reset();

        document.getElementById('quick-assign-asset-id').value = assetId;
        document.getElementById('quick-assign-device-name').innerText = `${deviceName} • IP: ${ip || 'Sin IP'} • Serial: ${serial || 'S/N'}`;
        document.getElementById('quick-assign-movement-type').value = 'assignment';
        document.getElementById('quick-assign-user-id').value = '';
        document.getElementById('quick-assign-submit-text').innerText = 'Formalizar y Generar Acta UINF-4.05';

        const statusBox = document.getElementById('quick-assign-current-status-box');
        const currentCustSpan = document.getElementById('quick-assign-current-custodian');
        if (custodianName && custodianName.trim().length > 0) {
            if (statusBox) statusBox.classList.remove('hidden');
            if (currentCustSpan) currentCustSpan.textContent = `${custodianName} ${custodianDept ? '(' + custodianDept + ')' : ''}`;
        } else {
            if (statusBox) statusBox.classList.add('hidden');
        }

        // Limpiar buscador y dropdown
        const searchInput = document.getElementById('quick-assign-ldap-search');
        if (searchInput) searchInput.value = '';
        const dropdown = document.getElementById('quick-assign-ldap-dropdown');
        if (dropdown) { dropdown.innerHTML = ''; dropdown.classList.add('hidden'); }
        const spinner = document.getElementById('quick-assign-ldap-spinner');
        if (spinner) spinner.classList.add('hidden');

        // Tarjeta preview
        const card = document.getElementById('quick-assign-selected-card');
        if (card) card.classList.add('hidden');

        // Campos editables
        document.getElementById('quick-assign-custodian-name').value = custodianName || '';
        document.getElementById('quick-assign-custodian-id').value = custodianId || '';
        document.getElementById('quick-assign-custodian-dept').value = custodianDept || '';
        document.getElementById('quick-assign-custodian-role').value = '';
        document.getElementById('quick-assign-observations').value = '';

        const errBox = document.getElementById('quick-assign-error');
        if (errBox) { errBox.classList.add('hidden'); errBox.innerHTML = ''; }
        const succBox = document.getElementById('quick-assign-success');
        if (succBox) { succBox.classList.add('hidden'); succBox.innerHTML = ''; }

        const submitBtn = document.getElementById('quick-assign-submit-btn');
        if (submitBtn) { submitBtn.disabled = false; }

        const modal = document.getElementById('modal-quick-assign');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => searchInput?.focus(), 60);
        }
    }

    function closeQuickAssignModal() {
        const modal = document.getElementById('modal-quick-assign');
        if (modal) {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
        if (quickAssignDebounceTimer) clearTimeout(quickAssignDebounceTimer);
    }

    function handleQuickAssignLdapInput(query) {
        if (quickAssignDebounceTimer) clearTimeout(quickAssignDebounceTimer);
        const q = query.trim();
        const dropdown = document.getElementById('quick-assign-ldap-dropdown');
        const spinner = document.getElementById('quick-assign-ldap-spinner');

        if (q.length < 2) {
            if (dropdown) { dropdown.innerHTML = ''; dropdown.classList.add('hidden'); }
            if (spinner) spinner.classList.add('hidden');
            return;
        }

        if (spinner) spinner.classList.remove('hidden');
        quickAssignDebounceTimer = setTimeout(async () => {
            const results = await fetchLdapUsers(q);
            if (spinner) spinner.classList.add('hidden');
            quickAssignLastResults = results;
            renderQuickAssignLdapDropdown(results);
        }, 250);
    }

    function renderQuickAssignLdapDropdown(users) {
        const dropdown = document.getElementById('quick-assign-ldap-dropdown');
        if (!dropdown) return;

        if (!users || users.length === 0) {
            dropdown.innerHTML = '<div class="p-3 text-center text-obsidian-muted font-mono text-[11px]">No se encontraron trabajadores en LDAP ni registros locales para el término ingresado.</div>';
            dropdown.classList.remove('hidden');
            return;
        }

        dropdown.innerHTML = users.map((u, idx) => `
            <div onclick="selectQuickAssignUserByIndex(${idx})" 
                 class="p-2 rounded-lg hover:bg-cyan-950/70 hover:border-cyan-500/40 border border-transparent flex items-center justify-between gap-2 cursor-pointer transition group">
                <div class="space-y-0.5">
                    <div class="font-bold text-white group-hover:text-cyan-300 flex items-center gap-1.5 text-xs">
                        <span>${escapeHtml(u.name)}</span>
                        <span class="px-1.5 py-0.2 rounded text-[8.5px] bg-cyan-950 border border-cyan-500/40 text-cyan-300 font-mono">${escapeHtml(u.uid || '')}</span>
                    </div>
                    <div class="text-[10px] text-obsidian-muted flex items-center gap-2 font-mono">
                        <span class="text-cyan-400 font-semibold">${escapeHtml(u.cedula || 'Sin Cédula')}</span>
                        <span>•</span>
                        <span>${escapeHtml(u.department || 'ATIT')}</span>
                        ${u.role ? `<span>• ${escapeHtml(u.role)}</span>` : ''}
                    </div>
                </div>
                <span class="material-symbols-outlined text-sm text-obsidian-muted group-hover:text-cyan-400">check_circle</span>
            </div>
        `).join('');
        dropdown.classList.remove('hidden');
    }

    function selectQuickAssignUserByIndex(idx) {
        const u = quickAssignLastResults[idx];
        if (!u) return;

        document.getElementById('quick-assign-user-id').value = u.user_id || '';
        document.getElementById('quick-assign-custodian-name').value = u.name || '';
        document.getElementById('quick-assign-custodian-id').value = u.cedula || '';
        document.getElementById('quick-assign-custodian-dept').value = u.department || 'ATIT Carabobo';
        document.getElementById('quick-assign-custodian-role').value = u.role || '';

        const card = document.getElementById('quick-assign-selected-card');
        if (card) {
            document.getElementById('quick-assign-card-name').textContent = `${u.name} (${u.uid || ''})`;
            document.getElementById('quick-assign-card-cedula').textContent = `C.I: ${u.cedula || 'N/A'}`;
            document.getElementById('quick-assign-card-dept').textContent = `Dpto: ${u.department || 'ATIT Carabobo'}`;
            card.classList.remove('hidden');
        }

        const dropdown = document.getElementById('quick-assign-ldap-dropdown');
        if (dropdown) dropdown.classList.add('hidden');
        const searchInput = document.getElementById('quick-assign-ldap-search');
        if (searchInput) searchInput.value = '';
    }

    function clearQuickAssignSelection() {
        const card = document.getElementById('quick-assign-selected-card');
        if (card) card.classList.add('hidden');
        document.getElementById('quick-assign-user-id').value = '';
        document.getElementById('quick-assign-custodian-name').value = '';
        document.getElementById('quick-assign-custodian-id').value = '';
        document.getElementById('quick-assign-custodian-dept').value = '';
        document.getElementById('quick-assign-custodian-role').value = '';
    }

    function releaseDeviceToStock() {
        const conf = confirm('¿Está seguro de devolver este equipo a stock disponible? Se generará el acta de devolución (ACTA-DEV) y se liberará la asignación actual.');
        if (!conf) return;

        document.getElementById('quick-assign-movement-type').value = 'return';
        const custInput = document.getElementById('quick-assign-custodian-name');
        if (!custInput.value || custInput.value.trim().length === 0) {
            const currentCust = document.getElementById('quick-assign-current-custodian')?.textContent || 'Almacén / Stock';
            custInput.value = currentCust.trim();
        }
        document.getElementById('quick-assign-submit-text').textContent = 'Formalizar Devolución a Stock (ACTA-DEV)';
        document.getElementById('form-quick-assign').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }

    async function submitQuickAssign(e) {
        e.preventDefault();
        const form = document.getElementById('form-quick-assign');
        const btn = document.getElementById('quick-assign-submit-btn');
        const errBox = document.getElementById('quick-assign-error');
        const succBox = document.getElementById('quick-assign-success');

        const custName = document.getElementById('quick-assign-custodian-name')?.value?.trim();
        if (!custName) {
            if (errBox) {
                errBox.textContent = 'El nombre del responsable es obligatorio para formalizar el acta.';
                errBox.classList.remove('hidden');
            }
            return;
        }

        if (errBox) { errBox.classList.add('hidden'); errBox.innerHTML = ''; }
        if (succBox) { succBox.classList.add('hidden'); succBox.innerHTML = ''; }
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="animate-spin material-symbols-outlined text-sm">sync</span> <span>Guardando Acta...</span>';
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                || document.querySelector('input[name="_token"]')?.value;

            const payload = {
                inventory_asset_id: document.getElementById('quick-assign-asset-id').value,
                movement_type: document.getElementById('quick-assign-movement-type').value || 'assignment',
                user_id: document.getElementById('quick-assign-user-id').value || null,
                custodian_name: custName,
                custodian_id_number: document.getElementById('quick-assign-custodian-id')?.value?.trim() || null,
                custodian_department: document.getElementById('quick-assign-custodian-dept')?.value?.trim() || null,
                custodian_role: document.getElementById('quick-assign-custodian-role')?.value?.trim() || null,
                observations: document.getElementById('quick-assign-observations')?.value?.trim() || null,
            };

            const res = await fetch("{{ route('admin.inventory.assignments.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();

            if (res.ok && data.success) {
                if (succBox) {
                    succBox.innerHTML = `
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-bold flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                <span>${data.message || 'Acta generada exitosamente.'}</span>
                            </span>
                            <a href="${data.pdf_url}" target="_blank" class="px-2.5 py-1 rounded-lg bg-red-600 hover:bg-red-500 text-white font-bold text-xs inline-flex items-center gap-1 shadow-sm transition">
                                <span class="material-symbols-outlined text-xs">picture_as_pdf</span>
                                <span>Descargar PDF</span>
                            </a>
                        </div>
                    `;
                    succBox.classList.remove('hidden');
                }
                showDeviceToast(data.message || 'Acta registrada con éxito.', 'success');
                setTimeout(() => {
                    closeQuickAssignModal();
                    window.location.reload();
                }, 1300);
            } else {
                const msg = data.message || (data.errors ? Object.values(data.errors).flat().join('<br>') : 'Error al registrar el acta de asignación.');
                if (errBox) {
                    errBox.innerHTML = msg;
                    errBox.classList.remove('hidden');
                }
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined text-sm">assignment_turned_in</span> <span>Reintentar Asignación</span>';
                }
            }
        } catch (err) {
            console.error('Error al procesar acta:', err);
            if (errBox) {
                errBox.textContent = 'Error de conexión con el servidor al registrar el acta.';
                errBox.classList.remove('hidden');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined text-sm">assignment_turned_in</span> <span>Reintentar Asignación</span>';
            }
        }
    }

    function handleCreateStatusChange(status) {
        const wrap = document.getElementById('create-assignment-wrapper');
        if (!wrap) return;
        if (status === 'assigned') {
            wrap.classList.remove('opacity-60');
            wrap.classList.add('border-cyan-500/60');
        } else {
            wrap.classList.add('opacity-60');
            wrap.classList.remove('border-cyan-500/60');
        }
    }

    // --- NOTIFICACIONES TOAST LIGERAS ---
    function showDeviceToast(message, type = 'info') {
        let container = document.getElementById('device-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'device-toast-container';
            container.className = 'fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm pointer-events-none font-mono text-xs';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        const isSuccess = type === 'success';
        const isError = type === 'error';
        const borderCol = isSuccess ? 'border-emerald-500/60 bg-[#021810]/95 text-emerald-300' : (isError ? 'border-red-500/60 bg-[#1c0606]/95 text-red-300' : 'border-cyan-500/60 bg-[#031524]/95 text-cyan-300');
        const icon = isSuccess ? 'check_circle' : (isError ? 'error' : 'info');

        toast.className = `p-3 rounded-xl border shadow-2xl backdrop-blur-md flex items-center gap-2 pointer-events-auto transition duration-200 animate-in fade-in slide-in-from-bottom-2 ${borderCol}`;
        toast.innerHTML = `
            <span class="material-symbols-outlined text-base">${icon}</span>
            <span class="flex-1">${escapeHtml(message)}</span>
        `;
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('opacity-0', 'transition', 'duration-300');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Escuchar Escape para cerrar modales
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeviceDetailsModal();
            closeDeviceCreateModal();
            closeDeviceEditModal();
            closeCreateSiteModal();
            closeDeviceHistoryModal();
            closePeripheralModal();
            closeBulkMaintenanceModal();
            closeMaintenanceModal();
            closeUploadPhysicalScanModal();
            closeQuickAssignModal();
            if (typeof closeReconcileLdapModal === 'function') closeReconcileLdapModal();
        }
    });

    // Delegación de eventos para la paginación reactiva sin recarga de página
    document.addEventListener('DOMContentLoaded', function() {
        const paginationWrapper = document.getElementById('devices-pagination-wrapper');
        if (paginationWrapper) {
            paginationWrapper.addEventListener('click', function(e) {
                const link = e.target.closest('a');
                if (link && link.href) {
                    e.preventDefault();
                    fetchDeviceResults(link.href);
                }
            });
        }
    });
</script>
@endsection
