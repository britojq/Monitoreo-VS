@extends('layouts.app')

@section('title', '404 Recurso No Encontrado • Sistema de Monitoreo')

@php
    $clientIp = request()->ip() ?: '0.0.0.0';
    $timestamp = now()->format('d/m/Y H:i:s');
    $method = request()->method();
    $path = request()->path();
    $fullUrl = request()->fullUrl();
    $homeUrl = route('home');
@endphp

@section('content')
<noscript>
    <meta http-equiv="refresh" content="5;url={{ route('home') }}">
</noscript>

<div class="min-h-screen flex flex-col justify-between py-8 px-4 sm:px-6 lg:px-8 relative overflow-hidden bg-obsidian-bg">
    <!-- EFECTOS AMBIENTALES DE ILUMINACIÓN NEÓN (CYAN Y PÚRPURA) -->
    <div class="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-cyan-500/10 rounded-full filter blur-[140px] pointer-events-none"></div>
    <div class="absolute bottom-1/4 right-1/4 w-[450px] h-[450px] bg-purple-600/10 rounded-full filter blur-[140px] pointer-events-none"></div>
    
    <!-- LÍNEAS DE CUADRÍCULA DECORATIVAS EN FONDO -->
    <div class="absolute inset-0 bg-[radial-gradient(#1c2e47_1px,transparent_1px)] [background-size:24px_24px] opacity-25 pointer-events-none"></div>

    <!-- CABECERA DE ESTADO -->
    <header class="relative z-10 text-center max-w-2xl mx-auto space-y-2">
        <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-cyan-950/70 border border-cyan-500/30 text-cyan-400 text-xs font-mono shadow-lg shadow-cyan-950/30">
            <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
            <span class="font-bold uppercase tracking-wider">Servicio de Diagnóstico y Enrutamiento HTTP</span>
        </div>
    </header>

    <!-- TARJETA PRINCIPAL DEL ERROR 404 -->
    <main class="relative z-10 max-w-2xl w-full mx-auto my-auto py-4">
        <div class="glass-panel rounded-2xl overflow-hidden border border-obsidian-border shadow-2xl shadow-cyan-950/30">
            <!-- BARRA SUPERIOR ESTILO TERMINAL -->
            <div class="bg-gradient-to-r from-[#0a1626] via-[#0d1d33] to-[#0a1626] px-6 py-3.5 border-b border-obsidian-border flex items-center justify-between">
                <div class="flex items-center space-x-2 text-xs font-mono font-bold text-obsidian-cyan">
                    <span class="material-symbols-outlined text-base">travel_explore</span>
                    <span>RUTA NO IDENTIFICADA EN EL CLÚSTER</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="px-2.5 py-0.5 rounded text-[11px] font-mono font-bold bg-cyan-500/20 border border-cyan-500/40 text-cyan-300">
                        HTTP 404
                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-black/40 border border-obsidian-border text-obsidian-muted">
                        NOT FOUND
                    </span>
                </div>
            </div>

            <!-- CONTENIDO DE LA ALERTA -->
            <div class="p-6 sm:p-8 space-y-6">
                <!-- GRÁFICO 404 Y TÍTULO -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-6">
                    <div class="relative shrink-0 flex items-center justify-center">
                        <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-cyan-500/20 via-obsidian-card to-purple-500/10 border-2 border-cyan-500/40 flex flex-col items-center justify-center shadow-xl shadow-cyan-500/10">
                            <span class="material-symbols-outlined text-4xl text-cyan-400">search_off</span>
                            <span class="font-mono text-xs font-extrabold text-cyan-300 tracking-wider">404</span>
                        </div>
                    </div>
                    <div class="space-y-1.5 flex-1">
                        <div class="text-[11px] font-mono uppercase tracking-widest text-obsidian-muted flex items-center gap-1.5">
                            <span class="text-cyan-400 font-bold">RECURSO INEXISTENTE</span>
                            <span>•</span>
                            <span>VALLE SECO MONITOREO</span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                            <span>Página No Encontrada</span>
                        </h1>
                        <p class="text-xs font-mono text-obsidian-muted leading-relaxed">
                            La dirección web o endpoint solicitado no existe en este servidor, ha sido reubicado o no se encuentra disponible.
                        </p>
                    </div>
                </div>

                <!-- CAJA DE REDIRECCIÓN AUTOMÁTICA CON CUENTA REGRESIVA Y BARRA DE PROGRESO -->
                <div class="p-4 rounded-xl bg-[#081524] border border-cyan-500/30 shadow-inner space-y-3">
                    <div class="flex items-center justify-between text-xs font-mono">
                        <div class="flex items-center gap-2 text-obsidian-text">
                            <span class="material-symbols-outlined text-cyan-400 text-lg animate-spin" style="animation-duration: 3s;">sync</span>
                            <span>Redireccionando automáticamente en:</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="countdown-badge" class="px-2.5 py-0.5 rounded-lg bg-cyan-500/20 border border-cyan-500/50 text-cyan-300 font-mono text-sm font-bold shadow-sm shadow-cyan-500/20">
                                <span id="countdown-number">5</span>s
                            </span>
                            <button id="toggle-redirect-btn" type="button" onclick="toggleRedirect()" class="text-[10px] font-mono text-obsidian-muted hover:text-white px-2 py-0.5 rounded border border-obsidian-border bg-obsidian-panel/80 transition hover:border-obsidian-cyan flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-xs" id="toggle-icon">pause</span>
                                <span id="toggle-text">Pausar</span>
                            </button>
                        </div>
                    </div>

                    <!-- BARRA DE PROGRESO DINÁMICA -->
                    <div class="w-full bg-[#030a12] rounded-full h-1.5 overflow-hidden p-0.5 border border-obsidian-border">
                        <div id="progress-bar" class="bg-gradient-to-r from-cyan-500 to-purple-500 h-full rounded-full transition-all duration-1000 ease-linear" style="width: 100%;"></div>
                    </div>
                    <div class="flex items-center justify-between text-[10px] font-mono text-obsidian-muted">
                        <span>Destino: <code class="text-cyan-400 font-semibold">{{ route('home') }}</code></span>
                        <span id="status-timer-text">Conectando al inicio...</span>
                    </div>
                </div>

                <!-- CONSOLA DE AUDITORÍA Y DETALLE TÉCNICO (TERMINAL) -->
                <div class="rounded-xl bg-[#060f1c] border border-obsidian-border overflow-hidden">
                    <div class="px-4 py-2 bg-obsidian-panel/80 border-b border-obsidian-border flex items-center justify-between text-[11px] font-mono text-obsidian-muted">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500/80"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500/80"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/80"></span>
                            <span class="ml-2 text-obsidian-cyan font-semibold">diagnostics@valle-seco:~#</span>
                        </div>
                        <span class="text-[10px] text-obsidian-muted font-mono">REGISTRO DE RUTA</span>
                    </div>
                    <div class="p-4 font-mono text-xs space-y-2 text-obsidian-text/90">
                        <div class="flex flex-col sm:flex-row sm:justify-between py-0.5 border-b border-obsidian-border/40 gap-1">
                            <span class="text-obsidian-muted text-[11px] uppercase">Ruta Solicitada:</span>
                            <span class="text-amber-300 break-all font-semibold">[{{ $method }}] /{{ ltrim($path, '/') }}</span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:justify-between py-0.5 border-b border-obsidian-border/40 gap-1">
                            <span class="text-obsidian-muted text-[11px] uppercase">Dirección IP del Cliente:</span>
                            <span class="text-white font-semibold"><code>{{ $clientIp }}</code></span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:justify-between py-0.5 gap-1">
                            <span class="text-obsidian-muted text-[11px] uppercase">Marca Temporal (VET):</span>
                            <span class="text-obsidian-cyan">{{ $timestamp }}</span>
                        </div>
                    </div>
                </div>

                <!-- BOTONES DE NAVEGACIÓN DIRECTA -->
                <div class="pt-2 flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('home') }}" id="btn-home" class="flex-1 py-3 px-4 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white font-mono text-xs font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-cyan-600/20 group">
                        <span class="material-symbols-outlined text-base group-hover:-translate-x-0.5 transition-transform">home</span>
                        <span>Volver a la Página Inicial</span>
                    </a>

                    @auth
                    <a href="{{ route('admin.dashboard') }}" class="flex-1 py-3 px-4 rounded-xl bg-obsidian-panel border border-obsidian-border hover:border-obsidian-cyan text-obsidian-text hover:text-white font-mono text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm">
                        <span class="material-symbols-outlined text-base text-obsidian-cyan">dashboard</span>
                        <span>Panel de Administración</span>
                    </a>
                    @else
                    <a href="{{ route('login') }}" class="flex-1 py-3 px-4 rounded-xl bg-obsidian-panel border border-obsidian-border hover:border-obsidian-cyan text-obsidian-text hover:text-white font-mono text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm">
                        <span class="material-symbols-outlined text-base text-obsidian-cyan">login</span>
                        <span>Iniciar Sesión</span>
                    </a>
                    @endauth
                </div>
            </div>
        </div>
    </main>

    <!-- PIE INSTITUCIONAL -->
    <footer class="relative z-10 text-center max-w-xl mx-auto space-y-1 pt-4 text-[10px] font-mono text-obsidian-muted/80">
        <p class="text-obsidian-text/80 font-semibold">
            Sistema de Monitoreo de Infraestructura • Valle Seco
        </p>
        <p class="text-[9px] text-obsidian-muted/60">
            Plataforma Corporativa de Supervisión y Telemetría v2.0
        </p>
    </footer>
