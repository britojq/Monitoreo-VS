@extends('layouts.admin')

@section('page_title', 'Topología Visual de Red')

@section('admin_content')
<div class="space-y-4">
    <!-- ========================================================================= -->
    <!-- ENCABEZADO Y CONTROLES SUPERIORES                                          -->
    <!-- ========================================================================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-[10.5px] font-mono font-semibold group shadow-xs">
                    <span class="material-symbols-outlined text-xs group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-[10.5px] font-mono text-obsidian-muted">Supervisión &amp; Jerarquía de Red</span>
            </div>
            <h1 class="text-xl font-black text-white tracking-wide flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-2xl">hub</span>
                Topología de Red Visual Dinámica
                <span class="w-6 h-6 rounded-md bg-cyan-950/80 border border-cyan-500/40 text-cyan-400 inline-flex items-center justify-center cursor-help" title="LLDP / CDP / FDB Dinámico">
                    <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                </span>
                @if(!auth()->user()->isAdmin() && !auth()->user()->hasPermission('topology.rebuild'))
                    <span class="w-6 h-6 rounded-md bg-amber-950/80 text-amber-300 border border-amber-500/40 inline-flex items-center justify-center cursor-help" title="Modo Consulta: Acceso de solo lectura">
                        <span class="material-symbols-outlined text-xs">visibility</span>
                    </span>
                @endif
            </h1>
            <p class="text-xs font-mono text-obsidian-muted">
                Descubrimiento automático de dispositivos, jerarquía física y lógica con grafo interactivo en tiempo real
            </p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto flex-wrap">
            <div class="flex items-center gap-1.5">
                <span class="text-obsidian-muted text-xs font-mono uppercase">Filtrar Sede:</span>
                <select id="site-select" onchange="filterBySite(this.value)" class="bg-obsidian-panel border border-cyan-500/40 text-cyan-300 text-xs px-2.5 py-1.5 rounded-lg focus:outline-none focus:border-cyan-400 font-mono shadow-xs" title="Filtrar por sede física">
                    <option value="">Todas las Sedes (Troncal WAN)</option>
                    @foreach($sites as $st)
                        <option value="{{ $st->id }}" {{ ($selectedSiteId ?? null) == $st->id ? 'selected' : '' }}>
                            {{ $st->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if((auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('topology.rebuild'))) && !$isClusterSlave)
                <form action="{{ route('admin.topology.rebuild') }}" method="POST" class="inline m-0">
                    @csrf
                    <button type="submit" class="w-9 h-9 rounded-lg bg-cyan-500/10 border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black transition flex items-center justify-center cursor-pointer shadow-xs" title="Re-escanear Dispositivos Interconectados (Protocolos LLDP / CDP / FDB)">
                        <span class="material-symbols-outlined text-lg">travel_explore</span>
                    </button>
                </form>
            @endif

            <button type="button" onclick="loadTopologyGraph()" class="w-9 h-9 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black transition flex items-center justify-center cursor-pointer shadow-xs" title="Refrescar Grafo de Topología">
                <span class="material-symbols-outlined text-lg">refresh</span>
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- HUD: MÉTRICAS DE TOPOLOGÍA                                                -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
        <!-- TOTAL DISPOSITIVOS -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Nodos Identificados</span>
                <span class="material-symbols-outlined text-sm text-cyan-400">devices</span>
            </div>
            <div class="text-lg font-bold font-mono text-white mt-1">{{ number_format($totalDevices) }}</div>
            <div class="text-[9px] font-mono text-cyan-400/80 mt-0.5">Equipos físicos y lógicos</div>
        </div>

        <!-- TOTAL ENLACES -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Enlaces Descubiertos</span>
                <span class="material-symbols-outlined text-sm text-purple-400">conversion_path</span>
            </div>
            <div class="text-lg font-bold font-mono text-white mt-1">{{ number_format($totalLinks) }}</div>
            <div class="text-[9px] font-mono text-purple-400/80 mt-0.5">Relaciones punto a punto</div>
        </div>

        <!-- ENLACES ACTIVOS -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Enlaces UP</span>
                <span class="material-symbols-outlined text-sm text-emerald-400">link</span>
            </div>
            <div class="text-lg font-bold font-mono text-emerald-400 mt-1">{{ number_format($activeLinks) }}</div>
            <div class="text-[9px] font-mono text-emerald-400/80 mt-0.5">Operatividad normal</div>
        </div>

        <!-- NÚCLEO CORE -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Switches Core</span>
                <span class="material-symbols-outlined text-sm text-amber-400">stars</span>
            </div>
            <div class="text-lg font-bold font-mono text-amber-300 mt-1">{{ number_format($coreDevices) }}</div>
            <div class="text-[9px] font-mono text-amber-400/80 mt-0.5">Columna vertebral</div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- CANVAS INTERACTIVO DE CYTOSCAPE.JS Y PANEL LATERAL                        -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        <!-- ÁREA DEL GRAFO (3 COLUMNAS) -->
        <div class="lg:col-span-3 rounded-xl bg-obsidian-panel/90 border border-obsidian-border relative overflow-hidden flex flex-col shadow-lg" style="height: 640px;">
            <!-- BARRA DE HERRAMIENTAS Y DISPOSICIÓN -->
            <div class="p-2.5 bg-obsidian-bg/80 border-b border-obsidian-border flex items-center justify-between gap-2 flex-wrap text-xs font-mono">
                <div class="flex items-center gap-2">
                    <span class="text-obsidian-muted text-[11px] uppercase">Distribución:</span>
                    <select id="layout-select" onchange="changeLayout(this.value)" class="bg-obsidian-panel border border-obsidian-border text-white text-[11px] px-2 py-1 rounded focus:outline-none focus:border-cyan-500">
                        <option value="breadthfirst" selected>Jerárquica / Árbol en Cascada</option>
                        <option value="cose">COSE (Fuerza Orgánica)</option>
                        <option value="concentric">Concéntrica</option>
                        <option value="circle">Circular</option>
                        <option value="grid">Cuadrícula</option>
                    </select>
                </div>

                <div class="flex items-center gap-1.5">
                    <button type="button" onclick="cyZoom(1.2)" class="p-1 px-2 rounded bg-obsidian-panel hover:bg-cyan-500 hover:text-black border border-obsidian-border transition text-[11px]" title="Zoom Acercar">
                        <span class="material-symbols-outlined text-xs align-middle">zoom_in</span>
                    </button>
                    <button type="button" onclick="cyZoom(0.8)" class="p-1 px-2 rounded bg-obsidian-panel hover:bg-cyan-500 hover:text-black border border-obsidian-border transition text-[11px]" title="Zoom Alejar">
                        <span class="material-symbols-outlined text-xs align-middle">zoom_out</span>
                    </button>
                    <button type="button" onclick="cyFit()" class="p-1 px-2 rounded bg-obsidian-panel hover:bg-cyan-500 hover:text-black border border-obsidian-border transition text-[11px]" title="Ajustar al Lienzo (Fit)">
                        <span class="material-symbols-outlined text-xs align-middle">crop_free</span>
                    </button>
                    <button type="button" onclick="cyReset()" class="p-1 px-2 rounded bg-obsidian-panel hover:bg-cyan-500 hover:text-black border border-obsidian-border transition text-[11px]" title="Restablecer Vista">
                        <span class="material-symbols-outlined text-xs align-middle">restart_alt</span>
                    </button>
                </div>
            </div>

            <!-- CONTENEDOR CYTOSCAPE -->
            <div id="cy" class="flex-1 w-full bg-[#051424]" style="min-height: 560px;"></div>

            <!-- LEYENDA FLOTANTE -->
            <div class="absolute bottom-3 left-3 bg-obsidian-panel/90 border border-obsidian-border/90 backdrop-blur-md rounded-lg p-2 text-[10px] font-mono text-obsidian-muted flex items-center gap-3 shadow-md pointer-events-none">
                <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span> Core Switch</div>
                <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-purple-400"></span> Switch / Router</div>
                <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-amber-400"></span> Firewall</div>
                <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span> Host UP</div>
                <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Host DOWN</div>
            </div>
        </div>

        <!-- PANEL DE DETALLES DEL NODO SELECCIONADO (1 COLUMNA) -->
        <div class="rounded-xl bg-obsidian-panel/90 border border-obsidian-border p-4 flex flex-col justify-between" style="min-height: 640px;">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-obsidian-border">
                    <h3 class="text-xs font-bold text-white uppercase font-mono tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-cyan-400 text-sm">info</span>
                        Ficha de Elemento
                    </h3>
                    <span id="node-badge" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-800 text-slate-300">
                        Seleccionar nodo
                    </span>
                </div>

                <div id="node-details-empty" class="py-16 text-center text-obsidian-muted font-mono text-xs">
                    <span class="material-symbols-outlined text-4xl text-obsidian-border mb-2 block">touch_app</span>
                    Haz clic sobre cualquier equipo del mapa para inspeccionar sus interfaces, IP, fabricante y enlaces.
                </div>

                <div id="node-details-content" class="space-y-3.5 mt-4 hidden font-mono text-xs">
                    <div>
                        <span class="text-[10px] text-obsidian-muted uppercase block">Nombre Etiqueta Corporativo</span>
                        <div id="detail-name" class="font-bold text-white text-sm break-words">--</div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-[10px] text-obsidian-muted uppercase block">Dirección IP</span>
                            <div id="detail-ip" class="font-bold text-cyan-300">--</div>
                        </div>
                        <div>
                            <span class="text-[10px] text-obsidian-muted uppercase block">Tipo de Nodo</span>
                            <div id="detail-type" class="font-semibold text-white capitalize">--</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-[10px] text-obsidian-muted uppercase block">Fabricante / Marca</span>
                            <div id="detail-vendor" class="text-white">--</div>
                        </div>
                        <div>
                            <span class="text-[10px] text-obsidian-muted uppercase block">Modelo</span>
                            <div id="detail-model" class="text-white">--</div>
                        </div>
                    </div>

                    <div>
                        <span class="text-[10px] text-obsidian-muted uppercase block">Sede Física</span>
                        <div id="detail-site" class="text-slate-300 text-[11px]">--</div>
                    </div>

                    <div id="detail-responsable-container" class="hidden">
                        <span class="text-[10px] text-obsidian-muted uppercase block">Responsable Asignado</span>
                        <div id="detail-responsable" class="text-emerald-300 font-semibold text-[11px]">--</div>
                    </div>

                    <div id="detail-notes-container" class="hidden">
                        <span class="text-[10px] text-cyan-400 font-bold uppercase block mb-1 flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">lan</span>
                            Notas y Puertos Asignados
                        </span>
                        <div id="detail-notes" class="p-2.5 bg-[#051424] rounded-lg border border-cyan-500/20 text-cyan-200 text-[11px] leading-relaxed whitespace-pre-line font-mono">--</div>
                    </div>

                    <div class="pt-3 border-t border-obsidian-border space-y-2">
                        <a id="detail-action-link" href="#" class="w-full py-2 px-3 rounded-lg bg-cyan-500/10 border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-sm" id="detail-action-icon">open_in_new</span>
                            <span id="detail-action-text">Ver Ficha Técnica</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- RESUMEN DE ENLACES DISPONIBLES -->
            <div class="pt-4 border-t border-obsidian-border">
                <div class="text-[10px] font-mono text-obsidian-muted uppercase mb-2">Protocolos de Descubrimiento</div>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-cyan-950/70 border border-cyan-500/30 text-cyan-300">CDP (Cisco)</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-purple-950/70 border border-purple-500/30 text-purple-300">LLDP (Estándar)</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-blue-950/70 border border-blue-500/30 text-blue-300">FDB Inferred</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS CYTOSCAPE.JS AUTÓNOMO LOCAL -->
<script src="{{ asset('vendor/cytoscape/cytoscape.min.js') }}"></script>
<script>
    let cy = null;

    document.addEventListener('DOMContentLoaded', function () {
        loadTopologyGraph();
    });

    function filterBySite(siteId) {
        loadTopologyGraph(siteId);
    }

    async function loadTopologyGraph(siteIdOverride) {
        const siteSelect = document.getElementById('site-select');
        const siteId = siteIdOverride !== undefined ? siteIdOverride : (siteSelect ? siteSelect.value : '');
        const cyContainer = document.getElementById('cy');

        let url = '{{ route("admin.topology.data") }}';
        if (siteId) {
            url += '?site_id=' + encodeURIComponent(siteId);
        }

        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();

            if (cy) {
                cy.destroy();
            }

            cy = cytoscape({
                container: cyContainer,
                elements: data.elements,
                style: [
                    // Estilo de Nodos Base
                    {
                        selector: 'node',
                        style: {
                            'label': 'data(label)',
                            'color': '#d4e4fa',
                            'font-family': 'JetBrains Mono, monospace',
                            'font-size': '10px',
                            'text-valign': 'bottom',
                            'text-margin-y': '6px',
                            'background-color': '#1e293b',
                            'border-width': 2,
                            'border-color': '#334155',
                            'width': 34,
                            'height': 34,
                            'transition-property': 'background-color, border-color, width, height',
                            'transition-duration': '0.3s'
                        }
                    },
                    // Core Switch
                    {
                        selector: 'node[type = "core_switch"]',
                        style: {
                            'background-color': '#0891b2',
                            'border-color': '#22d3ee',
                            'border-width': 3,
                            'width': 48,
                            'height': 48,
                            'shape': 'round-hexagon',
                            'font-weight': 'bold',
                            'font-size': '12px',
                            'color': '#22d3ee'
                        }
                    },
                    // Switches Normales
                    {
                        selector: 'node[type = "switch"]',
                        style: {
                            'background-color': '#0f172a',
                            'border-color': '#38bdf8',
                            'shape': 'round-rectangle',
                            'width': 38,
                            'height': 38
                        }
                    },
                    // Routers
                    {
                        selector: 'node[type = "router"]',
                        style: {
                            'background-color': '#1e1b4b',
                            'border-color': '#818cf8',
                            'shape': 'diamond',
                            'width': 42,
                            'height': 42
                        }
                    },
                    // Firewalls
                    {
                        selector: 'node[type = "firewall"]',
                        style: {
                            'background-color': '#451a03',
                            'border-color': '#f59e0b',
                            'shape': 'octagon',
                            'width': 40,
                            'height': 40
                        }
                    },
                    // Puestos de Trabajo / Workstations (ITAM)
                    {
                        selector: 'node[type = "workstation"]',
                        style: {
                            'background-color': '#064e3b',
                            'border-color': '#10b981',
                            'shape': 'round-rectangle',
                            'border-width': 2,
                            'width': 28,
                            'height': 28,
                            'font-size': '9px'
                        }
                    },
                    // Hosts Genéricos
                    {
                        selector: 'node[type = "host"]',
                        style: {
                            'background-color': '#1e293b',
                            'border-color': '#64748b',
                            'width': 26,
                            'height': 26,
                            'font-size': '9px'
                        }
                    },
                    // Estado DOWN
                    {
                        selector: 'node[status = "down"]',
                        style: {
                            'border-color': '#ef4444',
                            'background-color': '#450a0a',
                            'color': '#fca5a5'
                        }
                    },
                    // Enlaces Base
                    {
                        selector: 'edge',
                        style: {
                            'width': 2,
                            'line-color': '#334155',
                            'target-arrow-color': '#334155',
                            'target-arrow-shape': 'triangle',
                            'curve-style': 'bezier',
                            'arrow-scale': 0.8,
                            'label': 'data(label)',
                            'font-size': '8px',
                            'font-family': 'monospace',
                            'color': '#38bdf8',
                            'text-rotation': 'autorotate',
                            'text-background-opacity': 0.85,
                            'text-background-color': '#051424',
                            'text-background-padding': 2,
                            'text-background-shape': 'roundrectangle'
                        }
                    },
                    {
                        selector: 'edge[status = "up"]',
                        style: {
                            'line-color': '#0d9488',
                            'target-arrow-color': '#0d9488'
                        }
                    },
                    {
                        selector: 'edge[status = "down"]',
                        style: {
                            'line-color': '#ef4444',
                            'target-arrow-color': '#ef4444',
                            'line-style': 'dashed'
                        }
                    },
                    {
                        selector: 'edge[link_type = "CDP"]',
                        style: {
                            'line-color': '#06b6d4',
                            'target-arrow-color': '#06b6d4',
                            'width': 2.5
                        }
                    },
                    {
                        selector: 'edge[link_type = "LLDP"]',
                        style: {
                            'line-color': '#a855f7',
                            'target-arrow-color': '#a855f7',
                            'width': 2.5
                        }
                    },
                    {
                        selector: 'edge[link_type = "ACCESS"]',
                        style: {
                            'line-color': '#10b981',
                            'target-arrow-color': '#10b981',
                            'line-style': 'dotted',
                            'width': 1.5
                        }
                    },
                    {
                        selector: 'edge[link_type = "WAN"]',
                        style: {
                            'line-color': '#f59e0b',
                            'target-arrow-color': '#f59e0b',
                            'width': 2.5
                        }
                    },
                    // Selección
                    {
                        selector: ':selected',
                        style: {
                            'border-color': '#facc15',
                            'border-width': 4,
                            'line-color': '#facc15',
                            'target-arrow-color': '#facc15'
                        }
                    }
                ],
                layout: {
                    name: 'breadthfirst',
                    directed: true,
                    roots: ['#' + (data.meta && data.meta.root_id ? data.meta.root_id : 'snmp_4')],
                    spacingFactor: 1.4,
                    padding: 40,
                    animate: false
                }
            });

            window._topologyRootId = data.meta && data.meta.root_id ? data.meta.root_id : 'snmp_4';
            setTimeout(() => {
                if (cy) {
                    cy.resize();
                    cy.fit(null, 30);
                }
            }, 150);

            // Manejo de eventos al hacer clic en nodos
            cy.on('tap', 'node', function (evt) {
                const node = evt.target;
                showNodeDetails(node.data());
            });

            cy.on('tap', function (evt) {
                if (evt.target === cy) {
                    resetNodeDetails();
                }
            });

        } catch (error) {
            console.error('Error cargando topología:', error);
        }
    }

    function showNodeDetails(data) {
        document.getElementById('node-details-empty').classList.add('hidden');
        document.getElementById('node-details-content').classList.remove('hidden');

        document.getElementById('detail-name').innerText = data.label || 'N/A';
        document.getElementById('detail-ip').innerText = data.ip || 'Sin IP';
        document.getElementById('detail-type').innerText = (data.type || 'Dispositivo').replace('_', ' ');
        document.getElementById('detail-vendor').innerText = data.vendor || 'N/A';
        document.getElementById('detail-model').innerText = data.model || 'N/A';
        document.getElementById('detail-site').innerText = data.site_name || 'Sede Central';

        const respContainer = document.getElementById('detail-responsable-container');
        const respEl = document.getElementById('detail-responsable');
        if (data.responsable && data.responsable.trim() !== '') {
            respEl.innerText = data.responsable;
            respContainer.classList.remove('hidden');
        } else {
            respContainer.classList.add('hidden');
        }

        const notesContainer = document.getElementById('detail-notes-container');
        const notesEl = document.getElementById('detail-notes');
        if (data.notes && data.notes.trim() !== '') {
            notesEl.innerText = data.notes;
            notesContainer.classList.remove('hidden');
        } else {
            notesContainer.classList.add('hidden');
        }

        const badge = document.getElementById('node-badge');
        if (data.status === 'up') {
            badge.innerText = 'OPERATIVO (UP)';
            badge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/30';
        } else {
            badge.innerText = 'INACCESIBLE (DOWN)';
            badge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-rose-950 text-rose-400 border border-rose-500/30';
        }

        const actionLink = document.getElementById('detail-action-link');
        const actionText = document.getElementById('detail-action-text');
        const actionIcon = document.getElementById('detail-action-icon');

        if (data.device_url) {
            actionLink.href = data.device_url;
            actionLink.classList.remove('hidden');
            if (data.source_module === 'inventory_asset') {
                actionText.innerText = 'Ver Ficha en ITAM';
                actionIcon.innerText = 'computer';
            } else if (data.source_module === 'snmp') {
                actionText.innerText = 'Ver Telemetría SNMP';
                actionIcon.innerText = 'radar';
            } else {
                actionText.innerText = 'Ver Ficha de Red';
                actionIcon.innerText = 'dns';
            }
        } else {
            actionLink.classList.add('hidden');
        }
    }

    function resetNodeDetails() {
        document.getElementById('node-details-empty').classList.remove('hidden');
        document.getElementById('node-details-content').classList.add('hidden');
        document.getElementById('detail-notes-container').classList.add('hidden');
        document.getElementById('detail-responsable-container').classList.add('hidden');
        const badge = document.getElementById('node-badge');
        badge.innerText = 'Seleccionar nodo';
        badge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-800 text-slate-300';
    }

    function changeLayout(layoutName) {
        if (!cy) return;
        const rootSelector = '#' + (window._topologyRootId || 'snmp_4');
        const opts = {
            name: layoutName,
            animate: true,
            animationDuration: 500,
            fit: true,
            padding: 40
        };
        if (layoutName === 'breadthfirst') {
            opts.directed = true;
            opts.roots = [rootSelector];
            opts.spacingFactor = 1.4;
        } else if (layoutName === 'cose') {
            opts.nodeRepulsion = 600000;
            opts.idealEdgeLength = 100;
            opts.gravity = 80;
        }
        cy.layout(opts).run();
    }

    window.addEventListener('resize', function () {
        if (cy) {
            cy.resize();
            cy.fit(null, 30);
        }
    });

    function cyZoom(factor) {
        if (!cy) return;
        cy.zoom({
            level: cy.zoom() * factor,
            renderedPosition: { x: cy.width() / 2, y: cy.height() / 2 }
        });
    }

    function cyFit() {
        if (!cy) return;
        cy.fit(null, 30);
    }

    function cyReset() {
        if (!cy) return;
        cy.reset();
        cy.fit(null, 30);
    }
</script>
@endsection
