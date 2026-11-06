@extends('layouts.admin')

@section('page_title', 'Mis Equipos Asignados')

@section('admin_content')
<div class="space-y-6">
    <!-- ENCABEZADO DE PÁGINA (REGLA DE ORO #11: SOBRIO, SIN BADGES DE TEXTO EN TÍTULOS) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-obsidian-border">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-cyan-950/60 border border-cyan-500/50 flex items-center justify-center text-cyan-400 shadow-md shadow-cyan-500/20">
                <span class="material-symbols-outlined text-xl">computer</span>
            </div>
            <div>
                <h1 class="text-base sm:text-lg font-bold text-white tracking-tight">Mis Equipos Asignados</h1>
                <p class="text-xs text-obsidian-muted font-mono">Puestos de trabajo, hardware complementario y actas de asignación UINF-4.05</p>
            </div>
        </div>

        <div class="flex items-center gap-2 text-xs font-mono text-obsidian-muted bg-obsidian-panel/80 px-3.5 py-2 rounded-xl border border-obsidian-border shadow-sm">
            <span class="material-symbols-outlined text-sm text-cyan-400">badge</span>
            <span>Responsable: <strong class="text-white">{{ $user->name }}</strong></span>
            @if(!empty($user->cedula))
                <span class="text-obsidian-muted/60">• C.I. {{ $user->cedula }}</span>
            @endif
        </div>
    </div>

    <!-- TARJETAS DE RESUMEN (KPIS) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- TOTAL DE EQUIPOS -->
        <div class="p-4 rounded-xl bg-[#06111f]/90 border border-obsidian-border/80 shadow-lg relative overflow-hidden group hover:border-cyan-500/50 transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-mono uppercase tracking-wider text-obsidian-muted">Puestos Asignados</span>
                <span class="material-symbols-outlined text-cyan-400 text-lg">devices</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-white font-mono">{{ $totalDevices }}</span>
                <span class="text-[11px] text-obsidian-muted font-mono">{{ $totalDevices === 1 ? 'estación' : 'estaciones' }}</span>
            </div>
            <p class="text-[10px] text-obsidian-muted mt-2">Equipos principales bajo su responsabilidad operativa</p>
            <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-cyan-500/40 to-transparent"></div>
        </div>

        <!-- TOTAL DE PERIFÉRICOS -->
        <div class="p-4 rounded-xl bg-[#06111f]/90 border border-obsidian-border/80 shadow-lg relative overflow-hidden group hover:border-purple-500/50 transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-mono uppercase tracking-wider text-obsidian-muted">Periféricos Asociados</span>
                <span class="material-symbols-outlined text-purple-400 text-lg">keyboard</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-white font-mono">{{ $totalPeripherals }}</span>
                <span class="text-[11px] text-obsidian-muted font-mono">accesorios</span>
            </div>
            <p class="text-[10px] text-obsidian-muted mt-2">Monitores, teclados, ratones y reguladores enlazados</p>
            <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-purple-500/40 to-transparent"></div>
        </div>

        <!-- ACTAS REGISTRADAS -->
        <div class="p-4 rounded-xl bg-[#06111f]/90 border border-obsidian-border/80 shadow-lg relative overflow-hidden group hover:border-emerald-500/50 transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-mono uppercase tracking-wider text-obsidian-muted">Actas Digitalizadas</span>
                <span class="material-symbols-outlined text-emerald-400 text-lg">description</span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-white font-mono">{{ $totalActsWithPdf }}</span>
                <span class="text-[11px] text-obsidian-muted font-mono">de {{ $totalDevices }}</span>
            </div>
            <p class="text-[10px] text-obsidian-muted mt-2">Soportes oficiales UINF-4.05 listos para consulta o descarga</p>
            <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-emerald-500/40 to-transparent"></div>
        </div>
    </div>

    <!-- LISTADO DE EQUIPOS ASIGNADOS -->
    @if($devices->isEmpty())
        <!-- ESTADO VACÍO -->
        <div class="p-10 rounded-2xl bg-[#06111f]/80 border border-obsidian-border text-center space-y-4">
            <div class="w-16 h-16 mx-auto rounded-full bg-slate-900 border border-obsidian-border flex items-center justify-center text-obsidian-muted text-2xl">
                <span class="material-symbols-outlined text-3xl">desktop_access_disabled</span>
            </div>
            <div class="max-w-md mx-auto space-y-1">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Sin Equipos Asignados</h3>
                <p class="text-xs text-obsidian-muted leading-relaxed font-mono">
                    Actualmente no figuran puestos de trabajo o activos informáticos asignados a su nombre o cédula en el censo oficial.
                </p>
            </div>
            <div class="pt-2 text-[11px] font-mono text-cyan-400/80">
                <span>Si dispone de un puesto operativo en su oficina, contacte al personal de Soporte Técnico / ATIT para la formalización del acta de asignación.</span>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach($devices as $device)
                @php
                    $acta = $device->activeAssignment;
                    $hasActaPdf = $acta && !empty($acta->document_pdf_path);
                    $peripherals = $device->peripherals;
                    if ($peripherals->isEmpty() && $acta && is_array($acta->peripherals_json)) {
                        $peripherals = collect($acta->peripherals_json)->map(function ($p) {
                            return (object) [
                                'type' => $p['type'] ?? 'Periférico',
                                'brand' => $p['brand'] ?? '',
                                'model' => $p['model'] ?? ($p['type'] ?? 'Accesorio'),
                                'serial_number' => $p['serial_number'] ?? '',
                            ];
                        });
                    }
                @endphp
                <div class="rounded-2xl bg-[#06111f]/95 border border-obsidian-border hover:border-cyan-500/40 transition shadow-xl overflow-hidden">
                    <!-- CABECERA DEL EQUIPO -->
                    <div class="p-4 sm:p-5 bg-gradient-to-r from-[#071629] to-[#040c17] border-b border-obsidian-border/80 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start sm:items-center gap-3.5">
                            <div class="w-11 h-11 rounded-xl bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shrink-0 shadow-inner">
                                <span class="material-symbols-outlined text-2xl">desktop_windows</span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h2 class="text-sm sm:text-base font-bold text-white tracking-wide uppercase font-mono">
                                        {{ $device->name }}
                                    </h2>
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold uppercase tracking-wider {{ $device->is_active ? 'bg-emerald-950/80 text-emerald-400 border border-emerald-500/50' : 'bg-red-950/80 text-red-400 border border-red-500/50' }}">
                                        {{ $device->is_active ? 'Operativo' : 'Inactivo' }}
                                    </span>
                                    @if($device->asset_tag)
                                        <span class="text-[10px] font-mono text-obsidian-muted bg-slate-900 px-2 py-0.5 rounded border border-obsidian-border">
                                            {{ $device->asset_tag }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] font-mono text-obsidian-muted mt-1 flex items-center gap-2 flex-wrap">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-xs text-cyan-400">domain</span>
                                        {{ $device->site?->name ?? 'Sede Valle Seco' }}
                                    </span>
                                    @if($device->location_detail)
                                        <span>• {{ $device->location_detail }}</span>
                                    @endif
                                    @if($device->serial_number)
                                        <span>• S/N: <code class="text-slate-300">{{ $device->serial_number }}</code></span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- ACCIÓN DEL ACTA DE ASIGNACIÓN -->
                        <div class="flex items-center gap-2 shrink-0">
                            @if($hasActaPdf)
                                <a href="{{ route('admin.my_devices.acta', $acta->id) }}" 
                                   target="_blank" 
                                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500 hover:text-black border border-emerald-500/50 text-emerald-300 font-mono text-xs font-bold transition shadow-sm cursor-pointer group">
                                    <span class="material-symbols-outlined text-sm group-hover:scale-110 transition-transform">picture_as_pdf</span>
                                    <span>Ver Acta Oficial (UINF-4.05)</span>
                                </a>
                            @else
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 border border-obsidian-border text-obsidian-muted text-[11px] font-mono" title="El acta física está en proceso de digitalización y firma">
                                    <span class="material-symbols-outlined text-sm text-amber-400/80">pending_actions</span>
                                    <span>Acta en trámite</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- CUERPO DE DETALLES: ESPECIFICACIONES & PERIFÉRICOS -->
                    <div class="p-4 sm:p-5 grid grid-cols-1 lg:grid-cols-2 gap-5">
                        <!-- COLUMNA 1: FICHA TÉCNICA PRINCIPAL -->
                        <div class="space-y-3">
                            <h3 class="text-xs font-mono uppercase tracking-wider text-cyan-400 font-bold flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">memory</span>
                                Especificaciones del Equipo
                            </h3>
                            <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                                <div class="p-2.5 rounded-xl bg-[#040c17] border border-obsidian-border/70">
                                    <span class="text-[10px] text-obsidian-muted block uppercase">Marca / Modelo</span>
                                    <span class="text-white font-bold block truncate">{{ $device->brand ?? 'Genérico' }} {{ $device->model ?? 'Workstation' }}</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-[#040c17] border border-obsidian-border/70">
                                    <span class="text-[10px] text-obsidian-muted block uppercase">Sistema Operativo</span>
                                    <span class="text-slate-200 block truncate">{{ $device->operating_system ?? 'Linux / Windows' }}</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-[#040c17] border border-obsidian-border/70">
                                    <span class="text-[10px] text-obsidian-muted block uppercase">Dirección IP</span>
                                    <span class="text-cyan-300 font-bold block">{{ $device->ip_address ?? 'DHCP' }}</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-[#040c17] border border-obsidian-border/70">
                                    <span class="text-[10px] text-obsidian-muted block uppercase">Dirección MAC</span>
                                    <span class="text-slate-300 block font-mono text-[11px]">{{ $device->mac_address ?? 'No registrada' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- COLUMNA 2: PERIFÉRICOS ASOCIADOS (HARDWARE COMPLEMENTARIO) -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-mono uppercase tracking-wider text-purple-400 font-bold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">keyboard</span>
                                    Hardware Complementario (Periféricos)
                                </h3>
                                <span class="text-[10px] font-mono text-obsidian-muted">
                                    {{ $peripherals->count() }} {{ $peripherals->count() === 1 ? 'componente' : 'componentes' }}
                                </span>
                            </div>

                            @if($peripherals->isEmpty())
                                <div class="p-3 rounded-xl bg-[#040c17] border border-obsidian-border/50 text-center text-obsidian-muted text-xs font-mono">
                                    <span>Sin periféricos individuales registrados bajo este puesto.</span>
                                </div>
                            @else
                                <div class="space-y-2 max-h-48 overflow-y-auto custom-scroll pr-1">
                                    @foreach($peripherals as $peripheral)
                                        <div class="p-2.5 rounded-xl bg-[#040c17] border border-obsidian-border/70 flex items-center justify-between text-xs font-mono">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="material-symbols-outlined text-purple-400 text-base shrink-0">
                                                    @if(stripos($peripheral->type, 'monitor') !== false || stripos($peripheral->type, 'pantalla') !== false)
                                                        tv
                                                    @elseif(stripos($peripheral->type, 'teclado') !== false)
                                                        keyboard
                                                    @elseif(stripos($peripheral->type, 'mouse') !== false || stripos($peripheral->type, 'raton') !== false)
                                                        mouse
                                                    @elseif(stripos($peripheral->type, 'ups') !== false || stripos($peripheral->type, 'regulador') !== false)
                                                        battery_charging_full
                                                    @else
                                                        hardware
                                                    @endif
                                                </span>
                                                <div class="min-w-0">
                                                    <span class="text-white font-bold block truncate">
                                                        {{ $peripheral->brand ?? 'Marca' }} {{ $peripheral->model ?? $peripheral->type }}
                                                    </span>
                                                    <span class="text-[10px] text-obsidian-muted block uppercase">
                                                        {{ $peripheral->type ?? 'Periférico' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="text-right shrink-0 font-mono text-[11px] text-slate-300">
                                                <span>S/N: <code>{{ $peripheral->serial_number ?? 'S/S' }}</code></span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
