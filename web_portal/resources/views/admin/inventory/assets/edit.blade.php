@extends('layouts.admin')

@section('page_title', 'Editar Activo • ' . $asset->name)

@section('admin_content')
<div class="max-w-4xl mx-auto space-y-4">
    <!-- NAVEGACIÓN -->
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.inventory.assets.show', $asset->id) }}" class="px-2.5 py-1 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1 group">
            <span class="material-symbols-outlined text-sm group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
            <span>Volver a Ficha Técnica</span>
        </a>
        <span class="text-obsidian-border font-mono text-xs">/</span>
        <span class="font-mono text-xs text-obsidian-muted">Editar Activo</span>
    </div>

    <!-- FORMULARIO -->
    <div class="p-6 rounded-2xl bg-obsidian-panel/95 border border-obsidian-border shadow-xl">
        <div class="flex items-center gap-3 pb-4 mb-5 border-b border-obsidian-border">
            <span class="p-2.5 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">
                <span class="material-symbols-outlined text-2xl">edit_note</span>
            </span>
            <div>
                <h1 class="text-lg font-black text-white font-mono">Editar Metadatos de Activo</h1>
                <p class="text-xs text-obsidian-muted font-mono">Actualiza tag patrimonial, responsable asignado, sede física o notas operativas</p>
            </div>
        </div>

        @if(isset($errors) && (is_object($errors) ? (method_exists($errors, 'any') && $errors->any()) : !empty($errors)))
            <div class="p-3 mb-4 rounded-xl bg-red-950/40 border border-red-500/50 text-red-300 text-xs font-mono">
                <ul class="list-disc list-inside space-y-1">
                    @php $allErrors = (is_object($errors) && method_exists($errors, 'all')) ? $errors->all() : (is_array($errors) ? $errors : []); @endphp
                    @foreach($allErrors as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.inventory.assets.update', $asset->id) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <!-- SECCIÓN 1: IDENTIFICACIÓN PRINCIPAL -->
            <div class="space-y-3">
                <h3 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">badge</span>
                    Identificación Principal
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-cyan-400 mb-1 font-semibold uppercase">Nombre Etiqueta (Identidad Operativa) <span class="text-cyan-400">*</span></label>
                        <input type="text" name="name" required value="{{ old('name', $asset->name) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Código / Tag Patrimonial</label>
                        <input type="text" name="asset_tag" value="{{ old('asset_tag', $asset->asset_tag) }}" placeholder="Ej. IT-VS-0045" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Tipo de Activo <span class="text-cyan-400">*</span></label>
                        <select name="type" id="asset_type_select" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none" onchange="togglePcFields(this.value)">
                            <optgroup label="Equipos de Cómputo">
                                <option value="desktop" {{ old('type', $asset->type) === 'desktop' ? 'selected' : '' }}>Desktop / Computadora de Escritorio</option>
                                <option value="laptop" {{ old('type', $asset->type) === 'laptop' ? 'selected' : '' }}>Laptop / Computadora Portátil</option>
                                <option value="server" {{ old('type', $asset->type) === 'server' ? 'selected' : '' }}>Servidor Físico</option>
                            </optgroup>
                            <optgroup label="Dispositivos de Red e Infraestructura">
                                <option value="router" {{ old('type', $asset->type) === 'router' ? 'selected' : '' }}>Router / Enrutador</option>
                                <option value="switch" {{ old('type', $asset->type) === 'switch' ? 'selected' : '' }}>Switch de Red</option>
                                <option value="firewall" {{ old('type', $asset->type) === 'firewall' ? 'selected' : '' }}>Firewall / Seguridad</option>
                                <option value="access_point" {{ old('type', $asset->type) === 'access_point' ? 'selected' : '' }}>Access Point Wi-Fi</option>
                                <option value="ip_phone" {{ old('type', $asset->type) === 'ip_phone' ? 'selected' : '' }}>Teléfono IP</option>
                                <option value="ups" {{ old('type', $asset->type) === 'ups' ? 'selected' : '' }}>UPS / Respaldo Eléctrico</option>
                                <option value="printer" {{ old('type', $asset->type) === 'printer' ? 'selected' : '' }}>Impresora / Multifuncional</option>
                                <option value="network" {{ old('type', $asset->type) === 'network' ? 'selected' : '' }}>Dispositivo de Red Genérico</option>
                            </optgroup>
                            <optgroup label="Otros">
                                <option value="peripheral" {{ old('type', $asset->type) === 'peripheral' ? 'selected' : '' }}>Periférico</option>
                            </optgroup>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Sistema Operativo</label>
                        <input type="text" name="operating_system" value="{{ old('operating_system', $asset->operating_system) }}" placeholder="Ej. Windows 11 Pro / Debian 12" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Marca / Fabricante</label>
                        <input type="text" name="brand" value="{{ old('brand', $asset->brand) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Modelo Comercial</label>
                        <input type="text" name="model" value="{{ old('model', $asset->model) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Número de Serie de Fábrica</label>
                        <input type="text" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 2: UBICACIÓN Y ESTADO -->
            <div class="space-y-3 pt-3 border-t border-obsidian-border">
                <h3 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">location_on</span>
                    Ubicación y Estado Operativo
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Sede Física</label>
                        <select name="monitored_site_id" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            <option value="">Sin Asignar</option>
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}" {{ old('monitored_site_id', $asset->monitored_site_id) == $s->id ? 'selected' : '' }}>
                                    {{ $s->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Ubicación Detallada</label>
                        <input type="text" name="location_detail" value="{{ old('location_detail', $asset->location_detail) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Estado Operativo <span class="text-cyan-400">*</span></label>
                        <select name="status" required class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                            <option value="in_stock" {{ old('status', $asset->status) === 'in_stock' ? 'selected' : '' }}>En Stock (Disponible)</option>
                            <option value="assigned" {{ old('status', $asset->status) === 'assigned' ? 'selected' : '' }}>Asignado (En Uso)</option>
                            <option value="maintenance" {{ old('status', $asset->status) === 'maintenance' ? 'selected' : '' }}>En Mantenimiento</option>
                            <option value="unauthorized" {{ old('status', $asset->status) === 'unauthorized' ? 'selected' : '' }}>Descubierto (GLPI)</option>
                            <option value="damaged" {{ old('status', $asset->status) === 'damaged' ? 'selected' : '' }}>Dañado / Para Baja</option>
                            <option value="disposed" {{ old('status', $asset->status) === 'disposed' ? 'selected' : '' }}>Desincorporado</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 3: RED Y RESPONSABLE -->
            <div class="space-y-3 pt-3 border-t border-obsidian-border">
                <h3 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">lan</span>
                    Red y Responsable Asignado
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Dirección IP</label>
                        <input type="text" name="ip_address" value="{{ old('ip_address', $asset->ip_address) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Dirección MAC</label>
                        <input type="text" name="mac_address" value="{{ old('mac_address', $asset->mac_address) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Responsable Asignado</label>
                        <input type="text" name="custodian_name" value="{{ old('custodian_name', $asset->custodian_name) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1">Departamento / Área</label>
                        <input type="text" name="custodian_department" value="{{ old('custodian_department', $asset->custodian_department) }}" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>
            </div>

            @php
                $mon = $asset->peripherals->firstWhere('type', 'monitor');
                $kbd = $asset->peripherals->firstWhere('type', 'keyboard');
                $mse = $asset->peripherals->firstWhere('type', 'mouse');
                $pwr = $asset->peripherals->firstWhere('type', 'surge_protector') ?? $asset->peripherals->firstWhere('type', 'ups');
            @endphp

            <!-- SECCIÓN: PERIFÉRICOS DEL PUESTO DE TRABAJO (SOLO PC) -->
            <div id="pc_peripherals_section" class="space-y-3 pt-3 border-t border-obsidian-border" style="{{ in_array($asset->type, ['desktop', 'laptop', 'server']) ? '' : 'display:none;' }}">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-mono uppercase font-bold text-cyan-400 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">devices</span>
                        Periféricos del Puesto de Trabajo (Monitor, Teclado, Mouse, Protector)
                    </h3>
                    <span class="text-[11px] font-mono text-obsidian-muted">Actualiza o asocia periféricos al equipo</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- MONITOR -->
                    <div class="p-3 rounded-xl bg-obsidian-darker/60 border border-obsidian-border/80 space-y-2">
                        <div class="flex items-center gap-1.5 text-xs font-mono text-white font-semibold">
                            <span class="material-symbols-outlined text-cyan-400 text-sm">desktop_windows</span>
                            <span>Monitor</span>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Marca / Modelo</label>
                            <input type="text" name="monitor_brand" value="{{ old('monitor_brand', $mon ? trim($mon->brand . ' ' . $mon->model) : '') }}" placeholder="Ej. Dell 22 pulgadas" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Serial</label>
                            <input type="text" name="monitor_serial" value="{{ old('monitor_serial', $mon?->serial_number) }}" placeholder="Ej. CN-0XYZ12" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>

                    <!-- TECLADO -->
                    <div class="p-3 rounded-xl bg-obsidian-darker/60 border border-obsidian-border/80 space-y-2">
                        <div class="flex items-center gap-1.5 text-xs font-mono text-white font-semibold">
                            <span class="material-symbols-outlined text-cyan-400 text-sm">keyboard</span>
                            <span>Teclado</span>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Marca</label>
                            <input type="text" name="keyboard_brand" value="{{ old('keyboard_brand', $kbd?->brand) }}" placeholder="Ej. Logitech / Genius" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Serial</label>
                            <input type="text" name="keyboard_serial" value="{{ old('keyboard_serial', $kbd?->serial_number) }}" placeholder="Serial (opcional)" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>

                    <!-- MOUSE -->
                    <div class="p-3 rounded-xl bg-obsidian-darker/60 border border-obsidian-border/80 space-y-2">
                        <div class="flex items-center gap-1.5 text-xs font-mono text-white font-semibold">
                            <span class="material-symbols-outlined text-cyan-400 text-sm">mouse</span>
                            <span>Mouse</span>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Marca</label>
                            <input type="text" name="mouse_brand" value="{{ old('mouse_brand', $mse?->brand) }}" placeholder="Ej. HP / Logitech" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Serial</label>
                            <input type="text" name="mouse_serial" value="{{ old('mouse_serial', $mse?->serial_number) }}" placeholder="Serial (opcional)" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>

                    <!-- PROTECTOR / UPS -->
                    <div class="p-3 rounded-xl bg-obsidian-darker/60 border border-obsidian-border/80 space-y-2">
                        <div class="flex items-center gap-1.5 text-xs font-mono text-white font-semibold">
                            <span class="material-symbols-outlined text-cyan-400 text-sm">electric_bolt</span>
                            <span>Protector / Mini-UPS</span>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Marca</label>
                            <input type="text" name="surge_protector_brand" value="{{ old('surge_protector_brand', $pwr?->brand) }}" placeholder="Ej. Forza / APC" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-obsidian-muted mb-0.5">Serial</label>
                            <input type="text" name="surge_protector_serial" value="{{ old('surge_protector_serial', $pwr?->serial_number) }}" placeholder="Serial (opcional)" class="w-full px-2.5 py-1.5 bg-[#040b15] border border-obsidian-border rounded-lg text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 4: OBSERVACIONES -->
            <div class="space-y-3 pt-3 border-t border-obsidian-border">
                <label class="block text-xs font-mono text-obsidian-muted mb-1">Notas u Observaciones del Activo</label>
                <textarea name="notes" rows="3" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">{{ old('notes', $asset->notes) }}</textarea>
            </div>

            <!-- BOTONES -->
            <div class="flex items-center justify-end gap-2 pt-4 border-t border-obsidian-border">
                <a href="{{ route('admin.inventory.assets.show', $asset->id) }}" class="px-4 py-2 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition">
                    Cancelar
                </a>
                <button type="submit" class="px-5 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20">
                    <span class="material-symbols-outlined text-base">save</span>
                    <span>Actualizar Cambios</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePcFields(val) {
    const sec = document.getElementById('pc_peripherals_section');
    if (sec) {
        sec.style.display = (val === 'desktop' || val === 'laptop' || val === 'server') ? '' : 'none';
    }
}
</script>
@endsection
