<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryPeripheral;
use App\Models\MonitoredSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminPeripheralController extends Controller
{
    /**
     * Catálogo unificado de periféricos, repuestos y stock de hardware (ITAM).
     */
    public function index(Request $request): View
    {
        $siteFilter = $request->query('site_id');
        $typeFilter = $request->query('type');
        $statusFilter = $request->query('status');
        $search = trim((string)$request->query('search', ''));

        $query = InventoryPeripheral::with(['site', 'currentAsset', 'currentUser']);

        if (!empty($siteFilter)) {
            $query->where('monitored_site_id', $siteFilter);
        }

        if (!empty($typeFilter)) {
            $query->where('type', $typeFilter);
        }

        if (!empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                  ->orWhere('asset_tag', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('currentAsset', function ($aq) use ($search) {
                      $aq->where('name', 'like', "%{$search}%")
                         ->orWhere('asset_tag', 'like', "%{$search}%");
                  });
            });
        }

        $peripherals = $query->orderBy('id', 'desc')->paginate(25)->withQueryString();

        // KPIs de Periféricos & Stock
        $totalCount = InventoryPeripheral::count();
        $inStockCount = InventoryPeripheral::where('status', 'in_stock')->count();
        $assignedCount = InventoryPeripheral::where('status', 'assigned')->count();
        $damagedCount = InventoryPeripheral::where('status', 'damaged')->count();
        $maintenanceCount = InventoryPeripheral::where('status', 'in_maintenance')->count();

        $sites = MonitoredSite::orderBy('name')->get();

        // Puestos de trabajo anfitriones disponibles para vincular (PCs, Laptops, Servidores)
        $workstations = InventoryAsset::whereIn('type', ['desktop', 'laptop', 'all_in_one', 'workstation', 'server'])
            ->orderBy('name')
            ->get(['id', 'name', 'asset_tag', 'monitored_site_id']);

        return view('admin.inventory.peripherals.index', compact(
            'peripherals',
            'totalCount',
            'inStockCount',
            'assignedCount',
            'damagedCount',
            'maintenanceCount',
            'sites',
            'workstations',
            'siteFilter',
            'typeFilter',
            'statusFilter',
            'search'
        ));
    }

    /**
     * Registrar un nuevo periférico independiente o en stock.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:monitor,keyboard,mouse,surge_protector,ups,printer,scanner,headset,other',
            'brand' => 'nullable|string|max:64',
            'model' => 'nullable|string|max:96',
            'serial_number' => 'nullable|string|max:128',
            'status' => 'required|in:in_stock,assigned,in_maintenance,damaged,decommissioned',
            'monitored_site_id' => 'required|exists:monitored_sites,id',
            'current_asset_id' => 'nullable|exists:inventory_assets,id',
            'notes' => 'nullable|string',
        ]);

        $prefix = match($validated['type']) {
            'monitor' => 'MON',
            'keyboard' => 'KBD',
            'mouse' => 'MSE',
            'surge_protector', 'ups' => 'PWR',
            'printer' => 'PRN',
            'scanner' => 'SCN',
            'headset' => 'HDS',
            default => 'PER',
        };

        $assetTag = $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

        // Si se vincula a un puesto de trabajo y su condición no es dañada, asegurar estado 'assigned'
        $status = $validated['status'];
        $currentAssetId = !empty($validated['current_asset_id']) ? $validated['current_asset_id'] : null;
        if (!empty($currentAssetId) && $status === 'in_stock') {
            $status = 'assigned';
        }

        $serial = !empty($validated['serial_number']) ? trim($validated['serial_number']) : 'Sin Serial';

        $peripheral = InventoryPeripheral::create([
            'asset_tag' => $assetTag,
            'type' => $validated['type'],
            'brand' => $validated['brand'] ?: 'Genérico',
            'model' => $validated['model'] ?: 'Estándar',
            'serial_number' => $serial,
            'status' => $status,
            'monitored_site_id' => $validated['monitored_site_id'],
            'current_asset_id' => $currentAssetId,
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'created',
            'module' => 'inventory',
            'auditable_type' => InventoryPeripheral::class,
            'auditable_id' => $peripheral->id,
            'entity_name' => $peripheral->type_label,
            'entity_label' => $peripheral->asset_tag,
            'description' => "Periférico registrado: {$peripheral->type_label} [{$peripheral->brand} {$peripheral->model}] en estado '{$peripheral->status}'. Serial: {$peripheral->serial_number}",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.inventory.peripherals.index')
            ->with('success', "Periférico [{$peripheral->type_label}] registrado exitosamente en el inventario.");
    }

    /**
     * Actualizar datos o estado de un periférico existente.
     */
    public function update(Request $request, InventoryPeripheral $peripheral): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:monitor,keyboard,mouse,surge_protector,ups,printer,scanner,headset,other',
            'brand' => 'nullable|string|max:64',
            'model' => 'nullable|string|max:96',
            'serial_number' => 'nullable|string|max:128',
            'status' => 'required|in:in_stock,assigned,in_maintenance,damaged,decommissioned',
            'monitored_site_id' => 'required|exists:monitored_sites,id',
            'current_asset_id' => 'nullable|exists:inventory_assets,id',
            'notes' => 'nullable|string',
        ]);

        $status = $validated['status'];
        $currentAssetId = !empty($validated['current_asset_id']) ? $validated['current_asset_id'] : null;

        // Si se marca como en stock o dañado y no se seleccionó puesto, limpiar vínculo
        if (in_array($status, ['in_stock', 'damaged', 'decommissioned']) && empty($currentAssetId)) {
            $currentAssetId = null;
        } elseif (!empty($currentAssetId) && $status === 'in_stock') {
            $status = 'assigned';
        }

        $serial = !empty($validated['serial_number']) ? trim($validated['serial_number']) : 'Sin Serial';

        $oldStatus = $peripheral->status;

        $peripheral->update([
            'type' => $validated['type'],
            'brand' => $validated['brand'] ?: 'Genérico',
            'model' => $validated['model'] ?: 'Estándar',
            'serial_number' => $serial,
            'status' => $status,
            'monitored_site_id' => $validated['monitored_site_id'],
            'current_asset_id' => $currentAssetId,
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'updated',
            'module' => 'inventory',
            'auditable_type' => InventoryPeripheral::class,
            'auditable_id' => $peripheral->id,
            'entity_name' => $peripheral->type_label,
            'entity_label' => $peripheral->asset_tag,
            'description' => "Periférico actualizado: {$peripheral->type_label} [{$peripheral->asset_tag}]. Estado: {$oldStatus} -> {$status}. Serial: {$peripheral->serial_number}",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()
            ->with('success', "Periférico [{$peripheral->asset_tag}] actualizado exitosamente.");
    }

    /**
     * Desvincular periférico de su puesto de trabajo y regresarlo a Stock o marcarlo Dañado.
     */
    public function unlink(Request $request, InventoryPeripheral $peripheral): RedirectResponse
    {
        $newStatus = $request->input('status', 'in_stock');
        if (!in_array($newStatus, ['in_stock', 'damaged', 'in_maintenance'])) {
            $newStatus = 'in_stock';
        }

        $oldAsset = $peripheral->currentAsset?->name ?? 'Puesto';

        $peripheral->update([
            'current_asset_id' => null,
            'status' => $newStatus,
            'notes' => $request->input('notes') ?? $peripheral->notes,
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'updated',
            'module' => 'inventory',
            'auditable_type' => InventoryPeripheral::class,
            'auditable_id' => $peripheral->id,
            'entity_name' => $peripheral->type_label,
            'entity_label' => $peripheral->asset_tag,
            'description' => "Periférico desvinculado de '{$oldAsset}'. Estado establecido a: {$newStatus}",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()
            ->with('success', "Periférico desvinculado exitosamente y movido a " . ($newStatus === 'damaged' ? 'Dañado' : 'Stock') . ".");
    }

    /**
     * Eliminar un periférico del inventario.
     */
    public function destroy(Request $request, InventoryPeripheral $peripheral): RedirectResponse
    {
        $tag = $peripheral->asset_tag;
        $label = $peripheral->type_label;

        $peripheral->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'deleted',
            'module' => 'inventory',
            'auditable_type' => InventoryPeripheral::class,
            'auditable_id' => $peripheral->id,
            'entity_name' => $label,
            'entity_label' => $tag,
            'description' => "Periférico eliminado del catálogo: {$label} [{$tag}]",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()
            ->with('success', "Periférico [{$tag}] eliminado del inventario.");
    }
}
