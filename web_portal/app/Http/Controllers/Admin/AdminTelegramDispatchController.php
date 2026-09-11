<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramDispatch;
use App\Services\TelegramReportDispatchService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminTelegramDispatchController extends Controller
{
    /**
     * Despacha un reporte oficial a Telegram desde el Dashboard.
     */
    public function dispatch(Request $request, TelegramReportDispatchService $dispatchService): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        // Validar datos de la solicitud
        $validated = $request->validate([
            'report_type' => ['required', 'string', 'in:servicios,sedes,completo'],
            'mode' => ['nullable', 'string', 'in:instant,live'],
        ], [
            'report_type.required' => 'Debe seleccionar el tipo de reporte a despachar.',
            'report_type.in' => 'El tipo de reporte seleccionado no es válido.',
        ]);

        // Verificar si la ficha institucional está completa
        if (!$user->hasCompleteAtitProfile()) {
            return response()->json([
                'success' => false,
                'incomplete_profile' => true,
                'message' => 'Para despachar reportes oficiales debes registrar tu Cédula de Identidad, N° de Personal y Teléfono en tu perfil institucional.',
                'profile_url' => route('admin.profile.show'),
            ], 422);
        }

        $reportType = $validated['report_type'];
        $mode = $validated['mode'] ?? 'instant';

        $result = $dispatchService->dispatch($user, $reportType, $mode, $request->ip());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'operator' => $user->full_title_name,
            'report_type' => $reportType,
            'dispatched_at' => Carbon::now('America/Caracas')->format('h:i:s A'),
        ]);
    }

    /**
     * Consulta el estado del último despacho para informar al operador.
     */
    public function getLatestDispatch(): JsonResponse
    {
        $latest = TelegramDispatch::getLatestDispatch();
        $latestServices = TelegramDispatch::getLatestDispatch('servicios');
        $latestSedes = TelegramDispatch::getLatestDispatch('sedes');

        $formatDispatch = function (?TelegramDispatch $d) {
            if (!$d) {
                return null;
            }
            $diffMinutes = (int) round($d->created_at->diffInMinutes(now()));
            return [
                'id' => $d->id,
                'operator_name' => $d->operator_name,
                'report_type' => $d->report_type,
                'report_type_label' => $d->report_type === 'sedes' ? 'Sedes y Enlaces' : 'Servicios Corporativos',
                'created_at' => $d->created_at->format('Y-m-d H:i:s'),
                'time_ago' => $d->created_at->locale('es')->diffForHumans(),
                'diff_minutes' => $diffMinutes,
                'is_recent' => $diffMinutes < 15,
            ];
        };

        return response()->json([
            'latest_general' => $formatDispatch($latest),
            'latest_services' => $formatDispatch($latestServices),
            'latest_sedes' => $formatDispatch($latestSedes),
        ]);
    }
}
