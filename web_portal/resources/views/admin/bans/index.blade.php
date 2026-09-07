@extends('layouts.admin')

@section('page_title', 'Seguridad & Gestión de Baneos')

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
                <span class="text-xs font-mono text-obsidian-muted">Baneos & Seguridad</span>
            </div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-red-400">gavel</span>
                Seguridad & Gestión de Baneos
            </h2>
            <p class="text-xs font-mono text-obsidian-muted">Control de acceso, cuentas suspendidas y lista negra de direcciones IP</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-lg bg-red-950/80 border border-red-500/50 text-red-400 text-xs font-mono font-bold flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                Protección Activa
            </span>
        </div>
    </div>

    <!-- MENSAJES FLASH -->
    @if(session('success'))
        <div class="p-3.5 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-300 text-xs font-mono flex items-center gap-2">
            <span class="material-symbols-outlined text-base">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="p-3.5 rounded-xl bg-red-950/80 border border-red-500/50 text-red-300 text-xs font-mono flex items-center gap-2">
            <span class="material-symbols-outlined text-base">error</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-3.5 rounded-xl bg-red-950/80 border border-red-500/50 text-red-300 text-xs font-mono space-y-1">
            @foreach($errors->all() as $err)
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">error</span>
                    <span>{{ $err }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <!-- SECCIÓN 1: USUARIOS BANEADOS -->
    <div class="glass-card rounded-xl overflow-hidden border border-obsidian-border">
        <div class="px-5 py-4 bg-obsidian-panel border-b border-obsidian-border flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-obsidian-cyan">person_off</span>
                <h3 class="text-xs font-mono font-bold text-white uppercase tracking-wider">Cuentas de Usuario Suspendidas ({{ $bannedUsers->count() }})</h3>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-obsidian-panel/80 border-b border-obsidian-border text-obsidian-muted uppercase text-[11px]">
                    <tr>
                        <th class="px-5 py-3.5">ID / Usuario</th>
                        <th class="px-5 py-3.5">Correo</th>
                        <th class="px-5 py-3.5">Rol</th>
                        <th class="px-5 py-3.5">Fecha de Baneo</th>
                        <th class="px-5 py-3.5">Motivo / Incidente</th>
                        <th class="px-5 py-3.5 text-right">Acciones de Desbaneo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/60">
                    @forelse($bannedUsers as $u)
                        <tr class="hover:bg-obsidian-panel/40 transition">
                            <td class="px-5 py-4">
                                <div class="font-sans font-bold text-white">{{ $u->name }}</div>
                                <div class="text-[10px] text-obsidian-muted font-mono">ID: #{{ $u->id }}</div>
                            </td>
                            <td class="px-5 py-4 text-obsidian-cyan">
                                {{ $u->email }}
                            </td>
                            <td class="px-5 py-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-950/80 border border-amber-500/40 text-amber-300">
                                    {{ $u->role }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-obsidian-muted">
                                {{ $u->banned_at ? $u->banned_at->format('Y-m-d H:i:s') : 'Previo' }}
                            </td>
                            <td class="px-5 py-4 text-red-400/90 max-w-xs truncate" title="{{ $u->ban_reason }}">
                                {{ $u->ban_reason ?: 'Cuenta desactivada por la administración' }}
                            </td>
                            <td class="px-5 py-4 text-right space-x-1.5">
                                <!-- DESBANEAR USUARIO -->
                                <form action="{{ route('admin.bans.unban.user', $u->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" onclick="return confirm('¿Confirma reactivar y desbanear la cuenta de {{ $u->name }}?')" class="px-2.5 py-1.5 rounded-lg bg-emerald-950/70 border border-emerald-500/50 text-emerald-400 hover:bg-emerald-500 hover:text-black font-mono text-[11px] font-bold transition flex items-center gap-1 inline-flex shadow-sm" title="Reactivar Usuario">
                                        <span class="material-symbols-outlined text-sm">lock_open</span>
                                        <span>Desbanear Cuenta</span>
                                    </button>
                                </form>

                                <!-- DESBANEAR USUARIO E IPS -->
                                <form action="{{ route('admin.bans.unban.all', $u->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" onclick="return confirm('¿Confirma desbanear a {{ $u->name }} y todas las IPs asociadas a sus incidentes?')" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-[11px] font-bold transition flex items-center gap-1 inline-flex" title="Desbanear Usuario y Desbloquear IPs">
                                        <span class="material-symbols-outlined text-sm">security</span>
                                        <span>Desbanear Todo</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-obsidian-muted font-mono text-xs">
                                <div class="flex flex-col items-center justify-center gap-1.5">
                                    <span class="material-symbols-outlined text-emerald-400 text-3xl">verified_user</span>
                                    <span class="text-white font-semibold">No hay usuarios baneados en este momento</span>
                                    <span class="text-[11px]">Todas las cuentas activas cumplen con las políticas de acceso.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECCIÓN 2: LISTA NEGRA DE DIRECCIONES IP -->
    <div class="glass-card rounded-xl overflow-hidden border border-obsidian-border">
        <div class="px-5 py-4 bg-obsidian-panel border-b border-obsidian-border flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-red-400">block</span>
                <h3 class="text-xs font-mono font-bold text-white uppercase tracking-wider">Direcciones IP Bloqueadas en el Servidor ({{ $bannedIps->count() }})</h3>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-obsidian-panel/80 border-b border-obsidian-border text-obsidian-muted uppercase text-[11px]">
                    <tr>
                        <th class="px-5 py-3.5">Dirección IP</th>
                        <th class="px-5 py-3.5">Motivo del Bloqueo</th>
                        <th class="px-5 py-3.5">Usuario Vinculado</th>
                        <th class="px-5 py-3.5">Fecha y Hora</th>
                        <th class="px-5 py-3.5 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border/60">
                    @forelse($bannedIps as $ban)
                        <tr class="hover:bg-obsidian-panel/40 transition">
                            <td class="px-5 py-4 font-bold text-red-400">
                                {{ $ban->ip_address }}
                            </td>
                            <td class="px-5 py-4 text-obsidian-muted max-w-sm truncate" title="{{ $ban->reason }}">
                                {{ $ban->reason ?: 'Violación de privilegios de acceso' }}
                            </td>
                            <td class="px-5 py-4">
                                @if($ban->user)
                                    <span class="text-white font-sans font-semibold">{{ $ban->user->name }}</span>
                                    <span class="text-[10px] text-obsidian-muted">({{ $ban->user->email }})</span>
                                @else
                                    <span class="text-obsidian-muted">Manual / Desconocido</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-obsidian-muted">
                                {{ $ban->banned_at ? $ban->banned_at->format('Y-m-d H:i:s') : $ban->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <form action="{{ route('admin.bans.unban.ip', $ban->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" onclick="return confirm('¿Confirma desbloquear y remover la IP {{ $ban->ip_address }} de la lista negra?')" class="px-3 py-1.5 rounded-lg bg-emerald-950/70 border border-emerald-500/50 text-emerald-400 hover:bg-emerald-500 hover:text-black font-mono text-[11px] font-bold transition flex items-center gap-1 inline-flex" title="Remover de la Lista Negra">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                        <span>Desbloquear IP</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-obsidian-muted font-mono text-xs">
                                <div class="flex flex-col items-center justify-center gap-1.5">
                                    <span class="material-symbols-outlined text-emerald-400 text-3xl">shield</span>
                                    <span class="text-white font-semibold">Lista negra de direcciones IP limpia</span>
                                    <span class="text-[11px]">No hay direcciones IP bloqueadas actualmente por el firewall de aplicación.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECCIÓN 3: BLOQUEO MANUAL DE IP POR EL ADMINISTRADOR -->
    <div class="glass-card rounded-xl p-5 border border-obsidian-border">
        <h3 class="text-xs font-mono font-bold text-white uppercase tracking-wider mb-3 flex items-center gap-2">
            <span class="material-symbols-outlined text-obsidian-cyan">add_moderator</span>
            Bloqueo Manual de Dirección IP
        </h3>
        <form action="{{ route('admin.bans.ban.ip') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-3 font-mono text-xs">
            @csrf
            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px]">Dirección IP:</label>
                <input type="text" name="ip_address" required placeholder="Ej: 192.168.1.100" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:outline-none focus:border-obsidian-cyan"/>
            </div>
            <div>
                <label class="block text-obsidian-muted mb-1 text-[11px]">Motivo del Bloqueo:</label>
                <input type="text" name="reason" placeholder="Ej: Comportamiento sospechoso / escaneo" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:outline-none focus:border-obsidian-cyan"/>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-red-950/80 border border-red-500/60 text-red-400 hover:bg-red-500 hover:text-white font-bold transition flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-base">block</span>
                    <span>Añadir a Lista Negra</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
