@extends('layouts.admin')

@section('page_title', 'Configuración de Servicios')

@section('admin_content')
<div class="space-y-6">
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

    <!-- CABECERA -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-xs font-mono font-semibold group shadow-sm">
                    <span class="material-symbols-outlined text-sm group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-xs font-mono text-obsidian-muted">Servicios Monitoreados</span>
            </div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400">cloud_done</span>
                Servicios y Plataformas de Red Monitoreados
            </h2>
            <p class="text-xs font-mono text-obsidian-muted">Gestión de plataformas web, DNS, autenticación LDAP, proxies de salida y telemetría de red</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-300 text-xs font-mono flex items-center gap-1.5 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                Fuente: SERVIDOR MAESTRO
            </div>
            @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.manage_services')) && !$isClusterSlave)
                <button type="button" onclick="openCreateServiceModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-obsidian-cyan hover:bg-white text-black font-bold font-mono text-xs transition shadow-sm cursor-pointer">
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    <span>+ Agregar Servicio</span>
                </button>
            @endif
        </div>
    </div>

    @php
        $totalCount = $services->count();
        $activeCount = $services->where('is_active', true)->count();
        $inactiveCount = $totalCount - $activeCount;

        $upCount = 0;
        $downCount = 0;
        foreach($services->where('is_active', true) as $activeSrv) {
            $sn = $snapshotServices->get((string)$activeSrv->id) ?? $snapshotServices->get((string)$activeSrv->letter);
            if ($sn && ($sn['is_up'] ?? false)) {
                $upCount++;
            } else {
                $downCount++;
            }
        }

        $corporateServices = $services->where('scope', 'corporativo');
        $regionalServices = $services->where('scope', 'regional');
        $otherServices = $services->whereNotIn('scope', ['corporativo', 'regional']);
    @endphp

    <!-- RESUMEN KPI DE TELEMETRÍA (ESTILO OBSIDIAN) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 font-mono">
        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border bg-obsidian-panel/40">
            <div class="flex items-center justify-between text-obsidian-muted text-[10px] uppercase">
                <span>Total Registrados</span>
                <span class="material-symbols-outlined text-xs text-obsidian-cyan">dns</span>
            </div>
            <div class="mt-1 flex items-baseline gap-1">
                <span class="text-xl font-bold text-white">{{ $totalCount }}</span>
                <span class="text-[10px] text-obsidian-muted">servicios</span>
            </div>
            <p class="text-[9.5px] text-obsidian-muted mt-0.5">{{ $services->where('scope', 'corporativo')->count() }} Corp / {{ $services->where('scope', 'regional')->count() }} Reg</p>
        </div>

        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border bg-obsidian-panel/40">
            <div class="flex items-center justify-between text-obsidian-muted text-[10px] uppercase">
                <span>Monitoreo Activo</span>
                <span class="material-symbols-outlined text-xs text-emerald-400">sensors</span>
            </div>
            <div class="mt-1 flex items-baseline gap-1">
                <span class="text-xl font-bold text-emerald-400">{{ $activeCount }}</span>
                <span class="text-[10px] text-obsidian-muted">habilitados</span>
            </div>
            <p class="text-[9.5px] text-obsidian-muted mt-0.5">{{ $inactiveCount }} inactivos o en reserva</p>
        </div>

        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border bg-obsidian-panel/40">
            <div class="flex items-center justify-between text-obsidian-muted text-[10px] uppercase">
                <span>Operativos ONLINE</span>
                <span class="material-symbols-outlined text-xs text-emerald-400">check_circle</span>
            </div>
            <div class="mt-1 flex items-baseline gap-1">
                <span class="text-xl font-bold text-emerald-400">{{ $upCount }}</span>
                <span class="text-[10px] text-obsidian-muted">al 100%</span>
            </div>
            <p class="text-[9.5px] text-emerald-400/80 mt-0.5">Respuesta inmediata</p>
        </div>

        <div class="glass-card rounded-xl p-3.5 border border-obsidian-border bg-obsidian-panel/40">
            <div class="flex items-center justify-between text-obsidian-muted text-[10px] uppercase">
                <span>Con Falla / Timeout</span>
                <span class="material-symbols-outlined text-xs {{ $downCount > 0 ? 'text-red-400' : 'text-obsidian-muted' }}">warning</span>
            </div>
            <div class="mt-1 flex items-baseline gap-1">
                <span class="text-xl font-bold {{ $downCount > 0 ? 'text-red-400' : 'text-white' }}">{{ $downCount }}</span>
                <span class="text-[10px] text-obsidian-muted">servicios</span>
            </div>
            <p class="text-[9.5px] {{ $downCount > 0 ? 'text-red-400/90' : 'text-obsidian-muted' }} mt-0.5">{{ $downCount > 0 ? 'Requiere atención técnica' : 'Sin fallas detectadas' }}</p>
        </div>
    </div>

    <!-- BARRA DE BÚSQUEDA Y FILTRADO RÁPIDO -->
    <div class="glass-card rounded-xl p-3 flex flex-col sm:flex-row items-center justify-between gap-3 border border-obsidian-border/70">
        <div class="relative w-full sm:w-80">
            <span class="material-symbols-outlined text-sm text-obsidian-muted absolute left-3 top-1/2 -translate-y-1/2">search</span>
            <input type="text" id="service-search-input" onkeyup="filterServicesList()" placeholder="Buscar por nombre, IP, tipo o letra..." class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg pl-9 pr-3 py-1.5 text-xs text-white placeholder:text-obsidian-muted focus:outline-none focus:border-cyan-500/60 font-mono">
        </div>

        <div class="flex flex-wrap items-center gap-1.5 text-xs font-mono w-full sm:w-auto">
            <button type="button" onclick="setServiceFilter('all')" id="filter-btn-all" class="filter-chip px-2.5 py-1 rounded-lg transition font-semibold bg-obsidian-cyan text-black shadow-sm cursor-pointer">
                Todos ({{ $totalCount }})
            </button>
            <button type="button" onclick="setServiceFilter('corporativo')" id="filter-btn-corporativo" class="filter-chip px-2.5 py-1 rounded-lg transition font-semibold text-obsidian-muted hover:text-white bg-obsidian-panel border border-obsidian-border cursor-pointer">
                Corporativos ({{ $corporateServices->count() }})
            </button>
            <button type="button" onclick="setServiceFilter('regional')" id="filter-btn-regional" class="filter-chip px-2.5 py-1 rounded-lg transition font-semibold text-obsidian-muted hover:text-white bg-obsidian-panel border border-obsidian-border cursor-pointer">
                Regionales ({{ $regionalServices->count() }})
            </button>
            <button type="button" onclick="setServiceFilter('active')" id="filter-btn-active" class="filter-chip px-2.5 py-1 rounded-lg transition font-semibold text-obsidian-muted hover:text-white bg-obsidian-panel border border-obsidian-border cursor-pointer">
                Activos ({{ $activeCount }})
            </button>
            <button type="button" onclick="setServiceFilter('down')" id="filter-btn-down" class="filter-chip px-2.5 py-1 rounded-lg transition font-semibold text-obsidian-muted hover:text-white bg-obsidian-panel border border-obsidian-border cursor-pointer">
                Caídos ({{ $downCount }})
            </button>
        </div>
    </div>

    <!-- SECCIONES DE SERVICIOS POR ÁMBITO (ESTILO GLASS-CARD DE ADMIN/SITES) -->
    <div class="space-y-6">
        <!-- 1. SERVICIOS CORPORATIVOS -->
        @if($corporateServices->count() > 0)
            <div id="section-scope-corporativo" class="glass-card rounded-xl p-5 space-y-4 border border-obsidian-border/80">
                <!-- CABECERA DE ÁMBITO CORPORATIVO -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-obsidian-border/60 pb-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-cyan-950/80 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shadow-md shrink-0">
                            <span class="material-symbols-outlined text-xl">corporate_fare</span>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white font-sans flex items-center gap-2">
                                <span>Servicios y Plataformas Corporativas</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-obsidian-panel border border-obsidian-border text-obsidian-cyan">
                                    {{ $corporateServices->where('is_active', true)->count() }} Activos de {{ $corporateServices->count() }}
                                </span>
                            </h3>
                            <p class="text-xs font-mono text-obsidian-muted flex items-center gap-2 mt-0.5">
                                <span>Sistemas corporativos centrales, autenticación institucional y plataformas web</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-cyan-950/70 text-cyan-300 border border-cyan-500/40">
                            Ámbito: Corporativo Nacional
                        </span>
                    </div>
                </div>

                <!-- GRID DE TARJETAS DE SERVICIOS CORPORATIVOS -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($corporateServices as $s)
                        @include('admin.services._service_card', ['s' => $s, 'snapshotServices' => $snapshotServices, 'isClusterSlave' => $isClusterSlave])
                    @endforeach
                </div>
            </div>
        @endif

        <!-- 2. SERVICIOS REGIONALES -->
        @if($regionalServices->count() > 0)
            <div id="section-scope-regional" class="glass-card rounded-xl p-5 space-y-4 border border-obsidian-border/80">
                <!-- CABECERA DE ÁMBITO REGIONAL -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-obsidian-border/60 pb-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-950/80 border border-purple-500/40 flex items-center justify-center text-purple-300 shadow-md shrink-0">
                            <span class="material-symbols-outlined text-xl">hub</span>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white font-sans flex items-center gap-2">
                                <span>Servicios Regionales de Infraestructura</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-obsidian-panel border border-obsidian-border text-purple-300">
                                    {{ $regionalServices->where('is_active', true)->count() }} Activos de {{ $regionalServices->count() }}
                                </span>
                            </h3>
                            <p class="text-xs font-mono text-obsidian-muted flex items-center gap-2 mt-0.5">
                                <span>Servidores DNS locales, proxies Pfsense, servidores SMTP y DHCP regional</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-purple-950/70 text-purple-300 border border-purple-500/40">
                            Ámbito: Carabobo / Aragua / Valle Seco
                        </span>
                    </div>
                </div>

                <!-- GRID DE TARJETAS DE SERVICIOS REGIONALES -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($regionalServices as $s)
                        @include('admin.services._service_card', ['s' => $s, 'snapshotServices' => $snapshotServices, 'isClusterSlave' => $isClusterSlave])
                    @endforeach
                </div>
            </div>
        @endif

        <!-- 3. OTROS SERVICIOS (SI EXISTEN) -->
        @if($otherServices->count() > 0)
            <div id="section-scope-otros" class="glass-card rounded-xl p-5 space-y-4 border border-obsidian-border/80">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-obsidian-border/60 pb-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-obsidian-panel border border-obsidian-border flex items-center justify-center text-obsidian-muted shadow-md shrink-0">
                            <span class="material-symbols-outlined text-xl">dns</span>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white font-sans">Otros Servicios Monitoreados</h3>
                            <p class="text-xs font-mono text-obsidian-muted mt-0.5">Servicios con configuración especial o ámbito personalizado</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($otherServices as $s)
                        @include('admin.services._service_card', ['s' => $s, 'snapshotServices' => $snapshotServices, 'isClusterSlave' => $isClusterSlave])
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

