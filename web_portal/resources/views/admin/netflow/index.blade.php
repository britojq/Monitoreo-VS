@extends('layouts.admin')

@section('page_title', 'NetFlow & Tráfico')

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
                <span class="material-symbols-outlined text-cyan-400 text-2xl">swap_vert</span>
                Análisis de Flujos NetFlow v5 &amp; Top Talkers
                <span class="w-6 h-6 rounded-md bg-cyan-950/80 border border-cyan-500/40 text-cyan-400 inline-flex items-center justify-center cursor-help" title="Escuchando en UDP 2055 con agregación por minuto">
                    <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                </span>
                @if(!auth()->user()->isAdmin())
                    <span class="w-6 h-6 rounded-md bg-amber-950/80 text-amber-300 border border-amber-500/40 inline-flex items-center justify-center cursor-help" title="Modo Consulta: Acceso de solo lectura">
                        <span class="material-symbols-outlined text-xs">visibility</span>
                    </span>
                @endif
            </h1>
            <p class="text-xs font-mono text-obsidian-muted">Visibilidad profunda de consumo de ancho de banda, hosts dominantes y protocolos en la red corporativa</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="toggleNetflowGuide()" class="px-3 py-1.5 rounded-xl bg-cyan-950/40 border border-cyan-500/40 text-cyan-300 hover:bg-cyan-900/50 hover:text-white font-mono text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer shadow-xs" title="Ver Guía de Integración y Configuración del Router">
                <span class="material-symbols-outlined text-base">help</span>
                <span class="hidden sm:inline">Guía de Integración</span>
            </button>
            <a href="{{ route('admin.netflow.index') }}" class="p-2 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/50 transition cursor-pointer shadow-xs" title="Refrescar">
                <span class="material-symbols-outlined text-base">refresh</span>
            </a>
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
    <!-- GUÍA DE INTEGRACIÓN & ESTADO DEL COLECTOR UDP 2055                        -->
    <!-- ========================================================================= -->
    <div id="panel-guia-netflow" class="p-4 rounded-2xl bg-obsidian-panel/90 border border-cyan-500/30 space-y-3.5 shadow-lg {{ $totalFlows > 0 ? 'hidden' : '' }}">
        <div class="flex items-start justify-between gap-3 border-b border-obsidian-border/70 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shrink-0">
                    <span class="material-symbols-outlined text-xl">settings_ethernet</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white uppercase font-mono tracking-wider flex items-center gap-2">
                        Guía de Integración: ¿Cómo activar la recepción de NetFlow?
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted mt-0.5">
                        El colector interno está a la espera de datagramas UDP en el puerto 2055 exportados por los enrutadores o cortafuegos de la red.
                    </p>
                </div>
            </div>
            @if($totalFlows > 0)
            <button type="button" onclick="toggleNetflowGuide()" class="text-obsidian-muted hover:text-white transition p-1 cursor-pointer" title="Cerrar Guía">
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
                    UDP 2055 (Activo en segundo plano)
                </div>
            </div>
            <div class="p-2.5 rounded-xl bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                <span class="text-obsidian-muted text-[10px] uppercase block">Versión y Agregación:</span>
                <div class="font-bold text-white">
                    NetFlow v5 (Ventanas de 1 min)
                </div>
            </div>
            <div class="p-2.5 rounded-xl bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                <span class="text-obsidian-muted text-[10px] uppercase block">Mecanismo de Recolección:</span>
                <div class="font-bold text-emerald-400">
                    Push Pasivo (Datagramas Entrantes)
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
                <!-- CISCO IOS -->
                <div class="p-3 rounded-xl bg-[#030a14] border border-obsidian-border/80 space-y-2">
                    <div class="font-bold text-white flex items-center gap-1.5 text-[11px]">
                        <span class="material-symbols-outlined text-cyan-400 text-sm">router</span>
                        Router Cisco (IOS / IOS-XE)
                    </div>
                    <p class="text-obsidian-muted text-[10.5px]">Ejecuta en modo de configuración global:</p>
                    <pre class="bg-black/60 p-2.5 rounded-lg border border-obsidian-border text-[10.5px] text-cyan-300 overflow-x-auto leading-relaxed"><code>configure terminal
