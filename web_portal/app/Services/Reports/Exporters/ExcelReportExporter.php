<?php

declare(strict_types=1);

namespace App\Services\Reports\Exporters;

use App\Services\AuditService;
use App\Services\Reports\Evaluators\CensusReportEvaluator;
use App\Services\Reports\Evaluators\NocAuditReportEvaluator;
use App\Services\Reports\Spreadsheet\EquipmentInventorySpreadsheetBuilder;
use Carbon\Carbon;
use Illuminate\Http\Response;

/**
 * Exportador de inventario y reportes técnicos en formato Excel SpreadsheetML XML.
 */
class ExcelReportExporter
{
    public function __construct(
        protected CensusReportEvaluator $censusEvaluator,
        protected NocAuditReportEvaluator $nocEvaluator,
        protected EquipmentInventorySpreadsheetBuilder $builder
    ) {}

    /**
     * Genera el libro Excel estructurado (SpreadsheetML) según tipo y categoría.
     */
    public function exportExcel(string $type = 'census', string $category = 'all'): Response
    {
        $timestamp = Carbon::now()->format('Y-m-d_His');

        if ($type === 'census') {
            if ($category === 'servers') {
                $xml = $this->builder->buildSingleCategorySpreadsheetXml(
                    sheetName: 'Servidores',
                    title: 'Inventario de Servidores • Infraestructura y Centro de Datos',
                    categoryDesc: 'SERVIDORES Y CENTRO DE DATOS',
                    rows: $this->censusEvaluator->getServerRows()
                );
                $filename = "Inventario_Servidores_{$timestamp}.xls";
                $logLabel = 'EXCEL_SERVIDORES';
            } elseif ($category === 'telecom') {
                $xml = $this->builder->buildSingleCategorySpreadsheetXml(
                    sheetName: 'Telecomunicaciones',
                    title: 'Inventario de Telecomunicaciones • Switches, Routers, Telefonía IP y Red',
                    categoryDesc: 'TELECOMUNICACIONES Y DISPOSITIVOS DE RED',
                    rows: $this->censusEvaluator->getTelecomRows()
                );
                $filename = "Inventario_Telecomunicaciones_{$timestamp}.xls";
                $logLabel = 'EXCEL_TELECOMUNICACIONES';
            } elseif ($category === 'workstations') {
                $xml = $this->builder->buildWorkstationCategorySpreadsheetXml(
                    $this->censusEvaluator->getWorkstationRows()
                );
                $filename = "Inventario_Equipos_Computacion_{$timestamp}.xls";
                $logLabel = 'EXCEL_COMPUTACION';
            } else {
                $xml = $this->builder->buildEquipmentInventorySpreadsheetXml(
                    $this->censusEvaluator->getCenterDataRows(),
                    $this->censusEvaluator->getWorkstationRows()
                );
                $filename = "Inventario_Equipos_Tecnologicos_{$timestamp}.xls";
                $logLabel = 'EXCEL_2_HOJAS';
            }
        } else {
            $data = match ($type) {
                'ipam' => $this->nocEvaluator->getIpamData(),
                'compliance' => $this->nocEvaluator->getComplianceData(),
                'lifecycle' => $this->nocEvaluator->getLifecycleData(),
                'sla' => $this->nocEvaluator->getSlaData(),
                default => $this->censusEvaluator->getCensusData(),
            };
            $xml = $this->builder->buildGenericReportSpreadsheetXml($data);
            $filename = "Reporte_{$type}_{$timestamp}.xls";
            $logLabel = strtoupper($type);
        }

        AuditService::log(
            event: 'exported',
            module: 'reports',
            description: "Exportación de Reporte [{$type}] categoría [{$category}] en formato Excel",
            auditable: null,
            entityName: 'Centro de Reportes',
            entityLabel: $logLabel
        );

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