@if((auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.manage_services')) && !$isClusterSlave)
<!-- MODAL CREAR NUEVO SERVICIO (Solo Administrador) -->
<div id="modal-create-service" onclick="if(event.target === this) closeModal('modal-create-service')" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-lg w-full rounded-2xl p-6 border border-obsidian-border shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <h3 class="text-sm font-bold text-white uppercase font-mono flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan text-base">add_circle</span>
                Agregar Nuevo Servicio Monitoreado
            </h3>
            <button type="button" onclick="closeModal('modal-create-service')" class="text-obsidian-muted hover:text-white text-xl p-1 rounded hover:bg-obsidian-panel leading-none">&times;</button>
        </div>
        <form id="form-create-service" action="{{ route('admin.services.store') }}" method="POST" class="space-y-4 font-mono text-xs">
            @csrf
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">ID / Slug (opcional):</label>
                    <input type="text" name="letter" maxlength="10" placeholder="Auto" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white font-bold"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">Tipo de Servicio:</label>
                    <select name="type" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white">
                        <option value="WEB">WEB (HTTP/S)</option>
                        <option value="PING">PING (ICMP)</option>
                        <option value="DNS">DNS</option>
                        <option value="SMTP">SMTP</option>
                        <option value="DHCP">DHCP</option>
                        <option value="LDAP">LDAP</option>
                        <option value="PROXY">PROXY</option>
                        <option value="CUPS">CUPS</option>
                    </select>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">Ámbito:</label>
                    <select name="scope" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white font-semibold">
                        <option value="corporativo">Corporativo</option>
                        <option value="regional">Regional</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px]">Nombre Descriptivo:</label>
                <input type="text" name="name" required placeholder="ej. Sistema SIGECOM" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">Host / IP Destino:</label>
                    <input type="text" name="host_ip" placeholder="ej. 10.20.0.50" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">Puerto TCP (opcional):</label>
                    <input type="number" name="port" placeholder="ej. 80, 443, 389" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
                </div>
            </div>

            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px]">URL Completa (para WEB / PROXY):</label>
                <input type="text" name="web_url" placeholder="http://10.20.0.50/login" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
            </div>

            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px]">Credenciales / Token (Opcional):</label>
                <input type="text" name="credentials" placeholder="usuario:clave" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" id="create-service-active" name="is_active" value="1" checked class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan"/>
                <label for="create-service-active" class="text-obsidian-muted cursor-pointer select-none">Activar monitoreo inmediatamente</label>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-obsidian-border">
                <button type="button" onclick="closeModal('modal-create-service')" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white cursor-pointer">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">save</span>
                    Guardar Servicio
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR SERVICIO (Solo Administrador) -->
<div id="modal-edit-service" onclick="if(event.target === this) closeModal('modal-edit-service')" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-lg w-full rounded-2xl p-6 border border-obsidian-border shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <h3 class="text-sm font-bold text-white uppercase font-mono flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan text-base">edit</span>
                Editar Parámetros de Servicio
            </h3>
            <button type="button" onclick="closeModal('modal-edit-service')" class="text-obsidian-muted hover:text-white text-xl p-1 rounded hover:bg-obsidian-panel leading-none">&times;</button>
        </div>
        <form id="form-edit-service" method="POST" class="space-y-4 font-mono text-xs">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">ID / Slug:</label>
                    <input type="text" id="edit-service-letter" name="letter" maxlength="10" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white font-bold"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">Tipo de Servicio:</label>
                    <select id="edit-service-type" name="type" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white">
                        <option value="WEB">WEB (HTTP/S)</option>
                        <option value="PING">PING (ICMP)</option>
                        <option value="DNS">DNS</option>
                        <option value="SMTP">SMTP</option>
                        <option value="DHCP">DHCP</option>
                        <option value="LDAP">LDAP</option>
                        <option value="PROXY">PROXY</option>
                        <option value="CUPS">CUPS</option>
                    </select>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">Ámbito:</label>
                    <select id="edit-service-scope" name="scope" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white font-semibold">
                        <option value="corporativo">Corporativo</option>
                        <option value="regional">Regional</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px]">Nombre Descriptivo:</label>
                <input type="text" id="edit-service-name" name="name" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">Host / IP Destino:</label>
                    <input type="text" id="edit-service-host" name="host_ip" placeholder="ej. 10.0.0.1" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-1 text-[11px]">Puerto TCP (opcional):</label>
                    <input type="number" id="edit-service-port" name="port" placeholder="ej. 80, 443" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
                </div>
            </div>

            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px]">URL Completa (para WEB / PROXY):</label>
                <input type="text" id="edit-service-url" name="web_url" placeholder="https://ejemplo.com/salud" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
            </div>

            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px]">Credenciales / Token (Opcional):</label>
                <input type="text" id="edit-service-credentials" name="credentials" placeholder="user:pass o token" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white"/>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" id="edit-service-active" name="is_active" value="1" class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan"/>
                <label for="edit-service-active" class="text-obsidian-muted cursor-pointer select-none">Servicio activo en monitoreo</label>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-obsidian-border">
                <button type="button" onclick="closeModal('modal-edit-service')" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white cursor-pointer">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold cursor-pointer">Actualizar Servicio</button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- MODAL HISTÓRICO Y TELEMETRÍA (CHART.JS) -->
