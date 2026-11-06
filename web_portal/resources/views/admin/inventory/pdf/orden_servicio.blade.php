<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Servicio Técnico - {{ $ticket->ticket_code }}</title>
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
            line-height: 1.25;
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
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #000;
        }
        .header-table td {
            border: 1px solid #000;
            vertical-align: middle;
            padding: 6px 8px;
        }
        .logo-empresa {
            height: 36px;
            max-width: 200px;
        }
        .title-box {
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            font-weight: bold;
            line-height: 1.3;
        }
        .section-bar {
            background: #f0f0f0;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: left;
            padding: 3px 6px;
            border-top: 1.5px solid #000;
            border-bottom: 1px solid #000;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table td, .data-table th {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
        }
        .label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            display: block;
            color: #222;
            margin-bottom: 2px;
        }
        .val {
            font-size: 8.5pt;
        }
        .notes-box {
            padding: 6px 8px;
            border-bottom: 1px solid #000;
            min-height: 60px;
            font-size: 8pt;
            line-height: 1.3;
        }
        .parts-table {
            width: 100%;
            border-collapse: collapse;
        }
        .parts-table th {
            background: #fafafa;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #000;
            padding: 3px 5px;
            text-align: left;
        }
        .parts-table td {
            border: 1px solid #000;
            font-size: 7.5pt;
            padding: 3px 5px;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 2px solid #000;
        }
        .signatures-table td {
            border: 1px solid #000;
            vertical-align: bottom;
            padding: 4px 6px;
            height: 70px;
        }
        .sig-line {
            border-top: 1px solid #000;
            margin-top: 35px;
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
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
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
    </style>
</head>
<body>

@if(!isset($isPdf) || !$isPdf)
<div class="print-btn-bar no-print">
    <div>
        <strong>Monitoreo Valle Seco</strong> &mdash; Orden de Servicio Técnico
    </div>
    <div>
        <button onclick="window.print()">Imprimir / Guardar como PDF</button>
        <a href="{{ route('admin.inventory.maintenance.pdf', ['ticket' => $ticket->id, 'download' => 1]) }}" style="color: #93c5fd; margin-left: 15px; text-decoration: none; font-size: 12px;">Descargar PDF Directo</a>
    </div>
</div>
@endif

<div class="outer-frame">
    <!-- ENCABEZADO -->
    <table class="header-table">
        <tr>
            <td style="width: 30%; text-align: center;">
                @if(isset($empresaLogoBase64) && $empresaLogoBase64)
                    <img src="data:image/png;base64,{{ $empresaLogoBase64 }}" class="logo-empresa" alt="empresa">
                @else
                    <img src="{{ asset('assets/img/inventory/empresa_logo.png') }}" class="logo-empresa" alt="empresa">
                @endif
            </td>
            <td style="width: 45%;" class="title-box">
                CORPORACIÓN ELÉCTRICA NACIONAL<br>
                <span style="font-size: 9.5pt; font-weight: normal;">UNIDAD DE TECNOLOGÍA DE LA INFORMACIÓN</span><br>
                ORDEN DE SERVICIO Y MANTENIMIENTO TÉCNICO
            </td>
            <td style="width: 25%; text-align: center; vertical-align: middle;">
                <span class="label">Nº DE ORDEN</span>
                <strong style="font-family: monospace; font-size: 11pt; color: #111;">{{ $ticket->ticket_code }}</strong>
                <div style="font-size: 7pt; margin-top: 3px;">
                    FECHA: {{ $ticket->scheduled_at ? $ticket->scheduled_at->format('d/m/Y') : date('d/m/Y') }}
                </div>
            </td>
        </tr>
    </table>

    <!-- DATOS GENERALES DEL SERVICIO -->
    <div class="section-bar">1. Identificación del Servicio Técnico</div>
    <table class="data-table">
        <tr>
            <td style="width: 25%;">
                <span class="label">Tipo de Servicio</span>
                <strong class="val">{{ strtoupper($ticket->type_label) }}</strong>
            </td>
            <td style="width: 25%;">
                <span class="label">Prioridad</span>
                <strong class="val">{{ strtoupper($ticket->priority) }}</strong>
            </td>
            <td style="width: 25%;">
                <span class="label">Estado Actual</span>
                <strong class="val">{{ strtoupper($ticket->status_label) }}</strong>
            </td>
            <td style="width: 25%;">
                <span class="label">Técnico Responsable</span>
                <span class="val">{{ strtoupper($ticket->technician->name ?? 'NO ASIGNADO') }}</span>
            </td>
        </tr>
    </table>

    <!-- DATOS DEL ACTIVO INTERVENIDO -->
    <div class="section-bar">2. Identificación del Activo de TI</div>
    <table class="data-table">
        <tr>
            <td style="width: 30%;">
                <span class="label">Nombre del Activo / Tag</span>
                <strong class="val">{{ $ticket->asset->name }} [{{ $ticket->asset->asset_tag ?? 'S/T' }}]</strong>
            </td>
            <td style="width: 25%;">
                <span class="label">Serial</span>
                <span class="val" style="font-family: monospace;">{{ $ticket->asset->serial_number ?? 'S/N' }}</span>
            </td>
            <td style="width: 25%;">
                <span class="label">Marca / Modelo</span>
                <span class="val">{{ $ticket->asset->brand }} {{ $ticket->asset->model }}</span>
            </td>
            <td style="width: 20%;">
                <span class="label">Sede / Ubicación</span>
                <span class="val">{{ $ticket->asset->monitoredSite->name ?? $ticket->asset->location ?? 'Sede Principal' }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Responsable Asignado</span>
                <span class="val">{{ strtoupper($ticket->asset->custodian_name ?? 'EN STOCK DE INFORMÁTICA') }}</span>
            </td>
            <td colspan="2">
                <span class="label">Departamento / Extensión</span>
                <span class="val">{{ strtoupper($ticket->asset->custodian_department ?? 'N/A') }}</span>
            </td>
        </tr>
    </table>

    <!-- MOTIVO / DIAGNÓSTICO -->
    <div class="section-bar">3. Motivo del Servicio / Diagnóstico Inicial</div>
    <div class="notes-box">
        <strong>{{ $ticket->title }}</strong>
        <p style="margin: 4px 0 0 0; color: #222;">
            {{ $ticket->description ?? 'Mantenimiento preventivo / correctivo programado según cronograma del área.' }}
        </p>
    </div>

    <!-- RESOLUCIÓN TÉCNICA Y ACCIONES REALIZADAS -->
    <div class="section-bar">4. Acciones Técnicas Realizadas & Diagnóstico Final</div>
    <div class="notes-box" style="min-height: 80px;">
        @if(!empty($ticket->resolution_notes))
            {{ $ticket->resolution_notes }}
        @else
            <span style="color: #666; font-style: italic;">Servicio en proceso. Las notas técnicas y pruebas de operatividad se registrarán al culminar las labores.</span>
        @endif
    </div>

    <!-- REPUESTOS Y PARTES SUSTITUÍDAS -->
    <div class="section-bar">5. Repuestos, Componentes y Materiales Utilizados</div>
    @php $parts = is_array($ticket->parts_replaced_json) ? $ticket->parts_replaced_json : []; @endphp
    <table class="parts-table">
        <thead>
            <tr>
                <th style="width: 45%;">Descripción del Repuesto / Componente</th>
                <th style="width: 30%;">Serial / Número de Parte</th>
                <th style="width: 25%; text-align: right;">Costo (USD / Ref)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($parts as $part)
            <tr>
                <td><strong>{{ $part['name'] ?? 'Componente' }}</strong></td>
                <td style="font-family: monospace;">{{ $part['serial'] ?? 'N/A' }}</td>
                <td style="text-align: right; font-family: monospace;">${{ number_format($part['cost'] ?? 0, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3" style="text-align: center; color: #666; padding: 8px;">
                    No se requirió sustitución de partes físicas (Servicio de limpieza lógica / física o mantenimiento preventivo).
                </td>
            </tr>
            @endforelse
            <tr>
                <td colspan="2" style="text-align: right; font-weight: bold; background: #f9f9f9;">COSTO TOTAL DE LA INTERVENCIÓN:</td>
                <td style="text-align: right; font-weight: bold; font-family: monospace; background: #f9f9f9;">
                    ${{ number_format($ticket->cost, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- FIRMAS DE CONFORMIDAD -->
    <table class="signatures-table">
        <tr>
            <td style="width: 33%;">
                <span class="label">Técnico Especialista</span>
                <div style="font-size: 8pt; margin-top: 2px;">{{ strtoupper($ticket->technician->name ?? 'TÉCNICO TI') }}</div>
                <div class="sig-line">FIRMA Y CÉDULA</div>
            </td>
            <td style="width: 33%;">
                <span class="label">Supervisor / Jefe de TI</span>
                <div style="font-size: 8pt; margin-top: 2px;">UNIDAD DE INFORMÁTICA</div>
                <div class="sig-line">FIRMA Y SELLO</div>
            </td>
            <td style="width: 34%;">
                <span class="label">Responsable / Recibe Conforme</span>
                <div style="font-size: 8pt; margin-top: 2px;">{{ strtoupper($ticket->asset->custodian_name ?? 'REPRESENTANTE DEL ÁREA') }}</div>
                <div class="sig-line">FIRMA Y FECHA</div>
            </td>
        </tr>
    </table>

    <!-- PIE CORPORATIVO -->
    <div class="footer-legal">
        Corporación Eléctrica Nacional, S.A (empresa) &bull; Centro de Servicio Valle Seco &bull; Unidad de Tecnología de la Información<br>
        Documento de control interno de soporte técnico y trazabilidad de hardware &bull; Generado el {{ date('d/m/Y H:i') }}
    </div>
</div>

</body>
</html>
