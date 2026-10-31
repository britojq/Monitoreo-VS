<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Alert\FetchAlertDashboardAction;
use App\Actions\Alert\ManageAlertLifecycleAction;
use App\Actions\Alert\StoreAlertRuleAction;
use App\Actions\Alert\StoreCorrelationRuleAction;
use App\Actions\Alert\StoreMaintenanceWindowAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Alert\StoreAlertRuleRequest;
use App\Http\Requests\Alert\StoreCorrelationRequest;
use App\Http\Requests\Alert\StoreMaintenanceRequest;
use App\Models\AlertCorrelationGroup;
use App\Models\AlertRule;
use App\Models\AuditLog;
use App\Models\MaintenanceWindow;
use App\Services\Cluster\ClusterConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAlertController extends Controller
{
    public function index(Request $request, FetchAlertDashboardAction $action): View
    {
        return view('admin.alerts.index', $action->execute($request));
    }

    public function show(int $id, ManageAlertLifecycleAction $action): JsonResponse
    {
        return response()->json($action->getDetails($id));
    }

    public function acknowledge(Request $request, int $id, ManageAlertLifecycleAction $action): RedirectResponse
    {
        $action->acknowledge($id, $request->user(), (string) $request->input('notes', 'Reconocido'), $request->ip(), $request->userAgent());
        return redirect()->back()->with('success', "Alerta #{$id} reconocida correctamente.");
    }

    public function resolve(Request $request, int $id, ManageAlertLifecycleAction $action): RedirectResponse
    {
        if ($request->user()?->role !== 'admin') {
            return redirect()->back()->with('error', 'Acción reservada exclusivamente para el Administrador.');
        }
        $action->resolve($id, $request->user(), (string) $request->input('notes', 'Resuelto manualmente'), $request->ip(), $request->userAgent());
        return redirect()->back()->with('success', "Alerta #{$id} marcada como resuelta.");
    }

    public function silence(Request $request, int $id, ManageAlertLifecycleAction $action): RedirectResponse
    {
        $minutes = (int) $request->input('minutes', 60);
        $action->silence($id, $request->user(), $minutes, $request->ip(), $request->userAgent());
        return redirect()->back()->with('success', "Alerta #{$id} silenciada por {$minutes} minutos.");
    }

    public function evaluateNow(ManageAlertLifecycleAction $action): RedirectResponse
    {
        try {
            $action->evaluateNow();
            return redirect()->back()->with('success', 'Evaluación de reglas y escalación ejecutadas exitosamente.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Error al ejecutar evaluación: ' . $e->getMessage());
        }
    }

    public function storeRule(StoreAlertRuleRequest $request, StoreAlertRuleAction $action): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Servidor en modo ESCLAVO. Gestione reglas en MASTER ({$cluster->getMasterApiUrl()}).");
        }
        $data = $request->validated();
        $data['auto_resolve'] = $request->has('auto_resolve');
        $data['is_active'] = $request->has('is_active');
        $rule = $action->execute($data, $request->user(), $request->ip(), $request->userAgent());
        return redirect()->route('admin.alerts.index', ['tab' => 'rules'])->with('success', "Regla '{$rule->name}' creada exitosamente.");
    }

    public function destroyRule(int $id): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Servidor en modo ESCLAVO.");
        }
        $rule = AlertRule::findOrFail($id);
        $name = $rule->name;
        $rule->delete();
        AuditLog::log('deleted', 'alerts', "Regla '{$name}' eliminada", AlertRule::class, $id, 'Regla de Alerta', $name);
        return redirect()->route('admin.alerts.index', ['tab' => 'rules'])->with('success', "Regla '{$name}' eliminada.");
    }

    public function storeMaintenance(StoreMaintenanceRequest $request, StoreMaintenanceWindowAction $action): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Servidor en modo ESCLAVO.");
        }
        $window = $action->execute($request->validated(), $request->user(), $request->ip(), $request->userAgent());
        return redirect()->route('admin.alerts.index', ['tab' => 'maintenance'])->with('success', "Ventana '{$window->title}' programada.");
    }

    public function destroyMaintenance(int $id): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Servidor en modo ESCLAVO.");
        }
        $window = MaintenanceWindow::findOrFail($id);
        $title = $window->title;
        $window->delete();
        AuditLog::log('deleted', 'alerts', "Ventana '{$title}' eliminada", MaintenanceWindow::class, $id, 'Ventana de Mantenimiento', $title);
        return redirect()->route('admin.alerts.index', ['tab' => 'maintenance'])->with('success', "Ventana '{$title}' eliminada.");
    }

    public function storeCorrelation(StoreCorrelationRequest $request, StoreCorrelationRuleAction $action): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Servidor en modo ESCLAVO.");
        }
        $group = $action->execute($request->validated(), $request->user(), $request->ip(), $request->userAgent(), (array) $request->input('children', []));
        return redirect()->route('admin.alerts.index', ['tab' => 'correlation'])->with('success', "Grupo '{$group->name}' creado con éxito.");
    }

    public function destroyCorrelation(int $id): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Servidor en modo ESCLAVO.");
        }
        $group = AlertCorrelationGroup::findOrFail($id);
        $name = $group->name;
        $group->delete();
        AuditLog::log('deleted', 'alerts', "Grupo '{$name}' eliminado", AlertCorrelationGroup::class, $id, 'Grupo de Correlación', $name);
        return redirect()->route('admin.alerts.index', ['tab' => 'correlation'])->with('success', "Grupo '{$name}' eliminado.");
    }
}
