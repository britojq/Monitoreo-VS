@extends('layouts.admin')

@section('page_title', 'Orden de Servicio ' . $ticket->ticket_code . ' (Mantenimiento TI)')

@section('admin_content')
<div class="space-y-6">

    <!-- ENCABEZADO Y ACCIONES -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.devices.index') }}" class="text-xs font-mono text-obsidian-cyan hover:underline">&larr; Equipos &amp; Activos</a>
                <span class="text-obsidian-muted">&bull;</span>
                <a href="{{ route('admin.inventory.maintenance.index') }}" class="text-xs font-mono text-obsidian-muted hover:text-white hover:underline">Mesa de Mantenimiento</a>
                <span class="text-obsidian-muted">&bull;</span>
                <span class="text-xs font-mono text-white/80">Orden: <strong class="text-white">{{ $ticket->ticket_code }}</strong></span>
            </div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <span class="material-symbols-outlined text-amber-400 text-3xl">build</span>
                {{ $ticket->title }}
            </h1>
            <div class="flex items-center gap-2 mt-1">
                @if($ticket->status === 'completed')
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        FINALIZADO EL {{ $ticket->completed_at ? $ticket->completed_at->format('d/m/Y H:i') : '' }}
                    </span>
                @elseif($ticket->status === 'in_progress')
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-amber-500/10 text-amber-400 border border-amber-500/20 animate-pulse">
                        EN REPARACIÓN / TALLER
                    </span>
                @else
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-blue-500/10 text-blue-400 border border-blue-500/20">
                        PROGRAMADO PARA {{ $ticket->scheduled_at ? $ticket->scheduled_at->format('d/m/Y') : '' }}
                    </span>
                @endif
                <span class="text-xs text-obsidian-muted">&bull; Técnico: <strong class="text-white">{{ $ticket->technician->name ?? 'Sin asignar' }}</strong></span>
            </div>
        </div>

        <!-- ACCIONES RÁPIDAS -->
        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.maintenance'))
            <a href="{{ route('admin.inventory.maintenance.edit', $ticket->id) }}" class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold transition flex items-center gap-2 text-xs shadow-lg shadow-amber-500/20">
                <span class="material-symbols-outlined text-base">edit</span>
                Actualizar / Resolver Orden
            </a>
            @endif

            <a href="{{ route('admin.inventory.maintenance.pdf', $ticket->id) }}" target="_blank" class="px-4 py-2.5 rounded-xl bg-obsidian-dark hover:bg-obsidian-panel border border-obsidian-border text-white font-semibold transition flex items-center gap-1.5 text-xs">
                <span class="material-symbols-outlined text-base">print</span>
                Imprimir Orden
            </a>

            <a href="{{ route('admin.inventory.maintenance.pdf', ['ticket' => $ticket->id, 'download' => 1]) }}" class="px-3 py-2.5 rounded-xl bg-obsidian-dark hover:bg-obsidian-panel border border-emerald-500/40 text-emerald-400 font-semibold transition flex items-center gap-1.5 text-xs">
                <span class="material-symbols-outlined text-base">download</span>
                PDF
            </a>
        </div>
    </div>

    <!-- TARJETAS DE DETALLE -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Servicio -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl">
            <div class="text-xs font-mono uppercase text-amber-400 font-bold flex items-center gap-1.5 mb-3">
                <span class="material-symbols-outlined text-base">tune</span>
                Parámetros del Servicio
            </div>
            <div class="space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Tipo:</span>
                    <strong class="text-white uppercase font-mono">{{ $ticket->type_label }}</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Prioridad:</span>
                    <strong class="text-amber-400 uppercase font-mono">{{ $ticket->priority }}</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Garantía Aplicada:</span>
                    <span class="text-white">{{ $ticket->warranty_applied ? 'SÍ (Cubierto por garantía)' : 'NO' }}</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-obsidian-border/50">
                    <span class="text-obsidian-muted">Costo Registrado:</span>
                    <strong class="text-emerald-400 font-mono text-sm">${{ number_format($ticket->cost, 2) }}</strong>
                </div>
            </div>
        </div>

        <!-- Activo -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl">
            <div class="text-xs font-mono uppercase text-obsidian-cyan font-bold flex items-center gap-1.5 mb-3">
                <span class="material-symbols-outlined text-base">desktop_windows</span>
                Activo Intervenido
            </div>
            <div class="text-base font-bold text-white flex items-center gap-2">
                <a href="{{ route('admin.devices.index', ['search' => $ticket->asset->ip ?: ($ticket->asset->serial_number ?: $ticket->asset->asset_tag)]) }}" class="hover:underline text-obsidian-cyan" title="Ver equipo en Equipos & Activos">
                    {{ $ticket->asset->name }}
                </a>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-obsidian-dark border border-obsidian-border text-obsidian-cyan">
                    {{ $ticket->asset->asset_tag ?? 'S/T' }}
                </span>
            </div>
            <div class="text-xs text-obsidian-muted font-mono mt-1">Serial: {{ $ticket->asset->serial_number ?? 'S/N' }}</div>
            <div class="text-xs text-obsidian-muted mt-0.5">{{ $ticket->asset->brand }} {{ $ticket->asset->model }}</div>
            <div class="text-xs text-obsidian-muted mt-1 pt-2 border-t border-obsidian-border/50">
                Asignado a: <strong class="text-white">{{ $ticket->asset->custodian_name ?? 'En Stock TI' }}</strong>
            </div>
        </div>

        <!-- Tiempos -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl">
            <div class="text-xs font-mono uppercase text-purple-400 font-bold flex items-center gap-1.5 mb-3">
                <span class="material-symbols-outlined text-base">schedule</span>
                Cronograma de Atención
            </div>
            <div class="space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Programado:</span>
                    <span class="font-mono text-white">{{ $ticket->scheduled_at ? $ticket->scheduled_at->format('d/m/Y H:i') : 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Iniciado en Taller:</span>
                    <span class="font-mono text-amber-400">{{ $ticket->started_at ? $ticket->started_at->format('d/m/Y H:i') : 'No iniciado' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-obsidian-muted">Culminado:</span>
                    <span class="font-mono text-emerald-400">{{ $ticket->completed_at ? $ticket->completed_at->format('d/m/Y H:i') : 'En espera' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- NOTAS TÉCNICAS Y REPUESTOS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Diagnóstico y Resolución -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl space-y-4">
            <div>
                <div class="text-xs font-mono uppercase text-obsidian-muted font-bold mb-2">Diagnóstico / Motivo Inicial</div>
                <div class="p-3 rounded-xl bg-obsidian-dark/60 border border-obsidian-border text-xs text-white/90 leading-relaxed">
                    {{ $ticket->description ?? 'Sin descripción adicional registrada.' }}
                </div>
            </div>

            <div>
                <div class="text-xs font-mono uppercase text-emerald-400 font-bold mb-2">Resolución Técnica Final</div>
                <div class="p-3 rounded-xl bg-obsidian-dark/60 border border-obsidian-border text-xs text-white/90 leading-relaxed min-h-[60px]">
                    {{ $ticket->resolution_notes ?? 'En proceso. Las notas técnicas y pruebas de operatividad se registrarán al culminar las labores.' }}
                </div>
            </div>
        </div>

        <!-- Repuestos / Partes Sustituidas -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl">
            <div class="text-xs font-mono uppercase text-obsidian-cyan font-bold flex items-center gap-1.5 mb-3">
                <span class="material-symbols-outlined text-base">extension</span>
                Partes y Componentes Sustituidos
            </div>
            @php $parts = is_array($ticket->parts_replaced_json) ? $ticket->parts_replaced_json : []; @endphp
            @if(count($parts) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-obsidian-muted">
                        <thead class="bg-obsidian-dark/80 text-[10px] uppercase font-mono border-b border-obsidian-border">
                            <tr>
                                <th class="px-3 py-2">Componente</th>
                                <th class="px-3 py-2">Serial</th>
                                <th class="px-3 py-2 text-right">Costo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-obsidian-border/40">
                            @foreach($parts as $p)
                                <tr>
                                    <td class="px-3 py-2 text-white font-bold">{{ $p['name'] ?? 'Repuesto' }}</td>
                                    <td class="px-3 py-2 font-mono">{{ $p['serial'] ?? 'N/A' }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-emerald-400">${{ number_format($p['cost'] ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-xs text-obsidian-muted">
                    No se requirió el reemplazo de componentes físicos en esta intervención.
                </div>
            @endif
        </div>
    </div>

    <!-- VISTA PREVIA EMBEBIDA DE LA ORDEN DE SERVICIO -->
    <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-4">
            <div class="text-xs font-mono uppercase text-obsidian-cyan font-bold flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base">visibility</span>
                Vista Previa de la Orden de Servicio
            </div>
            <a href="{{ route('admin.inventory.maintenance.pdf', $ticket->id) }}" target="_blank" class="text-xs text-obsidian-cyan hover:underline flex items-center gap-1">
                Abrir en ventana completa &nearr;
            </a>
        </div>
        <div class="bg-white rounded-xl overflow-hidden shadow-inner border border-obsidian-border" style="height: 600px;">
            <iframe src="{{ route('admin.inventory.maintenance.pdf', $ticket->id) }}" class="w-full h-full border-none"></iframe>
        </div>
    </div>

</div>
@endsection
