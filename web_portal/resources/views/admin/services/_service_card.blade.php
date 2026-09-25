@php
    $typeUpper = strtoupper($s->type ?? 'OTRO');
    $serviceIcon = 'hub';
    $iconColor = 'text-cyan-400';
    switch($typeUpper) {
        case 'WEB':
            $serviceIcon = 'language';
            $iconColor = 'text-cyan-400';
            break;
        case 'DNS':
            $serviceIcon = 'dns';
            $iconColor = 'text-amber-400';
            break;
        case 'SMTP':
            $serviceIcon = 'mail';
            $iconColor = 'text-emerald-400';
            break;
        case 'DHCP':
            $serviceIcon = 'router';
            $iconColor = 'text-teal-400';
            break;
        case 'CUPS':
            $serviceIcon = 'print';
            $iconColor = 'text-sky-400';
            break;
        case 'LDAP':
            $serviceIcon = 'badge';
            $iconColor = 'text-indigo-400';
            break;
        case 'PROXY':
            $serviceIcon = 'shield';
            $iconColor = 'text-purple-400';
            break;
        case 'PING':
            $serviceIcon = 'sensors';
            $iconColor = 'text-blue-400';
            break;
        case 'DESACTIVADO':
            $serviceIcon = 'power_off';
            $iconColor = 'text-obsidian-muted';
            break;
        default:
            $serviceIcon = 'settings_input_component';
            $iconColor = 'text-cyan-400';
            break;
    }

    $cleanName = trim($s->name, '()');
    $targetDisp = $s->host_ip ?: ($s->web_url ?: 'No configurado');
    if ($s->port && $s->host_ip && !str_contains($s->host_ip, ':')) {
        $targetDisp = $s->host_ip . ':' . $s->port;
    }

    $snap = $snapshotServices->get((string)$s->id) ?? $snapshotServices->get((string)$s->letter);
    $isUp = $snap ? ($snap['is_up'] ?? false) : false;
    $lat = $snap ? ($snap['latency_ms'] ?? 0) : 0;
    $latStr = $lat > 0 ? ($lat . ' ms') : ($isUp ? '< 10 ms' : '--');
@endphp

