@extends('layouts.admin')

@section('page_title', 'Manual de Ayuda y Guía Operativa')

@section('admin_content')
<div class="space-y-6">

    <!-- ========================================================================= -->
    <!-- ENCABEZADO PRINCIPAL & HUD DE ASISTENCIA                                  -->
    <!-- ========================================================================= -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 p-5 rounded-2xl bg-obsidian-panel/90 border border-obsidian-border backdrop-blur-md shadow-xl">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-cyan-500/20 to-blue-500/30 border border-cyan-400/50 flex items-center justify-center text-cyan-300 glow-cyan shrink-0 shadow-lg shadow-cyan-500/20">
                <span class="material-symbols-outlined text-3xl">menu_book</span>
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-base sm:text-lg font-bold text-white tracking-tight font-mono">
                        MANUAL DE USUARIO & GUÍA OPERATIVA
                    </h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-cyan-950/80 text-cyan-300 border border-cyan-500/40">
                        {{ $systemVersion }}
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold {{ $clusterRole === 'master' ? 'bg-amber-950/80 text-amber-300 border border-amber-500/40' : 'bg-blue-950/80 text-blue-300 border border-blue-500/40' }}">
                        MODO {{ strtoupper($clusterRole) }}
                    </span>
                </div>
                <p class="text-xs font-mono text-obsidian-muted mt-0.5">
                    Referencia completa paso a paso de cada pantalla, botones, métricas y operaciones del sistema.
                </p>
            </div>
        </div>

        <!-- CONTROLES SUPERIORES (BÚSQUEDA Y BOTÓN ACERCA DE) -->
        <div class="flex items-center gap-3 self-stretch lg:self-auto">
            <div class="relative flex-1 lg:w-72">
                <span class="material-symbols-outlined absolute left-3 top-2.5 text-obsidian-muted text-base pointer-events-none">search</span>
                <input type="text" id="manual-search-input" oninput="filterManualModules()" onkeyup="filterManualModules()" placeholder="Buscar módulo, botón o métrica..." class="w-full bg-[#040d1a] border border-obsidian-border rounded-xl pl-9 pr-3 py-2 text-xs font-mono text-white placeholder-obsidian-muted focus:outline-none focus:border-obsidian-cyan transition">
            </div>
            <button type="button" onclick="openAboutSystemModal()" class="px-3.5 py-2 rounded-xl bg-purple-950/40 border border-purple-500/40 text-purple-300 hover:bg-purple-900/50 hover:text-white font-mono text-xs font-semibold transition flex items-center gap-1.5 shrink-0 cursor-pointer shadow-sm">
                <span class="material-symbols-outlined text-base">info</span>
                <span class="hidden sm:inline">Acerca</span>
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- PANEL DE NAVEGACIÓN RÁPIDA POR MÓDULOS                                    -->
    <!-- ========================================================================= -->
    <div id="panel-indice-modulos" class="scroll-mt-20 p-4 rounded-2xl bg-obsidian-panel/60 border border-obsidian-border/70 space-y-3">
        <div class="flex items-center justify-between text-xs font-mono">
            <span class="text-obsidian-cyan font-bold uppercase tracking-wider flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm">explore</span>
                Índice Rápido de Módulos (Haz clic para saltar a la sección)
            </span>
            <span class="text-obsidian-muted text-[11px]">15 secciones detalladas</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2 text-xs font-mono" id="quick-nav-container">
            <a href="#modulo-cluster" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-cyan-400">lan</span>
                <span class="truncate">1. Clúster & Roles</span>
            </a>
            <a href="#modulo-dashboard" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-cyan-400">dashboard</span>
                <span class="truncate">2. Dashboard</span>
            </a>
            <a href="#modulo-topologia" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-purple-400">hub</span>
                <span class="truncate">3. Topología Red</span>
            </a>
            <a href="#modulo-servicios" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-blue-400">dns</span>
                <span class="truncate">4. Servicios & SSL</span>
            </a>
            <a href="#modulo-sedes" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-emerald-400">domain</span>
                <span class="truncate">5. Sedes & Enlaces</span>
            </a>
            <a href="#modulo-equipos" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-amber-400">router</span>
                <span class="truncate">6. Equipos & WoL</span>
            </a>
            <a href="#modulo-discovery" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-red-400">radar</span>
                <span class="truncate">7. Radar & Discovery</span>
            </a>
            <a href="#modulo-snmp" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-cyan-400">sensors</span>
                <span class="truncate">8. Consola SNMP</span>
            </a>
            <a href="#modulo-alertas" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-pink-400">notifications_active</span>
                <span class="truncate">9. Alertas & IA</span>
            </a>
            <a href="#modulo-ncm" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-indigo-400">settings_backup_restore</span>
                <span class="truncate">10. Respaldos NCM</span>
            </a>
            <a href="#modulo-usuarios" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-purple-400">group</span>
                <span class="truncate">11. Usuarios & Roles</span>
            </a>
            <a href="#modulo-auditoria" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-amber-400">policy</span>
                <span class="truncate">12. Auditoría</span>
            </a>
            <a href="#modulo-seguridad" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-red-500">gavel</span>
                <span class="truncate">13. Baneos & Jaulas</span>
            </a>
            <a href="#modulo-bot" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-cyan-300">terminal</span>
                <span class="truncate">14. Bot Telegram</span>
            </a>
            <a href="#modulo-configuracion" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-slate-300">tune</span>
                <span class="truncate">15. Configuración</span>
            </a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 1: ARQUITECTURA DE CLÚSTER & ROLES                                -->
    <!-- ========================================================================= -->
    <div id="modulo-cluster" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-300">
                    <span class="material-symbols-outlined text-xl">lan</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        1. Arquitectura de Clúster de Alta Disponibilidad (Master / Esclavo)
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Diferencias operativas entre el Servidor Principal y el Nodo Réplica
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-cyan-950/80 text-cyan-300 border border-cyan-500/40">
                    GOBERNANZA
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-mono">
            <!-- SERVIDOR MASTER -->
            <div class="p-4 rounded-xl bg-amber-950/20 border border-amber-500/40 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-amber-300 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base">crown</span>
                        SERVIDOR MASTER (10.20.23.252)
                    </span>
                    <span class="px-2 py-0.5 rounded bg-amber-950 text-[10px] font-bold text-amber-400 border border-amber-500/30">Escaneo Oficial</span>
                </div>
                <p class="text-slate-300 leading-relaxed">
                    Es la autoridad central de monitoreo. Es el <b>único nodo autorizado</b> para consultar directamente hacia el firewall perimetral (pfSense) y ejecutar escaneos de infraestructura hacia la WAN.
                </p>
                <ul class="list-disc list-inside text-obsidian-muted space-y-1">
                    <li>Edición y mutación habilitada en todos los módulos.</li>
                    <li>Genera las instantáneas cifradas (<code class="text-amber-300">/api/cluster/snapshot</code>).</li>
                    <li>Despacha las notificaciones oficiales programadas a los canales corporativos de Telegram.</li>
                </ul>
            </div>

            <!-- SERVIDOR SLAVE -->
            <div class="p-4 rounded-xl bg-blue-950/20 border border-blue-500/40 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-blue-300 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base">sync</span>
                        SERVIDOR SLAVE / ESCLAVO (10.20.23.221)
                    </span>
                    <span class="px-2 py-0.5 rounded bg-blue-950 text-[10px] font-bold text-blue-400 border border-blue-500/30">Solo Lectura</span>
                </div>
                <p class="text-slate-300 leading-relaxed">
                    Actúa como réplica de respaldo y consulta. <b>No satura el firewall perimetral</b> ni arriesga baneos por credenciales concurrentes.
                </p>
                <ul class="list-disc list-inside text-obsidian-muted space-y-1">
                    <li>Sincronización continua de telemetría e históricos cada 1-15 minutos mediante <code class="text-blue-300">monitor_web_sync.py</code>.</li>
                    <li>Los botones de edición/creación/eliminación están protegidos y bloqueados visualmente con badges informativos.</li>
                    <li>Plena autonomía para consultar el bot de Telegram y navegar todo el portal.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 2: DASHBOARD & ESTADO GLOBAL                                      -->
    <!-- ========================================================================= -->
    <div id="modulo-dashboard" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-300">
                    <span class="material-symbols-outlined text-xl">dashboard</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        2. Dashboard & Estado Global
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Centro de operaciones en tiempo real con telemetría de sedes, servicios y proxies
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.dashboard') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <!-- CAPTURA CON LIGHTBOX -->
        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/dashboard.png') }}', 'Dashboard Principal de Monitoreo')">
            <img src="{{ asset('img/help/dashboard.png') }}" alt="Dashboard" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-cyan-300 uppercase tracking-wider">¿Qué ves en esta pantalla?</h3>
            <ul class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> Badge de Estatus Global:</b>
                    <p class="text-obsidian-muted text-[11px]">Indica si la red está <span class="text-emerald-400 font-bold">OPERACIONAL</span> (todos los servicios arriba), <span class="text-amber-400 font-bold">DEGRADADO</span> (fallas parciales o latencia alta) o <span class="text-red-400 font-bold">CRÍTICO</span>.</p>
                </li>
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-cyan-400">bolt</span> Botón 'Escanear Ahora':</b>
                    <p class="text-obsidian-muted text-[11px]">Ubicado en el pie del sidebar izquierdo. Permite al administrador disparar una comprobación síncrona manual de toda la infraestructura.</p>
                </li>
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-purple-400">domain</span> Tarjetas de Sedes Regionales:</b>
                    <p class="text-obsidian-muted text-[11px]">Despliegan el estado de conectividad en tiempo real de Valle Seco y sedes remotas, con latencia RTT promedio y pérdida de paquetes.</p>
                </li>
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-amber-400">alt_route</span> Widget de Proxies Squid:</b>
                    <p class="text-obsidian-muted text-[11px]">Monitorea los puertos de navegación corporativos, disponibilidad de cada proxy y tiempo de respuesta en milisegundos.</p>
                </li>
            </ul>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 3: TOPOLOGÍA DE RED                                               -->
    <!-- ========================================================================= -->
    <div id="modulo-topologia" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-950/70 border border-purple-500/40 flex items-center justify-center text-purple-300">
                    <span class="material-symbols-outlined text-xl">hub</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        3. Topología de Red Visual Dinámica
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Grafo interactivo de interconexión con Cytoscape.js y detección de vecinos LLDP/CDP
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.topology.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/topologia.png') }}', 'Topología Visual de Red')">
            <img src="{{ asset('img/help/topologia.png') }}" alt="Topología" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-purple-300 uppercase tracking-wider">Flujo de Trabajo y Operaciones:</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <div class="text-cyan-400 font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">filter_alt</span>
                        Paso 1: Filtrar Sede
                    </div>
                    <p class="text-obsidian-muted text-[11px]">Selecciona la sede en el selector superior para aislar los nodos y enlaces pertenecientes a esa ubicación.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <div class="text-cyan-400 font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">touch_app</span>
                        Paso 2: Interacción con Nodos
                    </div>
                    <p class="text-obsidian-muted text-[11px]">Haz clic sobre cualquier switch o router para abrir el panel lateral con IP, modelo, enlaces ascendentes y estado operativo.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <div class="text-cyan-400 font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">reorder</span>
                        Paso 3: Re-escanear Vecinos
                    </div>
                    <p class="text-obsidian-muted text-[11px]">En el Servidor Master, presiona 'Re-escanear Vecinos' para consultar tablas CDP/LLDP y actualizar enlaces físicos.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 4: SERVICIOS & CONECTIVIDAD                                       -->
    <!-- ========================================================================= -->
    <div id="modulo-servicios" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-950/70 border border-blue-500/40 flex items-center justify-center text-blue-300">
                    <span class="material-symbols-outlined text-xl">dns</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        4. Servicios, Proxies & Certificados SSL/TLS
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Pestañas consolidadas para la supervisión de aplicativos web, navegación y criptografía
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.services.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/servicios.png') }}', 'Pestañas Consolidadas de Servicios y Proxies')">
            <img src="{{ asset('img/help/servicios.png') }}" alt="Servicios" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 font-mono text-xs">
            <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                <b class="text-cyan-300 block">Pestaña 1: Servicios Web & Red</b>
                <p class="text-obsidian-muted text-[11px]">Supervisa URLs HTTP/HTTPS, puertos TCP y DNS. Incluye indicador de SLA mensual y latencia.</p>
                <p class="text-[10px] text-cyan-400">Botón 'Historial': abre modal con gráficas interactivas en ventanas de 1h, 6h, 24h y 7d.</p>
            </div>
            <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                <b class="text-amber-300 block">Pestaña 2: Proxies de Navegación</b>
                <p class="text-obsidian-muted text-[11px]">Supervisión continua de Squid en puertos 3128/8080 con tiempos de respuesta en milisegundos.</p>
                <p class="text-[10px] text-amber-400">Permite validar en tiempo real si el proxy permite salida a Internet o rechaza conexiones.</p>
            </div>
            <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                <b class="text-purple-300 block">Pestaña 3: Certificados SSL/TLS</b>
                <p class="text-obsidian-muted text-[11px]">Inspección automática de fechas de expiración de certificados HTTPS con alertas previas a 30 días.</p>
                <p class="text-[10px] text-purple-400">Evita caídas imprevistas de portales por certificados vencidos.</p>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 5: SEDES & ENLACES REGIONALES                                     -->
    <!-- ========================================================================= -->
    <div id="modulo-sedes" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-950/70 border border-emerald-500/40 flex items-center justify-center text-emerald-300">
                    <span class="material-symbols-outlined text-xl">domain</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        5. Sedes & Enlaces Regionales
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Monitoreo geográfico de enlaces de fibra óptica, radioenlaces y puertas de enlace
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.sites.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/sedes.png') }}', 'Sedes y Enlaces de Infraestructura')">
            <img src="{{ asset('img/help/sedes.png') }}" alt="Sedes" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-2 font-mono text-xs">
            <h3 class="font-bold text-emerald-300 uppercase tracking-wider">Métricas Clave:</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 text-[11px]">
                <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <span class="text-obsidian-muted block text-[10px]">ESTADO DE ENLACE</span>
                    <span class="font-bold text-emerald-400">UP / DOWN</span>
                    <p class="text-obsidian-muted text-[10px] mt-0.5">Determinado por sondeos ICMP periódicos.</p>
                </div>
                <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <span class="text-obsidian-muted block text-[10px]">RTT PROMEDIO</span>
                    <span class="font-bold text-cyan-300">&lt; 15 ms</span>
                    <p class="text-obsidian-muted text-[10px] mt-0.5">Tiempo de ida y vuelta hacia el router perimetral.</p>
                </div>
                <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <span class="text-obsidian-muted block text-[10px]">PÉRDIDA DE PAQUETES</span>
                    <span class="font-bold text-amber-300">0.0% Pérdida</span>
                    <p class="text-obsidian-muted text-[10px] mt-0.5">Métrica vital para diagnosticar degradación de radioenlaces.</p>
                </div>
                <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <span class="text-obsidian-muted block text-[10px]">HISTÓRICO 24H</span>
                    <span class="font-bold text-purple-300">Curvas Rojas / Verdes</span>
                    <p class="text-obsidian-muted text-[10px] mt-0.5">Regla de Oro #3: las caídas siempre se grafican en rojo.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 6: EQUIPOS, HARDWARE, CICLO DE VIDA & WOL                         -->
    <!-- ========================================================================= -->
    <div id="modulo-equipos" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-950/70 border border-amber-500/40 flex items-center justify-center text-amber-300">
                    <span class="material-symbols-outlined text-xl">router</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        6. Equipos & Hardware: Ciclo de Vida y Wake-on-LAN (WoL)
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Inventario activo, matriz de garantías/EOL, encendido remoto por Magic Packets y consolas web
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.devices.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/ciclo_vida.png') }}', 'Matriz de Ciclo de Vida y Garantías')">
                <img src="{{ asset('img/help/ciclo_vida.png') }}" alt="Ciclo de Vida" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                    <span class="material-symbols-outlined">zoom_in</span>
                    <span>Clic para ampliar: Ciclo de Vida</span>
                </div>
            </div>
            <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/wol.png') }}', 'Panel de Wake-on-LAN')">
                <img src="{{ asset('img/help/wol.png') }}" alt="WoL" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                    <span class="material-symbols-outlined">zoom_in</span>
                    <span>Clic para ampliar: Wake-on-LAN</span>
                </div>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-amber-300 uppercase tracking-wider">Acciones Operativas:</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-cyan-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">power_settings_new</span> Despertar Equipo (WoL):</b>
                    <p class="text-obsidian-muted text-[11px]">Envía un paquete mágico Ethernet Broadcast UDP 9 con la dirección MAC del equipo para encenderlo a distancia.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-purple-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">terminal</span> Terminales SSH / Telnet / VNC:</b>
                    <p class="text-obsidian-muted text-[11px]">Acceso de gestión web directo al dispositivo en un solo clic, sin requerir clientes de escritorio externos.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-amber-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">event_repeat</span> Fechas EOL y Salud SMART:</b>
                    <p class="text-obsidian-muted text-[11px]">Alertas automáticas cuando una garantía expira en menos de 90 días o cuando un disco reporta sectores reasignados.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 7: RADAR & AUTO-DISCOVERY                                         -->
    <!-- ========================================================================= -->
    <div id="modulo-discovery" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-950/70 border border-red-500/40 flex items-center justify-center text-red-300">
                    <span class="material-symbols-outlined text-xl">radar</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        7. Radar & Descubrimiento de Red (Anti-Rogue)
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Escaneo continuo de subredes, detección de nuevos hosts e identificación de intrusos
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.discovery.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/discovery.png') }}', 'Auto-Discovery de Red')">
            <img src="{{ asset('img/help/discovery.png') }}" alt="Discovery" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-red-300 uppercase tracking-wider">¿Cómo gestionar dispositivos detectados?</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-[11px]">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">1. Estado 'ROGUE' (Alerta Roja):</b>
                    <p class="text-obsidian-muted">Aparece cuando una IP o MAC desconocida emite tráfico en la subred sin haber sido clasificada previamente.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">2. Botón 'Clasificar':</b>
                    <p class="text-obsidian-muted">Permite asignar un nombre, departamento, tipo de equipo (Servidor, Impresora, PC, Switch) y marcarlo como autorizado.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">3. Historial de Presencia:</b>
                    <p class="text-obsidian-muted">Registra la fecha y hora de la primera y última vez que el dispositivo fue avistado en la red.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 8: CONSOLA SNMP 360° & TRAMPAS PUSH                               -->
    <!-- ========================================================================= -->
    <div id="modulo-snmp" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-300">
                    <span class="material-symbols-outlined text-xl">sensors</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        8. Consola SNMP 360° & Trampas Push
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Telemetría por interfaces de red, métricas de ancho de banda y recepción de Trampas UDP 162
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.snmp.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/snmp.png') }}', 'Consola SNMP 360 y Trampas Push')">
            <img src="{{ asset('img/help/snmp.png') }}" alt="SNMP" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-2 font-mono text-xs">
            <h3 class="font-bold text-cyan-300 uppercase tracking-wider">Capacidades de Telemetría:</h3>
            <ul class="list-disc list-inside text-obsidian-muted space-y-1 text-[11px]">
                <li><b>Pestaña 1 (Dispositivos SNMP):</b> Consulta de CPU, memoria RAM, uptime del sistema y estado de puertos Gigabit/FastEthernet.</li>
                <li><b>Botón 'Ver Interfaces':</b> Muestra la tabla de bocas del switch con velocidad negociada, tráfico In/Out en tiempo real y errores de CRC.</li>
                <li><b>Pestaña 2 (Trampas Push UDP 162):</b> Receptor asíncrono para notificaciones automáticas de eventos críticos como caídas de enlace (linkDown) o fuentes de poder redundantes sin esperar el ciclo de sondeo.</li>
            </ul>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 9: CENTRO DE ALERTAS & CAPACIDAD PREDICTIVA                       -->
    <!-- ========================================================================= -->
    <div id="modulo-alertas" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-pink-950/70 border border-pink-500/40 flex items-center justify-center text-pink-300">
                    <span class="material-symbols-outlined text-xl">notifications_active</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        9. Centro de Alertas & Capacidad Predictiva
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Correlación inteligente de fallas, escalación y predicción con el motor local de IA
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.alerts.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/alertas.png') }}', 'Centro de Alertas y Capacidad Predictiva')">
            <img src="{{ asset('img/help/alertas.png') }}" alt="Alertas" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 font-mono text-xs">
            <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                <b class="text-pink-300 block">Gestión de Incidentes Activos</b>
                <p class="text-obsidian-muted text-[11px]">Las alertas se agrupan por severidad (Crítica, Mayor, Advertencia). Puedes presionar <span class="text-emerald-400 font-bold">Reconocer (ACK)</span> para registrar que el equipo de soporte ya está interviniendo, o <span class="text-amber-400 font-bold">Silenciar</span> durante ventanas de mantenimiento.</p>
            </div>
            <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                <b class="text-cyan-300 block">Capacidad Predictiva con Motor Local de IA</b>
                <p class="text-obsidian-muted text-[11px]">Algoritmos de regresión y análisis de patrones en el motor local de IA alertan antes de que ocurra una saturación de ancho de banda o un agotamiento de almacenamiento en servidores de misión crítica.</p>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 10: RESPALDOS Y CONFIGURACIONES (NCM)                             -->
    <!-- ========================================================================= -->
    <div id="modulo-ncm" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-950/70 border border-indigo-500/40 flex items-center justify-center text-indigo-300">
                    <span class="material-symbols-outlined text-xl">settings_backup_restore</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        10. Respaldos y Configuraciones (NCM)
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Network Configuration Management: control de versiones de running-config y diff visual
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.configs.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/ncm.png') }}', 'Respaldos NCM y Control de Versiones')">
            <img src="{{ asset('img/help/ncm.png') }}" alt="NCM" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-2 font-mono text-xs">
            <h3 class="font-bold text-indigo-300 uppercase tracking-wider">Operaciones de Configuración:</h3>
            <ul class="list-disc list-inside text-obsidian-muted space-y-1 text-[11px]">
                <li><b>Descarga Automatizada:</b> El motor extrae periódicamente la <code class="text-cyan-300">running-config</code> de switches Cisco y routers vía SSH/SCP.</li>
                <li><b>Comparador Visual (Diff):</b> Resalta en verde las líneas agregadas y en rojo las líneas eliminadas entre dos versiones de configuración.</li>
                <li><b>Detección de Cambios No Autorizados:</b> Notifica al instante si alguien modificó VLANs, contraseñas o rutas estáticas fuera del horario aprobado.</li>
            </ul>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 11: USUARIOS, ROLES & LDAP                                        -->
    <!-- ========================================================================= -->
    <div id="modulo-usuarios" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-950/70 border border-purple-500/40 flex items-center justify-center text-purple-300">
                    <span class="material-symbols-outlined text-xl">group</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        11. Usuarios, Roles & Control de Acceso (RBAC)
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Autenticación híbrida LDAP institucional y cuentas locales de administración
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.users.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
                @endif
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/usuarios.png') }}', 'Usuarios y Roles RBAC')">
            <img src="{{ asset('img/help/usuarios.png') }}" alt="Usuarios" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 font-mono text-xs">
            <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                <b class="text-red-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">shield_person</span> Rol Administrador:</b>
                <p class="text-obsidian-muted text-[11px]">Acceso total: gestión de usuarios, auditoría, configuración de parámetros, activación de escaneos y cambios en clúster.</p>
            </div>
            <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                <b class="text-cyan-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">person</span> Rol Operador Corporativo:</b>
                <p class="text-obsidian-muted text-[11px]">Acceso a dashboards de telemetría, visualización de topología, consola SNMP, terminales web de soporte y reconocimiento de alertas.</p>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 12: AUDITORÍA & TRAZABILIDAD FORENSE                              -->
    <!-- ========================================================================= -->
    <div id="modulo-auditoria" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-950/70 border border-amber-500/40 flex items-center justify-center text-amber-300">
                    <span class="material-symbols-outlined text-xl">policy</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        12. Auditoría del Sistema & Trazabilidad Forense
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Custodia inmutable de registros, pistas de auditoría y comparador antes/después
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.audit.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
                @endif
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/auditoria.png') }}', 'Pistas de Auditoría y Trazabilidad')">
            <img src="{{ asset('img/help/auditoria.png') }}" alt="Auditoría" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-2 font-mono text-xs">
            <h3 class="font-bold text-amber-300 uppercase tracking-wider">Garantías de Auditoría:</h3>
            <ul class="list-disc list-inside text-obsidian-muted space-y-1 text-[11px]">
                <li><b>Registro Criptográfico Inmutable:</b> Cada inicio de sesión, cambio de rol, comando ejecutado o modificación en la base de datos queda archivado con IP, fecha exacta y usuario responsable.</li>
                <li><b>Modal Diff JSON:</b> Permite contrastar con un clic el estado anterior y el estado nuevo de cualquier registro modificado.</li>
            </ul>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 13: SEGURIDAD, BANEOS & FAIL2BAN                                  -->
    <!-- ========================================================================= -->
    <div id="modulo-seguridad" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-950/70 border border-red-500/40 flex items-center justify-center text-red-400">
                    <span class="material-symbols-outlined text-xl">gavel</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        13. Seguridad Perimetral, Baneos & Jaulas Fail2Ban
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Bloqueo preventivo de ataques de fuerza bruta, jaulas iptables y control de listas negras
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.bans.index') }}" class="text-xs font-mono text-red-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
                @endif
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/seguridad.png') }}', 'Baneos y Seguridad Fail2Ban')">
            <img src="{{ asset('img/help/seguridad.png') }}" alt="Seguridad" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-2 font-mono text-xs">
            <h3 class="font-bold text-red-400 uppercase tracking-wider">Mecanismos de Protección:</h3>
            <ul class="list-disc list-inside text-obsidian-muted space-y-1 text-[11px]">
                <li><b>Jaulas Activas:</b> Protegen SSH, Apache Auth y formularios web contra ataques automatizados.</li>
                <li><b>Baneo Manual:</b> Permite al administrador suspender inmediatamente una dirección IP anómala.</li>
                <li><b>Desbloqueo Seguro:</b> Botón de un solo clic para retirar una IP de la jaula en caso de falso positivo justificado.</li>
            </ul>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 14: COMANDOS & PLANTILLAS BOT TELEGRAM                            -->
    <!-- ========================================================================= -->
    <div id="modulo-bot" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-300">
                    <span class="material-symbols-outlined text-xl">terminal</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        14. Comandos de Telegram & Plantillas de Mensajes
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Interacción por mensajería móvil segura, control remoto y formato de alertas HTML
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.bot.commands.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
                @endif
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/bot.png') }}', 'Comandos y Plantillas del Bot de Telegram')">
            <img src="{{ asset('img/help/bot.png') }}" alt="Bot" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-2 font-mono text-xs">
            <h3 class="font-bold text-cyan-300 uppercase tracking-wider">Comandos de Mayor Uso:</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 text-[11px]">
                <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <code class="text-cyan-300 font-bold block">/estatus</code>
                    <span class="text-obsidian-muted text-[10px]">Reporte global ejecutivo del estado de red.</span>
                </div>
                <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <code class="text-cyan-300 font-bold block">/sedes</code>
                    <span class="text-obsidian-muted text-[10px]">Resumen de conectividad de sedes regionales.</span>
                </div>
                <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <code class="text-cyan-300 font-bold block">/topologia</code>
                    <span class="text-obsidian-muted text-[10px]">Estado de enlaces físicos y nodos descubiertos.</span>
                </div>
                <div class="p-2.5 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <code class="text-cyan-300 font-bold block">/wol [equipo]</code>
                    <span class="text-obsidian-muted text-[10px]">Envío inmediato de paquete mágico de encendido.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 15: CONFIGURACIÓN AVANZADA                                        -->
    <!-- ========================================================================= -->
    <div id="modulo-configuracion" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-600 flex items-center justify-center text-slate-300">
                    <span class="material-symbols-outlined text-xl">tune</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        15. Configuración Avanzada & Modo Clúster
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Intervalos de escaneo, token de réplica X-CLUSTER-TOKEN y umbrales operativos
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.settings.advanced') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
                @endif
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/configuracion.png') }}', 'Configuración Avanzada del Clúster')">
            <img src="{{ asset('img/help/configuracion.png') }}" alt="Configuración" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-2 font-mono text-xs">
            <h3 class="font-bold text-slate-300 uppercase tracking-wider">Parámetros del Clúster:</h3>
            <p class="text-obsidian-muted text-[11px] leading-relaxed">
                En este módulo se define el rol del servidor actual (<code class="text-amber-300">master</code> o <code class="text-blue-300">slave</code>), la URL del servidor maestro, los intervalos de cron para la sincronización asíncrona de snapshots (1 a 15 minutos) y las credenciales cifradas para la autenticación de réplicas en clúster.
            </p>
        </div>
    </div>

