@extends('layouts.app')

@section('title', '403 Acceso Restringido • Seguridad del Sistema')

@php
    $message = isset($exception) && $exception->getMessage() 
        ? $exception->getMessage() 
        : 'Acceso Denegado: Su cuenta o dirección IP han sido suspendidas preventivamente por violaciones a las políticas de seguridad y control de acceso RBAC.';
    $clientIp = request()->ip() ?: '0.0.0.0';
    $incidentRef = 'SEC-' . strtoupper(substr(md5($clientIp . date('YmdH') . ($message ?? '')), 0, 8));
    $timestamp = now()->format('d/m/Y H:i:s');
    $method = request()->method();
    $path = request()->path();
@endphp

@section('content')
<div class="min-h-screen flex flex-col justify-between py-10 px-4 sm:px-6 lg:px-8 relative overflow-hidden bg-obsidian-bg">
    <!-- EFECTOS AMBIENTALES DE SEGURIDAD (GLOWS ROJOS Y ÁMBAR) -->
    <div class="absolute top-10 left-1/4 w-[500px] h-[500px] bg-red-600/10 rounded-full filter blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-10 right-1/4 w-[450px] h-[450px] bg-amber-600/10 rounded-full filter blur-[120px] pointer-events-none"></div>
    
    <!-- LÍNEAS DE CUADRÍCULA DECORATIVAS EN FONDO -->
    <div class="absolute inset-0 bg-[radial-gradient(#1c2e47_1px,transparent_1px)] [background-size:24px_24px] opacity-25 pointer-events-none"></div>

    <!-- CABECERA INSTITUCIONAL -->
    <header class="relative z-10 text-center max-w-2xl mx-auto space-y-2">
        <div class="inline-flex items-center gap-3 px-4 py-1.5 rounded-full bg-red-950/80 border border-red-500/40 text-red-400 text-xs font-mono shadow-lg shadow-red-900/20">
            <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
            <span class="font-bold uppercase tracking-wider">Centro de Respuesta a Incidentes de Seguridad • ATIT</span>
        </div>
    </header>

    <!-- TARJETA PRINCIPAL DEL INCIDENTE -->
    <main class="relative z-10 max-w-2xl w-full mx-auto my-auto">
        <div class="glass-panel rounded-2xl overflow-hidden border border-red-500/40 shadow-2xl shadow-red-950/40">
            <!-- BARRA SUPERIOR DE ADVERTENCIA -->
            <div class="bg-gradient-to-r from-red-950/90 via-red-900/70 to-red-950/90 px-6 py-3.5 border-b border-red-500/30 flex items-center justify-between">
                <div class="flex items-center space-x-2 text-xs font-mono font-bold text-red-300">
                    <span class="material-symbols-outlined text-base text-red-400">gavel</span>
                    <span>PROTOCOLO DE DEFENSA PERIMETRAL ACTIVADO</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-red-500/20 border border-red-500/50 text-red-300">
                        HTTP 403
                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-black/40 border border-obsidian-border text-obsidian-muted">
                        REF: {{ $incidentRef }}
                    </span>
                </div>
            </div>

            <!-- CONTENIDO DE LA ALERTA -->
            <div class="p-6 sm:p-8 space-y-6">
                <!-- LOGO E ÍCONO DE ACCESO RESTRINGIDO -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                    <div class="w-18 h-18 sm:w-20 sm:h-20 shrink-0 rounded-2xl bg-gradient-to-br from-red-500/20 via-obsidian-card to-amber-500/10 border-2 border-red-500/50 flex items-center justify-center p-3 shadow-xl shadow-red-500/20">
                        <img src="{{ asset('img/logo.png') }}" alt="Seguridad Corporativa" class="w-full h-full object-contain filter drop-shadow-[0_0_10px_rgba(239,68,68,0.7)]">
                    </div>
                    <div class="space-y-1">
                        <div class="text-[11px] font-mono uppercase tracking-widest text-obsidian-muted flex items-center gap-1.5">
                            <span class="text-red-400 font-bold">ACCESO RESTRINGIDO</span>
                            <span>•</span>
                            <span>NODO VALLE SECO</span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                            <span>Acceso Restringido & Suspensión</span>
                            <span class="material-symbols-outlined text-red-400 text-2xl">lock</span>
                        </h1>
                        <p class="text-xs font-mono text-obsidian-muted">
                            La solicitud fue interceptada y neutralizada por el sistema de seguridad.
                        </p>
                    </div>
                </div>

                <!-- MENSAJE EXPLICATIVO DEL INCIDENTE -->
                <div class="p-4 rounded-xl bg-red-950/50 border border-red-500/40 text-red-200 text-xs font-mono flex items-start gap-3">
                    <span class="material-symbols-outlined text-red-400 text-xl shrink-0 mt-0.5">report</span>
                    <div class="space-y-1 leading-relaxed">
                        <span class="font-bold text-red-300 block uppercase text-[11px]">Motivo de la restricción:</span>
                        <p>{{ $message }}</p>
                    </div>
                </div>

                <!-- CONSOLA DE AUDITORÍA FORENSE (TERMINAL) -->
                <div class="rounded-xl bg-[#060f1c] border border-obsidian-border overflow-hidden">
                    <div class="px-4 py-2 bg-obsidian-panel/80 border-b border-obsidian-border flex items-center justify-between text-[11px] font-mono text-obsidian-muted">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500/80"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500/80"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/80"></span>
                            <span class="ml-2 text-obsidian-cyan font-semibold">terminal@atit-firewall:~#</span>
                        </div>
                        <span class="text-[10px] text-obsidian-muted font-mono">REGISTRO FORENSE</span>
                    </div>
                    <div class="p-4 font-mono text-xs space-y-2 text-obsidian-text/90">
                        <div class="flex flex-col sm:flex-row sm:justify-between py-0.5 border-b border-obsidian-border/40 gap-1">
                            <span class="text-obsidian-muted text-[11px] uppercase">Código de Incidente:</span>
                            <span class="text-red-400 font-bold tracking-wider">{{ $incidentRef }}</span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:justify-between py-0.5 border-b border-obsidian-border/40 gap-1">
                            <span class="text-obsidian-muted text-[11px] uppercase">Dirección IP del Cliente:</span>
                            <span class="text-white font-semibold flex items-center gap-1.5">
                                <code>{{ $clientIp }}</code>
                                @if(in_array($clientIp, ['127.0.0.1', '::1']))
                                    <span class="px-1.5 py-0.5 rounded text-[9px] bg-emerald-950 border border-emerald-500/40 text-emerald-300">Loopback Protegida</span>
                                @else
                                    <span class="px-1.5 py-0.5 rounded text-[9px] bg-red-950 border border-red-500/40 text-red-300">Lista Negra</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:justify-between py-0.5 border-b border-obsidian-border/40 gap-1">
                            <span class="text-obsidian-muted text-[11px] uppercase">Marca Temporal (VET):</span>
                            <span class="text-obsidian-cyan">{{ $timestamp }}</span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:justify-between py-0.5 border-b border-obsidian-border/40 gap-1">
                            <span class="text-obsidian-muted text-[11px] uppercase">Ruta Solicitada:</span>
                            <span class="text-amber-300">[{{ $method }}] /{{ ltrim($path, '/') }}</span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:justify-between py-0.5 gap-1">
                            <span class="text-obsidian-muted text-[11px] uppercase">Dispersión de Notificación:</span>
                            <span class="text-emerald-400 flex items-center gap-1 font-semibold text-[11px]">
                                <span class="material-symbols-outlined text-xs">send</span>
                                Despachado en vivo a Telegram SecOps
                            </span>
                        </div>
                    </div>
                </div>

                <!-- NOTA CORPORATIVA DE PROCEDIMIENTO -->
                <div class="p-3.5 rounded-xl bg-obsidian-panel/60 border border-obsidian-border text-[11px] font-mono text-obsidian-muted flex items-start gap-2.5">
                    <span class="material-symbols-outlined text-obsidian-cyan text-base shrink-0 mt-0.5">info</span>
                    <p class="leading-relaxed">
                        Si considera que este bloqueo responde a un falso positivo o error de privilegios en su turno de guardia, proporcione el identificador <code class="text-red-400 font-bold">{{ $incidentRef }}</code> al Administrador del Sistema para la verificación y restitución mediante el panel <span class="text-white">Baneos & Seguridad</span>.
                    </p>
                </div>

                <!-- BOTONES DE NAVEGACIÓN Y ACCIÓN -->
                <div class="pt-2 flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('home') }}" class="flex-1 py-3 px-4 rounded-xl bg-obsidian-panel border border-obsidian-border hover:border-obsidian-cyan text-obsidian-text hover:text-white font-mono text-xs font-bold transition flex items-center justify-center gap-2 group shadow-sm">
                        <span class="material-symbols-outlined text-base text-obsidian-cyan group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                        <span>Volver a Monitoreo Público</span>
                    </a>

                    <a href="{{ route('login') }}" class="flex-1 py-3 px-4 rounded-xl bg-gradient-to-r from-red-950 to-obsidian-panel border border-red-500/50 hover:border-red-400 text-red-300 hover:text-white font-mono text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm">
                        <span class="material-symbols-outlined text-base">login</span>
                        <span>Ir a Inicio de Sesión</span>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- PIE INSTITUCIONAL CORPORATIVO -->
    <footer class="relative z-10 text-center max-w-xl mx-auto space-y-1.5 pt-6 text-[10px] font-mono text-obsidian-muted/80">
        <p class="text-obsidian-text/80 font-semibold">
            Sistema de Monitoreo y Gestión de Red • División de Infraestructura Tecnológica
        </p>
        <p class="text-[9px] text-obsidian-muted/60">
            División de Operaciones y Redes • Nodo Valle Seco • Plataforma de Seguridad Perimetral v2.0
        </p>
    </footer>
</div>
@endsection
