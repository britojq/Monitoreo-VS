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
                    <span class="w-6 h-6 rounded-md bg-cyan-950/80 text-cyan-300 border border-cyan-500/40 inline-flex items-center justify-center cursor-help" title="Versión del Sistema: {{ $systemVersion }}">
                        <span class="material-symbols-outlined text-xs">verified</span>
                    </span>
                    <span class="w-6 h-6 rounded-md {{ $clusterRole === 'master' ? 'bg-amber-950/80 text-amber-300 border border-amber-500/40' : 'bg-blue-950/80 text-blue-300 border border-blue-500/40' }} inline-flex items-center justify-center cursor-help" title="Modo de Operación: {{ strtoupper($clusterRole) }}">
                        <span class="material-symbols-outlined text-xs">dns</span>
                    </span>
                </div>
                <p class="text-xs font-mono text-obsidian-muted mt-0.5">
                    Referencia completa y detallada de cada pantalla, botones, métricas, flujos y operaciones del centro de monitoreo e inventario.
                </p>
            </div>
        </div>

        <!-- CONTROLES SUPERIORES (BÚSQUEDA Y BOTÓN ACERCA DE) -->
        <div class="flex items-center gap-3 self-stretch lg:self-auto">
            <div class="relative flex-1 lg:w-72">
                <span class="material-symbols-outlined absolute left-3 top-2.5 text-obsidian-muted text-base pointer-events-none">search</span>
                <input type="text" id="manual-search-input" oninput="filterManualModules()" onkeyup="filterManualModules()" placeholder="Buscar módulo, función o métrica..." class="w-full bg-[#040d1a] border border-obsidian-border rounded-xl pl-9 pr-3 py-2 text-xs font-mono text-white placeholder-obsidian-muted focus:outline-none focus:border-obsidian-cyan transition">
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
            <span class="text-obsidian-muted text-[11px]">22 secciones especializadas</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 text-xs font-mono" id="quick-nav-container">
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
                <span class="truncate">3. Topología de Red</span>
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
            <a href="#modulo-itam-assets" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-cyan-300">inventory_2</span>
                <span class="truncate">7. Puestos de Trabajo</span>
            </a>
            <a href="#modulo-perifericos" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-indigo-400">keyboard</span>
                <span class="truncate">8. Periféricos</span>
            </a>
            <a href="#modulo-actas" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-teal-400">assignment</span>
                <span class="truncate">9. Actas & Pases</span>
            </a>
            <a href="#modulo-mantenimiento" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-orange-400">build</span>
                <span class="truncate">10. Mantenimiento</span>
            </a>
            <a href="#modulo-glpi" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-sky-400">install_desktop</span>
                <span class="truncate">11. Despliegue Agente</span>
            </a>
            <a href="#modulo-reportes" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-emerald-400">table_chart</span>
                <span class="truncate">12. Reportes</span>
            </a>
            <a href="#modulo-discovery" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-rose-400">radar</span>
                <span class="truncate">13. Radar & Discovery</span>
            </a>
            <a href="#modulo-snmp" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-cyan-400">sensors</span>
                <span class="truncate">14. Telemetría SNMP</span>
            </a>
            <a href="#modulo-netradar" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-yellow-400">insights</span>
                <span class="truncate">15. NET Radar</span>
            </a>
            <a href="#modulo-ups" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-amber-300">battery_charging_full</span>
                <span class="truncate">16. Supervisión UPS</span>
            </a>
            <a href="#modulo-ncm" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-indigo-400">settings_backup_restore</span>
                <span class="truncate">17. Respaldos NCM</span>
            </a>
            <a href="#modulo-alertas" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-pink-400">notifications_active</span>
                <span class="truncate">18. Alertas & Reglas</span>
            </a>
            <a href="#modulo-usuarios" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-purple-400">group</span>
                <span class="truncate">19. Usuarios & RBAC</span>
            </a>
            <a href="#modulo-auditoria" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-amber-400">policy</span>
                <span class="truncate">20. Pistas Auditoría</span>
            </a>
            <a href="#modulo-seguridad" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-red-500">gavel</span>
                <span class="truncate">21. Baneos & Jaulas</span>
            </a>
            <a href="#modulo-asistente" class="p-2 rounded-lg bg-obsidian-card/60 hover:bg-obsidian-border/60 border border-obsidian-border/60 text-slate-300 hover:text-cyan-300 transition flex items-center gap-2 truncate">
                <span class="material-symbols-outlined text-sm text-cyan-300">psychology</span>
                <span class="truncate">22. Asistente & Bot</span>
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
                        Diferencias operativas entre el Servidor Principal y el Nodo Réplica de Respaldo
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-mono">
            <!-- SERVIDOR MASTER -->
            <div class="p-4 rounded-xl bg-amber-950/20 border border-amber-500/40 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-amber-300 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base">crown</span>
                        Servidor Master (Producción)
                    </span>
                    <span class="w-6 h-6 rounded bg-amber-950 text-amber-400 border border-amber-500/30 flex items-center justify-center cursor-help" title="Nodo con autoridad para escaneo activo y mutación">
                        <span class="material-symbols-outlined text-xs">tune</span>
                    </span>
                </div>
                <p class="text-slate-300 leading-relaxed">
                    Es la autoridad central de monitoreo. Es el único nodo autorizado para consultar directamente hacia el cortafuegos perimetral y ejecutar escaneos de infraestructura hacia la red WAN.
                </p>
                <ul class="list-disc list-inside text-obsidian-muted space-y-1">
                    <li>Edición, alta y mutación permitidas en todos los módulos operativos.</li>
                    <li>Genera las instantáneas cifradas de replicación para los nodos réplica.</li>
                    <li>Despacha las notificaciones programadas a los canales privados y de supervisión.</li>
                    <li>Ejecuta demonios de sondeo activo SNMP, ICMP y comprobaciones de puertos.</li>
                </ul>
            </div>

            <!-- SERVIDOR SLAVE -->
            <div class="p-4 rounded-xl bg-blue-950/20 border border-blue-500/40 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-blue-300 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base">sync</span>
                        Servidor Slave (Desarrollo / Respaldo)
                    </span>
                    <span class="w-6 h-6 rounded bg-blue-950 text-blue-400 border border-blue-500/30 flex items-center justify-center cursor-help" title="Nodo réplica de consulta y resguardo continuo">
                        <span class="material-symbols-outlined text-xs">visibility</span>
                    </span>
                </div>
                <p class="text-slate-300 leading-relaxed">
                    Actúa como réplica de respaldo, contingencia y consulta. No satura el cortafuegos perimetral ni genera concurrencia sobre los enlaces de producción.
                </p>
                <ul class="list-disc list-inside text-obsidian-muted space-y-1">
                    <li>Sincronización continua de telemetría e históricos en tiempo real mediante el motor de replicación web.</li>
                    <li>Pobla integralmente las cuatro tablas de telemetría histórica en MariaDB para conservar métricas completas.</li>
                    <li>Los botones de edición y guardado se protegen visualmente con indicadores de modo consulta.</li>
                    <li>Permite navegación completa, consulta de métricas e interacción con el bot.</li>
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

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/dashboard.png') }}', 'Dashboard Principal de Monitoreo')">
            <img src="{{ asset('img/help/dashboard.png') }}" alt="Dashboard" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-cyan-300 uppercase tracking-wider">Componentes Principales del Dashboard:</h3>
            <ul class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> Estatus Global Operativo:</b>
                    <p class="text-obsidian-muted text-[11px]">Indica si la infraestructura está <span class="text-emerald-400 font-bold">OPERACIONAL</span>, <span class="text-amber-400 font-bold">DEGRADADA</span> o en estado <span class="text-red-400 font-bold">CRÍTICO</span> según el porcentaje de disponibilidad.</p>
                </li>
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-cyan-400">bolt</span> Escaneo Bajo Demanda:</b>
                    <p class="text-obsidian-muted text-[11px]">En el menú lateral se dispone del activador para disparar una comprobación síncrona inmediata de toda la infraestructura conectada.</p>
                </li>
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-purple-400">domain</span> Tarjetas de Sedes y Enlaces:</b>
                    <p class="text-obsidian-muted text-[11px]">Despliegan la conectividad de la sede principal y enlaces remotos con tiempo de ida y vuelta (RTT) promedio y tasa de pérdida de paquetes.</p>
                </li>
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-amber-400">alt_route</span> Supervisión de Proxies de Navegación:</b>
                    <p class="text-obsidian-muted text-[11px]">Verifica los puertos de salida corporativos, disponibilidad de cada pasarela y latencia de respuesta en milisegundos.</p>
                </li>
            </ul>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 3: TOPOLOGÍA DE RED & DESCUBRIMIENTO AUTOMÁTICO                   -->
    <!-- ========================================================================= -->
    <div id="modulo-topologia" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-950/70 border border-purple-500/40 flex items-center justify-center text-purple-300">
                    <span class="material-symbols-outlined text-xl">hub</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        3. Topología de Red & Grafo Dinámico
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Descubrimiento automático de dispositivos, jerarquía física y lógica en tiempo real
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

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/topologia.png') }}', 'Grafo Interactivo de Topología de Red')">
            <img src="{{ asset('img/help/topologia.png') }}" alt="Topología" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-purple-300 uppercase tracking-wider">Operación y Herramientas del Grafo:</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <span class="font-bold text-white flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-cyan-400 text-sm">travel_explore</span>
                        Re-escanear Dispositivos:
                    </span>
                    <p class="text-obsidian-muted text-[11px]">
                        Consulta tablas de protocolos LLDP, CDP y tablas FDB para detectar interconexiones entre conmutadores, enrutadores y servidores.
                    </p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <span class="font-bold text-white flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-purple-400 text-sm">filter_alt</span>
                        Filtro por Sede Física:
                    </span>
                    <p class="text-obsidian-muted text-[11px]">
                        Permite aislar la visualización a una sede en particular o inspeccionar la troncal WAN completa entre todas las sedes.
                    </p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <span class="font-bold text-white flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-emerald-400 text-sm">drag_pan</span>
                        Navegación Interactiva:
                    </span>
                    <p class="text-obsidian-muted text-[11px]">
                        Zoom con rueda del ratón, arrastre libre de nodos, centrado automático y panel lateral con detalles técnicos al hacer clic en un dispositivo.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 4: SERVICIOS CORPORATIVOS, HTTP/TCP & SSL/TLS                     -->
    <!-- ========================================================================= -->
    <div id="modulo-servicios" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-950/70 border border-blue-500/40 flex items-center justify-center text-blue-300">
                    <span class="material-symbols-outlined text-xl">dns</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        4. Servicios Corporativos, Plataformas Web & Certificados SSL/TLS
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Monitoreo continuo de puertos de aplicación, tiempo de respuesta y caducidad de certificados
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

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/servicios.png') }}', 'Supervisión de Servicios y Certificados SSL')">
            <img src="{{ asset('img/help/servicios.png') }}" alt="Servicios" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-blue-300 uppercase tracking-wider">Funcionalidades de Supervisión:</h3>
            <ul class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-blue-400 text-sm">speed</span> Disponibilidad & Gráficas de Latencia:</b>
                    <p class="text-obsidian-muted text-[11px]">Monitorea protocolos HTTP, HTTPS, TCP y DNS. Al hacer clic en un servicio se despliega el historial cronológico con curvas de latencia en milisegundos y eventos de caída.</p>
                </li>
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-emerald-400 text-sm">verified_user</span> Alertas de Certificados SSL/TLS:</b>
                    <p class="text-obsidian-muted text-[11px]">Inspecciona fecha de vencimiento, entidad emisora y algoritmo criptográfico de cada sitio HTTPS, emitiendo avisos preventivos ante expiraciones próximas.</p>
                </li>
            </ul>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 5: SEDES & ENLACES WAN REGIONALES                                 -->
    <!-- ========================================================================= -->
    <div id="modulo-sedes" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-950/70 border border-emerald-500/40 flex items-center justify-center text-emerald-300">
                    <span class="material-symbols-outlined text-xl">domain</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        5. Sedes & Enlaces WAN Regionales
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Telemetría de conectividad entre la sede principal y ubicaciones remotas
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

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/sedes.png') }}', 'Supervisión de Sedes y Enlaces WAN')">
            <img src="{{ asset('img/help/sedes.png') }}" alt="Sedes" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-emerald-300 uppercase tracking-wider">Métricas de Conectividad WAN:</h3>
            <p class="text-obsidian-muted text-[11px] leading-relaxed">
                Cada sede refleja su estado de enlace mediante sondas de eco ICMP continuas. Se calculan variables críticas de calidad de enlace:
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <span class="font-bold text-white block">RTT (Tiempo de Retorno):</span>
                    <span class="text-obsidian-muted text-[11px]">Mide la latencia en milisegundos desde el centro de monitoreo hasta la pasarela de la sede.</span>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <span class="font-bold text-white block">Pérdida de Paquetes:</span>
                    <span class="text-obsidian-muted text-[11px]">Porcentaje de paquetes descartados en la ráfaga de sondeo (0% a 100%).</span>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <span class="font-bold text-white block">Historial de Disponibilidad:</span>
                    <span class="text-obsidian-muted text-[11px]">Cronograma de uptime mensual acumulado para evaluar la estabilidad del proveedor de enlace.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 6: DISPOSITIVOS DE RED, ACCESO REMOTO & WoL                      -->
    <!-- ========================================================================= -->
    <div id="modulo-equipos" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-950/70 border border-amber-500/40 flex items-center justify-center text-amber-300">
                    <span class="material-symbols-outlined text-xl">router</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        6. Dispositivos de Red, Consola Remota Web & Wake-on-LAN (WoL)
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Gestión de enrutadores, conmutadores, servidores con acceso directo vía SSH/Telnet/VNC
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
            <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/equipos.png') }}', 'Dispositivos de Red y Consola Remota')">
                <img src="{{ asset('img/help/equipos.png') }}" alt="Equipos" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                    <span class="material-symbols-outlined">zoom_in</span>
                    <span>Clic para ampliar</span>
                </div>
            </div>
            <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/wol.png') }}', 'Módulo Wake-on-LAN de Encendido Remoto')">
                <img src="{{ asset('img/help/wol.png') }}" alt="WoL" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                    <span class="material-symbols-outlined">zoom_in</span>
                    <span>Clic para ampliar</span>
                </div>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-amber-300 uppercase tracking-wider">Capacidades de Gestión y Control Remoto:</h3>
            <ul class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-cyan-400 text-sm">terminal</span> Terminal SSH / Telnet en Web:</b>
                    <p class="text-obsidian-muted text-[11px]">Permite abrir sesiones de consola seguras emuladas en el navegador hacia equipos de red sin instalar clientes de escritorio externos.</p>
                </li>
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-purple-400 text-sm">desktop_windows</span> Visualizador Remoto VNC:</b>
                    <p class="text-obsidian-muted text-[11px]">Acceso a interfaz gráfica remota con túnel cifrado integrado para asistencia y administración de puestos de trabajo.</p>
                </li>
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-amber-400 text-sm">power_settings_new</span> Encendido Remoto WoL:</b>
                    <p class="text-obsidian-muted text-[11px]">Envío de paquetes mágicos UDP a la dirección física MAC para encender puestos apagados fuera del horario laboral.</p>
                </li>
            </ul>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 7: ITAM UNIFICADO - PUESTOS DE TRABAJO & FICHAS TÉCNICAS          -->
    <!-- ========================================================================= -->
    <div id="modulo-itam-assets" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-300">
                    <span class="material-symbols-outlined text-xl">inventory_2</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        7. ITAM Unificado: Puestos de Trabajo & Fichas Técnicas
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Censo maestro de activos físicos con ficha técnica desplegable y vinculación de periféricos
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.inventory.assets.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-cyan-300 uppercase tracking-wider">Estructura del Puesto de Trabajo:</h3>
            <p class="text-slate-300 leading-relaxed">
                Cada equipo en el inventario cuenta con una <b>Ficha Técnica Desplegable</b> que reúne en un solo punto toda la información del hardware, ubicación, red y estado operativo:
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-cyan-400 text-sm">badge</span> Nombre Etiqueta (Identidad Principal):</b>
                    <p class="text-obsidian-muted text-[11px]">Define el rol o puesto funcional dentro de la sede (ej. JEFE DE OFICINA, TAQUILLA 01). Es el dato principal visible en todas las vistas y reportes.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-purple-400 text-sm">memory</span> Especificaciones de Hardware:</b>
                    <p class="text-obsidian-muted text-[11px]">Procesador (CPU), memoria RAM instalada, almacenamiento principal, placa base, fabricante y número de serie del chasis.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-emerald-400 text-sm">person</span> Asignación & Responsable Actual:</b>
                    <p class="text-obsidian-muted text-[11px]">Identifica al funcionario receptor que tiene asignado el equipo, junto a su departamento y número de acta activa.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-amber-400 text-sm">history</span> Historial de Movimientos & Mantenimientos:</b>
                    <p class="text-obsidian-muted text-[11px]">Línea de tiempo cronológica con todas las asignaciones, traslados, asistencias técnicas y cambios de hardware realizados.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 8: PERIFÉRICOS ASOCIADOS & STOCK DE HARDWARE                      -->
    <!-- ========================================================================= -->
    <div id="modulo-perifericos" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-950/70 border border-indigo-500/40 flex items-center justify-center text-indigo-300">
                    <span class="material-symbols-outlined text-xl">keyboard</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        8. Periféricos Asociados & Stock de Hardware Complementario
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Control individual de monitores, teclados, mouse, reguladores y dispositivos en depósito
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.inventory.peripherals.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <p class="text-slate-300 leading-relaxed">
                Permite la administración granular de componentes complementarios vinculados a un puesto de trabajo o almacenados como stock de reposición:
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">Vinculación Flexible:</b>
                    <p class="text-obsidian-muted text-[11px]">Un periférico puede asociarse a un equipo específico o desvincularse hacia el inventario en depósito con un solo clic.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">Trazabilidad por Serial:</b>
                    <p class="text-obsidian-muted text-[11px]">Registro de marca, modelo, serial único, tipo de conector y condición física (Operativo, Averiado, En Reparación).</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">Inclusión en Actas:</b>
                    <p class="text-obsidian-muted text-[11px]">Todos los periféricos vinculados a un puesto se incorporan de manera automática en las actas de entrega y recepción.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 9: ACTAS DE ASIGNACIÓN (UINF-4.05) & PASES DE MOVILIZACIÓN        -->
    <!-- ========================================================================= -->
    <div id="modulo-actas" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-teal-950/70 border border-teal-500/40 flex items-center justify-center text-teal-300">
                    <span class="material-symbols-outlined text-xl">assignment</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        9. Mesa de Asignaciones, Actas UINF-4.05 & Pases de Movilización
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Generación de actas en PDF, verificación pública por QR y soporte físico digitalizado
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.inventory.assignments.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-teal-300 uppercase tracking-wider">Flujo de Asignación y Traslado:</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-teal-400 text-sm">picture_as_pdf</span> Acta de Entrega y Recepción (UINF-4.05):</b>
                    <p class="text-obsidian-muted text-[11px]">Genera el documento en PDF con diseño estándar de una hoja que incluye datos del funcionario receptor, departamento, equipo principal, periféricos y firmas formales.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-cyan-400 text-sm">qr_code_2</span> Código QR de Verificación Pública:</b>
                    <p class="text-obsidian-muted text-[11px]">Cada acta incluye un código QR único que dirige al portal público para comprobar la autenticidad e integridad del documento sin necesidad de iniciar sesión.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-indigo-400 text-sm">local_shipping</span> Pase de Movilización de Equipos:</b>
                    <p class="text-obsidian-muted text-[11px]">Formato especializado para traslados temporales o externos entre sedes, detallando motivo, responsable del traslado y fecha estimada de retorno.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-emerald-400 text-sm">upload_file</span> Soporte Físico Digitalizado:</b>
                    <p class="text-obsidian-muted text-[11px]">Permite adjuntar el archivo escaneado del acta firmada en formato PDF o imagen, resguardándolo permanentemente en el servidor.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 10: MANTENIMIENTO TÉCNICO & ÓRDENES DE SERVICIO                  -->
    <!-- ========================================================================= -->
    <div id="modulo-mantenimiento" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-orange-950/70 border border-orange-500/40 flex items-center justify-center text-orange-300">
                    <span class="material-symbols-outlined text-xl">build</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        10. Mantenimiento Técnico & Órdenes de Servicio
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Apertura y seguimiento de asistencias técnicas, reparaciones y soporte de garantías
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.inventory.maintenance.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <p class="text-slate-300 leading-relaxed">
                Permite registrar intervenciones preventivas o correctivas sobre los equipos del inventario:
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">Ciclo de Vida del Ticket:</b>
                    <p class="text-obsidian-muted text-[11px]">Transición estructurada de estados: Abierto, En Diagnóstico, Espera de Repuestos y Resuelto.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">Reporte de Servicio PDF:</b>
                    <p class="text-obsidian-muted text-[11px]">Exportación de la orden de trabajo con observaciones técnicas, piezas reemplazadas y firma de conformidad.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">Control de Costos & Garantías:</b>
                    <p class="text-obsidian-muted text-[11px]">Seguimiento a vigencia de garantías de proveedores y costos asociados a repuestos y mano de obra.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 11: DESPLIEGUE REMOTO DE AGENTES & AUTO-DISCOVERY GLPI            -->
    <!-- ========================================================================= -->
    <div id="modulo-glpi" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-sky-950/70 border border-sky-500/40 flex items-center justify-center text-sky-300">
                    <span class="material-symbols-outlined text-xl">install_desktop</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        11. Despliegue Remoto de Agentes & Auto-Discovery GLPI
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Instalación automatizada de agentes en puestos Windows/Linux y detección de desviaciones de hardware
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.inventory.deploy') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-sky-300 uppercase tracking-wider">Mecanismos de Automatización:</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-sky-400 text-sm">code</span> Scripts de Despliegue Rápido:</b>
                    <p class="text-obsidian-muted text-[11px]">Descarga de instaladores de una sola línea en PowerShell para estaciones Windows y Bash para distribuciones GNU/Linux con configuración del servidor predefinida.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-amber-400 text-sm">compare_arrows</span> Desviación de Hardware (Hardware Drift):</b>
                    <p class="text-obsidian-muted text-[11px]">Compara el inventario físico registrado contra el censo reportado por el agente para alertar cambios en memoria RAM, discos o procesador sin autorización.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 12: CENTRO UNIFICADO DE REPORTES & EXPORTACIÓN                     -->
    <!-- ========================================================================= -->
    <div id="modulo-reportes" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-950/70 border border-emerald-500/40 flex items-center justify-center text-emerald-300">
                    <span class="material-symbols-outlined text-xl">table_chart</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        12. Centro Unificado de Reportes & Exportación Consolidada
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Exportación segmentada del inventario unificado, disponibilidad de servicios y métricas
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.reports.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-emerald-300 uppercase tracking-wider">Formatos de Salida Disponibles:</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">Inventario Unificado:</b>
                    <p class="text-obsidian-muted text-[11px]">Exportación consolidada en formato de libro de cálculo estructurado con doble hoja técnica para análisis de auditoría.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">Exportación Segmentada:</b>
                    <p class="text-obsidian-muted text-[11px]">Filtrado por sede, tipo de equipo, estado operativo o departamento en formatos CSV y PDF.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">Asignaciones & Movilizaciones:</b>
                    <p class="text-obsidian-muted text-[11px]">Listado cronológico de actas generadas, traslados activos y responsables asignados.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 13: RADAR DE DESCUBRIMIENTO & RECONOCIMIENTO OUI                  -->
    <!-- ========================================================================= -->
    <div id="modulo-discovery" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-950/70 border border-rose-500/40 flex items-center justify-center text-rose-300">
                    <span class="material-symbols-outlined text-xl">radar</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        13. Radar de Descubrimiento de Red & Reconocimiento OUI
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Escaneo continuo de subredes, detección de fabricantes por dirección física y vinculación al inventario
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

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/discovery.png') }}', 'Radar de Descubrimiento de Red')">
            <img src="{{ asset('img/help/discovery.png') }}" alt="Discovery" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-rose-300 uppercase tracking-wider">Detección y Clasificación de Equipos:</h3>
            <ul class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-rose-400 text-sm">search_check</span> Barrido de Segmentos LAN:</b>
                    <p class="text-obsidian-muted text-[11px]">Sondea las subredes de la organización mediante solicitudes ARP/ICMP para identificar nuevas direcciones activas en la red.</p>
                </li>
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-cyan-400 text-sm">link</span> Cotejo Automático con MariaDB:</b>
                    <p class="text-obsidian-muted text-[11px]">Todo equipo detectado se coteja contra el inventario existente; si ya pertenece al censo, se vincula de forma transparente mostrando su Nombre Etiqueta.</p>
                </li>
            </ul>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 14: TELEMETRÍA SNMP & MONITOREO DE INTERFACES                     -->
    <!-- ========================================================================= -->
    <div id="modulo-snmp" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-300">
                    <span class="material-symbols-outlined text-xl">sensors</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        14. Telemetría SNMP & Monitoreo de Interfaces de Red
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Tráfico de interfaces, descarte de paquetes, errores de CRC, uso de CPU y memoria en enrutadores y conmutadores
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

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/snmp.png') }}', 'Módulo de Telemetría SNMP e Interfaces')">
            <img src="{{ asset('img/help/snmp.png') }}" alt="SNMP" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-cyan-300 uppercase tracking-wider">Telemetría de Red Recolectada:</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">Métricas de Tráfico (In/Out):</b>
                    <p class="text-obsidian-muted text-[11px]">Cálculo en tiempo real de ancho de banda consumido en megabits por segundo (Mbps) mediante contadores de 64 bits (ifHCInOctets).</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">Salud de Hardware:</b>
                    <p class="text-obsidian-muted text-[11px]">Monitoreo de porcentaje de CPU, consumo de memoria RAM y temperatura interna en dispositivos Cisco y genéricos.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white block">Receptor de SNMP Traps:</b>
                    <p class="text-obsidian-muted text-[11px]">Captura inmediata de eventos espontáneos como cambios de enlace (linkDown/linkUp) o fallos de alimentación.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 15: NET RADAR & ANÁLISIS PASIVO DE TRÁFICO                        -->
    <!-- ========================================================================= -->
    <div id="modulo-netradar" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-yellow-950/70 border border-yellow-500/40 flex items-center justify-center text-yellow-300">
                    <span class="material-symbols-outlined text-xl">insights</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        15. NET Radar & Análisis Pasivo de Tráfico (Top Talkers)
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Inspección de flujos de red, consumo de ancho de banda por host y detección de descargas concurrentes
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.netradar.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-yellow-300 uppercase tracking-wider">Funcionalidades de NET Radar:</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-yellow-400 text-sm">leaderboard</span> Top Talkers:</b>
                    <p class="text-obsidian-muted text-[11px]">Identifica los equipos y direcciones que generan el mayor volumen de tráfico entrante o saliente sobre el canal de comunicaciones.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-cyan-400 text-sm">security_update_good</span> Detección de Actualizaciones:</b>
                    <p class="text-obsidian-muted text-[11px]">Detecta de forma pasiva descargas masivas de paquetes de actualización del sistema operativo para evitar saturación de la pasarela de salida.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 16: SUPERVISIÓN ELÉCTRICA & TELEMETRÍA UPS                        -->
    <!-- ========================================================================= -->
    <div id="modulo-ups" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-950/70 border border-amber-500/40 flex items-center justify-center text-amber-300">
                    <span class="material-symbols-outlined text-xl">battery_charging_full</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        16. Supervisión Eléctrica & Telemetría de Unidades UPS
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Monitoreo continuo de respaldo eléctrico, estado de línea/batería, porcentaje de carga y autonomía
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                <a href="{{ route('admin.ups.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-amber-300 uppercase tracking-wider">Indicadores Eléctricos Críticos:</h3>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <span class="font-bold text-white block">Modo Operativo:</span>
                    <span class="text-obsidian-muted text-[11px]">Línea comercial activa (Online) o Modo Batería (On Battery) con alerta inmediata ante cortes.</span>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <span class="font-bold text-white block">Carga (%):</span>
                    <span class="text-obsidian-muted text-[11px]">Porcentaje de capacidad nominal consumida por los servidores y equipos conectados.</span>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <span class="font-bold text-white block">Nivel de Batería:</span>
                    <span class="text-obsidian-muted text-[11px]">Carga almacenada en los bancos de baterías para garantizar respaldo continuo.</span>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60">
                    <span class="font-bold text-white block">Autonomía Restante:</b>
                    <span class="text-obsidian-muted text-[11px]">Minutos estimados de funcionamiento restante bajo el régimen de consumo actual.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 17: RESPALDOS NCM DE CONFIGURACIONES                              -->
    <!-- ========================================================================= -->
    <div id="modulo-ncm" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-950/70 border border-indigo-500/40 flex items-center justify-center text-indigo-300">
                    <span class="material-symbols-outlined text-xl">settings_backup_restore</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        17. Respaldos NCM Automatizados de Configuraciones (Cisco / Enrutadores)
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Respaldo automático de configuraciones running-config y startup-config con comparador de cambios (Diff)
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

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/ncm.png') }}', 'Módulo NCM de Respaldos y Auditoría de Cambios')">
            <img src="{{ asset('img/help/ncm.png') }}" alt="NCM" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-indigo-300 uppercase tracking-wider">Gestión de Configuraciones:</h3>
            <ul class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-indigo-400 text-sm">history_edu</span> Versionado Histórico:</b>
                    <p class="text-obsidian-muted text-[11px]">Guarda cada versión del archivo de configuración con marca de tiempo, permitiendo recuperar versiones anteriores ante contingencias.</p>
                </li>
                <li class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-emerald-400 text-sm">difference</span> Comparador Visual (Diff):</b>
                    <p class="text-obsidian-muted text-[11px]">Resalta en verde las líneas agregadas y en rojo las líneas modificadas o removidas entre dos respaldos consecutivos.</p>
                </li>
            </ul>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 18: ALERTAS, ESCALACIÓN & REGLAS                                  -->
    <!-- ========================================================================= -->
    <div id="modulo-alertas" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-pink-950/70 border border-pink-500/40 flex items-center justify-center text-pink-300">
                    <span class="material-symbols-outlined text-xl">notifications_active</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        18. Sistema de Alertas, Escalación & Supresión de Tormentas
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Correlación de incidentes, niveles de severidad y despacho a canales de supervisión autorizados
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

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/alertas.png') }}', 'Módulo de Alertas y Notificaciones')">
            <img src="{{ asset('img/help/alertas.png') }}" alt="Alertas" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-pink-300 uppercase tracking-wider">Mecanismos de Protección:</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-pink-400 text-sm">thunderstorm</span> Supresión de Tormentas:</b>
                    <p class="text-obsidian-muted text-[11px]">Si un enlace troncal cae, el sistema suprime alertas derivadas de dispositivos aguas abajo para evitar saturación de avisos repetitivos.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-cyan-400 text-sm">check_circle</span> Reconocimiento (ACK) & Silenciamiento:</b>
                    <p class="text-obsidian-muted text-[11px]">Los operadores pueden reconocer un evento en atención o silenciar temporalmente un dispositivo en mantenimiento programado.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 19: USUARIOS, PERFILES & MATRIZ RBAC                              -->
    <!-- ========================================================================= -->
    <div id="modulo-usuarios" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-950/70 border border-purple-500/40 flex items-center justify-center text-purple-300">
                    <span class="material-symbols-outlined text-xl">group</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        19. Usuarios, Perfiles de Operador & Matriz RBAC Granular
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Administración de cuentas locales, sincronización con directorio LDAP y permisos por módulo
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

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/usuarios.png') }}', 'Gestión de Usuarios y Permisos RBAC')">
            <img src="{{ asset('img/help/usuarios.png') }}" alt="Usuarios" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-purple-300 uppercase tracking-wider">Roles y Permisología:</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-amber-400 text-sm">admin_panel_settings</span> Administrador:</b>
                    <p class="text-obsidian-muted text-[11px]">Acceso total para configurar parámetros de clúster, editar dispositivos, gestionar cuentas de usuario y despachar reportes.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-blue-400 text-sm">person</span> Operador de Monitoreo:</b>
                    <p class="text-obsidian-muted text-[11px]">Acceso de consulta con permisos granulares configurables para ver inventario, reconocer alertas o ejecutar comandos permitidos.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 20: PISTAS DE AUDITORÍA FORENSE                                    -->
    <!-- ========================================================================= -->
    <div id="modulo-auditoria" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-950/70 border border-amber-500/40 flex items-center justify-center text-amber-300">
                    <span class="material-symbols-outlined text-xl">policy</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        20. Pistas de Auditoría Forense & Resguardo Inmutable
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Registro cronológico de todas las mutaciones, inicios de sesión, cambios en inventario y acciones de red
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

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/auditoria.png') }}', 'Pistas de Auditoría Forense')">
            <img src="{{ asset('img/help/auditoria.png') }}" alt="Auditoría" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-amber-300 uppercase tracking-wider">Garantías de Integridad:</h3>
            <p class="text-slate-300 leading-relaxed">
                Toda acción relevante en el portal genera un registro inmutable almacenado en MariaDB con identificación del usuario operador, dirección de conexión, acción ejecutada y valores antes y después de la modificación.
            </p>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 21: BANEOS, JAULAS DE SEGURIDAD & PROTECCIÓN PERIMETRAL           -->
    <!-- ========================================================================= -->
    <div id="modulo-seguridad" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-950/70 border border-red-500/40 flex items-center justify-center text-red-300">
                    <span class="material-symbols-outlined text-xl">gavel</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        21. Baneos, Jaulas de Seguridad & Protección Perimetral
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Bloqueo preventivo de accesos indebidos, defensa contra ataques de fuerza bruta y pantalla de advertencia 403
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.bans.index') }}" class="text-xs font-mono text-cyan-400 hover:underline flex items-center gap-1">
                    <span>Ir al Módulo</span>
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
                @endif
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/seguridad.png') }}', 'Seguridad Perimetral y Gestión de Baneos')">
            <img src="{{ asset('img/help/seguridad.png') }}" alt="Seguridad" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-red-400 uppercase tracking-wider">Reglas de Confinamiento:</h3>
            <p class="text-slate-300 leading-relaxed">
                Intentos reiterados de inicio de sesión fallidos o intentos de forzar rutas administrativas provocan el confinamiento de la dirección de origen, mostrando la página de restricción de acceso con aviso al administrador del sistema.
            </p>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN 22: ASISTENTE VIRTUAL CORPORATIVO & BOT DE TELEGRAM               -->
    <!-- ========================================================================= -->
    <div id="modulo-asistente" class="manual-section scroll-mt-20 p-6 rounded-2xl bg-obsidian-panel/80 border border-obsidian-border space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-300">
                    <span class="material-symbols-outlined text-xl">psychology</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-mono uppercase tracking-wider">
                        22. Asistente Virtual Corporativo & Bot de Telegram
                    </h2>
                    <p class="text-xs font-mono text-obsidian-muted">
                        Consultas en lenguaje natural mediante el servicio local de IA y comandos desde la aplicación móvil
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToIndex(event)" class="px-2.5 py-1 rounded-lg bg-obsidian-card hover:bg-obsidian-border border border-obsidian-border text-[11px] font-mono text-obsidian-muted hover:text-cyan-300 flex items-center gap-1.5 transition cursor-pointer shadow-sm" title="Volver al índice de módulos">
                    <span class="material-symbols-outlined text-sm text-cyan-400">arrow_upward</span>
                    <span class="hidden sm:inline">Volver al Índice</span>
                </button>
            </div>
        </div>

        <div class="rounded-xl overflow-hidden border border-obsidian-border/80 group relative cursor-pointer" onclick="openLightbox('{{ asset('img/help/bot.png') }}', 'Bot de Notificaciones y Asistente Virtual')">
            <img src="{{ asset('img/help/bot.png') }}" alt="Bot Telegram" class="w-full h-auto object-cover group-hover:scale-[1.01] transition duration-300">
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 text-white font-mono text-xs">
                <span class="material-symbols-outlined">zoom_in</span>
                <span>Clic para ampliar imagen</span>
            </div>
        </div>

        <div class="space-y-3 font-mono text-xs">
            <h3 class="font-bold text-cyan-300 uppercase tracking-wider">Canales de Interacción Inteligente:</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-cyan-400 text-sm">chat</span> Asistente Virtual en el Portal Web:</b>
                    <p class="text-obsidian-muted text-[11px]">Integrado en la esquina inferior del portal web para responder consultas sobre estado de la red, inventario de puestos o diagnósticos en tiempo real utilizando el motor local de IA.</p>
                </div>
                <div class="p-3 rounded-lg bg-obsidian-card/60 border border-obsidian-border/60 space-y-1">
                    <b class="text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-blue-400 text-sm">send</span> Despacho de Reportes a Telegram:</b>
                    <p class="text-obsidian-muted text-[11px]">Genera resúmenes periódicos de disponibilidad y permite a los administradores autorizados consultar comandos rápidos mediante el bot corporativo.</p>
                </div>
            </div>
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
    <div class="max-w-6xl w-full max-h-[88vh] flex flex-col items-center justify-center relative animate-in fade-in zoom-in-95 duration-200" onclick="event.stopPropagation()">
        <div class="w-full flex items-center justify-between pb-2 text-xs font-mono text-white shrink-0">
            <span id="lightbox-caption" class="text-cyan-300 font-bold truncate max-w-[80vw]">Captura de Pantalla</span>
            <button type="button" onclick="closeLightbox()" class="text-obsidian-muted hover:text-white text-2xl leading-none p-1 transition cursor-pointer" title="Cerrar">&times;</button>
        </div>
        <div class="rounded-xl overflow-hidden border border-cyan-500/40 shadow-2xl bg-black/60 max-h-[80vh] flex items-center justify-center flex-1 min-h-0">
            <img id="lightbox-image" src="" alt="Captura Ampliada" class="max-w-full max-h-full object-contain">
        </div>
        <span class="text-[10px] font-mono text-obsidian-muted mt-2 shrink-0">Presiona ESC o haz clic afuera para cerrar</span>
    </div>
</div>

<script>
    // Desplazamiento suave al índice de módulos (respetando barra superior de 70px)
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
