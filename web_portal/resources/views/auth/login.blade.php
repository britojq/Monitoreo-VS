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
                <img src="{{ asset('img/logo.png') }}" alt="Logo CORPOELEC" class="w-full h-full object-contain filter drop-shadow-[0_0_8px_rgba(34,211,238,0.6)]">
            </div>
            <h2 class="text-2xl font-bold text-white tracking-tight">Acceso Administrativo</h2>
            <p class="text-xs font-mono text-obsidian-muted">
                ATIT • Monitoreo de Infraestructura - Valle Seco
            </p>
        </div>

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
                    <span>Usuario LDAP o Correo Admin</span>
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

        <!-- VOLVER AL SITIO PÚBLICO -->
        <div class="mt-6 pt-6 border-t border-obsidian-border/60 text-center">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-xs font-mono text-obsidian-cyan hover:underline">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                Volver a la vista pública de monitoreo
            </a>
        </div>
    </div>
</div>
@endsection
