@extends('layouts.admin')

@section('page_title', 'Configuración de Sedes y Enlaces')

@section('admin_content')
<div class="space-y-6">
    <!-- CABECERA -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-white">Sedes Regionales y Equipos de Red Monitoreados</h2>
            <p class="text-xs font-mono text-obsidian-muted">Datos del Historial de conexión</p>
        </div>
        <div class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan text-xs font-mono">
            Datos de monitoreo.conf
        </div>
    </div>

    <!-- LISTADO DE SEDES -->
    <div class="space-y-4">
        @foreach($sites as $site)
            <div class="glass-card rounded-xl p-5 space-y-4 border border-obsidian-border/80">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-obsidian-border/60 pb-3">
                    <div class="flex items-center space-x-3">
                        <span class="text-lg font-bold text-obsidian-purple font-mono">[ {{ $site->letter }} ]</span>
                        <div>
                            <h3 class="text-base font-bold text-white">{{ $site->name }}</h3>
                            <p class="text-xs font-mono text-obsidian-muted">IP Gateway: {{ $site->ip ?: '0.0.0.0' }}</p>
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
                        <button onclick="openSiteHistoryModal({{ $site->id }}, '{{ addslashes($site->name) }}', '{{ $site->letter }}', '{{ $site->ip }}')" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-purple/40 text-obsidian-purple hover:bg-obsidian-purple hover:text-white font-mono text-xs transition flex items-center gap-1 shadow-sm" title="Ver Gráficos y Métricas Temporales">
                            <span class="material-symbols-outlined text-sm">show_chart</span>
                            <span>Histórico</span>
                        </button>
                        @if(auth()->user()->isAdmin())
                            <button onclick="openEditSiteModal({{ json_encode($site) }})" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-xs transition flex items-center gap-1" title="Configurar Parámetros">
                                <span class="material-symbols-outlined text-sm">tune</span>
                                <span>Modificar</span>
                            </button>
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

                <!-- EQUIPOS SECUNDARIOS ASOCIADOS (1..8) -->
                <div>
                    <span class="text-[11px] font-mono uppercase text-obsidian-muted block mb-2">Equipos de Red Asociados ({{ $site->devices->where('is_active', true)->count() }} Activos de 8)</span>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach($site->devices as $dev)
                            <div class="p-2.5 rounded-lg border text-xs font-mono {{ $dev->is_active ? 'bg-obsidian-panel/80 border-obsidian-border' : 'bg-obsidian-panel/20 border-obsidian-border/30 opacity-50' }}">
                                <div class="flex items-center justify-between text-[10px] text-obsidian-muted">
                                    <span>#{{ $dev->device_number }}</span>
                                    <span class="{{ $dev->is_active ? 'text-emerald-400 font-bold' : 'text-obsidian-muted' }}">
                                        {{ $dev->is_active ? 'ACTIVO' : 'OFF' }}
                                    </span>
                                </div>
                                <div class="text-white font-sans font-semibold truncate text-[11px] mt-1">{{ $dev->name }}</div>
                                <div class="text-obsidian-muted text-[10px] truncate">{{ $dev->ip }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@if(auth()->user()->isAdmin())
<!-- MODAL EDITAR SEDE & EQUIPOS (Solo Administrador) -->
<div id="modal-edit-site" onclick="if(event.target === this) closeModal('modal-edit-site')" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-2xl w-full rounded-2xl p-6 border border-obsidian-border shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto custom-scroll">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <h3 class="text-sm font-bold text-white uppercase font-mono">Configurar Sede & Equipos de Red</h3>
            <button type="button" onclick="closeModal('modal-edit-site')" class="text-obsidian-muted hover:text-white text-xl p-1 rounded hover:bg-obsidian-panel leading-none">&times;</button>
        </div>
        <form id="form-edit-site" method="POST" class="space-y-5 font-mono text-xs">
            @csrf
            @method('PUT')
            
            <!-- DATOS BÁSICOS DE LA SEDE -->
            <div class="space-y-3 bg-obsidian-panel/60 p-4 rounded-xl border border-obsidian-border/60">
                <span class="text-[11px] font-bold text-obsidian-cyan uppercase tracking-wider block border-b border-obsidian-border/40 pb-1">1. Parámetros Principales de la Sede</span>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px]">Letra Clave:</label>
                        <input type="text" id="edit-site-letter" name="letter" required maxlength="5" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white font-bold"/>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-obsidian-muted mb-1 text-[11px]">Nombre de la Sede:</label>
                        <input type="text" id="edit-site-name" name="name" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px]">IP Gateway / Enlace:</label>
                        <input type="text" id="edit-site-ip" name="ip" placeholder="ej. 192.168.1.1" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
                    </div>
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px]">Dirección Física:</label>
                        <input type="text" id="edit-site-address" name="address" placeholder="Ubicación o Edificio" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px]">Teléfono Principal:</label>
                        <input type="text" id="edit-site-phone1" name="phone_1" placeholder="ej. 0241-1234567" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
                    </div>
                    <div>
                        <label class="block text-obsidian-muted mb-1 text-[11px]">Teléfono Secundario:</label>
                        <input type="text" id="edit-site-phone2" name="phone_2" placeholder="ej. 0414-7654321" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
                    </div>
                </div>
            </div>

            <!-- EQUIPOS ASOCIADOS (1..8) -->
            <div class="space-y-3 bg-obsidian-panel/60 p-4 rounded-xl border border-obsidian-border/60">
                <div class="flex items-center justify-between border-b border-obsidian-border/40 pb-1">
                    <span class="text-[11px] font-bold text-obsidian-cyan uppercase tracking-wider block">2. Equipos Secundarios de Red (1..8)</span>
                    <span class="text-[10px] text-obsidian-muted">Dejar IP vacía para omitir monitoreo</span>
                </div>
                
                <div class="space-y-2.5 max-h-60 overflow-y-auto custom-scroll pr-1">
                    @for($i = 1; $i <= 8; $i++)
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center p-2 rounded-lg bg-obsidian-panel/80 border border-obsidian-border/40 text-[11px]">
                            <div class="sm:col-span-1 text-center font-bold text-obsidian-cyan">
                                #{{ $i }}
                                <input type="hidden" name="devices[{{ $i }}][number]" value="{{ $i }}"/>
                            </div>
                            <div class="sm:col-span-4">
                                <input type="text" id="edit-site-dev-name-{{ $i }}" name="devices[{{ $i }}][name]" placeholder="Nombre Equipo {{ $i }}" class="w-full bg-obsidian-panel border border-obsidian-border rounded p-1.5 text-white text-[11px]"/>
                            </div>
                            <div class="sm:col-span-4">
                                <input type="text" id="edit-site-dev-ip-{{ $i }}" name="devices[{{ $i }}][ip]" placeholder="IP (ej. 192.168.1.{{ 10 + $i }})" class="w-full bg-obsidian-panel border border-obsidian-border rounded p-1.5 text-white text-[11px]"/>
                            </div>
                            <div class="sm:col-span-3 flex items-center justify-end gap-1.5">
                                <input type="checkbox" id="edit-site-dev-active-{{ $i }}" name="devices[{{ $i }}][is_active]" value="1" class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan"/>
                                <label for="edit-site-dev-active-{{ $i }}" class="text-[10px] text-obsidian-muted cursor-pointer select-none">Habilitar</label>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-obsidian-border">
                <button type="button" onclick="closeModal('modal-edit-site')" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold">Guardar Cambios de Sede</button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- MODAL HISTÓRICO Y GRÁFICO DE LÍNEA TEMPORAL PARA SEDES -->
