@extends('layouts.admin')

@section('page_title', 'Configuración de Proxies')

@section('admin_content')
<div class="space-y-6">
    <!-- CABECERA -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-white">Proxies Corporativos & Salidas a Internet</h2>
            <p class="text-xs font-mono text-obsidian-muted">Datos del Historial de conexión</p>
        </div>
        <div class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan text-xs font-mono">
            Datos de bot.conf
        </div>
    </div>

    <!-- TABLA DE PROXIES -->
    <div class="glass-card rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-obsidian-panel border-b border-obsidian-border text-obsidian-muted uppercase text-[11px]">
                    <tr>
                        <th class="px-6 py-4">Letra / ID</th>
                        <th class="px-6 py-4">Nombre del Proxy</th>
                        <th class="px-6 py-4">IP y Puerto</th>
                        <th class="px-6 py-4">Credenciales</th>
                        <th class="px-6 py-4">Estado</th>
                        <th class="px-6 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/60">
                    @foreach($proxies as $p)
                        <tr class="hover:bg-obsidian-panel/40 transition">
                            <td class="px-6 py-4 font-bold text-obsidian-cyan">
                                [ {{ $p->letter }} ]
                            </td>
                            <td class="px-6 py-4 font-sans font-semibold text-white">
                                {{ $p->name }}
                            </td>
                            <td class="px-6 py-4 text-obsidian-muted font-mono">
                                {{ $p->ip_port ?: '--' }}
                            </td>
                            <td class="px-6 py-4 text-obsidian-muted">
                                {{ $p->auth_userpass ? '••••••••:••••' : 'Sin autenticación' }}
                            </td>
                            <td class="px-6 py-4">
                                <form action="{{ route('admin.proxies.toggle', $p->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-bold transition {{ $p->is_active ? 'bg-emerald-950/70 text-emerald-400 border border-emerald-500/40' : 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $p->is_active ? 'bg-emerald-400' : 'bg-obsidian-muted' }}"></span>
                                        {{ $p->is_active ? 'Activo' : 'Desactivado' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-right space-x-1.5">
                                <button onclick="openProxyHistoryModal({{ $p->id }}, '{{ addslashes($p->name) }}', '{{ $p->letter }}', '{{ $p->ip_port }}')" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-amber-500/40 text-amber-400 hover:bg-amber-500 hover:text-black font-mono text-xs transition flex items-center gap-1 inline-flex shadow-sm" title="Ver Gráficos y Métricas Temporales">
                                    <span class="material-symbols-outlined text-sm">show_chart</span>
                                    <span>Histórico</span>
                                </button>
                                <button onclick="openEditProxyModal({{ json_encode($p) }})" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-xs transition flex items-center gap-1 inline-flex" title="Editar Parámetros">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                    <span>Modificar</span>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL EDITAR PROXY -->
<div id="modal-edit-proxy" onclick="if(event.target === this) closeModal('modal-edit-proxy')" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-md w-full rounded-2xl p-6 border border-obsidian-border shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto custom-scroll">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <h3 class="text-sm font-bold text-white uppercase font-mono">Editar Proxy</h3>
            <button type="button" onclick="closeModal('modal-edit-proxy')" class="text-obsidian-muted hover:text-white text-xl p-1 rounded hover:bg-obsidian-panel leading-none">&times;</button>
        </div>
        <form id="form-edit-proxy" method="POST" class="space-y-4 font-mono text-xs">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-obsidian-muted mb-1">Letra Clave</label>
                    <input type="text" name="letter" id="edit_p_letter" required maxlength="5" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white uppercase"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1">Nombre</label>
                    <input type="text" name="name" id="edit_p_name" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
                </div>
            </div>
            <div>
                <label class="block text-obsidian-muted mb-1">IP y Puerto (IP:PUERTO)</label>
                <input type="text" name="ip_port" id="edit_p_ip_port" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
            </div>
            <div>
                <label class="block text-obsidian-muted mb-1">Credenciales (usuario:clave)</label>
                <input type="text" name="auth_userpass" id="edit_p_auth" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" id="edit_p_active" class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan"/>
                <label for="edit_p_active" class="text-white">Proxy Habilitado</label>
            </div>
            <div class="pt-3 border-t border-obsidian-border flex justify-end gap-2">
                <button type="button" onclick="closeModal('modal-edit-proxy')" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold">Actualizar Proxy</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL HISTÓRICO Y GRÁFICO DE LÍNEA TEMPORAL PARA PROXIES -->
<div id="modal-proxy-history" onclick="if(event.target === this) closeModal('modal-proxy-history')" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden items-center justify-center p-3 sm:p-6">
    <div class="glass-panel max-w-4xl w-full rounded-2xl border border-obsidian-border/90 shadow-2xl space-y-4 max-h-[95vh] overflow-y-auto custom-scroll p-5 sm:p-6 bg-[#07172b]/95">
        <!-- CABECERA DEL HISTÓRICO -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-obsidian-border/80 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/15 border border-amber-500/40 flex items-center justify-center text-amber-400">
                    <span class="material-symbols-outlined text-xl">public</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span id="hist-proxy-letter" class="text-xs font-mono font-bold text-amber-400 bg-obsidian-panel px-2 py-0.5 rounded border border-obsidian-border">[ A ]</span>
                        <h3 id="hist-proxy-name" class="text-base font-bold text-white font-sans truncate max-w-md">Proxy</h3>
                        <span id="hist-proxy-badge" class="text-[9px] font-mono font-bold uppercase bg-obsidian-panel text-obsidian-cyan px-1.5 py-0.5 rounded border border-obsidian-border">PROXY GATEWAY</span>
                    </div>
                    <p id="hist-proxy-target" class="text-xs font-mono text-obsidian-muted mt-0.5 truncate">IP:Puerto: 10.20.0.1:8080</p>
                </div>
            </div>

            <!-- CONTROLES DE RANGO TEMPORAL -->
            <div class="flex items-center gap-2 self-end sm:self-auto">
                <div class="inline-flex rounded-lg p-1 bg-obsidian-panel border border-obsidian-border text-xs font-mono">
                    <button type="button" onclick="loadProxyHistory('6h')" id="btn-proxy-range-6h" class="px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition proxy-range-btn">6H</button>
                    <button type="button" onclick="loadProxyHistory('24h')" id="btn-proxy-range-24h" class="px-2.5 py-1 rounded-md bg-amber-400 text-black font-bold transition proxy-range-btn">24H</button>
                    <button type="button" onclick="loadProxyHistory('7d')" id="btn-proxy-range-7d" class="px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition proxy-range-btn">7D</button>
                    <button type="button" onclick="loadProxyHistory('30d')" id="btn-proxy-range-30d" class="px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition proxy-range-btn">30D</button>
                </div>
                <button type="button" onclick="closeModal('modal-proxy-history')" class="p-1.5 rounded-lg text-obsidian-muted hover:text-white hover:bg-obsidian-panel text-xl leading-none transition" title="Cerrar">&times;</button>
            </div>
        </div>

        <!-- 4 TARJETAS DE MÉTRICAS KPI -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Disponibilidad Túnel</span>
                    <span class="material-symbols-outlined text-xs text-emerald-400">verified</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-proxy-stat-uptime" class="text-lg font-bold font-mono text-emerald-400">--%</span>
                </div>
                <p id="hist-proxy-stat-checks" class="text-[9px] font-mono text-obsidian-muted mt-0.5">-- chequeos totales</p>
            </div>

            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Latencia Media Túnel</span>
                    <span class="material-symbols-outlined text-xs text-obsidian-cyan">speed</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-proxy-stat-avg" class="text-lg font-bold font-mono text-white">-- ms</span>
                </div>
                <p class="text-[9px] font-mono text-obsidian-muted mt-0.5">Tiempo de respuesta</p>
            </div>

            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Latencia Máxima</span>
                    <span class="material-symbols-outlined text-xs text-amber-400">trending_up</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-proxy-stat-max" class="text-lg font-bold font-mono text-amber-400">-- ms</span>
                </div>
                <p class="text-[9px] font-mono text-obsidian-muted mt-0.5">Pico de latencia</p>
            </div>

            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Fallas de Proxy</span>
                    <span class="material-symbols-outlined text-xs text-red-400">warning</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-proxy-stat-outages" class="text-lg font-bold font-mono text-white">0</span>
                </div>
                <p id="hist-proxy-stat-last-down" class="text-[9px] font-mono text-obsidian-muted mt-0.5">Sin incidentes</p>
            </div>
        </div>

        <!-- CONTENEDOR DEL GRÁFICO DE LÍNEA -->
        <div class="glass-card rounded-xl p-4 border border-obsidian-border space-y-3 bg-obsidian-panel/50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <h4 class="text-xs font-bold font-mono uppercase text-white tracking-wider">Histórico Temporal de Latencia & Conectividad HTTPS</h4>
                </div>
                <div class="flex items-center gap-3 text-[10px] font-mono text-obsidian-muted">
                    <span class="flex items-center gap-1"><span class="w-2 h-0.5 bg-amber-400"></span> Latencia Proxy (ms)</span>
                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Caída / Falló Túnel</span>
                </div>
            </div>
            
            <div class="relative w-full h-[220px]">
                <canvas id="proxyHistoryChart"></canvas>
                <div id="proxy-chart-loading-overlay" class="absolute inset-0 flex items-center justify-center bg-obsidian-bg/80 backdrop-blur-xs rounded-lg hidden">
                    <div class="flex items-center gap-2 text-amber-400 font-mono text-xs">
                        <span class="material-symbols-outlined animate-spin text-base">sync</span>
                        <span>Cargando telemetría de proxy...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- REGISTRO RECIENTE DE INCIDENTES / CAÍDAS -->
        <div class="glass-card rounded-xl p-4 border border-obsidian-border space-y-2 bg-obsidian-panel/30">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold font-mono uppercase text-white tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-amber-400 text-sm">history_toggle_off</span>
                    Registro de Fallas e Inaccesibilidad del Proxy
                </h4>
                <span id="hist-proxy-incidents-badge" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40">
                    0 Incidentes
                </span>
            </div>

            <div class="overflow-x-auto max-h-36 overflow-y-auto custom-scroll">
                <table class="w-full text-left text-[11px] font-mono">
                    <thead class="bg-obsidian-panel/80 text-obsidian-muted uppercase text-[9.5px]">
                        <tr>
                            <th class="px-3 py-1.5">Fecha y Hora</th>
                            <th class="px-3 py-1.5">Tiempo Relativo</th>
                            <th class="px-3 py-1.5">Diagnóstico / Error</th>
                            <th class="px-3 py-1.5 text-right">Código HTTP</th>
                        </tr>
                    </thead>
                    <tbody id="hist-proxy-incidents-tbody" class="divide-y divide-obsidian-border/40">
                        <tr>
                            <td colspan="4" class="px-3 py-3 text-center text-obsidian-muted text-[10px]">
                                No se registran fallas de proxy en el período seleccionado.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let currentProxyId = null;
    let currentProxyRange = '24h';
    let proxyHistoryChart = null;

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
            closeModal('modal-proxy-history');
            closeModal('modal-edit-proxy');
        }
    });

    function openEditProxyModal(p) {
        document.getElementById('form-edit-proxy').action = `/admin/proxies/${p.id}`;
        document.getElementById('edit_p_letter').value = p.letter;
        document.getElementById('edit_p_name').value = p.name;
        document.getElementById('edit_p_ip_port').value = p.ip_port || '';
        document.getElementById('edit_p_auth').value = p.auth_userpass || '';
        document.getElementById('edit_p_active').checked = p.is_active;
        openModal('modal-edit-proxy');
    }

    async function openProxyHistoryModal(proxyId, name, letter, ipPort) {
        currentProxyId = proxyId;
        document.getElementById('hist-proxy-name').innerText = name;
        document.getElementById('hist-proxy-letter').innerText = `[ ${letter} ]`;
        document.getElementById('hist-proxy-target').innerText = `IP:Puerto: ${ipPort || 'N/A'}`;
        
        openModal('modal-proxy-history');
        await loadProxyHistory(currentProxyRange);
    }

    async function loadProxyHistory(range) {
        if (!currentProxyId) return;
        currentProxyRange = range;
        
        // Update active tab buttons
        document.querySelectorAll('.proxy-range-btn').forEach(btn => {
            btn.className = 'px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition proxy-range-btn';
        });
        const activeBtn = document.getElementById(`btn-proxy-range-${range}`);
        if (activeBtn) {
            activeBtn.className = 'px-2.5 py-1 rounded-md bg-amber-400 text-black font-bold transition proxy-range-btn';
        }

        const overlay = document.getElementById('proxy-chart-loading-overlay');
        if (overlay) overlay.classList.remove('hidden');

        try {
            const res = await fetch(`/admin/proxies/${currentProxyId}/history?range=${range}`);
            if (!res.ok) throw new Error('Error al cargar histórico de proxy');
            const data = await res.json();
            if (!data.success) throw new Error('Datos no disponibles');

            // Stats
            document.getElementById('hist-proxy-stat-uptime').innerText = `${data.stats.uptime_percentage}%`;
            document.getElementById('hist-proxy-stat-checks').innerText = `${data.stats.total_checks} chequeos (${data.stats.up_checks} OK / ${data.stats.down_checks} fallos)`;
            document.getElementById('hist-proxy-stat-avg').innerText = `${data.stats.avg_latency} ms`;
            document.getElementById('hist-proxy-stat-max').innerText = `${data.stats.max_latency} ms`;
            document.getElementById('hist-proxy-stat-outages').innerText = `${data.stats.down_checks}`;
            
            const lastDown = document.getElementById('hist-proxy-stat-last-down');
            if (data.stats.down_checks > 0) {
                lastDown.innerText = `${data.stats.down_checks} eventos de indisponibilidad`;
                lastDown.className = 'text-[9px] font-mono text-red-400 mt-0.5';
            } else {
                lastDown.innerText = '100% de operatividad';
                lastDown.className = 'text-[9px] font-mono text-emerald-400 mt-0.5';
            }

            // Incidents Table
            const incidentsBadge = document.getElementById('hist-proxy-incidents-badge');
            const incidentsTbody = document.getElementById('hist-proxy-incidents-tbody');
            
            if (data.incidents && data.incidents.length > 0) {
                incidentsBadge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-red-950 text-red-400 border border-red-500/40 glow-red';
                incidentsBadge.innerText = `${data.incidents.length} Fallas`;
                
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
                            No se registran fallas de proxy en el período seleccionado.
                        </td>
                    </tr>
                `;
            }

            // Render Chart.js
            renderProxyHistoryChart(data.labels, data.latencies, data.statuses, data.points);

        } catch (err) {
            console.error('Error fetching proxy history:', err);
        } finally {
            if (overlay) overlay.classList.add('hidden');
        }
    }

    function renderProxyHistoryChart(labels, latencies, statuses, points) {
        const ctx = document.getElementById('proxyHistoryChart').getContext('2d');
        
        const isAllDown = statuses.length > 0 && statuses.every(s => s === 0);
        const lineColor = isAllDown ? '#ef4444' : '#fbbf24';

        // Gradient fill under line (Amber/Gold glow / Red glow if down)
        const gradient = ctx.createLinearGradient(0, 0, 0, 200);
        if (isAllDown) {
            gradient.addColorStop(0, 'rgba(239, 68, 68, 0.35)');
            gradient.addColorStop(1, 'rgba(239, 68, 68, 0.0)');
        } else {
            gradient.addColorStop(0, 'rgba(245, 158, 11, 0.35)');
            gradient.addColorStop(1, 'rgba(245, 158, 11, 0.0)');
        }

        // Point colors & radius
        const pointBackgroundColors = statuses.map(s => s === 1 ? '#fbbf24' : '#ef4444');
        const pointBorderColors = statuses.map(s => s === 1 ? '#051424' : '#fee2e2');
        const pointRadiuses = statuses.map(s => s === 1 ? (statuses.length > 30 ? 0 : 3) : 6);
        const pointHoverRadiuses = statuses.map(s => s === 1 ? 5 : 8);

        const chartData = {
            labels: labels,
            datasets: [{
                label: 'Latencia Proxy (ms)',
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

        if (proxyHistoryChart) {
            proxyHistoryChart.destroy();
        }

        proxyHistoryChart = new Chart(ctx, {
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
                        borderColor: isAllDown ? 'rgba(239, 68, 68, 0.5)' : 'rgba(245, 158, 11, 0.4)',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const pt = points[idx];
                                if (!pt) return `Latencia: ${context.parsed.y} ms`;
                                if (pt.is_up) {
                                    return `⚡ Latencia Túnel: ${pt.latency_ms} ms (${pt.status_message})`;
                                } else {
                                    return `❌ PROXY CAÍDO (${pt.status_message})`;
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
