<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PredictiveAnomaly;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminPredictiveController extends Controller
{
    /**
     * Muestra el panel de control de IA Predictiva y Análisis Proactivo de Capacidad.
     */
    public function index(Request $request): View
    {
        $typeFilter = $request->query('type');
        $statusFilter = $request->query('status'); // 'active' or 'acknowledged'
        $entityFilter = $request->query('entity_type');

        $query = PredictiveAnomaly::orderByDesc('detected_at');

        if (!empty($typeFilter)) {
            $query->where('anomaly_type', $typeFilter);
        }

        if ($statusFilter === 'active') {
            $query->where('acknowledged', false);
        } elseif ($statusFilter === 'acknowledged') {
            $query->where('acknowledged', true);
        }

        if (!empty($entityFilter)) {
            $query->where('entity_type', $entityFilter);
        }

        $anomalies = $query->paginate(20)->withQueryString();

        // KPIs HUD
        $totalAnomalies = PredictiveAnomaly::count();
        $activeAnomalies = PredictiveAnomaly::where('acknowledged', false)->count();
        $upwardTrends = PredictiveAnomaly::where('anomaly_type', 'trend_upward')->where('acknowledged', false)->count();
        $criticalOutliers = PredictiveAnomaly::where('anomaly_type', 'outlier')->where('acknowledged', false)->count();

        return view('admin.predictive.index', compact(
            'anomalies',
            'totalAnomalies',
            'activeAnomalies',
            'upwardTrends',
            'criticalOutliers',
            'typeFilter',
            'statusFilter',
            'entityFilter'
        ));
    }

    /**
     * Dispara un ciclo completo de análisis predictivo estadístico con el motor local de IA.
     */
    public function runAnalysis(): RedirectResponse
    {
        $cmd = '/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/predictive_analyzer.py --analyze --json 2>&1';
        $output = shell_exec($cmd);
        $result = json_decode($output, true);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'predictive_analysis_executed',
            'details' => 'Ejecución manual del motor local de IA y análisis predictivo de telemetría.',
            'ip_address' => request()->ip(),
        ]);

        $count = is_array($result) ? count($result) : 0;

        return redirect()->route('admin.predictive.index')
            ->with('status', "Ciclo predictivo completado con éxito por el motor local de IA. {$count} anomalías o tendencias evaluadas.");
    }

    /**
     * Marca una anomalía como atendida / reconocida.
     */
    public function acknowledge(int $id): RedirectResponse
    {
        $anomaly = PredictiveAnomaly::findOrFail($id);
        $anomaly->update(['acknowledged' => true]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'predictive_anomaly_acknowledged',
            'details' => "Anomalía predictiva #{$anomaly->id} marcada como atendida ({$anomaly->anomaly_type})",
            'ip_address' => request()->ip(),
        ]);

        return redirect()->route('admin.predictive.index')
            ->with('status', "Anomalía predictiva #{$anomaly->id} marcada como atendida.");
    }

    /**
     * Elimina el registro de una anomalía.
     */
    public function destroy(int $id): RedirectResponse
    {
        $anomaly = PredictiveAnomaly::findOrFail($id);
        $anomalyId = $anomaly->id;
        $anomaly->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'predictive_anomaly_deleted',
            'details' => "Anomalía predictiva #{$anomalyId} eliminada",
            'ip_address' => request()->ip(),
        ]);

        return redirect()->route('admin.predictive.index')
            ->with('status', "Registro de anomalía #{$anomalyId} descartado.");
    }
}
