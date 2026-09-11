@extends('layouts.admin')

@section('title', 'Comandos y Parámetros del Bot - ATIT Valle Seco')

@section('content')
<div class="space-y-6">
    <!-- ENCABEZADO -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-obsidian-card p-6 rounded-2xl border border-obsidian-border shadow-xl">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 rounded-xl bg-obsidian-cyan/10 text-obsidian-cyan border border-obsidian-cyan/20">
                    <span class="material-symbols-outlined text-2xl">terminal</span>
                </span>
                <div>
                    <h1 class="text-xl md:text-2xl font-black tracking-wide text-white uppercase">Comandos y Mensajes del Bot</h1>
                    <p class="text-xs text-obsidian-muted mt-0.5">Gestión de comandos dinámicos, textos de ayuda y políticas operativas en MariaDB (SSOT)</p>
                </div>
            </div>
        </div>

        <!-- INDICADOR SSOT -->
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            MariaDB Fuente Única de Verdad (SSOT)
        </div>
    </div>

    <!-- TABS DE NAVEGACIÓN -->
    <div class="flex items-center gap-2 border-b border-obsidian-border pb-2">
        <a href="{{ route('admin.bot.commands.index', ['tab' => 'commands']) }}" 
           class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition {{ $activeTab === 'commands' ? 'bg-obsidian-cyan text-black shadow-lg shadow-obsidian-cyan/20' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
            <span class="material-symbols-outlined text-sm">code</span>
            Comandos Activos ({{ $commands->count() }})
        </a>
        <a href="{{ route('admin.bot.commands.index', ['tab' => 'settings']) }}" 
           class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition {{ $activeTab === 'settings' ? 'bg-obsidian-cyan text-black shadow-lg shadow-obsidian-cyan/20' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
            <span class="material-symbols-outlined text-sm">settings</span>
            Mensajes del Sistema & Políticas
        </a>
    </div>

    @if(session('success'))
    <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">
        <span class="material-symbols-outlined">check_circle</span>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    @if($errors->any())
    <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm space-y-1">
        @foreach($errors->all() as $error)
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">error</span>
            <span>{{ $error }}</span>
        </div>
        @endforeach
    </div>
    @endif

    <!-- CONTENIDO TAB 1: COMANDOS DISPONIBLES -->
    @if($activeTab === 'commands')
    <div class="space-y-4">
        <!-- FILTROS Y BÚSQUEDA -->
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 bg-obsidian-card p-4 rounded-xl border border-obsidian-border">
            <!-- Categorías -->
            <div class="flex flex-wrap items-center gap-1.5">
                <a href="{{ route('admin.bot.commands.index', ['tab' => 'commands', 'category' => 'all']) }}" 
                   class="px-3 py-1 rounded-md text-xs font-medium transition {{ $category === 'all' ? 'bg-obsidian-panel text-obsidian-cyan border border-obsidian-cyan/30' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel/50' }}">
                    Todos
                </a>
                @foreach($categories as $cat)
                <a href="{{ route('admin.bot.commands.index', ['tab' => 'commands', 'category' => $cat]) }}" 
                   class="px-3 py-1 rounded-md text-xs font-medium transition {{ $category === $cat ? 'bg-obsidian-panel text-obsidian-cyan border border-obsidian-cyan/30' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel/50' }}">
                    {{ $cat }}
                </a>
                @endforeach
            </div>

            <!-- Búsqueda -->
            <form action="{{ route('admin.bot.commands.index') }}" method="GET" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="commands">
                <input type="hidden" name="category" value="{{ $category }}">
                <div class="relative">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Buscar comando..." 
                           class="w-48 md:w-64 bg-obsidian-panel border border-obsidian-border rounded-lg pl-9 pr-3 py-1.5 text-xs text-white focus:border-obsidian-cyan outline-none transition">
                    <span class="material-symbols-outlined absolute left-2.5 top-2 text-obsidian-muted text-sm">search</span>
                </div>
                @if(!empty($search))
                <a href="{{ route('admin.bot.commands.index', ['tab' => 'commands', 'category' => $category]) }}" class="p-1.5 text-obsidian-muted hover:text-white" title="Limpiar">
                    <span class="material-symbols-outlined text-sm">close</span>
                </a>
                @endif
            </form>
        </div>

        <!-- TABLA DE COMANDOS -->
        <div class="bg-obsidian-card rounded-xl border border-obsidian-border overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-obsidian-border bg-obsidian-panel/50 text-[10px] uppercase tracking-wider text-obsidian-muted">
                            <th class="py-3 px-4">Comando</th>
                            <th class="py-3 px-4">Categoría</th>
                            <th class="py-3 px-4">Nivel Acceso</th>
                            <th class="py-3 px-4">Descripción Oficial</th>
                            <th class="py-3 px-4 text-center">Estado</th>
                            <th class="py-3 px-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-obsidian-border/50 text-xs">
                        @forelse($commands as $cmd)
                        <tr class="hover:bg-obsidian-panel/30 transition">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-obsidian-cyan bg-obsidian-panel px-2 py-1 rounded border border-obsidian-border">/{{ $cmd->command }}</span>
                                    @if($cmd->title)
                                    <span class="text-white font-medium">{{ $cmd->title }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-obsidian-panel text-obsidian-muted border border-obsidian-border">
                                    {{ $cmd->category }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                @if($cmd->access_level === 'owner')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-500/10 text-red-400 border border-red-500/20">
                                    Owner
                                </span>
                                @elseif($cmd->access_level === 'admin')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                    Admin
                                </span>
                                @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    Operador
                                </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-obsidian-muted max-w-xs truncate" title="{{ $cmd->description }}">
                                {{ $cmd->description }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <form action="{{ route('admin.bot.commands.toggle', $cmd->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 rounded text-[10px] font-bold transition {{ $cmd->is_active ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/30' : 'bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30' }}">
                                        {{ $cmd->is_active ? 'ACTIVO' : 'PAUSADO' }}
                                    </button>
                                </form>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" 
                                            onclick="openHelpModal('{{ $cmd->command }}', '{{ addslashes($cmd->title ?? $cmd->command) }}', '{{ base64_encode($cmd->help_text ?? '') }}')"
                                            class="p-1.5 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white hover:bg-obsidian-border transition" 
                                            title="Ver Ayuda Telegram">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                    </button>
                                    <button type="button" 
                                            onclick="openEditModal({{ json_encode($cmd) }})"
                                            class="p-1.5 rounded-lg bg-obsidian-cyan/10 text-obsidian-cyan hover:bg-obsidian-cyan/20 transition" 
                                            title="Editar Comando">
                                        <span class="material-symbols-outlined text-sm">edit</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-obsidian-muted text-xs">
                                No se encontraron comandos que coincidan con el criterio de búsqueda.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- CONTENIDO TAB 2: MENSAJES DEL SISTEMA & POLÍTICAS -->
    @if($activeTab === 'settings')
    <div class="bg-obsidian-card p-6 rounded-xl border border-obsidian-border shadow-xl">
        <form action="{{ route('admin.bot.settings.update') }}" method="POST" class="space-y-6">
            @csrf

            <div class="border-b border-obsidian-border pb-4">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Políticas Globales de Ejecución</h3>
                <p class="text-xs text-obsidian-muted mt-0.5">Control de acceso maestro del bot para emergencias y mantenimiento</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <label class="flex items-start gap-3 p-4 rounded-xl bg-obsidian-panel border border-obsidian-border cursor-pointer hover:border-obsidian-cyan/40 transition">
                        <input type="checkbox" name="commands_enabled" value="1" {{ ($settings['commands_enabled']->setting_value ?? '1') == '1' ? 'checked' : '' }} class="mt-1 rounded bg-obsidian-card border-obsidian-border text-obsidian-cyan focus:ring-0">
                        <div>
                            <span class="text-xs font-bold text-white block">Recepción Global de Comandos</span>
                            <span class="text-[11px] text-obsidian-muted leading-tight block mt-0.5">Si se desactiva, el bot rechazará todas las solicitudes de comando temporalmente.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-4 rounded-xl bg-obsidian-panel border border-obsidian-border cursor-pointer hover:border-obsidian-cyan/40 transition">
                        <input type="checkbox" name="commands_locked_for_users" value="1" {{ ($settings['commands_locked_for_users']->setting_value ?? '0') == '1' ? 'checked' : '' }} class="mt-1 rounded bg-obsidian-card border-obsidian-border text-obsidian-cyan focus:ring-0">
                        <div>
                            <span class="text-xs font-bold text-white block">Bloqueo a No Administradores</span>
                            <span class="text-[11px] text-obsidian-muted leading-tight block mt-0.5">Limita los comandos únicamente a usuarios autorizados con rol de Administrador u Owner.</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Mensajería Interactiva del Bot</h3>

                <!-- start_header -->
                <div>
                    <label class="block text-xs font-bold text-white mb-1.5">
                        Encabezado de Bienvenida (<code class="text-obsidian-cyan">/start</code>)
                    </label>
                    <textarea name="start_header" rows="6" 
                              class="w-full bg-obsidian-panel border border-obsidian-border rounded-xl p-3 text-xs font-mono text-white focus:border-obsidian-cyan outline-none transition">{{ old('start_header', $settings['start_header']->setting_value ?? '') }}</textarea>
                    <span class="text-[10px] text-obsidian-muted">Soporta formato HTML de Telegram (&lt;b&gt;, &lt;i&gt;, &lt;code&gt;).</span>
                </div>

                <!-- unknown_command -->
                <div>
                    <label class="block text-xs font-bold text-white mb-1.5">
                        Aviso de Comando Desconocido
                    </label>
                    <input type="text" name="unknown_command" value="{{ old('unknown_command', $settings['unknown_command']->setting_value ?? '') }}"
                           class="w-full bg-obsidian-panel border border-obsidian-border rounded-xl p-3 text-xs text-white focus:border-obsidian-cyan outline-none transition">
                </div>

                <!-- help_general -->
                <div>
                    <label class="block text-xs font-bold text-white mb-1.5">
                        Encabezado General de Ayuda (<code class="text-obsidian-cyan">/help</code>)
                    </label>
                    <textarea name="help_general" rows="3" 
                              class="w-full bg-obsidian-panel border border-obsidian-border rounded-xl p-3 text-xs font-mono text-white focus:border-obsidian-cyan outline-none transition">{{ old('help_general', $settings['help_general']->setting_value ?? '') }}</textarea>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-obsidian-border">
                <button type="submit" class="flex items-center gap-2 px-6 py-2.5 rounded-xl bg-obsidian-cyan text-black font-bold text-xs uppercase tracking-wider hover:bg-obsidian-cyan/90 transition shadow-lg shadow-obsidian-cyan/20">
                    <span class="material-symbols-outlined text-sm">save</span>
                    Guardar Parámetros y Mensajes
                </button>
            </div>
        </form>
    </div>
    @endif
</div>

<!-- MODAL DE EDICIÓN DE COMANDO -->
<div id="editCommandModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm hidden">
    <div class="bg-obsidian-card w-full max-w-xl p-6 rounded-2xl border border-obsidian-border shadow-2xl relative max-h-[90vh] overflow-y-auto">
        <button type="button" onclick="closeEditModal()" class="absolute top-4 right-4 text-obsidian-muted hover:text-white">
            <span class="material-symbols-outlined">close</span>
        </button>

        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-obsidian-cyan">edit_square</span>
            <h3 class="text-base font-bold text-white" id="modalCommandTitle">Editar Comando</h3>
        </div>

        <form id="editCommandForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-obsidian-muted mb-1">Título Descriptivo</label>
                    <input type="text" name="title" id="cmdTitle" class="w-full bg-obsidian-panel border border-obsidian-border rounded-xl px-3 py-2 text-xs text-white focus:border-obsidian-cyan outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-obsidian-muted mb-1">Categoría</label>
                    <select name="category" id="cmdCategory" class="w-full bg-obsidian-panel border border-obsidian-border rounded-xl px-3 py-2 text-xs text-white focus:border-obsidian-cyan outline-none">
                        @foreach($categories as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                        <option value="General">General</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-obsidian-muted mb-1">Nivel de Acceso</label>
                    <select name="access_level" id="cmdAccess" class="w-full bg-obsidian-panel border border-obsidian-border rounded-xl px-3 py-2 text-xs text-white focus:border-obsidian-cyan outline-none">
                        <option value="all">Público / Operador (Todos)</option>
                        <option value="admin">Administrador</option>
                        <option value="owner">Propietario (Owner)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-obsidian-muted mb-1">Orden de Presentación</label>
                    <input type="number" name="sort_order" id="cmdOrder" class="w-full bg-obsidian-panel border border-obsidian-border rounded-xl px-3 py-2 text-xs text-white focus:border-obsidian-cyan outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-obsidian-muted mb-1">Descripción Breve (1 Línea)</label>
                <input type="text" name="description" id="cmdDesc" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-xl px-3 py-2 text-xs text-white focus:border-obsidian-cyan outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-obsidian-muted mb-1">Texto de Ayuda Telegram (<code class="text-obsidian-cyan">/help [comando]</code>)</label>
                <textarea name="help_text" id="cmdHelp" rows="6" class="w-full bg-obsidian-panel border border-obsidian-border rounded-xl p-3 text-xs font-mono text-white focus:border-obsidian-cyan outline-none"></textarea>
                <span class="text-[10px] text-obsidian-muted">Soporta etiquetas HTML (&lt;b&gt;, &lt;code&gt;, &lt;i&gt;).</span>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-obsidian-border">
                <label class="flex items-center gap-2 text-xs text-white cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" id="cmdActive" class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan">
                    Comando Activo
                </label>
                <div class="flex gap-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-xl bg-obsidian-panel text-obsidian-muted hover:text-white text-xs font-bold transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-obsidian-cyan text-black text-xs font-bold uppercase tracking-wider hover:bg-obsidian-cyan/90 transition shadow-lg shadow-obsidian-cyan/20">Guardar Cambios</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE PREVISUALIZACIÓN TELEGRAM -->
<div id="helpPreviewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm hidden">
    <div class="bg-[#182533] w-full max-w-md p-6 rounded-2xl border border-obsidian-border shadow-2xl relative text-[#e4ecf2]">
        <button type="button" onclick="closeHelpModal()" class="absolute top-4 right-4 text-obsidian-muted hover:text-white">
            <span class="material-symbols-outlined">close</span>
        </button>

        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-white/10">
            <span class="material-symbols-outlined text-[#5288c1]">chat</span>
            <div>
                <h4 class="text-xs font-bold text-white" id="previewBotName">Monitor Valle Seco</h4>
                <span class="text-[10px] text-[#7d95ab]">bot</span>
            </div>
        </div>

        <div class="bg-[#2b5278] p-4 rounded-2xl rounded-tl-sm text-xs leading-relaxed font-sans shadow-md" id="previewContent">
            <!-- Inyectado vía JS -->
        </div>

        <div class="mt-4 flex justify-end">
            <button type="button" onclick="closeHelpModal()" class="px-4 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-semibold text-white transition">Cerrar</button>
        </div>
    </div>
</div>

<script>
function openEditModal(cmd) {
    document.getElementById('modalCommandTitle').innerText = 'Editar Comando /' + cmd.command;
    document.getElementById('cmdTitle').value = cmd.title || '';
    document.getElementById('cmdCategory').value = cmd.category || 'General';
    document.getElementById('cmdAccess').value = cmd.access_level || 'all';
    document.getElementById('cmdOrder').value = cmd.sort_order || 0;
    document.getElementById('cmdDesc').value = cmd.description || '';
    document.getElementById('cmdHelp').value = cmd.help_text || '';
    document.getElementById('cmdActive').checked = Boolean(cmd.is_active);

    const form = document.getElementById('editCommandForm');
    form.action = '/admin/bot/commands/' + cmd.id;

    document.getElementById('editCommandModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editCommandModal').classList.add('hidden');
}

function openHelpModal(command, title, encodedHelp) {
    let raw = '';
    try {
        raw = decodeURIComponent(escape(atob(encodedHelp)));
    } catch(e) {
        raw = atob(encodedHelp);
    }

    // Parse simple HTML tags for preview
    let formatted = raw.replace(/\n/g, '<br>');
    document.getElementById('previewContent').innerHTML = formatted || '<i>Sin texto de ayuda configurado.</i>';
    document.getElementById('helpPreviewModal').classList.remove('hidden');
}

function closeHelpModal() {
    document.getElementById('helpPreviewModal').classList.add('hidden');
}
</script>
@endsection
