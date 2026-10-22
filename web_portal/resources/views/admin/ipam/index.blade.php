@extends('layouts.admin')

@section('title', 'Gestión IPAM & Direccionamiento de Red - ATIT Valle Seco')

@section('content')
<div class="space-y-5">
    <!-- CABECERA PRINCIPAL -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-[10.5px] font-mono font-semibold group shadow-xs">
                    <span class="material-symbols-outlined text-xs group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-xs font-mono text-obsidian-muted">Gestión IPAM</span>
            </div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">
                    <span class="material-symbols-outlined text-2xl">grid_view</span>
                </span>
                <div>
                    <h1 class="text-xl font-black text-white tracking-wide">
                        Gestión IPAM &amp; Matriz de Subredes
                    </h1>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Visualización matricial de direccionamiento, correlación de activos ITAM y detección de equipos no catalogados.
                    </p>
                </div>
            </div>
        </div>

        <!-- SELECTOR DE SUBRED -->
        <div class="flex items-center gap-2">
            <div class="relative">
                <select id="subnet-selector" onchange="onSubnetSelected(this.value)" class="px-3 py-1.5 rounded-xl bg-obsidian-panel border border-cyan-500/40 text-cyan-300 font-mono text-xs font-bold focus:outline-none focus:border-cyan-400 shadow-md">
                    @foreach($subnets as $s)
                        <option value="{{ $s['cidr'] }}" {{ $selectedSubnet === $s['cidr'] ? 'selected' : '' }}>
                            📍 {{ $s['site_name'] }} ({{ $s['cidr'] }})
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="button" onclick="refreshCurrentGrid()" class="w-8 h-8 rounded-xl bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black transition flex items-center justify-center cursor-pointer shadow-xs" title="Actualizar Cuadrícula en Vivo">
                <span class="material-symbols-outlined text-base" id="refresh-grid-icon">refresh</span>
            </button>
        </div>
    </div>

    <!-- TARJETAS DE MÉTRICAS Y BARRA DE OCUPACIÓN -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <!-- MÉTRICA 1: TOTAL HOSTS -->
        <div class="p-3.5 rounded-2xl bg-obsidian-card border border-obsidian-border flex items-center gap-3">
            <span class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30">
                <span class="material-symbols-outlined text-xl">lan</span>
            </span>
            <div>
                <span class="text-[10px] font-mono text-obsidian-muted uppercase font-bold block">Espacio Subred</span>
                <span class="text-base font-black text-white font-mono" id="stat-total-hosts">{{ $grid['metrics']['total_hosts'] }}</span>
                <span class="text-[9px] font-mono text-obsidian-muted block">Hosts /24</span>
            </div>
        </div>

        <!-- MÉTRICA 2: ASIGNADAS REGISTRADAS (UP) -->
        <div class="p-3.5 rounded-2xl bg-obsidian-card border border-obsidian-border flex items-center gap-3">
            <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                <span class="material-symbols-outlined text-xl">verified</span>
            </span>
            <div>
                <span class="text-[10px] font-mono text-obsidian-muted uppercase font-bold block">Registradas Activas</span>
                <span class="text-base font-black text-emerald-400 font-mono" id="stat-official-up">{{ $grid['metrics']['official_up_count'] }}</span>
                <span class="text-[9px] font-mono text-obsidian-muted block">Censo ITAM • UP</span>
            </div>
        </div>

        <!-- MÉTRICA 3: ASIGNADAS REGISTRADAS (DOWN) -->
        <div class="p-3.5 rounded-2xl bg-obsidian-card border border-obsidian-border flex items-center gap-3">
            <span class="p-2 rounded-xl bg-red-500/10 text-red-400 border border-red-500/30">
                <span class="material-symbols-outlined text-xl">link_off</span>
            </span>
            <div>
                <span class="text-[10px] font-mono text-obsidian-muted uppercase font-bold block">Registradas Caídas</span>
                <span class="text-base font-black text-red-400 font-mono" id="stat-official-down">{{ $grid['metrics']['official_down_count'] }}</span>
                <span class="text-[9px] font-mono text-obsidian-muted block">Censo ITAM • DOWN</span>
            </div>
        </div>

        <!-- MÉTRICA 4: NO CATALOGADAS / ROGUE -->
        <div class="p-3.5 rounded-2xl bg-obsidian-card border border-obsidian-border flex items-center gap-3">
            <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/30">
                <span class="material-symbols-outlined text-xl">warning_amber</span>
            </span>
            <div>
                <span class="text-[10px] font-mono text-obsidian-muted uppercase font-bold block">No Catalogadas</span>
                <span class="text-base font-black text-amber-400 font-mono" id="stat-rogue">{{ $grid['metrics']['rogue_count'] }}</span>
                <span class="text-[9px] font-mono text-obsidian-muted block">Responden • Sin Ficha</span>
            </div>
        </div>

        <!-- MÉTRICA 5: LIBRES / DISPONIBLES -->
        <div class="p-3.5 rounded-2xl bg-obsidian-card border border-obsidian-border col-span-2 sm:col-span-1 flex items-center gap-3">
            <span class="p-2 rounded-xl bg-slate-500/10 text-slate-400 border border-slate-500/30">
                <span class="material-symbols-outlined text-xl">check_box_outline_blank</span>
            </span>
            <div>
                <span class="text-[10px] font-mono text-obsidian-muted uppercase font-bold block">Disponibles</span>
                <span class="text-base font-black text-slate-300 font-mono" id="stat-free">{{ $grid['metrics']['free_count'] }}</span>
                <span class="text-[9px] font-mono text-obsidian-muted block">IPs Libres</span>
            </div>
        </div>
    </div>

    <!-- BARRA DE UTILIZACIÓN Y LEYENDA -->
    <div class="p-4 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border space-y-3 font-mono text-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="text-white font-bold">Ocupación de la Subred:</span>
                <span id="subnet-util-text" class="text-cyan-300 font-extrabold text-sm">{{ $grid['metrics']['utilization_pct'] }}%</span>
                <span class="text-obsidian-muted text-[11px]">({{ $grid['metrics']['total_used'] }} de 254 asignadas)</span>
            </div>

            <!-- LEYENDA VISUAL -->
            <div class="flex flex-wrap items-center gap-3 text-[11px]">
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-emerald-500/80 border border-emerald-400"></span>
                    <span class="text-slate-300">Registrado Activo</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-red-500/80 border border-red-400"></span>
                    <span class="text-slate-300">Registrado Caído</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-amber-500/80 border border-amber-400"></span>
                    <span class="text-slate-300">No Catalogado (Alerta)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-blue-600/80 border border-blue-400"></span>
                    <span class="text-slate-300">Reservada / Red</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-[#0b1726] border border-obsidian-border"></span>
                    <span class="text-slate-400">Libre / Disponible</span>
                </div>
            </div>
        </div>

        <div class="w-full bg-[#040c17] h-2.5 rounded-full overflow-hidden border border-obsidian-border flex">
            <div id="bar-official" class="bg-emerald-500 h-full transition-all duration-300" style="width: {{ round(($grid['metrics']['official_up_count'] / 254) * 100, 1) }}%" title="Registradas Activas"></div>
            <div id="bar-down" class="bg-red-500 h-full transition-all duration-300" style="width: {{ round(($grid['metrics']['official_down_count'] / 254) * 100, 1) }}%" title="Registradas Caídas"></div>
            <div id="bar-rogue" class="bg-amber-500 h-full transition-all duration-300" style="width: {{ round(($grid['metrics']['rogue_count'] / 254) * 100, 1) }}%" title="No Catalogadas"></div>
        </div>
    </div>

    <!-- CUADRÍCULA MATRICIAL IPAM DE 256 BLOQUES -->
    <div class="p-4 sm:p-5 rounded-2xl bg-obsidian-card border border-obsidian-border shadow-xl space-y-3">
        <div class="flex items-center justify-between pb-2 border-b border-obsidian-border/60 font-mono text-xs">
            <span class="text-obsidian-muted font-bold uppercase tracking-wider flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm text-cyan-400">apps</span>
                <span>Matriz de Direccionamiento: <strong class="text-cyan-300" id="current-cidr-title">{{ $grid['cidr'] }}</strong></span>
            </span>
            <span class="text-[11px] text-obsidian-muted">Haz clic en cualquier celda para ver detalles o asignar</span>
        </div>

        <!-- CONTENEDOR GRID 16x16 -->
        <div id="ipam-grid-container" class="grid grid-cols-8 sm:grid-cols-16 gap-1 sm:gap-1.5 select-none font-mono">
            @foreach($grid['cells'] as $cell)
                @php
                    $status = $cell['status'];
                    $octet = $cell['octet'];
                    $ip = $cell['ip'];

                    $bgClass = match($status) {
                        'network_id', 'broadcast' => 'bg-blue-950/70 border-blue-500/50 text-blue-300',
                        'official_up' => 'bg-emerald-950/80 border-emerald-500/60 text-emerald-300 hover:bg-emerald-900 hover:border-emerald-400 hover:shadow-lg hover:shadow-emerald-500/20',
                        'official_down' => 'bg-red-950/80 border-red-500/60 text-red-300 hover:bg-red-900 hover:border-red-400 hover:shadow-lg hover:shadow-red-500/20',
                        'rogue' => 'bg-amber-950/80 border-amber-500/60 text-amber-300 hover:bg-amber-900 hover:border-amber-400 hover:shadow-lg hover:shadow-amber-500/20 animate-pulse',
                        default => 'bg-[#06111f] border-obsidian-border/70 text-slate-500 hover:bg-cyan-950/40 hover:border-cyan-500/50 hover:text-cyan-300',
                    };
                @endphp

                <button type="button" 
                        onclick="inspectCell({{ json_encode($cell) }})"
                        class="h-8 sm:h-9 rounded-lg border text-[11px] sm:text-xs font-bold transition flex items-center justify-center cursor-pointer relative group {{ $bgClass }}"
                        title="{{ $cell['title'] ?? $cell['ip'] }}">
                    <span>.{{ $octet }}</span>
                    @if($status === 'official_up' && ($cell['is_gateway'] ?? false))
                        <span class="absolute top-0.5 right-0.5 w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                    @elseif($status === 'rogue')
                        <span class="absolute top-0.5 right-0.5 w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>
</div>

<!-- ============================================================================== -->
<!-- MODAL DE INSPECCIÓN DE CELDA IPAM                                              -->
<!-- ============================================================================== -->
<div id="modal-ipam-cell" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-obsidian-panel border border-cyan-500/40 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 flex flex-col font-mono text-xs">
        <!-- HEADER -->
        <div class="p-4 bg-obsidian-card border-b border-obsidian-border flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="p-1.5 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/30">
                    <span class="material-symbols-outlined text-lg" id="modal-cell-icon">info</span>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-white font-mono" id="modal-cell-ip">192.168.1.0</h3>
                    <p class="text-[10px] text-obsidian-muted" id="modal-cell-status-label">Estado de la dirección IP</p>
                </div>
            </div>
            <button type="button" onclick="closeCellModal()" class="text-obsidian-muted hover:text-white transition p-1">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- BODY -->
        <div class="p-4 space-y-3 overflow-y-auto max-h-[70vh] custom-scroll">
            <div id="modal-cell-details" class="space-y-2"></div>
        </div>

        <!-- FOOTER ACTIONS -->
        <div class="p-3.5 bg-obsidian-card/80 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0" id="modal-cell-actions">
            <button type="button" onclick="closeCellModal()" class="px-3 py-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition text-xs">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- SCRIPT DE INTERACCIÓN IPAM -->
<script>
    function inspectCell(cell) {
        const modal = document.getElementById('modal-ipam-cell');
        const icon = document.getElementById('modal-cell-icon');
        const ipLabel = document.getElementById('modal-cell-ip');
        const statusLabel = document.getElementById('modal-cell-status-label');
        const details = document.getElementById('modal-cell-details');
        const actions = document.getElementById('modal-cell-actions');

        ipLabel.innerText = cell.ip;

        if (cell.status === 'network_id' || cell.status === 'broadcast') {
            icon.innerText = 'router';
            icon.className = 'material-symbols-outlined text-lg text-blue-400';
            statusLabel.innerText = cell.label + ' • Dirección Reservada';
            details.innerHTML = `
                <div class="p-3 rounded-xl bg-[#040c17] border border-blue-500/30 text-blue-300">
                    <p class="text-[11px]">Esta dirección está reservada por el estándar IPv4 para la identificación de la red o difusión (broadcast).</p>
                </div>`;
            actions.innerHTML = `<button type="button" onclick="closeCellModal()" class="px-3.5 py-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition">Cerrar</button>`;
        } else if (cell.status === 'official_up' || cell.status === 'official_down') {
            const isUp = cell.status === 'official_up';
            icon.innerText = isUp ? 'verified' : 'link_off';
            icon.className = `material-symbols-outlined text-lg ${isUp ? 'text-emerald-400' : 'text-red-400'}`;
            statusLabel.innerText = isUp ? 'Equipo Registrado (En Línea)' : 'Equipo Registrado (Sin Respuesta / Caído)';

            details.innerHTML = `
                <div class="space-y-2 p-3 rounded-xl bg-[#040c17] border border-obsidian-border">
                    <div class="flex items-center justify-between pb-1.5 border-b border-obsidian-border/60">
                        <span class="text-obsidian-muted text-[10px] uppercase font-bold">Puesto / Nombre Etiqueta:</span>
                        <strong class="text-white text-xs">${cell.name || 'Sin nombre'}</strong>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-obsidian-muted text-[10px] uppercase font-bold">Sede Asignada:</span>
                        <span class="text-cyan-300">${cell.site_name || 'No asignada'}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-obsidian-muted text-[10px] uppercase font-bold">Responsable Asignado:</span>
                        <span class="text-slate-300 font-bold">${cell.responsable || 'No asignado'}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-obsidian-muted text-[10px] uppercase font-bold">Dirección MAC:</span>
                        <span class="text-slate-400 font-mono">${cell.mac || 'No registrada'}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-obsidian-muted text-[10px] uppercase font-bold">Tipo de Dispositivo:</span>
                        <span class="text-slate-300 capitalize">${cell.type || 'desktop'}</span>
                    </div>
                </div>`;

            let actionBtns = `<button type="button" onclick="closeCellModal()" class="px-3.5 py-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition">Cerrar</button>`;
            if (cell.asset_id) {
                actionBtns += `<a href="/admin/inventory/assets/${cell.asset_id}" target="_blank" class="px-3.5 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-bold transition flex items-center gap-1 shadow-md shadow-cyan-500/20">
                    <span class="material-symbols-outlined text-sm">visibility</span>
                    <span>Ver Ficha Técnica</span>
                </a>`;
            }
            actions.innerHTML = actionBtns;
        } else if (cell.status === 'rogue') {
            icon.innerText = 'warning_amber';
            icon.className = 'material-symbols-outlined text-lg text-amber-400 animate-pulse';
            statusLabel.innerText = 'Equipo No Catalogado (Detectado en Red)';

            details.innerHTML = `
                <div class="p-3 rounded-xl bg-amber-950/30 border border-amber-500/40 text-amber-200 space-y-2">
                    <p class="text-[11px] leading-relaxed">Este equipo responde a tráfico de red (ARP/Ping), pero <b>no se encuentra formalizado en el inventario ITAM</b>.</p>
                    <div class="pt-2 border-t border-amber-500/30 space-y-1 text-[11px]">
                        <div class="flex justify-between"><span>Hostname Detectado:</span><strong class="text-white">${cell.hostname || 'Desconocido'}</strong></div>
                        <div class="flex justify-between"><span>Dirección MAC:</span><strong class="text-cyan-300">${cell.mac || 'N/A'}</strong></div>
                        <div class="flex justify-between"><span>Fabricante:</span><span class="text-slate-300">${cell.vendor || 'Desconocido'}</span></div>
                        <div class="flex justify-between"><span>Última Detección:</span><span class="text-slate-400">${cell.detected_at || 'Reciente'}</span></div>
                    </div>
                </div>`;

            actions.innerHTML = `
                <button type="button" onclick="closeCellModal()" class="px-3.5 py-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition">Cerrar</button>
                <a href="/admin/inventory/assets/create?ip=${encodeURIComponent(cell.ip)}&mac=${encodeURIComponent(cell.mac || '')}" class="px-3.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-black font-bold transition flex items-center gap-1 shadow-md shadow-amber-500/20">
                    <span class="material-symbols-outlined text-sm">post_add</span>
                    <span>Catalogar en Inventario</span>
                </a>`;
        } else {
            // Libre / Disponible
            icon.innerText = 'check_box_outline_blank';
            icon.className = 'material-symbols-outlined text-lg text-slate-400';
            statusLabel.innerText = 'Dirección IP Libre / Disponible';

            details.innerHTML = `
                <div class="p-3 rounded-xl bg-[#040c17] border border-obsidian-border text-slate-300 space-y-1">
                    <p class="text-[11px]">La dirección <b>${cell.ip}</b> se encuentra disponible y no se detecta tráfico activo en los últimos sondeos de red.</p>
                    <p class="text-[10px] text-obsidian-muted mt-1">Disponible para asignarse a una nueva estación de trabajo, impresora o periférico.</p>
                </div>`;

            actions.innerHTML = `
                <button type="button" onclick="closeCellModal()" class="px-3.5 py-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition">Cerrar</button>
                <a href="/admin/inventory/assets/create?ip=${encodeURIComponent(cell.ip)}" class="px-3.5 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-bold transition flex items-center gap-1 shadow-md shadow-cyan-500/20">
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    <span>Asignar a Nuevo Equipo</span>
                </a>`;
        }

        modal.classList.remove('hidden');
    }

    function closeCellModal() {
        document.getElementById('modal-ipam-cell').classList.add('hidden');
    }

    async function onSubnetSelected(cidr) {
        if (!cidr) return;
        loadSubnetGrid(cidr);
    }

    async function refreshCurrentGrid() {
        const selector = document.getElementById('subnet-selector');
        const cidr = selector.value || '192.168.1.0/24';
        loadSubnetGrid(cidr);
    }

    async function loadSubnetGrid(cidr) {
        const icon = document.getElementById('refresh-grid-icon');
        icon.classList.add('animate-spin');

        try {
            const res = await fetch(`/admin/ipam/grid?subnet=${encodeURIComponent(cidr)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!res.ok) throw new Error('Error al consultar datos de la subred');
            const data = await res.json();

            // Actualizar Métricas
            document.getElementById('stat-total-hosts').innerText = data.metrics.total_hosts;
            document.getElementById('stat-official-up').innerText = data.metrics.official_up_count;
            document.getElementById('stat-official-down').innerText = data.metrics.official_down_count;
            document.getElementById('stat-rogue').innerText = data.metrics.rogue_count;
            document.getElementById('stat-free').innerText = data.metrics.free_count;
            document.getElementById('subnet-util-text').innerText = data.metrics.utilization_pct + '%';
            document.getElementById('current-cidr-title').innerText = data.cidr;

            // Actualizar Barras
            document.getElementById('bar-official').style.width = Math.round((data.metrics.official_up_count / 254) * 100) + '%';
            document.getElementById('bar-down').style.width = Math.round((data.metrics.official_down_count / 254) * 100) + '%';
            document.getElementById('bar-rogue').style.width = Math.round((data.metrics.rogue_count / 254) * 100) + '%';

            // Reconstruir Cuadrícula
            const container = document.getElementById('ipam-grid-container');
            container.innerHTML = '';

            data.cells.forEach(cell => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `h-8 sm:h-9 rounded-lg border text-[11px] sm:text-xs font-bold transition flex items-center justify-center cursor-pointer relative group ${getCellClass(cell.status)}`;
                btn.title = cell.title || cell.ip;
                btn.onclick = () => inspectCell(cell);

                let innerHtml = `<span>.${cell.octet}</span>`;
                if (cell.status === 'official_up' && cell.is_gateway) {
                    innerHtml += `<span class="absolute top-0.5 right-0.5 w-1.5 h-1.5 rounded-full bg-cyan-400"></span>`;
                } else if (cell.status === 'rogue') {
                    innerHtml += `<span class="absolute top-0.5 right-0.5 w-1.5 h-1.5 rounded-full bg-amber-400"></span>`;
                }

                btn.innerHTML = innerHtml;
                container.appendChild(btn);
            });
        } catch (e) {
            alert('Error al cargar la cuadrícula IPAM: ' + e.message);
        } finally {
            icon.classList.remove('animate-spin');
        }
    }

    function getCellClass(status) {
        switch (status) {
            case 'network_id':
            case 'broadcast':
                return 'bg-blue-950/70 border-blue-500/50 text-blue-300';
            case 'official_up':
                return 'bg-emerald-950/80 border-emerald-500/60 text-emerald-300 hover:bg-emerald-900 hover:border-emerald-400 hover:shadow-lg hover:shadow-emerald-500/20';
            case 'official_down':
                return 'bg-red-950/80 border-red-500/60 text-red-300 hover:bg-red-900 hover:border-red-400 hover:shadow-lg hover:shadow-red-500/20';
            case 'rogue':
                return 'bg-amber-950/80 border-amber-500/60 text-amber-300 hover:bg-amber-900 hover:border-amber-400 hover:shadow-lg hover:shadow-amber-500/20 animate-pulse';
            default:
                return 'bg-[#06111f] border-obsidian-border/70 text-slate-500 hover:bg-cyan-950/40 hover:border-cyan-500/50 hover:text-cyan-300';
        }
    }
</script>
@endsection
