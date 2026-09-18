@extends('layouts.admin')

@section('page_title', 'Sistema de Alertas y Correlación')

@section('admin_content')
<div class="space-y-4">
    <!-- CABECERA Y HUD DE ESTADO -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 bg-obsidian-card p-3.5 rounded-xl border border-obsidian-border shadow-md">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan text-2xl">notifications_active</span>
                <h1 class="text-lg font-bold text-white tracking-wide">Sistema Inteligente de Alertas y Correlación</h1>
                @if(!auth()->user()->isAdmin())
                    <span class="px-2 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 text-[10px] font-mono flex items-center gap-1" title="Operador: Modo Consulta y Reconocimiento de Incidentes">
                        <span class="material-symbols-outlined text-xs">visibility</span> Modo Consulta
                    </span>
                @endif
            </div>
            <p class="text-xs text-obsidian-muted mt-0.5">
                Monitoreo proactivo, supresión de tormentas, correlación topológica padre-hijo y escalación jerárquica.
            </p>
        </div>

        <!-- ACCIONES RÁPIDAS CABECERA -->
        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->isAdmin())
                <form action="{{ route('admin.alerts.evaluate_now') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-obsidian-cyan/10 border border-obsidian-cyan/40 text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-[11px] font-semibold transition flex items-center gap-1.5 shadow-xs" title="Evaluar todas las reglas y procesar escalaciones inmediatamente">
                        <span class="material-symbols-outlined text-sm">bolt</span> Evaluar Ahora
                    </button>
                </form>
                <button onclick="openNewRuleModal()" class="px-2.5 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/40 text-emerald-400 hover:bg-emerald-500 hover:text-black font-mono text-[11px] font-semibold transition flex items-center gap-1.5 shadow-xs" title="Configurar una nueva regla con umbrales y escalación">
                    <span class="material-symbols-outlined text-sm">add_alert</span> Nueva Regla
                </button>
                <button onclick="openMaintenanceModal()" class="px-2.5 py-1.5 rounded-lg bg-purple-500/10 border border-purple-500/40 text-purple-300 hover:bg-purple-500 hover:text-white font-mono text-[11px] font-semibold transition flex items-center gap-1.5 shadow-xs" title="Programar ventana de mantenimiento preventivo">
                    <span class="material-symbols-outlined text-sm">build_circle</span> Mantenimiento
                </button>
            @endif
        </div>
    </div>

    <!-- TARJETAS HUD METRICAS -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
        <!-- Críticas / Emergencia -->
        <div class="bg-obsidian-card p-2.5 rounded-xl border border-red-500/30 flex items-center gap-2.5 shadow-xs relative overflow-hidden">
            <div class="w-1.5 h-full bg-red-500 absolute left-0 top-0"></div>
            <div class="p-1.5 rounded-lg bg-red-500/10 text-red-400">
                <span class="material-symbols-outlined text-xl">warning</span>
            </div>
            <div>
                <div class="text-[10px] uppercase font-mono text-slate-400 tracking-wider">Críticas / Emerg.</div>
                <div class="text-base font-bold font-mono text-red-400 leading-tight">{{ $stats['critical_emergency'] }}</div>
            </div>
        </div>

        <!-- Firing -->
        <div class="bg-obsidian-card p-2.5 rounded-xl border border-amber-500/30 flex items-center gap-2.5 shadow-xs relative overflow-hidden">
            <div class="w-1.5 h-full bg-amber-500 absolute left-0 top-0"></div>
            <div class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400">
                <span class="material-symbols-outlined text-xl">campaign</span>
            </div>
            <div>
                <div class="text-[10px] uppercase font-mono text-slate-400 tracking-wider">Disparadas</div>
                <div class="text-base font-bold font-mono text-amber-400 leading-tight">{{ $stats['firing'] }}</div>
            </div>
        </div>

        <!-- Reconocidas -->
        <div class="bg-obsidian-card p-2.5 rounded-xl border border-sky-500/30 flex items-center gap-2.5 shadow-xs relative overflow-hidden">
            <div class="w-1.5 h-full bg-sky-500 absolute left-0 top-0"></div>
            <div class="p-1.5 rounded-lg bg-sky-500/10 text-sky-400">
                <span class="material-symbols-outlined text-xl">check_circle</span>
            </div>
            <div>
                <div class="text-[10px] uppercase font-mono text-slate-400 tracking-wider">Reconocidas</div>
                <div class="text-base font-bold font-mono text-sky-300 leading-tight">{{ $stats['acknowledged'] }}</div>
            </div>
        </div>

        <!-- Suprimidas / Flapping -->
        <div class="bg-obsidian-card p-2.5 rounded-xl border border-purple-500/30 flex items-center gap-2.5 shadow-xs relative overflow-hidden">
            <div class="w-1.5 h-full bg-purple-500 absolute left-0 top-0"></div>
            <div class="p-1.5 rounded-lg bg-purple-500/10 text-purple-300">
                <span class="material-symbols-outlined text-xl">shield</span>
            </div>
            <div>
                <div class="text-[10px] uppercase font-mono text-slate-400 tracking-wider">Suprimidas</div>
                <div class="text-base font-bold font-mono text-purple-300 leading-tight">{{ $stats['suppressed'] }}</div>
            </div>
        </div>

        <!-- Ventanas Mantenimiento -->
        <div class="bg-obsidian-card p-2.5 rounded-xl border border-indigo-500/30 flex items-center gap-2.5 shadow-xs relative overflow-hidden">
            <div class="w-1.5 h-full bg-indigo-500 absolute left-0 top-0"></div>
            <div class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-400">
                <span class="material-symbols-outlined text-xl">construction</span>
            </div>
            <div>
                <div class="text-[10px] uppercase font-mono text-slate-400 tracking-wider">Mantenimiento</div>
                <div class="text-base font-bold font-mono text-indigo-300 leading-tight">{{ $stats['active_maintenance'] }} Activas</div>
            </div>
        </div>

        <!-- Reglas Activas -->
        <div class="bg-obsidian-card p-2.5 rounded-xl border border-emerald-500/30 flex items-center gap-2.5 shadow-xs relative overflow-hidden">
            <div class="w-1.5 h-full bg-emerald-500 absolute left-0 top-0"></div>
            <div class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400">
                <span class="material-symbols-outlined text-xl">rule</span>
            </div>
            <div>
                <div class="text-[10px] uppercase font-mono text-slate-400 tracking-wider">Reglas Activas</div>
                <div class="text-base font-bold font-mono text-emerald-400 leading-tight">{{ $stats['total_rules'] }} Reglas</div>
            </div>
        </div>
    </div>

    <!-- PESTAÑAS DE NAVEGACIÓN -->
    <div class="flex items-center gap-1 border-b border-obsidian-border/80 px-1">
        <a href="{{ route('admin.alerts.index', ['tab' => 'active']) }}" 
           class="px-3.5 py-2 text-xs font-semibold rounded-t-lg transition flex items-center gap-1.5 border-t border-x {{ $tab === 'active' ? 'bg-obsidian-card border-obsidian-border text-obsidian-cyan -mb-px' : 'border-transparent text-slate-400 hover:text-white hover:bg-slate-800/40' }}"
           title="Ver todas las alarmas e incidentes actualmente activos">
            <span class="material-symbols-outlined text-sm">notifications_active</span>
            Alertas Activas ({{ $stats['firing'] + $stats['acknowledged'] + $stats['suppressed'] }})
        </a>
        <a href="{{ route('admin.alerts.index', ['tab' => 'history']) }}" 
           class="px-3.5 py-2 text-xs font-semibold rounded-t-lg transition flex items-center gap-1.5 border-t border-x {{ $tab === 'history' ? 'bg-obsidian-card border-obsidian-border text-obsidian-cyan -mb-px' : 'border-transparent text-slate-400 hover:text-white hover:bg-slate-800/40' }}"
           title="Historial de incidentes resueltos">
            <span class="material-symbols-outlined text-sm">history</span>
            Historial de Incidentes
        </a>
        <a href="{{ route('admin.alerts.index', ['tab' => 'rules']) }}" 
           class="px-3.5 py-2 text-xs font-semibold rounded-t-lg transition flex items-center gap-1.5 border-t border-x {{ $tab === 'rules' ? 'bg-obsidian-card border-obsidian-border text-obsidian-cyan -mb-px' : 'border-transparent text-slate-400 hover:text-white hover:bg-slate-800/40' }}"
           title="Configuración de reglas, umbrales y escalación">
            <span class="material-symbols-outlined text-sm">rule</span>
            Reglas de Alerta ({{ count($rules) }})
        </a>
        <a href="{{ route('admin.alerts.index', ['tab' => 'maintenance']) }}" 
           class="px-3.5 py-2 text-xs font-semibold rounded-t-lg transition flex items-center gap-1.5 border-t border-x {{ $tab === 'maintenance' ? 'bg-obsidian-card border-obsidian-border text-obsidian-cyan -mb-px' : 'border-transparent text-slate-400 hover:text-white hover:bg-slate-800/40' }}"
           title="Ventanas de mantenimiento programadas">
            <span class="material-symbols-outlined text-sm">build_circle</span>
            Mantenimiento ({{ $maintenanceWindows->total() }})
        </a>
        <a href="{{ route('admin.alerts.index', ['tab' => 'correlation']) }}" 
           class="px-3.5 py-2 text-xs font-semibold rounded-t-lg transition flex items-center gap-1.5 border-t border-x {{ $tab === 'correlation' ? 'bg-obsidian-card border-obsidian-border text-obsidian-cyan -mb-px' : 'border-transparent text-slate-400 hover:text-white hover:bg-slate-800/40' }}"
           title="Grupos de correlación topológica padre-hijo">
            <span class="material-symbols-outlined text-sm">account_tree</span>
            Correlación Topológica ({{ count($correlationGroups) }})
        </a>
    </div>

    <!-- CONTENIDO DE LAS PESTAÑAS -->
    <div class="bg-obsidian-card border border-obsidian-border rounded-xl shadow-md overflow-hidden">
        
        <!-- TAB 1: ALERTAS ACTIVAS -->
        @if($tab === 'active')
            <!-- FILTROS -->
            <div class="p-3 border-b border-obsidian-border/80 bg-slate-900/40 flex flex-wrap items-center justify-between gap-2.5">
                <form method="GET" action="{{ route('admin.alerts.index') }}" class="flex flex-wrap items-center gap-2 text-xs">
                    <input type="hidden" name="tab" value="active">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm">search</span>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por entidad o mensaje..." class="pl-8 pr-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs placeholder-slate-500 focus:outline-none focus:border-obsidian-cyan w-56 font-mono">
                    </div>
                    <select name="severity" onchange="this.form.submit()" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-obsidian-cyan font-mono">
                        <option value="">Todas las severidades</option>
                        <option value="emergency" {{ request('severity') === 'emergency' ? 'selected' : '' }}>🆘 Emergencia</option>
                        <option value="critical" {{ request('severity') === 'critical' ? 'selected' : '' }}>🚨 Crítica</option>
                        <option value="warning" {{ request('severity') === 'warning' ? 'selected' : '' }}>⚠️ Advertencia</option>
                        <option value="info" {{ request('severity') === 'info' ? 'selected' : '' }}>ℹ️ Información</option>
                    </select>
                    <select name="status" onchange="this.form.submit()" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-obsidian-cyan font-mono">
                        <option value="">Todos los estados</option>
                        <option value="firing" {{ request('status') === 'firing' ? 'selected' : '' }}>🔥 Disparadas / Activas</option>
                        <option value="acknowledged" {{ request('status') === 'acknowledged' ? 'selected' : '' }}>👁️ Reconocidas</option>
                        <option value="suppressed" {{ request('status') === 'suppressed' ? 'selected' : '' }}>🛡️ Suprimidas</option>
                    </select>
                    @if(request('search') || request('severity') || request('status'))
                        <a href="{{ route('admin.alerts.index', ['tab' => 'active']) }}" class="p-1.5 rounded-lg bg-slate-800 text-slate-400 hover:text-white transition" title="Limpiar filtros">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </a>
                    @endif
                </form>
                <div class="text-[11px] font-mono text-slate-400">
                    Total activas: <span class="text-obsidian-cyan font-bold">{{ $activeAlerts->total() }}</span>
                </div>
            </div>

            <!-- TABLA DE ALERTAS ACTIVAS -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-obsidian-panel/80 text-[11px] font-mono text-slate-400 uppercase tracking-wider border-b border-obsidian-border">
                            <th class="px-2.5 py-2 w-12 text-center">ID</th>
                            <th class="px-2.5 py-2 w-28">Severidad</th>
                            <th class="px-2.5 py-2 w-28">Estado</th>
                            <th class="px-2.5 py-2">Entidad Monitoreada</th>
                            <th class="px-2.5 py-2">Condición / Detalle</th>
                            <th class="px-2.5 py-2 w-24 text-center">Nivel Esc.</th>
                            <th class="px-2.5 py-2 w-32 font-mono">Disparo</th>
                            <th class="px-2.5 py-2 w-24 font-mono">Duración</th>
                            <th class="px-2.5 py-2 w-36 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-obsidian-border/50">
                        @forelse($activeAlerts as $a)
                            @php
                                $sevColors = [
                                    'emergency' => 'bg-fuchsia-500/10 text-fuchsia-400 border-fuchsia-500/30',
                                    'critical' => 'bg-red-500/10 text-red-400 border-red-500/30',
                                    'warning' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                                    'info' => 'bg-sky-500/10 text-sky-400 border-sky-500/30',
                                ];
                                $sevColor = $sevColors[$a->severity] ?? 'bg-slate-500/10 text-slate-400 border-slate-500/30';

                                $statColors = [
                                    'firing' => 'bg-red-500/15 text-red-300 border-red-500/40 animate-pulse',
                                    'acknowledged' => 'bg-sky-500/15 text-sky-300 border-sky-500/40',
                                    'suppressed' => 'bg-purple-500/15 text-purple-300 border-purple-500/40',
                                ];
                                $statColor = $statColors[$a->status] ?? 'bg-slate-700 text-slate-300 border-slate-600';
                            @endphp
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="px-2.5 py-1.5 text-center font-mono text-[11px] text-slate-400">#{{ $a->id }}</td>
                                <td class="px-2.5 py-1.5">
                                    <span class="px-2 py-0.5 rounded-full border text-[10px] font-mono uppercase font-bold inline-flex items-center gap-1 {{ $sevColor }}" title="Severidad: {{ $a->severity_label }}">
                                        {{ $a->severity_label }}
                                    </span>
                                </td>
                                <td class="px-2.5 py-1.5">
                                    <span class="px-2 py-0.5 rounded-full border text-[10px] font-mono uppercase font-semibold inline-flex items-center gap-1 {{ $statColor }}" title="Estado del incidente">
                                        {{ $a->status_label }}
                                    </span>
                                </td>
                                <td class="px-2.5 py-1.5">
                                    <div class="font-bold text-white text-[11.5px] truncate max-w-xs" title="{{ $a->entity_name }}">
                                        {{ $a->entity_name }}
                                    </div>
                                    <div class="text-[10px] font-mono text-slate-400 flex items-center gap-1">
                                        <span class="text-obsidian-cyan">{{ $a->entity_type_label }}</span>
                                        @if($a->is_correlated_suppressed)
                                            <span class="px-1 py-0.2 bg-purple-900/60 border border-purple-500/40 text-purple-300 rounded text-[9px]" title="Suprimido por caída de entidad padre">
                                                🔗 Correlacionado
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-2.5 py-1.5">
                                    <div class="font-mono text-[11px] text-slate-200">
                                        {{ $a->condition_label }}
                                    </div>
                                    <div class="text-[10.5px] text-slate-400 truncate max-w-sm" title="{{ $a->message }}">
                                        {{ $a->message }}
                                    </div>
                                </td>
                                <td class="px-2.5 py-1.5 text-center">
                                    <span class="px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 text-slate-300 font-mono text-[10px]" title="Nivel actual de escalación alcanzado">
                                        Nivel {{ $a->current_escalation_level }}
                                    </span>
                                </td>
                                <td class="px-2.5 py-1.5 font-mono text-[10.5px] text-slate-300">
                                    {{ $a->fired_at ? $a->fired_at->timezone('America/Caracas')->format('d/m/Y h:i A') : 'N/A' }}
                                </td>
                                <td class="px-2.5 py-1.5 font-mono text-[10.5px] text-amber-300 font-bold">
                                    {{ $a->duration_formatted }}
                                </td>
                                <td class="px-2.5 py-1.5 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Ver Detalle / Timeline -->
                                        <button onclick="openAlertDetails({{ $a->id }})" 
                                                class="p-1 rounded bg-obsidian-panel border border-cyan-500/40 text-cyan-400 hover:bg-cyan-500 hover:text-black transition"
                                                title="Ver Línea de Tiempo y Notificaciones">
                                            <span class="material-symbols-outlined text-[13px]">visibility</span>
                                        </button>

                                        <!-- Reconocer (Ack) -->
                                        @if($a->status === 'firing')
                                            <button onclick="openAckModal({{ $a->id }}, '{{ addslashes($a->entity_name) }}')"
                                                    class="p-1 rounded bg-obsidian-panel border border-sky-500/40 text-sky-400 hover:bg-sky-500 hover:text-black transition"
                                                    title="Reconocer Incidente (ACK)">
                                                <span class="material-symbols-outlined text-[13px]">check_circle</span>
                                            </button>
                                        @endif

                                        <!-- Silenciar (Silence) -->
                                        @if($a->status !== 'suppressed')
                                            <button onclick="openSilenceModal({{ $a->id }}, '{{ addslashes($a->entity_name) }}')"
                                                    class="p-1 rounded bg-obsidian-panel border border-purple-500/40 text-purple-300 hover:bg-purple-500 hover:text-white transition"
                                                    title="Silenciar Alerta Temporalmente">
                                                <span class="material-symbols-outlined text-[13px]">notifications_off</span>
                                            </button>
                                        @endif

                                        <!-- Resolver (Admin Only) -->
                                        @if(auth()->user()->isAdmin())
                                            <form action="{{ route('admin.alerts.resolve', $a->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Está seguro de marcar como resuelta manualmente la alerta #{{ $a->id }} ({{ addslashes($a->entity_name) }})?');">
                                                @csrf
                                                <button type="submit" 
                                                        class="p-1 rounded bg-obsidian-panel border border-emerald-500/40 text-emerald-400 hover:bg-emerald-500 hover:text-black transition"
                                                        title="Resolver Manualmente el Incidente">
                                                    <span class="material-symbols-outlined text-[13px]">done_all</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-slate-500 font-mono text-xs">
                                    <div class="flex flex-col items-center justify-center gap-1.5">
                                        <span class="material-symbols-outlined text-3xl text-emerald-400">check_circle</span>
                                        <span>No hay incidentes activos en este momento. Todos los servicios e infraestructura operan normalmente.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- PAGINACIÓN OBSIDIAN DARK -->
            @if($activeAlerts->hasPages())
                <div class="px-3.5 py-2.5 bg-obsidian-panel border-t border-obsidian-border/80 flex flex-col sm:flex-row items-center justify-between gap-2.5 text-xs font-mono">
                    <div class="text-obsidian-muted text-[10.5px]">
                        Mostrando <span class="text-white font-bold">{{ $activeAlerts->firstItem() }}</span> a <span class="text-white font-bold">{{ $activeAlerts->lastItem() }}</span> de <span class="text-cyan-400 font-bold">{{ $activeAlerts->total() }}</span> incidentes
                    </div>
                    <div class="flex items-center gap-1">
                        @if($activeAlerts->onFirstPage())
                            <span class="px-2 py-0.5 rounded bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[10.5px]">&laquo; Anterior</span>
                        @else
                            <a href="{{ $activeAlerts->previousPageUrl() }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[10.5px]">&laquo; Anterior</a>
                        @endif

                        @foreach($activeAlerts->getUrlRange(max(1, $activeAlerts->currentPage() - 2), min($activeAlerts->lastPage(), $activeAlerts->currentPage() + 2)) as $page => $url)
                            @if($page == $activeAlerts->currentPage())
                                <span class="px-2 py-0.5 rounded bg-cyan-500 text-black font-bold text-[10.5px]">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-slate-300 hover:bg-slate-700 transition text-[10.5px]">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if($activeAlerts->hasMorePages())
                            <a href="{{ $activeAlerts->nextPageUrl() }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[10.5px]">Siguiente &raquo;</a>
                        @else
                            <span class="px-2 py-0.5 rounded bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[10.5px]">Siguiente &raquo;</span>
                        @endif
                    </div>
                </div>
            @endif

        <!-- TAB 2: HISTORIAL DE INCIDENTES RESUELTOS -->
        @elseif($tab === 'history')
            <div class="p-3 border-b border-obsidian-border/80 bg-slate-900/40 flex flex-wrap items-center justify-between gap-2.5">
                <form method="GET" action="{{ route('admin.alerts.index') }}" class="flex items-center gap-2 text-xs">
                    <input type="hidden" name="tab" value="history">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm">search</span>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar en histórico..." class="pl-8 pr-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white text-xs placeholder-slate-500 focus:outline-none focus:border-obsidian-cyan w-64 font-mono">
                    </div>
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-200 hover:bg-slate-700 transition text-xs font-mono">Filtrar</button>
                </form>
                <div class="text-[11px] font-mono text-slate-400">
                    Incidentes resueltos: <span class="text-emerald-400 font-bold">{{ $historyAlerts->total() }}</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-obsidian-panel/80 text-[11px] font-mono text-slate-400 uppercase tracking-wider border-b border-obsidian-border">
                            <th class="px-2.5 py-2 w-12 text-center">ID</th>
                            <th class="px-2.5 py-2 w-24">Severidad</th>
                            <th class="px-2.5 py-2">Entidad Monitoreada</th>
                            <th class="px-2.5 py-2">Condición / Causa</th>
                            <th class="px-2.5 py-2 w-32 font-mono">Disparada</th>
                            <th class="px-2.5 py-2 w-32 font-mono">Resuelta</th>
                            <th class="px-2.5 py-2 w-24 font-mono">Duración</th>
                            <th class="px-2.5 py-2 w-28">Resolución</th>
                            <th class="px-2.5 py-2 w-16 text-center">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-obsidian-border/50">
                        @forelse($historyAlerts as $h)
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="px-2.5 py-1.5 text-center font-mono text-[11px] text-slate-400">#{{ $h->id }}</td>
                                <td class="px-2.5 py-1.5">
                                    <span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono uppercase font-bold border 
                                          {{ $h->severity === 'critical' || $h->severity === 'emergency' ? 'bg-red-500/10 text-red-400 border-red-500/30' : 'bg-amber-500/10 text-amber-400 border-amber-500/30' }}">
                                        {{ $h->severity_label }}
                                    </span>
                                </td>
                                <td class="px-2.5 py-1.5">
                                    <div class="font-bold text-white text-[11.5px]">{{ $h->entity_name }}</div>
                                    <div class="text-[10px] font-mono text-slate-400">{{ $h->entity_type_label }}</div>
                                </td>
                                <td class="px-2.5 py-1.5 font-mono text-[11px] text-slate-300">
                                    {{ $h->condition_label }}
                                </td>
                                <td class="px-2.5 py-1.5 font-mono text-[10px] text-slate-400">
                                    {{ $h->fired_at ? $h->fired_at->timezone('America/Caracas')->format('d/m/Y h:i A') : 'N/A' }}
                                </td>
                                <td class="px-2.5 py-1.5 font-mono text-[10px] text-emerald-400">
                                    {{ $h->resolved_at ? $h->resolved_at->timezone('America/Caracas')->format('d/m/Y h:i A') : 'N/A' }}
                                </td>
                                <td class="px-2.5 py-1.5 font-mono text-[10.5px] text-slate-200">
                                    {{ $h->duration_formatted }}
                                </td>
                                <td class="px-2.5 py-1.5">
                                    <span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono {{ $h->resolved_by === 'auto' ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30' : 'bg-sky-500/10 text-sky-300 border border-sky-500/30' }}">
                                        {{ $h->resolved_by === 'auto' ? 'Auto-Resuelta' : 'Manual' }}
                                    </span>
                                </td>
                                <td class="px-2.5 py-1.5 text-center">
                                    <button onclick="openAlertDetails({{ $h->id }})" class="p-1 rounded bg-obsidian-panel border border-slate-700 text-slate-300 hover:text-white transition" title="Ver Detalle">
                                        <span class="material-symbols-outlined text-[13px]">visibility</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-slate-500 font-mono text-xs">
                                    No se registran incidentes previos en el histórico.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($historyAlerts->hasPages())
                <div class="px-3.5 py-2.5 bg-obsidian-panel border-t border-obsidian-border/80 flex items-center justify-between text-xs font-mono">
                    <div class="text-slate-400 text-[10.5px]">
                        Mostrando {{ $historyAlerts->firstItem() }} a {{ $historyAlerts->lastItem() }} de {{ $historyAlerts->total() }} registros
                    </div>
                    <div>
                        {{ $historyAlerts->appends(['tab' => 'history'])->links() }}
                    </div>
                </div>
            @endif

        <!-- TAB 3: REGLAS DE ALERTA -->
        @elseif($tab === 'rules')
            <div class="p-3 border-b border-obsidian-border/80 bg-slate-900/40 flex items-center justify-between">
                <div class="text-xs text-slate-400">
                    Reglas corporativas activas de umbrales y monitoreo continuo.
                </div>
                @if(auth()->user()->isAdmin())
                    <button onclick="openNewRuleModal()" class="px-2.5 py-1 rounded bg-emerald-500/10 border border-emerald-500/40 text-emerald-400 hover:bg-emerald-500 hover:text-black text-xs font-mono font-semibold transition flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">add</span> Crear Regla
                    </button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-obsidian-panel/80 text-[11px] font-mono text-slate-400 uppercase tracking-wider border-b border-obsidian-border">
                            <th class="px-2.5 py-2 w-12 text-center">ID</th>
                            <th class="px-2.5 py-2">Nombre de Regla</th>
                            <th class="px-2.5 py-2">Entidad / Condición</th>
                            <th class="px-2.5 py-2 w-28">Umbral</th>
                            <th class="px-2.5 py-2 w-24">Severidad</th>
                            <th class="px-2.5 py-2 w-24">Cooldown</th>
                            <th class="px-2.5 py-2 w-24 text-center">Auto-Resolve</th>
                            <th class="px-2.5 py-2">Escalación (Telegram)</th>
                            @if(auth()->user()->isAdmin())
                                <th class="px-2.5 py-2 w-16 text-center">Acción</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-obsidian-border/50">
                        @foreach($rules as $r)
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="px-2.5 py-2 text-center font-mono text-slate-400">#{{ $r->id }}</td>
                                <td class="px-2.5 py-2">
                                    <div class="font-bold text-white">{{ $r->name }}</div>
                                    <div class="text-[10px] text-slate-400 truncate max-w-xs">{{ $r->description }}</div>
                                </td>
                                <td class="px-2.5 py-2 font-mono">
                                    <span class="text-obsidian-cyan">{{ $r->entity_type_label }}</span> &rarr; <span class="text-amber-300">{{ $r->condition_label }}</span>
                                </td>
                                <td class="px-2.5 py-2 font-mono text-slate-300">
                                    @if($r->threshold_value !== null)
                                         {{ $r->comparison }} {{ (float)$r->threshold_value }}
                                         @if($r->duration_seconds > 0)
                                             <span class="text-[9.5px] text-slate-400">({{ $r->duration_seconds }}s)</span>
                                         @endif
                                    @else
                                        <span class="text-slate-500">Estado</span>
                                    @endif
                                </td>
                                <td class="px-2.5 py-2 font-mono uppercase font-bold text-[10px]">
                                    <span class="px-1.5 py-0.5 rounded border 
                                           {{ $r->severity === 'critical' || $r->severity === 'emergency' ? 'bg-red-500/10 text-red-400 border-red-500/30' : 'bg-amber-500/10 text-amber-400 border-amber-500/30' }}" title="Severidad: {{ $r->severity_label }}">
                                        {{ $r->severity_label }}
                                    </span>
                                </td>
                                <td class="px-2.5 py-2 font-mono text-[10.5px] text-slate-300">
                                    {{ $r->cooldown_minutes }} min
                                </td>
                                <td class="px-2.5 py-2 text-center">
                                    @if($r->auto_resolve)
                                        <span class="text-emerald-400 material-symbols-outlined text-base" title="Auto-resolución activada">check_circle</span>
                                    @else
                                        <span class="text-slate-600 material-symbols-outlined text-base" title="Resolución manual requerida">cancel</span>
                                    @endif
                                </td>
                                <td class="px-2.5 py-2">
                                    <div class="flex items-center gap-1 font-mono text-[10px]">
                                        @foreach($r->escalationLevels as $el)
                                            <span class="px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 text-slate-300" title="Nivel {{ $el->level }}: retraso {{ $el->delay_minutes }}m hacia Chat ID {{ $el->target_external }}">
                                                L{{ $el->level }} ({{ $el->delay_minutes }}m)
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                @if(auth()->user()->isAdmin())
                                    <td class="px-2.5 py-2 text-center">
                                        <form action="{{ route('admin.alerts.rules.destroy', $r->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar la regla {{ addslashes($r->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 rounded bg-obsidian-panel border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition" title="Eliminar regla">
                                                <span class="material-symbols-outlined text-[13px]">delete</span>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        <!-- TAB 4: VENTANAS DE MANTENIMIENTO -->
        @elseif($tab === 'maintenance')
            <div class="p-3 border-b border-obsidian-border/80 bg-slate-900/40 flex items-center justify-between">
                <div class="text-xs text-slate-400">
                    Ventanas planificadas para silenciar alertas durante paradas o mantenimientos de red.
                </div>
                @if(auth()->user()->isAdmin())
                    <button onclick="openMaintenanceModal()" class="px-2.5 py-1 rounded bg-purple-500/10 border border-purple-500/40 text-purple-300 hover:bg-purple-500 hover:text-white text-xs font-mono font-semibold transition flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">add</span> Programar Mantenimiento
                    </button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-obsidian-panel/80 text-[11px] font-mono text-slate-400 uppercase tracking-wider border-b border-obsidian-border">
                            <th class="px-2.5 py-2 w-12 text-center">ID</th>
                            <th class="px-2.5 py-2">Título / Motivo</th>
                            <th class="px-2.5 py-2">Entidad Afectada</th>
                            <th class="px-2.5 py-2 w-36 font-mono">Inicio</th>
                            <th class="px-2.5 py-2 w-36 font-mono">Fin</th>
                            <th class="px-2.5 py-2 w-28 text-center">Estado</th>
                            <th class="px-2.5 py-2 w-32">Programado Por</th>
                            @if(auth()->user()->isAdmin())
                                <th class="px-2.5 py-2 w-16 text-center">Acción</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-obsidian-border/50">
                        @forelse($maintenanceWindows as $mw)
                            @php
                                $isRunning = $mw->isCurrentlyRunning();
                            @endphp
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="px-2.5 py-2 text-center font-mono text-slate-400">#{{ $mw->id }}</td>
                                <td class="px-2.5 py-2">
                                    <div class="font-bold text-white">{{ $mw->title }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $mw->description }}</div>
                                </td>
                                <td class="px-2.5 py-2 font-mono text-obsidian-cyan">
                                    {{ $mw->entity_type }} {{ $mw->entity_id ? '#' . $mw->entity_id : '(Todos)' }}
                                </td>
                                <td class="px-2.5 py-2 font-mono text-[10.5px] text-slate-300">
                                    {{ $mw->starts_at ? $mw->starts_at->timezone('America/Caracas')->format('d/m/Y h:i A') : 'N/A' }}
                                </td>
                                <td class="px-2.5 py-2 font-mono text-[10.5px] text-slate-300">
                                    {{ $mw->ends_at ? $mw->ends_at->timezone('America/Caracas')->format('d/m/Y h:i A') : 'N/A' }}
                                </td>
                                <td class="px-2.5 py-2 text-center">
                                    @if($isRunning)
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-mono text-[10px] font-bold animate-pulse inline-flex items-center gap-1">
                                            🟢 En Curso
                                        </span>
                                    @elseif($mw->ends_at && $mw->ends_at->isPast())
                                        <span class="px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-500 font-mono text-[10px]">
                                            Finalizado
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 font-mono text-[10px]">
                                            Programado
                                        </span>
                                    @endif
                                </td>
                                <td class="px-2.5 py-2 text-slate-300 font-mono text-[11px]">
                                    {{ $mw->creator ? $mw->creator->name : 'Sistema' }}
                                </td>
                                @if(auth()->user()->isAdmin())
                                    <td class="px-2.5 py-2 text-center">
                                        <form action="{{ route('admin.alerts.maintenance.destroy', $mw->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta ventana de mantenimiento?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 rounded bg-obsidian-panel border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition" title="Eliminar ventana">
                                                <span class="material-symbols-outlined text-[13px]">delete</span>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-500 font-mono text-xs">
                                    No hay ventanas de mantenimiento programadas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        <!-- TAB 5: TOPOLOGÍA Y CORRELACIÓN -->
        @elseif($tab === 'correlation')
            <div class="p-3 border-b border-obsidian-border/80 bg-slate-900/40 flex items-center justify-between">
                <div class="text-xs text-slate-400">
                    Grupos de correlación topológica padre-hijo (supresión por cascada y reducción de severidad).
                </div>
                @if(auth()->user()->isAdmin())
                    <button onclick="openCorrelationModal()" class="px-2.5 py-1 rounded bg-cyan-500/10 border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black text-xs font-mono font-semibold transition flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">add</span> Crear Grupo de Correlación
                    </button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-obsidian-panel/80 text-[11px] font-mono text-slate-400 uppercase tracking-wider border-b border-obsidian-border">
                            <th class="px-2.5 py-2 w-12 text-center">ID</th>
                            <th class="px-2.5 py-2">Grupo de Correlación</th>
                            <th class="px-2.5 py-2">Entidad Padre</th>
                            <th class="px-2.5 py-2">Estrategia</th>
                            <th class="px-2.5 py-2 text-center">Miembros Hijos</th>
                            <th class="px-2.5 py-2 w-24 text-center">Estado</th>
                            @if(auth()->user()->isAdmin())
                                <th class="px-2.5 py-2 w-16 text-center">Acción</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-obsidian-border/50">
                        @forelse($correlationGroups as $cg)
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="px-2.5 py-2 text-center font-mono text-slate-400">#{{ $cg->id }}</td>
                                <td class="px-2.5 py-2">
                                    <div class="font-bold text-white">{{ $cg->name }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $cg->description }}</div>
                                </td>
                                <td class="px-2.5 py-2 font-mono">
                                    <span class="text-obsidian-cyan font-bold">{{ $cg->parent_entity_type }}</span> #{{ $cg->parent_entity_id }}
                                </td>
                                <td class="px-2.5 py-2 font-mono text-amber-300">
                                    @if($cg->suppression_strategy === 'suppress_if_parent_down')
                                        Suprimir hijas si padre cae
                                    @elseif($cg->suppression_strategy === 'reduce_severity')
                                        Reducir severidad de hijas
                                    @elseif($cg->suppression_strategy === 'suppress_all')
                                        Suprimir todas las hijas
                                    @else
                                        {{ $cg->suppression_strategy }}
                                    @endif
                                </td>
                                <td class="px-2.5 py-2 text-center">
                                    <span class="px-2 py-0.5 rounded bg-slate-800 border border-slate-700 text-slate-300 font-mono text-xs">
                                        {{ $cg->members->count() }} entidades hijas
                                    </span>
                                </td>
                                <td class="px-2.5 py-2 text-center">
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-mono text-[10px]">
                                        Activo
                                    </span>
                                </td>
                                @if(auth()->user()->isAdmin())
                                    <td class="px-2.5 py-2 text-center">
                                        <form action="{{ route('admin.alerts.correlation.destroy', $cg->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este grupo de correlación?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 rounded bg-obsidian-panel border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition" title="Eliminar correlación">
                                                <span class="material-symbols-outlined text-[13px]">delete</span>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500 font-mono text-xs">
                                    No hay grupos de correlación configurados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODALES DEL SISTEMA DE ALERTAS -->
