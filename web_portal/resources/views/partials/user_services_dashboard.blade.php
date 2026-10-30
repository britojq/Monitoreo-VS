@php
    $snapshotServices = ($snapshotData && isset($snapshotData['services'])) ? collect($snapshotData['services'])->keyBy('letter') : collect();

    // Clasificación de Servicios en Activos vs Caídos
    $activeServices = $services->filter(function($s) use ($snapshotServices) {
        $snap = $snapshotServices->get($s->letter);
        return $snap ? ($snap['is_up'] ?? false) : false;
    });
    $downServices = $services->filter(function($s) use ($snapshotServices) {
        $snap = $snapshotServices->get($s->letter);
        return $snap ? !($snap['is_up'] ?? false) : true;
    });

    // Puestos de Trabajo y Equipos Asignados al Usuario Autenticado
    $currentUser = auth()->user();
    $userId = $currentUser->id;
    $cedula = trim((string) ($currentUser->cedula ?? ''));
    $rawDigits = preg_replace('/\D/', '', $cedula);
    $cedulaVariants = array_values(array_filter([
        $cedula,
        $rawDigits,
        !empty($rawDigits) ? 'V-' . $rawDigits : null,
        !empty($rawDigits) ? 'V-' . number_format((int)$rawDigits, 0, '', '.') : null,
    ]));
    $name = trim((string) ($currentUser->name ?? ''));

    // Reconciliación proactiva con MariaDB si existen activos desvinculados
    try {
        app(\App\Services\EntityResolverService::class)->reconcileUserAssets($currentUser);
    } catch (\Throwable $e) {}

    $userAssignedDevices = \App\Models\InventoryAsset::with([
        'peripherals',
        'activeAssignment',
        'site',
        'networkInterfaces',
    ])
    ->where(function ($query) use ($userId, $cedulaVariants, $name) {
        $query->where('current_user_id', $userId);
        if (!empty($cedulaVariants)) {
            $query->orWhereIn('custodian_id_number', $cedulaVariants);
        }
        if (!empty($name) && !in_array(strtolower($name), ['usuario', 'operador', 'admin', 'user'])) {
            $query->orWhere('custodian_name', $name);
        }
        $query->orWhereHas('assignments', function ($q) use ($userId, $cedulaVariants, $name) {
            $q->where('status', 'active')
              ->where(function ($sub) use ($userId, $cedulaVariants, $name) {
                  $sub->where('user_id', $userId);
                  if (!empty($cedulaVariants)) {
                      $sub->orWhereIn('custodian_id_number', $cedulaVariants);
                  }
                  if (!empty($name) && !in_array(strtolower($name), ['usuario', 'operador', 'admin', 'user'])) {
                      $sub->orWhere('custodian_name', $name);
                  }
              });
        });
    })
    ->orderBy('name')
    ->get();
@endphp

