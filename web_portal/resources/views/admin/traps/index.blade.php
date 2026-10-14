@extends('layouts.admin')

@section('page_title', 'Alertas SNMP (Eventos)')

@section('admin_content')
<div class="space-y-4">
    <!-- CABECERA -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-[10.5px] font-mono font-semibold group shadow-xs">
                    <span class="material-symbols-outlined text-xs group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-[10.5px] font-mono text-obsidian-muted">Telemetría de Red</span>
            </div>
            <h1 class="text-xl font-black text-white tracking-wide flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-2xl">forward_to_inbox</span>
                Receptor de Alertas SNMP (Eventos Push)
                <span class="w-6 h-6 rounded-md bg-cyan-950/80 border border-cyan-500/40 text-cyan-400 inline-flex items-center justify-center cursor-help" title="Escuchando en UDP 162 en tiempo real">
                    <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                </span>
                @if(!auth()->user()->isAdmin() && !auth()->user()->hasPermission('traps.process'))
                    <span class="w-6 h-6 rounded-md bg-amber-950/80 text-amber-300 border border-amber-500/40 inline-flex items-center justify-center cursor-help" title="Modo Consulta: Acceso de solo lectura">
                        <span class="material-symbols-outlined text-xs">visibility</span>
                    </span>
                @endif
            </h1>
            <p class="text-xs font-mono text-obsidian-muted">Captura asíncrona inmediata de eventos, cambios de enlace (linkDown/linkUp) y alarmas de hardware</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.traps.index') }}" class="p-2 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/50 transition cursor-pointer shadow-xs" title="Refrescar">
                <span class="material-symbols-outlined text-base">refresh</span>
            </a>
        </div>
    </div>

    <!-- PESTAÑAS RADAR & TELEMETRÍA DE RED (PANEL DE NAVEGACIÓN COMPACTO) -->
    <div class="flex flex-wrap items-center gap-1.5 border-b border-obsidian-border/80 pb-2.5">
        <a href="{{ route('admin.discovery.index', ['tab' => 'radar']) }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40" title="Auto-Discovery de Red y Detección Anti-Rogue">
            <span class="material-symbols-outlined text-sm">radar</span>
            <span>Auto-Discovery &amp; Anti-Rogue</span>
        </a>
        <a href="{{ route('admin.discovery.index', ['tab' => 'snmp']) }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40" title="Monitoreo y Telemetría SNMP">
            <span class="material-symbols-outlined text-sm">sensors</span>
            <span>Telemetría SNMP</span>
        </a>
        @if(!auth()->user() || auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('netradar.view')))
        <a href="{{ route('admin.netradar.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40" title="NET Radar (Tráfico y Hosts)">
            <span class="material-symbols-outlined text-sm">troubleshoot</span>
            <span>NET Radar (Tráfico &amp; Hosts)</span>
        </a>
        @endif
        <a href="{{ route('admin.traps.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer bg-cyan-500 text-black shadow-cyan-500/20" title="Alertas SNMP y Eventos UDP 162">
            <span class="material-symbols-outlined text-sm">forward_to_inbox</span>
            <span>Alertas SNMP (Eventos)</span>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- HUD: TARJETAS DE MÉTRICAS DE TRAPS                                         -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
        <!-- TOTAL TRAPS -->
        <a href="{{ route('admin.traps.index') }}" class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 hover:border-cyan-500/40 transition block {{ !request('severity') && request('processed') === null ? 'ring-1 ring-cyan-500/50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Total Recibidos</span>
                <span class="material-symbols-outlined text-sm text-cyan-400">inbox</span>
            </div>
            <div class="text-lg font-bold font-mono text-white mt-1">{{ number_format($totalTraps) }}</div>
            <div class="text-[9px] font-mono text-cyan-400/80 mt-0.5">Eventos push globales</div>
        </a>

        <!-- CRÍTICOS -->
        <a href="{{ route('admin.traps.index', ['severity' => 'critical']) }}" class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 hover:border-rose-500/40 transition block {{ request('severity') === 'critical' ? 'ring-1 ring-rose-500/50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Críticos / Emergencia</span>
                <span class="material-symbols-outlined text-sm text-rose-400">error</span>
            </div>
            <div class="text-lg font-bold font-mono text-rose-400 mt-1">{{ number_format($criticalTraps) }}</div>
            <div class="text-[9px] font-mono text-rose-400/80 mt-0.5">Caídas de enlace / Alarma</div>
        </a>

        <!-- ADVERTENCIAS -->
        <a href="{{ route('admin.traps.index', ['severity' => 'warning']) }}" class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 hover:border-amber-500/40 transition block {{ request('severity') === 'warning' ? 'ring-1 ring-amber-500/50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Advertencias</span>
                <span class="material-symbols-outlined text-sm text-amber-400">warning</span>
            </div>
            <div class="text-lg font-bold font-mono text-amber-400 mt-1">{{ number_format($warningTraps) }}</div>
            <div class="text-[9px] font-mono text-amber-400/80 mt-0.5">Reinicios / Auth fail</div>
        </a>

        <!-- PENDIENTES DE PROCESAR -->
        <a href="{{ route('admin.traps.index', ['processed' => '0']) }}" class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 hover:border-cyan-500/40 transition block {{ request('processed') === '0' ? 'ring-1 ring-cyan-500/50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Sin Procesar</span>
                <span class="material-symbols-outlined text-sm text-cyan-400">pending_actions</span>
            </div>
            <div class="text-lg font-bold font-mono text-cyan-300 mt-1">{{ number_format($unprocessedTraps) }}</div>
            <div class="text-[9px] font-mono text-cyan-400/80 mt-0.5">Requieren revisión</div>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- BARRA DE FILTROS Y BÚSQUEDA                                               -->
    <!-- ========================================================================= -->
    <div class="p-3 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90">
        <form method="GET" action="{{ route('admin.traps.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-2">
            <!-- BÚSQUEDA TEXTUAL -->
            <div class="sm:col-span-4 relative">
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por IP, OID, tipo o varbind..." class="w-full pl-8 pr-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white placeholder-obsidian-muted focus:outline-none focus:border-cyan-500">
                <span class="material-symbols-outlined absolute left-2.5 top-2 text-obsidian-muted text-sm">search</span>
            </div>

            <!-- SEVERIDAD -->
            <div class="sm:col-span-3">
                <select name="severity" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                    <option value="">Todas las severidades</option>
                    <option value="emergency" {{ $severityFilter === 'emergency' ? 'selected' : '' }}>🚨 Emergency</option>
                    <option value="critical" {{ $severityFilter === 'critical' ? 'selected' : '' }}>🔴 Critical</option>
                    <option value="warning" {{ $severityFilter === 'warning' ? 'selected' : '' }}>⚠️ Warning</option>
                    <option value="info" {{ $severityFilter === 'info' ? 'selected' : '' }}>ℹ️ Info</option>
                </select>
            </div>

            <!-- ESTADO PROCESADO -->
            <div class="sm:col-span-2">
                <select name="processed" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                    <option value="">Todos los estados</option>
                    <option value="0" {{ $processedFilter === '0' ? 'selected' : '' }}>Pendientes</option>
                    <option value="1" {{ $processedFilter === '1' ? 'selected' : '' }}>Procesados</option>
                </select>
            </div>

            <!-- DISPOSITIVO -->
            <div class="sm:col-span-2">
                <select name="device_id" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                    <option value="">Todos los equipos</option>
                    @foreach($devices as $dev)
                        <option value="{{ $dev->id }}" {{ $deviceFilter == $dev->id ? 'selected' : '' }}>{{ $dev->name }} ({{ $dev->ip_address }})</option>
                    @endforeach
                </select>
            </div>

            <!-- BOTÓN FILTRAR -->
            <div class="sm:col-span-1 flex gap-1">
                <button type="submit" class="w-full py-1.5 px-2 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center justify-center">
                    <span class="material-symbols-outlined text-sm">filter_alt</span>
                </button>
            </div>
        </form>
    </div>

    <!-- ========================================================================= -->
    <!-- TABLA DE SNMP TRAPS RECIBIDOS                                             -->
    <!-- ========================================================================= -->
    <div class="rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 overflow-hidden shadow-md">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono border-collapse">
                <thead>
                    <tr class="border-b border-obsidian-border bg-obsidian-bg/60 text-obsidian-muted uppercase text-[10px] tracking-wider">
                        <th class="p-3">Severidad</th>
                        <th class="p-3">Dispositivo / Origen</th>
                        <th class="p-3">Tipo de Trap</th>
                        <th class="p-3">OID Especificado</th>
                        <th class="p-3">VarBinds</th>
                        <th class="p-3">Recepción</th>
                        <th class="p-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/40">
                    @forelse($traps as $trap)
                    <tr class="hover:bg-cyan-950/20 transition">
                        <!-- SEVERIDAD -->
                        <td class="p-3">
                            @if(in_array($trap->severity, ['critical', 'emergency']))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400 animate-pulse"></span>
                                    {{ strtoupper($trap->severity) }}
                                </span>
                            @elseif($trap->severity === 'warning')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                    WARNING
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-400 border border-blue-500/30">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                                    INFO
                                </span>
                            @endif
                        </td>

                        <!-- ORIGEN / DISPOSITIVO -->
                        <td class="p-3">
                            <div class="font-bold text-white flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm text-cyan-400">router</span>
                                {{ $trap->source_ip }}
                            </div>
                            @if($trap->device)
                                <div class="text-[10px] text-obsidian-muted">{{ $trap->device->name }}</div>
                            @endif
                        </td>

                        <!-- TIPO DE TRAP -->
                        <td class="p-3">
                            <span class="font-bold text-cyan-300">{{ $trap->trap_type ?: 'genericTrap' }}</span>
                        </td>

                        <!-- OID -->
                        <td class="p-3 text-obsidian-muted text-[11px]" title="{{ $trap->trap_oid }}">
                            <code class="px-1.5 py-0.5 rounded bg-obsidian-bg border border-obsidian-border/60 text-cyan-400/90">
                                {{ Str::limit($trap->trap_oid, 28) }}
                            </code>
                        </td>

                        <!-- VARBINDS RESUMEN -->
                        <td class="p-3">
                            @php
                                $vbs = is_array($trap->varbinds) ? $trap->varbinds : (json_decode($trap->varbinds, true) ?: []);
                                $vbCount = count($vbs);
                            @endphp
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-obsidian-bg border border-obsidian-border text-[10px] text-obsidian-muted">
                                <span class="material-symbols-outlined text-xs text-cyan-400">data_object</span>
                                {{ $vbCount }} variable(s)
                            </span>
                        </td>

                        <!-- FECHA RECEPCIÓN -->
                        <td class="p-3 text-obsidian-muted text-[11px]">
                            <div>{{ $trap->received_at ? $trap->received_at->format('Y-m-d H:i:s') : 'N/A' }}</div>
                            <div class="text-[9px] text-cyan-400/70">{{ $trap->received_at ? $trap->received_at->diffForHumans() : '' }}</div>
                        </td>

                        <!-- ACCIONES -->
                        <td class="p-3 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button type="button" onclick="inspectTrap({{ $trap->id }})" class="p-1 rounded bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:text-white hover:border-cyan-500 transition" title="Inspeccionar VarBinds y detalles">
                                    <span class="material-symbols-outlined text-sm">visibility</span>
                                </button>

                                @if(!$trap->processed && (auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('traps.process'))))
                                <form action="{{ route('admin.traps.process', $trap->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="p-1 rounded bg-obsidian-panel border border-obsidian-border text-emerald-400 hover:text-white hover:border-emerald-500 transition" title="Marcar como procesado">
                                        <span class="material-symbols-outlined text-sm">check_circle</span>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-obsidian-muted">
                            <span class="material-symbols-outlined text-4xl block mb-2 text-cyan-500/40">inbox</span>
                            No hay registros de SNMP Traps recibidos con los filtros actuales.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($traps->hasPages())
        <div class="p-3 border-t border-obsidian-border bg-obsidian-bg/40">
            {{ $traps->links() }}
        </div>
        @endif
    </div>
</div>

<!-- MODAL PARA INSPECCIÓN DE TRAP -->
<div id="trap-modal" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4">
    <div class="bg-obsidian-panel border border-obsidian-border rounded-2xl max-w-xl w-full shadow-2xl flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden font-sans">
        <!-- CABECERA FIJA -->
        <div class="p-4 px-5 flex items-center justify-between border-b border-obsidian-border shrink-0 bg-obsidian-panel/90">
            <h3 class="text-xs font-bold text-white uppercase font-mono flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-sm">data_object</span>
                Detalle del SNMP Trap
            </h3>
            <button onclick="closeTrapModal()" class="text-obsidian-muted hover:text-white transition p-1 cursor-pointer" title="Cerrar ventana">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>
        <!-- CUERPO DESLIZABLE -->
        <div id="trap-modal-content" class="p-5 overflow-y-auto flex-1 custom-scroll space-y-3 font-mono text-xs">
            <div class="p-4 text-center text-obsidian-muted">Cargando...</div>
        </div>
        <!-- PIE FIJO -->
        <div class="p-3 px-5 border-t border-obsidian-border flex items-center justify-end shrink-0 bg-obsidian-panel/80">
            <button type="button" onclick="closeTrapModal()" class="px-4 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white hover:bg-obsidian-border transition text-xs font-mono cursor-pointer">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
function inspectTrap(id) {
    const modal = document.getElementById('trap-modal');
    const content = document.getElementById('trap-modal-content');
    modal.classList.remove('hidden');
    content.innerHTML = '<div class="p-4 text-center text-obsidian-muted">Cargando detalles del trap...</div>';

    fetch(`/admin/traps/${id}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.trap) {
                content.innerHTML = '<div class="p-4 text-rose-400">Error al cargar datos.</div>';
                return;
            }
            const t = data.trap;
            let vbsHtml = '';
            let parsedVbs = t.varbinds;
            if (typeof parsedVbs === 'string') {
                try { parsedVbs = JSON.parse(parsedVbs); } catch(e) { parsedVbs = []; }
            }
            if (Array.isArray(parsedVbs) && parsedVbs.length > 0) {
                vbsHtml = '<div class="space-y-1 mt-2">';
                parsedVbs.forEach(vb => {
                    vbsHtml += `<div class="p-1.5 rounded bg-obsidian-bg border border-obsidian-border/60 text-[11px] flex items-center justify-between">
                        <span class="text-cyan-400 font-bold">${vb.oid || 'OID'}</span>
                        <span class="text-white">${vb.value || 'N/A'}</span>
                    </div>`;
                });
                vbsHtml += '</div>';
            } else {
                vbsHtml = '<div class="text-[11px] text-obsidian-muted italic">Sin varbinds adicionales</div>';
            }

            content.innerHTML = `
                <div class="grid grid-cols-2 gap-2 text-[11px]">
                    <div><span class="text-obsidian-muted">Origen:</span> <span class="text-white font-bold">${t.source_ip}</span></div>
                    <div><span class="text-obsidian-muted">Tipo:</span> <span class="text-cyan-300 font-bold">${t.trap_type || 'generic'}</span></div>
                    <div><span class="text-obsidian-muted">Severidad:</span> <span class="text-white font-bold">${t.severity.toUpperCase()}</span></div>
                    <div><span class="text-obsidian-muted">Recibido:</span> <span class="text-white">${t.received_at || 'N/A'}</span></div>
                </div>
                <div class="text-[11px] mt-2">
                    <div class="text-obsidian-muted">Trap OID:</div>
                    <code class="block p-1.5 rounded bg-obsidian-bg border border-obsidian-border text-cyan-400 break-all mt-0.5">${t.trap_oid}</code>
                </div>
                <div class="mt-2">
                    <div class="text-obsidian-muted text-[11px]">Variables Vinculadas (VarBinds):</div>
                    ${vbsHtml}
                </div>
            `;
        })
        .catch(err => {
            content.innerHTML = '<div class="p-4 text-rose-400">Error de comunicación.</div>';
        });
}

function closeTrapModal() {
    document.getElementById('trap-modal').classList.add('hidden');
}
</script>
@endsection