<!-- ========================================================================= -->

<!-- 1. MODAL: DETALLE Y LÍNEA DE TIEMPO DE ALERTA -->
<div id="modal-alert-details" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-obsidian-card border border-obsidian-border rounded-xl w-full max-w-2xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-fadeIn">
        <div class="p-3.5 border-b border-obsidian-border flex items-center justify-between bg-slate-900/60">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan text-xl">timeline</span>
                <h3 class="text-sm font-bold text-white tracking-wide" id="modal-alert-title">Detalle del Incidente</h3>
            </div>
            <button onclick="closeModal('modal-alert-details')" class="text-slate-400 hover:text-white transition p-1">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <div class="p-4 overflow-y-auto space-y-4 text-xs font-mono" id="modal-alert-content">
            <div class="text-center py-6 text-slate-400">Cargando telemetría...</div>
        </div>
    </div>
</div>

<!-- 2. MODAL: RECONOCER ALERTA (ACK) -->
<div id="modal-ack" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-obsidian-card border border-obsidian-border rounded-xl w-full max-w-md p-4 shadow-2xl animate-fadeIn">
        <div class="flex items-center justify-between pb-3 border-b border-obsidian-border">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-sky-400">check_circle</span> Reconocer Incidente (ACK)
            </h3>
            <button onclick="closeModal('modal-ack')" class="text-slate-400 hover:text-white">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <form id="form-ack" method="POST" class="mt-3 space-y-3">
            @csrf
            <div>
                <label class="block text-[11px] font-mono text-slate-400 mb-1">Entidad:</label>
                <div id="ack-entity-name" class="p-2 rounded bg-obsidian-panel border border-obsidian-border text-white font-bold text-xs"></div>
            </div>
            <div>
                <label class="block text-[11px] font-mono text-slate-400 mb-1">Notas de atención / diagnóstico:</label>
                <textarea name="notes" rows="3" class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-sky-400 font-mono" placeholder="Ej: Atendiendo incidente, reiniciando interfaz..."></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('modal-ack')" class="px-3 py-1.5 rounded bg-slate-800 text-slate-300 hover:bg-slate-700 text-xs font-mono">Cancelar</button>
                <button type="submit" class="px-4 py-1.5 rounded bg-sky-500 text-black font-bold hover:bg-sky-400 text-xs font-mono">Confirmar Reconocimiento</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. MODAL: SILENCIAR ALERTA (SILENCE) -->
