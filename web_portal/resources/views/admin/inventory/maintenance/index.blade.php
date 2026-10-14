@extends('layouts.admin')

@section('page_title', 'Mantenimiento Técnico TI & Órdenes de Servicio')

@section('admin_content')
<div class="space-y-4">
    <!-- ENCABEZADO Y ACCIONES -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-obsidian-panel/40 border border-obsidian-border p-5 rounded-2xl backdrop-blur-md">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono uppercase tracking-widest text-obsidian-cyan font-bold">Fase 5 &bull; Soporte Técnico & ITAM</span>
                <span class="text-obsidian-muted">&bull;</span>
                <span class="text-xs font-mono text-amber-400">Órdenes de Servicio & Garantías</span>
            </div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <span class="material-symbols-outlined text-amber-400 text-3xl">build_circle</span>
                Módulo de Mantenimiento y Ciclo de Vida
            </h1>
            <p class="text-sm text-obsidian-muted mt-1">
                Control de servicios preventivos, correctivos, reemplazo de partes de hardware y emisión de órdenes de servicio técnico.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.devices.index') }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl border border-obsidian-border bg-obsidian-panel text-obsidian-muted hover:text-white transition text-xs font-mono shadow-xs" title="Volver a Equipos & Activos">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                <span>Equipos</span>
            </a>
            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.maintenance'))
            <div class="relative group">
                <a href="{{ route('admin.inventory.maintenance.create') }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black transition text-xs font-mono font-bold shadow-md shadow-amber-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-base">add_task</span>
                    <span class="font-bold text-sm leading-none">+</span>
                </a>
                <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                    <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-amber-500/50 shadow-xl shadow-amber-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs text-amber-400">add_task</span>
                        <span class="font-bold text-amber-200">Abrir Orden de Servicio</span>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- PESTAÑAS EQUIPOS & MANTENIMIENTO (PANEL DE NAVEGACIÓN COMPACTO) -->
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

    <!-- TARJETAS KPIS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Programados -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono text-obsidian-muted uppercase tracking-wider">Programados</span>
                <span class="p-2 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20">
                    <span class="material-symbols-outlined text-xl">event_upcoming</span>
                </span>
            </div>
            <div class="text-3xl font-bold font-mono text-white mt-3">{{ $stats['scheduled'] }}</div>
            <div class="text-xs text-obsidian-muted mt-1">Servicios en agenda técnica</div>
        </div>

        <!-- En Proceso -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono text-obsidian-muted uppercase tracking-wider">En Reparación / Taller</span>
                <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    <span class="material-symbols-outlined text-xl">engineering</span>
                </span>
            </div>
            <div class="text-3xl font-bold font-mono text-amber-400 mt-3">{{ $stats['in_progress'] }}</div>
            <div class="text-xs text-obsidian-muted mt-1">Equipos en intervención física</div>
        </div>

        <!-- Finalizados -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono text-obsidian-muted uppercase tracking-wider">Finalizados</span>
                <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="material-symbols-outlined text-xl">task_alt</span>
                </span>
            </div>
            <div class="text-3xl font-bold font-mono text-emerald-400 mt-3">{{ $stats['completed'] }}</div>
            <div class="text-xs text-obsidian-muted mt-1">Órdenes resueltas con éxito</div>
        </div>

        <!-- Inversión / Costo -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono text-obsidian-muted uppercase tracking-wider">Inversión en Repuestos</span>
                <span class="p-2 rounded-xl bg-purple-500/10 text-purple-400 border border-purple-500/20">
                    <span class="material-symbols-outlined text-xl">payments</span>
                </span>
            </div>
            <div class="text-3xl font-bold font-mono text-white mt-3">${{ number_format($stats['total_cost'], 2) }}</div>
            <div class="text-xs text-obsidian-muted mt-1">Costo total de partes aplicadas</div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="bg-obsidian-panel/40 border border-obsidian-border p-4 rounded-2xl backdrop-blur-md">
        <form method="GET" action="{{ route('admin.inventory.maintenance.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <!-- Buscador -->
            <div class="relative md:col-span-1">
                <span class="material-symbols-outlined absolute left-3 top-2.5 text-obsidian-muted text-lg">search</span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Código, título, serial, técnico..." class="w-full pl-10 pr-4 py-2 bg-obsidian-dark/80 border border-obsidian-border rounded-xl text-sm text-white placeholder-obsidian-muted/60 focus:border-amber-400 focus:ring-1 focus:ring-amber-400 outline-none transition">
            </div>

            <!-- Filtro Estado -->
            <div>
                <select name="status" class="w-full px-3 py-2 bg-obsidian-dark/80 border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none transition" onchange="this.form.submit()">
                    <option value="">Todos los Estados</option>
                    <option value="scheduled" {{ $statusFilter === 'scheduled' ? 'selected' : '' }}>Programado</option>
                    <option value="in_progress" {{ $statusFilter === 'in_progress' ? 'selected' : '' }}>En Proceso</option>
                    <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Finalizado</option>
                    <option value="canceled" {{ $statusFilter === 'canceled' ? 'selected' : '' }}>Cancelado</option>
                </select>
            </div>

            <!-- Filtro Tipo -->
            <div>
                <select name="type" class="w-full px-3 py-2 bg-obsidian-dark/80 border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none transition" onchange="this.form.submit()">
                    <option value="">Todos los Tipos</option>
                    <option value="preventive" {{ $typeFilter === 'preventive' ? 'selected' : '' }}>Preventivo</option>
                    <option value="corrective" {{ $typeFilter === 'corrective' ? 'selected' : '' }}>Correctivo</option>
                </select>
            </div>

            <!-- Filtro Prioridad -->
            <div class="flex gap-2">
                <select name="priority" class="w-full px-3 py-2 bg-obsidian-dark/80 border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none transition" onchange="this.form.submit()">
                    <option value="">Todas las Prioridades</option>
                    <option value="low" {{ $priorityFilter === 'low' ? 'selected' : '' }}>Baja</option>
                    <option value="medium" {{ $priorityFilter === 'medium' ? 'selected' : '' }}>Media</option>
                    <option value="high" {{ $priorityFilter === 'high' ? 'selected' : '' }}>Alta</option>
                    <option value="critical" {{ $priorityFilter === 'critical' ? 'selected' : '' }}>Crítica</option>
                </select>
                @if(!empty($search) || !empty($statusFilter) || !empty($typeFilter) || !empty($priorityFilter))
                    <a href="{{ route('admin.inventory.maintenance.index') }}" class="px-3 py-2 rounded-xl bg-obsidian-dark border border-obsidian-border text-obsidian-muted hover:text-white transition flex items-center justify-center" title="Limpiar filtros">
                        <span class="material-symbols-outlined text-sm">filter_alt_off</span>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- TABLA DE TICKETS -->
    <div class="bg-obsidian-panel/30 border border-obsidian-border rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-obsidian-muted">
                <thead class="bg-obsidian-dark/70 text-xs uppercase font-mono text-obsidian-muted/80 border-b border-obsidian-border">
                    <tr>
                        <th class="px-5 py-3.5">Código / Prioridad</th>
                        <th class="px-5 py-3.5">Activo de TI</th>
                        <th class="px-5 py-3.5">Tipo & Título del Servicio</th>
                        <th class="px-5 py-3.5">Técnico Asignado</th>
                        <th class="px-5 py-3.5">Estado</th>
                        <th class="px-5 py-3.5">Fecha</th>
                        <th class="px-5 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/50">
                    @forelse($tickets as $ticket)
                    <tr class="hover:bg-obsidian-panel/50 transition">
                        <!-- Código y Prioridad -->
                        <td class="px-5 py-4">
                            <div class="font-mono font-bold text-white text-xs flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm text-amber-400">build</span>
                                {{ $ticket->ticket_code }}
                            </div>
                            <div class="mt-1">
                                @if($ticket->priority === 'critical')
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold font-mono bg-red-500/20 text-red-400 border border-red-500/30">CRÍTICA</span>
                                @elseif($ticket->priority === 'high')
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold font-mono bg-orange-500/20 text-orange-400 border border-orange-500/30">ALTA</span>
                                @elseif($ticket->priority === 'medium')
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold font-mono bg-amber-500/20 text-amber-400 border border-amber-500/30">MEDIA</span>
                                @else
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-obsidian-dark text-obsidian-muted border border-obsidian-border">BAJA</span>
                                @endif
                            </div>
                        </td>

                        <!-- Activo -->
                        <td class="px-5 py-4">
                            <a href="{{ route('admin.inventory.assets.show', $ticket->asset->id) }}" class="font-semibold text-obsidian-cyan hover:underline flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">desktop_windows</span>
                                {{ $ticket->asset->name }}
                            </a>
                            <div class="text-xs text-obsidian-muted font-mono mt-0.5">
                                S/N: {{ $ticket->asset->serial_number ?? 'S/N' }}
                            </div>
                            <div class="text-[11px] text-obsidian-muted">
                                {{ $ticket->asset->monitoredSite->name ?? 'Sede Principal' }}
                            </div>
                        </td>

                        <!-- Tipo y Título -->
                        <td class="px-5 py-4">
                            <div class="font-bold text-white text-sm">{{ $ticket->title }}</div>
                            <div class="text-xs text-obsidian-muted mt-0.5 flex items-center gap-1">
                                <span class="px-1.5 py-0.2 rounded text-[10px] font-mono uppercase {{ $ticket->type === 'corrective' ? 'bg-rose-500/10 text-rose-400' : 'bg-blue-500/10 text-blue-400' }}">
                                    {{ $ticket->type_label }}
                                </span>
                                @if($ticket->cost > 0)
                                    &bull; <span class="font-mono text-emerald-400 font-bold">${{ number_format($ticket->cost, 2) }}</span>
                                @endif
                            </div>
                        </td>

                        <!-- Técnico -->
                        <td class="px-5 py-4">
                            <div class="text-white text-xs font-medium">{{ $ticket->technician->name ?? 'Sin asignar' }}</div>
                            <div class="text-[10px] text-obsidian-muted">{{ $ticket->technician->email ?? '' }}</div>
                        </td>

                        <!-- Estado -->
                        <td class="px-5 py-4">
                            @if($ticket->status === 'completed')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    FINALIZADO
                                </span>
                            @elseif($ticket->status === 'in_progress')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-amber-500/10 text-amber-400 border border-amber-500/20 animate-pulse">
                                    EN PROCESO
                                </span>
                            @elseif($ticket->status === 'canceled')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-red-500/10 text-red-400 border border-red-500/20">
                                    CANCELADO
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                    PROGRAMADO
                                </span>
                            @endif
                        </td>

                        <!-- Fecha -->
                        <td class="px-5 py-4 font-mono text-xs">
                            <div class="text-white">{{ $ticket->scheduled_at ? $ticket->scheduled_at->format('d/m/Y') : 'N/A' }}</div>
                            @if($ticket->completed_at)
                                <div class="text-[10px] text-emerald-400 mt-0.5">Fin: {{ $ticket->completed_at->format('d/m/Y') }}</div>
                            @endif
                        </td>

                        <!-- Acciones -->
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('admin.inventory.maintenance.show', $ticket->id) }}" class="p-1.5 rounded-lg bg-obsidian-dark hover:bg-obsidian-panel border border-obsidian-border text-obsidian-cyan transition" title="Ver Detalle de la Orden">
                                    <span class="material-symbols-outlined text-base">visibility</span>
                                </a>
                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.maintenance'))
                                <a href="{{ route('admin.inventory.maintenance.edit', $ticket->id) }}" class="p-1.5 rounded-lg bg-obsidian-dark hover:bg-obsidian-panel border border-obsidian-border text-amber-400 transition" title="Editar / Actualizar Estado">
                                    <span class="material-symbols-outlined text-base">edit</span>
                                </a>
                                @endif
                                <a href="{{ route('admin.inventory.maintenance.pdf', $ticket->id) }}" target="_blank" class="p-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 transition" title="Imprimir Orden de Servicio">
                                    <span class="material-symbols-outlined text-base">print</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-obsidian-muted">
                            <span class="material-symbols-outlined text-4xl mb-2 text-obsidian-muted/40">build_circle</span>
                            <p class="font-medium text-white/80">No hay órdenes de servicio ni mantenimientos registrados.</p>
                            <p class="text-xs text-obsidian-muted mt-1">Utiliza el botón superior para programar un servicio técnico.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
        <div class="p-4 border-t border-obsidian-border bg-obsidian-dark/40">
            {{ $tickets->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
