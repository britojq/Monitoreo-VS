@extends('layouts.admin')

@section('page_title', 'Dispositivos de Red')

@section('admin_content')
<div class="space-y-6">
    <!-- CABECERA -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-xs font-mono font-semibold group shadow-sm">
                    <span class="material-symbols-outlined text-sm group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-xs font-mono text-obsidian-muted">Dispositivos de Red Local</span>
            </div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400">router</span>
                Dispositivos de Red Local (Sede Valle Seco)
            </h2>
            <p class="text-xs font-mono text-obsidian-muted">Supervisión técnica de Switches, Routers y Enlaces LAN (10.20.23.0/24)</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1.5 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 text-xs font-mono flex items-center gap-1.5 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                Sincronizado con monitoreo.conf
            </span>
        </div>
    </div>

    <!-- TABLA DE DISPOSITIVOS -->
    <div class="glass-card rounded-xl overflow-hidden shadow-xl border border-obsidian-border/80">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-obsidian-panel border-b border-obsidian-border text-obsidian-muted uppercase text-[11px]">
                    <tr>
                        <th class="px-5 py-4">Slot / ID</th>
                        <th class="px-5 py-4">Dispositivo</th>
                        <th class="px-5 py-4">Dirección IP & MAC</th>
                        <th class="px-5 py-4">Modelo & Serial</th>
                        <th class="px-5 py-4">Acceso Remoto</th>
                        <th class="px-5 py-4">Monitoreo</th>
                        <th class="px-5 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody id="devices-table-body" class="divide-y divide-obsidian-border/60">
                    @foreach($devices as $d)
                        @php
                            $snap = $snapshotDevices->get($d->id);
                            $isUp = $snap ? ($snap['is_up'] ?? false) : true;
                            $lat = $snap ? ($snap['latency_ms'] ?? 0) : 0;
                            $latStr = $lat > 0 ? ($lat . ' ms') : '< 1 ms';
                            $accType = strtoupper(trim($d->access_type ?? 'SIN SOPORTE'));
                            $accPort = $d->access_port ?: ($accType === 'TELNET' ? 23 : ($accType === 'WEB' ? 80 : 5900));
                        @endphp
                        <tr class="hover:bg-obsidian-panel/40 transition">
                            <td class="px-5 py-4 font-bold text-obsidian-cyan whitespace-nowrap">
                                [ DISP #{{ $d->device_number }} ]
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full shrink-0 {{ $isUp ? 'bg-emerald-400 glow-green' : 'bg-red-500' }}"></span>
                                    <div>
                                        <div class="font-sans font-bold text-white text-sm flex items-center gap-1.5">
                                            {{ $d->name }}
                                        </div>
                                        @if($d->vendor_data)
                                            <span class="text-[10px] text-obsidian-muted font-mono">{{ $d->vendor_data }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-white">{{ $d->ip }}</div>
                                <div class="text-[10px] text-obsidian-muted">MAC: {{ $d->mac ?: 'No especificada' }}</div>
                            </td>
                            <td class="px-5 py-4">
                                @if($d->model)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-cyan-950/70 border border-cyan-500/30 text-cyan-300">
                                        {{ $d->model }}
                                    </span>
                                @else
                                    <span class="text-obsidian-muted">--</span>
                                @endif
                                @if($d->serial)
                                    <div class="text-[10px] text-obsidian-muted font-mono mt-0.5">S/N: {{ $d->serial }}</div>
                                @endif
                                @if($d->ports)
                                    <div class="text-[9.5px] text-obsidian-muted/80 font-mono">{{ $d->ports }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if($accType === 'TELNET')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-950 border border-cyan-500/40 text-cyan-300 inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[11px]">terminal</span>
                                        TELNET:{{ $accPort }}
                                    </span>
                                @elseif($accType === 'WEB')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-950 border border-blue-500/40 text-blue-300 inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[11px]">open_in_browser</span>
                                        WEB:{{ $accPort }}
                                    </span>
                                @elseif($accType === 'VNC')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-950 border border-purple-500/40 text-purple-300 inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[11px]">desktop_windows</span>
                                        VNC:{{ $accPort }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] bg-obsidian-panel border border-obsidian-border text-obsidian-muted">
                                        SIN ACCESO
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if(auth()->user()->isAdmin())
                                    <form action="{{ route('admin.devices.toggle', $d->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-bold transition {{ $d->is_active ? 'bg-emerald-950/70 text-emerald-400 border border-emerald-500/40' : 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border' }}" title="Click para alternar estado">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $d->is_active ? 'bg-emerald-400' : 'bg-obsidian-muted' }}"></span>
                                            {{ $d->is_active ? 'Activo' : 'Inactivo' }}
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-bold {{ $d->is_active ? 'bg-emerald-950/70 text-emerald-400 border border-emerald-500/40' : 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $d->is_active ? 'bg-emerald-400' : 'bg-obsidian-muted' }}"></span>
                                        {{ $d->is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right space-x-1.5 whitespace-nowrap">
                                <!-- BOTÓN: VER INFORMACIÓN DETALLADA (OPERADORES Y ADMINS) -->
                                <button type="button" 
                                        onclick="openDeviceDetailsModal({{ json_encode($d) }})" 
                                        class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-obsidian-cyan hover:text-black transition inline-flex items-center gap-1 cursor-pointer" 
                                        title="Ver Ficha Técnica Completa">
                                    <span class="material-symbols-outlined text-sm">visibility</span>
                                    <span>Detalles</span>
                                </button>

                                <!-- BOTÓN: HISTÓRICO Y GRÁFICO (OPERADORES Y ADMINS) -->
                                <button type="button" 
                                        onclick="openDeviceHistoryModal({{ $d->id }}, '{{ addslashes($d->name) }}', {{ $d->device_number }}, '{{ $d->ip }}')" 
                                        class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-purple/40 text-obsidian-purple hover:bg-obsidian-purple hover:text-white transition inline-flex items-center gap-1 cursor-pointer" 
                                        title="Ver Histórico de Conexión y Latencia">
                                    <span class="material-symbols-outlined text-sm">show_chart</span>
                                    <span>Histórico</span>
                                </button>

                                @if(auth()->user()->isAdmin())
                                    <!-- BOTÓN: EDITAR DISPOSITIVO (SOLO ADMINISTRADORES) -->
                                    <button type="button" 
                                            onclick="openDeviceEditModal({{ json_encode($d) }})" 
                                            class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-amber-500/40 text-amber-300 hover:bg-amber-500 hover:text-black transition inline-flex items-center gap-1 cursor-pointer" 
                                            title="Editar Parámetros Técnicos">
                                        <span class="material-symbols-outlined text-sm">edit</span>
                                        <span>Editar</span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 1: FICHA TÉCNICA DETALLADA (OPERADORES Y ADMINISTRADORES)          -->
<!-- ========================================================================= -->
<div id="modal-device-details" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel w-full max-w-2xl rounded-2xl border border-cyan-500/50 flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95 animate-in fade-in zoom-in-95 duration-200">
        <!-- CABECERA -->
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between bg-[#061527]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/80 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shadow-md">
                    <span class="material-symbols-outlined text-2xl">router</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white font-mono flex items-center gap-2">
                        <span id="det-device-name">--</span>
                        <span id="det-device-slot" class="px-2 py-0.5 rounded text-[10px] bg-obsidian-panel border border-obsidian-border text-obsidian-cyan font-mono">--</span>
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono" id="det-device-vendor">--</p>
                </div>
            </div>
            <button type="button" onclick="closeDeviceDetailsModal()" class="text-obsidian-muted hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- CONTENIDO -->
        <div class="p-6 space-y-5 overflow-y-auto max-h-[75vh]">
            <!-- DATOS BÁSICOS EN GRID -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 font-mono text-xs">
                <div class="bg-obsidian-panel/80 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1">
                    <span class="text-obsidian-muted text-[10px] uppercase font-bold tracking-wider">Dirección IP</span>
                    <p class="text-white font-semibold text-sm" id="det-device-ip">--</p>
                </div>
                <div class="bg-obsidian-panel/80 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1">
                    <span class="text-obsidian-muted text-[10px] uppercase font-bold tracking-wider">Dirección MAC</span>
                    <p class="text-obsidian-cyan font-semibold" id="det-device-mac">--</p>
                </div>
                <div class="bg-obsidian-panel/80 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1">
                    <span class="text-obsidian-muted text-[10px] uppercase font-bold tracking-wider">Modelo de Hardware</span>
                    <p class="text-cyan-300 font-semibold" id="det-device-model">--</p>
                </div>
                <div class="bg-obsidian-panel/80 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1">
                    <span class="text-obsidian-muted text-[10px] uppercase font-bold tracking-wider">Número de Serie</span>
                    <p class="text-amber-300 font-semibold" id="det-device-serial">--</p>
                </div>
                <div class="bg-obsidian-panel/80 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1">
                    <span class="text-obsidian-muted text-[10px] uppercase font-bold tracking-wider">Cantidad / Tipo de Puertos</span>
                    <p class="text-white font-semibold" id="det-device-ports">--</p>
                </div>
                <div class="bg-obsidian-panel/80 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1">
                    <span class="text-obsidian-muted text-[10px] uppercase font-bold tracking-wider">Protocolo de Acceso Remoto</span>
                    <p class="text-emerald-400 font-semibold" id="det-device-access">--</p>
                </div>
            </div>

            <!-- BLOQUE: NOTAS TÉCNICAS Y MAPEO DE PUERTOS -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-mono font-bold text-cyan-400 flex items-center gap-1.5 uppercase tracking-wider">
                        <span class="material-symbols-outlined text-sm">description</span>
                        Notas Técnicas & Distribución de Puertos
                    </label>
                    <span class="text-[10px] font-mono text-obsidian-muted">Configurado en monitoreo.conf</span>
                </div>
                <div id="det-device-notes" class="whitespace-pre-line font-mono text-xs text-cyan-200 bg-[#020b14]/90 p-4 rounded-xl border border-cyan-500/30 break-words leading-relaxed max-h-56 overflow-y-auto scrollbar-thin">
                    Sin notas técnicas registradas.
                </div>
            </div>
        </div>

        <!-- PIE DEL MODAL -->
        <div class="p-4 px-6 border-t border-obsidian-border bg-[#061527] flex items-center justify-end gap-3">
            <button type="button" onclick="closeDeviceDetailsModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-white/10 transition">
                Cerrar
            </button>
        </div>
    </div>
</div>

@if(auth()->user()->isAdmin())
<!-- ========================================================================= -->
<!-- MODAL 2: EDICIÓN DE DISPOSITIVO (SOLO ADMINISTRADORES)                     -->
<!-- ========================================================================= -->
<div id="modal-device-edit" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel w-full max-w-2xl rounded-2xl border border-amber-500/50 flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95 animate-in fade-in zoom-in-95 duration-200">
        <!-- CABECERA -->
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between bg-[#1f1406]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-950/80 border border-amber-500/40 flex items-center justify-center text-amber-300 shadow-md">
                    <span class="material-symbols-outlined text-2xl">edit_note</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white font-mono flex items-center gap-2">
                        <span>Editar Dispositivo de Red</span>
                        <span id="edit-device-slot" class="px-2 py-0.5 rounded text-[10px] bg-amber-950 border border-amber-500/40 text-amber-300 font-mono">--</span>
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono">Modificación de especificaciones y sincronización directa</p>
                </div>
            </div>
            <button type="button" onclick="closeDeviceEditModal()" class="text-obsidian-muted hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- FORMULARIO -->
        <form id="form-device-edit" method="POST" action="" class="flex flex-col flex-1 overflow-hidden">
            @csrf
            @method('PUT')
            <div class="p-6 space-y-4 overflow-y-auto max-h-[70vh]">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- NOMBRE -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Nombre del Dispositivo *</label>
                        <input type="text" name="name" id="edit-name" required class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- IP -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Dirección IP *</label>
                        <input type="text" name="ip" id="edit-ip" required class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- MAC -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Dirección MAC</label>
                        <input type="text" name="mac" id="edit-mac" placeholder="00:00:00:00:00:00" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- FABRICANTE -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Fabricante / Vendor</label>
                        <input type="text" name="vendor_data" id="edit-vendor-data" placeholder="Ej: Cisco Systems, Inc" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- MODELO -->
                    <div>
                        <label class="block text-xs font-mono text-cyan-400 mb-1 font-semibold uppercase">Modelo de Hardware</label>
                        <input type="text" name="model" id="edit-model" placeholder="Ej: Catalyst 2960 / Catalyst XXXXX" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-200 text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- SERIAL -->
                    <div>
                        <label class="block text-xs font-mono text-amber-400 mb-1 font-semibold uppercase">Número de Serie (Serial)</label>
                        <input type="text" name="serial" id="edit-serial" placeholder="Ej: XXXXXX-XXXX" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-amber-500/40 text-amber-200 text-xs font-mono focus:border-amber-400 focus:outline-hidden">
                    </div>

                    <!-- PUERTOS -->
                    <div>
                        <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Especificación de Puertos</label>
                        <input type="text" name="ports" id="edit-ports" placeholder="Ej: SW: 48 puertos" class="w-full px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    </div>

                    <!-- TIPO Y PUERTO DE ACCESO -->
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Acceso</label>
                            <select name="access_type" id="edit-access-type" class="w-full px-2 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                                <option value="TELNET">TELNET</option>
                                <option value="WEB">WEB</option>
                                <option value="VNC">VNC</option>
                                <option value="SIN SOPORTE">SIN SOPORTE</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-obsidian-muted mb-1 font-semibold uppercase">Puerto</label>
                            <input type="number" name="access_port" id="edit-access-port" placeholder="23" min="1" max="65535" class="w-full px-2 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                        </div>
                    </div>
                </div>

                <!-- NOTAS TÉCNICAS (MULTILÍNEA) -->
                <div>
                    <label class="block text-xs font-mono text-cyan-400 mb-1 font-semibold uppercase flex items-center justify-between">
                        <span>Notas Técnicas & Conexión de Puertos (Multilínea)</span>
                        <span class="text-[10px] text-obsidian-muted normal-case font-normal">Saltos de línea permitidos</span>
                    </label>
                    <textarea name="notes" id="edit-notes" rows="6" placeholder="Puerto 43 cascada con sw3&#10;Puerto 44 Ciau Paseo Mariño&#10;Puerto 45 Ciau Consolidado&#10;Puerto 46 Sede transmisión&#10;Puerto 47 cascada con sw1&#10;Puerto 48 conexión al router" class="w-full px-3.5 py-2.5 rounded-xl bg-[#020b14] border border-cyan-500/40 text-cyan-200 text-xs font-mono focus:border-cyan-400 focus:outline-hidden scrollbar-thin leading-relaxed"></textarea>
                </div>

                <!-- ACTIVO -->
                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" name="is_active" id="edit-is-active" value="1" class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan focus:ring-0">
                    <label for="edit-is-active" class="text-xs font-mono text-white cursor-pointer select-none">
                        Monitoreo activo por ping ICMP continuo
                    </label>
                </div>
            </div>

            <!-- PIE -->
            <div class="p-4 px-6 border-t border-obsidian-border bg-[#1f1406] flex items-center justify-end gap-3">
                <button type="button" onclick="closeDeviceEditModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs font-mono hover:bg-white/10 transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-black font-bold text-xs font-mono transition flex items-center gap-1.5 shadow-lg shadow-amber-500/20">
                    <span class="material-symbols-outlined text-base">save</span>
                    Guardar Cambios & Sincronizar
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- ========================================================================= -->
<!-- MODAL 3: HISTÓRICO & TELEMETRÍA (OPERADORES Y ADMINISTRADORES)            -->
<!-- ========================================================================= -->
<div id="modal-device-history" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel w-full max-w-4xl rounded-2xl border border-obsidian-border flex flex-col overflow-hidden shadow-2xl bg-[#040d1a]/95 animate-in fade-in zoom-in-95 duration-200">
        <!-- CABECERA -->
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between bg-[#061527]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-950/80 border border-purple-500/40 flex items-center justify-center text-purple-300 shadow-md">
                    <span class="material-symbols-outlined text-2xl">show_chart</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white font-mono flex items-center gap-2">
                        <span id="hist-device-name">--</span>
                        <span id="hist-device-slot" class="px-2 py-0.5 rounded text-[10px] bg-obsidian-panel border border-obsidian-border text-purple-300 font-mono">--</span>
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono" id="hist-device-ip">--</p>
                </div>
            </div>
            <button type="button" onclick="closeDeviceHistoryModal()" class="text-obsidian-muted hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- CONTENIDO -->
        <div class="p-6 space-y-6 overflow-y-auto max-h-[75vh]">
            <!-- SELECTOR DE RANGO Y STATS -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-1.5 p-1 bg-obsidian-panel rounded-lg border border-obsidian-border font-mono text-xs">
                    <button type="button" onclick="loadDeviceHistory('6h')" class="hist-range-btn px-3 py-1.5 rounded transition font-semibold" data-range="6h">6 Horas</button>
                    <button type="button" onclick="loadDeviceHistory('24h')" class="hist-range-btn px-3 py-1.5 rounded transition font-semibold bg-obsidian-cyan text-black" data-range="24h">24 Horas</button>
                    <button type="button" onclick="loadDeviceHistory('7d')" class="hist-range-btn px-3 py-1.5 rounded transition font-semibold text-obsidian-muted hover:text-white" data-range="7d">7 Días</button>
                    <button type="button" onclick="loadDeviceHistory('30d')" class="hist-range-btn px-3 py-1.5 rounded transition font-semibold text-obsidian-muted hover:text-white" data-range="30d">30 Días</button>
                </div>
                <div class="flex items-center gap-4 font-mono text-xs">
                    <div class="text-right">
                        <span class="text-obsidian-muted text-[10px] uppercase">Disponibilidad</span>
                        <div class="text-emerald-400 font-bold text-sm" id="hist-stat-uptime">--%</div>
                    </div>
                    <div class="text-right">
                        <span class="text-obsidian-muted text-[10px] uppercase">Latencia Prom.</span>
                        <div class="text-white font-bold text-sm" id="hist-stat-avg">-- ms</div>
                    </div>
                </div>
            </div>

            <!-- GRÁFICO CHART.JS -->
            <div class="glass-card p-4 rounded-xl border border-obsidian-border/80 relative min-h-[260px] flex items-center justify-center">
                <canvas id="deviceHistoryChart" class="w-full h-64"></canvas>
                <div id="chart-loading-overlay" class="absolute inset-0 flex items-center justify-center bg-obsidian-bg/80 backdrop-blur-xs rounded-xl hidden">
                    <div class="flex items-center gap-2 text-obsidian-cyan font-mono text-xs">
                        <span class="material-symbols-outlined text-lg animate-spin">progress_activity</span>
                        <span>Cargando datos históricos...</span>
                    </div>
                </div>
            </div>

            <!-- REGISTRO DE INCIDENTES -->
            <div class="space-y-2">
                <h4 class="text-xs font-mono font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-red-400">warning</span>
                    Registro de Incidentes y Caídas
                </h4>
                <div class="overflow-x-auto rounded-xl border border-obsidian-border/80">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-obsidian-panel border-b border-obsidian-border text-obsidian-muted text-[10px] uppercase">
                            <tr>
                                <th class="px-4 py-2.5">Fecha y Hora</th>
                                <th class="px-4 py-2.5">Tiempo Transcurrido</th>
                                <th class="px-4 py-2.5">Diagnóstico Reportado</th>
                            </tr>
                        </thead>
                        <tbody id="hist-incidents-tbody" class="divide-y divide-obsidian-border/60">
                            <tr>
                                <td colspan="3" class="px-4 py-4 text-center text-obsidian-muted">
                                    Sin incidentes registrados en este período.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentDeviceId = null;
    let currentDeviceRange = '24h';
    let deviceChart = null;

    // --- MODAL DE DETALLES TÉCNICOS ---
    function openDeviceDetailsModal(dev) {
        document.getElementById('det-device-name').innerText = dev.name || 'Dispositivo';
        document.getElementById('det-device-slot').innerText = 'SLOT #' + (dev.device_number || '--');
        document.getElementById('det-device-vendor').innerText = dev.vendor_data ? ('Fabricante: ' + dev.vendor_data) : 'Equipo de Infraestructura LAN';
        document.getElementById('det-device-ip').innerText = dev.ip || '--';
        document.getElementById('det-device-mac').innerText = dev.mac || 'No especificada';
        document.getElementById('det-device-model').innerText = dev.model || 'No especificado';
        document.getElementById('det-device-serial').innerText = dev.serial || 'No especificado';
        document.getElementById('det-device-ports').innerText = dev.ports || 'No especificado';
        
        const acc = (dev.access_type || 'SIN SOPORTE').toUpperCase();
        const port = dev.access_port || (acc === 'TELNET' ? 23 : (acc === 'WEB' ? 80 : 5900));
        document.getElementById('det-device-access').innerText = acc !== 'SIN SOPORTE' ? (acc + ' (Puerto ' + port + ')') : 'SIN ACCESO REMOTO';

        const notesEl = document.getElementById('det-device-notes');
        if (dev.notes && dev.notes.trim().length > 0) {
            notesEl.innerText = dev.notes;
            notesEl.classList.remove('text-obsidian-muted', 'italic');
            notesEl.classList.add('text-cyan-200');
        } else {
            notesEl.innerText = 'Sin notas técnicas o asignaciones de puertos registradas.';
            notesEl.classList.add('text-obsidian-muted', 'italic');
            notesEl.classList.remove('text-cyan-200');
        }

        const m = document.getElementById('modal-device-details');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeDeviceDetailsModal() {
        const m = document.getElementById('modal-device-details');
        m.classList.remove('flex');
        m.classList.add('hidden');
    }

    // --- MODAL DE EDICIÓN (SOLO ADMINISTRADOR) ---
    function openDeviceEditModal(dev) {
        document.getElementById('form-device-edit').action = '/admin/devices/' + dev.id;
        document.getElementById('edit-device-slot').innerText = 'SLOT #' + (dev.device_number || '--');
        document.getElementById('edit-name').value = dev.name || '';
        document.getElementById('edit-ip').value = dev.ip || '';
        document.getElementById('edit-mac').value = dev.mac || '';
        document.getElementById('edit-vendor-data').value = dev.vendor_data || '';
        document.getElementById('edit-model').value = dev.model || '';
        document.getElementById('edit-serial').value = dev.serial || '';
        document.getElementById('edit-ports').value = dev.ports || '';
        document.getElementById('edit-access-type').value = (dev.access_type || 'SIN SOPORTE').toUpperCase();
        document.getElementById('edit-access-port').value = dev.access_port || '';
        document.getElementById('edit-notes').value = dev.notes || '';
        document.getElementById('edit-is-active').checked = !!dev.is_active;

        const m = document.getElementById('modal-device-edit');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeDeviceEditModal() {
        const m = document.getElementById('modal-device-edit');
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
        }
    }

    // --- MODAL DE HISTÓRICO & TELEMETRÍA ---
    function openDeviceHistoryModal(id, name, slot, ip) {
        currentDeviceId = id;
        document.getElementById('hist-device-name').innerText = name;
        document.getElementById('hist-device-slot').innerText = 'SLOT #' + slot;
        document.getElementById('hist-device-ip').innerText = 'Host IP: ' + ip;

        const m = document.getElementById('modal-device-history');
        m.classList.remove('hidden');
        m.classList.add('flex');

        loadDeviceHistory('24h');
    }

    function closeDeviceHistoryModal() {
        const m = document.getElementById('modal-device-history');
        m.classList.remove('flex');
        m.classList.add('hidden');
        if (deviceChart) {
            deviceChart.destroy();
            deviceChart = null;
        }
    }

    async function loadDeviceHistory(range) {
        if (!currentDeviceId) return;
        currentDeviceRange = range;

        // Actualizar botones de rango
        document.querySelectorAll('.hist-range-btn').forEach(btn => {
            if (btn.getAttribute('data-range') === range) {
                btn.className = 'hist-range-btn px-3 py-1.5 rounded transition font-semibold bg-obsidian-cyan text-black';
            } else {
                btn.className = 'hist-range-btn px-3 py-1.5 rounded transition font-semibold text-obsidian-muted hover:text-white';
            }
        });

        const overlay = document.getElementById('chart-loading-overlay');
        if (overlay) overlay.classList.remove('hidden');

        try {
            const res = await fetch(`/admin/devices/${currentDeviceId}/history?range=${range}`);
            const data = await res.json();

            if (!data.success) {
                console.error('Error cargando historial:', data);
                return;
            }

            document.getElementById('hist-stat-uptime').innerText = (data.stats.uptime_percentage ?? 100) + '%';
            document.getElementById('hist-stat-avg').innerText = (data.stats.avg_latency ?? 0) + ' ms';

            // Renderizar incidentes
            const tbody = document.getElementById('hist-incidents-tbody');
            if (data.incidents && data.incidents.length > 0) {
                tbody.innerHTML = data.incidents.map(inc => `
                    <tr class="hover:bg-red-950/20 text-red-300">
                        <td class="px-4 py-2.5 font-bold">${inc.time}</td>
                        <td class="px-4 py-2.5 text-obsidian-muted">${inc.time_human}</td>
                        <td class="px-4 py-2.5">${inc.status_message}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="3" class="px-4 py-4 text-center text-obsidian-muted">
                            Sin incidentes registrados en el período seleccionado.
                        </td>
                    </tr>
                `;
            }

            // Renderizar gráfico
            renderDeviceChart(data.labels, data.latencies, data.statuses);

        } catch (err) {
            console.error('Error de red cargando telemetría:', err);
        } finally {
            if (overlay) overlay.classList.add('hidden');
        }
    }

    function renderDeviceChart(labels, latencies, statuses) {
        const ctx = document.getElementById('deviceHistoryChart').getContext('2d');
        if (deviceChart) {
            deviceChart.destroy();
            deviceChart = null;
        }

        const isAllDown = statuses.length > 0 && statuses.every(s => s === 0);
        const lineColor = isAllDown ? '#ef4444' : '#22d3ee';

        const gradient = ctx.createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, isAllDown ? 'rgba(239, 68, 68, 0.35)' : 'rgba(34, 211, 238, 0.35)');
        gradient.addColorStop(1, 'rgba(0, 0, 0, 0.0)');

        deviceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Latencia (ms)',
                    data: latencies,
                    borderColor: lineColor,
                    borderWidth: 2,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.25,
                    pointRadius: labels.length > 40 ? 0 : 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: statuses.map(s => s === 1 ? '#22d3ee' : '#ef4444'),
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                const val = context.parsed.y;
                                const status = statuses[context.dataIndex] === 1 ? 'OPERATIVO' : 'TIMEOUT';
                                return `Latencia: ${val} ms (${status})`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(28, 46, 71, 0.4)' },
                        ticks: { color: '#64748b', font: { family: 'monospace', size: 10 } }
                    },
                    y: {
                        grid: { color: 'rgba(28, 46, 71, 0.4)' },
                        ticks: { color: '#64748b', font: { family: 'monospace', size: 10 } },
                        beginAtZero: true
                    }
                }
            }
        });
    }
</script>
@endsection
