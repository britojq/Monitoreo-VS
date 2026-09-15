@extends('layouts.admin')

@section('page_title', 'Configuración de Sedes y Enlaces')

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
                <span class="text-xs font-mono text-obsidian-muted">Sedes Regionales & Equipos</span>
            </div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-400">domain</span>
                Sedes Regionales y Equipos de Red Monitoreados
            </h2>
            <p class="text-xs font-mono text-obsidian-muted">Gestión de enlaces troncales, gateways de sedes y topología de equipos locales</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-300 text-xs font-mono flex items-center gap-1.5 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                Fuente: SERVIDOR MAESTRO
            </div>
            @if(auth()->user()->isAdmin() && !$isClusterSlave)
                <button type="button" onclick="openCreateSiteModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold font-mono text-xs transition shadow-sm cursor-pointer">
                    <span class="material-symbols-outlined text-sm">domain_add</span>
                    <span>+ Agregar Sede</span>
                </button>
            @endif
        </div>
    </div>

    <!-- LISTADO DE SEDES -->
    <div class="space-y-4">
        @foreach($sites as $site)
            <div class="glass-card rounded-xl p-5 space-y-4 border border-obsidian-border/80">
                <!-- CABECERA DE SEDE (SIN NOMENCLATURA DE LETRAS) -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-obsidian-border/60 pb-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-950/80 border border-purple-500/40 flex items-center justify-center text-purple-300 shadow-md shrink-0">
                            <span class="material-symbols-outlined text-xl">domain</span>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white font-sans flex items-center gap-2">
                                <span>{{ $site->name }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-obsidian-panel border border-obsidian-border text-obsidian-cyan">
                                    {{ $site->devices->where('is_active', true)->count() }} Equipos Activos
                                </span>
                            </h3>
                            <p class="text-xs font-mono text-obsidian-muted flex items-center gap-2 mt-0.5">
                                <span>Gateway IP: <strong class="text-white">{{ $site->ip ?: 'No configurado' }}</strong></span>
                                @if($site->address)
                                    <span class="text-obsidian-border">|</span>
                                    <span class="truncate max-w-xs">{{ $site->address }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if(auth()->user()->isAdmin())
                            <form action="{{ route('admin.sites.toggle', $site->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold font-mono transition {{ $site->is_active ? 'bg-emerald-950/70 text-emerald-400 border border-emerald-500/40' : 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $site->is_active ? 'bg-emerald-400' : 'bg-obsidian-muted' }}"></span>
                                    {{ $site->is_active ? 'Activa' : 'Desactivada' }}
                                </button>
                            </form>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold font-mono {{ $site->is_active ? 'bg-emerald-950/70 text-emerald-400 border border-emerald-500/40' : 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $site->is_active ? 'bg-emerald-400' : 'bg-obsidian-muted' }}"></span>
                                {{ $site->is_active ? 'Activa' : 'Desactivada' }}
                            </span>
                        @endif

                        <button type="button" onclick="openSiteHistoryModal({{ $site->id }}, '{{ addslashes($site->name) }}', '{{ $site->ip }}')" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-purple/40 text-obsidian-purple hover:bg-obsidian-purple hover:text-white font-mono text-xs transition flex items-center gap-1 shadow-sm cursor-pointer" title="Ver Gráficos y Métricas Temporales">
                            <span class="material-symbols-outlined text-sm">show_chart</span>
                            <span>Histórico</span>
                        </button>

                        @if(auth()->user()->isAdmin())
                            @if($isClusterSlave)
                                <span class="px-3 py-1.5 rounded-lg bg-gray-900 border border-gray-800 text-gray-500 font-mono text-xs inline-flex items-center gap-1" title="Modificaciones restringidas al Servidor Master">
                                    <span class="material-symbols-outlined text-xs">lock</span>
                                    Solo Lectura
                                </span>
                            @else
                                <button type="button" onclick="openEditSiteModal({{ json_encode($site) }})" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-xs transition flex items-center gap-1 cursor-pointer" title="Configurar Parámetros y Equipos">
                                    <span class="material-symbols-outlined text-sm">tune</span>
                                    <span>Modificar</span>
                                </button>
                                <form action="{{ route('admin.sites.destroy', $site->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Está seguro de eliminar la sede [{{ addslashes($site->name) }}] y todos sus equipos asociados?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition inline-flex items-center" title="Eliminar Sede">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>

                <!-- INFO DE CONTACTO -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs font-mono text-obsidian-muted bg-obsidian-panel/40 p-3 rounded-lg border border-obsidian-border/40">
                    <div class="truncate">
                        <span class="text-white font-semibold">Dirección:</span> {{ $site->address ?: 'No especificada' }}
                    </div>
                    <div class="truncate">
                        <span class="text-white font-semibold">Teléfonos:</span> {{ $site->phone_1 ?: 'N/A' }} {{ $site->phone_2 ? ' / ' . $site->phone_2 : '' }}
                    </div>
                </div>

                <!-- EQUIPOS DE RED ASOCIADOS A ESTA SEDE -->
                <div>
                    <div class="flex items-center justify-between mb-2.5">
                        <span class="text-[11px] font-mono uppercase text-obsidian-muted font-semibold flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-cyan-400">devices</span>
                            Equipos de Red Asociados ({{ $site->devices->where('is_active', true)->count() }} Activos de {{ $site->devices->count() }})
                        </span>
                        @if(auth()->user()->isAdmin() && !$isClusterSlave)
                            <button type="button" onclick="openAddDeviceModal({{ $site->id }}, '{{ addslashes($site->name) }}')" class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-obsidian-panel border border-cyan-500/40 text-[11px] font-mono text-cyan-300 hover:bg-cyan-500 hover:text-black transition cursor-pointer">
                                <span class="material-symbols-outlined text-xs">add</span>
                                Agregar Dispositivo
                            </button>
                        @endif
                    </div>

                    @if($site->devices->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($site->devices as $dev)
                                @php
                                    $devAcc = strtoupper(trim($dev->access_type ?? 'SIN SOPORTE'));
                                    $devPort = $dev->access_port ?: ($devAcc === 'SSH' ? 22 : ($devAcc === 'TELNET' ? 23 : ($devAcc === 'WEB' ? 80 : 5900)));
                                    $netDev = isset($networkDevices) ? $networkDevices->get($dev->ip) : null;
                                    $netDevId = $netDev ? $netDev->id : $dev->id;
                                    $snap = isset($snapshotDevices) ? $snapshotDevices->get($dev->ip) : null;
                                    $isUp = $snap ? ($snap['is_up'] ?? false) : $dev->is_active;
                                    $lat = $snap ? ($snap['latency_ms'] ?? 0) : 0;
                                    $latStr = $lat > 0 ? ($lat . ' ms') : ($isUp ? '< 15 ms' : '--');

                                    // Determinar Icono del Tipo de Dispositivo
                                    $nameUpper = strtoupper($dev->name);
                                    $devIcon = 'devices';
                                    $devCategory = 'Dispositivo';
                                    if (str_contains($nameUpper, 'ROUTER')) {
                                        $devIcon = 'router';
                                        $devCategory = 'Router WAN';
                                    } elseif (str_contains($nameUpper, 'SW') || str_contains($nameUpper, 'SWITCH')) {
                                        $devIcon = 'hub';
                                        $devCategory = 'Switch';
                                    } elseif (str_contains($nameUpper, 'IMPRESORA') || str_contains($nameUpper, 'PRINT')) {
                                        $devIcon = 'print';
                                        $devCategory = 'Impresora';
                                    } elseif (str_contains($nameUpper, 'TAQUILLA') || str_contains($nameUpper, 'ATU') || str_contains($nameUpper, 'JEFE') || str_contains($nameUpper, 'EQUIPO') || str_contains($nameUpper, 'PC')) {
                                        $devIcon = 'desktop_windows';
                                        $devCategory = 'Estación / PC';
                                    } elseif (str_contains($nameUpper, 'SRV') || str_contains($nameUpper, 'SERVIDOR')) {
                                        $devIcon = 'dns';
                                        $devCategory = 'Servidor';
                                    }
                                @endphp
                                <div class="p-3 rounded-xl border text-xs font-mono relative flex flex-col justify-between transition {{ $dev->is_active ? 'bg-obsidian-panel/80 border-obsidian-border hover:border-cyan-500/40 shadow-xs' : 'bg-obsidian-panel/30 border-obsidian-border/30 opacity-60' }}">
                                    <div>
                                        <!-- CABECERA DISPOSITIVO -->
                                        <div class="flex items-center justify-between text-[10px] text-obsidian-muted border-b border-obsidian-border/40 pb-2 mb-2">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="material-symbols-outlined text-[15px] text-cyan-400 shrink-0" title="{{ $devCategory }}">{{ $devIcon }}</span>
                                                <span class="font-bold text-obsidian-cyan shrink-0">#{{ $dev->device_number ?: $dev->id }}</span>
                                                <span class="text-[9px] text-obsidian-muted truncate hidden sm:inline" title="{{ $devCategory }}">{{ $devCategory }}</span>
                                            </div>

                                            <div class="flex items-center gap-1.5 shrink-0">
                                                <!-- ENLACE / ACCESO DIRECTO EN EL ENCABEZADO (SIN DUPLICAR ABAJO) -->
                                                @if($devAcc === 'SSH')
                                                    <button type="button"
                                                            onclick="openSshTerminal('{{ $dev->ip }}', {{ $devPort }}, '{{ addslashes($dev->name) }}', '{{ addslashes($site->name) }}')"
                                                            class="px-2 py-0.5 rounded text-[9.5px] font-bold bg-emerald-950/90 hover:bg-emerald-500 hover:text-black border border-emerald-500/50 text-emerald-300 transition flex items-center gap-1 cursor-pointer shadow-xs"
                                                            title="Conectar por SSH (Puerto {{ $devPort }})">
                                                        <span class="material-symbols-outlined text-[11px]">terminal</span>
                                                        <span>SSH</span>
                                                    </button>
                                                @elseif($devAcc === 'TELNET')
                                                    <button type="button"
                                                            onclick="openTelnetTerminal('{{ $dev->ip }}', {{ $devPort }}, '{{ addslashes($dev->name) }}', '{{ addslashes($site->name) }}')"
                                                            class="px-2 py-0.5 rounded text-[9.5px] font-bold bg-cyan-950/90 hover:bg-cyan-500 hover:text-black border border-cyan-500/50 text-cyan-300 transition flex items-center gap-1 cursor-pointer shadow-xs"
                                                            title="Conectar por Telnet (Puerto {{ $devPort }})">
                                                        <span class="material-symbols-outlined text-[11px]">terminal</span>
                                                        <span>TELNET</span>
                                                    </button>
                                                @elseif($devAcc === 'WEB')
                                                    <a href="http://{{ $dev->ip }}:{{ $devPort }}"
                                                       target="_blank"
                                                       class="px-2 py-0.5 rounded text-[9.5px] font-bold bg-blue-950/90 hover:bg-blue-500 hover:text-white border border-blue-500/50 text-blue-300 transition flex items-center gap-1 cursor-pointer shadow-xs"
                                                       title="Abrir Panel Web (Puerto {{ $devPort }})">
                                                        <span class="material-symbols-outlined text-[11px]">open_in_browser</span>
                                                        <span>WEB</span>
                                                    </a>
                                                @elseif($devAcc === 'VNC')
                                                    <a href="/vnc.html?host={{ $dev->ip }}&port={{ $devPort }}"
                                                       target="_blank"
                                                       class="px-2 py-0.5 rounded text-[9.5px] font-bold bg-purple-950/90 hover:bg-purple-500 hover:text-white border border-purple-500/50 text-purple-300 transition flex items-center gap-1 cursor-pointer shadow-xs"
                                                       title="Conectar Escritorio Remoto VNC (Puerto {{ $devPort }})">
                                                        <span class="material-symbols-outlined text-[11px]">desktop_windows</span>
                                                        <span>VNC</span>
                                                    </a>
                                                @else
                                                    <span class="px-1.5 py-0.5 rounded text-[8.5px] bg-obsidian-panel border border-obsidian-border text-obsidian-muted inline-flex items-center gap-0.5" title="Sin protocolo de acceso remoto configurado">
                                                        <span class="material-symbols-outlined text-[10px]">power_off</span>
                                                        <span>S/S</span>
                                                    </span>
                                                @endif

                                                <!-- ESTADO ON/OFF -->
                                                <span class="px-1.5 py-0.5 rounded text-[8.5px] font-bold {{ $dev->is_active ? 'bg-emerald-950/70 text-emerald-400 border border-emerald-500/40' : 'bg-gray-900 text-obsidian-muted border border-obsidian-border' }}">
                                                    {{ $dev->is_active ? 'ON' : 'OFF' }}
                                                </span>

                                                @if(auth()->user()->isAdmin() && !$isClusterSlave)
                                                    <form action="{{ route('admin.sites.devices.destroy', $dev->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar equipo [{{ addslashes($dev->name) }}]?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-400 hover:text-red-200 transition text-xs leading-none p-0.5 rounded hover:bg-red-950/30" title="Eliminar Equipo">
                                                            <span class="material-symbols-outlined text-[13px]">delete</span>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- NOMBRE E IP -->
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full shrink-0 {{ $isUp ? 'bg-emerald-400 glow-green' : 'bg-red-500' }}" title="{{ $isUp ? 'Online (ICMP Respondido)' : 'Offline' }}"></span>
                                            <div class="text-white font-sans font-bold text-xs truncate" title="{{ $dev->name }}">{{ $dev->name }}</div>
                                        </div>

                                        <div class="flex items-center justify-between text-[11px] font-mono mt-1">
                                            <span class="text-obsidian-cyan font-semibold">{{ $dev->ip }}</span>
                                            <span class="text-[9.5px] text-obsidian-muted">{{ $latStr }}</span>
                                        </div>

                                        @if($dev->mac)
                                            <div class="text-obsidian-muted text-[10px] mt-0.5">MAC: <span class="text-gray-300">{{ $dev->mac }}</span></div>
                                        @endif
                                        @if($dev->model || $dev->vendor_data)
                                            <div class="text-amber-300/80 text-[10px] mt-0.5 truncate" title="{{ $dev->vendor_data }} {{ $dev->model }}">
                                                {{ $dev->vendor_data ?: 'Mod:' }} {{ $dev->model }} {{ $dev->serial ? '| S/N: ' . $dev->serial : '' }}
                                            </div>
                                        @endif
                                        @if($dev->ports)
                                            <div class="text-obsidian-muted text-[9.5px] mt-0.5 truncate" title="{{ $dev->ports }}">
                                                Puertos: {{ $dev->ports }}
                                            </div>
                                        @endif
                                    </div>

                                    <!-- CUADRO CON LA GRÁFICA HISTÓRICA DEBAJO DEL DISPOSITIVO -->
                                    <div class="mt-2.5 pt-2 border-t border-obsidian-border/50">
                                        <button type="button"
                                                onclick="openDeviceHistoryModal({{ $netDevId }}, '{{ addslashes($dev->name) }}', '{{ $dev->ip }}', '{{ $dev->device_number ?: $dev->id }}')"
                                                class="w-full py-1.5 px-2.5 rounded-lg bg-obsidian-panel/90 hover:bg-purple-950/40 border border-obsidian-border hover:border-purple-500/50 text-purple-300 hover:text-white font-mono text-[10.5px] font-semibold transition flex items-center justify-between group shadow-xs cursor-pointer"
                                                title="Ver Gráfica Histórica de Telemetría (Ping 24h)">
                                            <span class="flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-[14px] text-purple-400 group-hover:scale-110 transition-transform">show_chart</span>
                                                <span>Gráfica Histórica</span>
                                            </span>
                                            <span class="text-[9px] text-purple-400/80 group-hover:text-purple-300 flex items-center gap-0.5 font-normal">
                                                Ver Telemetría
                                                <span class="material-symbols-outlined text-[11px]">arrow_forward</span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-xs font-mono text-obsidian-muted bg-obsidian-panel/20 p-4 rounded-xl border border-dashed border-obsidian-border text-center">
                            Sin equipos de red configurados para esta sede.
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

@if(auth()->user()->isAdmin())
<!-- ========================================================================= -->
<!-- MODAL CREAR NUEVA SEDE (Solo Administrador)                               -->
<!-- ========================================================================= -->
<div id="modal-create-site" onclick="if(event.target === this) closeModal('modal-create-site')" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-lg w-full rounded-2xl p-6 border border-obsidian-border shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <h3 class="text-sm font-bold text-white uppercase font-mono flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-400 text-base">domain_add</span>
                Agregar Nueva Sede Regional
            </h3>
            <button type="button" onclick="closeModal('modal-create-site')" class="text-obsidian-muted hover:text-white text-xl p-1 rounded hover:bg-obsidian-panel leading-none">&times;</button>
        </div>
        <form id="form-create-site" action="{{ route('admin.sites.store') }}" method="POST" class="space-y-4 font-mono text-xs">
            @csrf
            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Nombre de la Sede *</label>
                <input type="text" name="name" required placeholder="ej. CIAU Morón / CIAU Pto Cabello" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">IP Gateway / Enlace:</label>
                    <input type="text" name="ip" placeholder="ej. 10.20.106.193" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Dirección Física:</label>
                    <input type="text" name="address" placeholder="Av. Principal, Edif. ..." class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Teléfono Principal:</label>
                    <input type="text" name="phone_1" placeholder="ej. 0242-3600000" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Teléfono Secundario:</label>
                    <input type="text" name="phone_2" placeholder="ej. 0414-0000000" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" id="create-site-active" name="is_active" value="1" checked class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan"/>
                <label for="create-site-active" class="text-obsidian-muted cursor-pointer select-none">Habilitar monitoreo de sede inmediatamente</label>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-obsidian-border">
                <button type="button" onclick="closeModal('modal-create-site')" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">save</span>
                    Guardar Sede
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL AGREGAR DISPOSITIVO A SEDE (Solo Administrador)                     -->
<!-- ========================================================================= -->
<div id="modal-add-device" onclick="if(event.target === this) closeModal('modal-add-device')" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-xl w-full rounded-2xl p-6 border border-obsidian-border shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <div>
                <h3 class="text-sm font-bold text-white uppercase font-mono flex items-center gap-2">
                    <span class="material-symbols-outlined text-cyan-400 text-base">router</span>
                    Agregar Dispositivo a Sede
                </h3>
                <p id="add-device-site-name" class="text-[11px] font-mono text-purple-300 mt-0.5">Sede</p>
            </div>
            <button type="button" onclick="closeModal('modal-add-device')" class="text-obsidian-muted hover:text-white text-xl p-1 rounded hover:bg-obsidian-panel leading-none">&times;</button>
        </div>
        <form id="form-add-device" method="POST" class="space-y-4 font-mono text-xs">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Nombre del Dispositivo *</label>
                    <input type="text" name="name" required placeholder="ej. SW Cisco Taquillas / ATU 01" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Dirección IP *</label>
                    <input type="text" name="ip" required placeholder="ej. 10.20.106.240" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Dirección MAC</label>
                    <input type="text" name="mac" placeholder="00:00:00:00:00:00" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Fabricante / Vendor</label>
                    <input type="text" name="vendor_data" placeholder="ej. Cisco Systems" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Modelo de Hardware</label>
                    <input type="text" name="model" placeholder="ej. Catalyst 2960" class="w-full bg-obsidian-panel border border-cyan-500/40 rounded-lg p-2 text-cyan-200 focus:border-cyan-400 focus:outline-hidden"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Número de Serie (Serial)</label>
                    <input type="text" name="serial" placeholder="ej. FOC1234..." class="w-full bg-obsidian-panel border border-amber-500/40 rounded-lg p-2 text-amber-200 focus:border-amber-400 focus:outline-hidden"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Especificación de Puertos</label>
                    <input type="text" name="ports" placeholder="ej. SW: 24 puertos" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:border-cyan-400 focus:outline-hidden"/>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Acceso</label>
                        <select name="access_type" id="modal-add-access-type" onchange="handleAddDeviceAccessChange(this.value)" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:border-cyan-400 focus:outline-hidden">
                            <option value="SSH">SSH</option>
                            <option value="TELNET">TELNET</option>
                            <option value="WEB">WEB</option>
                            <option value="VNC">VNC</option>
                            <option value="SIN SOPORTE">SIN SOPORTE</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Puerto</label>
                        <input type="number" name="access_port" id="modal-add-access-port" value="22" min="1" max="65535" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:border-cyan-400 focus:outline-hidden"/>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Notas Técnicas & Distribución de Puertos</label>
                <textarea name="notes" rows="3" placeholder="Información técnica..." class="w-full bg-[#020b14] border border-cyan-500/40 rounded-lg p-2 text-cyan-200 focus:border-cyan-400 focus:outline-hidden text-xs"></textarea>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" id="add-device-active" name="is_active" value="1" checked class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan"/>
                <label for="add-device-active" class="text-obsidian-muted cursor-pointer select-none">Habilitar monitoreo del dispositivo</label>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-obsidian-border">
                <button type="button" onclick="closeModal('modal-add-device')" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-bold flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    Registrar Equipo
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL EDITAR SEDE & GESTIONAR EQUIPOS DINÁMICAMENTE (Solo Administrador) -->
<!-- ========================================================================= -->
<div id="modal-edit-site" onclick="if(event.target === this) closeModal('modal-edit-site')" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-4xl w-full rounded-2xl p-6 border border-obsidian-border shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto custom-scroll bg-[#040d1a]/95">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <h3 class="text-sm font-bold text-white uppercase font-mono flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan text-base">tune</span>
                Configurar Sede & Administrar Equipos de Red
            </h3>
            <button type="button" onclick="closeModal('modal-edit-site')" class="text-obsidian-muted hover:text-white text-xl p-1 rounded hover:bg-obsidian-panel leading-none">&times;</button>
        </div>
        <form id="form-edit-site" method="POST" class="space-y-5 font-mono text-xs">
            @csrf
            @method('PUT')
            
            <!-- SECCIÓN 1: DATOS BÁSICOS DE LA SEDE -->
            <div class="space-y-3 bg-obsidian-panel/60 p-4 rounded-xl border border-obsidian-border/60">
                <span class="text-[11px] font-bold text-purple-300 uppercase tracking-wider block border-b border-obsidian-border/40 pb-1.5 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">domain</span>
                    1. Parámetros Principales de la Sede
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Nombre de la Sede *</label>
                        <input type="text" id="edit_st_name" name="name" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:border-cyan-400 focus:outline-hidden"/>
                    </div>
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">IP Gateway / Enlace:</label>
                        <input type="text" id="edit_st_ip" name="ip" placeholder="ej. 192.168.1.1" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:border-cyan-400 focus:outline-hidden"/>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Dirección Física:</label>
                        <input type="text" id="edit_st_address" name="address" placeholder="Ubicación o Edificio" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:border-cyan-400 focus:outline-hidden"/>
                    </div>
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Teléfono Principal:</label>
                        <input type="text" id="edit_st_phone_1" name="phone_1" placeholder="ej. 0241-1234567" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:border-cyan-400 focus:outline-hidden"/>
                    </div>
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px] font-semibold uppercase">Teléfono Secundario:</label>
                        <input type="text" id="edit_st_phone_2" name="phone_2" placeholder="ej. 0414-7654321" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:border-cyan-400 focus:outline-hidden"/>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" id="edit_st_active" name="is_active" value="1" class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan"/>
                    <label for="edit_st_active" class="text-obsidian-muted cursor-pointer select-none">Sede activa en el monitoreo continuo</label>
                </div>
            </div>

            <!-- SECCIÓN 2: EQUIPOS ASOCIADOS A ESTA SEDE (REPETIDOR DINÁMICO) -->
            <div class="space-y-3 bg-obsidian-panel/60 p-4 rounded-xl border border-obsidian-border/60">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-obsidian-border/40 pb-2 gap-2">
                    <div>
                        <span class="text-[11px] font-bold text-obsidian-cyan uppercase tracking-wider block flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm">router</span>
                            2. Equipos de Red de esta Sede
                        </span>
                        <p class="text-[10px] text-obsidian-muted">Ajuste técnico uniforme, adición dinámica y retiro de equipos</p>
                    </div>
                    <button type="button" onclick="addNewDeviceRow()" class="px-3 py-1.5 rounded-lg bg-cyan-950/80 border border-cyan-500/50 text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-xs font-mono font-bold flex items-center gap-1 self-start sm:self-auto cursor-pointer shadow-xs">
                        <span class="material-symbols-outlined text-sm">add_circle</span>
                        <span>+ Agregar Dispositivo a esta Sede</span>
                    </button>
                </div>
                
                <div id="devices-edit-container" class="space-y-3 max-h-80 overflow-y-auto custom-scroll pr-1">
                    <!-- Dinámico vía JS -->
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-obsidian-border">
                <button type="button" onclick="closeModal('modal-edit-site')" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold hover:bg-white transition flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">save</span>
                    Guardar Cambios de Sede & Equipos
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- ========================================================================= -->
<!-- MODAL HISTÓRICO Y GRÁFICO DE LÍNEA TEMPORAL PARA SEDES                     -->
<!-- ========================================================================= -->
<div id="modal-site-history" onclick="if(event.target === this) closeModal('modal-site-history')" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden items-center justify-center p-3 sm:p-6">
    <div class="glass-panel max-w-4xl w-full rounded-2xl border border-obsidian-border/90 shadow-2xl space-y-4 max-h-[95vh] overflow-y-auto custom-scroll p-5 sm:p-6 bg-[#07172b]/95">
        <!-- CABECERA DEL HISTÓRICO -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-obsidian-border/80 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-950/80 border border-purple-500/40 flex items-center justify-center text-purple-300 shadow-md shrink-0">
                    <span class="material-symbols-outlined text-xl">domain</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 id="hist-site-name" class="text-base font-bold text-white font-sans truncate max-w-md">Sede</h3>
                        <span class="text-[9px] font-mono font-bold uppercase bg-obsidian-panel text-obsidian-cyan px-2 py-0.5 rounded border border-obsidian-border">ENLACE SEDE</span>
                    </div>
                    <p id="hist-site-ip" class="text-xs font-mono text-obsidian-muted mt-0.5 truncate">IP Gateway: 10.20.0.1</p>
                </div>
            </div>

            <!-- CONTROLES DE RANGO TEMPORAL -->
            <div class="flex items-center gap-2 self-end sm:self-auto">
                <div class="inline-flex rounded-lg p-1 bg-obsidian-panel border border-obsidian-border text-xs font-mono">
                    <button type="button" onclick="loadSiteHistory('6h')" id="btn-site-range-6h" class="px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition site-range-btn">6H</button>
                    <button type="button" onclick="loadSiteHistory('24h')" id="btn-site-range-24h" class="px-2.5 py-1 rounded-md bg-obsidian-purple text-white font-bold transition site-range-btn">24H</button>
                    <button type="button" onclick="loadSiteHistory('7d')" id="btn-site-range-7d" class="px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition site-range-btn">7D</button>
                    <button type="button" onclick="loadSiteHistory('30d')" id="btn-site-range-30d" class="px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition site-range-btn">30D</button>
                </div>
                <button type="button" onclick="closeModal('modal-site-history')" class="p-1.5 rounded-lg text-obsidian-muted hover:text-white hover:bg-obsidian-panel text-xl leading-none transition" title="Cerrar">&times;</button>
            </div>
        </div>

        <!-- 4 TARJETAS DE MÉTRICAS KPI -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Disponibilidad Enlace</span>
                    <span class="material-symbols-outlined text-xs text-emerald-400">verified</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-site-stat-uptime" class="text-lg font-bold font-mono text-emerald-400">--%</span>
                </div>
                <p id="hist-site-stat-checks" class="text-[9px] font-mono text-obsidian-muted mt-0.5">-- chequeos totales</p>
            </div>

            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Latencia Media Ping</span>
                    <span class="material-symbols-outlined text-xs text-obsidian-cyan">speed</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-site-stat-avg" class="text-lg font-bold font-mono text-white">-- ms</span>
                </div>
                <p class="text-[9px] font-mono text-obsidian-muted mt-0.5">Tiempo de respuesta</p>
            </div>

            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Latencia Máxima</span>
                    <span class="material-symbols-outlined text-xs text-amber-400">trending_up</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-site-stat-max" class="text-lg font-bold font-mono text-amber-400">-- ms</span>
                </div>
                <p class="text-[9px] font-mono text-obsidian-muted mt-0.5">Pico de latencia</p>
            </div>

            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Caídas de Enlace</span>
                    <span class="material-symbols-outlined text-xs text-red-400">warning</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-site-stat-outages" class="text-lg font-bold font-mono text-white">0</span>
                </div>
                <p id="hist-site-stat-last-down" class="text-[9px] font-mono text-obsidian-muted mt-0.5">Sin incidentes</p>
            </div>
        </div>

        <!-- CONTENEDOR DEL GRÁFICO DE LÍNEA -->
        <div class="glass-card rounded-xl p-4 border border-obsidian-border space-y-3 bg-obsidian-panel/50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-obsidian-purple"></span>
                    <h4 class="text-xs font-bold font-mono uppercase text-white tracking-wider">Histórico Temporal de Conectividad & Ping</h4>
                </div>
                <div class="flex items-center gap-3 text-[10px] font-mono text-obsidian-muted">
                    <span class="flex items-center gap-1"><span class="w-2 h-0.5 bg-obsidian-purple"></span> Ping Enlace (ms)</span>
                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Enlace Caído</span>
                </div>
            </div>
            
            <div class="relative w-full h-[220px]">
                <canvas id="siteHistoryChart"></canvas>
                <div id="site-chart-loading-overlay" class="absolute inset-0 flex items-center justify-center bg-obsidian-bg/80 backdrop-blur-xs rounded-lg hidden">
                    <div class="flex items-center gap-2 text-obsidian-purple font-mono text-xs">
                        <span class="material-symbols-outlined animate-spin text-base">sync</span>
                        <span>Cargando telemetría de sede...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- REGISTRO RECIENTE DE INCIDENTES / CAÍDAS -->
        <div class="glass-card rounded-xl p-4 border border-obsidian-border space-y-2 bg-obsidian-panel/30">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold font-mono uppercase text-white tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-amber-400 text-sm">history_toggle_off</span>
                    Registro de Desconexiones e Incidentes de Enlace
                </h4>
                <span id="hist-site-incidents-badge" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40">
                    0 Incidentes
                </span>
            </div>

            <div class="overflow-x-auto max-h-36 overflow-y-auto custom-scroll">
                <table class="w-full text-left text-[11px] font-mono">
                    <thead class="bg-obsidian-panel/80 text-obsidian-muted uppercase text-[9.5px]">
                        <tr>
                            <th class="px-3 py-1.5">Fecha y Hora</th>
                            <th class="px-3 py-1.5">Tiempo Relativo</th>
                            <th class="px-3 py-1.5">Diagnóstico del Enlace</th>
                            <th class="px-3 py-1.5 text-right">Equipos Activos</th>
                        </tr>
                    </thead>
                    <tbody id="hist-site-incidents-tbody" class="divide-y divide-obsidian-border/40">
                        <tr>
                            <td colspan="4" class="px-3 py-3 text-center text-obsidian-muted text-[10px]">
                                No se registran caídas de enlace en el período seleccionado.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL TELEMETRÍA E HISTÓRICO DE DISPOSITIVO                               -->
<!-- ========================================================================= -->
<div id="modal-device-history" onclick="if(event.target === this) closeDeviceHistoryModal()" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-6">
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
            <button type="button" onclick="closeDeviceHistoryModal()" class="text-obsidian-muted hover:text-white p-1 rounded-lg hover:bg-white/10 transition leading-none text-xl" title="Cerrar">
                &times;
            </button>
        </div>

        <!-- CONTENIDO -->
        <div class="p-6 space-y-6 overflow-y-auto max-h-[75vh] custom-scroll">
            <!-- SELECTOR DE RANGO Y STATS -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-1.5 p-1 bg-obsidian-panel rounded-lg border border-obsidian-border font-mono text-xs">
                    <button type="button" onclick="loadDeviceHistory('6h')" class="hist-device-range-btn px-2.5 py-1 rounded transition font-semibold" data-range="6h">6 Horas</button>
                    <button type="button" onclick="loadDeviceHistory('24h')" class="hist-device-range-btn px-2.5 py-1 rounded transition font-semibold bg-obsidian-cyan text-black" data-range="24h">24 Horas</button>
                    <button type="button" onclick="loadDeviceHistory('7d')" class="hist-device-range-btn px-2.5 py-1 rounded transition font-semibold text-obsidian-muted hover:text-white" data-range="7d">7 Días</button>
                    <button type="button" onclick="loadDeviceHistory('30d')" class="hist-device-range-btn px-2.5 py-1 rounded transition font-semibold text-obsidian-muted hover:text-white" data-range="30d">30 Días</button>
                </div>
                <div class="flex items-center gap-4 font-mono text-xs">
                    <div class="text-right">
                        <span class="text-obsidian-muted text-[10px] uppercase">Disponibilidad</span>
                        <div class="text-emerald-400 font-bold text-sm" id="hist-device-stat-uptime">--%</div>
                    </div>
                    <div class="text-right">
                        <span class="text-obsidian-muted text-[10px] uppercase">Latencia Prom.</span>
                        <div class="text-white font-bold text-sm" id="hist-device-stat-avg">-- ms</div>
                    </div>
                </div>
            </div>

            <!-- GRÁFICO CHART.JS -->
            <div class="glass-card p-4 rounded-xl border border-obsidian-border/80 relative min-h-[260px] flex items-center justify-center">
                <canvas id="deviceHistoryChart" class="w-full h-64"></canvas>
                <div id="device-chart-loading-overlay" class="absolute inset-0 flex items-center justify-center bg-obsidian-bg/80 backdrop-blur-xs rounded-xl hidden">
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
                        <tbody id="hist-device-incidents-tbody" class="divide-y divide-obsidian-border/60">
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
    let currentSiteId = null;
    let currentSiteRange = '24h';
    let siteHistoryChart = null;
    let currentDeviceId = null;
    let currentDeviceRange = '24h';
    let deviceChart = null;
    let newDeviceCounter = 0;

    // --- ACCESO REMOTO HELPERS ---
    function openSshTerminal(ip, port = 22, name = '', site = '') {
        const url = `/admin/ssh/terminal?ip=${encodeURIComponent(ip)}&port=${port}&name=${encodeURIComponent(name)}&site=${encodeURIComponent(site)}`;
        const w = 1100;
        const h = 700;
        const left = (screen.width/2)-(w/2);
        const top = (screen.height/2)-(h/2);
        window.open(url, `ssh_${ip.replace(/\./g, '_')}`, `width=${w},height=${h},top=${top},left=${left},resizable=yes,scrollbars=no,status=no`);
    }

    async function openTelnetTerminal(ip, port = 23, name = '', site = '') {
        try {
            const res = await fetch("{{ route('admin.telnet.session') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ ip, port, name, site })
            });
            const data = await res.json();
            if (data.success && data.viewer_url) {
                const w = 1100;
                const h = 700;
                const left = (screen.width/2)-(w/2);
                const top = (screen.height/2)-(h/2);
                window.open(data.viewer_url, `telnet_${ip.replace(/\./g, '_')}`, `width=${w},height=${h},top=${top},left=${left},resizable=yes,scrollbars=no,status=no`);
            } else {
                alert('No se pudo inicializar la sesión Telnet: ' + (data.message || 'Error desconocido'));
            }
        } catch (err) {
            console.error('Error Telnet:', err);
            alert('Error de comunicación con el proxy Telnet.');
        }
    }

    function openModal(id) {
        const m = document.getElementById(id);
        if (m) {
            m.classList.remove('hidden');
            m.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }
    }

    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    }

    // Cerrar modal al presionar tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('modal-site-history');
            closeModal('modal-edit-site');
            closeModal('modal-create-site');
            closeModal('modal-add-device');
            closeDeviceHistoryModal();
        }
    });

    function openCreateSiteModal() {
        const form = document.getElementById('form-create-site');
        if (form) form.reset();
        openModal('modal-create-site');
    }

    function handleAddDeviceAccessChange(type) {
        const pInput = document.getElementById('modal-add-access-port');
        if (!pInput) return;
        if (type === 'SSH') pInput.value = 22;
        else if (type === 'TELNET') pInput.value = 23;
        else if (type === 'WEB') pInput.value = 80;
        else if (type === 'VNC') pInput.value = 5900;
        else pInput.value = '';
    }

    function openAddDeviceModal(siteId, siteName) {
        const form = document.getElementById('form-add-device');
        if (form) {
            form.action = `/admin/sites/${siteId}/devices`;
            form.reset();
        }
        const siteLabel = document.getElementById('add-device-site-name');
        if (siteLabel) siteLabel.innerText = `Sede: ${siteName}`;
        const accessSelect = document.getElementById('modal-add-access-type');
        if (accessSelect) accessSelect.value = 'SSH';
        const accessPort = document.getElementById('modal-add-access-port');
        if (accessPort) accessPort.value = 22;
        openModal('modal-add-device');
    }

    // --- MODAL MODIFICAR SEDE Y REPETIDOR DE DISPOSITIVOS ---
    function openEditSiteModal(site) {
        document.getElementById('form-edit-site').action = `/admin/sites/${site.id}`;
        document.getElementById('edit_st_name').value = site.name;
        document.getElementById('edit_st_ip').value = site.ip || '';
        document.getElementById('edit_st_phone_1').value = site.phone_1 || '';
        document.getElementById('edit_st_phone_2').value = site.phone_2 || '';
        document.getElementById('edit_st_address').value = site.address || '';
        document.getElementById('edit_st_active').checked = !!site.is_active;

        const devContainer = document.getElementById('devices-edit-container');
        devContainer.innerHTML = '';
        newDeviceCounter = 0;

        if (site.devices && site.devices.length > 0) {
            site.devices.forEach(dev => {
                appendDeviceEditCard(dev, dev.id);
            });
        } else {
            devContainer.innerHTML = `
                <div id="no-devices-msg" class="text-xs font-mono text-obsidian-muted bg-obsidian-panel/30 p-3 rounded-lg border border-dashed border-obsidian-border text-center">
                    No hay equipos registrados en esta sede. Usa el botón superior para agregar uno.
                </div>
            `;
        }

        openModal('modal-edit-site');
    }

    function appendDeviceEditCard(dev, devKey) {
        const devContainer = document.getElementById('devices-edit-container');
        const noMsg = document.getElementById('no-devices-msg');
        if (noMsg) noMsg.remove();

        const card = document.createElement('div');
        card.id = `dev-card-${devKey}`;
        card.className = 'bg-obsidian-panel p-3.5 rounded-xl border border-obsidian-border space-y-2 relative transition hover:border-cyan-500/30';
        
        const acc = (dev.access_type || 'SIN SOPORTE').toUpperCase();
        const port = dev.access_port || (acc === 'SSH' ? 22 : (acc === 'TELNET' ? 23 : (acc === 'WEB' ? 80 : 5900)));
        const isActive = dev.is_active !== undefined ? !!dev.is_active : true;

        card.innerHTML = `
            <!-- ENCABEZADO EQUIPO -->
            <div class="flex items-center justify-between border-b border-obsidian-border/50 pb-1.5">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-obsidian-cyan text-xs">#${dev.device_number || 'NUEVO'}</span>
                    <span class="text-white font-semibold text-xs truncate max-w-[200px]" id="title-${devKey}">${dev.name || 'Nuevo Dispositivo'}</span>
                </div>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-1 text-[11px] text-obsidian-muted cursor-pointer">
                        <input type="checkbox" name="devices[${devKey}][is_active]" value="1" ${isActive ? 'checked' : ''} class="rounded bg-obsidian-bg border-obsidian-border text-obsidian-cyan"/>
                        <span>Activo</span>
                    </label>
                    <button type="button" onclick="removeDeviceCard('${devKey}')" class="text-red-400 hover:text-red-200 transition text-xs flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-red-950/40 border border-red-500/30 cursor-pointer" title="Quitar este equipo">
                        <span class="material-symbols-outlined text-[13px]">delete</span>
                        <span>Quitar</span>
                    </button>
                </div>
            </div>

            <!-- FILA 1: NOMBRE, IP, MAC -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div>
                    <label class="block text-[10px] text-obsidian-muted uppercase font-semibold">Nombre *</label>
                    <input type="text" name="devices[${devKey}][name]" value="${escapeHtml(dev.name || '')}" required placeholder="Nombre del equipo" oninput="document.getElementById('title-${devKey}').innerText = this.value || 'Nuevo Dispositivo'" class="w-full bg-obsidian-bg border border-obsidian-border rounded p-1.5 text-white text-xs"/>
                </div>
                <div>
                    <label class="block text-[10px] text-obsidian-muted uppercase font-semibold">Dirección IP *</label>
                    <input type="text" name="devices[${devKey}][ip]" value="${escapeHtml(dev.ip || '')}" required placeholder="10.20.x.x" class="w-full bg-obsidian-bg border border-obsidian-border rounded p-1.5 text-white text-xs"/>
                </div>
                <div>
                    <label class="block text-[10px] text-obsidian-muted uppercase font-semibold">MAC</label>
                    <input type="text" name="devices[${devKey}][mac]" value="${escapeHtml(dev.mac || '')}" placeholder="00:00:00:00:00:00" class="w-full bg-obsidian-bg border border-obsidian-border rounded p-1.5 text-white text-xs"/>
                </div>
            </div>

            <!-- FILA 2: FABRICANTE, MODELO, SERIAL -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div>
                    <label class="block text-[10px] text-obsidian-muted uppercase font-semibold">Fabricante / Vendor</label>
                    <input type="text" name="devices[${devKey}][vendor_data]" value="${escapeHtml(dev.vendor_data || '')}" placeholder="Cisco Systems..." class="w-full bg-obsidian-bg border border-obsidian-border rounded p-1.5 text-white text-xs"/>
                </div>
                <div>
                    <label class="block text-[10px] text-cyan-400 uppercase font-semibold">Modelo</label>
                    <input type="text" name="devices[${devKey}][model]" value="${escapeHtml(dev.model || '')}" placeholder="Catalyst 2960..." class="w-full bg-obsidian-bg border border-cyan-500/40 rounded p-1.5 text-cyan-200 text-xs"/>
                </div>
                <div>
                    <label class="block text-[10px] text-amber-400 uppercase font-semibold">Serial</label>
                    <input type="text" name="devices[${devKey}][serial]" value="${escapeHtml(dev.serial || '')}" placeholder="S/N" class="w-full bg-obsidian-bg border border-amber-500/40 rounded p-1.5 text-amber-200 text-xs"/>
                </div>
            </div>

            <!-- FILA 3: PUERTOS, ACCESO, PUERTO -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div>
                    <label class="block text-[10px] text-obsidian-muted uppercase font-semibold">Puertos (SW)</label>
                    <input type="text" name="devices[${devKey}][ports]" value="${escapeHtml(dev.ports || '')}" placeholder="48 puertos" class="w-full bg-obsidian-bg border border-obsidian-border rounded p-1.5 text-white text-xs"/>
                </div>
                <div>
                    <label class="block text-[10px] text-obsidian-muted uppercase font-semibold">Acceso</label>
                    <select name="devices[${devKey}][access_type]" onchange="handleRepeaterAccessChange(this, '${devKey}')" class="w-full bg-obsidian-bg border border-obsidian-border rounded p-1.5 text-white text-xs">
                        <option value="SSH" ${acc === 'SSH' ? 'selected' : ''}>SSH</option>
                        <option value="TELNET" ${acc === 'TELNET' ? 'selected' : ''}>TELNET</option>
                        <option value="WEB" ${acc === 'WEB' ? 'selected' : ''}>WEB</option>
                        <option value="VNC" ${acc === 'VNC' ? 'selected' : ''}>VNC</option>
                        <option value="SIN SOPORTE" ${acc === 'SIN SOPORTE' ? 'selected' : ''}>SIN SOPORTE</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] text-obsidian-muted uppercase font-semibold">Puerto Acceso</label>
                    <input type="number" id="port-input-${devKey}" name="devices[${devKey}][access_port]" value="${port || ''}" placeholder="22" class="w-full bg-obsidian-bg border border-obsidian-border rounded p-1.5 text-white text-xs"/>
                </div>
            </div>

            <!-- FILA 4: NOTAS -->
            <div>
                <label class="block text-[10px] text-obsidian-muted uppercase font-semibold">Notas Técnicas & Conexión</label>
                <input type="text" name="devices[${devKey}][notes]" value="${escapeHtml(dev.notes || '')}" placeholder="Notas del equipo o cascadas..." class="w-full bg-obsidian-bg border border-obsidian-border rounded p-1.5 text-white text-xs"/>
            </div>
        `;
        devContainer.appendChild(card);
    }

    function addNewDeviceRow() {
        newDeviceCounter++;
        const newKey = `new_${newDeviceCounter}`;
        const emptyDev = {
            device_number: '+',
            name: '',
            ip: '',
            mac: '',
            vendor_data: '',
            model: '',
            serial: '',
            ports: '',
            access_type: 'SSH',
            access_port: 22,
            notes: '',
            is_active: true
        };
        appendDeviceEditCard(emptyDev, newKey);
    }

    function removeDeviceCard(key) {
        const card = document.getElementById(`dev-card-${key}`);
        if (!card) return;

        if (key.startsWith('new_')) {
            card.remove();
        } else {
            // Es un equipo existente, creamos input oculto _delete
            card.style.opacity = '0.4';
            card.style.border = '1px dashed #ef4444';
            card.innerHTML = `
                <input type="hidden" name="devices[${key}][_delete]" value="1"/>
                <div class="flex items-center justify-between text-xs py-1">
                    <span class="text-red-400 font-bold line-through">Equipo marcado para eliminación al guardar</span>
                    <button type="button" onclick="cancelRemoveDeviceCard('${key}')" class="text-cyan-400 hover:underline text-[11px] cursor-pointer">Deshacer</button>
                </div>
            `;
        }
    }

    function cancelRemoveDeviceCard(key) {
        // Recargar el modal para restaurar estado limpio
        alert('Para restaurar completamente el equipo, cancele y vuelva a abrir el modal de modificar.');
    }

    function handleRepeaterAccessChange(selectEl, key) {
        const pInput = document.getElementById(`port-input-${key}`);
        if (!pInput) return;
        const type = selectEl.value;
        if (type === 'SSH') pInput.value = 22;
        else if (type === 'TELNET') pInput.value = 23;
        else if (type === 'WEB') pInput.value = 80;
        else if (type === 'VNC') pInput.value = 5900;
        else pInput.value = '';
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    async function openSiteHistoryModal(siteId, name, ip) {
        currentSiteId = siteId;
        document.getElementById('hist-site-name').innerText = name;
        document.getElementById('hist-site-ip').innerText = `IP Gateway: ${ip || 'No configurado'}`;
        
        openModal('modal-site-history');
        await loadSiteHistory(currentSiteRange);
    }

    async function loadSiteHistory(range) {
        if (!currentSiteId) return;
        currentSiteRange = range;
        
        document.querySelectorAll('.site-range-btn').forEach(btn => {
            btn.className = 'px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition site-range-btn';
        });
        const activeBtn = document.getElementById(`btn-site-range-${range}`);
        if (activeBtn) {
            activeBtn.className = 'px-2.5 py-1 rounded-md bg-obsidian-purple text-white font-bold transition site-range-btn';
        }

        const overlay = document.getElementById('site-chart-loading-overlay');
        if (overlay) overlay.classList.remove('hidden');

        try {
            const res = await fetch(`/admin/sites/${currentSiteId}/history?range=${range}`);
            if (!res.ok) throw new Error('Error al cargar histórico de sede');
            const data = await res.json();
            if (!data.success) throw new Error('Datos no disponibles');

            // Stats
            document.getElementById('hist-site-stat-uptime').innerText = `${data.stats.uptime_percentage}%`;
            document.getElementById('hist-site-stat-checks').innerText = `${data.stats.total_checks} chequeos (${data.stats.up_checks} OK / ${data.stats.down_checks} caídos)`;
            document.getElementById('hist-site-stat-avg').innerText = `${data.stats.avg_latency} ms`;
            document.getElementById('hist-site-stat-max').innerText = `${data.stats.max_latency} ms`;
            document.getElementById('hist-site-stat-outages').innerText = `${data.stats.down_checks}`;
            
            const lastDown = document.getElementById('hist-site-stat-last-down');
            if (data.stats.down_checks > 0) {
                lastDown.innerText = `${data.stats.down_checks} caídas registradas`;
                lastDown.className = 'text-[9px] font-mono text-red-400 mt-0.5';
            } else {
                lastDown.innerText = '100% de operatividad';
                lastDown.className = 'text-[9px] font-mono text-emerald-400 mt-0.5';
            }

            // Incidents Table
            const incidentsBadge = document.getElementById('hist-site-incidents-badge');
            const incidentsTbody = document.getElementById('hist-site-incidents-tbody');
            
            if (data.incidents && data.incidents.length > 0) {
                incidentsBadge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-red-950 text-red-400 border border-red-500/40 glow-red';
                incidentsBadge.innerText = `${data.incidents.length} Caídas`;
                
                incidentsTbody.innerHTML = data.incidents.map(inc => `
                    <tr class="hover:bg-obsidian-panel/50 text-red-300">
                        <td class="px-3 py-1.5 font-bold">${inc.time}</td>
                        <td class="px-3 py-1.5 text-obsidian-muted">${inc.time_human}</td>
                        <td class="px-3 py-1.5">${inc.status_message}</td>
                        <td class="px-3 py-1.5 text-right font-bold text-obsidian-muted">${inc.devices_info}</td>
                    </tr>
                `).join('');
            } else {
                incidentsBadge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40';
                incidentsBadge.innerText = '0 Incidentes';
                incidentsTbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="px-3 py-3 text-center text-obsidian-muted text-[10px]">
                            No se registran caídas de enlace en el período seleccionado.
                        </td>
                    </tr>
                `;
            }

            // Render Chart.js
            renderSiteHistoryChart(data.labels, data.latencies, data.statuses, data.points);

        } catch (err) {
            console.error('Error fetching site history:', err);
        } finally {
            if (overlay) overlay.classList.add('hidden');
        }
    }

    function renderSiteHistoryChart(labels, latencies, statuses, points) {
        const ctx = document.getElementById('siteHistoryChart').getContext('2d');
        
        const isAllDown = statuses.length > 0 && statuses.every(s => s === 0);
        const lineColor = isAllDown ? '#ef4444' : '#a78bfa';

        const gradient = ctx.createLinearGradient(0, 0, 0, 200);
        if (isAllDown) {
            gradient.addColorStop(0, 'rgba(239, 68, 68, 0.35)');
            gradient.addColorStop(1, 'rgba(239, 68, 68, 0.0)');
        } else {
            gradient.addColorStop(0, 'rgba(139, 92, 246, 0.35)');
            gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)');
        }

        const pointBackgroundColors = statuses.map(s => s === 1 ? '#a78bfa' : '#ef4444');
        const pointBorderColors = statuses.map(s => s === 1 ? '#051424' : '#fee2e2');
        const pointRadiuses = statuses.map(s => s === 1 ? (statuses.length > 30 ? 0 : 3) : 6);
        const pointHoverRadiuses = statuses.map(s => s === 1 ? 5 : 8);

        const chartData = {
            labels: labels,
            datasets: [{
                label: 'Ping Enlace (ms)',
                data: latencies,
                borderColor: lineColor,
                borderWidth: 2,
                backgroundColor: gradient,
                fill: true,
                tension: 0.3,
                spanGaps: true,
                pointBackgroundColor: pointBackgroundColors,
                pointBorderColor: pointBorderColors,
                pointBorderWidth: 1.5,
                pointRadius: pointRadiuses,
                pointHoverRadius: pointHoverRadiuses,
            }]
        };

        if (siteHistoryChart) {
            siteHistoryChart.destroy();
        }

        siteHistoryChart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        backgroundColor: 'rgba(7, 23, 43, 0.95)',
                        titleColor: lineColor,
                        bodyColor: '#ffffff',
                        borderColor: isAllDown ? 'rgba(239, 68, 68, 0.5)' : 'rgba(139, 92, 246, 0.4)',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const pt = points[idx];
                                if (!pt) return `Ping: ${context.parsed.y} ms`;
                                if (pt.is_up) {
                                    return `⚡ Ping Enlace: ${pt.latency_ms} ms (${pt.devices_online}/${pt.devices_total} equipos ON)`;
                                } else {
                                    return `❌ ENLACE CAÍDO (${pt.status_message})`;
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(28, 46, 71, 0.5)',
                        },
                        ticks: {
                            color: '#8295b0',
                            font: { family: 'JetBrains Mono', size: 9.5 },
                            maxTicksLimit: 12,
                        }
                    },
                    y: {
                        beginAtZero: true,
                        suggestedMax: 10,
                        grid: {
                            color: 'rgba(28, 46, 71, 0.5)',
                        },
                        ticks: {
                            color: '#8295b0',
                            font: { family: 'JetBrains Mono', size: 9.5 },
                            callback: function(value) {
                                return value + ' ms';
                            }
                        }
                    }
                }
            }
        });
    }

    // --- MODAL DE HISTÓRICO & TELEMETRÍA DE DISPOSITIVO ---
    function openDeviceHistoryModal(id, name, ip, slot) {
        currentDeviceId = id;
        document.getElementById('hist-device-name').innerText = name;
        document.getElementById('hist-device-slot').innerText = 'ID #' + slot;
        document.getElementById('hist-device-ip').innerText = 'Host IP: ' + ip;

        openModal('modal-device-history');
        loadDeviceHistory('24h');
    }

    function closeDeviceHistoryModal() {
        closeModal('modal-device-history');
        if (deviceChart) {
            deviceChart.destroy();
            deviceChart = null;
        }
    }

    async function loadDeviceHistory(range) {
        if (!currentDeviceId) return;
        currentDeviceRange = range;

        document.querySelectorAll('.hist-device-range-btn').forEach(btn => {
            if (btn.getAttribute('data-range') === range) {
                btn.className = 'hist-device-range-btn px-2.5 py-1 rounded transition font-semibold bg-obsidian-cyan text-black';
            } else {
                btn.className = 'hist-device-range-btn px-2.5 py-1 rounded transition font-semibold text-obsidian-muted hover:text-white';
            }
        });

        const overlay = document.getElementById('device-chart-loading-overlay');
        if (overlay) overlay.classList.remove('hidden');

        try {
            const res = await fetch(`/admin/devices/${currentDeviceId}/history?range=${range}`);
            const data = await res.json();

            if (!data.success) {
                console.error('Error cargando historial de dispositivo:', data);
                return;
            }

            document.getElementById('hist-device-stat-uptime').innerText = (data.stats.uptime_percentage ?? 100) + '%';
            document.getElementById('hist-device-stat-avg').innerText = (data.stats.avg_latency ?? 0) + ' ms';

            const tbody = document.getElementById('hist-device-incidents-tbody');
            if (data.incidents && data.incidents.length > 0) {
                tbody.innerHTML = data.incidents.map(inc => `
                    <tr class="hover:bg-red-950/20 text-red-300">
                        <td class="px-3 py-2 font-bold">${inc.time}</td>
                        <td class="px-3 py-2 text-obsidian-muted">${inc.time_human}</td>
                        <td class="px-3 py-2">${inc.status_message}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="3" class="px-3 py-3 text-center text-obsidian-muted">
                            Sin incidentes registrados en este período.
                        </td>
                    </tr>
                `;
            }

            renderDeviceChart(data.labels, data.latencies, data.statuses);

        } catch (err) {
            console.error('Error de red cargando telemetría de dispositivo:', err);
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
                        ticks: { color: '#64748b', font: { family: 'JetBrains Mono', size: 10 } }
                    },
                    y: {
                        grid: { color: 'rgba(28, 46, 71, 0.4)' },
                        ticks: { color: '#64748b', font: { family: 'JetBrains Mono', size: 10 } },
                        beginAtZero: true
                    }
                }
            }
        });
    }
</script>
@endsection
