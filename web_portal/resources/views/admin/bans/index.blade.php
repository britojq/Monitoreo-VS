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
            <p class="text-xs font-mono text-obsidian-muted">Control perimetral Fail2ban, cortafuegos nftables, cuentas suspendidas y listas negras</p>
        </div>
        <div class="flex items-center gap-2">
            @if(!empty($fail2banStatus['is_running']))
                <span class="px-3 py-1.5 rounded-lg bg-emerald-950/80 border border-emerald-500/50 text-emerald-400 text-xs font-mono font-bold flex items-center gap-1.5 shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Fail2ban Activo ({{ $fail2banStatus['jail_count'] ?? 0 }} Jails)
                </span>
            @else
                <span class="px-3 py-1.5 rounded-lg bg-rose-950/80 border border-rose-500/50 text-rose-400 text-xs font-mono font-bold flex items-center gap-1.5 shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    Fail2ban Inactivo
                </span>
            @endif

            <form action="{{ route('admin.bans.fail2ban.reload') }}" method="POST" class="inline-block">
                @csrf
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-xs font-bold transition flex items-center gap-1.5 shadow-xs" title="Recargar reglas y servicios de Fail2ban">
                    <span class="material-symbols-outlined text-sm">refresh</span>
                    <span>Recargar Reglas</span>
                </button>
            </form>
        </div>
    </div>

    <!-- SECCIÓN 1: DEFENSOR PERIMETRAL FAIL2BAN (MONITOR DE JAILS) -->
    <div class="glass-card rounded-xl p-5 border border-obsidian-border space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-obsidian-border/60 pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-obsidian-panel border border-obsidian-border flex items-center justify-center text-obsidian-cyan">
                    <span class="material-symbols-outlined text-xl">security</span>
                </div>
                <div>
                    <h3 class="text-xs font-mono font-bold text-white uppercase tracking-wider">Defensa Perimetral Activa (Fail2ban + nftables)</h3>
                    <p class="text-[10px] font-mono text-obsidian-muted">Mitigación a nivel de kernel para SSH, Apache y servidor VNC</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-1 rounded bg-obsidian-panel border border-obsidian-border text-[10.5px] font-mono text-obsidian-muted">
                    Backend: <span class="text-white font-bold">nftables-multiport</span>
                </span>
            </div>
        </div>

        <!-- GRID DE JAILS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
            @forelse($fail2banJails as $jailName => $j)
                <div class="glass-card rounded-xl p-3.5 border border-obsidian-border/80 bg-obsidian-panel/30 hover:border-obsidian-cyan/40 transition">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg text-obsidian-cyan">{{ $j['icon'] ?? 'shield' }}</span>
                            <div>
                                <h4 class="text-xs font-mono font-bold text-white">{{ $j['label'] ?? $jailName }}</h4>
                                <span class="text-[9.5px] font-mono text-obsidian-muted">Jail: <code>{{ $jailName }}</code></span>
                            </div>
                        </div>
                        @if(($j['currently_banned'] ?? 0) > 0)
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-red-950/80 border border-red-500/40 text-red-400">
                                {{ $j['currently_banned'] }} BANEADA(S)
                            </span>
                        @else
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-emerald-950/80 border border-emerald-500/40 text-emerald-400">
                                MONITOREANDO
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-obsidian-border/40 text-[10.5px] font-mono">
                        <div>
                            <span class="text-obsidian-muted block text-[9.5px]">Intentos Fallidos:</span>
                            <span class="text-white font-bold">{{ $j['currently_failed'] ?? 0 }}</span>
                            <span class="text-obsidian-muted text-[9px]">/ {{ $j['total_failed'] ?? 0 }} total</span>
                        </div>
                        <div>
                            <span class="text-obsidian-muted block text-[9.5px]">IPs Bloqueadas:</span>
                            <span class="{{ ($j['currently_banned'] ?? 0) > 0 ? 'text-red-400 font-bold' : 'text-white' }}">
                                {{ $j['currently_banned'] ?? 0 }}
                            </span>
                            <span class="text-obsidian-muted text-[9px]">/ {{ $j['total_banned'] ?? 0 }} total</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-3 p-4 text-center text-obsidian-muted font-mono text-xs">
                    No se pudieron cargar los detalles de las Jails de Fail2ban.
                </div>
            @endforelse
        </div>

        <!-- TABLA DE IPS BANEADAS POR FAIL2BAN -->
        @php
            $consolidatedBannedIps = [];
            foreach ($fail2banJails as $jailName => $j) {
                if (!empty($j['banned_ips'])) {
                    foreach ($j['banned_ips'] as $ip) {
                        $consolidatedBannedIps[] = [
                            'ip' => $ip,
                            'jail' => $jailName,
                            'jail_label' => $j['label'] ?? $jailName,
                            'jail_icon' => $j['icon'] ?? 'shield',
                        ];
                    }
                }
            }
        @endphp

        <div class="pt-2">
            <h4 class="text-xs font-mono font-bold text-white uppercase tracking-wider mb-2 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-red-400 text-base">block</span>
                Direcciones IP Bloqueadas en Cortafuegos Fail2ban ({{ count($consolidatedBannedIps) }})
            </h4>

            @if(count($consolidatedBannedIps) > 0)
                <div class="overflow-x-auto rounded-lg border border-obsidian-border">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-obsidian-panel/90 border-b border-obsidian-border text-obsidian-muted uppercase text-[10.5px]">
                            <tr>
                                <th class="px-4 py-3">Dirección IP</th>
                                <th class="px-4 py-3">Servicio / Jail Afectada</th>
                                <th class="px-4 py-3">Capa de Mitigación</th>
                                <th class="px-4 py-3 text-right">Acción de Desbloqueo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-obsidian-border/50">
                            @foreach($consolidatedBannedIps as $ban)
                                <tr class="hover:bg-obsidian-panel/40 transition">
                                    <td class="px-4 py-3 font-bold text-red-400 flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                        <span>{{ $ban['ip'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-white">
                                        <div class="flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-sm text-obsidian-cyan">{{ $ban['jail_icon'] }}</span>
                                            <span>{{ $ban['jail_label'] }}</span>
                                            <span class="text-[9.5px] text-obsidian-muted font-mono">(<code>{{ $ban['jail'] }}</code>)</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-red-950/80 border border-red-500/40 text-red-300">
                                            Cortafuegos Kernel (nftables)
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form action="{{ route('admin.bans.fail2ban.unban') }}" method="POST" class="inline-block">
                                            @csrf
                                            <input type="hidden" name="ip_address" value="{{ $ban['ip'] }}"/>
                                            <input type="hidden" name="jail" value="{{ $ban['jail'] }}"/>
                                            <button type="submit" onclick="return confirm('¿Confirma desbloquear la IP {{ $ban['ip'] }} del firewall en la jail {{ $ban['jail'] }}?')" class="px-2.5 py-1 rounded-lg bg-emerald-950/70 border border-emerald-500/50 text-emerald-400 hover:bg-emerald-500 hover:text-black font-mono text-[11px] font-bold transition flex items-center gap-1 inline-flex shadow-sm">
                                                <span class="material-symbols-outlined text-sm">lock_open</span>
                                                <span>Desbloquear en Firewall</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="rounded-xl border border-obsidian-border bg-obsidian-panel/20 p-5 text-center text-obsidian-muted font-mono text-xs">
                    <div class="flex flex-col items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-emerald-400 text-3xl">verified_user</span>
                        <span class="text-white font-semibold">Firewall Perimetral Limpio</span>
                        <span class="text-[11px]">No existen direcciones IP bloqueadas activas en las 6 jails de Fail2ban. El tráfico fluye normalmente.</span>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- SECCIÓN 2: USUARIOS BANEADOS -->
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
                                <form action="{{ route('admin.bans.unban.user', $u->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" onclick="return confirm('¿Confirma reactivar y desbanear la cuenta de {{ $u->name }}?')" class="px-2.5 py-1.5 rounded-lg bg-emerald-950/70 border border-emerald-500/50 text-emerald-400 hover:bg-emerald-500 hover:text-black font-mono text-[11px] font-bold transition flex items-center gap-1 inline-flex shadow-sm" title="Reactivar Usuario">
                                        <span class="material-symbols-outlined text-sm">lock_open</span>
                                        <span>Desbanear Cuenta</span>
                                    </button>
                                </form>

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

    <!-- SECCIÓN 3: LISTA NEGRA DE APLICACIÓN WEB -->
    <div class="glass-card rounded-xl overflow-hidden border border-obsidian-border">
        <div class="px-5 py-4 bg-obsidian-panel border-b border-obsidian-border flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-red-400">block</span>
                <h3 class="text-xs font-mono font-bold text-white uppercase tracking-wider">Lista Negra de Direcciones IP en la Aplicación Web ({{ $bannedIps->count() }})</h3>
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
                                    <button type="submit" onclick="return confirm('¿Confirma desbloquear y remover la IP {{ $ban->ip_address }} de la lista negra de la aplicación?')" class="px-3 py-1.5 rounded-lg bg-emerald-950/70 border border-emerald-500/50 text-emerald-400 hover:bg-emerald-500 hover:text-black font-mono text-[11px] font-bold transition flex items-center gap-1 inline-flex" title="Remover de la Lista Negra">
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
                                    <span class="text-white font-semibold">Lista negra de la aplicación limpia</span>
                                    <span class="text-[11px]">No hay direcciones IP bloqueadas actualmente por el middleware web.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECCIÓN 4: BLOQUEO MANUAL MULTI-CAPA POR EL ADMINISTRADOR -->
    <div class="glass-card rounded-xl p-5 border border-obsidian-border">
        <h3 class="text-xs font-mono font-bold text-white uppercase tracking-wider mb-3 flex items-center gap-2">
            <span class="material-symbols-outlined text-obsidian-cyan">add_moderator</span>
            Bloqueo Manual de Dirección IP (Multi-Capa)
        </h3>
        <form action="{{ route('admin.bans.ban.ip') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-12 gap-3 font-mono text-xs">
            @csrf
            <div class="sm:col-span-3">
                <label class="block text-obsidian-muted mb-1 text-[11px]">Dirección IP:</label>
                <input type="text" name="ip_address" required placeholder="Ej: 198.51.100.25" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:outline-none focus:border-obsidian-cyan"/>
            </div>
            <div class="sm:col-span-3">
                <label class="block text-obsidian-muted mb-1 text-[11px]">Capa / Alcance del Bloqueo:</label>
                <select name="jail" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:outline-none focus:border-obsidian-cyan">
                    <option value="all">Cortafuegos Total (Todas las Jails + Web)</option>
                    <option value="sshd">Solo Cortafuegos SSH (Puerto 22)</option>
                    <option value="apache-auth">Solo Cortafuegos Web (Apache Auth)</option>
                    <option value="apache-badbots">Solo Cortafuegos Bad Bots (Web)</option>
                    <option value="vnc-bruteforce">Solo Cortafuegos VNC (Puerto 5900)</option>
                </select>
                <input type="hidden" name="apply_firewall" value="1"/>
            </div>
            <div class="sm:col-span-4">
                <label class="block text-obsidian-muted mb-1 text-[11px]">Motivo del Bloqueo:</label>
                <input type="text" name="reason" placeholder="Ej: Intentos de intrusión / Escaneo sospechoso" class="w-full bg-obsidian-panel border border-obsidian-border rounded-lg p-2 text-white focus:outline-none focus:border-obsidian-cyan"/>
            </div>
            <div class="sm:col-span-2 flex items-end">
                <button type="submit" class="w-full py-2 px-3 rounded-lg bg-red-950/80 border border-red-500/60 text-red-400 hover:bg-red-500 hover:text-white font-bold transition flex items-center justify-center gap-1.5 shadow-sm text-xs">
                    <span class="material-symbols-outlined text-base">block</span>
                    <span>Bloquear IP</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
