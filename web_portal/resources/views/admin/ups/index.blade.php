@extends('layouts.admin')

@section('page_title', 'Energía & UPS')

@section('admin_content')
@php
    $canManageUps = auth()->check() && (auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('ups.manage')));
@endphp
<div class="space-y-4">
    <!-- ========================================================================= -->
    <!-- BARRA SUPERIOR: ESTADO EN VIVO Y RESUMEN GENERAL                          -->
    <!-- ========================================================================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-[10.5px] font-mono font-semibold group shadow-xs">
                    <span class="material-symbols-outlined text-xs group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-[10.5px] font-mono text-obsidian-muted">Energía &amp; Telemetría Eléctrica</span>
            </div>
            <div class="flex items-center gap-3">
                <div id="ups-header-icon" class="w-10 h-10 rounded-xl {{ $device->is_on_battery ? 'bg-red-950/80 border-red-500/50 text-red-400' : 'bg-cyan-950/80 border-cyan-500/50 text-cyan-400' }} border flex items-center justify-center shrink-0 transition-colors">
                    <span class="material-symbols-outlined text-2xl" id="ups-status-icon">
                        {{ $device->is_on_battery ? 'power_off' : 'battery_charging_full' }}
                    </span>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl font-black text-white tracking-wide uppercase font-mono">
                            {{ $device->name }}
                        </h1>
                        @if($device->is_generic)
                        <span class="w-6 h-6 rounded-md bg-amber-950/90 text-amber-300 border border-amber-500/60 inline-flex items-center justify-center cursor-help animate-pulse" title="Pendiente de Identificación Corporativa">
                            <span class="material-symbols-outlined text-xs">warning</span>
                        </span>
                        @endif
                        <span id="ups-status-badge" class="w-6 h-6 rounded-md inline-flex items-center justify-center cursor-help {{ $device->is_on_battery ? 'bg-red-950/90 text-red-400 border border-red-500/60 animate-pulse' : ($device->is_online ? 'bg-emerald-950/90 text-emerald-400 border border-emerald-500/60' : 'bg-slate-900 text-slate-400 border border-slate-700') }}" title="{{ $device->is_on_battery ? 'Modo Batería (Corte Eléctrico Activo)' : ($device->is_online ? 'Red Comercial Normal (Alimentación Estable)' : 'UPS Desconectado') }}">
                            <span id="ups-pulse-dot" class="w-2 h-2 rounded-full {{ $device->is_on_battery ? 'bg-red-400' : ($device->is_online ? 'bg-emerald-400' : 'bg-slate-400') }}"></span>
                            <span id="ups-status-text" class="sr-only">{{ $device->is_on_battery ? 'MODO BATERÍA (CORTE ELÉCTRICO)' : ($device->is_online ? 'RED COMERCIAL NORMAL' : 'DESCONECTADO') }}</span>
                        </span>
                    </div>
                    <p class="text-[11px] font-mono text-obsidian-muted flex items-center gap-2 mt-0.5 flex-wrap">
                        <span>Modelo: <strong class="text-cyan-300">{{ $device->model }}</strong></span>
                        <span>•</span>
                        <span>Puerto: <strong class="text-white">{{ $device->serial_port }}</strong> @ {{ $device->baud_rate }} bps</span>
                        <span>•</span>
                        <span id="ups-last-seen" class="text-slate-400">Actualizado: {{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Sin datos' }}</span>
                        @if($device->inventoryAsset)
                        <span>•</span>
                        <a href="{{ route('admin.devices.index', ['search' => $device->inventoryAsset->ip_address]) }}" class="text-cyan-400 hover:text-cyan-300 hover:underline inline-flex items-center gap-1" title="Ver ficha técnica del servidor anfitrión en ITAM">
                            <span class="material-symbols-outlined text-xs">dns</span>
                            <span>Anfitrión: <strong class="text-white">{{ $device->inventoryAsset->display_identity ?? $device->inventoryAsset->name }}</strong> ({{ $device->inventoryAsset->ip_address }})</span>
                        </a>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto flex-wrap">
            @if($canManageUps)
            <button type="button" onclick="document.getElementById('panel-ups-settings')?.scrollIntoView({behavior: 'smooth'})" class="w-9 h-9 rounded-xl bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black transition flex items-center justify-center shadow-xs cursor-pointer" title="Configurar Identidad y Parámetros del UPS">
                <span class="material-symbols-outlined text-base">settings</span>
            </button>
            @endif
            <span class="w-9 h-9 rounded-xl bg-[#06111f] border border-cyan-500/40 text-cyan-300 inline-flex items-center justify-center shadow-xs cursor-help" title="Sondeo Serial Activo (Frecuencia: Cada 5 Segundos)">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
            </span>
        </div>
    </div>

    @if($device->is_generic)
    <!-- ALERTA DE EQUIPO GENÉRICO / CAMBIO DE HARDWARE (REGLA DE ORO #8) -->
    <div class="p-3.5 rounded-xl bg-amber-950/50 border border-amber-500/60 backdrop-blur-md flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-lg">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-amber-400 text-2xl shrink-0 animate-bounce">device_unknown</span>
            <div class="text-xs font-mono">
                <span class="font-bold text-amber-300 uppercase tracking-wide">⚠️ Nuevo UPS Detectado en {{ $device->serial_port }}</span>
                <p class="text-amber-200/80 text-[11px] mt-0.5">Se asignaron parámetros genéricos provisionales. Puede personalizar el nombre y modelo manualmente o usar el botón de auto-extracción para sincronizar con ITAM.</p>
            </div>
        </div>
        <button type="button" onclick="document.getElementById('panel-ups-settings')?.scrollIntoView({behavior: 'smooth'})" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-black font-mono font-bold text-xs transition flex items-center gap-1.5 shrink-0 cursor-pointer shadow-md">
            <span class="material-symbols-outlined text-sm">edit</span>
            <span>Asignar Identidad</span>
        </button>
    </div>
    @endif

    <!-- PESTAÑAS EQUIPOS & HARDWARE (PANEL DE NAVEGACIÓN COMPACTO) -->
    <div class="flex flex-wrap items-center gap-1.5 border-b border-obsidian-border/80 pb-2.5">
        <a href="{{ route('admin.devices.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.devices.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Equipos y Activos de Red Monitoreados">
            <span class="material-symbols-outlined text-sm">devices</span>
            <span>Equipos &amp; Activos</span>
        </a>
        <a href="{{ route('admin.inventory.peripherals.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.inventory.peripherals.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Inventario de Periféricos y Stock de Repuestos">
            <span class="material-symbols-outlined text-sm">keyboard</span>
            <span>Periféricos &amp; Stock</span>
        </a>
        <a href="{{ route('admin.inventory.deploy') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.inventory.deploy*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Despliegue Remoto Agente GLPI">
            <span class="material-symbols-outlined text-sm">send_to_mobile</span>
            <span>Despliegue GLPI</span>
        </a>
        <a href="{{ route('admin.wol.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.wol.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Encendido Remoto Wake-on-LAN">
            <span class="material-symbols-outlined text-sm">power</span>
            <span>Wake-on-LAN (WoL)</span>
        </a>
        <a href="{{ route('admin.ups.index') }}" class="px-2.5 py-1 rounded-lg font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer {{ request()->routeIs('admin.ups.*') ? 'bg-cyan-500 text-black shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}" title="Monitoreo Serial de Energía & UPS">
            <span class="material-symbols-outlined text-sm">battery_charging_full</span>
            <span>Monitoreo UPS</span>
        </a>
    </div>

    @if($isClusterSlave)
    <!-- ========================================================================= -->
    <!-- ALERTA DE MODO ESCLAVO (RÉPLICA DE SOLO LECTURA)                          -->
    <!-- ========================================================================= -->
    <div class="p-3.5 rounded-xl bg-amber-950/40 border border-amber-500/50 backdrop-blur-md flex items-start gap-3">
        <span class="material-symbols-outlined text-amber-400 text-xl shrink-0 mt-0.5">cloud_sync</span>
        <div class="text-xs font-mono leading-relaxed">
            <span class="font-bold text-amber-300 uppercase tracking-wide">MODO ESCLAVO (Sincronización en Réplica):</span>
            <span class="text-amber-200/90 ml-1">
                La telemetría de este UPS se recolecta en el servidor Master (<code class="text-white">{{ $clusterConfig['master_api_url'] }}</code>) y se replica en tiempo real a este nodo. La configuración de alertas se administra centralizadamente en el nodo Master.
            </span>
        </div>
    </div>
    @endif

    <!-- ========================================================================= -->
    <!-- TARJETAS HUD DE TELEMETRÍA EN TIEMPO REAL                                 -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- CARD 1: ESTADO DE LÍNEA Y OPERACIÓN -->
        <div class="p-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/80 backdrop-blur-md shadow-md flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono uppercase font-bold text-obsidian-muted tracking-wider">Modo Operativo</span>
                <span class="material-symbols-outlined text-lg text-cyan-400">electrical_services</span>
            </div>
            <div class="my-2">
                <div id="hud-mode-title" class="text-lg font-bold font-mono {{ $device->is_on_battery ? 'text-red-400' : 'text-emerald-400' }}">
                    {{ $device->is_on_battery ? 'EN BATERÍA' : 'LINEA CONECTADA' }}
                </div>
                <div class="text-[11px] font-mono text-obsidian-muted mt-0.5" id="hud-mode-sub">
                    @if($device->is_on_battery)
                        <span class="text-red-300 font-bold">Corte activo: {{ $outageDurationStr ?? 'Iniciando' }}</span>
                    @else
                        <span>Alimentación de red estable (Online)</span>
                    @endif
                </div>
            </div>
            <div class="pt-2 border-t border-obsidian-border/50 flex items-center justify-between text-[11px] font-mono text-obsidian-muted">
                <span>Bypass: <strong id="hud-bypass" class="text-white">{{ $device->is_bypass ? 'Activo' : 'Inactivo' }}</strong></span>
                <span>Frecuencia: <strong id="hud-freq" class="text-cyan-300">{{ number_format($device->frequency ?? 60.0, 1) }} Hz</strong></span>
            </div>
        </div>

        <!-- CARD 2: BANCO DE BATERÍAS (192Vdc) -->
        <div class="p-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/80 backdrop-blur-md shadow-md flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono uppercase font-bold text-obsidian-muted tracking-wider">Banco de Baterías</span>
                <span class="material-symbols-outlined text-lg text-emerald-400">battery_horiz_075</span>
            </div>
            <div class="my-2">
                <div class="flex items-baseline gap-2">
                    <span id="hud-battery-pct" class="text-2xl font-black font-mono {{ ($device->battery_percent ?? 100) < 25 ? 'text-red-400' : (($device->battery_percent ?? 100) < 60 ? 'text-amber-400' : 'text-emerald-400') }}">
                        {{ $device->battery_percent ?? 100 }}%
                    </span>
                    <span id="hud-battery-volts" class="text-xs font-mono text-obsidian-muted">
                        ({{ number_format($device->battery_voltage ?? 2.25, 2) }} V/celda)
                    </span>
                </div>
                <!-- Barra de batería -->
                <div class="w-full bg-[#06111f] h-2 rounded-full overflow-hidden mt-1.5 border border-obsidian-border/60">
                    <div id="hud-battery-bar" class="h-full rounded-full transition-all duration-500 {{ ($device->battery_percent ?? 100) < 25 ? 'bg-red-500' : (($device->battery_percent ?? 100) < 60 ? 'bg-amber-400' : 'bg-emerald-500') }}" style="width: {{ $device->battery_percent ?? 100 }}%"></div>
                </div>
            </div>
            <div class="pt-2 border-t border-obsidian-border/50 flex items-center justify-between text-[11px] font-mono text-obsidian-muted">
                <span>Bus: <strong class="text-white">192 Vdc</strong> (16 bats)</span>
                <span>Autonomía: <strong id="hud-runtime" class="text-cyan-300">~{{ $estimatedRuntimeMinutes }} min</strong></span>
            </div>
        </div>

        <!-- CARD 3: VOLTAJES DE ENTRADA & SALIDA (TOPOLOGÍA 220V IN ➔ 110V OUT) -->
        <div class="p-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/80 backdrop-blur-md shadow-md flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono uppercase font-bold text-obsidian-muted tracking-wider">Voltajes AC (Topología 220V ➔ 110V)</span>
                <span class="material-symbols-outlined text-lg text-yellow-400">bolt</span>
            </div>
            <div class="my-2 flex items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[9px] uppercase font-mono text-obsidian-muted block">Entrada (Red)</span>
                        <span class="text-[8px] px-1 py-0.2 rounded bg-amber-950/80 text-amber-300 border border-amber-500/40">220V</span>
                    </div>
                    <span id="hud-in-v" class="text-lg font-bold font-mono text-amber-300">
                        {{ number_format($device->input_voltage ?? 0.0, 1) }} <span class="text-xs text-obsidian-muted">VAC</span>
                    </span>
                </div>
                <div class="text-center text-obsidian-muted">
                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[9px] uppercase font-mono text-obsidian-muted block">Salida Equipos</span>
                        <span class="text-[8px] px-1 py-0.2 rounded bg-cyan-950/80 text-cyan-300 border border-cyan-500/40">110V</span>
                    </div>
                    <span id="hud-out-v" class="text-lg font-bold font-mono text-cyan-300">
                        {{ number_format($outputVoltageEquipos, 1) }} <span class="text-xs text-obsidian-muted">VAC</span>
                    </span>
                </div>
            </div>
            <div class="pt-2 border-t border-obsidian-border/50 flex items-center justify-between text-[11px] font-mono text-obsidian-muted">
                <span>Conversión: <strong class="text-emerald-400">220V In ➔ 110V Out</strong></span>
                <span>Inversor L-L: <strong class="text-cyan-300" id="hud-inverter-v">{{ number_format($device->output_voltage ?? 208.0, 1) }} VAC</strong></span>
            </div>
        </div>

        <!-- CARD 4: CONSUMO DE CARGA & TEMPERATURA -->
        <div class="p-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/80 backdrop-blur-md shadow-md flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono uppercase font-bold text-obsidian-muted tracking-wider">Carga & Temperatura</span>
                <span class="material-symbols-outlined text-lg text-purple-400">speed</span>
            </div>
            <div class="my-2">
                <div class="flex items-baseline justify-between">
                    <span id="hud-load-pct" class="text-2xl font-black font-mono {{ ($device->load_percent ?? 0) > 85 ? 'text-red-400' : (($device->load_percent ?? 0) > 60 ? 'text-amber-400' : 'text-purple-300') }}">
                        {{ $device->load_percent ?? 0 }}%
                    </span>
                    <span id="hud-load-watts" class="text-xs font-mono text-obsidian-muted font-bold">
                        ~{{ $loadWatts }} Watts
                    </span>
                </div>
                <!-- Barra de carga -->
                <div class="w-full bg-[#06111f] h-2 rounded-full overflow-hidden mt-1.5 border border-obsidian-border/60">
                    <div id="hud-load-bar" class="h-full rounded-full transition-all duration-500 {{ ($device->load_percent ?? 0) > 85 ? 'bg-red-500' : (($device->load_percent ?? 0) > 60 ? 'bg-amber-400' : 'bg-purple-500') }}" style="width: {{ $device->load_percent ?? 0 }}%"></div>
                </div>
            </div>
            <div class="pt-2 border-t border-obsidian-border/50 flex items-center justify-between text-[11px] font-mono text-obsidian-muted">
                <span>Temperatura: <strong id="hud-temp" class="text-amber-300">{{ number_format($device->temperature_c ?? 40.0, 1) }} °C</strong></span>
                <span>Capacidad: <strong class="text-white">6000 W</strong></span>
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- TABLA HISTÓRICA: MICRO-FLUCTUACIONES & FALLAS DEL SERVICIO ELÉCTRICO      -->
    <!-- ========================================================================= -->
    <div class="p-4 sm:p-5 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/80 backdrop-blur-md shadow-lg mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-obsidian-border/60 mb-4">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-400 text-xl">electric_bolt</span>
                <div>
                    <h3 class="text-xs font-bold text-white uppercase font-mono tracking-wider flex items-center gap-2">
                        <span>Registro de Micro-Fluctuaciones &amp; Fallas de Servicio Eléctrico</span>
                        <span class="w-5 h-5 rounded bg-amber-950/80 border border-amber-500/40 text-amber-300 inline-flex items-center justify-center cursor-help" title="{{ $powerEvents->total() }} {{ Str::plural('evento', $powerEvents->total()) }} registrados">
                            <span class="material-symbols-outlined text-xs">history</span>
                        </span>
                    </h3>
                    <p class="text-[10px] font-mono text-obsidian-muted">
                        Auditoría cronológica de variaciones transitorias absorbidas por batería (≤45s) y fallas comerciales confirmadas (>45s).
                    </p>
                </div>
            </div>

            <div class="text-[11px] font-mono text-obsidian-muted flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 text-[10px]">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    Filtro Anti-Spam (45s) Activo
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono">
                <thead>
                    <tr class="border-b border-obsidian-border/80 text-obsidian-muted uppercase text-[10px] tracking-wider bg-[#06111f]/60">
                        <th class="py-2.5 px-3">Fecha</th>
                        <th class="py-2.5 px-3">Hora de Inicio</th>
                        <th class="py-2.5 px-3">Tipo de Falla / Evento</th>
                        <th class="py-2.5 px-3">Duración en Batería</th>
                        <th class="py-2.5 px-3">Voltaje Comercial</th>
                        <th class="py-2.5 px-3">Estado</th>
                        <th class="py-2.5 px-3">Detalle Técnico</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/40">
                    @forelse($powerEvents as $event)
                    <tr class="hover:bg-cyan-500/5 transition">
                        <!-- FECHA -->
                        <td class="py-2.5 px-3 text-white font-medium whitespace-nowrap">
                            {{ $event->started_at ? $event->started_at->timezone('America/Caracas')->format('d/m/Y') : '—' }}
                        </td>

                        <!-- HORA -->
                        <td class="py-2.5 px-3 text-obsidian-muted whitespace-nowrap">
                            {{ $event->started_at ? $event->started_at->timezone('America/Caracas')->format('h:i:s A') : '—' }}
                        </td>

                        <!-- TIPO DE FALLA -->
                        <td class="py-2.5 px-3 whitespace-nowrap">
                            @if($event->event_type === 'outage')
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-red-500/15 text-red-300 border border-red-500/40 inline-flex items-center gap-1 shadow-sm">
                                    <span class="material-symbols-outlined text-xs">bolt</span>
                                    Falla de Servicio (>45s)
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/40 inline-flex items-center gap-1 shadow-sm">
                                    <span class="material-symbols-outlined text-xs">offline_bolt</span>
                                    Micro-Fluctuación (≤45s)
                                </span>
                            @endif
                        </td>

                        <!-- DURACIÓN EN BATERÍA -->
                        <td class="py-2.5 px-3 text-cyan-300 font-bold whitespace-nowrap">
                            {{ $event->duration_label }}
                        </td>

                        <!-- VOLTAJE COMERCIAL -->
                        <td class="py-2.5 px-3 text-slate-300 whitespace-nowrap">
                            @if($event->input_voltage !== null)
                                <span class="{{ $event->input_voltage < 100 ? 'text-red-400 font-bold' : 'text-slate-300' }}">
                                    {{ number_format($event->input_voltage, 1) }} VAC
                                </span>
                            @else
                                <span class="text-obsidian-muted">0.0 VAC</span>
                            @endif
                        </td>

                        <!-- ESTADO -->
                        <td class="py-2.5 px-3 whitespace-nowrap">
                            @if($event->status === 'resolved')
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 inline-flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    Resuelto
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-red-500/20 text-red-300 border border-red-500/40 inline-flex items-center gap-1 animate-pulse">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                    En Curso
                                </span>
                            @endif
                        </td>

                        <!-- DETALLE TÉCNICO -->
                        <td class="py-2.5 px-3 text-obsidian-muted text-[11px] truncate max-w-[280px]" title="{{ $event->description }}">
                            {{ $event->description ?? 'Amortiguado por inversor' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-obsidian-muted">
                            <span class="material-symbols-outlined text-3xl mb-1 text-obsidian-border block">check_circle</span>
                            <p class="text-xs font-semibold text-slate-300">Sin eventos de falla ni micro-fluctuaciones</p>
                            <p class="text-[10px] text-obsidian-muted mt-0.5">El fluido eléctrico comercial se ha mantenido estable y sin transitorios.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN (5 ÍTEMS) -->
        @if($powerEvents->hasPages())
        <div class="pt-3 mt-3 border-t border-obsidian-border/50 flex items-center justify-between">
            {{ $powerEvents->appends(request()->query())->links('vendor.pagination.obsidian') }}
        </div>
        @endif
    </div>

    <!-- ========================================================================= -->
    <!-- CURVAS DE TELEMETRÍA EN TIEMPO REAL (CHART.JS)                            -->
    <!-- ========================================================================= -->
    <div class="p-4 sm:p-5 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/80 backdrop-blur-md shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-obsidian-border/60 mb-4">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-xl">show_chart</span>
                <div>
                    <h3 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                        Curvas de Telemetría Eléctrica & Comportamiento (Últimas 24 Horas)
                    </h3>
                    <p class="text-[10px] font-mono text-obsidian-muted">
                        Visualización gráfica de voltajes AC, niveles de carga, autonomía de batería y temperatura térmica.
                    </p>
                </div>
            </div>

            <!-- BOTONES DE FILTRO DEL GRÁFICO -->
            <div class="flex items-center gap-1.5 p-1 rounded-lg bg-[#06111f] border border-obsidian-border/80 text-[11px] font-mono">
                <button type="button" onclick="setChartDataset('voltages')" id="btn-chart-voltages" class="px-2.5 py-1 rounded font-bold transition bg-obsidian-cyan text-black">
                    Voltajes (VAC)
                </button>
                <button type="button" onclick="setChartDataset('load_battery')" id="btn-chart-load" class="px-2.5 py-1 rounded font-bold transition text-obsidian-muted hover:text-white">
                    Carga & Batería (%)
                </button>
                <button type="button" onclick="setChartDataset('temperature')" id="btn-chart-temp" class="px-2.5 py-1 rounded font-bold transition text-obsidian-muted hover:text-white">
                    Temperatura (°C)
                </button>
            </div>
        </div>

        <div class="relative h-64 sm:h-72 w-full">
            <canvas id="upsTelemetryChart"></canvas>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN INFERIOR: CONFIGURACIÓN DE ALERTAS & ESPECIFICACIONES TÉCNICAS    -->
    <!-- ========================================================================= -->
    @php
        $canManageUps = auth()->check() && (auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('ups.manage')));
    @endphp

    <div class="grid grid-cols-1 {{ $canManageUps ? 'lg:grid-cols-2' : '' }} gap-6">

        @if($canManageUps)
        <!-- PANEL 1: IDENTIDAD, COMUNICACIÓN & CONFIGURACIÓN DEL UPS -->
        <div id="panel-ups-settings" class="p-4 sm:p-5 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/80 backdrop-blur-md shadow-lg flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-obsidian-border/60 mb-4 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-cyan-400 text-xl">tune</span>
                        <div>
                            <h3 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                                Identidad, Comunicación &amp; Alertas del UPS
                            </h3>
                            <p class="text-[10px] font-mono text-obsidian-muted">
                                Personalización de modelo, calibración de placa, puerto serial y sincronización con Inventario ITAM.
                            </p>
                        </div>
                    </div>
                    @if(!$isClusterSlave)
                    <button type="button" id="btn-interrogate-hw" class="px-2.5 py-1 rounded-lg bg-cyan-950/70 border border-cyan-500/50 text-cyan-300 hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-sm">
                        <span class="material-symbols-outlined text-xs">auto_fix_high</span>
                        <span id="btn-interrogate-text">Auto-Extraer Identidad</span>
                    </button>
                    @endif
                </div>

                <form action="{{ route('admin.ups.settings') }}" method="POST" id="form-ups-settings" class="space-y-4">
                    @csrf

                    <!-- SECCIÓN 1: IDENTIDAD DEL ACTIVO & VINCULACIÓN ITAM -->
                    <div class="space-y-3 p-3 rounded-lg bg-[#06111f] border border-obsidian-border/70">
                        <div class="flex items-center justify-between">
                            <label class="text-[11px] font-mono uppercase font-bold text-cyan-300 block tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-xs">badge</span>
                                <span>Identidad del Equipo &amp; Censo ITAM</span>
                            </label>
                            <span class="text-[9.5px] font-mono text-obsidian-muted">Censo Maestro de Activos</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="input_ups_name" class="text-[10px] font-mono text-obsidian-muted block mb-1">Nombre Etiqueta / Rol:</label>
                                <input type="text" name="name" id="input_ups_name" value="{{ $device->name }}" {{ $isClusterSlave ? 'disabled' : '' }} required class="w-full bg-[#030914] border border-obsidian-border/70 rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-cyan-500" placeholder="Ej: UPS ZTG LV6KL - Sede Valle Seco">
                            </div>
                            <div>
                                <label for="input_ups_model" class="text-[10px] font-mono text-obsidian-muted block mb-1">Marca &amp; Modelo:</label>
                                <input type="text" name="model" id="input_ups_model" value="{{ $device->model }}" {{ $isClusterSlave ? 'disabled' : '' }} required class="w-full bg-[#030914] border border-obsidian-border/70 rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-cyan-500" placeholder="Ej: ZTG LV6KL 6kVA">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="input_ups_serial" class="text-[10px] font-mono text-obsidian-muted block mb-1">Número de Serie:</label>
                                <input type="text" name="serial_number" id="input_ups_serial" value="{{ $device->serial_number }}" {{ $isClusterSlave ? 'disabled' : '' }} class="w-full bg-[#030914] border border-obsidian-border/70 rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-cyan-500" placeholder="Ej: LV6KL-VS-01">
                            </div>
                            <div>
                                <label for="select_inventory_asset" class="text-[10px] font-mono text-obsidian-muted block mb-1">Servidor Anfitrión Asociado (ITAM):</label>
                                <select name="inventory_asset_id" id="select_inventory_asset" {{ $isClusterSlave ? 'disabled' : '' }} class="w-full bg-[#030914] border border-obsidian-border/70 rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                                    <option value="">-- Sin servidor anfitrión asignado --</option>
                                    @foreach($inventoryAssets as $ast)
                                    <option value="{{ $ast->id }}" {{ (int)($device->inventory_asset_id ?? 0) === $ast->id ? 'selected' : '' }}>
                                        {{ $ast->name }} ({{ $ast->ip_address ?: 'Sin IP' }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: CALIBRACIÓN ELÉCTRICA DE PLACA -->
                    <div class="space-y-3 p-3 rounded-lg bg-[#06111f] border border-obsidian-border/70">
                        <label class="text-[11px] font-mono uppercase font-bold text-amber-300 block tracking-wider flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-xs">battery_charging_full</span>
                            <span>Especificaciones de Placa &amp; Banco de Baterías</span>
                        </label>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="input_rating_voltage" class="text-[10px] font-mono text-obsidian-muted block mb-1">Tensión Nominal Salida (VAC):</label>
                                <input type="number" step="0.1" name="rating_voltage" id="input_rating_voltage" value="{{ $device->rating_voltage ?? 208.0 }}" {{ $isClusterSlave ? 'disabled' : '' }} class="w-full bg-[#030914] border border-obsidian-border/70 rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                                <span class="text-[9px] font-mono text-slate-500">Típico: 208.0V o 220.0V (Bifásica) / 120.0V</span>
                            </div>
                            <div>
                                <label for="input_rating_bat_voltage" class="text-[10px] font-mono text-obsidian-muted block mb-1">Tensión Banco Baterías (Vdc):</label>
                                <input type="number" step="0.1" name="rating_battery_voltage" id="input_rating_bat_voltage" value="{{ $device->rating_battery_voltage ?? 192.0 }}" {{ $isClusterSlave ? 'disabled' : '' }} class="w-full bg-[#030914] border border-obsidian-border/70 rounded-lg px-3 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                                <span class="text-[9px] font-mono text-slate-500">192Vdc = 16 bat (6kVA) | 96Vdc = 8 bat (3kVA)</span>
                            </div>
                        </div>
                    </div>

                    <!-- TOGGLE DE ACTIVACIÓN -->
                    <div class="flex items-center justify-between p-3 rounded-lg bg-[#06111f] border border-obsidian-border/70">
                        <div>
                            <span class="text-xs font-bold font-mono text-white block">Supervisión de Alertas de Energía</span>
                            <span class="text-[10px] font-mono text-obsidian-muted block mt-0.5">
                                Emite mensajes automáticos en Telegram ante fluctuaciones y cortes de energía.
                            </span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="telegram_alert_enabled" value="0">
                            <input type="checkbox" name="telegram_alert_enabled" value="1" {{ $device->telegram_alert_enabled ? 'checked' : '' }} {{ $isClusterSlave ? 'disabled' : '' }} class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-500"></div>
                        </label>
                    </div>

                    <!-- SELECTOR DE DESTINO -->
                    <div class="space-y-2">
                        <label class="text-[11px] font-mono uppercase font-bold text-obsidian-muted block tracking-wider">
                            Destino de las Alertas:
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-start gap-2.5 p-3 rounded-lg bg-[#06111f] border border-obsidian-border/70 cursor-pointer hover:border-cyan-500/50 transition">
                                <input type="radio" name="telegram_alert_target" value="owner" {{ $device->telegram_alert_target === 'owner' ? 'checked' : '' }} {{ $isClusterSlave ? 'disabled' : '' }} class="mt-1 text-cyan-500 focus:ring-0">
                                <div>
                                    <span class="text-xs font-bold font-mono text-white block">👤 Administrador Privado</span>
                                    <span class="text-[10px] font-mono text-obsidian-muted block mt-0.5">
                                        Owner ID: <code class="text-cyan-300">38914901</code> (Chat confidencial)
                                    </span>
                                </div>
                            </label>

                            <label class="flex items-start gap-2.5 p-3 rounded-lg bg-[#06111f] border border-obsidian-border/70 cursor-pointer hover:border-purple-500/50 transition">
                                <input type="radio" name="telegram_alert_target" value="group" {{ $device->telegram_alert_target === 'group' ? 'checked' : '' }} {{ $isClusterSlave ? 'disabled' : '' }} class="mt-1 text-purple-500 focus:ring-0">
                                <div>
                                    <span class="text-xs font-bold font-mono text-white block">👥 Grupo Corporativo + Owner</span>
                                    <span class="text-[10px] font-mono text-obsidian-muted block mt-0.5">
                                        Despacho dual a <code class="text-purple-300">-1001383163558</code> y Administrador
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- COMUNICACIÓN SERIAL (PUERTO Y VELOCIDAD) -->
                    <div class="space-y-3 pt-3 border-t border-obsidian-border/60">
                        <div class="flex items-center justify-between">
                            <label class="text-[11px] font-mono uppercase font-bold text-obsidian-muted block tracking-wider">
                                Comunicación Serial (RS-232 / USB):
                            </label>
                            <button type="button" id="btn-refresh-ports" class="text-[10px] font-mono text-cyan-400 hover:text-cyan-300 flex items-center gap-1 cursor-pointer transition">
                                <span class="material-symbols-outlined text-xs">sync</span>
                                Detectar Puertos
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="serial_port_select" class="text-[10px] font-mono text-obsidian-muted block mb-1">Puerto Serial:</label>
                                <select name="serial_port" id="serial_port_select" {{ $isClusterSlave ? 'disabled' : '' }} class="w-full bg-[#06111f] border border-obsidian-border/70 rounded-lg px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                                    <option value="/dev/ttyS0" {{ $device->serial_port === '/dev/ttyS0' ? 'selected' : '' }}>/dev/ttyS0 (COM 1 - Físico)</option>
                                    <option value="/dev/ttyS1" {{ $device->serial_port === '/dev/ttyS1' ? 'selected' : '' }}>/dev/ttyS1 (COM 2 - Físico)</option>
                                    <option value="/dev/ttyUSB0" {{ $device->serial_port === '/dev/ttyUSB0' ? 'selected' : '' }}>/dev/ttyUSB0 (Adaptador USB 1)</option>
                                    <option value="/dev/ttyUSB1" {{ $device->serial_port === '/dev/ttyUSB1' ? 'selected' : '' }}>/dev/ttyUSB1 (Adaptador USB 2)</option>
                                    @if(!in_array($device->serial_port, ['/dev/ttyS0', '/dev/ttyS1', '/dev/ttyUSB0', '/dev/ttyUSB1']))
                                    <option value="{{ $device->serial_port }}" selected>{{ $device->serial_port }} (Personalizado)</option>
                                    @endif
                                </select>
                            </div>

                            <div>
                                <label for="baud_rate_select" class="text-[10px] font-mono text-obsidian-muted block mb-1">Velocidad (Baudios):</label>
                                <select name="baud_rate" id="baud_rate_select" {{ $isClusterSlave ? 'disabled' : '' }} class="w-full bg-[#06111f] border border-obsidian-border/70 rounded-lg px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                                    <option value="2400" {{ (int)$device->baud_rate === 2400 ? 'selected' : '' }}>2400 bps (Megatec Q1)</option>
                                    <option value="9600" {{ (int)$device->baud_rate === 9600 ? 'selected' : '' }}>9600 bps</option>
                                    <option value="1200" {{ (int)$device->baud_rate === 1200 ? 'selected' : '' }}>1200 bps</option>
                                    <option value="4800" {{ (int)$device->baud_rate === 4800 ? 'selected' : '' }}>4800 bps</option>
                                    <option value="19200" {{ (int)$device->baud_rate === 19200 ? 'selected' : '' }}>19200 bps</option>
                                </select>
                            </div>
                        </div>

                        <!-- BOTONES DE DIAGNÓSTICO EN VIVO -->
                        @if(!$isClusterSlave)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                            <button type="button" id="btn-test-serial" class="py-2 px-3 rounded-lg bg-cyan-950/60 border border-cyan-500/40 text-cyan-300 font-mono text-xs hover:bg-cyan-900/50 hover:border-cyan-400 transition flex items-center justify-center gap-1.5 cursor-pointer">
                                <span class="material-symbols-outlined text-sm">cable</span>
                                <span id="btn-test-serial-text">Probar Comunicación</span>
                            </button>
                            <button type="button" id="btn-scan-ports" class="py-2 px-3 rounded-lg bg-purple-950/60 border border-purple-500/40 text-purple-300 font-mono text-xs hover:bg-purple-900/50 hover:border-purple-400 transition flex items-center justify-center gap-1.5 cursor-pointer">
                                <span class="material-symbols-outlined text-sm">search</span>
                                <span id="btn-scan-ports-text">Escanear y Autodetectar</span>
                            </button>
                        </div>
                        <div id="serial-test-feedback" class="hidden text-xs font-mono p-2.5 rounded-lg border transition-all duration-300 leading-relaxed"></div>
                        @endif
                    </div>

                    <!-- AVISO DE REGLA DE SEGURIDAD -->
                    <div class="p-2.5 rounded-lg bg-cyan-950/30 border border-cyan-500/30 text-[10px] font-mono text-cyan-200/80 leading-relaxed">
                        <span class="font-bold text-cyan-300">🛡️ Control de Seguridad:</span> En servidores de desarrollo o modo esclavo, las alertas dirigidas a grupo se omiten y despachan únicamente al chat privado del Administrador para evitar avisos accidentales.
                    </div>

                    @if(!$isClusterSlave)
                    <div class="pt-2 space-y-3">
                        <button type="submit" id="btn-save-ups-settings" class="w-full py-2.5 px-4 rounded-lg bg-obsidian-cyan text-black font-bold font-mono text-xs uppercase tracking-wider hover:bg-cyan-300 transition flex items-center justify-center gap-2 cursor-pointer shadow-md shadow-cyan-950/40">
                            <span class="material-symbols-outlined text-base">save</span>
                            <span id="btn-save-text">Guardar Configuración de UPS</span>
                        </button>
                        <div id="ups-settings-feedback" class="hidden text-xs font-mono transition-all duration-300"></div>
                    </div>
                    @endif
                </form>
            </div>
        </div>
        @endif

        <!-- PANEL 2: FICHA TÉCNICA DEL UPS & PARÁMETROS NOMINALES -->
        <div class="p-4 sm:p-5 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/80 backdrop-blur-md shadow-lg flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2 pb-3 border-b border-obsidian-border/60 mb-4">
                    <span class="material-symbols-outlined text-cyan-400 text-xl">precision_manufacturing</span>
                    <div>
                        <h3 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                            Especificaciones Industriales de Fábrica
                        </h3>
                        <p class="text-[10px] font-mono text-obsidian-muted">
                            Parámetros nominales de hardware, firmware y topología de conversión física.
                        </p>
                    </div>
                </div>

                <div class="space-y-2.5 text-xs font-mono">
                    <div class="flex items-center justify-between p-2 rounded bg-[#06111f]/70 border border-obsidian-border/50">
                        <span class="text-obsidian-muted">Topología Eléctrica:</span>
                        <span class="text-white font-bold">Online Doble Conversión (VFI)</span>
                    </div>

                    <div class="flex items-center justify-between p-2 rounded bg-[#06111f]/70 border border-obsidian-border/50">
                        <span class="text-obsidian-muted">Potencia Aparente / Activa:</span>
                        <span class="text-cyan-300 font-bold">6000 VA / 6000 W (FP = 1.0)</span>
                    </div>

                    <div class="flex items-center justify-between p-2 rounded bg-[#06111f]/70 border border-obsidian-border/50">
                        <span class="text-obsidian-muted">Tensión Entrada Comercial:</span>
                        <span class="text-amber-300 font-bold">220.0 VAC (Bifásica L1-L2, 60 Hz)</span>
                    </div>

                    <div class="flex items-center justify-between p-2 rounded bg-[#06111f]/70 border border-obsidian-border/50">
                        <span class="text-obsidian-muted">Tensión Salida a Equipos:</span>
                        <span class="text-cyan-300 font-bold">110.0 VAC (Cargas Racks / PDUs)</span>
                    </div>

                    <div class="flex items-center justify-between p-2 rounded bg-[#06111f]/70 border border-obsidian-border/50">
                        <span class="text-obsidian-muted">Inversor Interno (L-L):</span>
                        <span class="text-white font-bold">208.0 VAC / 28.0 A (Fase a Fase)</span>
                    </div>

                    <div class="flex items-center justify-between p-2 rounded bg-[#06111f]/70 border border-obsidian-border/50">
                        <span class="text-obsidian-muted">Banco DC de Baterías:</span>
                        <span class="text-emerald-400 font-bold">192.0 Vdc (16 × 12V en serie)</span>
                    </div>

                    <div class="flex items-center justify-between p-2 rounded bg-[#06111f]/70 border border-obsidian-border/50">
                        <span class="text-obsidian-muted">Versión de Firmware:</span>
                        <span class="text-purple-300 font-bold">{{ $device->firmware_version ?? 'R1.01.55' }}</span>
                    </div>

                    <div class="flex items-center justify-between p-2 rounded bg-[#06111f]/70 border border-obsidian-border/50">
                        <span class="text-obsidian-muted">Protocolo de Comunicación:</span>
                        <span class="text-white font-bold">Megatec Q1 Industrial (ASCII)</span>
                    </div>

                    <div class="flex items-center justify-between p-2 rounded bg-[#06111f]/70 border border-obsidian-border/50">
                        <span class="text-obsidian-muted">Cable de Interfaz Serial:</span>
                        <span class="text-yellow-300 font-bold">DB9 Null-Modem (Pines 2-3 cruzados, Pin 5 GND)</span>
                    </div>
                </div>
            </div>

            <div class="pt-3 mt-3 border-t border-obsidian-border/50 flex items-center justify-between text-[10px] font-mono text-obsidian-muted">
                <span>Estado de Alarma Sonora (Buzzer): <strong class="text-white">{{ $device->beeper_on ? 'Habilitado' : 'Silenciado' }}</strong></span>
                <span>Serial: <strong class="text-cyan-300">{{ $device->serial_number ?? 'LV6KL-VS-01' }}</strong></span>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    // Datos históricos precargados por Blade
    const initialLabels = @json($chartLabels);
    const initialInputV = @json($chartInputV);
    const initialOutputV = @json($chartOutputV);
    const initialOutputEquiposV = @json($chartOutputEquiposV);
    const initialLoadPct = @json($chartLoadPct);
    const initialBatteryPct = @json($chartBatteryPct);
    const initialTemp = @json($chartTemp);

    let currentDatasetView = 'voltages';
    let upsChart = null;

    function buildChartConfig(viewType) {
        let datasets = [];
        let yAxisLabel = 'VAC';

        if (viewType === 'voltages') {
            datasets = [
                {
                    label: 'Entrada Comercial (220V)',
                    data: initialInputV,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    tension: 0.25,
                    fill: false,
                    pointRadius: initialInputV.length > 30 ? 0 : 2,
                    yAxisID: 'y',
                },
                {
                    label: 'Salida a Equipos (110V)',
                    data: initialOutputEquiposV,
                    borderColor: '#00f0ff',
                    backgroundColor: 'rgba(0, 240, 255, 0.1)',
                    borderWidth: 2,
                    tension: 0.25,
                    fill: false,
                    pointRadius: initialOutputEquiposV.length > 30 ? 0 : 2,
                    yAxisID: 'y1',
                }
            ];
            yAxisLabel = 'VAC';
        } else if (viewType === 'load_battery') {
            datasets = [
                {
                    label: 'Nivel Batería (%)',
                    data: initialBatteryPct,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.15)',
                    borderWidth: 2,
                    tension: 0.25,
                    fill: true,
                    pointRadius: initialBatteryPct.length > 30 ? 0 : 2,
                    yAxisID: 'y',
                },
                {
                    label: 'Carga de Consumo (%)',
                    data: initialLoadPct,
                    borderColor: '#a855f7',
                    backgroundColor: 'rgba(168, 85, 247, 0.15)',
                    borderWidth: 2,
                    tension: 0.25,
                    fill: true,
                    pointRadius: initialLoadPct.length > 30 ? 0 : 2,
                    yAxisID: 'y',
                }
            ];
            yAxisLabel = '%';
        } else if (viewType === 'temperature') {
            datasets = [
                {
                    label: 'Temperatura Inversor (°C)',
                    data: initialTemp,
                    borderColor: '#f43f5e',
                    backgroundColor: 'rgba(244, 63, 94, 0.15)',
                    borderWidth: 2,
                    tension: 0.25,
                    fill: true,
                    pointRadius: initialTemp.length > 30 ? 0 : 2,
                    yAxisID: 'y',
                }
            ];
            yAxisLabel = '°C';
        }

        return {
            type: 'line',
            data: {
                labels: initialLabels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: '#8295b0',
                            font: { family: 'JetBrains Mono', size: 10 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(6, 17, 31, 0.95)',
                        titleColor: '#00f0ff',
                        bodyColor: '#ffffff',
                        borderColor: 'rgba(0, 240, 255, 0.4)',
                        borderWidth: 1,
                        padding: 10,
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(28, 46, 71, 0.4)' },
                        ticks: {
                            color: '#8295b0',
                            font: { family: 'JetBrains Mono', size: 9 },
                            maxTicksLimit: 10,
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: viewType === 'voltages',
                            text: 'Entrada Red (220V)',
                            color: '#f59e0b',
                            font: { family: 'JetBrains Mono', size: 10 }
                        },
                        grid: { color: 'rgba(28, 46, 71, 0.4)' },
                        suggestedMin: viewType === 'voltages' ? 180 : 0,
                        suggestedMax: viewType === 'voltages' ? 245 : 100,
                        ticks: {
                            color: viewType === 'voltages' ? '#f59e0b' : '#8295b0',
                            font: { family: 'JetBrains Mono', size: 9 },
                            callback: function(value) {
                                return value + ' ' + (viewType === 'voltages' ? 'V' : yAxisLabel);
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: viewType === 'voltages',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Salida Equipos (110V)',
                            color: '#00f0ff',
                            font: { family: 'JetBrains Mono', size: 10 }
                        },
                        grid: { drawOnChartArea: false },
                        suggestedMin: 90,
                        suggestedMax: 130,
                        ticks: {
                            color: '#00f0ff',
                            font: { family: 'JetBrains Mono', size: 9 },
                            callback: function(value) {
                                return value + ' V';
                            }
                        }
                    }
                }
            }
        };
    }

    function initChart() {
        const ctx = document.getElementById('upsTelemetryChart');
        if (!ctx) return;
        upsChart = new Chart(ctx, buildChartConfig('voltages'));
    }

    function setChartDataset(viewType) {
        currentDatasetView = viewType;
        const btnV = document.getElementById('btn-chart-voltages');
        const btnL = document.getElementById('btn-chart-load');
        const btnT = document.getElementById('btn-chart-temp');

        [btnV, btnL, btnT].forEach(b => {
            b.className = 'px-2.5 py-1 rounded font-bold transition text-obsidian-muted hover:text-white';
        });

        if (viewType === 'voltages') {
            btnV.className = 'px-2.5 py-1 rounded font-bold transition bg-obsidian-cyan text-black';
        } else if (viewType === 'load_battery') {
            btnL.className = 'px-2.5 py-1 rounded font-bold transition bg-obsidian-cyan text-black';
        } else if (viewType === 'temperature') {
            btnT.className = 'px-2.5 py-1 rounded font-bold transition bg-obsidian-cyan text-black';
        }

        if (upsChart) {
            upsChart.destroy();
            const ctx = document.getElementById('upsTelemetryChart');
            upsChart = new Chart(ctx, buildChartConfig(viewType));
        }
    }

    // Polling en tiempo real cada 5 segundos
    async function pollUpsLive() {
        try {
            const resp = await fetch('{{ route("admin.ups.live") }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!resp.ok) return;
            const data = await resp.json();
            if (!data.success || !data.device) return;

            const dev = data.device;
            const comp = data.computed || {};
            const outEquipos = dev.output_voltage_equipos !== undefined ? dev.output_voltage_equipos : comp.output_voltage_equipos;

            // Actualizar HUD
            if (document.getElementById('hud-in-v')) {
                document.getElementById('hud-in-v').innerHTML = `${Number(dev.input_voltage || 0).toFixed(1)} <span class="text-xs text-obsidian-muted">VAC</span>`;
            }
            if (document.getElementById('hud-out-v')) {
                document.getElementById('hud-out-v').innerHTML = `${Number(outEquipos !== undefined ? outEquipos : 110.0).toFixed(1)} <span class="text-xs text-obsidian-muted">VAC</span>`;
            }
            if (document.getElementById('hud-inverter-v')) {
                document.getElementById('hud-inverter-v').innerText = `${Number(dev.output_voltage || 208.0).toFixed(1)} VAC`;
            }
            document.getElementById('hud-freq').innerText = `${Number(dev.frequency || 60.0).toFixed(1)} Hz`;
            document.getElementById('hud-bypass').innerText = dev.is_bypass ? 'Activo' : 'Inactivo';
            
            document.getElementById('hud-battery-pct').innerText = `${dev.battery_percent || 100}%`;
            document.getElementById('hud-battery-volts').innerText = `(${Number(dev.battery_voltage || 2.25).toFixed(2)} V/celda)`;
            document.getElementById('hud-battery-bar').style.width = `${dev.battery_percent || 100}%`;
            document.getElementById('hud-runtime').innerText = `~${comp.estimated_runtime_human || comp.estimated_runtime_minutes + ' min'}`;

            document.getElementById('hud-load-pct').innerText = `${dev.load_percent || 0}%`;
            document.getElementById('hud-load-watts').innerText = `~${comp.load_watts || 0} Watts`;
            document.getElementById('hud-load-bar').style.width = `${dev.load_percent || 0}%`;
            document.getElementById('hud-temp').innerText = `${Number(dev.temperature_c || 40.0).toFixed(1)} °C`;

            document.getElementById('ups-last-seen').innerText = `Actualizado: ${comp.last_seen_human || 'Ahora mismo'}`;

            // Estado de red
            const isBat = dev.is_on_battery;
            const modeTitle = document.getElementById('hud-mode-title');
            const modeSub = document.getElementById('hud-mode-sub');
            const statusBadge = document.getElementById('ups-status-badge');
            const statusText = document.getElementById('ups-status-text');
            const pulseDot = document.getElementById('ups-pulse-dot');
            const statusIcon = document.getElementById('ups-status-icon');
            const headerIcon = document.getElementById('ups-header-icon');

            if (isBat) {
                modeTitle.innerText = 'EN BATERÍA';
                modeTitle.className = 'text-lg font-bold font-mono text-red-400';
                modeSub.innerHTML = `<span class="text-red-300 font-bold">Corte activo: ${comp.outage_duration || 'Iniciando'}</span>`;
                statusBadge.className = 'w-6 h-6 rounded-md bg-red-950/90 text-red-400 border border-red-500/60 animate-pulse inline-flex items-center justify-center cursor-help';
                statusBadge.title = 'Modo Batería (Corte Eléctrico Activo)';
                if (statusText) statusText.innerText = 'MODO BATERÍA (CORTE ELÉCTRICO)';
                pulseDot.className = 'w-2 h-2 rounded-full bg-red-400';
                statusIcon.innerText = 'power_off';
                headerIcon.className = 'w-10 h-10 rounded-xl bg-red-950/80 border-red-500/50 text-red-400 border flex items-center justify-center shrink-0 transition-colors';
            } else {
                modeTitle.innerText = 'LINEA CONECTADA';
                modeTitle.className = 'text-lg font-bold font-mono text-emerald-400';
                modeSub.innerHTML = '<span>Alimentación de red estable (Online)</span>';
                statusBadge.className = 'w-6 h-6 rounded-md bg-emerald-950/90 text-emerald-400 border border-emerald-500/60 inline-flex items-center justify-center cursor-help';
                statusBadge.title = 'Red Comercial Normal (Alimentación Estable)';
                if (statusText) statusText.innerText = 'RED COMERCIAL NORMAL';
                pulseDot.className = 'w-2 h-2 rounded-full bg-emerald-400';
                statusIcon.innerText = 'battery_charging_full';
                headerIcon.className = 'w-10 h-10 rounded-xl bg-cyan-950/80 border-cyan-500/50 text-cyan-400 border flex items-center justify-center shrink-0 transition-colors';
            }

        } catch (e) {
            console.warn('UPS poll error:', e);
        }
    }

    // Guardado Asíncrono de Alertas UPS (AJAX - Sin Recarga de Página)
    function initUpsSettingsForm() {
        const formUpsSettings = document.getElementById('form-ups-settings');
        if (!formUpsSettings) return;

        formUpsSettings.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-save-ups-settings');
            const btnText = document.getElementById('btn-save-text');
            const feedback = document.getElementById('ups-settings-feedback');

            if (btn) btn.disabled = true;
            if (btnText) btnText.innerText = 'Guardando configuración...';
            if (feedback) {
                feedback.className = 'hidden';
                feedback.innerHTML = '';
            }

            try {
                const formData = new FormData(formUpsSettings);
                const resp = await fetch(formUpsSettings.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const data = await resp.json();

                if (resp.ok && data.success) {
                    if (feedback) {
                        feedback.className = 'p-3 rounded-lg border border-emerald-500/60 bg-emerald-950/70 text-emerald-300 text-xs font-mono flex items-center justify-between shadow-lg shadow-emerald-950/30';
                        feedback.innerHTML = `
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-base text-emerald-400">check_circle</span>
                                <span>${data.message || 'Configuración guardada exitosamente.'}</span>
                            </div>
                        `;
                        setTimeout(() => {
                            feedback.className = 'hidden';
                        }, 5000);
                    }
                } else {
                    throw new Error(data.message || 'Error al guardar la configuración.');
                }
            } catch (err) {
                if (feedback) {
                    feedback.className = 'p-3 rounded-lg border border-red-500/60 bg-red-950/70 text-red-300 text-xs font-mono flex items-center gap-2 shadow-lg shadow-red-950/30';
                    feedback.innerHTML = `
                        <span class="material-symbols-outlined text-base text-red-400">error</span>
                        <span>${err.message || 'Ocurrió un error al procesar la solicitud.'}</span>
                    `;
                }
            } finally {
                if (btn) btn.disabled = false;
                if (btnText) btnText.innerText = 'Guardar Configuración de UPS';
            }
        });
    }

    // Diagnóstico y Autodescubrimiento Serial de UPS (AJAX)
    function initUpsSerialControls() {
        const btnRefresh = document.getElementById('btn-refresh-ports');
        const btnTest = document.getElementById('btn-test-serial');
        const btnTestText = document.getElementById('btn-test-serial-text');
        const btnScan = document.getElementById('btn-scan-ports');
        const btnScanText = document.getElementById('btn-scan-ports-text');
        const selectPort = document.getElementById('serial_port_select');
        const selectBaud = document.getElementById('baud_rate_select');
        const feedback = document.getElementById('serial-test-feedback');

        if (btnRefresh && selectPort) {
            btnRefresh.addEventListener('click', async () => {
                btnRefresh.classList.add('animate-spin');
                try {
                    const resp = await fetch('{{ route("admin.ups.ports") }}', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    });
                    const data = await resp.json();
                    if (data.success && Array.isArray(data.ports)) {
                        const currentVal = selectPort.value;
                        selectPort.innerHTML = '';
                        data.ports.forEach(p => {
                            const opt = document.createElement('option');
                            opt.value = p.path;
                            opt.textContent = p.label;
                            if (p.path === currentVal || p.path === data.current_port) opt.selected = true;
                            selectPort.appendChild(opt);
                        });
                    }
                } catch (e) {
                    console.warn('Error refrescando puertos:', e);
                } finally {
                    btnRefresh.classList.remove('animate-spin');
                }
            });
        }

        if (btnTest) {
            btnTest.addEventListener('click', async () => {
                btnTest.disabled = true;
                if (btnTestText) btnTestText.innerText = 'Probando enlace...';
                if (feedback) {
                    feedback.className = 'p-3 rounded-lg border border-cyan-500/40 bg-cyan-950/40 text-cyan-300 text-xs font-mono flex items-center gap-2';
                    feedback.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">sync</span><span>Enviando trama Megatec Q1 al puerto serial...</span>';
                }

                try {
                    const resp = await fetch('{{ route("admin.ups.test-serial") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            serial_port: selectPort ? selectPort.value : null,
                            baud_rate: selectBaud ? selectBaud.value : 2400
                        })
                    });
                    const data = await resp.json();
                    if (feedback) {
                        if (data.success) {
                            feedback.className = 'p-3 rounded-lg border border-emerald-500/60 bg-emerald-950/70 text-emerald-300 text-xs font-mono flex items-start gap-2 shadow-lg';
                            feedback.innerHTML = `<span class="material-symbols-outlined text-emerald-400 text-base shrink-0">check_circle</span><div><strong class="block text-emerald-200">¡Conexión Confirmada!</strong><span class="text-[11px]">${data.message}</span></div>`;
                        } else {
                            feedback.className = 'p-3 rounded-lg border border-red-500/60 bg-red-950/70 text-red-300 text-xs font-mono flex items-start gap-2 shadow-lg';
                            feedback.innerHTML = `<span class="material-symbols-outlined text-red-400 text-base shrink-0">cancel</span><div><strong class="block text-red-200">Sin Respuesta</strong><span class="text-[11px]">${data.message}</span></div>`;
                        }
                    }
                } catch (err) {
                    if (feedback) {
                        feedback.className = 'p-3 rounded-lg border border-red-500/60 bg-red-950/70 text-red-300 text-xs font-mono flex items-center gap-2';
                        feedback.innerHTML = `<span class="material-symbols-outlined text-red-400 text-base">error</span><span>${err.message || 'Error de comunicación con el servidor'}</span>`;
                    }
                } finally {
                    btnTest.disabled = false;
                    if (btnTestText) btnTestText.innerText = 'Probar Comunicación';
                }
            });
        }

        if (btnScan) {
            btnScan.addEventListener('click', async () => {
                btnScan.disabled = true;
                if (btnScanText) btnScanText.innerText = 'Escaneando puertos...';
                if (feedback) {
                    feedback.className = 'p-3 rounded-lg border border-purple-500/40 bg-purple-950/40 text-purple-300 text-xs font-mono flex items-center gap-2';
                    feedback.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">radar</span><span>Escaneando puertos seriales activos a 2400 y 9600 baudios...</span>';
                }

                try {
                    const resp = await fetch('{{ route("admin.ups.scan-ports") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await resp.json();
                    if (feedback) {
                        if (data.success) {
                            feedback.className = 'p-3 rounded-lg border border-emerald-500/60 bg-emerald-950/70 text-emerald-300 text-xs font-mono flex items-start gap-2 shadow-lg';
                            feedback.innerHTML = `<span class="material-symbols-outlined text-emerald-400 text-base shrink-0">check_circle</span><div><strong class="block text-emerald-200">¡UPS Encontrado!</strong><span class="text-[11px]">${data.message}</span></div>`;
                            if (btnRefresh) btnRefresh.click();
                        } else {
                            feedback.className = 'p-3 rounded-lg border border-amber-500/60 bg-amber-950/70 text-amber-300 text-xs font-mono flex items-start gap-2 shadow-lg';
                            feedback.innerHTML = `<span class="material-symbols-outlined text-amber-400 text-base shrink-0">info</span><div><strong class="block text-amber-200">Escaneo Finalizado</strong><span class="text-[11px]">${data.message}</span></div>`;
                        }
                    }
                } catch (err) {
                    if (feedback) {
                        feedback.className = 'p-3 rounded-lg border border-red-500/60 bg-red-950/70 text-red-300 text-xs font-mono flex items-center gap-2';
                        feedback.innerHTML = `<span class="material-symbols-outlined text-red-400 text-base">error</span><span>${err.message || 'Error durante el escaneo'}</span>`;
                    }
                } finally {
                    btnScan.disabled = false;
                    if (btnScanText) btnScanText.innerText = 'Escanear y Autodetectar';
                }
            });
        }

        const btnInterrogate = document.getElementById('btn-interrogate-hw');
        const btnInterrogateText = document.getElementById('btn-interrogate-text');

        if (btnInterrogate) {
            btnInterrogate.addEventListener('click', async () => {
                btnInterrogate.disabled = true;
                if (btnInterrogateText) btnInterrogateText.innerText = 'Interrogando...';
                if (feedback) {
                    feedback.className = 'p-3 rounded-lg border border-cyan-500/40 bg-cyan-950/40 text-cyan-300 text-xs font-mono flex items-center gap-2';
                    feedback.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">sync</span><span>Consultando instrucciones Megatec I & F y descriptores USB...</span>';
                }

                try {
                    const resp = await fetch('{{ route("admin.ups.interrogate") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            serial_port: selectPort ? selectPort.value : null,
                            baud_rate: selectBaud ? selectBaud.value : 2400
                        })
                    });
                    const data = await resp.json();
                    if (data.success && data.data) {
                        const info = data.data.info;
                        const rating = data.data.rating;
                        const usb = data.data.usb;

                        let detectedModel = (info && info.model) ? info.model : (usb && usb.usb_model ? (usb.usb_vendor + ' ' + usb.usb_model).trim() : '');
                        if (detectedModel) {
                            const inputModel = document.getElementById('input_ups_model');
                            const inputName = document.getElementById('input_ups_name');
                            if (inputModel) inputModel.value = detectedModel;
                            if (inputName) inputName.value = 'UPS ' + detectedModel + ' - Sede Valle Seco';
                        }
                        if (rating) {
                            const inV = document.getElementById('input_rating_voltage');
                            const inBat = document.getElementById('input_rating_bat_voltage');
                            if (inV && rating.rating_voltage) inV.value = rating.rating_voltage;
                            if (inBat && rating.rating_battery_voltage) inBat.value = rating.rating_battery_voltage;
                        }
                        if (feedback) {
                            feedback.className = 'p-3 rounded-lg border border-emerald-500/60 bg-emerald-950/70 text-emerald-300 text-xs font-mono';
                            feedback.innerHTML = `✨ <strong>¡Datos de hardware extraídos con éxito!</strong> Revise los campos autorellenados y guarde los cambios.`;
                        }
                    } else {
                        if (feedback) {
                            feedback.className = 'p-3 rounded-lg border border-amber-500/60 bg-amber-950/70 text-amber-300 text-xs font-mono';
                            feedback.innerHTML = `⚠️ ` + (data.message || 'El UPS no devolvió cadenas de texto legibles. Puede asignar el nombre manualmente.');
                        }
                    }
                } catch (err) {
                    if (feedback) {
                        feedback.className = 'p-3 rounded-lg border border-red-500/60 bg-red-950/70 text-red-300 text-xs font-mono';
                        feedback.innerHTML = `❌ Error consultando el servicio: ` + err.message;
                    }
                } finally {
                    btnInterrogate.disabled = false;
                    if (btnInterrogateText) btnInterrogateText.innerText = 'Auto-Extraer Identidad';
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initChart();
        initUpsSettingsForm();
        initUpsSerialControls();
        setInterval(pollUpsLive, 5000);
    });
</script>
@endpush
