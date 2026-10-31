<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\NetRadar\ExportNetRadarReportAction;
use App\Actions\NetRadar\FetchNetRadarDashboardAction;
use App\Actions\NetRadar\TriggerNetRadarScanAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminNetRadarController extends Controller
{
    public function index(Request $request, FetchNetRadarDashboardAction $action): View
    {
        $this->authorize('netradar.view');
        return view('admin.netradar.index', $action->execute($request));
    }

    public function data(FetchNetRadarDashboardAction $action): JsonResponse
    {
        return response()->json($action->getDataJson());
    }

    public function events(Request $request, FetchNetRadarDashboardAction $action): JsonResponse
    {
        return response()->json($action->getEventsJson($request->query('ip')));
    }

    public function scanNow(Request $request, TriggerNetRadarScanAction $action): JsonResponse
    {
        $this->authorize('netradar.scan');
        $result = $action->execute($request->user(), $request->ip(), $request->userAgent());
        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function exportReport(Request $request, ExportNetRadarReportAction $action): StreamedResponse
    {
        $this->authorize('netradar.export');
        return $action->execute($request->user(), $request->ip(), $request->userAgent());
    }
}
