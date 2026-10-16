@if(auth()->check() && (auth()->user()->isAdmin() || auth()->user()->hasPermission('ai.chat') || in_array(auth()->user()->role, ['operator', 'operador'], true)))
<div id="ai-assistant-root" class="font-sans antialiased">
    <!-- BOTÓN FLOTANTE ANIMADO (ORB ATIT IA) -->
    <div id="ai-widget-orb-container" class="fixed bottom-6 right-6 z-[9999] flex items-center gap-3">
        <!-- Indicador de bienvenida / llamada a la acción flotante (se oculta al abrir) -->
        <div id="ai-widget-badge-cta" class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-obsidian-surface/90 border border-cyan-500/40 backdrop-blur-md shadow-lg shadow-cyan-950/40 text-[11px] font-mono text-cyan-300 animate-bounce duration-1000 cursor-pointer" onclick="toggleAiAssistantDrawer()">
            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>Asistente ATIT</span>
            <span class="text-[9px] text-cyan-400/70 font-sans">| IA</span>
        </div>

        <button type="button" 
                id="ai-widget-orb-btn" 
                onclick="toggleAiAssistantDrawer()" 
                title="Asistente Virtual Técnico ATIT - Consultas de Red & Inventario" 
                class="relative flex items-center justify-center w-14 h-14 rounded-full bg-gradient-to-br from-cyan-600 via-cyan-800 to-obsidian-base border-2 border-cyan-400/80 text-white shadow-xl shadow-cyan-500/30 hover:shadow-cyan-400/50 hover:scale-110 active:scale-95 transition-all duration-300 group cursor-pointer focus:outline-none">
            <!-- Anillo de radar pulsante de fondo -->
            <span class="absolute inset-0 rounded-full border border-cyan-400/60 animate-ping opacity-60 pointer-events-none"></span>
            <span class="absolute -inset-1 rounded-full bg-cyan-500/20 blur-sm group-hover:bg-cyan-400/30 transition pointer-events-none"></span>

            <!-- Icono Central Inteligente -->
            <span id="ai-widget-icon" class="material-symbols-outlined text-2xl text-cyan-200 group-hover:text-white group-hover:rotate-12 transition-transform duration-300">
                smart_toy
            </span>

            <!-- LED de Estado En Línea -->
            <span class="absolute top-0 right-0 flex h-3.5 w-3.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500 border-2 border-obsidian-base"></span>
            </span>
        </button>
    </div>

    <!-- PANEL FLOTANTE DE CHAT (DRAWER / OFFCANVAS) -->
    <div id="ai-assistant-drawer" 
         class="fixed bottom-24 right-4 sm:right-6 z-[10000] w-[94vw] sm:w-[440px] h-[580px] max-h-[82vh] bg-[#050e1b]/95 border border-cyan-500/40 rounded-2xl shadow-2xl shadow-cyan-950/70 backdrop-blur-xl flex flex-col overflow-hidden transition-all duration-300 ease-out translate-y-8 opacity-0 pointer-events-none">
        
        <!-- CABECERA DEL ASISTENTE -->
        <div class="px-4 py-3 bg-gradient-to-r from-obsidian-card via-[#061933] to-obsidian-card border-b border-cyan-500/30 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-cyan-500/20 border border-cyan-400/50 flex items-center justify-center text-cyan-300 shadow-inner">
                    <span class="material-symbols-outlined text-lg">neurology</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-xs text-white tracking-wide">Monitor Valle Seco</span>
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    </div>
                    <span class="text-[10px] text-cyan-300/80 font-mono">Especialista ATIT • En Línea</span>
                </div>
            </div>

            <div class="flex items-center gap-1.5">
                <button type="button" 
                        onclick="resetAiChatHistory()" 
                        title="Reiniciar conversación y limpiar memoria" 
                        class="w-7 h-7 rounded-lg bg-obsidian-card/80 border border-obsidian-border text-obsidian-muted hover:text-cyan-300 hover:border-cyan-500/50 flex items-center justify-center transition">
                    <span class="material-symbols-outlined text-sm">restart_alt</span>
                </button>
                <button type="button" 
                        onclick="toggleAiAssistantDrawer()" 
                        title="Cerrar panel" 
                        class="w-7 h-7 rounded-lg bg-obsidian-card/80 border border-obsidian-border text-obsidian-muted hover:text-red-400 hover:border-red-500/50 flex items-center justify-center transition">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
        </div>

        <!-- CHIPS DE CONSULTA RÁPIDA -->
        <div class="px-3 py-2 bg-[#030912]/80 border-b border-obsidian-border/50 flex items-center gap-1.5 overflow-x-auto scrollbar-none shrink-0 text-[10px] font-mono">
            <button type="button" onclick="sendQuickPrompt('¿Cuáles son las direcciones IP de los proxies y sus nombres exactos?')" class="px-2.5 py-1 rounded-full bg-cyan-950/40 border border-cyan-500/30 text-cyan-300 hover:bg-cyan-500 hover:text-black transition shrink-0">
                🌐 IPs Proxies
            </button>
            <button type="button" onclick="sendQuickPrompt('¿Cuántos equipos existen en la sede Paseo Mariño y cuál es su desglose?')" class="px-2.5 py-1 rounded-full bg-cyan-950/40 border border-cyan-500/30 text-cyan-300 hover:bg-cyan-500 hover:text-black transition shrink-0">
                🏢 Sede Paseo Mariño
            </button>
            <button type="button" onclick="sendQuickPrompt('¿Cómo configurar una subinterfaz VLAN con encapsulación dot1Q en Cisco?')" class="px-2.5 py-1 rounded-full bg-cyan-950/40 border border-cyan-500/30 text-cyan-300 hover:bg-cyan-500 hover:text-black transition shrink-0">
                ⚙️ Configurar Cisco
            </button>
            <button type="button" onclick="sendQuickPrompt('Dame un resumen del censo de infraestructura y sedes activas')" class="px-2.5 py-1 rounded-full bg-cyan-950/40 border border-cyan-500/30 text-cyan-300 hover:bg-cyan-500 hover:text-black transition shrink-0">
                📊 Censo Global
            </button>
        </div>

        <!-- CONTENEDOR DE MENSAJES -->
        <div id="ai-drawer-messages" class="flex-1 overflow-y-auto p-4 space-y-3 font-sans text-xs scrollbar-thin scrollbar-thumb-cyan-500/20">
            <!-- Mensaje de bienvenida inicial -->
            <div class="flex items-start gap-2.5 max-w-[90%]">
                <div class="w-7 h-7 rounded-lg bg-cyan-500/20 border border-cyan-400/40 flex items-center justify-center text-cyan-300 shrink-0">
                    <span class="material-symbols-outlined text-sm">smart_toy</span>
                </div>
                <div class="p-3 rounded-2xl rounded-tl-none bg-obsidian-card border border-cyan-500/30 text-slate-200 text-xs shadow-sm leading-relaxed space-y-1.5">
                    <p class="font-bold text-cyan-300">¡Saludos! Soy el Asistente Técnico de ATIT.</p>
                    <p>Estoy conectado en vivo a MariaDB y a la telemetría del NOC. Puedes consultarme:</p>
                    <ul class="list-disc list-inside text-[11px] text-slate-300 space-y-0.5">
                        <li>IPs y estados de proxies o sedes.</li>
                        <li>Censo y conteo de equipos por sede.</li>
                        <li>Búsqueda de equipos por serial o dirección IP.</li>
                        <li>Soporte técnico avanzado en routers/switches Cisco, Linux y redes.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- INDICADOR DE CARGA / TYPING -->
        <div id="ai-drawer-typing" class="hidden px-4 py-2 text-[11px] font-mono text-cyan-400 flex items-center gap-2 bg-[#040c17] border-t border-cyan-500/20 shrink-0">
            <span class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
            <span id="ai-typing-text">Consultando telemetría y base de datos...</span>
        </div>

        <!-- FORMULARIO DE ENVÍO -->
        <div class="p-3 bg-obsidian-card/90 border-t border-cyan-500/30 shrink-0">
            <form id="ai-drawer-form" onsubmit="submitAiDrawerMessage(event)" class="flex items-center gap-2">
                <input type="text" 
                       id="ai-drawer-input" 
                       placeholder="Pregunta sobre la red, inventario o configuración técnica..." 
                       autocomplete="off" 
                       class="flex-1 bg-[#030913] border border-obsidian-border rounded-xl px-3.5 py-2 text-xs text-white placeholder-obsidian-muted focus:outline-none focus:border-cyan-400 font-mono transition">
                <button type="submit" 
                        id="ai-drawer-send-btn" 
                        class="w-9 h-9 rounded-xl bg-cyan-500 text-black flex items-center justify-center hover:bg-cyan-300 hover:scale-105 active:scale-95 transition shadow-lg shadow-cyan-500/30 shrink-0 cursor-pointer">
                    <span class="material-symbols-outlined text-lg">send</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    let aiChatHistory = [];
    let isAiDrawerOpen = false;

    function toggleAiAssistantDrawer() {
        const drawer = document.getElementById('ai-assistant-drawer');
        const badge = document.getElementById('ai-widget-badge-cta');
        const icon = document.getElementById('ai-widget-icon');
        if (!drawer) return;

        isAiDrawerOpen = !isAiDrawerOpen;

        if (isAiDrawerOpen) {
            drawer.classList.remove('translate-y-8', 'opacity-0', 'pointer-events-none');
            drawer.classList.add('translate-y-0', 'opacity-100', 'pointer-events-auto');
            if (badge) badge.classList.add('hidden');
            if (icon) icon.textContent = 'close';
            setTimeout(() => {
                const input = document.getElementById('ai-drawer-input');
                if (input) input.focus();
            }, 150);
        } else {
            drawer.classList.add('translate-y-8', 'opacity-0', 'pointer-events-none');
            drawer.classList.remove('translate-y-0', 'opacity-100', 'pointer-events-auto');
            if (badge) badge.classList.remove('hidden');
            if (icon) icon.textContent = 'smart_toy';
        }
    }

    function sendQuickPrompt(promptText) {
        const input = document.getElementById('ai-drawer-input');
        if (input) {
            input.value = promptText;
            submitAiDrawerMessage(new Event('submit'));
        }
    }

    function resetAiChatHistory() {
        aiChatHistory = [];
        const container = document.getElementById('ai-drawer-messages');
        if (container) {
            container.innerHTML = `
                <div class="flex items-start gap-2.5 max-w-[90%]">
                    <div class="w-7 h-7 rounded-lg bg-cyan-500/20 border border-cyan-400/40 flex items-center justify-center text-cyan-300 shrink-0">
                        <span class="material-symbols-outlined text-sm">smart_toy</span>
                    </div>
                    <div class="p-3 rounded-2xl rounded-tl-none bg-obsidian-card border border-cyan-500/30 text-slate-200 text-xs shadow-sm leading-relaxed space-y-1.5">
                        <p class="font-bold text-cyan-300">Memoria reiniciada.</p>
                        <p>¿En qué puedo ayudarte ahora con respecto a la red, inventario o soporte técnico?</p>
                    </div>
                </div>
            `;
        }
    }

    async function submitAiDrawerMessage(e) {
        if (e && e.preventDefault) e.preventDefault();
        const input = document.getElementById('ai-drawer-input');
        const sendBtn = document.getElementById('ai-drawer-send-btn');
        const container = document.getElementById('ai-drawer-messages');
        const typing = document.getElementById('ai-drawer-typing');

        if (!input || !container) return;
        const messageText = input.value.trim();
        if (!messageText) return;

        // Renderizar mensaje del usuario
        appendUserMessage(messageText);
        input.value = '';
        input.disabled = true;
        if (sendBtn) sendBtn.disabled = true;
        if (typing) typing.classList.remove('hidden');
        scrollToBottom();

        try {
            const tokenEl = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

            const response = await fetch("{{ route('ai.chat') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: JSON.stringify({
                    message: messageText,
                    history: aiChatHistory
                })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                appendAssistantMessage(data.reply);
                aiChatHistory.push({ role: 'user', content: messageText });
                aiChatHistory.push({ role: 'assistant', content: data.reply });
                if (aiChatHistory.length > 10) {
                    aiChatHistory = aiChatHistory.slice(-10);
                }
            } else {
                const errMsg = data.error || 'Ocurrió un error al procesar tu consulta con el motor de IA.';
                appendAssistantMessage(`⚠️ **Error:** ${errMsg}`);
            }
        } catch (err) {
            appendAssistantMessage(`⚠️ **Error de conexión:** No se pudo comunicar con el servicio de IA. Verifica tu conexión.`);
        } finally {
            if (typing) typing.classList.add('hidden');
            input.disabled = false;
            if (sendBtn) sendBtn.disabled = false;
            input.focus();
            scrollToBottom();
        }
    }

    function appendUserMessage(text) {
        const container = document.getElementById('ai-drawer-messages');
        if (!container) return;
        const msgDiv = document.createElement('div');
        msgDiv.className = 'flex justify-end';
        msgDiv.innerHTML = `
            <div class="max-w-[85%] p-3 rounded-2xl rounded-tr-none bg-gradient-to-r from-cyan-600 to-cyan-700 text-white text-xs shadow-md font-sans">
                ${escapeHtml(text)}
            </div>
        `;
        container.appendChild(msgDiv);
    }

    function appendAssistantMessage(text) {
        const container = document.getElementById('ai-drawer-messages');
        if (!container) return;
        const msgDiv = document.createElement('div');
        msgDiv.className = 'flex items-start gap-2.5 max-w-[92%]';
        
        // Formatear Markdown básico
        const formatted = formatMarkdownText(text);

        msgDiv.innerHTML = `
            <div class="w-7 h-7 rounded-lg bg-cyan-500/20 border border-cyan-400/40 flex items-center justify-center text-cyan-300 shrink-0 mt-0.5">
                <span class="material-symbols-outlined text-sm">smart_toy</span>
            </div>
            <div class="p-3 rounded-2xl rounded-tl-none bg-obsidian-card border border-cyan-500/30 text-slate-200 text-xs shadow-sm leading-relaxed space-y-1.5 overflow-x-auto">
                ${formatted}
            </div>
        `;
        container.appendChild(msgDiv);
    }

    function scrollToBottom() {
        const container = document.getElementById('ai-drawer-messages');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function formatMarkdownText(raw) {
        if (!raw) return '';
        let escaped = escapeHtml(raw);

        // Bloques de código ```lenguaje ... ```
        escaped = escaped.replace(/```([a-zA-Z0-9_\-]*)\n([\s\S]*?)```/g, function(match, lang, code) {
            return `<pre class="my-2 p-2.5 rounded-lg bg-[#030913] border border-cyan-500/30 font-mono text-[11px] text-cyan-300 overflow-x-auto"><code>${code}</code></pre>`;
        });

        // Código inline `código`
        escaped = escaped.replace(/`([^`]+)`/g, '<code class="px-1.5 py-0.5 rounded bg-[#030913] text-cyan-300 font-mono text-[11px] border border-cyan-500/30">$1</code>');

        // Negrita **texto**
        escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong class="font-bold text-cyan-200">$1</strong>');

        // Saltos de línea
        escaped = escaped.replace(/\n/g, '<br>');

        return escaped;
    }
</script>
@endif
