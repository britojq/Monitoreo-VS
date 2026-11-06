<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Ups\FetchUpsDashboardAction;
use App\Actions\Ups\InterrogateUpsHardwareAction;
use App\Actions\Ups\UpdateUpsSettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUpsSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUpsController extends Controller
{
    public function index(Request $request, FetchUpsDashboardAction $action): View
    {
        return view('admin.ups.index', $action->execute($request));
    }

    public function live(Request $request, FetchUpsDashboardAction $action): JsonResponse|RedirectResponse
    {
        if (!$request->expectsJson() && !$request->ajax() && !str_contains($request->header('Accept', ''), 'application/json')) {
            return redirect()->route('admin.ups.index');
        }
        $data = $action->getLiveData();
        return $data ? response()->json($data) : response()->json(['success' => false, 'message' => 'Dispositivo UPS no configurado.'], 404);
    }

    public function updateSettings(UpdateUpsSettingsRequest $request, UpdateUpsSettingsAction $action): RedirectResponse|JsonResponse
    {
        $result = $action->execute($request->validated(), $request->user(), $request->ip(), $request->userAgent());
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }
        return redirect()->route('admin.ups.index')->with('success', 'Configuración de UPS actualizada y sincronizada.');
    }

    public function interrogateHardware(Request $request, InterrogateUpsHardwareAction $action): JsonResponse
    {
        $port = trim((string) $request->input('serial_port', ''));
        $baud = (int) $request->input('baud_rate', 2400);
        return response()->json($action->interrogate($port, $baud));
    }

    public function listPorts(InterrogateUpsHardwareAction $action): JsonResponse
    {
        return response()->json($action->listPorts());
    }

    public function testSerial(Request $request, InterrogateUpsHardwareAction $action): JsonResponse
    {
        $port = trim((string) $request->input('serial_port', ''));
        $baud = (int) $request->input('baud_rate', 2400);
        return response()->json($action->testSerial($port, $baud));
    }

    public function scanPorts(InterrogateUpsHardwareAction $action): JsonResponse
    {
        return response()->json($action->scanPorts());
    }
}
