<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MonitoredService;
use App\Models\SslCertificate;
use App\Models\SslCertificateHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;

class AdminSslController extends Controller
{
    /**
     * Muestra el listado de certificados SSL/TLS monitoreados.
     * Accesible tanto para Administrador como para Operador (Modo Consulta).
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $search = $request->query('search');

        $query = SslCertificate::with('service')
            ->orderBy('days_remaining', 'asc');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('domain', 'like', "%{$search}%")
                  ->orWhere('subject_cn', 'like', "%{$search}%")
                  ->orWhere('issuer_cn', 'like', "%{$search}%")
                  ->orWhere('issuer_org', 'like', "%{$search}%");
            });
        }

        if (!empty($statusFilter)) {
            if ($statusFilter === 'expiring') {
                $query->where('days_remaining', '<=', 30)
                      ->where('days_remaining', '>', 7)
                      ->where('last_check_status', '!=', 'error');
            } elseif ($statusFilter === 'critical') {
                $query->where('days_remaining', '<=', 7)
                      ->where('days_remaining', '>=', 0)
                      ->where('last_check_status', '!=', 'error');
            } elseif ($statusFilter === 'expired') {
                $query->where(function ($q) {
                    $q->where('days_remaining', '<', 0)
                      ->orWhere('last_check_status', 'expired');
                });
            } elseif ($statusFilter === 'valid') {
                $query->where('days_remaining', '>', 30)
                      ->where('last_check_status', '!=', 'error');
            } elseif ($statusFilter === 'error') {
                $query->where('last_check_status', 'error');
            }
        }

        $certificates = $query->paginate(15)->withQueryString();

        // Métricas HUD
        $totalCerts = SslCertificate::count();
        $validCerts = SslCertificate::where('days_remaining', '>', 30)
            ->where('last_check_status', '!=', 'error')->count();
        $expiringCerts = SslCertificate::where('days_remaining', '<=', 30)
            ->where('days_remaining', '>', 7)
            ->where('last_check_status', '!=', 'error')->count();
        $criticalCerts = SslCertificate::where('days_remaining', '<=', 7)
            ->where('days_remaining', '>=', 0)
            ->where('last_check_status', '!=', 'error')->count();
        $expiredCerts = SslCertificate::where(function ($q) {
            $q->where('days_remaining', '<', 0)
              ->orWhere('last_check_status', 'expired');
        })->count();
        $errorCerts = SslCertificate::where('last_check_status', 'error')->count();

        $stats = [
            'total' => $totalCerts,
            'valid' => $validCerts,
            'expiring' => $expiringCerts,
            'critical' => $criticalCerts,
            'expired' => $expiredCerts,
            'errors' => $errorCerts,
        ];

        // Servicios para el modal de adición
        $services = MonitoredService::where('is_active', true)->orderBy('name')->get();

        return view('admin.ssl.index', compact('certificates', 'stats', 'services'));
    }

    /**
     * Retorna los detalles completos y el historial de un certificado (para modal o AJAX).
     */
    public function show(int $id): JsonResponse
    {
        $certificate = SslCertificate::with(['service', 'history' => function ($q) {
            $q->orderBy('occurred_at', 'desc')->take(30);
        }])->findOrFail($id);

        $statusMeta = $certificate->getStatusMeta();

        $historyFormatted = $certificate->history->map(function ($h) {
            $meta = $h->getEventMeta();
            return [
                'id' => $h->id,
                'event_type' => $h->event_type,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'icon' => $meta['icon'],
                'badge' => $meta['badge'],
                'previous_fingerprint' => $h->previous_fingerprint,
                'new_fingerprint' => $h->new_fingerprint,
                'previous_valid_to' => $h->previous_valid_to ? $h->previous_valid_to->timezone('America/Caracas')->format('d/m/Y h:i A') : null,
                'new_valid_to' => $h->new_valid_to ? $h->new_valid_to->timezone('America/Caracas')->format('d/m/Y h:i A') : null,
                'days_remaining_at_event' => $h->days_remaining_at_event,
                'error_message' => $h->error_message,
                'occurred_at' => $h->occurred_at ? $h->occurred_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'N/A',
                'time_ago' => $h->occurred_at ? $h->occurred_at->diffForHumans() : '',
            ];
        });

        return response()->json([
            'success' => true,
            'certificate' => [
                'id' => $certificate->id,
                'domain' => $certificate->domain,
                'port' => $certificate->port,
                'service_name' => $certificate->service ? $certificate->service->name : null,
                'subject_cn' => $certificate->subject_cn,
                'subject_org' => $certificate->subject_org,
                'subject_ou' => $certificate->subject_ou,
                'subject_country' => $certificate->subject_country,
                'subject_state' => $certificate->subject_state,
                'subject_locality' => $certificate->subject_locality,
                'issuer_cn' => $certificate->issuer_cn,
                'issuer_org' => $certificate->issuer_org,
                'issuer_country' => $certificate->issuer_country,
                'serial_number' => $certificate->serial_number,
                'signature_algorithm' => $certificate->signature_algorithm,
                'public_key_algorithm' => $certificate->public_key_algorithm,
                'public_key_bits' => $certificate->public_key_bits,
                'version' => $certificate->version,
                'valid_from' => $certificate->valid_from ? $certificate->valid_from->timezone('America/Caracas')->format('d/m/Y h:i:s A') : null,
                'valid_to' => $certificate->valid_to ? $certificate->valid_to->timezone('America/Caracas')->format('d/m/Y h:i:s A') : null,
                'days_remaining' => $certificate->days_remaining,
                'is_self_signed' => $certificate->is_self_signed,
                'is_wildcard' => $certificate->is_wildcard,
                'is_ev' => $certificate->is_ev,
                'san_entries' => $certificate->san_entries ?: [],
                'fingerprint_sha256' => $certificate->fingerprint_sha256,
                'fingerprint_sha1' => $certificate->fingerprint_sha1,
                'pem_certificate' => $certificate->pem_certificate,
                'last_checked_at' => $certificate->last_checked_at ? $certificate->last_checked_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : null,
                'last_check_status' => $certificate->last_check_status,
                'consecutive_errors' => $certificate->consecutive_errors,
                'renewal_count' => $certificate->renewal_count,
                'notes' => $certificate->notes,
                'status_meta' => $statusMeta,
            ],
            'history' => $historyFormatted,
        ]);
    }

