<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Contracts\Repositories\InventoryAssetRepositoryInterface;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

readonly class UploadAssignmentScanAction
{
    public function __construct(
        private InventoryAssetRepositoryInterface $repository
    ) {}

    public function execute(int $id, Request $request): InventoryAssignment
    {
        $asset = $this->repository->findById($id);
        if (!$asset) {
            abort(404, 'Activo no encontrado');
        }

        $validated = $request->validate([
            'custodian_name' => 'required|string|max:128',
            'custodian_id_number' => 'nullable|string|max:32',
            'custodian_department' => 'nullable|string|max:128',
            'assigned_at' => 'required|date',
            'control_number' => 'nullable|string|max:64',
            'observations' => 'nullable|string',
            'scan_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:20480',
        ], [
            'custodian_name.required' => 'El nombre del funcionario asignado es obligatorio.',
            'assigned_at.required' => 'Debe indicar la fecha en que se firmó el documento físico.',
            'scan_file.required' => 'Debe adjuntar el archivo digitalizado (PDF o Imagen).',
            'scan_file.mimes' => 'El archivo debe ser un documento PDF o una imagen (JPG, PNG).',
            'scan_file.max' => 'El archivo no puede exceder los 20 MB.',
        ]);

        $file = $request->file('scan_file');
        $extension = $file->getClientOriginalExtension();
        $safeName = Str::slug($asset->display_identity ?: $asset->name);
        $fileName = "acta_fisica_{$safeName}_{$asset->id}_" . time() . ".{$extension}";
        $path = $file->storeAs('assignments/scans', $fileName, 'public');

        $year = Carbon::parse($validated['assigned_at'])->format('Y');
        $code = !empty($validated['control_number'])
            ? trim($validated['control_number'])
            : sprintf("UINF-4.05-%s-%04d", $year, InventoryAssignment::whereYear('created_at', $year)->count() + 1);

        $existing = InventoryAssignment::where('inventory_asset_id', $asset->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            $existing->update([
                'custodian_name' => $validated['custodian_name'],
                'custodian_id_number' => $validated['custodian_id_number'] ?: $existing->custodian_id_number,
                'custodian_department' => $validated['custodian_department'] ?: $existing->custodian_department,
                'assigned_at' => Carbon::parse($validated['assigned_at']),
                'control_number' => $validated['control_number'] ?: $existing->control_number,
                'document_pdf_path' => $path,
                'observations' => $validated['observations'] ?: $existing->observations,
            ]);
            $assignment = $existing;
        } else {
            $assignment = InventoryAssignment::create([
                'assignment_code' => $code,
                'inventory_asset_id' => $asset->id,
                'user_id' => Auth::id(),
                'custodian_name' => $validated['custodian_name'],
                'custodian_id_number' => $validated['custodian_id_number'],
                'custodian_department' => $validated['custodian_department'],
                'monitored_site_id' => $asset->monitored_site_id,
                'movement_type' => 'assignment',
                'provenance' => 'fisico_existente',
                'control_number' => $validated['control_number'],
                'assigned_at' => Carbon::parse($validated['assigned_at']),
                'document_pdf_path' => $path,
                'status' => 'active',
                'observations' => $validated['observations'] ?: 'Hoja de asignación física digitalizada e incorporada al sistema',
            ]);
        }

        $asset->update([
            'status' => 'assigned',
            'custodian_name' => $validated['custodian_name'],
            'custodian_id_number' => $validated['custodian_id_number'],
            'custodian_department' => $validated['custodian_department'],
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'uploaded_physical_assignment',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $asset->id,
            'entity_name' => $asset->display_identity,
            'entity_label' => $code,
            'description' => "Se cargó hoja física digitalizada de asignación para '{$asset->display_identity}' (Asignado a: {$validated['custodian_name']}, Archivo: {$fileName})",
            'ip_address' => $request->ip(),
        ]);

        return $assignment;
    }
}
