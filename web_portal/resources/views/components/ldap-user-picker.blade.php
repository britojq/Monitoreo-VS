@props([
    'id' => 'ldap-picker-' . uniqid(),
    'name' => 'custodian',
    'mode' => 'custodian', // 'custodian' o 'technician'
    'label' => 'Buscar Trabajador en Directorio Activo LDAP:',
    'placeholder' => 'Escriba Cédula, Usuario o Nombre (ej: 15482314, U1234567, jperez)...',
    'currentName' => '',
    'currentIdNumber' => '',
    'currentDepartment' => '',
    'currentRole' => '',
    'currentUserId' => '',
    'currentUid' => '',
    'currentPhone' => '',
    'showDetails' => true,
    'canRelease' => false,
    'required' => false,
    'theme' => 'cyan', // 'cyan', 'amber', 'emerald'
])

@php
    $themeBorder = match($theme) {
        'amber' => 'border-amber-500/40',
        'emerald' => 'border-emerald-500/40',
        default => 'border-cyan-500/40',
    };
    $themeBg = match($theme) {
        'amber' => 'bg-amber-950/40',
        'emerald' => 'bg-emerald-950/40',
        default => 'bg-cyan-950/40',
    };
    $themeText = match($theme) {
        'amber' => 'text-amber-300',
        'emerald' => 'text-emerald-300',
        default => 'text-cyan-300',
    };
    $themeFocus = match($theme) {
        'amber' => 'focus:border-amber-400',
        'emerald' => 'focus:border-emerald-400',
        default => 'focus:border-cyan-400',
    };

    $hasCurrent = !empty($currentName);
    $inputPrefix = ($name === 'custodian' || empty($name)) ? '' : "{$name}_";
    $fieldName = fn($field) => $inputPrefix . $field;
@endphp

