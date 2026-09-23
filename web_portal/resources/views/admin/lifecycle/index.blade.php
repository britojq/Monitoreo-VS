@extends('layouts.admin')

@section('page_title', 'Ciclo de Vida de Hardware')

@section('admin_content')
<div class="space-y-4">
    <!-- PESTAÑAS EQUIPOS & HARDWARE -->
    <div class="flex flex-wrap items-center gap-2 border-b border-obsidian-border/80 pb-3">
        <a href="{{ route('admin.devices.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.devices.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">router</span>
            <span>Dispositivos de Red</span>
        </a>
        <a href="{{ route('admin.lifecycle.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.lifecycle.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">inventory_2</span>
            <span>Ciclo de Vida & Inventario</span>
        </a>
        <a href="{{ route('admin.wol.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.wol.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">power</span>
            <span>Wake-on-LAN (WoL)</span>
        </a>
    </div>

    @if(session('status'))
        <div class="p-3 rounded-xl bg-emerald-950/80 border border-emerald-500/40 text-emerald-300 font-mono text-xs flex items-center gap-2 shadow-md">
            <span class="material-symbols-outlined text-base">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- ENCABEZADO Y GESTIÓN DE ACTIVOS                                           -->
    <!-- ========================================================================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 sm:px-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 backdrop-blur-md shadow-md">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-950/70 border border-emerald-500/40 flex items-center justify-center text-emerald-400 shrink-0">
                <span class="material-symbols-outlined text-lg">inventory_2</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                        Ciclo de Vida de Hardware & Garantías
                    </h2>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-500/40">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Gestión ITIL
                    </span>
                </div>
                <p class="text-[10px] font-mono text-obsidian-muted">
                    Control de contratos de soporte, fin de vida (EOL/EOS), reemplazo de baterías UPS y salud SMART de discos.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto flex-wrap">
            <span class="px-2.5 py-1 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 text-[10.5px] font-mono flex items-center gap-1.5 shadow-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                Fuente: SERVIDOR MAESTRO
            </span>

            @if(auth()->user()->isAdmin() && !$isClusterSlave)
                <button type="button" onclick="openLifecycleModal()" class="px-2.5 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/40 text-emerald-300 hover:bg-emerald-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs">
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    <span>Registrar Activo</span>
                </button>
            @elseif($isClusterSlave)
                <span class="px-2.5 py-1.5 rounded-lg bg-gray-900 border border-gray-800 text-gray-500 font-mono text-xs inline-flex items-center gap-1.5" title="Modificaciones restringidas al Servidor Master">
                    <span class="material-symbols-outlined text-xs">lock</span>
                    <span>Solo Lectura (Modo Esclavo)</span>
                </span>
            @endif
            <a href="{{ route('admin.lifecycle.index') }}" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs">
                <span class="material-symbols-outlined text-sm">refresh</span>
                <span>Refrescar</span>
            </a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- HUD: TARJETAS MÉTRICAS                                                    -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-2 sm:grid-cols-6 gap-2 sm:gap-3">
        <!-- TOTAL ACTIVOS -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Total Activos</span>
                <span class="material-symbols-outlined text-sm text-cyan-400">devices</span>
            </div>
            <div class="text-lg font-bold font-mono text-white mt-1">{{ number_format($totalTracked) }}</div>
            <div class="text-[9px] font-mono text-cyan-400/80 mt-0.5">En inventario</div>
        </div>

        <!-- GARANTÍAS VIGENTES -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Garantías OK</span>
                <span class="material-symbols-outlined text-sm text-emerald-400">verified</span>
            </div>
            <div class="text-lg font-bold font-mono text-emerald-400 mt-1">{{ number_format($warrantyValid) }}</div>
            <div class="text-[9px] font-mono text-emerald-400/80 mt-0.5">Vigencia amplia</div>
        </div>

        <!-- POR VENCER (<60D) -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Por Vencer</span>
                <span class="material-symbols-outlined text-sm text-amber-400">timer</span>
            </div>
            <div class="text-lg font-bold font-mono text-amber-300 mt-1">{{ number_format($warrantyExpiringSoon) }}</div>
            <div class="text-[9px] font-mono text-amber-400/80 mt-0.5">&lt; 60 días</div>
        </div>

        <!-- VENCIDAS -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Expiradas</span>
                <span class="material-symbols-outlined text-sm text-rose-400">cancel</span>
            </div>
            <div class="text-lg font-bold font-mono text-rose-400 mt-1">{{ number_format($warrantyExpired) }}</div>
            <div class="text-[9px] font-mono text-rose-400/80 mt-0.5">Sin soporte activo</div>
        </div>

        <!-- FIN DE VIDA EOL -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Fin de Vida</span>
                <span class="material-symbols-outlined text-sm text-purple-400">history_toggle_off</span>
            </div>
            <div class="text-lg font-bold font-mono text-purple-300 mt-1">{{ number_format($eolReached) }}</div>
            <div class="text-[9px] font-mono text-purple-400/80 mt-0.5">EOL alcanzado</div>
        </div>

        <!-- SALUD DE DISCOS SMART -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Alarma Discos</span>
                <span class="material-symbols-outlined text-sm {{ $diskAlerts > 0 ? 'text-rose-400' : 'text-slate-400' }}">hard_drive</span>
            </div>
            <div class="text-lg font-bold font-mono {{ $diskAlerts > 0 ? 'text-rose-400' : 'text-white' }} mt-1">{{ number_format($diskAlerts) }}</div>
            <div class="text-[9px] font-mono text-obsidian-muted mt-0.5">Fallo o riesgo SMART</div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TABLA DE CONTROL DE CICLO DE VIDA                                         -->
    <!-- ========================================================================= -->
    <div class="rounded-xl bg-obsidian-panel/80 border border-obsidian-border overflow-hidden shadow-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-left font-mono text-xs">
                <thead>
                    <tr class="bg-obsidian-bg/90 border-b border-obsidian-border text-[11px] text-obsidian-muted uppercase">
                        <th class="px-4 py-3">Dispositivo / Activo</th>
                        <th class="px-4 py-3">Número de Serie</th>
                        <th class="px-4 py-3">Vencimiento Garantía</th>
                        <th class="px-4 py-3">Fin de Vida (EOL)</th>
                        <th class="px-4 py-3">Salud SMART</th>
                        <th class="px-4 py-3">Firmware</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border">
                    @forelse($items as $it)
                        <tr class="hover:bg-emerald-500/5 transition">
                            <td class="px-4 py-3">
                                <div class="font-bold text-white flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm text-cyan-400">dns</span>
                                    {{ $it->device_name }}
                                </div>
                                <div class="text-[10px] text-obsidian-muted uppercase">{{ $it->entity_type }} #{{ $it->entity_id }}</div>
                            </td>
                            <td class="px-4 py-3 text-cyan-300 font-bold tracking-wider">
                                {{ $it->serial_number ?? 'Sin registro' }}
                            </td>
                            <td class="px-4 py-3">
                                @if($it->warranty_end_date)
                                    @php $days = $it->warranty_days_remaining; @endphp
                                    @if($days < 0)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-950 text-rose-400 border border-rose-500/30">
                                            Expirada ({{ abs($days) }}d atrás)
                                        </span>
                                    @elseif($days <= 60)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-950 text-amber-300 border border-amber-500/30 animate-pulse">
                                            Vence en {{ $days }} días
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/30">
                                            Vigente ({{ $days }}d)
                                        </span>
                                    @endif
                                    <div class="text-[9px] text-obsidian-muted mt-0.5">{{ $it->warranty_end_date->format('Y-m-d') }}</div>
                                @else
                                    <span class="text-obsidian-muted italic text-[11px]">Sin fecha</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($it->eol_date)
                                    @php $eolDays = $it->eol_days_remaining; @endphp
                                    @if($eolDays < 0)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-950 text-purple-300 border border-purple-500/30">
                                            EOL Alcanzado
                                        </span>
                                    @else
                                        <span class="text-white text-[11px] font-semibold">{{ $it->eol_date->format('Y-m-d') }}</span>
                                    @endif
                                @else
                                    <span class="text-obsidian-muted italic text-[11px]">No fijada</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $it->disk_health_color }}">
                                    SMART: {{ strtoupper($it->disk_health_status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-obsidian-muted text-[11px]">
                                <span class="text-white font-mono">{{ $it->firmware_version ?? 'N/A' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if(auth()->user()->isAdmin())
                                        @if($isClusterSlave)
                                            <span class="px-2 py-0.5 rounded bg-gray-900 border border-gray-800 text-gray-500 text-[10px] font-mono inline-flex items-center gap-1" title="Modificaciones restringidas al Servidor Master">
                                                <span class="material-symbols-outlined text-[12px]">lock</span>
                                                <span>Solo Lectura</span>
                                            </span>
                                        @else
                                            <button type="button" 
                                                    onclick='editLifecycleItem(@json($it))' 
                                                    class="p-1 rounded text-cyan-400 hover:bg-cyan-500/20 transition cursor-pointer" 
                                                    title="Editar Ficha">
                                                <span class="material-symbols-outlined text-sm">edit</span>
                                            </button>
                                            <form action="{{ route('admin.lifecycle.destroy', $it->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este registro de ciclo de vida?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-1 rounded text-rose-400 hover:bg-rose-500/20 transition cursor-pointer" title="Eliminar">
                                                    <span class="material-symbols-outlined text-sm">delete</span>
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        <span class="text-obsidian-muted text-[10px]">Lectura</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-obsidian-muted">
                                <span class="material-symbols-outlined text-3xl mb-2 text-obsidian-border block">inventory</span>
                                No hay registros de ciclo de vida ni garantías agregados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($items->hasPages())
            <div class="p-3 border-t border-obsidian-border">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>

@if(auth()->user()->isAdmin() && !$isClusterSlave)
<!-- MODAL REGISTRO / EDICIÓN DE CICLO DE VIDA -->
<div id="lifecycleModal" class="fixed inset-0 bg-black/70 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-obsidian-panel border border-obsidian-border rounded-xl w-full max-w-lg p-5 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-obsidian-border">
            <h3 class="text-xs font-bold text-white uppercase font-mono tracking-wider flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-400 text-sm" id="modal_title_icon">add_circle</span>
                <span id="modal_title_text">Ficha de Ciclo de Vida y Garantía</span>
            </h3>
            <button type="button" onclick="closeLifecycleModal()" class="text-obsidian-muted hover:text-white cursor-pointer">&times;</button>
        </div>

        <form action="{{ route('admin.lifecycle.store') }}" method="POST" class="space-y-3 font-mono text-xs">
            @csrf
            <input type="hidden" name="id" id="modal_lifecycle_id" value="">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Tipo de Activo *</label>
                    <select name="entity_type" id="modal_entity_type" onchange="updateEntityOptions(this.value)" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-emerald-500">
                        <option value="snmp_device">Dispositivo SNMP</option>
                        <option value="network_device">Equipo de Red</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Dispositivo *</label>
                    <select name="entity_id" id="modal_entity_id" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-emerald-500">
                        @foreach($availableSnmpDevices as $sd)
                            <option value="{{ $sd->id }}">{{ $sd->name }} ({{ $sd->ip_address }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Número de Serie (S/N)</label>
                    <input type="text" name="serial_number" id="modal_serial_number" placeholder="ej. FOC1842W0AB" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-emerald-500">
                </div>
                <div>
                    <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Versión de Firmware</label>
                    <input type="text" name="firmware_version" id="modal_firmware_version" placeholder="ej. 15.0(2)SE11" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Fecha de Compra</label>
                    <input type="date" name="purchase_date" id="modal_purchase_date" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-emerald-500">
                </div>
                <div>
                    <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Vencimiento de Garantía</label>
                    <input type="date" name="warranty_end_date" id="modal_warranty_end_date" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Fin de Vida (EOL)</label>
                    <input type="date" name="eol_date" id="modal_eol_date" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-emerald-500">
                </div>
                <div>
                    <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Salud SMART Discos</label>
                    <select name="disk_health_status" id="modal_disk_health_status" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-emerald-500">
                        <option value="ok">Saludable / OK</option>
                        <option value="warning">Advertencia (Sectores Reasignados)</option>
                        <option value="failing">Fallo Inminente / Crítico</option>
                        <option value="unknown">Desconocido / Sin SMART</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Notas / Observaciones</label>
                <textarea name="notes" id="modal_notes" rows="2" placeholder="Observaciones de soporte técnico, proveedor o recambio..." class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-emerald-500"></textarea>
            </div>

            <div class="pt-3 border-t border-obsidian-border flex items-center justify-end gap-2">
                <button type="button" onclick="closeLifecycleModal()" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 transition cursor-pointer">
                    Cancelar
                </button>
                <button type="submit" id="modal_submit_btn" class="px-3 py-1.5 rounded-lg bg-emerald-500 text-black font-bold hover:bg-emerald-400 transition cursor-pointer">
                    Guardar Ficha
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const snmpList = @json($availableSnmpDevices);
    const netList = @json($availableNetDevices);

    function updateEntityOptions(type) {
        const select = document.getElementById('modal_entity_id');
        select.innerHTML = '';
        const list = (type === 'snmp_device') ? snmpList : netList;
        list.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.innerText = item.name + ' (' + (item.ip_address || item.ip || 'N/A') + ')';
            select.appendChild(opt);
        });
    }

    function openLifecycleModal() {
        document.getElementById('modal_lifecycle_id').value = '';
        document.getElementById('modal_title_text').innerText = 'Ficha de Ciclo de Vida y Garantía';
        document.getElementById('modal_title_icon').innerText = 'add_circle';
        document.getElementById('modal_submit_btn').innerText = 'Guardar Ficha';
        
        document.getElementById('modal_entity_type').value = 'snmp_device';
        updateEntityOptions('snmp_device');
        
        document.getElementById('modal_serial_number').value = '';
        document.getElementById('modal_firmware_version').value = '';
        document.getElementById('modal_purchase_date').value = '';
        document.getElementById('modal_warranty_end_date').value = '';
        document.getElementById('modal_eol_date').value = '';
        document.getElementById('modal_disk_health_status').value = 'ok';
        document.getElementById('modal_notes').value = '';

        document.getElementById('lifecycleModal').classList.remove('hidden');
    }

    function editLifecycleItem(item) {
        document.getElementById('modal_lifecycle_id').value = item.id || '';
        document.getElementById('modal_title_text').innerText = 'Editar Ficha de Ciclo de Vida y Garantía';
        document.getElementById('modal_title_icon').innerText = 'edit';
        document.getElementById('modal_submit_btn').innerText = 'Actualizar Ficha';
        
        document.getElementById('modal_entity_type').value = item.entity_type;
        updateEntityOptions(item.entity_type);
        document.getElementById('modal_entity_id').value = item.entity_id;
        
        document.getElementById('modal_serial_number').value = item.serial_number || '';
        document.getElementById('modal_firmware_version').value = item.firmware_version || '';
        
        const formatDate = (val) => val ? val.substring(0, 10) : '';
        document.getElementById('modal_purchase_date').value = formatDate(item.purchase_date);
        document.getElementById('modal_warranty_end_date').value = formatDate(item.warranty_end_date);
        document.getElementById('modal_eol_date').value = formatDate(item.eol_date);
        
        document.getElementById('modal_disk_health_status').value = item.disk_health_status || 'unknown';
        document.getElementById('modal_notes').value = item.notes || '';

        document.getElementById('lifecycleModal').classList.remove('hidden');
    }

    function closeLifecycleModal() {
        document.getElementById('lifecycleModal').classList.add('hidden');
    }
</script>
@endif
@endsection
