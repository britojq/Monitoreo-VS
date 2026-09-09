@extends('layouts.admin')

@section('page_title', 'Configuración de Servicios')

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
                <span class="text-xs font-mono text-obsidian-muted">Servicios Monitoreados</span>
            </div>
            <h2 class="text-lg font-bold text-white">Servicios Corporativos Monitoreados</h2>
            <p class="text-xs font-mono text-obsidian-muted">Datos del Historial de conexión</p>
        </div>
        <div class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan text-xs font-mono">
            Datos de monitoreo.conf
        </div>
    </div>

    <!-- TABLA DE SERVICIOS -->
    <div class="glass-card rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-obsidian-panel border-b border-obsidian-border text-obsidian-muted uppercase text-[11px]">
                    <tr>
                        <th class="px-5 py-4">ID / Letra</th>
                        <th class="px-5 py-4">Nombre del Servicio</th>
                        <th class="px-5 py-4">Tipo</th>
                        <th class="px-5 py-4">Host IP / URL</th>
                        <th class="px-5 py-4">Puerto</th>
                        <th class="px-5 py-4">Estado</th>
                        <th class="px-5 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/60">
                    @foreach($services as $s)
                        <tr class="hover:bg-obsidian-panel/40 transition">
                            <td class="px-5 py-4 font-bold text-obsidian-cyan">
                                [ {{ $s->letter }} ]
                            </td>
                            <td class="px-5 py-4 font-sans font-semibold text-white">
                                {{ $s->name }}
                            </td>
                            <td class="px-5 py-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-obsidian-panel border border-obsidian-border text-obsidian-cyan">
                                    {{ $s->type }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-obsidian-muted truncate max-w-xs">
                                {{ $s->host_ip ?: ($s->web_url ?: '--') }}
                            </td>
                            <td class="px-5 py-4 text-obsidian-muted">
                                {{ $s->port ?: '--' }}
                            </td>
                            <td class="px-5 py-4">
                                @if(auth()->user()->isAdmin())
                                    <form action="{{ route('admin.services.toggle', $s->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-bold transition {{ $s->is_active ? 'bg-emerald-950/70 text-emerald-400 border border-emerald-500/40' : 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border' }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $s->is_active ? 'bg-emerald-400' : 'bg-obsidian-muted' }}"></span>
                                            {{ $s->is_active ? 'Activo' : 'Desactivado' }}
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-bold {{ $s->is_active ? 'bg-emerald-950/70 text-emerald-400 border border-emerald-500/40' : 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $s->is_active ? 'bg-emerald-400' : 'bg-obsidian-muted' }}"></span>
                                        {{ $s->is_active ? 'Activo' : 'Desactivado' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right space-x-1.5">
                                <button onclick="openServiceHistoryModal({{ $s->id }}, '{{ addslashes($s->name) }}', '{{ $s->letter }}', '{{ $s->type }}')" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-purple/40 text-obsidian-purple hover:bg-obsidian-purple hover:text-white transition flex items-center gap-1 inline-flex" title="Ver Gráfico e Histórico">
                                    <span class="material-symbols-outlined text-sm">show_chart</span>
                                    <span>Histórico</span>
                                </button>
                                @if(auth()->user()->isAdmin())
                                    @if($isClusterSlave)
                                        <span class="px-2.5 py-1.5 rounded-lg bg-gray-900 border border-gray-800 text-gray-500 text-[10px] font-mono inline-flex items-center gap-1" title="Modificaciones restringidas al Servidor Master">
                                            <span class="material-symbols-outlined text-xs">lock</span>
                                            Solo Lectura
                                        </span>
                                    @else
                                        <button onclick="openEditServiceModal({{ json_encode($s) }})" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition flex items-center gap-1 inline-flex" title="Editar Parámetros">
                                            <span class="material-symbols-outlined text-sm">edit</span>
                                            <span>Modificar</span>
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if(auth()->user()->isAdmin())
<!-- MODAL EDITAR SERVICIO (Solo Administrador) -->
<div id="modal-edit-service" onclick="if(event.target === this) closeModal('modal-edit-service')" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-lg w-full rounded-2xl p-6 border border-obsidian-border shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <h3 class="text-sm font-bold text-white uppercase font-mono">Editar Parámetros de Servicio</h3>
            <button type="button" onclick="closeModal('modal-edit-service')" class="text-obsidian-muted hover:text-white text-xl p-1 rounded hover:bg-obsidian-panel leading-none">&times;</button>
        </div>
        <form id="form-edit-service" method="POST" class="space-y-4 font-mono text-xs">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">Letra / Identificador:</label>
                    <input type="text" id="edit-service-letter" name="letter" required maxlength="5" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white font-bold"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">Tipo de Servicio:</label>
                    <select id="edit-service-type" name="type" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white">
                        <option value="WEB">WEB (HTTP/HTTPS)</option>
                        <option value="PING">PING (ICMP)</option>
                        <option value="PORT">PORT (TCP)</option>
                        <option value="DNS">DNS</option>
                        <option value="SMTP">SMTP</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px]">Nombre Descriptivo:</label>
                <input type="text" id="edit-service-name" name="name" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">Host / IP Destino:</label>
                    <input type="text" id="edit-service-host" name="host_ip" placeholder="ej. 10.0.0.1" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">Puerto TCP (opcional):</label>
                    <input type="number" id="edit-service-port" name="port" placeholder="ej. 80, 443" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
                </div>
            </div>

            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px]">URL Completa (para servicios WEB):</label>
                <input type="url" id="edit-service-url" name="web_url" placeholder="https://ejemplo.com/salud" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
            </div>

            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px]">Credenciales / Token (Opcional):</label>
                <input type="text" id="edit-service-credentials" name="credentials" placeholder="user:pass o token" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-obsidian-border">
                <button type="button" onclick="closeModal('modal-edit-service')" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold">Actualizar Servicio</button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- MODAL HISTÓRICO Y GRÁFICO DE LÍNEA TEMPORAL -->
