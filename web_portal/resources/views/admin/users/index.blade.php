@extends('layouts.admin')

@section('page_title', 'Gestión de Usuarios')

@section('admin_content')
<div class="space-y-6">
    <!-- CABECERA Y BOTÓN CREAR -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-xs font-mono font-semibold group shadow-sm">
                    <span class="material-symbols-outlined text-sm group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-xs font-mono text-obsidian-muted">Usuarios Registrados</span>
            </div>
            <h2 class="text-lg font-bold text-white">Usuarios del Sistema</h2>
            <p class="text-xs font-mono text-obsidian-muted">Administra los accesos y roles autorizados para el monitoreo</p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <button onclick="openModal('modal-ldap-search')" class="px-4 py-2.5 rounded-lg bg-obsidian-panel border border-cyan-500/50 text-cyan-300 font-bold text-xs font-mono uppercase flex items-center gap-2 hover:bg-cyan-950/60 hover:border-cyan-400 transition shadow-lg shadow-cyan-950/40">
                <span class="material-symbols-outlined text-base">manage_accounts</span>
                Buscar en LDAP & Autorizar
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold font-mono {{ ($isLdapEnabled ?? true) ? 'bg-emerald-950 text-emerald-300 border border-emerald-500/40' : 'bg-rose-950 text-rose-300 border border-rose-500/40' }}">
                    {{ ($isLdapEnabled ?? true) ? 'ACTIVO' : 'INACTIVO' }}
                </span>
            </button>
            <button onclick="openModal('modal-create-user')" class="px-4 py-2.5 rounded-lg bg-obsidian-cyan text-black font-bold text-xs font-mono uppercase flex items-center gap-2 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20">
                <span class="material-symbols-outlined text-base">person_add</span>
                Crear Usuario Local
            </button>
        </div>
    </div>

    <!-- TABLA DE USUARIOS -->
    <div class="glass-card rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-obsidian-panel border-b border-obsidian-border text-obsidian-muted uppercase text-[11px]">
                    <tr>
                        <th class="px-6 py-4">Usuario / Nombre</th>
                        <th class="px-6 py-4">Correo Electrónico</th>
                        <th class="px-6 py-4">Rol</th>
                        <th class="px-6 py-4">Estado</th>
                        <th class="px-6 py-4">Último Acceso</th>
                        <th class="px-6 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/60">
                    @foreach($users as $u)
                        <tr class="hover:bg-obsidian-panel/40 transition">
                            <td class="px-6 py-4 font-sans font-semibold text-white">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-obsidian-cyan/20 border border-obsidian-cyan/40 text-obsidian-cyan flex items-center justify-center font-mono text-xs shrink-0 overflow-hidden">
                                        @if($u->avatar_url)
                                            <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}" class="w-full h-full object-cover">
                                        @else
                                            {{ strtoupper(substr($u->name, 0, 2)) }}
                                        @endif
                                    </div>
                                    <div class="space-y-0.5">
                                        <div>{{ $u->name }}</div>
                                        @if($u->username)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono bg-cyan-950/80 border border-cyan-500/40 text-cyan-300">
                                                <span class="material-symbols-outlined text-[11px]">badge</span>
                                                LDAP: {{ $u->username }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono bg-amber-950/80 border border-amber-500/40 text-amber-300">
                                                <span class="material-symbols-outlined text-[11px]">vpn_key</span>
                                                Cuenta Local
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-obsidian-muted">{{ $u->email }}</td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1 items-start">
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $u->role === 'admin' ? 'bg-purple-950/70 text-purple-400 border border-purple-500/40' : 'bg-blue-950/70 text-blue-400 border border-blue-500/40' }}">
                                        {{ $u->role }}
                                    </span>
                                    @if(!$u->isAdmin())
                                        @if(is_array($u->permissions))
                                            <span id="badge-perm-{{ $u->id }}" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-mono bg-amber-950/70 border border-amber-500/50 text-amber-300" title="{{ count($u->permissions) }} permisos personalizados asignados">
                                                <span class="material-symbols-outlined text-[10px]">shield_person</span>
                                                Personalizado ({{ count($u->permissions) }})
                                            </span>
                                        @else
                                            <span id="badge-perm-{{ $u->id }}" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-mono bg-slate-900/90 border border-slate-700 text-slate-400" title="Permisos estándar del rol operador">
                                                <span class="material-symbols-outlined text-[10px]">verified</span>
                                                Por Defecto
                                            </span>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-mono bg-purple-950/50 border border-purple-500/30 text-purple-300" title="Acceso maestro irrestricto (*)">
                                            <span class="material-symbols-outlined text-[10px]">admin_panel_settings</span>
                                            Total (*)
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="flex items-center gap-1.5 {{ $u->is_active ? 'text-emerald-400' : 'text-red-400' }}">
                                    <span class="w-2 h-2 rounded-full {{ $u->is_active ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                                    {{ $u->is_active ? 'Activo' : 'Desactivado' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-obsidian-muted">
                                {{ $u->last_login_at ? $u->last_login_at->format('Y-m-d H:i') : 'Nunca' }}
                            </td>
                            <td class="px-6 py-4 text-right space-x-1.5">
                                <button type="button" onclick="openPermissionsModal({{ $u->id }})" class="p-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-amber-400 hover:bg-amber-400 hover:text-black transition" title="Gestionar Permisos">
                                    <span class="material-symbols-outlined text-sm">shield_person</span>
                                </button>
                                <button type="button" onclick="openEditUserModal({{ json_encode($u) }})" class="p-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition" title="Editar">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                </button>
                                @if($u->email !== 'britojq@gmail.com' && $u->id !== Auth::id())
                                    <form action="{{ route('admin.users.destroy', $u->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Está seguro de eliminar a este usuario?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg bg-red-950/40 border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition" title="Eliminar">
                                            <span class="material-symbols-outlined text-sm">delete</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL CREAR USUARIO -->