</div>

<!-- ========================================================================= -->
<!-- BOTÓN FLOTANTE: VOLVER AL ÍNDICE RÁPIDO                                   -->
<!-- ========================================================================= -->
<button id="btn-scroll-to-index" 
        type="button" 
        onclick="scrollToIndex(event)" 
        class="fixed bottom-6 right-6 z-40 px-3.5 py-2.5 rounded-xl bg-[#07172b]/95 hover:bg-cyan-950/90 border border-cyan-500/50 text-cyan-300 hover:text-white shadow-[0_8px_30px_rgba(0,0,0,0.7)] backdrop-blur-md flex items-center gap-2 font-mono text-xs font-bold transition-all duration-300 hover:scale-105 group cursor-pointer opacity-0 pointer-events-none translate-y-4 ring-1 ring-cyan-500/20" 
        title="Volver al índice de módulos">
    <span class="material-symbols-outlined text-base group-hover:-translate-y-0.5 transition-transform text-cyan-400">arrow_upward</span>
    <span class="hidden sm:inline">Volver al Índice</span>
</button>

<style>
    html {
        scroll-behavior: smooth;
    }
</style>

<!-- ========================================================================= -->
<!-- MODAL LIGHTBOX: VISOR DE CAPTURAS DE PANTALLA EN ALTA DEFINICIÓN         -->
<!-- ========================================================================= -->
<div id="manual-lightbox-modal" class="fixed inset-0 z-[99999] hidden flex items-center justify-center p-3 sm:p-6 bg-black/90 backdrop-blur-md transition-all duration-300" onclick="closeLightbox()">
    <div class="max-w-6xl w-full max-h-[95vh] flex flex-col items-center justify-center relative animate-in fade-in zoom-in-95 duration-200" onclick="event.stopPropagation()">
        <div class="w-full flex items-center justify-between pb-2 text-xs font-mono text-white">
            <span id="lightbox-caption" class="text-cyan-300 font-bold truncate max-w-[80vw]">Captura de Pantalla</span>
            <button type="button" onclick="closeLightbox()" class="text-obsidian-muted hover:text-white text-2xl leading-none p-1 transition cursor-pointer" title="Cerrar">&times;</button>
        </div>
        <div class="rounded-xl overflow-hidden border border-cyan-500/40 shadow-2xl bg-black/60 max-h-[85vh] flex items-center justify-center">
            <img id="lightbox-image" src="" alt="Captura Ampliada" class="max-w-full max-h-[82vh] object-contain">
        </div>
        <span class="text-[10px] font-mono text-obsidian-muted mt-2">Presiona ESC o haz clic afuera para cerrar</span>
    </div>
