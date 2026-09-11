@extends('layouts.admin')

@section('page_title', 'Dashboard')

@section('admin_content')
<div class="space-y-6">
    <!-- ========================================================================= -->
    <!-- BARRA SUPERIOR DE ACCIONES RÁPIDAS Y TELEMETRÍA                           -->
    <!-- ========================================================================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 sm:px-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 backdrop-blur-md shadow-md">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-cyan-950/70 border border-cyan-500/40 flex items-center justify-center text-cyan-400 shrink-0">
                <span class="material-symbols-outlined text-lg">monitoring</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                        Telemetría en Vivo & Emisión Oficial
                    </h2>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-950/80 text-emerald-400 border border-emerald-500/40">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        ACTIVO
                    </span>
                </div>
                <p class="text-[10px] font-mono text-obsidian-muted">
                    Operador en turno: <span class="text-cyan-300 font-semibold">{{ auth()->user()->full_title_name }}</span>
                    @if(auth()->user()->hasCompleteAtitProfile())
                        <span class="text-emerald-400 font-bold ml-1">✓ Ficha Lista</span>
                    @else
                        <a href="{{ route('admin.profile.show') }}" class="text-amber-400 underline font-bold ml-1 hover:text-white">⚠ Ficha Incompleta</a>
                    @endif
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 self-start sm:self-auto flex-wrap">
            <!-- BOTÓN MODAL DESPACHAR REPORTE A TELEGRAM -->
            <button type="button" onclick="openTelegramDispatchModal()" id="btn-dispatch-telegram-top"
                class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-cyan-500/20 via-blue-500/25 to-cyan-500/20 hover:from-cyan-500/35 hover:to-blue-500/35 border border-cyan-400/50 text-cyan-200 font-bold text-xs font-mono tracking-wide shadow-md shadow-cyan-950/30 hover:scale-[1.02] active:scale-[0.98] transition flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base text-cyan-400">send</span>
                <span>Enviar Reporte a Telegram</span>
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECCIÓN SUPERIOR: SUPERVISIÓN EN VIVO (IDÉNTICA A LA VISTA PÚBLICA)       -->
    <!-- ========================================================================= -->
    @include('partials.monitoring_board', ['isDashboard' => true])

    @if(auth()->user()->isAdmin())
    <!-- ========================================================================= -->
    <!-- ACCESO RÁPIDO A CONFIGURACIÓN AVANZADA DEL SISTEMA                        -->
    <!-- ========================================================================= -->
    <div class="pt-4 pb-2 border-t border-obsidian-border/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs font-mono">
        <div class="flex items-center gap-2 text-obsidian-muted">
            <span class="material-symbols-outlined text-base text-cyan-400">tune</span>
            <span>Ajustes de infraestructura, clúster, LDAP y automatizaciones:</span>
        </div>
        <a href="{{ route('admin.settings.advanced') }}" class="px-3.5 py-1.5 rounded-lg bg-obsidian-panel border border-cyan-500/30 text-cyan-300 hover:text-white hover:bg-cyan-950/50 flex items-center gap-1.5 transition">
            <span>Configuración Avanzada del Sistema</span>
            <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </a>
    </div>
    @endif
</div>

