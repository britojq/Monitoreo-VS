<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] }} • Reporte NOC + ITAM</title>
    <style>
        @page {
            margin: 12mm 10mm 14mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #1e293b;
            line-height: 1.25;
            margin: 0;
            padding: 0;
        }
        /* CABECERA DE REPORTE */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0891b2;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .logo-img {
            max-height: 38px;
            max-width: 130px;
        }
        .header-title-box {
            text-align: center;
        }
        .header-inst {
            font-size: 7.5pt;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-report-title {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
            text-transform: uppercase;
        }
        .header-meta {
            font-size: 7pt;
            color: #64748b;
            text-align: right;
            font-family: 'Courier New', Courier, monospace;
        }

        /* RESUMEN DE INDICADORES (KPIs) */
        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 10px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        .summary-table td {
            padding: 2px 6px;
        }
        .summary-label {
            font-weight: bold;
            color: #334155;
        }
        .summary-value {
            color: #0891b2;
            font-weight: bold;
            font-family: 'Courier New', Courier, monospace;
        }

        /* TABLA PRINCIPAL DE DATOS */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 4px 5px;
            border: 1px solid #0f172a;
            text-transform: uppercase;
            font-size: 6.5pt;
            letter-spacing: 0.3px;
        }
        .data-table td {
            padding: 3.5px 5px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .font-mono {
            font-family: 'Courier New', Courier, monospace;
        }
        .text-bold {
            font-weight: bold;
        }
        .text-cyan {
            color: #0891b2;
        }

        /* PIE DE PÁGINA */
        .footer-table {
            position: fixed;
            bottom: -5mm;
            left: 0;
            right: 0;
            width: 100%;
            border-top: 1px solid #cbd5e1;
            font-size: 6.5pt;
            color: #64748b;
            padding-top: 3px;
            font-family: 'Courier New', Courier, monospace;
        }
    </style>
</head>
<body>
    <!-- CABECERA DE REPORTE -->
    <table class="header-table">
        <tr>
            <td style="width: 20%; text-align: left;">
                @if(!empty($empresaLogoBase64))
                    <img src="data:image/png;base64,{{ $empresaLogoBase64 }}" class="logo-img" alt="empresa">
                @else
                    <span style="font-weight: bold; color: #0891b2; font-size: 11pt;">empresa</span>
                @endif
            </td>
            <td class="header-title-box" style="width: 60%;">
                <div class="header-inst">República Bolivariana de Venezuela • MPPEE • Gerencia General de TI</div>
                <div class="header-report-title">{{ $report['title'] }}</div>
                <div style="font-size: 7.5pt; color: #0891b2; font-weight: bold; margin-top: 1px;">Sede Centro de Operaciones Valle Seco</div>
            </td>
            <td style="width: 20%; text-align: right;">
                @if(!empty($mppeeLogoBase64))
                    <img src="data:image/png;base64,{{ $mppeeLogoBase64 }}" class="logo-img" alt="MPPEE">
                @endif
                <div class="header-meta" style="margin-top: 3px;">
                    <strong>Emisión:</strong> {{ $generatedAt }}
                </div>
            </td>
        </tr>
    </table>

    <!-- BLOQUE RESUMEN DE INDICADORES -->
    @if(!empty($report['summary']))
        <div class="summary-box">
            <table class="summary-table">
                <tr>
                    @foreach($report['summary'] as $label => $val)
                        <td class="summary-label">{{ $label }}: <span class="summary-value">{{ $val }}</span></td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

    <!-- TABLA PRINCIPAL DE DATOS -->
    <table class="data-table">
        <thead>
            <tr>
                @foreach($report['headers'] as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $row)
                <tr>
                    @foreach($row as $key => $cell)
                        <td class="{{ in_array($key, ['ip_address', 'mac_address', 'serial_number', 'asset_tag', 'assignment_code']) ? 'font-mono' : '' }} {{ $key === 'display_identity' ? 'text-bold' : '' }}">
                            {{ $cell }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report['headers']) }}" style="text-align: center; padding: 10px; color: #64748b; font-style: italic;">
                        No se registraron registros para este informe en MariaDB.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- PIE DE PÁGINA -->
    <table class="footer-table">
        <tr>
            <td style="text-align: left;">
                Monitor Valle Seco NOC + ITAM • MariaDB SSOT • Auditoría: {{ substr($auditHash ?? '', 0, 16) }}
            </td>
            <td style="text-align: right;">
                Documento Generado Automatizadamente • Confidencial
            </td>
        </tr>
    </table>
</body>
</html>
