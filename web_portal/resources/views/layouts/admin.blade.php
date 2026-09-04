@extends('layouts.app')

@section('content')
<!-- OVERLAY BACKDROP OSCURO PARA SIDEBAR -->
<div id="sidebar-backdrop" onclick="toggleAdminSidebar(false)" class="fixed inset-0 bg-black/70 backdrop-blur-xs z-40 hidden transition-opacity duration-300"></div>

<div class="min-h-screen flex relative">
    <!-- SIDEBAR DE ADMINISTRACIÓN RETRÁCTIL (AUTO-ESCONDIDO) -->
    <aside id="admin-sidebar" class="w-64 sm:w-72 glass-panel border-r border-obsidian-border flex flex-col justify-between shrink-0 z-50 fixed inset-y-0 left-0 transform -translate-x-full transition-transform duration-300 ease-in-out shadow-2xl bg-[#07172b]/98">
        <div>
            <!-- CABECERA DE SIDEBAR -->
            <div class="h-20 px-5 flex items-center justify-between border-b border-obsidian-border">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-tr from-obsidian-cyan/20 to-obsidian-purple/30 border border-obsidian-cyan/40 flex items-center justify-center text-obsidian-cyan glow-cyan p-1.5 shadow-lg shadow-cyan-500/20">
                        <img src="{{ asset('img/logo.png') }}" alt="Logo CORPOELEC" class="w-full h-full object-contain filter drop-shadow-[0_0_6px_rgba(34,211,238,0.6)]">
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-white tracking-tight">PANEL DE CONTROL</h2>
                        <p class="text-[10px] font-mono text-obsidian-cyan">ATIT VALLE SECO</p>
                    </div>
                </div>
                <!-- BOTÓN CERRAR SIDEBAR -->
                <button type="button" onclick="toggleAdminSidebar(false)" class="p-1.5 rounded-lg text-obsidian-muted hover:text-white hover:bg-obsidian-panel transition leading-none text-xl" title="Ocultar Menú">
                    &times;
                </button>
            </div>

            <!-- ENLACES DE NAVEGACIÓN -->
            <nav class="p-4 space-y-1.5 font-mono text-xs">
                <!-- DASHBOARD -->
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold transition {{ request()->routeIs('admin.dashboard') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">dashboard</span>
                    Dashboard
                </a>

                @if(auth()->user()->isAdmin())
                <!-- GESTIÓN DE USUARIOS (Solo Administrador) -->
                <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold transition {{ request()->routeIs('admin.users.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">group</span>
                    Usuarios
                </a>

                <!-- BANEOS & SEGURIDAD (Solo Administrador) -->
                <a href="{{ route('admin.bans.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold transition {{ request()->routeIs('admin.bans.*') ? 'bg-red-500 text-white shadow-lg shadow-red-500/20' : 'text-red-400 hover:text-white hover:bg-red-950/40' }}">
                    <span class="material-symbols-outlined text-lg">gavel</span>
                    Baneos & Seguridad
                </a>
                @endif

                <div class="pt-4 pb-1 px-4 text-[10px] uppercase tracking-wider text-obsidian-muted/60">
                    Infraestructura Monitoreada
                </div>

                <!-- SERVICIOS -->
                <a href="{{ route('admin.services.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-semibold transition {{ request()->routeIs('admin.services.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">dns</span>
                    Servicios
                </a>

                <!-- SEDES -->
                <a href="{{ route('admin.sites.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-semibold transition {{ request()->routeIs('admin.sites.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">domain</span>
                    Sedes & Enlaces
                </a>

                <!-- PROXIES -->
                <a href="{{ route('admin.proxies.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-semibold transition {{ request()->routeIs('admin.proxies.*') ? 'bg-obsidian-cyan text-black' : 'text-obsidian-muted hover:text-white hover:bg-obsidian-panel' }}">
                    <span class="material-symbols-outlined text-lg">public</span>
                    Proxies
                </a>
            </nav>
        </div>

        <!-- PIE DE SIDEBAR -->
        <div class="p-4 border-t border-obsidian-border space-y-2">
            @if(auth()->user()->isAdmin())
            <!-- BOTÓN ESCANEAR AHORA (Solo Administrador) -->
            <button onclick="triggerImmediateScan()" id="btn-scan-now" class="w-full py-2.5 px-3 rounded-lg bg-obsidian-panel border border-obsidian-border text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black font-mono text-xs font-bold transition flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-base" id="icon-scan-now">bolt</span>
                <span id="text-scan-now">Escanear Ahora</span>
            </button>
            @endif

            <!-- VER SITIO PÚBLICO -->
            <a href="{{ route('home') }}" target="_blank" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-mono text-obsidian-muted hover:text-white hover:bg-obsidian-panel transition">
                <span class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">visibility</span>
                    Ver Sitio Público
                </span>
                <span class="material-symbols-outlined text-sm">open_in_new</span>
            </a>

            <!-- CERRAR SESIÓN -->
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-mono text-red-400 hover:bg-red-950/40 hover:text-red-300 transition">
                    <span class="material-symbols-outlined text-base">logout</span>
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </aside>

    <!-- CONTENEDOR PRINCIPAL DERECHO (PANTALLA COMPLETA) -->
    <div class="flex-1 w-full flex flex-col min-w-0">
        <!-- TOPBAR ADMINISTRATIVA -->
        <header class="h-20 glass-panel border-b border-obsidian-border sticky top-0 z-30 px-4 sm:px-6 flex items-center justify-between">
            <div class="flex items-center space-x-3 sm:space-x-4">
                <!-- BOTÓN REFERENCIAL TOGGLE PARA DESPLEGAR EL MENÚ LATERAL -->
                <button type="button" onclick="toggleAdminSidebar()" id="btn-toggle-sidebar" class="px-3 py-2 rounded-xl bg-obsidian-cyan/15 border border-obsidian-cyan/50 text-obsidian-cyan hover:bg-obsidian-cyan hover:text-black transition flex items-center gap-2 font-mono text-xs font-bold shadow-lg shadow-cyan-500/10 cursor-pointer" title="Desplegar Menú Lateral">
                    <span class="material-symbols-outlined text-lg" id="icon-toggle-sidebar">menu</span>
                    <span class="hidden sm:inline">Menú</span>
                </button>

                <div class="flex items-center space-x-2">
                    <span class="text-xs font-mono text-obsidian-muted hidden md:inline">Panel Administrativo</span>
                    <span class="text-obsidian-border hidden md:inline">/</span>
                    <h1 class="text-base font-bold text-white">@yield('page_title', 'Dashboard')</h1>
                </div>
            </div>

            <!-- USUARIO EN SESIÓN -->
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <span class="text-xs font-bold text-white block">{{ Auth::user()->name }}</span>
                    <span class="text-[10px] font-mono text-obsidian-cyan uppercase">{{ Auth::user()->role }}</span>
                </div>
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-obsidian-cyan to-obsidian-purple text-black font-bold flex items-center justify-center text-sm shadow-md">
                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                </div>
            </div>
        </header>

        <!-- CONTENIDO DE LA PÁGINA -->
        <main class="flex-1 p-4 sm:p-6 space-y-6">
            <!-- ALERTAS FLASH -->
            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/50 text-emerald-400 text-xs font-mono flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">check_circle</span>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 rounded-xl bg-red-950/60 border border-red-500/50 text-red-400 text-xs font-mono flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">error</span>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-white">&times;</button>
                </div>
            @endif

            @if($errors->any())
                <div class="p-4 rounded-xl bg-red-950/60 border border-red-500/50 text-red-400 text-xs font-mono space-y-1">
                    <div class="flex items-center gap-2 font-bold">
                        <span class="material-symbols-outlined text-base">warning</span>
                        <span>Por favor corrige los siguientes errores:</span>
                    </div>
                    @foreach($errors->all() as $err)
                        <p class="pl-6">• {{ $err }}</p>
                    @endforeach
                </div>
            @endif

            @yield('admin_content')
        </main>
    </div>
</div>

<script>
    function toggleAdminSidebar(forceState) {
        const sidebar = document.getElementById('admin-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const icon = document.getElementById('icon-toggle-sidebar');
        if (!sidebar) return;

        const isClosed = sidebar.classList.contains('-translate-x-full');
        const shouldOpen = (typeof forceState === 'boolean') ? forceState : isClosed;

        if (shouldOpen) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            if (backdrop) backdrop.classList.remove('hidden');
            if (icon) icon.innerText = 'menu_open';
        } else {
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            if (backdrop) backdrop.classList.add('hidden');
            if (icon) icon.innerText = 'menu';
        }
    }

    // Cerrar sidebar al presionar Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            toggleAdminSidebar(false);
        }
    });

    async function triggerImmediateScan() {
        const btn = document.getElementById('btn-scan-now');
        const icon = document.getElementById('icon-scan-now');
        const txt = document.getElementById('text-scan-now');
        
        btn.disabled = true;
        icon.classList.add('animate-spin');
        txt.innerText = 'Escaneando...';

        try {
            const res = await fetch('{{ route("admin.sync.scan") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                }
            });
            const data = await res.json();
            if (data.success) {
                alert('⚡ Escaneo completado exitosamente.\n' + data.output);
                window.location.reload();
            } else {
                alert('⚠️ Error durante el escaneo: ' + (data.error || data.message));
            }
        } catch (err) {
            alert('Error de red al disparar escaneo: ' + err);
        } finally {
            btn.disabled = false;
            icon.classList.remove('animate-spin');
            txt.innerText = 'Escanear Ahora';
        }
    }
</script>
@endsection
