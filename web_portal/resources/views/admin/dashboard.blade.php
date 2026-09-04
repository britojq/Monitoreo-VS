@extends('layouts.admin')

@section('page_title', 'Dashboard General')

@section('admin_content')
<div class="space-y-6">
    <!-- STATS OVERVIEW -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- USUARIOS -->
        <div class="glass-card rounded-xl p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-mono uppercase text-obsidian-muted">Usuarios Registrados</span>
                <span class="material-symbols-outlined text-obsidian-cyan">group</span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-bold font-mono text-white">{{ $stats['users_count'] }}</span>
                <span class="text-xs font-mono text-obsidian-muted">activos</span>
            </div>
            <div class="mt-3">
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.users.index') }}" class="text-xs font-mono text-obsidian-cyan hover:underline flex items-center gap-1">
                        Gestionar usuarios &rarr;
                    </a>
                @else
                    <span class="text-xs font-mono text-obsidian-muted">
                        Solo consulta
                    </span>
                @endif
            </div>
        </div>

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
    </div>
</div>
@endsection
