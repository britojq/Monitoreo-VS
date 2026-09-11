@extends('layouts.admin')

@section('page_title', 'Mi Perfil de Usuario')

@section('admin_content')
<div class="space-y-6 max-w-6xl mx-auto">
    <!-- CABECERA DE PERFIL -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-xs font-mono font-semibold group shadow-sm">
                    <span class="material-symbols-outlined text-sm group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-xs font-mono text-obsidian-cyan">Mi Cuenta</span>
            </div>
            <h2 class="text-xl font-bold text-white tracking-tight">Perfil de Usuario</h2>
            <p class="text-xs font-mono text-obsidian-muted">
                @if($user->isLdapUser())
                    Cuenta asociada mediante <span class="text-obsidian-cyan font-semibold">Directorio Activo Corporativo LDAP</span> (Corporativo)
                @else
                    Cuenta administrada localmente en el portal de monitoreo
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-lg text-xs font-mono font-bold uppercase {{ $user->role === 'admin' ? 'bg-purple-950/80 text-purple-300 border border-purple-500/40' : 'bg-blue-950/80 text-blue-300 border border-blue-500/40' }}">
                Rol: {{ $user->role === 'admin' ? 'Administrador' : 'Operador' }}
            </span>
            <span class="px-3 py-1.5 rounded-lg text-xs font-mono font-bold flex items-center gap-1.5 {{ $user->is_active ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/40' : 'bg-red-950/80 text-red-300 border border-red-500/40' }}">
                <span class="w-2 h-2 rounded-full {{ $user->is_active ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                {{ $user->is_active ? 'Activo' : 'Suspendido' }}
            </span>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-red-950/50 border border-red-500/50 text-red-300 text-xs font-mono space-y-1">
            <div class="flex items-center gap-2 font-bold mb-1">
                <span class="material-symbols-outlined text-base">error</span>
                <span>Se encontraron los siguientes errores:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-[11px]">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- COLUMNA IZQUIERDA: TARJETA DE IDENTIDAD Y FOTO DE PERFIL -->
        <div class="glass-card rounded-2xl p-6 border border-obsidian-border flex flex-col items-center text-center space-y-5">
            <!-- CONTENEDOR DE FOTO / AVATAR -->
            <div class="relative group">
                <div id="avatar-container" class="w-36 h-36 rounded-full bg-gradient-to-tr from-obsidian-cyan/30 to-obsidian-purple/40 border-2 border-obsidian-cyan/50 text-obsidian-cyan font-bold flex items-center justify-center text-3xl shadow-xl shadow-cyan-500/10 overflow-hidden relative">
                    @if($user->avatar_url)
                        <img id="avatar-preview-img" src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                        <span id="avatar-preview-initials" class="hidden">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                    @else
                        <img id="avatar-preview-img" src="" alt="Vista previa" class="w-full h-full object-cover hidden">
                        <span id="avatar-preview-initials">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                    @endif
                </div>

                <!-- BADGE TIPO DE CUENTA EN AVATAR -->
                <div class="absolute bottom-1 right-1 p-1.5 rounded-full {{ $user->isLdapUser() ? 'bg-cyan-900 border border-cyan-400 text-cyan-300' : 'bg-amber-900 border border-amber-400 text-amber-300' }} shadow-lg" title="{{ $user->isLdapUser() ? 'Autenticación LDAP Corporativa' : 'Cuenta Local' }}">
                    <span class="material-symbols-outlined text-sm block">
                        {{ $user->isLdapUser() ? 'badge' : 'vpn_key' }}
                    </span>
                </div>
            </div>

            <!-- DETALLES BÁSICOS -->
            <div class="space-y-1">
                <h3 class="text-base font-bold text-white">{{ $user->name }}</h3>
                <p class="text-xs font-mono text-obsidian-muted">{{ $user->email }}</p>
                <div class="pt-1">
                    @if($user->isLdapUser())
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-[11px] font-mono bg-cyan-950/90 border border-cyan-500/50 text-cyan-300 font-semibold">
                            <span class="material-symbols-outlined text-[13px]">badge</span>
                            UID LDAP: {{ $user->username }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-[11px] font-mono bg-amber-950/90 border border-amber-500/50 text-amber-300 font-semibold">
                            <span class="material-symbols-outlined text-[13px]">lock</span>
                            Cuenta Local del Sistema
                        </span>
                    @endif
                </div>
            </div>

            <!-- FORMULARIO DE GESTIÓN DE FOTO DE PERFIL -->
            <form action="{{ route('admin.profile.update') }}" method="POST" enctype="multipart/form-data" class="w-full space-y-3 pt-2 border-t border-obsidian-border/80">
                @csrf
                <div>
                    <label class="block text-[11px] font-mono uppercase text-obsidian-muted mb-2">
                        Cambiar Fotografía de Perfil
                    </label>
                    <input type="file" name="avatar" id="avatar_input" accept="image/png, image/jpeg, image/jpg, image/webp" class="hidden" onchange="previewAvatar(this)"/>
                    <button type="button" onclick="document.getElementById('avatar_input').click()" class="w-full py-2 px-3 rounded-lg bg-obsidian-panel border border-obsidian-border hover:border-obsidian-cyan text-obsidian-text hover:text-white font-mono text-xs font-semibold flex items-center justify-center gap-2 transition cursor-pointer">
                        <span class="material-symbols-outlined text-base text-obsidian-cyan">add_a_photo</span>
                        <span id="btn-file-label">Seleccionar Imagen</span>
                    </button>
                    <p class="text-[10px] font-mono text-obsidian-muted/70 mt-1.5">Formatos: JPG, PNG, WEBP (Máx. 2MB)</p>
                </div>

                <div id="avatar-submit-container" class="hidden pt-1">
                    <button type="submit" class="w-full py-2 px-4 rounded-lg bg-obsidian-cyan text-black font-mono text-xs font-bold uppercase flex items-center justify-center gap-2 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20 cursor-pointer">
                        <span class="material-symbols-outlined text-base">save</span>
                        Guardar Foto de Perfil
                    </button>
                </div>
            </form>

            @if($user->avatar)
                <!-- FORMULARIO ELIMINAR FOTO -->
                <form action="{{ route('admin.profile.update') }}" method="POST" onsubmit="return confirm('¿Desea eliminar su foto de perfil actual y volver a las iniciales por defecto?');" class="w-full">
                    @csrf
                    <input type="hidden" name="remove_avatar" value="1"/>
                    <button type="submit" class="w-full py-1.5 px-3 rounded-lg bg-red-950/40 border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white font-mono text-[11px] transition flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">delete</span>
                        Eliminar Foto de Perfil
                    </button>
                </form>
            @endif

            <!-- METADATOS DE ACCESO -->
            <div class="w-full text-left font-mono text-[11px] bg-obsidian-panel/60 p-3.5 rounded-xl border border-obsidian-border/80 space-y-1.5 text-obsidian-muted">
                <div class="flex items-center justify-between">
                    <span>Último Acceso:</span>
                    <span class="text-white">{{ $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i') : 'Primer Inicio' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>IP Registrada:</span>
                    <span class="text-white font-mono">{{ $user->last_login_ip ?? '127.0.0.1' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Fecha de Alta:</span>
                    <span class="text-white">{{ $user->created_at ? $user->created_at->format('Y-m-d') : 'N/D' }}</span>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: DATOS DEL USUARIO Y SEGURIDAD -->
        <div class="lg:col-span-2 space-y-6">
            @if($user->isLdapUser())
                <!-- ========================================== -->
                <!-- VISTA PARA USUARIOS DE ORIGEN LDAP         -->
                <!-- ========================================== -->
                <div class="glass-card rounded-2xl p-6 border border-cyan-500/40 space-y-6">
                    <!-- AVISO POLÍTICA DE SEGURIDAD LDAP -->
                    <div class="p-4 rounded-xl bg-cyan-950/40 border border-cyan-500/40 text-cyan-300 text-xs font-mono space-y-2">
                        <div class="flex items-center gap-2 font-bold text-white text-sm">
                            <span class="material-symbols-outlined text-lg text-obsidian-cyan">verified_user</span>
                            <span>Directorio Activo Corporativo (LDAP)</span>
                        </div>
                        <p class="text-[11px] leading-relaxed text-obsidian-muted">
                            Su cuenta está asociada con los servidores de <strong class="text-white">Corporativo</strong>. Por motivos de seguridad, la contraseña y la información personal (nombre, correo corporativo e identificador) se sincronizan de forma exclusiva desde el servidor de Directorio Activo (<code class="text-obsidian-cyan">LDAP</code>) y <strong class="text-white">no pueden ser modificadas directamente en este portal</strong>.
                        </p>
                        <p class="text-[11px] text-obsidian-muted">
                            💡 En este portal, únicamente tiene permitido colocar o actualizar su <strong class="text-obsidian-cyan">fotografía de perfil</strong> en la sección izquierda.
                        </p>
                    </div>

                    <!-- CAMPOS EN MODO SOLO LECTURA -->
                    <div class="space-y-4 font-mono text-xs">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-obsidian-muted mb-1 flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm text-obsidian-muted">lock</span>
                                    <span>Nombre Completo (LDAP)</span>
                                </label>
                                <input type="text" value="{{ $user->name }}" readonly class="w-full bg-obsidian-panel/50 border border-obsidian-border rounded-lg p-2.5 text-obsidian-muted cursor-not-allowed select-all"/>
                            </div>
                            <div>
                                <label class="block text-obsidian-muted mb-1 flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm text-obsidian-muted">badge</span>
                                    <span>Identificador Único (UID)</span>
                                </label>
                                <input type="text" value="{{ $user->username }}" readonly class="w-full bg-obsidian-panel/50 border border-obsidian-border rounded-lg p-2.5 text-obsidian-cyan font-bold cursor-not-allowed select-all"/>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-obsidian-muted mb-1 flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm text-obsidian-muted">mail</span>
                                    <span>Correo Corporativo</span>
                                </label>
                                <input type="email" value="{{ $user->email }}" readonly class="w-full bg-obsidian-panel/50 border border-obsidian-border rounded-lg p-2.5 text-obsidian-muted cursor-not-allowed select-all"/>
                            </div>
                            <div>
                                <label class="block text-obsidian-muted mb-1 flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm text-obsidian-muted">shield_person</span>
                                    <span>Rol de Seguridad en Monitoreo</span>
                                </label>
                                <input type="text" value="{{ $user->role === 'admin' ? 'Administrador (Control Total)' : 'Operador (Consulta y Monitoreo)' }}" readonly class="w-full bg-obsidian-panel/50 border border-obsidian-border rounded-lg p-2.5 text-white cursor-not-allowed font-bold"/>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-obsidian-panel/40 border border-obsidian-border/60 text-[11px] text-obsidian-muted space-y-1.5">
                            <div class="flex items-center gap-2 text-white font-semibold">
                                <span class="material-symbols-outlined text-base text-amber-400">help_outline</span>
                                <span>¿Requiere modificar su información o restablecer su clave corporativa?</span>
                            </div>
                            <p class="leading-relaxed">
                                Comuníquese con la Dirección de Tecnología o utilice las herramientas institucionales de autoservicio de contraseñas de la corporación.
                            </p>
                        </div>
                    </div>
                </div>

            @else
                <!-- ========================================== -->
                <!-- VISTA PARA CUENTAS LOCALES                 -->
                <!-- ========================================== -->
                <div class="glass-card rounded-2xl p-6 border border-obsidian-border space-y-6">
                    <div class="flex items-center justify-between border-b border-obsidian-border pb-3">
                        <div class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-xl text-obsidian-cyan">manage_accounts</span>
                            <h3 class="text-sm font-bold text-white uppercase font-mono tracking-wide">Editar Información de la Cuenta Local</h3>
                        </div>
                        <span class="text-[11px] font-mono text-amber-400">Cuenta Local</span>
                    </div>

                    <form action="{{ route('admin.profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-4 font-mono text-xs">
                        @csrf

                        <!-- NOMBRE COMPLETO -->
                        <div>
                            <label class="block text-obsidian-muted mb-1">Nombre Completo</label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan transition"/>
                        </div>

                        <!-- CORREO ELECTRÓNICO -->
                        <div>
                            <label class="block text-obsidian-muted mb-1">Correo Electrónico de Contacto / Login</label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan transition"/>
                        </div>

                        <!-- ROL ASIGNADO (Solo informativo, no editable por el propio usuario) -->
                        <div>
                            <label class="block text-obsidian-muted mb-1">Rol de Acceso Asignado</label>
                            <input type="text" value="{{ $user->role === 'admin' ? 'Administrador' : 'Operador' }}" disabled class="w-full bg-obsidian-panel/50 border border-obsidian-border rounded-lg p-2.5 text-obsidian-muted cursor-not-allowed"/>
                            <p class="text-[10px] text-obsidian-muted/70 mt-1">El rol solo puede ser modificado por otro Administrador en la sección de Usuarios.</p>
                        </div>

                        <!-- CAMBIO DE CONTRASEÑA -->
                        <div class="pt-4 border-t border-obsidian-border/80 space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-base text-obsidian-cyan">key</span>
                                <h4 class="text-xs font-bold text-white uppercase">Cambiar Contraseña (Opcional)</h4>
                            </div>
                            <p class="text-[11px] text-obsidian-muted">Deje estos campos en blanco si no desea modificar su contraseña actual.</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                                <div>
                                    <label class="block text-obsidian-muted mb-1">Nueva Contraseña</label>
                                    <input type="password" name="password" minlength="6" placeholder="Mínimo 6 caracteres" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan transition"/>
                                </div>
                                <div>
                                    <label class="block text-obsidian-muted mb-1">Confirmar Nueva Contraseña</label>
                                    <input type="password" name="password_confirmation" minlength="6" placeholder="Repita la nueva contraseña" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan transition"/>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-obsidian-border flex justify-end">
                            <button type="submit" class="px-6 py-2.5 rounded-lg bg-obsidian-cyan text-black font-mono font-bold text-xs uppercase flex items-center gap-2 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20 cursor-pointer">
                                <span class="material-symbols-outlined text-base">save</span>
                                Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            <!-- ============================================================== -->
            <!-- TARJETA COMÚN: FICHA INSTITUCIONAL ATIT (FIRMA DE REPORTES)    -->
            <!-- ============================================================== -->
            <div class="glass-card rounded-2xl p-6 border border-obsidian-cyan/40 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-obsidian-border pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-2xl text-obsidian-cyan">assignment_ind</span>
                        <div>
                            <h3 class="text-sm font-bold text-white uppercase font-mono tracking-wide">Ficha Institucional ATIT / CORPOELEC</h3>
                            <p class="text-[11px] font-mono text-obsidian-muted">Firma oficial adjuntada a reportes de Telegram despachados desde la Web</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded text-[10px] font-mono font-bold uppercase w-fit {{ $user->hasCompleteAtitProfile() ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/40' : 'bg-amber-950/80 text-amber-300 border border-amber-500/40' }}">
                        {{ $user->hasCompleteAtitProfile() ? '✓ Ficha Completa' : '⚠️ Ficha Incompleta' }}
                    </span>
                </div>

                <form action="{{ route('admin.profile.update') }}" method="POST" class="space-y-4 font-mono text-xs">
                    @csrf
                    <input type="hidden" name="atit_profile_only" value="1"/>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- TÍTULO / GRADO ACADÉMICO -->
                        <div>
                            <label class="block text-obsidian-muted mb-1 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm text-obsidian-cyan">school</span>
                                <span>Título / Grado Académico</span>
                            </label>
                            <input type="text" name="academic_title" id="input_academic_title" value="{{ old('academic_title', $user->academic_title) }}" placeholder="Ej: Ing., Lic., T.S.U., Tec." class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan transition" oninput="updateSignaturePreview()"/>
                            <p class="text-[10px] text-obsidian-muted/70 mt-1">Prefijo opcional para su nombre oficial (ej: Ing.).</p>
                        </div>

                        <!-- CÉDULA DE IDENTIDAD -->
                        <div>
                            <label class="block text-obsidian-muted mb-1 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm text-obsidian-cyan">badge</span>
                                <span>Cédula de Identidad (C.I.)</span>
                            </label>
                            <input type="text" name="cedula" id="input_cedula" value="{{ old('cedula', $user->cedula) }}" placeholder="Ej: 11746281" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan transition" oninput="updateSignaturePreview()"/>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- NÚMERO DE PERSONAL / FICHA -->
                        <div>
                            <label class="block text-obsidian-muted mb-1 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm text-obsidian-cyan">pin</span>
                                <span>N° de Personal / Ficha Corpoelec</span>
                            </label>
                            <input type="text" name="personal_number" id="input_personal_number" value="{{ old('personal_number', $user->personal_number) }}" placeholder="Ej: 144306" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan transition" oninput="updateSignaturePreview()"/>
                        </div>

                        <!-- TELÉFONO DE CONTACTO -->
                        <div>
                            <label class="block text-obsidian-muted mb-1 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm text-obsidian-cyan">call</span>
                                <span>Teléfono de Contacto (📱Tlf)</span>
                            </label>
                            <input type="text" name="phone" id="input_phone" value="{{ old('phone', $user->phone) }}" placeholder="Ej: 02423602039 o celular" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2.5 text-white focus:outline-none focus:border-obsidian-cyan transition" oninput="updateSignaturePreview()"/>
                        </div>
                    </div>

                    <!-- PREVISUALIZACIÓN DE FIRMA TELEGRAM EN VIVO -->
                    <div class="mt-4 p-4 rounded-xl bg-black/40 border border-obsidian-border space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-obsidian-cyan font-bold uppercase flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">terminal</span>
                                Previsualización de Firma en Telegram:
                            </span>
                            <span class="text-[10px] text-obsidian-muted font-mono">Actualización en vivo</span>
                        </div>
                        <pre id="signature_preview" class="p-3 rounded-lg bg-obsidian-panel/80 border border-obsidian-border/80 text-emerald-400 font-mono text-xs leading-relaxed whitespace-pre select-all">Personal de  ATIT:
{{ $user->full_title_name }}
C.I: {{ $user->cedula ?? '(Sin cédula)' }}
N° Personal: {{ $user->personal_number ?? '(Sin ficha)' }}
📱Tlf: {{ $user->phone ?? '(Sin teléfono)' }}</pre>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="px-6 py-2.5 rounded-lg bg-obsidian-cyan text-black font-mono font-bold text-xs uppercase flex items-center gap-2 hover:bg-cyan-300 transition shadow-lg shadow-cyan-500/20 cursor-pointer">
                            <span class="material-symbols-outlined text-base">save</span>
                            Guardar Ficha Institucional
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const userRawName = @json($user->name);

    function updateSignaturePreview() {
        const title = (document.getElementById('input_academic_title')?.value || '').trim();
        const ci = (document.getElementById('input_cedula')?.value || '').trim() || '(Sin cédula)';
        const personal = (document.getElementById('input_personal_number')?.value || '').trim() || '(Sin ficha)';
        const phone = (document.getElementById('input_phone')?.value || '').trim() || '(Sin teléfono)';

        let fullName = userRawName;
        if (title && !fullName.toLowerCase().startsWith(title.toLowerCase())) {
            fullName = title + ' ' + fullName;
        }

        const preview = document.getElementById('signature_preview');
        if (preview) {
            preview.textContent = `Personal de  ATIT:\n${fullName}\nC.I: ${ci}\nN° Personal: ${personal}\n📱Tlf: ${phone}`;
        }
    }

    function previewAvatar(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];

            // Validar tamaño máximo de 2MB en cliente
            if (file.size > 2 * 1024 * 1024) {
                alert('La imagen seleccionada supera el límite máximo permitido de 2 MB.');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('avatar-preview-img');
                const initials = document.getElementById('avatar-preview-initials');
                const submitContainer = document.getElementById('avatar-submit-container');
                const label = document.getElementById('btn-file-label');

                img.src = e.target.result;
                img.classList.remove('hidden');
                if (initials) initials.classList.add('hidden');

                label.innerText = file.name.length > 20 ? file.name.substring(0, 18) + '...' : file.name;
                submitContainer.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    }
</script>
@endsection
