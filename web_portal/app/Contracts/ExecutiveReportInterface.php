<?php

namespace App\Contracts;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Response;

/**
 * Contrato para el Centro Unificado de Reportes & Exportación NOC + ITAM.
 */
interface ExecutiveReportInterface
{
    /**
     * Retorna los datos normalizados para el Censo Maestro NOC + ITAM.
     */
    public function getCensusData(): array;

    /**
     * Retorna los datos normalizados de la Matriz IPAM (256 Bloques).
     */
    public function getIpamData(): array;

    /**
     * Retorna los datos de Auditoría de Cumplimiento de Actas UINF-4.05 y Pases de Movilización.
     */
    public function getComplianceData(): array;

    /**
     * Retorna los datos de Ciclo de Vida, Hardware Drift y Garantías.
     */
    public function getLifecycleData(): array;

    /**
     * Retorna los datos de Disponibilidad SLA 24/7 de Enlaces WAN y Servicios NOC.
     */
    public function getSlaData(): array;

    /**
     * Exporta el reporte solicitado en formato CSV (Excel UTF-8 estructurado).
     */
    public function exportCsv(string $type, string $category = 'all'): StreamedResponse;

    /**
     * Genera el reporte solicitado en formato PDF imprimible.
     */
    public function exportPdf(string $type): Response;

    /**
     * Genera el libro Excel estructurado (SpreadsheetML por categoría o 2 hojas completas).
     */
    public function exportExcel(string $type = 'census', string $category = 'all'): Response;

    /**
     * Retorna métricas cuantitativas y KPIs para el centro de reportes.
     */
    public function getReportMetrics(): array;

    /**
     * Retorna el catálogo descriptivo de reportes disponibles.
     */
    public function getAvailableReports(): array;
}
