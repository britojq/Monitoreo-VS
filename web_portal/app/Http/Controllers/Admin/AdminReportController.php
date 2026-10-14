<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ExecutiveReportInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador para el Centro Unificado de Reportes y Exportación NOC + ITAM.
 */
class AdminReportController extends Controller
{
    public function __construct(
        protected ExecutiveReportInterface $reportService
    ) {}

    /**
     * Muestra el catálogo visual y centro de descargas de reportes.
     */
    public function index(): View
    {
        $reports = $this->reportService->getAvailableReports();
        $metrics = $this->reportService->getReportMetrics();

        return view('admin.reports.index', compact('reports', 'metrics'));
    }

    /**
     * Genera y despacha la exportación del reporte solicitado en Excel, CSV o PDF.
     */
    public function export(Request $request): StreamedResponse|Response
    {
        $type = (string)$request->query('type', 'census');
        $format = strtolower((string)$request->query('format', 'csv'));
        $category = strtolower((string)$request->query('category', 'all'));

        $allowedTypes = ['census', 'compliance', 'lifecycle', 'sla'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'census';
        }

        $allowedCategories = ['all', 'servers', 'telecom', 'workstations'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'all';
        }

        if ($format === 'excel' || $format === 'xls' || $format === 'xlsx') {
            return $this->reportService->exportExcel($type, $category);
        }

        if ($format === 'pdf') {
            return $this->reportService->exportPdf($type);
        }

        return $this->reportService->exportCsv($type, $category);
    }
}