<div id="modal-service-history" onclick="if(event.target === this) closeModal('modal-service-history')" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden items-center justify-center p-3 sm:p-6">
    <div class="glass-panel max-w-4xl w-full rounded-2xl border border-obsidian-border/90 shadow-2xl space-y-4 max-h-[95vh] overflow-y-auto custom-scroll p-5 sm:p-6 bg-[#07172b]/95">
        <!-- CABECERA DEL HISTÓRICO -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-obsidian-border/80 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-obsidian-cyan/10 border border-obsidian-cyan/40 flex items-center justify-center text-obsidian-cyan">
                    <span class="material-symbols-outlined text-xl">show_chart</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span id="hist-service-letter" class="text-xs font-mono font-bold text-obsidian-cyan bg-obsidian-panel px-2 py-0.5 rounded border border-obsidian-border">[ A ]</span>
                        <h3 id="hist-service-name" class="text-base font-bold text-white font-sans truncate max-w-md">Servicio</h3>
                        <span id="hist-service-type" class="text-[9px] font-mono font-bold uppercase bg-obsidian-panel text-obsidian-muted px-1.5 py-0.5 rounded border border-obsidian-border">WEB</span>
                    </div>
                    <p id="hist-service-target" class="text-xs font-mono text-obsidian-muted mt-0.5 truncate">Destino: 10.20.0.1</p>
                </div>
            </div>

            <!-- CONTROLES DE RANGO TEMPORAL -->
            <div class="flex items-center gap-2 self-end sm:self-auto">
                <div class="inline-flex rounded-lg p-1 bg-obsidian-panel border border-obsidian-border text-xs font-mono">
                    <button type="button" onclick="loadServiceHistory('6h')" id="btn-range-6h" class="px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition range-btn">6H</button>
                    <button type="button" onclick="loadServiceHistory('24h')" id="btn-range-24h" class="px-2.5 py-1 rounded-md bg-obsidian-cyan text-black font-bold transition range-btn">24H</button>
                    <button type="button" onclick="loadServiceHistory('7d')" id="btn-range-7d" class="px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition range-btn">7D</button>
                    <button type="button" onclick="loadServiceHistory('30d')" id="btn-range-30d" class="px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition range-btn">30D</button>
                </div>
                <button type="button" onclick="closeModal('modal-service-history')" class="p-1.5 rounded-lg text-obsidian-muted hover:text-white hover:bg-obsidian-panel text-xl leading-none transition" title="Cerrar">&times;</button>
            </div>
        </div>

        <!-- 4 TARJETAS DE MÉTRICAS KPI -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Disponibilidad</span>
                    <span class="material-symbols-outlined text-xs text-emerald-400">verified</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-stat-uptime" class="text-lg font-bold font-mono text-emerald-400">--%</span>
                </div>
                <p id="hist-stat-checks" class="text-[9px] font-mono text-obsidian-muted mt-0.5">-- chequeos totales</p>
            </div>

            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Latencia Media</span>
                    <span class="material-symbols-outlined text-xs text-obsidian-cyan">speed</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-stat-avg" class="text-lg font-bold font-mono text-white">-- ms</span>
                </div>
                <p class="text-[9px] font-mono text-obsidian-muted mt-0.5">Tiempo de respuesta</p>
            </div>

            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Latencia Máxima</span>
                    <span class="material-symbols-outlined text-xs text-amber-400">trending_up</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-stat-max" class="text-lg font-bold font-mono text-amber-400">-- ms</span>
                </div>
                <p class="text-[9px] font-mono text-obsidian-muted mt-0.5">Pico de latencia</p>
            </div>

            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Caídas / Fallas</span>
                    <span class="material-symbols-outlined text-xs text-red-400">warning</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-stat-outages" class="text-lg font-bold font-mono text-white">0</span>
                </div>
                <p id="hist-stat-last-down" class="text-[9px] font-mono text-obsidian-muted mt-0.5">Sin incidentes</p>
            </div>
        </div>

        <!-- CONTENEDOR DEL GRÁFICO DE LÍNEA -->
        <div class="glass-card rounded-xl p-4 border border-obsidian-border space-y-3 bg-obsidian-panel/50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-obsidian-cyan"></span>
                    <h4 class="text-xs font-bold font-mono uppercase text-white tracking-wider">Histórico Temporal de Latencia & Disponibilidad</h4>
                </div>
                <div class="flex items-center gap-3 text-[10px] font-mono text-obsidian-muted">
                    <span class="flex items-center gap-1"><span class="w-2 h-0.5 bg-obsidian-cyan"></span> Latencia (ms)</span>
                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Caída / Timeout</span>
                </div>
            </div>
            
            <div class="relative w-full h-[220px]">
                <canvas id="serviceHistoryChart"></canvas>
                <div id="chart-loading-overlay" class="absolute inset-0 flex items-center justify-center bg-obsidian-bg/80 backdrop-blur-xs rounded-lg hidden">
                    <div class="flex items-center gap-2 text-obsidian-cyan font-mono text-xs">
                        <span class="material-symbols-outlined animate-spin text-base">sync</span>
                        <span>Cargando telemetría...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- REGISTRO RECIENTE DE INCIDENTES / CAÍDAS -->
        <div class="glass-card rounded-xl p-4 border border-obsidian-border space-y-2 bg-obsidian-panel/30">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold font-mono uppercase text-white tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-amber-400 text-sm">history_toggle_off</span>
                    Registro de Incidentes y Caídas Detectadas
                </h4>
                <span id="hist-incidents-badge" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40">
                    0 Incidentes
                </span>
            </div>

            <div class="overflow-x-auto max-h-36 overflow-y-auto custom-scroll">
                <table class="w-full text-left text-[11px] font-mono">
                    <thead class="bg-obsidian-panel/80 text-obsidian-muted uppercase text-[9.5px]">
                        <tr>
                            <th class="px-3 py-1.5">Fecha y Hora</th>
                            <th class="px-3 py-1.5">Tiempo Relativo</th>
                            <th class="px-3 py-1.5">Estado / Causa</th>
                            <th class="px-3 py-1.5 text-right">Código HTTP</th>
                        </tr>
                    </thead>
                    <tbody id="hist-incidents-tbody" class="divide-y divide-obsidian-border/40">
                        <tr>
                            <td colspan="4" class="px-3 py-3 text-center text-obsidian-muted text-[10px]">
                                No se registran caídas en el período seleccionado.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let currentServiceId = null;
    let currentRange = '24h';
    let historyChart = null;

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
            closeModal('modal-service-history');
            closeModal('modal-edit-service');
        }
    });

    function openEditServiceModal(s) {
        document.getElementById('form-edit-service').action = `/admin/services/${s.id}`;
        document.getElementById('edit_s_letter').value = s.letter;
        document.getElementById('edit_s_type').value = s.type;
        document.getElementById('edit_s_name').value = s.name;
        document.getElementById('edit_s_ip').value = s.host_ip || '';
        document.getElementById('edit_s_port').value = s.port || '';
        document.getElementById('edit_s_web').value = s.web_url || '';
        document.getElementById('edit_s_cred').value = s.credentials || '';
        document.getElementById('edit_s_active').checked = s.is_active;
        openModal('modal-edit-service');
    }

    async function openServiceHistoryModal(serviceId, name, letter, type) {
        currentServiceId = serviceId;
        document.getElementById('hist-service-name').innerText = name;
        document.getElementById('hist-service-letter').innerText = `[ ${letter} ]`;
        document.getElementById('hist-service-type').innerText = type;
        
        openModal('modal-service-history');
        await loadServiceHistory(currentRange);
    }

    async function loadServiceHistory(range) {
        if (!currentServiceId) return;
        currentRange = range;
        
        // Update active tab buttons
        document.querySelectorAll('.range-btn').forEach(btn => {
            btn.className = 'px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition range-btn';
        });
        const activeBtn = document.getElementById(`btn-range-${range}`);
        if (activeBtn) {
            activeBtn.className = 'px-2.5 py-1 rounded-md bg-obsidian-cyan text-black font-bold transition range-btn';
        }

        const overlay = document.getElementById('chart-loading-overlay');
        if (overlay) overlay.classList.remove('hidden');

        try {
            const res = await fetch(`/admin/services/${currentServiceId}/history?range=${range}`);
            if (!res.ok) throw new Error('Error al cargar histórico');
            const data = await res.json();
            if (!data.success) throw new Error('Datos no disponibles');

            // Target
            document.getElementById('hist-service-target').innerText = `Destino: ${data.service.target}`;
            
            // Stats
            document.getElementById('hist-stat-uptime').innerText = `${data.stats.uptime_percentage}%`;
            document.getElementById('hist-stat-checks').innerText = `${data.stats.total_checks} chequeos (${data.stats.up_checks} OK / ${data.stats.down_checks} caídos)`;
            document.getElementById('hist-stat-avg').innerText = `${data.stats.avg_latency} ms`;
            document.getElementById('hist-stat-max').innerText = `${data.stats.max_latency} ms`;
            document.getElementById('hist-stat-outages').innerText = `${data.stats.down_checks}`;
            
            const lastDown = document.getElementById('hist-stat-last-down');
            if (data.stats.down_checks > 0) {
                lastDown.innerText = `${data.stats.down_checks} eventos de indisponibilidad`;
                lastDown.className = 'text-[9px] font-mono text-red-400 mt-0.5';
            } else {
                lastDown.innerText = '100% de disponibilidad';
                lastDown.className = 'text-[9px] font-mono text-emerald-400 mt-0.5';
            }

            // Incidents Table
            const incidentsBadge = document.getElementById('hist-incidents-badge');
            const incidentsTbody = document.getElementById('hist-incidents-tbody');
            
            if (data.incidents && data.incidents.length > 0) {
                incidentsBadge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-red-950 text-red-400 border border-red-500/40 glow-red';
                incidentsBadge.innerText = `${data.incidents.length} Caídas`;
                
                incidentsTbody.innerHTML = data.incidents.map(inc => `
                    <tr class="hover:bg-obsidian-panel/50 text-red-300">
                        <td class="px-3 py-1.5 font-bold">${inc.time}</td>
                        <td class="px-3 py-1.5 text-obsidian-muted">${inc.time_human}</td>
                        <td class="px-3 py-1.5">${inc.status_message}</td>
                        <td class="px-3 py-1.5 text-right font-bold">${inc.http_code}</td>
                    </tr>
                `).join('');
            } else {
                incidentsBadge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40';
                incidentsBadge.innerText = '0 Incidentes';
                incidentsTbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="px-3 py-3 text-center text-obsidian-muted text-[10px]">
                            No se registran caídas en el período seleccionado.
                        </td>
                    </tr>
                `;
            }

            // Render Chart.js
            renderHistoryChart(data.labels, data.latencies, data.statuses, data.points);

        } catch (err) {
            console.error('Error fetching history:', err);
        } finally {
            if (overlay) overlay.classList.add('hidden');
        }
    }

    function renderHistoryChart(labels, latencies, statuses, points) {
        const ctx = document.getElementById('serviceHistoryChart').getContext('2d');
        
        const isAllDown = statuses.length > 0 && statuses.every(s => s === 0);
        const lineColor = isAllDown ? '#ef4444' : '#22d3ee';

        // Gradient fill under line
        const gradient = ctx.createLinearGradient(0, 0, 0, 200);
        if (isAllDown) {
            gradient.addColorStop(0, 'rgba(239, 68, 68, 0.35)');
            gradient.addColorStop(1, 'rgba(239, 68, 68, 0.0)');
        } else {
            gradient.addColorStop(0, 'rgba(34, 211, 238, 0.35)');
            gradient.addColorStop(1, 'rgba(34, 211, 238, 0.0)');
        }

        // Point colors & radius
        const pointBackgroundColors = statuses.map(s => s === 1 ? '#22d3ee' : '#ef4444');
        const pointBorderColors = statuses.map(s => s === 1 ? '#051424' : '#fee2e2');
        const pointRadiuses = statuses.map(s => s === 1 ? (statuses.length > 30 ? 0 : 3) : 6);
        const pointHoverRadiuses = statuses.map(s => s === 1 ? 5 : 8);

        const chartData = {
            labels: labels,
            datasets: [{
                label: 'Latencia (ms)',
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

        if (historyChart) {
            historyChart.destroy();
        }

        historyChart = new Chart(ctx, {
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
                        borderColor: isAllDown ? 'rgba(239, 68, 68, 0.5)' : 'rgba(34, 211, 238, 0.4)',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const pt = points[idx];
                                if (!pt) return `Latencia: ${context.parsed.y} ms`;
                                if (pt.is_up) {
                                    return `⚡ Latencia: ${pt.latency_ms} ms (${pt.status_message})`;
                                } else {
                                    return `❌ SERVICIO CAÍDO / APAGADO (${pt.status_message})`;
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
