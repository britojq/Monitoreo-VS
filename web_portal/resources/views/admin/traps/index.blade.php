@extends('layouts.admin')

@section('page_title', 'SNMP Traps')

@section('admin_content')
<div class="space-y-4">
    <!-- PESTAÑAS CONSOLA SNMP 360° -->
    <div class="flex flex-wrap items-center gap-2 border-b border-obsidian-border/80 pb-3">
        <a href="{{ route('admin.snmp.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.snmp.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">sensors</span>
            <span>Dispositivos & Métricas SNMP</span>
        </a>
        <a href="{{ route('admin.traps.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.traps.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">forward_to_inbox</span>
            <span>SNMP Traps (UDP 162)</span>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- ENCABEZADO Y ESTADO DE ESCUCHA PUSH                                        -->
    <!-- ========================================================================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 sm:px-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 backdrop-blur-md shadow-md">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-400 shrink-0">
                <span class="material-symbols-outlined text-lg">forward_to_inbox</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                        Receptor de SNMP Traps (Modelo Push)
                    </h2>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-cyan-950/80 text-cyan-300 border border-cyan-500/40" title="Escuchando en UDP 162 en tiempo real">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        UDP 162
                    </span>
                    @if(!auth()->user()->isAdmin() && !auth()->user()->hasPermission('traps.process'))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-950/80 text-amber-300 border border-amber-500/40">
                            <span class="material-symbols-outlined text-[11px]">visibility</span>
                            MODO CONSULTA
                        </span>
                    @endif
                </div>
                <p class="text-[10px] font-mono text-obsidian-muted">
                    Captura asíncrona inmediata de eventos, cambios de enlace (linkDown/linkUp) y alarmas de hardware.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto flex-wrap">
            <a href="{{ route('admin.traps.index') }}" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs">
                <span class="material-symbols-outlined text-sm">refresh</span>
                <span>Refrescar</span>
            </a>
        </div>
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
<div id="trap-modal" class="fixed inset-0 z-50 hidden bg-black/70 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-obsidian-panel border border-obsidian-border rounded-xl max-w-xl w-full p-4 shadow-xl">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-2 mb-3">
            <h3 class="text-xs font-bold text-white uppercase font-mono flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-sm">data_object</span>
                Detalle del SNMP Trap
            </h3>
            <button onclick="closeTrapModal()" class="text-obsidian-muted hover:text-white cursor-pointer">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
        <div id="trap-modal-content" class="space-y-3 font-mono text-xs">
            <div class="p-4 text-center text-obsidian-muted">Cargando...</div>
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
