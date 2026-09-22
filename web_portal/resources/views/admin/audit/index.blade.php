@extends('layouts.admin')

@section('page_title', 'Pista de Auditoría & Trazabilidad')

@section('admin_content')
<style>
    /* Barra de desplazamiento horizontal visible, resaltada en cyan */
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
            <div class="flex items-center gap-2 mb-1.5">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-xs font-mono font-semibold group shadow-xs">
                    <span class="material-symbols-outlined text-sm group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-xs font-mono text-obsidian-muted">Auditoría del Sistema</span>
            </div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan">policy</span>
                Pista de Auditoría & Trazabilidad
            </h2>
            <p class="text-xs font-mono text-obsidian-muted">Registro exhaustivo de inicios de sesión, cambios en infraestructura y modificaciones por usuario</p>
        </div>
        <div class="flex items-center gap-2.5">
            <!-- BOTÓN DESCARGAR / EXPORTAR CON DROPDOWN -->
            <div class="relative" id="exportDropdownContainer">
                <button type="button" onclick="toggleExportMenu()" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border hover:border-obsidian-cyan/70 text-white text-xs font-mono font-semibold flex items-center gap-1.5 transition shadow-xs cursor-pointer group">
                    <span class="material-symbols-outlined text-base text-cyan-400 group-hover:scale-110 transition-transform">download</span>
                    <span>Descargar Registros</span>
                    <span class="material-symbols-outlined text-sm text-obsidian-muted">expand_more</span>
                </button>
                <div id="exportDropdownMenu" class="absolute right-0 mt-1 w-64 rounded-xl bg-[#071321] border border-obsidian-border shadow-2xl z-50 hidden overflow-hidden divide-y divide-obsidian-border/50 animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-3 py-2 text-[10px] font-mono text-obsidian-muted uppercase tracking-wider bg-obsidian-panel/80">
                        Seleccionar formato de descarga:
                    </div>
                    <div class="p-1.5 space-y-1">
                        <a href="{{ route('admin.audit.export', array_merge(request()->query(), ['format' => 'csv'])) }}" onclick="toggleExportMenu()" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-mono text-slate-200 hover:bg-emerald-950/40 hover:text-emerald-300 border border-transparent hover:border-emerald-500/30 transition group">
                            <span class="material-symbols-outlined text-lg text-emerald-400 group-hover:scale-110 transition-transform">table_view</span>
                            <div>
                                <div class="font-bold text-white text-[11px]">CSV (Excel)</div>
                                <div class="text-[9.5px] text-obsidian-muted">Compatible con hojas de cálculo UTF-8</div>
                            </div>
                        </a>
                        <a href="{{ route('admin.audit.export', array_merge(request()->query(), ['format' => 'json'])) }}" onclick="toggleExportMenu()" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-mono text-slate-200 hover:bg-cyan-950/40 hover:text-cyan-300 border border-transparent hover:border-cyan-500/30 transition group">
                            <span class="material-symbols-outlined text-lg text-cyan-400 group-hover:scale-110 transition-transform">data_object</span>
                            <div>
                                <div class="font-bold text-white text-[11px]">JSON Estructurado</div>
                                <div class="text-[9.5px] text-obsidian-muted">Respaldo técnico forense con diffs</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <span class="px-3 py-1.5 rounded-lg bg-emerald-950/80 border border-emerald-500/50 text-emerald-400 text-xs font-mono font-bold flex items-center gap-1.5 shadow-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Registro en Tiempo Real
            </span>
        </div>
    </div>

    <!-- TARJETAS KPI -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- TOTAL EVENTOS -->
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10.5px] font-mono text-obsidian-muted uppercase tracking-wider">Total Eventos</p>
                <p class="text-2xl font-bold font-mono text-white mt-1">{{ number_format($totalLogs) }}</p>
                <p class="text-[10px] font-mono text-obsidian-muted/80 mt-0.5">Historial acumulado</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-obsidian-panel border border-obsidian-border flex items-center justify-center text-obsidian-cyan">
                <span class="material-symbols-outlined text-xl">history</span>
            </div>
        </div>

        <!-- LOGINS HOY -->
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10.5px] font-mono text-obsidian-muted uppercase tracking-wider">Inicios de Sesión Hoy</p>
                <p class="text-2xl font-bold font-mono text-emerald-400 mt-1">{{ number_format($loginsToday) }}</p>
                <p class="text-[10px] font-mono text-emerald-500/80 mt-0.5">Accesos autorizados</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-950/50 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                <span class="material-symbols-outlined text-xl">login</span>
            </div>
        </div>

        <!-- CAMBIOS HOY -->
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10.5px] font-mono text-obsidian-muted uppercase tracking-wider">Modificaciones Hoy</p>
                <p class="text-2xl font-bold font-mono text-cyan-400 mt-1">{{ number_format($changesToday) }}</p>
                <p class="text-[10px] font-mono text-cyan-500/80 mt-0.5">Creaciones y ediciones</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-cyan-950/50 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                <span class="material-symbols-outlined text-xl">edit_document</span>
            </div>
        </div>

        <!-- FALLOS HOY -->
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10.5px] font-mono text-obsidian-muted uppercase tracking-wider">Accesos Fallidos Hoy</p>
                <p class="text-2xl font-bold font-mono text-rose-400 mt-1">{{ number_format($failedLoginsToday) }}</p>
                <p class="text-[10px] font-mono text-rose-500/80 mt-0.5">Intentos bloqueados</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-rose-950/50 border border-rose-500/30 flex items-center justify-center text-rose-400">
                <span class="material-symbols-outlined text-xl">gavel</span>
            </div>
        </div>
    </div>

    <!-- BARRA DE FILTROS -->
    <div class="glass-card rounded-xl p-3.5 border border-obsidian-border shadow-md">
        <form method="GET" action="{{ route('admin.audit.index') }}" class="space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-2.5">
                <!-- BUSCADOR TEXTUAL -->
                <div class="md:col-span-4 relative">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-obsidian-muted text-lg">search</span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar usuario, IP, entidad, cambios..." class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg pl-9 pr-3 py-2 text-xs font-mono text-white placeholder-obsidian-muted focus:outline-none focus:border-obsidian-cyan transition">
                </div>

                <!-- FILTRO EVENTO -->
                <div class="md:col-span-2">
                    <select name="event" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg px-2.5 py-2 text-xs font-mono text-white focus:outline-none focus:border-obsidian-cyan transition">
                        <option value="all" {{ request('event') == 'all' ? 'selected' : '' }}>Todos los Eventos</option>
                        <option value="login" {{ request('event') == 'login' ? 'selected' : '' }}>Inicios de Sesión</option>
                        <option value="logout" {{ request('event') == 'logout' ? 'selected' : '' }}>Cierres de Sesión</option>
                        <option value="created" {{ request('event') == 'created' ? 'selected' : '' }}>Creaciones</option>
                        <option value="updated" {{ request('event') == 'updated' ? 'selected' : '' }}>Modificaciones</option>
                        <option value="toggled" {{ request('event') == 'toggled' ? 'selected' : '' }}>Cambios de Estado</option>
                        <option value="deleted" {{ request('event') == 'deleted' ? 'selected' : '' }}>Eliminaciones</option>
                        <option value="login_failed" {{ request('event') == 'login_failed' ? 'selected' : '' }}>Accesos Fallidos</option>
                    </select>
                </div>

                <!-- FILTRO MÓDULO -->
                <div class="md:col-span-2">
                    <select name="module" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg px-2.5 py-2 text-xs font-mono text-white focus:outline-none focus:border-obsidian-cyan transition">
                        <option value="all" {{ request('module') == 'all' ? 'selected' : '' }}>Todos los Módulos</option>
                        <option value="auth" {{ request('module') == 'auth' ? 'selected' : '' }}>Seguridad & Acceso</option>
                        <option value="services" {{ request('module') == 'services' ? 'selected' : '' }}>Servicios</option>
                        <option value="sites" {{ request('module') == 'sites' ? 'selected' : '' }}>Sedes & Enlaces</option>
                        <option value="devices" {{ request('module') == 'devices' ? 'selected' : '' }}>Equipos de Red</option>
                        <option value="proxies" {{ request('module') == 'proxies' ? 'selected' : '' }}>Proxies</option>
                        <option value="users" {{ request('module') == 'users' ? 'selected' : '' }}>Usuarios</option>
                    </select>
                </div>

                <!-- FILTRO USUARIO -->
                <div class="md:col-span-2">
                    <select name="user_id" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg px-2.5 py-2 text-xs font-mono text-white focus:outline-none focus:border-obsidian-cyan transition">
                        <option value="all" {{ request('user_id') == 'all' ? 'selected' : '' }}>Todos los Usuarios</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->username ?: $u->email }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- FILTRO FECHA -->
                <div class="md:col-span-2">
                    <select name="date" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg px-2.5 py-2 text-xs font-mono text-white focus:outline-none focus:border-obsidian-cyan transition">
                        <option value="all" {{ request('date') == 'all' || !request('date') ? 'selected' : '' }}>Cualquier Fecha</option>
                        <option value="today" {{ request('date') == 'today' ? 'selected' : '' }}>Hoy</option>
                        <option value="yesterday" {{ request('date') == 'yesterday' ? 'selected' : '' }}>Ayer</option>
                        <option value="week" {{ request('date') == 'week' ? 'selected' : '' }}>Últimos 7 días</option>
                        <option value="month" {{ request('date') == 'month' ? 'selected' : '' }}>Últimos 30 días</option>
                    </select>
                </div>
            </div>

            <!-- BOTONES DE ACCIÓN -->
            <div class="flex flex-wrap items-center justify-between pt-2 border-t border-obsidian-border/50 text-xs font-mono gap-2">
                <div class="text-obsidian-muted flex items-center gap-2">
                    <span class="text-cyan-400 font-bold flex items-center gap-1 text-[11px]">
                        <span class="material-symbols-outlined text-sm">filter_alt</span>
                        Filtros de Trazabilidad
                    </span>
                    @if(request('search') || (request('event') && request('event') !== 'all') || (request('module') && request('module') !== 'all') || (request('user_id') && request('user_id') !== 'all') || (request('date') && request('date') !== 'all'))
                        <span class="px-2 py-0.5 rounded bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 text-[10px]">
                            Filtros aplicados
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.audit.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="px-3 py-1.5 rounded-lg bg-emerald-950/60 border border-emerald-500/40 text-emerald-300 hover:bg-emerald-500 hover:text-black transition flex items-center gap-1.5 text-xs shadow-xs font-semibold" title="Descargar registros filtrados en formato CSV (Excel)">
                        <span class="material-symbols-outlined text-sm">table_view</span>
                        Exportar CSV
                    </a>

                    @if(request('search') || (request('event') && request('event') !== 'all') || (request('module') && request('module') !== 'all') || (request('user_id') && request('user_id') !== 'all') || (request('date') && request('date') !== 'all'))
                        <a href="{{ route('admin.audit.index') }}" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition flex items-center gap-1 text-xs">
                            <span class="material-symbols-outlined text-sm">clear</span>
                            Limpiar Filtros
                        </a>
                    @endif
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-obsidian-cyan text-black font-semibold hover:bg-obsidian-cyan/80 transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                        <span class="material-symbols-outlined text-sm">search</span>
                        Aplicar Filtros
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- TABLA DE PISTA DE AUDITORÍA -->
    <div class="glass-card rounded-xl overflow-hidden shadow-xl border border-obsidian-border/80">
        <div class="custom-table-scroll">
            <table class="w-full text-left font-mono">
                <thead class="bg-obsidian-panel border-b border-obsidian-border text-obsidian-muted uppercase text-[9px] tracking-wider">
                    <tr>
                        <th class="px-2.5 py-2 whitespace-nowrap">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[11px] text-obsidian-cyan">schedule</span>
                                Fecha & Hora
                            </span>
                        </th>
                        <th class="px-2.5 py-2 whitespace-nowrap">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[11px] text-obsidian-cyan">person</span>
                                Usuario / Actor
                            </span>
                        </th>
                        <th class="px-2.5 py-2 whitespace-nowrap text-center">
                            <span class="inline-flex items-center gap-1">
                                <span class="material-symbols-outlined text-[11px] text-obsidian-cyan">flag</span>
                                Evento
                            </span>
                        </th>
                        <th class="px-2.5 py-2 whitespace-nowrap">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[11px] text-obsidian-cyan">category</span>
                                Módulo & Entidad
                            </span>
                        </th>
                        <th class="px-2.5 py-2">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[11px] text-obsidian-cyan">description</span>
                                Detalle / Acción
                            </span>
                        </th>
                        <th class="px-2.5 py-2 whitespace-nowrap text-center">
                            <span class="inline-flex items-center gap-1">
                                <span class="material-symbols-outlined text-[11px] text-obsidian-cyan">lan</span>
                                IP Origen
                            </span>
                        </th>
                        <th class="px-2.5 py-2 whitespace-nowrap text-right">
                            <span class="inline-flex items-center gap-1">
                                <span class="material-symbols-outlined text-[11px] text-obsidian-cyan">visibility</span>
                                Acción
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/60">
                    @forelse($logs as $log)
                        <tr class="hover:bg-obsidian-panel/50 transition">
                            <!-- FECHA Y HORA -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <div class="font-bold text-white text-[9.5px] font-mono flex items-center gap-1">
                                    <span class="text-slate-300">{{ $log->created_at->format('Y-m-d') }}</span>
                                    <span class="text-cyan-400 font-bold">{{ $log->created_at->format('H:i:s') }}</span>
                                </div>
                                <div class="text-[8.5px] text-obsidian-muted font-mono mt-0.5">{{ $log->created_at->diffForHumans() }}</div>
                            </td>

                            <!-- USUARIO / ACTOR -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-5 h-5 rounded-full bg-cyan-950/60 border border-cyan-500/40 flex items-center justify-center text-cyan-300 font-bold text-[8.5px] font-mono shrink-0">
                                        {{ strtoupper(substr($log->user_name ?: ($log->user_email ?: '?'), 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="font-sans font-semibold text-white text-[10.5px] leading-tight">
                                            {{ $log->user_name ?: 'Anónimo / Sistema' }}
                                        </div>
                                        <div class="flex items-center gap-1 mt-0.5">
                                            @if($log->user_role === 'admin' || $log->user_role === 'SuperAdmin')
                                                <span class="px-1 py-0.2 rounded text-[8px] font-mono font-bold uppercase bg-purple-950/80 text-purple-300 border border-purple-500/40">
                                                    Admin
                                                </span>
                                            @elseif($log->user_role === 'operator')
                                                <span class="px-1 py-0.2 rounded text-[8px] font-mono font-bold uppercase bg-blue-950/80 text-blue-300 border border-blue-500/40">
                                                    Operador
                                                </span>
                                            @elseif($log->user_role)
                                                <span class="px-1 py-0.2 rounded text-[8px] font-mono bg-obsidian-panel border border-obsidian-border text-obsidian-muted">
                                                    {{ $log->user_role }}
                                                </span>
                                            @else
                                                <span class="text-[8px] text-obsidian-muted/60 font-mono">Sistema</span>
                                            @endif

                                            @if($log->user_email)
                                                <span class="text-[8.5px] text-obsidian-muted/80 font-mono max-w-[120px] truncate" title="{{ $log->user_email }}">
                                                    {{ $log->user_email }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- EVENTO -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap text-center">
                                <span class="px-1.5 py-0.5 rounded text-[8.5px] font-mono font-bold uppercase inline-flex items-center gap-1 {{ $log->event_badge_class }}">
                                    <span class="material-symbols-outlined text-[10px]">{{ $log->event_icon }}</span>
                                    {{ $log->event_label }}
                                </span>
                            </td>

                            <!-- MÓDULO & ENTIDAD -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <div class="flex items-center gap-1 text-obsidian-cyan font-semibold text-[10px]">
                                    <span class="material-symbols-outlined text-[11px]">{{ $log->module_icon }}</span>
                                    <span>{{ $log->module_label }}</span>
                                </div>
                                <div class="text-[9px] text-slate-300 font-sans mt-0.5">
                                    @if($log->entity_name)
                                        <span class="text-obsidian-muted text-[8.5px] uppercase font-mono">{{ $log->entity_name }}:</span>
                                    @endif
                                    <strong class="text-cyan-300 font-mono text-[9px]">{{ $log->entity_label ?: 'N/A' }}</strong>
                                </div>
                            </td>

                            <!-- DETALLE / ACCIÓN -->
                            <td class="px-2.5 py-1.5 max-w-md">
                                <p class="text-slate-200 text-[10.5px] leading-snug font-sans">{{ $log->description }}</p>
                                
                                @if(!empty($log->changed_fields) && is_array($log->changed_fields))
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach($log->changed_fields as $field)
                                            <span class="px-1 py-0.2 rounded bg-cyan-950/60 border border-cyan-500/30 text-[8px] text-cyan-300 font-mono">
                                                {{ \App\Services\AuditService::getFieldLabel($field) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            <!-- IP ORIGEN -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap text-center">
                                <div class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-[9.5px] font-mono text-cyan-300">
                                    <span class="material-symbols-outlined text-[10px] text-obsidian-muted">lan</span>
                                    {{ $log->ip_address ?: '127.0.0.1' }}
                                </div>
                            </td>

                            <!-- ACCIÓN / VER DIFF -->
                            <td class="px-2.5 py-1.5 text-right whitespace-nowrap">
                                @if(!empty($log->old_values) || !empty($log->new_values))
                                    <button onclick="openAuditDiffModal({{ $log->id }})" class="px-1.5 py-0.5 rounded bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[9.5px] font-semibold inline-flex items-center gap-1 shadow-xs cursor-pointer" title="Ver detalle de cambios">
                                        <span class="material-symbols-outlined text-[10.5px]">difference</span>
                                        Ver Cambios
                                    </button>
                                @else
                                    <button onclick="openAuditDetailModal({{ $log->id }})" class="px-1.5 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white transition text-[9.5px] inline-flex items-center gap-1 cursor-pointer" title="Ver información del evento">
                                        <span class="material-symbols-outlined text-[10.5px]">visibility</span>
                                        Detalle
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-obsidian-muted font-mono">
                                <span class="material-symbols-outlined text-3xl mb-2 text-obsidian-muted/40">search_off</span>
                                <p class="text-xs font-bold text-white">No se encontraron registros de auditoría</p>
                                <p class="text-[10px] mt-1">Intente ajustar los filtros de búsqueda o el rango de fechas.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PIE DE TABLA / PAGINACIÓN OBSIDIAN DARK -->
        @if($logs->hasPages())
            <div class="border-t border-obsidian-border/80">
                {{ $logs->links('vendor.pagination.obsidian') }}
            </div>
        @else
            <div class="px-3.5 py-2 bg-obsidian-panel/70 border-t border-obsidian-border/80 flex items-center justify-between text-[10px] font-mono text-obsidian-muted">
                <div>
                    Mostrando <strong class="text-white">{{ $logs->count() }}</strong> de <strong class="text-cyan-400">{{ $logs->total() }}</strong> registros de auditoría
                </div>
                <div class="text-[9px] text-obsidian-muted/60">
                    Página 1 de 1
                </div>
            </div>
        @endif
    </div>
</div>

<!-- MODAL DE DETALLE Y DIFERENCIAS (DIFFS) -->
<div id="auditDiffModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden">
    <div class="glass-card rounded-2xl border border-obsidian-border max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <!-- MODAL HEADER -->
        <div class="px-6 py-4 bg-obsidian-panel border-b border-obsidian-border flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div id="modalEventBadge" class="px-2.5 py-1 rounded-md text-[11px] font-mono font-bold uppercase inline-flex items-center gap-1">
                    <span id="modalEventIcon" class="material-symbols-outlined text-sm">history</span>
                    <span id="modalEventText">EVENTO</span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span>Detalle de Auditoría</span>
                        <span id="modalAuditId" class="text-xs font-mono text-obsidian-muted">#</span>
                    </h3>
                    <p id="modalAuditTimestamp" class="text-[11px] font-mono text-obsidian-muted">Fecha y Hora</p>
                </div>
            </div>
            <button onclick="closeAuditModal()" class="w-8 h-8 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white hover:border-red-500/50 flex items-center justify-center transition">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <!-- MODAL BODY -->
        <div class="p-6 overflow-y-auto space-y-5 font-mono text-xs">
            <!-- TARJETA DE RESUMEN -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 rounded-xl bg-obsidian-panel/60 border border-obsidian-border text-xs">
                <div>
                    <span class="text-[10px] text-obsidian-muted uppercase block">Usuario / Actor</span>
                    <span id="modalUser" class="text-white font-bold text-xs mt-0.5 block font-sans">Usuario</span>
                    <span id="modalUserEmail" class="text-[10px] text-obsidian-cyan">email</span>
                </div>
                <div>
                    <span class="text-[10px] text-obsidian-muted uppercase block">Módulo & Entidad</span>
                    <span id="modalModuleEntity" class="text-white font-bold text-xs mt-0.5 block">Módulo</span>
                    <span id="modalEntityLabel" class="text-[10px] text-cyan-300">Etiqueta</span>
                </div>
                <div>
                    <span class="text-[10px] text-obsidian-muted uppercase block">Dirección IP & Origen</span>
                    <span id="modalIp" class="text-white font-bold text-xs mt-0.5 block">127.0.0.1</span>
                    <span id="modalRelativeTime" class="text-[10px] text-obsidian-muted">Hace x tiempo</span>
                </div>
            </div>

            <!-- DESCRIPCIÓN DEL EVENTO -->
            <div class="p-3.5 rounded-xl bg-obsidian-panel/40 border border-obsidian-border">
                <span class="text-[10px] text-obsidian-muted uppercase tracking-wider block mb-1">Descripción del Registro</span>
                <p id="modalDescription" class="text-white text-xs leading-relaxed font-sans">Descripción completa...</p>
            </div>

            <!-- TABLA DE DIFERENCIAS (DIFFS) -->
            <div id="modalDiffContainer">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-cyan-400 text-sm">difference</span>
                        Comparativa de Valores Modificados (Antes vs Después)
                    </h4>
                    <span id="modalDiffCount" class="text-[10px] text-obsidian-muted">0 cambios</span>
                </div>

                <div class="border border-obsidian-border rounded-xl overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-obsidian-panel border-b border-obsidian-border text-obsidian-muted uppercase text-[10px]">
                            <tr>
                                <th class="px-4 py-2.5 w-1/4">Campo</th>
                                <th class="px-4 py-2.5 w-3/8 text-rose-300 bg-rose-950/20">Valor Anterior</th>
                                <th class="px-4 py-2.5 w-3/8 text-emerald-300 bg-emerald-950/20">Valor Nuevo</th>
                            </tr>
                        </thead>
                        <tbody id="modalDiffTbody" class="divide-y divide-obsidian-border/50">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- AGENTE DE NAVEGADOR -->
            <div class="text-[10px] text-obsidian-muted/70 truncate pt-2 border-t border-obsidian-border/40">
                <span>User Agent: </span>
                <span id="modalUserAgent" class="font-mono">N/A</span>
            </div>
        </div>

        <!-- MODAL FOOTER -->
        <div class="px-6 py-3.5 bg-obsidian-panel border-t border-obsidian-border flex justify-end">
            <button onclick="closeAuditModal()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-white hover:bg-obsidian-border transition text-xs font-mono font-semibold">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
    async function openAuditDiffModal(id) {
        try {
            const resp = await fetch(`{{ url('admin/audit') }}/${id}`);
            const data = await resp.json();
            
            if (!data.success || !data.audit) {
                alert('No se pudo cargar la información del registro de auditoría.');
                return;
            }

            populateModal(data.audit);
        } catch (e) {
            console.error(e);
            alert('Error al consultar el registro de auditoría.');
        }
    }

    async function openAuditDetailModal(id) {
        openAuditDiffModal(id);
    }

    function populateModal(audit) {
        document.getElementById('modalAuditId').innerText = `#${audit.id}`;
        document.getElementById('modalAuditTimestamp').innerText = `${audit.created_at} (${audit.created_at_human})`;
        
        // Badge
        const badge = document.getElementById('modalEventBadge');
        badge.className = `px-2.5 py-1 rounded-md text-[11px] font-mono font-bold uppercase inline-flex items-center gap-1 ${audit.event_badge}`;
        document.getElementById('modalEventIcon').innerText = audit.event_icon;
        document.getElementById('modalEventText').innerText = audit.event_label;

        // Info
        document.getElementById('modalUser').innerText = audit.user_name || 'Sistema Automático';
        document.getElementById('modalUserEmail').innerText = audit.user_email || (audit.user_role ? `Rol: ${audit.user_role}` : 'Sin correo');
        document.getElementById('modalModuleEntity').innerText = audit.module_label;
        document.getElementById('modalEntityLabel').innerText = audit.entity_name ? `${audit.entity_name}: ${audit.entity_label}` : (audit.entity_label || 'N/A');
        document.getElementById('modalIp').innerText = audit.ip_address || '127.0.0.1';
        document.getElementById('modalRelativeTime').innerText = audit.created_at_human;
        document.getElementById('modalDescription').innerText = audit.description || 'Sin descripción adicional';
        document.getElementById('modalUserAgent').innerText = audit.user_agent || 'N/A';

        // Diff Table
        const tbody = document.getElementById('modalDiffTbody');
        tbody.innerHTML = '';

        if (audit.diff && audit.diff.length > 0) {
            document.getElementById('modalDiffContainer').classList.remove('hidden');
            document.getElementById('modalDiffCount').innerText = `${audit.diff.length} campo(s) afectado(s)`;

            audit.diff.forEach(item => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-obsidian-panel/30 transition';

                const formatVal = (v) => {
                    if (v === null || v === undefined || v === '') {
                        return '<span class="text-obsidian-muted/60 italic">(Vacío / Sin asignar)</span>';
                    }
                    if (typeof v === 'boolean') {
                        return v ? '<span class="text-emerald-400 font-bold">Activo / Sí</span>' : '<span class="text-rose-400 font-bold">Inactivo / No</span>';
                    }
                    if (typeof v === 'object') {
                        return `<pre class="text-[10px] text-cyan-300 font-mono">${JSON.stringify(v, null, 2)}</pre>`;
                    }
                    return `<span class="break-all font-mono">${escapeHtml(String(v))}</span>`;
                };

                tr.innerHTML = `
                    <td class="px-4 py-3 font-semibold text-white">
                        <div>${escapeHtml(item.label)}</div>
                        <div class="text-[9px] text-obsidian-muted font-mono">${escapeHtml(item.field)}</div>
                    </td>
                    <td class="px-4 py-3 bg-rose-950/10 text-rose-300/90 border-l border-r border-obsidian-border/30">
                        ${formatVal(item.old)}
                    </td>
                    <td class="px-4 py-3 bg-emerald-950/10 text-emerald-300">
                        ${formatVal(item.new)}
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            document.getElementById('modalDiffContainer').classList.add('hidden');
        }

        document.getElementById('auditDiffModal').classList.remove('hidden');
    }

    function closeAuditModal() {
        document.getElementById('auditDiffModal').classList.add('hidden');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Manejo de menú desplegable de exportación
    function toggleExportMenu() {
        const menu = document.getElementById('exportDropdownMenu');
        if (menu) {
            menu.classList.toggle('hidden');
        }
    }

    // Cerrar menú si se hace clic afuera
    document.addEventListener('click', function(event) {
        const container = document.getElementById('exportDropdownContainer');
        const menu = document.getElementById('exportDropdownMenu');
        if (container && menu && !container.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });

    // Cerrar al presionar Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAuditModal();
            const menu = document.getElementById('exportDropdownMenu');
            if (menu) menu.classList.add('hidden');
        }
    });
</script>
@endsection