<div class="space-y-4">
    <!-- BANNER SUPERIOR COMPACTO: TELEMETRÍA EN VIVO -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 px-3.5 py-2.5 rounded-xl bg-[#06111f]/95 border border-obsidian-border/90 shadow-md">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shrink-0">
                <span class="material-symbols-outlined text-base">monitoring</span>
            </div>
            <div>
                <h1 class="text-xs sm:text-sm font-bold text-white tracking-wide font-mono uppercase">
                    Telemetría en Vivo
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto text-xs font-mono bg-[#040c17] px-3 py-1 rounded-lg border border-obsidian-border/80">
            <span class="w-2 h-2 rounded-full {{ $downServices->isEmpty() ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400 animate-pulse' }}" id="user-status-dot"></span>
            <span class="text-white text-[11px] font-semibold" id="user-status-text">
                {{ $downServices->isEmpty() ? 'Todos los servicios operativos' : $downServices->count() . ' servicio(s) sin conexión' }}
            </span>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- PRIMERA SECCIÓN (FILA SUPERIOR): SERVICIOS ACTIVOS (EN 3 COLUMNAS)         -->
    <!-- ========================================================================= -->
    <div class="rounded-xl border border-obsidian-border/80 bg-[#06111f]/90 overflow-hidden flex flex-col shadow-lg">
        <!-- CABECERA -->
        <div class="px-3.5 py-2 border-b border-obsidian-border/80 flex items-center justify-between bg-gradient-to-r from-[#071629] to-[#040c17]">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-sm text-emerald-400">check_circle</span>
                <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                    Servicios Activos (<span id="count-active-services">{{ $activeServices->count() }}</span>)
                </h2>
            </div>
            <div class="w-6 h-6 rounded-md bg-emerald-950/80 border border-emerald-500/40 flex items-center justify-center text-emerald-400" title="{{ $activeServices->count() }} servicios operativos">
                <span class="material-symbols-outlined text-sm">wifi</span>
            </div>
        </div>

        <!-- GRILLA DE 3 COLUMNAS: SOLO ICONO Y NOMBRE (SIN LATENCIA) -->
        <div class="p-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2" id="active-services-container">
                @forelse($activeServices as $s)
                    @php
                        $svcType = strtoupper(trim($s->type ?? 'SISTEMA'));
                        $icon = match($svcType) {
                            'WEB' => 'language',
                            'DNS' => 'dns',
                            'SMTP', 'MAIL', 'CORREO' => 'mail',
                            'LDAP', 'DIRECTORIO' => 'badge',
                            'BASE DE DATOS', 'DB' => 'database',
                            default => 'hub',
                        };
                    @endphp
                    <div class="py-2 px-3 rounded-xl bg-[#040c17]/90 hover:bg-[#07192e] border border-obsidian-border/70 hover:border-emerald-500/40 transition flex items-center gap-2.5 group item-searchable select-none shadow-xs"
                         data-search="{{ strtolower($s->name) }}">
                        <div class="w-6 h-6 rounded-md bg-emerald-950/70 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shrink-0">
                            <span class="material-symbols-outlined text-sm">{{ $icon }}</span>
                        </div>
                        <span class="text-xs font-semibold text-white group-hover:text-emerald-300 transition-colors truncate font-mono">
                            {{ $s->name }}
                        </span>
                    </div>
                @empty
                    <div class="col-span-full p-4 text-center text-xs font-mono text-obsidian-muted">
                        No hay servicios activos reportados en este momento.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SEGUNDA SECCIÓN (FILA INFERIOR): SERVICIOS SIN CONEXIÓN                   -->
    <!-- ========================================================================= -->
    <div class="rounded-xl border border-obsidian-border/80 bg-[#06111f]/90 overflow-hidden flex flex-col shadow-lg">
        <!-- CABECERA -->
        <div class="px-3.5 py-2 border-b border-obsidian-border/80 flex items-center justify-between bg-gradient-to-r from-[#071629] to-[#040c17]">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-sm {{ $downServices->isEmpty() ? 'text-emerald-400' : 'text-red-400' }}" id="down-header-icon">
                    {{ $downServices->isEmpty() ? 'check_circle' : 'error' }}
                </span>
                <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                    Servicios Sin Conexión (<span id="count-down-services">{{ $downServices->count() }}</span>)
                </h2>
            </div>
            <div class="w-6 h-6 rounded-md {{ $downServices->isEmpty() ? 'bg-emerald-950/80 border-emerald-500/40 text-emerald-400' : 'bg-red-950/80 border-red-500/40 text-red-400' }} flex items-center justify-center" id="down-header-box" title="{{ $downServices->count() }} servicios sin conexión">
                <span class="material-symbols-outlined text-sm" id="down-header-box-icon">
                    {{ $downServices->isEmpty() ? 'check' : 'cloud_off' }}
                </span>
            </div>
        </div>

        <!-- GRILLA DE SERVICIOS SIN CONEXIÓN -->
        <div class="p-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2" id="down-services-container">
                @if($downServices->isEmpty())
                    <div class="col-span-full p-4 text-center rounded-xl bg-[#040c17]/60 border border-obsidian-border/60 flex items-center justify-center gap-3" id="down-empty-state">
                        <span class="material-symbols-outlined text-emerald-400 text-lg">verified</span>
                        <span class="text-xs font-mono text-obsidian-muted">
                            Todos los servicios se encuentran operando con normalidad.
                        </span>
                    </div>
                @else
                    @foreach($downServices as $s)
                        <div class="py-2 px-3 rounded-xl bg-red-950/25 hover:bg-red-950/35 border border-red-500/40 transition flex items-center gap-2.5 group item-searchable select-none shadow-xs"
                             data-search="{{ strtolower($s->name) }}">
                            <div class="w-6 h-6 rounded-md bg-red-950/80 border border-red-500/40 flex items-center justify-center text-red-400 shrink-0">
                                <span class="material-symbols-outlined text-sm">cloud_off</span>
                            </div>
                            <span class="text-xs font-semibold text-red-200 group-hover:text-white transition-colors truncate font-mono">
                                {{ $s->name }}
                            </span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TERCERA SECCIÓN: EQUIPOS Y PUESTOS DE TRABAJO ASIGNADOS                    -->
    <!-- ========================================================================= -->
    <div class="rounded-xl border border-obsidian-border/80 bg-[#06111f]/90 overflow-hidden flex flex-col shadow-lg">
        <!-- CABECERA (REGLA DE ORO #11: CERO BADGES DE TEXTO) -->
        <div class="px-3.5 py-2.5 border-b border-obsidian-border/80 flex items-center justify-between bg-gradient-to-r from-[#071629] to-[#040c17]">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-cyan-400">devices</span>
                <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                    Equipos Asignados (<span id="count-user-devices">{{ $userAssignedDevices->count() }}</span>)
                </h2>
            </div>
            <div class="w-6 h-6 rounded-md bg-cyan-950/80 border border-cyan-500/40 flex items-center justify-center text-cyan-400" title="{{ $userAssignedDevices->count() }} equipos asignados a su cuenta">
                <span class="material-symbols-outlined text-sm">computer</span>
            </div>
        </div>

        <div class="p-3">
            @if($userAssignedDevices->isEmpty())
                <div class="p-6 text-center rounded-xl bg-[#040c17]/60 border border-obsidian-border/60 flex items-center justify-center gap-3">
                    <span class="material-symbols-outlined text-obsidian-muted text-lg">desktop_access_disabled</span>
                    <span class="text-xs font-mono text-obsidian-muted">
                        Actualmente no figuran puestos de trabajo o activos informáticos asignados a su cuenta.
                    </span>
                </div>
            @else
                <div class="overflow-x-auto custom-scroll rounded-xl border border-obsidian-border/80">
                    <table class="w-full text-left font-mono border-collapse min-w-[850px]">
                        <thead class="bg-[#040b15] border-b border-obsidian-border/80 text-obsidian-muted uppercase text-[10px] tracking-wider select-none">
                            <tr>
                                <th class="py-2.5 px-3 w-8 text-center" title="Ficha Técnica Desplegable">Info</th>
                                <th class="py-2.5 px-3 whitespace-nowrap">Puesto / Equipo</th>
                                <th class="py-2.5 px-3 whitespace-nowrap">Sede Asignada</th>
                                <th class="py-2.5 px-3 whitespace-nowrap">IP / Red</th>
                                <th class="py-2.5 px-3 whitespace-nowrap text-center">Estatus</th>
                                <th class="py-2.5 px-3 whitespace-nowrap text-center">Periféricos</th>
                                <th class="py-2.5 px-4 whitespace-nowrap text-right">Acta Oficial</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-obsidian-border/60 text-xs">
                            @foreach($userAssignedDevices as $device)
                                @php
                                    $acta = $device->activeAssignment;
                                    $hasActaPdf = $acta && !empty($acta->document_pdf_path);
                                    $siteName = $device->site ? $device->site->name : 'Sede Valle Seco';
                                    
                                    // Periféricos asociados
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
                                <tr class="hover:bg-[#07192e]/60 transition-colors group select-none">
                                    <!-- INFO (BOTÓN DESPLEGABLE) -->
                                    <td class="py-2 px-3 text-center">
                                        <button type="button" 
                                                onclick="toggleUserDeviceAccordion('device-detail-{{ $device->id }}', this)" 
                                                class="w-6 h-6 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-cyan-400 hover:border-cyan-500/50 flex items-center justify-center transition cursor-pointer"
                                                title="Ver Ficha Técnica Desplegable">
                                            <span class="material-symbols-outlined text-sm transition-transform duration-200">expand_more</span>
                                        </button>
                                    </td>

                                    <!-- IDENTIDAD / PUESTO -->
                                    <td class="py-2 px-3 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-md bg-cyan-950/70 border border-cyan-500/30 flex items-center justify-center text-cyan-400 shrink-0">
                                                <span class="material-symbols-outlined text-sm">desktop_windows</span>
                                            </div>
                                            <div class="min-w-0">
                                                <span class="text-xs font-bold text-white group-hover:text-cyan-300 transition-colors block truncate font-mono">
                                                    {{ $device->name }}
                                                </span>
                                                <div class="flex items-center gap-1.5 text-[10px] text-obsidian-muted truncate">
                                                    @if($device->asset_tag)
                                                        <span class="text-cyan-400 font-semibold">{{ $device->asset_tag }}</span>
                                                        <span>•</span>
                                                    @endif
                                                    <span>{{ $device->brand ?? 'Genérico' }} {{ $device->model ?? 'PC' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- SEDE ASIGNADA -->
                                    <td class="py-2 px-3 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5 text-xs text-slate-200">
                                            <span class="material-symbols-outlined text-xs text-cyan-400">domain</span>
                                            <span class="font-medium truncate max-w-[180px]">{{ $siteName }}</span>
                                        </div>
                                        @if($device->location_detail)
                                            <span class="text-[10px] text-obsidian-muted block truncate max-w-[180px]">{{ $device->location_detail }}</span>
                                        @endif
                                    </td>

                                    <!-- IP / MAC -->
                                    <td class="py-2 px-3 whitespace-nowrap">
                                        <div class="text-xs font-mono text-cyan-300 font-semibold">
                                            {{ $device->ip_address ?: 'DHCP' }}
                                        </div>
                                        @if($device->mac_address)
                                            <span class="text-[10px] font-mono text-obsidian-muted block">{{ $device->mac_address }}</span>
                                        @endif
                                    </td>

                                    <!-- ESTATUS -->
                                    <td class="py-2 px-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-mono font-bold uppercase {{ $device->is_active ? 'bg-emerald-950/80 border border-emerald-500/40 text-emerald-300' : 'bg-red-950/80 border border-red-500/40 text-red-300' }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $device->is_active ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                                            {{ $device->is_active ? 'Operativo' : 'Inactivo' }}
                                        </span>
                                    </td>

                                    <!-- PERIFÉRICOS ASOCIADOS -->
                                    <td class="py-2 px-3 whitespace-nowrap text-center">
                                        @if($peripherals->isNotEmpty())
                                            <span class="inline-flex items-center gap-1 text-[11px] font-mono text-purple-300 bg-purple-950/60 px-2 py-0.5 rounded-md border border-purple-500/30" title="{{ $peripherals->count() }} periféricos asociados">
                                                <span class="material-symbols-outlined text-xs">keyboard</span>
                                                <span>{{ $peripherals->count() }} acc.</span>
                                            </span>
                                        @else
                                            <span class="text-[10px] font-mono text-obsidian-muted">Sin extras</span>
                                        @endif
                                    </td>

                                    <!-- ACTA OFICIAL (UINF-4.05) -->
                                    <td class="py-2 px-4 whitespace-nowrap text-right">
                                        @if($hasActaPdf)
                                            <a href="{{ route('admin.my_devices.acta', $acta->id) }}" 
                                               target="_blank" 
                                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-500/20 hover:bg-emerald-500 hover:text-black border border-emerald-500/50 text-emerald-300 font-mono text-xs font-bold transition shadow-xs group/btn" 
                                               title="Ver Acta Oficial Digitalizada (UINF-4.05)">
                                                <span class="material-symbols-outlined text-xs group-hover/btn:scale-110 transition-transform">picture_as_pdf</span>
                                                <span>Ver Acta</span>
                                            </a>
                                        @elseif($acta)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[#040c17] border border-obsidian-border text-obsidian-muted text-[10px] font-mono" title="Asignación registrada • Documento físico en digitalización">
                                                <span class="material-symbols-outlined text-xs text-amber-400">pending_actions</span>
                                                <span>En trámite</span>
                                            </span>
                                        @else
                                            <span class="text-[10px] font-mono text-obsidian-muted/60">Sin acta</span>
                                        @endif
                                    </td>
                                </tr>

                                <!-- FILA DESPLEGABLE (FICHA TÉCNICA) -->
                                <tr id="device-detail-{{ $device->id }}" class="hidden bg-[#030913]/90 border-b border-obsidian-border/80">
                                    <td colspan="7" class="p-3.5">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs font-mono">
                                            <!-- ESPECIFICACIONES DE HARDWARE -->
                                            <div class="p-3 rounded-lg bg-[#040c17] border border-obsidian-border/70 space-y-2">
                                                <div class="flex items-center gap-1.5 text-cyan-400 font-bold text-[11px] uppercase tracking-wider">
                                                    <span class="material-symbols-outlined text-sm">memory</span>
                                                    <span>Ficha Técnica del Equipo</span>
                                                </div>
                                                <div class="grid grid-cols-2 gap-2 text-[11px]">
                                                    <div>
                                                        <span class="text-obsidian-muted text-[10px] block uppercase">Marca / Modelo:</span>
                                                        <span class="text-white font-semibold">{{ $device->brand ?? 'Genérico' }} {{ $device->model ?? 'PC' }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-obsidian-muted text-[10px] block uppercase">Sistema Operativo:</span>
                                                        <span class="text-slate-200">{{ $device->operating_system ?: 'Linux / Windows' }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-obsidian-muted text-[10px] block uppercase">Número de Serial:</span>
                                                        <span class="text-slate-200 font-mono">{{ $device->serial_number ?: 'No registrado' }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-obsidian-muted text-[10px] block uppercase">Placa de Activo:</span>
                                                        <span class="text-cyan-300 font-mono">{{ $device->asset_tag ?: 'N/A' }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- PERIFÉRICOS ASOCIADOS (HARDWARE COMPLEMENTARIO) -->
                                            <div class="p-3 rounded-lg bg-[#040c17] border border-obsidian-border/70 space-y-2">
                                                <div class="flex items-center gap-1.5 text-purple-400 font-bold text-[11px] uppercase tracking-wider">
                                                    <span class="material-symbols-outlined text-sm">keyboard</span>
                                                    <span>Hardware Complementario (Periféricos)</span>
                                                </div>
                                                @if($peripherals->isNotEmpty())
                                                    <div class="space-y-1.5 max-h-32 overflow-y-auto custom-scroll pr-1">
                                                        @foreach($peripherals as $p)
                                                            <div class="flex items-center justify-between p-1.5 rounded bg-[#06111f] border border-obsidian-border/60 text-[10.5px]">
                                                                <span class="text-white font-medium">{{ $p->brand ?? '' }} {{ $p->model ?? $p->type }}</span>
                                                                <span class="text-obsidian-muted font-mono">S/N: {{ $p->serial_number ?: 'S/S' }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <p class="text-obsidian-muted text-[11px]">No se registran periféricos adicionales asociados a este puesto.</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- SCRIPT REACTIVO DE AUTO-ACTUALIZACIÓN EN VIVO (3 COLUMNAS SIN LATENCIA) & ACORDEÓN -->
<script>
    function toggleUserDeviceAccordion(rowId, btn) {
        const row = document.getElementById(rowId);
        if (!row) return;
        const icon = btn.querySelector('.material-symbols-outlined');
        if (row.classList.contains('hidden')) {
            row.classList.remove('hidden');
            if (icon) icon.innerText = 'expand_less';
        } else {
            row.classList.add('hidden');
            if (icon) icon.innerText = 'expand_more';
        }
    }

    (function() {
        const typeIcons = {
            'WEB': 'language',
            'DNS': 'dns',
            'SMTP': 'mail',
            'MAIL': 'mail',
            'CORREO': 'mail',
            'LDAP': 'badge',
            'DIRECTORIO': 'badge',
            'BASE DE DATOS': 'database',
            'DB': 'database'
        };

        function getIcon(type) {
            return typeIcons[type] || 'hub';
        }

        async function refreshUserDashboard() {
            try {
                const res = await fetch('/api/status', { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                if (!data.success || !data.snapshot || !Array.isArray(data.snapshot.services)) return;

                const services = data.snapshot.services;
                const active = services.filter(s => s.is_up);
                const down = services.filter(s => !s.is_up);

                // 1. Indicador general superior
                const dot = document.getElementById('user-status-dot');
                const text = document.getElementById('user-status-text');
                if (dot && text) {
                    if (down.length === 0) {
                        dot.className = 'w-2 h-2 rounded-full bg-emerald-400 animate-pulse';
                        text.innerText = 'Todos los servicios operativos';
                    } else {
                        dot.className = 'w-2 h-2 rounded-full bg-amber-400 animate-pulse';
                        text.innerText = down.length + ' servicio(s) sin conexión';
                    }
                }

                // 2. Sección: Servicios Activos
                const countActive = document.getElementById('count-active-services');
                if (countActive) countActive.innerText = active.length;

                const activeContainer = document.getElementById('active-services-container');
                if (activeContainer) {
                    if (active.length > 0) {
                        activeContainer.innerHTML = active.map(s => {
                            const type = (s.type || 'SISTEMA').toUpperCase();
                            const icon = getIcon(type);
                            return `
                                <div class="py-2 px-3 rounded-xl bg-[#040c17]/90 hover:bg-[#07192e] border border-obsidian-border/70 hover:border-emerald-500/40 transition flex items-center gap-2.5 group item-searchable select-none shadow-xs"
                                     data-search="${s.name.toLowerCase()}">
                                    <div class="w-6 h-6 rounded-md bg-emerald-950/70 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shrink-0">
                                        <span class="material-symbols-outlined text-sm">${icon}</span>
                                    </div>
                                    <span class="text-xs font-semibold text-white group-hover:text-emerald-300 transition-colors truncate font-mono">
                                        ${s.name}
                                    </span>
                                </div>
                            `;
                        }).join('');
                    } else {
                        activeContainer.innerHTML = `
                            <div class="col-span-full p-4 text-center text-xs font-mono text-obsidian-muted">
                                No hay servicios activos reportados en este momento.
                            </div>
                        `;
                    }
                }

                // 3. Sección: Servicios Sin Conexión
                const countDown = document.getElementById('count-down-services');
                if (countDown) countDown.innerText = down.length;

                const headerIcon = document.getElementById('down-header-icon');
                const headerBox = document.getElementById('down-header-box');
                const headerBoxIcon = document.getElementById('down-header-box-icon');
                if (headerIcon && headerBox && headerBoxIcon) {
                    if (down.length === 0) {
                        headerIcon.className = 'material-symbols-outlined text-sm text-emerald-400';
                        headerIcon.innerText = 'check_circle';
                        headerBox.className = 'w-6 h-6 rounded-md bg-emerald-950/80 border border-emerald-500/40 flex items-center justify-center text-emerald-400';
                        headerBox.title = '0 servicios sin conexión';
                        headerBoxIcon.innerText = 'check';
                    } else {
                        headerIcon.className = 'material-symbols-outlined text-sm text-red-400';
                        headerIcon.innerText = 'error';
                        headerBox.className = 'w-6 h-6 rounded-md bg-red-950/80 border border-red-500/40 flex items-center justify-center text-red-400';
                        headerBox.title = down.length + ' servicios sin conexión';
                        headerBoxIcon.innerText = 'cloud_off';
                    }
                }

                const downContainer = document.getElementById('down-services-container');
                if (downContainer) {
                    if (down.length === 0) {
                        downContainer.innerHTML = `
                            <div class="col-span-full p-4 text-center rounded-xl bg-[#040c17]/60 border border-obsidian-border/60 flex items-center justify-center gap-3" id="down-empty-state">
                                <span class="material-symbols-outlined text-emerald-400 text-lg">verified</span>
                                <span class="text-xs font-mono text-obsidian-muted">
                                    Todos los servicios se encuentran operando con normalidad.
                                </span>
                            </div>
                        `;
                    } else {
                        downContainer.innerHTML = down.map(s => {
                            return `
                                <div class="py-2 px-3 rounded-xl bg-red-950/25 hover:bg-red-950/35 border border-red-500/40 transition flex items-center gap-2.5 group item-searchable select-none shadow-xs"
                                     data-search="${s.name.toLowerCase()}">
                                    <div class="w-6 h-6 rounded-md bg-red-950/80 border border-red-500/40 flex items-center justify-center text-red-400 shrink-0">
                                        <span class="material-symbols-outlined text-sm">cloud_off</span>
                                    </div>
                                    <span class="text-xs font-semibold text-red-200 group-hover:text-white transition-colors truncate font-mono">
                                        ${s.name}
                                    </span>
                                </div>
                            `;
                        }).join('');
                    }
                }

                // Re-aplicar filtro si hay texto en el input superior
                if (typeof window.filterLiveItems === 'function') {
                    window.filterLiveItems();
                }

            } catch (err) {
                // Silencioso en caso de error de red
            }
        }

        // Consultar cada 15 segundos
        setInterval(refreshUserDashboard, 15000);
    })();
</script>