<!-- MODAL ENVIAR REPORTE OFICIAL A TELEGRAM -->
<div id="modal-telegram-dispatch" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="glass-panel max-w-2xl w-full rounded-2xl p-5 sm:p-6 border border-cyan-500/40 shadow-2xl space-y-4 max-h-[92vh] flex flex-col">
        <!-- HEADER MODAL -->
        <div class="flex items-center justify-between pb-3 border-b border-obsidian-border shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-950 to-blue-950 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shadow-md shrink-0">
                    <span class="material-symbols-outlined text-2xl">send</span>
                </div>
                <div>
                    <h3 class="text-sm sm:text-base font-bold text-white uppercase font-mono tracking-wide flex items-center gap-2">
                        Envío Oficial a Telegram
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-cyan-950 text-cyan-300 border border-cyan-500/40">
                            ATIT • EN VIVO
                        </span>
                    </h3>
                    <p class="text-[11px] font-mono text-obsidian-muted">
                        Emisión manual de reportes consolidados firmados con tu ficha institucional
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeTelegramDispatchModal()" class="text-obsidian-muted hover:text-white text-2xl leading-none transition p-1 cursor-pointer" title="Cerrar ventana">&times;</button>
        </div>

        <!-- CUERPO DEL MODAL (SCROLLABLE) -->
        <div class="flex-1 overflow-y-auto space-y-4 pr-1 custom-scroll" id="dispatch-modal-scroll-area">
            <!-- ALERTA DE RESPUESTA / RESULTADO (FEEDBACK INMEDIATO ARRIBA) -->
            <div id="dispatch-feedback-alert" class="hidden p-3.5 rounded-xl border text-xs font-mono transition-all"></div>

            <!-- 1. EXPLICACIÓN DE PROPÓSITO ("Para qué es y qué hará") -->
            <div class="p-3.5 rounded-xl bg-gradient-to-r from-blue-950/40 to-cyan-950/40 border border-cyan-500/30 text-xs font-mono text-cyan-200/90 leading-relaxed flex items-start gap-3">
                <span class="material-symbols-outlined text-cyan-400 text-lg shrink-0 mt-0.5">info</span>
                <div>
                    <strong class="text-white block mb-0.5">¿Qué realiza esta acción?</strong>
                    Compila el estado de disponibilidad y latencia de la infraestructura y lo transmite de forma inmediata al canal institucional de Telegram. El reporte conservará la estructura oficial establecida e incluirá tus credenciales como operador en turno.
                </div>
            </div>

            <!-- 2. BANNER DE HISTORIAL Y ALERTA DE RECIENTE ENVÍO -->
            <div id="dispatch-recent-alert" class="p-3.5 rounded-xl border text-xs font-mono flex items-start gap-3 bg-[#040e1a] border-obsidian-border text-obsidian-muted transition-all">
                <span class="material-symbols-outlined text-base animate-spin text-cyan-400 shrink-0 mt-0.5" id="dispatch-recent-icon">sync</span>
                <div class="flex-1" id="dispatch-recent-content">
                    Consultando registros de actividad reciente...
                </div>
            </div>

            <!-- 3. FICHA DEL OPERADOR FIRMANTE -->
            <div class="p-3.5 rounded-xl bg-[#040e1a]/90 border border-obsidian-border/80 space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-mono font-bold uppercase text-gray-300 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-cyan-400">badge</span>
                        Ficha Institucional del Operador
                    </span>
                    @if(auth()->user()->hasCompleteAtitProfile())
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950/80 text-emerald-400 border border-emerald-500/40">
                            ✓ Ficha Completa
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-950/80 text-amber-300 border border-amber-500/40">
                            ⚠ Ficha Incompleta
                        </span>
                    @endif
                </div>

                @if(!auth()->user()->hasCompleteAtitProfile())
                    <div class="p-3 rounded-lg bg-amber-950/50 border border-amber-500/40 text-amber-200 text-xs font-mono flex items-start gap-2.5">
                        <span class="material-symbols-outlined text-base text-amber-400 shrink-0 mt-0.5">warning</span>
                        <div class="leading-relaxed">
                            <strong>Datos Faltantes para Firma Institucional:</strong><br>
                            Tu cuenta aún no tiene completos los campos requeridos (C.I., N° Personal o Teléfono). Debes completarlos para autorizar el envío de reportes oficiales.
                            <div class="pt-2">
                                <a href="{{ route('admin.profile.show') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 border border-amber-400/50 text-amber-200 font-bold hover:text-white transition">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                    <span>Completar Mi Perfil Ahora &rarr;</span>
                                </a>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- PREVISUALIZACIÓN DE LA FIRMA -->
                    <div class="bg-[#020710] p-3 rounded-lg border border-obsidian-border/60 font-mono text-xs text-gray-300 space-y-1">
                        <div class="text-[10px] text-obsidian-muted uppercase tracking-wider mb-1 flex items-center justify-between">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs text-cyan-400">draw</span>
                                Estructura de firma institucional al pie del reporte:
                            </span>
                            <span id="dispatch-preview-obs-badge" class="hidden px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-amber-950/80 text-amber-300 border border-amber-500/40">
                                + Observaciones Anexadas
                            </span>
                        </div>
                        <div class="pl-2 border-l-2 border-cyan-500/60 text-cyan-200/90 whitespace-pre-line leading-relaxed select-all">
