@extends('layouts.admin')

@section('page_title', 'Detalle Acta ' . $assignment->assignment_code . ' (UINF-4.05)')

@section('admin_content')
<div class="space-y-6">

    <!-- ENCABEZADO Y ACCIONES DE IMPRESIÓN / DESCARGA -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-obsidian-panel/40 border border-obsidian-border p-6 rounded-2xl backdrop-blur-md">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.devices.index') }}" class="text-xs font-mono text-obsidian-cyan hover:underline">&larr; Equipos &amp; Activos</a>
                <span class="text-obsidian-muted">&bull;</span>
                <a href="{{ route('admin.inventory.assignments.index') }}" class="text-xs font-mono text-obsidian-muted hover:text-white hover:underline">Mesa de Asignaciones</a>
                <span class="text-obsidian-muted">&bull;</span>
                <span class="text-xs font-mono text-white/80">Código de Asignación: <strong class="text-white">{{ $assignment->assignment_code }}</strong></span>
            </div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <span class="material-symbols-outlined text-obsidian-cyan text-3xl">verified</span>
                ACTA DE ASIGNACION
            </h1>
            <p class="text-sm text-obsidian-muted mt-1">
                Emitida el {{ $assignment->assigned_at ? $assignment->assigned_at->format('d/m/Y H:i') : 'N/A' }} por <strong class="text-white">{{ $assignment->deliveredByUser->name ?? 'Administrador' }}</strong>
            </p>
        </div>

        <!-- BOTONES DE ACCIÓN RÁPIDA (SOLO ICONOS CON TOOLTIP) -->
        <div class="flex items-center gap-2">
            <!-- Ver Hoja Física Escaneada si existe -->
            @if($assignment->has_signed_scan)
            <a href="{{ route('admin.inventory.assignments.view-scan', $assignment->id) }}" target="_blank" 
               class="w-10 h-10 rounded-xl bg-purple-500 hover:bg-purple-400 text-black flex items-center justify-center transition shadow-lg shadow-purple-500/25 cursor-pointer" 
               title="Ver Acta Física Escaneada y Sellada (PDF)">
                <span class="material-symbols-outlined text-xl">file_present</span>
            </a>
            @elseif($assignment->movement_type === 'assignment' && (auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage')))
            <button type="button" onclick="openUploadScanModal({{ $assignment->id }}, '{{ addslashes($assignment->assignment_code) }}', '{{ addslashes($assignment->custodian_name) }}')" 
               class="w-10 h-10 rounded-xl bg-amber-500 hover:bg-amber-400 text-black flex items-center justify-center transition shadow-lg shadow-amber-500/25 cursor-pointer" 
               title="Subir Acta Escaneada Firmada">
                <span class="material-symbols-outlined text-xl">upload_file</span>
            </button>
            @endif

            <!-- Imprimir Acta UINF-4.05 -->
            <a href="{{ route('admin.inventory.assignments.pdf', $assignment->id) }}" target="_blank" 
               class="w-10 h-10 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black flex items-center justify-center transition shadow-lg shadow-emerald-500/25 cursor-pointer" 
               title="Imprimir Acta">
                <span class="material-symbols-outlined text-xl">print</span>
            </a>

            <!-- Descargar en PDF el Acta -->
            <a href="{{ route('admin.inventory.assignments.pdf', ['assignment' => $assignment->id, 'download' => 1]) }}" 
               class="w-10 h-10 rounded-xl bg-obsidian-dark hover:bg-emerald-950/60 border border-emerald-500/50 text-emerald-400 hover:text-emerald-300 flex items-center justify-center transition shadow-sm cursor-pointer" 
               title="Descargar en PDF el Acta">
                <span class="material-symbols-outlined text-xl">download</span>
            </a>

            <!-- Imprimir Movilización de Equipo -->
            <a href="{{ route('admin.inventory.assignments.pdf-movilizacion', $assignment->id) }}" target="_blank" 
               class="w-10 h-10 rounded-xl bg-amber-500 hover:bg-amber-400 text-black flex items-center justify-center transition shadow-lg shadow-amber-500/25 cursor-pointer" 
               title="Imprimir Movilizacion de Equipo">
                <span class="material-symbols-outlined text-xl">local_shipping</span>
            </a>

            <!-- Descargar PDF Movilización -->
            <a href="{{ route('admin.inventory.assignments.pdf-movilizacion', ['assignment' => $assignment->id, 'download' => 1]) }}" 
               class="w-10 h-10 rounded-xl bg-obsidian-dark hover:bg-amber-950/60 border border-amber-500/50 text-amber-400 hover:text-amber-300 flex items-center justify-center transition shadow-sm cursor-pointer" 
               title="Descargar PDF Movilizacion">
                <span class="material-symbols-outlined text-xl">download</span>
            </a>
        </div>
    </div>

    <!-- BANNER DE SOPORTE DIGITAL -->
    @if($assignment->movement_type === 'assignment')
        @if($assignment->has_signed_scan)
            <div class="p-4 rounded-2xl bg-emerald-950/30 border border-emerald-500/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-md">
                <div class="flex items-center gap-3">
                    <span class="p-2 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        <span class="material-symbols-outlined text-xl">verified</span>
                    </span>
                    <div>
                        <h4 class="text-sm font-bold text-white flex items-center gap-2">
                            <span>Soporte Digital Validado</span>
                            <span class="px-2 py-0.2 rounded-full text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">VALIDADA</span>
                        </h4>
                        <p class="text-xs text-obsidian-muted font-mono mt-0.5">
                            El acta física firmada por el funcionario y sellada por la unidad se encuentra digitalizada y resguardada.
                        </p>
                    </div>
                </div>
                <a href="{{ route('admin.inventory.assignments.view-scan', $assignment->id) }}" target="_blank" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black font-mono text-xs font-bold transition shadow-sm cursor-pointer whitespace-nowrap">
                    <span class="material-symbols-outlined text-base">visibility</span>
                    <span>Ver Documento Escaneado</span>
                </a>
            </div>
        @elseif($assignment->scan_compliance_status === 'overdue')
            <div class="p-4 rounded-2xl bg-rose-950/40 border border-rose-500/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xl animate-pulse">
                <div class="flex items-center gap-3">
                    <span class="p-2 rounded-xl bg-rose-500/20 text-rose-300 border border-rose-500/40">
                        <span class="material-symbols-outlined text-xl">emergency</span>
                    </span>
                    <div>
                        <h4 class="text-sm font-bold text-white flex items-center gap-2">
                            <span>Alerta de Auditoría: Documento Digital Pendiente</span>
                            <span class="px-2 py-0.2 rounded-full text-[10px] font-mono font-bold bg-rose-500/30 text-rose-200 border border-rose-500/50">PENDIENTE</span>
                        </h4>
                        <p class="text-xs text-rose-200/80 font-mono mt-0.5">
                            Han transcurrido <strong>{{ $assignment->days_since_assigned }} días</strong> continuos desde la entrega sin que se haya digitalizado la hoja firmada.
                        </p>
                    </div>
                </div>
                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                <button type="button" onclick="openUploadScanModal({{ $assignment->id }}, '{{ addslashes($assignment->assignment_code) }}', '{{ addslashes($assignment->custodian_name) }}')" 
                   class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-rose-500 hover:bg-rose-400 text-white font-mono text-xs font-bold transition shadow-lg shadow-rose-950/60 cursor-pointer whitespace-nowrap">
                    <span class="material-symbols-outlined text-base">upload_file</span>
                    <span>Subir Escaneo Inmediatamente</span>
                </button>
                @endif
            </div>
        @else
            <div class="p-4 rounded-2xl bg-amber-950/25 border border-amber-500/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-md">
                <div class="flex items-center gap-3">
                    <span class="p-2 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/40">
                        <span class="material-symbols-outlined text-xl">schedule</span>
                    </span>
                    <div>
                        <h4 class="text-sm font-bold text-white flex items-center gap-2">
                            <span>Pendiente de Digitalización de Hoja Física</span>
                            <span class="px-2 py-0.2 rounded-full text-[10px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">EN TRÁMITE</span>
                        </h4>
                        <p class="text-xs text-obsidian-muted font-mono mt-0.5">
                            Han transcurrido <strong>{{ $assignment->days_since_assigned }} días</strong> desde la entrega formal del equipo.
                        </p>
                    </div>
                </div>
                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('inventory.assign') || auth()->user()->hasPermission('inventory.manage'))
                <button type="button" onclick="openUploadScanModal({{ $assignment->id }}, '{{ addslashes($assignment->assignment_code) }}', '{{ addslashes($assignment->custodian_name) }}')" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-mono text-xs font-bold transition shadow-sm cursor-pointer whitespace-nowrap">
                    <span class="material-symbols-outlined text-base">upload_file</span>
                    <span>Adjuntar Escaneo Firmado</span>
                </button>
                @endif
            </div>
        @endif
    @endif

    <!-- TARJETAS DE RESUMEN GENERAL -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Responsable / Trabajador -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl">
            <div class="text-xs font-mono uppercase text-obsidian-cyan font-bold flex items-center gap-1.5 mb-3">
                <span class="material-symbols-outlined text-base">person</span>
                Responsable Asignado
            </div>
            <div class="text-lg font-bold text-white">{{ $assignment->custodian_name }}</div>
            <div class="text-xs text-obsidian-muted font-mono mt-1">C.I: {{ $assignment->custodian_id_number ?? 'N/A' }}</div>
            <div class="text-xs text-obsidian-muted mt-0.5">Gerencia: <strong class="text-white">{{ $assignment->custodian_department ?? 'N/A' }}</strong></div>
            @if($assignment->phone_extension)
                <div class="text-xs text-obsidian-muted mt-0.5 font-mono">Extensión: {{ $assignment->phone_extension }}</div>
            @endif
            <div class="mt-3 pt-3 border-t border-obsidian-border/50 text-[11px] text-obsidian-muted">
                Procedencia: <span class="uppercase font-mono font-bold text-white">{{ $assignment->provenance }}</span>
                @if($assignment->provenance_other)
                    ({{ $assignment->provenance_other }})
                @endif
            </div>
        </div>

        <!-- Activo Principal -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl">
            <div class="text-xs font-mono uppercase text-obsidian-cyan font-bold flex items-center gap-1.5 mb-3">
                <span class="material-symbols-outlined text-base">desktop_windows</span>
                Activo Asignado
            </div>
            @if($assignment->asset)
                <div class="text-lg font-bold text-white flex items-center gap-2">
                    {{ $assignment->asset->name }}
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-obsidian-dark border border-obsidian-border text-obsidian-cyan">
                        {{ $assignment->asset->asset_tag }}
                    </span>
                </div>
                <div class="text-xs text-obsidian-muted font-mono mt-1">Serial: {{ $assignment->asset->serial_number ?? 'S/N' }}</div>
                <div class="text-xs text-obsidian-muted mt-0.5">Marca / Modelo: {{ $assignment->asset->brand }} {{ $assignment->asset->model }}</div>
                <div class="text-xs text-obsidian-muted mt-0.5 font-mono">IP: {{ $assignment->asset->ip_address ?? 'N/A' }}</div>
                <div class="mt-3 pt-3 border-t border-obsidian-border/50 flex items-center justify-between text-xs">
                    <span class="text-obsidian-muted">Estado actual:</span>
                    <span class="font-mono text-emerald-400 font-bold uppercase">{{ $assignment->asset->status_label }}</span>
                </div>
            @else
                <div class="text-xs text-obsidian-muted italic">Activo no disponible</div>
            @endif
        </div>

        <!-- Garita y Punto de Control -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl">
            <div class="text-xs font-mono uppercase text-amber-400 font-bold flex items-center gap-1.5 mb-3">
                <span class="material-symbols-outlined text-base">security</span>
                Punto de Control
            </div>
            <div class="text-sm font-bold text-white">{{ $assignment->checkpoint_name ?? $assignment->site->name ?? 'Centro de Servicio Valle Seco' }}</div>
            <div class="text-xs text-obsidian-muted mt-1">Operador Garita: <strong class="text-white">{{ $assignment->gate_operator_name ?? 'N/A' }}</strong></div>
            <div class="text-xs text-obsidian-muted mt-0.5 font-mono">Hora Salida: {{ $assignment->departure_time ?? 'N/A' }}</div>
            @if($assignment->entry_time)
                <div class="text-xs text-obsidian-muted mt-0.5 font-mono">Hora Entrada: {{ $assignment->entry_time }}</div>
            @endif
            <div class="mt-3 pt-3 border-t border-obsidian-border/50 text-[11px] text-obsidian-muted">
                Obs: <span class="text-white/80 italic">{{ $assignment->observations ?? 'Sin observaciones' }}</span>
            </div>
        </div>

        <!-- Validación Forense & Código QR -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl flex flex-col justify-between">
            <div>
                <div class="text-xs font-mono uppercase text-emerald-400 font-bold flex items-center gap-1.5 mb-3">
                    <span class="material-symbols-outlined text-base">qr_code_2</span>
                    Sello Digital & QR
                </div>
                <div class="flex items-center gap-3">
                    @if(!empty($qrCodeDataUri))
                        <div class="p-1 rounded-xl bg-white border border-slate-700 shadow-sm shrink-0">
                            <img src="{{ $qrCodeDataUri }}" class="w-14 h-14" alt="QR Verificación">
                        </div>
                    @endif
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-white flex items-center gap-1">
                            <span>Hash HMAC</span>
                            <span class="text-[9px] px-1.5 py-0.2 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded font-mono">SHA-256</span>
                        </div>
                        <div class="text-[10px] text-obsidian-muted font-mono truncate mt-1">
                            {{ substr(hash_hmac('sha256', $assignment->assignment_code, config('app.key') ?: 'key'), 0, 16) }}...
                        </div>
                        <div class="text-[10px] text-cyan-400 mt-1">Verificación en alcabalas</div>
                    </div>
                </div>
            </div>
            <div class="mt-3 pt-3 border-t border-obsidian-border/50">
                <a href="{{ $verificationUrl ?? route('public.acta.verify', $assignment->assignment_code) }}" target="_blank"
                   class="inline-flex items-center justify-center gap-1.5 w-full px-3 py-1.5 rounded-xl bg-obsidian-dark hover:bg-obsidian-panel border border-emerald-500/40 text-emerald-300 font-mono text-xs font-bold transition shadow-xs">
                    <span class="material-symbols-outlined text-sm">verified</span>
                    <span>Validar Certificado</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ESPECIFICACIONES TÉCNICAS Y PERIFÉRICOS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Hardware Snapshot -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl">
            <div class="text-xs font-mono uppercase text-obsidian-cyan font-bold flex items-center gap-1.5 mb-4">
                <span class="material-symbols-outlined text-base">memory</span>
                Especificaciones Técnicas Registradas en el Acta
            </div>
            @php $specs = $assignment->hardware_specs ?? []; @endphp
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="p-2.5 rounded-xl bg-obsidian-dark/60 border border-obsidian-border">
                    <span class="text-[10px] font-mono text-obsidian-muted uppercase block">Procesador</span>
                    <strong class="text-white">{{ $specs['processor'] ?? 'N/A' }}</strong>
                </div>
                <div class="p-2.5 rounded-xl bg-obsidian-dark/60 border border-obsidian-border">
                    <span class="text-[10px] font-mono text-obsidian-muted uppercase block">Velocidad</span>
                    <strong class="text-white">{{ $specs['speed'] ?? 'N/A' }}</strong>
                </div>
                <div class="p-2.5 rounded-xl bg-obsidian-dark/60 border border-obsidian-border">
                    <span class="text-[10px] font-mono text-obsidian-muted uppercase block">Memoria RAM</span>
                    <strong class="text-white">{{ $specs['ram'] ?? 'N/A' }}</strong>
                </div>
                <div class="p-2.5 rounded-xl bg-obsidian-dark/60 border border-obsidian-border">
                    <span class="text-[10px] font-mono text-obsidian-muted uppercase block">Disco Duro</span>
                    <strong class="text-white">{{ $specs['disk'] ?? 'N/A' }}</strong>
                </div>
                <div class="p-2.5 rounded-xl bg-obsidian-dark/60 border border-obsidian-border">
                    <span class="text-[10px] font-mono text-obsidian-muted uppercase block">Sistema Operativo</span>
                    <strong class="text-white">{{ $specs['os'] ?? 'N/A' }}</strong>
                </div>
                <div class="p-2.5 rounded-xl bg-obsidian-dark/60 border border-obsidian-border">
                    <span class="text-[10px] font-mono text-obsidian-muted uppercase block">Dirección IP</span>
                    <strong class="text-white font-mono">{{ $specs['ip_address'] ?? 'N/A' }}</strong>
                </div>
            </div>
        </div>

        <!-- Periféricos del Kit -->
        <div class="bg-obsidian-panel/30 border border-obsidian-border p-5 rounded-2xl">
            <div class="text-xs font-mono uppercase text-obsidian-cyan font-bold flex items-center gap-1.5 mb-4">
                <span class="material-symbols-outlined text-base">devices_other</span>
                Periféricos y Accesorios Entregados ({{ count($assignment->peripherals_json ?? []) }})
            </div>
            @php $peripherals = is_array($assignment->peripherals_json) ? $assignment->peripherals_json : []; @endphp
            @if(count($peripherals) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-obsidian-muted">
                        <thead class="bg-obsidian-dark/80 text-[10px] uppercase font-mono border-b border-obsidian-border">
                            <tr>
                                <th class="px-3 py-2">Tipo</th>
                                <th class="px-3 py-2">Marca/Modelo</th>
                                <th class="px-3 py-2">Serial</th>
                                <th class="px-3 py-2">Obs</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-obsidian-border/40">
                            @foreach($peripherals as $p)
                                <tr>
                                    <td class="px-3 py-2 font-bold text-white">{{ $p['type'] ?? 'N/A' }}</td>
                                    <td class="px-3 py-2">{{ $p['brand'] ?? '' }} {{ $p['model'] ?? '' }}</td>
                                    <td class="px-3 py-2 font-mono">{{ $p['serial_number'] ?? 'N/A' }}</td>
                                    <td class="px-3 py-2 text-[11px]">{{ $p['observations'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-6 text-xs text-obsidian-muted">
                    No se registraron periféricos adicionales para esta acta.
                </div>
            @endif
        </div>
    </div>

    <!-- VISTA PREVIA DE DOCUMENTACIÓN ASIGNADA (FORMATO CARTA CENTRADO) -->
    <div class="bg-obsidian-panel/30 border border-obsidian-border p-6 rounded-2xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5 border-b border-obsidian-border/50 pb-4">
            <div>
                <div class="text-xs font-mono uppercase text-obsidian-cyan font-bold flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">description</span>
                    <span id="preview-document-title">Vista Previa: Acta de Asignación UINF-4.05</span>
                </div>
                <p class="text-[11px] text-obsidian-muted font-mono mt-0.5">
                    Proporción Carta (8.5" &times; 11") • Hoja completa idéntica a la impresión física
                </p>
            </div>

            <!-- SELECTOR DE VISTA PREVIA (TABS) Y ACCIONES -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-xl p-1 bg-obsidian-dark border border-obsidian-border">
                    <button type="button" onclick="switchPreviewDoc('acta')" id="btn-tab-acta" 
                            class="px-3 py-1.5 rounded-lg text-xs font-mono font-bold transition flex items-center gap-1.5 bg-emerald-500 text-black shadow-xs cursor-pointer">
                        <span class="material-symbols-outlined text-sm">description</span>
                        <span>Acta UINF-4.05</span>
                    </button>
                    <button type="button" onclick="switchPreviewDoc('movilizacion')" id="btn-tab-movilizacion" 
                            class="px-3 py-1.5 rounded-lg text-xs font-mono font-semibold transition flex items-center gap-1.5 text-obsidian-muted hover:text-white cursor-pointer">
                        <span class="material-symbols-outlined text-sm">local_shipping</span>
                        <span>Pase de Movilización</span>
                    </button>
                    @if($assignment->has_signed_scan)
                    <button type="button" onclick="switchPreviewDoc('escaneo')" id="btn-tab-escaneo" 
                            class="px-3 py-1.5 rounded-lg text-xs font-mono font-semibold transition flex items-center gap-1.5 text-purple-400 hover:text-white cursor-pointer">
                        <span class="material-symbols-outlined text-sm">file_present</span>
                        <span>Acta Escaneada (Firmada)</span>
                    </button>
                    @endif
                </div>

                <a id="btn-preview-fullscreen" href="{{ route('admin.inventory.assignments.pdf', $assignment->id) }}" target="_blank" 
                   class="p-2 rounded-xl bg-obsidian-dark hover:bg-obsidian-panel border border-cyan-500/40 text-cyan-300 transition flex items-center justify-center shadow-xs"
                   title="Abrir documento en ventana completa">
                    <span class="material-symbols-outlined text-base">open_in_new</span>
                </a>
            </div>
        </div>

        <!-- MESA DE VISUALIZACIÓN CENTRADA (EFECTO HOJA REAL) -->
        <div class="bg-[#020710] p-4 sm:p-8 rounded-2xl border border-obsidian-border/60 flex justify-center items-start shadow-inner overflow-x-auto">
            <div class="w-full max-w-[830px] bg-white rounded-sm shadow-[0_15px_50px_rgba(0,0,0,0.85)] border border-slate-300/80 overflow-hidden">
                <iframe id="acta-preview-iframe" 
                        src="{{ route('admin.inventory.assignments.pdf', ['assignment' => $assignment->id, 'embed' => 1]) }}" 
                        class="w-full border-none block" 
                        style="height: 1120px; min-height: 1060px;" 
                        onload="autoFitActaIframe(this)">
                </iframe>
            </div>
        </div>
    </div>

</div>

<!-- MODAL: SUBIR HOJA FÍSICA ESCANEADA A ACTA -->
<div id="uploadScanModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-[#0b1322] border border-cyan-500/40 rounded-2xl max-w-lg w-full flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden shadow-2xl relative">
        <div class="p-4 px-6 border-b border-obsidian-border flex items-center justify-between shrink-0 bg-[#061527]">
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400">
                    <span class="material-symbols-outlined text-xl">upload_file</span>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-white">Subir Acta Física Firmada y Escaneada</h3>
                    <p class="text-[11px] font-mono text-obsidian-muted">Digitalización con firmas y sellos</p>
                </div>
            </div>
            <button type="button" onclick="closeUploadScanModal()" class="text-obsidian-muted hover:text-white transition p-1 cursor-pointer">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="uploadScanForm" method="POST" action="{{ route('admin.inventory.assignments.upload-scan', $assignment->id) }}" enctype="multipart/form-data" class="flex flex-col flex-1 min-h-0 overflow-hidden m-0">
            @csrf
            <div class="p-5 space-y-4 overflow-y-auto flex-1 custom-scroll">
                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Código de Acta</label>
                    <input type="text" value="{{ $assignment->assignment_code }}" disabled class="w-full px-3 py-1.5 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-cyan-300 font-bold">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Responsable Asignado</label>
                    <input type="text" value="{{ $assignment->custodian_name }}" disabled class="w-full px-3 py-1.5 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white">
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Documento Escaneado (PDF o Imagen) <span class="text-cyan-400">*</span></label>
                    <input type="file" name="scan_file" required accept=".pdf,image/jpeg,image/png" class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-mono file:bg-cyan-500/20 file:text-cyan-300 hover:file:bg-cyan-500 hover:file:text-black">
                    <span class="text-[10px] text-obsidian-muted mt-1 block">Formatos permitidos: PDF, JPG, PNG (máx. 20 MB).</span>
                </div>

                <div>
                    <label class="block text-xs font-mono text-obsidian-muted mb-1">Observaciones de Digitalización (Opcional)</label>
                    <textarea name="observations" rows="2" placeholder="Notas sobre el estado de la firma, sellos o fecha de recepción..." class="w-full px-3 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white focus:border-cyan-500 focus:outline-none"></textarea>
                </div>
            </div>

            <div class="p-3.5 px-6 border-t border-obsidian-border flex items-center justify-end gap-2 shrink-0 bg-[#061527]">
                <button type="button" onclick="closeUploadScanModal()" class="px-4 py-2 rounded-xl bg-obsidian-dark border border-obsidian-border text-obsidian-muted hover:text-white text-xs font-mono transition cursor-pointer">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">cloud_upload</span>
                    <span>Guardar y Validar Acta</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const previewUrls = {
        acta: {
            embed: "{{ route('admin.inventory.assignments.pdf', ['assignment' => $assignment->id, 'embed' => 1]) }}",
            fullscreen: "{{ route('admin.inventory.assignments.pdf', $assignment->id) }}",
            title: "Vista Previa: Acta de Asignación UINF-4.05"
        },
        movilizacion: {
            embed: "{{ route('admin.inventory.assignments.pdf-movilizacion', ['assignment' => $assignment->id, 'embed' => 1]) }}",
            fullscreen: "{{ route('admin.inventory.assignments.pdf-movilizacion', $assignment->id) }}",
            title: "Vista Previa: Pase de Movilización por Puntos de Control"
        }@if($assignment->has_signed_scan),
        escaneo: {
            embed: "{{ route('admin.inventory.assignments.view-scan', $assignment->id) }}",
            fullscreen: "{{ route('admin.inventory.assignments.view-scan', $assignment->id) }}",
            title: "Vista Previa: Acta Física Escaneada y Sellada (Soporte Digital)"
        }
        @endif
    };

    function switchPreviewDoc(type) {
        const iframe = document.getElementById('acta-preview-iframe');
        const btnActa = document.getElementById('btn-tab-acta');
        const btnMov = document.getElementById('btn-tab-movilizacion');
        const btnEsc = document.getElementById('btn-tab-escaneo');
        const titleEl = document.getElementById('preview-document-title');
        const fullBtn = document.getElementById('btn-preview-fullscreen');

        if (!iframe || !previewUrls[type]) return;

        iframe.src = previewUrls[type].embed;
        if (titleEl) titleEl.textContent = previewUrls[type].title;
        if (fullBtn) fullBtn.href = previewUrls[type].fullscreen;

        if (btnActa) btnActa.className = 'px-3 py-1.5 rounded-lg text-xs font-mono font-semibold transition flex items-center gap-1.5 text-obsidian-muted hover:text-white cursor-pointer';
        if (btnMov) btnMov.className = 'px-3 py-1.5 rounded-lg text-xs font-mono font-semibold transition flex items-center gap-1.5 text-obsidian-muted hover:text-white cursor-pointer';
        if (btnEsc) btnEsc.className = 'px-3 py-1.5 rounded-lg text-xs font-mono font-semibold transition flex items-center gap-1.5 text-obsidian-muted hover:text-white cursor-pointer';

        if (type === 'acta' && btnActa) {
            btnActa.className = 'px-3 py-1.5 rounded-lg text-xs font-mono font-bold transition flex items-center gap-1.5 bg-emerald-500 text-black shadow-xs cursor-pointer';
        } else if (type === 'movilizacion' && btnMov) {
            btnMov.className = 'px-3 py-1.5 rounded-lg text-xs font-mono font-bold transition flex items-center gap-1.5 bg-amber-500 text-black shadow-xs cursor-pointer';
        } else if (type === 'escaneo' && btnEsc) {
            btnEsc.className = 'px-3 py-1.5 rounded-lg text-xs font-mono font-bold transition flex items-center gap-1.5 bg-purple-500 text-black shadow-xs cursor-pointer';
        }
    }

    function autoFitActaIframe(iframe) {
        try {
            if (iframe.contentWindow && iframe.contentWindow.document.body) {
                const doc = iframe.contentWindow.document;
                const frameEl = doc.querySelector('.outer-frame');
                const contentH = frameEl ? (frameEl.offsetHeight + 40) : (doc.body.scrollHeight + 30);
                if (contentH > 800) {
                    iframe.style.height = contentH + 'px';
                }
            }
        } catch(e) {
            // Fallback: altura estándar para tamaño carta
        }
    }

    function openUploadScanModal(assignmentId, code, custodianName) {
        document.getElementById('uploadScanModal').classList.remove('hidden');
    }

    function closeUploadScanModal() {
        document.getElementById('uploadScanModal').classList.add('hidden');
    }
</script>
@endsection
