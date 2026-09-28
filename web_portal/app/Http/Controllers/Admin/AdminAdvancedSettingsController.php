<?php

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
        $aiConfig = (new AiConfigService())->getConfig();

        return view('admin.settings.advanced', [
            'stats' => $stats,
            'cronConfig' => $cronConfig,
            'latestSnapshot' => $latestSnapshot,
            'aiConfig' => $aiConfig,
        ]);
    }

    /**
     * Alternar o fijar el estado del Asistente Virtual corporativo de IA.
     */
    public function toggleAi(Request $request)
    {
        $aiService = new AiConfigService();
        $currentConfig = $aiService->getConfig();
        $currentUser = $request->user();
        $adminName = $currentUser ? "{$currentUser->name} [Web]" : "Administrador [Web]";

        if ($request->has('enabled')) {
            $newState = filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN);
        } else {
            $newState = !$currentConfig['enabled'];
        }

        $success = $aiService->updateConfig($newState, $adminName);

        if ($success) {
            AuditService::logCustom(
                event: $newState ? 'enabled' : 'disabled',
                module: 'CONFIG',
                entityName: 'ai_assistant',
                entityLabel: 'Asistente Virtual IA',
                description: $newState 
                    ? "El Administrador {$adminName} activó el Asistente Virtual corporativo de IA en el portal web."
                    : "El Administrador {$adminName} desactivó el Asistente Virtual corporativo de IA en el portal web.",
                oldValues: ['ai_web_enabled' => $currentConfig['enabled']],
                newValues: ['ai_web_enabled' => $newState],
                request: $request,
                user: $currentUser
            );

            $msg = $newState 
                ? 'El Asistente Virtual corporativo de IA ha sido ACTIVADO correctamente.'
                : 'El Asistente Virtual corporativo de IA ha sido DESACTIVADO por el módulo administrativo.';

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
                'message' => 'No fue posible actualizar el estado del Asistente Virtual de IA.',
            ], 500);
        }

        return back()->with('error', 'No fue posible actualizar el estado del Asistente Virtual de IA.');
    }
}
