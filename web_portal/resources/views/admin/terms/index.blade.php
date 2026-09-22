@extends('layouts.admin')

@section('page_title', 'Términos de Uso & Seguridad')

@section('admin_content')
<div class="space-y-6">
    <!-- CABECERA -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition text-xs font-mono font-semibold group shadow-sm">
                    <span class="material-symbols-outlined text-sm group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Dashboard
                </a>
                <span class="text-obsidian-border font-mono text-xs">/</span>
                <span class="text-xs font-mono text-obsidian-muted">Seguridad Institucional</span>
            </div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan">verified_user</span>
                Aceptación de Términos de Uso & Custodia de Registros
            </h2>
            <p class="text-xs font-mono text-obsidian-muted">Supervisión, auditoría y control de consentimiento legal para usuarios del Portal y Bot de Monitoreo</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="toggleTermsPreviewModal(true)" class="px-3.5 py-2 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-text hover:text-white hover:border-cyan-500/50 transition font-mono text-xs font-bold flex items-center gap-2 cursor-pointer shadow-sm">
                <span class="material-symbols-outlined text-base text-cyan-400">visibility</span>
                <span>Ver Texto Legal</span>
            </button>

            <form action="{{ route('admin.terms.resetAll') }}" method="POST" onsubmit="return confirm('¿Está seguro de que desea reiniciar la aceptación de términos para TODOS los usuarios? Deberán aceptarlos obligatoriamente en su próximo inicio de sesión.');">
                @csrf
                <button type="submit" class="px-3.5 py-2 rounded-xl bg-amber-950/40 border border-amber-500/50 text-amber-300 hover:bg-amber-600 hover:text-black transition font-mono text-xs font-bold flex items-center gap-2 cursor-pointer shadow-sm">
                    <span class="material-symbols-outlined text-base">restart_alt</span>
                    <span>Exigir Nueva Firma a Todos</span>
                </button>
            </form>
        </div>
    </div>

    <!-- TARJETAS KPI -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- TOTAL USUARIOS -->
        <div class="glass-card rounded-xl p-4 border border-obsidian-border flex items-center justify-between">
            <div>
                <p class="text-[11px] font-mono text-obsidian-muted uppercase tracking-wider">Total Usuarios</p>
                <p class="text-2xl font-bold font-mono text-white mt-1">{{ number_format($totalUsers) }}</p>
                <p class="text-[10px] font-mono text-obsidian-muted/80 mt-0.5">Cuentas registradas</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-obsidian-panel border border-obsidian-border flex items-center justify-center text-obsidian-cyan">
                <span class="material-symbols-outlined text-2xl">group</span>
            </div>
        </div>

        <!-- TÉRMINOS ACEPTADOS -->
        <div class="glass-card rounded-xl p-4 border border-obsidian-border flex items-center justify-between">
            <div>
                <p class="text-[11px] font-mono text-obsidian-muted uppercase tracking-wider">Términos Aceptados</p>
                <p class="text-2xl font-bold font-mono text-emerald-400 mt-1">{{ number_format($acceptedCount) }}</p>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <div class="w-16 h-1.5 bg-obsidian-panel rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-400 rounded-full" style="width: {{ $acceptanceRate }}%"></div>
                    </div>
                    <span class="text-[10px] font-mono text-emerald-400 font-bold">{{ $acceptanceRate }}%</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-950/50 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                <span class="material-symbols-outlined text-2xl">task_alt</span>
            </div>
        </div>

        <!-- PENDIENTES POR ACEPTAR -->
        <div class="glass-card rounded-xl p-4 border border-obsidian-border flex items-center justify-between">
            <div>
                <p class="text-[11px] font-mono text-obsidian-muted uppercase tracking-wider">Pendientes de Firma</p>
                <p class="text-2xl font-bold font-mono {{ $pendingCount > 0 ? 'text-amber-400' : 'text-white' }} mt-1">{{ number_format($pendingCount) }}</p>
                <p class="text-[10px] font-mono {{ $pendingCount > 0 ? 'text-amber-500/80' : 'text-obsidian-muted/80' }} mt-0.5">Bloqueados hasta aceptar</p>
            </div>
            <div class="w-12 h-12 rounded-xl {{ $pendingCount > 0 ? 'bg-amber-950/50 border border-amber-500/30 text-amber-400' : 'bg-obsidian-panel border border-obsidian-border text-obsidian-muted' }} flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">pending_actions</span>
            </div>
        </div>

        <!-- MARCO LEGAL ACTIVO -->
        <div class="glass-card rounded-xl p-4 border border-obsidian-border flex items-center justify-between">
            <div>
                <p class="text-[11px] font-mono text-obsidian-muted uppercase tracking-wider">Marco Jurídico</p>
                <p class="text-xs font-bold font-mono text-cyan-300 mt-1">Ley Delitos Informáticos</p>
                <p class="text-[10px] font-mono text-cyan-400/70 mt-0.5">Arts. 6, 7, 8, 9, 10, 11, 13</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-cyan-950/50 border border-cyan-500/30 flex items-center justify-center text-cyan-300">
                <span class="material-symbols-outlined text-2xl">gavel</span>
            </div>
        </div>
    </div>

    <!-- TARJETA INFORMATIVA / AVISO DE CUSTODIO DE REGISTROS -->
    <div class="glass-panel rounded-2xl p-5 border border-cyan-500/30 bg-[#061426]/90 relative overflow-hidden shadow-lg">
        <div class="absolute -right-8 -bottom-8 w-44 h-44 bg-cyan-500/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-cyan-500/20 to-blue-500/20 border border-cyan-500/40 flex items-center justify-center text-cyan-300 shrink-0 shadow-md">
                    <span class="material-symbols-outlined text-2xl">security</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-white tracking-tight font-mono">PROTOCOLO DE SEGURIDAD & CONSENTIMIENTO OBLIGATORIO</h3>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-mono bg-cyan-950/80 text-cyan-300 border border-cyan-500/30 font-bold">VIGENTE v1.0</span>
                    </div>
                    <p class="text-xs text-obsidian-muted font-mono mt-1 leading-relaxed">
                        Todo usuario que inicia sesión (vía base de datos local o Directorio Activo LDAP) debe confirmar de manera obligatoria la lectura y acatamiento de la custodia de registros y el aviso legal antes de interactuar con el sistema.
                    </p>
                </div>
            </div>
            <div class="shrink-0">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#040c17] border border-cyan-500/30 text-[11px] font-mono text-cyan-200">
                    <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                    Auditoría Automática Activa
                </span>
            </div>
        </div>
    </div>

    <!-- TABLA DE CONTROL DE USUARIOS Y FIRMAS -->
    <div class="glass-panel rounded-2xl border border-obsidian-border overflow-hidden shadow-xl">
        <!-- BARRA DE FILTROS -->
        <div class="p-4 border-b border-obsidian-border bg-[#07172b]/80">
            <form action="{{ route('admin.terms.index') }}" method="GET" class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2.5 flex-1 min-w-[280px]">
                    <!-- BÚSQUEDA -->
                    <div class="relative flex-1 min-w-[200px] max-w-sm">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-obsidian-muted text-sm">search</span>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre, usuario o email..." 
                               class="w-full bg-[#051424] border border-obsidian-border rounded-xl pl-9 pr-3 py-2 text-xs text-white placeholder-obsidian-muted focus:outline-none focus:border-obsidian-cyan font-mono transition">
                    </div>

                    <!-- FILTRO POR ESTADO -->
                    <select name="status" onchange="this.form.submit()" class="bg-[#051424] border border-obsidian-border rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-obsidian-cyan transition">
                        <option value="">Todos los Estados</option>
                        <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>✅ Aceptados</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>⏳ Pendientes</option>
                    </select>

                    <!-- FILTRO POR ROL -->
                    <select name="role" onchange="this.form.submit()" class="bg-[#051424] border border-obsidian-border rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-obsidian-cyan transition">
                        <option value="all">Todos los Roles</option>
                        <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Administradores</option>
                        <option value="operator" {{ request('role') === 'operator' ? 'selected' : '' }}>Operadores</option>
                    </select>

                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">filter_alt</span>
                        Filtrar
                    </button>

                    @if(request()->hasAny(['search', 'status', 'role']))
                        <a href="{{ route('admin.terms.index') }}" class="px-3 py-2 rounded-xl bg-red-950/40 border border-red-500/40 text-red-300 hover:text-white font-mono text-xs transition flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">close</span>
                            Limpiar
                        </a>
                    @endif
                </div>

                <div class="text-[11px] font-mono text-obsidian-muted">
                    Mostrando {{ $users->firstItem() ?? 0 }} - {{ $users->lastItem() ?? 0 }} de {{ $users->total() }} usuarios
                </div>
            </form>
        </div>

        <!-- TABLA -->
        <div class="overflow-x-auto custom-scroll">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-[#051424]/90 text-obsidian-muted uppercase text-[10px] tracking-wider border-b border-obsidian-border">
                    <tr>
                        <th class="px-5 py-3.5">Usuario / Personal</th>
                        <th class="px-4 py-3.5">Rol Institucional</th>
                        <th class="px-4 py-3.5">Estado de Consentimiento</th>
                        <th class="px-4 py-3.5">Fecha y Hora de Firma</th>
                        <th class="px-4 py-3.5">IP de Confirmación</th>
                        <th class="px-4 py-3.5">Versión</th>
                        <th class="px-5 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/50">
                    @forelse($users as $usr)
                        <tr class="hover:bg-obsidian-panel/40 transition">
                            <!-- USUARIO -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-obsidian-cyan/20 to-obsidian-purple/30 border border-obsidian-cyan/30 text-obsidian-cyan font-bold flex items-center justify-center text-xs shadow-sm overflow-hidden shrink-0">
                                        @if($usr->avatar_url)
                                            <img src="{{ $usr->avatar_url }}" alt="{{ $usr->name }}" class="w-full h-full object-cover">
                                        @else
                                            {{ strtoupper(substr($usr->name, 0, 2)) }}
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-bold text-white">{{ $usr->name }}</p>
                                        <div class="flex items-center gap-1.5 text-[11px] text-obsidian-muted">
                                            @if($usr->username)
                                                <span class="px-1.5 py-0.2 rounded bg-cyan-950/60 text-cyan-300 border border-cyan-500/30 text-[10px]">LDAP: {{ $usr->username }}</span>
                                            @endif
                                            <span>{{ $usr->email }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- ROL -->
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $usr->isAdmin() ? 'bg-red-950/60 text-red-300 border border-red-500/40' : 'bg-cyan-950/60 text-cyan-300 border border-cyan-500/40' }}">
                                    {{ strtoupper($usr->role) }}
                                </span>
                            </td>

                            <!-- ESTADO -->
                            <td class="px-4 py-3.5">
                                @if($usr->hasAcceptedTerms())
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-950/60 text-emerald-400 border border-emerald-500/40 text-[11px] font-bold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        ACEPTADO
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-950/60 text-amber-300 border border-amber-500/40 text-[11px] font-bold animate-pulse">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                        PENDIENTE
                                    </span>
                                @endif
                            </td>

                            <!-- FECHA Y HORA -->
                            <td class="px-4 py-3.5">
                                @if($usr->terms_accepted_at)
                                    <span class="text-white font-semibold">{{ $usr->terms_accepted_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') }}</span>
                                    <span class="block text-[10px] text-obsidian-muted">{{ $usr->terms_accepted_at->diffForHumans() }}</span>
                                @else
                                    <span class="text-obsidian-muted italic">Sin registro aún</span>
                                @endif
                            </td>

                            <!-- IP DE CONFIRMACIÓN -->
                            <td class="px-4 py-3.5">
                                @if($usr->terms_accepted_ip)
                                    <code class="px-2 py-0.5 rounded bg-[#030914] text-cyan-300 border border-cyan-500/20 text-[11px]">{{ $usr->terms_accepted_ip }}</code>
                                @else
                                    <span class="text-obsidian-muted/60">—</span>
                                @endif
                            </td>

                            <!-- VERSIÓN -->
                            <td class="px-4 py-3.5">
                                <span class="px-2 py-0.5 rounded bg-obsidian-panel border border-obsidian-border text-obsidian-text text-[10px]">
                                    v{{ $usr->terms_version ?? '1.0' }}
                                </span>
                            </td>

                            <!-- ACCIONES -->
                            <td class="px-5 py-3.5 text-right">
                                @if($usr->hasAcceptedTerms())
                                    <form action="{{ route('admin.terms.reset', $usr) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Desea revocar la aceptación del usuario {{ $usr->name }} para que vuelva a firmar en su próximo inicio de sesión?');">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 rounded-lg bg-amber-950/40 border border-amber-500/40 text-amber-300 hover:bg-amber-600 hover:text-black transition text-[11px] font-semibold flex items-center gap-1 ml-auto cursor-pointer" title="Forzar re-aceptación en próximo login">
                                            <span class="material-symbols-outlined text-sm">refresh</span>
                                            <span>Exigir Nueva Firma</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="text-[11px] text-amber-400 font-semibold italic">Aparición activa en login</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-obsidian-muted font-mono">
                                <span class="material-symbols-outlined text-4xl block mx-auto text-obsidian-muted/40 mb-2">person_off</span>
                                No se encontraron usuarios coincidentes con los filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN -->
        @if($users->hasPages())
            <div class="p-4 border-t border-obsidian-border bg-[#07172b]/50">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL DE PREVISUALIZACIÓN DE TEXTO LEGAL Y LINEAMIENTOS -->
<!-- ========================================================================= -->
<div id="terms-preview-modal" class="fixed inset-0 z-50 hidden transition-opacity duration-300">
    <div onclick="toggleTermsPreviewModal(false)" class="fixed inset-0 bg-black/80 backdrop-blur-sm cursor-pointer"></div>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto pointer-events-none">
        <div class="w-full max-w-2xl glass-panel rounded-2xl border border-cyan-500/40 bg-[#07172b]/98 p-6 shadow-2xl relative text-left space-y-5 animate-in fade-in zoom-in-95 duration-200 pointer-events-auto custom-scroll max-h-[90vh] overflow-y-auto">
            <!-- Botón Cerrar -->
            <button type="button" onclick="toggleTermsPreviewModal(false)" class="absolute top-4 right-4 text-obsidian-muted hover:text-white transition text-xl leading-none cursor-pointer" title="Cerrar ventana">
                &times;
            </button>

            <!-- Cabecera -->
            <div class="flex items-center gap-3 border-b border-obsidian-border pb-4">
                <div class="w-10 h-10 rounded-xl bg-cyan-500/20 border border-cyan-500/40 flex items-center justify-center text-cyan-300 glow-cyan">
                    <span class="material-symbols-outlined text-2xl">gavel</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white tracking-tight">TEXTO OFICIAL DE LINEAMIENTOS Y MARCO JURÍDICO</h3>
                    <p class="text-xs font-mono text-obsidian-cyan">SISTEMA DE MONITOREO & ASISTENTE VIRTUAL • ATIT VALLE SECO</p>
                </div>
            </div>

            <!-- Cuerpo del texto legal -->
            <div class="space-y-4 font-mono text-xs leading-relaxed text-gray-200">
                <!-- ADVERTENCIA DE SEGURIDAD -->
                <div class="p-4 rounded-xl bg-amber-950/40 border border-amber-500/40 space-y-2">
                    <div class="flex items-center gap-2 text-amber-400 font-bold text-sm">
                        <span class="material-symbols-outlined text-lg">warning</span>
                        <span>ADVERTENCIA DE SEGURIDAD</span>
                    </div>
                    <p class="text-amber-200/90">
                        Este Sistema y su Asistente/Bot están protegidos por un <b>Custodio de Registros</b>.
                    </p>
                    <p class="text-amber-200/90">
                        Toda la información contenida y procesada por este sistema es de carácter <b>Confidencial</b> y se encuentra amparada bajo estrictos protocolos de privacidad y protección de datos institucionales.
                    </p>
                </div>

                <!-- AVISO LEGAL -->
                <div class="p-4 rounded-xl bg-red-950/30 border border-red-500/40 space-y-3">
                    <div class="flex items-center gap-2 text-red-400 font-bold text-sm">
                        <span class="material-symbols-outlined text-lg">policy</span>
                        <span>AVISO LEGAL & RESPONSABILIDAD PENAL</span>
                    </div>
                    <p class="text-gray-200">
                        Se registran y almacenan de forma continua los datos (<b>ID de Usuario, Nombre, Dirección IP de Origen, Fecha, Hora, Acciones y Mensajes enviados</b>) en nuestros servidores para fines de auditoría, trazabilidad y seguridad institucional.
                    </p>
                    <p class="text-gray-200 bg-[#030914] p-3 rounded-lg border border-red-500/30">
                        ⚖️ Cualquier <b>ACCESO NO AUTORIZADO</b>, intento de intrusión, interceptación, vulneración de contraseñas o uso indebido de la información será sancionado conforme a lo establecido en la <b>Ley Contra los Delitos Informáticos</b> (Capítulos I y II, artículos 6, 7, 8, 9, 10, 11 y 13).
                    </p>
                    <p class="text-red-300">
                        Si usted no cuenta con autorización expresa para acceder a este sistema o utilizar sus servicios, desconéctese y cierre su sesión inmediatamente.
                    </p>
                    <p class="text-obsidian-cyan text-[11px] italic">
                        La permanencia, navegación o utilización de este portal constituye la aceptación plena e incondicional de los términos y condiciones aquí expuestos.
                    </p>
                </div>
            </div>

            <div class="pt-2 border-t border-obsidian-border flex justify-end">
                <button type="button" onclick="toggleTermsPreviewModal(false)" class="px-5 py-2 rounded-xl bg-obsidian-cyan text-black font-bold font-mono text-xs hover:bg-cyan-300 transition cursor-pointer">
                    Entendido / Cerrar Previsualización
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleTermsPreviewModal(show) {
        const modal = document.getElementById('terms-preview-modal');
        if (!modal) return;
        if (show) {
            modal.classList.remove('hidden');
        } else {
            modal.classList.add('hidden');
        }
    }
</script>
@endsection
