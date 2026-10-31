<?php

declare(strict_types=1);

namespace App\Services\Reports\Exporters;

use App\Services\AuditService;
use App\Services\Reports\Evaluators\CensusReportEvaluator;
use App\Services\Reports\Evaluators\NocAuditReportEvaluator;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportador de reportes ejecutivos e inventario a formato CSV.
 */
class CsvReportExporter
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
     * Genera la descarga CSV en flujo (StreamedResponse) para reportes generales o categorizados.
     */
    public function exportCsv(string $type, string $category = 'all'): StreamedResponse
    {
        $timestamp = Carbon::now()->format('Y-m-d_His');

        if ($type === 'census' && $category !== 'all') {
            return $this->exportCategoryCsv($category, $timestamp);
        }

        $data = $this->getReportData($type);
        $filename = "reporte_{$type}_{$timestamp}.csv";

        AuditService::log(
            event: 'exported',
            module: 'reports',
            description: "Exportación de Reporte [{$data['title']}] en formato CSV",
            auditable: null,
            entityName: 'Centro de Reportes',
            entityLabel: strtoupper($type)
        );

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['REPÚBLICA BOLIVARIANA DE VENEZUELA - MPPEE - EMPRESA']);
            fputcsv($handle, [$data['title']]);
            fputcsv($handle, ['Fecha de Emisión: ' . now()->format('d/m/Y H:i:s')]);
            fputcsv($handle, []);

            if (!empty($data['summary'])) {
                fputcsv($handle, ['--- RESUMEN DE INDICADORES ---']);
                foreach ($data['summary'] as $k => $v) {
                    fputcsv($handle, [$k, $v]);
                }
                fputcsv($handle, []);
            }

            fputcsv($handle, $data['headers']);

            foreach ($data['rows'] as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * Exporta en CSV una categoría específica del inventario (servidores, telecom o computación).
     */
    public function exportCategoryCsv(string $category, string $timestamp): StreamedResponse
    {
        if ($category === 'servers') {
            $title = 'Inventario de Servidores • Infraestructura y Datacenter';
            $filename = "inventario_servidores_{$timestamp}.csv";
            $headers = ['RACK', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'S.O', 'FÍSICO', 'VIRTUALIZADO', 'HARDWARE', 'PROCESADOR', 'SERVICIOS', 'IP', 'MAC', 'CONDICIONES', 'ADMINISTRADOR'];
            $rows = $this->censusEvaluator->getServerRows();
        } elseif ($category === 'telecom') {
            $title = 'Inventario de Telecomunicaciones • Switches, Routers, Telefonía IP y Red';
            $filename = "inventario_telecomunicaciones_{$timestamp}.csv";
            $headers = ['RACK', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'S.O', 'FÍSICO', 'VIRTUALIZADO', 'HARDWARE', 'PROCESADOR', 'SERVICIOS', 'IP', 'MAC', 'CONDICIONES', 'ADMINISTRADOR'];
            $rows = $this->censusEvaluator->getTelecomRows();
        } else {
            $title = 'Inventario de Equipos de Computación & Periféricos de Trabajo';
            $filename = "inventario_equipos_computacion_{$timestamp}.csv";
            $headers = ['TIPO DE EQUIPO', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'SISTEMA OPERATIVO', 'APLICATIVOS INSTALADOS', 'NOMBRE Y APELLIDO', 'CÉDULA', 'TELÉFONO', 'ESTADO', 'UNIDAD', 'CONDICIONES', 'OBSERVACION'];
            $rows = $this->censusEvaluator->getWorkstationRows();
        }

        AuditService::log(
            event: 'exported',
            module: 'reports',
            description: "Exportación de Inventario Categorizado [{$category}] en formato CSV",
            auditable: null,
            entityName: 'Centro de Reportes',
            entityLabel: strtoupper($category)
        );

        return response()->streamDownload(function () use ($title, $headers, $rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['REPÚBLICA BOLIVARIANA DE VENEZUELA - MPPEE - EMPRESA']);
            fputcsv($handle, [$title]);
            fputcsv($handle, ['Gerencia General de ATIT • Sede Valle Seco']);
            fputcsv($handle, ['Fecha de Emisión: ' . now()->format('d/m/Y H:i:s')]);
            fputcsv($handle, []);
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
