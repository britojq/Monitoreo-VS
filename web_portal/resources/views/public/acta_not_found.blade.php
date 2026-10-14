<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acta No Encontrada - Sistema de Monitoreo & ITAM</title>
    <!-- Activos Locales 100% Autónomos (Cero CDNs Externos) -->
    <link rel="stylesheet" href="{{ asset('vendor/fonts/fonts.css') }}"/>
    <link rel="stylesheet" href="{{ asset('vendor/material-symbols/material-symbols.css') }}"/>
    <script src="{{ asset('vendor/tailwindcss/tailwind.min.js') }}"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0b0f19; color: #f1f5f9; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-2xl p-8 text-center shadow-2xl">
        <div class="w-16 h-16 bg-red-500/10 text-red-400 rounded-2xl flex items-center justify-center mx-auto mb-6">
            <span class="material-symbols-outlined text-4xl">gpp_maybe</span>
        </div>
        <h1 class="text-2xl font-bold text-white mb-2">Acta No Registrada</h1>
        <p class="text-slate-400 text-sm mb-6 leading-relaxed">
            El código de documento <span class="font-mono font-semibold text-white bg-slate-800 px-2 py-0.5 rounded">{{ $searchedCode }}</span> no figura en el censo de activos de TI o ha sido dado de baja.
        </p>
        <div class="p-4 bg-slate-950/60 border border-slate-800/80 rounded-xl text-left text-xs text-slate-400 mb-6 space-y-2">
            <div class="flex items-center gap-2 text-slate-300 font-semibold">
                <span class="material-symbols-outlined text-base text-amber-400">info</span>
                Recomendaciones de Seguridad:
            </div>
            <p>1. Verifique que el código escaneado coincida con la numeración física UINF-4.05.</p>
            <p>2. Si sospecha de un documento apócrifo, comuníquese con la Unidad de Informática.</p>
        </div>
        <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-2 w-full px-5 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium text-sm transition">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
            Volver al Portal
        </a>
    </div>
</body>
</html>
