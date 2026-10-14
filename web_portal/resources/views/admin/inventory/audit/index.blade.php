@extends('layouts.admin')

@section('page_title', 'Auditoría & Trazabilidad ITAM • Valle Seco')

@section('admin_content')
<style>
    .custom-table-scroll {
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: #06b6d4 #040b15;
    }
    .custom-table-scroll::-webkit-scrollbar {
        height: 8px;
    }
    .custom-table-scroll::-webkit-scrollbar-track {
        background: #040b15;
        border-radius: 4px;
        border: 1px solid #1e293b;
    }
    .custom-table-scroll::-webkit-scrollbar-thumb {
        background: #06b6d4;
        border-radius: 4px;
        border: 2px solid #040b15;
    }
    .custom-table-scroll::-webkit-scrollbar-thumb:hover {
        background: #22d3ee;
    }
</style>

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
                <a href="{{ route('admin.devices.index') }}" class="text-[10.5px] font-mono text-obsidian-muted hover:text-white transition">Equipos &amp; Activos</a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-[10.5px] font-mono text-obsidian-muted">Trazabilidad ITAM</span>
            </div>
            <h1 class="text-xl font-black text-white tracking-wide flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-2xl">history_edu</span>
                Consola de Auditoría &amp; Trazabilidad ITAM
            </h1>
            <p class="text-xs font-mono text-obsidian-muted">Pista cronológica inmutable de asignaciones, modificaciones de hardware, pases de movilización y mantenimientos</p>
        </div>
        <div class="flex items-center gap-2">
            <!-- BOTÓN EXPORTAR CON DROPDOWN (SOLO ICONO CON TOOLTIP) -->
            <div class="relative" id="exportDropdownContainer">
                <button type="button" onclick="toggleExportMenu()" class="w-9 h-9 rounded-xl bg-obsidian-panel border border-obsidian-border hover:border-cyan-400 text-white transition flex items-center justify-center shadow-xs cursor-pointer group" title="Exportar Trazabilidad Forense (CSV / JSON)">
                    <span class="material-symbols-outlined text-lg text-cyan-400 group-hover:scale-110 transition-transform">download</span>
                </button>
                <div id="exportDropdownMenu" class="absolute right-0 mt-1.5 w-64 rounded-xl bg-[#071321] border border-obsidian-border shadow-2xl z-50 hidden overflow-hidden divide-y divide-obsidian-border/50 animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-3 py-2 text-[10px] font-mono text-obsidian-muted uppercase tracking-wider bg-obsidian-panel/80">
                        Formato de Exportación Forense:
                    </div>
                    <div class="p-1.5 space-y-1">
                        <a href="{{ route('admin.inventory.audit.export', array_merge(request()->query(), ['format' => 'csv'])) }}" onclick="toggleExportMenu()" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-mono text-slate-200 hover:bg-emerald-950/40 hover:text-emerald-300 border border-transparent hover:border-emerald-500/30 transition group">
                            <span class="material-symbols-outlined text-lg text-emerald-400 group-hover:scale-110 transition-transform">table_view</span>
                            <div>
                                <div class="font-bold text-white text-[11px]">CSV (Excel UTF-8)</div>
                                <div class="text-[9.5px] text-obsidian-muted">Auditoría estructurada tabular</div>
                            </div>
                        </a>
                        <a href="{{ route('admin.inventory.audit.export', array_merge(request()->query(), ['format' => 'json'])) }}" onclick="toggleExportMenu()" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-mono text-slate-200 hover:bg-cyan-950/40 hover:text-cyan-300 border border-transparent hover:border-cyan-500/30 transition group">
                            <span class="material-symbols-outlined text-lg text-cyan-400 group-hover:scale-110 transition-transform">data_object</span>
                            <div>
                                <div class="font-bold text-white text-[11px]">JSON Estructurado</div>
                                <div class="text-[9.5px] text-obsidian-muted">Payload técnico forense con diffs</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <span class="w-9 h-9 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-400 inline-flex items-center justify-center shadow-xs cursor-help" title="SSOT MariaDB • Registro En Vivo">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            </span>
        </div>
    </div>

    <!-- TARJETAS KPI -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- TOTAL EVENTOS ITAM -->
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10.5px] font-mono text-obsidian-muted uppercase tracking-wider">Eventos ITAM</p>
                <p class="text-2xl font-bold font-mono text-white mt-1">{{ number_format($kpis['total_events']) }}</p>
                <p class="text-[10px] font-mono text-obsidian-muted/80 mt-0.5">{{ number_format($kpis['events_last_30_days']) }} en últimos 30 días</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-obsidian-panel border border-obsidian-border flex items-center justify-center text-cyan-400">
                <span class="material-symbols-outlined text-xl">history</span>
            </div>
        </div>

        <!-- MODIFICACIONES DE HARDWARE -->
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10.5px] font-mono text-obsidian-muted uppercase tracking-wider">Modificaciones de Hardware<span class="sr-only"> Hardware Drifts</span></p>
                <p class="text-2xl font-bold font-mono text-rose-300 mt-1">{{ number_format($kpis['drifts_count']) }}</p>
                <p class="text-[10px] font-mono text-obsidian-muted/80 mt-0.5">Alteraciones de componentes</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-rose-950/40 border border-rose-500/30 flex items-center justify-center text-rose-400">
                <span class="material-symbols-outlined text-xl">memory</span>
            </div>
        </div>

        <!-- ASIGNACIONES ACTIVAS -->
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10.5px] font-mono text-obsidian-muted uppercase tracking-wider">Actas UINF-4.05 Activas</p>
                <p class="text-2xl font-bold font-mono text-emerald-300 mt-1">{{ number_format($kpis['active_assignments']) }}</p>
                <p class="text-[10px] font-mono text-obsidian-muted/80 mt-0.5">Asignados a Responsables</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-950/40 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                <span class="material-symbols-outlined text-xl">assignment_ind</span>
            </div>
        </div>

        <!-- DISCREPANCIAS / DIGITALIZACIÓN PENDIENTE -->
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10.5px] font-mono text-obsidian-muted uppercase tracking-wider">Discrepancias / Digitalización Pendiente</p>
                <p class="text-2xl font-bold font-mono {{ $kpis['outdated_assignments'] > 0 ? 'text-amber-300' : 'text-slate-400' }} mt-1">
                    {{ number_format($kpis['outdated_assignments']) }}
                </p>
                <p class="text-[10px] font-mono text-obsidian-muted/80 mt-0.5">Actas por renovar o escanear</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-amber-950/40 border border-amber-500/30 flex items-center justify-center text-amber-400">
                <span class="material-symbols-outlined text-xl">warning</span>
            </div>
        </div>
    </div>

    <!-- FORMULARIO DE FILTROS -->
    <div class="p-4 rounded-xl bg-obsidian-panel/90 border border-obsidian-border shadow-xs">
        <form method="GET" action="{{ route('admin.inventory.audit.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <!-- BÚSQUEDA TEXTUAL -->
            <div>
                <label for="search" class="block text-[11px] font-mono text-obsidian-muted mb-1">Buscar por término:</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-2.5 top-2.5 text-obsidian-muted text-sm">search</span>
                    <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" 
                           placeholder="Etiqueta, activo, responsable..." 
                           class="w-full pl-8 pr-3 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                </div>
            </div>

            <!-- FILTRO POR ACTIVO -->
            <div>
                <label for="asset_id" class="block text-[11px] font-mono text-obsidian-muted mb-1">Filtrar Activo:</label>
                <select id="asset_id" name="asset_id" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    <option value="">-- Todos los Activos --</option>
                    @foreach($assets as $ast)
                        <option value="{{ $ast->id }}" {{ ($filters['asset_id'] ?? '') == $ast->id ? 'selected' : '' }}>
                            {{ $ast->name }} ({{ $ast->asset_tag }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- FILTRO POR EVENTO -->
            <div>
                <label for="event" class="block text-[11px] font-mono text-obsidian-muted mb-1">Tipo de Evento:</label>
                <select id="event" name="event" class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
                    <option value="">-- Todos los Eventos --</option>
                    <option value="created" {{ ($filters['event'] ?? '') == 'created' ? 'selected' : '' }}>Alta / Creación</option>
                    <option value="updated" {{ ($filters['event'] ?? '') == 'updated' ? 'selected' : '' }}>Modificación</option>
                    <option value="approved" {{ ($filters['event'] ?? '') == 'approved' ? 'selected' : '' }}>Aprobación GLPI</option>
                    <option value="deleted" {{ ($filters['event'] ?? '') == 'deleted' ? 'selected' : '' }}>Baja / Desincorporación</option>
                    <option value="drift_detected" {{ ($filters['event'] ?? '') == 'drift_detected' ? 'selected' : '' }}>Modificación de Hardware</option>
                    <option value="exported" {{ ($filters['event'] ?? '') == 'exported' ? 'selected' : '' }}>Exportación</option>
                </select>
            </div>

            <!-- FECHA DESDE -->
            <div>
                <label for="date_from" class="block text-[11px] font-mono text-obsidian-muted mb-1">Desde:</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                       class="w-full px-2.5 py-1.5 rounded-lg bg-[#040b15] border border-obsidian-border text-white text-xs font-mono focus:border-cyan-400 focus:outline-hidden">
            </div>

            <!-- BOTONES DE ACCIÓN -->
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-1.5 px-3 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black text-xs font-mono font-bold transition flex items-center justify-center gap-1 shadow-xs cursor-pointer">
                    <span class="material-symbols-outlined text-sm">filter_alt</span>
                    <span>Filtrar</span>
                </button>
                <a href="{{ route('admin.inventory.audit.index') }}" class="py-1.5 px-2.5 rounded-lg bg-obsidian-panel border border-obsidian-border hover:bg-slate-800 text-obsidian-muted hover:text-white text-xs font-mono transition flex items-center justify-center" title="Limpiar filtros">
                    <span class="material-symbols-outlined text-sm">restart_alt</span>
                </a>
            </div>
        </form>
    </div>

    <!-- TABLA DE AUDITORÍA -->
    <div class="rounded-2xl bg-obsidian-panel/90 border border-obsidian-border shadow-md overflow-hidden">
        <div class="custom-table-scroll">
            <table class="w-full text-left text-xs font-mono divide-y divide-obsidian-border/60">
                <thead class="bg-[#040c17] text-obsidian-muted uppercase text-[10.5px] tracking-wider font-semibold">
                    <tr>
                        <th class="py-3 px-3.5">Fecha / Hora</th>
                        <th class="py-3 px-3.5">Activo / Entidad</th>
                        <th class="py-3 px-3.5">Evento</th>
                        <th class="py-3 px-3.5">Descripción de la Operación</th>
                        <th class="py-3 px-3.5">Usuario Operador</th>
                        <th class="py-3 px-3.5 text-center">Forense</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/40 text-slate-200">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-900/60 transition-colors">
                            <!-- FECHA -->
                            <td class="py-2.5 px-3.5 whitespace-nowrap text-obsidian-muted">
                                <div class="font-bold text-white text-[11px]">{{ $log->created_at->format('d/m/Y H:i:s') }}</div>
                                <div class="text-[9.5px] text-cyan-400">{{ $log->created_at->diffForHumans() }}</div>
                            </td>

                            <!-- ACTIVO / ENTIDAD -->
                            <td class="py-2.5 px-3.5">
                                <div class="font-bold text-cyan-300">{{ $log->entity_name ?: ($log->auditable_type ? class_basename($log->auditable_type) : 'Activo ITAM') }}</div>
                                @if($log->entity_label)
                                    <div class="text-[10px] text-obsidian-muted flex items-center gap-1">
                                        <span class="material-symbols-outlined text-xs">tag</span>
                                        <span>{{ $log->entity_label }}</span>
                                    </div>
                                @endif
                                @if($log->auditable_type === 'App\\Models\\InventoryAsset' && $log->auditable_id)
                                    <a href="{{ route('admin.inventory.assets.show', $log->auditable_id) }}" class="text-[9.5px] text-cyan-400 hover:underline flex items-center gap-0.5 mt-0.5">
                                        <span>Ver Ficha Técnica</span>
                                        <span class="material-symbols-outlined text-[10px]">open_in_new</span>
                                    </a>
                                @endif
                            </td>

                            <!-- EVENTO -->
                            <td class="py-2.5 px-3.5 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $log->event_badge_class }} inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">
                                        {{ match($log->event) {
                                            'created' => 'add_circle',
                                            'updated' => 'edit_note',
                                            'approved' => 'verified',
                                            'deleted' => 'delete',
                                            'exported' => 'download',
                                            default => 'info',
                                        } }}
                                    </span>
                                    <span>{{ $log->event_label }}</span>
                                </span>
                            </td>

                            <!-- DESCRIPCIÓN -->
                            <td class="py-2.5 px-3.5 max-w-md">
                                <div class="text-xs text-slate-100 line-clamp-2" title="{{ $log->description }}">
                                    {{ $log->description }}
                                </div>
                            </td>

                            <!-- USUARIO OPERADOR -->
                            <td class="py-2.5 px-3.5 whitespace-nowrap">
                                <div class="font-bold text-white text-[11px]">{{ $log->user_name ?: 'Sistema' }}</div>
                                @if($log->ip_address)
                                    <div class="text-[10px] text-obsidian-muted flex items-center gap-1">
                                        <span class="material-symbols-outlined text-xs">lan</span>
                                        <span>{{ $log->ip_address }}</span>
                                    </div>
                                @endif
                            </td>

                            <!-- ACCIONES / INSPECCIÓN -->
                            <td class="py-2.5 px-3.5 whitespace-nowrap text-center">
                                @if(!empty($log->old_values) || !empty($log->new_values) || !empty($log->changed_fields))
                                    <button type="button" 
                                            onclick='openDiffModal(@json($log))'
                                            class="w-7 h-7 rounded-lg bg-cyan-950/80 hover:bg-cyan-900 border border-cyan-500/40 text-cyan-300 transition inline-flex items-center justify-center cursor-pointer"
                                            title="Inspeccionar cambios de campos antes y después">
                                        <span class="material-symbols-outlined text-sm">difference</span>
                                    </button>
                                @else
                                    <span class="text-obsidian-muted/50 text-[10px] font-mono">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-obsidian-muted italic">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-3xl text-obsidian-muted/60">search_off</span>
                                    <span>No se encontraron registros de auditoría ITAM con los filtros seleccionados.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN -->
        @if($logs->hasPages())
            <div class="p-3 border-t border-obsidian-border bg-[#040b15]">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>

<!-- MODAL FORENSE DIFF INSPECTION -->
<div id="diffModal" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-[#071321] border border-cyan-500/40 rounded-2xl max-w-2xl w-full p-5 space-y-4 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between pb-3 border-b border-obsidian-border">
            <div class="flex items-center gap-2 text-cyan-400">
                <span class="material-symbols-outlined text-xl">difference</span>
                <h3 class="font-bold text-white text-sm font-mono">Inspección Forense de Cambios y Comparativa</h3>
            </div>
            <button type="button" onclick="closeDiffModal()" class="text-obsidian-muted hover:text-white transition cursor-pointer">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <div class="p-3 rounded-xl bg-obsidian-panel border border-obsidian-border flex items-center justify-between">
                <div>
                    <div class="text-[10px] text-obsidian-muted uppercase">Operación &amp; Activo:</div>
                    <div class="text-white font-bold" id="modalEntityTitle">Activo</div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] text-obsidian-muted uppercase">Fecha / Operador:</div>
                    <div class="text-cyan-300" id="modalOperatorTime">Fecha</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="p-3 rounded-xl bg-rose-950/20 border border-rose-500/30 space-y-1.5">
                    <div class="text-rose-400 font-bold text-[11px] flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">remove_circle</span>
                        <span>Valores Anteriores (Pre-Modificación):</span>
                    </div>
                    <pre id="modalOldValues" class="text-[10.5px] text-slate-300 overflow-x-auto p-2 rounded bg-black/40 border border-rose-950 max-h-48 whitespace-pre-wrap"></pre>
                </div>
                <div class="p-3 rounded-xl bg-emerald-950/20 border border-emerald-500/30 space-y-1.5">
                    <div class="text-emerald-400 font-bold text-[11px] flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">add_circle</span>
                        <span>Valores Nuevos (Post-Modificación):</span>
                    </div>
                    <pre id="modalNewValues" class="text-[10.5px] text-slate-300 overflow-x-auto p-2 rounded bg-black/40 border border-emerald-950 max-h-48 whitespace-pre-wrap"></pre>
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-2 border-t border-obsidian-border">
            <button type="button" onclick="closeDiffModal()" class="px-4 py-1.5 rounded-xl bg-obsidian-panel hover:bg-slate-800 border border-obsidian-border text-white text-xs font-mono font-bold transition cursor-pointer">
                Cerrar Inspección
            </button>
        </div>
    </div>
</div>

<script>
    function toggleExportMenu() {
        const menu = document.getElementById('exportDropdownMenu');
        if (menu) {
            menu.classList.toggle('hidden');
        }
    }

    document.addEventListener('click', function(e) {
        const container = document.getElementById('exportDropdownContainer');
        const menu = document.getElementById('exportDropdownMenu');
        if (container && menu && !container.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });

    function openDiffModal(log) {
        document.getElementById('modalEntityTitle').textContent = (log.entity_name || 'Activo') + (log.entity_label ? ' (' + log.entity_label + ')' : '');
        document.getElementById('modalOperatorTime').textContent = (log.user_name || 'Sistema') + ' • ' + (log.created_at ? log.created_at.substring(0, 19).replace('T', ' ') : '');
        
        document.getElementById('modalOldValues').textContent = log.old_values ? JSON.stringify(log.old_values, null, 2) : 'Sin datos previos o alta inicial';
        document.getElementById('modalNewValues').textContent = log.new_values ? JSON.stringify(log.new_values, null, 2) : 'Sin datos posteriores o eliminación';
        
        document.getElementById('diffModal').classList.remove('hidden');
    }

    function closeDiffModal() {
        document.getElementById('diffModal').classList.add('hidden');
    }
</script>
@endsection
