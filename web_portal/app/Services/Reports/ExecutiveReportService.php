<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Contracts\ExecutiveReportInterface;
use App\Services\Reports\Evaluators\CensusReportEvaluator;
use App\Services\Reports\Evaluators\NocAuditReportEvaluator;
use App\Services\Reports\Evaluators\ReportCatalogEvaluator;
use App\Services\Reports\Exporters\CsvReportExporter;
use App\Services\Reports\Exporters\ExcelReportExporter;
use App\Services\Reports\Exporters\PdfReportExporter;
use App\Services\Reports\Spreadsheet\EquipmentInventorySpreadsheetBuilder;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fachada delgada para el Centro Unificado de Reportes & Exportación NOC + ITAM.
 * Orquesta evaluadores de datos, constructores de plantillas y exportadores.
 */
class ExecutiveReportService implements ExecutiveReportInterface
{
    public function __construct(
        protected ReportCatalogEvaluator $catalogEvaluator,
        protected CensusReportEvaluator $censusEvaluator,
        protected NocAuditReportEvaluator $nocEvaluator,
        protected CsvReportExporter $csvExporter,
        protected PdfReportExporter $pdfExporter,
        protected ExcelReportExporter $excelExporter,
        protected EquipmentInventorySpreadsheetBuilder $spreadsheetBuilder
    ) {}

    public function getAvailableReports(): array
    {
        return $this->catalogEvaluator->getAvailableReports();
    }

    public function getReportMetrics(): array
    {
        return $this->catalogEvaluator->getReportMetrics();
    }

    public function getCensusData(): array
    {
        return $this->censusEvaluator->getCensusData();
    }

    public function getServerRows(): array
    {
        return $this->censusEvaluator->getServerRows();
    }

    public function getTelecomRows(): array
    {
        return $this->censusEvaluator->getTelecomRows();
    }

    public function getCenterDataRows(): array
    {
        return $this->censusEvaluator->getCenterDataRows();
    }

    public function getWorkstationRows(): array
    {
        return $this->censusEvaluator->getWorkstationRows();
    }

    public function getIpamData(): array
    {
        return $this->nocEvaluator->getIpamData();
    }

    public function getComplianceData(): array
    {
        return $this->nocEvaluator->getComplianceData();
    }

    public function getLifecycleData(): array
    {
        return $this->nocEvaluator->getLifecycleData();
    }

    public function getSlaData(): array
    {
        return $this->nocEvaluator->getSlaData();
    }

    public function exportCsv(string $type, string $category = 'all'): StreamedResponse
    {
        return $this->csvExporter->exportCsv($type, $category);
    }

    public function exportPdf(string $type): Response
    {
        return $this->pdfExporter->exportPdf($type);
    }

    public function exportExcel(string $type = 'census', string $category = 'all'): Response
    {
        return $this->excelExporter->exportExcel($type, $category);
    }

    public function buildEquipmentInventorySpreadsheetXml(): string
    {
        return $this->spreadsheetBuilder->buildEquipmentInventorySpreadsheetXml(
            $this->censusEvaluator->getCenterDataRows(),
            $this->censusEvaluator->getWorkstationRows()
        );
    }

    public function buildSingleCategorySpreadsheetXml(string $sheetName, string $title, string $categoryDesc, array $rows): string
    {
        return $this->spreadsheetBuilder->buildSingleCategorySpreadsheetXml($sheetName, $title, $categoryDesc, $rows);
    }

    public function buildWorkstationCategorySpreadsheetXml(array $rows): string
    {
        return $this->spreadsheetBuilder->buildWorkstationCategorySpreadsheetXml($rows);
    }

    public function buildGenericReportSpreadsheetXml(string $type): string
    {
        $data = match ($type) {
            'ipam' => $this->nocEvaluator->getIpamData(),
            'compliance' => $this->nocEvaluator->getComplianceData(),
            'lifecycle' => $this->nocEvaluator->getLifecycleData(),
            'sla' => $this->nocEvaluator->getSlaData(),
            default => $this->censusEvaluator->getCensusData(),
        };

        return $this->spreadsheetBuilder->buildGenericReportSpreadsheetXml($data);
    }
}
