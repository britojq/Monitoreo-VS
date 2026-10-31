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
        $user = $request->user();
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission('netradar.scan') && $user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'No tiene permisos para capturas en vivo.'], 403);
        }
        $result = $action->execute($user, $request->ip(), $request->userAgent());
        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function exportReport(Request $request, ExportNetRadarReportAction $action): StreamedResponse
    {
        return $action->execute($request->user(), $request->ip(), $request->userAgent());
    }
}
