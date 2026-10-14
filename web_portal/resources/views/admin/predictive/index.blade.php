@extends('layouts.admin')

@section('page_title', 'IA Predictiva y Capacidad')

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
                <span class="text-[10.5px] font-mono text-obsidian-muted">Alertas &amp; Respaldos</span>
            </div>
            <h1 class="text-xl font-black text-white tracking-wide flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-400 text-2xl">psychology</span>
                Motor de Inteligencia Artificial Predictiva &amp; Capacidad
                <span class="w-6 h-6 rounded-md bg-purple-950/80 border border-purple-500/40 text-purple-300 inline-flex items-center justify-center cursor-help" title="Motor Local de IA Activo">
                    <span class="w-1.5 h-1.5 rounded-full bg-purple-400 animate-pulse"></span>
                </span>
                @if(!auth()->user()->isAdmin() && !auth()->user()->hasPermission('predictive.run') && !auth()->user()->hasPermission('predictive.manage'))
                    <span class="w-6 h-6 rounded-md bg-amber-950/80 text-amber-300 border border-amber-500/40 inline-flex items-center justify-center cursor-help" title="Modo Consulta: Acceso de solo lectura">
                        <span class="material-symbols-outlined text-xs">visibility</span>
                    </span>
                @endif
            </h1>
            <p class="text-xs font-mono text-obsidian-muted">Inferencia estadística, detección temprana de saturación de recursos y anomalías operacionales antes del corte de servicio</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('predictive.run')))
                <form action="{{ route('admin.predictive.run') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-purple-500/10 border border-purple-500/40 text-purple-300 hover:bg-purple-500 hover:text-black transition text-xs font-mono font-bold shadow-xs cursor-pointer" title="Ejecutar Análisis IA Ahora">
                        <span class="material-symbols-outlined text-base">auto_fix_high</span>
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.predictive.index') }}" class="p-2 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/50 transition cursor-pointer shadow-xs" title="Refrescar">
                <span class="material-symbols-outlined text-base">refresh</span>
            </a>
        </div>
    </div>

    <!-- PESTAÑAS HUB ALERTAS & RESPALDOS (PANEL DE NAVEGACIÓN UNIFICADO) -->
    <div class="flex flex-wrap items-center gap-1.5 border-b border-obsidian-border/80 pb-2.5">
        <a href="{{ route('admin.alerts.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.alerts.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Sistema Inteligente de Alertas y Correlación de Incidentes">
            <span class="material-symbols-outlined text-sm">notifications_active</span>
            <span>Alertas &amp; Correlación</span>
        </a>
        <a href="{{ route('admin.configs.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.configs.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Respaldos y Auditoría de Configuraciones de Red (GitOps)">
            <span class="material-symbols-outlined text-sm">settings_backup_restore</span>
            <span>Respaldos de Configuración</span>
        </a>
        <a href="{{ route('admin.predictive.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.predictive.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="IA Predictiva &amp; Salud de Enlaces">
            <span class="material-symbols-outlined text-sm">psychology</span>
            <span>IA Predictiva &amp; Salud de Enlaces</span>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- HUD: TARJETAS MÉTRICAS                                                    -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
        <!-- TOTAL ANOMALÍAS -->
        <a href="{{ route('admin.predictive.index') }}" class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 hover:border-purple-500/40 transition block {{ !request('status') && !request('type') ? 'ring-1 ring-purple-500/50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Total Detectadas</span>
                <span class="material-symbols-outlined text-sm text-purple-400">insights</span>
            </div>
            <div class="text-lg font-bold font-mono text-white mt-1">{{ number_format($totalAnomalies) }}</div>
            <div class="text-[9px] font-mono text-purple-400/80 mt-0.5">Histórico global</div>
        </a>

        <!-- ANOMALÍAS ACTIVAS -->
        <a href="{{ route('admin.predictive.index', ['status' => 'active']) }}" class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 hover:border-rose-500/40 transition block {{ request('status') === 'active' ? 'ring-1 ring-rose-500/50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Anomalías Activas</span>
                <span class="material-symbols-outlined text-sm text-rose-400">warning</span>
            </div>
            <div class="text-lg font-bold font-mono text-rose-400 mt-1">{{ number_format($activeAnomalies) }}</div>
            <div class="text-[9px] font-mono text-rose-400/80 mt-0.5">Requieren atención</div>
        </a>

        <!-- TENDENCIAS ALCISTAS -->
        <a href="{{ route('admin.predictive.index', ['type' => 'trend_upward']) }}" class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 hover:border-amber-500/40 transition block {{ request('type') === 'trend_upward' ? 'ring-1 ring-amber-500/50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Tendencias Alcistas</span>
                <span class="material-symbols-outlined text-sm text-amber-400">trending_up</span>
            </div>
            <div class="text-lg font-bold font-mono text-amber-300 mt-1">{{ number_format($upwardTrends) }}</div>
            <div class="text-[9px] font-mono text-amber-400/80 mt-0.5">Riesgo de agotamiento</div>
        </a>

        <!-- OUTLIERS (>3σ) -->
        <a href="{{ route('admin.predictive.index', ['type' => 'outlier']) }}" class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 hover:border-cyan-500/40 transition block {{ request('type') === 'outlier' ? 'ring-1 ring-cyan-500/50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Desviaciones (> 3σ)</span>
                <span class="material-symbols-outlined text-sm text-cyan-400">scatter_plot</span>
            </div>
            <div class="text-lg font-bold font-mono text-cyan-300 mt-1">{{ number_format($criticalOutliers) }}</div>
            <div class="text-[9px] font-mono text-cyan-400/80 mt-0.5">Picos estadísticos</div>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- FILTROS                                                                   -->
    <!-- ========================================================================= -->
    <div class="p-3 rounded-xl bg-obsidian-panel/80 border border-obsidian-border">
        <form method="GET" action="{{ route('admin.predictive.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-2 text-xs font-mono">
            <div>
                <select name="type" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-purple-500">
                    <option value="">-- Todos los Patrones --</option>
                    <option value="trend_upward" {{ $typeFilter === 'trend_upward' ? 'selected' : '' }}>Tendencia Alcista Crítica</option>
                    <option value="outlier" {{ $typeFilter === 'outlier' ? 'selected' : '' }}>Desviación Estadística (> 3σ)</option>
                    <option value="seasonal_pattern" {{ $typeFilter === 'seasonal_pattern' ? 'selected' : '' }}>Patrón Inusual / Cíclico</option>
                    <option value="correlation" {{ $typeFilter === 'correlation' ? 'selected' : '' }}>Correlación Anómala</option>
                </select>
            </div>

            <div>
                <select name="status" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-purple-500">
                    <option value="">-- Todos los Estados --</option>
                    <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Solo Activas (Pendientes)</option>
                    <option value="acknowledged" {{ $statusFilter === 'acknowledged' ? 'selected' : '' }}>Solo Atendidas</option>
                </select>
            </div>

            <div>
                <select name="entity_type" class="w-full px-2.5 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-purple-500">
                    <option value="">-- Todas las Entidades --</option>
                    <option value="network_device" {{ $entityFilter === 'network_device' ? 'selected' : '' }}>Equipos de Red</option>
                    <option value="snmp_device" {{ $entityFilter === 'snmp_device' ? 'selected' : '' }}>Dispositivos SNMP</option>
                    <option value="service" {{ $entityFilter === 'service' ? 'selected' : '' }}>Servicios Monitoreados</option>
                    <option value="site" {{ $entityFilter === 'site' ? 'selected' : '' }}>Sedes / Enlaces WAN</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="w-full px-3 py-1.5 rounded-lg bg-purple-500 text-white font-bold hover:bg-purple-600 transition cursor-pointer">
                    Filtrar
                </button>
                <a href="{{ route('admin.predictive.index') }}" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 transition">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- ========================================================================= -->
    <!-- TABLA DE ANOMALÍAS PREDICTIVAS                                            -->
    <!-- ========================================================================= -->
    <div class="rounded-xl bg-obsidian-panel/80 border border-obsidian-border overflow-hidden shadow-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-left font-mono text-xs">
                <thead>
                    <tr class="bg-obsidian-bg/90 border-b border-obsidian-border text-[11px] text-obsidian-muted uppercase">
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Patrón / Tipo</th>
                        <th class="px-4 py-3">Entidad Afectada</th>
                        <th class="px-4 py-3">Diagnóstico Predictivo</th>
                        <th class="px-4 py-3">Proyección de Impacto</th>
                        <th class="px-4 py-3">Detección</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border">
                    @forelse($anomalies as $ano)
                        <tr class="hover:bg-purple-500/5 transition">
                            <td class="px-4 py-3">
                                @if($ano->acknowledged)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/30">
                                        <span class="material-symbols-outlined text-xs">check_circle</span>
                                        Atendida
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-950 text-rose-400 border border-rose-500/30 animate-pulse">
                                        <span class="material-symbols-outlined text-xs">error</span>
                                        Activa
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold border {{ $ano->anomaly_badge_color }}">
                                    {{ $ano->anomaly_label }}
                                </span>
                                <div class="text-[10px] text-obsidian-muted mt-1">Confianza: <span class="text-white font-bold">{{ $ano->confidence }}%</span></div>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $res = $ano->resolved_entity;
                                @endphp
                                @if($res)
                                    <a href="{{ $res['url'] }}" class="font-bold text-white hover:text-cyan-300 transition block truncate max-w-[200px]" title="Ver ficha en {{ $res['type_label'] }}">
                                        {{ $res['name'] }}
                                    </a>
                                    <div class="text-[10px] text-cyan-300 font-mono">{{ $res['ip'] ?? $res['type_label'] }}</div>
                                @else
                                    <div class="font-bold text-white uppercase">{{ str_replace('_', ' ', $ano->entity_type) }} #{{ $ano->entity_id }}</div>
                                    <div class="text-[10px] text-cyan-300">Métrica: {{ $ano->metric_name ?? 'Global' }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 max-w-xs text-obsidian-text">
                                {{ $ano->description }}
                            </td>
                            <td class="px-4 py-3 max-w-xs text-[11px] text-amber-300 font-medium">
                                {{ $ano->predicted_impact ?? 'Monitoreo proactivo continuo.' }}
                            </td>
                            <td class="px-4 py-3 text-[10px] text-obsidian-muted whitespace-nowrap">
                                <div>{{ $ano->detected_at->format('Y-m-d H:i') }}</div>
                                <div class="text-[9px]">{{ $ano->detected_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if(auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('predictive.manage')))
                                        @if(!$ano->acknowledged)
                                            <form action="{{ route('admin.predictive.acknowledge', $ano->id) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 rounded bg-purple-500/20 hover:bg-purple-500 hover:text-black border border-purple-500/40 text-purple-300 text-[11px] font-bold transition flex items-center gap-1 cursor-pointer" title="Marcar como atendida">
                                                    <span class="material-symbols-outlined text-xs">done</span>
                                                    Atender
                                                </button>
                                            </form>
                                        @endif

                                        <form action="{{ route('admin.predictive.destroy', $ano->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Descartar esta anomalía?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 rounded text-rose-400 hover:bg-rose-500/20 transition cursor-pointer" title="Descartar">
                                                <span class="material-symbols-outlined text-sm">delete</span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[10px] text-obsidian-muted font-mono">Solo lectura</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-obsidian-muted">
                                <span class="material-symbols-outlined text-3xl mb-2 text-obsidian-border block">verified</span>
                                No se detectan anomalías ni riesgos de saturación predictivos pendientes.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($anomalies->hasPages())
            <div class="p-3 border-t border-obsidian-border">
                {{ $anomalies->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
