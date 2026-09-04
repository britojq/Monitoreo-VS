@extends('layouts.admin')

@section('page_title', 'Gestión de Usuarios')

@section('admin_content')
<div class="space-y-6">
    <!-- CABECERA Y BOTÓN CREAR -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-white">Usuarios del Sistema</h2>
            <p class="text-xs font-mono text-obsidian-muted">Administra los accesos y roles autorizados para el monitoreo</p>
        </div>
        <button onclick="openModal('modal-create-user')" class="px-4 py-2.5 rounded-lg bg-obsidian-cyan text-black font-bold text-xs font-mono uppercase flex items-center gap-2 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20">
            <span class="material-symbols-outlined text-base">person_add</span>
            Crear Nuevo Usuario
        </button>
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
                                    <div class="w-8 h-8 rounded-full bg-obsidian-cyan/20 border border-obsidian-cyan/40 text-obsidian-cyan flex items-center justify-center font-mono text-xs shrink-0">
                                        {{ strtoupper(substr($u->name, 0, 2)) }}
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
                                <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase {{ $u->role === 'admin' ? 'bg-purple-950/70 text-purple-400 border border-purple-500/40' : 'bg-blue-950/70 text-blue-400 border border-blue-500/40' }}">
                                    {{ $u->role }}
                                </span>
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
                            <td class="px-6 py-4 text-right space-x-2">
                                <button onclick="openEditUserModal({{ json_encode($u) }})" class="p-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition" title="Editar">
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
                <input type="email" name="email" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan" placeholder="usuario@corpoelec.gob.ve"/>
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
                    Usuario federado: <code id="ldap-notice-username" class="text-obsidian-cyan font-bold"></code>. Su contraseña reside exclusivamente en el Directorio Activo de Corpoelec y no se administra en este portal.
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

<script>
    function openModal(id) {
        const m = document.getElementById(id);
        m.classList.remove('hidden');
        m.classList.add('flex');
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
</script>
@endsection
