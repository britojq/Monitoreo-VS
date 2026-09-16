@extends('layouts.app')

@section('title', '419 Sesión Expirada • Sistema de Monitoreo')

@php
    $clientIp = request()->ip() ?: '0.0.0.0';
    $timestamp = now()->format('d/m/Y H:i:s');
    $method = request()->method();
    $path = request()->path();
    $homeUrl = route('home');
@endphp

@section('content')
<noscript>
    <meta http-equiv="refresh" content="5;url={{ route('login') }}">
</noscript>

<div class="min-h-screen flex flex-col justify-between py-8 px-4 sm:px-6 lg:px-8 relative overflow-hidden bg-obsidian-bg">
    <!-- EFECTOS AMBIENTALES (ÁMBAR Y AMARILLO) -->
    <div class="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-amber-500/10 rounded-full filter blur-[140px] pointer-events-none"></div>
    <div class="absolute bottom-1/4 right-1/4 w-[450px] h-[450px] bg-yellow-600/10 rounded-full filter blur-[140px] pointer-events-none"></div>
    
    <!-- LÍNEAS DE CUADRÍCULA DECORATIVAS EN FONDO -->
    <div class="absolute inset-0 bg-[radial-gradient(#1c2e47_1px,transparent_1px)] [background-size:24px_24px] opacity-25 pointer-events-none"></div>

    <!-- CABECERA DE ESTADO -->
    <header class="relative z-10 text-center max-w-2xl mx-auto space-y-2">
        <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-amber-950/70 border border-amber-500/30 text-amber-400 text-xs font-mono shadow-lg shadow-amber-950/30">
            <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
            <span class="font-bold uppercase tracking-wider">Control de Integridad de Sesión y Tokens CSRF</span>
        </div>
    </header>

    <!-- TARJETA PRINCIPAL DEL ERROR 419 -->
    <main class="relative z-10 max-w-2xl w-full mx-auto my-auto py-4">
        <div class="glass-panel rounded-2xl overflow-hidden border border-amber-500/40 shadow-2xl shadow-amber-950/30">
            <!-- BARRA SUPERIOR ESTILO TERMINAL -->
            <div class="bg-gradient-to-r from-amber-950/80 via-[#131f30] to-amber-950/80 px-6 py-3.5 border-b border-amber-500/30 flex items-center justify-between">
                <div class="flex items-center space-x-2 text-xs font-mono font-bold text-amber-300">
                    <span class="material-symbols-outlined text-base text-amber-400">hourglass_disabled</span>
                    <span>CADUCIDAD DE TOKEN DE SEGURIDAD</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="px-2.5 py-0.5 rounded text-[11px] font-mono font-bold bg-amber-500/20 border border-amber-500/40 text-amber-300">
                        HTTP 419
                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-black/40 border border-obsidian-border text-obsidian-muted">
                        PAGE EXPIRED
                    </span>
                </div>
            </div>

            <!-- CONTENIDO DE LA ALERTA -->
            <div class="p-6 sm:p-8 space-y-6">
                <!-- GRÁFICO 419 Y TÍTULO -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-6">
                    <div class="relative shrink-0 flex items-center justify-center">
                        <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-amber-500/20 via-obsidian-card to-yellow-500/10 border-2 border-amber-500/40 flex flex-col items-center justify-center shadow-xl shadow-amber-500/10">
                            <span class="material-symbols-outlined text-4xl text-amber-400">history_toggle_off</span>
                            <span class="font-mono text-xs font-extrabold text-amber-300 tracking-wider">419</span>
                        </div>
                    </div>
                    <div class="space-y-1.5 flex-1">
                        <div class="text-[11px] font-mono uppercase tracking-widest text-obsidian-muted flex items-center gap-1.5">
                            <span class="text-amber-400 font-bold">SESIÓN CADUCADA</span>
                            <span>•</span>
                            <span>VALLE SECO MONITOREO</span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                            <span>Página o Sesión Expirada</span>
                        </h1>
                        <p class="text-xs font-mono text-obsidian-muted leading-relaxed">
                            La clave temporal de seguridad del formulario ha caducado por inactividad. Inicie sesión nuevamente o recargue la página.
                        </p>
                    </div>
                </div>

                <!-- CAJA DE REDIRECCIÓN AUTOMÁTICA -->
                <div class="p-4 rounded-xl bg-[#081524] border border-amber-500/30 shadow-inner space-y-3">
                    <div class="flex items-center justify-between text-xs font-mono">
                        <div class="flex items-center gap-2 text-obsidian-text">
                            <span class="material-symbols-outlined text-amber-400 text-lg animate-spin" style="animation-duration: 3s;">sync</span>
                            <span>Redireccionando al inicio en:</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="countdown-badge" class="px-2.5 py-0.5 rounded-lg bg-amber-500/20 border border-amber-500/50 text-amber-300 font-mono text-sm font-bold shadow-sm shadow-amber-500/20">
                                <span id="countdown-number">5</span>s
                            </span>
                            <button id="toggle-redirect-btn" type="button" onclick="toggleRedirect()" class="text-[10px] font-mono text-obsidian-muted hover:text-white px-2 py-0.5 rounded border border-obsidian-border bg-obsidian-panel/80 transition hover:border-amber-400 flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-xs" id="toggle-icon">pause</span>
                                <span id="toggle-text">Pausar</span>
                            </button>
                        </div>
                    </div>

                    <!-- BARRA DE PROGRESO DINÁMICA -->
                    <div class="w-full bg-[#030a12] rounded-full h-1.5 overflow-hidden p-0.5 border border-obsidian-border">
                        <div id="progress-bar" class="bg-gradient-to-r from-amber-500 to-yellow-500 h-full rounded-full transition-all duration-1000 ease-linear" style="width: 100%;"></div>
                    </div>
                    <div class="flex items-center justify-between text-[10px] font-mono text-obsidian-muted">
                        <span>Destino: <code class="text-amber-300 font-semibold">{{ route('home') }}</code></span>
                        <span id="status-timer-text">Conectando a la página principal...</span>
                    </div>
                </div>

                <!-- BOTONES DE NAVEGACIÓN DIRECTA -->
                <div class="pt-2 flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('home') }}" id="btn-home" class="flex-1 py-3 px-4 rounded-xl bg-gradient-to-r from-amber-600 to-yellow-600 hover:from-amber-500 hover:to-yellow-500 text-white font-mono text-xs font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-amber-600/20 group">
                        <span class="material-symbols-outlined text-base group-hover:-translate-x-0.5 transition-transform">home</span>
                        <span>Volver a la Página Inicial</span>
                    </a>

                    <a href="{{ route('login') }}" class="flex-1 py-3 px-4 rounded-xl bg-obsidian-panel border border-obsidian-border hover:border-amber-400 text-obsidian-text hover:text-white font-mono text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm">
                        <span class="material-symbols-outlined text-base text-amber-400">login</span>
                        <span>Ir a Inicio de Sesión</span>
                    </a>
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