<div id="dispatch-preview-obs-container" class="hidden mb-2 text-amber-300 bg-amber-950/20 p-2 rounded border border-amber-500/30">━━━━━━━━━━━━
📝 <b>OBSERVACIONES:</b>
<span id="dispatch-preview-obs-text" class="text-amber-200"></span>
━━━━━━━━━━━━</div>👨‍💻 <b>Personal de  ATIT:</b>
{{ auth()->user()->full_title_name }}
C.I: {{ auth()->user()->cedula }}
N° Personal: {{ auth()->user()->personal_number }}
📱Tlf: {{ auth()->user()->phone }}</div>
                    </div>
                @endif
            </div>

            <!-- 4. SELECTOR DEL TIPO DE REPORTE -->
            <div class="space-y-2">
                <label class="text-xs font-mono font-bold uppercase text-gray-300 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-cyan-400">category</span>
                    Seleccione el Reporte a Enviar
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- OPCIÓN 1: SERVICIOS -->
                    <label class="cursor-pointer group">
                        <input type="radio" name="dispatch_report_type" value="servicios" checked onchange="triggerFullPreviewReload()" class="peer sr-only">
                        <div class="p-3.5 rounded-xl border border-obsidian-border bg-[#040e1a] peer-checked:border-cyan-400 peer-checked:bg-cyan-950/30 peer-checked:shadow-lg peer-checked:shadow-cyan-950/40 transition group-hover:border-cyan-500/50 h-full flex flex-col justify-between">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-blue-400 text-lg">dns</span>
                                    <span class="text-xs font-bold text-white font-mono uppercase">Servicios Corporativos</span>
                                </div>
                                <span class="w-4 h-4 rounded-full border border-cyan-400 peer-checked:bg-cyan-400 peer-checked:border-cyan-400 flex items-center justify-center text-[10px] text-black font-bold">✓</span>
                            </div>
                            <p class="text-[11px] font-mono text-obsidian-muted leading-tight">
                                Telemetría de servidores corporativos y regionales Carabobo (A a la T).
                            </p>
                        </div>
                    </label>

                    <!-- OPCIÓN 2: SEDES -->
                    <label class="cursor-pointer group">
                        <input type="radio" name="dispatch_report_type" value="sedes" onchange="triggerFullPreviewReload()" class="peer sr-only">
                        <div class="p-3.5 rounded-xl border border-obsidian-border bg-[#040e1a] peer-checked:border-cyan-400 peer-checked:bg-cyan-950/30 peer-checked:shadow-lg peer-checked:shadow-cyan-950/40 transition group-hover:border-cyan-500/50 h-full flex flex-col justify-between">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-purple-400 text-lg">domain</span>
                                    <span class="text-xs font-bold text-white font-mono uppercase">Sedes y Enlaces</span>
                                </div>
                                <span class="w-4 h-4 rounded-full border border-cyan-400 peer-checked:bg-cyan-400 peer-checked:border-cyan-400 flex items-center justify-center text-[10px] text-black font-bold">✓</span>
                            </div>
                            <p class="text-[11px] font-mono text-obsidian-muted leading-tight">
                                Estatus de sedes CIAU Eje Costero, enlaces WAN y dispositivos de red.
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- 5. SELECTOR DE DESTINATARIO -->
            @if(auth()->user()->isAdmin())
            <div class="space-y-2">
                <label class="text-xs font-mono font-bold uppercase text-gray-300 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-cyan-400">near_me</span>
                    Destino de la Transmisión
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <!-- OPCIÓN 1: AMBOS -->
                    <label class="cursor-pointer group">
                        <input type="radio" name="dispatch_destination" value="both" checked class="peer sr-only">
                        <div class="p-3 rounded-xl border border-obsidian-border bg-[#040e1a] peer-checked:border-cyan-400 peer-checked:bg-cyan-950/30 peer-checked:shadow-md transition group-hover:border-cyan-500/50 h-full flex flex-col justify-between">
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-cyan-400 text-base">campaign</span>
                                    <span class="text-xs font-bold text-white font-mono uppercase">Ambos</span>
                                </div>
                                <span class="w-3.5 h-3.5 rounded-full border border-cyan-400 peer-checked:bg-cyan-400 flex items-center justify-center text-[9px] text-black font-bold">✓</span>
                            </div>
                            <p class="text-[10px] font-mono text-obsidian-muted leading-tight">
                                Grupo Corporativo y Administrador Privado.
                            </p>
                        </div>
                    </label>

                    <!-- OPCIÓN 2: GRUPO CORPORATIVO -->
                    <label class="cursor-pointer group">
                        <input type="radio" name="dispatch_destination" value="group" class="peer sr-only">
                        <div class="p-3 rounded-xl border border-obsidian-border bg-[#040e1a] peer-checked:border-cyan-400 peer-checked:bg-cyan-950/30 peer-checked:shadow-md transition group-hover:border-cyan-500/50 h-full flex flex-col justify-between">
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-blue-400 text-base">groups</span>
                                    <span class="text-xs font-bold text-white font-mono uppercase">Grupo Sede</span>
                                </div>
                                <span class="w-3.5 h-3.5 rounded-full border border-cyan-400 peer-checked:bg-cyan-400 flex items-center justify-center text-[9px] text-black font-bold">✓</span>
                            </div>
                            <p class="text-[10px] font-mono text-obsidian-muted leading-tight">
                                Canal de Sede Valle Seco (-1001383163558).
                            </p>
                        </div>
                    </label>

                    <!-- OPCIÓN 3: OWNER / PRIVADO -->
                    <label class="cursor-pointer group">
                        <input type="radio" name="dispatch_destination" value="owner" class="peer sr-only">
                        <div class="p-3 rounded-xl border border-obsidian-border bg-[#040e1a] peer-checked:border-cyan-400 peer-checked:bg-cyan-950/30 peer-checked:shadow-md transition group-hover:border-cyan-500/50 h-full flex flex-col justify-between">
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-purple-400 text-base">lock_person</span>
                                    <span class="text-xs font-bold text-white font-mono uppercase">Solo Owner</span>
                                </div>
                                <span class="w-3.5 h-3.5 rounded-full border border-cyan-400 peer-checked:bg-cyan-400 flex items-center justify-center text-[9px] text-black font-bold">✓</span>
                            </div>
                            <p class="text-[10px] font-mono text-obsidian-muted leading-tight">
                                Chat privado del Administrador (38914901).
                            </p>
                        </div>
                    </label>
                </div>
            </div>
            @else
            <!-- DESTINO EXCLUSIVO PARA ROL OPERADOR (SIN ACCESO A OWNER) -->
            <div class="space-y-1.5">
                <label class="text-xs font-mono font-bold uppercase text-gray-300 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-cyan-400">near_me</span>
                    Destino de la Transmisión
                </label>
                <div class="p-3 rounded-xl border border-cyan-500/30 bg-[#040e1a] flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-blue-950/80 border border-blue-500/40 flex items-center justify-center text-blue-300 shrink-0">
                            <span class="material-symbols-outlined text-base">groups</span>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-white font-mono uppercase block">Grupo Corporativo (Sede Valle Seco)</span>
                            <span class="text-[10px] font-mono text-obsidian-muted">Canal institucional oficial autorizado (<code>-1001383163558</code>)</span>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-cyan-950 text-cyan-300 border border-cyan-500/40 shrink-0">
                        ✓ Canal Autorizado
                    </span>
                    <input type="hidden" name="dispatch_destination" value="group">
                </div>
            </div>
            @endif

            <!-- 6. CAMPO DE OBSERVACIONES (EXCLUSIVO WEB) -->
            <div class="p-3.5 rounded-xl bg-[#040e1a]/90 border border-obsidian-border/80 space-y-2.5">
                <div class="flex items-center justify-between">
                    <label for="dispatch_toggle_observations" class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" id="dispatch_toggle_observations" onchange="toggleObservationsInput()" class="w-4 h-4 rounded bg-[#020710] border-cyan-500/40 text-cyan-500 focus:ring-0 focus:ring-offset-0 cursor-pointer">
                        <span class="text-xs font-mono font-bold uppercase text-gray-300 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-amber-400">edit_note</span>
                            ¿Desea agregar observaciones al reporte?
                        </span>
                    </label>
                    <span class="text-[10px] font-mono text-obsidian-muted uppercase tracking-wider">Opcional • Web</span>
                </div>

                <div id="dispatch_observations_container" class="hidden space-y-1.5 pt-1">
                    <textarea id="dispatch_observations_text" maxlength="1000" rows="3" oninput="updateObservationsPreview()" placeholder="Escriba aquí cualquier novedad u observación operativa que se anexará antes de la firma institucional (ej. Mantenimiento preventivo en enlace microondas, contingencia eléctrica en subestación...)" class="w-full bg-[#020710] border border-obsidian-border rounded-xl p-2.5 text-xs font-mono text-white focus:outline-none focus:border-amber-400 resize-y transition custom-scroll"></textarea>
                    <div class="flex items-center justify-between text-[10px] font-mono text-obsidian-muted">
                        <span>Se insertará inmediatamente antes de la firma de <strong>Personal de ATIT</strong>.</span>
                        <span id="dispatch_obs_counter">0 / 1000</span>
                    </div>
                </div>
            </div>

            <!-- 7. CAPTURA DE PANTALLA EN HD & MODO DE DATOS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- CAPTURA HD -->
                <div class="p-3 rounded-xl bg-[#040e1a]/70 border border-obsidian-border/60 flex items-center justify-between gap-2">
                    <label for="dispatch_include_screenshot" class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" id="dispatch_include_screenshot" checked class="w-4 h-4 rounded bg-[#020710] border-cyan-500/40 text-cyan-500 focus:ring-0 focus:ring-offset-0 cursor-pointer">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-cyan-400 text-lg">photo_camera</span>
                            <div>
                                <span class="text-xs font-mono text-white font-bold block">Captura Gráfica HD:</span>
                                <span class="text-[10px] font-mono text-obsidian-muted">Adjunta imagen panorámica 2x</span>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- ORIGEN DE LOS DATOS -->
                <div class="p-3 rounded-xl bg-[#040e1a]/70 border border-obsidian-border/60 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-400 text-base">bolt</span>
                        <div>
                            <span class="text-xs font-mono text-white font-bold block">Origen de Datos:</span>
                            <span class="text-[10px] font-mono text-obsidian-muted">Snapshot sin retrasos</span>
                        </div>
                    </div>
                    <select id="dispatch_mode_select" onchange="triggerFullPreviewReload()" class="bg-[#020710] border border-obsidian-border text-white text-xs font-mono rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-cyan-400">
                        <option value="instant" selected>⚡ Snapshot</option>
                        <option value="live">🔍 Sondeo Físico</option>
                    </select>
                </div>
            </div>

            <!-- 8. VISTA PREVIA COMPLETA DEL MENSAJE (SIMULACIÓN TELEGRAM) -->
            <div class="space-y-2 pt-2 border-t border-obsidian-border/80">
                <div class="flex items-center justify-between">
                    <button type="button" onclick="toggleFullMessagePreview()" class="flex items-center gap-1.5 text-xs font-mono font-bold text-cyan-400 hover:text-cyan-300 transition cursor-pointer">
                        <span class="material-symbols-outlined text-sm" id="icon-toggle-full-preview">visibility</span>
                        <span id="text-toggle-full-preview">Ver Vista Previa del Mensaje Completo</span>
                        <span class="material-symbols-outlined text-xs transition-transform" id="arrow-toggle-full-preview">expand_more</span>
                    </button>
                    <span class="text-[10px] font-mono text-obsidian-muted flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs text-blue-400">send</span>
                        Simulación de entrega Telegram
                    </span>
                </div>

                <div id="dispatch-full-preview-container" class="hidden space-y-2 pt-1">
                    <div class="p-3.5 rounded-xl bg-[#08121f] border border-cyan-500/30 text-gray-200 font-mono text-xs shadow-inner">
                        <div class="flex items-center justify-between pb-2 mb-2 border-b border-cyan-500/20 text-[10px] text-cyan-300">
                            <span class="flex items-center gap-1.5 font-bold uppercase tracking-wider">
                                <span class="material-symbols-outlined text-xs text-cyan-400">chat</span>
                                Mensaje que recibirá Telegram:
                            </span>
                            <button type="button" onclick="loadFullMessagePreview()" class="text-obsidian-muted hover:text-white flex items-center gap-1 transition cursor-pointer" title="Recargar vista previa">
                                <span class="material-symbols-outlined text-xs" id="spinner-preview-refresh">sync</span>
                                <span>Actualizar</span>
                            </button>
                        </div>
                        <div id="dispatch-full-preview-content" class="max-h-64 overflow-y-auto whitespace-pre-wrap leading-relaxed pr-1 custom-scroll text-[11px] select-all bg-[#040e1a] p-3 rounded-lg border border-obsidian-border/60">
                            Cargando previsualización...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FOOTER MODAL CON ACCIONES -->
        <div class="pt-3 border-t border-obsidian-border flex items-center justify-between gap-3 shrink-0">
            <button type="button" onclick="closeTelegramDispatchModal()" class="px-4 py-2 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white text-xs font-mono font-bold transition cursor-pointer">
                Cancelar
            </button>

            <button type="button" onclick="submitTelegramDispatch()" id="btn-submit-telegram-dispatch"
                {{ !auth()->user()->hasCompleteAtitProfile() ? 'disabled' : '' }}
                class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-black font-mono font-bold text-xs uppercase tracking-wider flex items-center gap-2 shadow-lg shadow-cyan-950/50 hover:shadow-cyan-500/25 transition-all cursor-pointer {{ !auth()->user()->hasCompleteAtitProfile() ? 'opacity-50 cursor-not-allowed grayscale' : '' }}">
                <span class="material-symbols-outlined text-base" id="icon-submit-dispatch">send</span>
                <span id="text-submit-dispatch">Confirmar y Enviar a Telegram</span>
            </button>
        </div>
    </div>
</div>

<script>
// --- GESTOR DE ENVÍO OFICIAL A TELEGRAM ---
function toggleObservationsInput() {
    const toggle = document.getElementById('dispatch_toggle_observations');
    const container = document.getElementById('dispatch_observations_container');
    const obsText = document.getElementById('dispatch_observations_text');
    if (!toggle || !container) return;

    if (toggle.checked) {
        container.classList.remove('hidden');
        if (obsText) obsText.focus();
    } else {
        container.classList.add('hidden');
    }
    updateObservationsPreview();
}

function updateObservationsPreview() {
    const toggle = document.getElementById('dispatch_toggle_observations');
    const obsText = document.getElementById('dispatch_observations_text');
    const previewContainer = document.getElementById('dispatch-preview-obs-container');
    const previewText = document.getElementById('dispatch-preview-obs-text');
    const previewBadge = document.getElementById('dispatch-preview-obs-badge');
    const counter = document.getElementById('dispatch_obs_counter');

    const val = (obsText ? obsText.value : '').trim();
    if (counter) counter.textContent = `${(obsText ? obsText.value : '').length} / 1000`;

    if (toggle && toggle.checked && val.length > 0) {
        if (previewContainer) previewContainer.classList.remove('hidden');
        if (previewBadge) previewBadge.classList.remove('hidden');
        if (previewText) previewText.textContent = val;
    } else {
        if (previewContainer) previewContainer.classList.add('hidden');
        if (previewBadge) previewBadge.classList.add('hidden');
        if (previewText) previewText.textContent = '';
    }
    triggerFullPreviewReload();
}

let fullPreviewDebounceTimer = null;

function toggleFullMessagePreview() {
    const container = document.getElementById('dispatch-full-preview-container');
    const icon = document.getElementById('icon-toggle-full-preview');
    const text = document.getElementById('text-toggle-full-preview');
    const arrow = document.getElementById('arrow-toggle-full-preview');
    if (!container) return;

    const isHidden = container.classList.contains('hidden');
    if (isHidden) {
        container.classList.remove('hidden');
        if (icon) icon.textContent = 'visibility_off';
        if (text) text.textContent = 'Ocultar Vista Previa del Mensaje';
        if (arrow) arrow.textContent = 'expand_less';
        loadFullMessagePreview();
    } else {
        container.classList.add('hidden');
        if (icon) icon.textContent = 'visibility';
        if (text) text.textContent = 'Ver Vista Previa del Mensaje Completo';
        if (arrow) arrow.textContent = 'expand_more';
    }
}

function triggerFullPreviewReload() {
    const container = document.getElementById('dispatch-full-preview-container');
    if (container && !container.classList.contains('hidden')) {
        clearTimeout(fullPreviewDebounceTimer);
        fullPreviewDebounceTimer = setTimeout(() => {
            loadFullMessagePreview();
        }, 350);
    }
}

function loadFullMessagePreview() {
    const previewContent = document.getElementById('dispatch-full-preview-content');
    const spinner = document.getElementById('spinner-preview-refresh');
    if (!previewContent) return;

    const reportTypeEl = document.querySelector('input[name="dispatch_report_type"]:checked');
    const reportType = reportTypeEl ? reportTypeEl.value : 'servicios';
    const modeEl = document.getElementById('dispatch_mode_select');
    const mode = modeEl ? modeEl.value : 'instant';
    const obsToggle = document.getElementById('dispatch_toggle_observations');
    const obsTextEl = document.getElementById('dispatch_observations_text');
    const observations = (obsToggle && obsToggle.checked && obsTextEl) ? obsTextEl.value.trim() : '';

    if (spinner) spinner.classList.add('animate-spin');
    previewContent.classList.add('opacity-50');

    fetch('{{ route('admin.telegram.dispatch.preview') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            report_type: reportType,
            mode: mode,
            observations: observations
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.preview_raw) {
            previewContent.innerHTML = data.preview_raw;
        } else {
            previewContent.innerHTML = '<span class="text-rose-400">Error al generar la vista previa: ' + (data.message || 'Desconocido') + '</span>';
        }
    })
    .catch(err => {
        previewContent.innerHTML = '<span class="text-rose-400">Error al consultar el servidor: ' + err.message + '</span>';
    })
    .finally(() => {
        if (spinner) spinner.classList.remove('animate-spin');
        previewContent.classList.remove('opacity-50');
    });
}

