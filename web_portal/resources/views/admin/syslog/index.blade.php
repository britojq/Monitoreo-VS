@extends('layouts.admin')

@section('page_title', 'Syslog Live')

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
                <span class="text-[10.5px] font-mono text-obsidian-muted">Tráfico &amp; Logs de Red</span>
            </div>
            <h1 class="text-xl font-black text-white tracking-wide flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-2xl">receipt_long</span>
                Consola Syslog Centralizada (Live-Tail)
                <span class="w-6 h-6 rounded-md bg-cyan-950/80 border border-cyan-500/40 text-cyan-400 inline-flex items-center justify-center cursor-help" title="Escuchando en UDP 514 (RFC 3164 / RFC 5424)">
                    <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                </span>
                @if(!auth()->user()->isAdmin())
                    <span class="w-6 h-6 rounded-md bg-amber-950/80 text-amber-300 border border-amber-500/40 inline-flex items-center justify-center cursor-help" title="Modo Consulta: Acceso de solo lectura">
                        <span class="material-symbols-outlined text-xs">visibility</span>
                    </span>
                @endif
            </h1>
            <p class="text-xs font-mono text-obsidian-muted">Recepción push de bitácoras de switches, routers Cisco, servidores y cortafuegos en tiempo real</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- BOTÓN GUÍA DE INTEGRACIÓN -->
            <button type="button" onclick="toggleSyslogGuide()" class="px-3 py-1.5 rounded-xl bg-cyan-950/40 border border-cyan-500/40 text-cyan-300 hover:bg-cyan-900/50 hover:text-white font-mono text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer shadow-xs" title="Ver Guía de Integración y Configuración de Equipos">
                <span class="material-symbols-outlined text-base">help</span>
                <span class="hidden sm:inline">Guía de Integración</span>
            </button>

            <!-- BOTÓN LIVE TAIL (AUTO-POLLING) -->
            <button type="button" id="btn-live-tail" onclick="toggleLiveTail()" class="px-2.5 py-1.5 rounded-xl bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping" id="live-indicator"></span>
                <span id="live-text">Live-Tail: ON</span>
            </button>
        </div>
    </div>

    <!-- PESTAÑAS TRÁFICO & LOGS DE RED (PANEL DE NAVEGACIÓN COMPACTO) -->
    <div class="flex flex-wrap items-center gap-1.5 border-b border-obsidian-border/80 pb-2.5">
        <a href="{{ route('admin.netflow.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.netflow.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Análisis de Flujos NetFlow v5 &amp; Top Talkers">
            <span class="material-symbols-outlined text-sm">swap_vert</span>
            <span>NetFlow &amp; Flujos</span>
        </a>
        <a href="{{ route('admin.syslog.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.syslog.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Servidor Syslog en Vivo UDP 514">
            <span class="material-symbols-outlined text-sm">receipt_long</span>
            <span>Syslog en Vivo (UDP 514)</span>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- GUÍA DE INTEGRACIÓN & ESTADO DEL COLECTOR UDP 514                         -->
    <!-- ========================================================================= -->
    <div id="panel-guia-syslog" class="p-4 rounded-2xl bg-obsidian-panel/90 border border-cyan-500/30 space-y-3.5 shadow-lg {{ $totalLogs > 0 ? 'hidden' : '' }}">
        <div class="flex items-start justify-between gap-3 border-b border-obsidian-border/70 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shrink-0">
                    <span class="material-symbols-outlined text-xl">receipt_long</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white uppercase font-mono tracking-wider flex items-center gap-2">
                        Guía de Integración: ¿Cómo enviar eventos Syslog desde tus equipos?
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted mt-0.5">
                        El servidor Syslog centralizado escucha eventos en tiempo real en el puerto UDP 514 estándar.
                    </p>
                </div>
            </div>
            @if($totalLogs > 0)
            <button type="button" onclick="toggleSyslogGuide()" class="text-obsidian-muted hover:text-white transition p-1 cursor-pointer" title="Cerrar Guía">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
            @endif
        </div>

        <!-- ESTADO TÉCNICO DEL RECEPTOR -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs font-mono">
            <div class="p-2.5 rounded-xl bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                <span class="text-obsidian-muted text-[10px] uppercase block">Puerto de Escucha:</span>
                <div class="font-bold text-cyan-300 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                    UDP 514 (Activo en segundo plano)
                </div>
            </div>
            <div class="p-2.5 rounded-xl bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                <span class="text-obsidian-muted text-[10px] uppercase block">Estándares Soportados:</span>
                <div class="font-bold text-white">
                    RFC 3164 (BSD) &amp; RFC 5424 (IETF)
                </div>
            </div>
            <div class="p-2.5 rounded-xl bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                <span class="text-obsidian-muted text-[10px] uppercase block">Transmisión en Vivo:</span>
                <div class="font-bold text-emerald-400">
                    Live-Tail Asíncrono (Auto-Scroll)
                </div>
            </div>
        </div>

        <!-- PESTAÑAS DE COMANDOS SEGÚN FABRICANTE -->
        <div class="space-y-2 pt-1 font-mono text-xs">
            <div class="text-[11px] font-bold text-cyan-400 uppercase tracking-wider flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm">terminal</span>
                Configuración en Equipos de Red:
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                <!-- SWITCH / ROUTER CISCO -->
                <div class="p-3 rounded-xl bg-[#030a14] border border-obsidian-border/80 space-y-2">
                    <div class="font-bold text-white flex items-center gap-1.5 text-[11px]">
                        <span class="material-symbols-outlined text-cyan-400 text-sm">router</span>
                        Switch / Router Cisco (IOS)
                    </div>
                    <p class="text-obsidian-muted text-[10.5px]">Ejecuta en modo de configuración global:</p>
                    <pre class="bg-black/60 p-2.5 rounded-lg border border-obsidian-border text-[10.5px] text-cyan-300 overflow-x-auto leading-relaxed"><code>configure terminal