</div>

<script>
    (function() {
        let secondsLeft = 5;
        const totalSeconds = 5;
        let isPaused = false;
        const targetUrl = "{{ route('home') }}";
        
        const numberEl = document.getElementById('countdown-number');
        const progressEl = document.getElementById('progress-bar');
        const toggleBtn = document.getElementById('toggle-redirect-btn');
        const toggleIcon = document.getElementById('toggle-icon');
        const toggleText = document.getElementById('toggle-text');
        const statusText = document.getElementById('status-timer-text');

        window.toggleRedirect = function() {
            isPaused = !isPaused;
            if (isPaused) {
                toggleIcon.textContent = 'play_arrow';
                toggleText.textContent = 'Reanudar';
                statusText.textContent = 'Redirección en pausa';
                statusText.className = 'text-amber-400 font-semibold';
            } else {
                toggleIcon.textContent = 'pause';
                toggleText.textContent = 'Pausar';
                statusText.textContent = 'Conectando al inicio...';
                statusText.className = 'text-obsidian-muted';
            }
        };

        const interval = setInterval(function() {
            if (isPaused) return;

            secondsLeft--;
            if (numberEl) {
                numberEl.textContent = Math.max(0, secondsLeft);
            }
            if (progressEl) {
                const pct = (secondsLeft / totalSeconds) * 100;
                progressEl.style.width = Math.max(0, pct) + '%';
            }

            if (secondsLeft <= 0) {
                clearInterval(interval);
                window.location.href = targetUrl;
            }
        }, 1000);
    })();
</script>
@endsection