<div id="modal-site-history" onclick="if(event.target === this) closeModal('modal-site-history')" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden items-center justify-center p-3 sm:p-6">
    <div class="glass-panel max-w-4xl w-full rounded-2xl border border-obsidian-border/90 shadow-2xl space-y-4 max-h-[95vh] overflow-y-auto custom-scroll p-5 sm:p-6 bg-[#07172b]/95">
        <!-- CABECERA DEL HISTÓRICO -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-obsidian-border/80 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-obsidian-purple/15 border border-obsidian-purple/40 flex items-center justify-center text-obsidian-purple">
                    <span class="material-symbols-outlined text-xl">domain</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span id="hist-site-letter" class="text-xs font-mono font-bold text-obsidian-purple bg-obsidian-panel px-2 py-0.5 rounded border border-obsidian-border">[ A ]</span>
                        <h3 id="hist-site-name" class="text-base font-bold text-white font-sans truncate max-w-md">Sede</h3>
                        <span id="hist-site-badge" class="text-[9px] font-mono font-bold uppercase bg-obsidian-panel text-obsidian-cyan px-1.5 py-0.5 rounded border border-obsidian-border">ENLACE SEDE</span>
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

<script>
    let currentSiteId = null;
    let currentSiteRange = '24h';
    let siteHistoryChart = null;

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
        }
    });

    function openEditSiteModal(site) {
        document.getElementById('form-edit-site').action = `/admin/sites/${site.id}`;
        document.getElementById('edit_st_letter').value = site.letter;
        document.getElementById('edit_st_name').value = site.name;
        document.getElementById('edit_st_ip').value = site.ip || '';
        document.getElementById('edit_st_phone_1').value = site.phone_1 || '';
        document.getElementById('edit_st_phone_2').value = site.phone_2 || '';
        document.getElementById('edit_st_address').value = site.address || '';
        document.getElementById('edit_st_active').checked = site.is_active;

        const devContainer = document.getElementById('devices-edit-container');
        devContainer.innerHTML = '';

        if (site.devices && site.devices.length > 0) {
            site.devices.forEach(dev => {
                const row = document.createElement('div');
                row.className = 'grid grid-cols-12 gap-2 items-center bg-obsidian-panel p-2 rounded-lg border border-obsidian-border';
                row.innerHTML = `
                    <div class="col-span-1 text-center font-bold text-obsidian-muted">#${dev.device_number}</div>
                    <div class="col-span-6">
                        <input type="text" name="devices[${dev.id}][name]" value="${dev.name || ''}" placeholder="Nombre del equipo" class="w-full bg-obsidian-bg border border-obsidian-border rounded p-1.5 text-white"/>
                    </div>
                    <div class="col-span-4">
                        <input type="text" name="devices[${dev.id}][ip]" value="${dev.ip || ''}" placeholder="Dirección IP" class="w-full bg-obsidian-bg border border-obsidian-border rounded p-1.5 text-white"/>
                    </div>
                    <div class="col-span-1 text-center">
                        <input type="checkbox" name="devices[${dev.id}][is_active]" value="1" ${dev.is_active ? 'checked' : ''} class="rounded bg-obsidian-bg border-obsidian-border text-obsidian-cyan" title="Activar equipo"/>
                    </div>
                `;
                devContainer.appendChild(row);
            });
        }

        openModal('modal-edit-site');
    }

    async function openSiteHistoryModal(siteId, name, letter, ip) {
        currentSiteId = siteId;
        document.getElementById('hist-site-name').innerText = name;
        document.getElementById('hist-site-letter').innerText = `[ ${letter} ]`;
        document.getElementById('hist-site-ip').innerText = `IP Gateway: ${ip || '0.0.0.0'}`;
        
        openModal('modal-site-history');
        await loadSiteHistory(currentSiteRange);
    }

    async function loadSiteHistory(range) {
        if (!currentSiteId) return;
        currentSiteRange = range;
        
        // Update active tab buttons
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

        // Gradient fill under line (Purple glow / Red glow if down)
        const gradient = ctx.createLinearGradient(0, 0, 0, 200);
        if (isAllDown) {
            gradient.addColorStop(0, 'rgba(239, 68, 68, 0.35)');
            gradient.addColorStop(1, 'rgba(239, 68, 68, 0.0)');
        } else {
            gradient.addColorStop(0, 'rgba(139, 92, 246, 0.35)');
            gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)');
        }

        // Point colors & radius
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
</script>
@endsection
