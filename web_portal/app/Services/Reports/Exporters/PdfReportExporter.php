<?php

declare(strict_types=1);

namespace App\Services\Reports\Exporters;

use App\Services\AuditService;
use App\Services\Reports\Evaluators\CensusReportEvaluator;
use App\Services\Reports\Evaluators\NocAuditReportEvaluator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;

/**
 * Exportador de reportes ejecutivos a formato PDF imprimible.
 */
class PdfReportExporter
{
    public function __construct(
        protected CensusReportEvaluator $censusEvaluator,
        protected NocAuditReportEvaluator $nocEvaluator
    ) {}

    /**
     * Resuelve los datos consolidados del reporte según el tipo.
     */
    public function getReportData(string $type): array
    {
        return match ($type) {
            'census' => $this->censusEvaluator->getCensusData(),
            'ipam' => $this->nocEvaluator->getIpamData(),
            'compliance' => $this->nocEvaluator->getComplianceData(),
            'lifecycle' => $this->nocEvaluator->getLifecycleData(),
            'sla' => $this->nocEvaluator->getSlaData(),
            default => $this->censusEvaluator->getCensusData(),
        };
    }

    /**
     * Genera el reporte solicitado en formato PDF imprimible y auditado.
     */
    public function exportPdf(string $type): Response
    {
        $data = $this->getReportData($type);
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $filename = "reporte_{$type}_{$timestamp}.pdf";

        // Logotipos corporativos locales
        $empresaPath = public_path('assets/img/inventory/empresa_logo.png');
        $mppeePath = public_path('assets/img/inventory/mppee_logo.png');

        $empresaLogoBase64 = file_exists($empresaPath) ? base64_encode(file_get_contents($empresaPath)) : null;
        $mppeeLogoBase64 = file_exists($mppeePath) ? base64_encode(file_get_contents($mppeePath)) : null;

        AuditService::log(
            event: 'exported',
            module: 'reports',
            description: "Exportación de Reporte [{$data['title']}] en formato PDF",
            auditable: null,
            entityName: 'Centro de Reportes',
            entityLabel: strtoupper($type)
        );

        $pdf = Pdf::loadView('admin.reports.pdf.executive_report', [
            'report' => $data,
            'type' => $type,
            'empresaLogoBase64' => $empresaLogoBase64,
            'mppeeLogoBase64' => $mppeeLogoBase64,
            'generatedAt' => now()->format('d/m/Y H:i:s'),
            'auditHash' => strtoupper(hash('sha256', $type . now()->timestamp . 'VALLE_SECO_REPORTS')),
        ])->setPaper('letter', 'landscape');

        return $pdf->download($filename);
    }
}
