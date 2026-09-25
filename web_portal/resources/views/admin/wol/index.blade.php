@extends('layouts.admin')

@section('page_title', 'Wake-on-LAN (WoL)')

@section('admin_content')
<div class="space-y-4">
    <!-- PESTAÑAS EQUIPOS & HARDWARE -->
    <div class="flex flex-wrap items-center gap-2 border-b border-obsidian-border/80 pb-3">
        <a href="{{ route('admin.devices.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.devices.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">router</span>
            <span>Dispositivos de Red</span>
        </a>
        <a href="{{ route('admin.lifecycle.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.lifecycle.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">inventory_2</span>
            <span>Ciclo de Vida & Inventario</span>
        </a>
        <a href="{{ route('admin.wol.index') }}" class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('admin.wol.*') ? 'bg-cyan-500 text-black shadow-lg shadow-cyan-500/20' : 'bg-obsidian-panel/80 border border-obsidian-border text-obsidian-muted hover:text-white hover:border-cyan-500/40' }}">
            <span class="material-symbols-outlined text-base">power</span>
            <span>Wake-on-LAN (WoL)</span>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- ENCABEZADO Y ACCIONES                                                     -->
    <!-- ========================================================================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 sm:px-4 rounded-xl bg-obsidian-panel/80 border border-obsidian-border/90 backdrop-blur-md shadow-md">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-amber-950/70 border border-amber-500/40 flex items-center justify-center text-amber-400 shrink-0">
                <span class="material-symbols-outlined text-lg">power</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xs font-bold text-white uppercase font-mono tracking-wider">
                        Encendido Remoto Wake-on-LAN (Magic Packet)
                    </h2>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-950/80 text-amber-300 border border-amber-500/40">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                        UDP 9 / Broadcast
                    </span>
                    @if(!auth()->user()->isAdmin() && !auth()->user()->hasPermission('wol.wake') && !auth()->user()->hasPermission('wol.manage'))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-950/80 text-amber-300 border border-amber-500/40">
                            <span class="material-symbols-outlined text-[11px]">visibility</span>
                            MODO CONSULTA
                        </span>
                    @endif
                </div>
                <p class="text-[10px] font-mono text-obsidian-muted">
                    Activación de energía a nivel Ethernet (Capa 2) para servidores y estaciones de trabajo de Valle Seco.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto flex-wrap">
            <span class="px-2.5 py-1 rounded-lg bg-cyan-950/80 border border-cyan-500/40 text-cyan-300 text-[10.5px] font-mono flex items-center gap-1.5 shadow-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                Fuente: SERVIDOR MAESTRO
            </span>

            @if((auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('wol.manage'))) && !$isClusterSlave)
                <button type="button" onclick="openWolModal()" class="px-2.5 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/40 text-amber-300 hover:bg-amber-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs">
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    <span>Registrar Equipo</span>
                </button>
            @elseif($isClusterSlave)
                <span class="px-2.5 py-1.5 rounded-lg bg-gray-900 border border-gray-800 text-gray-500 font-mono text-xs inline-flex items-center gap-1.5" title="Modificaciones restringidas al Servidor Master">
                    <span class="material-symbols-outlined text-xs">lock</span>
                    <span>Solo Lectura (Modo Esclavo)</span>
                </span>
            @endif
            <a href="{{ route('admin.wol.index') }}" class="px-2.5 py-1.5 rounded-lg bg-obsidian-panel border border-cyan-500/40 text-cyan-300 hover:bg-cyan-500 hover:text-black font-mono text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs">
                <span class="material-symbols-outlined text-sm">refresh</span>
                <span>Refrescar</span>
            </a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- HUD: TARJETAS MÉTRICAS                                                    -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-3">
        <!-- TOTAL EQUIPOS -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Equipos Registrados</span>
                <span class="material-symbols-outlined text-sm text-amber-400">devices</span>
            </div>
            <div class="text-lg font-bold font-mono text-white mt-1">{{ number_format($totalDevices) }}</div>
            <div class="text-[9px] font-mono text-amber-400/80 mt-0.5">Habilitados para WoL</div>
        </div>

        <!-- ACTIVADOS RECIENTEMENTE -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Encendidos (24h)</span>
                <span class="material-symbols-outlined text-sm text-emerald-400">bolt</span>
            </div>
            <div class="text-lg font-bold font-mono text-emerald-400 mt-1">{{ number_format($recentlyWoken) }}</div>
            <div class="text-[9px] font-mono text-emerald-400/80 mt-0.5">Paquetes transmitidos</div>
        </div>

        <!-- BROADCAST DEFAULT -->
        <div class="p-2.5 rounded-xl bg-obsidian-panel/70 border border-obsidian-border/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-mono text-obsidian-muted uppercase">Subred Broadcast</span>
                <span class="material-symbols-outlined text-sm text-cyan-400">settings_ethernet</span>
            </div>
            <div class="text-sm font-bold font-mono text-cyan-300 mt-2">255.255.255.255</div>
            <div class="text-[9px] font-mono text-cyan-400/80 mt-0.5">Subred o Global</div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- FILTRO Y BÚSQUEDA                                                         -->
    <!-- ========================================================================= -->
    <div class="p-3 rounded-xl bg-obsidian-panel/80 border border-obsidian-border">
        <form method="GET" action="{{ route('admin.wol.index') }}" class="flex items-center gap-2">
            <div class="relative flex-1">
                <span class="material-symbols-outlined absolute left-3 top-2.5 text-obsidian-muted text-sm">search</span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nombre, dirección MAC o IP..." class="w-full pl-9 pr-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white text-xs font-mono focus:outline-none focus:border-cyan-500">
            </div>
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-cyan-500 text-black font-mono text-xs font-bold hover:bg-cyan-400 transition cursor-pointer">
                Buscar
            </button>
            @if(!empty($search))
                <a href="{{ route('admin.wol.index') }}" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300 font-mono text-xs hover:bg-slate-700 transition">
                    Limpiar
                </a>
            @endif
        </form>
    </div>

    <!-- ========================================================================= -->
    <!-- TABLA DE EQUIPOS WAKE-ON-LAN                                              -->
    <!-- ========================================================================= -->
    <div class="rounded-xl bg-obsidian-panel/80 border border-obsidian-border overflow-hidden shadow-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-left font-mono text-xs">
                <thead>
                    <tr class="bg-obsidian-bg/90 border-b border-obsidian-border text-[11px] text-obsidian-muted uppercase">
                        <th class="px-4 py-3">Nombre del Equipo</th>
                        <th class="px-4 py-3">Dirección MAC</th>
                        <th class="px-4 py-3">IP / Broadcast</th>
                        <th class="px-4 py-3">Sede</th>
                        <th class="px-4 py-3">Último Encendido</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-obsidian-border">
                    @forelse($devices as $dev)
                        <tr class="hover:bg-cyan-500/5 transition">
                            <td class="px-4 py-3 font-bold text-white flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm text-amber-400">desktop_windows</span>
                                {{ $dev->name }}
                            </td>
                            <td class="px-4 py-3 text-cyan-300 font-bold tracking-wider">
                                {{ $dev->formatted_mac }}
                            </td>
                            <td class="px-4 py-3 text-obsidian-muted text-[11px]">
                                <div class="text-white">{{ $dev->ip_address ?? 'N/A' }}</div>
                                <div class="text-[10px] text-obsidian-muted">{{ $dev->broadcast_address ?? '255.255.255.255' }}</div>
                            </td>
                            <td class="px-4 py-3 text-obsidian-muted">
                                {{ $dev->site->name ?? 'Valle Seco (General)' }}
                            </td>
                            <td class="px-4 py-3 text-[11px]">
                                @if($dev->last_woken_at)
                                    <span class="text-emerald-400 font-bold">{{ $dev->last_woken_at->format('Y-m-d H:i') }}</span>
                                    <span class="text-[9px] text-obsidian-muted block">({{ $dev->last_woken_at->diffForHumans() }})</span>
                                @else
                                    <span class="text-obsidian-muted italic">Nunca</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if(auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('wol.wake')))
                                        <form action="{{ route('admin.wol.wake', $dev->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded bg-amber-500/20 hover:bg-amber-500 hover:text-black border border-amber-500/40 text-amber-300 text-[11px] font-bold transition flex items-center gap-1 cursor-pointer" title="Transmitir Magic Packet">
                                                <span class="material-symbols-outlined text-xs">bolt</span>
                                                Encender
                                            </button>
                                        </form>
                                    @endif

                                    @if(auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('wol.manage')))
                                        @if($isClusterSlave)
                                            <span class="p-1 rounded bg-gray-900 border border-gray-800 text-gray-500 text-[10px] font-mono inline-flex items-center" title="Modificaciones restringidas al Servidor Master">
                                                <span class="material-symbols-outlined text-[12px]">lock</span>
                                            </span>
                                        @else
                                            <form action="{{ route('admin.wol.destroy', $dev->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Confirmas eliminar este equipo de WoL?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-1 rounded text-rose-400 hover:bg-rose-500/20 transition cursor-pointer" title="Eliminar">
                                                    <span class="material-symbols-outlined text-sm">delete</span>
                                                </button>
                                            </form>
                                        @endif
                                    @elseif(!auth()->user()->isAdmin() && !auth()->user()->hasPermission('wol.wake'))
                                        <span class="text-[10px] text-obsidian-muted font-mono">Solo lectura</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-obsidian-muted">
                                <span class="material-symbols-outlined text-3xl mb-2 text-obsidian-border block">power_off</span>
                                No hay dispositivos registrados para Wake-on-LAN.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($devices->hasPages())
            <div class="p-3 border-t border-obsidian-border">
                {{ $devices->links() }}
            </div>
        @endif
    </div>