logging host &lt;IP_SERVIDOR_MONITOREO&gt;
logging trap informational
logging facility local7
logging source-interface &lt;INTERFAZ&gt;
service timestamps log datetime msec
end
write memory</code></pre>
                </div>

                <!-- CORTAFUEGOS PFSENSE / OPNSENSE -->
                <div class="p-3 rounded-xl bg-[#030a14] border border-obsidian-border/80 space-y-2">
                    <div class="font-bold text-white flex items-center gap-1.5 text-[11px]">
                        <span class="material-symbols-outlined text-emerald-400 text-sm">shield</span>
                        Cortafuegos pfSense / OPNsense
                    </div>
                    <p class="text-obsidian-muted text-[10.5px]">Desde la interfaz gráfica de administración:</p>
                    <ol class="list-decimal list-inside text-obsidian-muted text-[10.5px] space-y-1 bg-black/60 p-2.5 rounded-lg border border-obsidian-border leading-relaxed">
                        <li>Ve a: <span class="text-slate-200">Status ➔ System Logs ➔ Settings</span>.</li>
                        <li>Marca: <b>Enable Remote Logging</b>.</li>
                        <li><b>Remote Log Servers:</b> <code class="text-emerald-300">&lt;IP_SERVIDOR_MONITOREO&gt;:514</code></li>
                        <li>Selecciona los eventos (Sistema, Firewall, DHCP).</li>
                        <li>Haz clic en <b>Save</b>.</li>
                    </ol>
                </div>

                <!-- SERVIDORES LINUX (RSYSLOG) -->
                <div class="p-3 rounded-xl bg-[#030a14] border border-obsidian-border/80 space-y-2">
                    <div class="font-bold text-white flex items-center gap-1.5 text-[11px]">
                        <span class="material-symbols-outlined text-amber-400 text-sm">dns</span>
                        Servidores GNU/Linux (rsyslog)
                    </div>
                    <p class="text-obsidian-muted text-[10.5px]">Añade al final de <code class="text-amber-300">/etc/rsyslog.conf</code>:</p>
                    <pre class="bg-black/60 p-2.5 rounded-lg border border-obsidian-border text-[10.5px] text-amber-300 overflow-x-auto leading-relaxed"><code>*.* @&lt;IP_SERVIDOR_MONITOREO&gt;:514

# Reinicia el servicio:
sudo systemctl restart rsyslog

# Prueba inmediata:
logger -n &lt;IP_SERVIDOR_MONITOREO&gt; -P 514 "Prueba de evento Syslog"</code></pre>
                </div>
            </div>
        </div>

        <div class="p-2.5 rounded-xl bg-cyan-950/20 border border-cyan-500/20 text-obsidian-muted text-[11px] font-mono flex items-center gap-2">
            <span class="material-symbols-outlined text-cyan-400 text-base shrink-0">info</span>
            <span>Cualquier evento de caída de interfaz, cambio de configuración o autenticación se desplegará instantáneamente en la bitácora inferior gracias al receptor Live-Tail.</span>
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
                        <td colspan="5" class="p-8 text-center text-obsidian-muted font-mono">
                            <span class="material-symbols-outlined text-4xl block mb-2 text-cyan-500/40">receipt_long</span>
                            <span class="text-white font-bold block mb-1">A la espera de eventos Syslog en UDP 514</span>
                            <span class="text-xs text-obsidian-muted block max-w-md mx-auto">
                                El colector se encuentra activo en segundo plano. Configura el reenvío de logs en tus switches, routers o servidores para comenzar a recibir eventos.
                            </span>
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

function toggleSyslogGuide() {
    const guide = document.getElementById('panel-guia-syslog');
    if (guide) {
        guide.classList.toggle('hidden');
    }
}

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
