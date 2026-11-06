<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SnmpDevice;
use App\Models\SnmpTrapReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminTrapController extends Controller
{
    /**
     * Muestra el visor de SNMP Traps recibidos en tiempo real.
     */
    public function index(Request $request): View
    {
        $severityFilter = $request->query('severity');
        $processedFilter = $request->query('processed');
        $deviceFilter = $request->query('device_id');
        $search = $request->query('search');

        $query = SnmpTrapReceived::with('device')->orderByDesc('received_at');

        if (!empty($severityFilter)) {
            $query->where('severity', $severityFilter);
        }

        if ($processedFilter !== null && $processedFilter !== '') {
            $query->where('processed', (bool) $processedFilter);
        }

        if (!empty($deviceFilter)) {
            $query->where('snmp_device_id', $deviceFilter);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('source_ip', 'like', "%{$search}%")
                  ->orWhere('trap_type', 'like', "%{$search}%")
                  ->orWhere('trap_oid', 'like', "%{$search}%")
                  ->orWhere('varbinds', 'like', "%{$search}%");
            });
        }

        // Métricas HUD
        $totalTraps = SnmpTrapReceived::count();
        $criticalTraps = SnmpTrapReceived::whereIn('severity', ['critical', 'emergency'])->count();
        $warningTraps = SnmpTrapReceived::where('severity', 'warning')->count();
        $unprocessedTraps = SnmpTrapReceived::where('processed', false)->count();

        $traps = $query->paginate(20)->withQueryString();
        $devices = SnmpDevice::where('is_active', true)->orderBy('name')->get();

        return view('admin.traps.index', compact(
            'traps',
            'devices',
            'totalTraps',
            'criticalTraps',
            'warningTraps',
            'unprocessedTraps',
            'severityFilter',
            'processedFilter',
            'deviceFilter',
            'search'
        ));
    }

    /**
     * Retorna detalles en formato JSON de un trap para visualización en modal.
     */
    public function show(int $id): JsonResponse
    {
        $trap = SnmpTrapReceived::with('device')->findOrFail($id);
        return response()->json([
            'success' => true,
            'trap' => $trap,
        ]);
    }

    /**
     * Marca un trap como procesado / revisado.
     */
    public function markProcessed(int $id): RedirectResponse
    {
        $trap = SnmpTrapReceived::findOrFail($id);
        $trap->update(['processed' => true]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'resolved',
            'module' => 'traps',
            'auditable_type' => SnmpTrapReceived::class,
            'auditable_id' => $trap->id,
            'entity_name' => "Alerta SNMP #{$trap->id}",
            'entity_label' => $trap->source_ip,
            'description' => "Alerta SNMP #{$trap->id} de {$trap->source_ip} ({$trap->trap_type}) marcada como atendida/procesada.",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('status', "Alerta SNMP #{$trap->id} marcada como procesada.");
    }
}