<div id="{{ $id }}-container" class="space-y-3 font-mono text-xs" data-picker-id="{{ $id }}">
    <!-- TARJETA DE ESTADO ACTUAL (SI YA POSEE RESPONSABLE/ASIGNACIÓN) -->
    <div id="{{ $id }}-current-card" class="{{ $hasCurrent ? 'flex' : 'hidden' }} p-3 rounded-xl bg-[#041224] border {{ $themeBorder }} items-center justify-between gap-3 text-xs shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg {{ $themeBg }} border {{ $themeBorder }} flex items-center justify-center {{ $themeText }}">
                <span class="material-symbols-outlined text-lg">badge</span>
            </div>
            <div>
                <div class="text-white font-bold text-xs" id="{{ $id }}-current-name-display">{{ $currentName ?: '--' }}</div>
                <div class="text-[10.5px] text-obsidian-muted" id="{{ $id }}-current-detail-display">
                    C.I: {{ $currentIdNumber ?: 'N/A' }} • {{ $currentDepartment ?: 'Sin Departamento' }}
                    @if($currentRole)
                        • {{ $currentRole }}
                    @endif
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if($canRelease)
                <button type="button" onclick="window['{{ $id }}_setReleaseAction']()" class="px-2.5 py-1.5 rounded-lg bg-red-950/60 border border-red-500/40 text-red-300 hover:bg-red-600 hover:text-white transition text-[10.5px] font-bold flex items-center gap-1 cursor-pointer shadow-xs" title="Liberar este equipo a Stock Disponible">
                    <span class="material-symbols-outlined text-sm">person_off</span>
                    <span>Liberar a Stock</span>
                </button>
            @endif
            <button type="button" onclick="window['{{ $id }}_toggleSearch']()" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-slate-300 hover:text-white hover:border-cyan-400 transition text-[10.5px] font-bold flex items-center gap-1 cursor-pointer">
                <span class="material-symbols-outlined text-sm">swap_horiz</span>
                <span>Cambiar</span>
            </button>
        </div>
    </div>

    <!-- AVISO DE LIBERACIÓN A STOCK PENDIENTE -->
    <div id="{{ $id }}-release-notice" class="hidden p-3 rounded-xl bg-red-950/50 border border-red-500/40 text-red-200 text-xs flex items-center justify-between gap-2 shadow-sm animate-in fade-in duration-150">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-red-400 text-base">warning</span>
            <span>El equipo se devolverá a <strong>Stock Disponible</strong> al guardar los cambios.</span>
        </div>
        <button type="button" onclick="window['{{ $id }}_cancelRelease']()" class="text-xs text-red-300 underline hover:text-white font-bold cursor-pointer">Deshacer</button>
    </div>

    <!-- BUSCADOR LDAP INTERACTIVO -->
    <div id="{{ $id }}-search-wrapper" class="{{ $hasCurrent ? 'hidden' : 'block' }} space-y-1.5 relative">
        <div class="flex items-center justify-between">
            <label class="block text-[11px] font-mono text-slate-300 font-semibold uppercase flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm {{ $themeText }}">search</span>
                <span>{{ $label }}</span>
            </label>
            <span class="text-[9.5px] font-mono px-1.5 py-0.2 rounded bg-cyan-950 border border-cyan-500/30 text-cyan-300">Directorio Activo</span>
        </div>

        <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-obsidian-muted text-sm pointer-events-none">badge</span>
            <input type="text" 
                   id="{{ $id }}-ldap-input" 
                   placeholder="{{ $placeholder }}" 
                   class="w-full pl-9 pr-16 py-2 rounded-xl bg-[#020710] border border-obsidian-border text-white text-xs font-mono placeholder-obsidian-muted/50 {{ $themeFocus }} focus:outline-none transition shadow-inner"
                   autocomplete="off" 
                   oninput="window['{{ $id }}_handleSearch'](this.value)"
                   onkeydown="if(event.key === 'Enter') { event.preventDefault(); window['{{ $id }}_triggerSearch'](); }">
            <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1.5">
                <span id="{{ $id }}-spinner" class="material-symbols-outlined {{ $themeText }} text-sm animate-spin hidden">progress_activity</span>
                <button type="button" 
                        onclick="window['{{ $id }}_triggerSearch']()" 
                        class="w-7 h-7 rounded-lg {{ $themeBg }} border {{ $themeBorder }} {{ $themeText }} hover:bg-white/10 hover:border-cyan-400 flex items-center justify-center transition cursor-pointer shadow-xs" 
                        title="Buscar trabajador">
                    <span class="material-symbols-outlined text-sm">search</span>
                </button>
            </div>
        </div>

        <!-- DROPDOWN FLOTANTE ABSOLUTO (CERO DEFORMACIÓN DE MODAL) -->
        <div id="{{ $id }}-dropdown" class="hidden absolute z-50 left-0 right-0 top-full mt-1.5 max-h-56 overflow-y-auto rounded-xl bg-[#040e1e] border border-cyan-500/60 shadow-2xl p-1.5 space-y-1 divide-y divide-obsidian-border/30 font-mono text-xs backdrop-blur-md"></div>
    </div>

    <!-- TARJETA DE USUARIO VERIFICADO RECIÉN SELECCIONADO -->
    <div id="{{ $id }}-selected-card" class="hidden p-3 rounded-xl {{ $themeBg }} border {{ $themeBorder }} text-xs font-mono flex items-center justify-between gap-2 shadow-md animate-in fade-in duration-150">
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined {{ $themeText }} text-xl">verified</span>
            <div>
                <div class="font-bold text-white text-xs flex items-center gap-1.5">
                    <span id="{{ $id }}-selected-name">--</span>
                    <span id="{{ $id }}-selected-uid" class="px-1.5 py-0.2 rounded text-[8.5px] bg-cyan-950 border border-cyan-500/40 text-cyan-300 font-mono">--</span>
                </div>
                <div class="text-[10px] text-obsidian-muted" id="{{ $id }}-selected-detail">--</div>
            </div>
        </div>
        <button type="button" onclick="window['{{ $id }}_clearSelection']()" class="text-obsidian-muted hover:text-red-400 p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer" title="Quitar o cambiar selección">
            <span class="material-symbols-outlined text-base">close</span>
        </button>
    </div>

    <!-- CAMPOS DE DETALLE DE LA ASIGNACIÓN (EDITABLES / COMPLEMENTARIOS) -->
    @if($showDetails)
        <div id="{{ $id }}-details-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            @if($mode === 'custodian')
                <div>
                    <label class="block text-obsidian-muted mb-0.5 text-[10.5px]">Nombre Completo del Trabajador @if($required)<span class="text-cyan-400">*</span>@endif</label>
                    <input type="text" 
                           name="{{ $fieldName('custodian_name') }}" 
                           id="{{ $id }}-custodian-name" 
                           value="{{ old($fieldName('custodian_name'), $currentName) }}" 
                           placeholder="Nombre y Apellidos" 
                           @if($required) required @endif
                           class="w-full px-2.5 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono {{ $themeFocus }} focus:outline-none">
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-0.5 text-[10.5px]">Cédula de Identidad</label>
                    <div class="relative flex items-center">
                        <input type="text" 
                               name="{{ $fieldName('custodian_id_number') }}" 
                               id="{{ $id }}-custodian-id" 
                               value="{{ old($fieldName('custodian_id_number'), $currentIdNumber) }}" 
                               placeholder="V-12.345.678" 
                               onkeydown="if(event.key === 'Enter') { event.preventDefault(); window['{{ $id }}_searchByCedula'](); }"
                               class="w-full pl-2.5 pr-8 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono {{ $themeFocus }} focus:outline-none">
                        <button type="button" 
                                onclick="window['{{ $id }}_searchByCedula']()" 
                                class="absolute right-1 top-1/2 -translate-y-1/2 w-6 h-6 rounded flex items-center justify-center text-obsidian-muted hover:text-cyan-400 hover:bg-white/10 transition cursor-pointer" 
                                title="Buscar datos asociados a esta Cédula">
                            <span class="material-symbols-outlined text-xs">search</span>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-0.5 text-[10.5px]">Departamento / Gerencia</label>
                    <input type="text" 
                           name="{{ $fieldName('custodian_department') }}" 
                           id="{{ $id }}-custodian-dept" 
                           value="{{ old($fieldName('custodian_department'), $currentDepartment) }}" 
                           placeholder="Ej: ATIT Carabobo" 
                           class="w-full px-2.5 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono {{ $themeFocus }} focus:outline-none">
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-0.5 text-[10.5px]">Cargo / Rol Asignado</label>
                    <input type="text" 
                           name="{{ $fieldName('custodian_role') }}" 
                           id="{{ $id }}-custodian-role" 
                           value="{{ old($fieldName('custodian_role'), $currentRole) }}" 
                           placeholder="Ej: Analista de Sistemas" 
                           class="w-full px-2.5 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono {{ $themeFocus }} focus:outline-none">
                </div>
            @else
                <div>
                    <label class="block text-obsidian-muted mb-0.5 text-[10.5px]">Nombre del Técnico @if($required)<span class="text-amber-400">*</span>@endif</label>
                    <input type="text" 
                           name="{{ $fieldName('technician_name') }}" 
                           id="{{ $id }}-technician-name" 
                           value="{{ old($fieldName('technician_name'), $currentName) }}" 
                           placeholder="Nombre y Apellidos del Técnico" 
                           @if($required) required @endif
                           class="w-full px-2.5 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono {{ $themeFocus }} focus:outline-none">
                </div>
                <div>
                    <label class="block text-obsidian-muted mb-0.5 text-[10.5px]">Cédula del Técnico</label>
                    <div class="relative flex items-center">
                        <input type="text" 
                               name="{{ $fieldName('technician_id_number') }}" 
                               id="{{ $id }}-technician-id" 
                               value="{{ old($fieldName('technician_id_number'), $currentIdNumber) }}" 
                               placeholder="V-12.345.678" 
                               onkeydown="if(event.key === 'Enter') { event.preventDefault(); window['{{ $id }}_searchByCedula'](); }"
                               class="w-full pl-2.5 pr-8 py-1.5 rounded-lg bg-[#020710] border border-obsidian-border text-white text-xs font-mono {{ $themeFocus }} focus:outline-none">
                        <button type="button" 
                                onclick="window['{{ $id }}_searchByCedula']()" 
                                class="absolute right-1 top-1/2 -translate-y-1/2 w-6 h-6 rounded flex items-center justify-center text-obsidian-muted hover:text-amber-400 hover:bg-white/10 transition cursor-pointer" 
                                title="Buscar datos asociados a esta Cédula">
                            <span class="material-symbols-outlined text-xs">search</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- INPUTS OCULTOS DE RESOLUCIÓN Y ACCIÓN -->
    <input type="hidden" name="{{ $fieldName('user_id') }}" id="{{ $id }}-user-id" value="{{ old($fieldName('user_id'), $currentUserId) }}">
    <input type="hidden" name="{{ $fieldName('assignment_action') }}" id="{{ $id }}-assignment-action" value="{{ $hasCurrent ? 'keep' : 'assign' }}">
