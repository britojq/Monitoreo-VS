<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\AlertCorrelationGroup;
use App\Models\AlertCorrelationMember;
use App\Models\AlertEscalationLevel;
use App\Models\AlertNotification;
use App\Models\AlertRule;
use App\Models\AuditLog;
use App\Models\MaintenanceWindow;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\SnmpDevice;
use App\Models\SslCertificate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;

class AdminAlertController extends Controller
{
    /**
     * Vista principal de Alertas, Correlación, Reglas y Mantenimiento.
     * Accesible tanto para Administrador como para Operador (Modo Consulta / Operación).
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'active');
        $severityFilter = $request->query('severity');
        $statusFilter = $request->query('status');
        $search = $request->query('search');

        // 1. Alertas Activas (Firing, Acknowledged, Suppressed)
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

        // 3. Reglas de Alerta
        $rules = AlertRule::with('escalationLevels')->orderBy('id')->get();

        // 4. Ventanas de Mantenimiento
        $maintenanceWindows = MaintenanceWindow::with('creator')->orderBy('starts_at', 'desc')->paginate(10, ['*'], 'maint_page');

        // 5. Grupos de Correlación
        $correlationGroups = AlertCorrelationGroup::with('members')->orderBy('id')->get();

        // Métricas HUD
        $totalFiring = Alert::where('status', 'firing')->count();
        $totalAcknowledged = Alert::where('status', 'acknowledged')->count();
        $totalSuppressed = Alert::where('status', 'suppressed')->count();
        $criticalEmergFiring = Alert::whereIn('status', ['firing', 'acknowledged'])
            ->whereIn('severity', ['critical', 'emergency'])->count();
        $activeMaintenanceCount = MaintenanceWindow::activeNow()->count();
        $totalRules = AlertRule::where('is_active', true)->count();

        $stats = [
            'firing' => $totalFiring,
            'acknowledged' => $totalAcknowledged,
            'suppressed' => $totalSuppressed,
            'critical_emergency' => $criticalEmergFiring,
            'active_maintenance' => $activeMaintenanceCount,
            'total_rules' => $totalRules,
        ];

        // Listas auxiliares para modales de creación
        $services = MonitoredService::where('is_active', true)->orderBy('name')->get();
        $sites = MonitoredSite::where('is_active', true)->orderBy('name')->get();
        $snmpDevices = SnmpDevice::where('is_active', true)->orderBy('name')->get();
        $sslCerts = SslCertificate::where('is_active', true)->orderBy('domain')->get();

        return view('admin.alerts.index', compact(
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
        ));
    }

    /**
     * Retorna datos completos y notificaciones de una alerta (para modal de detalle/timeline).
     */
    public function show(int $id): JsonResponse
    {
        $alert = Alert::with(['rule', 'correlationGroup', 'parentAlert', 'acknowledgedByUser', 'notifications.escalationLevel'])
            ->findOrFail($id);

        $notifications = $alert->notifications->map(function ($n) {
            return [
                'id' => $n->id,
                'channel' => strtoupper($n->channel),
                'target' => $n->target,
                'status' => $n->status === 'sent' ? 'Enviada' : ($n->status === 'failed' ? 'Fallida' : ucfirst($n->status)),
                'sent_at' => $n->sent_at ? $n->sent_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'N/A',
                'error_message' => $n->error_message,
                'message_preview' => strip_tags($n->message_sent),
            ];
        });

        return response()->json([
            'id' => $alert->id,
            'entity_name' => $alert->entity_name,
            'entity_type' => $alert->entity_type,
            'entity_type_label' => $alert->entity_type_label,
            'entity_id' => $alert->entity_id,
            'condition_type' => $alert->condition_type,
            'condition_label' => $alert->condition_label,
            'severity' => $alert->severity,
            'severity_label' => $alert->severity_label,
            'status' => $alert->status,
            'status_label' => $alert->status_label,
            'current_escalation_level' => $alert->current_escalation_level,
            'value_at_trigger' => $alert->value_at_trigger,
            'threshold_value' => $alert->threshold_value,
            'message' => $alert->message,
            'notes' => $alert->notes,
            'fired_at' => $alert->fired_at ? $alert->fired_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'N/A',
            'acknowledged_at' => $alert->acknowledged_at ? $alert->acknowledged_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : null,
            'acknowledged_by_user' => $alert->acknowledgedByUser ? $alert->acknowledgedByUser->name : null,
            'resolved_at' => $alert->resolved_at ? $alert->resolved_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : null,
            'resolved_by' => $alert->resolved_by === 'auto' ? 'Auto-Resuelta por el Sistema' : ($alert->resolved_by ?: 'Operador'),
            'duration_formatted' => $alert->duration_formatted,
            'is_correlated_suppressed' => (bool) $alert->is_correlated_suppressed,
            'parent_alert_name' => $alert->parentAlert ? $alert->parentAlert->entity_name : null,
            'correlation_group_name' => $alert->correlationGroup ? $alert->correlationGroup->name : null,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Reconoce una alerta (Operador o Administrador).
     */
    public function acknowledge(Request $request, int $id): RedirectResponse
    {
        $alert = Alert::findOrFail($id);
        $notes = $request->input('notes', 'Incidente reconocido desde consola web.');

        $alert->acknowledge(Auth::id() ?? 1, $notes);

        $user = Auth::user();
        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Operador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'acknowledged',
            'module' => 'alerts',
            'auditable_type' => Alert::class,
            'auditable_id' => $alert->id,
            'entity_name' => 'Alerta',
            'entity_label' => "Alerta #{$id} ({$alert->entity_name})",
            'description' => "Alerta #{$id} ({$alert->entity_name}) reconocida por " . ($user?->name ?? 'Usuario') . ($notes ? ": {$notes}" : ''),
            'new_values' => ['notes' => $notes, 'status' => 'acknowledged'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', "Alerta #{$id} reconocida correctamente.");
    }

    /**
     * Resuelve manualmente una alerta (Administrador exclusivo).
     */
    public function resolve(Request $request, int $id): RedirectResponse
    {
        if (Auth::user()->role !== 'admin') {
            return redirect()->back()->with('error', 'Acción reservada exclusivamente para el Administrador.');
        }

        $alert = Alert::findOrFail($id);
        $notes = $request->input('notes', 'Incidente resuelto manualmente desde la consola web.');

        $alert->resolve('manual', $notes);

        $user = Auth::user();
        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'resolved',
            'module' => 'alerts',
            'auditable_type' => Alert::class,
            'auditable_id' => $alert->id,
            'entity_name' => 'Alerta',
            'entity_label' => "Alerta #{$id} ({$alert->entity_name})",
            'description' => "Alerta #{$id} ({$alert->entity_name}) resuelta manualmente por " . ($user?->name ?? 'Usuario') . ($notes ? ": {$notes}" : ''),
            'new_values' => ['notes' => $notes, 'status' => 'resolved', 'resolved_type' => 'manual'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', "Alerta #{$id} marcada como resuelta.");
    }

    /**
     * Silencia una alerta creando una ventana temporal de mantenimiento (Operador o Administrador).
     */
    public function silence(Request $request, int $id): RedirectResponse
    {
        $alert = Alert::findOrFail($id);
        $minutes = (int) $request->input('minutes', 60);
        if ($minutes <= 0) {
            $minutes = 60;
        }

        $startsAt = Carbon::now();
        $endsAt = Carbon::now()->addMinutes($minutes);

        MaintenanceWindow::create([
            'title' => "Silenciado: {$alert->entity_name}",
            'description' => "Silenciado temporal por {$minutes} min desde la consola web.",
            'entity_type' => $alert->entity_type,
            'entity_id' => $alert->entity_id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by' => Auth::id(),
            'is_active' => true,
        ]);

        $alert->status = 'suppressed';
        $alert->notes = trim(($alert->notes ? $alert->notes . "\n" : '') . "[Silenciado por {$minutes} min hasta " . $endsAt->format('d/m/Y H:i') . "]");
        $alert->save();

        $user = Auth::user();
        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Operador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'silenced',
            'module' => 'alerts',
            'auditable_type' => Alert::class,
            'auditable_id' => $alert->id,
            'entity_name' => 'Alerta',
            'entity_label' => "Alerta #{$id} ({$alert->entity_name})",
            'description' => "Alerta #{$id} ({$alert->entity_name}) silenciada por {$minutes} minutos por " . ($user?->name ?? 'Usuario'),
            'new_values' => ['minutes' => $minutes, 'ends_at' => $endsAt->toIso8601String(), 'status' => 'suppressed'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', "Alerta #{$id} silenciada por {$minutes} minutos.");
    }

    /**
     * Evalúa las reglas de alerta de inmediato en segundo plano.
     */
    public function evaluateNow(): RedirectResponse
    {
        try {
            Process::run('/usr/local/bin/estatus alertas --evaluate --escalate');
            return redirect()->back()->with('success', 'Evaluación de reglas y escalación ejecutadas exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al ejecutar evaluación: ' . $e->getMessage());
        }
    }

    /**
     * Crea o actualiza una regla de alerta (Administrador exclusivo).
     */
    public function storeRule(Request $request): RedirectResponse
    {
        if (Auth::user()->role !== 'admin') {
            return redirect()->back()->with('error', 'Acción no autorizada.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entity_type' => 'required|string',
            'entity_id' => 'nullable|integer',
            'condition_type' => 'required|string',
            'threshold_value' => 'nullable|numeric',
            'comparison' => 'nullable|string|in:gt,lt,eq,gte,lte',
            'duration_seconds' => 'nullable|integer|min:0',
            'severity' => 'required|string|in:info,warning,critical,emergency',
            'cooldown_minutes' => 'nullable|integer|min:1',
            'max_alerts_per_hour' => 'nullable|integer|min:1',
            'auto_resolve' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['auto_resolve'] = $request->has('auto_resolve');
        $validated['is_active'] = $request->has('is_active');

        $rule = AlertRule::create($validated);

        // Crear 3 niveles de escalación por defecto
        AlertEscalationLevel::create([
            'alert_rule_id' => $rule->id,
            'level' => 1,
            'delay_minutes' => 0,
            'channel' => 'telegram',
            'target_type' => 'user',
            'target_id' => Auth::id() ?? 1,
            'target_external' => '38914901',
            'is_active' => true,
        ]);
        AlertEscalationLevel::create([
            'alert_rule_id' => $rule->id,
            'level' => 2,
            'delay_minutes' => 15,
            'channel' => 'telegram',
            'target_type' => 'user',
            'target_id' => Auth::id() ?? 1,
            'target_external' => '38914901',
            'is_active' => true,
        ]);

        $user = Auth::user();
        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'created',
            'module' => 'alerts',
            'auditable_type' => AlertRule::class,
            'auditable_id' => $rule->id,
            'entity_name' => 'Regla de Alerta',
            'entity_label' => $rule->name,
            'description' => "Regla de alerta '{$rule->name}' creada por " . ($user?->name ?? 'Usuario'),
            'new_values' => $rule->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.alerts.index', ['tab' => 'rules'])->with('success', "Regla '{$rule->name}' creada exitosamente.");
    }

    /**
     * Elimina una regla de alerta (Administrador exclusivo).
     */
    public function destroyRule(int $id): RedirectResponse
    {
        if (Auth::user()->role !== 'admin') {
            return redirect()->back()->with('error', 'Acción no autorizada.');
        }

        $rule = AlertRule::findOrFail($id);
        $name = $rule->name;
        $rule->delete();

        $user = Auth::user();
        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'deleted',
            'module' => 'alerts',
            'auditable_type' => AlertRule::class,
            'auditable_id' => $id,
            'entity_name' => 'Regla de Alerta',
            'entity_label' => $name,
            'description' => "Regla de alerta '{$name}' eliminada por " . ($user?->name ?? 'Usuario'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('admin.alerts.index', ['tab' => 'rules'])->with('success', "Regla '{$name}' eliminada.");
    }

    /**
     * Crea una ventana de mantenimiento (Administrador exclusivo).
     */
    public function storeMaintenance(Request $request): RedirectResponse
    {
        if (Auth::user()->role !== 'admin') {
            return redirect()->back()->with('error', 'Acción no autorizada.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entity_type' => 'required|string',
            'entity_id' => 'nullable|integer',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'suppress_severities' => 'nullable|array',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['is_active'] = true;

        $window = MaintenanceWindow::create($validated);

        $user = Auth::user();
        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'created',
            'module' => 'alerts',
            'auditable_type' => MaintenanceWindow::class,
            'auditable_id' => $window->id,
            'entity_name' => 'Ventana de Mantenimiento',
            'entity_label' => $window->title,
            'description' => "Ventana de mantenimiento '{$window->title}' creada por " . ($user?->name ?? 'Usuario'),
            'new_values' => $window->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.alerts.index', ['tab' => 'maintenance'])->with('success', "Ventana de mantenimiento '{$window->title}' programada con éxito.");
    }

    /**
     * Elimina una ventana de mantenimiento (Administrador exclusivo).
     */
    public function destroyMaintenance(int $id): RedirectResponse
    {
        if (Auth::user()->role !== 'admin') {
            return redirect()->back()->with('error', 'Acción no autorizada.');
        }

        $window = MaintenanceWindow::findOrFail($id);
        $title = $window->title;
        $window->delete();

        $user = Auth::user();
        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'deleted',
            'module' => 'alerts',
            'auditable_type' => MaintenanceWindow::class,
            'auditable_id' => $id,
            'entity_name' => 'Ventana de Mantenimiento',
            'entity_label' => $title,
            'description' => "Ventana de mantenimiento '{$title}' eliminada por " . ($user?->name ?? 'Usuario'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('admin.alerts.index', ['tab' => 'maintenance'])->with('success', "Ventana de mantenimiento '{$title}' eliminada.");
    }

    /**
     * Crea un grupo de correlación topológica (Administrador exclusivo).
     */
    public function storeCorrelation(Request $request): RedirectResponse
    {
        if (Auth::user()->role !== 'admin') {
            return redirect()->back()->with('error', 'Acción no autorizada.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_entity_type' => 'required|string|in:site,network_device,proxy,snmp_device',
            'parent_entity_id' => 'required|integer',
            'suppression_strategy' => 'required|string|in:suppress_all,suppress_if_parent_down,reduce_severity',
        ]);

        $validated['is_active'] = true;
        $group = AlertCorrelationGroup::create($validated);

        // Si se enviaron miembros hijos
        $children = $request->input('children', []);
        if (is_array($children)) {
            foreach ($children as $child) {
                if (!empty($child['type']) && !empty($child['id'])) {
                    AlertCorrelationMember::create([
                        'correlation_group_id' => $group->id,
                        'child_entity_type' => $child['type'],
                        'child_entity_id' => (int) $child['id'],
                    ]);
                }
            }
        }

        $user = Auth::user();
        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'created',
            'module' => 'alerts',
            'auditable_type' => AlertCorrelationGroup::class,
            'auditable_id' => $group->id,
            'entity_name' => 'Grupo de Correlación',
            'entity_label' => $group->name,
            'description' => "Grupo de correlación '{$group->name}' creado por " . ($user?->name ?? 'Usuario'),
            'new_values' => $group->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.alerts.index', ['tab' => 'correlation'])->with('success', "Grupo de correlación '{$group->name}' creado con éxito.");
    }

    /**
     * Elimina un grupo de correlación (Administrador exclusivo).
     */
    public function destroyCorrelation(int $id): RedirectResponse
    {
        if (Auth::user()->role !== 'admin') {
            return redirect()->back()->with('error', 'Acción no autorizada.');
        }

        $group = AlertCorrelationGroup::findOrFail($id);
        $name = $group->name;
        $group->delete();

        $user = Auth::user();
        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'deleted',
            'module' => 'alerts',
            'auditable_type' => AlertCorrelationGroup::class,
            'auditable_id' => $id,
            'entity_name' => 'Grupo de Correlación',
            'entity_label' => $name,
            'description' => "Grupo de correlación '{$name}' eliminado por " . ($user?->name ?? 'Usuario'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('admin.alerts.index', ['tab' => 'correlation'])->with('success', "Grupo de correlación '{$name}' eliminado.");
    }
}