    /**
     * Registra un nuevo dominio para monitoreo SSL. (Solo Administrador).
     */
    public function store(Request $request): RedirectResponse
    {
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Acción no permitida para rol Operador.');
        }

        $validated = $request->validate([
            'domain' => 'required|string|max:255|unique:ssl_certificates,domain',
            'port' => 'nullable|integer|min:1|max:65535',
            'service_id' => 'nullable|exists:monitored_services,id',
            'alert_threshold_warning' => 'nullable|integer|min:1|max:365',
            'alert_threshold_critical' => 'nullable|integer|min:1|max:365',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cert = SslCertificate::create([
            'domain' => strtolower(trim($validated['domain'])),
            'port' => $validated['port'] ?? 443,
            'service_id' => $validated['service_id'] ?? null,
            'alert_threshold_warning' => $validated['alert_threshold_warning'] ?? 30,
            'alert_threshold_critical' => $validated['alert_threshold_critical'] ?? 7,
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        // Ejecutar inspección inmediata en segundo plano
        $python = '/scripts/telegram-admin-bot/venv/bin/python';
        $script = '/scripts/telegram-admin-bot/monitor/ssl_checker.py';
        Process::run("{$python} {$script} --check {$cert->domain} --port {$cert->port}");

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'ssl_certificate_created',
            'target_entity' => 'ssl_certificate',
            'target_id' => $cert->id,
            'details' => json_encode(['domain' => $cert->domain, 'port' => $cert->port]),
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.ssl.index')->with('success', "Dominio '{$cert->domain}' agregado e inspeccionado exitosamente.");
    }

    /**
     * Re-inspecciona inmediatamente un certificado específico. (Solo Administrador).
     */
    public function recheck(int $id): JsonResponse|RedirectResponse
    {
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Acción no permitida para rol Operador.');
        }

        $cert = SslCertificate::findOrFail($id);

        $python = '/scripts/telegram-admin-bot/venv/bin/python';
        $script = '/scripts/telegram-admin-bot/monitor/ssl_checker.py';
        $result = Process::run("{$python} {$script} --check {$cert->domain} --port {$cert->port}");

        $cert->refresh();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'ssl_certificate_rechecked',
            'target_entity' => 'ssl_certificate',
            'target_id' => $cert->id,
            'details' => json_encode(['domain' => $cert->domain, 'status' => $cert->last_check_status]),
            'ip_address' => request()->ip(),
        ]);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Certificado para '{$cert->domain}' re-inspeccionado.",
                'certificate' => $cert,
            ]);
        }

        return back()->with('success', "Certificado para '{$cert->domain}' re-inspeccionado exitosamente.");
    }

    /**
     * Re-inspecciona todos los certificados activos. (Solo Administrador).
     */
    public function recheckAll(): RedirectResponse
    {
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Acción no permitida para rol Operador.');
        }

        $python = '/scripts/telegram-admin-bot/venv/bin/python';
        $script = '/scripts/telegram-admin-bot/monitor/ssl_checker.py';
        Process::run("{$python} {$script} --check-all");

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'ssl_certificates_recheck_all',
            'target_entity' => 'ssl_certificate',
            'target_id' => null,
            'details' => json_encode(['action' => 'recheck_all']),
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', 'Todos los certificados SSL han sido re-inspeccionados.');
    }

    /**
     * Elimina un certificado del monitoreo. (Solo Administrador).
     */
    public function destroy(int $id): RedirectResponse
    {
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Acción no permitida para rol Operador.');
        }

        $cert = SslCertificate::findOrFail($id);
        $domain = $cert->domain;
        $cert->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'ssl_certificate_deleted',
            'target_entity' => 'ssl_certificate',
            'target_id' => $id,
            'details' => json_encode(['domain' => $domain]),
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', "Certificado para '{$domain}' eliminado del monitoreo.");
    }
}
