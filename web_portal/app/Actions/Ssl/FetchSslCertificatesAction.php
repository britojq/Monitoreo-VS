<?php

declare(strict_types=1);

namespace App\Actions\Ssl;

use App\Models\MonitoredService;
use App\Models\SslCertificate;
use Illuminate\Http\Request;

class FetchSslCertificatesAction
{
    public function execute(Request $request): array
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

        $stats = [
            'total' => SslCertificate::count(),
            'valid' => SslCertificate::where('days_remaining', '>', 30)
                ->where('last_check_status', '!=', 'error')->count(),
            'expiring' => SslCertificate::where('days_remaining', '<=', 30)
                ->where('days_remaining', '>', 7)
                ->where('last_check_status', '!=', 'error')->count(),
            'critical' => SslCertificate::where('days_remaining', '<=', 7)
                ->where('days_remaining', '>=', 0)
                ->where('last_check_status', '!=', 'error')->count(),
            'expired' => SslCertificate::where(function ($q) {
                $q->where('days_remaining', '<', 0)
                  ->orWhere('last_check_status', 'expired');
            })->count(),
            'errors' => SslCertificate::where('last_check_status', 'error')->count(),
        ];

        $services = MonitoredService::where('is_active', true)->orderBy('name')->get();

        return compact('certificates', 'stats', 'services');
    }

    public function getDetails(int $id): array
    {
        $certificate = SslCertificate::with(['service', 'history' => function ($q) {
            $q->orderBy('occurred_at', 'desc')->take(30);
        }])->findOrFail($id);

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

        return [
            'success' => true,
            'certificate' => [
                'id' => $certificate->id,
                'domain' => $certificate->domain,
                'port' => $certificate->port,
                'service_name' => $certificate->service?->name,
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
                'status_meta' => $certificate->getStatusMeta(),
            ],
            'history' => $historyFormatted,
        ];
    }
}
