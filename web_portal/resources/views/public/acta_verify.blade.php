<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado de Autenticidad - {{ $data['code'] }}</title>
    <!-- Activos Locales 100% Autónomos (Cero CDNs Externos) -->
    <link rel="stylesheet" href="{{ asset('vendor/fonts/fonts.css') }}"/>
    <link rel="stylesheet" href="{{ asset('vendor/material-symbols/material-symbols.css') }}"/>
    <script src="{{ asset('vendor/tailwindcss/tailwind.min.js') }}"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #080c14; color: #f1f5f9; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="min-h-screen py-8 px-4 flex flex-col items-center justify-center">

    <div class="max-w-2xl w-full bg-slate-900/90 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden backdrop-blur-md">
        
        <!-- CABECERA CORPORATIVA -->
        <div class="p-6 bg-gradient-to-r from-slate-950 via-slate-900 to-slate-950 border-b border-slate-800/80 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                    <span class="material-symbols-outlined text-2xl">verified_user</span>
                </div>
                <div>
                    <div class="text-[10px] uppercase tracking-wider font-extrabold text-cyan-400">empresa • UNIDAD DE INFORMÁTICA</div>
                    <div class="text-base font-bold text-white tracking-tight">Certificado de Autenticidad Digital</div>
                </div>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold font-mono {{ $isValidSignature ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/10 text-amber-400 border border-amber-500/30' }}">
                    <span class="w-2 h-2 rounded-full {{ $isValidSignature ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400' }}"></span>
                    {{ $isValidSignature ? 'DOCUMENTO AUTÉNTICO' : 'REVISIÓN REQUERIDA' }}
                </span>
            </div>
        </div>

        <div class="p-6 md:p-8 space-y-6">

            <!-- BANNER PRINCIPAL DEL DOCUMENTO -->
            <div class="p-5 rounded-2xl bg-slate-950/70 border border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <div class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Código de Documento</div>
                    <div class="text-2xl font-black text-white font-mono tracking-wide mt-0.5">{{ $data['code'] }}</div>
                    <div class="text-xs text-cyan-400 mt-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">description</span>
                        {{ $data['movement_label'] }}
                    </div>
                </div>
                <div class="text-left sm:text-right">
                    <div class="text-xs text-slate-400 font-semibold">Fecha de Emisión</div>
                    <div class="text-sm font-bold text-slate-200 mt-0.5">{{ $data['issued_at'] }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">Sede: {{ $data['site_name'] }}</div>
                </div>
            </div>

            <!-- DETALLES DEL RESPONSABLE Y UNIDAD EMISORA -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-800">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-cyan-400">person</span>
                        Funcionario Receptor / Responsable
                    </div>
                    <div class="text-base font-bold text-white mt-2">{{ strtoupper($data['responsible_name']) }}</div>
                    <div class="text-xs text-slate-400 mt-0.5">Cédula: <span class="text-slate-200 font-mono">{{ $data['responsible_id_number'] ?: 'No registrada' }}</span></div>
                    @if($data['responsible_department'])
                    <div class="text-xs text-slate-500 mt-0.5">Área: {{ $data['responsible_department'] }}</div>
                    @endif
                </div>

                <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-800">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-cyan-400">badge</span>
                        Emitido y Entregado Por
                    </div>
                    <div class="text-base font-bold text-white mt-2">{{ strtoupper($data['delivered_by']) }}</div>
                    <div class="text-xs text-slate-400 mt-0.5">Área Técnica: <span class="text-slate-200 font-semibold">Unidad de Informática</span></div>
                    <div class="text-xs text-slate-500 mt-0.5">Estado: <span class="text-emerald-400 font-semibold">Vigente en MariaDB ITAM</span></div>
                </div>
            </div>

            <!-- DATOS DEL ACTIVO ASIGNADO -->
            @if($data['asset'])
            <div class="p-5 rounded-2xl bg-slate-950/70 border border-slate-800 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-cyan-400">computer</span>
                        <span class="text-sm font-bold text-white uppercase tracking-wider">Activo Tecnológico Asignado</span>
                    </div>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-300 border border-cyan-500/20">
                        {{ $data['asset']['asset_tag'] }}
                    </span>
                </div>

                <div>
                    <!-- Nombre Etiqueta corporativo (SSOT Regla #7) -->
                    <div class="text-xs text-slate-400">Identidad Operativa (Nombre Etiqueta):</div>
                    <div class="text-lg font-bold text-cyan-300">{{ $data['asset']['name'] }}</div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                    <div>
                        <span class="text-slate-500 block">Serial del Equipo:</span>
                        <span class="font-mono font-semibold text-slate-200">{{ $data['asset']['serial_number'] ?: 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Marca / Modelo:</span>
                        <span class="font-semibold text-slate-200">{{ trim("{$data['asset']['brand']} {$data['asset']['model']}") ?: 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Dirección IP:</span>
                        <span class="font-mono font-semibold text-slate-200">{{ $data['asset']['ip_address'] ?: 'Sin IP' }}</span>
                    </div>
                </div>

                <!-- Hardware complementario -->
                <div class="pt-3 border-t border-slate-800/60 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                    <div class="bg-slate-900 px-3 py-2 rounded-lg border border-slate-800/60">
                        <span class="text-slate-500 block text-[10px] font-bold uppercase">Procesador</span>
                        <span class="font-semibold text-slate-300 truncate block">{{ $data['specs']['cpu'] }}</span>
                    </div>
                    <div class="bg-slate-900 px-3 py-2 rounded-lg border border-slate-800/60">
                        <span class="text-slate-500 block text-[10px] font-bold uppercase">Memoria RAM</span>
                        <span class="font-semibold text-slate-300 truncate block">{{ $data['specs']['ram'] }}</span>
                    </div>
                    <div class="bg-slate-900 px-3 py-2 rounded-lg border border-slate-800/60">
                        <span class="text-slate-500 block text-[10px] font-bold uppercase">Almacenamiento</span>
                        <span class="font-semibold text-slate-300 truncate block">{{ $data['specs']['disk'] }}</span>
                    </div>
                </div>

                @if(!empty($data['peripherals']))
                <div class="pt-2">
                    <span class="text-slate-400 text-xs font-semibold block mb-2">Periféricos Asociados:</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach($data['peripherals'] as $periph)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs bg-slate-900 border border-slate-800 text-slate-300 font-mono">
                            <span class="material-symbols-outlined text-xs text-cyan-400">devices_other</span>
                            {{ strtoupper($periph['type']) }}: {{ $periph['brand'] }} ({{ $periph['serial'] ?: 'S/N' }})
                        </span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @endif

            <!-- SELLO DIGITAL Y FIRMA CRIPTOGRÁFICA -->
            <div class="p-4 rounded-xl bg-slate-950/90 border border-slate-800 text-xs space-y-2">
                <div class="flex items-center justify-between text-slate-400 font-semibold">
                    <span class="flex items-center gap-1.5 text-cyan-400">
                        <span class="material-symbols-outlined text-sm">lock</span>
                        Firma Criptográfica SHA-256 (Hash de Integridad)
                    </span>
                    <span class="font-mono text-emerald-400">{{ $data['signature_short'] }}...</span>
                </div>
                <div class="p-2 rounded bg-slate-900 border border-slate-800/80 font-mono text-[11px] text-slate-300 break-all select-all">
                    {{ $data['signature'] }}
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1">
                    <span>Validado en línea: {{ $data['verified_at'] }}</span>
                    <span>Soporte Digital: {{ $data['has_physical_scan'] ? 'Hoja física escaneada registrada' : 'Pendiente de escaneo físico' }}</span>
                </div>
            </div>

        </div>

        <!-- PIE DE PÁGINA -->
        <div class="p-4 bg-slate-950 border-t border-slate-800 text-center text-xs text-slate-500">
            Corporación Eléctrica Nacional, S.A (empresa) • Centro de Operaciones & ITAM Valle Seco
        </div>

    </div>

</body>
</html>
