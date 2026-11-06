@extends('layouts.admin')

@section('page_title', 'Actualizar Ticket ' . $ticket->ticket_code . ' (Mantenimiento TI)')

@section('admin_content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- ENCABEZADO -->
    <div class="flex items-center justify-between bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.inventory.maintenance.show', $ticket->id) }}" class="text-xs font-mono text-obsidian-cyan hover:underline">&larr; Volver al Ticket</a>
                <span class="text-obsidian-muted">&bull;</span>
                <span class="text-xs font-mono text-amber-400">Actualizar Estado & Resolución</span>
            </div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <span class="material-symbols-outlined text-amber-400 text-3xl">edit_note</span>
                Orden {{ $ticket->ticket_code }}: {{ $ticket->title }}
            </h1>
            <p class="text-sm text-obsidian-muted mt-1">
                Activo: <strong class="text-white">{{ $ticket->asset->name }}</strong> [{{ $ticket->asset->asset_tag ?? 'S/T' }}] &bull; S/N: {{ $ticket->asset->serial_number ?? 'S/N' }}
            </p>
        </div>
    </div>

    <!-- FORMULARIO -->
    <form method="POST" action="{{ route('admin.inventory.maintenance.update', $ticket->id) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md space-y-4">
            <!-- Estado y Prioridad -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Estado de la Orden *</label>
                    <select name="status" required class="w-full px-3 py-2 bg-obsidian-dark border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none transition font-bold">
                        <option value="scheduled" {{ $ticket->status === 'scheduled' ? 'selected' : '' }}>🔵 Programado (En espera de atención)</option>
                        <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>🟡 En Proceso (En taller / Intervención)</option>
                        <option value="completed" {{ $ticket->status === 'completed' ? 'selected' : '' }}>🟢 Finalizado (Servicio resuelto con éxito)</option>
                        <option value="canceled" {{ $ticket->status === 'canceled' ? 'selected' : '' }}>🔴 Cancelado (Desestimado o suspendido)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Nivel de Prioridad *</label>
                    <select name="priority" required class="w-full px-3 py-2 bg-obsidian-dark border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none transition">
                        <option value="low" {{ $ticket->priority === 'low' ? 'selected' : '' }}>Baja</option>
                        <option value="medium" {{ $ticket->priority === 'medium' ? 'selected' : '' }}>Media</option>
                        <option value="high" {{ $ticket->priority === 'high' ? 'selected' : '' }}>Alta</option>
                        <option value="critical" {{ $ticket->priority === 'critical' ? 'selected' : '' }}>Crítica</option>
                    </select>
                </div>
            </div>

            <!-- Título y Técnico -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Título del Servicio *</label>
                    <input type="text" name="title" value="{{ old('title', $ticket->title) }}" required class="w-full px-3 py-2 bg-obsidian-dark border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Técnico Responsable</label>
                    <select name="technician_user_id" class="w-full px-3 py-2 bg-obsidian-dark border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none transition">
                        <option value="">-- Sin asignar --</option>
                        @foreach($technicians as $tech)
                            <option value="{{ $tech->id }}" {{ $ticket->technician_user_id == $tech->id ? 'selected' : '' }}>
                                {{ $tech->name }} ({{ ucfirst($tech->role) }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Diagnóstico Inicial -->
            <div>
                <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Diagnóstico / Motivo Inicial</label>
                <textarea name="description" rows="2" class="w-full px-3 py-2 bg-obsidian-dark border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none">{{ old('description', $ticket->description) }}</textarea>
            </div>

            <!-- Notas de Resolución Técnica -->
            <div>
                <label class="block text-xs font-mono text-emerald-400 uppercase mb-1 font-bold">Resolución Técnica y Pruebas Realizadas</label>
                <textarea name="resolution_notes" rows="4" placeholder="Describe detalladamente los trabajos técnicos ejecutados, pruebas de operatividad y diagnóstico final..." class="w-full px-3 py-2 bg-obsidian-dark border border-emerald-500/40 rounded-xl text-sm text-white focus:border-emerald-400 outline-none">{{ old('resolution_notes', $ticket->resolution_notes) }}</textarea>
            </div>

            <!-- Costo y Garantía -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-obsidian-border/50">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Costo Total de Repuestos / Intervención ($ USD)</label>
                    <input type="number" step="0.01" name="cost" id="totalCostInput" value="{{ old('cost', $ticket->cost) }}" class="w-48 px-3 py-2 bg-obsidian-dark border border-obsidian-border rounded-xl text-sm text-white font-mono focus:border-amber-400 outline-none">
                </div>

                <div class="flex items-center pt-5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="warranty_applied" value="1" {{ $ticket->warranty_applied ? 'checked' : '' }} class="rounded text-amber-500 focus:ring-amber-500">
                        <span class="text-xs text-white">Servicio cubierto por Garantía de Proveedor / Fabricante</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- PARTES Y REPUESTOS SUSTITUÍDOS -->
        <div class="bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-obsidian-cyan">extension</span>
                        Partes y Repuestos Sustituidos
                    </h2>
                    <p class="text-xs text-obsidian-muted mt-0.5">Al finalizar la orden, las partes registradas aquí se reflejarán en el historial inmutable de hardware del equipo.</p>
                </div>
                <button type="button" onclick="addPartRow()" class="px-3 py-1.5 rounded-lg bg-obsidian-cyan/10 hover:bg-obsidian-cyan/20 border border-obsidian-cyan/30 text-xs text-obsidian-cyan font-bold transition flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">add</span>
                    + Agregar Repuesto
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-obsidian-muted" id="partsTable">
                    <thead class="bg-obsidian-dark/80 text-[10px] uppercase font-mono border-b border-obsidian-border">
                        <tr>
                            <th class="px-3 py-2 w-1/2">Descripción de la Parte</th>
                            <th class="px-3 py-2 w-1/4">Serial / Lote</th>
                            <th class="px-3 py-2 w-1/4">Costo ($)</th>
                            <th class="px-3 py-2 text-center w-10">Quitar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-obsidian-border/40" id="partsBody">
                        @php $parts = is_array($ticket->parts_replaced_json) ? $ticket->parts_replaced_json : []; @endphp
                        @foreach($parts as $idx => $p)
                        <tr id="part-row-{{ $idx }}">
                            <td class="px-3 py-1.5">
                                <input type="text" name="parts_replaced[{{ $idx }}][name]" value="{{ $p['name'] ?? '' }}" required class="w-full px-2 py-1 bg-obsidian-dark border border-obsidian-border rounded text-xs text-white">
                            </td>
                            <td class="px-3 py-1.5">
                                <input type="text" name="parts_replaced[{{ $idx }}][serial]" value="{{ $p['serial'] ?? '' }}" class="w-full px-2 py-1 bg-obsidian-dark border border-obsidian-border rounded text-xs text-white font-mono">
                            </td>
                            <td class="px-3 py-1.5">
                                <input type="number" step="0.01" name="parts_replaced[{{ $idx }}][cost]" value="{{ $p['cost'] ?? 0 }}" class="w-full px-2 py-1 bg-obsidian-dark border border-obsidian-border rounded text-xs text-white font-mono" onchange="recalcCost()">
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                <button type="button" onclick="removePartRow({{ $idx }})" class="text-red-400 hover:text-red-300">
                                    <span class="material-symbols-outlined text-base">close</span>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ACCIONES -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('admin.inventory.maintenance.show', $ticket->id) }}" class="px-5 py-2.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white transition text-sm">
                Cancelar
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold transition flex items-center gap-2 text-sm shadow-lg shadow-amber-500/20">
                <span class="material-symbols-outlined text-lg">save</span>
                Guardar Actualización
            </button>
        </div>
    </form>
</div>

<script>
let partIndex = {{ count($parts ?? []) }};

function addPartRow(name = '', serial = '', cost = 0.00) {
    const tbody = document.getElementById('partsBody');
    const tr = document.createElement('tr');
    tr.id = `part-row-${partIndex}`;
    tr.innerHTML = `
        <td class="px-3 py-1.5">
            <input type="text" name="parts_replaced[${partIndex}][name]" value="${name}" placeholder="Ej: Memoria RAM 8GB DDR4 Kingston" required class="w-full px-2 py-1 bg-obsidian-dark border border-obsidian-border rounded text-xs text-white">
        </td>
        <td class="px-3 py-1.5">
            <input type="text" name="parts_replaced[${partIndex}][serial]" value="${serial}" placeholder="Serial de la parte" class="w-full px-2 py-1 bg-obsidian-dark border border-obsidian-border rounded text-xs text-white font-mono">
        </td>
        <td class="px-3 py-1.5">
            <input type="number" step="0.01" name="parts_replaced[${partIndex}][cost]" value="${cost}" class="w-full px-2 py-1 bg-obsidian-dark border border-obsidian-border rounded text-xs text-white font-mono part-cost-input" onchange="recalcCost()">
        </td>
        <td class="px-3 py-1.5 text-center">
            <button type="button" onclick="removePartRow(${partIndex})" class="text-red-400 hover:text-red-300">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    partIndex++;
}

function removePartRow(idx) {
    const row = document.getElementById(`part-row-${idx}`);
    if (row) row.remove();
    recalcCost();
}

function recalcCost() {
    let total = 0;
    document.querySelectorAll('.part-cost-input').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    if (total > 0) {
        document.getElementById('totalCostInput').value = total.toFixed(2);
    }
}
</script>
@endsection
