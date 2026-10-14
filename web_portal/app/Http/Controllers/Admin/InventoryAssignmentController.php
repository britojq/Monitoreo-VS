<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryPeripheral;
use App\Models\MonitoredSite;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use App\Contracts\ActaVerificationInterface;
use App\Contracts\QrCodeGeneratorInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InventoryAssignmentController extends Controller
{
    public function __construct(
        protected ?QrCodeGeneratorInterface $qrService = null,
        protected ?ActaVerificationInterface $verificationService = null
    ) {
        $this->qrService = $qrService ?? app(QrCodeGeneratorInterface::class);
        $this->verificationService = $verificationService ?? app(ActaVerificationInterface::class);
    }

    /**
     * Listado general de Asignaciones, Devoluciones y Pases de Movilización.
     */
    public function index(Request $request): View
    {
        $movementFilter = $request->query('movement_type');
        $siteFilter = $request->query('site_id');
        $slaFilter = $request->query('sla_status');
        $search = trim((string)$request->query('search', ''));

        $query = InventoryAssignment::with(['asset', 'user', 'site', 'deliveredByUser'])
            ->orderBy('id', 'desc');

        if (!empty($movementFilter)) {
            $query->where('movement_type', $movementFilter);
        }

        if (!empty($siteFilter)) {
            $query->where('monitored_site_id', $siteFilter);
        }

        if (!empty($slaFilter)) {
            if ($slaFilter === 'overdue') {
                $query->where('status', 'active')
                      ->where('movement_type', 'assignment')
                      ->where(fn($q) => $q->whereNull('document_pdf_path')->orWhere('document_pdf_path', ''))
                      ->where('assigned_at', '<', now()->subDays(15));
            } elseif ($slaFilter === 'pending') {
                $query->where('status', 'active')
                      ->where('movement_type', 'assignment')
                      ->where(fn($q) => $q->whereNull('document_pdf_path')->orWhere('document_pdf_path', ''))
                      ->where('assigned_at', '>=', now()->subDays(15));
            } elseif ($slaFilter === 'validated') {
                $query->whereNotNull('document_pdf_path')->where('document_pdf_path', '!=', '');
            }
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('assignment_code', 'like', "%{$search}%")
                  ->orWhere('custodian_name', 'like', "%{$search}%")
                  ->orWhere('custodian_id_number', 'like', "%{$search}%")
                  ->orWhere('custodian_department', 'like', "%{$search}%")
                  ->orWhere('control_number', 'like', "%{$search}%")
                  ->orWhereHas('asset', function ($qa) use ($search) {
                      $qa->where('name', 'like', "%{$search}%")
                         ->orWhere('serial_number', 'like', "%{$search}%")
                         ->orWhere('asset_tag', 'like', "%{$search}%");
                  });
            });
        }

        $assignments = $query->paginate(15)->withQueryString();

        // Métricas de mesa de control
        $stats = [
            'total_active' => InventoryAssignment::where('status', 'active')->count(),
            'total_assignments' => InventoryAssignment::where('movement_type', 'assignment')->count(),
            'total_returns' => InventoryAssignment::where('movement_type', 'return')->count(),
            'total_transfers' => InventoryAssignment::where('movement_type', 'transfer')->count(),
            'scan_validated' => InventoryAssignment::whereNotNull('document_pdf_path')->where('document_pdf_path', '!=', '')->count(),
            'scan_overdue' => InventoryAssignment::where('status', 'active')->where('movement_type', 'assignment')->where(fn($q) => $q->whereNull('document_pdf_path')->orWhere('document_pdf_path', ''))->where('assigned_at', '<', now()->subDays(15))->count(),
            'scan_pending' => InventoryAssignment::where('status', 'active')->where('movement_type', 'assignment')->where(fn($q) => $q->whereNull('document_pdf_path')->orWhere('document_pdf_path', ''))->where('assigned_at', '>=', now()->subDays(15))->count(),
        ];

        $sites = MonitoredSite::orderBy('name')->get();

        return view('admin.inventory.assignments.index', compact('assignments', 'stats', 'sites', 'movementFilter', 'siteFilter', 'slaFilter', 'search'));
    }

    /**
     * Formulario interactivo para registrar nueva Asignación, Devolución o Movilización.
     */
    public function create(Request $request): View
    {
        $preselectedAssetId = $request->query('asset_id');
        $preselectedAsset = null;
        if ($preselectedAssetId) {
            $preselectedAsset = InventoryAsset::with(['components', 'peripherals'])->find($preselectedAssetId);
        }

        $bulkAssetIds = array_filter(explode(',', (string)$request->query('asset_ids', '')));
        $bulkAssets = collect();
        if (!empty($bulkAssetIds)) {
            $bulkAssets = InventoryAsset::with(['components', 'peripherals'])->whereIn('id', $bulkAssetIds)->get();
            if (!$preselectedAsset && $bulkAssets->isNotEmpty()) {
                $preselectedAsset = $bulkAssets->first();
            }
        }

        $assets = InventoryAsset::with(['components', 'peripherals'])
            ->whereIn('status', ['in_stock', 'active', 'assigned'])
            ->orderBy('name')
            ->get();

        if ($preselectedAsset && !$assets->contains('id', $preselectedAsset->id)) {
            $assets->prepend($preselectedAsset);
        }

        foreach ($bulkAssets as $bAsset) {
            if (!$assets->contains('id', $bAsset->id)) {
                $assets->push($bAsset);
            }
        }

        $sites = MonitoredSite::orderBy('name')->get();
        $users = User::orderBy('name')->get(['id', 'name', 'email', 'cedula', 'phone']);
        $availablePeripherals = InventoryPeripheral::whereIn('status', ['in_stock', 'active'])->get();

        $returnTo = $request->query('return_to');
        if (!$returnTo) {
            $prev = url()->previous();
            if ($prev && $prev !== url()->current() && !str_contains($prev, '/assignments/create')) {
                $returnTo = $prev;
            } else {
                $returnTo = route('admin.inventory.assets.index');
            }
        }

        return view('admin.inventory.assignments.create', compact('assets', 'preselectedAsset', 'bulkAssets', 'sites', 'users', 'availablePeripherals', 'returnTo'));
    }

    /**
     * Almacena el acta de Asignación / Devolución y actualiza el estado del activo.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'movement_type' => 'required|in:assignment,return,transfer,loan',
            'inventory_asset_id' => 'required|exists:inventory_assets,id',
            'user_id' => 'nullable|exists:users,id',
            'custodian_name' => 'required|string|max:128',
            'custodian_id_number' => 'nullable|string|max:32',
            'custodian_department' => 'nullable|string|max:128',
            'custodian_role' => 'nullable|string|max:96',
            'phone_extension' => 'nullable|string|max:32',
            'monitored_site_id' => 'nullable|exists:monitored_sites,id',
            'provenance' => 'nullable|in:nuevo,reparacion,stock,otro',
            'provenance_other' => 'nullable|string|max:128',
            'checkpoint_name' => 'nullable|string|max:128',
            'gate_operator_name' => 'nullable|string|max:128',
            'departure_time' => 'nullable|string|max:32',
            'entry_time' => 'nullable|string|max:32',
            'control_number' => 'nullable|string|max:64',
            'assigned_at' => 'nullable|date',
            'observations' => 'nullable|string',
            'peripherals' => 'nullable|array',
            'peripherals.*.type' => 'required|string',
            'peripherals.*.brand' => 'nullable|string',
            'peripherals.*.model' => 'nullable|string',
            'peripherals.*.serial_number' => 'nullable|string',
            'peripherals.*.observations' => 'nullable|string',
            'specs' => 'nullable|array',
        ]);

        $validated['provenance'] = $validated['provenance'] ?? 'stock';
        $assignedAt = !empty($validated['assigned_at']) ? Carbon::parse($validated['assigned_at']) : Carbon::now();
        $validated['assigned_at'] = $assignedAt;

        $asset = InventoryAsset::with(['components'])->findOrFail($validated['inventory_asset_id']);

        // Generar correlativo oficial único
        $year = $assignedAt->format('Y');
        $prefix = ($validated['movement_type'] === 'return') ? "ACTA-DEV-{$year}" : "UINF-4.05-{$year}";
        $countThisYear = InventoryAssignment::where('assignment_code', 'like', "{$prefix}-%")->count() + 1;
        $code = sprintf("%s-%04d", $prefix, $countThisYear);

        DB::beginTransaction();
        try {
            // Snapshot de especificaciones técnicas del hardware
            $cpuComponent = $asset->components->where('type', 'cpu')->first();
            $ramComponents = $asset->components->where('type', 'ram');
            $diskComponents = $asset->components->where('type', 'disk');

            $hardwareSpecs = [
                'processor' => $validated['specs']['processor'] ?? ($cpuComponent?->name ?? 'N/A'),
                'speed' => $validated['specs']['speed'] ?? 'N/A',
                'ram' => $validated['specs']['ram'] ?? ($ramComponents->isNotEmpty() ? $ramComponents->pluck('capacity')->implode(' + ') : 'N/A'),
                'disk' => $validated['specs']['disk'] ?? ($diskComponents->isNotEmpty() ? $diskComponents->pluck('capacity')->implode(' + ') : 'N/A'),
                'os' => $validated['specs']['os'] ?? ($asset->operating_system ?? 'N/A'),
                'device_name' => $validated['specs']['device_name'] ?? ($asset->device_name ?? $asset->name),
                'ip_address' => $validated['specs']['ip_address'] ?? ($asset->ip_address ?? 'N/A'),
            ];

            // Preservar inmutabilidad e histórico de actas previas:
            // Al emitir nueva asignación, las actas activas previas pasan a estado histórico 'superseded'
            if ($validated['movement_type'] === 'assignment') {
                InventoryAssignment::where('inventory_asset_id', $asset->id)
                    ->where('status', 'active')
                    ->update(['status' => 'superseded']);
            } elseif ($validated['movement_type'] === 'return') {
                InventoryAssignment::where('inventory_asset_id', $asset->id)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'returned',
                        'returned_at' => Carbon::parse($validated['assigned_at']),
                    ]);
            }

            // Crear el registro de asignación
            $assignment = InventoryAssignment::create([
                'assignment_code' => $code,
                'inventory_asset_id' => $asset->id,
                'user_id' => $validated['user_id'] ?? null,
                'custodian_name' => $validated['custodian_name'],
                'custodian_id_number' => $validated['custodian_id_number'] ?? null,
                'custodian_department' => $validated['custodian_department'] ?? null,
                'custodian_role' => $validated['custodian_role'] ?? null,
                'phone_extension' => $validated['phone_extension'] ?? null,
                'monitored_site_id' => $validated['monitored_site_id'] ?? $asset->monitored_site_id,
                'movement_type' => $validated['movement_type'],
                'provenance' => $validated['provenance'],
                'provenance_other' => $validated['provenance_other'] ?? null,
                'checkpoint_name' => $validated['checkpoint_name'] ?? ($asset->location ?? 'Centro de Servicio Valle Seco'),
                'gate_operator_name' => $validated['gate_operator_name'] ?? null,
                'departure_time' => $validated['departure_time'] ?? null,
                'entry_time' => $validated['entry_time'] ?? null,
                'control_number' => $validated['control_number'] ?? $code,
                'peripherals_json' => $validated['peripherals'] ?? [],
                'hardware_specs' => $hardwareSpecs,
                'assigned_at' => Carbon::parse($validated['assigned_at']),
                'returned_at' => ($validated['movement_type'] === 'return') ? Carbon::parse($validated['assigned_at']) : null,
                'status' => 'active',
                'delivered_by_user_id' => Auth::id(),
                'observations' => $validated['observations'] ?? null,
            ]);

            // Actualizar estado del activo según la operación
            if ($validated['movement_type'] === 'assignment') {
                $asset->update([
                    'status' => 'assigned',
                    'current_user_id' => $validated['user_id'] ?? null,
                    'custodian_name' => $validated['custodian_name'],
                    'custodian_id_number' => $validated['custodian_id_number'] ?? null,
                    'custodian_department' => $validated['custodian_department'] ?? null,
                    'monitored_site_id' => $validated['monitored_site_id'] ?? $asset->monitored_site_id,
                ]);
            } elseif ($validated['movement_type'] === 'return') {
                $asset->update([
                    'status' => 'in_stock',
                    'current_user_id' => null,
                    'custodian_name' => null,
                    'custodian_id_number' => null,
                    'custodian_department' => null,
                ]);
            } elseif ($validated['movement_type'] === 'transfer') {
                $asset->update([
                    'monitored_site_id' => $validated['monitored_site_id'] ?? $asset->monitored_site_id,
                    'location' => $validated['checkpoint_name'] ?? $asset->location,
                ]);
            }

            // Auditoría forense
            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name ?? 'Sistema',
                'user_email' => Auth::user()->email ?? 'system@local',
                'user_role' => Auth::user()->role ?? 'admin',
                'event' => 'created',
                'module' => 'inventory',
                'auditable_type' => InventoryAssignment::class,
                'auditable_id' => $assignment->id,
                'entity_name' => $assignment->assignment_code,
                'entity_label' => $assignment->custodian_name,
                'description' => "Acta {$code} registrada para activo [{$asset->asset_tag}] ({$validated['movement_type']}) - Asignado a: {$validated['custodian_name']}",
                'new_values' => $assignment->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();

            // Notificación al chat privado del Administrador
            if (class_exists(\App\Services\TelegramNotificationService::class)) {
                app(\App\Services\TelegramNotificationService::class)->notifyAssetAssignment($assignment);
            }

            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Acta {$code} registrada exitosamente.",
                    'assignment' => $assignment,
                    'code' => $code,
                    'pdf_url' => route('admin.inventory.assignments.pdf', $assignment->id),
                    'show_url' => route('admin.inventory.assignments.show', $assignment->id),
                ]);
            }

            return redirect()->route('admin.inventory.assignments.show', $assignment->id)
                ->with('success', "Acta {$code} registrada exitosamente.");
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Error al registrar el acta: {$e->getMessage()}",
                ], 422);
            }
            return back()->withInput()->with('error', "Error al registrar el acta: {$e->getMessage()}");
        }
    }

    /**
     * Ficha de detalle de un Acta o Movilización con acciones de impresión y descarga.
     */
    public function show(InventoryAssignment $assignment): View
    {
        $assignment->load(['asset.components', 'user', 'site', 'deliveredByUser']);

        $verificationUrl = $this->verificationService->getVerificationUrl($assignment);
        $qrCodeDataUri = $this->qrService->generateDataUri($verificationUrl, 160);
        $qrCodeSvg = $this->qrService->generateSvg($verificationUrl, 160);

        return view('admin.inventory.assignments.show', compact('assignment', 'verificationUrl', 'qrCodeDataUri', 'qrCodeSvg'));
    }

    /**
     * Renderizado y descarga de la plantilla oficial UINF-4.05 (Asignación / Devolución).
     */
    public function pdfActa(Request $request, InventoryAssignment $assignment)
    {
        $assignment->load(['asset.components', 'site', 'deliveredByUser']);

        $verificationUrl = $this->verificationService->getVerificationUrl($assignment);
        $qrCodeDataUri = $this->qrService->generateDataUri($verificationUrl, 130);

        $empresaPath = public_path('assets/img/inventory/empresa_logo.png');
        $mppeePath = public_path('assets/img/inventory/mppee_logo.png');

        $empresaLogoBase64 = file_exists($empresaPath) ? base64_encode(file_get_contents($empresaPath)) : null;
        $mppeeLogoBase64 = file_exists($mppeePath) ? base64_encode(file_get_contents($mppeePath)) : null;

        if ($request->has('download')) {
            $pdf = Pdf::loadView('admin.inventory.pdf.acta_asignacion', [
                'assignment' => $assignment,
                'empresaLogoBase64' => $empresaLogoBase64,
                'mppeeLogoBase64' => $mppeeLogoBase64,
                'qrCodeDataUri' => $qrCodeDataUri,
                'verificationUrl' => $verificationUrl,
                'isPdf' => true,
            ]);
            $pdf->setPaper('letter', 'portrait');
            return $pdf->download("{$assignment->assignment_code}.pdf");
        }

        return view('admin.inventory.pdf.acta_asignacion', [
            'assignment' => $assignment,
            'empresaLogoBase64' => $empresaLogoBase64,
            'mppeeLogoBase64' => $mppeeLogoBase64,
            'qrCodeDataUri' => $qrCodeDataUri,
            'verificationUrl' => $verificationUrl,
            'isPdf' => false,
        ]);
    }

    /**
     * Renderizado y descarga de la Constancia oficial de Movilización por Puntos de Control.
     */
    public function pdfMovilizacion(Request $request, InventoryAssignment $assignment)
    {
        $assignment->load(['asset', 'site', 'deliveredByUser']);

        $verificationUrl = $this->verificationService->getVerificationUrl($assignment);
        $qrCodeDataUri = $this->qrService->generateDataUri($verificationUrl, 130);

        $empresaPath = public_path('assets/img/inventory/empresa_logo.png');
        $empresaLogoBase64 = file_exists($empresaPath) ? base64_encode(file_get_contents($empresaPath)) : null;

        if ($request->has('download')) {
            $pdf = Pdf::loadView('admin.inventory.pdf.movilizacion_equipos', [
                'assignment' => $assignment,
                'empresaLogoBase64' => $empresaLogoBase64,
                'qrCodeDataUri' => $qrCodeDataUri,
                'verificationUrl' => $verificationUrl,
                'isPdf' => true,
            ]);
            $pdf->setPaper('letter', 'portrait');
            return $pdf->download("PASE-MOVILIZACION-{$assignment->assignment_code}.pdf");
        }

        return view('admin.inventory.pdf.movilizacion_equipos', [
            'assignment' => $assignment,
            'empresaLogoBase64' => $empresaLogoBase64,
            'qrCodeDataUri' => $qrCodeDataUri,
            'verificationUrl' => $verificationUrl,
            'isPdf' => false,
        ]);
    }

    /**
     * Cargar y adjuntar hoja física escaneada firmada (SLA 15 días continuos).
     */
    public function uploadScan(Request $request, InventoryAssignment $assignment): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'scan_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'observations' => 'nullable|string|max:500',
        ], [
            'scan_file.required' => 'Debe adjuntar el archivo digitalizado (PDF o Imagen).',
            'scan_file.mimes' => 'El archivo debe ser un documento PDF o una imagen (JPG, PNG).',
            'scan_file.max' => 'El archivo no puede exceder los 20 MB.',
        ]);

        $file = $request->file('scan_file');
        $extension = $file->getClientOriginalExtension();
        $safeCode = Str::slug($assignment->assignment_code);
        $fileName = "acta_firmada_{$safeCode}_" . time() . ".{$extension}";
        $path = $file->storeAs('assignments/scans', $fileName, 'public');

        $assignment->update([
            'document_pdf_path' => $path,
            'observations' => $validated['observations'] ?? $assignment->observations,
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'uploaded_assignment_scan',
            'module' => 'inventory',
            'auditable_type' => InventoryAssignment::class,
            'auditable_id' => $assignment->id,
            'entity_name' => $assignment->assignment_code,
            'entity_label' => $assignment->custodian_name,
            'description' => "Acta física escaneada cargada para {$assignment->assignment_code} (Archivo: {$fileName})",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Acta escaneada subida exitosamente para {$assignment->assignment_code}. Asignación validada con soporte digital.",
                'path' => $path,
                'view_url' => route('admin.inventory.assignments.view-scan', $assignment->id),
            ]);
        }

        return redirect()->back()
            ->with('success', "Acta física escaneada subida exitosamente para {$assignment->assignment_code}. Asignación validada.");
    }

    /**
     * Visualizar en el navegador el PDF o imagen digitalizada del acta firmada.
     */
    public function viewScan(InventoryAssignment $assignment)
    {
        if (empty($assignment->document_pdf_path)) {
            abort(404, 'No hay documento escaneado asociado a esta acta.');
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($assignment->document_pdf_path)) {
            abort(404, 'El archivo digitalizado no fue encontrado en el servidor de almacenamiento.');
        }

        $mime = $disk->mimeType($assignment->document_pdf_path) ?? 'application/pdf';
        $fileName = basename($assignment->document_pdf_path);

        return response()->file($disk->path($assignment->document_pdf_path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Eliminar registro de asignación (solo administradores).
     */
    public function destroy(InventoryAssignment $assignment): RedirectResponse
    {
        $code = $assignment->assignment_code;
        $assignment->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name ?? 'Sistema',
            'user_email' => Auth::user()->email ?? 'system@local',
            'user_role' => Auth::user()->role ?? 'admin',
            'event' => 'deleted',
            'module' => 'inventory',
            'auditable_type' => InventoryAssignment::class,
            'auditable_id' => $assignment->id,
            'entity_name' => $code,
            'description' => "Acta {$code} eliminada del sistema.",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('admin.inventory.assignments.index')
            ->with('success', "Acta {$code} eliminada correctamente.");
    }
}
