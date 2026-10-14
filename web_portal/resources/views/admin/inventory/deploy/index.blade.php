@extends('layouts.admin')

@section('page_title', 'Despliegue Remoto de Agente GLPI (ITAM)')

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
    }
</style>

<div class="space-y-4">
    <!-- CABECERA PRINCIPAL -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <a href="{{ route('admin.devices.index') }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-[10.5px] font-mono font-semibold group shadow-xs">
                    <span class="material-symbols-outlined text-xs group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Equipos &amp; Activos
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-xs font-mono text-obsidian-muted">Despliegue GLPI</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="p-2 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">
                    <span class="material-symbols-outlined text-2xl">rocket_launch</span>
                </span>
                <div>
                    <h1 class="text-xl font-black text-white tracking-wide">
                        Despliegue Remoto de Agente GLPI
                    </h1>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Instalación silenciosa desatendida (Windows 32/64b y Linux Xubuntu) y conmutación entre servidores.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($isClusterSlave ?? false)
                <div class="relative group">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-amber-950/80 border border-amber-500/40 text-amber-300 transition shadow-xs cursor-help">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                        <span class="material-symbols-outlined text-base text-amber-400">dns</span>
                    </span>
                    <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-amber-500/50 shadow-xl shadow-amber-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-xs text-amber-400">dns</span>
                            <span class="font-bold text-amber-200">Fuente: Servidor Esclavo (Réplica)</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="relative group">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 transition shadow-xs cursor-help">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        <span class="material-symbols-outlined text-base text-cyan-400">dns</span>
                    </span>
                    <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-xs text-cyan-400">dns</span>
                            <span class="font-bold text-cyan-200">Fuente: Servidor Maestro</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- BOTÓN EXPLORAR INSTALADORES -->
            <div class="relative group">
                <a href="/instaladores/" target="_blank" class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition shadow-xs cursor-pointer">
                    <span class="material-symbols-outlined text-base">folder_zip</span>
                </a>
                <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                    <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs text-cyan-400">folder_zip</span>
                        <span class="font-bold text-cyan-200">Explorar Instaladores</span>
                    </div>
                </div>
            </div>

            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.deploy'))
            <!-- BOTÓN ESCANEAR SUBRED / SEGMENTO -->
            <div class="relative group">
                <button type="button" onclick="openScanSubnetModal()" class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition shadow-xs cursor-pointer" title="Escanear Segmento de Red Bajo Demanda">
                    <span class="material-symbols-outlined text-base">radar</span>
                </button>
                <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                    <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs text-cyan-400">radar</span>
                        <span class="font-bold text-cyan-200">Escanear Segmento</span>
                    </div>
                </div>
            </div>

            <!-- BOTÓN AUDITORÍA DE DISCREPANCIAS DE HARDWARE (HARDWARE DRIFT) -->
            <div class="relative group">
                <button type="button" onclick="openHardwareDriftModal()" class="relative inline-flex items-center justify-center w-8 h-8 rounded-xl bg-obsidian-panel border {{ ($driftSummary['total_drift_count'] ?? 0) > 0 ? 'border-amber-500/70 text-amber-400 bg-amber-950/30' : 'border-cyan-500/40 text-cyan-300' }} hover:border-amber-400 hover:text-white font-mono text-xs font-bold transition shadow-xs cursor-pointer" title="Auditoría Forense de Discrepancias de Hardware ({{ $driftSummary['total_drift_count'] ?? 0 }})">
                    <span class="material-symbols-outlined text-base {{ ($driftSummary['total_drift_count'] ?? 0) > 0 ? 'animate-pulse' : '' }}">warning_amber</span>
                    @if(($driftSummary['total_drift_count'] ?? 0) > 0)
                        <span class="absolute -top-1 -right-1 flex h-4 min-w-4 px-1 items-center justify-center rounded-full bg-amber-500 text-black text-[9px] font-bold font-mono">
                            {{ $driftSummary['total_drift_count'] }}
                        </span>
                    @endif
                </button>
                <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                    <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-amber-500/50 shadow-xl shadow-amber-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs text-amber-400">warning</span>
                        <span class="font-bold text-amber-200">Discrepancias de Hardware ({{ $driftSummary['total_drift_count'] ?? 0 }})</span>
                    </div>
                </div>
            </div>
            @endif

            <!-- BOTÓN GUÍA DE INSTALACIÓN & COMANDOS 1-CLIC -->
            <div class="relative group">
                <button type="button" onclick="openOneLinerModal()" class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition shadow-md shadow-cyan-500/20 cursor-pointer" title="Guía de Instalación GLPI (1-Clic y Manual)">
                    <span class="material-symbols-outlined text-base">terminal</span>
                </button>
                <div class="absolute right-0 top-full mt-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                    <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs text-cyan-400">menu_book</span>
                        <span class="font-bold text-cyan-200">Guía de Instalación GLPI</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    <!-- TARJETAS HUD DE ESTADÍSTICAS -->
    <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
        <!-- TOTAL DETECTADOS -->
        <div class="p-3 rounded-xl bg-obsidian-panel/60 border border-obsidian-border flex items-center justify-between">
            <div>
                <div class="text-[10px] font-mono uppercase text-obsidian-muted">Total Equipos</div>
                <div class="text-xl font-black text-white font-mono mt-0.5">{{ $stats['total'] }}</div>
            </div>
            <span class="material-symbols-outlined text-2xl text-cyan-400/80">lan</span>
        </div>

        <!-- EQUIPOS DE SEDES -->
        <div class="p-3 rounded-xl bg-cyan-950/20 border border-cyan-500/30 flex items-center justify-between">
            <div>
                <div class="text-[10px] font-mono uppercase text-cyan-400">Sedes CIAU</div>
                <div class="text-xl font-black text-cyan-300 font-mono mt-0.5">{{ $stats['monitored'] }}</div>
            </div>
            <span class="material-symbols-outlined text-2xl text-cyan-400">location_city</span>
        </div>

        <!-- WINDOWS -->
        <div class="p-3 rounded-xl bg-obsidian-panel/60 border border-obsidian-border flex items-center justify-between">
            <div>
                <div class="text-[10px] font-mono uppercase text-obsidian-muted">Windows (32/64)</div>
                <div class="text-xl font-black text-blue-400 font-mono mt-0.5">{{ $stats['windows'] }}</div>
            </div>
            <span class="material-symbols-outlined text-2xl text-blue-400/80">desktop_windows</span>
        </div>

        <!-- LINUX -->
        <div class="p-3 rounded-xl bg-obsidian-panel/60 border border-obsidian-border flex items-center justify-between">
            <div>
                <div class="text-[10px] font-mono uppercase text-obsidian-muted">Linux / Xubuntu</div>
                <div class="text-xl font-black text-emerald-400 font-mono mt-0.5">{{ $stats['linux'] }}</div>
            </div>
            <span class="material-symbols-outlined text-2xl text-emerald-400/80">terminal</span>
        </div>

        <!-- REPORTANDO GLPI -->
        <div class="p-3 rounded-xl bg-emerald-950/20 border border-emerald-500/30 flex items-center justify-between">
            <div>
                <div class="text-[10px] font-mono uppercase text-emerald-400">Agentes Activos</div>
                <div class="text-xl font-black text-emerald-300 font-mono mt-0.5">{{ $stats['reporting'] }}</div>
            </div>
            <span class="material-symbols-outlined text-2xl text-emerald-400 animate-pulse">check_circle</span>
        </div>

        <!-- PENDIENTES (SOLO PCs SOPORTADOS) -->
        <div class="p-3 rounded-xl bg-amber-950/20 border border-amber-500/30 flex items-center justify-between col-span-2 sm:col-span-1">
            <div>
                <div class="text-[10px] font-mono uppercase text-amber-400">Sin Agente (PCs)</div>
                <div class="text-xl font-black text-amber-300 font-mono mt-0.5">{{ $stats['pending'] }}</div>
            </div>
            <span class="material-symbols-outlined text-2xl text-amber-400">pending_actions</span>
        </div>
    </div>

    <!-- BARRA DE BÚSQUEDA Y FILTROS -->
    <div class="p-3 rounded-xl bg-obsidian-panel/40 border border-obsidian-border">
        <form method="GET" action="{{ route('admin.inventory.deploy') }}" class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[280px]">
                <div class="relative flex-1 min-w-[180px]">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-obsidian-muted text-base">search</span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por IP, Hostname, Sede o MAC..." class="w-full pl-9 pr-3 py-1.5 rounded-lg bg-obsidian-card border border-obsidian-border text-white font-mono text-xs focus:outline-none focus:border-cyan-500 transition">
                </div>

                <!-- FILTRO POR SEDE / ORIGEN -->
                <select name="site" onchange="this.form.submit()" class="px-3 py-1.5 rounded-lg bg-obsidian-card border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                    <option value="">🏢 Todas las Sedes & Fuentes</option>
                    <option value="monitored" {{ request('site') === 'monitored' ? 'selected' : '' }}>🏛️ Solo Equipos Monitoreados (Sedes)</option>
                    <option value="discovered" {{ request('site') === 'discovered' ? 'selected' : '' }}>🌐 Solo Barrido de Red (ARP)</option>
                    <optgroup label="Filtrar por Sede:">
                        @foreach($sedes as $s)
                            <option value="{{ $s->id }}" {{ request('site') == $s->id ? 'selected' : '' }}>📍 {{ $s->name }}</option>
                        @endforeach
                    </optgroup>
                </select>

                <select name="os" onchange="this.form.submit()" class="px-3 py-1.5 rounded-lg bg-obsidian-card border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                    <option value="all">S.O.: Todos</option>
                    <option value="windows" {{ request('os') === 'windows' ? 'selected' : '' }}>Windows (32/64b)</option>
                    <option value="linux" {{ request('os') === 'linux' ? 'selected' : '' }}>Linux / Xubuntu</option>
                </select>

                <select name="status" onchange="this.form.submit()" class="px-3 py-1.5 rounded-lg bg-obsidian-card border border-obsidian-border text-xs font-mono text-white focus:outline-none focus:border-cyan-500">
                    <option value="">Estado GLPI: Todos</option>
                    <option value="reporting" {{ request('status') === 'reporting' ? 'selected' : '' }}>🟢 Reportando</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>⏳ Sin Agente (PCs)</option>
                    <option value="unregistered" {{ request('status') === 'unregistered' ? 'selected' : '' }}>🆕 Por Catalogar (Nuevos)</option>
                    <option value="unsupported" {{ request('status') === 'unsupported' ? 'selected' : '' }}>🚫 Sin Soporte GLPI</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                @if(request()->hasAny(['search', 'site', 'os', 'status']))
                    <a href="{{ route('admin.inventory.deploy') }}" class="px-2.5 py-1.5 rounded-lg text-xs font-mono text-obsidian-muted hover:text-white border border-obsidian-border hover:bg-obsidian-panel transition flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">clear</span>
                        <span>Limpiar</span>
                    </a>
                @endif
                <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-cyan-500 text-black font-mono text-xs font-bold hover:bg-cyan-400 transition flex items-center gap-1 shadow-sm">
                    <span class="material-symbols-outlined text-sm">filter_alt</span>
                    <span>Filtrar</span>
                </button>
            </div>
        </form>
    </div>

    @if(!empty($scanSubnet))
    <!-- BANNER DE ESCANEO DE SUBRED ACTIVO -->
    <div class="p-3.5 rounded-xl bg-cyan-950/40 border border-cyan-500/40 flex flex-wrap items-center justify-between gap-3 animate-in fade-in duration-200">
        <div class="flex items-center gap-2.5">
            <span class="p-2 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/30">
                <span class="material-symbols-outlined text-lg animate-pulse">radar</span>
            </span>
            <div>
                <div class="text-xs font-bold text-white font-mono flex items-center gap-2">
                    <span>Resultados de Escaneo en Subred:</span>
                    <span class="px-2 py-0.5 rounded bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 font-mono">{{ $scanSubnet }}</span>
                </div>
                <div class="text-[10px] text-obsidian-muted mt-0.5">
                    Mostrando equipos de inventario y nuevos hallazgos activos en este segmento.
                </div>
            </div>
        </div>
        <a href="{{ route('admin.inventory.deploy') }}" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40 font-mono text-xs transition flex items-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-sm text-red-400">close</span>
            <span>Cerrar Escaneo (Ver Solo Inventario)</span>
        </a>
    </div>
    @endif

    <!-- TABLA DE EQUIPOS DETECTADOS -->
    <div class="rounded-xl border border-obsidian-border bg-obsidian-card overflow-hidden">
        <div class="custom-table-scroll">
            <table class="w-full text-left font-mono text-xs">
                <thead>
                    <tr class="bg-obsidian-panel/80 text-obsidian-muted uppercase text-[10px] tracking-wider border-b border-obsidian-border">
                        <th class="py-2.5 px-3 text-center w-8">
                            <input type="checkbox" id="select-all-devices" onchange="toggleSelectAllDevices(this.checked)" class="rounded border-obsidian-border bg-obsidian-card text-cyan-500 focus:ring-0 focus:ring-offset-0 cursor-pointer" title="Seleccionar todos los visibles">
                        </th>
                        <th class="py-2.5 px-3">Dispositivo / Hostname</th>
                        <th class="py-2.5 px-2.5 whitespace-nowrap">Sede / Origen</th>
                        <th class="py-2.5 px-2.5 whitespace-nowrap">Dirección IP & MAC</th>
                        <th class="py-2.5 px-2.5 whitespace-nowrap">Modelo / Fabricante</th>
                        <th class="py-2.5 px-2 text-center whitespace-nowrap" title="Sistema Operativo">S.O.</th>
                        <th class="py-2.5 px-2 text-center whitespace-nowrap" title="Estado del Agente GLPI">Agente GLPI</th>
                        <th class="py-2.5 px-3 text-center whitespace-nowrap" title="Acciones de Despliegue">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/40">
                    @forelse($paginatedDevices as $dev)
                    <tr id="row-{{ str_replace('.', '-', $dev['ip']) }}" class="hover:bg-cyan-500/5 transition">
                        <!-- SELECT CHECKBOX -->
                        <td class="py-2 px-3 text-center">
                            <input type="checkbox" class="device-checkbox rounded border-obsidian-border bg-obsidian-card text-cyan-500 focus:ring-0 focus:ring-offset-0 cursor-pointer"
                                value="{{ $dev['ip'] }}"
                                data-ip="{{ $dev['ip'] }}"
                                data-mac="{{ $dev['mac'] !== 'No registrada' ? $dev['mac'] : '' }}"
                                data-hostname="{{ $dev['hostname'] }}"
                                data-vendor="{{ $dev['vendor'] }}"
                                data-os-type="{{ $dev['os_type'] }}"
                                data-os-arch="{{ $dev['os_arch'] }}"
                                data-is-unregistered="{{ $dev['is_unregistered'] ? '1' : '0' }}"
                                data-is-glpi-supported="{{ $dev['is_glpi_supported'] ? '1' : '0' }}"
                                data-suggested-key="{{ $dev['suggested_type']['key'] ?? 'desktop' }}"
                                data-suggested-label="{{ $dev['suggested_type']['label'] ?? '' }}"
                                data-site-id="{{ $dev['site_id'] }}"
                                onchange="onDeviceSelectionChanged()">
                        </td>

                        <!-- HOSTNAME -->
                        <td class="py-2 px-3 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-base {{ $dev['os_type'] === 'windows' ? 'text-blue-400' : ($dev['os_type'] === 'linux' ? 'text-emerald-400' : 'text-obsidian-muted') }}">
                                    {{ $dev['os_type'] === 'windows' ? 'desktop_windows' : ($dev['os_type'] === 'linux' ? 'terminal' : 'devices') }}
                                </span>
                                <div>
                                    <div class="font-bold text-white flex items-center gap-1.5 flex-wrap">
                                        <span>{{ $dev['hostname'] }}</span>
                                        @if($dev['is_new_discovery'] ?? false)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 inline-flex items-center gap-0.5" title="Equipo detectado en el barrido de subred no registrado en inventario">
                                                <span class="material-symbols-outlined text-[10px]">auto_awesome</span>
                                                Nuevo en Red
                                            </span>
                                        @endif
                                        @if($dev['is_ip_reassigned'] ?? false)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 inline-flex items-center gap-0.5" title="Reasignación DHCP detectada: Esta MAC pertenecía anteriormente a {{ $dev['previous_ip'] }} ({{ $dev['reassigned_asset_name'] ?? 'Activo' }})">
                                                <span class="material-symbols-outlined text-[10px]">sync_alt</span>
                                                Antes: {{ $dev['previous_ip'] }}
                                            </span>
                                        @endif
                                    </div>
                                    @if(!empty($dev['notes']))
                                        <div class="text-[10px] text-cyan-400/80 max-w-[200px] truncate" title="{{ $dev['notes'] }}">{{ $dev['notes'] }}</div>
                                    @else
                                        <div class="text-[10px] text-obsidian-muted">Visto: {{ $dev['last_seen'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <!-- SEDE / ORIGEN -->
                        <td class="py-2 px-2.5 whitespace-nowrap">
                            @if($dev['source'] === 'monitored' || $dev['source'] === 'both')
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-cyan-500/15 text-cyan-300 border border-cyan-500/30 inline-flex items-center gap-1" title="{{ $dev['source_label'] }}">
                                    <span class="material-symbols-outlined text-xs">location_city</span>
                                    {{ $dev['site_name'] }}
                                </span>
                            @elseif($dev['source'] === 'scan')
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 inline-flex items-center gap-1" title="{{ $dev['source_label'] }}">
                                    <span class="material-symbols-outlined text-xs">radar</span>
                                    {{ $dev['site_name'] }}
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/10 text-amber-300 border border-amber-500/20 inline-flex items-center gap-1" title="Detectado por barrido automático de red ARP">
                                    <span class="material-symbols-outlined text-xs">radar</span>
                                    Red Local (ARP)
                                </span>
                            @endif
                        </td>

                        <!-- IP & MAC -->
                        <td class="py-2 px-2.5 whitespace-nowrap">
                            <div class="font-bold text-cyan-300 font-mono text-[11.5px]">{{ $dev['ip'] }}</div>
                            <div class="text-[9.5px] text-obsidian-muted font-mono">{{ $dev['mac'] ?: '—' }}</div>
                        </td>

                        <!-- VENDOR / MODELO -->
                        <td class="py-2 px-2.5 whitespace-nowrap text-obsidian-muted text-[11px]">
                            {{ Str::limit($dev['vendor'], 22) }}
                        </td>

                        <!-- SISTEMA OPERATIVO (ICONO + TOOLTIP OBSIDIAN) -->
                        <td class="py-2 px-2 text-center whitespace-nowrap">
                            @if($dev['os_type'] === 'windows')
                                <div class="relative group inline-flex items-center justify-center">
                                    <span class="w-7 h-7 rounded-lg bg-blue-500/15 border border-blue-500/35 text-blue-400 inline-flex items-center justify-center shadow-xs cursor-help" title="Sistema Operativo: {{ $dev['os_label'] }}">
                                        <span class="material-symbols-outlined text-[17px]">window</span>
                                    </span>
                                    <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-1.5 hidden group-hover:flex flex-col items-center z-50 pointer-events-none transition-all duration-200">
                                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-blue-500/50 shadow-xl shadow-blue-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-xs text-blue-400">window</span>
                                            <span class="font-bold text-blue-200">{{ $dev['os_label'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            @elseif($dev['os_type'] === 'linux')
                                <div class="relative group inline-flex items-center justify-center">
                                    <span class="w-7 h-7 rounded-lg bg-emerald-500/15 border border-emerald-500/35 text-emerald-400 inline-flex items-center justify-center shadow-xs cursor-help" title="Sistema Operativo: {{ $dev['os_label'] }}">
                                        <span class="material-symbols-outlined text-[17px]">terminal</span>
                                    </span>
                                    <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-1.5 hidden group-hover:flex flex-col items-center z-50 pointer-events-none transition-all duration-200">
                                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-emerald-500/50 shadow-xl shadow-emerald-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-xs text-emerald-400">terminal</span>
                                            <span class="font-bold text-emerald-200">{{ $dev['os_label'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="relative group inline-flex items-center justify-center">
                                    <span class="w-7 h-7 rounded-lg bg-slate-800/80 border border-slate-700/80 text-slate-400 inline-flex items-center justify-center shadow-xs cursor-help" title="Sistema Operativo: Desconocido">
                                        <span class="material-symbols-outlined text-[17px]">help</span>
                                    </span>
                                    <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-1.5 hidden group-hover:flex flex-col items-center z-50 pointer-events-none transition-all duration-200">
                                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-slate-600 shadow-xl shadow-slate-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-xs text-slate-400">help</span>
                                            <span class="text-slate-300">Desconocido</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>

                        <!-- ESTADO GLPI (ICONO + TOOLTIP OBSIDIAN) -->
                        <td class="py-2 px-2 text-center whitespace-nowrap">
                            @if($dev['is_unregistered'])
                                <div class="relative group inline-flex items-center justify-center">
                                    <span class="w-7 h-7 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 inline-flex items-center justify-center shadow-xs cursor-help" title="Nuevo en Red: Pendiente por catalogar e incorporar a Inventario (Sugerido: {{ $dev['suggested_type']['label'] }})">
                                        <span class="material-symbols-outlined text-[17px] text-amber-400 animate-pulse">new_releases</span>
                                    </span>
                                    <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-1.5 hidden group-hover:flex flex-col items-center z-50 pointer-events-none transition-all duration-200">
                                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-amber-500/50 shadow-xl shadow-amber-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-xs text-amber-400">new_releases</span>
                                            <span class="font-bold text-amber-200">Por Catalogar</span>
                                            <span class="text-[9.5px] text-amber-300/80">({{ $dev['suggested_type']['label'] }})</span>
                                        </div>
                                    </div>
                                </div>
                            @elseif(!$dev['is_glpi_supported'])
                                <div class="relative group inline-flex items-center justify-center">
                                    <span class="w-7 h-7 rounded-lg bg-slate-800/80 border border-slate-700/80 text-slate-400 inline-flex items-center justify-center shadow-xs cursor-help" title="Sin Soporte GLPI: Dispositivo de red o periférico no compatible con agente local">
                                        <span class="material-symbols-outlined text-[17px] text-slate-400">block</span>
                                    </span>
                                    <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-1.5 hidden group-hover:flex flex-col items-center z-50 pointer-events-none transition-all duration-200">
                                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-slate-700 shadow-xl shadow-slate-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-xs text-slate-400">block</span>
                                            <span class="font-bold text-slate-300">Sin Soporte GLPI</span>
                                            <span class="text-[9.5px] text-obsidian-muted">(Hardware / Red)</span>
                                        </div>
                                    </div>
                                </div>
                            @elseif($dev['is_reporting'])
                                <div class="relative group inline-flex items-center justify-center">
                                    <span class="w-7 h-7 rounded-lg bg-emerald-500/15 border border-emerald-500/40 text-emerald-400 inline-flex items-center justify-center shadow-xs cursor-help" title="GLPI Activo: Reportando ({{ $dev['last_sync'] }})">
                                        <span class="material-symbols-outlined text-[17px] text-emerald-400 animate-pulse">check_circle</span>
                                    </span>
                                    <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-1.5 hidden group-hover:flex flex-col items-center z-50 pointer-events-none transition-all duration-200">
                                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-emerald-500/50 shadow-xl shadow-emerald-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-xs text-emerald-400">check_circle</span>
                                            <span class="font-bold text-emerald-200">Reportando a Central</span>
                                            <span class="text-[9.5px] text-emerald-300/80">({{ $dev['last_sync'] }})</span>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="relative group inline-flex items-center justify-center">
                                    <span class="w-7 h-7 rounded-lg bg-amber-500/15 border border-amber-500/35 text-amber-400 inline-flex items-center justify-center shadow-xs cursor-help" title="Sin Agente GLPI: Estación pendiente por despliegue">
                                        <span class="material-symbols-outlined text-[17px]">schedule</span>
                                    </span>
                                    <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-1.5 hidden group-hover:flex flex-col items-center z-50 pointer-events-none transition-all duration-200">
                                        <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-amber-500/50 shadow-xl shadow-amber-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-xs text-amber-400">schedule</span>
                                            <span class="font-bold text-amber-200">Sin Agente GLPI</span>
                                            <span class="text-[9.5px] text-amber-300/80">(Pendiente)</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>

                        <!-- ACCIONES (ICONOS COMPACTOS + TOOLTIP OBSIDIAN) -->
                        <td class="py-2 px-3 whitespace-nowrap text-center">
                            <div class="inline-flex items-center justify-center gap-1">
                                @if($dev['is_unregistered'] && (auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage')))
                                    <!-- BOTÓN CATALOGAR EN INVENTARIO (OPCIÓN A) -->
                                    <div class="relative group inline-flex items-center justify-center">
                                        <button type="button" 
                                            onclick="openCatalogModal('{{ $dev['ip'] }}', '{{ addslashes($dev['hostname']) }}', '{{ addslashes($dev['mac'] ?? '') }}', '{{ $dev['suggested_type']['key'] ?? 'desktop' }}', '{{ addslashes($dev['suggested_type']['label'] ?? '') }}', '{{ $dev['site_id'] ?? '' }}', '{{ addslashes($dev['vendor'] ?? '') }}')" 
                                            class="w-7 h-7 rounded-lg bg-amber-500/20 hover:bg-amber-500 hover:text-black border border-amber-500/40 text-amber-300 transition inline-flex items-center justify-center shadow-xs cursor-pointer" 
                                            title="Catalogar e incorporar en Inventario">
                                            <span class="material-symbols-outlined text-[16px]">post_add</span>
                                        </button>
                                        <div class="absolute right-0 bottom-full mb-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                                            <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-amber-500/50 shadow-xl shadow-amber-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-xs text-amber-400">post_add</span>
                                                <span class="font-bold text-amber-200">Catalogar en Inventario</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if($dev['is_glpi_supported'])
                                    @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.deploy'))
                                    <!-- BOTÓN INSTALAR AGENTE -->
                                    <div class="relative group inline-flex items-center justify-center">
                                        <button type="button" onclick="openDeployModal('{{ $dev['ip'] }}', '{{ $dev['hostname'] }}', '{{ $dev['os_type'] }}', 'install', '{{ $dev['os_arch'] }}')" class="w-7 h-7 rounded-lg bg-cyan-500/20 hover:bg-cyan-500 hover:text-black border border-cyan-500/40 text-cyan-300 transition inline-flex items-center justify-center shadow-xs cursor-pointer" title="Instalar Agente GLPI">
                                            <span class="material-symbols-outlined text-[16px]">bolt</span>
                                        </button>
                                        <div class="absolute right-0 bottom-full mb-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                                            <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-cyan-500/50 shadow-xl shadow-cyan-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-xs text-cyan-400">bolt</span>
                                                <span class="font-bold text-cyan-200">Instalar Agente GLPI</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- BOTÓN CONMUTAR ENTORNO -->
                                    @if($dev['is_reporting'])
                                    <div class="relative group inline-flex items-center justify-center">
                                        <button type="button" onclick="openDeployModal('{{ $dev['ip'] }}', '{{ $dev['hostname'] }}', '{{ $dev['os_type'] }}', 'switch', '{{ $dev['os_arch'] }}')" class="w-7 h-7 rounded-lg bg-purple-500/20 hover:bg-purple-500 hover:text-white border border-purple-500/40 text-purple-300 transition inline-flex items-center justify-center shadow-xs cursor-pointer" title="Conmutar Servidor GLPI (Dev ⇄ Prod)">
                                            <span class="material-symbols-outlined text-[16px]">swap_horiz</span>
                                        </button>
                                        <div class="absolute right-0 bottom-full mb-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                                            <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-purple-500/50 shadow-xl shadow-purple-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-xs text-purple-400">swap_horiz</span>
                                                <span class="font-bold text-purple-200">Conmutar Servidor</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- BOTÓN FORZAR SONDEO INMEDIATO AL AGENTE EN VIVO -->
                                    <div class="relative group inline-flex items-center justify-center">
                                        <button type="button" onclick="triggerRemoteAgent('{{ $dev['ip'] }}', '{{ $dev['hostname'] }}')" class="w-7 h-7 rounded-lg bg-emerald-500/20 hover:bg-emerald-500 hover:text-black border border-emerald-500/40 text-emerald-300 transition inline-flex items-center justify-center shadow-xs cursor-pointer" title="Forzar Sondeo Inmediato al Agente GLPI">
                                            <span class="material-symbols-outlined text-[16px]">sync</span>
                                        </button>
                                        <div class="absolute right-0 bottom-full mb-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                                            <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-emerald-500/50 shadow-xl shadow-emerald-950/70 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-xs text-emerald-400">sync</span>
                                                <span class="font-bold text-emerald-200">Sondear Agente Ahora</span>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    @endif

                                    <!-- BOTÓN COPIAR 1-LINEA -->
                                    <div class="relative group inline-flex items-center justify-center">
                                        <button type="button" onclick="copyOneLiner('{{ $dev['os_type'] }}', '{{ $dev['ip'] }}')" class="w-7 h-7 rounded-lg bg-obsidian-panel hover:bg-obsidian-border border border-obsidian-border text-obsidian-muted hover:text-white transition inline-flex items-center justify-center shadow-xs cursor-pointer" title="Copiar Comando 1-Clic">
                                            <span class="material-symbols-outlined text-[15px]">content_copy</span>
                                        </button>
                                        <div class="absolute right-0 bottom-full mb-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                                            <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-obsidian-border shadow-xl shadow-cyan-950/40 text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-xs text-cyan-400">terminal</span>
                                                <span class="text-white">Copiar Comando 1-Clic</span>
                                            </div>
                                        </div>
                                    </div>
                                @elseif(!$dev['is_unregistered'])
                                    <div class="relative group inline-flex items-center justify-center">
                                        <span class="w-7 h-7 rounded-lg bg-obsidian-panel/60 border border-obsidian-border/40 text-obsidian-muted/40 inline-flex items-center justify-center cursor-not-allowed" title="Sin Soporte: Dispositivo no compatible con agente GLPI">
                                            <span class="material-symbols-outlined text-[16px] text-obsidian-muted/50">do_not_disturb_on</span>
                                        </span>
                                        <div class="absolute right-0 bottom-full mb-1.5 hidden group-hover:flex flex-col items-end z-50 pointer-events-none transition-all duration-200">
                                            <div class="px-2.5 py-1.5 rounded-lg bg-[#06111f] border border-obsidian-border shadow-xl text-white text-[11px] font-mono whitespace-nowrap flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-xs text-obsidian-muted">do_not_disturb_on</span>
                                                <span class="font-bold text-obsidian-muted">Sin Soporte</span>
                                                <span class="text-[9.5px] text-obsidian-muted/60">(No aplica)</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-obsidian-muted">
                            <span class="material-symbols-outlined text-3xl mb-1 text-obsidian-muted/50">devices_off</span>
                            <div>No se encontraron equipos bajo los criterios de búsqueda.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($paginatedDevices->hasPages())
        <div class="p-3 border-t border-obsidian-border bg-obsidian-panel/40">
            {{ $paginatedDevices->links('vendor.pagination.obsidian') }}
        </div>
        @endif
    </div>
</div>

<!-- ============================================================================== -->
<!-- BARRA FLOTANTE DE ACCIONES POR LOTE (BULK ACTIONS BAR)                         -->
<!-- ============================================================================== -->
<div id="bulk-actions-bar" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-[#06111f]/95 border border-cyan-500/50 backdrop-blur-md rounded-2xl shadow-2xl shadow-cyan-950/80 px-5 py-3 text-white font-mono text-xs flex flex-wrap items-center gap-4 transition-all duration-300">
    <div class="flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-cyan-500 text-black font-bold flex items-center justify-center text-[11px]" id="bulk-selected-count">0</span>
        <span class="font-bold text-cyan-300">Equipos seleccionados</span>
    </div>
    <div class="h-4 w-px bg-obsidian-border"></div>
    <div class="flex items-center gap-2">
        @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
        <!-- BOTÓN CATALOGAR EN LOTE -->
        <button type="button" onclick="openBulkCatalogModal()" class="px-3.5 py-1.5 rounded-xl bg-amber-500/20 hover:bg-amber-500 hover:text-black border border-amber-500/40 text-amber-300 font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
            <span class="material-symbols-outlined text-sm">post_add</span>
            <span>Catalogar en Lote</span>
        </button>
        @endif

        @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.deploy'))
        <!-- BOTÓN DESPLIEGUE GLPI EN LOTE (SECUENCIAL) -->
        <button type="button" onclick="openBulkDeployModal('install')" class="px-3.5 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-bold transition flex items-center gap-1.5 shadow-md shadow-cyan-500/20 cursor-pointer">
            <span class="material-symbols-outlined text-sm">rocket_launch</span>
            <span>Desplegar GLPI en Lote</span>
        </button>

        <!-- BOTÓN CONMUTAR SERVIDOR EN LOTE (DEV ⇄ PROD) -->
        <button type="button" onclick="openBulkDeployModal('switch')" class="px-3.5 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold transition flex items-center gap-1.5 shadow-md shadow-purple-600/20 cursor-pointer">
            <span class="material-symbols-outlined text-sm">swap_horiz</span>
            <span>Conmutar Servidor en Lote</span>
        </button>
        @endif

        <!-- CANCELAR SELECCIÓN -->
        <button type="button" onclick="clearDeviceSelection()" class="px-2.5 py-1.5 rounded-xl text-obsidian-muted hover:text-white border border-transparent hover:border-obsidian-border transition flex items-center gap-1 cursor-pointer">
            <span class="material-symbols-outlined text-sm">close</span>
            <span>Deseleccionar</span>
        </button>
    </div>
</div>

<!-- ============================================================================== -->
<!-- MODAL DE CATALOGACIÓN EN LOTE (BULK CATALOG)                                  -->
<!-- ============================================================================== -->
@if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
<div id="modal-bulk-catalog" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-obsidian-panel border border-amber-500/40 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 flex flex-col max-h-[85vh] sm:max-h-[88vh]">
        <!-- MODAL HEADER -->
        <div class="p-4 bg-obsidian-card border-b border-obsidian-border flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/30">
                    <span class="material-symbols-outlined text-lg">post_add</span>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-white font-mono">Catalogación Masiva en Inventario</h3>
                    <p class="text-[11px] font-mono text-obsidian-muted" id="bulk-catalog-subtitle">Incorporar equipos seleccionados a la base de datos ITAM</p>
                </div>
            </div>
            <button type="button" onclick="closeBulkCatalogModal()" class="text-obsidian-muted hover:text-white transition p-1">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- MODAL BODY FORM -->
        <form id="form-bulk-catalog" onsubmit="executeBulkCatalog(event)" class="flex flex-col flex-1 min-h-0 overflow-hidden font-mono text-xs">
            <div class="p-4 space-y-3 overflow-y-auto flex-1 custom-scroll">
                <!-- CHIPS DE EQUIPOS SELECCIONADOS -->
                <div class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Equipos a procesar:</label>
                    <div id="bulk-catalog-chips" class="p-2 rounded-xl bg-obsidian-card border border-obsidian-border/80 flex flex-wrap gap-1.5 max-h-28 overflow-y-auto">
                        <!-- Dinámico vía JS -->
                    </div>
                </div>

                <!-- CAMPO: TIPO DE DISPOSITIVO -->
                <div class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">
                        Tipo de Dispositivo en Común: <span class="text-amber-400">*</span>
                    </label>
                    <select id="bulk-catalog-type" name="type" required class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-amber-500">
                        <option value="desktop" selected>🖥️ Estación de Trabajo (PC Desktop)</option>
                        <option value="laptop">💻 Computadora Portátil (Laptop)</option>
                        <option value="server">🗄️ Servidor</option>
                        <option value="printer">🖨️ Impresora de Red</option>
                        <option value="switch">🔀 Switch de Red</option>
                        <option value="router">🌐 Router / Gateway</option>
                        <option value="ip_phone">☎️ Teléfono VoIP</option>
                        <option value="access_point">📶 Punto de Acceso (AP)</option>
                        <option value="firewall">🛡️ Firewall</option>
                        <option value="ups">🔋 UPS / Respaldo Eléctrico</option>
                    </select>
                </div>

                <!-- CAMPO: SEDE -->
                <div class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">
                        Sede: <span class="text-amber-400">*</span>
                    </label>
                    <select id="bulk-catalog-site" name="monitored_site_id" required class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-amber-500">
                        @foreach($sedes as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- CAMPO: PREFIJO DE NOMBRE ETIQUETA -->
                <div class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">
                        Prefijo para Nombre Etiqueta (Opcional):
                    </label>
                    <input type="text" id="bulk-catalog-prefix" name="name_prefix" placeholder="ej. PUESTO DE TRABAJO o IMPRESORA" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-amber-500 font-mono">
                    <p class="text-[10px] text-obsidian-muted/80">Si se indica un prefijo, se numerarán: "PREFIJO 01", "PREFIJO 02"... Dejar en blanco para conservar el hostname o IP individual.</p>
                </div>

                <!-- CAMPO: NOTAS / OBSERVACIONES -->
                <div class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Notas / Observaciones del Lote:</label>
                    <textarea id="bulk-catalog-notes" name="notes" rows="2" placeholder="Observaciones generales para los equipos seleccionados..." class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-amber-500 font-mono"></textarea>
                </div>

                <!-- FEEDBACK ALERT -->
                <div id="bulk-catalog-feedback" class="hidden p-3 rounded-lg border text-xs font-mono max-h-44 overflow-y-auto break-words whitespace-pre-wrap select-all"></div>
            </div>

            <!-- MODAL ACTIONS -->
            <div class="p-3.5 bg-obsidian-card/80 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0">
                <button type="button" onclick="closeBulkCatalogModal()" class="px-3.5 py-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-bulk-catalog" class="px-4 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-black font-bold transition flex items-center gap-1.5 shadow-md shadow-amber-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm" id="btn-bulk-catalog-icon">save</span>
                    <span id="btn-bulk-catalog-text">Guardar en Inventario</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- ============================================================================== -->
<!-- ============================================================================== -->
<!-- MODAL DE DESPLIEGUE GLPI EN LOTE (SECUENCIAL / UNO TRAS UNO)                   -->
<!-- ============================================================================== -->
@if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.deploy'))
<div id="modal-bulk-deploy" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-obsidian-panel border border-cyan-500/40 rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 flex flex-col max-h-[85vh] sm:max-h-[88vh]">
        <!-- MODAL HEADER -->
        <div class="p-4 bg-obsidian-card border-b border-obsidian-border flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="p-1.5 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/30">
                    <span class="material-symbols-outlined text-lg" id="bulk-modal-icon">rocket_launch</span>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-white font-mono" id="bulk-modal-title">Despliegue de Agente GLPI por Lote</h3>
                    <p class="text-[11px] font-mono text-obsidian-muted" id="bulk-deploy-subtitle">Ejecución secuencial paso a paso (Tolerancia a fallos)</p>
                </div>
            </div>
            <button type="button" onclick="closeBulkDeployModal()" class="text-obsidian-muted hover:text-white transition p-1 cursor-pointer" title="Cerrar ventana">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- MODAL BODY -->
        <form id="form-bulk-deploy" onsubmit="executeSequentialBulkDeploy(event)" class="flex flex-col flex-1 min-h-0 overflow-hidden font-mono text-xs">
            <div class="p-4 space-y-3 overflow-y-auto flex-1 custom-scroll">
                <!-- CHIPS DE EQUIPOS SELECCIONADOS -->
                <div class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Equipos seleccionados para la operación:</label>
                    <div id="bulk-deploy-chips" class="p-2 rounded-xl bg-obsidian-card border border-obsidian-border/80 flex flex-wrap gap-1.5 max-h-28 overflow-y-auto">
                        <!-- Dinámico vía JS -->
                    </div>
                </div>

                <!-- SELECTOR DE TIPO DE OPERACIÓN (INSTALAR VS CONMUTAR) -->
                <div class="space-y-1.5">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Tipo de Operación:</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label id="bulk-action-install-label" class="p-2.5 rounded-xl border border-cyan-500/40 bg-cyan-950/20 cursor-pointer flex items-center gap-2 text-white hover:bg-cyan-950/40 transition">
                            <input type="radio" name="bulk_action" value="install" checked onchange="onBulkActionChanged(this.value)" class="text-cyan-500 focus:ring-0">
                            <div>
                                <div class="font-bold text-cyan-300 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">rocket_launch</span>
                                    <span>Instalar Agente</span>
                                </div>
                                <div class="text-[10px] text-obsidian-muted">Despliegue nuevo de cero</div>
                            </div>
                        </label>

                        <label id="bulk-action-switch-label" class="p-2.5 rounded-xl border border-purple-500/40 bg-purple-950/20 cursor-pointer flex items-center gap-2 text-white hover:bg-purple-950/40 transition">
                            <input type="radio" name="bulk_action" value="switch" onchange="onBulkActionChanged(this.value)" class="text-purple-500 focus:ring-0">
                            <div>
                                <div class="font-bold text-purple-300 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">swap_horiz</span>
                                    <span>Conmutar Servidor</span>
                                </div>
                                <div class="text-[10px] text-obsidian-muted">Migración rápida (Dev ⇄ Prod)</div>
                            </div>
                        </label>
                    </div>
                    <div id="bulk-action-hint" class="hidden text-[10.5px] font-mono text-purple-300/90 p-2.5 rounded-xl bg-purple-950/40 border border-purple-500/40 space-y-1">
                        <div class="flex items-center gap-1 font-bold text-purple-200">
                            <span class="material-symbols-outlined text-xs text-purple-400">info</span>
                            <span>Migración Rápida Sin Reinstalar:</span>
                        </div>
                        <p class="text-[10px] text-purple-300/80">
                            Modifica únicamente la directiva del servidor en la configuración local de los equipos seleccionados, reinicia el servicio y fuerza el inventario al instante (~2 a 4 seg por equipo).
                        </p>
                    </div>
                </div>

                <!-- SELECTOR DE SERVIDOR OBJETIVO -->
                <div class="space-y-1.5">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Servidor Destino GLPI:</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="p-2.5 rounded-xl border border-cyan-500/40 bg-cyan-950/20 cursor-pointer flex items-center gap-2 text-white hover:bg-cyan-950/40 transition">
                            <input type="radio" name="bulk_target" value="dev" checked class="text-cyan-500 focus:ring-0">
                            <div>
                                <div class="font-bold text-cyan-300">Desarrollo (Pruebas)</div>
                                <div class="text-[10px] text-obsidian-muted">10.20.23.221</div>
                            </div>
                        </label>

                        <label class="p-2.5 rounded-xl border border-obsidian-border bg-obsidian-card cursor-pointer flex items-center gap-2 text-white hover:border-red-500/40 transition">
                            <input type="radio" name="bulk_target" value="prod" class="text-red-500 focus:ring-0">
                            <div>
                                <div class="font-bold text-red-300">Producción</div>
                                <div class="text-[10px] text-obsidian-muted">10.20.23.252</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- SELECTOR DE S.O. PREDOMINANTE -->
                <div class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Sistema Operativo de los Equipos:</label>
                    <select id="bulk-deploy-os" name="bulk_os" onchange="toggleBulkDomainField()" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-500">
                        <option value="windows" selected>Windows (32 o 64 bits - WinRM / Dominio)</option>
                        <option value="linux">Linux / Xubuntu (SSH con Sudo)</option>
                    </select>
                </div>

                <!-- CREDENCIALES ADMINISTRATIVAS -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div class="space-y-1">
                        <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Usuario Administrativo:</label>
                        <input type="text" id="bulk-deploy-user" name="bulk_user" required placeholder="ej. administrador o admin" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-500">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Contraseña:</label>
                        <input type="password" id="bulk-deploy-pass" name="bulk_pass" placeholder="Contraseña de red" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-500">
                    </div>
                </div>

                <!-- CAMPO DOMINIO (SÓLO WINDOWS) -->
                <div id="bulk-field-domain" class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Dominio Active Directory (Opcional):</label>
                    <input type="text" id="bulk-deploy-domain" name="bulk_domain" placeholder="ej. MIDOMINIO (dejar vacío si es admin local)" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-500">
                </div>

                <!-- PROGRESO Y STATUS DEL LOTE (SECUENCIAL) -->
                <div id="bulk-deploy-progress-container" class="hidden space-y-2 p-3 rounded-xl bg-obsidian-card border border-obsidian-border">
                    <div class="flex items-center justify-between text-[11px]">
                        <span id="bulk-progress-status" class="text-cyan-300 font-bold">Iniciando despliegue secuencial...</span>
                        <span id="bulk-progress-percent" class="text-white font-bold">0%</span>
                    </div>
                    <!-- Barra de Progreso -->
                    <div class="w-full h-2 rounded-full bg-black/60 overflow-hidden border border-obsidian-border/50">
                        <div id="bulk-progress-bar" class="h-full bg-gradient-to-r from-cyan-500 to-emerald-500 transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <!-- Contador de Resultados -->
                    <div class="flex items-center gap-3 text-[10px] text-obsidian-muted pt-1">
                        <span class="flex items-center gap-1 text-emerald-400 font-bold">
                            <span class="material-symbols-outlined text-xs">check_circle</span>
                            Exitosos: <span id="bulk-success-count">0</span>
                        </span>
                        <span class="flex items-center gap-1 text-red-400 font-bold">
                            <span class="material-symbols-outlined text-xs">cancel</span>
                            Fallidos: <span id="bulk-failed-count">0</span>
                        </span>
                        <span class="flex items-center gap-1 text-cyan-300">
                            <span class="material-symbols-outlined text-xs">hourglass_top</span>
                            Pendientes: <span id="bulk-pending-count">0</span>
                        </span>
                    </div>
                </div>

                <!-- LIVE STATUS FEEDBACK -->
                <div id="bulk-deploy-feedback" class="hidden p-3 rounded-lg border text-xs font-mono max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all"></div>

                <!-- TERMINAL LOG OUTPUT -->
                <div id="bulk-deploy-terminal" class="hidden rounded-lg bg-black border border-obsidian-border p-2.5 max-h-40 overflow-y-auto text-[11px] font-mono whitespace-pre-wrap"></div>
            </div>

            <!-- MODAL ACTIONS -->
            <div class="p-3.5 bg-obsidian-card/80 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0">
                <button type="button" onclick="closeBulkDeployModal()" id="btn-cancel-bulk-deploy" class="px-3.5 py-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition">
                    Cerrar
                </button>
                <button type="submit" id="btn-submit-bulk-deploy" class="px-4 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-bold transition flex items-center gap-1.5 shadow-md shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm" id="btn-bulk-deploy-icon">rocket_launch</span>
                    <span id="btn-bulk-deploy-text">Iniciar Despliegue Secuencial</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- ============================================================================== -->
<!-- MODAL DE DESPLIEGUE REMOTO PUSH & CONMUTACIÓN (AJAX)                          -->
<!-- ============================================================================== -->
@if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.deploy'))
<div id="modal-deploy" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-obsidian-panel border border-cyan-500/40 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 flex flex-col max-h-[85vh] sm:max-h-[88vh]">
        <!-- MODAL HEADER -->
        <div class="p-4 bg-obsidian-card border-b border-obsidian-border flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="p-1.5 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/30">
                    <span class="material-symbols-outlined text-lg" id="modal-action-icon">bolt</span>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-white font-mono" id="modal-title">Desplegar Agente GLPI</h3>
                    <p class="text-[11px] font-mono text-obsidian-muted" id="modal-subtitle">Equipo seleccionado: 10.20.23.X</p>
                </div>
            </div>
            <button onclick="closeDeployModal()" class="text-obsidian-muted hover:text-white transition p-1">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- MODAL BODY FORM -->
        <form id="form-deploy-action" onsubmit="executeRemoteDeploy(event)" class="flex flex-col flex-1 min-h-0 overflow-hidden font-mono text-xs">
            <div class="p-4 space-y-3 overflow-y-auto flex-1 custom-scroll">
                <input type="hidden" id="deploy-host" name="host">
                <input type="hidden" id="deploy-action" name="action" value="install">

                <!-- SELECTOR DE SERVIDOR OBJETIVO -->
                <div class="space-y-1.5">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Servidor Destino GLPI:</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="p-2.5 rounded-xl border border-cyan-500/40 bg-cyan-950/20 cursor-pointer flex items-center gap-2 text-white hover:bg-cyan-950/40 transition">
                            <input type="radio" name="target" value="dev" checked class="text-cyan-500 focus:ring-0">
                            <div>
                                <div class="font-bold text-cyan-300">Desarrollo (Pruebas)</div>
                                <div class="text-[10px] text-obsidian-muted">10.20.23.221</div>
                            </div>
                        </label>

                        <label class="p-2.5 rounded-xl border border-obsidian-border bg-obsidian-card cursor-pointer flex items-center gap-2 text-white hover:border-red-500/40 transition">
                            <input type="radio" name="target" value="prod" class="text-red-500 focus:ring-0">
                            <div>
                                <div class="font-bold text-red-300">Producción</div>
                                <div class="text-[10px] text-obsidian-muted">10.20.23.252</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- SELECTOR DE S.O. -->
                <div class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Sistema Operativo:</label>
                    <select id="deploy-os" name="os" onchange="toggleDomainField()" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-500">
                        <option value="windows">Windows (32 o 64 bits - WinRM / Dominio)</option>
                        <option value="linux">Linux / Xubuntu (SSH con Sudo)</option>
                    </select>
                </div>

                <!-- CREDENCIALES ADMINISTRATIVAS -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div class="space-y-1">
                        <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Usuario Administrativo:</label>
                        <input type="text" id="deploy-user" name="user" required placeholder="ej. administrador o admin" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-500">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Contraseña:</label>
                        <input type="password" id="deploy-pass" name="password" placeholder="Contraseña de red" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-500">
                    </div>
                </div>

                <!-- CAMPO DOMINIO (SÓLO WINDOWS) -->
                <div id="field-domain" class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Dominio Active Directory (Opcional):</label>
                    <input type="text" id="deploy-domain" name="domain" placeholder="ej. MIDOMINIO (dejar vacío si es admin local)" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-500">
                </div>

                <!-- LIVE STATUS FEEDBACK -->
                <div id="deploy-feedback" class="hidden p-3 rounded-lg border text-xs font-mono max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all"></div>

                <!-- TERMINAL LOG OUTPUT -->
                <div id="deploy-terminal" class="hidden rounded-lg bg-black border border-obsidian-border p-2.5 max-h-36 overflow-y-auto text-[11px] text-green-400 font-mono whitespace-pre-wrap"></div>
            </div>

            <!-- MODAL ACTIONS -->
            <div class="p-3.5 bg-obsidian-card/80 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0">
                <button type="button" onclick="closeDeployModal()" class="px-3.5 py-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-deploy" class="px-4 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-bold transition flex items-center gap-1.5 shadow-md shadow-cyan-500/20">
                    <span class="material-symbols-outlined text-sm" id="btn-deploy-icon">rocket_launch</span>
                    <span id="btn-deploy-text">Lanzar Despliegue</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- ============================================================================== -->
<!-- MODAL DE COMANDOS 1-CLIC (MANUAL)                                              -->
<!-- ============================================================================== -->
<!-- MODAL GUÍA DE DESPLIEGUE GLPI (COMANDOS 1-CLIC & INSTALACIÓN MANUAL)          -->
<!-- ============================================================================== -->
<div id="modal-oneliner" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-obsidian-panel border border-cyan-500/40 rounded-2xl w-full max-w-2xl sm:max-w-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 flex flex-col max-h-[85vh] sm:max-h-[88vh]">
        <!-- MODAL HEADER -->
        <div class="p-4 bg-obsidian-card border-b border-obsidian-border flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30">
                    <span class="material-symbols-outlined text-xl">menu_book</span>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-white font-mono flex items-center gap-2">
                        <span>Guía de Instalación y Despliegue GLPI</span>
                    </h3>
                    <p class="text-[11px] font-mono text-obsidian-muted">Comandos 1-Clic, despliegue manual paso a paso y parámetros de configuración</p>
                </div>
            </div>
            <button onclick="closeOneLinerModal()" class="text-obsidian-muted hover:text-white transition p-1 cursor-pointer">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- SELECTOR DE PESTAÑAS DEL MODAL -->
        <div class="flex items-center gap-1.5 px-4 pt-3 pb-2 border-b border-obsidian-border bg-obsidian-panel/90 shrink-0 font-mono text-xs">
            <button type="button" id="tab-btn-oneliner" onclick="switchDeployGuideTab('oneliner')" class="px-3 py-1.5 rounded-lg font-bold transition flex items-center gap-1.5 cursor-pointer bg-cyan-500 text-black shadow-xs">
                <span class="material-symbols-outlined text-sm">terminal</span>
                <span>Comandos 1-Clic</span>
            </button>
            <button type="button" id="tab-btn-manual" onclick="switchDeployGuideTab('manual')" class="px-3 py-1.5 rounded-lg font-bold transition flex items-center gap-1.5 cursor-pointer text-obsidian-muted hover:text-white hover:bg-obsidian-card border border-transparent">
                <span class="material-symbols-outlined text-sm">build_circle</span>
                <span>Instalación Manual</span>
            </button>
            <button type="button" id="tab-btn-params" onclick="switchDeployGuideTab('params')" class="px-3 py-1.5 rounded-lg font-bold transition flex items-center gap-1.5 cursor-pointer text-obsidian-muted hover:text-white hover:bg-obsidian-card border border-transparent">
                <span class="material-symbols-outlined text-sm">tune</span>
                <span>Servidores &amp; Token</span>
            </button>
        </div>

        <!-- CUERPO DEL MODAL (SCROLLABLE) -->
        <div class="p-4 space-y-4 font-mono text-xs overflow-y-auto flex-1 custom-scroll">
            
            <!-- PESTAÑA 1: COMANDOS 1-CLIC -->
            <div id="guide-tab-content-oneliner" class="space-y-4">
                <div class="p-3 rounded-xl bg-cyan-950/20 border border-cyan-500/30 text-cyan-200 text-[11px] flex items-start gap-2">
                    <span class="material-symbols-outlined text-cyan-400 text-base shrink-0">info</span>
                    <span>Los comandos 1-Clic se ejecutan directamente en la consola del equipo objetivo. Descargan el agente, configuran el servidor y envían el primer reporte de inventario de forma desatendida.</span>
                </div>

                <!-- WINDOWS 1-LINER -->
                <div class="p-3.5 rounded-xl bg-obsidian-card border border-blue-500/30 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-blue-400 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base">desktop_windows</span>
                            Windows (PowerShell como Administrador):
                        </span>
                        <button type="button" onclick="copyToClipboard('(New-Object System.Net.WebClient).DownloadString(\'http://10.20.23.221/deploy/win\') | iex')" class="px-2 py-1 rounded bg-blue-500/20 hover:bg-blue-500 hover:text-black text-blue-300 transition text-[11px] flex items-center gap-1 cursor-pointer">
                            <span class="material-symbols-outlined text-xs">content_copy</span>
                            Copiar
                        </button>
                    </div>
                    <div class="p-2.5 rounded-lg bg-black/90 border border-obsidian-border text-cyan-300 select-all break-all text-[11.5px]">
                        (New-Object System.Net.WebClient).DownloadString('http://10.20.23.221/deploy/win') | iex
                    </div>
                    <div class="text-[10px] text-obsidian-muted flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs text-blue-400">check</span>
                        Compatible con Windows 7 SP1 (PowerShell 2.0), Windows 8, 10 y 11. Detecta automáticamente arquitectura 32 y 64 bits.
                    </div>
                </div>

                <!-- LINUX 1-LINER -->
                <div class="p-3.5 rounded-xl bg-obsidian-card border border-emerald-500/30 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-emerald-400 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base">terminal</span>
                            Linux / Xubuntu / Debian (Terminal con sudo):
                        </span>
                        <button type="button" onclick="copyToClipboard('(which curl >/dev/null 2>&1 &amp;&amp; curl -sSL http://10.20.23.221/deploy/linux || wget -qO- http://10.20.23.221/deploy/linux) | sudo bash')" class="px-2 py-1 rounded bg-emerald-500/20 hover:bg-emerald-500 hover:text-black text-emerald-300 transition text-[11px] flex items-center gap-1 cursor-pointer">
                            <span class="material-symbols-outlined text-xs">content_copy</span>
                            Copiar
                        </button>
                    </div>
                    <div class="p-2.5 rounded-lg bg-black/90 border border-obsidian-border text-emerald-300 select-all break-all text-[11.5px]">
                        (which curl &gt;/dev/null 2&gt;&amp;1 &amp;&amp; curl -sSL http://10.20.23.221/deploy/linux || wget -qO- http://10.20.23.221/deploy/linux) | sudo bash
                    </div>
                    <div class="text-[10px] text-obsidian-muted flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs text-emerald-400">check</span>
                        Detección universal: Funciona con curl o wget. Instala el paquete .deb, dependencias de Perl y habilita el servicio.
                    </div>
                </div>

                <!-- CONMUTACIÓN RÁPIDA A PRODUCCIÓN (DEV ⇄ PROD) -->
                <div class="p-3.5 rounded-xl bg-purple-950/20 border border-purple-500/30 space-y-2.5">
                    <div class="flex items-center gap-1.5 text-purple-300 font-bold">
                        <span class="material-symbols-outlined text-base">swap_horiz</span>
                        <span>Conmutación Rápida a Producción (Sin Reinstalar):</span>
                    </div>
                    <p class="text-[10.5px] text-obsidian-muted">Si el equipo ya tiene el agente instalado y deseas cambiar su destino a Producción (10.20.23.252):</p>
                    
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-blue-300 font-bold">Windows (PowerShell Admin):</span>
                            <button type="button" onclick="copyToClipboard('(New-Object System.Net.WebClient).DownloadString(\'http://10.20.23.221/deploy/switch/win\') | iex')" class="text-purple-300 hover:underline flex items-center gap-0.5 cursor-pointer">
                                <span class="material-symbols-outlined text-xs">content_copy</span> Copiar
                            </button>
                        </div>
                        <div class="p-2 rounded bg-black/90 border border-obsidian-border text-purple-200 select-all break-all text-[11px]">
                            (New-Object System.Net.WebClient).DownloadString('http://10.20.23.221/deploy/switch/win') | iex
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-emerald-300 font-bold">Linux (Terminal con sudo):</span>
                            <button type="button" onclick="copyToClipboard('(which curl >/dev/null 2>&1 &amp;&amp; curl -sSL http://10.20.23.221/deploy/switch/linux || wget -qO- http://10.20.23.221/deploy/switch/linux) | sudo bash -s prod')" class="text-purple-300 hover:underline flex items-center gap-0.5 cursor-pointer">
                                <span class="material-symbols-outlined text-xs">content_copy</span> Copiar
                            </button>
                        </div>
                        <div class="p-2 rounded bg-black/90 border border-obsidian-border text-purple-200 select-all break-all text-[11px]">
                            (which curl &gt;/dev/null 2&gt;&amp;1 &amp;&amp; curl -sSL http://10.20.23.221/deploy/switch/linux || wget -qO- http://10.20.23.221/deploy/switch/linux) | sudo bash -s prod
                        </div>
                    </div>
                </div>
            </div>

            <!-- PESTAÑA 2: INSTALACIÓN MANUAL PASO A PASO -->
            <div id="guide-tab-content-manual" class="hidden space-y-5">
                <div class="p-3 rounded-xl bg-amber-950/20 border border-amber-500/30 text-amber-200 text-[11px] flex items-start gap-2">
                    <span class="material-symbols-outlined text-amber-400 text-base shrink-0">handyman</span>
                    <span>Utilice esta sección para puestos con políticas estrictas de seguridad, equipos sin salida web directa o que requieran instalación guiada mediante paquetes instaladores locales.</span>
                </div>

                <!-- MANUAL WINDOWS -->
                <div class="p-4 rounded-xl bg-obsidian-card border border-blue-500/30 space-y-3">
                    <div class="flex items-center justify-between border-b border-obsidian-border pb-2">
                        <span class="font-bold text-blue-400 flex items-center gap-1.5 text-sm">
                            <span class="material-symbols-outlined">desktop_windows</span>
                            Procedimiento Manual para Windows
                        </span>
                        <div class="flex items-center gap-2">
                            <a href="http://10.20.23.221/instaladores/GLPI-Agent-x86.msi" download class="px-2 py-1 rounded bg-blue-500/20 hover:bg-blue-500 hover:text-black text-blue-300 transition text-[10px] font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs">download</span> MSI 32-bit (x86)
                            </a>
                            <a href="http://10.20.23.221/instaladores/GLPI-Agent-x64.msi" download class="px-2 py-1 rounded bg-blue-500/20 hover:bg-blue-500 hover:text-black text-blue-300 transition text-[10px] font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs">download</span> MSI 64-bit (x64)
                            </a>
                        </div>
                    </div>

                    <div class="space-y-2 text-[11px]">
                        <p class="text-white font-bold">1. Instalación desatendida desde Símbolo del Sistema (cmd como Administrador):</p>
                        <div class="p-2.5 rounded-lg bg-black/90 border border-obsidian-border text-cyan-300 select-all break-all">
                            msiexec.exe /i GLPI-Agent-x86.msi /quiet /qn SERVER="http://10.20.23.221/api/v1/inventory/receiver/ValleSeco_GLPI_Token_2026_Secure" ADD_FIREWALL_EXCEPTION=1 RUNNOW=1 TAG="ValleSeco-ITAM"
                        </div>
                        <p class="text-[10px] text-obsidian-muted">* Sustituya GLPI-Agent-x86.msi por GLPI-Agent-x64.msi en sistemas de 64 bits.</p>

                        <p class="text-white font-bold pt-1">2. Si ya está instalado y solo desea cambiar el servidor destino:</p>
                        <p class="text-obsidian-muted">En el Registro de Windows (<code class="text-amber-300">regedit</code>):</p>
                        <div class="p-2 rounded bg-black/90 border border-obsidian-border text-amber-200 text-[10.5px]">
                            Ruta: HKLM\SOFTWARE\GLPI-Agent (o WOW6432Node)<br>
                            Valor de cadena 'server' = http://10.20.23.221/api/v1/inventory/receiver/ValleSeco_GLPI_Token_2026_Secure
                        </div>
                        <p class="text-obsidian-muted">Reiniciar el servicio en cmd: <code class="text-cyan-300">net stop glpi-agent &amp;&amp; net start glpi-agent</code></p>

                        <p class="text-white font-bold pt-1">3. Forzar el envío inmediato de inventario:</p>
                        <div class="p-2 rounded bg-black/90 border border-obsidian-border text-emerald-300 text-[10.5px]">
                            "C:\Program Files\GLPI-Agent\glpi-agent.bat" --force
                        </div>
                        <p class="text-[10px] text-obsidian-muted">O bien abrir en el navegador local del PC: <a href="http://127.0.0.1:62354/now" target="_blank" class="text-cyan-400 hover:underline">http://127.0.0.1:62354/now</a></p>
                    </div>
                </div>

                <!-- MANUAL LINUX -->
                <div class="p-4 rounded-xl bg-obsidian-card border border-emerald-500/30 space-y-3">
                    <div class="flex items-center justify-between border-b border-obsidian-border pb-2">
                        <span class="font-bold text-emerald-400 flex items-center gap-1.5 text-sm">
                            <span class="material-symbols-outlined">terminal</span>
                            Procedimiento Manual para Linux / Xubuntu
                        </span>
                        <a href="http://10.20.23.221/instaladores/glpi-agent_all.deb" download class="px-2.5 py-1 rounded bg-emerald-500/20 hover:bg-emerald-500 hover:text-black text-emerald-300 transition text-[10px] font-bold flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">download</span> Paquete DEB
                        </a>
                    </div>

                    <div class="space-y-2 text-[11px]">
                        <p class="text-white font-bold">1. Descargar paquete .deb desde el repositorio local:</p>
                        <div class="p-2.5 rounded-lg bg-black/90 border border-obsidian-border text-emerald-300 select-all break-all">
                            wget http://10.20.23.221/instaladores/glpi-agent_all.deb
                        </div>

                        <p class="text-white font-bold pt-1">2. Instalar el paquete y resolver dependencias de Perl:</p>
                        <div class="p-2.5 rounded-lg bg-black/90 border border-obsidian-border text-emerald-300 select-all break-all">
                            sudo dpkg -i glpi-agent_all.deb || sudo apt-get install -f -y
                        </div>

                        <p class="text-white font-bold pt-1">3. Configurar el archivo <code class="text-cyan-300">/etc/glpi-agent/agent.cfg</code>:</p>
                        <div class="p-2.5 rounded-lg bg-black/90 border border-obsidian-border text-cyan-200 text-[10.5px]">
                            server = http://10.20.23.221/api/v1/inventory/receiver/ValleSeco_GLPI_Token_2026_Secure<br>
                            tag = ValleSeco-ITAM
                        </div>

                        <p class="text-white font-bold pt-1">4. Reiniciar servicio y forzar reporte:</p>
                        <div class="p-2.5 rounded-lg bg-black/90 border border-obsidian-border text-emerald-300 select-all break-all">
                            sudo systemctl restart glpi-agent &amp;&amp; sudo glpi-agent --force
                        </div>
                    </div>
                </div>
            </div>

            <!-- PESTAÑA 3: PARÁMETROS & SERVIDORES -->
            <div id="guide-tab-content-params" class="hidden space-y-4">
                <div class="p-3 rounded-xl bg-purple-950/20 border border-purple-500/30 text-purple-200 text-[11px] flex items-start gap-2">
                    <span class="material-symbols-outlined text-purple-400 text-base shrink-0">dns</span>
                    <span>Parámetros de conectividad autoritativos del concentrador MariaDB para entornos de Desarrollo y Producción.</span>
                </div>

                <div class="rounded-xl border border-obsidian-border overflow-hidden bg-obsidian-card">
                    <table class="w-full text-left text-[11.5px] font-mono">
                        <thead class="bg-black/60 text-obsidian-muted uppercase text-[10px] border-b border-obsidian-border">
                            <tr>
                                <th class="p-3">Parámetro</th>
                                <th class="p-3">Entorno Desarrollo</th>
                                <th class="p-3">Entorno Producción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-obsidian-border/60">
                            <tr>
                                <td class="p-3 text-obsidian-muted">IP Central</td>
                                <td class="p-3 font-bold text-cyan-300">10.20.23.221</td>
                                <td class="p-3 font-bold text-purple-300">10.20.23.252</td>
                            </tr>
                            <tr>
                                <td class="p-3 text-obsidian-muted">Endpoint Receptor GLPI</td>
                                <td class="p-3 text-cyan-300 select-all break-all text-[10.5px]">/api/v1/inventory/receiver/ValleSeco_GLPI_Token_2026_Secure</td>
                                <td class="p-3 text-purple-300 select-all break-all text-[10.5px]">/api/v1/inventory/receiver/ValleSeco_GLPI_Token_2026_Secure</td>
                            </tr>
                            <tr>
                                <td class="p-3 text-obsidian-muted">Repositorio Instaladores</td>
                                <td class="p-3 text-cyan-300 select-all text-[10.5px]">http://10.20.23.221/instaladores</td>
                                <td class="p-3 text-purple-300 select-all text-[10.5px]">http://10.20.23.252/instaladores</td>
                            </tr>
                            <tr>
                                <td class="p-3 text-obsidian-muted">Token de Autorización</td>
                                <td class="p-3 text-amber-300 font-bold select-all" colspan="2">
                                    <div class="flex items-center gap-2">
                                        <span>ValleSeco_GLPI_Token_2026_Secure</span>
                                        <button type="button" onclick="copyToClipboard('ValleSeco_GLPI_Token_2026_Secure')" class="text-cyan-400 hover:underline text-[10px] flex items-center gap-0.5 cursor-pointer">
                                            <span class="material-symbols-outlined text-xs">content_copy</span> Copiar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="p-3 text-obsidian-muted">Etiqueta de Inventario (TAG)</td>
                                <td class="p-3 text-emerald-300 font-bold" colspan="2">ValleSeco-ITAM</td>
                            </tr>
                            <tr>
                                <td class="p-3 text-obsidian-muted">Puerto Local del Agente</td>
                                <td class="p-3 text-white" colspan="2">62354 (Disparador HTTP local: <code class="text-cyan-300">http://127.0.0.1:62354/now</code>)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- MODAL FOOTER -->
        <div class="p-3.5 bg-obsidian-card/80 border-t border-obsidian-border flex items-center justify-between shrink-0 font-mono text-xs">
            <div id="copy-toast-msg" class="hidden text-emerald-400 font-bold flex items-center gap-1 text-[11px] animate-pulse">
                <span class="material-symbols-outlined text-sm">check_circle</span>
                <span>¡Copiado al portapapeles!</span>
            </div>
            <div class="ml-auto">
                <button type="button" onclick="closeOneLinerModal()" class="px-4 py-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white transition cursor-pointer">
                    Cerrar Guía
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================================== -->
<!-- MODAL DE ESCANEO DE SUBRED BAJO DEMANDA                                        -->
<!-- ============================================================================== -->
@if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.deploy'))
<div id="modal-scan-subnet" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-obsidian-panel border border-cyan-500/40 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 flex flex-col max-h-[85vh] sm:max-h-[88vh]">
        <!-- MODAL HEADER -->
        <div class="p-4 bg-obsidian-card border-b border-obsidian-border flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="p-1.5 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/30">
                    <span class="material-symbols-outlined text-lg">radar</span>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-white font-mono">Escanear Segmento de Red</h3>
                    <p class="text-[10px] text-obsidian-muted font-mono">Sondear equipos activos en subred bajo demanda</p>
                </div>
            </div>
            <button type="button" onclick="closeScanSubnetModal()" class="text-obsidian-muted hover:text-white p-1 rounded-lg hover:bg-obsidian-panel transition">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- MODAL BODY -->
        <form id="form-scan-subnet" onsubmit="executeSubnetScan(event)" class="flex flex-col flex-1 min-h-0 overflow-hidden font-mono text-xs">
            <div class="p-5 space-y-4 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-[11px] text-obsidian-muted mb-1.5 font-bold uppercase tracking-wider">
                        Opción 1: Seleccionar Sede
                    </label>
                    <select id="scan-site-selector" onchange="onScanSiteChanged(this.value)" class="w-full px-3 py-2 rounded-xl bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-cyan-500">
                        <option value="">-- Elige una Sede para autocompletar su subred --</option>
                        @foreach($sedes as $s)
                            <option value="{{ $siteSubnets[$s->id] ?? '' }}">
                                📍 {{ $s->name }} ({{ $siteSubnets[$s->id] ?? 'Segmento manual' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="relative flex items-center py-1">
                    <div class="flex-grow border-t border-obsidian-border/60"></div>
                    <span class="flex-shrink mx-3 text-[10px] text-obsidian-muted uppercase font-bold">O ingresar segmento manual</span>
                    <div class="flex-grow border-t border-obsidian-border/60"></div>
                </div>

                <div>
                    <label class="block text-[11px] text-obsidian-muted mb-1.5 font-bold uppercase tracking-wider">
                        Segmento CIDR a Escanear
                    </label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-2.5 text-obsidian-muted text-base">lan</span>
                        <input type="text" id="scan-subnet-input" name="subnet" required placeholder="ej: 10.20.106.0/24" value="10.20.23.0/24" class="w-full pl-9 pr-3 py-2 rounded-xl bg-obsidian-card border border-obsidian-border text-cyan-300 font-mono text-xs focus:outline-none focus:border-cyan-500">
                    </div>
                    <p class="text-[10px] text-obsidian-muted/70 mt-1">Formato CIDR: /24 hasta /30 (ej. 10.20.23.0/24, 10.20.106.0/24, 10.20.107.0/24).</p>
                </div>

                <!-- SPINNER DE CARGA -->
                <div id="scan-loading-indicator" class="hidden p-4 rounded-xl bg-cyan-950/20 border border-cyan-500/30 text-center space-y-2">
                    <div class="inline-flex items-center gap-2 text-cyan-300 font-bold text-xs">
                        <span class="material-symbols-outlined text-lg animate-spin">progress_activity</span>
                        <span>Escaneando segmento de red en tiempo real...</span>
                    </div>
                    <p class="text-[10px] text-obsidian-muted">Sondeando hosts activos mediante ARP/ICMP. Esto toma unos segundos.</p>
                </div>

                <div id="scan-feedback-alert" class="hidden p-3 rounded-xl text-xs font-mono max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all"></div>
            </div>

            <!-- MODAL FOOTER -->
            <div class="p-3.5 bg-obsidian-card/80 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0">
                <button type="button" onclick="closeScanSubnetModal()" class="px-3 py-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition text-xs">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-scan" class="px-4 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-bold transition flex items-center gap-1.5 text-xs shadow-md shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">radar</span>
                    <span>Iniciar Escaneo</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- ============================================================================== -->
<!-- MODAL DE CATALOGAR EN INVENTARIO (OPCIÓN A - ALTA RÁPIDA ITAM / RED)          -->
<!-- ============================================================================== -->
@if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
<div id="modal-catalog-asset" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-obsidian-panel border border-amber-500/40 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 flex flex-col max-h-[85vh] sm:max-h-[88vh]">
        <!-- MODAL HEADER -->
        <div class="p-4 bg-obsidian-card border-b border-obsidian-border flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/30">
                    <span class="material-symbols-outlined text-lg">post_add</span>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-white font-mono">Catalogar en Inventario ITAM</h3>
                    <p class="text-[11px] font-mono text-obsidian-muted">Incorporar hallazgo de red a la base de datos ITAM</p>
                </div>
            </div>
            <button type="button" onclick="closeCatalogModal()" class="text-obsidian-muted hover:text-white transition p-1">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- MODAL BODY FORM -->
        <form id="form-catalog-asset" onsubmit="executeCatalogAsset(event)" class="flex flex-col flex-1 min-h-0 overflow-hidden font-mono text-xs">
            <div class="p-4 space-y-3 overflow-y-auto flex-1 custom-scroll">
                <input type="hidden" id="catalog-ip" name="ip">
                <input type="hidden" id="catalog-mac" name="mac">

                <!-- RESUMEN DE IDENTIDAD DE RED -->
                <div class="p-2.5 rounded-xl bg-obsidian-card border border-obsidian-border/80 flex items-center justify-between text-[11px]">
                    <div class="space-y-0.5">
                        <span class="text-obsidian-muted block text-[10px] uppercase font-bold">Dirección IP</span>
                        <span id="catalog-display-ip" class="text-amber-300 font-bold font-mono"></span>
                    </div>
                    <div class="space-y-0.5">
                        <span class="text-obsidian-muted block text-[10px] uppercase font-bold">Dirección MAC</span>
                        <span id="catalog-display-mac" class="text-slate-300 font-mono"></span>
                    </div>
                    <div class="space-y-0.5">
                        <span class="text-obsidian-muted block text-[10px] uppercase font-bold">Fabricante</span>
                        <span id="catalog-display-vendor" class="text-slate-300"></span>
                    </div>
                </div>

                <!-- CAMPO: NOMBRE ETIQUETA CORPORATIVO (SSOT REGLA #7) -->
                <div class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">
                        Nombre Etiqueta (Puesto / Rol Operativo): <span class="text-amber-400">*</span>
                    </label>
                    <input type="text" id="catalog-name" name="name" required placeholder="ej. IMPRESORA DEPARTAMENTO DE ARCHIVO o TAQUILLA 01" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-amber-500 font-mono">
                    <p class="text-[10px] text-obsidian-muted/80">Identidad principal obligatoria según la Regla de Oro #7 (Rol o función del equipo).</p>
                </div>

                <!-- CAMPO: TIPO DE DISPOSITIVO -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="block text-obsidian-muted text-[11px] uppercase font-bold">
                            Tipo de Dispositivo: <span class="text-amber-400">*</span>
                        </label>
                        <div id="catalog-type-hint" class="text-[10px] text-amber-300/80">
                            Sugerencia: <span id="catalog-suggested-text" class="font-bold text-amber-300"></span>
                        </div>
                    </div>
                    <select id="catalog-type" name="type" required onchange="updateCatalogBtnText()" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-amber-500">
                        <option value="printer">🖨️ Impresora de Red</option>
                        <option value="switch">🔀 Switch de Red</option>
                        <option value="router">🌐 Router / Gateway</option>
                        <option value="ip_phone">☎️ Teléfono VoIP</option>
                        <option value="desktop">🖥️ Estación de Trabajo (PC Desktop)</option>
                        <option value="laptop">💻 Computadora Portátil (Laptop)</option>
                        <option value="server">🗄️ Servidor</option>
                        <option value="access_point">📶 Punto de Acceso (AP)</option>
                        <option value="firewall">🛡️ Firewall</option>
                        <option value="ups">🔋 UPS / Respaldo Eléctrico</option>
                    </select>
                </div>

                <!-- CAMPO: SEDE -->
                <div class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">
                        Sede: <span class="text-amber-400">*</span>
                    </label>
                    <select id="catalog-site" name="monitored_site_id" required class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-amber-500">
                        @foreach($sedes as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- CAMPOS: MARCA Y MODELO -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div class="space-y-1">
                        <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Marca / Fabricante:</label>
                        <input type="text" id="catalog-brand" name="brand" placeholder="ej. HP, Cisco, Canon" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-amber-500">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Modelo (Opcional):</label>
                        <input type="text" id="catalog-model" name="model" placeholder="ej. LaserJet Pro M404" class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-amber-500">
                    </div>
                </div>

                <!-- CAMPO: NOTAS / OBSERVACIONES -->
                <div class="space-y-1">
                    <label class="block text-obsidian-muted text-[11px] uppercase font-bold">Notas / Ubicación Física (Opcional):</label>
                    <textarea id="catalog-notes" name="notes" rows="2" placeholder="Ubicación física, departamento u observaciones..." class="w-full px-3 py-2 rounded-lg bg-obsidian-card border border-obsidian-border text-white text-xs focus:outline-none focus:border-amber-500 font-mono"></textarea>
                </div>

                <!-- FEEDBACK ALERT -->
                <div id="catalog-feedback" class="hidden p-3 rounded-lg border text-xs font-mono max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all"></div>
            </div>

            <!-- MODAL ACTIONS -->
            <div class="p-3.5 bg-obsidian-card/80 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0">
                <button type="button" onclick="closeCatalogModal()" class="px-3.5 py-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-catalog" class="px-4 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-black font-bold transition flex items-center gap-1.5 shadow-md shadow-amber-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm" id="btn-catalog-icon">save</span>
                    <span id="btn-catalog-text">Guardar y Mover a Inventario</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- ============================================================================== -->
<!-- MODAL DE AUDITORÍA FORENSE DE DISCREPANCIAS DE HARDWARE (HARDWARE DRIFT)       -->
<!-- ============================================================================== -->
<div id="modal-hardware-drift" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-obsidian-panel border border-amber-500/40 rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 flex flex-col max-h-[88vh]">
        <!-- MODAL HEADER -->
        <div class="p-4 bg-obsidian-card border-b border-obsidian-border flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2.5">
                <span class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/30">
                    <span class="material-symbols-outlined text-lg">warning_amber</span>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-white font-mono flex items-center gap-2">
                        <span>Auditoría Forense de Discrepancias de Hardware</span>
                        <span class="w-6 h-6 rounded-md bg-amber-950/80 border border-amber-500/40 text-amber-300 inline-flex items-center justify-center cursor-help" title="{{ count($driftAssets) }} Discrepancias Detectadas" id="drift-count-badge">
                            <span class="material-symbols-outlined text-xs">warning</span>
                        </span>
                    </h3>
                    <p class="text-[11px] font-mono text-obsidian-muted">Puestos de trabajo con sustracción, reducción o alteración de componentes físicos</p>
                </div>
            </div>
            <button type="button" onclick="closeHardwareDriftModal()" class="text-obsidian-muted hover:text-white transition p-1">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- MODAL BODY -->
        <div class="p-4 space-y-4 overflow-y-auto flex-1 custom-scroll font-mono text-xs">
            <!-- TARJETAS DE RESUMEN METRICO -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <div class="p-2.5 rounded-xl bg-[#040c17] border border-obsidian-border text-center">
                    <span class="text-[10px] text-obsidian-muted uppercase block">Total Discrepancias</span>
                    <span class="text-base font-black text-amber-400">{{ $driftSummary['total_drift_count'] ?? 0 }}</span>
                </div>
                <div class="p-2.5 rounded-xl bg-[#040c17] border border-obsidian-border text-center">
                    <span class="text-[10px] text-obsidian-muted uppercase block">Disminución de RAM</span>
                    <span class="text-base font-black text-red-400">{{ $driftSummary['ram_reductions'] ?? 0 }}</span>
                </div>
                <div class="p-2.5 rounded-xl bg-[#040c17] border border-obsidian-border text-center">
                    <span class="text-[10px] text-obsidian-muted uppercase block">Discos Faltantes</span>
                    <span class="text-base font-black text-purple-300">{{ $driftSummary['missing_disks'] ?? 0 }}</span>
                </div>
                <div class="p-2.5 rounded-xl bg-[#040c17] border border-obsidian-border text-center">
                    <span class="text-[10px] text-obsidian-muted uppercase block">Cambios de CPU</span>
                    <span class="text-base font-black text-cyan-300">{{ $driftSummary['cpu_changes'] ?? 0 }}</span>
                </div>
            </div>

            <!-- LISTADO DE EQUIPOS AFECTADOS -->
            <div class="space-y-2.5">
                <h4 class="text-[11px] font-bold text-white uppercase tracking-wider flex items-center justify-between">
                    <span>Equipos con Alertas Activas</span>
                    <span class="text-[10px] text-obsidian-muted font-normal">Comparado contra censo y actas activas</span>
                </h4>

                @forelse($driftAssets as $dAsset)
                    <div class="p-3 rounded-xl bg-[#040c17] border border-amber-500/30 hover:border-amber-400/60 transition space-y-2">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/40 text-[10px] font-bold">
                                    {{ $dAsset['asset_tag'] }}
                                </span>
                                <span class="font-bold text-white text-xs">{{ $dAsset['name'] }}</span>
                                <span class="text-obsidian-muted text-[10px]">📍 {{ $dAsset['site_name'] }}</span>
                            </div>
                            <div class="text-[10px] text-slate-400">
                                👤 Responsable: <strong class="text-cyan-300">{{ $dAsset['responsable'] }}</strong>
                            </div>
                        </div>

                        <!-- EVENTOS DETECTADOS -->
                        <div class="space-y-1 pl-2 border-l-2 border-amber-500/40 text-[11px]">
                            @foreach($dAsset['events'] as $ev)
                                <div class="text-slate-200">
                                    <span class="text-red-400 font-bold">• [{{ strtoupper($ev['component_type']) }}]</span>
                                    <span>{{ $ev['reason'] }}</span>
                                    @if(!empty($ev['old_value']) && !empty($ev['new_value']))
                                        <span class="text-obsidian-muted">({{ json_encode($ev['old_value']) }} ➔ {{ json_encode($ev['new_value']) }})</span>
                                    @endif
                                    <span class="text-[10px] text-slate-500 ml-1">{{ $ev['recorded_at'] }}</span>
                                </div>
                            @endforeach
                        </div>

                        <!-- ACCIONES DIRECTAS -->
                        <div class="flex items-center justify-between pt-1 border-t border-obsidian-border/50 text-[11px]">
                            <span class="text-[10px] text-obsidian-muted">IP: <strong class="text-cyan-300">{{ $dAsset['ip_address'] ?? 'N/A' }}</strong></span>
                            <div class="flex items-center gap-2">
                                @if(!empty($dAsset['ip_address']))
                                    <button type="button" onclick="triggerRemoteAgent('{{ $dAsset['ip_address'] }}', '{{ $dAsset['name'] }}')" class="px-2.5 py-1 rounded-lg bg-cyan-950 text-cyan-300 border border-cyan-500/40 hover:bg-cyan-900 transition flex items-center gap-1 cursor-pointer">
                                        <span class="material-symbols-outlined text-xs">sync</span>
                                        <span>Sondear Agente</span>
                                    </button>
                                @endif
                                <a href="{{ route('admin.inventory.assets.show', $dAsset['asset_id']) }}" target="_blank" class="px-2.5 py-1 rounded-lg bg-obsidian-panel text-slate-200 border border-obsidian-border hover:text-white transition flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">visibility</span>
                                    <span>Ver Ficha</span>
                                </a>
                                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                                    <button type="button" onclick="promptAcknowledgeDrift({{ $dAsset['asset_id'] }}, '{{ addslashes($dAsset['name']) }}')" class="px-2.5 py-1 rounded-lg bg-amber-500 text-black font-bold hover:bg-amber-400 transition flex items-center gap-1 cursor-pointer">
                                        <span class="material-symbols-outlined text-xs">check_circle</span>
                                        <span>Regularizar</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-obsidian-muted space-y-1">
                        <span class="material-symbols-outlined text-3xl text-emerald-400 block">verified</span>
                        <p class="text-xs text-emerald-300 font-bold">¡Cero Discrepancias de Hardware!</p>
                        <p class="text-[10px]">Todos los equipos reportan componentes en perfecta paridad con el censo y las actas emitidas.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- MODAL FOOTER -->
        <div class="p-3.5 bg-obsidian-card/80 border-t border-obsidian-border flex items-center justify-between shrink-0 font-mono text-xs">
            <span class="text-[10px] text-obsidian-muted">Motor de Auditoría Forense de Hardware GLPI</span>
            <button type="button" onclick="closeHardwareDriftModal()" class="px-3.5 py-1.5 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white transition cursor-pointer">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- JAVASCRIPT LOGIC -->
<script>
    function openScanSubnetModal() {
        const modal = document.getElementById('modal-scan-subnet');
        if (modal) modal.classList.remove('hidden');
    }

    function closeScanSubnetModal() {
        const modal = document.getElementById('modal-scan-subnet');
        if (modal) modal.classList.add('hidden');
    }

    function openHardwareDriftModal() {
        const modal = document.getElementById('modal-hardware-drift');
        if (modal) modal.classList.remove('hidden');
    }

    function closeHardwareDriftModal() {
        const modal = document.getElementById('modal-hardware-drift');
        if (modal) modal.classList.add('hidden');
    }

    async function triggerRemoteAgent(ip, name) {
        if (!ip) return;
        if (!confirm(`¿Enviar orden de inventario inmediato al agente GLPI en ${name} (${ip})?`)) return;

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch("{{ route('admin.inventory.deploy.trigger-agent') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ ip: ip })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                alert(`✅ [EXITOSO]: ${data.message}`);
            } else {
                alert(`⚠️ [AVISO]: ${data.message || 'No se pudo contactar al agente en el puerto 62354.'}`);
            }
        } catch (e) {
            alert(`❌ Error al conectar con el servidor: ${e.message}`);
        }
    }

    async function promptAcknowledgeDrift(assetId, name) {
        const reason = prompt(`Ingrese el motivo o justificación técnica para regularizar las discrepancias en '${name}':`, 'Mantenimiento preventivo autorizado / Reconfiguración autorizada');
        if (!reason || !reason.trim()) return;

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch("{{ route('admin.inventory.deploy.drift-acknowledge') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ asset_id: assetId, reason: reason.trim() })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                alert(`✅ ${data.message}`);
                location.reload();
            } else {
                alert(`⚠️ ${data.message || 'Error al regularizar la discrepancia.'}`);
            }
        } catch (e) {
            alert(`❌ Error al procesar solicitud: ${e.message}`);
        }
    }

    function onScanSiteChanged(subnet) {
        if (subnet) {
            document.getElementById('scan-subnet-input').value = subnet;
        }
    }

    async function executeSubnetScan(event) {
        event.preventDefault();
        const subnet = document.getElementById('scan-subnet-input').value.trim();
        if (!subnet) return;

        const btnSubmit = document.getElementById('btn-submit-scan');
        const loading = document.getElementById('scan-loading-indicator');
        const feedback = document.getElementById('scan-feedback-alert');

        btnSubmit.disabled = true;
        btnSubmit.classList.add('opacity-50', 'cursor-not-allowed');
        loading.classList.remove('hidden');
        feedback.classList.add('hidden');

        try {
            const response = await fetch("{{ route('admin.inventory.deploy.scan-subnet') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ subnet: subnet })
            });

            const data = await response.json();

            if (data.success && data.redirect_url) {
                feedback.className = "p-3 rounded-xl bg-emerald-950/40 border border-emerald-500/40 text-emerald-300 text-xs font-mono block";
                feedback.innerHTML = `<span class="material-symbols-outlined text-sm align-middle mr-1">check_circle</span> ${data.message} Redirigiendo a resultados...`;
                setTimeout(() => {
                    window.location.href = data.redirect_url;
                }, 800);
            } else {
                feedback.className = "p-3 rounded-xl bg-red-950/40 border border-red-500/40 text-red-300 text-xs font-mono max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all block";
                feedback.innerHTML = `<span class="material-symbols-outlined text-sm align-middle mr-1">error</span> ${data.error || 'Error al ejecutar el escaneo.'}`;
                btnSubmit.disabled = false;
                btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
                loading.classList.add('hidden');
            }
        } catch (err) {
            feedback.className = "p-3 rounded-xl bg-red-950/40 border border-red-500/40 text-red-300 text-xs font-mono max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all block";
            feedback.innerHTML = `<span class="material-symbols-outlined text-sm align-middle mr-1">error</span> Error de comunicación: ${err.message}`;
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
            loading.classList.add('hidden');
        }
    }
    function openDeployModal(ip, hostname, osType, action, osArch = '') {
        document.getElementById('deploy-host').value = ip;
        document.getElementById('deploy-action').value = action;
        const archText = osArch ? ` • ${osArch}` : '';
        document.getElementById('modal-subtitle').innerText = `Equipo: ${hostname} (${ip})${archText}`;

        const osSelect = document.getElementById('deploy-os');
        if (osType === 'windows' || osType === 'linux') {
            osSelect.value = osType;
        } else {
            osSelect.value = 'windows';
        }
        toggleDomainField();

        const titleEl = document.getElementById('modal-title');
        const iconEl = document.getElementById('modal-action-icon');
        const btnText = document.getElementById('btn-deploy-text');

        if (action === 'switch') {
            titleEl.innerText = 'Conmutar Servidor GLPI';
            iconEl.innerText = 'swap_horiz';
            btnText.innerText = 'Conmutar Servidor';
        } else {
            titleEl.innerText = 'Desplegar Agente GLPI';
            iconEl.innerText = 'rocket_launch';
            btnText.innerText = 'Lanzar Despliegue';
        }

        // Limpiar feedbacks anteriores
        document.getElementById('deploy-feedback').className = 'hidden';
        document.getElementById('deploy-terminal').className = 'hidden';
        document.getElementById('deploy-terminal').innerText = '';

        document.getElementById('modal-deploy').classList.remove('hidden');
    }

    function closeDeployModal() {
        document.getElementById('modal-deploy').classList.add('hidden');
    }

    function toggleDomainField() {
        const os = document.getElementById('deploy-os').value;
        const domField = document.getElementById('field-domain');
        if (os === 'windows') {
            domField.classList.remove('hidden');
        } else {
            domField.classList.add('hidden');
        }
    }

    function openOneLinerModal(defaultTab = 'oneliner') {
        const modal = document.getElementById('modal-oneliner');
        if (modal) {
            modal.classList.remove('hidden');
            switchDeployGuideTab(defaultTab);
        }
    }

    function closeOneLinerModal() {
        const modal = document.getElementById('modal-oneliner');
        if (modal) modal.classList.add('hidden');
    }

    function switchDeployGuideTab(tab) {
        const tabs = ['oneliner', 'manual', 'params'];
        tabs.forEach(t => {
            const btn = document.getElementById(`tab-btn-${t}`);
            const content = document.getElementById(`guide-tab-content-${t}`);
            if (t === tab) {
                if (btn) {
                    btn.className = 'px-3 py-1.5 rounded-lg font-bold transition flex items-center gap-1.5 cursor-pointer bg-cyan-500 text-black shadow-xs';
                }
                if (content) {
                    content.classList.remove('hidden');
                }
            } else {
                if (btn) {
                    btn.className = 'px-3 py-1.5 rounded-lg font-bold transition flex items-center gap-1.5 cursor-pointer text-obsidian-muted hover:text-white hover:bg-obsidian-card border border-transparent';
                }
                if (content) {
                    content.classList.add('hidden');
                }
            }
        });
    }

    function showCopyToast() {
        const toast = document.getElementById('copy-toast-msg');
        if (toast) {
            toast.classList.remove('hidden');
            clearTimeout(window._copyToastTimer);
            window._copyToastTimer = setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                showCopyToast();
            }).catch(() => {
                fallbackCopyToClipboard(text);
            });
        } else {
            fallbackCopyToClipboard(text);
        }
    }

    function fallbackCopyToClipboard(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showCopyToast();
        } catch (err) {
            console.error('Error al copiar al portapapeles:', err);
        }
        textArea.remove();
    }

    function copyOneLiner(osType, ip) {
        let cmd = osType === 'linux' 
            ? '(which curl >/dev/null 2>&1 && curl -sSL http://10.20.23.221/deploy/linux || wget -qO- http://10.20.23.221/deploy/linux) | sudo bash'
            : '(New-Object System.Net.WebClient).DownloadString(\'http://10.20.23.221/deploy/win\') | iex';
        copyToClipboard(cmd);
        openOneLinerModal('oneliner');
    }

    async function executeRemoteDeploy(e) {
        e.preventDefault();

        const form = document.getElementById('form-deploy-action');
        const btn = document.getElementById('btn-submit-deploy');
        const btnText = document.getElementById('btn-deploy-text');
        const btnIcon = document.getElementById('btn-deploy-icon');
        const feedback = document.getElementById('deploy-feedback');
        const terminal = document.getElementById('deploy-terminal');

        btn.disabled = true;
        btnText.innerText = 'Ejecutando en segundo plano...';
        btnIcon.innerText = 'hourglass_top';

        feedback.className = 'p-3 rounded-lg border border-cyan-500/40 bg-cyan-950/30 text-cyan-300 font-mono text-xs flex items-center gap-2';
        feedback.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">refresh</span> Conectando con el equipo y ejecutando instalador silencioso...';
        
        terminal.className = 'hidden';
        terminal.innerText = '';

        const formData = new FormData(form);

        try {
            const resp = await fetch('{{ route('admin.inventory.deploy.execute') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await resp.json();

            if (data.success) {
                feedback.className = 'p-3 rounded-lg border border-emerald-500/40 bg-emerald-950/40 text-emerald-300 font-mono text-xs max-h-48 overflow-y-auto break-words select-all block';
                feedback.innerHTML = '<div class="flex items-center gap-2 font-bold mb-1"><span class="material-symbols-outlined text-sm text-emerald-400">check_circle</span> ¡Operación ejecutada exitosamente!</div>';

                if (data.stdout) {
                    terminal.className = 'rounded-lg bg-black border border-obsidian-border p-2.5 max-h-36 overflow-y-auto text-[11px] text-green-400 font-mono whitespace-pre-wrap';
                    terminal.innerText = data.stdout;
                }

                // Si fue verificado en base de datos
                if (data.db_verification && data.db_verification.verified) {
                    feedback.innerHTML += `<div class="mt-1 text-[11px] text-cyan-300 font-bold">📦 Activo registrado en GLPI: ${data.db_verification.asset.name}</div>`;
                }

                setTimeout(() => {
                    location.reload();
                }, 4000);
            } else {
                feedback.className = 'p-3 rounded-lg border border-red-500/40 bg-red-950/40 text-red-300 font-mono text-xs max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all block';
                const errDetail = data.error || data.stderr || 'Revise credenciales o conectividad';
                feedback.innerHTML = `<div class="flex items-center gap-1.5 font-bold mb-1 text-red-200"><span class="material-symbols-outlined text-sm text-red-400">error</span> Falló la operación:</div><div class="text-[11px] opacity-95 leading-relaxed">${errDetail}</div>`;

                if (data.stderr || data.stdout) {
                    terminal.className = 'rounded-lg bg-black border border-red-500/30 p-2.5 max-h-36 overflow-y-auto text-[11px] text-red-300 font-mono whitespace-pre-wrap';
                    terminal.innerText = (data.stdout ? data.stdout + '\n' : '') + (data.stderr || '');
                }
            }
        } catch (err) {
            feedback.className = 'p-3 rounded-lg border border-red-500/40 bg-red-950/40 text-red-300 font-mono text-xs max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all block';
            feedback.innerHTML = `<div class="flex items-center gap-1.5 font-bold mb-1 text-red-200"><span class="material-symbols-outlined text-sm text-red-400">error</span> Error de comunicación:</div><div class="text-[11px] opacity-95 leading-relaxed">${err.message}</div>`;
        } finally {
            btn.disabled = false;
            btnText.innerText = 'Lanzar Despliegue';
            btnIcon.innerText = 'rocket_launch';
        }
    }

    // ==============================================================================
    // FUNCIONES DEL MODAL DE CATALOGAR EN INVENTARIO (OPCIÓN A)
    // ==============================================================================
    function openCatalogModal(ip, hostname, mac, suggestedKey, suggestedLabel, siteId, vendor) {
        document.getElementById('catalog-ip').value = ip;
        document.getElementById('catalog-mac').value = mac || '';
        document.getElementById('catalog-display-ip').innerText = ip;
        document.getElementById('catalog-display-mac').innerText = mac || 'Sin MAC';
        document.getElementById('catalog-display-vendor').innerText = vendor || 'Genérico';

        // Pre-cargar nombre etiqueta con hostname si existe
        document.getElementById('catalog-name').value = hostname || '';

        const typeSelect = document.getElementById('catalog-type');
        if (suggestedKey && Array.from(typeSelect.options).some(o => o.value === suggestedKey)) {
            typeSelect.value = suggestedKey;
        } else {
            typeSelect.value = 'desktop';
        }

        const hintEl = document.getElementById('catalog-suggested-text');
        if (hintEl) {
            hintEl.innerText = suggestedLabel || 'No determinada';
        }

        const siteSelect = document.getElementById('catalog-site');
        if (siteId && Array.from(siteSelect.options).some(o => o.value == siteId)) {
            siteSelect.value = siteId;
        }

        document.getElementById('catalog-brand').value = vendor || '';
        document.getElementById('catalog-model').value = '';
        document.getElementById('catalog-notes').value = '';

        const feedback = document.getElementById('catalog-feedback');
        feedback.className = 'hidden';
        feedback.innerHTML = '';

        const btn = document.getElementById('btn-submit-catalog');
        btn.disabled = false;
        updateCatalogBtnText();

        document.getElementById('modal-catalog-asset').classList.remove('hidden');
    }

    function updateCatalogBtnText() {
        const type = document.getElementById('catalog-type').value;
        const isWorkstation = ['desktop', 'laptop', 'server'].includes(type);
        const btnText = document.getElementById('btn-catalog-text');
        if (btnText) {
            if (isWorkstation) {
                btnText.innerText = 'Registrar como PC y Habilitar';
            } else {
                btnText.innerText = 'Guardar y Mover a Inventario';
            }
        }
    }

    function closeCatalogModal() {
        document.getElementById('modal-catalog-asset').classList.add('hidden');
    }

    async function executeCatalogAsset(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-submit-catalog');
        const btnText = document.getElementById('btn-catalog-text');
        const btnIcon = document.getElementById('btn-catalog-icon');
        const feedback = document.getElementById('catalog-feedback');

        btn.disabled = true;
        const oldText = btnText.innerText;
        btnText.innerText = 'Guardando en inventario...';
        btnIcon.innerText = 'hourglass_top';

        feedback.className = 'p-3 rounded-lg border border-amber-500/40 bg-amber-950/30 text-amber-300 font-mono text-xs flex items-center gap-2';
        feedback.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">refresh</span> Guardando activo e integrando al ecosistema...';

        const payload = {
            ip: document.getElementById('catalog-ip').value,
            mac: document.getElementById('catalog-mac').value,
            name: document.getElementById('catalog-name').value.trim(),
            type: document.getElementById('catalog-type').value,
            monitored_site_id: document.getElementById('catalog-site').value,
            brand: document.getElementById('catalog-brand').value.trim(),
            model: document.getElementById('catalog-model').value.trim(),
            notes: document.getElementById('catalog-notes').value.trim(),
        };

        try {
            const resp = await fetch('{{ route('admin.inventory.deploy.promote-asset') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });

            const data = await resp.json();

            if (data.success) {
                feedback.className = 'p-3 rounded-lg border border-emerald-500/40 bg-emerald-950/40 text-emerald-300 font-mono text-xs flex items-center gap-2';
                feedback.innerHTML = `<span class="material-symbols-outlined text-sm text-emerald-400">check_circle</span> ${data.message}`;

                const rowId = 'row-' + payload.ip.replace(/\./g, '-');
                const rowEl = document.getElementById(rowId);

                if (data.action === 'moved') {
                    if (rowEl) {
                        rowEl.style.transition = 'all 0.5s ease';
                        rowEl.style.opacity = '0';
                        rowEl.style.transform = 'translateX(20px)';
                        setTimeout(() => {
                            rowEl.remove();
                        }, 500);
                    }
                    setTimeout(() => {
                        closeCatalogModal();
                    }, 1500);
                } else {
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                }
            } else {
                feedback.className = 'p-3 rounded-lg border border-red-500/40 bg-red-950/40 text-red-300 font-mono text-xs max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all block';
                feedback.innerHTML = `<span class="material-symbols-outlined text-sm text-red-400">error</span> ${data.error || data.message || 'Error al catalogar el activo.'}`;
                btn.disabled = false;
                btnText.innerText = oldText;
                btnIcon.innerText = 'save';
            }
        } catch (err) {
            feedback.className = 'p-3 rounded-lg border border-red-500/40 bg-red-950/40 text-red-300 font-mono text-xs max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all block';
            feedback.innerHTML = `Error de comunicación: ${err.message}`;
            btn.disabled = false;
            btnText.innerText = oldText;
            btnIcon.innerText = 'save';
        }
    }

    // ==============================================================================
    // FUNCIONES DE SELECCIÓN Y ACCIONES POR LOTE (BULK ACTIONS)
    // ==============================================================================
    function toggleSelectAllDevices(isChecked) {
        const checkboxes = document.querySelectorAll('.device-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = isChecked;
        });
        onDeviceSelectionChanged();
    }

    function onDeviceSelectionChanged() {
        const checkedBoxes = document.querySelectorAll('.device-checkbox:checked');
        const count = checkedBoxes.length;
        const total = document.querySelectorAll('.device-checkbox').length;
        const bar = document.getElementById('bulk-actions-bar');
        const countEl = document.getElementById('bulk-selected-count');
        const selectAll = document.getElementById('select-all-devices');

        if (countEl) countEl.innerText = count;

        if (count > 0) {
            bar.classList.remove('hidden');
        } else {
            bar.classList.add('hidden');
        }

        if (selectAll) {
            selectAll.checked = (total > 0 && count === total);
            selectAll.indeterminate = (count > 0 && count < total);
        }
    }

    function clearDeviceSelection() {
        const checkboxes = document.querySelectorAll('.device-checkbox');
        checkboxes.forEach(cb => { cb.checked = false; });
        const selectAll = document.getElementById('select-all-devices');
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
        onDeviceSelectionChanged();
    }

    function getSelectedDevicesData() {
        const checkedBoxes = document.querySelectorAll('.device-checkbox:checked');
        const list = [];
        checkedBoxes.forEach(cb => {
            list.push({
                ip: cb.dataset.ip,
                mac: cb.dataset.mac || '',
                hostname: cb.dataset.hostname || '',
                vendor: cb.dataset.vendor || '',
                osType: cb.dataset.osType || 'windows',
                osArch: cb.dataset.osArch || '',
                isUnregistered: cb.dataset.isUnregistered === '1',
                isGlpiSupported: cb.dataset.isGlpiSupported === '1',
                suggestedKey: cb.dataset.suggestedKey || 'desktop',
                suggestedLabel: cb.dataset.suggestedLabel || '',
                siteId: cb.dataset.siteId || ''
            });
        });
        return list;
    }

    // ==============================================================================
    // MODAL DE CATALOGACIÓN EN LOTE (BULK CATALOG)
    // ==============================================================================
    function openBulkCatalogModal() {
        const devices = getSelectedDevicesData();
        if (devices.length === 0) return;

        const chipsContainer = document.getElementById('bulk-catalog-chips');
        chipsContainer.innerHTML = '';
        devices.forEach(dev => {
            const chip = document.createElement('span');
            chip.className = 'px-2 py-0.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-300 text-[10.5px] font-mono flex items-center gap-1';
            chip.innerHTML = `<span class="material-symbols-outlined text-[11px]">devices</span> <b>${dev.ip}</b> <span class="text-obsidian-muted">(${dev.hostname || 'Host'})</span>`;
            chipsContainer.appendChild(chip);
        });

        document.getElementById('bulk-catalog-subtitle').innerText = `Mover ${devices.length} equipo(s) seleccionado(s) a la base de datos ITAM`;
        document.getElementById('bulk-catalog-feedback').className = 'hidden';
        document.getElementById('bulk-catalog-feedback').innerHTML = '';

        const btn = document.getElementById('btn-submit-bulk-catalog');
        btn.disabled = false;
        document.getElementById('btn-bulk-catalog-text').innerText = 'Guardar en Inventario';

        // Intentar pre-seleccionar tipo si todos son de un mismo tipo sugerido
        const firstSuggested = devices[0].suggestedKey;
        const allSameType = devices.every(d => d.suggestedKey === firstSuggested);
        const typeSelect = document.getElementById('bulk-catalog-type');
        if (allSameType && firstSuggested && Array.from(typeSelect.options).some(o => o.value === firstSuggested)) {
            typeSelect.value = firstSuggested;
        }

        document.getElementById('modal-bulk-catalog').classList.remove('hidden');
    }

    function closeBulkCatalogModal() {
        document.getElementById('modal-bulk-catalog').classList.add('hidden');
    }

    async function executeBulkCatalog(e) {
        e.preventDefault();
        const devices = getSelectedDevicesData();
        if (devices.length === 0) return;

        const btn = document.getElementById('btn-submit-bulk-catalog');
        const btnText = document.getElementById('btn-bulk-catalog-text');
        const btnIcon = document.getElementById('btn-bulk-catalog-icon');
        const feedback = document.getElementById('bulk-catalog-feedback');

        btn.disabled = true;
        btnText.innerText = 'Catalogando lote...';
        btnIcon.innerText = 'hourglass_top';

        feedback.className = 'p-3 rounded-lg border border-amber-500/40 bg-amber-950/30 text-amber-300 font-mono text-xs flex items-center gap-2';
        feedback.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">refresh</span> Procesando deduplicación por MAC y actualizando censo ITAM...';

        const payload = {
            devices: devices.map(d => ({
                ip: d.ip,
                mac: d.mac,
                hostname: d.hostname,
                vendor: d.vendor
            })),
            type: document.getElementById('bulk-catalog-type').value,
            monitored_site_id: document.getElementById('bulk-catalog-site').value,
            name_prefix: document.getElementById('bulk-catalog-prefix').value.trim(),
            notes: document.getElementById('bulk-catalog-notes').value.trim(),
        };

        try {
            const resp = await fetch('{{ route('admin.inventory.deploy.bulk-promote') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });

            const data = await resp.json();

            if (data.success) {
                feedback.className = 'p-3 rounded-lg border border-emerald-500/40 bg-emerald-950/40 text-emerald-300 font-mono text-xs flex items-center gap-2';
                feedback.innerHTML = `<span class="material-symbols-outlined text-sm text-emerald-400">check_circle</span> ${data.message}`;

                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                feedback.className = 'p-3 rounded-lg border border-red-500/40 bg-red-950/40 text-red-300 font-mono text-xs max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all block';
                feedback.innerHTML = `<span class="material-symbols-outlined text-sm text-red-400">error</span> ${data.message || data.error || 'Error al procesar el lote.'}`;
                btn.disabled = false;
                btnText.innerText = 'Guardar en Inventario';
                btnIcon.innerText = 'save';
            }
        } catch (err) {
            feedback.className = 'p-3 rounded-lg border border-red-500/40 bg-red-950/40 text-red-300 font-mono text-xs max-h-48 overflow-y-auto break-words whitespace-pre-wrap select-all block';
            feedback.innerHTML = `Error de red: ${err.message}`;
            btn.disabled = false;
            btnText.innerText = 'Guardar en Inventario';
            btnIcon.innerText = 'save';
        }
    }

    // ==============================================================================
    // MODAL DE DESPLIEGUE Y CONMUTACIÓN GLPI EN LOTE (SECUENCIAL / UNO TRAS UNO)
    // ==============================================================================
    function onBulkActionChanged(action) {
        const hint = document.getElementById('bulk-action-hint');
        const modalTitle = document.getElementById('bulk-modal-title');
        const modalIcon = document.getElementById('bulk-modal-icon');
        const btnText = document.getElementById('btn-bulk-deploy-text');
        const btnIcon = document.getElementById('btn-bulk-deploy-icon');
        const subtitle = document.getElementById('bulk-deploy-subtitle');
        const count = getSelectedDevicesData().length;

        if (action === 'switch') {
            if (hint) hint.classList.remove('hidden');
            if (modalTitle) modalTitle.innerText = 'Conmutación de Agente GLPI por Lote';
            if (modalIcon) modalIcon.innerText = 'swap_horiz';
            if (btnText) btnText.innerText = 'Iniciar Conmutación Secuencial';
            if (btnIcon) btnIcon.innerText = 'swap_horiz';
            if (subtitle) subtitle.innerText = `Migración rápida de servidor Dev ⇄ Prod (${count} equipo(s) seleccionado(s))`;
        } else {
            if (hint) hint.classList.add('hidden');
            if (modalTitle) modalTitle.innerText = 'Despliegue de Agente GLPI por Lote';
            if (modalIcon) modalIcon.innerText = 'rocket_launch';
            if (btnText) btnText.innerText = 'Iniciar Despliegue Secuencial';
            if (btnIcon) btnIcon.innerText = 'rocket_launch';
            if (subtitle) subtitle.innerText = `Instalación secuencial para ${count} equipo(s) seleccionado(s)`;
        }
    }

    function openBulkDeployModal(defaultAction = 'install') {
        const devices = getSelectedDevicesData();
        if (devices.length === 0) return;

        const chipsContainer = document.getElementById('bulk-deploy-chips');
        chipsContainer.innerHTML = '';
        devices.forEach(dev => {
            const chip = document.createElement('span');
            chip.className = 'px-2 py-0.5 rounded-lg bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 text-[10.5px] font-mono flex items-center gap-1';
            chip.innerHTML = `<span class="material-symbols-outlined text-[11px]">devices</span> <b>${dev.ip}</b> <span class="text-obsidian-muted">(${dev.hostname || 'Host'})</span>`;
            chipsContainer.appendChild(chip);
        });

        // Preseleccionar acción solicitada
        const actionRadio = document.querySelector(`input[name="bulk_action"][value="${defaultAction}"]`);
        if (actionRadio) {
            actionRadio.checked = true;
        }
        onBulkActionChanged(defaultAction);

        // Si es conmutación, preseleccionar Producción como destino sugerido
        if (defaultAction === 'switch') {
            const prodTarget = document.querySelector('input[name="bulk_target"][value="prod"]');
            if (prodTarget) prodTarget.checked = true;
        }

        document.getElementById('bulk-deploy-feedback').className = 'hidden';
        document.getElementById('bulk-deploy-terminal').className = 'hidden';
        document.getElementById('bulk-deploy-terminal').innerText = '';
        document.getElementById('bulk-deploy-progress-container').classList.add('hidden');

        // Reset barra de progreso y contadores
        document.getElementById('bulk-progress-bar').style.width = '0%';
        document.getElementById('bulk-progress-percent').innerText = '0%';
        document.getElementById('bulk-success-count').innerText = '0';
        document.getElementById('bulk-failed-count').innerText = '0';
        document.getElementById('bulk-pending-count').innerText = devices.length;

        const btn = document.getElementById('btn-submit-bulk-deploy');
        btn.disabled = false;
        document.getElementById('btn-cancel-bulk-deploy').disabled = false;

        toggleBulkDomainField();
        document.getElementById('modal-bulk-deploy').classList.remove('hidden');
    }

    function closeBulkDeployModal() {
        document.getElementById('modal-bulk-deploy').classList.add('hidden');
    }

    function toggleBulkDomainField() {
        const os = document.getElementById('bulk-deploy-os').value;
        const domField = document.getElementById('bulk-field-domain');
        if (os === 'windows') {
            domField.classList.remove('hidden');
        } else {
            domField.classList.add('hidden');
        }
    }

    async function executeSequentialBulkDeploy(e) {
        e.preventDefault();
        const devices = getSelectedDevicesData();
        if (devices.length === 0) return;

        const actionRadio = document.querySelector('input[name="bulk_action"]:checked');
        const action = actionRadio ? actionRadio.value : 'install';
        const isSwitch = (action === 'switch');

        const btn = document.getElementById('btn-submit-bulk-deploy');
        const btnText = document.getElementById('btn-bulk-deploy-text');
        const btnIcon = document.getElementById('btn-bulk-deploy-icon');
        const btnCancel = document.getElementById('btn-cancel-bulk-deploy');
        const feedback = document.getElementById('bulk-deploy-feedback');
        const terminal = document.getElementById('bulk-deploy-terminal');
        const progressContainer = document.getElementById('bulk-deploy-progress-container');
        const progressBar = document.getElementById('bulk-progress-bar');
        const progressPercent = document.getElementById('bulk-progress-percent');
        const progressStatus = document.getElementById('bulk-progress-status');
        const successCountEl = document.getElementById('bulk-success-count');
        const failedCountEl = document.getElementById('bulk-failed-count');
        const pendingCountEl = document.getElementById('bulk-pending-count');

        btn.disabled = true;
        btnCancel.disabled = true;
        btnText.innerText = isSwitch ? 'Conmutando en lote...' : 'Desplegando en lote...';
        btnIcon.innerText = 'hourglass_top';

        progressContainer.classList.remove('hidden');
        terminal.classList.remove('hidden');
        const opTitle = isSwitch ? 'CONMUTACIÓN RÁPIDA DE SERVIDOR GLPI' : 'DESPLIEGUE SECUENCIAL DE AGENTE GLPI';
        terminal.innerText = `>>> INICIANDO ${opTitle} EN ${devices.length} EQUIPOS <<<\n\n`;

        const targetRadio = document.querySelector('input[name="bulk_target"]:checked');
        const target = targetRadio ? targetRadio.value : 'dev';
        const targetLabel = (target === 'prod') ? 'PRODUCCIÓN (10.20.23.252)' : 'DESARROLLO (10.20.23.221)';
        terminal.innerText += `-> Destino seleccionado: ${targetLabel}\n`;
        terminal.innerText += `-> Modo de operación: ${isSwitch ? 'Reconfiguración y reinicio en caliente (sin reinstalar)' : 'Instalación completa desatendida'}\n\n`;

        const os = document.getElementById('bulk-deploy-os').value;
        const user = document.getElementById('bulk-deploy-user').value.trim();
        const password = document.getElementById('bulk-deploy-pass').value;
        const domain = document.getElementById('bulk-deploy-domain').value.trim();

        let successCount = 0;
        let failedCount = 0;
        const total = devices.length;

        for (let i = 0; i < total; i++) {
            const dev = devices[i];
            const currentNum = i + 1;
            const currentPct = Math.round(((i) / total) * 100);

            progressBar.style.width = `${currentPct}%`;
            progressPercent.innerText = `${currentPct}%`;
            const actionVerb = isSwitch ? 'Conmutando' : 'Instalando';
            progressStatus.innerText = `${actionVerb} equipo ${currentNum} de ${total}: ${dev.ip} (${dev.hostname || 'Host'})...`;
            pendingCountEl.innerText = (total - i);

            terminal.innerText += `[${currentNum}/${total}] Conectando con ${dev.ip} (${dev.hostname})...\n`;
            terminal.scrollTop = terminal.scrollHeight;

            const formData = new FormData();
            formData.append('host', dev.ip);
            formData.append('action', action);
            formData.append('target', target);
            formData.append('os', os);
            formData.append('user', user);
            formData.append('password', password);
            if (domain) {
                formData.append('domain', domain);
            }

            try {
                const resp = await fetch('{{ route('admin.inventory.deploy.execute') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await resp.json();

                if (data.success) {
                    successCount++;
                    successCountEl.innerText = successCount;
                    const okMsg = isSwitch ? 'Conmutación completada' : 'Instalación completada';
                    terminal.innerText += `   -> [OK] ${okMsg} exitosamente en ${dev.ip}.\n`;
                    if (data.stdout) {
                        terminal.innerText += `   -> Salida: ${data.stdout.trim().split('\n').slice(-2).join(' | ')}\n`;
                    }
                } else {
                    failedCount++;
                    failedCountEl.innerText = failedCount;
                    terminal.innerText += `   -> [ERROR] Falló en ${dev.ip}: ${data.error || data.stderr || 'Sin respuesta'}\n`;
                }
            } catch (err) {
                failedCount++;
                failedCountEl.innerText = failedCount;
                terminal.innerText += `   -> [EXCEPCIÓN] Fallo de comunicación en ${dev.ip}: ${err.message}\n`;
            }

            terminal.innerText += `--------------------------------------------------\n`;
            terminal.scrollTop = terminal.scrollHeight;
        }

        // Finalización
        progressBar.style.width = '100%';
        progressPercent.innerText = '100%';
        const finalVerb = isSwitch ? 'Conmutación' : 'Despliegue';
        progressStatus.innerText = `¡${finalVerb} finalizado! (${successCount} exitosos, ${failedCount} fallidos)`;
        pendingCountEl.innerText = '0';

        terminal.innerText += `\n>>> RESUMEN FINAL: ${successCount} EXITOSOS, ${failedCount} FALLIDOS DE ${total} TOTALES. <<<\n`;
        terminal.scrollTop = terminal.scrollHeight;

        feedback.className = 'p-3 rounded-lg border border-cyan-500/40 bg-cyan-950/40 text-cyan-300 font-mono text-xs flex items-center justify-between';
        feedback.innerHTML = `<span>Proceso completado: <b>${successCount} exitosos</b>, <b>${failedCount} con error</b>.</span>
            <button type="button" onclick="location.reload()" class="px-2.5 py-1 rounded-md bg-cyan-500 text-black font-bold text-[11px] hover:bg-cyan-400 cursor-pointer">Recargar Vista</button>`;
        feedback.classList.remove('hidden');

        btnText.innerText = isSwitch ? 'Conmutación Completada' : 'Despliegue Completado';
        btnIcon.innerText = 'task_alt';
        btnCancel.disabled = false;
    }
</script>
@endsection
