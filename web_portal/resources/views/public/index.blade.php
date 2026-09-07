@extends('layouts.app')

@section('title', 'ATIT • Monitoreo de Infraestructura - Valle Seco')

@section('content')
<div class="h-screen flex flex-col overflow-hidden bg-[#020617] text-white">
    <!-- TOP NAVIGATION BAR (HEADER INSTITUCIONAL) -->
    <header class="h-16 shrink-0 glass-panel border-b border-obsidian-border flex items-center justify-between px-4 sm:px-6 z-30 shadow-2xl">
        <!-- MARCA & TÍTULO INSTITUCIONAL -->
        <div class="flex items-center space-x-3.5">
            <div class="w-10 h-10 rounded-lg bg-gradient-to-tr from-obsidian-cyan/20 to-obsidian-purple/30 border border-obsidian-cyan/40 flex items-center justify-center text-obsidian-cyan glow-cyan shrink-0 p-1.5 overflow-hidden shadow-lg shadow-cyan-500/20">
                <img src="{{ asset('img/logo.png') }}" alt="Logo CORPOELEC" class="w-full h-full object-contain filter drop-shadow-[0_0_6px_rgba(34,211,238,0.6)]">
            </div>
            <div>
                <h1 class="text-sm sm:text-base font-bold tracking-tight text-white flex items-center gap-2">
                    ATIT • Monitoreo de Infraestructura - Valle Seco
                </h1>
                <p class="text-[10px] font-mono text-obsidian-cyan flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-obsidian-cyan pulse-dot"></span>
                    SISTEMA DE MONITOREO VALLE SECO
                </p>
            </div>
        </div>

        <!-- BUSCADOR CENTRAL -->
        <div class="hidden md:flex items-center flex-1 max-w-xs mx-8">
            <div class="relative w-full">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-obsidian-muted text-sm">search</span>
                <input type="text" id="live-search-input" onkeyup="filterLiveItems()" placeholder="Buscar servicio o sede..." class="w-full bg-[#051424]/90 border border-obsidian-border rounded-lg pl-8 pr-3 py-1.5 text-xs text-white placeholder-obsidian-muted focus:outline-none focus:border-obsidian-cyan font-mono transition"/>
            </div>
        </div>

        <!-- METRICAS & LOGIN -->
        <div class="flex items-center space-x-3 sm:space-x-4">
            <!-- BOTÓN ASISTENTE IA (UBICADO ANTES DEL BADGE INFORMATIVO) -->
            <button type="button" 
                    id="btn-open-ai-chat" 
                    onclick="handleAiChatClick()" 
                    title="Asistente Virtual IA - Sede Valle Seco" 
                    class="flex items-center gap-1.5 px-3 py-1 rounded-full border border-cyan-500/50 bg-cyan-950/40 text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-xs font-semibold transition-all duration-200 shadow-sm hover:shadow-cyan-500/25 hover:scale-[1.02] cursor-pointer group">
                <span class="material-symbols-outlined text-sm group-hover:rotate-12 transition-transform">smart_toy</span>
                <span>IA</span>
                <span class="flex h-2 w-2 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-400"></span>
                </span>
            </button>

            <!-- BADGE GLOBAL -->
            <div id="global-status-badge" class="hidden sm:flex items-center gap-2 px-3 py-1 rounded-full border text-xs font-mono font-semibold {{ ($latestSnapshot && $latestSnapshot->global_status == 'OPERACIONAL') ? 'bg-emerald-950/60 text-emerald-400 border-emerald-500/50 glow-green' : (($latestSnapshot && $latestSnapshot->global_status == 'DEGRADADO') ? 'bg-amber-950/60 text-amber-400 border-amber-500/50' : 'bg-red-950/60 text-red-400 border-red-500/50 glow-red') }}"
                 data-tech-title="ESTADO GLOBAL DE INFRAESTRUCTURA"
                 data-tech-type="SISTEMA"
                 @if(Auth::check())
                 data-tech-ip="Red Corporativa Nacional"
                 data-tech-protocol="Orquestador Asíncrono Python"
                 data-tech-latency="< 2.5s ciclo"
                 data-tech-status="{{ $latestSnapshot ? $latestSnapshot->global_status : 'OPERACIONAL' }}"
                 data-tech-details="Chequeo continuo en tiempo real de servicios y sedes regionales."
                 @else
                 data-tech-auth-required="true"
                 title="DEBE INICIAR SESIÓN PARA VER LOS DATOS"
                 @endif>
                <span class="w-2 h-2 rounded-full {{ ($latestSnapshot && $latestSnapshot->global_status == 'OPERACIONAL') ? 'bg-emerald-400 pulse-dot' : (($latestSnapshot && $latestSnapshot->global_status == 'DEGRADADO') ? 'bg-amber-400' : 'bg-red-400 pulse-dot') }}"></span>
                <span id="global-status-text">{{ $latestSnapshot ? $latestSnapshot->global_status : 'OPERACIONAL' }}</span>
            </div>

            <!-- RELOJ & SINCRONIZACIÓN -->
            <div class="hidden lg:flex flex-col text-right font-mono text-[11px] text-obsidian-muted">
                <span class="text-[9px] uppercase tracking-wider text-obsidian-cyan">Último Escaneo</span>
                <span id="last-sync-time" class="text-white font-bold">{{ $latestSnapshot ? $latestSnapshot->created_at->format('H:i:s') : '--:--:--' }}</span>
            </div>

            <!-- BOTÓN INICIO DE SESIÓN (SOLO ÍCONO) -->
            @auth
                <a href="{{ route('admin.dashboard') }}" title="Panel de Administración" class="w-9 h-9 rounded-lg bg-obsidian-cyan text-black flex items-center justify-center transition hover:bg-cyan-300 hover:shadow-lg hover:shadow-cyan-500/30 glow-cyan">
                    <span class="material-symbols-outlined text-lg">admin_panel_settings</span>
                </a>
            @else
                <a href="{{ route('login') }}" title="Iniciar Sesión" class="w-9 h-9 rounded-lg bg-obsidian-cyan/10 border border-obsidian-cyan/60 text-obsidian-cyan flex items-center justify-center transition hover:bg-obsidian-cyan hover:text-black hover:shadow-lg hover:shadow-cyan-500/30">
                    <span class="material-symbols-outlined text-lg">lock</span>
                </a>
            @endauth
        </div>
    </header>

    @include('partials.monitoring_board', ['isDashboard' => false])
</div>
@endsection
