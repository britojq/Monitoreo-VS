<!DOCTYPE html>
<html class="dark" lang="es">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <title>@yield('title', 'ATIT • Monitoreo de Infraestructura - Valle Seco')</title>
    
    <!-- Favicons & App Icons -->
    <link rel="apple-touch-icon" sizes="57x57" href="{{ asset('img/ico/apple-icon-57x57.png') }}">
    <link rel="apple-touch-icon" sizes="60x60" href="{{ asset('img/ico/apple-icon-60x60.png') }}">
    <link rel="apple-touch-icon" sizes="72x72" href="{{ asset('img/ico/apple-icon-72x72.png') }}">
    <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('img/ico/apple-icon-76x76.png') }}">
    <link rel="apple-touch-icon" sizes="114x114" href="{{ asset('img/ico/apple-icon-114x114.png') }}">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('img/ico/apple-icon-120x120.png') }}">
    <link rel="apple-touch-icon" sizes="144x144" href="{{ asset('img/ico/apple-icon-144x144.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('img/ico/apple-icon-152x152.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/ico/apple-icon-180x180.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('img/ico/android-icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/ico/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('img/ico/favicon-96x96.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('img/ico/favicon-16x16.png') }}">
    <link rel="shortcut icon" href="{{ asset('img/ico/favicon.ico') }}" type="image/x-icon">
    <link rel="manifest" href="{{ asset('img/ico/manifest.json') }}">
    <meta name="msapplication-TileColor" content="#051424">
    <meta name="msapplication-TileImage" content="{{ asset('img/ico/ms-icon-144x144.png') }}">
    <meta name="theme-color" content="#051424">
    
    <!-- Activos Locales 100% Autónomos (Cero CDNs Externos) -->
    <link rel="stylesheet" href="{{ asset('vendor/fonts/fonts.css') }}"/>
    <link rel="stylesheet" href="{{ asset('vendor/material-symbols/material-symbols.css') }}"/>
    <script src="{{ asset('vendor/tailwindcss/tailwind.min.js') }}"></script>
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>

    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "obsidian-bg": "#051424",
                        "obsidian-card": "#0b1726",
                        "obsidian-panel": "#101f33",
                        "obsidian-border": "#1c2e47",
                        "obsidian-cyan": "#22d3ee",
                        "obsidian-purple": "#8b5cf6",
                        "obsidian-green": "#10b981",
                        "obsidian-red": "#ef4444",
                        "obsidian-amber": "#f59e0b",
                        "obsidian-text": "#d4e4fa",
                        "obsidian-muted": "#8295b0",
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        };
    </script>
    
    <style>
        body {
            background-color: #051424;
            color: #d4e4fa;
            font-family: 'Inter', sans-serif;
        }
        .glass-panel {
            background: rgba(11, 23, 38, 0.75);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(28, 46, 71, 0.8);
        }
        .glass-card {
            background: rgba(16, 31, 51, 0.65);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(39, 63, 94, 0.6);
            transition: all 0.25s ease-in-out;
        }
        .glass-card:hover {
            border-color: rgba(34, 211, 238, 0.5);
            box-shadow: 0 0 20px rgba(34, 211, 238, 0.15);
            transform: translateY(-2px);
        }
        .glow-cyan {
            box-shadow: 0 0 15px rgba(34, 211, 238, 0.35);
        }
        .glow-green {
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.35);
        }
        .glow-red {
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.35);
        }
        .pulse-dot {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .4; }
        }
        /* Custom Scrollbar for sleek Obsidian panels */
        .custom-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scroll::-webkit-scrollbar-track {
            background: rgba(2, 6, 23, 0.4);
        }
        .custom-scroll::-webkit-scrollbar-thumb {
            background: rgba(34, 211, 238, 0.25);
            border-radius: 4px;
        }
        .custom-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(34, 211, 238, 0.6);
        }
        /* Floating Tooltip HUD */
        #tech-tooltip {
            position: fixed;
            z-index: 99999;
            pointer-events: none;
            opacity: 0;
            transform: scale(0.95) translateY(5px);
            transition: opacity 0.18s cubic-bezier(0.16, 1, 0.3, 1), transform 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        }
        #tech-tooltip.show {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
        #tech-tooltip a, #tech-tooltip button {
            pointer-events: auto;
        }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen flex flex-col bg-obsidian-bg text-obsidian-text antialiased selection:bg-obsidian-cyan selection:text-black">
    @yield('content')

    @stack('scripts')
</body>
</html>
