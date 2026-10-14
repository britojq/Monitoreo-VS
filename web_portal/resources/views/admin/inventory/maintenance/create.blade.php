@extends('layouts.admin')

@section('page_title', 'Programar Mantenimiento TI & Orden de Servicio')

@section('admin_content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- ENCABEZADO -->
    <div class="flex items-center justify-between bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.devices.index') }}" class="text-xs font-mono text-obsidian-cyan hover:underline">&larr; Equipos &amp; Activos</a>
                <span class="text-obsidian-muted">&bull;</span>
                <a href="{{ route('admin.inventory.maintenance.index') }}" class="text-xs font-mono text-obsidian-muted hover:text-white hover:underline">Mesa de Mantenimiento</a>
                <span class="text-obsidian-muted">&bull;</span>
                <span class="text-xs font-mono text-amber-400">Nueva Orden de Servicio</span>
            </div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <span class="material-symbols-outlined text-amber-400 text-3xl">add_task</span>
                Abrir Orden de Servicio / Mantenimiento
            </h1>
            <p class="text-sm text-obsidian-muted mt-1">
                Programa un servicio preventivo o registra una falla técnica correctiva para el parque computacional.
            </p>
        </div>
    </div>

    <!-- FORMULARIO -->
    <form method="POST" action="{{ route('admin.inventory.maintenance.store') }}" class="space-y-6">
        @csrf

        <div class="bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md space-y-4">
            <!-- Selección de Activo -->
            <div>
                <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Activo de Cómputo a Intervenir *</label>
                <select name="inventory_asset_id" required class="w-full px-3 py-2 bg-obsidian-dark/80 border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none transition">
                    <option value="">-- Seleccionar activo del inventario --</option>
                    @foreach($assets as $asset)
                        <option value="{{ $asset->id }}" {{ ($preselectedAsset && $preselectedAsset->id == $asset->id) ? 'selected' : '' }}>
                            [{{ $asset->asset_tag ?? 'S/T' }}] {{ $asset->name }} &bull; S/N: {{ $asset->serial_number ?? 'S/N' }} &bull; ({{ $asset->status_label }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Tipo y Prioridad -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Tipo de Servicio *</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-obsidian-border bg-obsidian-dark/60 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-500/10">
                            <input type="radio" name="type" value="preventive" checked class="text-blue-500 focus:ring-blue-500">
                            <div>
                                <strong class="text-xs text-white block">Preventivo</strong>
                                <span class="text-[10px] text-obsidian-muted">Limpieza / Mantenimiento</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-obsidian-border bg-obsidian-dark/60 cursor-pointer has-[:checked]:border-rose-500 has-[:checked]:bg-rose-500/10">
                            <input type="radio" name="type" value="corrective" class="text-rose-500 focus:ring-rose-500">
                            <div>
                                <strong class="text-xs text-white block">Correctivo</strong>
                                <span class="text-[10px] text-obsidian-muted">Falla / Reparación</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Nivel de Prioridad *</label>
                    <select name="priority" required class="w-full px-3 py-2 bg-obsidian-dark/80 border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none transition">
                        <option value="low">Baja (Mantenimiento rutinario programado)</option>
                        <option value="medium" selected>Media (Servicio regular)</option>
                        <option value="high">Alta (Equipo operativo degradado o con fallas)</option>
                        <option value="critical">Crítica (Equipo inoperativo / Caído)</option>
                    </select>
                </div>
            </div>

            <!-- Título del Servicio -->
            <div>
                <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Título / Resumen del Servicio *</label>
                <input type="text" name="title" required placeholder="Ej: Mantenimiento preventivo anual y cambio de pasta térmica" class="w-full px-3 py-2 bg-obsidian-dark border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none">
            </div>

            <!-- Técnico y Fecha -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Técnico Responsable</label>
                    <select name="technician_user_id" class="w-full px-3 py-2 bg-obsidian-dark/80 border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none transition">
                        <option value="">-- Asignar más adelante --</option>
                        @foreach($technicians as $tech)
                            <option value="{{ $tech->id }}" {{ auth()->id() == $tech->id ? 'selected' : '' }}>
                                {{ $tech->name }} ({{ ucfirst($tech->role) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Fecha Programada</label>
                    <input type="date" name="scheduled_at" value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 bg-obsidian-dark border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none font-mono">
                </div>
            </div>

            <!-- Descripción y Diagnóstico Inicial -->
            <div>
                <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Descripción del Problema / Diagnóstico Preliminar</label>
                <textarea name="description" rows="3" placeholder="Detalle técnico de los síntomas reportados o tareas de mantenimiento planificadas..." class="w-full px-3 py-2 bg-obsidian-dark border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none"></textarea>
            </div>

            <!-- Costo Estimado -->
            <div>
                <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Costo Estimado Inicial (USD / Referencial)</label>
                <input type="number" step="0.01" name="cost" value="0.00" class="w-48 px-3 py-2 bg-obsidian-dark border border-obsidian-border rounded-xl text-sm text-white focus:border-amber-400 outline-none font-mono">
            </div>
        </div>

        <!-- ACCIONES -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('admin.inventory.maintenance.index') }}" class="px-5 py-2.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white transition text-sm">
                Cancelar
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold transition flex items-center gap-2 text-sm shadow-lg shadow-amber-500/20">
                <span class="material-symbols-outlined text-lg">check_circle</span>
                Crear Orden de Servicio
            </button>
        </div>
    </form>
</div>
@endsection