function openTelegramDispatchModal() {
    const modal = document.getElementById('modal-telegram-dispatch');
    if (!modal) return;

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    const feedback = document.getElementById('dispatch-feedback-alert');
    if (feedback) {
        feedback.classList.add('hidden');
        feedback.innerHTML = '';
    }

    updateObservationsPreview();
    fetchLatestDispatchStatus();
}

function closeTelegramDispatchModal() {
    const modal = document.getElementById('modal-telegram-dispatch');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function fetchLatestDispatchStatus() {
    const icon = document.getElementById('dispatch-recent-icon');
    const content = document.getElementById('dispatch-recent-content');
    const alertBox = document.getElementById('dispatch-recent-alert');

    if (!content || !alertBox) return;

    if (icon) {
        icon.className = 'material-symbols-outlined text-base animate-spin text-cyan-400 shrink-0 mt-0.5';
        icon.textContent = 'sync';
    }
    alertBox.className = 'p-3.5 rounded-xl border text-xs font-mono flex items-start gap-3 bg-[#040e1a] border-obsidian-border text-obsidian-muted transition-all';
    content.textContent = 'Consultando registros de actividad reciente...';

    fetch('{{ route('admin.telegram.dispatch.status') }}', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (icon) icon.classList.remove('animate-spin');

        const latest = data.latest_general;
        if (latest && latest.is_recent) {
            alertBox.className = 'p-3.5 rounded-xl border text-xs font-mono flex items-start gap-3 bg-amber-950/40 border-amber-500/50 text-amber-200 transition-all';
            if (icon) {
                icon.className = 'material-symbols-outlined text-base text-amber-400 shrink-0 mt-0.5';
                icon.textContent = 'warning';
            }
            content.innerHTML = `<div><strong class="text-amber-300 block mb-0.5">⚠️ Aviso de Envío Reciente (${latest.diff_minutes} min):</strong>` +
                `El operador <strong class="text-white">${latest.operator_name}</strong> envió un reporte de <strong class="text-cyan-300">${latest.report_type_label}</strong> ` +
                `<span class="underline">${latest.time_ago}</span> (${latest.created_at}). Confirme si es indispensable emitir otra actualización para evitar duplicados en el canal institucional.</div>`;
        } else if (latest) {
            alertBox.className = 'p-3.5 rounded-xl border text-xs font-mono flex items-start gap-3 bg-emerald-950/30 border-emerald-500/40 text-emerald-300 transition-all';
            if (icon) {
                icon.className = 'material-symbols-outlined text-base text-emerald-400 shrink-0 mt-0.5';
                icon.textContent = 'check_circle';
            }
            content.innerHTML = `<div><strong class="text-white block mb-0.5">✅ Canal Despejado:</strong>` +
                `Último reporte enviado ${latest.time_ago} (${latest.created_at}) emitido por <span class="text-white font-bold">${latest.operator_name}</span>.</div>`;
        } else {
            alertBox.className = 'p-3.5 rounded-xl border text-xs font-mono flex items-start gap-3 bg-[#040e1a] border-obsidian-border text-obsidian-muted transition-all';
            if (icon) {
                icon.className = 'material-symbols-outlined text-base text-cyan-400 shrink-0 mt-0.5';
                icon.textContent = 'info';
            }
            content.textContent = 'No hay registros de envíos previos en la base de datos.';
        }
    })
    .catch(err => {
        if (icon) icon.classList.remove('animate-spin');
        content.textContent = 'No se pudo consultar el historial reciente de envíos: ' + err.message;
    });
}

