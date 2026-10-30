<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\CreateAssignmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreAssignmentRequest;
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
    public function store(StoreAssignmentRequest $request, CreateAssignmentAction $action): RedirectResponse|JsonResponse
    {
        try {
            $assignment = $action->execute($request->validated(), $request->ip(), $request->userAgent());
            $code = $assignment->assignment_code;

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
        } catch (\Throwable $e) {
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
