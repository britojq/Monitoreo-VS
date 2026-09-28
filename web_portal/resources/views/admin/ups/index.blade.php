@extends('layouts.admin')

@section('page_title', 'Energía & UPS')

@section('admin_content')
<div class="space-y-6">
    <!-- PESTAÑAS EQUIPOS & HARDWARE -->
    <div class="flex flex-wrap items-center gap-2 border-b border-obsidian-border/80 pb-3">
        <a href="{{ route('admin.devices.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.devices.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">router</span>
            <span>Dispositivos de Red</span>
        </a>
        <a href="{{ route('admin.lifecycle.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.lifecycle.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">inventory_2</span>
            <span>Ciclo de Vida & Inventario</span>
        </a>
        <a href="{{ route('admin.wol.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.wol.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">power</span>
            <span>Wake-on-LAN (WoL)</span>
        </a>
        <a href="{{ route('admin.ups.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.ups.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">battery_charging_full</span>
            <span>Monitoreo UPS ZTG</span>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- BARRA SUPERIOR: ESTADO EN VIVO Y RESUMEN GENERAL                          -->
    <!-- ========================================================================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 sm:px-4 rounded-xl bg-obsidian-panel/90 border border-obsidian-border/90 backdrop-blur-md shadow-lg">
        <div class="flex items-center gap-3">
            <div id="ups-header-icon" class="w-10 h-10 rounded-xl {{ $device->is_on_battery ? 'bg-red-950/80 border-red-500/50 text-red-400' : 'bg-cyan-950/80 border-cyan-500/50 text-cyan-400' }} border flex items-center justify-center shrink-0 transition-colors">
                <span class="material-symbols-outlined text-2xl" id="ups-status-icon">
                    {{ $device->is_on_battery ? 'power_off' : 'battery_charging_full' }}
                </span>
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-sm sm:text-base font-bold text-white uppercase font-mono tracking-wider">
                        {{ $device->name }}
                    </h1>
                    <span id="ups-status-badge" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold {{ $device->is_on_battery ? 'bg-red-950/90 text-red-400 border border-red-500/60 animate-pulse' : ($device->is_online ? 'bg-emerald-950/90 text-emerald-400 border border-emerald-500/60' : 'bg-slate-900 text-slate-400 border border-slate-700') }}">
                        <span id="ups-pulse-dot" class="w-1.5 h-1.5 rounded-full {{ $device->is_on_battery ? 'bg-red-400' : ($device->is_online ? 'bg-emerald-400' : 'bg-slate-400') }}"></span>
                        <span id="ups-status-text">{{ $device->is_on_battery ? 'MODO BATERÍA (CORTE ELÉCTRICO)' : ($device->is_online ? 'RED COMERCIAL NORMAL' : 'DESCONECTADO') }}</span>
                    </span>
                </div>
                <p class="text-[11px] font-mono text-obsidian-muted flex items-center gap-2 mt-0.5">
                    <span>Modelo: <strong class="text-cyan-300">{{ $device->model }}</strong></span>
                    <span>•</span>
                    <span>Puerto: <strong class="text-white">{{ $device->serial_port }}</strong> @ {{ $device->baud_rate }} bps</span>
                    <span>•</span>
                    <span id="ups-last-seen" class="text-slate-400">Actualizado: {{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Sin datos' }}</span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#06111f] border border-cyan-500/30 text-cyan-300 text-xs font-mono">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                <span>SONDEO SERIAL ACTIVO (5s)</span>
            </span>
        </div>
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
                    {{ $device->is_on_battery ? 'EN BATERÍA' : 'LÍNEA COMERCIAL' }}
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

        <!-- CARD 3: VOLTAJES DE ENTRADA & SALIDA -->
        <div class="p-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/80 backdrop-blur-md shadow-md flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono uppercase font-bold text-obsidian-muted tracking-wider">Voltajes AC</span>
                <span class="material-symbols-outlined text-lg text-yellow-400">bolt</span>
            </div>
            <div class="my-2 flex items-center justify-between gap-3">
                <div>
                    <span class="text-[9px] uppercase font-mono text-obsidian-muted block">Entrada (Red)</span>
                    <span id="hud-in-v" class="text-lg font-bold font-mono text-white">
                        {{ number_format($device->input_voltage ?? 0.0, 1) }} <span class="text-xs text-obsidian-muted">VAC</span>
                    </span>
                </div>
                <div class="text-center text-obsidian-muted">
                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                </div>
                <div>
                    <span class="text-[9px] uppercase font-mono text-obsidian-muted block">Salida (UPS)</span>
                    <span id="hud-out-v" class="text-lg font-bold font-mono text-cyan-300">
                        {{ number_format($device->output_voltage ?? 0.0, 1) }} <span class="text-xs text-obsidian-muted">VAC</span>
                    </span>
                </div>
            </div>
            <div class="pt-2 border-t border-obsidian-border/50 flex items-center justify-between text-[11px] font-mono text-obsidian-muted">
                <span>Regulación: <strong class="text-emerald-400">Doble Conversión</strong></span>
                <span>Nominal: <strong class="text-white">{{ number_format($device->rating_voltage ?? 208.0, 0) }} VAC</strong></span>
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
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- PANEL 1: CONFIGURACIÓN DE ALERTAS TELEGRAM -->
        <div class="p-4 sm:p-5 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/80 backdrop-blur-md shadow-lg flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2 pb-3 border-b border-obsidian-border/60 mb-4">
                    <span class="material-symbols-outlined text-purple-400 text-xl">notifications_active</span>
                    <div>
                        <h3 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                            Alertas Reactivas a Telegram (Apagón & Restablecimiento)
                        </h3>
                        <p class="text-[10px] font-mono text-obsidian-muted">
                            Despacho instantáneo ante eventos de corte eléctrico, batería baja o retorno de red comercial.
                        </p>
                    </div>
                </div>

                <form action="{{ route('admin.ups.settings') }}" method="POST" id="form-ups-settings" class="space-y-4">
                    @csrf

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
                                    <span class="text-xs font-bold font-mono text-white block">👥 Grupo Corporativo</span>
                                    <span class="text-[10px] font-mono text-obsidian-muted block mt-0.5">
                                        Grupo Sede: <code class="text-purple-300">-1001383163558</code>
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- AVISO DE REGLA DE SEGURIDAD -->
                    <div class="p-2.5 rounded-lg bg-cyan-950/30 border border-cyan-500/30 text-[10px] font-mono text-cyan-200/80 leading-relaxed">
                        <span class="font-bold text-cyan-300">🛡️ Control de Seguridad:</span> En servidores de desarrollo o modo esclavo, las alertas dirigidas a grupo se reenrutan automáticamente al chat privado del Administrador para evitar avisos accidentales.
                    </div>

                    @if(!$isClusterSlave)
                    <div class="pt-2 space-y-3">
                        <button type="submit" id="btn-save-ups-settings" class="w-full py-2.5 px-4 rounded-lg bg-obsidian-cyan text-black font-bold font-mono text-xs uppercase tracking-wider hover:bg-cyan-300 transition flex items-center justify-center gap-2 cursor-pointer shadow-md shadow-cyan-950/40">
                            <span class="material-symbols-outlined text-base">save</span>
                            <span id="btn-save-text">Guardar Configuración de Alertas</span>
                        </button>
                        <div id="ups-settings-feedback" class="hidden text-xs font-mono transition-all duration-300"></div>
                    </div>
                    @endif
                </form>
            </div>
        </div>

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
                        <span class="text-obsidian-muted">Tensión & Corriente Nominal:</span>
                        <span class="text-white font-bold">208.0 VAC / 28.0 A (60 Hz)</span>
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
                    label: 'Voltaje Entrada (VAC)',
                    data: initialInputV,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    tension: 0.25,
                    fill: false,
                    pointRadius: initialInputV.length > 30 ? 0 : 2,
                },
                {
                    label: 'Voltaje Salida (VAC)',
                    data: initialOutputV,
                    borderColor: '#00f0ff',
                    backgroundColor: 'rgba(0, 240, 255, 0.1)',
                    borderWidth: 2,
                    tension: 0.25,
                    fill: false,
                    pointRadius: initialOutputV.length > 30 ? 0 : 2,
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
                        grid: { color: 'rgba(28, 46, 71, 0.4)' },
                        ticks: {
                            color: '#8295b0',
                            font: { family: 'JetBrains Mono', size: 9 },
                            callback: function(value) {
                                return value + ' ' + yAxisLabel;
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
            const resp = await fetch('{{ route("admin.ups.live") }}');
            if (!resp.ok) return;
            const data = await resp.json();
            if (!data.success || !data.device) return;

            const dev = data.device;
            const comp = data.computed || {};

            // Actualizar HUD
            document.getElementById('hud-in-v').innerHTML = `${Number(dev.input_voltage || 0).toFixed(1)} <span class="text-xs text-obsidian-muted">VAC</span>`;
            document.getElementById('hud-out-v').innerHTML = `${Number(dev.output_voltage || 0).toFixed(1)} <span class="text-xs text-obsidian-muted">VAC</span>`;
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
                statusBadge.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-red-950/90 text-red-400 border border-red-500/60 animate-pulse';
                statusText.innerText = 'MODO BATERÍA (CORTE ELÉCTRICO)';
                pulseDot.className = 'w-1.5 h-1.5 rounded-full bg-red-400';
                statusIcon.innerText = 'power_off';
                headerIcon.className = 'w-10 h-10 rounded-xl bg-red-950/80 border-red-500/50 text-red-400 border flex items-center justify-center shrink-0 transition-colors';
            } else {
                modeTitle.innerText = 'LÍNEA COMERCIAL';
                modeTitle.className = 'text-lg font-bold font-mono text-emerald-400';
                modeSub.innerHTML = '<span>Alimentación de red estable (Online)</span>';
                statusBadge.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-950/90 text-emerald-400 border border-emerald-500/60';
                statusText.innerText = 'RED COMERCIAL NORMAL';
                pulseDot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-400';
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
                if (btnText) btnText.innerText = 'Guardar Configuración de Alertas';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initChart();
        initUpsSettingsForm();
        setInterval(pollUpsLive, 5000);
    });
</script>
@endpush