function submitTelegramDispatch() {
    const reportTypeEl = document.querySelector('input[name="dispatch_report_type"]:checked');
    const reportType = reportTypeEl ? reportTypeEl.value : 'servicios';
    const destRadio = document.querySelector('input[name="dispatch_destination"]:checked');
    const destHidden = document.querySelector('input[name="dispatch_destination"][type="hidden"]');
    const destination = destRadio ? destRadio.value : (destHidden ? destHidden.value : 'group');
    const modeEl = document.getElementById('dispatch_mode_select');
    const mode = modeEl ? modeEl.value : 'instant';

    const obsToggle = document.getElementById('dispatch_toggle_observations');
    const obsTextEl = document.getElementById('dispatch_observations_text');
    const observations = (obsToggle && obsToggle.checked && obsTextEl) ? obsTextEl.value.trim() : '';

    const screenshotEl = document.getElementById('dispatch_include_screenshot');
    const includeScreenshot = screenshotEl ? screenshotEl.checked : true;

    const btn = document.getElementById('btn-submit-telegram-dispatch');
    const icon = document.getElementById('icon-submit-dispatch');
    const text = document.getElementById('text-submit-dispatch');
    const feedback = document.getElementById('dispatch-feedback-alert');

    if (btn) btn.disabled = true;
    if (icon) {
        icon.className = 'material-symbols-outlined text-base animate-spin';
        icon.textContent = 'sync';
    }
    if (text) text.textContent = 'Enviando Reporte...';

    if (feedback) {
        feedback.classList.remove('hidden', 'bg-emerald-950/40', 'border-emerald-500/50', 'text-emerald-300', 'bg-rose-950/40', 'border-rose-500/50', 'text-rose-300');
        feedback.classList.add('bg-cyan-950/40', 'border-cyan-500/50', 'text-cyan-300');
        feedback.innerHTML = '<div class="flex items-center gap-2"><span class="material-symbols-outlined text-sm animate-spin">sync</span> Compilando telemetría oficial y transmitiendo a Telegram...</div>';
    }

    fetch('{{ route('admin.telegram.dispatch') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            report_type: reportType,
            mode: mode,
            destination: destination,
            observations: observations,
            include_screenshot: includeScreenshot
        })
    })
    .then(r => r.json().then(data => ({ status: r.status, body: data })))
    .then(({ status, body }) => {
        if (feedback) {
            feedback.classList.remove('bg-cyan-950/40', 'border-cyan-500/50', 'text-cyan-300');
            if (status === 200 && body.success) {
                feedback.classList.add('bg-emerald-950/40', 'border-emerald-500/50', 'text-emerald-300');
                feedback.innerHTML = `<div class="flex items-start gap-2.5">
                    <span class="material-symbols-outlined text-base text-emerald-400 shrink-0 mt-0.5">check_circle</span>
                    <div>
                        <strong class="text-white block">¡Reporte Oficial Transmitido con Éxito!</strong>
                        <span>${body.message}</span>
                        <div class="mt-1 text-[11px] text-cyan-300">Firmado por: <strong>${body.operator}</strong> • Hora: <strong>${body.dispatched_at}</strong></div>
                    </div>
                </div>`;
                fetchLatestDispatchStatus();
            } else if (body.incomplete_profile) {
                feedback.classList.add('bg-amber-950/40', 'border-amber-500/50', 'text-amber-200');
                feedback.innerHTML = `<div class="flex items-start gap-2.5">
                    <span class="material-symbols-outlined text-base text-amber-400 shrink-0 mt-0.5">warning</span>
                    <div>
                        <strong class="text-white block">Perfil Institucional Incompleto</strong>
                        <span>${body.message}</span>
                        <div class="mt-1.5"><a href="${body.profile_url}" class="underline font-bold text-cyan-300 hover:text-white">Ir a Mi Perfil &rarr;</a></div>
                    </div>
                </div>`;
            } else {
                feedback.classList.add('bg-rose-950/40', 'border-rose-500/50', 'text-rose-300');
                feedback.innerHTML = `<div class="flex items-start gap-2.5">
                    <span class="material-symbols-outlined text-base text-rose-400 shrink-0 mt-0.5">error</span>
                    <div>
                        <strong class="text-white block">Error en el Envío</strong>
                        <span>${body.message || 'No se pudo transmitir el reporte.'}</span>
                    </div>
                </div>`;
            }
        }
    })
    .catch(err => {
        if (feedback) {
            feedback.classList.remove('bg-cyan-950/40', 'border-cyan-500/50', 'text-cyan-300');
            feedback.classList.add('bg-rose-950/40', 'border-rose-500/50', 'text-rose-300');
            feedback.innerHTML = `<div class="flex items-start gap-2.5">
                <span class="material-symbols-outlined text-base text-rose-400 shrink-0 mt-0.5">error</span>
                <div>
                    <strong class="text-white block">Error de Red</strong>
                    <span>Ocurrió una falla de conexión al comunicarse con el servidor: ${err.message}</span>
                </div>
            </div>`;
        }
    })
    .finally(() => {
        if (btn) btn.disabled = false;
        if (icon) {
            icon.className = 'material-symbols-outlined text-base';
            icon.textContent = 'send';
        }
        if (text) text.textContent = 'Confirmar y Enviar a Telegram';
    });
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeTelegramDispatchModal();
    }
});
</script>
@endsection
