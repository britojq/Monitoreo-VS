<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\ApproveInventoryAssetAction;
use App\Actions\Inventory\AssociatePeripheralAction;
use App\Actions\Inventory\BulkCompleteMaintenanceAction;
use App\Actions\Inventory\BulkMaintenanceAction;
use App\Actions\Inventory\BulkMobilizeAction;
use App\Actions\Inventory\CreateInventoryAssetAction;
use App\Actions\Inventory\DeleteInventoryAssetAction;
use App\Actions\Inventory\DisassociatePeripheralAction;
use App\Actions\Inventory\GetInventoryIndexViewModelAction;
use App\Actions\Inventory\GetInventoryShowViewModelAction;
use App\Actions\Inventory\UpdateInventoryAssetAction;
use App\Actions\Inventory\UploadAssignmentScanAction;
use App\Contracts\Repositories\InventoryAssetRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryAssetRequest;
use App\Http\Requests\Inventory\UpdateInventoryAssetRequest;
use App\Models\MonitoredSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryAssetController extends Controller
{
    /**
     * Catálogo principal de Activos de TI con métricas, filtros y buscador en tiempo real.
     */
    public function index(Request $request, GetInventoryIndexViewModelAction $action): View
    {
        $viewModel = $action->execute($request);
        return view('admin.inventory.assets.index', $viewModel);
    }

    /**
     * Ficha Técnica Desplegable del Activo de TI.
     */
    public function show(int $id, GetInventoryShowViewModelAction $action): View
    {
        $viewModel = $action->execute($id);
        return view('admin.inventory.assets.show', $viewModel);
    }

    /**
     * Formulario de alta manual de activo.
     */
    public function create(): View
    {
        $sites = MonitoredSite::where('is_active', true)->orderBy('name')->get();
        return view('admin.inventory.assets.create', compact('sites'));
    }

    /**
     * Guardar activo creado manualmente.
     */
    public function store(StoreInventoryAssetRequest $request, CreateInventoryAssetAction $action): RedirectResponse
    {
        $asset = $action->execute($request->toDto(), $request->all());

        return redirect()->route('admin.inventory.assets.index')
            ->with('success', "Activo '{$asset->display_identity}' registrado exitosamente en el inventario.");
    }

    /**
     * Formulario de edición de metadatos del activo.
     */
    public function edit(int $id, InventoryAssetRepositoryInterface $repository): View
    {
        $asset = $repository->findById($id);
        if (!$asset) {
            abort(404, 'Activo no encontrado');
        }

        $sites = MonitoredSite::orderBy('name')->get();
        return view('admin.inventory.assets.edit', compact('asset', 'sites'));
    }

    /**
     * Actualizar metadatos de un activo.
     */
    public function update(UpdateInventoryAssetRequest $request, int $id, UpdateInventoryAssetAction $action): RedirectResponse
    {
        $asset = $action->execute($id, $request->toDto(), $request->all());

        return redirect()->route('admin.inventory.assets.index')
            ->with('success', "Activo '{$asset->display_identity}' actualizado exitosamente.");
    }

    /**
     * Aprobar e incorporar al inventario formal un equipo detectado.
     */
    public function approve(Request $request, int $id, ApproveInventoryAssetAction $action): RedirectResponse
    {
        $asset = $action->execute($id, $request);

        return redirect()->back()
            ->with('success', "Equipo '{$asset->name}' aprobado e incorporado formalmente al inventario.");
    }

    /**
     * Desincorporar o dar de baja un activo.
     */
    public function destroy(Request $request, int $id, DeleteInventoryAssetAction $action): RedirectResponse
    {
        $name = $action->execute($id, $request);

        return redirect()->route('admin.inventory.assets.index')
            ->with('success', "Activo '{$name}' eliminado del catálogo.");
    }

    /**
     * Asociar o reemplazar un periférico de puesto.
     */
    public function associatePeripheral(Request $request, int $id, AssociatePeripheralAction $action): RedirectResponse
    {
        $action->execute($id, $request);

        return redirect()->back()->with('success', "Periférico guardado exitosamente.");
    }

    /**
     * Desvincular periférico del activo.
     */
    public function disassociatePeripheral(Request $request, int $id, int $peripheralId, DisassociatePeripheralAction $action): RedirectResponse
    {
        $action->execute($id, $peripheralId, $request);

        return redirect()->back()->with('success', "Periférico desvinculado correctamente.");
    }

    /**
     * Programar mantenimiento masivo para múltiples activos seleccionados.
     */
    public function bulkMaintenance(Request $request, BulkMaintenanceAction $action): RedirectResponse
    {
        $this->normalizeBulkAssetIds($request);
        $count = $action->execute($request);

        return redirect()->back()->with('success', "Se programó exitosamente el mantenimiento para {$count} equipo(s).");
    }

    /**
     * Finalizar mantenimiento masivo para múltiples activos seleccionados.
     */
    public function bulkCompleteMaintenance(Request $request, BulkCompleteMaintenanceAction $action): RedirectResponse
    {
        $this->normalizeBulkAssetIds($request);
        $count = $action->execute($request);

        return redirect()->back()->with('success', "Se finalizó exitosamente el mantenimiento para {$count} equipo(s). Estado operativo restaurado.");
    }

    /**
     * Procesar movilización masiva de múltiples activos seleccionados y emitir Pase de Movilización oficial.
     */
    public function bulkMobilize(Request $request, BulkMobilizeAction $action): RedirectResponse|JsonResponse
    {
        $this->normalizeBulkAssetIds($request);
        $assignment = $action->execute($request);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Movilización registrada exitosamente bajo el comprobante {$assignment->assignment_code}.",
                'code' => $assignment->assignment_code,
                'pdf_url' => route('admin.inventory.assignments.pdf-movilizacion', $assignment->id),
                'assignment_id' => $assignment->id,
            ]);
        }

        return redirect()->back()
            ->with('success', "Movilización registrada exitosamente bajo el comprobante {$assignment->assignment_code}.")
            ->with('bulk_mobilization_pdf', route('admin.inventory.assignments.pdf-movilizacion', $assignment->id))
            ->with('bulk_mobilization_code', $assignment->assignment_code);
    }

    /**
     * Subir y vincular hoja de asignación física digitalizada.
     */
    public function uploadAssignmentScan(Request $request, int $id, UploadAssignmentScanAction $action): RedirectResponse
    {
        $assignment = $action->execute($id, $request);

        return redirect()->back()->with('success', "Hoja de asignación física digitalizada exitosamente. Activo marcado como ASIGNADO.");
    }

    private function normalizeBulkAssetIds(Request $request): void
    {
        if (is_string($request->input('asset_ids'))) {
            $request->merge(['asset_ids' => array_filter(explode(',', $request->input('asset_ids')))]);
        } elseif ($request->filled('selected_asset_ids') && !$request->has('asset_ids')) {
            $raw = is_array($request->input('selected_asset_ids')) ? $request->input('selected_asset_ids') : explode(',', $request->input('selected_asset_ids'));
            $request->merge(['asset_ids' => array_filter($raw)]);
        }
        if ($request->filled('technician_user_id') && !$request->has('technician_id')) {
            $request->merge(['technician_id' => $request->input('technician_user_id')]);
        }
    }
}
