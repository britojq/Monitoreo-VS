@extends('layouts.admin')

@section('page_title', 'Respaldos y Auditoría de Configuraciones (GitOps)')

@section('admin_content')
<div class="space-y-4">

    <!-- CABECERA PRINCIPAL Y ACCIONES -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 bg-obsidian-card p-3.5 rounded-xl border border-obsidian-border shadow-md">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan text-2xl">settings_backup_restore</span>
                <h1 class="text-lg font-bold text-white tracking-wide">Respaldos y Auditoría de Configuraciones</h1>
                @if(!auth()->user()->isAdmin())
                    <span class="px-2 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 text-[10px] font-mono flex items-center gap-1" title="Operador: Modo Consulta y Auditoría de Diffs">
                        <span class="material-symbols-outlined text-xs">visibility</span> Modo Consulta
                    </span>
                @else
                    <span class="px-2 py-0.5 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 text-[10px] font-mono flex items-center gap-1" title="Administrador con Control Total de Respaldos">
                        <span class="material-symbols-outlined text-xs">admin_panel_settings</span> Control Total
                    </span>
                @endif
            </div>
            <p class="text-xs text-obsidian-muted mt-0.5">
                Versionado continuo estilo GitOps, control diferencial de cambios línea por línea y detección de anomalías en switches, routers y firewalls.
            </p>
        </div>

        <!-- BOTONES DE ACCIÓN (SOLO ADMINISTRADOR) -->
        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->isAdmin())
                <form action="{{ route('admin.configs.backup_all') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-obsidian-cyan/10 border border-obsidian-cyan/40 text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-[11px] font-semibold transition flex items-center gap-1.5 shadow-xs cursor-pointer" title="Ejecutar ciclo de respaldo y análisis diferencial para todos los equipos">
                        <span class="material-symbols-outlined text-sm">cloud_sync</span> Respaldar Todo
                    </button>
                </form>
                <button onclick="openBackupDeviceModal()" class="px-2.5 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/40 text-emerald-400 hover:bg-emerald-500 hover:text-black font-mono text-[11px] font-semibold transition flex items-center gap-1.5 shadow-xs cursor-pointer" title="Iniciar respaldo manual de un dispositivo específico">
                    <span class="material-symbols-outlined text-sm">backup</span> Nuevo Respaldo
                </button>
            @endif
        </div>
    </div>

    <!-- TARJETAS HUD DE MÉTRICAS -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5">
        <!-- Equipos Respaldados -->
        <div class="bg-obsidian-card p-2.5 rounded-xl border border-cyan-500/30 flex items-center gap-2.5 shadow-xs relative overflow-hidden">
            <div class="w-1.5 h-full bg-cyan-500 absolute left-0 top-0"></div>
            <div class="p-1.5 rounded-lg bg-cyan-500/10 text-cyan-400">
                <span class="material-symbols-outlined text-xl">router</span>
            </div>
            <div>
                <div class="text-[10px] uppercase font-mono text-slate-400 tracking-wider">Equipos Respaldados</div>
                <div class="text-base font-bold font-mono text-cyan-300 leading-tight">{{ $totalDevices }}</div>
            </div>
        </div>

        <!-- Total Versiones -->
        <div class="bg-obsidian-card p-2.5 rounded-xl border border-emerald-500/30 flex items-center gap-2.5 shadow-xs relative overflow-hidden">
            <div class="w-1.5 h-full bg-emerald-500 absolute left-0 top-0"></div>
            <div class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400">
                <span class="material-symbols-outlined text-xl">inventory_2</span>
            </div>
            <div>
                <div class="text-[10px] uppercase font-mono text-slate-400 tracking-wider">Total Versiones</div>
                <div class="text-base font-bold font-mono text-emerald-400 leading-tight">{{ $totalBackups }}</div>
            </div>
        </div>

        <!-- Respaldos Hoy -->
        <div class="bg-obsidian-card p-2.5 rounded-xl border border-sky-500/30 flex items-center gap-2.5 shadow-xs relative overflow-hidden">
            <div class="w-1.5 h-full bg-sky-500 absolute left-0 top-0"></div>
            <div class="p-1.5 rounded-lg bg-sky-500/10 text-sky-400">
                <span class="material-symbols-outlined text-xl">today</span>
            </div>
            <div>
                <div class="text-[10px] uppercase font-mono text-slate-400 tracking-wider">Respaldos Hoy</div>
                <div class="text-base font-bold font-mono text-sky-300 leading-tight">{{ $backupsToday }}</div>
            </div>
        </div>

        <!-- Cambios Detectados -->
        <div class="bg-obsidian-card p-2.5 rounded-xl border border-amber-500/30 flex items-center gap-2.5 shadow-xs relative overflow-hidden">
            <div class="w-1.5 h-full bg-amber-500 absolute left-0 top-0"></div>
            <div class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400">
                <span class="material-symbols-outlined text-xl">difference</span>
            </div>
            <div>
                <div class="text-[10px] uppercase font-mono text-slate-400 tracking-wider">Cambios Detectados</div>
                <div class="text-base font-bold font-mono text-amber-400 leading-tight">{{ $totalChanges }}</div>
            </div>
        </div>

        <!-- Espacio Almacenado -->
        <div class="bg-obsidian-card p-2.5 rounded-xl border border-purple-500/30 flex items-center gap-2.5 shadow-xs relative overflow-hidden col-span-2 sm:col-span-1">
            <div class="w-1.5 h-full bg-purple-500 absolute left-0 top-0"></div>
            <div class="p-1.5 rounded-lg bg-purple-500/10 text-purple-400">
                <span class="material-symbols-outlined text-xl">save</span>
            </div>
            <div>
                <div class="text-[10px] uppercase font-mono text-slate-400 tracking-wider">Espacio Total</div>
                <div class="text-base font-bold font-mono text-purple-300 leading-tight">{{ $totalSizeFormatted }}</div>
            </div>
        </div>
    </div>

    <!-- NAVEGACIÓN POR PESTAÑAS Y BUSCADOR -->
    <div class="bg-obsidian-card p-3 rounded-xl border border-obsidian-border space-y-3 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
            <!-- PESTAÑAS -->
            <div class="flex items-center gap-1.5 p-1 bg-[#060e1a] rounded-lg border border-obsidian-border/80">
                <a href="{{ route('admin.configs.index', array_merge(request()->except('page', 'changes_page'), ['tab' => 'backups'])) }}"
                   class="px-3 py-1.5 rounded-md text-xs font-mono font-semibold transition flex items-center gap-1.5 {{ $activeTab === 'backups' ? 'bg-obsidian-cyan text-black shadow-xs' : 'text-slate-400 hover:text-white' }}">
                    <span class="material-symbols-outlined text-sm">history</span>
                    <span>Versiones de Configuración</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'backups' ? 'bg-black/30 text-black' : 'bg-obsidian-panel text-slate-400' }}">
                        {{ $configurations->total() }}
                    </span>
                </a>
                <a href="{{ route('admin.configs.index', array_merge(request()->except('page', 'changes_page'), ['tab' => 'changes'])) }}"
                   class="px-3 py-1.5 rounded-md text-xs font-mono font-semibold transition flex items-center gap-1.5 {{ $activeTab === 'changes' ? 'bg-obsidian-cyan text-black shadow-xs' : 'text-slate-400 hover:text-white' }}">
                    <span class="material-symbols-outlined text-sm">change_history</span>
                    <span>Historial de Cambios / Diffs</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'changes' ? 'bg-black/30 text-black' : 'bg-obsidian-panel text-slate-400' }}">
                        {{ $changeLogs->total() }}
                    </span>
                </a>
            </div>

            <!-- FILTROS Y BUSCADOR -->
            <form method="GET" action="{{ route('admin.configs.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <div class="relative min-w-[200px]">
                    <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm">search</span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por equipo, IP, hash..."
                           class="w-full pl-8 pr-3 py-1.5 rounded-lg bg-[#060e1a] border border-obsidian-border text-xs text-slate-200 placeholder-slate-500 focus:outline-hidden focus:border-obsidian-cyan font-mono">
                </div>
                <select name="device_type" onchange="this.form.submit()"
                        class="px-2.5 py-1.5 rounded-lg bg-[#060e1a] border border-obsidian-border text-xs text-slate-300 focus:outline-hidden focus:border-obsidian-cyan font-mono">
                    <option value="all" {{ $deviceType === 'all' ? 'selected' : '' }}>Todos los Tipos</option>
                    <option value="cisco_switch" {{ $deviceType === 'cisco_switch' ? 'selected' : '' }}>Switch Cisco</option>
                    <option value="cisco_router" {{ $deviceType === 'cisco_router' ? 'selected' : '' }}>Router Cisco</option>
                    <option value="pfsense" {{ $deviceType === 'pfsense' ? 'selected' : '' }}>Firewall pfSense</option>
                    <option value="linux_server" {{ $deviceType === 'linux_server' ? 'selected' : '' }}>Servidor Linux</option>
                </select>
                @if($search !== '' || $deviceType !== 'all')
                    <a href="{{ route('admin.configs.index', ['tab' => $activeTab]) }}" class="px-2 py-1.5 rounded-lg bg-red-950/30 border border-red-500/30 text-red-400 hover:text-white text-xs font-mono flex items-center gap-1" title="Limpiar filtros">
                        <span class="material-symbols-outlined text-sm">filter_alt_off</span>
                    </a>
                @endif
            </form>
        </div>

        <!-- ========================================================================= -->
        <!-- PESTAÑA 1: VERSIONES DE CONFIGURACIÓN                                     -->
        <!-- ========================================================================= -->
        @if($activeTab === 'backups')
            <div class="overflow-x-auto rounded-lg border border-obsidian-border">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#060e1a] border-b border-obsidian-border text-[10px] font-mono uppercase tracking-wider text-slate-400">
                            <th class="px-3 py-2">Dispositivo</th>
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">Versión (Hash SHA-256)</th>
                            <th class="px-3 py-2 text-center">Tamaño / Líneas</th>
                            <th class="px-3 py-2">Fecha Captura</th>
                            <th class="px-3 py-2 text-center">Auditoría / Estado</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-obsidian-border/50 text-xs font-mono">
                        @forelse($configurations as $cfg)
                            <tr class="hover:bg-slate-900/40 transition">
                                <!-- Dispositivo -->
                                <td class="px-3 py-2">
                                    <div class="font-bold text-white flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-xs text-obsidian-cyan">
                                            {{ $cfg->device_type === 'cisco_router' ? 'alt_route' : ($cfg->device_type === 'pfsense' ? 'shield' : 'router') }}
                                        </span>
                                        <span>{{ $cfg->resolved_name }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono flex items-center gap-1 mt-0.5">
                                        <span class="text-cyan-400">{{ $cfg->device_ip }}</span>
                                    </div>
                                </td>

                                <!-- Tipo -->
                                <td class="px-3 py-2">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono border {{ $cfg->device_type_badge['class'] }}">
                                        {{ $cfg->device_type_badge['label'] }}
                                    </span>
                                </td>

                                <!-- Commit Hash -->
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-1.5 py-0.5 rounded bg-[#060e1a] border border-cyan-500/30 text-cyan-300 font-mono text-[11px]" title="{{ $cfg->config_hash }}">
                                            {{ $cfg->short_hash }}
                                        </span>
                                        <button onclick="copyToClipboard('{{ $cfg->config_hash }}', this)" class="text-slate-500 hover:text-cyan-300 transition" title="Copiar SHA-256 completo">
                                            <span class="material-symbols-outlined text-xs">content_copy</span>
                                        </button>
                                    </div>
                                </td>

                                <!-- Tamaño / Líneas -->
                                <td class="px-3 py-2 text-center">
                                    <div class="text-slate-200 font-bold">{{ $cfg->line_count }} <span class="text-[10px] font-normal text-slate-400">líneas</span></div>
                                    <div class="text-[10px] text-slate-500">{{ $cfg->size_formatted }}</div>
                                </td>

                                <!-- Fecha Captura -->
                                <td class="px-3 py-2">
                                    <div class="text-slate-200">{{ $cfg->captured_at ? $cfg->captured_at->timezone('America/Caracas')->format('d/m/Y h:i A') : 'N/A' }}</div>
                                    <div class="text-[10px] text-slate-400 capitalize">Vía {{ $cfg->captured_by ?? 'programado' }}</div>
                                </td>

                                <!-- Auditoría / Estado -->
                                <td class="px-3 py-2 text-center">
                                    @if($cfg->latestChangeLog)
                                        @if($cfg->latestChangeLog->change_type === 'modified')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-500/10 border border-amber-500/30 text-amber-300 font-mono inline-flex items-center gap-1" title="Cambios detectados vs versión anterior">
                                                <span class="material-symbols-outlined text-[11px]">difference</span>
                                                <span>+{{ $cfg->latestChangeLog->lines_added }}/-{{ $cfg->latestChangeLog->lines_removed }}</span>
                                            </span>
                                        @elseif($cfg->latestChangeLog->change_type === 'reverted')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-purple-500/10 border border-purple-500/30 text-purple-300 font-mono inline-flex items-center gap-1" title="Configuración revertida a estado previo">
                                                <span class="material-symbols-outlined text-[11px]">history</span> Reversión
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 font-mono inline-flex items-center gap-1" title="Línea base inicial registrada">
                                                <span class="material-symbols-outlined text-[11px]">flag</span> Línea Base
                                            </span>
                                        @endif
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-800 text-slate-400 font-mono">Sin diferencias</span>
                                    @endif
                                </td>

                                <!-- Acciones -->
                                <td class="px-3 py-2 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Ver Config Completa -->
                                        <button onclick="openConfigModal({{ $cfg->id }})" class="p-1 rounded-md bg-obsidian-cyan/10 border border-obsidian-cyan/30 text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition cursor-pointer" title="Ver configuración completa">
                                            <span class="material-symbols-outlined text-sm">visibility</span>
                                        </button>
                                        <!-- Ver Diff -->
                                        <button onclick="openDiffModal({{ $cfg->id }})" class="p-1 rounded-md bg-amber-500/10 border border-amber-500/30 text-amber-400 hover:bg-amber-500 hover:text-black transition cursor-pointer" title="Ver diferencias respecto a la versión anterior">
                                            <span class="material-symbols-outlined text-sm">compare_arrows</span>
                                        </button>
                                        <!-- Eliminar Versión (Solo Admin) -->
                                        @if(auth()->user()->isAdmin())
                                            <form action="{{ route('admin.configs.destroy', $cfg->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Está seguro de eliminar esta versión de respaldo? Esta acción no se puede deshacer.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-1 rounded-md bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500 hover:text-white transition cursor-pointer" title="Eliminar registro de respaldo">
                                                    <span class="material-symbols-outlined text-sm">delete</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                    <span class="material-symbols-outlined text-3xl mb-1 text-slate-600">inventory_2</span>
                                    <p class="text-xs">No se encontraron respaldos de configuración que coincidan con la búsqueda.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- PAGINACIÓN OBSIDIAN -->
            @if($configurations->hasPages())
                <div class="mt-3 flex items-center justify-between px-2 py-2 bg-[#060e1a] rounded-lg border border-obsidian-border text-xs font-mono text-slate-400">
                    <div>
                        Mostrando <span class="text-cyan-400 font-bold">{{ $configurations->firstItem() ?? 0 }}</span> - <span class="text-cyan-400 font-bold">{{ $configurations->lastItem() ?? 0 }}</span> de <span class="text-white font-bold">{{ $configurations->total() }}</span> versiones
                    </div>
                    <div class="flex items-center gap-1">
                        @if($configurations->onFirstPage())
                            <span class="px-2 py-1 rounded bg-obsidian-card text-slate-600 cursor-not-allowed">Anterior</span>
                        @else
                            <a href="{{ $configurations->previousPageUrl() }}" class="px-2 py-1 rounded bg-obsidian-panel border border-obsidian-border text-slate-300 hover:text-white hover:border-cyan-500/40 transition">Anterior</a>
                        @endif

                        <span class="px-2 py-1 text-cyan-300 font-bold">Pág. {{ $configurations->currentPage() }} de {{ $configurations->lastPage() }}</span>

                        @if($configurations->hasMorePages())
                            <a href="{{ $configurations->nextPageUrl() }}" class="px-2 py-1 rounded bg-obsidian-panel border border-obsidian-border text-slate-300 hover:text-white hover:border-cyan-500/40 transition">Siguiente</a>
                        @else
                            <span class="px-2 py-1 rounded bg-obsidian-card text-slate-600 cursor-not-allowed">Siguiente</span>
                        @endif
                    </div>
                </div>
            @endif
        @endif

        <!-- ========================================================================= -->
        <!-- PESTAÑA 2: HISTORIAL DE CAMBIOS Y DIFFS                                   -->
        <!-- ========================================================================= -->
        @if($activeTab === 'changes')
            <div class="overflow-x-auto rounded-lg border border-obsidian-border">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#060e1a] border-b border-obsidian-border text-[10px] font-mono uppercase tracking-wider text-slate-400">
                            <th class="px-3 py-2">Fecha Detección</th>
                            <th class="px-3 py-2">Dispositivo</th>
                            <th class="px-3 py-2">Tipo de Cambio</th>
                            <th class="px-3 py-2 text-center">Variación Líneas</th>
                            <th class="px-3 py-2">Versiones Comparadas</th>
                            <th class="px-3 py-2">Resumen Diferencial</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-obsidian-border/50 text-xs font-mono">
                        @forelse($changeLogs as $chg)
                            <tr class="hover:bg-slate-900/40 transition">
                                <!-- Fecha -->
                                <td class="px-3 py-2">
                                    <div class="text-slate-200">{{ $chg->detected_at ? $chg->detected_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'N/A' }}</div>
                                </td>

                                <!-- Dispositivo -->
                                <td class="px-3 py-2">
                                    <div class="font-bold text-white flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-xs text-obsidian-cyan">router</span>
                                        <span>{{ $chg->configuration?->resolved_name ?? 'Dispositivo' }}</span>
                                    </div>
                                    <div class="text-[10px] text-cyan-400 mt-0.5">{{ $chg->configuration?->device_ip ?? '' }}</div>
                                </td>

                                <!-- Tipo de Cambio -->
                                <td class="px-3 py-2">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono border {{ $chg->change_type_badge['class'] }}">
                                        {{ $chg->change_type_badge['label'] }}
                                    </span>
                                </td>

                                <!-- Variación Líneas -->
                                <td class="px-3 py-2 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="text-emerald-400 font-bold">+{{ $chg->lines_added }}</span>
                                        <span class="text-red-400 font-bold">-{{ $chg->lines_removed }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-500">{{ $chg->total_lines_changed }} alteradas</div>
                                </td>

                                <!-- Versiones -->
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-1 text-[11px]">
                                        <span class="px-1.5 py-0.2 rounded bg-[#060e1a] border border-slate-700 text-slate-400">
                                            {{ $chg->previousConfiguration?->short_hash ?? 'Base' }}
                                        </span>
                                        <span class="material-symbols-outlined text-xs text-slate-500">arrow_right_alt</span>
                                        <span class="px-1.5 py-0.2 rounded bg-[#060e1a] border border-cyan-500/30 text-cyan-300">
                                            {{ $chg->configuration?->short_hash ?? 'Actual' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Resumen -->
                                <td class="px-3 py-2">
                                    <div class="text-slate-300 text-[11px] truncate max-w-xs" title="{{ $chg->diff_summary }}">
                                        {{ $chg->diff_summary ?? 'Sin resumen' }}
                                    </div>
                                </td>

                                <!-- Acciones -->
                                <td class="px-3 py-2 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button onclick="openDiffModal({{ $chg->id }})" class="px-2 py-1 rounded-md bg-amber-500/10 border border-amber-500/30 text-amber-400 hover:bg-amber-500 hover:text-black transition text-[11px] flex items-center gap-1 cursor-pointer" title="Examinar auditoría diferencial línea por línea">
                                            <span class="material-symbols-outlined text-xs">compare_arrows</span>
                                            <span>Ver Diff</span>
                                        </button>
                                        @if($chg->configuration)
                                            <button onclick="openConfigModal({{ $chg->configuration->id }})" class="p-1 rounded-md bg-obsidian-cyan/10 border border-obsidian-cyan/30 text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition cursor-pointer" title="Ver configuración resultante">
                                                <span class="material-symbols-outlined text-sm">visibility</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                    <span class="material-symbols-outlined text-3xl mb-1 text-slate-600">difference</span>
                                    <p class="text-xs">No se han registrado modificaciones o diffs en el período consultado.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- PAGINACIÓN OBSIDIAN -->
            @if($changeLogs->hasPages())
                <div class="mt-3 flex items-center justify-between px-2 py-2 bg-[#060e1a] rounded-lg border border-obsidian-border text-xs font-mono text-slate-400">
                    <div>
                        Mostrando <span class="text-cyan-400 font-bold">{{ $changeLogs->firstItem() ?? 0 }}</span> - <span class="text-cyan-400 font-bold">{{ $changeLogs->lastItem() ?? 0 }}</span> de <span class="text-white font-bold">{{ $changeLogs->total() }}</span> cambios
                    </div>
                    <div class="flex items-center gap-1">
                        @if($changeLogs->onFirstPage())
                            <span class="px-2 py-1 rounded bg-obsidian-card text-slate-600 cursor-not-allowed">Anterior</span>
                        @else
                            <a href="{{ $changeLogs->previousPageUrl() }}" class="px-2 py-1 rounded bg-obsidian-panel border border-obsidian-border text-slate-300 hover:text-white hover:border-cyan-500/40 transition">Anterior</a>
                        @endif

                        <span class="px-2 py-1 text-cyan-300 font-bold">Pág. {{ $changeLogs->currentPage() }} de {{ $changeLogs->lastPage() }}</span>

                        @if($changeLogs->hasMorePages())
                            <a href="{{ $changeLogs->nextPageUrl() }}" class="px-2 py-1 rounded bg-obsidian-panel border border-obsidian-border text-slate-300 hover:text-white hover:border-cyan-500/40 transition">Siguiente</a>
                        @else
                            <span class="px-2 py-1 rounded bg-obsidian-card text-slate-600 cursor-not-allowed">Siguiente</span>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>

<!-- ============================================================================= -->
<!-- MODAL 1: VISOR DE CONFIGURACIÓN COMPLETA                                       -->
<!-- ============================================================================= -->
<div id="configViewerModal" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
    <div class="bg-obsidian-card w-full max-w-4xl rounded-2xl border border-obsidian-border shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
        <!-- Cabecera Modal -->
        <div class="p-3.5 border-b border-obsidian-border flex items-center justify-between bg-[#060e1a]">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan text-xl">description</span>
                <div>
                    <h3 id="cfgModalTitle" class="text-sm font-bold text-white font-mono">Configuración de Dispositivo</h3>
                    <div id="cfgModalSubtitle" class="text-[11px] text-slate-400 font-mono">Cargando...</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button id="btnCopyConfig" onclick="copyFullConfig()" class="px-2.5 py-1 rounded-lg bg-obsidian-cyan/10 border border-obsidian-cyan/30 text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-xs transition flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">content_copy</span>
                    <span id="copyBtnText">Copiar</span>
                </button>
                <button onclick="closeConfigModal()" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
        </div>

        <!-- Banner de Metadatos -->
        <div id="cfgModalMeta" class="px-3.5 py-2 bg-[#081220] border-b border-obsidian-border/60 flex flex-wrap items-center justify-between text-[11px] font-mono text-slate-300 gap-2">
            <div>Hash: <span id="cfgModalHash" class="text-cyan-400">...</span></div>
            <div>Líneas: <span id="cfgModalLines" class="text-white font-bold">0</span> | Tamaño: <span id="cfgModalSize" class="text-slate-400">0 KB</span></div>
            <div>Captura: <span id="cfgModalDate" class="text-slate-300">...</span></div>
        </div>

        <!-- Contenedor de Código -->
        <div class="p-3 flex-1 overflow-auto bg-[#040811]">
            <pre id="cfgModalContent" class="text-[11px] font-mono text-slate-300 leading-relaxed select-text whitespace-pre overflow-x-auto p-2">Cargando contenido...</pre>
        </div>
    </div>
</div>

<!-- ============================================================================= -->
<!-- MODAL 2: VISOR DE DIFF UNIFICADO (CONTROL DIFERENCIAL GITOPS)                 -->
<!-- ============================================================================= -->
<div id="diffViewerModal" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
    <div class="bg-obsidian-card w-full max-w-5xl rounded-2xl border border-obsidian-border shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
        <!-- Cabecera Modal -->
        <div class="p-3.5 border-b border-obsidian-border flex items-center justify-between bg-[#060e1a]">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-400 text-xl">compare_arrows</span>
                <div>
                    <h3 id="diffModalTitle" class="text-sm font-bold text-white font-mono">Auditoría Diferencial de Configuración</h3>
                    <div id="diffModalSubtitle" class="text-[11px] text-slate-400 font-mono">Comparativa de cambios</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="closeDiffModal()" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
        </div>

        <!-- Barra de Estadísticas Diff -->
        <div class="px-3.5 py-2 bg-[#081220] border-b border-obsidian-border/60 flex flex-wrap items-center justify-between text-[11px] font-mono gap-2">
            <div class="flex items-center gap-2">
                <span id="diffModalBadge" class="px-2 py-0.5 rounded-full text-[10px] border">Modificación</span>
                <span id="diffModalSummary" class="text-slate-300">...</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-emerald-400 font-bold">+<span id="diffModalAdded">0</span> líneas agregadas</span>
                <span class="text-red-400 font-bold">-<span id="diffModalRemoved">0</span> líneas eliminadas</span>
            </div>
        </div>

        <!-- Contenedor Diff con Colores Git -->
        <div class="p-3 flex-1 overflow-auto bg-[#040811]">
            <div id="diffLinesContainer" class="font-mono text-[11px] leading-relaxed select-text space-y-0.5">
                <!-- Se pobla dinámicamente con JS -->
            </div>
        </div>
    </div>
</div>

<!-- ============================================================================= -->
<!-- MODAL 3: NUEVO RESPALDO MANUAL (SOLO ADMINISTRADOR)                           -->
<!-- ============================================================================= -->
@if(auth()->user()->isAdmin())
<div id="backupDeviceModal" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
    <div class="bg-obsidian-card w-full max-w-lg rounded-2xl border border-obsidian-border shadow-2xl overflow-hidden">
        <form action="{{ route('admin.configs.backup_device') }}" method="POST">
            @csrf
            <div class="p-4 border-b border-obsidian-border flex items-center justify-between bg-[#060e1a]">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-400 text-xl">backup</span>
                    <h3 class="text-sm font-bold text-white font-mono">Ejecutar Respaldo Manual</h3>
                </div>
                <button type="button" onclick="closeBackupDeviceModal()" class="text-slate-400 hover:text-white">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            <div class="p-4 space-y-3 bg-obsidian-card">
                <div>
                    <label class="block text-xs font-mono text-slate-300 mb-1">Seleccione el Dispositivo de Red</label>
                    <select name="device_target" required class="w-full px-3 py-2 rounded-lg bg-[#060e1a] border border-obsidian-border text-xs text-slate-200 font-mono focus:outline-hidden focus:border-obsidian-cyan">
                        <option value="">-- Seleccionar Equipo --</option>
                        <optgroup label="Dispositivos de Red (Switches / Routers)">
                            @foreach($networkDevices as $ndev)
                                <option value="net_{{ $ndev->id }}">{{ $ndev->name }} ({{ $ndev->ip }}) - {{ $ndev->model ?? 'Cisco' }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Equipos con Telemetría SNMP / Firewalls">
                            @foreach($snmpDevices as $sdev)
                                <option value="snmp_{{ $sdev->id }}">{{ $sdev->name }} ({{ $sdev->ip_address }}) - {{ strtoupper($sdev->device_type) }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <div class="p-3 rounded-lg bg-cyan-950/20 border border-cyan-500/20 text-xs font-mono text-cyan-300 space-y-1">
                    <div class="font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">info</span>
                        <span>Procedimiento de Auditoría GitOps</span>
                    </div>
                    <p class="text-[11px] text-cyan-300/80">
                        El sistema extraerá la configuración activa ('running-config' o XML de firewall), normalizará comentarios volátiles, generará el hash SHA-256 y registrará un diff unificado en caso de detectar cambios.
                    </p>
                </div>
            </div>

            <div class="p-3.5 border-t border-obsidian-border bg-[#060e1a] flex items-center justify-end gap-2">
                <button type="button" onclick="closeBackupDeviceModal()" class="px-3 py-1.5 rounded-lg border border-obsidian-border text-slate-300 hover:text-white text-xs font-mono transition">
                    Cancelar
                </button>
                <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-sm cursor-pointer">
                    <span class="material-symbols-outlined text-sm">cloud_upload</span>
                    <span>Iniciar Respaldo</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
    let currentConfigText = '';

    // Copiar texto genérico al portapapeles
    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<span class="material-symbols-outlined text-xs text-emerald-400">check</span>';
            setTimeout(() => { btn.innerHTML = orig; }, 1500);
        });
    }

    // Modal Visor de Configuración
    function openConfigModal(id) {
        const modal = document.getElementById('configViewerModal');
        const title = document.getElementById('cfgModalTitle');
        const subtitle = document.getElementById('cfgModalSubtitle');
        const hash = document.getElementById('cfgModalHash');
        const lines = document.getElementById('cfgModalLines');
        const size = document.getElementById('cfgModalSize');
        const date = document.getElementById('cfgModalDate');
        const content = document.getElementById('cfgModalContent');

        content.textContent = 'Cargando contenido de la configuración...';
        modal.classList.remove('hidden');

        fetch(`{{ url('admin/configs') }}/${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    content.textContent = data.error;
                    return;
                }
                title.textContent = `${data.device_name} (${data.device_ip})`;
                subtitle.textContent = `Tipo: ${data.device_type} | Versión: ${data.short_hash}`;
                hash.textContent = data.config_hash;
                lines.textContent = data.line_count;
                size.textContent = data.config_size_formatted;
                date.textContent = data.captured_at;
                content.textContent = data.config_text;
                currentConfigText = data.config_text;
            })
            .catch(err => {
                content.textContent = 'Error al recuperar el archivo de configuración.';
            });
    }

    function closeConfigModal() {
        document.getElementById('configViewerModal').classList.add('hidden');
    }

    function copyFullConfig() {
        if (!currentConfigText) return;
        navigator.clipboard.writeText(currentConfigText).then(() => {
            const textSpan = document.getElementById('copyBtnText');
            textSpan.textContent = '¡Copiado!';
            setTimeout(() => { textSpan.textContent = 'Copiar'; }, 2000);
        });
    }

    // Modal Visor de Diff Unificado
    function openDiffModal(id) {
        const modal = document.getElementById('diffViewerModal');
        const title = document.getElementById('diffModalTitle');
        const subtitle = document.getElementById('diffModalSubtitle');
        const badge = document.getElementById('diffModalBadge');
        const summary = document.getElementById('diffModalSummary');
        const added = document.getElementById('diffModalAdded');
        const removed = document.getElementById('diffModalRemoved');
        const container = document.getElementById('diffLinesContainer');

        container.innerHTML = '<div class="text-slate-400 p-4 text-center">Cargando diferencias unificadas...</div>';
        modal.classList.remove('hidden');

        fetch(`{{ url('admin/configs') }}/${id}/diff`)
            .then(res => res.json())
            .then(data => {
                if (!data.has_diff) {
                    container.innerHTML = `<div class="p-6 text-center text-slate-400"><span class="material-symbols-outlined text-3xl mb-1 text-slate-500">check_circle</span><p class="text-xs">${data.message}</p></div>`;
                    title.textContent = 'Auditoría Diferencial';
                    subtitle.textContent = 'Sin diferencias';
                    badge.className = 'px-2 py-0.5 rounded-full text-[10px] border bg-slate-800 text-slate-400';
                    badge.textContent = 'Sin Cambios';
                    summary.textContent = '';
                    added.textContent = '0';
                    removed.textContent = '0';
                    return;
                }

                title.textContent = `Auditoría Diferencial: ${data.device_name} (${data.device_ip})`;
                subtitle.textContent = `Comparativa: ${data.previous_date} ➔ ${data.current_date}`;
                badge.className = `px-2 py-0.5 rounded-full text-[10px] border ${data.badge ? data.badge.class : 'bg-amber-500/10 text-amber-300'}`;
                badge.textContent = data.badge ? data.badge.label : data.change_type;
                summary.textContent = data.diff_summary || '';
                added.textContent = data.lines_added || '0';
                removed.textContent = data.lines_removed || '0';

                // Renderizado línea por línea del diff con estilo git
                const rawDiff = data.diff_unified || '';
                const lines = rawDiff.split('\n');
                let html = '';

                lines.forEach(line => {
                    const esc = line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    if (line.startsWith('+++') || line.startsWith('---')) {
                        html += `<div class="px-2 py-0.5 bg-[#091528] text-cyan-400 font-bold border-l-2 border-cyan-500">${esc}</div>`;
                    } else if (line.startsWith('@@')) {
                        html += `<div class="px-2 py-0.5 bg-[#0e1d35] text-purple-300 font-bold border-l-2 border-purple-500 my-1">${esc}</div>`;
                    } else if (line.startsWith('+')) {
                        html += `<div class="px-2 py-0.5 bg-emerald-950/40 text-emerald-300 border-l-2 border-emerald-500">${esc}</div>`;
                    } else if (line.startsWith('-')) {
                        html += `<div class="px-2 py-0.5 bg-red-950/40 text-red-300 border-l-2 border-red-500">${esc}</div>`;
                    } else {
                        html += `<div class="px-2 py-0.5 text-slate-400 hover:bg-slate-900/30">${esc}</div>`;
                    }
                });

                container.innerHTML = html || '<div class="text-slate-500 p-4">Diff vacío.</div>';
            })
            .catch(err => {
                container.innerHTML = '<div class="text-red-400 p-4 text-center">Error al consultar el registro de diff.</div>';
            });
    }

    function closeDiffModal() {
        document.getElementById('diffViewerModal').classList.add('hidden');
    }

    // Modal Nuevo Respaldo
    function openBackupDeviceModal() {
        const m = document.getElementById('backupDeviceModal');
        if (m) m.classList.remove('hidden');
    }

    function closeBackupDeviceModal() {
        const m = document.getElementById('backupDeviceModal');
        if (m) m.classList.add('hidden');
    }

    // Cerrar modales con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeConfigModal();
            closeDiffModal();
            closeBackupDeviceModal();
        }
    });
</script>
@endsection
