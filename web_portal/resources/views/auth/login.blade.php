@extends('layouts.app')

@section('title', 'Iniciar Sesión • ATIT Valle Seco')

@section('content')
<div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- BACKGROUND GLOWS -->
    <div class="absolute top-1/4 left-1/3 w-96 h-96 bg-obsidian-cyan/10 rounded-full filter blur-[100px] pointer-events-none"></div>
    <div class="absolute bottom-1/4 right-1/3 w-96 h-96 bg-obsidian-purple/10 rounded-full filter blur-[100px] pointer-events-none"></div>

    <div class="max-w-md w-full glass-panel rounded-2xl p-8 relative z-10 shadow-2xl border border-obsidian-border/80">
        <!-- LOGO & CABECERA -->
        <div class="text-center space-y-3">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl bg-gradient-to-tr from-obsidian-cyan/20 to-obsidian-purple/30 border border-obsidian-cyan/40 text-obsidian-cyan glow-cyan mb-2 p-2.5 shadow-lg shadow-cyan-500/20">
                <img src="{{ asset('img/logo.png') }}" alt="Logo Corporativo" class="w-full h-full object-contain filter drop-shadow-[0_0_8px_rgba(34,211,238,0.6)]" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                <span class="material-symbols-outlined text-3xl text-obsidian-cyan hidden">monitoring</span>
            </div>
            <h2 class="text-2xl font-bold text-white tracking-tight">Acceso Administrativo</h2>
            <p class="text-xs font-mono text-obsidian-muted">
                ATIT • Monitoreo de Infraestructura - Valle Seco
            </p>
        </div>

        <!-- AVISO INFORMATIVO (EJ: CIERRE POR INACTIVIDAD) -->
        @if (session('info'))
            <div class="mt-6 p-4 rounded-xl bg-cyan-950/60 border border-cyan-500/50 text-cyan-300 text-xs font-mono flex items-center gap-2.5 shadow-lg shadow-cyan-500/10">
                <span class="material-symbols-outlined text-base text-cyan-400 shrink-0">timer</span>
                <span>{{ session('info') }}</span>
            </div>
        @endif

        <!-- MENSAJE DE ERROR -->
        @if ($errors->any())
            <div class="mt-6 p-4 rounded-xl bg-red-950/60 border border-red-500/50 text-red-400 text-xs font-mono space-y-1">
                <div class="flex items-center gap-2 font-bold">
                    <span class="material-symbols-outlined text-sm">error</span>
                    <span>Error de Autenticación</span>
                </div>
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <!-- FORMULARIO DE LOGIN -->
        <form class="mt-6 space-y-5" action="{{ route('login.submit') }}" method="POST">
            @csrf

            <!-- USUARIO LDAP O CORREO ADMINISTRADOR -->
            <div>
                <label for="login" class="block text-xs font-mono uppercase text-obsidian-muted mb-2 flex items-center justify-between">
                    <span>Usuario LDAP o Correo</span>
                    <span class="text-[10px] text-obsidian-cyan/70 font-normal lowercase">ej: usuario o correo</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-obsidian-muted">
                        <span class="material-symbols-outlined text-lg">badge</span>
                    </div>
                    <input id="login" name="login" type="text" autocomplete="username" required value="{{ old('login', old('email')) }}" placeholder="Ej: X1029384 o usuario@dominio.com" class="block w-full pl-10 pr-4 py-2.5 bg-obsidian-panel/80 border border-obsidian-border rounded-lg text-sm text-white placeholder-obsidian-muted focus:outline-none focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan font-mono transition"/>
                </div>
            </div>

            <!-- CONTRASEÑA -->
            <div>
                <label for="password" class="block text-xs font-mono uppercase text-obsidian-muted mb-2">Contraseña</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-obsidian-muted">
                        <span class="material-symbols-outlined text-lg">lock</span>
                    </div>
                    <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="••••••••" class="block w-full pl-10 pr-4 py-2.5 bg-obsidian-panel/80 border border-obsidian-border rounded-lg text-sm text-white placeholder-obsidian-muted focus:outline-none focus:border-obsidian-cyan focus:ring-1 focus:ring-obsidian-cyan font-mono transition"/>
                </div>
            </div>

            <!-- RECORDAR SESIÓN -->
            <div class="flex items-center justify-between">
                <label class="flex items-center space-x-2 text-xs font-mono text-obsidian-muted cursor-pointer">
                    <input type="checkbox" name="remember" class="w-4 h-4 rounded bg-obsidian-panel border-obsidian-border text-obsidian-cyan focus:ring-0 focus:ring-offset-0"/>
                    <span>Recordar sesión</span>
                </label>
            </div>

            <!-- BOTÓN INGRESAR -->
            <button type="submit" class="w-full py-3 px-4 rounded-lg bg-obsidian-cyan text-black font-bold text-sm tracking-wider uppercase transition-all duration-200 hover:bg-cyan-300 hover:shadow-lg hover:shadow-cyan-500/30 flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-lg">login</span>
                Ingresar al Sistema
            </button>
        </form>

        <!-- REDIRECCIÓN AUTOMÁTICA A VISTA PÚBLICA (15s) -->
        <div id="redirect-widget" class="mt-6 pt-5 border-t border-obsidian-border/60 space-y-3">
            <div class="flex items-center justify-between text-[11px] font-mono">
                <div class="flex items-center gap-1.5 text-obsidian-muted" id="redirect-status-text">
                    <span class="material-symbols-outlined text-sm text-obsidian-cyan animate-pulse" id="redirect-icon">schedule</span>
                    <span>Retornando a la vista pública en <strong id="redirect-countdown" class="text-obsidian-cyan font-bold">15</strong>s</span>
                </div>
                <button type="button" id="btn-pause-redirect" class="text-[10px] px-2 py-0.5 rounded border border-obsidian-border text-obsidian-muted hover:text-white hover:border-obsidian-cyan/50 hover:bg-obsidian-panel transition">
                    Pausar
                </button>
            </div>

            <!-- BARRA DE PROGRESO DE CUENTA REGRESIVA -->
            <div class="w-full bg-obsidian-panel/80 rounded-full h-1 overflow-hidden border border-obsidian-border/50">
                <div id="redirect-progress-bar" class="h-full bg-gradient-to-r from-obsidian-cyan to-emerald-400 transition-all duration-1000 ease-linear" style="width: 100%;"></div>
            </div>

            <div class="text-center pt-1">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-xs font-mono text-obsidian-cyan hover:underline group">
                    <span class="material-symbols-outlined text-sm group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                    Volver a la vista pública de monitoreo ahora
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const TOTAL_SECONDS = 15;
    let remaining = TOTAL_SECONDS;
    let isPaused = false;
    let timerId = null;

    const countdownEl = document.getElementById('redirect-countdown');
    const progressBar = document.getElementById('redirect-progress-bar');
    const pauseBtn = document.getElementById('btn-pause-redirect');
    const statusText = document.getElementById('redirect-status-text');
    const loginInput = document.getElementById('login');
    const passwordInput = document.getElementById('password');

    function updateDisplay() {
        if (countdownEl) {
            countdownEl.textContent = remaining;
        }
        if (progressBar) {
            const pct = Math.max(0, (remaining / TOTAL_SECONDS) * 100);
            progressBar.style.width = pct + '%';
        }
    }

    function pauseTimer() {
        if (isPaused) return;
        isPaused = true;
        clearInterval(timerId);
        if (pauseBtn) {
            pauseBtn.textContent = 'Reanudar';
            pauseBtn.classList.add('border-amber-500/50', 'text-amber-300');
        }
        if (statusText) {
            statusText.innerHTML = `<span class="material-symbols-outlined text-sm text-amber-400">pause_circle</span> <span>Temporizador pausado</span>`;
        }
        if (progressBar) {
            progressBar.classList.remove('from-obsidian-cyan', 'to-emerald-400');
            progressBar.classList.add('from-amber-500', 'to-yellow-400');
        }
    }

    function resumeTimer() {
        if (!isPaused) return;
        isPaused = false;
        if (pauseBtn) {
            pauseBtn.textContent = 'Pausar';
            pauseBtn.classList.remove('border-amber-500/50', 'text-amber-300');
        }
        if (statusText) {
            statusText.innerHTML = `<span class="material-symbols-outlined text-sm text-obsidian-cyan animate-pulse">schedule</span> <span>Retornando a la vista pública en <strong id="redirect-countdown" class="text-obsidian-cyan font-bold">${remaining}</strong>s</span>`;
        }
        if (progressBar) {
            progressBar.classList.remove('from-amber-500', 'to-yellow-400');
            progressBar.classList.add('from-obsidian-cyan', 'to-emerald-400');
        }
        startTimer();
    }

    function tick() {
        if (isPaused) return;

        // Si el usuario ya comenzó a escribir en el formulario, pausar automáticamente para no interrumpirlo
        if ((loginInput && loginInput.value.trim().length > 0) || (passwordInput && passwordInput.value.trim().length > 0)) {
            pauseTimer();
            return;
        }

        remaining--;
        updateDisplay();

        if (remaining <= 0) {
            clearInterval(timerId);
            if (statusText) {
                statusText.innerHTML = `<span class="material-symbols-outlined text-sm text-obsidian-cyan animate-spin">sync</span> <span class="text-obsidian-cyan font-bold">Redireccionando...</span>`;
            }
            window.location.href = "{{ route('home') }}";
        }
    }

    function startTimer() {
        clearInterval(timerId);
        timerId = setInterval(tick, 1000);
    }

    // Eventos de pausa interactiva
    if (pauseBtn) {
        pauseBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (isPaused) {
                resumeTimer();
            } else {
                pauseTimer();
            }
        });
    }

    // Auto-pausar si el usuario interactúa o escribe en el formulario de login
    [loginInput, passwordInput].forEach(input => {
        if (input) {
            input.addEventListener('input', () => {
                pauseTimer();
            });
            input.addEventListener('focus', () => {
                if (input.value.trim().length > 0) {
                    pauseTimer();
                }
            });
        }
    });

    // Iniciar temporizador
    updateDisplay();
    startTimer();
});
</script>
@endsection