<div id="modal-silence" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-obsidian-card border border-obsidian-border rounded-xl w-full max-w-md p-4 shadow-2xl animate-fadeIn">
        <div class="flex items-center justify-between pb-3 border-b border-obsidian-border">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-400">notifications_off</span> Silenciar Alerta
            </h3>
            <button onclick="closeModal('modal-silence')" class="text-slate-400 hover:text-white">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <form id="form-silence" method="POST" class="mt-3 space-y-3">
            @csrf
            <div>
                <label class="block text-[11px] font-mono text-slate-400 mb-1">Entidad:</label>
                <div id="silence-entity-name" class="p-2 rounded bg-obsidian-panel border border-obsidian-border text-white font-bold text-xs"></div>
            </div>
            <div>
                <label class="block text-[11px] font-mono text-slate-400 mb-1">Duración del silencio:</label>
                <select name="minutes" class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-purple-400 font-mono">
                    <option value="15">15 Minutos</option>
                    <option value="30">30 Minutos</option>
                    <option value="60" selected>1 Hora (60 min)</option>
                    <option value="120">2 Horas (120 min)</option>
                    <option value="240">4 Horas (240 min)</option>
                    <option value="1440">24 Horas (1 día)</option>
                </select>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('modal-silence')" class="px-3 py-1.5 rounded bg-slate-800 text-slate-300 hover:bg-slate-700 text-xs font-mono">Cancelar</button>
                <button type="submit" class="px-4 py-1.5 rounded bg-purple-500 text-white font-bold hover:bg-purple-400 text-xs font-mono">Silenciar Incidente</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. MODAL: NUEVA REGLA (ADMIN ONLY) -->
