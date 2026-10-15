@extends('layouts.admin')

@section('page_title', 'Nueva Asignación / Movilización TI (UINF-4.05)')

@section('admin_content')
<div class="max-w-5xl mx-auto space-y-6">

    <!-- ENCABEZADO -->
    <div class="flex items-center justify-between bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ $returnTo ?? route('admin.devices.index') }}" class="text-xs font-mono text-obsidian-cyan hover:underline flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    <span>Volver</span>
                </a>
                <span class="text-obsidian-muted">&bull;</span>
                <span class="text-xs font-mono text-emerald-400">Emisión UINF-4.05</span>
            </div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <span class="material-symbols-outlined text-obsidian-cyan text-3xl">post_add</span>
                Registrar Asignación / Devolución / Movilización
            </h1>
            <p class="text-sm text-obsidian-muted mt-1">
                Selecciona la modalidad operativa y los activos para generar la documentación de resguardo y auditoría.
            </p>
        </div>
    </div>

    @if(isset($bulkAssets) && $bulkAssets->count() > 1)
    <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/40 text-amber-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-lg">
        <div class="flex items-center gap-3">
            <span class="p-2 rounded-lg bg-amber-500/20 text-amber-400">
                <span class="material-symbols-outlined text-2xl">local_shipping</span>
            </span>
            <div>
                <div class="text-xs font-mono font-bold uppercase tracking-wider text-amber-300">Movilización Masiva Activa ({{ $bulkAssets->count() }} equipos)</div>
                <div class="text-[11px] font-mono text-obsidian-muted">Se ha precargado automáticamente el Pase de Movilización con los equipos seleccionados en el inventario.</div>
            </div>
        </div>
        <div class="flex items-center gap-1.5 flex-wrap">
            @foreach($bulkAssets as $ba)
                <span class="px-2 py-0.5 rounded-md bg-amber-950/80 border border-amber-800 text-[10px] font-mono text-amber-200">
                    {{ $ba->display_identity }}
                </span>
            @endforeach
        </div>
    </div>
    @endif

    <!-- FORMULARIO PRINCIPAL -->
    <form method="POST" action="{{ route('admin.inventory.assignments.store') }}" id="assignmentForm" class="space-y-6">
        @csrf
        <input type="hidden" name="return_to" value="{{ $returnTo ?? route('admin.devices.index') }}">

        <!-- 1. MODALIDAD OPERATIVA -->
        <div class="bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md">
            <h2 class="text-base font-bold text-white flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-obsidian-cyan">tune</span>
                1. Modalidad Operativa del Movimiento
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Asignación -->
                <label class="cursor-pointer p-4 rounded-xl border border-obsidian-border bg-obsidian-dark/60 hover:border-emerald-500/50 transition flex items-start gap-3 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-500/10">
                    <input type="radio" name="movement_type" value="assignment" class="mt-1 text-emerald-500 focus:ring-emerald-500" checked onchange="toggleMovementFields('assignment')">
                    <div>
                        <div class="font-bold text-white text-sm flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-emerald-400 text-lg">assignment_ind</span>
                            Asignación de Activo
                        </div>
                        <div class="text-xs text-obsidian-muted mt-1">Entrega formal a un trabajador con compromiso de resguardo UINF-4.05.</div>
                    </div>
                </label>

                <!-- Devolución -->
                <label class="cursor-pointer p-4 rounded-xl border border-obsidian-border bg-obsidian-dark/60 hover:border-blue-500/50 transition flex items-start gap-3 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-500/10">
                    <input type="radio" name="movement_type" value="return" class="mt-1 text-blue-500 focus:ring-blue-500" onchange="toggleMovementFields('return')">
                    <div>
                        <div class="font-bold text-white text-sm flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-blue-400 text-lg">keyboard_return</span>
                            Devolución de Activo
                        </div>
                        <div class="text-xs text-obsidian-muted mt-1">Recepción del equipo reintegrado al stock o por desincorporación.</div>
                    </div>
                </label>

                <!-- Movilización / Punto de Control -->
                <label class="cursor-pointer p-4 rounded-xl border border-obsidian-border bg-obsidian-dark/60 hover:border-amber-500/50 transition flex items-start gap-3 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-500/10">
                    <input type="radio" name="movement_type" value="transfer" class="mt-1 text-amber-500 focus:ring-amber-500" onchange="toggleMovementFields('transfer')">
                    <div>
                        <div class="font-bold text-white text-sm flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-amber-400 text-lg">local_shipping</span>
                            Pase de Movilización
                        </div>
                        <div class="text-xs text-obsidian-muted mt-1">Constancia de entrada y salida por garita de seguridad / resguardo.</div>
                    </div>
                </label>
            </div>
        </div>

        <!-- 2. EQUIPO PRINCIPAL & ESPECIFICACIONES -->
        <div class="bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md">
            <h2 class="text-base font-bold text-white flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-obsidian-cyan">desktop_windows</span>
                2. Activo Principal de Cómputo (PC / Servidor / Laptop)
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Selector de Activo -->
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Seleccionar Activo del Inventario *</label>
                    <select name="inventory_asset_id" id="assetSelector" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-sm text-white focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan outline-none transition" onchange="onAssetSelected(this)">
                        <option value="">-- Seleccione un activo --</option>
                        @foreach($assets as $asset)
                            @php
                                $cpuComp = $asset->components->where('type', 'cpu')->first();
                                $cpu = $cpuComp?->name ?? 'N/A';
                                $speed = $cpuComp?->capacity ?? '';
                                $ram = $asset->components->where('type', 'ram')->pluck('capacity')->implode('+') ?: 'N/A';
                                $disk = $asset->components->where('type', 'disk')->where('status', 'active')->pluck('capacity')->implode('+') ?: 'N/A';
                                $peripheralsData = $asset->peripherals->map(function($p) {
                                    return [
                                        'type' => strtoupper($p->type),
                                        'brand' => $p->brand,
                                        'model' => $p->model,
                                        'serial_number' => $p->serial_number,
                                        'notes' => $p->notes,
                                    ];
                                })->values();
                            @endphp
                            <option value="{{ $asset->id }}" 
                                    data-name="{{ $asset->name }}"
                                    data-tag="{{ $asset->asset_tag }}"
                                    data-serial="{{ $asset->serial_number }}"
                                    data-brand="{{ $asset->brand }}"
                                    data-model="{{ $asset->model }}"
                                    data-cpu="{{ $cpu }}"
                                    data-speed="{{ $speed }}"
                                    data-ram="{{ $ram }}"
                                    data-disk="{{ $disk }}"
                                    data-os="{{ $asset->operating_system }}"
                                    data-ip="{{ $asset->ip_address }}"
                                    data-site="{{ $asset->monitored_site_id }}"
                                    data-custodian-name="{{ $asset->custodian_name }}"
                                    data-custodian-id="{{ $asset->custodian_id_number }}"
                                    data-custodian-dept="{{ $asset->custodian_department }}"
                                    data-peripherals="{{ json_encode($peripheralsData) }}"
                                    {{ ($preselectedAsset && $preselectedAsset->id == $asset->id) ? 'selected' : '' }}>
                                [{{ $asset->asset_tag ?? 'TAG' }}] {{ $asset->name }} - S/N: {{ $asset->serial_number ?? 'S/N' }} ({{ $asset->status_label }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Fecha del Movimiento -->
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Fecha de Registro *</label>
                    <input type="date" name="assigned_at" value="{{ date('Y-m-d') }}" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-sm text-white focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan outline-none transition font-mono">
                </div>
            </div>

            <!-- Previsualización / Override de Especificaciones Técnicas (Fila PC de UINF-4.05) -->
            <div class="mt-4 pt-4 border-t border-obsidian-border/50">
                <div class="text-xs font-bold text-obsidian-cyan font-mono uppercase mb-3 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">memory</span>
                    Especificaciones Técnicas para el Acta UINF-4.05
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div>
                        <label class="block text-[10px] font-mono text-obsidian-muted uppercase mb-1">Procesador</label>
                        <input type="text" name="specs[processor]" id="specProcessor" placeholder="Ej: Intel Core i5" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-obsidian-muted uppercase mb-1">Velocidad</label>
                        <input type="text" name="specs[speed]" id="specSpeed" placeholder="Ej: 3.2 GHz" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-obsidian-muted uppercase mb-1">Memoria RAM</label>
                        <input type="text" name="specs[ram]" id="specRam" placeholder="Ej: 8GB" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-obsidian-muted uppercase mb-1">Disco Duro</label>
                        <input type="text" name="specs[disk]" id="specDisk" placeholder="Ej: 500GB" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-obsidian-muted uppercase mb-1">Sistema Operativo</label>
                        <input type="text" name="specs[os]" id="specOs" placeholder="Ej: Windows 10 / Debian" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-obsidian-muted uppercase mb-1">Dirección IP</label>
                        <input type="text" name="specs[ip_address]" id="specIp" placeholder="Ej: 10.20.23.100" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs text-white font-mono placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. DATOS DEL RESPONSABLE / TRABAJADOR Y PROCEDENCIA -->
        <div class="bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md">
            <h2 class="text-base font-bold text-white flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-obsidian-cyan">badge</span>
                3. Datos del Responsable / Trabajador &amp; Procedencia
            </h2>

            <div class="space-y-4">
                <x-ldap-user-picker 
                    id="assignment-picker" 
                    name="custodian" 
                    label="Buscar Trabajador en Directorio Activo LDAP:" 
                    :show-details="true" 
                    :required="true" 
                    theme="cyan" 
                />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                    <div>
                        <label class="block text-[10.5px] font-mono text-obsidian-muted mb-1 uppercase font-semibold">Extensión Telefónica (Opcional)</label>
                        <input type="text" name="phone_extension" id="phoneExtension" value="{{ old('phone_extension') }}" placeholder="Ej: 2111 / 0242-2210000" class="w-full px-2.5 py-1.5 bg-[#020710] border border-obsidian-border rounded-lg text-xs text-white placeholder-obsidian-muted/60 focus:border-cyan-400 focus:outline-none font-mono">
                    </div>
                </div>
            </div>

            <!-- Procedencia del Activo (UINF-4.05) -->
            <div class="mt-4 pt-4 border-t border-obsidian-border/50">
                <label class="block text-xs font-mono text-obsidian-muted uppercase mb-2">Procedencia del Activo (Casillas UINF-4.05)</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <label class="flex items-center gap-2 p-2.5 rounded-lg border border-obsidian-border bg-[#040b15]/60 cursor-pointer">
                        <input type="radio" name="provenance" value="stock" checked class="text-obsidian-cyan focus:ring-obsidian-cyan">
                        <span class="text-xs text-white font-medium">Stock de Informática</span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-lg border border-obsidian-border bg-[#040b15]/60 cursor-pointer">
                        <input type="radio" name="provenance" value="nuevo" class="text-obsidian-cyan focus:ring-obsidian-cyan">
                        <span class="text-xs text-white font-medium">Equipo Nuevo</span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-lg border border-obsidian-border bg-[#040b15]/60 cursor-pointer">
                        <input type="radio" name="provenance" value="reparacion" class="text-obsidian-cyan focus:ring-obsidian-cyan">
                        <span class="text-xs text-white font-medium">Reparación / Mantenimiento</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-2 p-2.5 rounded-lg border border-obsidian-border bg-[#040b15]/60 cursor-pointer flex-shrink-0">
                            <input type="radio" name="provenance" value="otro" class="text-obsidian-cyan focus:ring-obsidian-cyan">
                            <span class="text-xs text-white font-medium">Otro:</span>
                        </label>
                        <input type="text" name="provenance_other" placeholder="Especificar..." class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. PERIFÉRICOS INCLUIDOS EN EL KIT -->
        <div class="bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-obsidian-cyan">devices_other</span>
                    4. Periféricos y Accesorios Incluidos en el Acta
                </h2>
                <div class="flex gap-2">
                    <button type="button" onclick="addStandardKit()" class="px-3 py-1.5 rounded-lg bg-obsidian-dark hover:bg-obsidian-panel border border-obsidian-border text-xs text-obsidian-cyan font-medium transition flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">inventory_2</span>
                        Cargar Kit Estándar (Monitor, Teclado, Mouse, Regulador)
                    </button>
                    <button type="button" onclick="addPeripheralRow()" class="px-3 py-1.5 rounded-lg bg-obsidian-cyan/10 hover:bg-obsidian-cyan/20 border border-obsidian-cyan/30 text-xs text-obsidian-cyan font-bold transition flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">add</span>
                        + Agregar Fila
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-obsidian-muted" id="peripheralsTable">
                    <thead class="bg-obsidian-dark/80 text-[10px] uppercase font-mono border-b border-obsidian-border">
                        <tr>
                            <th class="px-3 py-2 w-1/5">Activo / Tipo</th>
                            <th class="px-3 py-2 w-1/5">Serial</th>
                            <th class="px-3 py-2 w-1/5">Modelo</th>
                            <th class="px-3 py-2 w-1/5">Marca</th>
                            <th class="px-3 py-2 w-1/5">Observaciones</th>
                            <th class="px-3 py-2 text-center w-10">Quitar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-obsidian-border/40" id="peripheralsBody">
                        <!-- Las filas se agregan interactivamente -->
                    </tbody>
                </table>
            </div>
            <p class="text-[11px] text-obsidian-muted mt-2">
                * Los periféricos ingresados se imprimirán en las filas 2 a 5 del formato UINF-4.05 y se trazará la anulación de líneas vacías automáticamente.
            </p>
        </div>

        <!-- 5. DATOS DEL PUNTO DE CONTROL (GARITA / RESGUARDO) -->
        <div class="bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md" id="checkpointSection">
            <h2 class="text-base font-bold text-white flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-amber-400">security</span>
                5. Datos del Punto de Control & Garita (Para Pases de Movilización)
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">05 Nombre del Punto de Control</label>
                    <input type="text" name="checkpoint_name" value="Centro de Servicio Valle Seco" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-sm text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">06 Operador o Recepcionista</label>
                    <input type="text" name="gate_operator_name" placeholder="Nombre de vigilante o recepcionista" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-sm text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">09 Hora de Salida</label>
                    <input type="text" name="departure_time" value="{{ date('H:i') }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-sm text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none font-mono">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">08 Hora de Entrada</label>
                    <input type="text" name="entry_time" placeholder="Opcional" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-sm text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none font-mono">
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-xs font-mono text-obsidian-muted uppercase mb-1">Observación del Traslado</label>
                <textarea name="observations" rows="2" placeholder="Ej: Traslado de asignación de equipos para gerencia de transmisión Carabobo..." class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-sm text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none"></textarea>
            </div>
        </div>

        <!-- BOTÓN DE ENVÍO -->
        <div class="flex items-center justify-end gap-3 pt-4">
            <a href="{{ $returnTo ?? route('admin.devices.index') }}" class="px-5 py-2.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white transition text-sm">
                Cancelar
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-obsidian-cyan hover:bg-cyan-400 text-black font-bold transition flex items-center gap-2 text-sm shadow-lg shadow-cyan-500/20">
                <span class="material-symbols-outlined text-lg">check_circle</span>
                Registrar Movimiento y Generar Actas
            </button>
        </div>
    </form>
</div>

<script>
let peripheralIndex = 0;

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function addPeripheralRow(type = '', brand = '', model = '', serial = '', obs = 'ASIGNADO EN KIT') {
    const tbody = document.getElementById('peripheralsBody');
    const tr = document.createElement('tr');
    tr.id = `peripheral-row-${peripheralIndex}`;
    tr.className = 'hover:bg-white/[0.02] transition';
    tr.innerHTML = `
        <td class="px-3 py-1.5">
            <input type="text" name="peripherals[${peripheralIndex}][type]" value="${escapeHtml(type)}" placeholder="Ej: MONITOR" required class="w-full px-2 py-1 bg-[#040b15] border border-obsidian-border rounded text-xs text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none uppercase font-mono">
        </td>
        <td class="px-3 py-1.5">
            <input type="text" name="peripherals[${peripheralIndex}][serial_number]" value="${escapeHtml(serial)}" placeholder="Serial" class="w-full px-2 py-1 bg-[#040b15] border border-obsidian-border rounded text-xs text-white font-mono placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
        </td>
        <td class="px-3 py-1.5">
            <input type="text" name="peripherals[${peripheralIndex}][model]" value="${escapeHtml(model)}" placeholder="Modelo" class="w-full px-2 py-1 bg-[#040b15] border border-obsidian-border rounded text-xs text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
        </td>
        <td class="px-3 py-1.5">
            <input type="text" name="peripherals[${peripheralIndex}][brand]" value="${escapeHtml(brand)}" placeholder="Marca" class="w-full px-2 py-1 bg-[#040b15] border border-obsidian-border rounded text-xs text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
        </td>
        <td class="px-3 py-1.5">
            <input type="text" name="peripherals[${peripheralIndex}][observations]" value="${escapeHtml(obs)}" placeholder="Observaciones" class="w-full px-2 py-1 bg-[#040b15] border border-obsidian-border rounded text-xs text-white placeholder-obsidian-muted/60 focus:border-obsidian-cyan focus:outline-none">
        </td>
        <td class="px-3 py-1.5 text-center">
            <button type="button" onclick="removePeripheralRow(${peripheralIndex})" class="text-red-400 hover:text-red-300 p-1" title="Eliminar fila">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    peripheralIndex++;
}

function removePeripheralRow(idx) {
    const row = document.getElementById(`peripheral-row-${idx}`);
    if (row) row.remove();
}

function addStandardKit() {
    addPeripheralRow('MONITOR', 'AOC / LENOVO', 'ESTÁNDAR', '[No reportado por GLPI - Ingreso manual]', 'ASIGNADO EN KIT (INGRESO MANUAL)');
    addPeripheralRow('TECLADO', 'GENIUS / HP', 'USB', '[No reportado por GLPI - Ingreso manual]', 'ASIGNADO EN KIT (INGRESO MANUAL)');
    addPeripheralRow('MOUSE', 'GENIUS / HP', 'ÓPTICO USB', '[No reportado por GLPI - Ingreso manual]', 'ASIGNADO EN KIT (INGRESO MANUAL)');
    addPeripheralRow('PROTECTOR DE VOLTAJE', 'MARSRIVA / CDP', 'MR-AVR600', '[No reportado por GLPI - Ingreso manual]', 'ASIGNADO EN KIT (INGRESO MANUAL)');
}

function onAssetSelected(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) return;

    document.getElementById('specProcessor').value = opt.getAttribute('data-cpu') || '';
    document.getElementById('specSpeed').value = opt.getAttribute('data-speed') || '';
    document.getElementById('specRam').value = opt.getAttribute('data-ram') || '';
    document.getElementById('specDisk').value = opt.getAttribute('data-disk') || '';
    document.getElementById('specOs').value = opt.getAttribute('data-os') || '';
    document.getElementById('specIp').value = opt.getAttribute('data-ip') || '';

    // Si el activo ya tiene un responsable registrado, autollenar con el picker
    const custName = opt.getAttribute('data-custodian-name');
    if (custName && typeof window['assignment-picker_populate'] === 'function') {
        window['assignment-picker_populate']({
            name: custName,
            id_number: opt.getAttribute('data-custodian-id') || '',
            department: opt.getAttribute('data-custodian-dept') || '',
            role: '',
            user_id: ''
        });
    }

    // Cargar periféricos vinculados detectados por GLPI
    const tbody = document.getElementById('peripheralsBody');
    tbody.innerHTML = '';
    peripheralIndex = 0;

    let periphs = [];
    try {
        periphs = JSON.parse(opt.getAttribute('data-peripherals') || '[]');
    } catch (e) {
        periphs = [];
    }

    let hasMonitor = false;
    let hasKeyboard = false;
    let hasMouse = false;
    let hasUps = false;

    if (periphs && periphs.length > 0) {
        periphs.forEach(p => {
            const pType = (p.type || 'MONITOR').toUpperCase();
            if (pType.includes('MONITOR')) hasMonitor = true;
            if (pType.includes('TECLADO') || pType.includes('KEYBOARD')) hasKeyboard = true;
            if (pType.includes('MOUSE') || pType.includes('RATON')) hasMouse = true;
            if (pType.includes('PROTECTOR') || pType.includes('UPS')) hasUps = true;

            const serial = p.serial_number ? p.serial_number : '[No reportado por GLPI - Ingreso manual]';
            const obs = p.serial_number ? 'DETECTADO POR GLPI (ACTIVO)' : 'DETECTADO POR GLPI (SERIAL PENDIENTE)';
            addPeripheralRow(pType, p.brand || '', p.model || '', serial, obs);
        });
    }

    // Si no tiene monitor detectado, agregar fila estándar de monitor con aviso
    if (!hasMonitor) {
        addPeripheralRow('MONITOR', opt.getAttribute('data-brand') || 'AOC / LENOVO', 'ESTÁNDAR', '[No reportado por GLPI - Ingreso manual]', 'ASIGNADO EN KIT (INGRESO MANUAL)');
    }

    // Completar el kit operativo básico (Teclado, Mouse, Protector)
    if (!hasKeyboard) {
        addPeripheralRow('TECLADO', 'GENIUS / HP', 'USB', '[No reportado por GLPI - Ingreso manual]', 'ASIGNADO EN KIT (INGRESO MANUAL)');
    }
    if (!hasMouse) {
        addPeripheralRow('MOUSE', 'GENIUS / HP', 'ÓPTICO USB', '[No reportado por GLPI - Ingreso manual]', 'ASIGNADO EN KIT (INGRESO MANUAL)');
    }
    if (!hasUps) {
        addPeripheralRow('PROTECTOR DE VOLTAJE', 'MARSRIVA / CDP', 'MR-AVR600', '[No reportado por GLPI - Ingreso manual]', 'ASIGNADO EN KIT (INGRESO MANUAL)');
    }
}

function toggleMovementFields(type) {
    const checkpoint = document.getElementById('checkpointSection');
    if (type === 'transfer') {
        checkpoint.classList.add('ring-1', 'ring-amber-500/50');
    } else {
        checkpoint.classList.remove('ring-1', 'ring-amber-500/50');
    }
}

// Inicializar con el activo preseleccionado si existe
const bulkAssetsData = @json($bulkAssets ?? []);

document.addEventListener('DOMContentLoaded', () => {
    const pickerContainer = document.getElementById('assignment-picker-container');
    if (pickerContainer) {
        pickerContainer.addEventListener('ldap-user-selected', (e) => {
            const u = e.detail;
            if (u && (u.phone || u.telephoneNumber)) {
                const phoneInput = document.getElementById('phoneExtension');
                if (phoneInput && !phoneInput.value) {
                    phoneInput.value = u.phone || u.telephoneNumber || '';
                }
            }
        });
    }

    const sel = document.getElementById('assetSelector');
    if (sel && sel.value) {
        onAssetSelected(sel);
    } else if (document.getElementById('peripheralsBody').children.length === 0) {
        addStandardKit();
    }

    if (bulkAssetsData && bulkAssetsData.length > 1) {
        const transferRadio = document.querySelector('input[name="movement_type"][value="transfer"]');
        if (transferRadio) {
            transferRadio.checked = true;
            toggleMovementFields('transfer');
        }
        bulkAssetsData.slice(1).forEach((item) => {
            const devType = (item.type || 'EQUIPO').toUpperCase();
            const brand = item.brand || 'GENÉRICO';
            const model = item.model || item.name;
            const serial = item.serial_number || ('TAG: ' + item.asset_tag);
            const obs = 'MOVILIZACIÓN CONJUNTA (' + (item.ip_address || item.asset_tag) + ')';
            addPeripheralRow(devType, brand, model, serial, obs);
        });
    }
});
</script>
@endsection