<div id="modal-service-history" onclick="if(event.target === this) closeServiceHistoryModal()" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden items-center justify-center p-3 sm:p-6">
    <div class="glass-panel max-w-4xl w-full rounded-2xl border border-obsidian-border/90 shadow-2xl space-y-4 max-h-[95vh] overflow-y-auto custom-scroll p-5 sm:p-6 bg-[#07172b]/95">
        <!-- CABECERA DEL HISTÓRICO -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-obsidian-border/80 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-obsidian-cyan/10 border border-obsidian-cyan/40 flex items-center justify-center text-obsidian-cyan">
                    <span class="material-symbols-outlined text-xl">show_chart</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span id="hist-service-id" class="text-xs font-mono font-bold text-obsidian-cyan bg-obsidian-panel px-2 py-0.5 rounded border border-obsidian-border">ID #1</span>
                        <h3 id="hist-service-name" class="text-base font-bold text-white font-sans truncate max-w-md">Servicio</h3>
                        <span id="hist-service-type" class="text-[10px] font-mono font-bold uppercase bg-obsidian-panel text-cyan-300 px-2 py-0.5 rounded border border-cyan-500/40">WEB</span>
                    </div>
                    <p id="hist-service-target" class="text-xs font-mono text-obsidian-muted mt-0.5 truncate">Destino: 10.20.0.1</p>
                </div>
            </div>

            <!-- CONTROLES DE RANGO TEMPORAL -->
            <div class="flex items-center gap-2 self-end sm:self-auto">
                <div class="inline-flex rounded-lg p-1 bg-obsidian-panel border border-obsidian-border text-xs font-mono">
                    <button type="button" onclick="loadServiceHistory('6h')" id="btn-range-6h" class="px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition range-btn cursor-pointer">6H</button>
                    <button type="button" onclick="loadServiceHistory('24h')" id="btn-range-24h" class="px-2.5 py-1 rounded-md bg-obsidian-cyan text-black font-bold transition range-btn cursor-pointer">24H</button>
                    <button type="button" onclick="loadServiceHistory('7d')" id="btn-range-7d" class="px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition range-btn cursor-pointer">7D</button>
                    <button type="button" onclick="loadServiceHistory('30d')" id="btn-range-30d" class="px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition range-btn cursor-pointer">30D</button>
                </div>
                <button type="button" onclick="closeServiceHistoryModal()" class="p-1.5 rounded-lg text-obsidian-muted hover:text-white hover:bg-obsidian-panel text-xl leading-none transition cursor-pointer" title="Cerrar">&times;</button>
            </div>
        </div>

        <!-- 4 TARJETAS DE MÉTRICAS KPI -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Disponibilidad</span>
                    <span class="material-symbols-outlined text-xs text-emerald-400">verified</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-stat-uptime" class="text-lg font-bold font-mono text-emerald-400">--%</span>
                </div>
                <p id="hist-stat-checks" class="text-[9px] font-mono text-obsidian-muted mt-0.5">-- chequeos totales</p>
            </div>

            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Latencia Media</span>
                    <span class="material-symbols-outlined text-xs text-obsidian-cyan">speed</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-stat-avg" class="text-lg font-bold font-mono text-white">-- ms</span>
                </div>
                <p class="text-[9px] font-mono text-obsidian-muted mt-0.5">Tiempo de respuesta</p>
            </div>

            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Latencia Máxima</span>
                    <span class="material-symbols-outlined text-xs text-amber-400">trending_up</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-stat-max" class="text-lg font-bold font-mono text-amber-400">-- ms</span>
                </div>
                <p class="text-[9px] font-mono text-obsidian-muted mt-0.5">Pico de latencia</p>
            </div>

            <div class="glass-card rounded-xl p-3 border border-obsidian-border bg-obsidian-panel/40">
                <div class="flex items-center justify-between text-obsidian-muted text-[10px] font-mono uppercase">
                    <span>Caídas / Fallas</span>
                    <span class="material-symbols-outlined text-xs text-red-400">warning</span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span id="hist-stat-outages" class="text-lg font-bold font-mono text-white">0</span>
                </div>
                <p id="hist-stat-last-down" class="text-[9px] font-mono text-obsidian-muted mt-0.5">Sin incidentes</p>
            </div>
        </div>

        <!-- CONTENEDOR DEL GRÁFICO DE LÍNEA -->
        <div class="glass-card rounded-xl p-4 border border-obsidian-border space-y-3 bg-obsidian-panel/50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-obsidian-cyan"></span>
                    <h4 class="text-xs font-bold font-mono uppercase text-white tracking-wider">Histórico Temporal de Latencia & Disponibilidad</h4>
                </div>
                <div class="flex items-center gap-3 text-[10px] font-mono text-obsidian-muted">
                    <span class="flex items-center gap-1"><span class="w-2 h-0.5 bg-obsidian-cyan"></span> Latencia (ms)</span>
                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Caída / Timeout</span>
                </div>
            </div>
            
            <div class="relative w-full h-[220px]">
                <canvas id="serviceHistoryChart"></canvas>
                <div id="chart-loading-overlay" class="absolute inset-0 flex items-center justify-center bg-obsidian-bg/80 backdrop-blur-xs rounded-lg hidden">
                    <div class="flex items-center gap-2 text-obsidian-cyan font-mono text-xs">
                        <span class="material-symbols-outlined animate-spin text-base">sync</span>
                        <span>Cargando telemetría...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- REGISTRO RECIENTE DE INCIDENTES / CAÍDAS -->
        <div class="glass-card rounded-xl p-4 border border-obsidian-border space-y-2 bg-obsidian-panel/30">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold font-mono uppercase text-white tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-amber-400 text-sm">history_toggle_off</span>
                    Registro de Incidentes y Caídas Detectadas
                </h4>
                <span id="hist-incidents-badge" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40">
                    0 Incidentes
                </span>
            </div>

            <div class="overflow-x-auto max-h-36 overflow-y-auto custom-scroll">
                <table class="w-full text-left text-[11px] font-mono">
                    <thead class="bg-obsidian-panel/80 text-obsidian-muted uppercase text-[9.5px]">
                        <tr>
                            <th class="px-3 py-1.5">Fecha y Hora</th>
                            <th class="px-3 py-1.5">Tiempo Relativo</th>
                            <th class="px-3 py-1.5">Estado / Causa</th>
                            <th class="px-3 py-1.5 text-right">Código HTTP</th>
                        </tr>
                    </thead>
                    <tbody id="hist-incidents-tbody" class="divide-y divide-obsidian-border/40">
                        <tr>
                            <td colspan="4" class="px-3 py-3 text-center text-obsidian-muted text-[10px]">
                                No se registran caídas en el período seleccionado.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let currentServiceId = null;
    let currentRange = '24h';
    let historyChart = null;
    let activeFilterType = 'all';

    function openModal(id) {
        const m = document.getElementById(id);
        if (m) {
            m.classList.remove('hidden');
            m.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }
    }

    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) {
            m.classList.remove('flex');
            m.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    }

    function closeServiceHistoryModal() {
        closeModal('modal-service-history');
        if (historyChart) {
            historyChart.destroy();
            historyChart = null;
        }
    }

    // Cerrar modal al presionar tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeServiceHistoryModal();
            closeModal('modal-edit-service');
            closeModal('modal-create-service');
        }
    });

    function openCreateServiceModal() {
        document.getElementById('form-create-service').reset();
        openModal('modal-create-service');
    }

    function openEditServiceModal(s) {
        document.getElementById('form-edit-service').action = `/admin/services/${s.id}`;
        document.getElementById('edit-service-letter').value = s.letter || '';
        document.getElementById('edit-service-type').value = s.type;
        document.getElementById('edit-service-scope').value = s.scope || 'corporativo';
        document.getElementById('edit-service-name').value = s.name;
        document.getElementById('edit-service-host').value = s.host_ip || '';
        document.getElementById('edit-service-port').value = s.port || '';
        document.getElementById('edit-service-url').value = s.web_url || '';
        document.getElementById('edit-service-credentials').value = s.credentials || '';
        document.getElementById('edit-service-active').checked = !!s.is_active;
        openModal('modal-edit-service');
    }

    async function openServiceHistoryModal(serviceId, name, idVal, type) {
        currentServiceId = serviceId;
        document.getElementById('hist-service-name').innerText = name;
        document.getElementById('hist-service-id').innerText = `ID #${serviceId}`;
        document.getElementById('hist-service-type').innerText = type;
        
        openModal('modal-service-history');
        await loadServiceHistory(currentRange);
    }

    async function loadServiceHistory(range) {
        if (!currentServiceId) return;
        currentRange = range;
        
        // Update active tab buttons
        document.querySelectorAll('.range-btn').forEach(btn => {
            btn.className = 'px-2.5 py-1 rounded-md text-obsidian-muted hover:text-white transition range-btn cursor-pointer';
        });
        const activeBtn = document.getElementById(`btn-range-${range}`);
        if (activeBtn) {
            activeBtn.className = 'px-2.5 py-1 rounded-md bg-obsidian-cyan text-black font-bold transition range-btn cursor-pointer';
        }

        const overlay = document.getElementById('chart-loading-overlay');
        if (overlay) overlay.classList.remove('hidden');

        try {
            const res = await fetch(`/admin/services/${currentServiceId}/history?range=${range}`);
            if (!res.ok) throw new Error('Error al cargar histórico');
            const data = await res.json();
            if (!data.success) throw new Error('Datos no disponibles');

            // Target
            document.getElementById('hist-service-target').innerText = `Destino: ${data.service.target}`;
            
            // Stats
            document.getElementById('hist-stat-uptime').innerText = `${data.stats.uptime_percentage}%`;
            document.getElementById('hist-stat-checks').innerText = `${data.stats.total_checks} chequeos (${data.stats.up_checks} OK / ${data.stats.down_checks} caídos)`;
            document.getElementById('hist-stat-avg').innerText = `${data.stats.avg_latency} ms`;
            document.getElementById('hist-stat-max').innerText = `${data.stats.max_latency} ms`;
            document.getElementById('hist-stat-outages').innerText = `${data.stats.down_checks}`;
            
            const lastDown = document.getElementById('hist-stat-last-down');
            if (data.stats.down_checks > 0) {
                lastDown.innerText = `${data.stats.down_checks} eventos de indisponibilidad`;
                lastDown.className = 'text-[9px] font-mono text-red-400 mt-0.5';
            } else {
                lastDown.innerText = '100% de disponibilidad';
                lastDown.className = 'text-[9px] font-mono text-emerald-400 mt-0.5';
            }

            // Incidents Table
            const incidentsBadge = document.getElementById('hist-incidents-badge');
            const incidentsTbody = document.getElementById('hist-incidents-tbody');
            
            if (data.incidents && data.incidents.length > 0) {
                incidentsBadge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-red-950 text-red-400 border border-red-500/40 glow-red';
                incidentsBadge.innerText = `${data.incidents.length} Caídas`;
                
                incidentsTbody.innerHTML = data.incidents.map(inc => `
                    <tr class="hover:bg-obsidian-panel/50 text-red-300">
                        <td class="px-3 py-1.5 font-bold">${inc.time}</td>
                        <td class="px-3 py-1.5 text-obsidian-muted">${inc.time_human}</td>
                        <td class="px-3 py-1.5">${inc.status_message}</td>
                        <td class="px-3 py-1.5 text-right font-bold">${inc.http_code}</td>
                    </tr>
                `).join('');
            } else {
                incidentsBadge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40';
                incidentsBadge.innerText = '0 Incidentes';
                incidentsTbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="px-3 py-3 text-center text-obsidian-muted text-[10px]">
                            No se registran caídas en el período seleccionado.
                        </td>
                    </tr>
                `;
            }

            // Render Chart.js
            renderHistoryChart(data.labels, data.latencies, data.statuses, data.points);

        } catch (err) {
            console.error('Error fetching history:', err);
        } finally {
            if (overlay) overlay.classList.add('hidden');
        }
    }

    function renderHistoryChart(labels, latencies, statuses, points) {
        const ctx = document.getElementById('serviceHistoryChart').getContext('2d');
        
        const isAllDown = statuses.length > 0 && statuses.every(s => s === 0);
        const lineColor = isAllDown ? '#ef4444' : '#22d3ee';

        const gradient = ctx.createLinearGradient(0, 0, 0, 200);
        if (isAllDown) {
            gradient.addColorStop(0, 'rgba(239, 68, 68, 0.35)');
            gradient.addColorStop(1, 'rgba(239, 68, 68, 0.0)');
        } else {
            gradient.addColorStop(0, 'rgba(34, 211, 238, 0.35)');
            gradient.addColorStop(1, 'rgba(34, 211, 238, 0.0)');
        }

        const pointBackgroundColors = statuses.map(s => s === 1 ? '#22d3ee' : '#ef4444');
        const pointBorderColors = statuses.map(s => s === 1 ? '#051424' : '#fee2e2');
        const pointRadiuses = statuses.map(s => s === 1 ? (statuses.length > 30 ? 0 : 3) : 6);
        const pointHoverRadiuses = statuses.map(s => s === 1 ? 5 : 8);

        const chartData = {
            labels: labels,
            datasets: [{
                label: 'Latencia (ms)',
                data: latencies,
                borderColor: lineColor,
                borderWidth: 2,
                backgroundColor: gradient,
                fill: true,
                tension: 0.3,
                spanGaps: true,
                pointBackgroundColor: pointBackgroundColors,
                pointBorderColor: pointBorderColors,
                pointBorderWidth: 1.5,
                pointRadius: pointRadiuses,
                pointHoverRadius: pointHoverRadiuses,
            }]
        };

        if (historyChart) {
            historyChart.destroy();
        }

        historyChart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(7, 23, 43, 0.95)',
                        titleColor: lineColor,
                        bodyColor: '#ffffff',
                        borderColor: isAllDown ? 'rgba(239, 68, 68, 0.5)' : 'rgba(34, 211, 238, 0.4)',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const pt = points[idx];
                                if (!pt) return `Latencia: ${context.parsed.y} ms`;
                                if (pt.is_up) {
                                    return `Latencia: ${pt.latency_ms} ms (${pt.status_message})`;
                                } else {
                                    return `SERVICIO CAÍDO / APAGADO (${pt.status_message})`;
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(28, 46, 71, 0.5)' },
                        ticks: {
                            color: '#8295b0',
                            font: { family: 'JetBrains Mono', size: 9.5 },
                            maxTicksLimit: 12,
                        }
                    },
                    y: {
                        beginAtZero: true,
                        suggestedMax: 10,
                        grid: { color: 'rgba(28, 46, 71, 0.5)' },
                        ticks: {
                            color: '#8295b0',
                            font: { family: 'JetBrains Mono', size: 9.5 },
                            callback: function(value) {
                                return value + ' ms';
                            }
                        }
                    }
                }
            }
        });
    }

    // --- FILTRADO EN TIEMPO REAL ---
    function setServiceFilter(type) {
        activeFilterType = type;
        document.querySelectorAll('.filter-chip').forEach(btn => {
            btn.className = 'filter-chip px-2.5 py-1 rounded-lg transition font-semibold text-obsidian-muted hover:text-white bg-obsidian-panel border border-obsidian-border cursor-pointer';
        });
        const activeBtn = document.getElementById(`filter-btn-${type}`);
        if (activeBtn) {
            activeBtn.className = 'filter-chip px-2.5 py-1 rounded-lg transition font-semibold bg-obsidian-cyan text-black shadow-sm cursor-pointer';
        }
        filterServicesList();
    }

    function filterServicesList() {
        const query = (document.getElementById('service-search-input').value || '').toLowerCase().trim();
        const cards = document.querySelectorAll('.service-card');

        cards.forEach(card => {
            const name = card.getAttribute('data-name') || '';
            const letter = card.getAttribute('data-letter') || '';
            const type = card.getAttribute('data-type') || '';
            const target = card.getAttribute('data-target') || '';
            const scope = card.getAttribute('data-scope') || '';
            const isActive = card.getAttribute('data-active') === '1';
            const status = card.getAttribute('data-status') || '';

            const matchesSearch = !query || name.includes(query) || letter.includes(query) || type.includes(query) || target.includes(query);
            
            let matchesFilter = true;
            if (activeFilterType === 'corporativo') {
                matchesFilter = scope === 'corporativo';
            } else if (activeFilterType === 'regional') {
                matchesFilter = scope === 'regional';
            } else if (activeFilterType === 'active') {
                matchesFilter = isActive;
            } else if (activeFilterType === 'down') {
                matchesFilter = isActive && status === 'down';
            }

            if (matchesSearch && matchesFilter) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });

        // Ocultar sección si no contiene tarjetas visibles
        ['corporativo', 'regional', 'otros'].forEach(scope => {
            const sec = document.getElementById(`section-scope-${scope}`);
            if (sec) {
                const visible = sec.querySelectorAll('.service-card:not(.hidden)').length;
                if (visible === 0) {
                    sec.classList.add('hidden');
                } else {
                    sec.classList.remove('hidden');
                }
            }
        });
    }
</script>
@endsection
