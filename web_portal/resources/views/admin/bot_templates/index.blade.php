@extends('layouts.admin')

@section('page_title', 'Plantillas de Mensajería')

@section('admin_content')
<div class="space-y-6">
    <!-- CABECERA DE LA PÁGINA -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-obsidian-border/80">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-950 to-blue-950 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shadow-md shrink-0">
                <span class="material-symbols-outlined text-2xl">edit_note</span>
            </div>
            <div>
                <h1 class="text-base sm:text-lg font-bold text-white tracking-wide uppercase font-mono flex items-center gap-2">
                    Plantillas de Mensajería & Reportes Oficiales
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-cyan-950 text-cyan-300 border border-cyan-500/40">
                        MARIADB • SSOT
                    </span>
                </h1>
                <p class="text-xs font-mono text-obsidian-muted">
                    Edite el formato, encabezados institucionales, leyendas y firmas de los reportes emitidos por el Bot de Telegram
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.dashboard') }}" class="px-3.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-xs font-mono text-obsidian-muted hover:text-white transition flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                <span>Dashboard</span>
            </a>
        </div>
    </div>

    <!-- ALERTAS FLASH DE SESIÓN -->
    @if(session('success'))
    <div class="p-3.5 rounded-xl bg-emerald-950/60 border border-emerald-500/50 text-emerald-300 text-xs font-mono flex items-center gap-2 shadow-lg">
        <span class="material-symbols-outlined text-base text-emerald-400">check_circle</span>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="p-3.5 rounded-xl bg-rose-950/60 border border-rose-500/50 text-rose-300 text-xs font-mono flex items-center gap-2 shadow-lg">
        <span class="material-symbols-outlined text-base text-rose-400">error</span>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    <!-- SELECTOR DE PESTAÑAS POR TIPO DE PLANTILLA -->
    @php
        $currentTemplate = $templates->get($activeTab) ?? $templates->first();
    @endphp

    <div class="flex flex-wrap gap-2 border-b border-obsidian-border pb-3">
        <a href="{{ route('admin.bot.templates.index', ['tab' => 'servicios']) }}"
           class="px-4 py-2 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ $activeTab === 'servicios' ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white' }}">
            <span class="material-symbols-outlined text-base">dns</span>
            <span>Servicios Corporativos</span>
        </a>

        <a href="{{ route('admin.bot.templates.index', ['tab' => 'sedes']) }}"
           class="px-4 py-2 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ $activeTab === 'sedes' ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white' }}">
            <span class="material-symbols-outlined text-base">domain</span>
            <span>Sedes y Enlaces</span>
        </a>

        <a href="{{ route('admin.bot.templates.index', ['tab' => 'debug_servicios']) }}"
           class="px-4 py-2 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ $activeTab === 'debug_servicios' ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white' }}">
            <span class="material-symbols-outlined text-base">terminal</span>
            <span>Debug Servicios</span>
        </a>

        <a href="{{ route('admin.bot.templates.index', ['tab' => 'debug_sedes']) }}"
           class="px-4 py-2 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ $activeTab === 'debug_sedes' ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white' }}">
            <span class="material-symbols-outlined text-base">terminal</span>
            <span>Debug Sedes</span>
        </a>
    </div>

    @if($currentTemplate)
    <!-- CUADRÍCULA DE DOS COLUMNAS: FORMULARIO DE EDICIÓN Y PREVISUALIZACIÓN TELEGRAM -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- COLUMNA IZQUIERDA: FORMULARIO (7 COLS) -->
        <div class="lg:col-span-7 glass-panel rounded-2xl p-5 sm:p-6 border border-obsidian-border shadow-xl space-y-5">
            <div class="flex items-center justify-between pb-3 border-b border-obsidian-border/70">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-cyan-400 text-lg">edit</span>
                    <h2 class="text-sm font-bold text-white uppercase font-mono">
                        Configuración: {{ $currentTemplate->title }}
                    </h2>
                </div>
                <span class="text-[11px] font-mono text-obsidian-muted">
                    Clave: <code class="text-cyan-300">{{ $currentTemplate->template_key }}</code>
                </span>
            </div>

            <form action="{{ route('admin.bot.templates.update', $currentTemplate) }}" method="POST" id="form-template-edit" class="space-y-4">
                @csrf
                @method('PUT')

                <!-- TÍTULO DESCRIPTIVO -->
                <div class="space-y-1.5">
                    <label class="text-xs font-mono font-bold text-gray-300 uppercase flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs text-cyan-400">title</span>
                        Nombre Descriptivo de la Plantilla
                    </label>
                    <input type="text" name="title" id="input_title" required
                           value="{{ old('title', $currentTemplate->title) }}"
                           class="w-full bg-[#030914] border border-obsidian-border text-white text-xs font-mono rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-cyan-400 transition"
                           placeholder="Ej. Servicios Corporativos (Carabobo - Valle Seco)"/>
                </div>

                <!-- ENCABEZADO INSTITUCIONAL -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-mono font-bold text-gray-300 uppercase flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-xs text-cyan-400">corporate_fare</span>
                            Encabezado Institucional (Gerencia, División, Departamento, Lugar)
                        </label>
                        <span class="text-[10px] font-mono text-obsidian-muted">Soporta HTML &lt;b&gt;</span>
                    </div>
                    <textarea name="header_text" id="input_header_text" rows="5" required
                              class="w-full bg-[#030914] border border-obsidian-border text-white text-xs font-mono rounded-lg p-3 focus:outline-none focus:border-cyan-400 transition leading-relaxed custom-scroll"
                              placeholder="Líneas institucionales del encabezado...">{{ old('header_text', $currentTemplate->header_text) }}</textarea>
                </div>

                <!-- SUB-ENCABEZADO / SECCIÓN -->
                <div class="space-y-1.5">
                    <label class="text-xs font-mono font-bold text-gray-300 uppercase flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs text-cyan-400">view_headline</span>
                        Sub-encabezado de Estatus
                    </label>
                    <input type="text" name="sub_header" id="input_sub_header"
                           value="{{ old('sub_header', $currentTemplate->sub_header) }}"
                           class="w-full bg-[#030914] border border-obsidian-border text-white text-xs font-mono rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-cyan-400 transition"
                           placeholder="Ej. <b>ESTATUS DE SERVICIOS CORPORATIVOS (CARABOBO - VALLE SECO)</b>"/>
                </div>

                <!-- LEYENDA DE ESTADOS -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-mono font-bold text-gray-300 uppercase flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-xs text-cyan-400">legend_toggle</span>
                            Leyenda de Operatividad y Estados
                        </label>
                        <span class="text-[10px] font-mono text-obsidian-muted">Emojis e indicadores</span>
                    </div>
                    <textarea name="legend_text" id="input_legend_text" rows="4"
                              class="w-full bg-[#030914] border border-obsidian-border text-white text-xs font-mono rounded-lg p-3 focus:outline-none focus:border-cyan-400 transition leading-relaxed custom-scroll"
                              placeholder="Leyenda de operatividad...">{{ old('legend_text', $currentTemplate->legend_text) }}</textarea>
                </div>

                <!-- DECLARACIÓN DE IMPACTO AL SEN -->
                <div class="space-y-1.5">
                    <label class="text-xs font-mono font-bold text-gray-300 uppercase flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs text-cyan-400">electric_bolt</span>
                        Declaración de Impacto al SEN
                    </label>
                    <textarea name="impact_statement" id="input_impact_statement" rows="3"
                              class="w-full bg-[#030914] border border-obsidian-border text-white text-xs font-mono rounded-lg p-3 focus:outline-none focus:border-cyan-400 transition leading-relaxed custom-scroll"
                              placeholder="Impacto al SEN...">{{ old('impact_statement', $currentTemplate->impact_statement) }}</textarea>
                </div>

                <!-- FIRMA PREDETERMINADA PARA CRON AUTOMÁTICO -->
                <div class="space-y-1.5 p-3.5 rounded-xl bg-[#020712] border border-cyan-500/20">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-mono font-bold text-cyan-300 uppercase flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-cyan-400">draw</span>
                            Firma Predeterminada (Envíos Programados por CRON)
                        </label>
                        <span class="text-[10px] font-mono text-emerald-400 bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-500/30">
                            Firma del Owner
                        </span>
                    </div>
                    <p class="text-[11px] font-mono text-obsidian-muted pb-1 leading-relaxed">
                        Esta firma se aplica en los reportes automáticos programados (07:30 y 16:00). En los envíos manuales desde el Dashboard, el sistema la reemplaza dinámicamente con la ficha del usuario en turno.
                    </p>
                    <textarea name="default_signature" id="input_default_signature" rows="5"
                              class="w-full bg-[#040e1f] border border-obsidian-border text-cyan-200 text-xs font-mono rounded-lg p-3 focus:outline-none focus:border-cyan-400 transition leading-relaxed custom-scroll"
                              placeholder="Personal de  ATIT:&#10;...&#10;📱Tlf: ...">{{ old('default_signature', $currentTemplate->default_signature) }}</textarea>
                </div>

                <!-- LEMA DE CIERRE -->
                <div class="space-y-1.5">
                    <label class="text-xs font-mono font-bold text-gray-300 uppercase flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-xs text-cyan-400">campaign</span>
                        Lema Institucional de Cierre
                    </label>
                    <input type="text" name="slogan" id="input_slogan"
                           value="{{ old('slogan', $currentTemplate->slogan) }}"
                           class="w-full bg-[#030914] border border-obsidian-border text-white text-xs font-mono rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-cyan-400 transition"
                           placeholder="<b>⚡️ATIT Somos la Voz, Comando y Control de SEN, Nadie se Cansa ⚡️</b>"/>
                </div>

                <!-- BOTONES DE ACCIÓN Y AUDITORÍA -->
                <div class="pt-3 border-t border-obsidian-border/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="text-[11px] font-mono text-obsidian-muted">
                        Última modificación: <span class="text-white">{{ $currentTemplate->updated_at ? $currentTemplate->updated_at->format('d/m/Y H:i') : 'N/A' }}</span>
                        por <span class="text-cyan-300 font-semibold">{{ $currentTemplate->updated_by ?: 'Sistema' }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-obsidian-cyan text-black font-mono font-bold text-xs uppercase tracking-wide flex items-center gap-2 hover:bg-cyan-300 transition shadow-lg shadow-cyan-950/40 cursor-pointer">
                            <span class="material-symbols-outlined text-base">save</span>
                            <span>Guardar Cambios</span>
                        </button>
                    </div>
                </div>
            </form>

            <!-- FORMULARIO SEPARADO PARA RESTAURAR VALORES DE FÁBRICA -->
            <div class="pt-2 flex justify-end">
                <form action="{{ route('admin.bot.templates.reset', $currentTemplate) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas restaurar la plantilla \'{{ $currentTemplate->title }}\' a sus valores originales de fábrica?');">
                    @csrf
                    <button type="submit" class="text-xs font-mono text-amber-400/80 hover:text-amber-300 underline flex items-center gap-1 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">restart_alt</span>
                        <span>Restaurar Valores de Fábrica</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- COLUMNA DERECHA: PREVISUALIZACIÓN EN VIVO TIPO TELEGRAM (5 COLS) -->
        <div class="lg:col-span-5 space-y-3 lg:sticky lg:top-20">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-400 text-lg">smartphone</span>
                    <h3 class="text-xs font-bold text-white uppercase font-mono tracking-wide">
                        Previsualización en Vivo (Telegram)
                    </h3>
                </div>
                <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-blue-950/70 text-blue-300 border border-blue-500/30">
                    HTML Mode
                </span>
            </div>

            <!-- TELÉFONO / BURBUJA DE TELEGRAM -->
            <div class="rounded-2xl bg-[#0e1621] border border-[#242f3d] p-4 shadow-2xl space-y-3 font-sans">
                <!-- CABECERA DEL MENSAJE (INFO DEL BOT) -->
                <div class="flex items-center gap-3 pb-3 border-b border-[#1f2b38]">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-cyan-600 to-blue-600 flex items-center justify-center text-white font-bold text-sm shadow-md">
                        🤖
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm font-semibold text-white truncate">ATIT • Valle Seco Bot</span>
                            <span class="text-[9px] font-mono bg-blue-900/60 text-blue-300 px-1.5 py-0.2 rounded">BOT</span>
                        </div>
                        <p class="text-[11px] text-gray-400 truncate">@IA_ValleSeco_bot</p>
                    </div>
                </div>

                <!-- BURBUJA DE CHAT TELEGRAM -->
                <div class="bg-[#182533] rounded-2xl rounded-tl-sm p-4 border border-[#2b3a4a] text-[#f5f5f5] text-[13px] leading-relaxed shadow-md select-text" id="telegram-preview-bubble">
                    <div class="flex items-center gap-2 text-cyan-400 font-mono text-xs">
                        <span class="material-symbols-outlined text-sm animate-spin">sync</span>
                        <span>Cargando previsualización...</span>
                    </div>
                </div>

                <!-- PIE DE CHAT -->
                <div class="flex items-center justify-between text-[11px] text-gray-500 font-mono px-1">
                    <span>Transmisión Oficial ATIT</span>
                    <span>Hoy {{ date('h:i A') }} ✓✓</span>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<script>
let previewDebounce = null;

function updateLivePreview() {
    const header = document.getElementById('input_header_text') ? document.getElementById('input_header_text').value : '';
    const subHeader = document.getElementById('input_sub_header') ? document.getElementById('input_sub_header').value : '';
    const legend = document.getElementById('input_legend_text') ? document.getElementById('input_legend_text').value : '';
    const impact = document.getElementById('input_impact_statement') ? document.getElementById('input_impact_statement').value : '';
    const signature = document.getElementById('input_default_signature') ? document.getElementById('input_default_signature').value : '';
    const slogan = document.getElementById('input_slogan') ? document.getElementById('input_slogan').value : '';
    const key = '{{ $currentTemplate ? $currentTemplate->template_key : "servicios" }}';

    fetch('{{ route('admin.bot.templates.preview') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            template_key: key,
            header_text: header,
            sub_header: subHeader,
            legend_text: legend,
            impact_statement: impact,
            default_signature: signature,
            slogan: slogan
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const bubble = document.getElementById('telegram-preview-bubble');
            if (bubble) {
                bubble.innerHTML = data.rendered_html;
            }
        }
    })
    .catch(err => {
        console.error('Error fetching preview:', err);
    });
}

function debouncePreview() {
    clearTimeout(previewDebounce);
    previewDebounce = setTimeout(updateLivePreview, 250);
}

document.addEventListener('DOMContentLoaded', function() {
    const inputs = ['input_header_text', 'input_sub_header', 'input_legend_text', 'input_impact_statement', 'input_default_signature', 'input_slogan'];
    inputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', debouncePreview);
        }
    });

    updateLivePreview();
});
</script>
@endsection
