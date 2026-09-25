@extends('layouts.admin')

@section('page_title', 'Certificados SSL/TLS')

@section('admin_content')
<div class="space-y-4">
    <!-- PESTAÑAS SERVICIOS & CONECTIVIDAD -->
    <div class="flex flex-wrap items-center gap-2 border-b border-obsidian-border/80 pb-3">
        <a href="{{ route('admin.services.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.services.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">dns</span>
            <span>Servicios Principales</span>
        </a>
        <a href="{{ route('admin.proxies.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.proxies.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">public</span>
            <span>Proxies & Pasarelas</span>
        </a>
        <a href="{{ route('admin.ssl.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.ssl.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">lock</span>
            <span>Certificados SSL/TLS</span>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- ENCABEZADO Y ACCIONES PRINCIPALES                                         -->
    <!-- ========================================================================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 sm:px-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 backdrop-blur-md shadow-md">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-400 shrink-0">
                <span class="material-symbols-outlined text-lg">lock</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                        Monitoreo de Certificados SSL/TLS
                    </h2>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-cyan-950/80 text-cyan-300 border border-cyan-500/40" title="Sistema de inspección criptográfica y prevención de caducidad">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        ACTIVO
                    </span>
                    @if(!auth()->user()->isAdmin() && !auth()->user()->hasPermission('ssl.manage') && !auth()->user()->hasPermission('ssl.recheck'))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-950/80 text-amber-300 border border-amber-500/40" title="Modo Consulta: Acceso de lectura sin permisos de modificación">
                            <span class="material-symbols-outlined text-[11px]">visibility</span>
                            MODO CONSULTA
                        </span>
                    @endif
                </div>
                <p class="text-[10px] font-mono text-obsidian-muted">
                    Supervisión proactiva de vigencia, cadena criptográfica y renovaciones HTTPS corporativas.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto flex-wrap">
            <span class="px-2.5 py-1 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 text-[10.5px] font-mono flex items-center gap-1.5 shadow-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                Fuente: SERVIDOR MAESTRO
            </span>

            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('ssl.recheck'))
                <!-- RE-INSPECCIONAR TODOS -->
                <form action="{{ route('admin.ssl.recheck_all') }}" method="POST" class="inline" onsubmit="return confirm('¿Desea re-inspeccionar todos los certificados SSL ahora?');">
                    @csrf
                    <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs" title="Ejecutar re-inspección inmediata de todos los dominios SSL registrados">
                        <span class="material-symbols-outlined text-sm">sync</span>
                        <span>Escanear Todos</span>
                    </button>
                </form>
            @endif

            @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('ssl.manage')) && !$isClusterSlave)
                <!-- AGREGAR DOMINIO SSL -->
                <button type="button" onclick="openAddSslModal()" class="px-3 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-md shadow-cyan-950/30" title="Registrar un nuevo dominio HTTPS para inspección continua">
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    <span>+ Agregar Dominio</span>
                </button>
            @elseif($isClusterSlave && (auth()->user()->isAdmin() || auth()->user()->hasPermission('ssl.manage')))
                <span class="px-2.5 py-1.5 rounded-lg bg-gray-900 border border-gray-800 text-gray-500 font-mono text-xs inline-flex items-center gap-1.5" title="Modificaciones restringidas al Servidor Master">
                    <span class="material-symbols-outlined text-xs">lock</span>
                    <span>Solo Lectura (Modo Esclavo)</span>
                </span>
            @endif
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- HUD: TARJETAS DE MÉTRICAS Y ESTADOS DE VIGENCIA                           -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 sm:gap-3">
        <!-- TOTAL -->
        <a href="{{ route('admin.ssl.index') }}" class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80 hover:border-cyan-500/40 transition block {{ !request('status') ? 'ring-1 ring-cyan-500/50' : '' }}" title="Total de certificados SSL monitoreados">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Total</span>
                <span class="material-symbols-outlined text-sm text-cyan-400">lock</span>
            </div>
            <div class="text-lg font-bold font-mono text-white mt-1">{{ $stats['total'] }}</div>
            <div class="text-[9px] font-mono text-obsidian-muted">Dominios Activos</div>
        </a>

        <!-- VÁLIDOS (> 30d) -->
        <a href="{{ route('admin.ssl.index', ['status' => 'valid']) }}" class="p-2.5 rounded-xl bg-emerald-950/20 border border-emerald-500/30 hover:border-emerald-500/60 transition block {{ request('status') === 'valid' ? 'ring-1 ring-emerald-500/50' : '' }}" title="Certificados con más de 30 días de vigencia restante">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-emerald-400 uppercase">Válidos</span>
                <span class="material-symbols-outlined text-sm text-emerald-400">verified_user</span>
            </div>
            <div class="text-lg font-bold font-mono text-emerald-300 mt-1">{{ $stats['valid'] }}</div>
            <div class="text-[9px] font-mono text-emerald-400/80">&gt; 30 días restantes</div>
        </a>

        <!-- POR VENCER (<= 30d) -->
        <a href="{{ route('admin.ssl.index', ['status' => 'expiring']) }}" class="p-2.5 rounded-xl bg-amber-950/20 border border-amber-500/30 hover:border-amber-500/60 transition block {{ request('status') === 'expiring' ? 'ring-1 ring-amber-500/50' : '' }}" title="Certificados que vencen en los próximos 30 días">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-amber-400 uppercase">Por Vencer</span>
                <span class="material-symbols-outlined text-sm text-amber-400">schedule</span>
            </div>
            <div class="text-lg font-bold font-mono text-amber-300 mt-1">{{ $stats['expiring'] }}</div>
            <div class="text-[9px] font-mono text-amber-400/80">&le; 30 días restantes</div>
        </a>

        <!-- CRÍTICOS (<= 7d) -->
        <a href="{{ route('admin.ssl.index', ['status' => 'critical']) }}" class="p-2.5 rounded-xl bg-rose-950/20 border border-rose-500/30 hover:border-rose-500/60 transition block {{ request('status') === 'critical' ? 'ring-1 ring-rose-500/50' : '' }}" title="Urgente: Certificados que vencen en menos de 7 días">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-rose-400 uppercase">Críticos</span>
                <span class="material-symbols-outlined text-sm text-rose-400">alarm</span>
            </div>
            <div class="text-lg font-bold font-mono text-rose-300 mt-1">{{ $stats['critical'] }}</div>
            <div class="text-[9px] font-mono text-rose-400/80">&le; 7 días restantes</div>
        </a>

        <!-- EXPIRADOS (< 0d) -->
        <a href="{{ route('admin.ssl.index', ['status' => 'expired']) }}" class="p-2.5 rounded-xl bg-red-950/20 border border-red-500/30 hover:border-red-500/60 transition block {{ request('status') === 'expired' ? 'ring-1 ring-red-500/50' : '' }}" title="Certificados vencidos que requieren renovación inmediata">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-red-400 uppercase">Expirados</span>
                <span class="material-symbols-outlined text-sm text-red-400">dangerous</span>
            </div>
            <div class="text-lg font-bold font-mono text-red-300 mt-1">{{ $stats['expired'] }}</div>
            <div class="text-[9px] font-mono text-red-400/80">Caducados</div>
        </a>

        <!-- ERRORES -->
        <a href="{{ route('admin.ssl.index', ['status' => 'error']) }}" class="p-2.5 rounded-xl bg-purple-950/20 border border-purple-500/30 hover:border-purple-500/60 transition block {{ request('status') === 'error' ? 'ring-1 ring-purple-500/50' : '' }}" title="Servidores con fallos de conexión o TLS inalcanzable">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-purple-400 uppercase">Fallos</span>
                <span class="material-symbols-outlined text-sm text-purple-400">error</span>
            </div>
            <div class="text-lg font-bold font-mono text-purple-300 mt-1">{{ $stats['errors'] }}</div>
            <div class="text-[9px] font-mono text-purple-400/80">Inalcanzables</div>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- BARRA DE FILTROS Y BÚSQUEDA                                               -->
    <!-- ========================================================================= -->
    <div class="p-3 rounded-xl bg-obsidian-panel/60 border border-obsidian-border flex flex-col sm:flex-row items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.ssl.index') }}" class="flex-1 flex flex-col sm:flex-row items-center gap-2.5 w-full">
            <!-- BUSCADOR -->
            <div class="relative w-full sm:max-w-xs">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-obsidian-muted text-sm">search</span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar dominio, CN o emisor..." class="w-full bg-[#051424] border border-obsidian-border rounded-lg pl-8 pr-3 py-1.5 text-xs text-white placeholder-obsidian-muted focus:outline-none focus:border-cyan-500 font-mono transition" title="Filtrar por nombre de dominio, Common Name o Autoridad Certificadora">
            </div>

            <!-- FILTRO DE ESTADO -->
            <select name="status" onchange="this.form.submit()" class="w-full sm:w-auto bg-[#051424] border border-obsidian-border rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-cyan-500 font-mono transition cursor-pointer" title="Filtrar por estado de vigencia del certificado">
                <option value="">Todos los Estados</option>
                <option value="valid" {{ request('status') === 'valid' ? 'selected' : '' }}>Válidos (&gt; 30d)</option>
                <option value="expiring" {{ request('status') === 'expiring' ? 'selected' : '' }}>Por Vencer (&le; 30d)</option>
                <option value="critical" {{ request('status') === 'critical' ? 'selected' : '' }}>Críticos (&le; 7d)</option>
                <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expirados</option>
                <option value="error" {{ request('status') === 'error' ? 'selected' : '' }}>Fallos / Inalcanzable</option>
            </select>

            <button type="submit" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black text-xs font-mono font-bold transition flex items-center gap-1 cursor-pointer" title="Aplicar filtros de búsqueda">
                <span class="material-symbols-outlined text-xs">filter_list</span>
                <span>Filtrar</span>
            </button>

            @if(request()->hasAny(['search', 'status']))
                <a href="{{ route('admin.ssl.index') }}" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white text-xs font-mono transition" title="Limpiar todos los filtros">
                    Limpiar
                </a>
            @endif
        </form>
    </div>

    <!-- ========================================================================= -->
    <!-- TABLA DE CERTIFICADOS SSL/TLS (COMPACTA, ESTILO OBSIDIAN DARK)           -->
    <!-- ========================================================================= -->
    <div class="rounded-xl border border-obsidian-border bg-obsidian-card overflow-hidden shadow-lg">
        <div class="overflow-x-auto custom-scroll">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-obsidian-border bg-obsidian-panel/90 text-obsidian-muted text-[10px] uppercase font-mono">
                        <th class="px-2.5 py-2 font-bold tracking-wider" title="Estado de vigencia y seguridad TLS">Estado</th>
                        <th class="px-2.5 py-2 font-bold tracking-wider" title="Nombre de dominio y puerto del servicio">Dominio & Puerto</th>
                        <th class="px-2.5 py-2 font-bold tracking-wider" title="Servicio corporativo asociado">Servicio</th>
                        <th class="px-2.5 py-2 font-bold tracking-wider" title="Autoridad Certificadora emisora (Issuer)">Emisor (CA)</th>
                        <th class="px-2.5 py-2 font-bold tracking-wider" title="Vigencia y días restantes hasta la caducidad">Vigencia & Días</th>
                        <th class="px-2.5 py-2 font-bold tracking-wider" title="Algoritmo de firma y longitud de clave pública">Cifrado & Clave</th>
                        <th class="px-2.5 py-2 font-bold tracking-wider" title="Fecha y hora de la última verificación">Último Chequeo</th>
                        <th class="px-2.5 py-2 text-right font-bold tracking-wider" title="Acciones de detalle, re-inspección y auditoría">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/50">
                    @forelse($certificates as $cert)
                        @php
                            $meta = $cert->getStatusMeta();
                            $days = $cert->days_remaining;
                        @endphp
                        <tr class="hover:bg-cyan-950/20 transition-colors {{ $days < 0 || $cert->last_check_status === 'expired' ? 'bg-red-950/20' : ($days <= 7 && $cert->last_check_status !== 'error' ? 'bg-rose-950/20' : ($days <= 30 && $cert->last_check_status !== 'error' ? 'bg-amber-950/10' : '')) }}">
                            <!-- ESTADO / BADGE -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded {{ $meta['bg_class'] }} text-[9.5px] font-bold" title="Estado: {{ $meta['label'] }}">
                                    <span class="material-symbols-outlined text-[11px]">{{ $meta['icon'] }}</span>
                                    <span>{{ $meta['label'] }}</span>
                                </span>
                            </td>

                            <!-- DOMINIO & PUERTO -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm {{ $meta['color'] === 'emerald' ? 'text-emerald-400' : ($meta['color'] === 'amber' ? 'text-amber-400' : 'text-red-400') }}" title="Candado SSL">
                                        {{ $days < 0 || $cert->last_check_status === 'error' ? 'lock_open' : 'lock' }}
                                    </span>
                                    <div>
                                        <span class="font-bold text-white text-[11.5px] tracking-tight" title="Dominio: {{ $cert->domain }}:{{ $cert->port }}">{{ $cert->domain }}</span>
                                        <span class="text-cyan-300 font-mono text-[9.5px]">:{{ $cert->port }}</span>
                                        @if($cert->is_wildcard)
                                            <span class="ml-1 px-1 py-0.2 rounded bg-purple-950/80 border border-purple-500/30 text-purple-300 text-[8.5px] font-bold" title="Certificado comodín (*.dominio)">WILDCARD</span>
                                        @endif
                                        @if($cert->is_self_signed)
                                            <span class="ml-1 px-1 py-0.2 rounded bg-amber-950/80 border border-amber-500/30 text-amber-300 text-[8.5px] font-bold" title="Certificado autofirmado (Self-Signed)">AUTOFIRMADO</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- SERVICIO VINCULADO -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                @if($cert->service)
                                    <span class="px-1.5 py-0.5 rounded bg-cyan-950/60 border border-cyan-500/30 text-cyan-200 text-[10px] font-semibold" title="Servicio corporativo: {{ $cert->service->name }}">
                                        {{ $cert->service->name }}
                                    </span>
                                @else
                                    <span class="text-obsidian-muted text-[10px] italic" title="No vinculado a servicio específico">Externo / Independiente</span>
                                @endif
                            </td>

                            <!-- EMISOR (ISSUER) -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <span class="text-gray-300 text-[10.5px] max-w-[180px] truncate block font-mono" title="Autoridad Emisora: {{ $cert->issuer_cn ?: ($cert->issuer_org ?: 'Desconocido') }}">
                                    {{ $cert->issuer_cn ?: ($cert->issuer_org ?: 'Desconocido') }}
                                </span>
                            </td>

                            <!-- VIGENCIA & DÍAS -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                @if($cert->valid_to)
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-mono text-[10.5px] font-bold {{ $days < 0 ? 'text-red-400' : ($days <= 7 ? 'text-rose-400' : ($days <= 30 ? 'text-amber-400' : 'text-emerald-400')) }}" title="Días restantes hasta caducidad">
                                                {{ $days }} días
                                            </span>
                                            <span class="text-obsidian-muted text-[9.5px]" title="Vence el {{ $cert->valid_to->timezone('America/Caracas')->format('d/m/Y') }}">
                                                ({{ $cert->valid_to->timezone('America/Caracas')->format('d/m/Y') }})
                                            </span>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-obsidian-muted text-[10px] font-mono" title="Sin fecha de vigencia registrada">N/A</span>
                                @endif
                            </td>

                            <!-- CIFRADO & CLAVE -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <span class="font-mono text-[10px] text-gray-300" title="Algoritmo de clave: {{ $cert->public_key_algorithm }} {{ $cert->public_key_bits ? '(' . $cert->public_key_bits . ' bits)' : '' }} | Firma: {{ $cert->signature_algorithm }}">
                                    {{ $cert->public_key_algorithm ?: 'RSA' }} {{ $cert->public_key_bits ? '(' . $cert->public_key_bits . 'b)' : '' }}
                                </span>
                            </td>

                            <!-- ÚLTIMO CHEQUEO -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap">
                                <span class="font-mono text-[9.5px] text-obsidian-muted" title="{{ $cert->last_checked_at ? 'Última comprobación: ' . $cert->last_checked_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'Pendiente de escaneo' }}">
                                    {{ $cert->last_checked_at ? $cert->last_checked_at->timezone('America/Caracas')->format('d/m/Y h:i A') : 'Pendiente' }}
                                </span>
                            </td>

                            <!-- ACCIONES -->
                            <td class="px-2.5 py-1.5 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <!-- DETALLES E HISTORIAL (Para todos los usuarios) -->
                                    <button type="button" onclick="openCertDetailsModal({{ $cert->id }})" class="p-1 rounded bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black transition cursor-pointer shadow-xs inline-flex items-center" title="Examinar Detalles Criptográficos, Cadena y Registro Histórico">
                                        <span class="material-symbols-outlined text-[13px]">info</span>
                                    </button>

                                    @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('ssl.recheck'))
                                    <!-- RE-INSPECCIONAR DOMINIO -->
                                    <form action="{{ route('admin.ssl.recheck', $cert->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="p-1 rounded bg-obsidian-panel border border-emerald-500/40 text-emerald-300 hover:bg-emerald-500 hover:text-black transition cursor-pointer shadow-xs inline-flex items-center" title="Re-inspeccionar de inmediato este certificado SSL">
                                            <span class="material-symbols-outlined text-[13px]">sync</span>
                                        </button>
                                    </form>
                                    @endif

                                    @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('ssl.manage'))
                                        @if(!$isClusterSlave)
                                        <!-- ELIMINAR (Master) -->
                                        <form action="{{ route('admin.ssl.destroy', $cert->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Está seguro de eliminar del monitoreo el certificado para {{ $cert->domain }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 rounded bg-obsidian-panel border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition cursor-pointer shadow-xs inline-flex items-center" title="Eliminar certificado del inventario de monitoreo">
                                                <span class="material-symbols-outlined text-[13px]">delete</span>
                                            </button>
                                        </form>
                                        @else
                                        <span class="p-1 rounded bg-gray-900 border border-gray-800 text-gray-600 inline-flex items-center" title="Modificaciones bloqueadas en Modo Esclavo">
                                            <span class="material-symbols-outlined text-[13px]">lock</span>
                                        </span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-6 text-center text-obsidian-muted font-mono text-xs">
                                No se encontraron certificados SSL/TLS con los criterios de búsqueda actuales.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- ========================================================================= -->
        <!-- PAGINACIÓN OBSIDIAN DARK (IDÉNTICA A DISCOVERY, SNMP Y DEVICES)           -->
        <!-- ========================================================================= -->
        @if($certificates->hasPages())
            <div class="px-3.5 py-2.5 bg-obsidian-panel border-t border-obsidian-border/80 flex flex-col sm:flex-row items-center justify-between gap-2.5 text-xs font-mono">
                <div class="text-obsidian-muted text-[10.5px]">
                    Mostrando <span class="text-white font-bold">{{ $certificates->firstItem() }}</span> a <span class="text-white font-bold">{{ $certificates->lastItem() }}</span> de <span class="text-cyan-400 font-bold">{{ $certificates->total() }}</span> certificados monitoreados (15 por página)
                </div>
                <div class="flex items-center gap-1">
                    {{-- Anterior --}}
                    @if($certificates->onFirstPage())
                        <span class="px-2 py-0.5 rounded bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[10.5px]">
                            &laquo; Anterior
                        </span>
                    @else
                        <a href="{{ $certificates->previousPageUrl() }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[10.5px]" title="Página Anterior">
                            &laquo; Anterior
                        </a>
                    @endif

                    {{-- Páginas --}}
                    @foreach($certificates->getUrlRange(1, $certificates->lastPage()) as $page => $url)
                        @if($page == $certificates->currentPage())
                            <span class="px-2 py-0.5 rounded bg-cyan-500 text-black font-bold text-[10.5px]">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white transition text-[10.5px]" title="Ir a la página {{ $page }}">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach

                    {{-- Siguiente --}}
                    @if($certificates->hasMorePages())
                        <a href="{{ $certificates->nextPageUrl() }}" class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-cyan-300 hover:bg-cyan-500 hover:text-black transition text-[10.5px]" title="Página Siguiente">
                            Siguiente &raquo;
                        </a>
                    @else
                        <span class="px-2 py-0.5 rounded bg-obsidian-panel/40 border border-obsidian-border/40 text-obsidian-muted/40 cursor-not-allowed text-[10.5px]">
                            Siguiente &raquo;
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

