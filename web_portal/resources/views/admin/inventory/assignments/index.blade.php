@extends('layouts.admin')

@section('page_title', 'Mesa de Asignaciones y Actas TI (UINF-4.05)')

@section('admin_content')
<div class="space-y-4">
    <!-- ENCABEZADO Y ACCIONES -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-obsidian-panel/40 border border-obsidian-border p-5 rounded-2xl backdrop-blur-md">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono uppercase tracking-widest text-obsidian-cyan font-bold">Fase 4 &bull; ITAM</span>
                <span class="text-obsidian-muted">&bull;</span>
                <span class="text-xs font-mono text-emerald-400">Formato UINF-4.05 & Puntos de Control</span>
            </div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <span class="material-symbols-outlined text-obsidian-cyan text-3xl">assignment_turned_in</span>
                Mesa de Asignaciones, Devoluciones y Pases
            </h1>
            <p class="text-sm text-obsidian-muted mt-1">
                Generador y gestor de actas de asignación <code class="text-white/80 font-mono text-xs">UINF-4.05</code> y constancias de movilización de equipos por garitas de seguridad.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.devices.index') }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl border border-obsidian-border bg-obsidian-panel text-obsidian-muted hover:text-white transition text-xs font-mono shadow-xs" title="Volver a Equipos & Activos">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                <span>Equipos</span>
            </a>
            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign'))
            <div class="relative group">
                <a href="{{ route('admin.inventory.assignments.create') }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black transition text-xs font-mono font-bold shadow-md shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-base">post_add</span>
                    <span class="font-bold text-sm leading-none">+</span>
                </a>
                <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                    <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs text-cyan-400">post_add</span>
                        <span class="font-bold text-cyan-200">Nueva Asignación / Movilización</span>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- PESTAÑAS EQUIPOS & ASIGNACIONES (PANEL DE NAVEGACIÓN COMPACTO) -->
    <div class="flex flex-wrap items-center gap-1.5 border-b border-obsidian-border/80 pb-2.5">
        <a href="{{ route('admin.devices.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.devices.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Equipos y Activos de Red Monitoreados">
            <span class="material-symbols-outlined text-sm">devices</span>
            <span>Equipos &amp; Activos</span>
        </a>
        <a href="{{ route('admin.inventory.assignments.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.inventory.assignments.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Asignaciones y Actas de Entrega">
            <span class="material-symbols-outlined text-sm">assignment_turned_in</span>
            <span>Asignaciones &amp; Actas</span>
        </a>
        <a href="{{ route('admin.inventory.maintenance.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.inventory.maintenance.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Mantenimiento Técnico Preventivo y Correctivo">
            <span class="material-symbols-outlined text-sm">build</span>
            <span>Mantenimiento TI</span>
        </a>
        <a href="{{ route('admin.inventory.deploy') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.inventory.deploy*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Despliegue Remoto Agente GLPI">
            <span class="material-symbols-outlined text-sm">send_to_mobile</span>
            <span>Despliegue GLPI</span>
        </a>
    </div>

    <!-- TARJETAS DE INDICADORES (KPIS) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Asignaciones Activas -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono text-obsidian-muted uppercase tracking-wider">Asignaciones Activas</span>
                <span class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                    <span class="material-symbols-outlined text-xl">assignment_ind</span>
                </span>
            </div>
            <div class="text-3xl font-bold font-mono text-white mt-3">{{ $stats['total_assignments'] }}</div>
            <div class="text-xs text-obsidian-muted mt-1">Equipos entregados con acta UINF-4.05</div>
        </div>

        <!-- Soporte Validado (Escaneado) -->
        <a href="{{ route('admin.inventory.assignments.index', ['sla_status' => 'validated']) }}" class="bg-obsidian-panel/30 border border-obsidian-border hover:border-emerald-500/50 p-5 rounded-2xl relative overflow-hidden transition group cursor-pointer">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono text-obsidian-muted group-hover:text-emerald-300 uppercase tracking-wider transition">Soporte Validado</span>
                <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 group-hover:scale-105 transition">
                    <span class="material-symbols-outlined text-xl">verified</span>
                </span>
            </div>
            <div class="text-3xl font-bold font-mono text-emerald-400 mt-3">{{ $stats['scan_validated'] }}</div>
            <div class="text-xs text-obsidian-muted mt-1">Con acta física escaneada y sellada</div>
        </a>

        <!-- Soporte Digital Pendiente -->
        <a href="{{ route('admin.inventory.assignments.index', ['sla_status' => 'overdue']) }}" class="bg-obsidian-panel/30 border {{ $stats['scan_overdue'] > 0 ? 'border-rose-500/50 bg-rose-950/10' : 'border-obsidian-border' }} hover:border-rose-400 p-5 rounded-2xl relative overflow-hidden transition group cursor-pointer">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono text-obsidian-muted group-hover:text-rose-300 uppercase tracking-wider transition">Digitalización Pendiente</span>
                <span class="p-2 rounded-xl {{ $stats['scan_overdue'] > 0 ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse' : 'bg-slate-800 text-slate-400 border border-slate-700' }}">
                    <span class="material-symbols-outlined text-xl">emergency</span>
                </span>
            </div>
            <div class="text-3xl font-bold font-mono {{ $stats['scan_overdue'] > 0 ? 'text-rose-400' : 'text-slate-300' }} mt-3">
                {{ $stats['scan_overdue'] }}
            </div>
            <div class="text-xs text-obsidian-muted mt-1">
                {{ $stats['scan_overdue'] > 0 ? 'Requieren subir escaneo' : 'Documentación al día' }}
            </div>
        </a>

        <!-- Movilizaciones / Traslados -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono text-obsidian-muted uppercase tracking-wider">Pases Garitas</span>
                <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    <span class="material-symbols-outlined text-xl">local_shipping</span>
                </span>
            </div>
            <div class="text-3xl font-bold font-mono text-white mt-3">{{ $stats['total_transfers'] }}</div>
            <div class="text-xs text-obsidian-muted mt-1">Pases por puntos de control</div>
        </div>
    </div>

    <!-- BARRA DE FILTROS Y BÚSQUEDA -->
    <div class="bg-obsidian-panel/40 border border-obsidian-border p-4 rounded-2xl backdrop-blur-md">
        <form method="GET" action="{{ route('admin.inventory.assignments.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <!-- Buscador -->
            <div class="relative md:col-span-2">
                <span class="material-symbols-outlined absolute left-3 top-2.5 text-obsidian-muted text-lg">search</span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por código, responsable, cédula, serial o activo..." class="w-full pl-10 pr-4 py-2 bg-obsidian-dark/80 border border-obsidian-border rounded-xl text-sm text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan outline-none transition">
            </div>

            <!-- Filtro Tipo Movimiento -->
            <div>
                <select name="movement_type" class="w-full px-3 py-2 bg-obsidian-dark/80 border border-obsidian-border rounded-xl text-sm text-white focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan outline-none transition" onchange="this.form.submit()">
                    <option value="">Todos los movimientos</option>
                    <option value="assignment" {{ $movementFilter === 'assignment' ? 'selected' : '' }}>Asignación de Activo</option>
                    <option value="return" {{ $movementFilter === 'return' ? 'selected' : '' }}>Devolución de Activo</option>
                    <option value="transfer" {{ $movementFilter === 'transfer' ? 'selected' : '' }}>Pase de Movilización</option>
                    <option value="loan" {{ $movementFilter === 'loan' ? 'selected' : '' }}>Préstamo Temporal</option>
                </select>
            </div>

            <!-- Filtro Soporte Digital -->
            <div>
                <select name="sla_status" class="w-full px-3 py-2 bg-obsidian-dark/80 border border-obsidian-border rounded-xl text-sm text-white focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan outline-none transition" onchange="this.form.submit()">
                    <option value="">Todo Soporte Digital</option>
                    <option value="validated" {{ ($slaFilter ?? '') === 'validated' ? 'selected' : '' }}>Validada (Con Soporte Escaneado)</option>
                    <option value="pending" {{ ($slaFilter ?? '') === 'pending' ? 'selected' : '' }}>Pendiente de Escaneo</option>
                    <option value="overdue" {{ ($slaFilter ?? '') === 'overdue' ? 'selected' : '' }}>Sin Documento Digital</option>
                </select>
            </div>

            <!-- Filtro Sede -->
            <div class="flex gap-2">
                <select name="site_id" class="w-full px-3 py-2 bg-obsidian-dark/80 border border-obsidian-border rounded-xl text-sm text-white focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan outline-none transition" onchange="this.form.submit()">
                    <option value="">Todas las Sedes</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" {{ $siteFilter == $site->id ? 'selected' : '' }}>{{ $site->name }}</option>
                    @endforeach
                </select>
                @if(!empty($search) || !empty($movementFilter) || !empty($siteFilter) || !empty($slaFilter))
                    <a href="{{ route('admin.inventory.assignments.index') }}" class="px-3 py-2 rounded-xl bg-obsidian-dark border border-obsidian-border text-obsidian-muted hover:text-white transition flex items-center justify-center" title="Limpiar filtros">
                        <span class="material-symbols-outlined text-sm">filter_alt_off</span>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- TABLA DE MOVIMIENTOS Y ACTAS -->
    <div class="bg-obsidian-panel/30 border border-obsidian-border rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-obsidian-muted">
                <thead class="bg-obsidian-dark/70 text-xs uppercase font-mono text-obsidian-muted/80 border-b border-obsidian-border">
                    <tr>
                        <th class="px-5 py-3.5">Código / Tipo</th>
                        <th class="px-5 py-3.5">Responsable Asignado</th>
                        <th class="px-5 py-3.5">Activo Principal</th>
                        <th class="px-5 py-3.5">Periféricos</th>
                        <th class="px-5 py-3.5">Sede / Garita</th>
                        <th class="px-5 py-3.5">Fecha</th>
                        <th class="px-5 py-3.5">Soporte Digital</th>
                        <th class="px-5 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/50">
                    @forelse($assignments as $assignment)
                    <tr class="hover:bg-obsidian-panel/50 transition">
                        <!-- Código y Tipo -->
                        <td class="px-5 py-4">
                            <div class="font-mono font-bold text-white text-xs flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm text-obsidian-cyan">description</span>
                                {{ $assignment->assignment_code }}
                            </div>
                            <div class="mt-1">
                                @if($assignment->movement_type === 'assignment')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        ASIGNACIÓN UINF-4.05
                                    </span>
                                @elseif($assignment->movement_type === 'return')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                        DEVOLUCIÓN ACTA
                                    </span>
                                @elseif($assignment->movement_type === 'transfer')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        MOVILIZACIÓN P/C
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-purple-500/10 text-purple-400 border border-purple-500/20">
                                        PRÉSTAMO TEMPORAL
                                    </span>
                                @endif

                                @if($assignment->status === 'superseded')
                                    <span class="ml-1 px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-slate-800 text-slate-400 border border-slate-700" title="Acta sustituida por renovación de hardware">
                                        HISTÓRICA
                                    </span>
                                @endif
                            </div>
                        </td>

                        <!-- Responsable Asignado -->
                        <td class="px-5 py-4">
                            <div class="font-semibold text-white">{{ $assignment->custodian_name }}</div>
                            <div class="text-xs text-obsidian-muted font-mono mt-0.5">
                                C.I: {{ $assignment->custodian_id_number ?? 'N/A' }} 
                                @if($assignment->phone_extension)
                                    &bull; Ext: {{ $assignment->phone_extension }}
                                @endif
                            </div>
                            <div class="text-[11px] text-obsidian-muted/80">
                                {{ $assignment->custodian_department ?? 'N/A' }}
                            </div>
                        </td>

                        <!-- Activo Principal -->
                        <td class="px-5 py-4">
                            @if($assignment->asset)
                                <a href="{{ route('admin.inventory.assets.show', $assignment->asset->id) }}" class="font-semibold text-obsidian-cyan hover:underline flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">desktop_windows</span>
                                    {{ $assignment->asset->name }}
                                </a>
                                <div class="text-xs text-obsidian-muted font-mono mt-0.5">
                                    S/N: {{ $assignment->asset->serial_number ?? 'S/N' }}
                                </div>
                                <div class="text-[11px] text-obsidian-muted/80">
                                    {{ $assignment->asset->brand }} {{ $assignment->asset->model }}
                                </div>
                            @else
                                <span class="text-xs text-obsidian-muted italic">Activo desincorporado</span>
                            @endif
                        </td>

                        <!-- Periféricos -->
                        <td class="px-5 py-4">
                            @php
                                $peripherals = is_array($assignment->peripherals_json) ? $assignment->peripherals_json : [];
                            @endphp
                            @if(count($peripherals) > 0)
                                <div class="flex flex-wrap gap-1 max-w-[200px]">
                                    @foreach($peripherals as $p)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-obsidian-dark border border-obsidian-border text-white/80" title="{{ $p['brand'] ?? '' }} {{ $p['model'] ?? '' }} (S/N: {{ $p['serial_number'] ?? 'N/A' }})">
                                            {{ $p['type'] ?? 'Item' }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs text-obsidian-muted/60">Solo equipo principal</span>
                            @endif
                        </td>

                        <!-- Sede / Garita -->
                        <td class="px-5 py-4">
                            <div class="text-white text-xs font-medium">
                                {{ $assignment->site->name ?? $assignment->checkpoint_name ?? 'Sede Principal' }}
                            </div>
                            @if($assignment->gate_operator_name)
                                <div class="text-[10px] text-obsidian-muted mt-0.5">
                                    Garita: {{ $assignment->gate_operator_name }}
                                </div>
                            @endif
                        </td>

                        <!-- Fecha -->
                        <td class="px-5 py-4 font-mono text-xs">
                            <div class="text-white">{{ $assignment->assigned_at ? $assignment->assigned_at->format('d/m/Y') : 'N/A' }}</div>
                            <div class="text-obsidian-muted text-[11px]">{{ $assignment->assigned_at ? $assignment->assigned_at->format('H:i') : '' }}</div>
                        </td>

                        <!-- Soporte Digital -->
                        <td class="px-5 py-4">
                            @if($assignment->movement_type === 'assignment')
                                @if($assignment->has_signed_scan)
                                    <div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                            <span class="material-symbols-outlined text-[12px]">verified</span>
                                            <span>Validada con Soporte</span>
                                        </span>
                                        <div class="mt-1">
                                            <a href="{{ route('admin.inventory.assignments.view-scan', $assignment->id) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-mono text-cyan-400 hover:text-cyan-300 hover:underline">
                                                <span class="material-symbols-outlined text-xs">picture_as_pdf</span>
                                                <span>Ver PDF Escaneado</span>
                                            </a>
                                        </div>
                                    </div>
                                @elseif($assignment->scan_compliance_status === 'overdue')
                                    <div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse">
                                            <span class="material-symbols-outlined text-[12px]">emergency</span>
                                            <span>Digitalización Pendiente</span>
                                        </span>
                                        <div class="text-[10px] text-rose-400/80 font-mono mt-0.5">
                                            {{ $assignment->days_since_assigned }} días transcurridos
                                        </div>
                                        @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign'))
                                        <div class="mt-1">
                                            <button type="button" onclick="openUploadScanModal({{ $assignment->id }}, '{{ addslashes($assignment->assignment_code) }}', '{{ addslashes($assignment->custodian_name) }}')" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-rose-500/20 hover:bg-rose-500 hover:text-white border border-rose-500/40 text-rose-300 text-[10px] font-mono transition cursor-pointer">
                                                 <span class="material-symbols-outlined text-xs">upload_file</span>
                                                 <span>Subir Escaneo</span>
                                            </button>
                                        </div>
                                        @endif
                                    </div>
                                @else
                                    <div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                            <span class="material-symbols-outlined text-[12px]">schedule</span>
                                            <span>En Proceso</span>
                                        </span>
                                        <div class="text-[10px] text-obsidian-muted font-mono mt-0.5">
                                            {{ $assignment->days_since_assigned }} días transcurridos
                                        </div>
                                        @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign'))
                                        <div class="mt-1">
                                            <button type="button" onclick="openUploadScanModal({{ $assignment->id }}, '{{ addslashes($assignment->assignment_code) }}', '{{ addslashes($assignment->custodian_name) }}')" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-500/20 hover:bg-amber-500 hover:text-black border border-amber-500/40 text-amber-300 text-[10px] font-mono transition cursor-pointer">
                                                <span class="material-symbols-outlined text-xs">upload_file</span>
                                                <span>Adjuntar Escaneo</span>
                                            </button>
                                        </div>
                                        @endif
                                    </div>
                                @endif
                            @else
                                <span class="text-xs text-obsidian-muted/60 font-mono">No aplica</span>
                            @endif
                        </td>

                        <!-- Acciones -->
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <!-- Ver Ficha Detallada -->
                                <a href="{{ route('admin.inventory.assignments.show', $assignment->id) }}" class="p-1.5 rounded-lg bg-obsidian-dark hover:bg-obsidian-panel border border-obsidian-border text-obsidian-cyan transition" title="Ver Detalle y Opciones">
                                    <span class="material-symbols-outlined text-base">visibility</span>
                                </a>

                                <!-- Ver PDF Escaneado si existe -->
                                @if($assignment->has_signed_scan)
                                <a href="{{ route('admin.inventory.assignments.view-scan', $assignment->id) }}" target="_blank" class="p-1.5 rounded-lg bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 text-purple-400 transition" title="Ver Acta Física Escaneada (PDF)">
                                    <span class="material-symbols-outlined text-base">file_present</span>
                                </a>
                                @elseif($assignment->movement_type === 'assignment' && (auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign')))
                                <button type="button" onclick="openUploadScanModal({{ $assignment->id }}, '{{ addslashes($assignment->assignment_code) }}', '{{ addslashes($assignment->custodian_name) }}')" class="p-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 text-amber-400 transition cursor-pointer" title="Subir Hoja Física Escaneada">
                                    <span class="material-symbols-outlined text-base">upload_file</span>
                                </button>
                                @endif

                                <!-- Imprimir / Ver Acta UINF-4.05 -->
                                <a href="{{ route('admin.inventory.assignments.pdf', $assignment->id) }}" target="_blank" class="p-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 transition" title="Imprimir / Ver Acta UINF-4.05">
                                    <span class="material-symbols-outlined text-base">print</span>
                                </a>

                                <!-- Descargar PDF UINF-4.05 -->
                                <a href="{{ route('admin.inventory.assignments.pdf', ['assignment' => $assignment->id, 'download' => 1]) }}" class="p-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 transition" title="Descargar PDF UINF-4.05">
                                    <span class="material-symbols-outlined text-base">download</span>
                                </a>

                                <!-- Imprimir Pase de Movilización -->
                                <a href="{{ route('admin.inventory.assignments.pdf-movilizacion', $assignment->id) }}" target="_blank" class="p-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 text-amber-400 transition" title="Imprimir Pase Punto de Control">
                                    <span class="material-symbols-outlined text-base">badge</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-obsidian-muted">
                            <span class="material-symbols-outlined text-4xl mb-2 text-obsidian-muted/40">assignment_late</span>
                            <p class="font-medium text-white/80">No se encontraron actas ni movimientos registrados.</p>
                            <p class="text-xs text-obsidian-muted mt-1">Utiliza el botón superior para emitir una nueva acta UINF-4.05 o registrar un pase de movilización.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($assignments->hasPages())
        <div class="p-4 border-t border-obsidian-border bg-obsidian-dark/40">
            {{ $assignments->links() }}
        </div>
        @endif
    </div>

</div>

<!-- MODAL: SUBIR HOJA FÍSICA ESCANEADA A ACTA -->
<div id="uploadScanModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-[#0b1322] border border-cyan-500/40 rounded-2xl max-w-lg w-full flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden shadow-2xl relative">
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between shrink-0 bg-[#061527]">
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">
                    <span class="material-symbols-outlined text-xl">upload_file</span>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-white">Subir Acta Física Firmada y Escaneada</h3>
                    <p class="text-[11px] font-mono text-obsidian-muted">Digitalización con firmas y sellos</p>
                </div>
            </div>
            <button type="button" onclick="closeUploadScanModal()" class="text-obsidian-muted hover:text-white transition p-1 cursor-pointer">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="uploadScanForm" method="POST" action="" enctype="multipart/form-data" class="flex flex-col flex-1 min-h-0 overflow-hidden m-0">
            @csrf
            <div class="p-5 space-y-4 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Código de Acta</label>
                    <input type="text" id="uploadModalCode" disabled class="w-full px-3 py-1.5 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-cyan-300 font-bold">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Responsable Asignado</label>
                    <input type="text" id="uploadModalCustodian" disabled class="w-full px-3 py-1.5 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Documento Escaneado (PDF o Imagen) <span class="text-cyan-400">*</span></label>
                    <input type="file" name="scan_file" required accept=".pdf,image/jpeg,image/png" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-mono file:bg-cyan-500/20 file:text-cyan-300 hover:file:bg-cyan-500 hover:file:text-black">
                    <span class="text-[10px] text-obsidian-muted mt-1 block">Formatos permitidos: PDF, JPG, PNG (máx. 20 MB).</span>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Observaciones de Digitalización (Opcional)</label>
                    <textarea name="observations" rows="2" placeholder="Notas sobre el estado de la firma, sellos o fecha de recepción..." class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none"></textarea>
                </div>
            </div>

            <div class="p-3.5 px-6 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0 bg-[#061527]">
                <button type="button" onclick="closeUploadScanModal()" class="px-4 py-2 rounded-xl bg-obsidian-dark border border-obsidian-border text-obsidian-muted hover:text-white text-xs font-mono transition cursor-pointer">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">cloud_upload</span>
                    <span>Guardar y Validar Acta</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openUploadScanModal(assignmentId, code, custodianName) {
        const form = document.getElementById('uploadScanForm');
        form.action = '/admin/inventory/assignments/' + assignmentId + '/upload-scan';
        document.getElementById('uploadModalCode').value = code || '';
        document.getElementById('uploadModalCustodian').value = custodianName || '';
        document.getElementById('uploadScanModal').classList.remove('hidden');
    }

    function closeUploadScanModal() {
        document.getElementById('uploadScanModal').classList.add('hidden');
    }
</script>
@endsection