<div id="modal-create-user" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-md w-full rounded-2xl p-6 border border-obsidian-border shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <h3 class="text-sm font-bold text-white uppercase font-mono">Crear Nuevo Usuario</h3>
            <button onclick="closeModal('modal-create-user')" class="text-obsidian-muted hover:text-white text-xl">&times;</button>
        </div>
        <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-4 font-mono text-xs">
            @csrf
            <div>
                <label class="block text-obsidian-muted mb-1">Nombre Completo</label>
                <input type="text" name="name" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan" placeholder="Ej. Operador Tecnico"/>
            </div>
            <div>
                <label class="block text-obsidian-muted mb-1">Correo Electrónico</label>
                <input type="email" name="email" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan" placeholder="usuario@empresa.local"/>
            </div>
            <div>
                <label class="block text-obsidian-muted mb-1">Contraseña</label>
                <input type="password" name="password" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan" placeholder="Mínimo 6 caracteres"/>
            </div>
            <div>
                <label class="block text-obsidian-muted mb-1">Rol de Acceso</label>
                <select name="role" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan">
                    <option value="admin">Administrador (Control Total)</option>
                    <option value="operator">Operador (Solo Consulta y Monitoreo)</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" checked id="create_is_active" class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan"/>
                <label for="create_is_active" class="text-white">Cuenta Activa</label>
            </div>
            <div class="pt-3 border-t border-obsidian-border flex justify-end gap-2">
                <button type="button" onclick="closeModal('modal-create-user')" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold">Guardar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR USUARIO -->
<div id="modal-edit-user" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-md w-full rounded-2xl p-6 border border-obsidian-border shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
            <h3 class="text-sm font-bold text-white uppercase font-mono">Editar Usuario</h3>
            <button onclick="closeModal('modal-edit-user')" class="text-obsidian-muted hover:text-white text-xl">&times;</button>
        </div>
        <form id="form-edit-user" method="POST" class="space-y-4 font-mono text-xs">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-obsidian-muted mb-1">Nombre Completo</label>
                <input type="text" name="name" id="edit_name" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan"/>
            </div>
            <div>
                <label class="block text-obsidian-muted mb-1">Correo Electrónico</label>
                <input type="email" name="email" id="edit_email" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan"/>
            </div>
            <!-- CONTENEDOR CONTRASEÑA CUENTA LOCAL -->
            <div id="container-edit-password">
                <label class="block text-obsidian-muted mb-1">Nueva Contraseña (Dejar en blanco para mantener la actual)</label>
                <input type="password" name="password" id="edit_password" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan" placeholder="••••••••"/>
            </div>

            <!-- CONTENEDOR INFORMATIVO PARA USUARIOS LDAP -->
            <div id="container-ldap-notice" class="hidden p-3.5 rounded-xl bg-cyan-950/40 border border-cyan-500/40 text-xs font-mono text-cyan-300 space-y-1">
                <div class="flex items-center gap-1.5 font-bold text-white">
                    <span class="material-symbols-outlined text-base text-obsidian-cyan">lock_person</span>
                    <span>Autenticación Centralizada (LDAP Corporativo)</span>
                </div>
                <p class="text-[11px] text-obsidian-muted leading-relaxed">
                    Usuario federado: <code id="ldap-notice-username" class="text-obsidian-cyan font-bold"></code>. Su contraseña reside exclusivamente en el Directorio Activo Corporativo y no se administra en este portal.
                </p>
            </div>
            <div>
                <label class="block text-obsidian-muted mb-1">Rol de Acceso</label>
                <select name="role" id="edit_role" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan">
                    <option value="admin">Administrador</option>
                    <option value="operator">Operador</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" id="edit_is_active" class="rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan"/>
                <label for="edit_is_active" class="text-white">Cuenta Activa</label>
            </div>
            <div class="pt-3 border-t border-obsidian-border flex justify-end gap-2">
                <button type="button" onclick="closeModal('modal-edit-user')" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-obsidian-cyan text-black font-bold">Actualizar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL BUSCAR Y AUTORIZAR EN LDAP -->
