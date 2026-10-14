<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de Asignación / Devolución - {{ $assignment->assignment_code }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 8mm 10mm 8mm 10mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            line-height: 1.15;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .outer-frame {
            border: 2px solid #000;
            width: 100%;
            position: relative;
        }
        .format-code {
            text-align: right;
            font-size: 7.5pt;
            font-weight: bold;
            padding: 1px 4px;
            letter-spacing: 0.5px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1.5px solid #000;
        }
        .header-table td {
            vertical-align: middle;
            padding: 4px 8px;
        }
        .logo-mppee {
            height: 38px;
            max-width: 250px;
        }
        .logo-empresa {
            height: 30px;
            max-width: 180px;
        }
        .date-box {
            border-collapse: collapse;
            margin-left: auto;
            margin-top: 4px;
        }
        .date-box th, .date-box td {
            border: 1px solid #000;
            font-size: 7.5pt;
            text-align: center;
            padding: 1px 8px;
            min-width: 24px;
        }
        .date-box th {
            font-weight: bold;
            background: #f0f0f0;
        }
        .main-title {
            text-align: center;
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            font-weight: bold;
            font-style: italic;
            letter-spacing: 0.8px;
            padding: 4px 2px;
            border-bottom: 1.5px solid #000;
            background: #fafafa;
        }
        .form-table {
            width: 100%;
            border-collapse: collapse;
        }
        .form-table td, .form-table th {
            border: 1px solid #000;
            padding: 3px 5px;
            vertical-align: top;
        }
        .field-label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            display: block;
            color: #111;
            margin-bottom: 2px;
        }
        .field-value {
            font-size: 8.5pt;
            font-weight: normal;
            min-height: 14px;
        }
        .mode-row {
            background: #f9f9f9;
            text-align: center;
            font-weight: bold;
            font-size: 8.5pt;
            padding: 4px !important;
        }
        .checkbox-item {
            display: inline-block;
            margin: 0 20px;
        }
        .box-check {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1.2px solid #000;
            text-align: center;
            line-height: 12px;
            font-size: 8.5pt;
            font-weight: bold;
            margin-right: 4px;
            vertical-align: middle;
            background: #fff;
        }
        .assets-table {
            width: 100%;
            border-collapse: collapse;
        }
        .assets-table th {
            border: 1px solid #000;
            background: #f0f0f0;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: center;
            padding: 3px;
            text-transform: uppercase;
        }
        .assets-table td {
            border: 1px solid #000;
            font-size: 8pt;
            padding: 3px 5px;
            height: 20px;
            vertical-align: middle;
        }
        .section-header {
            background: #f0f0f0;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            padding: 3px;
            border: 1px solid #000;
            border-top: 1.5px solid #000;
        }
        .specs-table {
            width: 100%;
            border-collapse: collapse;
        }
        .specs-table th {
            border: 1px solid #000;
            background: #f9f9f9;
            font-size: 7pt;
            font-weight: bold;
            text-align: center;
            padding: 2px 3px;
            text-transform: uppercase;
        }
        .specs-table td {
            border: 1px solid #000;
            font-size: 7.5pt;
            text-align: center;
            padding: 3px;
            height: 18px;
        }
        .commitment-box {
            padding: 6px 8px;
            border-top: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
            font-size: 7.5pt;
            line-height: 1.25;
            text-align: justify;
        }
        .commitment-title {
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signatures-table td {
            border: 1px solid #000;
            vertical-align: bottom;
            padding: 4px 6px;
        }
        .sig-line {
            border-top: 1px solid #000;
            margin-top: 28px;
            text-align: center;
            font-size: 7pt;
            font-weight: bold;
            padding-top: 2px;
        }
        .footer-legal {
            border-top: 1.5px solid #000;
            padding: 4px 6px;
            font-size: 6.5pt;
            line-height: 1.15;
            text-align: center;
            color: #222;
        }
        .footer-bottom {
            display: flex;
            justify-content: space-between;
            font-size: 6.5pt;
            font-weight: bold;
            padding: 2px 4px 1px 4px;
        }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
        @media screen {
            body {
                background: {{ request('embed') ? '#ffffff' : '#f1f5f9' }};
                margin: 0;
                padding: {{ request('embed') ? '8px 12px' : '20px 10px' }};
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .outer-frame {
                background: #ffffff;
                width: 100%;
                max-width: 816px;
                margin: 0 auto;
                {{ request('embed') ? '' : 'box-shadow: 0 10px 30px rgba(0,0,0,0.18);' }}
            }
            .print-btn-bar {
                width: 100%;
                max-width: 816px;
                border-radius: 8px;
            }
        }
        .print-btn-bar {
            background: #1e293b;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: sans-serif;
            margin-bottom: 15px;
        }
        .print-btn-bar button {
            background: #3b82f6;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
        }
        .print-btn-bar button:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>

@if((!isset($isPdf) || !$isPdf) && !request('embed'))
<div class="print-btn-bar no-print">
    <div>
        <strong>Monitoreo Valle Seco</strong> &mdash; Vista Previa UINF-4.05
    </div>
    <div>
        <button onclick="window.print()">Imprimir / Guardar como PDF</button>
        <a href="{{ route('admin.inventory.assignments.pdf', ['assignment' => $assignment->id, 'download' => 1]) }}" style="color: #93c5fd; margin-left: 15px; text-decoration: none; font-size: 12px;">Descargar PDF Directo</a>
    </div>
</div>
@endif

<div class="outer-frame">
    <!-- CÓDIGO SUPERIOR CORPORATIVO -->
    <div class="format-code">UINF-4.05</div>

    <!-- ENCABEZADO CORPORATIVO -->
    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                @if(isset($mppeeLogoBase64) && $mppeeLogoBase64)
                    <img src="data:image/png;base64,{{ $mppeeLogoBase64 }}" class="logo-mppee" alt="MPPEE">
                @else
                    <img src="{{ asset('assets/img/inventory/mppee_logo.png') }}" class="logo-mppee" alt="MPPEE">
                @endif
            </td>
            <td style="width: 50%; text-align: right;">
                @if(isset($empresaLogoBase64) && $empresaLogoBase64)
                    <img src="data:image/png;base64,{{ $empresaLogoBase64 }}" class="logo-empresa" alt="empresa">
                @else
                    <img src="{{ asset('assets/img/inventory/empresa_logo.png') }}" class="logo-empresa" alt="empresa">
                @endif
                <table style="margin-left: auto; border-collapse: collapse; margin-top: 3px;">
                    <tr>
                        @if(!empty($qrCodeDataUri))
                        <td style="vertical-align: middle; padding-right: 8px; text-align: center;">
                            <img src="{{ $qrCodeDataUri }}" style="width: 48px; height: 48px; border: 1px solid #000;" alt="QR Verificación">
                            <div style="font-size: 4.5pt; font-weight: bold; text-align: center; margin-top: 1px; letter-spacing: 0.3px;">VALIDACIÓN QR</div>
                        </td>
                        @endif
                        <td style="vertical-align: top;">
                            <table class="date-box" style="margin-top: 0;">
                                <tr>
                                    <th>D</th>
                                    <th>M</th>
                                    <th>A</th>
                                </tr>
                                <tr>
                                    <td>{{ $assignment->assigned_at ? $assignment->assigned_at->format('d') : date('d') }}</td>
                                    <td>{{ $assignment->assigned_at ? $assignment->assigned_at->format('m') : date('m') }}</td>
                                    <td>{{ $assignment->assigned_at ? $assignment->assigned_at->format('Y') : date('Y') }}</td>
                                </tr>
                            </table>
                            <div style="font-size: 6.5pt; font-weight: bold; margin-top: 2px; text-align: right; font-family: monospace;">
                                Nº: {{ $assignment->assignment_code }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- TÍTULO PRINCIPAL -->
    <div class="main-title">
        ASIGNACIÓN/DEVOLUCIÓN DE ACTIVOS DE INFORMACIÓN
    </div>

    <!-- DATOS DEL USUARIO Y PROCEDENCIA -->
    <table class="form-table">
        <tr>
            <td style="width: 45%;">
                <span class="field-label">Nombre y Apellido del Usuario</span>
                <div class="field-value">{{ strtoupper($assignment->custodian_name) }}</div>
            </td>
            <td style="width: 25%;">
                <span class="field-label">Nº de Cédula</span>
                <div class="field-value">{{ $assignment->custodian_id_number ?? 'N/A' }}</div>
            </td>
            <td style="width: 30%;">
                <span class="field-label">Ubicación</span>
                <div class="field-value">{{ strtoupper($assignment->site->name ?? $assignment->asset->location ?? 'SEDE VALLE SECO') }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="field-label">Unidad Gerencial y/o Departamental</span>
                <div class="field-value">{{ strtoupper($assignment->custodian_department ?? 'A.S.H.O') }}</div>
            </td>
            <td>
                <span class="field-label">Extensión</span>
                <div class="field-value">{{ $assignment->phone_extension ?? 'N/A' }}</div>
            </td>
            <td style="padding: 2px 5px;">
                <span class="field-label">Procedencia del Activo</span>
                <div style="font-size: 7.5pt; margin-top: 2px;">
                    @php $prov = strtolower($assignment->provenance ?? 'stock'); @endphp
                    <span style="display: inline-block; width: 48%; margin-bottom: 2px;">
                        <span class="box-check">{{ $prov === 'nuevo' ? 'X' : '' }}</span> NUEVO
                    </span>
                    <span style="display: inline-block; width: 48%; margin-bottom: 2px;">
                        <span class="box-check">{{ $prov === 'reparacion' ? 'X' : '' }}</span> REPARACIÓN
                    </span><br>
                    <span style="display: inline-block; width: 48%;">
                        <span class="box-check">{{ $prov === 'stock' ? 'X' : '' }}</span> STOCK
                    </span>
                    <span style="display: inline-block; width: 48%;">
                        <span class="box-check">{{ $prov === 'otro' ? 'X' : '' }}</span> OTRO{{ $prov === 'otro' && $assignment->provenance_other ? ': ' . $assignment->provenance_other : '' }}
                    </span>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="3" class="mode-row">
                @php $isAsg = ($assignment->movement_type === 'assignment' || $assignment->movement_type === 'initial'); @endphp
                <div class="checkbox-item">
                    <span class="box-check">{{ $isAsg ? 'X' : '' }}</span> ASIGNACIÓN DE
                </div>
                <div class="checkbox-item">
                    <span class="box-check">{{ !$isAsg ? 'X' : '' }}</span> DEVOLUCIÓN DE
                </div>
            </td>
        </tr>
    </table>

    <!-- TABLA DE EQUIPOS Y PERIFÉRICOS -->
    <table class="assets-table">
        <thead>
            <tr>
                <th style="width: 22%;">ACTIVO</th>
                <th style="width: 20%;">SERIAL</th>
                <th style="width: 20%;">MODELO</th>
                <th style="width: 15%;">MARCA</th>
                <th style="width: 23%;">OBSERVACIONES</th>
            </tr>
        </thead>
        <tbody>
            @php
                $rows = [];
                // Fila 1: PC Principal / Laptop
                if ($assignment->asset) {
                    $rows[] = [
                        'activo' => strtoupper($assignment->asset->type_label ?? $assignment->asset->type ?? 'DESKTOP PC'),
                        'serial' => $assignment->asset->serial_number ?? 'S/N',
                        'modelo' => $assignment->asset->model ?? 'N/A',
                        'marca' => $assignment->asset->brand ?? 'N/A',
                        'obs' => $isAsg ? 'EQUIPO ASIGNADO' : 'EQUIPO DEVUELTO'
                    ];
                }
                
                // Filas de Periféricos (desde JSON o base de datos)
                $peripherals = is_array($assignment->peripherals_json) ? $assignment->peripherals_json : [];
                foreach ($peripherals as $p) {
                    $rows[] = [
                        'activo' => strtoupper($p['type'] ?? 'PERIFÉRICO'),
                        'serial' => $p['serial_number'] ?? 'N/A',
                        'modelo' => $p['model'] ?? 'N/A',
                        'marca' => $p['brand'] ?? 'N/A',
                        'obs' => $p['observations'] ?? ($isAsg ? 'ASIGNADO' : 'DEVUELTO')
                    ];
                }

                // Asegurar al menos 9 filas para mantener proporción visual exacta del formato original
                $totalTargetRows = 9;
                $filledCount = count($rows);
            @endphp

            @foreach($rows as $r)
                <tr>
                    <td><strong>{{ $r['activo'] }}</strong></td>
                    <td style="font-family: monospace;">{{ $r['serial'] }}</td>
                    <td>{{ $r['modelo'] }}</td>
                    <td>{{ $r['marca'] }}</td>
                    <td style="font-size: 7.5pt;">{{ $r['obs'] }}</td>
                </tr>
            @endforeach

            @for($i = $filledCount; $i < $totalTargetRows; $i++)
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <!-- ESPECIFICACIONES DE LA ASIGNACIÓN / DEVOLUCIÓN -->
    <div class="section-header">ESPECIFICACIONES DE LA ASIGNACIÓN / DEVOLUCIÓN</div>
    
    @php
        $specs = $assignment->hardware_specs ?? [];
        $asset = $assignment->asset;
        $cpu = $specs['processor'] ?? ($asset ? $asset->components->where('type', 'cpu')->first()?->name : 'N/A');
        $speed = $specs['speed'] ?? ($specs['cpu_speed'] ?? 'N/A');
        $ram = $specs['ram'] ?? ($asset ? $asset->components->where('type', 'ram')->pluck('capacity')->implode(' + ') : 'N/A');
        $disk = $specs['disk'] ?? ($asset ? $asset->components->where('type', 'disk')->pluck('capacity')->implode(' + ') : 'N/A');
        $os = $specs['os'] ?? ($asset ? $asset->operating_system : 'N/A');
        $deviceName = $specs['device_name'] ?? ($asset ? $asset->device_name : 'N/A');
        $ip = $specs['ip_address'] ?? ($asset ? $asset->ip_address : 'N/A');
    @endphp

    <table class="specs-table">
        <tr>
            <th colspan="6" style="background: #f7f7f7; font-size: 7pt;">PC</th>
        </tr>
        <tr>
            <th style="width: 25%;">PROCESADOR</th>
            <th style="width: 12%;">VELOCIDAD</th>
            <th style="width: 15%;">MEMORIA RAM</th>
            <th style="width: 15%;">DISCO DURO</th>
            <th style="width: 18%;">SISTEMA OPERATIVO</th>
            <th style="width: 15%;">NOMBRE DEL EQUIPO</th>
        </tr>
        <tr>
            <td>{{ $cpu ?? 'N/A' }}</td>
            <td>{{ $speed ?? 'N/A' }}</td>
            <td>{{ $ram ?? 'N/A' }}</td>
            <td>{{ $disk ?? 'N/A' }}</td>
            <td>{{ $os ?? 'N/A' }}</td>
            <td style="font-family: monospace;">{{ $deviceName ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th colspan="6" style="background: #f7f7f7; font-size: 7pt;">IMPRESORAS / RED</th>
        </tr>
        <tr>
            <td colspan="3" style="text-align: left; padding-left: 10px;">
                <strong>DIRECCIÓN IP (RED):</strong> {{ $ip ?? 'N/A' }}
            </td>
            <td colspan="3" style="text-align: left; padding-left: 10px;">
                <strong>DIRECCIÓN LOCAL (USUARIO/MÁQUINA):</strong> {{ $assignment->custodian_name }}
            </td>
        </tr>
    </table>

    <!-- COMPROMISO LEGAL Y FIRMAS -->
    <div class="commitment-box">
        <div class="commitment-title">
            COMPROMISO DE {{ $isAsg ? 'ASIGNACIÓN' : 'DEVOLUCIÓN' }}
        </div>
        Yo, <strong>{{ strtoupper($assignment->custodian_name) }}</strong>, cédula Nº <strong>{{ $assignment->custodian_id_number ?? '_________' }}</strong>
        me comprometo a velar por el resguardo del (los) equipo (s) {{ $isAsg ? 'asignado (s)' : 'devuelto (s)' }} como herramienta de trabajo; así como el cumplimiento de las normas y procedimientos para el traslado de estos recursos a cualquier otra área de la Empresa diferente a la asignada a fin de garantizar la integridad de los mismos, como también el cumplimiento de la Norma de la Seguridad de la Información de Electricidad de Valencia.
    </div>

    <!-- TABLA DE FIRMAS -->
    <table class="signatures-table">
        <tr>
            <td style="width: 50%; height: 50px;">
                <div class="sig-line">FIRMA DEL TRABAJADOR</div>
            </td>
            <td style="width: 50%; height: 50px;">
                <div class="sig-line">FECHA: {{ $assignment->assigned_at ? $assignment->assigned_at->format('d/m/Y') : date('d/m/Y') }}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="background: #fafafa; font-size: 7pt; font-weight: bold; padding: 2px 4px; text-transform: uppercase;">
                UNIDAD DE INFORMÁTICA
            </td>
        </tr>
        <tr>
            <td style="height: 50px;">
                <span class="field-label">Nombre y Apellido</span>
                <div style="font-size: 8pt; margin-top: 2px;">{{ strtoupper($assignment->deliveredByUser->name ?? auth()->user()->name ?? 'ADMINISTRADOR DE SISTEMAS') }}</div>
                <div class="sig-line" style="margin-top: 18px;">FIRMA</div>
            </td>
            <td style="height: 50px;">
                <div class="sig-line" style="margin-top: 32px;">FECHA: {{ $assignment->assigned_at ? $assignment->assigned_at->format('d/m/Y') : date('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <!-- PIE CORPORATIVO Y LEGAL -->
    <div class="footer-legal">
        Corporación Eléctrica Nacional, S.A (empresa) / R.I.F: G-20010014-1<br>
        Avenida Vollmer entre Caracas y Alameda, Edificio Centro Empresarial Caracas, Piso 6, Oficina 6. San Bernardino Caracas, Venezuela / Teléfono: (0212) 502.21.11
    </div>
    <div class="footer-bottom">
        <div>Distribución: Original Unidad de Informática; 1ra copia Trabajador; 2da copia Expediente</div>
        @if(!empty($verificationUrl))
        <div style="font-family: monospace; font-size: 6pt;">Verificación Digital: {{ $verificationUrl }}</div>
        @endif
        <div>(PYM 08-2009/Rev. 4)</div>
    </div>
</div>

</body>
</html>