</div>

<script>
(function() {
    const id = "{{ $id }}";
    const mode = "{{ $mode }}";
    let debounceTimer = null;
    let lastResults = [];
    let isDirectCedulaSearch = false;

    window[id + '_handleSearch'] = function(query, immediate = false, isCedula = false) {
        if (debounceTimer) clearTimeout(debounceTimer);
        const q = (query || '').trim();
        const dropdown = document.getElementById(id + '-dropdown');
        const spinner = document.getElementById(id + '-spinner');
        isDirectCedulaSearch = isCedula;

        if (q.length < 2) {
            if (dropdown) { dropdown.innerHTML = ''; dropdown.classList.add('hidden'); }
            if (spinner) spinner.classList.add('hidden');
            return;
        }

        if (spinner) spinner.classList.remove('hidden');

        const doSearch = async () => {
            try {
                const res = await fetch(`/admin/users/ldap/search?q=${encodeURIComponent(q)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await res.json();
                if (spinner) spinner.classList.add('hidden');
                lastResults = data.results || [];
                window[id + '_renderDropdown'](lastResults);
            } catch (err) {
                if (spinner) spinner.classList.add('hidden');
                console.error('Error buscando trabajador:', err);
            }
        };

        if (immediate) {
            doSearch();
        } else {
            debounceTimer = setTimeout(doSearch, 250);
        }
    };

    window[id + '_triggerSearch'] = function(customQuery = null, isCedula = false) {
        const searchInput = document.getElementById(id + '-ldap-input');
        const q = customQuery !== null ? customQuery : (searchInput ? searchInput.value : '');
        if (searchInput && customQuery !== null) {
            searchInput.value = customQuery;
        }
        window[id + '_handleSearch'](q, true, isCedula);
    };

    window[id + '_searchByCedula'] = function() {
        const cedulaInput = document.getElementById(id + '-custodian-id') || document.getElementById(id + '-technician-id');
        const cedula = cedulaInput ? cedulaInput.value.trim() : '';
        if (cedula.length >= 2) {
            const currentCard = document.getElementById(id + '-current-card');
            const selCard = document.getElementById(id + '-selected-card');
            if (currentCard) currentCard.classList.add('hidden');
            if (selCard) selCard.classList.add('hidden');
            const searchWrap = document.getElementById(id + '-search-wrapper');
            if (searchWrap) searchWrap.classList.remove('hidden');
            window[id + '_triggerSearch'](cedula, true, true);
        }
    };

    window[id + '_renderDropdown'] = function(users) {
        const dropdown = document.getElementById(id + '-dropdown');
        if (!dropdown) return;

        if (users && users.length === 1 && isDirectCedulaSearch) {
            isDirectCedulaSearch = false;
            window[id + '_selectUser'](0);
            return;
        }
        isDirectCedulaSearch = false;

        if (!users || users.length === 0) {
            dropdown.innerHTML = '<div class="p-3 text-center text-obsidian-muted font-mono text-[11px]">Sin coincidencias en MariaDB ni Directorio Activo LDAP.</div>';
            dropdown.classList.remove('hidden');
            return;
        }

        dropdown.innerHTML = users.map((u, idx) => `
            <div onclick="window['${id}_selectUser'](${idx})" 
                 class="p-2.5 rounded-lg hover:bg-cyan-950/70 hover:border-cyan-500/40 border border-transparent flex items-center justify-between gap-2 cursor-pointer transition group">
                <div class="space-y-0.5">
                    <div class="font-bold text-white group-hover:text-cyan-300 flex items-center gap-1.5 text-xs">
                        <span>${escapeHtml(u.name || '')}</span>
                        ${u.uid ? `<span class="px-1.5 py-0.2 rounded text-[8.5px] bg-cyan-950 border border-cyan-500/40 text-cyan-300 font-mono">${escapeHtml(u.uid)}</span>` : ''}
                    </div>
                    <div class="text-[10px] text-obsidian-muted flex items-center gap-2 font-mono">
                        <span class="text-cyan-400 font-semibold">${escapeHtml(u.cedula || 'Sin Cédula')}</span>
                        <span>•</span>
                        <span>${escapeHtml(u.department || 'ATIT')}</span>
                        ${u.role ? `<span>•</span> <span>${escapeHtml(u.role)}</span>` : ''}
                    </div>
                </div>
                <span class="material-symbols-outlined text-sm text-obsidian-muted group-hover:text-cyan-400">check_circle</span>
            </div>
        `).join('');
        dropdown.classList.remove('hidden');
    };

    window[id + '_selectUser'] = function(idx) {
        const u = lastResults[idx];
        if (!u) return;

        // Actualizar inputs ocultos y detalle
        const userIdInput = document.getElementById(id + '-user-id');
        if (userIdInput) userIdInput.value = u.user_id || '';

        const actionInput = document.getElementById(id + '-assignment-action');
        if (actionInput) actionInput.value = 'assign';

        if (mode === 'custodian') {
            const nameEl = document.getElementById(id + '-custodian-name');
            const idEl = document.getElementById(id + '-custodian-id');
            const deptEl = document.getElementById(id + '-custodian-dept');
            const roleEl = document.getElementById(id + '-custodian-role');

            if (nameEl) nameEl.value = u.name || '';
            if (idEl) idEl.value = u.cedula || '';
            if (deptEl) deptEl.value = u.department || 'ATIT Carabobo';
            if (roleEl) roleEl.value = u.role || '';
        } else {
            const nameEl = document.getElementById(id + '-technician-name');
            const idEl = document.getElementById(id + '-technician-id');
            if (nameEl) nameEl.value = u.name || '';
            if (idEl) idEl.value = u.cedula || '';
        }

        // Mostrar tarjeta de seleccionado
        const selCard = document.getElementById(id + '-selected-card');
        const selName = document.getElementById(id + '-selected-name');
        const selUid = document.getElementById(id + '-selected-uid');
        const selDetail = document.getElementById(id + '-selected-detail');

        if (selName) selName.textContent = u.name || '';
        if (selUid) {
            selUid.textContent = u.uid || '';
            selUid.classList.toggle('hidden', !u.uid);
        }
        if (selDetail) selDetail.textContent = `C.I: ${u.cedula || 'N/A'} • ${u.department || 'ATIT'} • ${u.role || ''}`;
        if (selCard) selCard.classList.remove('hidden');

        // Ocultar buscador y dropdown
        const searchWrap = document.getElementById(id + '-search-wrapper');
        const dropdown = document.getElementById(id + '-dropdown');
        const searchInput = document.getElementById(id + '-ldap-input');
        const currentCard = document.getElementById(id + '-current-card');
        const releaseNotice = document.getElementById(id + '-release-notice');

        if (dropdown) dropdown.classList.add('hidden');
        if (searchInput) searchInput.value = '';
        if (searchWrap) searchWrap.classList.add('hidden');
        if (currentCard) currentCard.classList.add('hidden');
        if (releaseNotice) releaseNotice.classList.add('hidden');

        // Evento custom para vistas que requieran sincronizar otros campos
        const container = document.getElementById(id + '-container');
        if (container) {
            container.dispatchEvent(new CustomEvent('ldap-user-selected', { detail: u, bubbles: true }));
        }
    };

    window[id + '_clearSelection'] = function() {
        const userIdInput = document.getElementById(id + '-user-id');
        if (userIdInput) userIdInput.value = '';

        const actionInput = document.getElementById(id + '-assignment-action');
        if (actionInput) actionInput.value = 'assign';

        if (mode === 'custodian') {
            const nameEl = document.getElementById(id + '-custodian-name');
            const idEl = document.getElementById(id + '-custodian-id');
            const deptEl = document.getElementById(id + '-custodian-dept');
            const roleEl = document.getElementById(id + '-custodian-role');

            if (nameEl) nameEl.value = '';
            if (idEl) idEl.value = '';
            if (deptEl) deptEl.value = '';
            if (roleEl) roleEl.value = '';
        } else {
            const nameEl = document.getElementById(id + '-technician-name');
            const idEl = document.getElementById(id + '-technician-id');
            if (nameEl) nameEl.value = '';
            if (idEl) idEl.value = '';
        }

        const selCard = document.getElementById(id + '-selected-card');
        if (selCard) selCard.classList.add('hidden');

        const searchWrap = document.getElementById(id + '-search-wrapper');
        if (searchWrap) searchWrap.classList.remove('hidden');

        const searchInput = document.getElementById(id + '-ldap-input');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }

        const container = document.getElementById(id + '-container');
        if (container) {
            container.dispatchEvent(new CustomEvent('ldap-user-cleared', { bubbles: true }));
        }
    };

    window[id + '_toggleSearch'] = function() {
        const currentCard = document.getElementById(id + '-current-card');
        const searchWrap = document.getElementById(id + '-search-wrapper');
        if (currentCard) currentCard.classList.add('hidden');
        if (searchWrap) {
            searchWrap.classList.remove('hidden');
            const input = document.getElementById(id + '-ldap-input');
            if (input) input.focus();
        }
    };

    window[id + '_setReleaseAction'] = function() {
        const actionInput = document.getElementById(id + '-assignment-action');
        if (actionInput) actionInput.value = 'release';

        const userIdInput = document.getElementById(id + '-user-id');
        if (userIdInput) userIdInput.value = '';

        if (mode === 'custodian') {
            const nameEl = document.getElementById(id + '-custodian-name');
            const idEl = document.getElementById(id + '-custodian-id');
            const deptEl = document.getElementById(id + '-custodian-dept');
            const roleEl = document.getElementById(id + '-custodian-role');

            if (nameEl) nameEl.value = '';
            if (idEl) idEl.value = '';
            if (deptEl) deptEl.value = '';
            if (roleEl) roleEl.value = '';
        }

        const currentCard = document.getElementById(id + '-current-card');
        const searchWrap = document.getElementById(id + '-search-wrapper');
        const selCard = document.getElementById(id + '-selected-card');
        const releaseNotice = document.getElementById(id + '-release-notice');

        if (currentCard) currentCard.classList.add('hidden');
        if (searchWrap) searchWrap.classList.add('hidden');
        if (selCard) selCard.classList.add('hidden');
        if (releaseNotice) releaseNotice.classList.remove('hidden');
    };

    window[id + '_cancelRelease'] = function() {
        const actionInput = document.getElementById(id + '-assignment-action');
        if (actionInput) actionInput.value = 'keep';

        const releaseNotice = document.getElementById(id + '-release-notice');
        const currentCard = document.getElementById(id + '-current-card');
        const searchWrap = document.getElementById(id + '-search-wrapper');

        if (releaseNotice) releaseNotice.classList.add('hidden');
        if (currentCard) currentCard.classList.remove('hidden');
        if (searchWrap) searchWrap.classList.add('hidden');
    };

    window[id + '_populate'] = function(data) {
        if (!data || !data.name) {
            window[id + '_reset']();
            return;
        }

        const actionInput = document.getElementById(id + '-assignment-action');
        if (actionInput) actionInput.value = 'keep';

        const userIdInput = document.getElementById(id + '-user-id');
        if (userIdInput) userIdInput.value = data.user_id || '';

        if (mode === 'custodian') {
            const nameEl = document.getElementById(id + '-custodian-name');
            const idEl = document.getElementById(id + '-custodian-id');
            const deptEl = document.getElementById(id + '-custodian-dept');
            const roleEl = document.getElementById(id + '-custodian-role');

            if (nameEl) nameEl.value = data.name || '';
            if (idEl) idEl.value = data.id_number || data.cedula || '';
            if (deptEl) deptEl.value = data.department || '';
            if (roleEl) roleEl.value = data.role || '';
        } else {
            const nameEl = document.getElementById(id + '-technician-name');
            const idEl = document.getElementById(id + '-technician-id');
            if (nameEl) nameEl.value = data.name || '';
            if (idEl) idEl.value = data.id_number || data.cedula || '';
        }

        const curName = document.getElementById(id + '-current-name-display');
        const curDetail = document.getElementById(id + '-current-detail-display');
        if (curName) curName.textContent = data.name || '--';
        if (curDetail) {
            curDetail.textContent = `C.I: ${data.id_number || data.cedula || 'N/A'} • ${data.department || 'Sin Dpto'}${data.role ? ' • ' + data.role : ''}`;
        }

        const currentCard = document.getElementById(id + '-current-card');
        const searchWrap = document.getElementById(id + '-search-wrapper');
        const selCard = document.getElementById(id + '-selected-card');
        const releaseNotice = document.getElementById(id + '-release-notice');

        if (currentCard) currentCard.classList.remove('hidden');
        if (searchWrap) searchWrap.classList.add('hidden');
        if (selCard) selCard.classList.add('hidden');
        if (releaseNotice) releaseNotice.classList.add('hidden');
    };

    window[id + '_reset'] = function() {
        const actionInput = document.getElementById(id + '-assignment-action');
        if (actionInput) actionInput.value = 'assign';

        const userIdInput = document.getElementById(id + '-user-id');
        if (userIdInput) userIdInput.value = '';

        if (mode === 'custodian') {
            const nameEl = document.getElementById(id + '-custodian-name');
            const idEl = document.getElementById(id + '-custodian-id');
            const deptEl = document.getElementById(id + '-custodian-dept');
            const roleEl = document.getElementById(id + '-custodian-role');

            if (nameEl) nameEl.value = '';
            if (idEl) idEl.value = '';
            if (deptEl) deptEl.value = '';
            if (roleEl) roleEl.value = '';
        } else {
            const nameEl = document.getElementById(id + '-technician-name');
            const idEl = document.getElementById(id + '-technician-id');
            if (nameEl) nameEl.value = '';
            if (idEl) idEl.value = '';
        }

        const currentCard = document.getElementById(id + '-current-card');
        const searchWrap = document.getElementById(id + '-search-wrapper');
        const selCard = document.getElementById(id + '-selected-card');
        const releaseNotice = document.getElementById(id + '-release-notice');
        const dropdown = document.getElementById(id + '-dropdown');
        const searchInput = document.getElementById(id + '-ldap-input');

        if (currentCard) currentCard.classList.add('hidden');
        if (searchWrap) searchWrap.classList.remove('hidden');
        if (selCard) selCard.classList.add('hidden');
        if (releaseNotice) releaseNotice.classList.add('hidden');
        if (dropdown) dropdown.classList.add('hidden');
        if (searchInput) searchInput.value = '';
    };

    // Sincronización en tiempo real de campos manuales con las tarjetas visuales
    function syncCardFromInputs() {
        const nameEl = document.getElementById(id + '-custodian-name') || document.getElementById(id + '-technician-name');
        const idEl = document.getElementById(id + '-custodian-id') || document.getElementById(id + '-technician-id');
        const deptEl = document.getElementById(id + '-custodian-dept');
        const roleEl = document.getElementById(id + '-custodian-role');

        const nameVal = nameEl ? nameEl.value.trim() : '';
        const idVal = idEl ? idEl.value.trim() : '';
        const deptVal = deptEl ? deptEl.value.trim() : '';
        const roleVal = roleEl ? roleEl.value.trim() : '';

        // Actualizar tarjeta seleccionada si está visible
        const selName = document.getElementById(id + '-selected-name');
        const selDetail = document.getElementById(id + '-selected-detail');
        if (selName && nameVal) selName.textContent = nameVal;
        if (selDetail) {
            selDetail.textContent = `C.I: ${idVal || 'N/A'}${deptVal ? ' • ' + deptVal : ''}${roleVal ? ' • ' + roleVal : ''}`;
        }

        // Actualizar tarjeta de asignación actual si está visible
        const curName = document.getElementById(id + '-current-name-display');
        const curDetail = document.getElementById(id + '-current-detail-display');
        if (curName && nameVal) curName.textContent = nameVal;
        if (curDetail) {
            curDetail.textContent = `C.I: ${idVal || 'N/A'}${deptVal ? ' • ' + deptVal : ''}${roleVal ? ' • ' + roleVal : ''}`;
        }

        // Si el usuario escribe o corrige manualmente, asegurar acción de asignación
        const actionInput = document.getElementById(id + '-assignment-action');
        if (actionInput && actionInput.value !== 'release') {
            actionInput.value = 'assign';
        }
    }

    ['custodian-name', 'custodian-id', 'custodian-dept', 'custodian-role', 'technician-name', 'technician-id'].forEach(fieldKey => {
        const el = document.getElementById(id + '-' + fieldKey);
        if (el) {
            el.addEventListener('input', syncCardFromInputs);
        }
    });

    // Cerrar dropdown al hacer click fuera
    document.addEventListener('click', function(e) {
        const container = document.getElementById(id + '-container');
        if (container && !container.contains(e.target)) {
            const dropdown = document.getElementById(id + '-dropdown');
            if (dropdown) dropdown.classList.add('hidden');
        }
    });

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
</script>
