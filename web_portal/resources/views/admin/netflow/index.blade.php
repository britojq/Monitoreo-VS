@extends('layouts.admin')

@section('page_title', 'NetFlow & Tráfico')

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
    <!-- ENCABEZADO Y ESTADO DEL RECOLECTOR NETFLOW                                -->
    <!-- ========================================================================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 sm:px-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 backdrop-blur-md shadow-md">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-400 shrink-0">
                <span class="material-symbols-outlined text-lg">swap_vert</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                        Análisis de Flujos NetFlow v5 & Top Talkers
                    </h2>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-cyan-950/80 text-cyan-300 border border-cyan-500/40" title="Escuchando en UDP 2055 con agregación por minuto">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        UDP 2055
                    </span>
                    @if(!auth()->user()->isAdmin())
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-950/80 text-amber-300 border border-amber-500/40">
                            <span class="material-symbols-outlined text-[11px]">visibility</span>
                            MODO CONSULTA
                        </span>
                    @endif
                </div>
                <p class="text-[10px] font-mono text-obsidian-muted">
                    Visibilidad profunda de consumo de ancho de banda, hosts dominantes y protocolos en la red corporativa.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto flex-wrap">
            <a href="{{ route('admin.netflow.index') }}" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs">
                <span class="material-symbols-outlined text-sm">refresh</span>
                <span>Refrescar</span>
            </a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- HUD: TARJETAS DE MÉTRICAS DE TRÁFICO                                      -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
        <!-- TOTAL FLUJOS -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Total Flujos</span>
                <span class="material-symbols-outlined text-sm text-cyan-400">traffic</span>
            </div>
            <div class="text-lg font-bold font-mono text-white mt-1">{{ number_format($totalFlows) }}</div>
            <div class="text-[9px] font-mono text-cyan-400/80 mt-0.5">Ventanas agregadas (1m)</div>
        </div>

        <!-- TOTAL BYTES -->
        @php
            $gb = $totalBytes / 1073741824;
            $mb = $totalBytes / 1048576;
            $trafficStr = $gb >= 1 ? number_format($gb, 2) . ' GB' : number_format($mb, 2) . ' MB';
        @endphp
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Tráfico Total</span>
                <span class="material-symbols-outlined text-sm text-emerald-400">arrow_circle_down</span>
            </div>
            <div class="text-lg font-bold font-mono text-emerald-400 mt-1">{{ $trafficStr }}</div>
            <div class="text-[9px] font-mono text-emerald-400/80 mt-0.5">Volumen transferido</div>
        </div>

        <!-- TOTAL PAQUETES -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Paquetes</span>
                <span class="material-symbols-outlined text-sm text-cyan-400">stacked_line_chart</span>
            </div>
            <div class="text-lg font-bold font-mono text-white mt-1">{{ number_format($totalPackets) }}</div>
            <div class="text-[9px] font-mono text-cyan-400/80 mt-0.5">Datagramas analizados</div>
        </div>

        <!-- EXPORTERS ACTIVOS -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Routers Exporters</span>
                <span class="material-symbols-outlined text-sm text-cyan-400">router</span>
            </div>
            <div class="text-lg font-bold font-mono text-cyan-300 mt-1">{{ number_format($activeExporters) }}</div>
            <div class="text-[9px] font-mono text-cyan-400/80 mt-0.5">Fuentes de flujo activas</div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TOP TALKERS (RANKINGS VISUALES)                                           -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <!-- TOP SOURCES -->
        <div class="p-3 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-2 mb-2">
                    <span class="text-xs font-bold text-white uppercase font-mono flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-cyan-400 text-sm">upload</span>
                        Top Emisores (Src IP)
                    </span>
                    <span class="text-[10px] font-mono text-obsidian-muted">5 min</span>
                </div>
                <div class="space-y-2 mt-2">
                    @forelse($topSources as $src)
                    <div>
                        <div class="flex items-center justify-between text-[11px] font-mono mb-1">
                            <span class="text-white font-bold">{{ $src->rank_value }}</span>
                            <span class="text-cyan-300">{{ $src->formatted_bytes }} ({{ $src->percentage }}%)</span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-obsidian-bg overflow-hidden">
                            <div class="h-full rounded-full bg-cyan-400" style="width: {{ min($src->percentage, 100) }}%"></div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-6 text-obsidian-muted text-xs font-mono">
                        Sin datos en la última ventana
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- TOP DESTINATIONS -->
        <div class="p-3 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-2 mb-2">
                    <span class="text-xs font-bold text-white uppercase font-mono flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-emerald-400 text-sm">download</span>
                        Top Receptores (Dst IP)
                    </span>
                    <span class="text-[10px] font-mono text-obsidian-muted">5 min</span>
                </div>
                <div class="space-y-2 mt-2">
                    @forelse($topDestinations as $dst)
                    <div>
                        <div class="flex items-center justify-between text-[11px] font-mono mb-1">
                            <span class="text-white font-bold">{{ $dst->rank_value }}</span>
                            <span class="text-emerald-300">{{ $dst->formatted_bytes }} ({{ $dst->percentage }}%)</span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-obsidian-bg overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-400" style="width: {{ min($dst->percentage, 100) }}%"></div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-6 text-obsidian-muted text-xs font-mono">
                        Sin datos en la última ventana
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- TOP PROTOCOLS -->
        <div class="p-3 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-2 mb-2">
                    <span class="text-xs font-bold text-white uppercase font-mono flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-amber-400 text-sm">pie_chart</span>
                        Protocolos
                    </span>
                    <span class="text-[10px] font-mono text-obsidian-muted">5 min</span>
                </div>
                <div class="space-y-2 mt-2">
                    @forelse($topProtocols as $proto)
                    <div>
                        <div class="flex items-center justify-between text-[11px] font-mono mb-1">
                            <span class="text-white font-bold">{{ $proto->rank_value }}</span>
                            <span class="text-amber-300">{{ $proto->formatted_bytes }} ({{ $proto->percentage }}%)</span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-obsidian-bg overflow-hidden">
                            <div class="h-full rounded-full bg-amber-400" style="width: {{ min($proto->percentage, 100) }}%"></div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-6 text-obsidian-muted text-xs font-mono">
                        Sin datos en la última ventana
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- FILTROS Y BÚSQUEDA DE FLUJOS                                              -->
    <!-- ========================================================================= -->
    <div class="p-3 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90">
        <form method="GET" action="{{ route('admin.netflow.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-2">
            <!-- BUSCADOR -->
            <div class="sm:col-span-5 relative">
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por IP o puerto origen/destino..." class="w-full pl-8 pr-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white placeholder-obsidian-muted focus:outline-none focus:border-cyan-500">
                <span class="material-symbols-outlined absolute left-2.5 top-2 text-obsidian-muted text-sm">search</span>
            </div>

            <!-- EXPORTER -->
            <div class="sm:col-span-3">
                <select name="exporter_ip" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                    <option value="">Todos los exporters</option>
                    @foreach($exporters as $exp)
                        <option value="{{ $exp }}" {{ $exporterFilter === $exp ? 'selected' : '' }}>{{ $exp }}</option>
                    @endforeach
                </select>
            </div>

            <!-- PROTOCOLO -->
            <div class="sm:col-span-3">
                <select name="protocol" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                    <option value="">Todos los protocolos</option>
                    <option value="6" {{ $protocolFilter === '6' ? 'selected' : '' }}>TCP (6)</option>
                    <option value="17" {{ $protocolFilter === '17' ? 'selected' : '' }}>UDP (17)</option>
                    <option value="1" {{ $protocolFilter === '1' ? 'selected' : '' }}>ICMP (1)</option>
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
    <!-- TABLA DE FLUJOS RECIENTES AGREGADOS                                       -->
    <!-- ========================================================================= -->
    <div class="rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 overflow-hidden shadow-md">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono border-collapse">
                <thead>
                    <tr class="border-b border-obsidian-border bg-obsidian-bg/60 text-obsidian-muted uppercase text-[10px] tracking-wider">
                        <th class="p-3">Exporter</th>
                        <th class="p-3">Origen (IP:Puerto)</th>
                        <th class="p-3">Destino (IP:Puerto)</th>
                        <th class="p-3">Proto</th>
                        <th class="p-3 text-right">Bytes</th>
                        <th class="p-3 text-right">Paquetes</th>
                        <th class="p-3">Ventana Temporal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/40">
                    @forelse($records as $rec)
                    <tr class="hover:bg-cyan-950/20 transition">
                        <!-- EXPORTER -->
                        <td class="p-3 text-obsidian-muted">
                            <span class="inline-flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs text-cyan-400">router</span>
                                {{ $rec->exporter_ip }}
                            </span>
                        </td>

                        <!-- ORIGEN -->
                        <td class="p-3 font-bold text-white">
                            {{ $rec->src_ip }}<span class="text-cyan-400">:{{ $rec->src_port }}</span>
                        </td>

                        <!-- DESTINO -->
                        <td class="p-3 font-bold text-slate-200">
                            {{ $rec->dst_ip }}<span class="text-emerald-400">:{{ $rec->dst_port }}</span>
                        </td>

                        <!-- PROTOCOLO -->
                        <td class="p-3">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-obsidian-bg border border-obsidian-border text-cyan-300">
                                {{ $rec->protocol_name }}
                            </span>
                        </td>

                        <!-- BYTES -->
                        <td class="p-3 text-right font-bold text-white">
                            @if($rec->bytes >= 1048576)
                                {{ number_format($rec->bytes / 1048576, 2) }} MB
                            @elseif($rec->bytes >= 1024)
                                {{ number_format($rec->bytes / 1024, 2) }} KB
                            @else
                                {{ $rec->bytes }} B
                            @endif
                        </td>

                        <!-- PAQUETES -->
                        <td class="p-3 text-right text-obsidian-muted">
                            {{ number_format($rec->packets) }}
                        </td>

                        <!-- VENTANA -->
                        <td class="p-3 text-[10px] text-obsidian-muted">
                            {{ $rec->window_start ? $rec->window_start->format('H:i:s') : '' }} - {{ $rec->window_end ? $rec->window_end->format('H:i:s d/m') : '' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-obsidian-muted">
                            <span class="material-symbols-outlined text-4xl block mb-2 text-cyan-500/40">swap_vert</span>
                            No hay registros de flujos NetFlow con los filtros aplicados.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
        <div class="p-3 border-t border-obsidian-border bg-obsidian-bg/40">
            {{ $records->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
