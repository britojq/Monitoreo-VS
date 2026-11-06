@extends('layouts.admin')

@section('page_title', 'Inventario y Gestión de Activos de TI (ITAM)')

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
    .accordion-content {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
</style>

<div class="space-y-4">
    <!-- CABECERA PRINCIPAL -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-[10.5px] font-mono font-semibold group shadow-xs">
                    <span class="material-symbols-outlined text-xs group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-[10.5px] font-mono text-obsidian-muted">Inventario de TI</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="p-2 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">
                    <span class="material-symbols-outlined text-2xl">inventory_2</span>
                </span>
                <div>
                    <h1 class="text-xl font-black text-white tracking-wide">
                        Inventario de Activos de TI
                    </h1>
                    <p class="text-xs text-obsidian-muted font-mono">Control de identidad, periféricos, historial de hardware, Asignacion de Activos y mantenimiento</p>
                </div>
            </div>
        </div>

        @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
        <div class="flex items-center gap-2">
            <!-- BOTÓN EXPORTAR EXCEL (FORMATO ATIT 2 HOJAS) -->
            <a href="{{ route('admin.reports.export', ['type' => 'census', 'format' => 'excel']) }}" class="w-9 h-9 rounded-xl bg-obsidian-panel hover:bg-cyan-950/60 border border-cyan-500/40 hover:border-cyan-400 text-cyan-300 transition flex items-center justify-center shadow-xs cursor-pointer" title="Exportar Formato Corporativo ATIT (Excel 2 Hojas: Centro de Datos &amp; Estaciones de Trabajo)">
                <span class="material-symbols-outlined text-lg text-cyan-400">table_chart</span>
            </a>

            <div class="relative group">
                <a href="{{ route('admin.inventory.assets.create') }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black transition text-xs font-mono font-bold shadow-md shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-base">add_circle</span>
                    <span class="font-bold text-sm leading-none">+</span>
                </a>
                <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                    <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs text-cyan-400">add_circle</span>
                        <span class="font-bold text-cyan-200">Nuevo Activo Manual</span>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- PESTAÑAS SUPERIORES DEL ECOSISTEMA ITAM UNIFICADO (PANEL DE NAVEGACIÓN COMPACTO) -->
    <div class="flex flex-wrap items-center gap-1.5 border-b border-obsidian-border/80 pb-2.5">
        <a href="{{ route('admin.inventory.assets.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer bg-cyan-500 text-black shadow-cyan-500/20" title="Catálogo Completo de Activos de TI">
            <span class="material-symbols-outlined text-sm">devices</span>
            <span>Catálogo de Activos</span>
        </a>
        <a href="{{ route('admin.inventory.assignments.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40" title="Asignaciones y Actas de Entrega">
            <span class="material-symbols-outlined text-sm">assignment_turned_in</span>
            <span>Asignaciones &amp; Actas</span>
        </a>
        <a href="{{ route('admin.inventory.maintenance.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40" title="Mantenimiento Técnico Preventivo y Correctivo">
            <span class="material-symbols-outlined text-sm">build</span>
            <span>Mantenimiento TI</span>
        </a>
        <a href="{{ route('admin.inventory.deploy') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40" title="Despliegue Remoto Agente GLPI">
            <span class="material-symbols-outlined text-sm">send_to_mobile</span>
            <span>Despliegue Remoto GLPI</span>
        </a>
        <a href="{{ route('admin.lifecycle.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40" title="Garantías y Ciclo de Vida">
            <span class="material-symbols-outlined text-sm">history_toggle_off</span>
            <span>Garantías &amp; Ciclo</span>
        </a>
        <a href="{{ route('admin.ups.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40" title="Monitoreo Serial de Energía & UPS">
            <span class="material-symbols-outlined text-sm">battery_charging_full</span>
            <span>Energía &amp; UPS</span>
    </div>

    <!-- TARJETAS KPIS SUPERIORES -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <!-- TOTAL ACTIVOS -->
        <a href="{{ route('admin.inventory.assets.index') }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border hover:border-cyan-500/50 transition group shadow-sm">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">Total Parque</span>
                <span class="material-symbols-outlined text-lg text-cyan-400 group-hover:scale-110 transition-transform">database</span>
            </div>
            <div class="text-2xl font-black text-white font-mono">{{ $totalAssets }}</div>
            <div class="text-[10px] font-mono text-cyan-400/80 mt-1">Equipos registrados</div>
        </a>

        <!-- ASIGNADOS / EN USO -->
        <a href="{{ route('admin.inventory.assets.index', ['status' => 'assigned']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'assigned' ? 'border-emerald-500 bg-emerald-950/10' : 'border-obsidian-border hover:border-emerald-500/50' }} transition group shadow-sm">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">Asignados</span>
                <span class="material-symbols-outlined text-lg text-emerald-400 group-hover:scale-110 transition-transform">person_check</span>
            </div>
            <div class="text-2xl font-black text-emerald-400 font-mono">{{ $assignedAssets }}</div>
            <div class="text-[10px] font-mono text-emerald-400/80 mt-1">Con responsable asignado</div>
        </a>

        <!-- EN STOCK / DISPONIBLE -->
        <a href="{{ route('admin.inventory.assets.index', ['status' => 'in_stock']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'in_stock' ? 'border-cyan-500 bg-cyan-950/10' : 'border-obsidian-border hover:border-cyan-500/50' }} transition group shadow-sm">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">En Stock</span>
                <span class="material-symbols-outlined text-lg text-cyan-400 group-hover:scale-110 transition-transform">warehouse</span>
            </div>
            <div class="text-2xl font-black text-cyan-400 font-mono">{{ $inStockAssets }}</div>
            <div class="text-[10px] font-mono text-cyan-400/80 mt-1">Disponibles para entrega</div>
        </a>

        <!-- EN MANTENIMIENTO -->
        <a href="{{ route('admin.inventory.assets.index', ['status' => 'maintenance']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'maintenance' ? 'border-amber-500 bg-amber-950/10' : 'border-obsidian-border hover:border-amber-500/50' }} transition group shadow-sm">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">Mantenimiento</span>
                <span class="material-symbols-outlined text-lg text-amber-400 group-hover:scale-110 transition-transform">build</span>
            </div>
            <div class="text-2xl font-black text-amber-400 font-mono">{{ $maintenanceAssets }}</div>
            <div class="text-[10px] font-mono text-amber-400/80 mt-1">Taller / Reparación</div>
        </a>

        <!-- DESCUBIERTOS GLPI -->
        <a href="{{ route('admin.inventory.assets.index', ['status' => 'unauthorized']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'unauthorized' ? 'border-purple-500 bg-purple-950/20' : 'border-purple-500/40 hover:border-purple-400' }} transition group shadow-sm relative overflow-hidden">
            @if($unauthorizedAssets > 0)
                <div class="absolute -top-6 -right-6 w-12 h-12 bg-purple-500/20 rounded-full blur-xl animate-pulse"></div>
            @endif
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-purple-300 uppercase font-bold flex items-center gap-1">
                    @if($unauthorizedAssets > 0)
                        <span class="w-1.5 h-1.5 rounded-full bg-purple-400 animate-ping"></span>
                    @endif
                    Descubiertos
                </span>
                <span class="material-symbols-outlined text-lg text-purple-400 group-hover:scale-110 transition-transform">radar</span>
            </div>
            <div class="text-2xl font-black text-purple-400 font-mono">{{ $unauthorizedAssets }}</div>
            <div class="text-[10px] font-mono text-purple-300/80 mt-1">Por autorizar (GLPI)</div>
        </a>
    </div>

    <!-- BARRA DE FILTROS & BÚSQUEDA -->
    <div class="p-3.5 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border">
        <form method="GET" action="{{ route('admin.inventory.assets.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
            <!-- BUSCADOR -->
            <div class="lg:col-span-4 relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-obsidian-muted text-lg">search</span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por identidad, hostname, serial, tag, IP o responsable..." 
                       class="w-full pl-9 pr-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white placeholder-obsidian-muted focus:border-cyan-500 focus:outline-none transition">
            </div>

            <!-- FILTRO DE SEDE -->
            <div class="lg:col-span-3">
                <select name="site_id" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none transition">
                    <option value="">Todas las Sedes</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" {{ (string)$siteFilter === (string)$site->id ? 'selected' : '' }}>
                            {{ $site->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- FILTRO DE TIPO -->
            <div class="lg:col-span-2">
                <select name="type" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none transition">
                    <option value="">Todos los Tipos</option>
                    <option value="desktop" {{ $typeFilter === 'desktop' ? 'selected' : '' }}>Desktop / PC</option>
                    <option value="laptop" {{ $typeFilter === 'laptop' ? 'selected' : '' }}>Laptop / Portátil</option>
                    <option value="router" {{ $typeFilter === 'router' ? 'selected' : '' }}>Router Gateway</option>
                    <option value="switch" {{ $typeFilter === 'switch' ? 'selected' : '' }}>Switch de Red</option>
                    <option value="server" {{ $typeFilter === 'server' ? 'selected' : '' }}>Servidor</option>
                    <option value="printer" {{ $typeFilter === 'printer' ? 'selected' : '' }}>Impresora</option>
                    <option value="ip_phone" {{ $typeFilter === 'ip_phone' ? 'selected' : '' }}>Teléfono IP</option>
                </select>
            </div>

            <!-- FILTRO DE ESTADO -->
            <div class="lg:col-span-2">
                <select name="status" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none transition">
                    <option value="">Todos los Estados</option>
                    <option value="in_stock" {{ $statusFilter === 'in_stock' ? 'selected' : '' }}>En Stock (Disponible)</option>
                    <option value="assigned" {{ $statusFilter === 'assigned' ? 'selected' : '' }}>Asignado (En Uso)</option>
                    <option value="maintenance" {{ $statusFilter === 'maintenance' ? 'selected' : '' }}>En Mantenimiento</option>
                    <option value="unauthorized" {{ $statusFilter === 'unauthorized' ? 'selected' : '' }}>Descubierto (GLPI)</option>
                    <option value="damaged" {{ $statusFilter === 'damaged' ? 'selected' : '' }}>Dañado / Para Baja</option>
                    <option value="decommissioned" {{ $statusFilter === 'decommissioned' ? 'selected' : '' }}>Desincorporado</option>
                </select>
            </div>

            <!-- BOTONES ACCIÓN -->
            <div class="lg:col-span-1 flex items-center gap-1.5">
                <button type="submit" class="w-full py-2 px-3 bg-cyan-500/20 border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black rounded-xl text-xs font-mono font-bold transition flex items-center justify-center gap-1" title="Aplicar Filtros">
                    <span class="material-symbols-outlined text-base">filter_alt</span>
                </button>
                @if($search || $statusFilter || $typeFilter || $siteFilter)
                <a href="{{ route('admin.inventory.assets.index') }}" class="p-2 bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white rounded-xl transition" title="Limpiar Filtros">
                    <span class="material-symbols-outlined text-base">close</span>
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- TABLA DE ACTIVOS UNIFICADA (7 COLUMNAS CON ACORDEÓN DESPLEGABLE) -->
    <div class="rounded-2xl bg-obsidian-panel/90 border border-obsidian-border overflow-hidden shadow-xl">
        <div class="custom-table-scroll">
            <table class="w-full text-left border-collapse min-w-[1150px]">
                <thead>
                    <tr class="border-b border-obsidian-border bg-[#040b15]/95 text-[11px] font-mono text-obsidian-muted uppercase tracking-wider">
                        <th class="py-3 px-3 w-10 text-center">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="w-4 h-4 rounded border-obsidian-border bg-[#040b15] text-cyan-500 focus:ring-0 cursor-pointer" title="Seleccionar todos los activos de la página">
                        </th>
                        <th class="py-3 px-4">Equipo / Identidad</th>
                        <th class="py-3 px-3">Tipo &amp; S.O.</th>
                        <th class="py-3 px-3">Sede &amp; Red</th>
                        <th class="py-3 px-3">Tiene Asignación</th>
                        <th class="py-3 px-3">Mantenimiento</th>
                        <th class="py-3 px-3">Estado</th>
                        <th class="py-3 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/50 text-xs font-mono">
                    @forelse($assets as $asset)
                    @php
                        $isPc = $asset->is_pc;
                        $cpu = $asset->components->firstWhere('type', 'cpu');
                        $rams = $asset->components->where('type', 'ram');
                        $disks = $asset->components->where('type', 'disk');
                        $monitor = $asset->peripherals->where('type', 'monitor')->first();
                        $keyboard = $asset->peripherals->where('type', 'keyboard')->first();
                        $mouse = $asset->peripherals->where('type', 'mouse')->first();
                        $protector = $asset->peripherals->whereIn('type', ['surge_protector', 'ups'])->first();
                        $activeAssignment = $asset->assignments->firstWhere('status', 'active') ?? $asset->assignments->first();
                        $activeMaint = $asset->maintenanceTickets->whereIn('status', ['scheduled', 'in_progress', 'pending_parts'])->first();
                        $maintCount = $asset->maintenanceTickets->count();
                    @endphp

                    <!-- FILA PRINCIPAL DEL ACTIVO (CLICKEABLE PARA DESPLEGAR) -->
                    <tr id="asset-row-{{ $asset->id }}" class="hover:bg-cyan-500/[0.03] transition-colors group">
                        <!-- CHECKBOX DE SELECCIÓN MASIVA -->
                        <td class="py-3 px-3 text-center" onclick="event.stopPropagation()">
                            <input type="checkbox" class="asset-checkbox w-4 h-4 rounded border-obsidian-border bg-[#040b15] text-cyan-500 focus:ring-0 cursor-pointer" 
                                   value="{{ $asset->id }}" 
                                   data-id="{{ $asset->id }}"
                                   data-name="{{ $asset->display_identity }}"
                                   data-site-id="{{ $asset->monitored_site_id }}"
                                   data-site-name="{{ $asset->monitoredSite?->name ?? 'Sin Sede' }}"
                                   onchange="onAssetCheckChanged()">
                        </td>

                        <!-- COLUMNA 1: EQUIPO / IDENTIDAD -->
                        <td class="py-3 px-4 cursor-pointer select-none" onclick="toggleAssetAccordion({{ $asset->id }}, event)">
                            <div class="flex items-center gap-2.5">
                                <!-- BOTÓN CHEVRON INDICADOR DE DESPLIEGUE -->
                                <button type="button" class="p-1 rounded-lg bg-obsidian-panel/80 hover:bg-cyan-500/20 text-obsidian-muted hover:text-cyan-300 transition shrink-0" title="Desplegar especificaciones">
                                    <span id="chevron-{{ $asset->id }}" class="material-symbols-outlined text-base transition-transform duration-200">expand_more</span>
                                </button>

                                <!-- ICONO DEL DISPOSITIVO -->
                                <span class="p-2 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 group-hover:scale-105 transition-transform shrink-0">
                                    <span class="material-symbols-outlined text-lg">{{ $asset->type_icon }}</span>
                                </span>

                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-white group-hover:text-cyan-300 transition text-sm">
                                            {{ $asset->display_identity }}
                                        </span>
                                        @if($asset->monitored_network_device_id)
                                            <span class="px-1.5 py-0.2 text-[9px] font-mono rounded bg-emerald-950/80 text-emerald-300 border border-emerald-800/80 flex items-center gap-0.5" title="Vinculado al motor de monitoreo activo">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                Monitoreado
                                            </span>
                                        @endif
                                        @if($asset->asset_tag)
                                            <span class="px-1.5 py-0.2 text-[9px] rounded bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                {{ $asset->asset_tag }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-obsidian-muted flex items-center gap-1.5 flex-wrap mt-0.5">
                                        @if($asset->technical_subtitle)
                                            <span class="text-slate-300 font-mono">PC: <code>{{ $asset->technical_subtitle }}</code></span>
                                            <span>•</span>
                                        @endif
                                        <span>{{ $asset->brand ?? 'Marca Genérica' }} {{ $asset->model ?? '' }}</span>
                                        @if($asset->serial_number)
                                            <span>•</span>
                                            <span class="text-slate-400">S/N: <code>{{ $asset->serial_number }}</code></span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- COLUMNA 2: TIPO & S.O. -->
                        <td class="py-3 px-3 cursor-pointer" onclick="toggleAssetAccordion({{ $asset->id }}, event)">
                            <div class="space-y-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-800/90 text-slate-300 border border-slate-700 inline-block">
                                    {{ $asset->type }}
                                </span>
                                @if($asset->operating_system)
                                    <div class="text-[10px] text-slate-400 truncate max-w-[140px]" title="{{ $asset->operating_system }}">
                                        {{ $asset->operating_system }}
                                    </div>
                                @elseif($cpu)
                                    <div class="text-[10px] text-slate-400 truncate max-w-[140px]" title="{{ $cpu->name }}">
                                        {{ $cpu->name }}
                                    </div>
                                @else
                                    <div class="text-[10px] text-obsidian-muted italic">Infraestructura</div>
                                @endif
                            </div>
                        </td>

                        <!-- COLUMNA 3: SEDE & RED -->
                        <td class="py-3 px-3 cursor-pointer" onclick="toggleAssetAccordion({{ $asset->id }}, event)">
                            <div class="space-y-0.5">
                                @if($asset->monitoredSite)
                                    <div class="text-white font-medium flex items-center gap-1 text-xs">
                                        <span class="material-symbols-outlined text-xs text-cyan-400">location_on</span>
                                        <span class="truncate max-w-[130px]" title="{{ $asset->monitoredSite->name }}">{{ $asset->monitoredSite->name }}</span>
                                    </div>
                                @else
                                    <span class="text-obsidian-muted italic text-[11px]">Sin sede física</span>
                                @endif

                                @if($asset->ip_address)
                                    <div class="text-cyan-300 font-mono text-[11px] flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[11px] text-cyan-500">lan</span>
                                        <span>{{ $asset->ip_address }}</span>
                                    </div>
                                @endif
                                @if($asset->mac_address)
                                    <div class="text-[9.5px] text-obsidian-muted/80 font-mono">{{ $asset->mac_address }}</div>
                                @endif
                            </div>
                        </td>

                        <!-- COLUMNA 4: TIENE ASIGNACIÓN -->
                        <td class="py-3 px-3" onclick="event.stopPropagation()">
                            @if($activeAssignment)
                                <div class="space-y-1">
                                    <div class="text-white font-medium flex items-center gap-1 text-xs">
                                        <span class="material-symbols-outlined text-xs text-emerald-400">person</span>
                                        <span class="truncate max-w-[125px]" title="Responsable: {{ $activeAssignment->custodian_name }} ({{ $activeAssignment->custodian_department ?? 'Sin Dpto' }})">
                                            {{ $activeAssignment->custodian_name }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <!-- Badge de Soporte Digital -->
                                        <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-bold {{ $activeAssignment->scan_compliance_badge_class }}" title="Soporte Digital de Asignación">
                                            {{ $activeAssignment->scan_compliance_label }}
                                        </span>

                                        @if($asset->has_outdated_assignment && (auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage')))
                                            <a href="{{ route('admin.inventory.assignments.create', ['asset_id' => $asset->id]) }}" 
                                               class="p-1 rounded bg-amber-500/20 border border-amber-500/50 text-amber-300 hover:bg-amber-500 hover:text-black transition animate-pulse" 
                                               title="Alerta de Auditoría: Hardware modificado posterior al acta. Clic para renovar planilla UINF-4.05">
                                                <span class="material-symbols-outlined text-[13px]">warning</span>
                                            </a>
                                        @endif

                                        @if($activeAssignment->document_pdf_path)
                                            <!-- Ver Hoja Física Digitalizada (PDF / Imagen) -->
                                            <a href="{{ route('admin.inventory.assignments.view-scan', $activeAssignment->id) }}" target="_blank" 
                                               class="p-1 rounded bg-emerald-950/70 border border-emerald-500/60 text-emerald-300 hover:bg-emerald-500 hover:text-black transition" 
                                               title="Documento Físico Escaneado: Clic para abrir y ver hoja digitalizada">
                                                <span class="material-symbols-outlined text-[13px]">file_present</span>
                                            </a>
                                        @else
                                            <!-- Subir Hoja Física Escaneada si falta -->
                                            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                                            <button type="button" 
                                                    onclick="openUploadPhysicalScanModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}')"
                                                    class="p-1 rounded bg-amber-950/50 border border-amber-600/60 text-amber-300 hover:bg-amber-500 hover:text-black transition" 
                                                    title="Pendiente de hoja física: Clic para subir documento escaneado">
                                                <span class="material-symbols-outlined text-[13px]">upload_file</span>
                                            </button>
                                            @endif
                                        @endif

                                        <!-- Descargar Acta Digital UINF-4.05 generada por sistema -->
                                        <a href="{{ route('admin.inventory.assignments.pdf', $activeAssignment->id) }}" target="_blank" 
                                            class="p-1 rounded bg-red-950/40 border border-red-800/60 text-red-400 hover:bg-red-500 hover:text-white transition" 
                                            title="Descargar Acta UINF-4.05 (PDF): {{ $activeAssignment->assignment_code }}">
                                            <span class="material-symbols-outlined text-[13px]">picture_as_pdf</span>
                                        </a>

                                        <!-- Ver Ficha de Detalle de Asignación / Movilización -->
                                        <a href="{{ route('admin.inventory.assignments.show', $activeAssignment->id) }}" 
                                            class="p-1 rounded bg-cyan-950/60 border border-cyan-800/60 text-cyan-300 hover:bg-cyan-500 hover:text-black transition" 
                                            title="Ver Ficha de Asignación / Pase de Movilización: {{ $activeAssignment->assignment_code }}">
                                            <span class="material-symbols-outlined text-[13px]">badge</span>
                                        </a>
                                    </div>
                                </div>
                            @elseif($asset->custodian_name)
                                <div class="space-y-1">
                                    <div class="text-white font-medium flex items-center gap-1 text-xs">
                                        <span class="material-symbols-outlined text-xs text-slate-400">person</span>
                                        <span class="truncate max-w-[125px]" title="Responsable: {{ $asset->custodian_name }}">{{ $asset->custodian_name }}</span>
                                    </div>
                                    @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                                    <div class="flex items-center gap-1.5">
                                        <button type="button" 
                                                onclick="openUploadPhysicalScanModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', '{{ addslashes($asset->custodian_name) }}', '{{ addslashes($asset->custodian_id_number ?? '') }}', '{{ addslashes($asset->custodian_department ?? '') }}')"
                                                class="p-1 rounded bg-amber-500/20 border border-amber-500/40 text-amber-300 hover:bg-amber-500 hover:text-black transition" 
                                                title="Subir Hoja Física Escaneada de {{ $asset->custodian_name }}">
                                            <span class="material-symbols-outlined text-[13px]">upload_file</span>
                                        </button>
                                        <a href="{{ route('admin.inventory.assignments.create', ['asset_id' => $asset->id, 'return_to' => url()->full()]) }}" 
                                            class="p-1 rounded bg-cyan-500/20 border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black transition" 
                                            title="Generar Acta Digital UINF-4.05 en Sistema">
                                            <span class="material-symbols-outlined text-[13px]">assignment_add</span>
                                        </a>
                                    </div>
                                    @endif
                                </div>
                            @else
                                <!-- ESTADO: SIN ASIGNAR (Solo iconos con tooltip interactivo) -->
                                <div class="flex items-center gap-1.5" title="Estado: Sin Asignar">
                                    <span class="p-1 rounded bg-slate-800/80 border border-slate-700/60 text-obsidian-muted" title="Estado: Sin Asignar">
                                        <span class="material-symbols-outlined text-[14px]">person_off</span>
                                    </span>
                                    @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                                        <button type="button" 
                                                onclick="openUploadPhysicalScanModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}')" 
                                                class="p-1 rounded bg-amber-500/15 border border-amber-500/30 text-amber-400 hover:bg-amber-500 hover:text-black transition" 
                                                title="Sin Asignar: Cargar Hoja Física de Asignación Escaneada">
                                            <span class="material-symbols-outlined text-[14px]">upload_file</span>
                                        </button>
                                        <a href="{{ route('admin.inventory.assignments.create', ['asset_id' => $asset->id, 'return_to' => url()->full()]) }}" 
                                           class="p-1 rounded bg-cyan-500/15 border border-cyan-500/30 text-cyan-400 hover:bg-cyan-500 hover:text-black transition" 
                                           title="Sin Asignar: Emitir Nueva Acta UINF-4.05 en Sistema">
                                            <span class="material-symbols-outlined text-[14px]">assignment_add</span>
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </td>

                        <!-- COLUMNA 5: MANTENIMIENTO -->
                        <td class="py-3 px-3" onclick="event.stopPropagation()">
                            <div class="flex items-center gap-1.5">
                                @if($activeMaint)
                                    <button type="button" onclick="openMaintenanceModal({{ $asset->id }})" 
                                            class="p-1 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-500/40 hover:bg-amber-500 hover:text-black transition flex items-center gap-1" 
                                            title="Mantenimiento en curso ({{ $activeMaint->status === 'scheduled' ? 'Programado' : 'En Curso' }}): {{ $activeMaint->title }} [{{ $activeMaint->scheduled_at ? $activeMaint->scheduled_at->format('d/m/Y') : '' }}]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                        <span class="material-symbols-outlined text-[14px]">engineering</span>
                                    </button>
                                @else
                                    <span class="p-1 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400" 
                                          title="Mantenimiento al día{{ $maintCount > 0 ? " ({$maintCount} órdenes previas)" : '' }}">
                                        <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                    </span>
                                    @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                    <button type="button" onclick="openSingleMaintenanceModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}')" 
                                            class="p-1 rounded bg-amber-500/15 border border-amber-500/30 text-amber-400 hover:bg-amber-500 hover:text-black transition" 
                                            title="Planificar Mantenimiento Preventivo o Correctivo">
                                        <span class="material-symbols-outlined text-[14px]">calendar_add_on</span>
                                    </button>
                                    @endif
                                @endif

                                @if($maintCount > 0)
                                    <button type="button" onclick="openMaintenanceModal({{ $asset->id }})" 
                                            class="p-1 rounded bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 hover:bg-cyan-500 hover:text-black transition" 
                                            title="Ver Historial de Mantenimientos ({{ $maintCount }} órdenes)">
                                        <span class="material-symbols-outlined text-[14px]">history</span>
                                    </button>
                                @endif
                            </div>
                        </td>

                        <!-- COLUMNA 6: ESTADO -->
                        <td class="py-3 px-3 cursor-pointer" onclick="toggleAssetAccordion({{ $asset->id }}, event)">
                            @if($asset->status === 'assigned')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-1 w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    Asignado
                                </span>
                            @elseif($asset->status === 'in_stock')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 flex items-center gap-1 w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                                    En Stock
                                </span>
                            @elseif($asset->status === 'unauthorized')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/40 flex items-center gap-1 w-fit animate-pulse" title="Descubierto por agente GLPI. Requiere aprobación.">
                                    <span class="w-1.5 h-1.5 rounded-full bg-purple-400"></span>
                                    Descubierto
                                </span>
                            @elseif($asset->status === 'maintenance')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 flex items-center gap-1 w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                    Mantenimiento
                                </span>
                            @elseif($asset->status === 'damaged')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-500/20 text-red-300 border border-red-500/40 flex items-center gap-1 w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                    Dañado
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-500/20 text-slate-300 border border-slate-500/40 flex items-center gap-1 w-fit">
                                    {{ $asset->status_label }}
                                </span>
                            @endif
                        </td>

                        <!-- COLUMNA 7: ACCIONES -->
                        <td class="py-3 px-4 text-right" onclick="event.stopPropagation()">
                            <div class="flex items-center justify-end gap-1.5">
                                @if($asset->status === 'unauthorized' && (auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage')))
                                <!-- BOTÓN APROBACIÓN DIRECTA -->
                                <button type="button" onclick="openApproveModal({{ $asset->id }}, '{{ addslashes($asset->name) }}', '{{ addslashes($asset->serial_number ?? '') }}')" 
                                        class="px-2 py-1 rounded-lg bg-purple-500/20 border border-purple-500/40 text-purple-300 hover:bg-purple-500 hover:text-black font-bold text-[10px] transition flex items-center gap-1 shadow-sm" title="Aprobar e incorporar formalmente">
                                    <span class="material-symbols-outlined text-sm">verified</span>
                                    <span>Aprobar</span>
                                </button>
                                @endif

                                <!-- BOTÓN DESPLEGAR / ACORDEÓN -->
                                <button type="button" onclick="toggleAssetAccordion({{ $asset->id }}, event)" class="p-1.5 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 hover:bg-cyan-500 hover:text-black transition" title="Ver Detalle Desplegable">
                                    <span class="material-symbols-outlined text-base">unfold_more</span>
                                </button>

                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                <!-- BOTÓN EDITAR (INCLUYE ASOCIAR PERIFÉRICOS) -->
                                <a href="{{ route('admin.inventory.assets.edit', $asset->id) }}" class="p-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 hover:text-white hover:border-slate-500 transition" title="Editar Activo y Periféricos">
                                    <span class="material-symbols-outlined text-base">edit</span>
                                </a>

                                <!-- BOTÓN ELIMINAR -->
                                <form action="{{ route('admin.inventory.assets.destroy', $asset->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Está seguro de desincorporar o eliminar el activo {{ $asset->display_identity }}? Esta acción no se puede deshacer.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg bg-red-950/30 border border-red-800/40 text-red-400 hover:bg-red-500 hover:text-white transition" title="Eliminar Activo">
                                        <span class="material-symbols-outlined text-base">delete</span>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>

                    <!-- FILA EXPANDIBLE (ACORDEÓN DESPLEGABLE TIPO SEDES REGIONALES) -->
                    <tr id="accordion-row-{{ $asset->id }}" class="hidden bg-[#030812] border-b border-cyan-500/30">
                        <td colspan="8" class="p-4 sm:p-5 space-y-4">
                            @if($isPc)
                                <!-- CASO PC: ESPECIFICACIONES DE HARDWARE, 4 PERIFÉRICOS DE PUESTO E HISTÓRICO DE CAMBIOS -->
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
                                                        <span class="text-white font-medium text-right truncate max-w-[155px]" title="{{ $asset->brand ?: 'Estándar' }}">
                                                            {{ $asset->brand ?: 'Estándar / Genérico' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Modelo:</span>
                                                        <span class="text-cyan-300 font-semibold text-right truncate max-w-[155px]" title="{{ $asset->model ?: 'Sin modelo' }}">
                                                            {{ $asset->model ?: 'Sin modelo' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Serial:</span>
                                                        <span class="text-amber-300 font-mono font-medium text-right truncate max-w-[155px]" title="{{ $asset->serial_number ?: 'Sin Serial' }}">
                                                            {{ $asset->serial_number ?: 'Sin Serial' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Etiqueta:</span>
                                                        <span class="text-slate-300 text-right truncate max-w-[155px]">
                                                            #{{ $asset->device_number ?: $asset->id }} • {{ $asset->asset_tag ?: 'S/TAG' }}
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
                                                        <span class="text-white text-right truncate max-w-[155px]" title="{{ $asset->operating_system ?? 'Linux / Windows' }}">
                                                            {{ $asset->operating_system ?? 'Linux / Windows' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">Red / IP:</span>
                                                        <span class="text-cyan-300 font-mono text-right truncate max-w-[155px]">
                                                            {{ $asset->ip_address ?? 'N/A' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-1">
                                                        <span class="text-obsidian-muted shrink-0">MAC:</span>
                                                        <span class="text-slate-400 font-mono text-right text-[10px] truncate max-w-[155px]" title="Dirección MAC: {{ $asset->mac_address ?: 'No asignada' }}">
                                                            {{ $asset->mac_address ?: 'No asignada' }}
                                                        </span>
                                                    </div>
                                                    @if($asset->networkInterfaces->count() > 1)
                                                    <div class="flex items-baseline justify-between gap-1 pt-1 border-t border-obsidian-border/40">
                                                        <span class="text-obsidian-muted shrink-0">Interfaces:</span>
                                                        <span class="text-emerald-400 font-medium text-right text-[10.5px] cursor-help" title="{{ $asset->networkInterfaces->map(fn($n) => $n->interface_name . ($n->is_primary ? ' [Principal]' : '') . ': ' . ($n->ip_address ?: 'sin IP') . ' (' . ($n->mac_address ?: 'S/MAC') . ')')->implode(' | ') }}">
                                                            {{ $asset->networkInterfaces->count() }} NICs configuradas
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

                                    <!-- CUADRO DE 4 PERIFÉRICOS ASOCIADOS AL PUESTO DE TRABAJO -->
                                    <div class="space-y-2 mb-4">
                                        <div class="flex items-center justify-between">
                                            <h4 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-base">desktop_mac</span>
                                                Periféricos de Puesto de Trabajo (Monitor, Teclado, Mouse, Regulador)
                                            </h4>
                                            <span class="text-[10px] text-obsidian-muted font-mono">Dispositivos vinculados a esta estación</span>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                            <!-- 1. MONITOR -->
                                            <div class="p-3 rounded-xl border {{ $monitor ? 'bg-[#060e1c] border-cyan-500/40' : 'bg-[#040914] border-dashed border-obsidian-border' }} relative transition">
                                                <div class="flex items-center justify-between mb-2">
                                                    <div class="flex items-center gap-1.5 text-xs font-bold {{ $monitor ? 'text-cyan-300' : 'text-obsidian-muted' }}">
                                                        <span class="material-symbols-outlined text-base">monitor</span>
                                                        <span>Monitor</span>
                                                    </div>
                                                    @if($monitor)
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-800">
                                                            {{ $monitor->status_label }}
                                                        </span>
                                                    @endif
                                                </div>
                                                @if($monitor)
                                                    <div class="space-y-1 text-xs font-mono">
                                                        <div class="text-white font-semibold truncate">{{ $monitor->brand }} {{ $monitor->model }}</div>
                                                        <div class="text-[10px] text-slate-400 truncate">S/N: <code>{{ $monitor->serial_number ?: 'Sin Serial' }}</code></div>
                                                        <div class="text-[9.5px] text-obsidian-muted truncate">Tag: {{ $monitor->asset_tag }}</div>
                                                    </div>
                                                    <div class="mt-2.5 pt-2 border-t border-obsidian-border/50 flex items-center justify-between">
                                                        <button type="button" onclick="openPeripheralModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', 'monitor', '{{ addslashes($monitor->brand ?? '') }}', '{{ addslashes($monitor->model ?? '') }}', '{{ addslashes($monitor->serial_number ?? '') }}')" 
                                                                class="text-[10px] text-cyan-400 hover:underline font-mono flex items-center gap-0.5">
                                                            <span class="material-symbols-outlined text-xs">edit</span>
                                                            <span>Cambiar</span>
                                                        </button>
                                                        <form action="{{ route('admin.inventory.assets.peripherals.destroy', ['id' => $asset->id, 'peripheralId' => $monitor->id]) }}" method="POST" class="inline" onsubmit="return confirm('¿Desvincular monitor del equipo?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-[10px] text-red-400 hover:underline font-mono">Quitar</button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <div class="text-center py-2 text-obsidian-muted text-xs">
                                                        <p class="text-[10.5px] italic mb-2">Sin monitor asociado</p>
                                                        <button type="button" onclick="openPeripheralModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', 'monitor')" 
                                                                class="px-2.5 py-1 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 hover:bg-cyan-500 hover:text-black font-bold text-[10px] transition inline-flex items-center gap-1">
                                                            <span class="material-symbols-outlined text-xs">add</span>
                                                            <span>Asociar Monitor</span>
                                                        </button>
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
                                                    @endif
                                                </div>
                                                @if($keyboard)
                                                    <div class="space-y-1 text-xs font-mono">
                                                        <div class="text-white font-semibold truncate">{{ $keyboard->brand }} {{ $keyboard->model ?: 'Estándar USB' }}</div>
                                                        <div class="text-[10px] text-slate-400 truncate">S/N: <code>{{ $keyboard->serial_number ?: 'Sin Serial' }}</code></div>
                                                        <div class="text-[9.5px] text-obsidian-muted truncate">Tag: {{ $keyboard->asset_tag }}</div>
                                                    </div>
                                                    <div class="mt-2.5 pt-2 border-t border-obsidian-border/50 flex items-center justify-between">
                                                        <button type="button" onclick="openPeripheralModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', 'keyboard', '{{ addslashes($keyboard->brand ?? '') }}', '{{ addslashes($keyboard->model ?? '') }}', '{{ addslashes($keyboard->serial_number ?? '') }}')" 
                                                                class="text-[10px] text-cyan-400 hover:underline font-mono flex items-center gap-0.5">
                                                            <span class="material-symbols-outlined text-xs">edit</span>
                                                            <span>Cambiar</span>
                                                        </button>
                                                        <form action="{{ route('admin.inventory.assets.peripherals.destroy', ['id' => $asset->id, 'peripheralId' => $keyboard->id]) }}" method="POST" class="inline" onsubmit="return confirm('¿Desvincular teclado del equipo?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-[10px] text-red-400 hover:underline font-mono">Quitar</button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <div class="text-center py-2 text-obsidian-muted text-xs">
                                                        <p class="text-[10.5px] italic mb-2">Sin teclado asociado</p>
                                                        <button type="button" onclick="openPeripheralModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', 'keyboard')" 
                                                                class="px-2.5 py-1 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 hover:bg-cyan-500 hover:text-black font-bold text-[10px] transition inline-flex items-center gap-1">
                                                            <span class="material-symbols-outlined text-xs">add</span>
                                                            <span>Asociar Teclado</span>
                                                        </button>
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
                                                    @endif
                                                </div>
                                                @if($mouse)
                                                    <div class="space-y-1 text-xs font-mono">
                                                        <div class="text-white font-semibold truncate">{{ $mouse->brand }} {{ $mouse->model ?: 'Óptico USB' }}</div>
                                                        <div class="text-[10px] text-slate-400 truncate">S/N: <code>{{ $mouse->serial_number ?: 'Sin Serial' }}</code></div>
                                                        <div class="text-[9.5px] text-obsidian-muted truncate">Tag: {{ $mouse->asset_tag }}</div>
                                                    </div>
                                                    <div class="mt-2.5 pt-2 border-t border-obsidian-border/50 flex items-center justify-between">
                                                        <button type="button" onclick="openPeripheralModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', 'mouse', '{{ addslashes($mouse->brand ?? '') }}', '{{ addslashes($mouse->model ?? '') }}', '{{ addslashes($mouse->serial_number ?? '') }}')" 
                                                                class="text-[10px] text-cyan-400 hover:underline font-mono flex items-center gap-0.5">
                                                            <span class="material-symbols-outlined text-xs">edit</span>
                                                            <span>Cambiar</span>
                                                        </button>
                                                        <form action="{{ route('admin.inventory.assets.peripherals.destroy', ['id' => $asset->id, 'peripheralId' => $mouse->id]) }}" method="POST" class="inline" onsubmit="return confirm('¿Desvincular mouse del equipo?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-[10px] text-red-400 hover:underline font-mono">Quitar</button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <div class="text-center py-2 text-obsidian-muted text-xs">
                                                        <p class="text-[10.5px] italic mb-2">Sin mouse asociado</p>
                                                        <button type="button" onclick="openPeripheralModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', 'mouse')" 
                                                                class="px-2.5 py-1 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 hover:bg-cyan-500 hover:text-black font-bold text-[10px] transition inline-flex items-center gap-1">
                                                            <span class="material-symbols-outlined text-xs">add</span>
                                                            <span>Asociar Mouse</span>
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- 4. PROTECTOR DE VOLTAJE / UPS -->
                                            <div class="p-3 rounded-xl border {{ $protector ? 'bg-[#060e1c] border-cyan-500/40' : 'bg-[#040914] border-dashed border-obsidian-border' }} relative transition">
                                                <div class="flex items-center justify-between mb-2">
                                                    <div class="flex items-center gap-1.5 text-xs font-bold {{ $protector ? 'text-cyan-300' : 'text-obsidian-muted' }}">
                                                        <span class="material-symbols-outlined text-base">bolt</span>
                                                        <span>Protector / UPS</span>
                                                    </div>
                                                    @if($protector)
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-800">
                                                            {{ $protector->status_label }}
                                                        </span>
                                                    @endif
                                                </div>
                                                @if($protector)
                                                    <div class="space-y-1 text-xs font-mono">
                                                        <div class="text-white font-semibold truncate">{{ $protector->brand }} {{ $protector->model ?: 'Regulador de Voltaje' }}</div>
                                                        <div class="text-[10px] text-slate-400 truncate">S/N: <code>{{ $protector->serial_number ?: 'Sin Serial' }}</code></div>
                                                        <div class="text-[9.5px] text-obsidian-muted truncate">Tag: {{ $protector->asset_tag }}</div>
                                                    </div>
                                                    <div class="mt-2.5 pt-2 border-t border-obsidian-border/50 flex items-center justify-between">
                                                        <button type="button" onclick="openPeripheralModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', 'surge_protector', '{{ addslashes($protector->brand ?? '') }}', '{{ addslashes($protector->model ?? '') }}', '{{ addslashes($protector->serial_number ?? '') }}')" 
                                                                class="text-[10px] text-cyan-400 hover:underline font-mono flex items-center gap-0.5">
                                                            <span class="material-symbols-outlined text-xs">edit</span>
                                                            <span>Cambiar</span>
                                                        </button>
                                                        <form action="{{ route('admin.inventory.assets.peripherals.destroy', ['id' => $asset->id, 'peripheralId' => $protector->id]) }}" method="POST" class="inline" onsubmit="return confirm('¿Desvincular protector del equipo?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-[10px] text-red-400 hover:underline font-mono">Quitar</button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <div class="text-center py-2 text-obsidian-muted text-xs">
                                                        <p class="text-[10.5px] italic mb-2">Sin protector asociado</p>
                                                        <button type="button" onclick="openPeripheralModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', 'surge_protector')" 
                                                                class="px-2.5 py-1 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 hover:bg-cyan-500 hover:text-black font-bold text-[10px] transition inline-flex items-center gap-1">
                                                            <span class="material-symbols-outlined text-xs">add</span>
                                                            <span>Asociar Protector</span>
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- RESGUARDO DE RESPONSABLE Y HOJA FÍSICA DIGITALIZADA -->
                                    <div class="p-3.5 rounded-xl border {{ $activeAssignment && $activeAssignment->document_pdf_path ? 'bg-[#051410] border-emerald-500/40' : ($activeAssignment ? 'bg-[#151205] border-amber-500/40' : 'bg-[#040914] border-obsidian-border') }} flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs font-mono mb-4">
                                        <div class="flex items-start gap-3">
                                            <span class="p-2 rounded-lg {{ $activeAssignment && $activeAssignment->document_pdf_path ? 'bg-emerald-500/20 text-emerald-400' : ($activeAssignment ? 'bg-amber-500/20 text-amber-400' : 'bg-slate-800 text-slate-400') }}">
                                                <span class="material-symbols-outlined text-xl">
                                                    {{ $activeAssignment && $activeAssignment->document_pdf_path ? 'verified_user' : ($activeAssignment ? 'assignment_late' : 'person_off') }}
                                                </span>
                                            </span>
                                            <div>
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-bold uppercase tracking-wider {{ $activeAssignment && $activeAssignment->document_pdf_path ? 'text-emerald-300' : ($activeAssignment ? 'text-amber-300' : 'text-slate-300') }}">
                                                        @if($activeAssignment && $activeAssignment->document_pdf_path)
                                                            Hoja de Asignación Física Digitalizada (Resguardada)
                                                        @elseif($activeAssignment)
                                                            Asignación Activa en Sistema (Pendiente de Hoja Física Escaneada)
                                                        @else
                                                            Activo sin Asignación ni Documento Registrado
                                                        @endif
                                                    </span>
                                                    @if($activeAssignment)
                                                        <span class="px-2 py-0.2 rounded text-[9.5px] font-mono font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                            {{ $activeAssignment->assignment_code }}
                                                        </span>
                                                        <span class="px-2 py-0.2 rounded text-[9.5px] font-mono font-bold {{ $activeAssignment->scan_compliance_badge_class }}">
                                                            {{ $activeAssignment->scan_compliance_label }}
                                                        </span>
                                                    @endif
                                                    @if($asset->has_outdated_assignment)
                                                        <a href="{{ route('admin.inventory.assignments.create', ['asset_id' => $asset->id]) }}" 
                                                           class="px-2 py-0.2 rounded text-[9.5px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/50 hover:bg-amber-500 hover:text-black transition animate-pulse" 
                                                           title="Hardware modificado posterior al acta. Emitir nueva planilla UINF-4.05">
                                                            ⚠️ Renovar Acta
                                                        </a>
                                                    @endif
                                                </div>
                                                @if($activeAssignment)
                                                    <div class="text-slate-300 mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px]">
                                                        <span>Responsable: <strong class="text-white">{{ $activeAssignment->custodian_name }}</strong></span>
                                                        @if($activeAssignment->custodian_id_number)
                                                            <span>• C.I: <span class="text-slate-400">{{ $activeAssignment->custodian_id_number }}</span></span>
                                                        @endif
                                                        @if($activeAssignment->custodian_department)
                                                            <span>• Dpto: <span class="text-slate-400">{{ $activeAssignment->custodian_department }}</span></span>
                                                        @endif
                                                        <span>• Fecha: <span class="text-cyan-400">{{ $activeAssignment->assigned_at ? $activeAssignment->assigned_at->format('d/m/Y') : 'N/A' }}</span></span>
                                                    </div>
                                                @else
                                                    <p class="text-[11px] text-obsidian-muted mt-0.5">
                                                        Este equipo se encuentra en inventario disponible y no posee responsable registrado.
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
                                                        onclick="openUploadPhysicalScanModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}')"
                                                        class="px-2.5 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition flex items-center gap-1 text-xs" title="Reemplazar archivo digitalizado">
                                                    <span class="material-symbols-outlined text-base">sync</span>
                                                    <span>Reemplazar</span>
                                                </button>
                                                @endif
                                            @elseif($activeAssignment)
                                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                                <button type="button" 
                                                        onclick="openUploadPhysicalScanModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}')"
                                                        class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold transition flex items-center gap-1.5 text-xs shadow-sm">
                                                    <span class="material-symbols-outlined text-base">upload_file</span>
                                                    <span>Subir Hoja Física Escaneada</span>
                                                </button>
                                                @endif
                                                <a href="{{ route('admin.inventory.assignments.pdf', $activeAssignment->id) }}" target="_blank" 
                                                   class="px-2.5 py-1.5 rounded-xl bg-red-950/40 border border-red-800/60 text-red-400 hover:bg-red-500 hover:text-white transition flex items-center gap-1 text-xs">
                                                    <span class="material-symbols-outlined text-base">picture_as_pdf</span>
                                                    <span>Acta Digital UINF-4.05</span>
                                                </a>
                                            @else
                                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                                <button type="button" 
                                                        onclick="openUploadPhysicalScanModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}')"
                                                        class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold transition flex items-center gap-1.5 text-xs shadow-sm">
                                                    <span class="material-symbols-outlined text-base">upload_file</span>
                                                    <span>Subir Hoja Física Existente</span>
                                                </button>
                                                <a href="{{ route('admin.inventory.assignments.create', ['asset_id' => $asset->id, 'return_to' => url()->full()]) }}" 
                                                   class="px-3 py-1.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500 border border-cyan-500/40 text-cyan-300 hover:text-black font-bold transition flex items-center gap-1.5 text-xs">
                                                    <span class="material-symbols-outlined text-base">assignment_add</span>
                                                    <span>Emitir Acta en Sistema</span>
                                                </a>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    <!-- TABLA: HISTÓRICO DE MODIFICACIONES Y CAMBIOS EN EL EQUIPO (PAGINACIÓN CLIENTE 5 ITEMS) -->
                                    <div class="p-4 rounded-xl bg-[#040914] border border-obsidian-border space-y-3">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                            <div class="flex items-center gap-2">
                                                <span class="material-symbols-outlined text-cyan-400 text-lg">history</span>
                                                <h4 class="text-xs font-mono uppercase font-bold text-white">
                                                    Histórico de Modificaciones y Componentes
                                                </h4>
                                                <span class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                    {{ $asset->componentHistories->count() }} eventos
                                                </span>
                                            </div>

                                            <!-- FILTROS POR TIPO DE COMPONENTE -->
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="text-[10px] text-obsidian-muted font-mono">Filtrar:</span>
                                                <select onchange="filterAssetHistory({{ $asset->id }}, this.value)" class="px-2 py-1 bg-[#020610] border border-obsidian-border rounded-lg text-[10px] font-mono text-cyan-300 focus:outline-none">
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

                                        <!-- CONTENEDOR DE LA TABLA DE HISTÓRICO -->
                                        <div class="overflow-x-auto rounded-lg border border-obsidian-border/50">
                                            <table class="w-full text-left text-xs font-mono" id="history-table-{{ $asset->id }}">
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
                                                <tbody id="history-tbody-{{ $asset->id }}" class="divide-y divide-obsidian-border/30">
                                                    <!-- Filas de histórico renderizadas por Blade y controladas por JS -->
                                                    @forelse($asset->componentHistories as $h)
                                                    <tr class="history-item-{{ $asset->id }} hover:bg-white/[0.01]" data-type="{{ strtolower($h->component_type) }}">
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
                                                            @elseif($h->action === 'replaced')
                                                                <span class="text-cyan-300 font-bold text-[10px] flex items-center gap-0.5">
                                                                    <span class="material-symbols-outlined text-xs">sync</span> Reemplazado
                                                                </span>
                                                            @elseif($h->action === 'removed')
                                                                <span class="text-red-400 font-bold text-[10px] flex items-center gap-0.5">
                                                                    <span class="material-symbols-outlined text-xs">remove_circle</span> Retirado
                                                                </span>
                                                            @else
                                                                <span class="text-amber-400 font-bold text-[10px]">{{ ucfirst($h->action) }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="py-2 px-3 text-[10px] text-slate-400">
                                                            @if($h->old_value)
                                                                {{ is_array($h->old_value) ? ($h->old_value['model'] ?? ($h->old_value['name'] ?? json_encode($h->old_value))) : $h->old_value }}
                                                            @else
                                                                <span class="italic text-obsidian-muted">Ninguno</span>
                                                            @endif
                                                        </td>
                                                        <td class="py-2 px-3 text-[10px] text-white font-medium">
                                                            @if($h->new_value)
                                                                {{ is_array($h->new_value) ? ($h->new_value['model'] ?? ($h->new_value['name'] ?? json_encode($h->new_value))) : $h->new_value }}
                                                            @else
                                                                <span class="italic text-obsidian-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td class="py-2 px-3 text-[10px]">
                                                            <div class="text-slate-300 truncate max-w-[200px]" title="{{ $h->reason }}">{{ $h->reason ?: 'Actualización de inventario' }}</div>
                                                            <div class="text-[9px] text-obsidian-muted">{{ $h->registeredByUser?->name ?? 'Agente GLPI' }}</div>
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

                                        <!-- PAGINACIÓN CLIENTE (5 ITEMS POR PÁGINA) -->
                                        <div id="history-pagination-{{ $asset->id }}" class="flex items-center justify-between pt-1 text-[10px] font-mono text-obsidian-muted">
                                            <span id="history-counter-{{ $asset->id }}">Mostrando registros...</span>
                                            <div class="flex items-center gap-1.5">
                                                <button type="button" onclick="changeAssetHistoryPage({{ $asset->id }}, -1)" class="px-2 py-0.5 rounded bg-[#02050c] border border-obsidian-border hover:border-cyan-500/50 text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed" id="history-prev-{{ $asset->id }}">
                                                    &larr; Anterior
                                                </button>
                                                <span id="history-page-info-{{ $asset->id }}" class="text-cyan-300 font-bold px-1.5">1</span>
                                                <button type="button" onclick="changeAssetHistoryPage({{ $asset->id }}, 1)" class="px-2 py-0.5 rounded bg-[#02050c] border border-obsidian-border hover:border-cyan-500/50 text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed" id="history-next-{{ $asset->id }}">
                                                    Siguiente &rarr;
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <!-- CASO DISPOSITIVO DE RED (ROUTER, SWITCH, SERVIDOR, IMPRESORA, TELÉFONO) -->
                                <div class="p-4 rounded-xl bg-[#060e1c] border border-obsidian-border space-y-4">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-obsidian-border/60 gap-3">
                                        <div class="flex items-center gap-3">
                                            <span class="p-2.5 rounded-xl bg-purple-500/10 border border-purple-500/30 text-purple-400">
                                                <span class="material-symbols-outlined text-2xl">{{ $asset->type_icon }}</span>
                                            </span>
                                            <div>
                                                <h4 class="text-sm font-bold text-white font-mono flex items-center gap-2">
                                                    {{ $asset->display_identity }}
                                                    <span class="px-2 py-0.2 rounded text-[10px] font-bold uppercase bg-purple-950 text-purple-300 border border-purple-800">
                                                        {{ $asset->type }}
                                                    </span>
                                                </h4>
                                                <p class="text-xs text-obsidian-muted font-mono">{{ $asset->model ?? 'Dispositivo de infraestructura de red' }} • Sede {{ $asset->monitoredSite?->name ?? 'General' }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('admin.devices.index', ['site_id' => $asset->monitored_site_id]) }}" class="px-3 py-1.5 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 text-purple-300 text-xs font-mono font-bold transition flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-sm">hub</span>
                                                <span>Ver en Monitoreo (/admin/devices)</span>
                                            </a>
                                        </div>
                                    </div>

                                    <!-- GRID DE PARÁMETROS TÉCNICOS -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs font-mono">
                                        <div class="p-3 rounded-lg bg-[#040914] border border-obsidian-border/50">
                                            <span class="text-[10px] text-obsidian-muted uppercase block">Dirección IP &amp; MAC</span>
                                            <div class="text-cyan-300 font-bold mt-1">{{ $asset->ip_address ?? 'No configurada' }}</div>
                                            <div class="text-[10px] text-slate-400">{{ $asset->mac_address ?? 'MAC no asignada' }}</div>
                                        </div>

                                        <div class="p-3 rounded-lg bg-[#040914] border border-obsidian-border/50">
                                            <span class="text-[10px] text-obsidian-muted uppercase block">Puertos &amp; Interfaces</span>
                                            <div class="text-white font-medium mt-1">{{ $asset->networkDevice?->ports ?? '1x GE' }}</div>
                                            <div class="text-[10px] text-slate-400">Capacidad de conmutación</div>
                                        </div>

                                        <div class="p-3 rounded-lg bg-[#040914] border border-obsidian-border/50">
                                            <span class="text-[10px] text-obsidian-muted uppercase block">Protocolo de Acceso</span>
                                            <div class="text-white font-medium mt-1">{{ $asset->networkDevice?->access_type ?? 'SIN SOPORTE' }}</div>
                                            <div class="text-[10px] text-slate-400">Puerto: {{ $asset->networkDevice?->access_port ?? 'N/A' }}</div>
                                        </div>

                                        <div class="p-3 rounded-lg bg-[#040914] border border-obsidian-border/50">
                                            <span class="text-[10px] text-obsidian-muted uppercase block">Serial de Fábrica</span>
                                            <div class="text-white font-medium mt-1">{{ $asset->serial_number ?: 'SN-NET-' . $asset->id }}</div>
                                            <div class="text-[10px] text-slate-400">Tag: {{ $asset->asset_tag ?? 'N/A' }}</div>
                                        </div>
                                    </div>

                                    @if($asset->notes)
                                    <div class="p-2.5 rounded-lg bg-[#040914] border border-obsidian-border/40 text-xs font-mono text-slate-300">
                                        <span class="text-obsidian-muted uppercase text-[9px] font-bold block mb-0.5">Notas Técnicas:</span>
                                        {{ $asset->notes }}
                                    </div>
                                    @endif

                                    <!-- RESGUARDO DE RESPONSABLE Y HOJA FÍSICA DIGITALIZADA -->
                                    <div class="p-3.5 rounded-xl border {{ $activeAssignment && $activeAssignment->document_pdf_path ? 'bg-[#051410] border-emerald-500/40' : ($activeAssignment ? 'bg-[#151205] border-amber-500/40' : 'bg-[#040914] border-obsidian-border') }} flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs font-mono">
                                        <div class="flex items-start gap-3">
                                            <span class="p-2 rounded-lg {{ $activeAssignment && $activeAssignment->document_pdf_path ? 'bg-emerald-500/20 text-emerald-400' : ($activeAssignment ? 'bg-amber-500/20 text-amber-400' : 'bg-slate-800 text-slate-400') }}">
                                                <span class="material-symbols-outlined text-xl">
                                                    {{ $activeAssignment && $activeAssignment->document_pdf_path ? 'verified_user' : ($activeAssignment ? 'assignment_late' : 'person_off') }}
                                                </span>
                                            </span>
                                            <div>
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-bold uppercase tracking-wider {{ $activeAssignment && $activeAssignment->document_pdf_path ? 'text-emerald-300' : ($activeAssignment ? 'text-amber-300' : 'text-slate-300') }}">
                                                        @if($activeAssignment && $activeAssignment->document_pdf_path)
                                                            Hoja de Asignación Física Digitalizada (Resguardada)
                                                        @elseif($activeAssignment)
                                                            Asignación Activa en Sistema (Pendiente de Hoja Física Escaneada)
                                                        @else
                                                            Dispositivo de Red sin Asignación Directa ni Documento
                                                        @endif
                                                    </span>
                                                    @if($activeAssignment)
                                                        <span class="px-2 py-0.2 rounded text-[9.5px] font-mono font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                            {{ $activeAssignment->assignment_code }}
                                                        </span>
                                                        <span class="px-2 py-0.2 rounded text-[9.5px] font-mono font-bold {{ $activeAssignment->scan_compliance_badge_class }}">
                                                            {{ $activeAssignment->scan_compliance_label }}
                                                        </span>
                                                    @endif
                                                </div>
                                                @if($activeAssignment)
                                                    <div class="text-slate-300 mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px]">
                                                        <span>Responsable: <strong class="text-white">{{ $activeAssignment->custodian_name }}</strong></span>
                                                        @if($activeAssignment->custodian_id_number)
                                                            <span>• C.I: <span class="text-slate-400">{{ $activeAssignment->custodian_id_number }}</span></span>
                                                        @endif
                                                        @if($activeAssignment->custodian_department)
                                                            <span>• Dpto: <span class="text-slate-400">{{ $activeAssignment->custodian_department }}</span></span>
                                                        @endif
                                                        <span>• Fecha: <span class="text-cyan-400">{{ $activeAssignment->assigned_at ? $activeAssignment->assigned_at->format('d/m/Y') : 'N/A' }}</span></span>
                                                    </div>
                                                @else
                                                    <p class="text-[11px] text-obsidian-muted mt-0.5">
                                                        Equipo de infraestructura sin responsable directo asignado.
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
                                                        onclick="openUploadPhysicalScanModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}')"
                                                        class="px-2.5 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition flex items-center gap-1 text-xs" title="Reemplazar archivo digitalizado">
                                                    <span class="material-symbols-outlined text-base">sync</span>
                                                    <span>Reemplazar</span>
                                                </button>
                                                @endif
                                            @elseif($activeAssignment)
                                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                                <button type="button" 
                                                        onclick="openUploadPhysicalScanModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}', '{{ addslashes($activeAssignment->custodian_name) }}', '{{ addslashes($activeAssignment->custodian_id_number ?? '') }}', '{{ addslashes($activeAssignment->custodian_department ?? '') }}')"
                                                        class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold transition flex items-center gap-1.5 text-xs shadow-sm">
                                                    <span class="material-symbols-outlined text-base">upload_file</span>
                                                    <span>Subir Hoja Física Escaneada</span>
                                                </button>
                                                @endif
                                                <a href="{{ route('admin.inventory.assignments.pdf', $activeAssignment->id) }}" target="_blank" 
                                                   class="px-2.5 py-1.5 rounded-xl bg-red-950/40 border border-red-800/60 text-red-400 hover:bg-red-500 hover:text-white transition flex items-center gap-1 text-xs">
                                                    <span class="material-symbols-outlined text-base">picture_as_pdf</span>
                                                    <span>Acta Digital UINF-4.05</span>
                                                </a>
                                            @else
                                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                                <button type="button" 
                                                        onclick="openUploadPhysicalScanModal({{ $asset->id }}, '{{ addslashes($asset->display_identity) }}')"
                                                        class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold transition flex items-center gap-1.5 text-xs shadow-sm">
                                                    <span class="material-symbols-outlined text-base">upload_file</span>
                                                    <span>Subir Hoja Física Existente</span>
                                                </button>
                                                <a href="{{ route('admin.inventory.assignments.create', ['asset_id' => $asset->id, 'return_to' => url()->full()]) }}" 
                                                   class="px-3 py-1.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500 border border-cyan-500/40 text-cyan-300 hover:text-black font-bold transition flex items-center gap-1.5 text-xs">
                                                    <span class="material-symbols-outlined text-base">assignment_add</span>
                                                    <span>Emitir Acta en Sistema</span>
                                                </a>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-obsidian-muted">
                            <span class="material-symbols-outlined text-4xl mb-2 text-obsidian-border block">devices_off</span>
                            <p class="text-sm font-semibold text-slate-300">No se encontraron activos de TI</p>
                            <p class="text-xs text-obsidian-muted mt-1">Prueba cambiando los filtros de búsqueda o registra un nuevo equipo.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN PRINCIPAL -->
        @if($assets->hasPages())
        <div class="p-3 border-t border-obsidian-border bg-[#040b15]/60 flex items-center justify-between">
            {{ $assets->links('vendor.pagination.obsidian') }}
        </div>
        @endif
    </div>
</div>

<!-- BARRA FLOTANTE INFERIOR DE ACCIONES MASIVAS -->
<div id="bulkActionBar" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-[#081224] border border-cyan-500/50 rounded-2xl px-5 py-3 shadow-2xl flex items-center gap-4 transition-all duration-300 translate-y-32 opacity-0 pointer-events-none">
    <div class="flex items-center gap-2 text-xs font-mono text-white">
        <span class="material-symbols-outlined text-cyan-400 text-lg">checklist</span>
        <span id="bulkSelectedCount" class="font-bold text-cyan-300">0</span>
        <span class="text-obsidian-muted">equipos seleccionados</span>
    </div>

    <div class="h-5 w-px bg-obsidian-border"></div>

    <div class="flex items-center gap-2">
        <!-- BOTÓN MOVILIZACIÓN MASIVA (PASE DE SALIDA) -->
        <button type="button" onclick="executeBulkMobilization()" class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-amber-500/20">
            <span class="material-symbols-outlined text-base">local_shipping</span>
            <span>Movilizar Seleccionados</span>
        </button>

        <!-- BOTÓN MANTENIMIENTO MASIVO -->
        <button type="button" onclick="openBulkMaintenanceModal()" class="px-3.5 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20">
            <span class="material-symbols-outlined text-base">calendar_month</span>
            <span>Planificar Mantenimiento</span>
        </button>

        <!-- DESELECCIONAR TODOS -->
        <button type="button" onclick="deselectAll()" class="p-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white hover:border-slate-500 transition" title="Deseleccionar todos">
            <span class="material-symbols-outlined text-base">close</span>
        </button>
    </div>
</div>

<!-- MODAL DE ASOCIAR / CAMBIAR PERIFÉRICO -->
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

<!-- MODAL DE PLANIFICAR MANTENIMIENTO INDIVIDUAL / MASIVO -->
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
            <div id="maintPlanHiddenAssetInputs">
                <!-- Se inyectan los IDs seleccionados -->
            </div>

            <div class="p-4 sm:p-5 space-y-3.5 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Equipos Seleccionados</label>
                    <div id="maintPlanAssetSummary" class="p-2.5 rounded-xl bg-[#040b15] border border-obsidian-border text-xs font-mono text-cyan-300 max-h-24 overflow-y-auto">
                        <!-- Resumen de nombres -->
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

<!-- MODAL DE APROBACIÓN DE EQUIPO DESCUBIERTO POR GLPI -->
<div id="approveModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-[#0b1322] border border-cyan-500/40 rounded-2xl max-w-md w-full shadow-2xl relative flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-obsidian-border shrink-0">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-400">verified</span>
                Aprobar Activo Descubierto (GLPI)
            </h3>
            <button type="button" onclick="closeApproveModal()" class="text-obsidian-muted hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="approveForm" method="POST" action="" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            @csrf
            <div class="p-4 sm:p-5 space-y-3.5 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Nombre del Equipo</label>
                    <input type="text" id="modalAssetName" disabled class="w-full px-3 py-1.5 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-slate-300">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Código / Tag Patrimonial <span class="text-cyan-400">*</span></label>
                    <input type="text" name="asset_tag" required placeholder="Ej. PC-VS-042" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Estado de Incorporación</label>
                    <select name="target_status" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        <option value="in_stock">En Stock (Disponible en Almacén)</option>
                        <option value="assigned">Asignado a Responsable Directo</option>
                        <option value="maintenance">En Mantenimiento Técnico</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Sede Física</label>
                    <select name="monitored_site_id" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        <option value="">Seleccione una sede...</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Responsable Asignado (Opcional)</label>
                    <input type="text" name="custodian_name" placeholder="Nombre y Apellido del trabajador" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-3.5 bg-[#070e1a] border-t border-obsidian-border shrink-0">
                <button type="button" onclick="closeApproveModal()" class="px-3 py-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20">
                    <span class="material-symbols-outlined text-base">check_circle</span>
                    <span>Confirmar Aprobación</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE HISTORIAL COMPLETO DE MANTENIMIENTO TI -->
<div id="maintenanceModal" class="fixed inset-0 z-50 bg-black/85 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-[#0b1322] border border-cyan-500/40 rounded-2xl max-w-4xl w-full shadow-2xl relative max-h-[85vh] sm:max-h-[88vh] flex flex-col overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 px-6 border-b border-obsidian-border gap-3 shrink-0 bg-[#061527]">
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
                <button type="button" onclick="closeMaintenanceModal()" class="p-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition cursor-pointer" title="Cerrar modal">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
        </div>

        <div class="overflow-y-auto p-4 sm:p-6 flex-1 min-h-0 custom-scroll space-y-4">
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

        <div class="p-4 px-6 border-t border-obsidian-border flex items-center justify-between text-xs font-mono shrink-0 bg-[#061527]">
            <a href="{{ route('admin.inventory.maintenance.index') }}" class="text-cyan-400 hover:underline flex items-center gap-1">
                <span>Ver módulo general de mantenimientos</span>
                <span class="material-symbols-outlined text-xs">arrow_forward</span>
            </a>
            <button type="button" onclick="closeMaintenanceModal()" class="px-4 py-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white transition cursor-pointer">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- MODAL PARA SUBIR HOJA FÍSICA ESCANEADA (PDF O IMAGEN) -->
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
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Nombre del Responsable <span class="text-cyan-400">*</span></label>
                        <input type="text" name="custodian_name" id="scanModalCustodianName" required placeholder="Ej. Juan Pérez" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Cédula del Responsable</label>
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

@php
    $assetsMaintenanceMap = $assets->mapWithKeys(function ($a) {
        return [$a->id => [
            'id' => $a->id,
            'name' => $a->display_identity,
            'tag' => $a->asset_tag,
            'serial' => $a->serial_number,
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

<script>
    const assetsMaintenanceMap = @json($assetsMaintenanceMap);
    const historyState = {};

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
            // Inicializar paginación de histórico del equipo
            initAssetHistory(assetId);
        } else {
            row.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-180', 'text-cyan-400');
            if (parentRow) parentRow.classList.remove('bg-cyan-500/[0.04]', 'border-cyan-500/40');
        }
    }

    // 2. CONTROL DE HISTÓRICO CON PAGINACIÓN CLIENTE (5 ITEMS) Y FILTRO POR TIPO
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

        // Filtrar filas
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

        // Ocultar todas primero
        rows.forEach(r => r.style.display = 'none');

        // Mostrar solo las de la página actual que pasaron el filtro
        filtered.slice(startIdx, endIdx).forEach(r => r.style.display = '');

        // Actualizar controles
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

        const ids = checked.map(cb => cb.value).join(',');
        window.location.href = `/admin/inventory/assignments/create?bulk=1&asset_ids=${ids}&movement_type=transfer&return_to=${encodeURIComponent(window.location.href)}`;
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
            badge.className = 'inline-block mr-1.5 mb-1 px-2 py-0.5 rounded bg-cyan-950/80 text-cyan-300 border border-cyan-800 text-[10px]';
            badge.innerText = cb.getAttribute('data-name');
            summary.appendChild(badge);
        });

        document.getElementById('maintPlanModalTitle').innerText = `Planificar Mantenimiento (${checked.length} Equipos)`;
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

    // 4. MODAL PARA ASOCIAR O CAMBIAR PERIFÉRICO
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

    // 5. MODALES DE MANTENIMIENTO TI Y APROBACIÓN
    function openMaintenanceModal(assetId) {
        const asset = assetsMaintenanceMap[assetId];
        if (!asset) return;

        document.getElementById('maintModalAssetTitle').innerText = asset.name + (asset.tag ? ' • [' + asset.tag + ']' : (asset.serial ? ' • [S/N: ' + asset.serial + ']' : ''));
        const newTicketBtn = document.getElementById('maintModalNewTicketBtn');
        if (newTicketBtn) newTicketBtn.href = asset.new_ticket_url;

        const tbody = document.getElementById('maintTicketsBody');
        const emptyState = document.getElementById('maintEmptyState');
        const tableContainer = document.getElementById('maintTableContainer');

        tbody.innerHTML = '';

        if (asset.tickets.length === 0) {
            tableContainer.classList.add('hidden');
            emptyState.classList.remove('hidden');
        } else {
            emptyState.classList.add('hidden');
            tableContainer.classList.remove('hidden');

            asset.tickets.forEach(ticket => {
                const tr = document.createElement('tr');
                tr.className = 'border-b border-obsidian-border/40 hover:bg-white/[0.02] transition';
                tr.innerHTML = `
                    <td class="py-2.5 px-3">
                        <div class="font-bold text-cyan-300 font-mono text-xs flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-cyan-400">build</span>
                            <span>${ticket.code}</span>
                        </div>
                        <div class="text-[10px] text-obsidian-muted">${ticket.date}</div>
                    </td>
                    <td class="py-2.5 px-3">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-800 text-slate-300 border border-slate-700">
                            ${ticket.type_label}
                        </span>
                    </td>
                    <td class="py-2.5 px-3">
                        <div class="text-white font-medium text-xs truncate max-w-[220px]" title="${ticket.title}">${ticket.title}</div>
                        <div class="text-[10px] text-obsidian-muted truncate max-w-[220px]" title="${ticket.description || ''}">${ticket.description || 'Sin notas'}</div>
                    </td>
                    <td class="py-2.5 px-3">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border ${ticket.status_class}">
                            ${ticket.status_label}
                        </span>
                    </td>
                    <td class="py-2.5 px-3">
                        <div class="text-slate-300 text-xs flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs text-obsidian-muted">engineering</span>
                            <span>${ticket.technician}</span>
                        </div>
                    </td>
                    <td class="py-2.5 px-3 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <a href="${ticket.pdf_url}" target="_blank" class="p-1.5 rounded-lg bg-red-950/30 border border-red-800/40 text-red-400 hover:bg-red-500 hover:text-white transition" title="Descargar Orden de Servicio (PDF)">
                                <span class="material-symbols-outlined text-sm">picture_as_pdf</span>
                            </a>
                            <a href="${ticket.show_url}" class="p-1.5 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 hover:bg-cyan-500 hover:text-black transition" title="Ver Detalle Completo">
                                <span class="material-symbols-outlined text-sm">visibility</span>
                            </a>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        document.getElementById('maintenanceModal').classList.remove('hidden');
    }

    function closeMaintenanceModal() {
        document.getElementById('maintenanceModal').classList.add('hidden');
    }

    function openApproveModal(assetId, name, serial) {
        document.getElementById('approveForm').action = '/admin/inventory/assets/' + assetId + '/approve';
        document.getElementById('modalAssetName').value = name + (serial ? ' (' + serial + ')' : '');
        document.getElementById('approveModal').classList.remove('hidden');
    }

    function closeApproveModal() {
        document.getElementById('approveModal').classList.add('hidden');
    }

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

    // Cerrar con Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeMaintenanceModal();
            closeApproveModal();
            closePeripheralModal();
            closeBulkMaintenanceModal();
            closeUploadPhysicalScanModal();
        }
    });
</script>
@endsection