<div id="modal-ldap-search" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="glass-panel max-w-4xl w-full rounded-2xl p-6 border border-obsidian-border shadow-2xl space-y-5 max-h-[90vh] flex flex-col">
        <!-- HEADER MODAL -->
        <div class="flex items-center justify-between pb-3 border-b border-obsidian-border shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-lg bg-cyan-950/80 border border-cyan-500/30 flex items-center justify-center text-cyan-300 shrink-0">
                    <span class="material-symbols-outlined text-xl">manage_accounts</span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white uppercase font-mono tracking-wide flex items-center gap-2">
                        Directorio LDAP Corporativo • Búsqueda y Autorización
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold font-mono {{ ($isLdapEnabled ?? true) ? 'bg-emerald-950 text-emerald-300 border border-emerald-500/40' : 'bg-rose-950 text-rose-300 border border-rose-500/40' }}">
                            {{ ($isLdapEnabled ?? true) ? 'HABILITADO' : 'DESHABILITADO' }}
                        </span>
                    </h3>
                    <p class="text-[11px] font-mono text-obsidian-muted">Localice usuarios en el Directorio Activo Corporativo y concédales acceso manual con rol asignado</p>
                </div>
            </div>
            <button onclick="closeModal('modal-ldap-search')" class="text-obsidian-muted hover:text-white text-2xl leading-none transition">&times;</button>
        </div>

        @if(!($isLdapEnabled ?? true))
        <div class="shrink-0 p-3 bg-rose-950/50 border border-rose-500/40 rounded-lg text-rose-300 text-xs font-mono flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-rose-400 shrink-0">warning</span>
            <span>Atención: El servicio de autenticación LDAP corporativo se encuentra <strong>DESACTIVADO</strong> por el Administrador. Puede reactivarlo en la sección LDAP del <a href="{{ route('admin.dashboard') }}" class="underline hover:text-white font-bold text-cyan-300">Panel Principal</a>.</span>
        </div>
        @endif

        <!-- BARRA DE BÚSQUEDA -->
        <div class="shrink-0 space-y-2">
            <form id="form-ldap-search" onsubmit="event.preventDefault(); executeLdapSearch();" class="flex gap-2">
                <div class="relative flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-obsidian-muted text-base">search</span>
                    <input type="text" id="ldap_search_query" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg pl-9 pr-3 py-2.5 text-xs font-mono text-white placeholder-obsidian-muted/60 focus:outline-none focus:border-obsidian-cyan" placeholder="Buscar por UID / Cédula (ej. 1234567, U1234567) o Correo (ej. usuario@empresa.local)"/>
                </div>
                <button type="submit" id="btn-search-ldap" class="px-5 py-2.5 rounded-lg bg-obsidian-cyan text-black font-mono font-bold text-xs uppercase flex items-center gap-2 hover:bg-cyan-300 transition shrink-0">
                    <span class="material-symbols-outlined text-base" id="icon-search-ldap">search</span>
                    <span>Buscar</span>
                </button>
            </form>
            <div class="flex items-center justify-between text-[11px] font-mono text-obsidian-muted">
                <span>💡 Ingrese el UID exacto (o número de cédula) para consulta instantánea (&lt;15ms).</span>
                <span id="ldap-search-status" class="text-cyan-400 font-semibold"></span>
            </div>
        </div>

        <!-- ALERTA DE NOTIFICACIÓN DE ACCIÓN -->
        <div id="ldap-action-alert" class="hidden shrink-0 p-3 rounded-lg border text-xs font-mono"></div>

        <!-- ÁREA DE RESULTADOS (SCROLLABLE) -->
        <div class="flex-1 overflow-y-auto space-y-3 min-h-[200px] max-h-[420px] pr-1" id="ldap-results-container">
            <!-- Estado inicial por defecto -->
            <div id="ldap-initial-state" class="h-48 flex flex-col items-center justify-center text-center p-6 border border-dashed border-obsidian-border/80 rounded-xl">
                <span class="material-symbols-outlined text-4xl text-obsidian-muted/40 mb-2">fingerprint</span>
                <p class="text-xs font-mono text-obsidian-muted">Realice una búsqueda para consultar cuentas en el Directorio Activo LDAP.</p>
                <p class="text-[10px] font-mono text-obsidian-muted/60 mt-1">Los usuarios autorizados podrán iniciar sesión con su clave institucional, aunque no pertenezcan al grupo ATIT.</p>
            </div>

            <!-- Spinner de carga -->
            <div id="ldap-loading-state" class="hidden h-48 flex flex-col items-center justify-center text-center p-6 border border-dashed border-cyan-500/30 rounded-xl">
                <span class="material-symbols-outlined text-4xl text-obsidian-cyan animate-spin mb-2">sync</span>
                <p class="text-xs font-mono text-cyan-300">Consultando servidor LDAP corporativo ({{ $ldapConfig['host'] ?? '10.20.0.22' }})...</p>
                <p class="text-[10px] font-mono text-obsidian-muted mt-1">Buscando registros coincidentes...</p>
            </div>

            <!-- Contenedor dinámico de resultados -->
            <div id="ldap-results-list" class="space-y-2.5 hidden"></div>
        </div>

        <!-- FOOTER MODAL -->
        <div class="shrink-0 pt-3 border-t border-obsidian-border flex items-center justify-between">
            <span class="text-[11px] font-mono text-obsidian-muted">Servidor LDAP: <code class="text-cyan-400 font-mono">{{ $ldapConfig['host'] ?? '10.20.0.22' }}:{{ $ldapConfig['port'] ?? 389 }}</code> (Directorio Activo)</span>
            <button type="button" onclick="closeModal('modal-ldap-search')" class="px-4 py-2 rounded-lg bg-obsidian-panel text-obsidian-muted hover:text-white text-xs font-mono">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODAL GESTIÓN DE PERMISOS -->
