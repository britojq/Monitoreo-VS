@extends('layouts.admin')

@section('page_title', 'Configuración Avanzada')

@section('admin_content')
<div class="space-y-6">
    <!-- ========================================================================= -->
    <!-- BARRA SUPERIOR DE CABECERA Y NAVEGACIÓN                                   -->
    <!-- ========================================================================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 backdrop-blur-md shadow-md">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-obsidian-cyan/15 border border-obsidian-cyan/40 flex items-center justify-center text-obsidian-cyan shadow-md shrink-0">
                <span class="material-symbols-outlined text-2xl">tune</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-sm sm:text-base font-bold text-white tracking-wide uppercase font-mono">
                        Configuración Avanzada del Sistema
                    </h1>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-obsidian-cyan/20 text-obsidian-cyan border border-obsidian-cyan/40">
                        ADMIN
                    </span>
                </div>
                <p class="text-[11px] font-mono text-obsidian-muted mt-0.5">
                    Parámetros del motor de sincronización, automatización de Telegram, arquitectura de clúster y autenticación LDAP
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 self-start sm:self-auto flex-wrap">
            <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded-xl bg-obsidian-panel hover:bg-cyan-950/40 border border-obsidian-border hover:border-cyan-500/40 text-gray-300 hover:text-white font-mono font-bold text-xs flex items-center gap-1.5 transition">
                <span class="material-symbols-outlined text-sm text-cyan-400">dashboard</span>
                <span>Volver al Dashboard</span>
            </a>
            <span class="px-3 py-1 rounded-full text-xs font-mono font-bold bg-obsidian-cyan/20 text-obsidian-cyan border border-obsidian-cyan/40">
                ROL: ADMINISTRADOR
            </span>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- RESUMEN DE MÉTRICAS & ESTADÍSTICAS GLOBALES                               -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
        <!-- USUARIOS -->
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

        <!-- SERVICIOS -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono uppercase text-obsidian-muted">Servicios Monitoreados</span>
                <span class="material-symbols-outlined text-blue-400">dns</span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-bold font-mono text-white">{{ $stats['services_active'] ?? 0 }}</span>
                <span class="text-xs font-mono text-obsidian-muted">/ {{ $stats['services_count'] ?? 0 }} total</span>
            </div>
            <div class="mt-3">
                <a href="{{ route('admin.services.index') }}" class="text-xs font-mono text-blue-400 hover:underline flex items-center gap-1">
                    Configurar servicios &rarr;
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
                <span class="text-3xl font-bold font-mono text-white">{{ $stats['sites_active'] ?? 0 }}</span>
                <span class="text-xs font-mono text-obsidian-muted">Sedes ({{ $stats['devices_count'] ?? 0 }} equipos)</span>
            </div>
            <div class="mt-3">
                <a href="{{ route('admin.sites.index') }}" class="text-xs font-mono text-purple-400 hover:underline flex items-center gap-1">
                    Configurar sedes &rarr;
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
                    Gestionar dispositivos &rarr;
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
                <span class="text-3xl font-bold font-mono text-white">{{ $stats['proxies_active'] ?? 0 }}</span>
                <span class="text-xs font-mono text-obsidian-muted">/ {{ $stats['proxies_count'] ?? 0 }} total</span>
            </div>
            <div class="mt-3">
                <a href="{{ route('admin.proxies.index') }}" class="text-xs font-mono text-emerald-400 hover:underline flex items-center gap-1">
                    Configurar proxies &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SINCRONIZACIÓN Y ARCHIVOS (.conf) & SNAPSHOT                               -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- SINCRONIZACIÓN Y ARCHIVOS -->
        <div class="glass-card rounded-xl p-6 lg:col-span-2 space-y-4">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan">sync</span>
                Sincronización Bidireccional de Archivos (.conf)
            </h2>
            <p class="text-xs text-obsidian-muted leading-relaxed">
                Los cambios que realices en este panel (Servicios, Sedes, Dispositivos y Proxies) se guardan en la base de datos MariaDB y se exportan automáticamente a los archivos oficiales <code class="text-obsidian-cyan font-mono">/scripts/telegram-admin-bot/config/monitoreo.conf</code> y <code class="text-obsidian-cyan font-mono">bot.conf</code> para que el motor asíncrono de Python y el bot de Telegram los utilicen según la frecuencia de chequeo establecida (actualmente cada {{ $cronConfig['web_check_interval'] ?? 10 }} minutos).
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

        <!-- ÚLTIMO REPORTE DE TELEMETRÍA (SNAPSHOT) -->
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

    @if(isset($cronConfig))
    <!-- ========================================================================= -->
    <!-- CONTROL DE ENVÍOS PROGRAMADOS A TELEGRAM (CRON)                           -->
    <!-- ========================================================================= -->
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
                        Control del envío desatendido de reportes de infraestructura a los canales oficiales y grupos autorizados.
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
                        Horarios Diarios de Envío (24 horas)
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

    <!-- ========================================================================= -->
    <!-- FRECUENCIA DE CHEQUEO WEB & TELEMETRÍA                                    -->
    <!-- ========================================================================= -->
    <div class="glass-card rounded-xl p-6 space-y-5 border border-cyan-500/30">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-obsidian-border/70">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-cyan-950/80 text-obsidian-cyan border border-cyan-500/40">
                    <span class="material-symbols-outlined text-xl">update</span>
                </div>
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        Frecuencia de Chequeo Web & Telemetría
                        @if($isClusterSlave)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-gray-900 text-gray-400 border border-gray-700">
                                <span class="material-symbols-outlined text-xs">lock</span>
                                DESACTIVADO EN ESCLAVO (SOLO MASTER)
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-cyan-950/80 text-cyan-300 border border-cyan-500/40">
                                CADA {{ $cronConfig['web_check_interval'] ?? 10 }} MINUTOS
                            </span>
                        @endif
                    </h2>
                    <p class="text-xs text-obsidian-muted mt-0.5">
                        Intervalo en que el servicio de monitoreo en segundo plano ejecuta la verificación de enlaces y actualiza este portal web.
                    </p>
                </div>
            </div>

            <!-- BOTÓN ESCANEAR / SINCRONIZAR AHORA -->
            <button onclick="triggerImmediateScan()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-bold text-xs font-mono uppercase flex items-center justify-center gap-2 transition cursor-pointer">
                <span class="material-symbols-outlined text-base">{{ $isClusterSlave ? 'cloud_sync' : 'play_arrow' }}</span>
                {{ $isClusterSlave ? 'Sincronizar con Master Ahora' : 'Escanear Ahora' }}
            </button>
        </div>

        <!-- CUADRÍCULA: SELECTOR DE INTERVALO & AUDITORÍA -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
            <!-- SELECTOR DE FRECUENCIA Y ACCESOS RÁPIDOS (2 COLS) -->
            <div class="lg:col-span-2 space-y-4">
                @if($isClusterSlave)
                    <div class="p-3.5 rounded-xl bg-obsidian-panel/90 border border-amber-500/30 text-xs font-mono text-amber-300 flex items-start gap-2.5">
                        <span class="material-symbols-outlined text-base text-amber-400 shrink-0 mt-0.5">lock</span>
                        <div class="leading-relaxed">
                            <strong class="text-white block mb-0.5">Operación Desactivada en Servidor Esclavo:</strong>
                            Este servidor no ejecuta escaneos de red ni chequeos a pfSense para prevenir colisiones de credenciales. La frecuencia oficial de escaneo de infraestructura se configura exclusivamente en el <strong class="text-cyan-300">Servidor Master</strong> (<code>{{ $clusterConfig['master_api_url'] }}</code>).
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-between">
                    <span class="text-xs font-mono font-bold uppercase flex items-center gap-1.5 {{ $isClusterSlave ? 'text-gray-500' : 'text-gray-300' }}">
                        <span class="material-symbols-outlined text-sm {{ $isClusterSlave ? 'text-gray-500' : 'text-obsidian-cyan' }}">timer</span>
                        Ajustar Frecuencia de Ejecución
                    </span>
                    <span class="text-[11px] font-mono px-2.5 py-0.5 rounded-md border {{ $isClusterSlave ? 'bg-gray-900/60 text-gray-500 border-gray-800' : 'text-cyan-300 bg-cyan-950/50 border-cyan-500/30' }}">
                        Actual en Master: <strong>{{ $cronConfig['web_check_interval'] ?? 10 }} min</strong>
                    </span>
                </div>

                <!-- FORMULARIO DE ACTUALIZACIÓN -->
                <form action="{{ route('admin.cron.interval.update') }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative flex-1 min-w-[200px] max-w-xs">
                            <input type="number" name="interval_minutes" id="web_interval_input" min="1" max="1440" required
                                value="{{ $cronConfig['web_check_interval'] ?? 10 }}"
                                {{ $isClusterSlave ? 'disabled' : '' }}
                                class="w-full bg-[#040d1a] border border-obsidian-border text-white text-sm font-mono rounded-lg px-4 py-2.5 focus:outline-none focus:border-obsidian-cyan {{ $isClusterSlave ? 'opacity-40 cursor-not-allowed text-gray-500' : '' }}">
                            <span class="absolute right-3 top-2.5 text-xs font-mono text-obsidian-muted pointer-events-none">minutos</span>
                        </div>
                        @if($isClusterSlave)
                            <button type="button" disabled class="px-5 py-2.5 rounded-lg bg-gray-900 text-gray-500 border border-gray-800 font-bold text-xs font-mono uppercase flex items-center gap-2 cursor-not-allowed" title="Configurado exclusivamente en el Servidor Master">
                                <span class="material-symbols-outlined text-base">lock</span>
                                Configurable Solo en Master
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
                        <span class="text-[11px] font-mono {{ $isClusterSlave ? 'text-gray-600' : 'text-obsidian-muted' }}">Preajustes rápidos:</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach([1, 2, 5, 10, 15, 20, 30, 60] as $preset)
                                <button type="button" 
                                    {{ $isClusterSlave ? 'disabled' : '' }}
                                    onclick="document.getElementById('web_interval_input').value = {{ $preset }}"
                                    class="px-3 py-1 rounded-lg text-xs font-mono border transition {{ $isClusterSlave ? 'opacity-30 cursor-not-allowed bg-gray-900 text-gray-600 border-gray-800' : (($cronConfig['web_check_interval'] ?? 10) == $preset ? 'bg-cyan-950 text-obsidian-cyan border-cyan-500/60 font-bold cursor-pointer' : 'bg-[#040d1a] text-gray-300 border-obsidian-border hover:border-obsidian-cyan/50 hover:text-white cursor-pointer') }}">
                                    {{ $preset }} min @if($preset === 10)<span class="text-[10px] {{ $isClusterSlave ? 'text-gray-600' : 'text-cyan-400' }} font-bold">(Recomendado)</span>@endif
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
    @endif

    <!-- ========================================================================= -->
    <!-- CONFIGURACIÓN DE CLÚSTER & ROL DEL SERVIDOR (MASTER / SLAVE)               -->
    <!-- ========================================================================= -->
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

                <!-- CAMPO INTERVALO DE SINCRONIZACIÓN DEL SLAVE (VISIBLE CUANDO ES SLAVE) -->
                <div id="field_slave_sync_interval" class="space-y-2 {{ $isClusterSlave ? '' : 'hidden' }}">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-mono font-bold text-gray-300 uppercase flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-amber-400">schedule</span>
                            Intervalo de Sincronización con el Master
                        </label>
                        <span class="text-[11px] font-mono text-amber-300 bg-amber-950/60 px-2.5 py-0.5 rounded border border-amber-500/30">
                            Actual: <strong>{{ $clusterConfig['slave_sync_interval_minutes'] ?? 2 }} min</strong>
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative flex-1 min-w-[180px] max-w-xs">
                            <input type="number" name="slave_sync_interval_minutes" id="input_slave_sync_interval" min="1" max="1440"
                                value="{{ $clusterConfig['slave_sync_interval_minutes'] ?? 2 }}"
                                class="w-full bg-[#040d1a] border border-obsidian-border text-white text-sm font-mono rounded-lg px-4 py-2.5 focus:outline-none focus:border-obsidian-cyan">
                            <span class="absolute right-3 top-2.5 text-xs font-mono text-obsidian-muted pointer-events-none">minutos</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pt-0.5">
                        <span class="text-[11px] font-mono text-obsidian-muted">Preajustes:</span>
                        @foreach([1, 2, 5, 10, 15] as $preset_sync)
                            <button type="button" onclick="document.getElementById('input_slave_sync_interval').value = {{ $preset_sync }}"
                                class="px-2.5 py-1 rounded-lg text-xs font-mono border transition cursor-pointer {{ ($clusterConfig['slave_sync_interval_minutes'] ?? 2) == $preset_sync ? 'bg-amber-950 text-amber-300 border-amber-500/60 font-bold' : 'bg-[#040d1a] text-gray-300 border-obsidian-border hover:border-amber-500/50 hover:text-white' }}">
                                {{ $preset_sync }} min @if($preset_sync === 2)<span class="text-[10px] text-amber-400 font-bold">(Recomendado)</span>@endif
                            </button>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-obsidian-muted leading-relaxed">
                        Frecuencia en minutos con la que este nodo esclavo descarga automáticamente el snapshot y la telemetría del Master vía API sin escanear la red física ni tocar pfSense.
                    </p>
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
                    <div class="flex items-center gap-2">
                        <input type="text" name="cluster_token" id="input_cluster_token" value="{{ $clusterConfig['cluster_token'] }}" required
                            onclick="this.select();" onfocus="this.select();"
                            class="flex-1 bg-[#040d1a] border border-obsidian-border text-cyan-300 text-xs font-mono rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-obsidian-cyan select-all"
                            title="Haga clic para seleccionar todo el token">
                        <button type="button" onclick="copyClusterToken()" class="px-3.5 py-2.5 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-400 hover:bg-cyan-500 hover:text-black font-bold text-xs font-mono flex items-center gap-1.5 transition cursor-pointer shadow-sm shrink-0" title="Copiar Token al portapapeles">
                            <span class="material-symbols-outlined text-sm">content_copy</span>
                            <span>Copiar</span>
                        </button>
                    </div>
                    <div id="copy-token-badge" class="hidden text-[11px] font-mono text-emerald-400 flex items-center gap-1 mt-1">
                        <span class="material-symbols-outlined text-xs">check_circle</span>
                        <span>¡Token copiado al portapapeles con éxito!</span>
                    </div>
                    <p class="text-[11px] text-obsidian-muted">
                        Este token autentica las peticiones entre los servidores. Debe ser idéntico en el Master y en los Slaves.
                    </p>
                </div>

                <div class="pt-2">
                    <button type="submit" id="btn-save-cluster" class="px-5 py-2.5 rounded-lg bg-obsidian-cyan text-black hover:bg-cyan-300 font-bold text-xs font-mono uppercase flex items-center gap-2 transition cursor-pointer shadow-lg shadow-cyan-950/40">
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

    <!-- ========================================================================= -->
    <!-- CONFIGURACIÓN DE DIRECTORIO ACTIVO & AUTENTICACIÓN LDAP                   -->
    <!-- ========================================================================= -->
    <div class="glass-card rounded-xl p-6 space-y-5 border {{ ($ldapConfig['enabled'] ?? true) ? 'border-cyan-500/30' : 'border-rose-500/30' }}">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-obsidian-border/70">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ ($ldapConfig['enabled'] ?? true) ? 'bg-cyan-950/80 text-obsidian-cyan border border-cyan-500/40' : 'bg-rose-950/80 text-rose-400 border border-rose-500/40' }}">
                    <span class="material-symbols-outlined text-xl">badge</span>
                </div>
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        Directorio Activo Corporativo & Autenticación LDAP
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold {{ ($ldapConfig['enabled'] ?? true) ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/40' : 'bg-rose-950/80 text-rose-300 border border-rose-500/40' }}">
                            {{ ($ldapConfig['enabled'] ?? true) ? '● AUTENTICACIÓN LDAP HABILITADA' : '○ AUTENTICACIÓN LDAP DESHABILITADA' }}
                        </span>
                    </h2>
                    <p class="text-xs text-obsidian-muted mt-0.5">
                        Permite a los usuarios corporativos iniciar sesión con sus credenciales de red (UID/Cédula y contraseña) y auto-aprobarse según su departamento.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.users.index') }}" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:text-white hover:bg-cyan-950/40 text-xs font-mono flex items-center gap-1.5 transition">
                    <span class="material-symbols-outlined text-sm">manage_accounts</span>
                    Gestión de Usuarios
                </a>
            </div>
        </div>

        <!-- FORMULARIO DE CONFIGURACIÓN LDAP -->
        <form action="{{ route('admin.ldap.update') }}" method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            @csrf
            <!-- PARÁMETROS LDAP (2 COLS) -->
            <div class="lg:col-span-2 space-y-4">
                <!-- INTERRUPTOR DE ACTIVACIÓN/DESACTIVACIÓN -->
                <div class="space-y-2">
                    <label class="text-xs font-mono font-bold text-gray-300 uppercase block">Estado del Servicio LDAP:</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- HABILITADO -->
                        <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ ($ldapConfig['enabled'] ?? true) ? 'bg-emerald-950/30 border-emerald-500/50 text-white ring-1 ring-emerald-500/30' : 'bg-[#040d1a] border-obsidian-border text-obsidian-muted hover:border-emerald-500/30' }}">
                            <input type="radio" name="ldap_enabled" value="1" {{ ($ldapConfig['enabled'] ?? true) ? 'checked' : '' }} class="mt-1 text-emerald-500 focus:ring-0">
                            <div>
                                <span class="text-xs font-bold text-emerald-300 block">Habilitado (Recomendado)</span>
                                <span class="text-[11px] text-obsidian-muted leading-tight block mt-0.5">
                                    Permite inicio de sesión con cuentas de red corporativas y aprovisionamiento automático (JIT).
                                </span>
                            </div>
                        </label>

                        <!-- DESHABILITADO -->
                        <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ !($ldapConfig['enabled'] ?? true) ? 'bg-rose-950/30 border-rose-500/50 text-white ring-1 ring-rose-500/30' : 'bg-[#040d1a] border-obsidian-border text-obsidian-muted hover:border-rose-500/30' }}">
                            <input type="radio" name="ldap_enabled" value="0" {{ !($ldapConfig['enabled'] ?? true) ? 'checked' : '' }} class="mt-1 text-rose-500 focus:ring-0">
                            <div>
                                <span class="text-xs font-bold text-rose-300 block">Deshabilitado</span>
                                <span class="text-[11px] text-obsidian-muted leading-tight block mt-0.5">
                                    Bloquea validación externa. El acceso al portal solo estará disponible para cuentas locales existentes.
                                </span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- HOST Y PUERTO -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="text-xs font-mono font-bold text-obsidian-cyan uppercase block flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">dns</span>
                            Servidor Host LDAP / IP:
                        </label>
                        <input type="text" name="ldap_host" id="input_ldap_host" value="{{ $ldapConfig['host'] ?? '10.20.0.22' }}" required
                            placeholder="10.20.0.22"
                            class="w-full bg-[#040d1a] border border-obsidian-border rounded-lg px-3 py-2 text-xs font-mono text-white focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan focus:outline-none">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-mono font-bold text-obsidian-cyan uppercase block flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">tag</span>
                            Puerto:
                        </label>
                        <input type="number" name="ldap_port" id="input_ldap_port" value="{{ $ldapConfig['port'] ?? 389 }}" min="1" max="65535" required
                            placeholder="389"
                            class="w-full bg-[#040d1a] border border-obsidian-border rounded-lg px-3 py-2 text-xs font-mono text-white focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan focus:outline-none">
                    </div>
                </div>

                <!-- BASE DN -->
                <div class="space-y-1.5">
                    <label class="text-xs font-mono font-bold text-obsidian-cyan uppercase block flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs">folder_special</span>
                        Base DN (Árbol de Búsqueda):
                    </label>
                    <input type="text" name="ldap_base_dn" id="input_ldap_base_dn" value="{{ $ldapConfig['base_dn'] ?? 'dc=corpoelec,dc=gob,dc=ve' }}" required
                        placeholder="dc=corpoelec,dc=gob,dc=ve"
                        class="w-full bg-[#040d1a] border border-obsidian-border rounded-lg px-3 py-2 text-xs font-mono text-white focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan focus:outline-none">
                    <p class="text-[11px] font-mono text-obsidian-muted">
                        Raíz LDAP sobre la que se ejecutan los filtros de búsqueda por UID, Cédula y Correo corporativo.
                    </p>
                </div>

                <!-- ÁREAS PERMITIDAS & ROL POR DEFECTO -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="text-xs font-mono font-bold text-obsidian-cyan uppercase block flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">workspaces</span>
                            Áreas Organizacionales Autorizadas:
                        </label>
                        <input type="text" name="ldap_allowed_areas" id="input_ldap_allowed_areas" value="{{ $ldapConfig['allowed_areas'] ?? 'ATIT, GPO TRAB INFRA TECNOL CARABOBO, INFRAESTRUCTURA, TELECOMUNICACIONES' }}"
                            placeholder="ATIT, INFRAESTRUCTURA..."
                            class="w-full bg-[#040d1a] border border-obsidian-border rounded-lg px-3 py-2 text-xs font-mono text-white focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan focus:outline-none">
                        <p class="text-[10px] font-mono text-obsidian-muted">
                            Separadas por comas. Empleados con estas áreas en su ficha LDAP ingresan directamente con auto-registro.
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-mono font-bold text-obsidian-cyan uppercase block flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">shield_person</span>
                            Rol Asignado (JIT):
                        </label>
                        <select name="ldap_default_role" id="select_ldap_role"
                            class="w-full bg-[#040d1a] border border-obsidian-border rounded-lg px-3 py-2 text-xs font-mono text-white focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan focus:outline-none">
                            <option value="operator" {{ ($ldapConfig['default_role'] ?? 'operator') === 'operator' ? 'selected' : '' }}>Operador (Recomendado)</option>
                            <option value="admin" {{ ($ldapConfig['default_role'] ?? 'operator') === 'admin' ? 'selected' : '' }}>Administrador</option>
                        </select>
                        <p class="text-[10px] font-mono text-obsidian-muted">
                            Rol al auto-registrarse.
                        </p>
                    </div>
                </div>

                <!-- FEEDBACK DE PRUEBA DE CONEXIÓN -->
                <div id="ldap_test_feedback" class="hidden text-xs font-mono p-3 rounded-lg border"></div>

                <!-- BOTONES DE ACCIÓN -->
                <div class="pt-2 flex flex-wrap items-center gap-3">
                    <button type="submit" id="btn-save-ldap" class="px-5 py-2.5 rounded-lg bg-obsidian-cyan text-black hover:bg-cyan-300 font-bold text-xs font-mono uppercase flex items-center gap-2 transition cursor-pointer shadow-lg shadow-cyan-950/40">
                        <span class="material-symbols-outlined text-base">save</span>
                        Guardar Configuración LDAP
                    </button>
                    <button type="button" onclick="testLdapConnection()" id="btn-test-ldap" class="px-4 py-2.5 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:text-white hover:bg-cyan-950/60 font-mono text-xs uppercase flex items-center gap-2 transition cursor-pointer">
                        <span class="material-symbols-outlined text-base" id="icon-test-ldap">wifi_tethering</span>
                        Probar Conexión LDAP
                    </button>
                </div>
            </div>

            <!-- AUDITORÍA Y ESTADO LDAP (1 COL) -->
            <div class="bg-[#040d1a]/80 border border-obsidian-border/70 rounded-xl p-4 space-y-2 text-xs font-mono">
                <div class="text-[11px] uppercase font-bold text-obsidian-muted flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs">info</span>
                    Auditoría del Servicio LDAP
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Estado del servicio:</span>
                    <span class="font-bold {{ ($ldapConfig['enabled'] ?? true) ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ ($ldapConfig['enabled'] ?? true) ? 'HABILITADO' : 'DESHABILITADO' }}
                    </span>
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Servidor actual:</span>
                    <span class="text-white">{{ $ldapConfig['host'] ?? '10.20.0.22' }}:{{ $ldapConfig['port'] ?? 389 }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Rol inicial:</span>
                    <span class="text-cyan-300 uppercase font-bold">{{ $ldapConfig['default_role'] ?? 'operator' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Último cambio:</span>
                    <span class="text-white">{{ $ldapConfig['updated_at'] ?: 'Por defecto' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-obsidian-border/40">
                    <span class="text-obsidian-muted">Modificado por:</span>
                    <span class="text-cyan-300 truncate max-w-[140px]" title="{{ $ldapConfig['updated_by'] }}">{{ $ldapConfig['updated_by'] ?: 'Sistema' }}</span>
                </div>
                <div class="pt-2">
                    <a href="{{ route('admin.users.index') }}" class="text-[11px] text-cyan-400 hover:text-cyan-300 flex items-center gap-1">
                        <span>Gestionar usuarios autorizados</span>
                        <span class="material-symbols-outlined text-xs">arrow_forward</span>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleClusterRoleFields(role) {
    const masterField = document.getElementById('field_master_url');
    const slaveIntervalField = document.getElementById('field_slave_sync_interval');
    if (role === 'slave') {
        if (masterField) masterField.classList.remove('hidden');
        if (slaveIntervalField) slaveIntervalField.classList.remove('hidden');
    } else {
        if (masterField) masterField.classList.add('hidden');
        if (slaveIntervalField) slaveIntervalField.classList.add('hidden');
    }
}

function copyClusterToken() {
    const input = document.getElementById('input_cluster_token');
    if (!input) return;
    const textToCopy = input.value;
    
    input.focus();
    input.select();
    input.setSelectionRange(0, 99999);

    let copied = false;

    try {
        copied = document.execCommand('copy');
    } catch (e) {
        console.warn('execCommand falló:', e);
    }

    if (!copied && navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(textToCopy).then(() => {
            showCopySuccessBadge();
        }).catch(() => {
            copyViaTextarea(textToCopy);
        });
        return;
    }

    if (copied) {
        showCopySuccessBadge();
    } else {
        copyViaTextarea(textToCopy);
    }
}

function copyViaTextarea(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    ta.style.top = '-9999px';
    ta.setAttribute('readonly', '');
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    ta.setSelectionRange(0, 99999);
    try {
        const ok = document.execCommand('copy');
        if (ok) {
            showCopySuccessBadge();
        } else {
            window.prompt('Copie el token de clúster con Ctrl+C:', text);
        }
    } catch (err) {
        window.prompt('Copie el token de clúster con Ctrl+C:', text);
    }
    document.body.removeChild(ta);
}

function showCopySuccessBadge() {
    const badge = document.getElementById('copy-token-badge');
    if (badge) {
        badge.classList.remove('hidden');
        setTimeout(() => badge.classList.add('hidden'), 3500);
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

function testLdapConnection() {
    const host = document.getElementById('input_ldap_host').value.trim();
    const port = document.getElementById('input_ldap_port').value.trim();
    const baseDn = document.getElementById('input_ldap_base_dn').value.trim();
    const feedback = document.getElementById('ldap_test_feedback');
    const btn = document.getElementById('btn-test-ldap');
    const icon = document.getElementById('icon-test-ldap');

    if (!host) {
        alert('Por favor ingrese el host o dirección IP del servidor LDAP.');
        return;
    }

    feedback.classList.remove('hidden', 'bg-emerald-950/40', 'border-emerald-500/50', 'text-emerald-300', 'bg-rose-950/40', 'border-rose-500/50', 'text-rose-300');
    feedback.classList.add('bg-cyan-950/40', 'border-cyan-500/50', 'text-cyan-300');
    feedback.innerHTML = '<div class="flex items-center gap-2"><span class="material-symbols-outlined text-sm animate-spin">sync</span> Probando conexión y Base DN con el servidor LDAP corporativo...</div>';
    
    btn.disabled = true;
    if (icon) icon.classList.add('animate-spin');

    fetch('{{ route('admin.ldap.testConnection') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ host: host, port: port, base_dn: baseDn })
    })
    .then(r => r.json())
    .then(data => {
        feedback.classList.remove('bg-cyan-950/40', 'border-cyan-500/50', 'text-cyan-300');
        if (data.success) {
            feedback.classList.add('bg-emerald-950/40', 'border-emerald-500/50', 'text-emerald-300');
            feedback.innerHTML = `<div class="flex items-center gap-2"><span class="material-symbols-outlined text-base text-emerald-400">check_circle</span> <span><strong>Éxito:</strong> ${data.message}</span></div>`;
        } else {
            feedback.classList.add('bg-rose-950/40', 'border-rose-500/50', 'text-rose-300');
            feedback.innerHTML = `<div class="flex items-center gap-2"><span class="material-symbols-outlined text-base text-rose-400">error</span> <span><strong>Error:</strong> ${data.message}</span></div>`;
        }
    })
    .catch(err => {
        feedback.classList.remove('bg-cyan-950/40', 'border-cyan-500/50', 'text-cyan-300');
        feedback.classList.add('bg-rose-950/40', 'border-rose-500/50', 'text-rose-300');
        feedback.innerHTML = `<div class="flex items-center gap-2"><span class="material-symbols-outlined text-base text-rose-400">error</span> <span>Error de red al intentar verificar el servidor LDAP.</span></div>`;
    })
    .finally(() => {
        btn.disabled = false;
        if (icon) icon.classList.remove('animate-spin');
    });
}
</script>
@endsection