<!-- ============================================================================= -->
<!-- MODAL: DETALLES CRIPTOGRÁFICOS E HISTORIAL DE CERTIFICADO                      -->
<!-- ============================================================================= -->
<div id="modal-cert-details" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="relative w-full max-w-3xl rounded-2xl bg-obsidian-card border border-obsidian-border shadow-2xl overflow-hidden font-sans">
        <!-- CABECERA -->
        <div class="px-5 py-3 border-b border-obsidian-border bg-obsidian-panel/90 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-400">
                    <span class="material-symbols-outlined text-base">verified</span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white uppercase font-mono tracking-wider" id="modal-cert-domain">Detalle de Certificado</h3>
                    <p class="text-[10px] font-mono text-obsidian-muted" id="modal-cert-port-subtitle">Puerto: 443</p>
                </div>
            </div>
            <button type="button" onclick="closeCertDetailsModal()" class="text-obsidian-muted hover:text-white transition p-1 cursor-pointer" title="Cerrar ventana de detalles">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <!-- PESTAÑAS -->
        <div class="px-5 pt-2 border-b border-obsidian-border/60 bg-obsidian-panel/40 flex items-center gap-4 text-xs font-mono">
            <button type="button" onclick="switchCertTab('tab-general')" id="btn-tab-general" class="pb-2 border-b-2 border-cyan-400 text-cyan-300 font-bold transition">
                Resumen & Criptografía
            </button>
            <button type="button" onclick="switchCertTab('tab-history')" id="btn-tab-history" class="pb-2 border-b-2 border-transparent text-obsidian-muted hover:text-white transition">
                Historial de Eventos & Renovaciones
            </button>
            <button type="button" onclick="switchCertTab('tab-pem')" id="btn-tab-pem" class="pb-2 border-b-2 border-transparent text-obsidian-muted hover:text-white transition">
                Certificado PEM
            </button>
        </div>

        <!-- CUERPO DEL MODAL -->
        <div class="p-5 max-h-[70vh] overflow-y-auto custom-scroll space-y-4 text-xs">
            <!-- PESTAÑA 1: RESUMEN Y CRIPTOGRAFÍA -->
            <div id="tab-general" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 font-mono text-[11px]">
                    <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border/60 space-y-1.5">
                        <div class="text-[10px] uppercase text-cyan-400 font-bold">Información del Sujeto (Subject)</div>
                        <div><span class="text-obsidian-muted">Common Name (CN):</span> <span id="detail-subject-cn" class="text-white font-bold"></span></div>
                        <div><span class="text-obsidian-muted">Organización:</span> <span id="detail-subject-org" class="text-gray-300"></span></div>
                        <div><span class="text-obsidian-muted">Unidad:</span> <span id="detail-subject-ou" class="text-gray-300"></span></div>
                        <div><span class="text-obsidian-muted">País / Región:</span> <span id="detail-subject-geo" class="text-gray-300"></span></div>
                    </div>

                    <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border/60 space-y-1.5">
                        <div class="text-[10px] uppercase text-cyan-400 font-bold">Autoridad Emisora (Issuer)</div>
                        <div><span class="text-obsidian-muted">Emisor (CN):</span> <span id="detail-issuer-cn" class="text-white font-bold"></span></div>
                        <div><span class="text-obsidian-muted">Organización:</span> <span id="detail-issuer-org" class="text-gray-300"></span></div>
                        <div><span class="text-obsidian-muted">País:</span> <span id="detail-issuer-country" class="text-gray-300"></span></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 font-mono text-[11px]">
                    <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border/60 space-y-1">
                        <div class="text-[10px] uppercase text-obsidian-muted font-bold">Vigencia</div>
                        <div><span class="text-obsidian-muted">Desde:</span> <span id="detail-valid-from" class="text-gray-200"></span></div>
                        <div><span class="text-obsidian-muted">Hasta:</span> <span id="detail-valid-to" class="text-white font-bold"></span></div>
                        <div><span class="text-obsidian-muted">Restante:</span> <span id="detail-days-remaining" class="text-cyan-300 font-bold"></span></div>
                    </div>

                    <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border/60 space-y-1">
                        <div class="text-[10px] uppercase text-obsidian-muted font-bold">Clave & Algoritmos</div>
                        <div><span class="text-obsidian-muted">Tipo:</span> <span id="detail-key-algo" class="text-gray-200"></span></div>
                        <div><span class="text-obsidian-muted">Longitud:</span> <span id="detail-key-bits" class="text-gray-200"></span></div>
                        <div><span class="text-obsidian-muted">Firma:</span> <span id="detail-sig-algo" class="text-gray-200"></span></div>
                    </div>

                    <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border/60 space-y-1">
                        <div class="text-[10px] uppercase text-obsidian-muted font-bold">Identificación</div>
                        <div><span class="text-obsidian-muted">Versión:</span> <span id="detail-version" class="text-gray-200"></span></div>
                        <div><span class="text-obsidian-muted">Renovaciones:</span> <span id="detail-renewals" class="text-cyan-300 font-bold"></span></div>
                        <div><span class="text-obsidian-muted">N° Serie:</span> <span id="detail-serial" class="text-gray-300 text-[9px] break-all block"></span></div>
                    </div>
                </div>

                <!-- SAN ENTRIES -->
                <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border/60 font-mono text-[11px] space-y-1.5">
                    <div class="text-[10px] uppercase text-cyan-400 font-bold">Nombres Alternativos del Sujeto (SAN)</div>
                    <div id="detail-sans-list" class="flex flex-wrap gap-1.5"></div>
                </div>

                <!-- HUELLAS DIGITALES -->
                <div class="p-3 rounded-lg bg-obsidian-panel/60 border border-obsidian-border/60 font-mono text-[10px] space-y-1 text-obsidian-muted">
                    <div>SHA-256: <span id="detail-fp-sha256" class="text-gray-300 select-all font-bold"></span></div>
                    <div>SHA-1: <span id="detail-fp-sha1" class="text-gray-400 select-all"></span></div>
                </div>
            </div>

            <!-- PESTAÑA 2: HISTORIAL DE EVENTOS -->
            <div id="tab-history" class="hidden space-y-2">
                <div id="history-container" class="space-y-2 font-mono text-xs">
                    <!-- Dinámico vía JS -->
                </div>
            </div>

            <!-- PESTAÑA 3: CERTIFICADO PEM -->
            <div id="tab-pem" class="hidden space-y-2">
                <div class="flex items-center justify-between font-mono text-xs">
                    <span class="text-obsidian-muted">Certificado X.509 codificado en Base64 (PEM):</span>
                    <button type="button" onclick="copyPemToClipboard()" id="btn-copy-pem" class="px-2.5 py-1 rounded bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black transition flex items-center gap-1 cursor-pointer text-[10.5px]">
                        <span class="material-symbols-outlined text-xs">content_copy</span>
                        <span id="text-copy-pem">Copiar PEM</span>
                    </button>
                </div>
                <textarea id="detail-pem-text" readonly class="w-full h-64 bg-[#030914] border border-obsidian-border rounded-lg p-3 text-[10px] font-mono text-emerald-300 focus:outline-none custom-scroll select-all"></textarea>
            </div>
        </div>

        <!-- PIE DEL MODAL -->
        <div class="px-5 py-3 border-t border-obsidian-border bg-obsidian-panel/80 flex items-center justify-end">
            <button type="button" onclick="closeCertDetailsModal()" class="px-4 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-white hover:bg-obsidian-border transition text-xs font-mono cursor-pointer">
                Cerrar
            </button>
        </div>
    </div>
