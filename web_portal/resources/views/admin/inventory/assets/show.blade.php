@extends('layouts.admin')

@section('page_title', 'Ficha Técnica • ' . $asset->name)

@section('admin_content')
<div class="space-y-4">
    <!-- NAVEGACIÓN Y BREADCRUMB -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.devices.index') }}" class="px-2.5 py-1 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1 group">
                <span class="material-symbols-outlined text-sm group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                <span>Volver a Equipos &amp; Activos</span>
            </a>
            <span class="text-obsidian-border font-mono text-xs">/</span>
            <span class="font-mono text-xs text-obsidian-muted">Ficha Técnica</span>
        </div>

        <div class="flex items-center gap-2">
            @if($asset->status === 'unauthorized' && (auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage')))
                <button type="button" onclick="openApproveModal({{ $asset->id }}, '{{ addslashes($asset->name) }}', '{{ addslashes($asset->serial_number ?? '') }}')" 
                        class="px-3.5 py-1.5 rounded-xl bg-purple-500 hover:bg-purple-400 text-white font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-purple-500/20">
                    <span class="material-symbols-outlined text-base">verified</span>
                    <span>Aprobar e Incorporar Activo</span>
                </button>
            @endif

            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.manage'))
                <a href="{{ route('admin.inventory.assets.edit', $asset->id) }}" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white font-mono text-xs font-bold transition flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base">edit</span>
                    <span>Editar Metadatos</span>
                </a>
            @endif

            @if(!empty($asset->ip_address) && (auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.deploy')))
                <button type="button" onclick="triggerRemoteAssetAgent('{{ $asset->ip_address }}', '{{ addslashes($asset->name) }}')" class="px-3 py-1.5 rounded-xl bg-cyan-950/80 hover:bg-cyan-900 border border-cyan-500/50 text-cyan-300 font-mono text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs" title="Enviar orden de inventario inmediato al agente GLPI local (Puerto 62354)">
                    <span class="material-symbols-outlined text-base">sync</span>
                    <span>Sondear Agente GLPI</span>
                </button>
            @endif
        </div>
    </div>

    <!-- CABECERA RESUMEN DEL ACTIVO (HERO OBSIDIAN) -->
    <div class="p-5 rounded-2xl bg-gradient-to-r from-[#071322] via-[#0b172a] to-[#040c17] border border-cyan-500/30 shadow-xl relative overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="p-3.5 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 shrink-0">
                    <span class="material-symbols-outlined text-3xl">{{ $asset->type_icon }}</span>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl sm:text-2xl font-black text-white tracking-wide">{{ $asset->display_identity ?? $asset->name }}</h1>
                        @if($asset->asset_tag)
                            <span class="px-2.5 py-0.5 rounded-lg bg-cyan-500/20 border border-cyan-500/40 text-cyan-300 font-mono text-xs font-bold">
                                {{ $asset->asset_tag }}
                            </span>
                        @endif
                        @if($asset->status === 'assigned')
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                Asignado en Uso
                            </span>
                        @elseif($asset->status === 'in_stock')
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                                En Stock (Disponible)
                            </span>
                        @elseif($asset->status === 'unauthorized')
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-500/20 text-purple-300 border border-purple-500/40 flex items-center gap-1.5 animate-pulse">
                                <span class="w-2 h-2 rounded-full bg-purple-400"></span>
                                Descubierto por GLPI (Pendiente Aprobación)
                            </span>
                        @elseif($asset->status === 'maintenance')
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                En Mantenimiento
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-500/20 text-slate-300 border border-slate-500/40">
                                {{ $asset->status_label }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-mono text-obsidian-muted">
                        @if($asset->technical_subtitle && $asset->technical_subtitle !== ($asset->display_identity ?? $asset->name))
                            <div class="flex items-center gap-1 text-cyan-300">
                                <span class="material-symbols-outlined text-sm text-cyan-400">computer</span>
                                <span>Host / Equipo: <code class="text-white bg-slate-900 px-1 rounded">{{ $asset->technical_subtitle }}</code></span>
                            </div>
                        @endif

                        @if($asset->brand || $asset->model)
                            <div class="flex items-center gap-1 text-slate-300">
                                <span class="material-symbols-outlined text-sm text-cyan-400">verified</span>
                                <span>{{ $asset->brand ?? 'Genérico' }} {{ $asset->model ?? '' }}</span>
                            </div>
                        @endif

                        @if($asset->serial_number)
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm text-cyan-400">qr_code</span>
                                <span>Serial: <code class="text-white bg-slate-900 px-1 rounded">{{ $asset->serial_number }}</code></span>
                            </div>
                        @endif

                        @if($asset->monitoredSite)
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm text-cyan-400">location_on</span>
                                <span class="text-white">{{ $asset->monitoredSite->name }}</span>
                                @if($asset->location_detail)
                                    <span class="text-obsidian-muted">({{ $asset->location_detail }})</span>
                                @endif
                            </div>
                        @endif

                        @if($asset->last_glpi_sync_at)
                            <div class="flex items-center gap-1 text-purple-300">
                                <span class="material-symbols-outlined text-sm text-purple-400">sync</span>
                                <span>GLPI Sync: {{ $asset->last_glpi_sync_at->timezone('America/Caracas')->format('d/m/Y h:i A') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- DETALLES DE RESPONSABLE & RED EN HERO -->
            <div class="flex flex-wrap lg:flex-col lg:items-end gap-2 text-xs font-mono border-t lg:border-t-0 border-obsidian-border/60 pt-3 lg:pt-0">
                @if($asset->ip_address)
                    <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/80 border border-slate-700/60">
                        <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                        <span class="text-obsidian-muted">IP:</span>
                        <span class="text-cyan-300 font-bold">{{ $asset->ip_address }}</span>
                        @if($asset->mac_address)
                            <span class="text-slate-500">|</span>
                            <span class="text-obsidian-muted text-[11px]">{{ $asset->mac_address }}</span>
                        @endif
                    </div>
                @endif

                @if($asset->custodian_name)
                    <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-950/30 border border-emerald-500/30 text-emerald-300">
                        <span class="material-symbols-outlined text-sm text-emerald-400">badge</span>
                        <span>Responsable: <strong>{{ $asset->custodian_name }}</strong></span>
                        @if($asset->custodian_department)
                            <span class="text-emerald-400/70 text-[11px]">({{ $asset->custodian_department }})</span>
                        @endif
                    </div>
                @endif

                @if($asset->status === 'assigned' && $asset->has_outdated_assignment)
                    <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-950/50 border border-amber-500/60 text-amber-300 animate-pulse">
                        <span class="material-symbols-outlined text-sm text-amber-400">warning</span>
                        <span class="font-bold">Hardware Modificado • Renovar Acta</span>
                    </div>
                @endif

                @if($asset->is_scan_overdue)
                    <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-950/60 border border-red-500/80 text-red-300 animate-pulse">
                        <span class="material-symbols-outlined text-sm text-red-400">emergency</span>
                        <span class="font-bold">Digitalización Pendiente</span>
                    </div>
                @endif

                @if(!empty($extraData['anydesk_id']))
                    <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-950/30 border border-red-500/40 text-red-300">
                        <span class="material-symbols-outlined text-sm text-red-400">desktop_windows</span>
                        <span>AnyDesk ID: <strong>{{ $extraData['anydesk_id'] }}</strong></span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- PESTAÑAS DE LA FICHA TÉCNICA -->
    <div class="flex items-center gap-2 border-b border-obsidian-border">
        <button type="button" onclick="switchTab('hardware')" id="tab-btn-hardware" class="px-4 py-2 font-mono text-xs font-bold border-b-2 border-cyan-400 text-cyan-400 flex items-center gap-2 transition cursor-pointer">
            <span class="material-symbols-outlined text-base">memory</span>
            <span>Especificaciones de Hardware</span>
        </button>
        <button type="button" onclick="switchTab('history')" id="tab-btn-history" class="px-4 py-2 font-mono text-xs font-bold border-b-2 border-transparent text-obsidian-muted hover:text-white flex items-center gap-2 transition cursor-pointer">
            <span class="material-symbols-outlined text-base">history</span>
            <span>Historial de Hardware</span>
            @if($asset->componentHistories->count() > 0)
                <span class="px-1.5 py-0.2 rounded-full text-[9px] bg-cyan-950 text-cyan-300 border border-cyan-800">
                    {{ $asset->componentHistories->count() }}
                </span>
            @endif
        </button>
        <button type="button" onclick="switchTab('assignments')" id="tab-btn-assignments" class="px-4 py-2 font-mono text-xs font-bold border-b-2 border-transparent text-obsidian-muted hover:text-white flex items-center gap-2 transition cursor-pointer">
            <span class="material-symbols-outlined text-base">assignment_ind</span>
            <span>Responsable & Asignaciones</span>
            @if($asset->has_outdated_assignment || $asset->is_scan_overdue)
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
            @endif
        </button>
        <button type="button" onclick="switchTab('maintenance')" id="tab-btn-maintenance" class="px-4 py-2 font-mono text-xs font-bold border-b-2 border-transparent text-obsidian-muted hover:text-white flex items-center gap-2 transition cursor-pointer">
            <span class="material-symbols-outlined text-base">build</span>
            <span>Mantenimiento & Tickets</span>
        </button>
        <button type="button" onclick="switchTab('timeline')" id="tab-btn-timeline" class="px-4 py-2 font-mono text-xs font-bold border-b-2 border-transparent text-obsidian-muted hover:text-white flex items-center gap-2 transition cursor-pointer">
            <span class="material-symbols-outlined text-base">history_edu</span>
            <span>Trazabilidad Forense</span>
            @if(isset($timeline) && $timeline->count() > 0)
                <span class="px-1.5 py-0.2 rounded-full text-[9px] bg-cyan-950 text-cyan-300 border border-cyan-800">
                    {{ $timeline->count() }}
                </span>
            @endif
        </button>
    </div>

    <!-- CONTENIDO PESTAÑA 1: HARDWARE Y ESPECIFICACIONES -->
    <div id="tab-content-hardware" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- TARJETA: PROCESADOR (CPU) -->
            <div class="p-4 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-obsidian-border/60">
                    <div class="flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400">
                            <span class="material-symbols-outlined text-xl">memory</span>
                        </span>
                        <h3 class="font-bold text-white text-xs font-mono uppercase">Procesador (CPU)</h3>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-cyan-950 text-cyan-300 border border-cyan-800">
                        {{ $cpus->count() }} Socket(s)
                    </span>
                </div>

                @forelse($cpus as $cpu)
                    <div class="space-y-1.5 text-xs font-mono">
                        <div class="text-white font-bold">{{ $cpu->name }}</div>
                        @if($cpu->capacity)
                            <div class="text-obsidian-cyan text-[11px] flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs">speed</span>
                                {{ $cpu->capacity }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-xs font-mono text-obsidian-muted italic py-3 text-center">
                        No se detectó información de CPU.
                    </div>
                @endforelse
            </div>

            <!-- TARJETA: MEMORIA RAM -->
            <div class="p-4 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-obsidian-border/60">
                    <div class="flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-purple-500/10 text-purple-400">
                            <span class="material-symbols-outlined text-xl">developer_board</span>
                        </span>
                        <h3 class="font-bold text-white text-xs font-mono uppercase">Memoria RAM</h3>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-purple-950 text-purple-300 border border-purple-800">
                        Total
                    </span>
                </div>

                @forelse($rams as $ram)
                    <div class="space-y-1.5 text-xs font-mono">
                        <div class="text-2xl font-black text-purple-400 font-mono">{{ $ram->capacity }}</div>
                        <div class="text-[11px] text-obsidian-muted">
                            {{ $ram->name }}
                            @if($ram->brand) • {{ $ram->brand }} @endif
                            @if($ram->serial_number) • S/N: {{ $ram->serial_number }} @endif
                        </div>
                    </div>
                @empty
                    <div class="text-xs font-mono text-obsidian-muted italic py-3 text-center">
                        Sin datos de memoria RAM.
                    </div>
                @endforelse
            </div>

            <!-- TARJETA: SISTEMA OPERATIVO & KERNEL -->
            <div class="p-4 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-obsidian-border/60">
                    <div class="flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400">
                            <span class="material-symbols-outlined text-xl">terminal</span>
                        </span>
                        <h3 class="font-bold text-white text-xs font-mono uppercase">Sistema Operativo</h3>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-emerald-950 text-emerald-300 border border-emerald-800">
                        OS
                    </span>
                </div>

                <div class="space-y-2 text-xs font-mono">
                    <div>
                        <div class="text-[11px] text-obsidian-muted">Distribución / Versión</div>
                        <div class="text-white font-bold">{{ $asset->operating_system ?? 'No reportado' }}</div>
                    </div>
                    @if($asset->os_kernel)
                    <div>
                        <div class="text-[11px] text-obsidian-muted">Versión de Kernel</div>
                        <div class="text-emerald-300 font-mono text-[11px]">{{ $asset->os_kernel }}</div>
                    </div>
                    @endif
                    @if($extraData['software_count'] > 0)
                    <div class="pt-1 border-t border-obsidian-border/40 text-[11px] text-obsidian-muted flex items-center justify-between">
                        <span>Software instalado reportado:</span>
                        <span class="text-cyan-400 font-bold">{{ $extraData['software_count'] }} paquetes</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- TARJETA EXTENDIDA: ALMACENAMIENTO Y DISCOS FÍSICOS -->
        <div class="p-4 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-obsidian-border/60">
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400">
                        <span class="material-symbols-outlined text-xl">hard_drive</span>
                    </span>
                    <h3 class="font-bold text-white text-xs font-mono uppercase">Unidades de Almacenamiento Físico (Discos / SSD)</h3>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-amber-950 text-amber-300 border border-amber-800">
                    {{ $disks->count() }} Unidad(es)
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @forelse($disks as $disk)
                    <div class="p-3 rounded-xl bg-[#040b15] border border-obsidian-border space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-white text-xs font-mono">{{ $disk->name }}</span>
                            @if($disk->capacity)
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                    {{ $disk->capacity }}
                                </span>
                            @endif
                        </div>
                        <div class="text-[11px] text-obsidian-muted font-mono space-y-0.5">
                            @if($disk->serial_number)
                                <div>Serial Físico: <code class="text-slate-300">{{ $disk->serial_number }}</code></div>
                            @endif
                            @if($disk->model)
                                <div>Modelo: {{ $disk->model }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 text-xs font-mono text-obsidian-muted italic py-3 text-center">
                        No se detectaron unidades de almacenamiento físico registradas.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- TARJETA EXTENDIDA: INTERFACES DE RED (MULTI-NIC) -->
        <div class="p-4 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-obsidian-border/60">
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400">
                        <span class="material-symbols-outlined text-xl">lan</span>
                    </span>
                    <h3 class="font-bold text-white text-xs font-mono uppercase">Interfaces de Red (Multi-NIC)</h3>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-cyan-950 text-cyan-300 border border-cyan-800">
                    {{ $interfaces->count() }} Interfaz(ces)
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @forelse($interfaces as $iface)
                    <div class="p-3 rounded-xl bg-[#040b15] border {{ $iface->is_primary ? 'border-cyan-500/50 ring-1 ring-cyan-500/20' : 'border-obsidian-border' }} space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-white text-xs font-mono flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full {{ $iface->status === 'up' ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                                {{ $iface->interface_name }}
                            </span>
                            @if($iface->is_primary)
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/40">
                                    Principal
                                </span>
                            @endif
                        </div>
                        <div class="text-[11px] text-obsidian-muted font-mono space-y-0.5">
                            @if($iface->ip_address)
                                <div class="flex items-center justify-between">
                                    <span>IP:</span>
                                    <span class="text-cyan-300 font-bold">{{ $iface->ip_address }}</span>
                                </div>
                            @endif
                            @if($iface->mac_address)
                                <div class="flex items-center justify-between">
                                    <span>MAC:</span>
                                    <span class="text-slate-300">{{ $iface->mac_address }}</span>
                                </div>
                            @endif
                            @if($iface->interface_type)
                                <div class="flex items-center justify-between">
                                    <span>Tipo:</span>
                                    <span class="text-slate-400 uppercase text-[10px]">{{ $iface->interface_type }}</span>
                                </div>
                            @endif
                            @if($iface->speed)
                                <div class="flex items-center justify-between">
                                    <span>Velocidad:</span>
                                    <span class="text-slate-400 text-[10px]">{{ $iface->speed }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 text-xs font-mono text-obsidian-muted italic py-3 text-center">
                        No se detectaron interfaces de red registradas.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- PERIFÉRICOS, VIDEO & ACCESO REMOTO -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- GRÁFICOS Y DISPOSITIVOS USB -->
            <div class="p-4 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border space-y-3">
                <div class="flex items-center gap-2 pb-2 border-b border-obsidian-border/60">
                    <span class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400">
                        <span class="material-symbols-outlined text-xl">usb</span>
                    </span>
                    <h3 class="font-bold text-white text-xs font-mono uppercase">Controladores de Video & Periféricos USB</h3>
                </div>

                <div class="space-y-2 text-xs font-mono">
                    @if($extraData['video_gpu'])
                        <div>
                            <div class="text-[11px] text-obsidian-muted">Controlador Gráfico (GPU)</div>
                            <div class="text-cyan-300 font-bold">{{ $extraData['video_gpu'] }}</div>
                        </div>
                    @endif

                    @if(!empty($extraData['usb_devices']))
                        <div>
                            <div class="text-[11px] text-obsidian-muted mb-1.5">Dispositivos USB Detectados</div>
                            <div class="space-y-1">
                                @foreach($extraData['usb_devices'] as $usb)
                                    <div class="flex items-center gap-2 px-2.5 py-1 rounded bg-[#040b15] border border-obsidian-border text-[11px] text-slate-300">
                                        <span class="material-symbols-outlined text-xs text-obsidian-muted">cable</span>
                                        <span>{{ $usb['name'] ?? ($usb['caption'] ?? 'Dispositivo USB') }}</span>
                                        @if(!empty($usb['manufacturer']))
                                            <span class="text-obsidian-muted/70">({{ $usb['manufacturer'] }})</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="text-obsidian-muted italic text-[11px]">No se reportaron periféricos USB en el último escaneo.</div>
                    @endif
                </div>
            </div>

            <!-- DETALLES DE AUDITORÍA & NOTAS -->
            <div class="p-4 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border space-y-3">
                <div class="flex items-center gap-2 pb-2 border-b border-obsidian-border/60">
                    <span class="p-2 rounded-xl bg-purple-500/10 text-purple-400">
                        <span class="material-symbols-outlined text-xl">description</span>
                    </span>
                    <h3 class="font-bold text-white text-xs font-mono uppercase">Observaciones & Metadatos</h3>
                </div>

                <div class="space-y-3 text-xs font-mono">
                    <div>
                        <div class="text-[11px] text-obsidian-muted">Notas Internas</div>
                        <div class="text-slate-300 bg-[#040b15] p-3 rounded-xl border border-obsidian-border min-h-[60px] whitespace-pre-line">
                            {{ $asset->notes ?: 'Sin notas registradas para este activo.' }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-[11px]">
                        <div>
                            <span class="text-obsidian-muted">Fecha de Creación:</span>
                            <div class="text-slate-300 font-bold">{{ $asset->created_at->timezone('America/Caracas')->format('d/m/Y h:i A') }}</div>
                        </div>
                        <div>
                            <span class="text-obsidian-muted">Última Modificación:</span>
                            <div class="text-slate-300 font-bold">{{ $asset->updated_at->timezone('America/Caracas')->format('d/m/Y h:i A') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CONTENIDO PESTAÑA 2: HISTORIAL INMUTABLE DE COMPONENTES -->
    <div id="tab-content-history" class="space-y-4 hidden">
        <div class="p-4 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-obsidian-border">
                <div>
                    <h3 class="font-bold text-white text-sm font-mono flex items-center gap-2">
                        <span class="material-symbols-outlined text-cyan-400">shield_lock</span>
                        Bitácora Inmutable de Modificaciones de Hardware
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono">Registro forense automático de cambios en memoria RAM, procesador y unidades de almacenamiento</p>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-cyan-950 text-cyan-300 border border-cyan-800">
                    {{ $asset->componentHistories->count() }} Eventos
                </span>
            </div>

            <div class="space-y-2">
                @forelse($asset->componentHistories as $hist)
                    <div class="p-3 rounded-xl bg-[#040b15] border border-obsidian-border flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs font-mono">
                        <div class="flex items-start gap-3">
                            <span class="p-2 rounded-lg {{ $hist->action === 'added' ? 'bg-emerald-500/10 text-emerald-400' : ($hist->action === 'removed' ? 'bg-red-500/10 text-red-400' : 'bg-amber-500/10 text-amber-400') }} shrink-0">
                                <span class="material-symbols-outlined text-base">
                                    {{ $hist->action === 'added' ? 'add_circle' : ($hist->action === 'removed' ? 'remove_circle' : 'change_circle') }}
                                </span>
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-white uppercase">{{ $hist->action }}</span>
                                    <span class="text-obsidian-muted">•</span>
                                    <span class="text-cyan-400 font-bold uppercase">{{ $hist->component_type }}</span>
                                    <span class="text-obsidian-muted">•</span>
                                    <span class="text-slate-400">{{ $hist->reason ?? 'Sin motivo especificado' }}</span>
                                </div>

                                <div class="mt-1 text-[11px] text-slate-300">
                                    @if($hist->old_value && $hist->new_value)
                                        <span class="text-red-400 line-through">{{ json_encode($hist->old_value) }}</span>
                                        <span class="text-obsidian-muted mx-1">➔</span>
                                        <span class="text-emerald-400 font-bold">{{ json_encode($hist->new_value) }}</span>
                                    @elseif($hist->new_value)
                                        <span class="text-emerald-400 font-bold">Instalado: {{ json_encode($hist->new_value) }}</span>
                                    @elseif($hist->old_value)
                                        <span class="text-red-400 line-through">Retirado: {{ json_encode($hist->old_value) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="text-[11px] text-obsidian-muted shrink-0 text-right">
                            <div>{{ $hist->recorded_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') }}</div>
                            <div class="text-[10px] text-slate-500">ID #{{ $hist->id }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-10 text-obsidian-muted">
                        <span class="material-symbols-outlined text-3xl mb-1 text-obsidian-border block">verified_user</span>
                        <p class="text-xs">No se han registrado modificaciones de hardware en este activo.</p>
                        <p class="text-[10px] text-slate-500 mt-0.5">Cualquier cambio detectado por el agente GLPI se registrará aquí automáticamente.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- CONTENIDO PESTAÑA 3: RESPONSABLE Y ASIGNACIONES -->
    <div id="tab-content-assignments" class="space-y-4 hidden">
        <div class="p-4 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-obsidian-border">
                <div>
                    <h3 class="font-bold text-white text-sm font-mono flex items-center gap-2">
                        <span class="material-symbols-outlined text-emerald-400">badge</span>
                        Responsable Asignado & Historial de Movilizaciones
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono">Actas UINF-4.05, asignación formal y trazabilidad de resguardo</p>
                </div>
                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                <a href="{{ route('admin.inventory.assignments.create', ['asset_id' => $asset->id]) }}" class="px-3 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                    <span class="material-symbols-outlined text-sm">note_add</span>
                    <span>Emitir Nueva Acta</span>
                </a>
                @endif
            </div>

            @if($asset->status === 'assigned' && $asset->has_outdated_assignment)
                <div class="p-4 rounded-xl bg-amber-950/30 border border-amber-500/50 mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-2xl text-amber-400 shrink-0 mt-0.5">warning</span>
                        <div>
                            <div class="text-xs font-mono font-bold text-amber-300 uppercase">Hardware Modificado Posterior al Acta Activa</div>
                            <p class="text-xs font-mono text-slate-300 mt-0.5">
                                Se detectaron sustituciones o cambios de periféricos/componentes posteriores a la emisión del acta actual. Para mantener la validez legal y técnica, debe emitirse una nueva planilla UINF-4.05.
                            </p>
                        </div>
                    </div>
                    @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                    <a href="{{ route('admin.inventory.assignments.create', ['asset_id' => $asset->id]) }}" class="px-3 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-mono text-xs font-bold transition shrink-0 flex items-center gap-1.5 self-start sm:self-center shadow-lg shadow-amber-500/20">
                        <span class="material-symbols-outlined text-sm">assignment_return</span>
                        <span>Renovar Acta Ahora</span>
                    </a>
                    @endif
                </div>
            @endif

            @if($asset->custodian_name)
                <div class="p-4 rounded-xl bg-emerald-950/20 border border-emerald-500/40 mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-mono text-emerald-400 uppercase font-bold">Responsable Directo Asignado</div>
                        <div class="text-lg font-bold text-white">{{ $asset->custodian_name }}</div>
                        <div class="text-xs font-mono text-obsidian-muted">
                            {{ $asset->custodian_department ?? 'Departamento no especificado' }}
                            @if($asset->custodian_id_number) • C.I. {{ $asset->custodian_id_number }} @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($asset->activeAssignment)
                            <span class="px-2.5 py-1 rounded-xl text-xs font-mono font-bold {{ $asset->activeAssignment->scan_compliance_badge_class }}">
                                {{ $asset->activeAssignment->scan_compliance_label }}
                            </span>
                        @endif
                        <span class="px-3 py-1 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 font-mono text-xs font-bold">
                            Asignado
                        </span>
                    </div>
                </div>
            @else
                <div class="p-4 rounded-xl bg-cyan-950/20 border border-cyan-500/40 mb-4 flex items-center justify-between">
                    <div>
                        <div class="text-xs font-mono text-cyan-400 uppercase font-bold">Estado del Activo</div>
                        <div class="text-sm font-bold text-white">Disponible en Stock / Almacén</div>
                        <div class="text-xs font-mono text-obsidian-muted">El equipo no posee un responsable directo asignado.</div>
                    </div>
                </div>
            @endif

            <div class="space-y-3 mt-4">
                <h4 class="text-xs font-mono font-bold text-obsidian-muted uppercase flex items-center justify-between">
                    <span>Historial Inmutable de Actas y Movimientos</span>
                    <span class="text-[10px] text-slate-500">{{ $asset->assignments->count() }} registro(s)</span>
                </h4>
                @forelse($asset->assignments as $asg)
                    <div class="p-3.5 rounded-xl bg-[#040b15] border {{ $asg->status === 'active' ? 'border-cyan-500/40 bg-cyan-950/10' : 'border-obsidian-border' }} flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs font-mono">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-white">{{ $asg->movement_type_label }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-800 text-cyan-300 border border-slate-700">
                                    {{ $asg->assignment_code }}
                                </span>
                                @if($asg->status === 'active')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 font-bold">ACTIVA</span>
                                @elseif($asg->status === 'superseded')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-500/20 border border-amber-500/40 text-amber-300 font-bold">HISTÓRICA (RENOVADA)</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-800 text-slate-400 border border-slate-700 font-bold">{{ strtoupper($asg->status) }}</span>
                                @endif
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $asg->scan_compliance_badge_class }}">
                                    {{ $asg->scan_compliance_label }}
                                </span>
                            </div>
                            <div class="text-[11px] text-obsidian-muted flex flex-wrap items-center gap-x-3">
                                <span>Responsable: <strong class="text-slate-300">{{ $asg->custodian_name }}</strong></span>
                                @if($asg->custodian_id_number)
                                    <span>• C.I: {{ $asg->custodian_id_number }}</span>
                                @endif
                                <span>• Fecha: {{ $asg->assigned_at ? $asg->assigned_at->format('d/m/Y') : $asg->created_at->format('d/m/Y') }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap shrink-0">
                            <!-- Ver Detalle Acta -->
                            <a href="{{ route('admin.inventory.assignments.show', $asg->id) }}" class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-cyan-300 text-xs font-bold transition flex items-center gap-1 border border-slate-700">
                                <span class="material-symbols-outlined text-sm">description</span>
                                <span>Detalle Acta</span>
                            </a>
                            <!-- Ver PDF UINF-4.05 -->
                            <a href="{{ route('admin.inventory.assignments.pdf', $asg->id) }}" target="_blank" class="px-2.5 py-1.5 rounded-lg bg-cyan-500/20 border border-cyan-500/40 text-cyan-300 text-xs font-bold hover:bg-cyan-500 hover:text-black transition flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">print</span>
                                <span>PDF</span>
                            </a>

                            <!-- Ver o Subir Acta Escaneada Firmada -->
                            @if($asg->document_pdf_path)
                                <a href="{{ route('admin.inventory.assignments.view-scan', $asg->id) }}" target="_blank" class="px-2.5 py-1.5 rounded-lg bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-xs font-bold hover:bg-emerald-500 hover:text-black transition flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">verified</span>
                                    <span>Ver Escaneo Firmado</span>
                                </a>
                            @else
                                <a href="{{ route('admin.inventory.assignments.show', $asg->id) }}" class="px-2.5 py-1.5 rounded-lg bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs font-bold hover:bg-amber-500 hover:text-black transition flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">upload_file</span>
                                    <span>Subir Escaneo</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-xs font-mono text-obsidian-muted italic py-6 text-center">
                        No hay actas de entrega registradas aún para este equipo.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- CONTENIDO PESTAÑA 4: MANTENIMIENTO Y TICKETS -->
    <div id="tab-content-maintenance" class="space-y-4 hidden">
        <div class="p-4 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-obsidian-border">
                <div>
                    <h3 class="font-bold text-white text-sm font-mono flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-400">build</span>
                        Órdenes de Trabajo y Mantenimiento Técnico
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono">Historial de intervenciones preventivas y correctivas</p>
                </div>
            </div>

            <div class="space-y-2">
                @forelse($asset->maintenanceTickets as $ticket)
                    <div class="p-3 rounded-xl bg-[#040b15] border border-obsidian-border flex items-center justify-between text-xs font-mono">
                        <div>
                            <span class="font-bold text-white">{{ $ticket->title }}</span>
                            <div class="text-[11px] text-obsidian-muted">{{ $ticket->type_label }} • Estado: {{ $ticket->status_label }}</div>
                        </div>
                        <div class="text-right text-[11px] text-obsidian-muted">
                            {{ $ticket->created_at->format('d/m/Y') }}
                        </div>
                    </div>
                @empty
                    <div class="text-xs font-mono text-obsidian-muted italic py-6 text-center">
                        No se registran tickets de mantenimiento abiertos para este equipo.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- CONTENIDO PESTAÑA 5: TRAZABILIDAD FORENSE & LÍNEA DE TIEMPO CRONOLÓGICA -->
    <div id="tab-content-timeline" class="space-y-4 hidden">
        <div class="p-5 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-obsidian-border/60 gap-3">
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400">
                        <span class="material-symbols-outlined text-xl">history_edu</span>
                    </span>
                    <div>
                        <h3 class="font-bold text-white text-xs font-mono uppercase tracking-wider">Línea de Tiempo Cronológica Unificada</h3>
                        <p class="text-[11px] font-mono text-obsidian-muted">Consolidación de actas UINF-4.05, drifts de hardware, mantenimientos y registros de auditoría</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.inventory.audit.index', ['asset_id' => $asset->id]) }}" class="px-2.5 py-1 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 hover:bg-cyan-900 text-xs font-mono font-bold transition flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">open_in_new</span>
                        <span>Ver en Consola Global</span>
                    </a>
                </div>
            </div>

            <!-- CONTENEDOR TIMELINE -->
            <div class="relative pl-6 sm:pl-8 space-y-6 before:content-[''] before:absolute before:left-2.5 sm:before:left-3.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-gradient-to-b before:from-cyan-500 before:via-purple-500/50 before:to-transparent">
                @forelse($timeline as $event)
                    <div class="relative group">
                        <!-- PUNTO CONECTOR -->
                        <div class="absolute -left-6 sm:-left-8 top-1 w-5 h-5 rounded-full bg-[#071321] border-2 border-cyan-400 group-hover:scale-125 transition-transform flex items-center justify-center shadow-lg shadow-cyan-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                        </div>

                        <!-- TARJETA DEL EVENTO -->
                        <div class="p-4 rounded-xl bg-[#040b15] border border-obsidian-border hover:border-cyan-500/40 transition-all space-y-2">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono {{ $event['badge_class'] }} flex items-center gap-1">
                                        <span class="material-symbols-outlined text-xs">{{ $event['icon'] }}</span>
                                        <span>{{ $event['type_label'] }}</span>
                                    </span>
                                    <span class="font-bold text-white text-xs font-mono">{{ $event['title'] }}</span>
                                </div>
                                <div class="text-[10px] font-mono text-obsidian-muted flex items-center gap-1.5 whitespace-nowrap">
                                    <span class="text-cyan-400 font-bold">{{ $event['timestamp_formatted'] }}</span>
                                    <span>({{ $event['time_ago'] }})</span>
                                </div>
                            </div>

                            <p class="text-xs text-slate-300 font-mono">{{ $event['description'] }}</p>

                            <div class="flex flex-wrap items-center justify-between pt-2 border-t border-obsidian-border/40 text-[10.5px] font-mono text-obsidian-muted gap-2">
                                <div class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs text-obsidian-muted">person</span>
                                    <span>Registrado por: <strong class="text-white">{{ $event['user_name'] }}</strong></span>
                                </div>

                                <div class="flex items-center gap-2">
                                    @if(!empty($event['details']['show_url']))
                                        <a href="{{ $event['details']['show_url'] }}" class="text-cyan-400 hover:underline flex items-center gap-0.5">
                                            <span>Ver Detalle</span>
                                            <span class="material-symbols-outlined text-[11px]">arrow_forward</span>
                                        </a>
                                    @endif
                                    @if(!empty($event['details']['pdf_url']))
                                        <a href="{{ $event['details']['pdf_url'] }}" target="_blank" class="text-emerald-400 hover:underline flex items-center gap-0.5">
                                            <span class="material-symbols-outlined text-[11px]">picture_as_pdf</span>
                                            <span>Acta PDF</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-xs font-mono text-obsidian-muted italic py-8 text-center">
                        No se registran eventos cronológicos de auditoría para este activo.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- SCRIPT DE PESTAÑAS Y MODAL -->
<script>
    function switchTab(tabName) {
        const tabs = ['hardware', 'history', 'assignments', 'maintenance', 'timeline'];
        tabs.forEach(t => {
            const btn = document.getElementById('tab-btn-' + t);
            const content = document.getElementById('tab-content-' + t);
            if (t === tabName) {
                btn.className = 'px-4 py-2 font-mono text-xs font-bold border-b-2 border-cyan-400 text-cyan-400 flex items-center gap-2 transition cursor-pointer';
                content.classList.remove('hidden');
            } else {
                btn.className = 'px-4 py-2 font-mono text-xs font-bold border-b-2 border-transparent text-obsidian-muted hover:text-white flex items-center gap-2 transition cursor-pointer';
                content.classList.add('hidden');
            }
        });
    }

    function openApproveModal(assetId, name, serial) {
        document.getElementById('approveForm').action = '/admin/inventory/assets/' + assetId + '/approve';
        document.getElementById('modalAssetName').value = name + (serial ? ' (' + serial + ')' : '');
        document.getElementById('approveModal').classList.remove('hidden');
    }

    function closeApproveModal() {
        document.getElementById('approveModal').classList.add('hidden');
    }

    async function triggerRemoteAssetAgent(ip, name) {
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
</script>

<!-- MODAL DE APROBACIÓN REUTILIZABLE -->
<!-- MODAL DE APROBACIÓN REUTILIZABLE -->
<div id="approveModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-[#0b1322] border border-cyan-500/40 rounded-2xl max-w-md w-full flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden shadow-2xl relative">
        <div class="p-4 px-5 border-b border-obsidian-border flex items-center justify-between shrink-0 bg-[#061527]">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-400">verified</span>
                Aprobar e Incorporar Activo
            </h3>
            <button type="button" onclick="closeApproveModal()" class="text-obsidian-muted hover:text-white transition p-1">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="approveForm" method="POST" action="" class="flex flex-col flex-1 min-h-0 overflow-hidden m-0">
            @csrf
            <div class="p-4 px-5 space-y-3.5 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Nombre del Equipo</label>
                    <input type="text" id="modalAssetName" disabled class="w-full px-3 py-1.5 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-slate-300">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Código / Tag Patrimonial <span class="text-cyan-400">*</span></label>
                    <input type="text" name="asset_tag" required placeholder="Ej. PC-VS-042" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Estado de Incorporación</label>
                    <select name="target_status" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                        <option value="in_stock">En Stock (Disponible en Almacén)</option>
                        <option value="assigned">Asignado a Responsable Directo</option>
                        <option value="maintenance">En Mantenimiento Técnico</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Responsable Asignado (Opcional)</label>
                    <input type="text" name="custodian_name" placeholder="Nombre y Apellido del trabajador" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div class="p-3.5 px-5 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0 bg-[#061527]">
                <button type="button" onclick="closeApproveModal()" class="px-3 py-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-1.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20">
                    <span class="material-symbols-outlined text-base">check_circle</span>
                    <span>Confirmar Aprobación</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
