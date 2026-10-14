@extends('layouts.admin')

@section('page_title', 'Centro de Reportes & Exportación NOC + ITAM • Valle Seco')

@section('admin_content')
<div class="space-y-4">
    <!-- CABECERA -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-[10.5px] font-mono font-semibold group shadow-xs">
                    <span class="material-symbols-outlined text-xs group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-[10.5px] font-mono text-obsidian-muted">Reportes &amp; Exportación</span>
            </div>
            <h1 class="text-xl font-black text-white tracking-wide flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-2xl">summarize</span>
                Centro de Reportes &amp; Exportación NOC + ITAM
            </h1>
            <p class="text-xs font-mono text-obsidian-muted">Consolidación en tiempo real de inventarios y métricas en formato Excel, CSV y PDF para auditoría, supervisión y operaciones</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-9 h-9 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-400 inline-flex items-center justify-center shadow-xs cursor-help" title="SSOT MariaDB • Generación en Vivo">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            </span>
        </div>
    </div>

    <!-- TARJETAS KPI RESUMEN DE GOBERNANZA -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- TOTAL ACTIVOS & INFRAESTRUCTURA -->
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10.5px] font-mono text-obsidian-muted uppercase tracking-wider">Activos Censados</p>
                <p class="text-2xl font-bold font-mono text-white mt-1">{{ number_format($metrics['total_entities']) }}</p>
                <p class="text-[10px] font-mono text-obsidian-muted/80 mt-0.5">Parque NOC + ITAM Valle Seco</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-obsidian-panel border border-obsidian-border flex items-center justify-center text-cyan-400">
                <span class="material-symbols-outlined text-xl">devices</span>
            </div>
        </div>

        <!-- DISPONIBILIDAD OPERATIVA NOC -->
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10.5px] font-mono text-obsidian-muted uppercase tracking-wider">Disponibilidad de Red NOC</p>
                <p class="text-2xl font-bold font-mono text-blue-300 mt-1">{{ $metrics['sla_rate'] }}%</p>
                <p class="text-[10px] font-mono text-obsidian-muted/80 mt-0.5">Supervisión 24/7 de sedes y enlaces</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-blue-950/40 border border-blue-500/30 flex items-center justify-center text-blue-400">
                <span class="material-symbols-outlined text-xl">hub</span>
            </div>
        </div>

        <!-- ACTAS UINF-4.05 REGISTRADAS -->
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10.5px] font-mono text-obsidian-muted uppercase tracking-wider">Actas UINF-4.05</p>
                <p class="text-2xl font-bold font-mono text-emerald-300 mt-1">{{ number_format($metrics['total_assignments']) }}</p>
                <p class="text-[10px] font-mono text-obsidian-muted/80 mt-0.5">{{ number_format($metrics['active_assignments']) }} asignaciones vigentes</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-950/40 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                <span class="material-symbols-outlined text-xl">assignment_turned_in</span>
            </div>
        </div>

        <!-- TASA DE DIGITALIZACIÓN DE ACTAS -->
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10.5px] font-mono text-obsidian-muted uppercase tracking-wider">Digitalización de Soportes</p>
                <p class="text-2xl font-bold font-mono {{ $metrics['compliance_rate'] >= 80 ? 'text-emerald-400' : 'text-amber-400' }} mt-1">
                    {{ $metrics['compliance_rate'] }}%
                </p>
                <p class="text-[10px] font-mono text-obsidian-muted/80 mt-0.5">Actas con respaldo escaneado</p>
            </div>
            <div class="w-11 h-11 rounded-xl {{ $metrics['compliance_rate'] >= 80 ? 'bg-emerald-950/40 border-emerald-500/30 text-emerald-400' : 'bg-amber-950/40 border-amber-500/30 text-amber-400' }} border flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">verified</span>
            </div>
        </div>
    </div>

    <!-- CATÁLOGO DE REPORTES -->
    <div class="space-y-3 pt-2">
        <div class="flex items-center justify-between pb-2 border-b border-obsidian-border">
            <h2 class="font-bold text-white text-xs font-mono uppercase tracking-wider flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-base">download_for_offline</span>
                <span>Catálogo de Reportes Imprimibles y Estructurados</span>
            </h2>
            <span class="text-obsidian-muted font-mono text-xs">{{ count($reports) }} reportes listos</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach($reports as $r)
                <div class="p-5 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border hover:border-cyan-500/40 transition-all shadow-md flex flex-col justify-between space-y-4 group {{ $r['id'] === 'census' ? 'lg:col-span-2' : '' }}">
                    <div class="space-y-2.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 group-hover:scale-105 transition-transform shrink-0">
                                    <span class="material-symbols-outlined text-2xl">{{ $r['icon'] }}</span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-white text-sm font-mono tracking-wide">{{ $r['title'] }}</h3>
                                    <p class="text-[11px] font-mono text-cyan-300/90">{{ $r['subtitle'] }}</p>
                                </div>
                            </div>
                            <!-- INDICADOR COMPACTO CON TOOLTIP (REGLA DE ORO #11: CERO BADGES DE TEXTO EN ENCABEZADOS) -->
                            <span class="w-7 h-7 rounded-lg {{ $r['badge_class'] }} flex items-center justify-center shrink-0 cursor-help" title="{{ $r['badge'] }}">
                                <span class="material-symbols-outlined text-sm">{{ $r['icon'] }}</span>
                            </span>
                        </div>

                        <p class="text-xs font-mono text-slate-300 leading-relaxed pl-1">
                            {{ $r['description'] }}
                        </p>
                    </div>

                    @if($r['id'] === 'census')
                        <!-- SECCIÓN DE EXPORTACIÓN SEGMENTADA -->
                        <div class="pt-3 border-t border-obsidian-border/60 space-y-2.5">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                <span class="text-[11px] font-mono text-cyan-300 font-bold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">segment</span>
                                    <span>Exportación Segmentada:</span>
                                </span>
                                <span class="text-[10px] font-mono text-obsidian-muted">Formatos compatibles con Excel (.xls) y CSV (UTF-8)</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                                <!-- 1. SERVIDORES -->
                                <div class="p-3 rounded-xl bg-obsidian-card/80 border border-obsidian-border hover:border-cyan-500/30 transition flex flex-col justify-between space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-md bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400 shrink-0">
                                            <span class="material-symbols-outlined text-sm">dns</span>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-white text-xs font-bold font-mono truncate">Servidores</p>
                                            <p class="text-[9.5px] font-mono text-obsidian-muted truncate">Centro de Datos • 17 cols</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 pt-1.5 border-t border-obsidian-border/40">
                                        <a href="{{ route('admin.reports.export', ['type' => 'census', 'format' => 'excel', 'category' => 'servers']) }}" 
                                           class="flex-1 py-1 px-2 rounded-lg bg-cyan-950/60 hover:bg-cyan-900 border border-cyan-500/40 text-cyan-200 text-center font-mono font-bold text-[10.5px] transition flex items-center justify-center gap-1"
                                           title="Descargar Servidores en formato Excel (Centro de Datos 17 columnas)">
                                            <span class="material-symbols-outlined text-xs text-cyan-400">table_chart</span>
                                            XLS
                                        </a>
                                        <a href="{{ route('admin.reports.export', ['type' => 'census', 'format' => 'csv', 'category' => 'servers']) }}" 
                                           class="flex-1 py-1 px-2 rounded-lg bg-emerald-950/60 hover:bg-emerald-900 border border-emerald-500/40 text-emerald-200 text-center font-mono font-bold text-[10.5px] transition flex items-center justify-center gap-1"
                                           title="Descargar Servidores en formato CSV">
                                            <span class="material-symbols-outlined text-xs text-emerald-400">table_view</span>
                                            CSV
                                        </a>
                                    </div>
                                </div>

                                <!-- 2. TELECOMUNICACIONES -->
                                <div class="p-3 rounded-xl bg-obsidian-card/80 border border-obsidian-border hover:border-cyan-500/30 transition flex flex-col justify-between space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-md bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400 shrink-0">
                                            <span class="material-symbols-outlined text-sm">router</span>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-white text-xs font-bold font-mono truncate">Telecomunicaciones</p>
                                            <p class="text-[9.5px] font-mono text-obsidian-muted truncate">SW, Router, VOIP • 17 cols</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 pt-1.5 border-t border-obsidian-border/40">
                                        <a href="{{ route('admin.reports.export', ['type' => 'census', 'format' => 'excel', 'category' => 'telecom']) }}" 
                                           class="flex-1 py-1 px-2 rounded-lg bg-cyan-950/60 hover:bg-cyan-900 border border-cyan-500/40 text-cyan-200 text-center font-mono font-bold text-[10.5px] transition flex items-center justify-center gap-1"
                                           title="Descargar Telecomunicaciones en formato Excel (Centro de Datos 17 columnas)">
                                            <span class="material-symbols-outlined text-xs text-cyan-400">table_chart</span>
                                            XLS
                                        </a>
                                        <a href="{{ route('admin.reports.export', ['type' => 'census', 'format' => 'csv', 'category' => 'telecom']) }}" 
                                           class="flex-1 py-1 px-2 rounded-lg bg-emerald-950/60 hover:bg-emerald-900 border border-emerald-500/40 text-emerald-200 text-center font-mono font-bold text-[10.5px] transition flex items-center justify-center gap-1"
                                           title="Descargar Telecomunicaciones en formato CSV">
                                            <span class="material-symbols-outlined text-xs text-emerald-400">table_view</span>
                                            CSV
                                        </a>
                                    </div>
                                </div>

                                <!-- 3. EQUIPOS DE COMPUTACIÓN -->
                                <div class="p-3 rounded-xl bg-obsidian-card/80 border border-obsidian-border hover:border-cyan-500/30 transition flex flex-col justify-between space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-md bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400 shrink-0">
                                            <span class="material-symbols-outlined text-sm">desktop_windows</span>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-white text-xs font-bold font-mono truncate">Equipos Computación</p>
                                            <p class="text-[9.5px] font-mono text-obsidian-muted truncate">Puestos &amp; Periféricos • 16 cols</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 pt-1.5 border-t border-obsidian-border/40">
                                        <a href="{{ route('admin.reports.export', ['type' => 'census', 'format' => 'excel', 'category' => 'workstations']) }}" 
                                           class="flex-1 py-1 px-2 rounded-lg bg-cyan-950/60 hover:bg-cyan-900 border border-cyan-500/40 text-cyan-200 text-center font-mono font-bold text-[10.5px] transition flex items-center justify-center gap-1"
                                           title="Descargar Equipos de Computación en formato Excel (Estaciones de Trabajo con ASIGNADO)">
                                            <span class="material-symbols-outlined text-xs text-cyan-400">table_chart</span>
                                            XLS
                                        </a>
                                        <a href="{{ route('admin.reports.export', ['type' => 'census', 'format' => 'csv', 'category' => 'workstations']) }}" 
                                           class="flex-1 py-1 px-2 rounded-lg bg-emerald-950/60 hover:bg-emerald-900 border border-emerald-500/40 text-emerald-200 text-center font-mono font-bold text-[10.5px] transition flex items-center justify-center gap-1"
                                           title="Descargar Equipos de Computación en formato CSV">
                                            <span class="material-symbols-outlined text-xs text-emerald-400">table_view</span>
                                            CSV
                                        </a>
                                    </div>
                                </div>

                                <!-- 4. INVENTARIO UNIFICADO -->
                                <div class="p-3 rounded-xl bg-cyan-950/40 border border-cyan-500/40 hover:border-cyan-400 transition flex flex-col justify-between space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-md bg-cyan-400/20 border border-cyan-400/40 flex items-center justify-center text-cyan-300 shrink-0">
                                            <span class="material-symbols-outlined text-sm">menu_book</span>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-cyan-200 text-xs font-bold font-mono truncate">Inventario Unificado</p>
                                            <p class="text-[9.5px] font-mono text-cyan-300/80 truncate">2 Hojas consolidadas</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 pt-1.5 border-t border-cyan-500/30">
                                        <a href="{{ route('admin.reports.export', ['type' => 'census', 'format' => 'excel', 'category' => 'all']) }}" 
                                           class="flex-1 py-1 px-2 rounded-lg bg-cyan-500 text-black hover:bg-cyan-400 text-center font-mono font-bold text-[10.5px] transition flex items-center justify-center gap-1 shadow-sm"
                                           title="Descargar Inventario Unificado en Excel (2 Hojas: Centro de Datos y Estaciones de Trabajo)">
                                            <span class="material-symbols-outlined text-xs">table_chart</span>
                                            XLS
                                        </a>
                                        <a href="{{ route('admin.reports.export', ['type' => 'census', 'format' => 'csv', 'category' => 'all']) }}" 
                                           class="flex-1 py-1 px-2 rounded-lg bg-emerald-950/70 hover:bg-emerald-900 border border-emerald-500/50 text-emerald-200 text-center font-mono font-bold text-[10.5px] transition flex items-center justify-center gap-1"
                                           title="Descargar inventario consolidado en formato CSV">
                                            <span class="material-symbols-outlined text-xs text-emerald-400">table_view</span>
                                            CSV
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- BARRA DE ACCIÓN PDF -->
                        <div class="pt-3 border-t border-obsidian-border/60 flex items-center justify-between gap-3 font-mono text-xs">
                            <span class="text-obsidian-muted text-[10.5px]">Documento Imprimible Completo:</span>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.reports.export', ['type' => $r['id'], 'format' => 'pdf']) }}" 
                                   target="_blank"
                                   class="px-3.5 py-1.5 rounded-xl bg-cyan-950/80 hover:bg-cyan-900 border border-cyan-500/50 hover:border-cyan-400 text-cyan-300 font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                                   title="Generar e imprimir reporte en formato PDF">
                                    <span class="material-symbols-outlined text-sm text-cyan-400">picture_as_pdf</span>
                                    <span>PDF</span>
                                </a>
                            </div>
                        </div>
                    @else
                        <!-- BOTONES DE ACCIÓN PARA CADA FORMATO EN OTROS REPORTES -->
                        <div class="pt-3 border-t border-obsidian-border/60 flex items-center justify-between gap-3 font-mono text-xs">
                            <span class="text-obsidian-muted text-[10.5px]">Descargar formato:</span>
                            <div class="flex items-center gap-2">
                                <!-- BOTÓN EXCEL -->
                                <a href="{{ route('admin.reports.export', ['type' => $r['id'], 'format' => 'excel']) }}" 
                                   class="px-3 py-1.5 rounded-xl bg-[#031526] hover:bg-cyan-950/70 border border-cyan-500/50 hover:border-cyan-400 text-cyan-200 font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                                   title="Descargar reporte en formato Excel (.xls)">
                                    <span class="material-symbols-outlined text-sm text-cyan-400">table_chart</span>
                                    <span>EXCEL</span>
                                </a>

                                <!-- BOTÓN CSV -->
                                <a href="{{ route('admin.reports.export', ['type' => $r['id'], 'format' => 'csv']) }}" 
                                   class="px-3 py-1.5 rounded-xl bg-[#040b15] hover:bg-emerald-950/60 border border-emerald-500/40 hover:border-emerald-400 text-emerald-300 font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                                   title="Descargar datos en formato CSV para Microsoft Excel o procesadores de hoja de cálculo">
                                    <span class="material-symbols-outlined text-sm text-emerald-400">table_view</span>
                                    <span>CSV</span>
                                </a>

                                <!-- BOTÓN PDF -->
                                <a href="{{ route('admin.reports.export', ['type' => $r['id'], 'format' => 'pdf']) }}" 
                                   target="_blank"
                                   class="px-3 py-1.5 rounded-xl bg-cyan-950/80 hover:bg-cyan-900 border border-cyan-500/50 hover:border-cyan-400 text-cyan-300 font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                                   title="Generar e imprimir reporte en formato PDF">
                                    <span class="material-symbols-outlined text-sm text-cyan-400">picture_as_pdf</span>
                                    <span>PDF</span>
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
