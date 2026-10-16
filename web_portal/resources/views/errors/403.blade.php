@extends('layouts.app')

@section('title', '403 Acceso Restringido • Seguridad del Sistema')

@php
    $clientIp = request()->ip() ?: '0.0.0.0';
    $isBanned = false;
    if (auth()->check() && !auth()->user()->is_active) {
        $isBanned = true;
    } elseif (\App\Models\BannedIp::isBanned($clientIp)) {
        $isBanned = true;
    }

    $message = isset($exception) && $exception->getMessage() 
        ? $exception->getMessage() 
        : ($isBanned 
            ? 'Acceso Denegado: Su cuenta o dirección IP han sido suspendidas preventivamente por políticas de seguridad.'
            : 'Acceso Denegado: Su cuenta no dispone de permisos suficientes para acceder a este recurso.');
@endphp

@section('content')
<div class="min-h-screen flex flex-col justify-between py-10 px-4 sm:px-6 lg:px-8 relative overflow-hidden bg-obsidian-bg">
    <!-- EFECTOS AMBIENTALES DECORATIVOS -->
    <div class="absolute top-10 left-1/4 w-[500px] h-[500px] bg-red-600/10 rounded-full filter blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-10 right-1/4 w-[450px] h-[450px] bg-amber-600/10 rounded-full filter blur-[120px] pointer-events-none"></div>
    
    <!-- LÍNEAS DE CUADRÍCULA DECORATIVAS EN FONDO -->
    <div class="absolute inset-0 bg-[radial-gradient(#1c2e47_1px,transparent_1px)] [background-size:24px_24px] opacity-25 pointer-events-none"></div>

    <!-- CABECERA CORPORATIVA LIMPIA -->
    <header class="relative z-10 text-center max-w-2xl mx-auto space-y-2">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-red-950/80 border border-red-500/40 text-red-400 text-xs font-mono shadow-lg shadow-red-900/20">
            <span class="w-2 h-2 rounded-full bg-red-500"></span>
            <span class="font-bold uppercase tracking-wider">Control de Seguridad y Acceso</span>
        </div>
    </header>

    <!-- TARJETA PRINCIPAL DEL INCIDENTE -->
    <main class="relative z-10 max-w-xl w-full mx-auto my-auto">
        <div class="glass-panel rounded-2xl overflow-hidden border border-red-500/40 shadow-2xl shadow-red-950/40">
            <!-- BARRA SUPERIOR SOBRIA -->
            <div class="bg-gradient-to-r from-red-950/90 via-red-900/70 to-red-950/90 px-6 py-3 border-b border-red-500/30 flex items-center justify-between">
                <div class="flex items-center space-x-2 text-xs font-mono font-bold text-red-300">
                    <span class="material-symbols-outlined text-base text-red-400">shield</span>
                    <span>ACCESO NO AUTORIZADO</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-red-500/20 border border-red-500/50 text-red-300">
                        HTTP 403
                    </span>
                </div>
            </div>

            <!-- CONTENIDO DE LA ALERTA -->
            <div class="p-6 sm:p-8 space-y-6">
                <!-- LOGO E ÍCONO DE ACCESO RESTRINGIDO -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                    <div class="w-16 h-16 sm:w-18 sm:h-18 shrink-0 rounded-2xl bg-gradient-to-br from-red-500/20 via-obsidian-card to-amber-500/10 border-2 border-red-500/50 flex items-center justify-center p-3 shadow-xl shadow-red-500/20">
                        <img src="{{ asset('img/logo.png') }}" alt="Seguridad Corporativa" class="w-full h-full object-contain filter drop-shadow-[0_0_10px_rgba(239,68,68,0.7)]">
                    </div>
                    <div class="space-y-1">
                        <div class="text-[11px] font-mono uppercase tracking-widest text-obsidian-muted flex items-center gap-1.5">
                            <span class="text-red-400 font-bold">ACCESO RESTRINGIDO</span>
                            <span>•</span>
                            <span>PORTAL DE GESTIÓN</span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                            <span>{{ $isBanned ? 'Cuenta Suspendida' : 'Permisos Insuficientes' }}</span>
                            <span class="material-symbols-outlined text-red-400 text-2xl">lock</span>
                        </h1>
                        <p class="text-xs font-mono text-obsidian-muted">
                            {{ $isBanned ? 'Su acceso ha sido suspendido preventivamente.' : 'La operación solicitada requiere permisos que su usuario no posee actualmente.' }}
                        </p>
                    </div>
                </div>

                <!-- MENSAJE EXPLICATIVO -->
                <div class="p-4 rounded-xl bg-red-950/40 border border-red-500/40 text-red-200 text-xs font-mono flex items-start gap-3">
                    <span class="material-symbols-outlined text-red-400 text-xl shrink-0 mt-0.5">error_outline</span>
                    <div class="space-y-1 leading-relaxed">
                        <span class="font-bold text-red-300 block uppercase text-[11px]">Detalle:</span>
                        <p>{{ $message }}</p>
                    </div>
                </div>

                <!-- NOTA DE PROCEDIMIENTO -->
                <div class="p-3.5 rounded-xl bg-obsidian-panel/60 border border-obsidian-border text-[11px] font-mono text-obsidian-muted flex items-start gap-2.5">
                    <span class="material-symbols-outlined text-obsidian-cyan text-base shrink-0 mt-0.5">info</span>
                    <p class="leading-relaxed">
                        @if($isBanned)
                            Si considera que se trata de un inconveniente técnico o requiere asistencia para restituir su acceso, comuníquese con el Administrador del Sistema.
                        @else
                            Su sesión de usuario se mantiene activa. Si requiere acceso a esta sección para sus labores operativas, solicite la habilitación de los permisos correspondientes.
                        @endif
                    </p>
                </div>

                <!-- BOTONES DE NAVEGACIÓN Y ACCIÓN -->
                <div class="pt-2 flex flex-col sm:flex-row gap-3">
                    @if(auth()->check())
                        <a href="{{ route('admin.dashboard') }}" class="flex-1 py-2.5 px-4 rounded-xl bg-obsidian-cyan text-black font-mono text-xs font-bold transition flex items-center justify-center gap-2 hover:bg-cyan-300 shadow-sm">
                            <span class="material-symbols-outlined text-base">dashboard</span>
                            <span>Ir al Dashboard</span>
                        </a>
                    @endif

                    <a href="{{ route('home') }}" class="flex-1 py-2.5 px-4 rounded-xl bg-obsidian-panel border border-obsidian-border hover:border-obsidian-cyan text-obsidian-text hover:text-white font-mono text-xs font-bold transition flex items-center justify-center gap-2 group shadow-sm">
                        <span class="material-symbols-outlined text-base text-obsidian-cyan group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                        <span>Monitoreo Público</span>
                    </a>

                    @if(!auth()->check())
                        <a href="{{ route('login') }}" class="flex-1 py-2.5 px-4 rounded-xl bg-gradient-to-r from-red-950 to-obsidian-panel border border-red-500/50 hover:border-red-400 text-red-300 hover:text-white font-mono text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm">
                            <span class="material-symbols-outlined text-base">login</span>
                            <span>Iniciar Sesión</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </main>

    <!-- PIE CORPORATIVO -->
    <footer class="relative z-10 text-center max-w-xl mx-auto space-y-1.5 pt-6 text-[10px] font-mono text-obsidian-muted/80">
        <p class="text-obsidian-text/80 font-semibold">
            Sistema de Monitoreo y Gestión de Red • División de Infraestructura Tecnológica
        </p>
        <p class="text-[9px] text-obsidian-muted/60">
            Nodo Valle Seco • Plataforma Corporativa
        </p>
    </footer>
</div>
@endsection
