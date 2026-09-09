@extends('layouts.admin')

@section('page_title', 'Dashboard')

@section('admin_content')
<div class="space-y-6">
    <!-- ========================================================================= -->
    <!-- SECCIÓN SUPERIOR: SUPERVISIÓN EN VIVO (IDÉNTICA A LA VISTA PÚBLICA)       -->
    <!-- ========================================================================= -->
    @include('partials.monitoring_board', ['isDashboard' => true])

    <!-- ========================================================================= -->
    <!-- SECCIÓN INFERIOR: GESTIÓN ADMINISTRATIVA Y TELEMETRÍA                     -->
    <!-- ========================================================================= -->
    <div class="pt-6 pb-2 border-t border-obsidian-border/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-obsidian-cyan/15 border border-obsidian-cyan/40 flex items-center justify-center text-obsidian-cyan shadow-md">
                <span class="material-symbols-outlined text-xl">admin_panel_settings</span>
            </div>
            <div>
                <h2 class="text-sm sm:text-base font-bold text-white tracking-wide uppercase font-mono">
                    Panel de Gestión & Telemetría Administrativa
                </h2>
                <p class="text-[11px] font-mono text-obsidian-muted">
                    {{ auth()->user()->isAdmin() ? 'Herramientas de configuración, sincronización y control del sistema' : 'Consola de supervisión y consulta operativa' }}
                </p>
            </div>
        </div>
        <span class="px-3 py-1 rounded-full text-xs font-mono font-bold self-start sm:self-auto {{ auth()->user()->isAdmin() ? 'bg-obsidian-cyan/20 text-obsidian-cyan border border-obsidian-cyan/40' : 'bg-emerald-950/60 text-emerald-400 border border-emerald-500/40' }}">
            {{ auth()->user()->isAdmin() ? 'ROL: ADMINISTRADOR' : 'ROL: OPERADOR' }}
        </span>
    </div>

    <!-- STATS OVERVIEW -->
    <div class="grid grid-cols-1 sm:grid-cols-2 {{ auth()->user()->isAdmin() ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }} gap-5">
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

        <!-- DISPOSITIVOS DE RED (VALLE SECO) -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono uppercase text-obsidian-muted">Dispositivos de Red</span>
                <span class="material-symbols-outlined text-cyan-400">router</span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-bold font-mono text-white">{{ $stats['network_devices_active'] ?? 0 }}</span>
                <span class="text-xs font-mono text-obsidian-muted">/ {{ $stats['network_devices_count'] ?? 0 }} total</span>
            </div>
            <div class="mt-3">
                <a href="{{ route('admin.devices.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    {{ auth()->user()->isAdmin() ? 'Gestionar dispositivos' : 'Ver detalles' }} &rarr;
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
                Los cambios que realices en este panel (Servicios, Sedes, Dispositivos y Proxies) se guardan en la base de datos MySQL y se exportan automáticamente a los archivos oficiales <code class="text-obsidian-cyan font-mono">/scripts/telegram-admin-bot/config/monitoreo.conf</code> y <code class="text-obsidian-cyan font-mono">bot.conf</code> para que el motor asíncrono de Python y el bot de Telegram los utilicen según la frecuencia de chequeo establecida (actualmente cada {{ $cronConfig['web_check_interval'] ?? 10 }} minutos).
            </p>
            <div class="pt-2 flex flex-wrap gap-3">
                @if($isClusterSlave)
                    <button type="button" disabled class="px-4 py-2 rounded-lg bg-gray-800 text-gray-400 border border-gray-700 font-bold text-xs font-mono uppercase flex items-center gap-2 cursor-not-allowed opacity-60" title="Deshabilitado en Modo Esclavo">
                        <span class="material-symbols-outlined text-base">lock</span>
                        Solo Lectura (Modo Esclavo)
                    </button>
                @else
                    <form action="{{ route('admin.sync.manual') }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold text-xs font-mono uppercase flex items-center gap-2 hover:bg-cyan-300 transition cursor-pointer">
                            <span class="material-symbols-outlined text-base">save</span>
                            Forzar Exportación a .conf
                        </button>
                    </form>
                @endif
                <button onclick="triggerImmediateScan()" class="px-4 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan font-bold text-xs font-mono uppercase flex items-center gap-2 hover:bg-obsidian-cyan hover:text-black transition cursor-pointer">
                    <span class="material-symbols-outlined text-base">play_arrow</span>
                    {{ $isClusterSlave ? 'Sincronizar con Master Ahora' : 'Ejecutar Escaneo Ahora' }}
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
                    <button type="submit" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-red-950/80 hover:bg-red-900 border border-red-500/50 text-red-200 font-bold text-xs font-mono uppercase flex items-center justify-center gap-2 transition shadow-lg shadow-red-950/30 cursor-pointer">
                        <span class="material-symbols-outlined text-base">pause_circle</span>
                        Pausar Envíos Automáticos
                    </button>
                @else
                    <button type="submit" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-emerald-950/80 hover:bg-emerald-900 border border-emerald-500/50 text-emerald-200 font-bold text-xs font-mono uppercase flex items-center justify-center gap-2 transition shadow-lg shadow-emerald-950/30 cursor-pointer">
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
                                <button type="submit" class="text-obsidian-muted hover:text-red-400 transition ml-1 flex items-center cursor-pointer" title="Eliminar este horario">
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
                    <button type="submit" class="px-3 py-2 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-cyan-950/60 font-bold text-xs font-mono flex items-center gap-1.5 transition cursor-pointer">
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

    <!-- FRECUENCIA DE CHEQUEO Y ACTUALIZACIÓN EN EL PORTAL WEB (Exclusivo Administrador) -->
    <div class="glass-card rounded-xl p-6 space-y-5 border border-cyan-500/30">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-obsidian-border/70">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-cyan-950/80 text-obsidian-cyan border border-cyan-500/40">
                    <span class="material-symbols-outlined text-xl">update</span>
                </div>
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        Frecuencia de Chequeo Web & Telemetría
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-cyan-950/80 text-cyan-300 border border-cyan-500/40">
                            CADA {{ $cronConfig['web_check_interval'] ?? 10 }} MINUTOS
                        </span>
                    </h2>
                    <p class="text-xs text-obsidian-muted mt-0.5">
                        Intervalo en que el servicio de monitoreo en segundo plano ejecuta la verificación de enlaces y actualiza este portal web.
                    </p>
                </div>
            </div>

            <!-- BOTÓN ESCANEAR AHORA -->
            <button onclick="triggerImmediateScan()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-bold text-xs font-mono uppercase flex items-center justify-center gap-2 transition cursor-pointer">
                <span class="material-symbols-outlined text-base">play_arrow</span>
                Escanear Ahora
            </button>
        </div>

        <!-- CUADRÍCULA: SELECTOR DE INTERVALO & AUDITORÍA -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
            <!-- SELECTOR DE FRECUENCIA Y ACCESOS RÁPIDOS (2 COLS) -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-mono font-bold text-gray-300 uppercase flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-obsidian-cyan">timer</span>
                        Ajustar Frecuencia de Ejecución
                    </span>
                    <span class="text-[11px] font-mono text-cyan-300 bg-cyan-950/50 px-2.5 py-0.5 rounded-md border border-cyan-500/30">
                        Actual: <strong>{{ $cronConfig['web_check_interval'] ?? 10 }} min</strong>
                    </span>
                </div>

                <!-- FORMULARIO DE ACTUALIZACIÓN -->
                <form action="{{ route('admin.cron.interval.update') }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative flex-1 min-w-[200px] max-w-xs">
                            <input type="number" name="interval_minutes" id="web_interval_input" min="1" max="1440" required
                                value="{{ $cronConfig['web_check_interval'] ?? 10 }}"
                                class="w-full bg-[#040d1a] border border-obsidian-border text-white text-sm font-mono rounded-lg px-4 py-2.5 focus:outline-none focus:border-obsidian-cyan">
                            <span class="absolute right-3 top-2.5 text-xs font-mono text-obsidian-muted pointer-events-none">minutos</span>
                        </div>
                        @if($isClusterSlave)
                            <button type="button" disabled class="px-5 py-2.5 rounded-lg bg-gray-800 text-gray-400 font-bold text-xs font-mono uppercase flex items-center gap-2 cursor-not-allowed opacity-60" title="Configurado exclusivamente en el Servidor Master">
                                <span class="material-symbols-outlined text-base">lock</span>
                                Configurado en Master
                            </button>
                        @else
                            <button type="submit" class="px-5 py-2.5 rounded-lg bg-obsidian-cyan text-black hover:bg-cyan-300 font-bold text-xs font-mono uppercase flex items-center gap-2 transition cursor-pointer shadow-lg shadow-cyan-950/40">
                                <span class="material-symbols-outlined text-base">save</span>
                                Guardar Frecuencia
                            </button>
                        @endif
                    </div>

                    <!-- PRESETS RÁPIDOS -->
                    <div class="space-y-1.5 pt-1">
                        <span class="text-[11px] font-mono text-obsidian-muted">Preajustes rápidos:</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach([1, 2, 5, 10, 15, 20, 30, 60] as $preset)
                                <button type="button" onclick="document.getElementById('web_interval_input').value = {{ $preset }}"
                                    class="px-3 py-1 rounded-lg text-xs font-mono border transition cursor-pointer {{ ($cronConfig['web_check_interval'] ?? 10) == $preset ? 'bg-cyan-950 text-obsidian-cyan border-cyan-500/60 font-bold' : 'bg-[#040d1a] text-gray-300 border-obsidian-border hover:border-obsidian-cyan/50 hover:text-white' }}">
                                    {{ $preset }} min @if($preset === 10)<span class="text-[10px] text-cyan-400 font-bold">(Recomendado)</span>@endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                </form>
            </div>

            <!-- DETALLES DE AUDITORÍA (1 COL) -->
            <div class="bg-[#040d1a]/80 border border-obsidian-border/70 rounded-xl p-4 space-y-2 text-xs font-mono">
                <div class="text-[11px] uppercase font-bold text-obsidian-muted flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs">info</span>
                    Auditoría de Monitoreo Web
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Frecuencia actual:</span>
                    <span class="text-white font-bold">{{ $cronConfig['web_check_interval'] ?? 10 }} min</span>
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Último cambio:</span>
                    <span class="text-white">{{ $cronConfig['web_check_interval_updated_at'] ?: 'N/A' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Modificado por:</span>
                    <span class="text-cyan-300 truncate max-w-[150px]" title="{{ $cronConfig['web_check_interval_updated_by'] }}">{{ $cronConfig['web_check_interval_updated_by'] ?: 'N/A' }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-obsidian-muted">Motor de escaneo:</span>
                    <span class="text-emerald-400">Python Web Sync</span>
                </div>
            </div>
        </div>
    </div>

    <!-- CONFIGURACIÓN DE CLÚSTER & ROL DEL SERVIDOR (MASTER / SLAVE) (Exclusivo Administrador) -->
    <div class="glass-card rounded-xl p-6 space-y-5 border {{ $isClusterSlave ? 'border-amber-500/40' : 'border-cyan-500/30' }}">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-obsidian-border/70">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ $isClusterSlave ? 'bg-amber-950/80 text-amber-400 border border-amber-500/40' : 'bg-cyan-950/80 text-obsidian-cyan border border-cyan-500/40' }}">
                    <span class="material-symbols-outlined text-xl">{{ $isClusterSlave ? 'cloud_sync' : 'hub' }}</span>
                </div>
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        Arquitectura de Clúster (Master / Slave)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold {{ $isClusterSlave ? 'bg-amber-950/80 text-amber-400 border border-amber-500/40' : 'bg-cyan-950/80 text-cyan-300 border border-cyan-500/40' }}">
                            {{ $isClusterSlave ? 'NODO RÉPLICA (SLAVE)' : 'NODO PRINCIPAL (MASTER)' }}
                        </span>
                    </h2>
                    <p class="text-xs text-obsidian-muted mt-0.5">
                        Define si este servidor actúa como nodo escaneador oficial (Master) o como nodo réplica de consulta (Slave) para prevenir colisiones en pfSense.
                    </p>
                </div>
            </div>
        </div>

        <!-- FORMULARIO DE ROL Y CLÚSTER -->
        <form action="{{ route('admin.cluster.update') }}" method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            @csrf
            <!-- SELECTOR DE ROL Y PARÁMETROS (2 COLS) -->
            <div class="lg:col-span-2 space-y-4">
                <div class="space-y-2">
                    <label class="text-xs font-mono font-bold text-gray-300 uppercase block">Seleccione el Rol de este Servidor:</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- OPCIÓN MASTER -->
                        <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ $isClusterMaster ? 'bg-cyan-950/40 border-cyan-500/60 text-white ring-1 ring-cyan-500/30' : 'bg-[#040d1a] border-obsidian-border text-obsidian-muted hover:border-obsidian-cyan/40' }}">
                            <input type="radio" name="node_role" value="master" {{ $isClusterMaster ? 'checked' : '' }} onchange="toggleClusterRoleFields('master')" class="mt-1 text-obsidian-cyan focus:ring-0">
                            <div>
                                <div class="font-bold text-xs font-mono flex items-center gap-1.5 text-white">
                                    <span class="material-symbols-outlined text-sm text-cyan-400">dns</span>
                                    Servidor Maestro (Master)
                                </div>
                                <p class="text-[11px] text-obsidian-muted mt-1 leading-relaxed">
                                    Realiza los escaneos de red cada 10 min, gestiona las configuraciones oficiales y sirve la API de telemetría hacia los esclavos.
                                </p>
                            </div>
                        </label>

                        <!-- OPCIÓN SLAVE -->
                        <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ $isClusterSlave ? 'bg-amber-950/40 border-amber-500/60 text-white ring-1 ring-amber-500/30' : 'bg-[#040d1a] border-obsidian-border text-obsidian-muted hover:border-amber-500/40' }}">
                            <input type="radio" name="node_role" value="slave" {{ $isClusterSlave ? 'checked' : '' }} onchange="toggleClusterRoleFields('slave')" class="mt-1 text-amber-400 focus:ring-0">
                            <div>
                                <div class="font-bold text-xs font-mono flex items-center gap-1.5 text-white">
                                    <span class="material-symbols-outlined text-sm text-amber-400">cloud_sync</span>
                                    Servidor Esclavo (Slave)
                                </div>
                                <p class="text-[11px] text-obsidian-muted mt-1 leading-relaxed">
                                    No realiza escaneos automáticos de fondo a la red. Sincroniza su telemetría web desde el Master sin colisionar con pfSense.
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- CAMPO URL DEL MASTER (VISIBLE CUANDO ES SLAVE) -->
                <div id="field_master_url" class="space-y-1.5 {{ $isClusterSlave ? '' : 'hidden' }}">
                    <label class="text-xs font-mono font-bold text-gray-300 uppercase flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-amber-400">link</span>
                        URL / Dirección del Servidor Master
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="url" name="master_api_url" id="input_master_url" value="{{ $clusterConfig['master_api_url'] }}" placeholder="http://10.20.23.252"
                            class="flex-1 bg-[#040d1a] border border-obsidian-border text-white text-xs font-mono rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-obsidian-cyan">
                        <button type="button" onclick="testMasterConnection()" id="btn-test-connection" class="px-3 py-2.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-cyan-950/60 font-bold text-xs font-mono flex items-center gap-1.5 transition cursor-pointer whitespace-nowrap">
                            <span class="material-symbols-outlined text-sm">wifi_tethering</span>
                            Probar Conexión
                        </button>
                    </div>
                    <div id="test-connection-feedback" class="hidden text-xs font-mono pt-1"></div>
                </div>

                <!-- CAMPO TOKEN DE CLÚSTER -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-mono font-bold text-gray-300 uppercase flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-obsidian-cyan">vpn_key</span>
                            Token Secreto de Clúster (X-Cluster-Token)
                        </label>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="copyClusterToken()" class="text-[11px] font-mono text-obsidian-cyan hover:underline flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-xs">content_copy</span>
                                Copiar
                            </button>
                            <button type="button" onclick="generateNewClusterToken()" class="text-[11px] font-mono text-gray-400 hover:text-white flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-xs">refresh</span>
                                Regenerar
                            </button>
                        </div>
                    </div>
                    <div class="relative">
                        <input type="text" name="cluster_token" id="input_cluster_token" value="{{ $clusterConfig['cluster_token'] }}" required
                            class="w-full bg-[#040d1a] border border-obsidian-border text-cyan-300 text-xs font-mono rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-obsidian-cyan pr-24">
                        <span id="copy-token-badge" class="absolute right-2.5 top-2.5 text-[10px] font-mono text-emerald-400 bg-emerald-950/80 px-2 py-0.5 rounded border border-emerald-500/40 hidden">¡Copiado!</span>
                    </div>
                    <p class="text-[11px] text-obsidian-muted">
                        Este token autentica las peticiones entre los servidores. Debe ser idéntico en el Master y en los Slaves.
                    </p>
                </div>

                <div class="pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-obsidian-cyan text-black hover:bg-cyan-300 font-bold text-xs font-mono uppercase flex items-center gap-2 transition cursor-pointer shadow-lg shadow-cyan-950/40">
                        <span class="material-symbols-outlined text-base">save</span>
                        Guardar Configuración de Clúster
                    </button>
                </div>
            </div>

            <!-- DETALLES DE ESTADO DEL CLÚSTER (1 COL) -->
            <div class="bg-[#040d1a]/80 border border-obsidian-border/70 rounded-xl p-4 space-y-2 text-xs font-mono">
                <div class="text-[11px] uppercase font-bold text-obsidian-muted flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs">info</span>
                    Estado del Clúster
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Rol actual:</span>
                    <span class="text-white font-bold uppercase {{ $isClusterSlave ? 'text-amber-400' : 'text-cyan-300' }}">{{ $clusterConfig['node_role'] }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Última sinc:</span>
                    <span class="text-white">{{ $clusterConfig['cluster_last_sync_at'] ?: 'N/A' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Estado del enlace:</span>
                    <span class="{{ $clusterConfig['cluster_last_sync_status'] === 'ok' || $clusterConfig['cluster_last_sync_status'] === 'master_active' ? 'text-emerald-400' : 'text-amber-400' }}">
                        {{ $clusterConfig['cluster_last_sync_status'] === 'master_active' ? 'Master Activo' : ($clusterConfig['cluster_last_sync_status'] === 'ok' ? 'Sincronizado' : 'Pendiente') }}
                    </span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-obsidian-muted">API Oculta:</span>
                    <span class="text-emerald-400">/api/cluster/*</span>
                </div>
            </div>
        </form>
    </div>
    @endif
</div>

<script>
function toggleClusterRoleFields(role) {
    const masterField = document.getElementById('field_master_url');
    if (!masterField) return;
    if (role === 'slave') {
        masterField.classList.remove('hidden');
    } else {
        masterField.classList.add('hidden');
    }
}

function copyClusterToken() {
    const input = document.getElementById('input_cluster_token');
    if (!input) return;
    input.select();
    navigator.clipboard.writeText(input.value);
    const badge = document.getElementById('copy-token-badge');
    if (badge) {
        badge.classList.remove('hidden');
        setTimeout(() => badge.classList.add('hidden'), 2500);
    }
}

function generateNewClusterToken() {
    if (!confirm('¿Desea generar un nuevo token de clúster? Si tiene nodos esclavos conectados, deberá actualizar el token en ellos.')) return;
    fetch('{{ route('admin.cluster.generate-token') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.token) {
            document.getElementById('input_cluster_token').value = data.token;
        }
    });
}

function testMasterConnection() {
    const url = document.getElementById('input_master_url').value;
    const token = document.getElementById('input_cluster_token').value;
    const btn = document.getElementById('btn-test-connection');
    const feedback = document.getElementById('test-connection-feedback');

    if (!btn || !feedback) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">refresh</span> Probando...';
    feedback.classList.remove('hidden', 'text-emerald-400', 'text-red-400');
    feedback.classList.add('text-obsidian-muted');
    feedback.textContent = 'Contactando servidor Master vía API...';

    fetch('{{ route('admin.cluster.test') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ master_api_url: url, cluster_token: token })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm">wifi_tethering</span> Probar Conexión';
        feedback.classList.remove('text-obsidian-muted');
        if (data.success) {
            feedback.classList.add('text-emerald-400');
            feedback.textContent = '✅ ' + data.message;
        } else {
            feedback.classList.add('text-red-400');
            feedback.textContent = '❌ ' + (data.message || 'Error al conectar con el Master.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm">wifi_tethering</span> Probar Conexión';
        feedback.classList.remove('text-obsidian-muted');
        feedback.classList.add('text-red-400');
        feedback.textContent = '❌ Error de red al probar conexión: ' + err.message;
    });
}
</script>
@endsection
