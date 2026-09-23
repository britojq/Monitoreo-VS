@extends('layouts.admin')

@section('page_title', 'Syslog Live')

@section('admin_content')
<div class="space-y-4">
    <!-- PESTAÑAS TRÁFICO & LOGS DE RED -->
    <div class="flex flex-wrap items-center gap-2 border-b border-obsidian-border/80 pb-3">
        <a href="{{ route('admin.netflow.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.netflow.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">swap_vert</span>
            <span>NetFlow & Flujos</span>
        </a>
        <a href="{{ route('admin.syslog.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.syslog.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">receipt_long</span>
            <span>Syslog en Vivo (UDP 514)</span>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- ENCABEZADO Y ESTADO LIVE                                                  -->
    <!-- ========================================================================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 sm:px-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 backdrop-blur-md shadow-md">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-400 shrink-0">
                <span class="material-symbols-outlined text-lg">receipt_long</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                        Consola Syslog Centralizada (Live-Tail)
                    </h2>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-cyan-950/80 text-cyan-300 border border-cyan-500/40" title="Escuchando en UDP 514 (RFC 3164 / RFC 5424)">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        UDP 514
                    </span>
                    @if(!auth()->user()->isAdmin())
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-950/80 text-amber-300 border border-amber-500/40">
                            <span class="material-symbols-outlined text-[11px]">visibility</span>
                            MODO CONSULTA
                        </span>
                    @endif
                </div>
                <p class="text-[10px] font-mono text-obsidian-muted">
                    Recepción push de bitácoras de switches, routers Cisco, servidores y firewalls en tiempo real.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto flex-wrap">
            <!-- BOTÓN LIVE TAIL (AUTO-POLLING) -->
            <button type="button" id="btn-live-tail" onclick="toggleLiveTail()" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping" id="live-indicator"></span>
                <span id="live-text">Live-Tail: ON</span>
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- HUD: TARJETAS DE MÉTRICAS SYSLOG                                          -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
        <!-- TOTAL LOGS -->
        <a href="{{ route('admin.syslog.index') }}" class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 hover:border-cyan-500/40 transition block {{ !request('severity') ? 'ring-1 ring-cyan-500/50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Total Mensajes</span>
                <span class="material-symbols-outlined text-sm text-cyan-400">subject</span>
            </div>
            <div class="text-lg font-bold font-mono text-white mt-1">{{ number_format($totalLogs) }}</div>
            <div class="text-[9px] font-mono text-cyan-400/80 mt-0.5">Eventos en base de datos</div>
        </a>

        <!-- CRÍTICOS / ALERTA -->
        <a href="{{ route('admin.syslog.index', ['severity' => '2']) }}" class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 hover:border-rose-500/40 transition block {{ request('severity') === '2' ? 'ring-1 ring-rose-500/50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Críticos / Alerta</span>
                <span class="material-symbols-outlined text-sm text-rose-400">gpp_maybe</span>
            </div>
            <div class="text-lg font-bold font-mono text-rose-400 mt-1">{{ number_format($criticalLogs) }}</div>
            <div class="text-[9px] font-mono text-rose-400/80 mt-0.5">Nivel 0 - 2 (Emerg/Crit)</div>
        </a>

        <!-- ERRORES -->
        <a href="{{ route('admin.syslog.index', ['severity' => '3']) }}" class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 hover:border-amber-500/40 transition block {{ request('severity') === '3' ? 'ring-1 ring-amber-500/50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Errores</span>
                <span class="material-symbols-outlined text-sm text-amber-400">warning</span>
            </div>
            <div class="text-lg font-bold font-mono text-amber-400 mt-1">{{ number_format($errorLogs) }}</div>
            <div class="text-[9px] font-mono text-amber-400/80 mt-0.5">Nivel 3 (Error)</div>
        </a>

        <!-- FUENTES ACTIVAS -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 block">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Equipos Emisores</span>
                <span class="material-symbols-outlined text-sm text-cyan-400">hub</span>
            </div>
            <div class="text-lg font-bold font-mono text-cyan-300 mt-1">{{ number_format($sourcesCount) }}</div>
            <div class="text-[9px] font-mono text-cyan-400/80 mt-0.5">IPs distintas capturadas</div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- FILTROS Y BÚSQUEDA                                                        -->
    <!-- ========================================================================= -->
    <div class="p-3 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90">
        <form method="GET" action="{{ route('admin.syslog.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-2">
            <!-- BUSCADOR -->
            <div class="sm:col-span-4 relative">
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar texto, programa o IP..." class="w-full pl-8 pr-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white placeholder-obsidian-muted focus:outline-none focus:border-cyan-500">
                <span class="material-symbols-outlined absolute left-2.5 top-2 text-obsidian-muted text-sm">search</span>
            </div>

            <!-- SEVERIDAD -->
            <div class="sm:col-span-3">
                <select name="severity" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                    <option value="">Todas las severidades</option>
                    <option value="0" {{ $severityFilter === '0' ? 'selected' : '' }}>🚨 0 - Emergency</option>
                    <option value="1" {{ $severityFilter === '1' ? 'selected' : '' }}>🚨 1 - Alert</option>
                    <option value="2" {{ $severityFilter === '2' ? 'selected' : '' }}>🔴 2 - Critical</option>
                    <option value="3" {{ $severityFilter === '3' ? 'selected' : '' }}>⚠️ 3 - Error</option>
                    <option value="4" {{ $severityFilter === '4' ? 'selected' : '' }}>🟡 4 - Warning</option>
                    <option value="5" {{ $severityFilter === '5' ? 'selected' : '' }}>ℹ️ 5 - Notice</option>
                    <option value="6" {{ $severityFilter === '6' ? 'selected' : '' }}>ℹ️ 6 - Informational</option>
                    <option value="7" {{ $severityFilter === '7' ? 'selected' : '' }}>⚙️ 7 - Debug</option>
                </select>
            </div>

            <!-- PROGRAMA / DAEMON -->
            <div class="sm:col-span-2">
                <select name="program" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                    <option value="">Todos los programas</option>
                    @foreach($programs as $prog)
                        <option value="{{ $prog }}" {{ $programFilter === $prog ? 'selected' : '' }}>{{ $prog }}</option>
                    @endforeach
                </select>
            </div>

            <!-- IP ORIGEN -->
            <div class="sm:col-span-2">
                <select name="source_ip" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                    <option value="">Todas las fuentes</option>
                    @foreach($sources as $src)
                        <option value="{{ $src }}" {{ $sourceFilter === $src ? 'selected' : '' }}>{{ $src }}</option>
                    @endforeach
                </select>
            </div>

            <!-- BOTÓN FILTRAR -->
            <div class="sm:col-span-1">
                <button type="submit" class="w-full py-1.5 px-2 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center justify-center">
                    <span class="material-symbols-outlined text-sm">filter_alt</span>
                </button>
            </div>
        </form>
    </div>

    <!-- ========================================================================= -->
    <!-- CONSOLA LIVE SYSLOG (TERMINAL STYLE)                                       -->
    <!-- ========================================================================= -->
    <div class="rounded-xl bg-obsidian-panel/95 border border-obsidian-border/90 overflow-hidden shadow-lg">
        <div class="flex items-center justify-between px-3 py-2 bg-obsidian-bg/80 border-b border-obsidian-border text-[11px] font-mono text-obsidian-muted">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-sm">terminal</span>
                <span class="text-white font-bold">Bitácora en Vivo</span>
                <span class="text-obsidian-muted">| Mostrando últimos eventos</span>
            </div>
            <div class="text-[10px] text-cyan-400/80 font-mono">
                Presione <kbd class="px-1 py-0.5 bg-obsidian-panel rounded border border-obsidian-border text-white">Espacio</kbd> para pausar autoscroll
            </div>
        </div>

        <div class="overflow-x-auto max-h-[550px] overflow-y-auto" id="syslog-container">
            <table class="w-full text-left text-xs font-mono border-collapse">
                <thead class="sticky top-0 bg-obsidian-bg z-10">
                    <tr class="border-b border-obsidian-border text-obsidian-muted uppercase text-[10px]">
                        <th class="p-2.5 w-24">Hora</th>
                        <th class="p-2.5 w-24">Severidad</th>
                        <th class="p-2.5 w-32">Origen</th>
                        <th class="p-2.5 w-32">Programa</th>
                        <th class="p-2.5">Mensaje</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/30" id="syslog-tbody">
                    @forelse($events as $event)
                    <tr class="hover:bg-cyan-950/20 transition syslog-row" data-id="{{ $event->id }}">
                        <td class="p-2 text-[10px] text-obsidian-muted whitespace-nowrap">
                            {{ $event->received_at ? $event->received_at->format('H:i:s d/m') : 'N/A' }}
                        </td>
                        <td class="p-2 whitespace-nowrap">
                            <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold border {{ $event->severity_color }}">
                                {{ $event->severity_label }}
                            </span>
                        </td>
                        <td class="p-2 text-white font-bold whitespace-nowrap">
                            {{ $event->source_ip }}
                        </td>
                        <td class="p-2 text-cyan-300 font-bold whitespace-nowrap">
                            {{ $event->program ?: 'syslog' }}
                        </td>
                        <td class="p-2 text-obsidian-muted text-[11px] font-mono break-all leading-snug">
                            <span class="text-slate-200">{{ $event->message }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr id="empty-row">
                        <td colspan="5" class="p-8 text-center text-obsidian-muted">
                            <span class="material-symbols-outlined text-4xl block mb-2 text-cyan-500/40">receipt_long</span>
                            No hay registros de eventos Syslog con los filtros aplicados.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($events->hasPages())
        <div class="p-3 border-t border-obsidian-border bg-obsidian-bg/40">
            {{ $events->links() }}
        </div>
        @endif
    </div>
</div>

<script>
let liveTailActive = true;
let pollTimer = null;
let highestId = {{ $events->isNotEmpty() ? $events->first()->id : 0 }};

function toggleLiveTail() {
    liveTailActive = !liveTailActive;
    const ind = document.getElementById('live-indicator');
    const txt = document.getElementById('live-text');
    if (liveTailActive) {
        ind.className = 'w-2 h-2 rounded-full bg-emerald-400 animate-ping';
        txt.textContent = 'Live-Tail: ON';
        startPolling();
    } else {
        ind.className = 'w-2 h-2 rounded-full bg-slate-500';
        txt.textContent = 'Live-Tail: PAUSADO';
        clearInterval(pollTimer);
    }
}

function startPolling() {
    clearInterval(pollTimer);
    pollTimer = setInterval(fetchNewLogs, 3000);
}

function fetchNewLogs() {
    if (!liveTailActive) return;

    fetch(`/admin/syslog/live?last_id=${highestId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.events || data.events.length === 0) return;

            const tbody = document.getElementById('syslog-tbody');
            const emptyRow = document.getElementById('empty-row');
            if (emptyRow) emptyRow.remove();

            data.events.forEach(ev => {
                if (ev.id > highestId) highestId = ev.id;
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-cyan-950/20 transition syslog-row bg-cyan-950/30';
                tr.dataset.id = ev.id;
                tr.innerHTML = `
                    <td class="p-2 text-[10px] text-obsidian-muted whitespace-nowrap">${ev.received_at}</td>
                    <td class="p-2 whitespace-nowrap">
                        <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold border ${ev.severity_color}">
                            ${ev.severity_label}
                        </span>
                    </td>
                    <td class="p-2 text-white font-bold whitespace-nowrap">${ev.source_ip}</td>
                    <td class="p-2 text-cyan-300 font-bold whitespace-nowrap">${ev.program}</td>
                    <td class="p-2 text-obsidian-muted text-[11px] font-mono break-all leading-snug">
                        <span class="text-slate-200">${ev.message}</span>
                    </td>
                `;
                tbody.insertBefore(tr, tbody.firstChild);

                // Quitar highlight después de 2s
                setTimeout(() => {
                    tr.classList.remove('bg-cyan-950/30');
                }, 2000);
            });
        })
        .catch(err => console.debug('Live-tail poll error:', err));
}

document.addEventListener('DOMContentLoaded', () => {
    startPolling();
});
</script>
@endsection
