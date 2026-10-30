<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;
use App\Models\MonitoringSnapshot;
use App\Models\User;
use App\Services\AiConfigService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAdvancedSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $stats = [
            'users_count' => User::count(),
            'services_count' => MonitoredService::count(),
            'services_active' => MonitoredService::where('is_active', true)->count(),
            'sites_count' => MonitoredSite::count(),
            'sites_active' => MonitoredSite::where('is_active', true)->count(),
            'devices_count' => MonitoredSiteDevice::count(),
            'network_devices_count' => MonitoredNetworkDevice::count(),
            'network_devices_active' => MonitoredNetworkDevice::where('is_active', true)->count(),
            'proxies_count' => MonitoredProxy::count(),
            'proxies_active' => MonitoredProxy::where('is_active', true)->count(),
        ];

        $cronConfig = (new AdminCronController())->getCronConfig();
        $latestSnapshot = MonitoringSnapshot::latest()->first();
        $aiService = new AiConfigService();
        $groupAiConfig = $aiService->getGroupAiConfig();

        return view('admin.settings.advanced', [
            'stats' => $stats,
            'cronConfig' => $cronConfig,
            'latestSnapshot' => $latestSnapshot,
            'groupAiConfig' => $groupAiConfig,
        ]);
    }

    /**
     * Alternar o fijar el estado del Asistente Virtual de IA en el Grupo Corporativo de Telegram.
     */
    public function toggleGroupAi(Request $request)
    {
        $aiService = new AiConfigService();
        $currentConfig = $aiService->getGroupAiConfig();
        $currentUser = $request->user();
        $adminName = $currentUser ? "{$currentUser->name} [Web]" : "Administrador [Web]";

        if ($request->has('enabled')) {
            $newState = filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN);
        } else {
            $newState = !$currentConfig['enabled'];
        }

        $success = $aiService->updateGroupAiConfig($newState, $adminName);

        if ($success) {
            AuditService::logCustom(
                event: $newState ? 'enabled' : 'disabled',
                module: 'CONFIG',
                entityName: 'telegram_group_ai',
                entityLabel: 'Asistente IA en Grupo Telegram',
                description: $newState 
                    ? "El Administrador {$adminName} activó el Asistente de IA en el Grupo Corporativo de Telegram."
                    : "El Administrador {$adminName} desactivó el Asistente de IA en el Grupo Corporativo de Telegram.",
                oldValues: ['telegram_group_ai_enabled' => $currentConfig['enabled']],
                newValues: ['telegram_group_ai_enabled' => $newState],
                request: $request,
                user: $currentUser
            );

            $msg = $newState 
                ? 'El Asistente de IA en el Grupo Corporativo de Telegram ha sido ACTIVADO exitosamente.'
                : 'El Asistente de IA en el Grupo Corporativo de Telegram ha sido DESACTIVADO por la administración.';

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'enabled' => $newState,
                    'message' => $msg,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $adminName,
                ]);
            }

            return back()->with('success', $msg);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'No fue posible actualizar el estado del Asistente de IA en el Grupo.',
            ], 500);
        }

        return back()->with('error', 'No fue posible actualizar el estado del Asistente de IA en el Grupo.');
    }
}
