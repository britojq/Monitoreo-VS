<?php

declare(strict_types=1);

namespace App\Actions\Alert;

use App\Models\Alert;
use App\Models\AlertCorrelationGroup;
use App\Models\AlertRule;
use App\Models\MaintenanceWindow;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\SnmpDevice;
use App\Models\SslCertificate;
use Illuminate\Http\Request;

class FetchAlertDashboardAction
{
    public function execute(Request $request): array
    {
        $tab = $request->query('tab', 'active');
        $severityFilter = $request->query('severity');
        $statusFilter = $request->query('status');
        $search = $request->query('search');

        // 1. Alertas Activas
        $activeQuery = Alert::with(['rule', 'correlationGroup', 'acknowledgedByUser'])
            ->whereIn('status', ['firing', 'acknowledged', 'suppressed'])
            ->orderByRaw("FIELD(severity, 'emergency', 'critical', 'warning', 'info')")
            ->orderBy('fired_at', 'desc');

        if (!empty($severityFilter)) {
            $activeQuery->where('severity', $severityFilter);
        }
        if (!empty($statusFilter)) {
            $activeQuery->where('status', $statusFilter);
        }
        if (!empty($search)) {
            $activeQuery->where(function ($q) use ($search) {
                $q->where('entity_name', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhere('condition_type', 'like', "%{$search}%");
            });
        }
        $activeAlerts = $activeQuery->paginate(15, ['*'], 'active_page')->withQueryString();

        // 2. Historial de Incidentes Resueltos
        $historyQuery = Alert::with(['rule', 'acknowledgedByUser'])
            ->whereIn('status', ['resolved', 'auto_resolved'])
            ->orderBy('resolved_at', 'desc');
        if (!empty($search)) {
            $historyQuery->where(function ($q) use ($search) {
                $q->where('entity_name', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhere('condition_type', 'like', "%{$search}%");
            });
        }
        $historyAlerts = $historyQuery->paginate(15, ['*'], 'history_page')->withQueryString();

        // 3. Reglas de Alerta y Entidades Auxiliares
        $rules = AlertRule::with('escalationLevels')->orderBy('id')->get();
        $maintenanceWindows = MaintenanceWindow::with('creator')->orderBy('starts_at', 'desc')->paginate(10, ['*'], 'maint_page');
        $correlationGroups = AlertCorrelationGroup::with('members')->orderBy('id')->get();

        $stats = [
            'firing' => Alert::where('status', 'firing')->count(),
            'acknowledged' => Alert::where('status', 'acknowledged')->count(),
            'suppressed' => Alert::where('status', 'suppressed')->count(),
            'critical_emergency' => Alert::whereIn('status', ['firing', 'acknowledged'])
                ->whereIn('severity', ['critical', 'emergency'])->count(),
            'active_maintenance' => MaintenanceWindow::activeNow()->count(),
            'total_rules' => AlertRule::where('is_active', true)->count(),
        ];

        $services = MonitoredService::where('is_active', true)->orderBy('name')->get();
        $sites = MonitoredSite::where('is_active', true)->orderBy('name')->get();
        $snmpDevices = SnmpDevice::where('is_active', true)->orderBy('name')->get();
        $sslCerts = SslCertificate::where('is_active', true)->orderBy('domain')->get();

        return compact(
            'tab',
            'activeAlerts',
            'historyAlerts',
            'rules',
            'maintenanceWindows',
            'correlationGroups',
            'stats',
            'services',
            'sites',
            'snmpDevices',
            'sslCerts'
        );
    }
}
