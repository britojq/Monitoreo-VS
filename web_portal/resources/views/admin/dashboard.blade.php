@extends('layouts.admin')

@section('page_title', 'Dashboard General')

@section('admin_content')
<div class="space-y-6">
    <!-- STATS OVERVIEW -->
    <div class="grid grid-cols-1 sm:grid-cols-2 {{ auth()->user()->isAdmin() ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }} gap-5">
        @if(auth()->user()->isAdmin())
        <!-- USUARIOS (Exclusivo Administrador) -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono uppercase text-obsidian-muted">Usuarios Registrados</span>
                <span class="material-symbols-outlined text-obsidian-cyan">group</span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-bold font-mono text-white">{{ $stats['users_count'] ?? 0 }}</span>
                <span class="text-xs font-mono text-obsidian-muted">activos</span>
            </div>
            <div class="mt-3">
                <a href="{{ route('admin.users.index') }}" class="text-xs font-mono text-obsidian-cyan hover:underline flex items-center gap-1">
                    Gestionar usuarios &rarr;
                </a>
            </div>
        </div>
        @endif

        <!-- SERVICIOS -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono uppercase text-obsidian-muted">Servicios Monitoreados</span>
                <span class="material-symbols-outlined text-blue-400">dns</span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-bold font-mono text-white">{{ $stats['services_active'] }}</span>
                <span class="text-xs font-mono text-obsidian-muted">/ {{ $stats['services_count'] }} total</span>
            </div>
            <div class="mt-3">
                <a href="{{ route('admin.services.index') }}" class="text-xs font-mono text-blue-400 hover:underline flex items-center gap-1">
                    {{ auth()->user()->isAdmin() ? 'Configurar servicios' : 'Ver servicios' }} &rarr;
                </a>
            </div>
        </div>

        <!-- SEDES Y ENLACES -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono uppercase text-obsidian-muted">Sedes & Equipos</span>
                <span class="material-symbols-outlined text-purple-400">domain</span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-bold font-mono text-white">{{ $stats['sites_active'] }}</span>
                <span class="text-xs font-mono text-obsidian-muted">Sedes ({{ $stats['devices_count'] }} equipos)</span>
            </div>
            <div class="mt-3">
                <a href="{{ route('admin.sites.index') }}" class="text-xs font-mono text-purple-400 hover:underline flex items-center gap-1">
                    {{ auth()->user()->isAdmin() ? 'Configurar sedes' : 'Ver sedes' }} &rarr;
                </a>
            </div>
        </div>

        <!-- PROXIES -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono uppercase text-obsidian-muted">Proxies Corporativos</span>
                <span class="material-symbols-outlined text-emerald-400">public</span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-bold font-mono text-white">{{ $stats['proxies_active'] }}</span>
                <span class="text-xs font-mono text-obsidian-muted">/ {{ $stats['proxies_count'] }} total</span>
            </div>
            <div class="mt-3">
                <a href="{{ route('admin.proxies.index') }}" class="text-xs font-mono text-emerald-400 hover:underline flex items-center gap-1">
                    {{ auth()->user()->isAdmin() ? 'Configurar proxies' : 'Ver proxies' }} &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- ACCIONES RÁPIDAS & ESTADO DE CONFIGURACIÓN -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @if(auth()->user()->isAdmin())
        <!-- SINCRONIZACIÓN Y ARCHIVOS (Solo Administrador) -->
        <div class="glass-card rounded-xl p-6 lg:col-span-2 space-y-4">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan">sync</span>
                Sincronización Bidireccional de Archivos (.conf)
            </h2>
            <p class="text-xs text-obsidian-muted leading-relaxed">
                Los cambios que realices en este panel (Servicios, Sedes, Dispositivos y Proxies) se guardan en la base de datos MySQL y se exportan automáticamente a los archivos oficiales <code class="text-obsidian-cyan font-mono">/scripts/telegram-admin-bot/config/monitoreo.conf</code> y <code class="text-obsidian-cyan font-mono">bot.conf</code> para que el motor asíncrono de Python y el bot de Telegram los utilicen en cada ciclo de 5 minutos.
            </p>
            <div class="pt-2 flex flex-wrap gap-3">
                <form action="{{ route('admin.sync.manual') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold text-xs font-mono uppercase flex items-center gap-2 hover:bg-cyan-300 transition">
                        <span class="material-symbols-outlined text-base">save</span>
                        Forzar Exportación a .conf
                    </button>
                </form>
                <button onclick="triggerImmediateScan()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan font-bold text-xs font-mono uppercase flex items-center gap-2 hover:bg-obsidian-cyan hover:text-black transition">
                    <span class="material-symbols-outlined text-base">play_arrow</span>
                    Ejecutar Escaneo Ahora
                </button>
            </div>
        </div>
        @else
        <div class="glass-card rounded-xl p-6 lg:col-span-2 space-y-4">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-400">verified_user</span>
                Consola de Operador
            </h2>
            <p class="text-xs text-obsidian-muted leading-relaxed">
                Su cuenta posee el rol de <strong class="text-obsidian-cyan">Operador</strong>. Tiene acceso en tiempo real a la supervisión, telemetría y consulta de métricas de conectividad de los servicios, sedes y proxies de la corporación.
            </p>
            <div class="p-3 rounded-lg bg-obsidian-panel border border-obsidian-border/60 text-xs font-mono text-obsidian-muted flex items-center gap-2">
                <span class="material-symbols-outlined text-sm text-obsidian-cyan">info</span>
                <span>Los parámetros de configuración son administrados centralmente por el Administrador.</span>
            </div>
        </div>
        @endif

        <!-- ÚLTIMO REPORTE DE TELEMETRÍA -->
        <div class="glass-card rounded-xl p-6 space-y-4">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-400">history</span>
                Último Snapshot
            </h2>
            @if($latestSnapshot)
                <div class="space-y-2 text-xs font-mono">
                    <div class="flex justify-between py-1 border-b border-obsidian-border/50">
                        <span class="text-obsidian-muted">Estado Global:</span>
                        <span class="font-bold {{ $latestSnapshot->global_status == 'OPERACIONAL' ? 'text-emerald-400' : 'text-amber-400' }}">{{ $latestSnapshot->global_status }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-obsidian-border/50">
                        <span class="text-obsidian-muted">Servicios:</span>
                        <span class="text-white">{{ $latestSnapshot->services_online }} / {{ $latestSnapshot->services_total }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-obsidian-border/50">
                        <span class="text-obsidian-muted">Sedes:</span>
                        <span class="text-white">{{ $latestSnapshot->sites_online }} / {{ $latestSnapshot->sites_total }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-obsidian-border/50">
                        <span class="text-obsidian-muted">Proxies:</span>
                        <span class="text-white">{{ $latestSnapshot->proxies_online }} / {{ $latestSnapshot->proxies_total }}</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-obsidian-muted">Fecha:</span>
                        <span class="text-obsidian-cyan">{{ $latestSnapshot->created_at->format('Y-m-d H:i:s') }}</span>
                    </div>
                </div>
            @else
                <p class="text-xs font-mono text-obsidian-muted">No hay snapshots registrados todavía.</p>
            @endif
        </div>
    @if(auth()->user()->isAdmin() && isset($cronConfig))
    <!-- CONTROL DE ENVÍOS PROGRAMADOS A TELEGRAM (CRON) (Exclusivo Administrador) -->
    <div class="glass-card rounded-xl p-6 space-y-5 border {{ ($cronConfig['enabled'] ?? true) ? 'border-cyan-500/30' : 'border-amber-500/30' }}">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-obsidian-border/70">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ ($cronConfig['enabled'] ?? true) ? 'bg-cyan-950/80 text-obsidian-cyan border border-cyan-500/40' : 'bg-amber-950/80 text-amber-400 border border-amber-500/40' }}">
                    <span class="material-symbols-outlined text-xl">schedule_send</span>
                </div>
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        Envíos Programados a Telegram (Cron)
                        @if($cronConfig['enabled'] ?? true)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-950/80 text-emerald-400 border border-emerald-500/40">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                ACTIVO
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-red-950/80 text-red-400 border border-red-500/40">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                PAUSADO
                            </span>
                        @endif
                    </h2>
                    <p class="text-xs text-obsidian-muted mt-0.5">
                        Control del despacho desatendido de reportes de infraestructura a los canales oficiales y grupos autorizados.
                    </p>
                </div>
            </div>

            <!-- BOTÓN INTERRUPTOR (ACTIVAR / PAUSAR) -->
            <form action="{{ route('admin.cron.toggle') }}" method="POST" onsubmit="return confirm('¿Confirmas que deseas {{ ($cronConfig['enabled'] ?? true) ? 'PAUSAR' : 'REANUDAR' }} los envíos automáticos de reportes a Telegram?');">
                @csrf
                @if($cronConfig['enabled'] ?? true)
                    <button type="submit" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-red-950/80 hover:bg-red-900 border border-red-500/50 text-red-200 font-bold text-xs font-mono uppercase flex items-center justify-center gap-2 transition shadow-lg shadow-red-950/30">
                        <span class="material-symbols-outlined text-base">pause_circle</span>
                        Pausar Envíos Automáticos
                    </button>
                @else
                    <button type="submit" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-emerald-950/80 hover:bg-emerald-900 border border-emerald-500/50 text-emerald-200 font-bold text-xs font-mono uppercase flex items-center justify-center gap-2 transition shadow-lg shadow-emerald-950/30">
                        <span class="material-symbols-outlined text-base">play_circle</span>
                        Reanudar Envíos Automáticos
                    </button>
                @endif
            </form>
        </div>

        <!-- CUADRÍCULA: HORARIOS PROGRAMADOS & AUDITORÍA -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
            <!-- LISTADO Y GESTOR DE HORARIOS (2 COLS) -->
            <div class="lg:col-span-2 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-mono font-bold text-gray-300 uppercase flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-obsidian-cyan">alarm</span>
                        Horarios Diarios de Despacho (24 horas)
                    </span>
                    @if(!empty($cronConfig['next_schedule']) && ($cronConfig['enabled'] ?? true))
                        <span class="text-[11px] font-mono text-cyan-300 bg-cyan-950/50 px-2.5 py-0.5 rounded-md border border-cyan-500/30">
                            Próximo: <strong>{{ $cronConfig['next_schedule'] }}</strong> ({{ $cronConfig['next_day'] }})
                        </span>
                    @endif
                </div>

                <!-- BADGES DE HORARIOS ACTIVOS -->
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    @forelse($cronConfig['schedules'] ?? [] as $hour)
                        <div class="flex items-center gap-2 bg-[#040d1a] border border-obsidian-border/80 hover:border-obsidian-cyan/50 px-3 py-1.5 rounded-lg text-xs font-mono text-white transition group shadow-sm">
                            <span class="material-symbols-outlined text-sm text-obsidian-cyan">schedule</span>
                            <span class="font-bold tracking-wide">{{ $hour }}</span>
                            <form action="{{ route('admin.cron.schedules.remove') }}" method="POST" class="inline" onsubmit="return confirm('¿Deseas eliminar el horario de las {{ $hour }}?');">
                                @csrf
                                <input type="hidden" name="time" value="{{ $hour }}">
                                <button type="submit" class="text-obsidian-muted hover:text-red-400 transition ml-1 flex items-center" title="Eliminar este horario">
                                    <span class="material-symbols-outlined text-sm">close</span>
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="text-xs font-mono text-amber-400/90 italic">No hay horarios de envío configurados.</p>
                    @endforelse
                </div>

                <!-- FORMULARIO PARA AÑADIR NUEVO HORARIO -->
                <form action="{{ route('admin.cron.schedules.add') }}" method="POST" class="pt-2 flex flex-wrap items-center gap-2">
                    @csrf
                    <div class="relative">
                        <input type="time" name="time" required class="bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-3 py-2 focus:outline-none focus:border-obsidian-cyan">
                    </div>
                    <button type="submit" class="px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-cyan-950/60 font-bold text-xs font-mono flex items-center gap-1.5 transition">
                        <span class="material-symbols-outlined text-base">add</span>
                        Añadir Horario
                    </button>
                </form>
            </div>

            <!-- DETALLES DE AUDITORÍA Y SINCRONIZACIÓN (1 COL) -->
            <div class="bg-[#040d1a]/80 border border-obsidian-border/70 rounded-xl p-4 space-y-2 text-xs font-mono">
                <div class="text-[11px] uppercase font-bold text-obsidian-muted flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs">info</span>
                    Auditoría y Sincronización
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Último cambio:</span>
                    <span class="text-white">{{ $cronConfig['updated_at'] ?: 'N/A' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Modificado por:</span>
                    <span class="text-cyan-300 truncate max-w-[150px]" title="{{ $cronConfig['updated_by'] }}">{{ $cronConfig['updated_by'] ?: 'N/A' }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-obsidian-muted">Canal sincronizado:</span>
                    <span class="text-emerald-400">Telegram Bot (/cron)</span>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