@if(auth()->user()->isAdmin())
<div id="modal-new-rule" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-obsidian-card border border-obsidian-border rounded-xl w-full max-w-lg p-4 shadow-2xl animate-fadeIn max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-obsidian-border">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-400">add_alert</span> Nueva Regla de Alerta
            </h3>
            <button onclick="closeModal('modal-new-rule')" class="text-slate-400 hover:text-white">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <form action="{{ route('admin.alerts.rules.store') }}" method="POST" class="mt-3 space-y-3 text-xs">
            @csrf
            <div>
                <label class="block text-[11px] font-mono text-slate-400 mb-1">Nombre de la Regla:</label>
                <input type="text" name="name" required placeholder="Ej: Latencia Crítica en Base de Datos" class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-emerald-400 font-mono">
            </div>
            <div>
                <label class="block text-[11px] font-mono text-slate-400 mb-1">Descripción Técnica:</label>
                <input type="text" name="description" placeholder="Explicación del criterio de disparo..." class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-emerald-400 font-mono">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-mono text-slate-400 mb-1">Tipo de Entidad:</label>
                    <select name="entity_type" required class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-emerald-400 font-mono">
                        <option value="service">Servicio Web/TCP</option>
                        <option value="site">Sede WAN</option>
                        <option value="snmp_device">Dispositivo SNMP</option>
                        <option value="interface">Interfaz de Red</option>
                        <option value="ssl_certificate">Certificado SSL/TLS</option>
                        <option value="discovered_device">Dispositivo Descubierto</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-mono text-slate-400 mb-1">Condición:</label>
                    <select name="condition_type" required class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-emerald-400 font-mono">
                        <option value="is_down">Caído / Inalcanzable (is_down)</option>
                        <option value="latency_high">Latencia Alta</option>
                        <option value="packet_loss_high">Pérdida de Paquetes</option>
                        <option value="cpu_high">Uso de CPU Alto</option>
                        <option value="memory_high">Memoria RAM Alta</option>
                        <option value="temperature_high">Temperatura Elevada</option>
                        <option value="cert_expiring">Certificado por Vencer</option>
                        <option value="cert_expired">Certificado Vencido</option>
                        <option value="interface_down">Interfaz Caída</option>
                        <option value="new_device">Dispositivo no Autorizado (Rogue)</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="block text-[11px] font-mono text-slate-400 mb-1">Comparador:</label>
                    <select name="comparison" class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-emerald-400 font-mono">
                        <option value="gt">&gt; Mayor que</option>
                        <option value="gte">&gt;= Mayor o igual</option>
                        <option value="lt">&lt; Menor que</option>
                        <option value="lte">&lt;= Menor o igual</option>
                        <option value="eq">= Igual a</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-mono text-slate-400 mb-1">Umbral:</label>
                    <input type="number" step="0.01" name="threshold_value" placeholder="Ej: 500" class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-emerald-400 font-mono">
                </div>
                <div>
                    <label class="block text-[11px] font-mono text-slate-400 mb-1">Severidad:</label>
                    <select name="severity" required class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-emerald-400 font-mono">
                        <option value="info">ℹ️ Información</option>
                        <option value="warning" selected>⚠️ Advertencia</option>
                        <option value="critical">🚨 Crítica</option>
                        <option value="emergency">🆘 Emergencia</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-mono text-slate-400 mb-1">Cooldown (Minutos):</label>
                    <input type="number" name="cooldown_minutes" value="30" class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-emerald-400 font-mono">
                </div>
                <div>
                    <label class="block text-[11px] font-mono text-slate-400 mb-1">Max Alertas / Hora:</label>
                    <input type="number" name="max_alerts_per_hour" value="5" class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-emerald-400 font-mono">
                </div>
            </div>
            <div class="flex items-center gap-4 pt-1">
                <label class="flex items-center gap-2 text-xs font-mono text-slate-300 cursor-pointer">
                    <input type="checkbox" name="auto_resolve" value="1" checked class="rounded bg-obsidian-panel border-obsidian-border text-emerald-500">
                    Auto-resolver al normalizar
                </label>
                <label class="flex items-center gap-2 text-xs font-mono text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded bg-obsidian-panel border-obsidian-border text-emerald-500">
                    Regla Activa
                </label>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-obsidian-border">
                <button type="button" onclick="closeModal('modal-new-rule')" class="px-3 py-1.5 rounded bg-slate-800 text-slate-300 hover:bg-slate-700 text-xs font-mono">Cancelar</button>
                <button type="submit" class="px-4 py-1.5 rounded bg-emerald-500 text-black font-bold hover:bg-emerald-400 text-xs font-mono">Guardar Regla</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. MODAL: PROGRAMAR MANTENIMIENTO -->