</div>

@if((auth()->user()->isAdmin() || auth()->user()->hasPermission('ssl.manage')) && !$isClusterSlave)
<!-- ============================================================================= -->
<!-- MODAL: AGREGAR NUEVO DOMINIO SSL (SOLO ADMINISTRADOR)                          -->
<!-- ============================================================================= -->
<div id="modal-add-ssl" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="relative w-full max-w-md rounded-2xl bg-obsidian-card border border-obsidian-border shadow-2xl overflow-hidden font-sans">
        <form action="{{ route('admin.ssl.store') }}" method="POST">
            @csrf
            <!-- CABECERA -->
            <div class="px-5 py-3 border-b border-obsidian-border bg-obsidian-panel/90 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-400">
                        <span class="material-symbols-outlined text-base">add_moderator</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white uppercase font-mono tracking-wider">Nuevo Dominio SSL</h3>
                        <p class="text-[10px] font-mono text-obsidian-muted">Supervisión continua de caducidad</p>
                    </div>
                </div>
                <button type="button" onclick="closeAddSslModal()" class="text-obsidian-muted hover:text-white transition p-1 cursor-pointer" title="Cerrar modal">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            <!-- FORMULARIO -->
            <div class="p-5 space-y-3.5 text-xs font-mono">
                <div>
                    <label class="block text-[11px] text-cyan-300 font-bold mb-1">Nombre de Dominio o IP (FQDN):</label>
                    <input type="text" name="domain" required placeholder="ej: portal.empresa.com.ve" class="w-full bg-[#051424] border border-obsidian-border rounded-lg px-3 py-1.5 text-white placeholder-obsidian-muted focus:outline-none focus:border-cyan-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] text-obsidian-muted mb-1">Puerto TLS:</label>
                        <input type="number" name="port" value="443" min="1" max="65535" required class="w-full bg-[#051424] border border-obsidian-border rounded-lg px-3 py-1.5 text-white focus:outline-none focus:border-cyan-500">
                    </div>
                    <div>
                        <label class="block text-[11px] text-obsidian-muted mb-1">Servicio Vinculado:</label>
                        <select name="service_id" class="w-full bg-[#051424] border border-obsidian-border rounded-lg px-3 py-1.5 text-white focus:outline-none focus:border-cyan-500">
                            <option value="">-- Ninguno (Independiente) --</option>
                            @foreach($services as $svc)
                                <option value="{{ $svc->id }}">{{ $svc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] text-obsidian-muted mb-1">Aviso Por Vencer (días):</label>
                        <input type="number" name="alert_threshold_warning" value="30" min="1" max="365" class="w-full bg-[#051424] border border-obsidian-border rounded-lg px-3 py-1.5 text-white focus:outline-none focus:border-cyan-500">
                    </div>
                    <div>
                        <label class="block text-[11px] text-obsidian-muted mb-1">Aviso Crítico (días):</label>
                        <input type="number" name="alert_threshold_critical" value="7" min="1" max="365" class="w-full bg-[#051424] border border-obsidian-border rounded-lg px-3 py-1.5 text-white focus:outline-none focus:border-cyan-500">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] text-obsidian-muted mb-1">Notas u Observaciones:</label>
                    <textarea name="notes" rows="2" placeholder="Detalles de la aplicación o certificado..." class="w-full bg-[#051424] border border-obsidian-border rounded-lg px-3 py-1.5 text-white placeholder-obsidian-muted focus:outline-none focus:border-cyan-500"></textarea>
                </div>
            </div>

            <!-- PIE -->
            <div class="px-5 py-3 border-t border-obsidian-border bg-obsidian-panel/80 flex items-center justify-end gap-2">
                <button type="button" onclick="closeAddSslModal()" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white transition text-xs font-mono cursor-pointer">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-black font-mono text-xs font-bold transition flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">check</span>
                    <span>Registrar e Inspeccionar</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