</div>

@if((auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasPermission') && auth()->user()->hasPermission('wol.manage'))) && !$isClusterSlave)
<!-- MODAL REGISTRO DE DISPOSITIVO WOL -->
<div id="wolModal" class="fixed inset-0 bg-black/70 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-obsidian-panel border border-obsidian-border rounded-xl w-full max-w-md p-5 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-obsidian-border">
            <h3 class="text-xs font-bold text-white uppercase font-mono tracking-wider flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-400 text-sm">add_circle</span>
                Registrar Equipo Wake-on-LAN
            </h3>
            <button type="button" onclick="closeWolModal()" class="text-obsidian-muted hover:text-white">&times;</button>
        </div>

        <form action="{{ route('admin.wol.store') }}" method="POST" class="space-y-3 font-mono text-xs">
            @csrf
            <div>
                <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Nombre o Hostname *</label>
                <input type="text" name="name" required placeholder="ej. Servidor Respaldo SAN" class="w-full px-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-amber-500">
            </div>

            <div>
                <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Dirección MAC *</label>
                <input type="text" name="mac_address" required placeholder="00:11:22:33:44:55" pattern="^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$|^[0-9A-Fa-f]{12}$" class="w-full px-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-amber-500">
                <span class="text-[9px] text-obsidian-muted">Acepta formatos 00:11:22:33:44:55 o 001122334455</span>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-[10px] text-obsidian-muted uppercase block mb-1">IP Asignada (Opcional)</label>
                    <input type="text" name="ip_address" placeholder="10.20.23.X" class="w-full px-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-amber-500">
                </div>
                <div>
                    <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Broadcast</label>
                    <input type="text" name="broadcast_address" value="255.255.255.255" class="w-full px-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-amber-500">
                </div>
            </div>

            <div>
                <label class="text-[10px] text-obsidian-muted uppercase block mb-1">Sede / Ubicación</label>
                <select name="site_id" class="w-full px-3 py-1.5 rounded-lg bg-obsidian-bg border border-obsidian-border text-white focus:outline-none focus:border-amber-500">
                    <option value="">Valle Seco (General)</option>
                    @foreach($sites as $st)
                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pt-3 border-t border-obsidian-border flex items-center justify-end gap-2">
                <button type="button" onclick="closeWolModal()" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 transition">
                    Cancelar
                </button>
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-500 text-black font-bold hover:bg-amber-400 transition cursor-pointer">
                    Guardar Equipo
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openWolModal() {
        document.getElementById('wolModal').classList.remove('hidden');
    }
    function closeWolModal() {
        document.getElementById('wolModal').classList.add('hidden');
    }
</script>
@endif
@endsection