<div id="modal-new-maintenance" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-obsidian-card border border-obsidian-border rounded-xl w-full max-w-lg p-4 shadow-2xl animate-fadeIn">
        <div class="flex items-center justify-between pb-3 border-b border-obsidian-border">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-400">construction</span> Programar Mantenimiento
            </h3>
            <button onclick="closeModal('modal-new-maintenance')" class="text-slate-400 hover:text-white">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <form action="{{ route('admin.alerts.maintenance.store') }}" method="POST" class="mt-3 space-y-3 text-xs">
            @csrf
            <div>
                <label class="block text-[11px] font-mono text-slate-400 mb-1">Título de la Ventana:</label>
                <input type="text" name="title" required placeholder="Ej: Mantenimiento Enlace Fibra Óptica" class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-purple-400 font-mono">
            </div>
            <div>
                <label class="block text-[11px] font-mono text-slate-400 mb-1">Descripción:</label>
                <input type="text" name="description" placeholder="Detalle de las labores..." class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-purple-400 font-mono">
            </div>
            <div>
                <label class="block text-[11px] font-mono text-slate-400 mb-1">Entidad Afectada:</label>
                <select name="entity_type" required class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-purple-400 font-mono">
                    <option value="all">Todas las entidades (Mantenimiento Global)</option>
                    <option value="site">Sedes WAN</option>
                    <option value="service">Servicios</option>
                    <option value="snmp_device">Dispositivos SNMP</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-mono text-slate-400 mb-1">Fecha y Hora de Inicio:</label>
                    <input type="datetime-local" name="starts_at" required class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-purple-400 font-mono">
                </div>
                <div>
                    <label class="block text-[11px] font-mono text-slate-400 mb-1">Fecha y Hora de Fin:</label>
                    <input type="datetime-local" name="ends_at" required class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-purple-400 font-mono">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-obsidian-border">
                <button type="button" onclick="closeModal('modal-new-maintenance')" class="px-3 py-1.5 rounded bg-slate-800 text-slate-300 hover:bg-slate-700 text-xs font-mono">Cancelar</button>
                <button type="submit" class="px-4 py-1.5 rounded bg-purple-500 text-white font-bold hover:bg-purple-400 text-xs font-mono">Guardar Ventana</button>
            </div>
        </form>
    </div>