<div class="service-card p-3.5 rounded-xl border text-xs font-mono relative flex flex-col justify-between transition {{ $s->is_active ? 'bg-obsidian-panel/80 border-obsidian-border hover:border-cyan-500/40 shadow-xs' : 'bg-obsidian-panel/30 border-obsidian-border/30 opacity-60' }}"
     data-name="{{ strtolower($s->name) }}"
     data-letter="{{ strtolower($s->letter ?? '') }}"
     data-type="{{ strtolower($s->type) }}"
     data-target="{{ strtolower($s->host_ip . ' ' . $s->web_url) }}"
     data-active="{{ $s->is_active ? '1' : '0' }}"
     data-status="{{ $isUp ? 'online' : 'down' }}"
     data-scope="{{ strtolower($s->scope ?? 'corporativo') }}">
    
    <div>
        <!-- CABECERA DE TARJETA (Icono de Protocolo, Nombre Principal del Servicio y Acceso Directo) -->
        <div class="flex items-center justify-between text-[11px] text-obsidian-muted border-b border-obsidian-border/40 pb-2 mb-2.5 gap-2">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <span class="material-symbols-outlined text-[17px] {{ $iconColor }} shrink-0" title="Tipo: {{ $s->type }}">{{ $serviceIcon }}</span>
                <h4 class="font-sans font-bold text-white text-xs truncate" title="{{ $s->name }}">
                    {{ $cleanName }}
                </h4>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
                <!-- ACCESO DIRECTO (Sin duplicar palabras: botón limpio 'Abrir' con icono) -->
                @if($s->web_url && in_array(strtoupper($s->type), ['WEB', 'PROXY', 'OTRO']))
                    <a href="{{ $s->web_url }}" target="_blank" rel="noopener noreferrer" class="px-2 py-0.5 rounded bg-obsidian-panel border border-cyan-500/40 text-[10px] text-cyan-300 hover:bg-cyan-500 hover:text-black font-semibold flex items-center gap-1 transition cursor-pointer" title="Abrir enlace: {{ $s->web_url }}">
                        <span class="material-symbols-outlined text-[12px]">open_in_new</span>
                        <span>Abrir</span>
                    </a>
                @endif

                <!-- TOGGLE ESTADO MONITOREO -->
                @if((auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.manage_services')) && !$isClusterSlave)
                    <form action="{{ route('admin.services.toggle', $s->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="p-0.5 rounded transition cursor-pointer {{ $s->is_active ? 'text-emerald-400 hover:bg-emerald-950/60' : 'text-obsidian-muted hover:bg-obsidian-panel' }}" title="{{ $s->is_active ? 'Monitoreo activo (Clic para pausar)' : 'Monitoreo pausado (Clic para reanudar)' }}">
                            <span class="material-symbols-outlined text-[19px] leading-none">{{ $s->is_active ? 'toggle_on' : 'toggle_off' }}</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- CUERPO DE LA TARJETA (Métricas y Parámetros Técnicos sin Redundancias) -->
        <div class="space-y-2 mb-3">
            <!-- Destino y Estado Online/Down -->
            <div class="text-[11px] text-obsidian-muted flex items-center justify-between gap-2">
                <span class="truncate" title="{{ $targetDisp }}">
                    Destino: <strong class="text-white font-mono">{{ $targetDisp }}</strong>
                </span>
                <span class="text-[10px] px-2 py-0.5 rounded font-bold font-mono shrink-0 {{ $isUp ? 'bg-emerald-950 text-emerald-400 border border-emerald-500/40' : ($s->is_active ? 'bg-red-950 text-red-400 border border-red-500/40' : 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border') }}">
                    {{ $s->is_active ? ($isUp ? 'ONLINE' : 'DOWN') : 'INACTIVO' }}
                </span>
            </div>

            <!-- Protocolo & Latencia -->
            <div class="flex items-center justify-between text-[10px] font-mono text-obsidian-muted pt-0.5">
                <span class="flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px] text-obsidian-cyan">tune</span>
                    <span>Tipo: <strong class="text-white">{{ $s->type }}{{ $s->port ? ':'.$s->port : '' }}</strong></span>
                </span>
                <span class="flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px] {{ $isUp ? 'text-cyan-400' : 'text-obsidian-muted' }}">speed</span>
                    <span>Latencia: <strong class="text-white">{{ $latStr }}</strong></span>
                </span>
            </div>

            @if($s->check_interface)
                <div class="flex items-center justify-between text-[9.5px] font-mono text-obsidian-muted/80">
                    <span>Interfaz: <strong class="text-obsidian-muted">{{ $s->check_interface }}</strong></span>
                    <span class="text-obsidian-muted/60">ID #{{ $s->id }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- PIE DEL CUADRO: GRÁFICA HISTÓRICA DEDICADA + ACCIONES ADMIN -->
    <div class="pt-2.5 border-t border-obsidian-border/40 flex items-center gap-1.5">
        <button type="button" onclick="openServiceHistoryModal({{ $s->id }}, '{{ addslashes($cleanName) }}', '{{ $s->id }}', '{{ $s->type }}')" class="flex-1 py-1.5 px-2 rounded-lg bg-obsidian-panel border border-cyan-500/30 text-cyan-300 hover:bg-cyan-500 hover:text-black transition flex items-center justify-between text-[11px] font-semibold group cursor-pointer shadow-xs">
            <span class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm text-cyan-400 group-hover:text-black">show_chart</span>
                <span>Gráfica Histórica</span>
            </span>
            <span class="text-[10px] text-obsidian-muted group-hover:text-black font-mono flex items-center gap-0.5">
                <span>Ver Telemetría</span>
                <span class="material-symbols-outlined text-[13px]">chevron_right</span>
            </span>
        </button>

        @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('infra.manage_services'))
            @if($isClusterSlave)
                <span class="p-1.5 rounded-lg bg-gray-900 border border-gray-800 text-gray-500 text-[10px]" title="Solo Lectura (Modo Esclavo)">
                    <span class="material-symbols-outlined text-xs">lock</span>
                </span>
            @else
                <button type="button" onclick="openEditServiceModal({{ json_encode($s) }})" class="p-1.5 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition cursor-pointer shrink-0" title="Modificar Parámetros">
                    <span class="material-symbols-outlined text-sm">edit</span>
                </button>
                <form action="{{ route('admin.services.destroy', $s->id) }}" method="POST" class="inline shrink-0" onsubmit="return confirm('¿Está seguro de eliminar el servicio [{{ addslashes($s->name) }}]?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="p-1.5 rounded-lg bg-obsidian-panel border border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white transition cursor-pointer" title="Eliminar Servicio">
                        <span class="material-symbols-outlined text-sm">delete</span>
                    </button>
                </form>
            @endif
        @endif
    </div>
</div>
