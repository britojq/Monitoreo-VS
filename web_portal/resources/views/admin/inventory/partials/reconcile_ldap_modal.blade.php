{{-- Modal de Conciliación de Responsable con Directorio Activo (LDAP) --}}
<div id="reconcileLdapModal" onclick="if(event.target === this) closeReconcileLdapModal()" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm hidden" style="backdrop-filter: blur(4px);">
    <div class="relative w-full max-w-2xl bg-[#0a101d] border border-cyan-500/30 rounded-2xl shadow-2xl shadow-cyan-950/40 flex flex-col max-h-[90vh] overflow-hidden animate-fadeIn">
        
        {{-- Cabecera del Modal --}}
        <div class="flex items-center justify-between p-4 sm:p-5 border-b border-obsidian-border bg-[#070e1a] shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                    <span class="material-symbols-outlined text-lg">manage_accounts</span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white tracking-wide">Conciliación con Directorio Activo (LDAP)</h3>
                    <p class="text-[11px] font-mono text-obsidian-muted">Actualice la identidad oficial del funcionario sin alterar la planilla física</p>
                </div>
            </div>
            <button type="button" onclick="closeReconcileLdapModal()" class="w-8 h-8 rounded-lg flex items-center justify-center text-obsidian-muted hover:text-white hover:bg-slate-800/60 transition" title="Cerrar modal">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        {{-- Cuerpo del Modal --}}
        <div class="p-4 sm:p-5 space-y-4 overflow-y-auto flex-1 custom-scroll">
            
            {{-- Identificación del Equipo --}}
            <div class="p-3 rounded-xl bg-[#040b15] border border-obsidian-border flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-mono text-obsidian-muted uppercase tracking-wider block">Equipo Destino</span>
                    <span id="reconModalAssetTitle" class="text-xs font-mono font-bold text-cyan-400">--</span>
                </div>
                <div id="reconModalScanBadge" class="flex items-center gap-1.5 text-[11px] font-mono px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400">
                    <span class="material-symbols-outlined text-xs">verified</span>
                    <span>Soporte Digital Resguardado</span>
                </div>
            </div>

            {{-- Comparativa: Datos Actuales vs Datos LDAP --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {{-- Columna A: Dato Actual --}}
                <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                    <div class="flex items-center gap-1.5 mb-2 text-obsidian-muted">
                        <span class="material-symbols-outlined text-xs">person</span>
                        <span class="text-[10px] font-mono uppercase tracking-wider">Responsable Actual</span>
                    </div>
                    <div class="space-y-1">
                        <div class="text-xs font-bold text-slate-200" id="reconCurrentName">Sin asignar</div>
                        <div class="text-[11px] font-mono text-obsidian-muted" id="reconCurrentId">Cédula: --</div>
                        <div class="text-[11px] font-mono text-obsidian-muted" id="reconCurrentDept">Dpto: --</div>
                    </div>
                </div>

                {{-- Columna B: Dato LDAP Seleccionado --}}
                <div class="p-3 rounded-xl bg-cyan-950/20 border border-cyan-500/30">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-1.5 text-cyan-400">
                            <span class="material-symbols-outlined text-xs">badge</span>
                            <span class="text-[10px] font-mono uppercase tracking-wider font-bold">Oficial LDAP</span>
                        </div>
                        <span id="reconSelectedSource" class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-cyan-500/20 text-cyan-300 hidden">LDAP</span>
                    </div>
                    <div class="space-y-1" id="reconTargetPreview">
                        <div class="text-xs font-mono italic text-obsidian-muted">Seleccione un funcionario de la lista...</div>
                    </div>
                </div>
            </div>

            {{-- Buscador LDAP en Tiempo Real --}}
            <div>
                <label class="block text-xs font-mono text-obsidian-muted mb-1.5 flex items-center justify-between">
                    <span>Buscar Funcionario en Directorio Activo</span>
                    <span class="text-[10px] text-cyan-400/80">Escriba mínimo 2 caracteres</span>
                </label>
                <div class="relative">
                    <input type="text" 
                           id="reconLdapSearchInput" 
                           placeholder="Buscar por nombre, apellido, cédula o usuario de red..." 
                           class="w-full pl-9 pr-8 py-2 bg-[#040b15] border border-obsidian-border rounded-xl text-xs font-mono text-white placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none transition">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-obsidian-muted text-base">search</span>
                    <div id="reconSearchSpinner" class="absolute right-3 top-2.5 hidden">
                        <span class="material-symbols-outlined text-cyan-400 text-base animate-spin">progress_activity</span>
                    </div>
                </div>
            </div>

            {{-- Lista de Resultados LDAP --}}
            <div id="reconSearchResultsContainer" class="border border-obsidian-border rounded-xl bg-[#050e1c] max-h-48 overflow-y-auto custom-scroll divide-y divide-slate-800/60 hidden">
                {{-- Inyectado dinámicamente --}}
            </div>

            {{-- Aviso de Preservación de Planilla --}}
            <div class="p-3 rounded-xl bg-cyan-500/5 border border-cyan-500/20 flex items-start gap-2.5">
                <span class="material-symbols-outlined text-cyan-400 text-sm mt-0.5">info</span>
                <p class="text-[11px] font-mono text-slate-300 leading-relaxed">
                    <strong>Garantía de Integridad:</strong> Al conciliar, los datos se sincronizan directamente con MariaDB. La planilla física escaneada previamente registrada <span class="text-cyan-300">se mantendrá resguardada</span> sin necesidad de volver a digitalizar ni subir el documento.
                </p>
            </div>
        </div>

        {{-- Pie del Modal con Acciones --}}
        <div class="flex items-center justify-end gap-2.5 p-3.5 sm:p-4 bg-[#070e1a] border-t border-obsidian-border shrink-0">
            <button type="button" 
                    onclick="closeReconcileLdapModal()" 
                    class="px-3.5 py-1.5 rounded-xl border border-obsidian-border text-obsidian-muted hover:text-white font-mono text-xs transition cursor-pointer">
                Cancelar
            </button>
            <button type="button" 
                    id="reconConfirmBtn" 
                    disabled 
                    onclick="submitReconcileLdap()" 
                    class="px-4 py-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 disabled:opacity-40 disabled:hover:bg-cyan-600 disabled:cursor-not-allowed text-white font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-cyan-600/20 cursor-pointer">
                <span class="material-symbols-outlined text-sm">verified_user</span>
                <span>Confirmar y Conciliar</span>
            </button>
        </div>
    </div>
</div>

<script>
    let reconState = {
        assetId: null,
        assetName: '',
        selectedEmployee: null,
        searchTimeout: null,
    };

    function openReconcileLdapModal(assetId, assetName, custodianName, custodianId, custodianDept, hasScan = true) {
        reconState.assetId = assetId;
        reconState.assetName = assetName;
        reconState.selectedEmployee = null;

        document.getElementById('reconModalAssetTitle').textContent = '#' + assetId + ' — ' + (assetName || 'Dispositivo');
        document.getElementById('reconCurrentName').textContent = custodianName || 'Sin asignar';
        document.getElementById('reconCurrentId').textContent = 'Cédula: ' + (custodianId || 'N/A');
        document.getElementById('reconCurrentDept').textContent = 'Dpto: ' + (custodianDept || 'N/A');

        const scanBadge = document.getElementById('reconModalScanBadge');
        if (hasScan) {
            scanBadge.className = 'flex items-center gap-1.5 text-[11px] font-mono px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400';
            scanBadge.innerHTML = '<span class="material-symbols-outlined text-xs">verified</span><span>Soporte Digital Resguardado</span>';
        } else {
            scanBadge.className = 'flex items-center gap-1.5 text-[11px] font-mono px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400';
            scanBadge.innerHTML = '<span class="material-symbols-outlined text-xs">info</span><span>Sin Planilla Digital</span>';
        }

        // Reset inputs y resultados
        document.getElementById('reconLdapSearchInput').value = '';
        document.getElementById('reconSearchResultsContainer').innerHTML = '';
        document.getElementById('reconSearchResultsContainer').classList.add('hidden');
        document.getElementById('reconTargetPreview').innerHTML = '<div class="text-xs font-mono italic text-obsidian-muted">Seleccione un funcionario de la lista...</div>';
        document.getElementById('reconSelectedSource').classList.add('hidden');
        document.getElementById('reconConfirmBtn').disabled = true;

        document.getElementById('reconcileLdapModal').classList.remove('hidden');
        setTimeout(() => document.getElementById('reconLdapSearchInput').focus(), 100);
    }

    function closeReconcileLdapModal() {
        document.getElementById('reconcileLdapModal').classList.add('hidden');
        reconState.selectedEmployee = null;
    }

    // Listener de búsqueda con debouncing
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('reconLdapSearchInput');
        if (!input) return;

        input.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            clearTimeout(reconState.searchTimeout);

            if (query.length < 2) {
                document.getElementById('reconSearchResultsContainer').classList.add('hidden');
                document.getElementById('reconSearchResultsContainer').innerHTML = '';
                return;
            }

            document.getElementById('reconSearchSpinner').classList.remove('hidden');

            reconState.searchTimeout = setTimeout(async function() {
                try {
                    const response = await fetch('/admin/inventory/custodian/search-ldap?q=' + encodeURIComponent(query), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const res = await response.json();
                    renderReconSearchResults(res.data || []);
                } catch (err) {
                    console.error('Error buscando en LDAP:', err);
                } finally {
                    document.getElementById('reconSearchSpinner').classList.add('hidden');
                }
            }, 300);
        });

        // Escuchar Escape para cerrar modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('reconcileLdapModal');
                if (modal && !modal.classList.contains('hidden')) {
                    closeReconcileLdapModal();
                }
            }
        });
    });

    function renderReconSearchResults(employees) {
        const container = document.getElementById('reconSearchResultsContainer');
        container.innerHTML = '';

        if (!employees || employees.length === 0) {
            container.innerHTML = '<div class="p-3 text-center text-xs font-mono text-obsidian-muted">No se encontraron funcionarios en el Directorio Activo con ese criterio.</div>';
            container.classList.remove('hidden');
            return;
        }

        employees.forEach((emp, index) => {
            const item = document.createElement('div');
            item.className = 'p-2.5 hover:bg-cyan-500/10 cursor-pointer transition flex items-center justify-between text-left';
            item.onclick = () => selectReconEmployee(emp);

            const badgeText = emp.source === 'ldap' ? 'LDAP' : 'BD';
            const badgeClass = emp.source === 'ldap' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'bg-slate-700/40 text-slate-300 border border-slate-600';

            item.innerHTML = `
                <div class="space-y-0.5">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-white">${emp.name}</span>
                        <span class="text-[9px] font-mono px-1 rounded ${badgeClass}">${badgeText}</span>
                    </div>
                    <div class="text-[11px] font-mono text-obsidian-muted flex items-center gap-2">
                        <span>C.I: ${emp.id_number || 'N/A'}</span>
                        <span>•</span>
                        <span>Dpto: ${emp.department || 'No especificado'}</span>
                        ${emp.username ? `<span>•</span><span>UID: ${emp.username}</span>` : ''}
                    </div>
                </div>
                <span class="material-symbols-outlined text-sm text-cyan-400 opacity-60">check_circle</span>
            `;

            container.appendChild(item);
        });

        container.classList.remove('hidden');
    }

    function selectReconEmployee(emp) {
        reconState.selectedEmployee = emp;

        // Ocultar resultados
        document.getElementById('reconSearchResultsContainer').classList.add('hidden');

        // Actualizar preview en tarjeta derecha
        const preview = document.getElementById('reconTargetPreview');
        preview.innerHTML = `
            <div class="text-xs font-bold text-cyan-300">${emp.name}</div>
            <div class="text-[11px] font-mono text-slate-300">Cédula: ${emp.id_number || 'N/A'}</div>
            <div class="text-[11px] font-mono text-slate-300">Dpto: ${emp.department || 'N/A'}</div>
            ${emp.role ? `<div class="text-[10px] font-mono text-cyan-400/80">Cargo: ${emp.role}</div>` : ''}
        `;

        const sourceBadge = document.getElementById('reconSelectedSource');
        sourceBadge.textContent = emp.source === 'ldap' ? 'Oficial LDAP' : 'Usuario Sistema';
        sourceBadge.classList.remove('hidden');

        document.getElementById('reconConfirmBtn').disabled = false;
    }

    async function submitReconcileLdap() {
        if (!reconState.assetId || !reconState.selectedEmployee) return;

        const btn = document.getElementById('reconConfirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span><span>Conciliando...</span>';

        const emp = reconState.selectedEmployee;
        const payload = {
            asset_id: reconState.assetId,
            custodian_name: emp.name,
            custodian_id_number: emp.id_number || '',
            custodian_department: emp.department || '',
            custodian_role: emp.role || 'Personal Corporativo',
            phone_extension: emp.phone || '',
            username: emp.username || '',
            email: emp.email || '',
        };

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                           || document.querySelector('input[name="_token"]')?.value;

            const response = await fetch('/admin/inventory/custodian/reconcile', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                // Notificación visual de éxito
                alert("✓ " + result.message);
                closeReconcileLdapModal();
                // Recargar suavemente para reflejar en toda la mesa/ficha
                window.location.reload();
            } else {
                alert("❌ Error: " + (result.message || 'No se pudo conciliar el registro.'));
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined text-sm">verified_user</span><span>Confirmar y Conciliar</span>';
            }
        } catch (err) {
            console.error('Error enviando conciliación:', err);
            alert("❌ Ocurrió un error de red al procesar la conciliación.");
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm">verified_user</span><span>Confirmar y Conciliar</span>';
        }
    }
</script>