function openCertDetailsModal(certId) {
    const modal = document.getElementById('modal-cert-details');
    modal.classList.remove('hidden');

    fetch(`/admin/ssl/${certId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const c = data.certificate;

            document.getElementById('modal-cert-domain').textContent = c.domain;
            document.getElementById('modal-cert-port-subtitle').textContent = `Puerto TLS: ${c.port} ${c.service_name ? '• Servicio: ' + c.service_name : ''}`;

            document.getElementById('detail-subject-cn').textContent = c.subject_cn || 'N/A';
            document.getElementById('detail-subject-org').textContent = c.subject_org || 'N/A';
            document.getElementById('detail-subject-ou').textContent = c.subject_ou || 'N/A';
            document.getElementById('detail-subject-geo').textContent = [c.subject_locality, c.subject_state, c.subject_country].filter(Boolean).join(', ') || 'N/A';

            document.getElementById('detail-issuer-cn').textContent = c.issuer_cn || 'N/A';
            document.getElementById('detail-issuer-org').textContent = c.issuer_org || 'N/A';
            document.getElementById('detail-issuer-country').textContent = c.issuer_country || 'N/A';

            document.getElementById('detail-valid-from').textContent = c.valid_from || 'N/A';
            document.getElementById('detail-valid-to').textContent = c.valid_to || 'N/A';
            document.getElementById('detail-days-remaining').textContent = `${c.days_remaining} días restantes (${c.status_meta.label})`;

            document.getElementById('detail-key-algo').textContent = c.public_key_algorithm || 'RSA';
            document.getElementById('detail-key-bits').textContent = c.public_key_bits ? `${c.public_key_bits} bits` : 'N/A';
            document.getElementById('detail-sig-algo').textContent = c.signature_algorithm || 'N/A';

            document.getElementById('detail-version').textContent = `v${c.version || 3}`;
            document.getElementById('detail-renewals').textContent = `${c.renewal_count || 0} registradas`;
            document.getElementById('detail-serial').textContent = c.serial_number || 'N/A';

            // SANs
            const sansContainer = document.getElementById('detail-sans-list');
            sansContainer.innerHTML = '';
            if (c.san_entries && c.san_entries.length > 0) {
                c.san_entries.forEach(san => {
                    const badge = document.createElement('span');
                    badge.className = 'px-1.5 py-0.5 rounded bg-cyan-950/80 border border-cyan-500/30 text-cyan-300 text-[10px] font-mono';
                    badge.textContent = san;
                    sansContainer.appendChild(badge);
                });
            } else {
                sansContainer.innerHTML = '<span class="text-obsidian-muted italic">Sin entradas alternativas registradas</span>';
            }

            // Fingerprints & PEM
            document.getElementById('detail-fp-sha256').textContent = c.fingerprint_sha256 || 'N/A';
            document.getElementById('detail-fp-sha1').textContent = c.fingerprint_sha1 || 'N/A';
            document.getElementById('detail-pem-text').value = c.pem_certificate || 'Certificado PEM no disponible.';

            // Historial
            const histContainer = document.getElementById('history-container');
            histContainer.innerHTML = '';
            if (data.history && data.history.length > 0) {
                data.history.forEach(h => {
                    const item = document.createElement('div');
                    item.className = 'p-2.5 rounded-lg bg-obsidian-panel/60 border border-obsidian-border/60 flex items-start justify-between gap-3';
                    item.innerHTML = `
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded ${h.badge} text-[9.5px] font-bold">
                                    <span class="material-symbols-outlined text-[11px]">${h.icon}</span>
                                    <span>${h.label}</span>
                                </span>
                                <span class="text-[10px] text-obsidian-muted">${h.occurred_at} (${h.time_ago})</span>
                            </div>
                            ${h.error_message ? `<p class="text-[10.5px] text-red-300 font-sans">${h.error_message}</p>` : ''}
                            ${h.new_valid_to ? `<p class="text-[10px] text-obsidian-muted">Nueva vigencia: <span class="text-emerald-300 font-bold">${h.new_valid_to}</span> (Días: ${h.days_remaining_at_event})</p>` : ''}
                        </div>
                    `;
                    histContainer.appendChild(item);
                });
            } else {
                histContainer.innerHTML = '<div class="p-4 text-center text-obsidian-muted">Sin historial de cambios registrado aún.</div>';
            }
        })
        .catch(err => console.error(err));
}

function closeCertDetailsModal() {
    document.getElementById('modal-cert-details').classList.add('hidden');
}

function switchCertTab(tabId) {
    ['tab-general', 'tab-history', 'tab-pem'].forEach(id => {
        document.getElementById(id).classList.add('hidden');
    });
    document.getElementById(tabId).classList.remove('hidden');

    ['btn-tab-general', 'btn-tab-history', 'btn-tab-pem'].forEach(btnId => {
        const btn = document.getElementById(btnId);
        btn.classList.remove('border-cyan-400', 'text-cyan-300', 'font-bold');
        btn.classList.add('border-transparent', 'text-obsidian-muted');
    });

    const activeBtn = document.getElementById(tabId.replace('tab-', 'btn-tab-'));
    if (activeBtn) {
        activeBtn.classList.remove('border-transparent', 'text-obsidian-muted');
        activeBtn.classList.add('border-cyan-400', 'text-cyan-300', 'font-bold');
    }
}

function copyPemToClipboard() {
    const pemText = document.getElementById('detail-pem-text').value;
    if (!pemText) return;
    navigator.clipboard.writeText(pemText).then(() => {
        const txt = document.getElementById('text-copy-pem');
        txt.textContent = '¡Copiado!';
        setTimeout(() => { txt.textContent = 'Copiar PEM'; }, 2000);
    });
}

function openAddSslModal() {
    const modal = document.getElementById('modal-add-ssl');
    if (modal) modal.classList.remove('hidden');
}

function closeAddSslModal() {
    const modal = document.getElementById('modal-add-ssl');
    if (modal) modal.classList.add('hidden');
}
</script>
@endsection