</div>

<!-- 6. MODAL: NUEVA CORRELACIÓN TOPOLÓGICA -->
<div id="modal-new-correlation" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-obsidian-card border border-obsidian-border rounded-xl w-full max-w-lg p-4 shadow-2xl animate-fadeIn">
        <div class="flex items-center justify-between pb-3 border-b border-obsidian-border">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400">account_tree</span> Nuevo Grupo de Correlación
            </h3>
            <button onclick="closeModal('modal-new-correlation')" class="text-slate-400 hover:text-white">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <form action="{{ route('admin.alerts.correlation.store') }}" method="POST" class="mt-3 space-y-3 text-xs">
            @csrf
            <div>
                <label class="block text-[11px] font-mono text-slate-400 mb-1">Nombre del Grupo:</label>
                <input type="text" name="name" required placeholder="Ej: Router Core -> Servicios Locales" class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-400 font-mono">
            </div>
            <div>
                <label class="block text-[11px] font-mono text-slate-400 mb-1">Descripción:</label>
                <input type="text" name="description" placeholder="Detalles de topología..." class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-400 font-mono">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-mono text-slate-400 mb-1">Tipo de Entidad Padre:</label>
                    <select name="parent_entity_type" required class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-400 font-mono">
                        <option value="site">Sede WAN</option>
                        <option value="snmp_device">Dispositivo SNMP</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-mono text-slate-400 mb-1">ID Entidad Padre:</label>
                    <input type="number" name="parent_entity_id" required placeholder="Ej: 1" class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-400 font-mono">
                </div>
            </div>
            <div>
                <label class="block text-[11px] font-mono text-slate-400 mb-1">Estrategia de Supresión:</label>
                <select name="suppression_strategy" required class="w-full p-2 rounded bg-obsidian-panel border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-400 font-mono">
                    <option value="suppress_if_parent_down">Suprimir alertas hijas si padre está caído</option>
                    <option value="reduce_severity">Reducir severidad de hijas</option>
                    <option value="suppress_all">Suprimir todas las hijas</option>
                </select>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-obsidian-border">
                <button type="button" onclick="closeModal('modal-new-correlation')" class="px-3 py-1.5 rounded bg-slate-800 text-slate-300 hover:bg-slate-700 text-xs font-mono">Cancelar</button>
                <button type="submit" class="px-4 py-1.5 rounded bg-cyan-500 text-black font-bold hover:bg-cyan-400 text-xs font-mono">Guardar Correlación</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }
    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    function openNewRuleModal() {
        openModal('modal-new-rule');
    }
    function openMaintenanceModal() {
        openModal('modal-new-maintenance');
    }
    function openCorrelationModal() {
        openModal('modal-new-correlation');
    }

    function openAckModal(id, name) {
        document.getElementById('ack-entity-name').innerText = name + ' (ID #' + id + ')';
        document.getElementById('form-ack').action = '/admin/alerts/' + id + '/ack';
        openModal('modal-ack');
    }

    function openSilenceModal(id, name) {
        document.getElementById('silence-entity-name').innerText = name + ' (ID #' + id + ')';
        document.getElementById('form-silence').action = '/admin/alerts/' + id + '/silence';
        openModal('modal-silence');
    }

    function openAlertDetails(id) {
        const container = document.getElementById('modal-alert-content');
        container.innerHTML = '<div class="text-center py-6 text-slate-400">Cargando telemetría...</div>';
        openModal('modal-alert-details');

        fetch('/admin/alerts/' + id)
            .then(res => res.json())
            .then(data => {
                document.getElementById('modal-alert-title').innerText = 'Incidente #' + data.id + ' — ' + data.entity_name;

                let notifsHtml = '';
                if (data.notifications && data.notifications.length > 0) {
                    notifsHtml = '<div class="space-y-2 mt-2">';
                    data.notifications.forEach(n => {
                        const isSent = n.status === 'sent';
                        const statusLabel = isSent ? 'ENVIADO' : (n.status === 'failed' ? 'FALLIDO' : (n.status === 'pending' ? 'PENDIENTE' : n.status.toUpperCase()));
                        notifsHtml += `
                            <div class="p-2 rounded bg-slate-900 border border-obsidian-border text-[11px] flex flex-col gap-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-200">Canal: ${n.channel.toUpperCase()} &rarr; ${n.target}</span>
                                    <span class="px-1.5 py-0.2 rounded text-[9.5px] ${isSent ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-red-500/10 text-red-400 border border-red-500/30'}">
                                        ${statusLabel} (${n.sent_at})
                                    </span>
                                </div>
                                <div class="text-[10px] text-slate-400 truncate">${n.message_preview}</div>
                            </div>
                        `;
                    });
                    notifsHtml += '</div>';
                } else {
                    notifsHtml = '<div class="text-slate-500 text-xs py-2">No se han registrado notificaciones externas para este incidente.</div>';
                }

                const etype = data.entity_type_label || data.entity_type;
                const sev = data.severity_label || data.severity;
                const cond = data.condition_label || data.condition_type;
                const stat = data.status_label || data.status;

                container.innerHTML = `
                    <div class="grid grid-cols-2 gap-2 bg-obsidian-panel p-3 rounded-lg border border-obsidian-border text-xs">
                        <div><span class="text-slate-500">Entidad:</span> <span class="text-white font-bold">${data.entity_name}</span> <span class="text-cyan-400">(${etype})</span></div>
                        <div><span class="text-slate-500">Severidad:</span> <span class="font-bold uppercase text-amber-400">${sev}</span></div>
                        <div><span class="text-slate-500">Condición:</span> <span class="text-cyan-300 font-bold">${cond}</span></div>
                        <div><span class="text-slate-500">Estado:</span> <span class="font-bold uppercase text-white">${stat}</span></div>
                        <div><span class="text-slate-500">Detectado:</span> <span class="text-slate-300">${data.fired_at}</span></div>
                        <div><span class="text-slate-500">Duración:</span> <span class="text-amber-300 font-bold">${data.duration_formatted}</span></div>
                        ${data.acknowledged_at ? `<div><span class="text-slate-500">Reconocido:</span> <span class="text-sky-300">${data.acknowledged_at} (${data.acknowledged_by_user || 'Operador'})</span></div>` : ''}
                        ${data.resolved_at ? `<div><span class="text-slate-500">Resuelto:</span> <span class="text-emerald-400">${data.resolved_at} (${data.resolved_by})</span></div>` : ''}
                    </div>

                    <div class="p-3 rounded-lg bg-obsidian-panel border border-obsidian-border">
                        <div class="font-bold text-slate-400 mb-1">Detalle del Mensaje:</div>
                        <div class="text-slate-200">${data.message || 'Sin mensaje adicional'}</div>
                    </div>

                    ${data.notes ? `
                        <div class="p-3 rounded-lg bg-obsidian-panel border border-obsidian-border">
                            <div class="font-bold text-slate-400 mb-1">Bitácora / Notas Técnicas:</div>
                            <pre class="text-[10.5px] text-slate-300 whitespace-pre-wrap font-mono">${data.notes}</pre>
                        </div>
                    ` : ''}

                    <div>
                        <div class="font-bold text-slate-300 flex items-center gap-1.5 mb-1">
                            <span class="material-symbols-outlined text-sm text-cyan-400">send</span> Notificaciones Despachadas
                        </div>
                        ${notifsHtml}
                    </div>
                `;
            })
            .catch(err => {
                container.innerHTML = '<div class="text-red-400 text-center py-4">Error cargando detalles del incidente.</div>';
            });
    }
</script>
@endsection
