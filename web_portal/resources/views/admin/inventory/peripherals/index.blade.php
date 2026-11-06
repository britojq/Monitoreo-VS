@extends('layouts.admin')

@section('page_title', 'Periféricos & Stock de Hardware')

@section('admin_content')
<div class="space-y-4">
    <!-- ========================================================================= -->
    <!-- CABECERA Y ACCIONES PRINCIPALES                                           -->
    <!-- ========================================================================= -->
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
                <span class="material-symbols-outlined text-cyan-400 text-2xl">keyboard</span>
                Periféricos &amp; Stock de Hardware
            </h1>
            <p class="text-xs font-mono text-obsidian-muted">Censo físico de Monitores, Teclados, Ratones, Reguladores, UPS y stock de repuestos para asignación y desincorporación</p>
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

            @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage')) && !($isClusterSlave ?? false))
                <div class="relative group">
                    <button type="button" onclick="openCreatePeripheralModal()" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black transition text-xs font-mono font-bold shadow-md shadow-cyan-500/20 cursor-pointer">
                        <span class="material-symbols-outlined text-base">keyboard</span>
                        <span class="font-bold text-sm leading-none">+</span>
                    </button>
                    <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-xs text-cyan-400">add</span>
                            <span class="font-bold text-cyan-200">Registrar Periférico</span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="relative group">
                <a href="{{ route('admin.inventory.peripherals.index') }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition shadow-xs cursor-pointer">
                    <span class="material-symbols-outlined text-base">refresh</span>
                </a>
                <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                    <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs text-cyan-400">refresh</span>
                        <span class="font-bold text-cyan-200">Refrescar Vista</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    <!-- ========================================================================= -->
    <!-- HUD: TARJETAS MÉTRICAS DE HARDWARE & PERIFÉRICOS                          -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <!-- TOTAL PERIFÉRICOS -->
        <a href="{{ route('admin.inventory.peripherals.index') }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border hover:border-cyan-500/50 transition group shadow-xs">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">Total Periféricos</span>
                <span class="material-symbols-outlined text-lg text-cyan-400 group-hover:scale-110 transition-transform">category</span>
            </div>
            <div class="text-2xl font-black text-white font-mono">{{ $totalCount }}</div>
            <div class="text-[10px] font-mono text-cyan-400/80 mt-1">Periféricos censados</div>
        </a>

        <!-- EN STOCK (DISPONIBLES) -->
        <a href="{{ route('admin.inventory.peripherals.index', ['status' => 'in_stock']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'in_stock' ? 'border-blue-500 bg-blue-950/20' : 'border-obsidian-border hover:border-blue-500/50' }} transition group shadow-xs">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">En Stock / Almacén</span>
                <span class="material-symbols-outlined text-lg text-blue-400 group-hover:scale-110 transition-transform">inventory_2</span>
            </div>
            <div class="text-2xl font-black text-blue-400 font-mono">{{ $inStockCount }}</div>
            <div class="text-[10px] font-mono text-blue-400/80 mt-1">Disponibles para entrega</div>
        </a>

        <!-- ASIGNADOS A PUESTOS -->
        <a href="{{ route('admin.inventory.peripherals.index', ['status' => 'assigned']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'assigned' ? 'border-emerald-500 bg-emerald-950/20' : 'border-obsidian-border hover:border-emerald-500/50' }} transition group shadow-xs">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">Asignados a Puestos</span>
                <span class="material-symbols-outlined text-lg text-emerald-400 group-hover:scale-110 transition-transform">desktop_windows</span>
            </div>
            <div class="text-2xl font-black text-emerald-400 font-mono">{{ $assignedCount }}</div>
            <div class="text-[10px] font-mono text-emerald-400/80 mt-1">En uso operativo</div>
        </a>

        <!-- DAÑADOS / PARA BAJA -->
        <a href="{{ route('admin.inventory.peripherals.index', ['status' => 'damaged']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'damaged' ? 'border-red-500 bg-red-950/20' : 'border-obsidian-border hover:border-red-500/50' }} transition group shadow-xs">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">Dañados / Para Baja</span>
                <span class="material-symbols-outlined text-lg text-red-400 group-hover:scale-110 transition-transform">report_problem</span>
            </div>
            <div class="text-2xl font-black text-red-400 font-mono">{{ $damagedCount }}</div>
            <div class="text-[10px] font-mono text-red-400/80 mt-1">Fuera de servicio</div>
        </a>

        <!-- EN MANTENIMIENTO -->
        <a href="{{ route('admin.inventory.peripherals.index', ['status' => 'in_maintenance']) }}" class="p-3.5 rounded-2xl bg-obsidian-panel/90 border {{ $statusFilter === 'in_maintenance' ? 'border-amber-500 bg-amber-950/20' : 'border-obsidian-border hover:border-amber-500/50' }} transition group shadow-xs">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-mono text-obsidian-muted uppercase font-bold">En Mantenimiento</span>
                <span class="material-symbols-outlined text-lg text-amber-400 group-hover:scale-110 transition-transform">build</span>
            </div>
            <div class="text-2xl font-black text-amber-400 font-mono">{{ $maintenanceCount }}</div>
            <div class="text-[10px] font-mono text-amber-400/80 mt-1">En revisión técnica</div>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- BARRA DE FILTROS & BÚSQUEDA REACTIVA                                      -->
    <!-- ========================================================================= -->
    <div class="p-3.5 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border shadow-xs">
        <form method="GET" action="{{ route('admin.inventory.peripherals.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
            <!-- BUSCADOR -->
            <div class="lg:col-span-4 relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-obsidian-muted text-lg pointer-events-none">search</span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por serial, tag, marca, modelo, notas..." class="w-full pl-9 pr-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white placeholder-obsidian-muted focus:border-cyan-500 focus:outline-none transition">
            </div>

            <!-- FILTRO DE SEDE -->
            <div class="lg:col-span-3">
                <select name="site_id" onchange="this.form.submit()" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none transition cursor-pointer">
                    <option value="">Todas las Sedes</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" {{ (string)$siteFilter === (string)$site->id ? 'selected' : '' }}>
                            {{ $site->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- FILTRO DE TIPO DE PERIFÉRICO -->
            <div class="lg:col-span-2">
                <select name="type" onchange="this.form.submit()" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none transition cursor-pointer">
                    <option value="">Todos los Tipos</option>
                    <option value="monitor" {{ $typeFilter === 'monitor' ? 'selected' : '' }}>Monitor / Pantalla</option>
                    <option value="keyboard" {{ $typeFilter === 'keyboard' ? 'selected' : '' }}>Teclado</option>
                    <option value="mouse" {{ $typeFilter === 'mouse' ? 'selected' : '' }}>Mouse / Ratón</option>
                    <option value="surge_protector" {{ $typeFilter === 'surge_protector' ? 'selected' : '' }}>Regulador / Protector</option>
                    <option value="ups" {{ $typeFilter === 'ups' ? 'selected' : '' }}>Sistema UPS</option>
                    <option value="printer" {{ $typeFilter === 'printer' ? 'selected' : '' }}>Impresora Local</option>
                    <option value="scanner" {{ $typeFilter === 'scanner' ? 'selected' : '' }}>Escáner / Lector</option>
                    <option value="headset" {{ $typeFilter === 'headset' ? 'selected' : '' }}>Auriculares</option>
                    <option value="other" {{ $typeFilter === 'other' ? 'selected' : '' }}>Otro</option>
                </select>
            </div>

            <!-- FILTRO DE ESTADO -->
            <div class="lg:col-span-2">
                <select name="status" onchange="this.form.submit()" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none transition cursor-pointer">
                    <option value="">Todos los Estados</option>
                    <option value="in_stock" {{ $statusFilter === 'in_stock' ? 'selected' : '' }}>🔵 En Stock (Disponible)</option>
                    <option value="assigned" {{ $statusFilter === 'assigned' ? 'selected' : '' }}>🟢 Asignado a Puesto</option>
                    <option value="damaged" {{ $statusFilter === 'damaged' ? 'selected' : '' }}>🔴 Dañado / Para Baja</option>
                    <option value="in_maintenance" {{ $statusFilter === 'in_maintenance' ? 'selected' : '' }}>🟡 En Mantenimiento</option>
                    <option value="decommissioned" {{ $statusFilter === 'decommissioned' ? 'selected' : '' }}>⚫ Desincorporado</option>
                </select>
            </div>

            <!-- BOTÓN LIMPIAR -->
            <div class="lg:col-span-1 flex items-center gap-1.5">
                <a href="{{ route('admin.inventory.peripherals.index') }}" class="{{ ($search || $statusFilter || $typeFilter || $siteFilter) ? '' : 'hidden' }} w-full py-2 px-2.5 bg-red-950/60 border border-red-500/40 text-red-300 hover:bg-red-600 hover:text-white rounded-xl text-xs font-mono font-bold transition flex items-center justify-center gap-1 shadow-xs cursor-pointer" title="Limpiar todos los filtros">
                    <span class="material-symbols-outlined text-base">filter_alt_off</span>
                    <span>Limpiar</span>
                </a>
            </div>
        </form>
    </div>

    <!-- ========================================================================= -->
    <!-- TABLA DE PERIFÉRICOS & STOCK                                              -->
    <!-- ========================================================================= -->
    <div class="rounded-2xl bg-obsidian-panel/90 border border-obsidian-border overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-[#050e1c] text-obsidian-muted uppercase text-[10px] tracking-wider border-b border-obsidian-border/80">
                    <tr>
                        <th class="py-3 px-4">Tag / Código</th>
                        <th class="py-3 px-4">Tipo</th>
                        <th class="py-3 px-4">Marca &amp; Modelo</th>
                        <th class="py-3 px-4">Serial (S/N)</th>
                        <th class="py-3 px-4">Sede</th>
                        <th class="py-3 px-4">Estado</th>
                        <th class="py-3 px-4">Puesto Asociado</th>
                        <th class="py-3 px-4">Notas / Falla</th>
                        <th class="py-3 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/60">
                    @forelse($peripherals as $p)
                        <tr class="hover:bg-cyan-950/20 transition group">
                            <!-- TAG / CÓDIGO -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="font-bold text-cyan-300 bg-cyan-950/60 border border-cyan-800/80 px-2 py-0.5 rounded text-[11px]">
                                    {{ $p->asset_tag ?: 'PER-' . str_pad($p->id, 5, '0', STR_PAD_LEFT) }}
                                </span>
                            </td>

                            <!-- TIPO -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="p-1 rounded bg-[#030914] border border-obsidian-border text-cyan-400">
                                        <span class="material-symbols-outlined text-base">{{ $p->type_icon }}</span>
                                    </span>
                                    <span class="text-white font-medium">{{ $p->type_label }}</span>
                                </div>
                            </td>

                            <!-- MARCA & MODELO -->
                            <td class="py-3 px-4 text-slate-200">
                                <div class="font-semibold text-white">{{ $p->brand ?: 'Genérico' }}</div>
                                <div class="text-[10px] text-obsidian-muted">{{ $p->model ?: 'Estándar' }}</div>
                            </td>

                            <!-- SERIAL -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if(empty($p->serial_number) || in_array(strtoupper($p->serial_number), ['SIN SERIAL', 'N/A', 'S/N']))
                                    <span class="text-obsidian-muted italic text-[10.5px]">Sin Serial</span>
                                @else
                                    <span class="text-amber-300 font-mono font-bold bg-amber-950/40 border border-amber-800/60 px-1.5 py-0.5 rounded text-[11px]">
                                        {{ $p->serial_number }}
                                    </span>
                                @endif
                            </td>

                            <!-- SEDE -->
                            <td class="py-3 px-4 whitespace-nowrap text-slate-300">
                                {{ $p->site?->name ?? 'General' }}
                            </td>

                            <!-- ESTADO -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($p->status === 'in_stock')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-blue-950/80 text-blue-300 border border-blue-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                                        <span>En Stock</span>
                                    </span>
                                @elseif($p->status === 'assigned')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        <span>Asignado</span>
                                    </span>
                                @elseif($p->status === 'damaged')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-red-950/80 text-red-300 border border-red-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
                                        <span>Dañado</span>
                                    </span>
                                @elseif($p->status === 'in_maintenance')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-950/80 text-amber-300 border border-amber-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                        <span>Mantenimiento</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-slate-900 text-slate-400 border border-slate-700">
                                        <span>{{ ucfirst($p->status) }}</span>
                                    </span>
                                @endif
                            </td>

                            <!-- PUESTO ASOCIADO -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($p->currentAsset)
                                    <a href="{{ route('admin.devices.index', ['search' => $p->currentAsset->asset_tag ?? $p->currentAsset->name]) }}" class="inline-flex items-center gap-1 text-cyan-400 hover:text-cyan-300 hover:underline font-bold" title="Ver puesto en catálogo de dispositivos">
                                        <span class="material-symbols-outlined text-xs">desktop_windows</span>
                                        <span>{{ $p->currentAsset->display_identity ?? $p->currentAsset->name }}</span>
                                    </a>
                                @else
                                    <span class="text-obsidian-muted italic text-[10.5px]">Libre en Stock</span>
                                @endif
                            </td>

                            <!-- NOTAS / FALLA -->
                            <td class="py-3 px-4 max-w-[180px] truncate text-slate-400" title="{{ $p->notes }}">
                                {{ $p->notes ?: '—' }}
                            </td>

                            <!-- ACCIONES -->
                            <td class="py-3 px-4 whitespace-nowrap text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                        <!-- BOTÓN EDITAR -->
                                        <button type="button" onclick="openEditPeripheralModal({{ json_encode($p) }})" class="p-1 rounded bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black transition inline-flex items-center justify-center cursor-pointer" title="Editar periférico / Cambiar estado">
                                            <span class="material-symbols-outlined text-[13px]">edit</span>
                                        </button>

                                        <!-- BOTÓN DESVINCULAR (SI ESTÁ ASOCIADO) -->
                                        @if($p->currentAsset)
                                            <form action="{{ route('admin.inventory.peripherals.unlink', $p->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Desvincular este periférico del puesto {{ $p->currentAsset->name }} y moverlo a Stock?');">
                                                @csrf
                                                <button type="submit" class="p-1 rounded bg-amber-950/70 border border-amber-500/60 text-amber-300 hover:bg-amber-500 hover:text-black transition inline-flex items-center justify-center cursor-pointer" title="Desvincular de puesto y pasar a Stock">
                                                    <span class="material-symbols-outlined text-[13px]">link_off</span>
                                                </button>
                                            </form>
                                        @endif

                                        <!-- BOTÓN ELIMINAR -->
                                        <form action="{{ route('admin.inventory.peripherals.destroy', $p->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Está seguro de eliminar permanentemente este periférico del inventario?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 rounded bg-red-950/70 border border-red-500/60 text-red-300 hover:bg-red-500 hover:text-white transition inline-flex items-center justify-center cursor-pointer" title="Eliminar del inventario">
                                                <span class="material-symbols-outlined text-[13px]">delete</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-obsidian-muted italic text-xs">
                                No se encontraron periféricos registrados con los criterios seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($peripherals->hasPages())
            <div class="p-3 border-t border-obsidian-border/80 bg-[#050e1c]">
                {{ $peripherals->links() }}
            </div>
        @endif
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: REGISTRAR NUEVO PERIFÉRICO (LIMPIO - CERO RED)                     -->
<!-- ========================================================================= -->
<div id="modal-create-peripheral" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-[#0b1322] border border-cyan-500/40 rounded-2xl max-w-lg w-full shadow-2xl flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden">
        <div class="flex items-center justify-between p-4 px-6 border-b border-obsidian-border shrink-0 bg-[#061527]">
            <h3 class="text-sm font-bold text-white flex items-center gap-2 font-mono">
                <span class="material-symbols-outlined text-cyan-400">add_circle</span>
                <span>Registrar Nuevo Periférico / Hardware</span>
            </h3>
            <button type="button" onclick="closeCreatePeripheralModal()" class="text-obsidian-muted hover:text-white leading-none cursor-pointer">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="{{ route('admin.inventory.peripherals.store') }}" method="POST" class="flex flex-col flex-1 min-h-0 overflow-hidden font-mono text-xs">
            @csrf

            <div class="p-4 sm:p-5 space-y-3.5 overflow-y-auto flex-1 custom-scroll">
                <!-- TIPO DE PERIFÉRICO & SEDE -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-cyan-300 mb-1">Tipo de Periférico <span class="text-cyan-400">*</span></label>
                        <select name="type" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            <option value="monitor">🖥️ Monitor / Pantalla</option>
                            <option value="keyboard">⌨️ Teclado</option>
                            <option value="mouse">🖱️ Mouse / Ratón</option>
                            <option value="surge_protector">⚡ Regulador / Protector</option>
                            <option value="ups">🔋 Sistema UPS</option>
                            <option value="printer">🖨️ Impresora Local USB</option>
                            <option value="scanner">📠 Escáner / Lector</option>
                            <option value="headset">🎧 Auriculares / Headset</option>
                            <option value="other">📦 Otro Periférico</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-cyan-300 mb-1">Sede Regional <span class="text-cyan-400">*</span></label>
                        <select name="monitored_site_id" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- MARCA Y MODELO -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Marca</label>
                        <input type="text" name="brand" placeholder="ej. HP / Genius / Samsung / Forza" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Modelo</label>
                        <input type="text" name="model" placeholder="ej. KB-110 / V193b / FVR-1211" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>

                <!-- SERIAL CON BOTÓN RÁPIDO "SIN SERIAL" -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-mono text-amber-400">Número de Serie (Serial)</label>
                        <button type="button" onclick="setCreateSerialNone()" class="text-[10px] font-mono text-amber-300 hover:text-white underline cursor-pointer">
                            Establecer "Sin Serial"
                        </button>
                    </div>
                    <input type="text" name="serial_number" id="create_peripheral_serial" placeholder="ej. 3CQ51323GC o SN-KBD-098" class="w-full px-3 py-2 bg-[#040b15] border border-amber-500/30 rounded-xl text-xs font-mono text-amber-200 focus:border-amber-400 focus:outline-none">
                </div>

                <!-- ESTADO / CONDICIÓN INICIAL -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-emerald-400 mb-1">Condición / Estado Inicial <span class="text-emerald-400">*</span></label>
                        <select name="status" id="create_peripheral_status" required class="w-full px-3 py-2 bg-[#040b15] border border-emerald-500/30 rounded-xl text-xs font-mono text-white focus:border-emerald-500 focus:outline-none">
                            <option value="in_stock" selected>🔵 En Stock (Disponible / Almacén)</option>
                            <option value="damaged">🔴 Dañado (Para Desincorporar / Falla)</option>
                            <option value="in_maintenance">🟡 En Mantenimiento (En Taller)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-cyan-300 mb-1">Puesto de Trabajo Asociado (Opcional)</label>
                        <select name="current_asset_id" id="create_peripheral_asset" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            <option value="">Libre en Stock / Sin Puesto</option>
                            @foreach($workstations as $ws)
                                <option value="{{ $ws->id }}">{{ $ws->name }} ({{ $ws->asset_tag }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- OBSERVACIONES / NOTAS DE FALLA -->
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Observaciones / Motivo de Daño o Procedencia</label>
                    <textarea name="notes" rows="2" placeholder="ej. Nuevo en caja para repuesto / Dañado por sobretensión en transformador / Pantalla parpadea" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none"></textarea>
                </div>
            </div>

            <!-- BOTONES -->
            <div class="p-3.5 px-6 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0 bg-[#061527]">
                <button type="button" onclick="closeCreatePeripheralModal()" class="px-4 py-2 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition cursor-pointer">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    <span>Guardar Periférico</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDITAR PERIFÉRICO / CAMBIAR ESTADO                                 -->
<!-- ========================================================================= -->
<div id="modal-edit-peripheral" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-[#0b1322] border border-cyan-500/40 rounded-2xl max-w-lg w-full shadow-2xl flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden">
        <div class="flex items-center justify-between p-4 px-6 border-b border-obsidian-border shrink-0 bg-[#061527]">
            <h3 class="text-sm font-bold text-white flex items-center gap-2 font-mono">
                <span class="material-symbols-outlined text-cyan-400">edit</span>
                <span>Editar Periférico: <span id="edit_peripheral_tag_title" class="text-cyan-300"></span></span>
            </h3>
            <button type="button" onclick="closeEditPeripheralModal()" class="text-obsidian-muted hover:text-white leading-none cursor-pointer">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="form-edit-peripheral" method="POST" class="flex flex-col flex-1 min-h-0 overflow-hidden font-mono text-xs">
            @csrf
            @method('PUT')

            <div class="p-4 sm:p-5 space-y-3.5 overflow-y-auto flex-1 custom-scroll">
                <!-- TIPO DE PERIFÉRICO & SEDE -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-cyan-300 mb-1">Tipo de Periférico <span class="text-cyan-400">*</span></label>
                        <select name="type" id="edit_type" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            <option value="monitor">🖥️ Monitor / Pantalla</option>
                            <option value="keyboard">⌨️ Teclado</option>
                            <option value="mouse">🖱️ Mouse / Ratón</option>
                            <option value="surge_protector">⚡ Regulador / Protector</option>
                            <option value="ups">🔋 Sistema UPS</option>
                            <option value="printer">🖨️ Impresora Local USB</option>
                            <option value="scanner">📠 Escáner / Lector</option>
                            <option value="headset">🎧 Auriculares / Headset</option>
                            <option value="other">📦 Otro Periférico</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-cyan-300 mb-1">Sede Regional <span class="text-cyan-400">*</span></label>
                        <select name="monitored_site_id" id="edit_site_id" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- MARCA Y MODELO -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Marca</label>
                        <input type="text" name="brand" id="edit_brand" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Modelo</label>
                        <input type="text" name="model" id="edit_model" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>

                <!-- SERIAL CON BOTÓN RÁPIDO "SIN SERIAL" -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-mono text-amber-400">Número de Serie (Serial)</label>
                        <button type="button" onclick="setEditSerialNone()" class="text-[10px] font-mono text-amber-300 hover:text-white underline cursor-pointer">
                            Establecer "Sin Serial"
                        </button>
                    </div>
                    <input type="text" name="serial_number" id="edit_serial_number" class="w-full px-3 py-2 bg-[#040b15] border border-amber-500/30 rounded-xl text-xs font-mono text-amber-200 focus:border-amber-400 focus:outline-none">
                </div>

                <!-- ESTADO / CONDICIÓN -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-emerald-400 mb-1">Condición / Estado <span class="text-emerald-400">*</span></label>
                        <select name="status" id="edit_status" required class="w-full px-3 py-2 bg-[#040b15] border border-emerald-500/30 rounded-xl text-xs font-mono text-white focus:border-emerald-500 focus:outline-none">
                            <option value="in_stock">🔵 En Stock (Disponible / Almacén)</option>
                            <option value="assigned">🟢 Asignado a Puesto</option>
                            <option value="damaged">🔴 Dañado (Para Desincorporar / Falla)</option>
                            <option value="in_maintenance">🟡 En Mantenimiento (En Taller)</option>
                            <option value="decommissioned">⚫ Desincorporado (Baja)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-cyan-300 mb-1">Puesto de Trabajo Asociado</label>
                        <select name="current_asset_id" id="edit_current_asset_id" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            <option value="">Libre en Stock / Sin Puesto</option>
                            @foreach($workstations as $ws)
                                <option value="{{ $ws->id }}">{{ $ws->name }} ({{ $ws->asset_tag }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- OBSERVACIONES / NOTAS DE FALLA -->
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Observaciones / Motivo de Daño o Procedencia</label>
                    <textarea name="notes" id="edit_notes" rows="2" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none"></textarea>
                </div>
            </div>

            <!-- BOTONES -->
            <div class="p-3.5 px-6 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0 bg-[#061527]">
                <button type="button" onclick="closeEditPeripheralModal()" class="px-4 py-2 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition cursor-pointer">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">save</span>
                    <span>Actualizar Periférico</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreatePeripheralModal() {
        document.getElementById('modal-create-peripheral').classList.remove('hidden');
    }

    function closeCreatePeripheralModal() {
        document.getElementById('modal-create-peripheral').classList.add('hidden');
    }

    function setCreateSerialNone() {
        document.getElementById('create_peripheral_serial').value = 'Sin Serial';
    }

    function openEditPeripheralModal(peripheral) {
        document.getElementById('form-edit-peripheral').action = '/admin/inventory/peripherals/' + peripheral.id;
        document.getElementById('edit_peripheral_tag_title').innerText = peripheral.asset_tag || ('ID #' + peripheral.id);
        document.getElementById('edit_type').value = peripheral.type || 'other';
        document.getElementById('edit_site_id').value = peripheral.monitored_site_id || '';
        document.getElementById('edit_brand').value = peripheral.brand || '';
        document.getElementById('edit_model').value = peripheral.model || '';
        document.getElementById('edit_serial_number').value = peripheral.serial_number || '';
        document.getElementById('edit_status').value = peripheral.status || 'in_stock';
        document.getElementById('edit_current_asset_id').value = peripheral.current_asset_id || '';
        document.getElementById('edit_notes').value = peripheral.notes || '';

        document.getElementById('modal-edit-peripheral').classList.remove('hidden');
    }

    function closeEditPeripheralModal() {
        document.getElementById('modal-edit-peripheral').classList.add('hidden');
    }

    function setEditSerialNone() {
        document.getElementById('edit_serial_number').value = 'Sin Serial';
    }
</script>
@endsection