<div id="modal-permissions-user" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-6 overflow-y-auto">
    <div class="glass-panel max-w-4xl w-full rounded-2xl border border-obsidian-border shadow-2xl flex flex-col max-h-[92vh] bg-[#0b1726]/95">
        <!-- HEADER -->
        <div class="p-4 sm:p-5 border-b border-obsidian-border flex items-center justify-between shrink-0 bg-[#071321]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 shrink-0">
                    <span class="material-symbols-outlined text-2xl">shield_person</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white font-mono flex items-center gap-2">
                        <span>Matriz de Permisos</span>
                        <span id="perm-user-role-badge" class="px-2 py-0.5 rounded text-[9.5px] uppercase font-mono font-bold bg-blue-950 border border-blue-500/40 text-blue-300">OPERADOR</span>
                    </h3>
                    <p class="text-xs text-obsidian-muted font-mono" id="perm-user-subtitle">Cargando datos de usuario...</p>
                </div>
            </div>
            <button onclick="closeModal('modal-permissions-user')" class="text-obsidian-muted hover:text-white text-2xl transition">&times;</button>
        </div>

        <!-- ALERTA / BARRA DE FILTRO Y ACCIONES RÁPIDAS -->
        <div class="p-3 sm:px-5 py-3 border-b border-obsidian-border/80 bg-obsidian-bg/60 shrink-0 space-y-2">
            <!-- Banner contextual -->
            <div id="perm-admin-banner" class="hidden p-2.5 rounded-lg border border-purple-500/40 bg-purple-950/40 text-purple-300 text-xs font-mono flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-purple-400">info</span>
                <span>Este usuario tiene rol de <b>Administrador</b> y cuenta con acceso total irrestricto (*) a todos los módulos. Los cambios aplicados tendrán efecto en caso de que se conmute a rol Operador.</span>
            </div>
            
            <div id="perm-alert-msg" class="hidden p-2.5 rounded-lg text-xs font-mono flex items-center justify-between"></div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-2.5 pt-1">
                <!-- Buscador rápido -->
                <div class="relative w-full sm:w-72">
                    <span class="material-symbols-outlined absolute left-2.5 top-2 text-sm text-obsidian-muted">search</span>
                    <input type="text" id="perm-search-input" oninput="filterPermissions(this.value)" placeholder="Filtrar permisos o módulos..." class="w-full pl-8 pr-3 py-1.5 bg-obsidian-panel border border-obsidian-border rounded-lg text-xs text-white placeholder-obsidian-muted focus:outline-none focus:border-obsidian-cyan font-mono" />
                </div>

                <!-- Botones de acción rápida -->
                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button type="button" onclick="resetPermissionsToDefault()" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-muted hover:text-white hover:border-obsidian-cyan text-[11px] font-mono transition flex items-center gap-1 cursor-pointer" title="Restablecer a la plantilla oficial de operador">
                        <span class="material-symbols-outlined text-sm text-cyan-400">restart_alt</span>
                        <span>Por Defecto</span>
                    </button>
                    <button type="button" onclick="grantAllPermissions()" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-emerald-400 hover:bg-emerald-500/20 text-[11px] font-mono transition flex items-center gap-1 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">check_box</span>
                        <span>Todos</span>
                    </button>
                    <button type="button" onclick="revokeAllPermissions()" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-red-400 hover:bg-red-500/20 text-[11px] font-mono transition flex items-center gap-1 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">check_box_outline_blank</span>
                        <span>Ninguno</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- CUERPO CON MÓDULOS Y SWITCHES (SCROLLABLE) -->
        <div id="perm-modules-container" class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-4 font-mono text-xs">
            <div id="perm-loading" class="py-12 text-center text-obsidian-muted">
                <span class="material-symbols-outlined text-3xl animate-spin text-obsidian-cyan mb-2">sync</span>
                <p>Cargando matriz de permisos del usuario...</p>
            </div>
            <!-- Los módulos se renderizan dinámicamente vía JavaScript -->
            <div id="perm-modules-list" class="hidden space-y-4"></div>
        </div>

        <!-- FOOTER -->
        <div class="p-4 sm:px-6 py-3.5 border-t border-obsidian-border bg-[#071321] flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
            <div class="text-xs font-mono text-obsidian-muted flex items-center gap-2">
                <span>Seleccionados:</span>
                <span id="perm-count-badge" class="px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-obsidian-panel border border-obsidian-border text-cyan-300">0 / 0</span>
                <span id="perm-status-note" class="text-[11px] text-amber-300"></span>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <button type="button" onclick="closeModal('modal-permissions-user')" class="px-4 py-2 rounded-lg border border-obsidian-border text-obsidian-muted hover:text-white hover:bg-obsidian-panel font-mono text-xs transition">
                    Cancelar
                </button>
                <button type="button" id="btn-save-permissions" onclick="savePermissions()" class="px-5 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-black font-mono text-xs font-bold transition flex items-center gap-2 shadow-lg shadow-amber-500/20 cursor-pointer">
                    <span class="material-symbols-outlined text-base">save</span>
                    <span id="btn-save-permissions-text">Guardar Permisos</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function openModal(id) {
        const m = document.getElementById(id);
        m.classList.remove('hidden');
        m.classList.add('flex');
        if (id === 'modal-ldap-search') {
            setTimeout(() => {
                const input = document.getElementById('ldap_search_query');
                if (input) input.focus();
            }, 100);
        }
    }

    function closeModal(id) {
        const m = document.getElementById(id);
        m.classList.remove('flex');
        m.classList.add('hidden');
    }

    function openEditUserModal(user) {
        document.getElementById('form-edit-user').action = `/admin/users/${user.id}`;
        document.getElementById('edit_name').value = user.name;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_is_active').checked = user.is_active;

        if (user.username) {
            document.getElementById('container-edit-password').classList.add('hidden');
            document.getElementById('edit_password').value = '';
            document.getElementById('ldap-notice-username').innerText = user.username;
            document.getElementById('container-ldap-notice').classList.remove('hidden');
        } else {
            document.getElementById('container-ldap-notice').classList.add('hidden');
            document.getElementById('container-edit-password').classList.remove('hidden');
        }

        openModal('modal-edit-user');
    }

    async function executeLdapSearch() {
        const input = document.getElementById('ldap_search_query');
        const q = input.value.trim();
        const statusEl = document.getElementById('ldap-search-status');
        const initialEl = document.getElementById('ldap-initial-state');
        const loadingEl = document.getElementById('ldap-loading-state');
        const listEl = document.getElementById('ldap-results-list');
        const alertEl = document.getElementById('ldap-action-alert');
        const btnSearch = document.getElementById('btn-search-ldap');

        alertEl.classList.add('hidden');

        if (q.length < 2) {
            alertEl.className = 'shrink-0 p-3 rounded-lg border border-amber-500/50 bg-amber-950/40 text-amber-300 text-xs font-mono';
            alertEl.innerHTML = '⚠️ Ingrese al menos 2 caracteres para realizar la búsqueda en LDAP.';
            alertEl.classList.remove('hidden');
            return;
        }

        // UI Loading
        initialEl.classList.add('hidden');
        listEl.classList.add('hidden');
        loadingEl.classList.remove('hidden');
        btnSearch.disabled = true;
        btnSearch.classList.add('opacity-70');
        statusEl.innerText = 'Consultando...';

        try {
            const url = `{{ route('admin.users.ldap.search') }}?q=${encodeURIComponent(q)}`;
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();

            loadingEl.classList.add('hidden');
            btnSearch.disabled = false;
            btnSearch.classList.remove('opacity-70');

            if (!data.success && (!data.results || data.results.length === 0)) {
                statusEl.innerText = '0 resultados';
                listEl.innerHTML = `
                    <div class="h-36 flex flex-col items-center justify-center text-center p-6 border border-dashed border-amber-500/40 rounded-xl bg-amber-950/20">
                        <span class="material-symbols-outlined text-3xl text-amber-400 mb-1">person_off</span>
                        <p class="text-xs font-mono text-amber-300 font-semibold">${data.message || 'No se encontraron usuarios en el directorio LDAP con ese término.'}</p>
                        <p class="text-[11px] font-mono text-obsidian-muted mt-1">Verifique el número de cédula o correo corporativo e intente nuevamente.</p>
                    </div>
                `;
                listEl.classList.remove('hidden');
                return;
            }

            statusEl.innerText = `${data.count} encontrado(s)`;
            
            if (data.results.length === 0) {
                listEl.innerHTML = `
                    <div class="h-36 flex flex-col items-center justify-center text-center p-6 border border-dashed border-obsidian-border rounded-xl">
                        <span class="material-symbols-outlined text-3xl text-obsidian-muted mb-1">search_off</span>
                        <p class="text-xs font-mono text-obsidian-muted">No se hallaron coincidencias en el servidor LDAP corporativo.</p>
                    </div>
                `;
                listEl.classList.remove('hidden');
                return;
            }

            let html = '';
            data.results.forEach(user => {
                const uidSafe = escapeHtml(user.uid);
                const nameSafe = escapeHtml(user.name);
                const emailSafe = escapeHtml(user.email);
                const descSafe = escapeHtml(user.description || 'Sin cargo especificado');
                const sedeSafe = escapeHtml(user.sede || '');
                const isDefault = user.is_default_area;
                const isAuth = user.already_authorized;
                const role = user.current_role || 'operator';

                html += `
                    <div class="glass-card p-4 rounded-xl border border-obsidian-border/80 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:border-cyan-500/40 transition">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-sans font-bold text-sm text-white">${nameSafe}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-cyan-950/90 border border-cyan-500/50 text-cyan-300 font-semibold">
                                    UID: ${uidSafe}
                                </span>
                                ${isDefault ? 
                                    '<span class="px-2 py-0.5 rounded text-[10px] font-mono bg-emerald-950/80 border border-emerald-500/40 text-emerald-300">Área Estándar (ATIT)</span>' : 
                                    '<span class="px-2 py-0.5 rounded text-[10px] font-mono bg-amber-950/80 border border-amber-500/40 text-amber-300">Área Externa (Requiere Autorización)</span>'
                                }
                            </div>
                            <div class="text-xs text-obsidian-muted font-mono flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                <span>✉️ ${emailSafe}</span>
                                ${sedeSafe && sedeSafe !== 'No especificada' ? `<span>🏢 ${sedeSafe}</span>` : ''}
                            </div>
                            <div class="text-[11px] text-obsidian-muted/90 font-mono">
                                💼 <span class="text-white/80">${descSafe}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0 self-end md:self-center">
                            ${isAuth ? `
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded text-[10px] font-mono font-bold uppercase ${role === 'admin' ? 'bg-purple-950/90 text-purple-300 border border-purple-500/50' : 'bg-blue-950/90 text-blue-300 border border-blue-500/50'}">
                                        ${role === 'admin' ? '✓ Admin Activo' : '✓ Operador Activo'}
                                    </span>
                                    <select id="role-select-${uidSafe}" class="bg-obsidian-panel border border-obsidian-border rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-obsidian-cyan">
                                        <option value="operator" ${role === 'operator' ? 'selected' : ''}>Operador</option>
                                        <option value="admin" ${role === 'admin' ? 'selected' : ''}>Administrador</option>
                                    </select>
                                    <button id="btn-auth-${uidSafe}" onclick="authorizeLdap('${uidSafe}', 'role-select-${uidSafe}', this)" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-cyan-500/50 text-cyan-300 font-mono text-xs font-semibold hover:bg-cyan-950 hover:text-white transition">
                                        Actualizar
                                    </button>
                                </div>
                            ` : `
                                <div class="flex items-center gap-2">
                                    <select id="role-select-${uidSafe}" class="bg-obsidian-panel border border-obsidian-border rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-obsidian-cyan">
                                        <option value="operator" selected>Operador</option>
                                        <option value="admin">Administrador</option>
                                    </select>
                                    <button id="btn-auth-${uidSafe}" onclick="authorizeLdap('${uidSafe}', 'role-select-${uidSafe}', this)" class="px-3.5 py-1.5 rounded-lg bg-emerald-500/20 border border-emerald-500/50 text-emerald-300 hover:bg-emerald-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-emerald-950">
                                        <span class="material-symbols-outlined text-sm">how_to_reg</span>
                                        Autorizar Acceso
                                    </button>
                                </div>
                            `}
                        </div>
                    </div>
                `;
            });

            listEl.innerHTML = html;
            listEl.classList.remove('hidden');

        } catch (err) {
            loadingEl.classList.add('hidden');
            btnSearch.disabled = false;
            btnSearch.classList.remove('opacity-70');
            statusEl.innerText = 'Error';
            alertEl.className = 'shrink-0 p-3 rounded-lg border border-red-500/50 bg-red-950/40 text-red-300 text-xs font-mono';
            alertEl.innerHTML = '❌ Ocurrió un error al comunicarse con el servidor: ' + escapeHtml(err.message);
            alertEl.classList.remove('hidden');
        }
    }

    async function authorizeLdap(uid, selectId, btnElement) {
        const select = document.getElementById(selectId);
        const role = select ? select.value : 'operator';
        const alertEl = document.getElementById('ldap-action-alert');
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const token = tokenMeta ? tokenMeta.getAttribute('content') : '{{ csrf_token() }}';

        alertEl.classList.add('hidden');
        const originalHtml = btnElement.innerHTML;
        btnElement.disabled = true;
        btnElement.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">sync</span> Procesando...';

        try {
            const res = await fetch('{{ route('admin.users.ldap.authorize') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ uid: uid, role: role })
            });

            const data = await res.json();
            btnElement.disabled = false;
            btnElement.innerHTML = originalHtml;

            if (data.success) {
                alertEl.className = 'shrink-0 p-3 rounded-lg border border-emerald-500/50 bg-emerald-950/40 text-emerald-300 text-xs font-mono flex items-center justify-between';
                alertEl.innerHTML = `
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">check_circle</span>
                        <span>${escapeHtml(data.message)}</span>
                    </div>
                    <span class="text-[10px] text-emerald-400">Actualizando lista de usuarios...</span>
                `;
                alertEl.classList.remove('hidden');

                setTimeout(() => {
                    window.location.reload();
                }, 1200);
            } else {
                alertEl.className = 'shrink-0 p-3 rounded-lg border border-red-500/50 bg-red-950/40 text-red-300 text-xs font-mono';
                alertEl.innerHTML = '❌ ' + escapeHtml(data.message || 'Error al autorizar usuario.');
                alertEl.classList.remove('hidden');
            }
        } catch (err) {
            btnElement.disabled = false;
            btnElement.innerHTML = originalHtml;
            alertEl.className = 'shrink-0 p-3 rounded-lg border border-red-500/50 bg-red-950/40 text-red-300 text-xs font-mono';
            alertEl.innerHTML = '❌ Error de red o servidor: ' + escapeHtml(err.message);
            alertEl.classList.remove('hidden');
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // =========================================================================
    // GESTIÓN VISUAL DE PERMISOS
    // =========================================================================
    let currentPermUserId = null;
    let permDataCache = null;
    let permIsResetToDefault = false;

    async function openPermissionsModal(userId) {
        currentPermUserId = userId;
        permIsResetToDefault = false;
        
        // Reset UI
        document.getElementById('perm-loading').classList.remove('hidden');
        document.getElementById('perm-modules-list').classList.add('hidden');
        document.getElementById('perm-alert-msg').classList.add('hidden');
        document.getElementById('perm-admin-banner').classList.add('hidden');
        document.getElementById('perm-search-input').value = '';
        document.getElementById('perm-status-note').innerText = '';
        document.getElementById('perm-user-subtitle').innerText = 'Cargando datos de usuario #' + userId + '...';
        
        openModal('modal-permissions-user');

        try {
            const url = `/admin/users/${userId}/permissions`;
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();

            if (!data.success) {
                alert('Error al consultar permisos: ' + (data.message || 'Desconocido'));
                closeModal('modal-permissions-user');
                return;
            }

            permDataCache = data;
            renderPermissionsModal(data);

        } catch (err) {
            console.error(err);
            alert('Error al comunicarse con el servidor: ' + err.message);
            closeModal('modal-permissions-user');
        }
    }

    function renderPermissionsModal(data) {
        const user = data.user;
        const modules = data.modules;
        const assigned = data.assigned_permissions;
        const effective = data.effective_permissions || [];
        const defaults = data.default_operator || [];
        const isDefault = data.user.is_default;
        
        // Header
        const badgeRole = document.getElementById('perm-user-role-badge');
        badgeRole.innerText = user.role.toUpperCase();
        if (user.is_admin) {
            badgeRole.className = 'px-2 py-0.5 rounded text-[9.5px] uppercase font-mono font-bold bg-purple-950 border border-purple-500/40 text-purple-300';
            document.getElementById('perm-admin-banner').classList.remove('hidden');
        } else {
            badgeRole.className = 'px-2 py-0.5 rounded text-[9.5px] uppercase font-mono font-bold bg-blue-950 border border-blue-500/40 text-blue-300';
            document.getElementById('perm-admin-banner').classList.add('hidden');
        }

        const usernameText = user.username ? ` • LDAP: ${user.username}` : ' • Cuenta Local';
        document.getElementById('perm-user-subtitle').innerText = `${user.name} (${user.email})${usernameText}`;

        // Render Modules
        const listEl = document.getElementById('perm-modules-list');
        let html = '';

        Object.keys(modules).forEach(modKey => {
            const mod = modules[modKey];
            const permEntries = Object.entries(mod.permissions);
            
            html += `
                <div class="perm-module-card p-4 rounded-xl border border-obsidian-border bg-obsidian-panel/90 space-y-3" data-module-key="${modKey}">
                    <div class="flex items-center justify-between border-b border-obsidian-border/70 pb-2">
                        <div class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-lg text-obsidian-cyan">${mod.icon || 'folder'}</span>
                            <div>
                                <h4 class="text-xs font-bold text-white uppercase font-mono tracking-wider">${escapeHtml(mod.name)}</h4>
                                <p class="text-[10px] text-obsidian-muted">${escapeHtml(mod.description)}</p>
                            </div>
                        </div>
                        <span id="perm-mod-count-${modKey}" class="px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-obsidian-card border border-obsidian-border text-cyan-300">
                            0 / ${permEntries.length}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
            `;

            permEntries.forEach(([permKey, perm]) => {
                const isChecked = effective.includes(permKey);
                const isDefaultOp = !!perm.default_operator;

                html += `
                    <div class="perm-item p-2.5 rounded-lg border border-obsidian-border/70 bg-obsidian-card/60 hover:bg-obsidian-card hover:border-obsidian-cyan/40 transition flex items-start justify-between gap-3" data-perm-key="${permKey}" data-perm-text="${escapeHtml(perm.name + ' ' + perm.description + ' ' + permKey).toLowerCase()}">
                        <div class="space-y-1 min-w-0 pr-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="font-bold text-white text-[11px]">${escapeHtml(perm.name)}</span>
                                ${isDefaultOp ? '<span class="px-1.5 py-0.2 rounded text-[8px] font-mono bg-cyan-950/70 border border-cyan-500/30 text-cyan-300">Defecto Operador</span>' : ''}
                            </div>
                            <p class="text-[10px] text-obsidian-muted leading-tight">${escapeHtml(perm.description)}</p>
                            <span class="inline-block text-[8.5px] font-mono text-obsidian-cyan/70">${permKey}</span>
                        </div>
                        <div class="shrink-0 pt-0.5">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" value="${permKey}" class="sr-only peer perm-checkbox" onchange="onPermToggle()" ${isChecked ? 'checked' : ''} ${user.email === 'britojq@gmail.com' ? 'disabled' : ''}>
                                <div class="w-8 h-4 bg-obsidian-panel peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[1px] after:left-[1px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all border border-obsidian-border peer-checked:bg-amber-500 peer-checked:border-amber-400"></div>
                            </label>
                        </div>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        listEl.innerHTML = html;
        document.getElementById('perm-loading').classList.add('hidden');
        listEl.classList.remove('hidden');

        updatePermissionCounters();
    }

    function onPermToggle() {
        permIsResetToDefault = false;
        updatePermissionCounters();
    }

    function updatePermissionCounters() {
        if (!permDataCache) return;
        const checkboxes = document.querySelectorAll('.perm-checkbox');
        const checked = document.querySelectorAll('.perm-checkbox:checked');
        const total = checkboxes.length;
        const totalChecked = checked.length;

        document.getElementById('perm-count-badge').innerText = `${totalChecked} / ${total}`;

        // Actualizar por módulo
        const modules = permDataCache.modules;
        Object.keys(modules).forEach(modKey => {
            const card = document.querySelector(`.perm-module-card[data-module-key="${modKey}"]`);
            if (card) {
                const modBoxes = card.querySelectorAll('.perm-checkbox');
                const modChecked = card.querySelectorAll('.perm-checkbox:checked');
                const badge = document.getElementById(`perm-mod-count-${modKey}`);
                if (badge) {
                    badge.innerText = `${modChecked.length} / ${modBoxes.length}`;
                    if (modChecked.length > 0) {
                        badge.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-cyan-950 border border-cyan-500/40 text-cyan-300';
                    } else {
                        badge.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-obsidian-card border border-obsidian-border text-obsidian-muted';
                    }
                }
            }
        });

        const statusNote = document.getElementById('perm-status-note');
        if (permIsResetToDefault) {
            statusNote.innerText = '(Configurado a valores por defecto)';
        } else {
            statusNote.innerText = '(Modo personalizado)';
        }
    }

    function filterPermissions(query) {
        const q = (query || '').toLowerCase().trim();
        const cards = document.querySelectorAll('.perm-module-card');

        cards.forEach(card => {
            const items = card.querySelectorAll('.perm-item');
            let hasVisibleItem = false;

            items.forEach(item => {
                const text = item.getAttribute('data-perm-text') || '';
                if (!q || text.includes(q)) {
                    item.classList.remove('hidden');
                    hasVisibleItem = true;
                } else {
                    item.classList.add('hidden');
                }
            });

            if (hasVisibleItem) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    }

    function resetPermissionsToDefault() {
        if (!permDataCache) return;
        const defaults = permDataCache.default_operator || [];
        const checkboxes = document.querySelectorAll('.perm-checkbox');

        checkboxes.forEach(cb => {
            cb.checked = defaults.includes(cb.value);
        });

        permIsResetToDefault = true;
        updatePermissionCounters();

        const alertEl = document.getElementById('perm-alert-msg');
        alertEl.className = 'p-2.5 rounded-lg border border-cyan-500/50 bg-cyan-950/40 text-cyan-300 text-xs font-mono flex items-center gap-2';
        alertEl.innerHTML = '<span class="material-symbols-outlined text-base">info</span><span>Se aplicó la plantilla de permisos estándar para el rol Operador. Presione "Guardar Permisos" para confirmar.</span>';
        alertEl.classList.remove('hidden');
    }

    function grantAllPermissions() {
        const checkboxes = document.querySelectorAll('.perm-checkbox');
        checkboxes.forEach(cb => cb.checked = true);
        permIsResetToDefault = false;
        updatePermissionCounters();
    }

    function revokeAllPermissions() {
        const checkboxes = document.querySelectorAll('.perm-checkbox');
        checkboxes.forEach(cb => cb.checked = false);
        permIsResetToDefault = false;
        updatePermissionCounters();
    }

    async function savePermissions() {
        if (!currentPermUserId) return;
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const token = tokenMeta ? tokenMeta.getAttribute('content') : '{{ csrf_token() }}';
        const btn = document.getElementById('btn-save-permissions');
        const btnText = document.getElementById('btn-save-permissions-text');
        const alertEl = document.getElementById('perm-alert-msg');

        const checkedBoxes = document.querySelectorAll('.perm-checkbox:checked');
        const selected = Array.from(checkedBoxes).map(cb => cb.value);

        btn.disabled = true;
        btnText.innerText = 'Guardando...';
        alertEl.classList.add('hidden');

        try {
            const url = `/admin/users/${currentPermUserId}/permissions`;
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    permissions: selected,
                    reset_to_default: permIsResetToDefault
                })
            });

            const data = await res.json();
            btn.disabled = false;
            btnText.innerText = 'Guardar Permisos';

            if (data.success) {
                alertEl.className = 'p-2.5 rounded-lg border border-emerald-500/50 bg-emerald-950/40 text-emerald-300 text-xs font-mono flex items-center justify-between';
                alertEl.innerHTML = `
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">check_circle</span>
                        <span>${escapeHtml(data.message)}</span>
                    </div>
                `;
                alertEl.classList.remove('hidden');

                // Actualizar la insignia en la tabla principal
                const tableBadge = document.getElementById(`badge-perm-${currentPermUserId}`);
                if (tableBadge) {
                    if (data.is_default) {
                        tableBadge.className = 'inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-mono bg-slate-900/90 border border-slate-700 text-slate-400';
                        tableBadge.title = 'Permisos estándar del rol operador';
                        tableBadge.innerHTML = '<span class="material-symbols-outlined text-[10px]">verified</span>Por Defecto';
                    } else {
                        tableBadge.className = 'inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-mono bg-amber-950/70 border border-amber-500/50 text-amber-300';
                        tableBadge.title = `${selected.length} permisos personalizados asignados`;
                        tableBadge.innerHTML = `<span class="material-symbols-outlined text-[10px]">shield_person</span>Personalizado (${selected.length})`;
                    }
                }

                setTimeout(() => {
                    closeModal('modal-permissions-user');
                }, 1200);

            } else {
                alertEl.className = 'p-2.5 rounded-lg border border-red-500/50 bg-red-950/40 text-red-300 text-xs font-mono';
                alertEl.innerHTML = '❌ ' + escapeHtml(data.message || 'Error al guardar permisos.');
                alertEl.classList.remove('hidden');
            }

        } catch (err) {
            btn.disabled = false;
            btnText.innerText = 'Guardar Permisos';
            alertEl.className = 'p-2.5 rounded-lg border border-red-500/50 bg-red-950/40 text-red-300 text-xs font-mono';
            alertEl.innerHTML = '❌ Error de red: ' + escapeHtml(err.message);
            alertEl.classList.remove('hidden');
        }
    }
</script>
@endsection
