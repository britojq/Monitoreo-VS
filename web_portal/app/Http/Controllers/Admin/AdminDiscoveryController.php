<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Discovery\AuthorizeDiscoveredDeviceAction;
use App\Actions\Discovery\FetchDiscoveredDevicesAction;
use App\Actions\Discovery\StoreSubnetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\StoreSubnetRequest;
use App\Services\Cluster\ClusterConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDiscoveryController extends Controller
{
    public function index(Request $request, FetchDiscoveredDevicesAction $action): View
    {
        return view('admin.discovery.index', $action->execute($request));
    }

    public function authorizeDevice(Request $request, int $id, AuthorizeDiscoveredDeviceAction $action): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Servidor en modo ESCLAVO. Gestione en MASTER ({$cluster->getMasterApiUrl()}).");
        }
        $device = $action->authorize($id, $request->user(), (string) $request->input('device_type', 'workstation'), $request->input('notes'));
        return back()->with('success', "Dispositivo {$device->ip_address} aprobado exitosamente.");
    }

    public function markRogue(Request $request, int $id, AuthorizeDiscoveredDeviceAction $action): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Servidor en modo ESCLAVO.");
        }
        $device = $action->markRogue($id, $request->user(), (string) $request->input('reason', 'No reconocido'));
        return back()->with('warning', "Dispositivo {$device->ip_address} marcado como INTRUSO (Rogue).");
    }

    public function update(Request $request, int $id, AuthorizeDiscoveredDeviceAction $action): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Servidor en modo ESCLAVO.");
        }
        $validated = $request->validate([
            'device_type' => ['required', 'string', 'in:router,switch,firewall,server,workstation,printer,ap,camera,ups,phone,unknown'],
            'classification_status' => ['required', 'string', 'in:pendiente,clasificado,ignorado,rogue,byod'],
            'is_authorized' => ['nullable', 'boolean'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'site_id' => ['nullable', 'exists:monitored_sites,id'],
            'notes' => ['nullable', 'string'],
        ]);
        $device = $action->update($id, $validated, $request->user(), $request->has('is_authorized'));
        return back()->with('success', "Dispositivo {$device->ip_address} actualizado correctamente.");
    }

    public function scanNow(Request $request, AuthorizeDiscoveredDeviceAction $action): RedirectResponse
    {
        $subnet = $request->input('subnet');
        $action->scanNow($subnet ? (string) $subnet : null);
        $target = $subnet ? "subred {$subnet}" : "todas las subredes";
        return back()->with('success', "Escaneo de descubrimiento iniciado en segundo plano para {$target}.");
    }

    public function storeSubnet(StoreSubnetRequest $request, StoreSubnetAction $action): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Servidor en modo ESCLAVO.");
        }
        $subnet = $action->execute($request->validated());
        return back()->with('success', "Subred {$subnet->subnet} registrada para escaneo automático.");
    }

    public function history(int $id, FetchDiscoveredDevicesAction $action): JsonResponse
    {
        return response()->json($action->getHistory($id));
    }
}