</div>

<script>
    // Desplazamiento suave al índice de módulos (respetando topbar fija de 70px)
    function scrollToIndex(event) {
        if (event) {
            event.preventDefault();
        }
        const target = document.getElementById('panel-indice-modulos') || document.getElementById('quick-nav-container');
        if (target) {
            const headerOffset = 70;
            const elementPosition = target.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
            window.scrollTo({
                top: offsetPosition > 0 ? offsetPosition : 0,
                behavior: 'smooth'
            });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    // Control de visibilidad del botón flotante 'Volver al Índice'
    window.addEventListener('scroll', function() {
        const btn = document.getElementById('btn-scroll-to-index');
        if (btn) {
            if (window.scrollY > 250) {
                btn.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-4');
                btn.classList.add('opacity-100', 'pointer-events-auto', 'translate-y-0');
            } else {
                btn.classList.add('opacity-0', 'pointer-events-none', 'translate-y-4');
                btn.classList.remove('opacity-100', 'pointer-events-auto', 'translate-y-0');
            }
        }
    });

    // Buscador interactivo de módulos en el manual
    function filterManualModules() {
        const query = document.getElementById('manual-search-input').value.toLowerCase().trim();
        const sections = document.querySelectorAll('.manual-section');
        
        sections.forEach(function(section) {
            const text = section.innerText.toLowerCase();
            if (query === '' || text.includes(query)) {
                section.style.display = '';
            } else {
                section.style.display = 'none';
            }
        });
    }

    // Modal Lightbox para visualización de capturas en alta definición
    function openLightbox(imageUrl, caption) {
        const modal = document.getElementById('manual-lightbox-modal');
        const img = document.getElementById('lightbox-image');
        const cap = document.getElementById('lightbox-caption');
        
        if (modal && img) {
            img.src = imageUrl;
            if (cap) cap.innerText = caption || 'Captura de Pantalla del Sistema';
            modal.classList.remove('hidden');
        }
    }

    function closeLightbox() {
        const modal = document.getElementById('manual-lightbox-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLightbox();
        }
    });
</script>
@endsection