ip flow-export destination &lt;IP_SERVIDOR_MONITOREO&gt; 2055
ip flow-export version 5
ip flow-cache timeout active 1
ip flow-cache timeout inactive 15
interface GigabitEthernet0/0
 ip flow ingress
 ip flow egress
end
write memory</code></pre>
                </div>

                <!-- PFSENSE / OPNSENSE -->
                <div class="p-3 rounded-xl bg-[#030a14] border border-obsidian-border/80 space-y-2">
                    <div class="font-bold text-white flex items-center gap-1.5 text-[11px]">
                        <span class="material-symbols-outlined text-emerald-400 text-sm">shield</span>
                        Cortafuegos pfSense / OPNsense
                    </div>
                    <p class="text-obsidian-muted text-[10.5px]">Desde el portal web de administración:</p>
                    <ol class="list-decimal list-inside text-obsidian-muted text-[10.5px] space-y-1 bg-black/60 p-2.5 rounded-lg border border-obsidian-border leading-relaxed">
                        <li>Instala el paquete <code class="text-emerald-300">softflowd</code> en el Gestor de Paquetes.</li>
                        <li>Navega a: <span class="text-slate-200">Services ➔ softflowd</span>.</li>
                        <li><b>Host:</b> <code class="text-emerald-300">&lt;IP_SERVIDOR_MONITOREO&gt;</code></li>
                        <li><b>Port:</b> <code class="text-emerald-300">2055</code> (UDP).</li>
                        <li><b>Versión:</b> <code class="text-emerald-300">5</code> (o compatible).</li>
                        <li>Selecciona las interfaces LAN y WAN.</li>
                        <li>Guarda y aplica los cambios.</li>
                    </ol>
                </div>

                <!-- MIKROTIK ROUTEROS -->
                <div class="p-3 rounded-xl bg-[#030a14] border border-obsidian-border/80 space-y-2">
                    <div class="font-bold text-white flex items-center gap-1.5 text-[11px]">
                        <span class="material-symbols-outlined text-amber-400 text-sm">settings_input_component</span>
                        MikroTik RouterOS
                    </div>
                    <p class="text-obsidian-muted text-[10.5px]">Ejecuta en la consola de comandos de RouterOS:</p>
                    <pre class="bg-black/60 p-2.5 rounded-lg border border-obsidian-border text-[10.5px] text-amber-300 overflow-x-auto leading-relaxed"><code>/ip traffic-flow set enabled=yes
/ip traffic-flow target add \
 dst-address=&lt;IP_SERVIDOR_MONITOREO&gt; \
 port=2055 \
 version=5
/ip traffic-flow print</code></pre>
                </div>
            </div>
        </div>

        <div class="p-2.5 rounded-xl bg-cyan-950/20 border border-cyan-500/20 text-obsidian-muted text-[11px] font-mono flex items-center gap-2">
            <span class="material-symbols-outlined text-cyan-400 text-base shrink-0">info</span>
            <span>Una vez que el enrutador comience a transmitir datagramas, el demonio agrupará el tráfico automáticamente por ventanas de 60 segundos y los rankings de Top Talkers y flujos se poblarán en tiempo real.</span>
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
                        <td colspan="7" class="p-8 text-center text-obsidian-muted font-mono">
                            <span class="material-symbols-outlined text-4xl block mb-2 text-cyan-500/40">swap_vert</span>
                            <span class="text-white font-bold block mb-1">A la espera de flujos NetFlow v5 en UDP 2055</span>
                            <span class="text-xs text-obsidian-muted block max-w-md mx-auto">
                                El colector se encuentra activo en segundo plano. Configura la exportación de NetFlow en tu enrutador o cortafuegos para comenzar a recibir telemetría.
                            </span>
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

<script>
    function toggleNetflowGuide() {
        const guide = document.getElementById('panel-guia-netflow');
        if (guide) {
            guide.classList.toggle('hidden');
        }
    }
</script>
@endsection
