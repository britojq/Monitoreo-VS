<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pase de Movilización por Puntos de Control - {{ $assignment->assignment_code }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 4mm 5mm 4mm 5mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        html, body {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.15;
            color: #000;
            background: #fff;
        }
        .page-container {
            width: 100%;
        }
        .page-break {
            page-break-after: always;
        }
        .outer-frame {
            border: 1.5px solid #000;
            width: 100%;
            position: relative;
            box-sizing: border-box;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #000;
        }
        .header-table td {
            vertical-align: middle;
            padding: 4px 8px;
        }
        .logo-box {
            width: 30%;
            text-align: center;
        }
        .logo-empresa {
            height: 34px;
            max-width: 200px;
        }
        .title-box {
            width: 70%;
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            font-weight: bold;
            line-height: 1.25;
            letter-spacing: 0.3px;
        }
        .section-bar {
            background: #f0f0f0;
            font-size: 7pt;
            font-weight: bold;
            text-align: left;
            padding: 2.5px 6px;
            border-bottom: 1px solid #000;
            text-transform: uppercase;
        }
        .fields-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #000;
        }
        .fields-table td {
            padding: 3px 6px;
            vertical-align: top;
            border-right: 1px solid #000;
        }
        .fields-table td:last-child {
            border-right: none;
        }
        .field-num {
            font-size: 6.5pt;
            font-weight: bold;
            color: #222;
            margin-right: 3px;
        }
        .field-val {
            font-size: 8pt;
            min-height: 14px;
            margin-top: 1px;
        }
        .main-body-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #000;
        }
        .main-body-table > tbody > tr > td {
            vertical-align: top;
        }
        .left-body-col {
            width: 74%;
            border-right: 1px solid #000;
            vertical-align: top;
            padding: 0;
        }
        .right-body-col {
            width: 26%;
            vertical-align: top;
            padding: 0;
        }
        .items-header {
            font-size: 6.5pt;
            font-weight: bold;
            padding: 2.5px 5px;
            border-bottom: 1px solid #000;
            background: #fafafa;
        }
        .desc-table {
            width: 100%;
            border-collapse: collapse;
        }
        .desc-table th {
            font-size: 7pt;
            font-weight: bold;
            text-align: left;
            padding: 2.5px 5px;
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            background: #f5f5f5;
        }
        .desc-table th:last-child {
            border-right: none;
        }
        .desc-table td {
            font-size: 7.5pt;
            padding: 2px 5px;
            border-bottom: 1px solid #e0e0e0;
            border-right: 1px solid #000;
            vertical-align: middle;
        }
        .desc-table td:last-child {
            border-right: none;
        }
        .time-box-item {
            padding: 4px 6px;
            border-bottom: 1px solid #000;
        }
        .bottom-table {
            width: 100%;
            border-collapse: collapse;
        }
        .bottom-table td {
            padding: 4px 6px;
            vertical-align: top;
            border-right: 1px solid #000;
        }
        .bottom-table td:last-child {
            border-right: none;
        }
        .sig-box {
            height: 68px;
            position: relative;
        }
        .sig-line-label {
            font-size: 6.5pt;
            font-weight: bold;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 2px;
            margin-top: 42px;
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
            .page-container {
                max-width: 816px;
                margin: 0 auto 20px auto;
            }
            .outer-frame {
                background: #ffffff;
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
            background: #10b981;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
        }
        .print-btn-bar button:hover {
            background: #059669;
        }
    </style>
</head>
<body>

@if((!isset($isPdf) || !$isPdf) && !request('embed'))
<div class="print-btn-bar no-print">
    <div>
        <strong>Monitoreo Valle Seco</strong> &mdash; Pase de Movilización por Puntos de Control
    </div>
    <div>
        <button onclick="window.print()">Imprimir / Guardar como PDF</button>
        <a href="{{ route('admin.inventory.assignments.pdf-movilizacion', ['assignment' => $assignment->id, 'download' => 1]) }}" style="color: #6ee7b7; margin-left: 15px; text-decoration: none; font-size: 12px;">Descargar PDF Directo</a>
    </div>
</div>
@endif

@php
    $typeMap = [
        'PRINTER' => 'IMPRESORA',
        'DESKTOP' => 'COMPUTADORA',
        'LAPTOP' => 'PORTÁTIL',
        'MONITOR' => 'MONITOR',
        'KEYBOARD' => 'TECLADO',
        'MOUSE' => 'MOUSE',
        'SCANNER' => 'ESCÁNER',
        'PROTECTOR' => 'PROTECTOR DE VOLTAJE',
        'REGULADOR' => 'REGULADOR DE VOLTAJE',
        'POWER SUPPLY' => 'FUENTE DE PODER',
    ];

    $allItems = [];
    if ($assignment->asset) {
        $cleanSerial = trim($assignment->asset->serial_number ?? '');
        if (str_starts_with($cleanSerial, '[') || in_array(strtoupper($cleanSerial), ['S/N', 'N/A', 'SIN SERIAL', 'NO REPORTADO', 'SIN SERIAL REGISTRADO'])) {
            $cleanSerial = '';
        }
        $rawType = strtoupper($assignment->asset->type_label ?? $assignment->asset->type ?? 'EQUIPO');
        $cleanType = preg_replace('/\s*\(.*?\)/', '', $rawType);
        $cleanType = preg_replace('/\s*-\s*Asociado.*$/i', '', $cleanType);
        $cleanType = trim($cleanType);
        $finalType = $typeMap[$cleanType] ?? $cleanType;

        $allItems[] = [
            'type' => $finalType ?: 'EQUIPO',
            'brand' => $assignment->asset->brand ?? '',
            'model' => $assignment->asset->model ?? '',
            'serial' => $cleanSerial,
        ];
    }

    $peripherals = is_array($assignment->peripherals_json) ? $assignment->peripherals_json : [];
    foreach ($peripherals as $p) {
        $pSerial = trim($p['serial_number'] ?? '');
        if (str_starts_with($pSerial, '[') || in_array(strtoupper($pSerial), ['S/N', 'N/A', 'SIN SERIAL', 'NO REPORTADO', 'SIN SERIAL REGISTRADO'])) {
            $pSerial = '';
        }
        $rawType = $p['type'] ?? 'PERIFÉRICO';
        $cleanType = preg_replace('/\s*\(.*?\)/', '', $rawType);
        $cleanType = preg_replace('/\s*-\s*Asociado.*$/i', '', $cleanType);
        $cleanType = trim(strtoupper($cleanType));
        $finalType = $typeMap[$cleanType] ?? $cleanType;

        $allItems[] = [
            'type' => $finalType ?: 'PERIFÉRICO',
            'brand' => $p['brand'] ?? '',
            'model' => $p['model'] ?? '',
            'serial' => $pSerial,
        ];
    }

    $maxItemsPerPage = 20;
    $itemChunks = !empty($allItems) ? array_chunk($allItems, $maxItemsPerPage) : [[]];
    $totalPages = count($itemChunks);
@endphp

@foreach($itemChunks as $chunkIndex => $pageItems)
    @php
        $pageNumber = $chunkIndex + 1;
        $itemsInThisPage = count($pageItems);
        $bodyHeight = 730;
        $usedHeight = 22 + 22 + ($itemsInThisPage * 19);
        $remHeight = max(40, (int)($bodyHeight - $usedHeight - 20));
    @endphp

    <div class="page-container {{ !$loop->last ? 'page-break' : '' }}">
        <div class="outer-frame">
            <!-- ENCABEZADO -->
            <table class="header-table">
                <tr>
                    <td class="logo-box">
                        @if(isset($empresaLogoBase64) && $empresaLogoBase64)
                            <img src="data:image/png;base64,{{ $empresaLogoBase64 }}" class="logo-empresa" alt="empresa">
                        @else
                            <img src="{{ asset('assets/img/inventory/empresa_logo.png') }}" class="logo-empresa" alt="empresa">
                        @endif
                    </td>
                    <td class="title-box">
                        CONSTANCIA DE ENTRADA Y SALIDA DE EQUIPOS<br>POR LOS PUNTOS DE CONTROL
                        @if($totalPages > 1)
                            <div style="font-size: 7.5pt; font-weight: normal; margin-top: 2px;">(Hoja {{ $pageNumber }} de {{ $totalPages }})</div>
                        @endif
                    </td>
                </tr>
            </table>

            <!-- BLOQUE 1: DATOS DEL TRABAJADOR, CONTRATISTA O VISITANTE -->
            <div class="section-bar">
                <span>DATOS DEL TRABAJADOR, CONTRATISTA O VISITANTE</span>
            </div>
            <table class="fields-table">
                <tr>
                    <td style="width: 25%;">
                        <span class="field-num">01 Lugar</span>
                        <div class="field-val">{{ strtoupper($assignment->checkpoint_name ?? $assignment->site->name ?? 'C/S VALLE SECO') }}</div>
                    </td>
                    <td style="width: 20%;">
                        <span class="field-num">02 Fecha</span>
                        <div class="field-val">{{ $assignment->assigned_at ? $assignment->assigned_at->format('d/m/Y') : date('d/m/Y') }}</div>
                    </td>
                    <td style="width: 38%;">
                        <span class="field-num">03 Nombres y apellidos</span>
                        <div class="field-val"><strong>{{ strtoupper($assignment->custodian_name) }}</strong></div>
                    </td>
                    <td style="width: 17%;">
                        <span class="field-num">04 C.I.</span>
                        <div class="field-val">
                            @php
                                $ci = trim($assignment->custodian_id_number ?? '');
                                if (!empty($ci) && !str_starts_with(strtoupper($ci), 'V-') && !str_starts_with(strtoupper($ci), 'E-') && is_numeric(str_replace(['.', '-'], '', $ci))) {
                                    $ci = 'V-' . number_format((int)str_replace(['.', '-'], '', $ci), 0, '', '.');
                                }
                            @endphp
                            {{ $ci ?: 'N/A' }}
                        </div>
                    </td>
                </tr>
            </table>

            <!-- BLOQUE 2: DATOS DEL PUNTO DE CONTROL -->
            <div class="section-bar">
                <span>DATOS DEL PUNTO DE CONTROL</span>
            </div>
            <table class="fields-table">
                <tr>
                    <td style="width: 50%;">
                        <span class="field-num">05 Nombre del punto de control</span>
                        <div class="field-val">{{ strtoupper($assignment->checkpoint_name ?? $assignment->site->name ?? 'CENTRO DE SERVICIO VALLE SECO') }}</div>
                    </td>
                    <td style="width: 50%;">
                        <span class="field-num">06 Nombre del Operador o Recepcionista</span>
                        <div class="field-val">{{ strtoupper($assignment->gate_operator_name ?? '') }}</div>
                    </td>
                </tr>
            </table>

            <!-- BLOQUE 3: DESCRIPCIÓN DEL BIEN Y CONTROL HORARIO -->
            <div class="section-bar">
                <span>DESCRIPCIÓN DEL BIEN</span>
            </div>
            <table class="main-body-table">
                <tr>
                    <!-- COLUMNA IZQUIERDA: EQUIPOS -->
                    <td class="left-body-col">
                        <div class="items-header">07 Descripción de Equipos y Accesorios</div>
                        <table class="desc-table">
                            <thead>
                                <tr>
                                    <th style="width: 28%;">EQUIPO</th>
                                    <th style="width: 22%;">MARCA</th>
                                    <th style="width: 25%;">MODELO</th>
                                    <th style="width: 25%;">SERIAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pageItems as $item)
                                    <tr>
                                        <td><strong>{{ $item['type'] }}</strong></td>
                                        <td>{{ $item['brand'] ?: '' }}</td>
                                        <td>{{ $item['model'] ?: '' }}</td>
                                        <td style="font-family: monospace;">{{ $item['serial'] ?: '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!-- LÍNEA DIAGONAL EN Z DE ANULACIÓN EN EL ESPACIO REMANENTE -->
                        @if($remHeight >= 40)
                            <div style="width: 100%; height: {{ $remHeight }}px; text-align: center; overflow: hidden; margin-top: 6px;">
                                <img src="data:image/svg+xml;base64,{{ base64_encode('
                                    <svg xmlns="http://www.w3.org/2000/svg" width="500" height="' . $remHeight . '" viewBox="0 0 500 ' . $remHeight . '" preserveAspectRatio="none">
                                        <line x1="20" y1="10" x2="480" y2="10" stroke="#111" stroke-width="1.3" />
                                        <line x1="480" y1="10" x2="20" y2="' . ($remHeight - 15) . '" stroke="#111" stroke-width="1.3" />
                                        <line x1="20" y1="' . ($remHeight - 15) . '" x2="480" y2="' . ($remHeight - 15) . '" stroke="#111" stroke-width="1.3" />
                                    </svg>
                                ') }}" style="width: 100%; height: {{ $remHeight }}px; display: block; margin: 0 auto;" />
                            </div>
                        @endif
                    </td>

                    <!-- COLUMNA DERECHA: REGISTRO HORARIO Y PUNTO DE CONTROL -->
                    <td class="right-body-col">
                        <div class="time-box-item">
                            <span class="field-num">08. Hora de entrada</span>
                            <div style="font-size: 11pt; font-family: monospace; font-weight: bold; margin-top: 4px; text-align: center;">
                                {{ $assignment->entry_time ?? '' }}
                            </div>
                        </div>
                        <div class="time-box-item">
                            <span class="field-num">09. Hora de Salida</span>
                            <div style="font-size: 11pt; font-family: monospace; font-weight: bold; margin-top: 4px; text-align: center;">
                                {{ $assignment->departure_time ?? date('H:i') }}
                            </div>
                        </div>
                        <div class="time-box-item">
                            <span class="field-num">10. Nº de Control</span>
                            <div style="font-size: 8.5pt; font-family: monospace; font-weight: bold; margin-top: 6px; text-align: center;">
                                {{ $assignment->control_number ?? $assignment->assignment_code }}
                            </div>
                        </div>
                        <div class="time-box-item" style="border-bottom: none; height: 610px; text-align: center; padding-top: 15px;">
                            <span class="field-num" style="display: block; text-align: left; margin-bottom: 10px;">11. Sello / Firma del Punto de Control</span>
                            @if(!empty($qrCodeDataUri))
                            <div style="margin: 15px auto 10px auto; text-align: center;">
                                <img src="{{ $qrCodeDataUri }}" style="width: 72px; height: 72px; border: 1px solid #000;" alt="QR PCP">
                                <div style="font-size: 5.5pt; font-weight: bold; margin-top: 2px; letter-spacing: 0.3px;">CONTROL PCP - VERIFICACIÓN</div>
                            </div>
                            @endif
                            <div style="font-size: 7pt; color: #666; margin-top: 20px; line-height: 1.4;">
                                (Espacio exclusivo para sello húmedo y firma del recepcionista de guardia)
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- BLOQUE 4: OBSERVACIONES Y CONFORMACIÓN -->
            <table class="bottom-table">
                <tr>
                    <td style="width: 50%;">
                        <span class="field-num">OBSERVACIÓN:</span>
                        <div style="font-size: 7.5pt; margin-top: 4px; line-height: 1.25;">
                            {{ $assignment->observations ?? 'Traslado de asignación de equipos autorizados por el área de Informática.' }}
                        </div>
                    </td>
                    <td style="width: 25%;">
                        <div class="sig-box">
                            <span class="field-num">12 Conformado por</span>
                            <div class="sig-line-label">
                                Trabajador Contratista o Visitante
                            </div>
                        </div>
                    </td>
                    <td style="width: 25%;">
                        <div class="sig-box">
                            <span class="field-num">13 Conformado por</span>
                            <div class="sig-line-label">
                                Responsable Protección y Resguardo
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
@endforeach

</body>
</html>
