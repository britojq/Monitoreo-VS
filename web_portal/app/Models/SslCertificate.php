<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SslCertificate extends Model
{
    use HasFactory;

    protected $table = 'ssl_certificates';

    protected $fillable = [
        'service_id',
        'domain',
        'port',
        'subject_cn',
        'subject_org',
        'subject_ou',
        'subject_country',
        'subject_state',
        'subject_locality',
        'issuer_cn',
        'issuer_org',
        'issuer_country',
        'serial_number',
        'signature_algorithm',
        'public_key_algorithm',
        'public_key_bits',
        'version',
        'valid_from',
        'valid_to',
        'days_remaining',
        'is_self_signed',
        'is_wildcard',
        'is_ev',
        'san_entries',
        'fingerprint_sha256',
        'fingerprint_sha1',
        'pem_certificate',
        'alert_threshold_warning',
        'alert_threshold_critical',
        'last_checked_at',
        'last_check_status',
        'consecutive_errors',
        'renewal_count',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'port' => 'integer',
        'public_key_bits' => 'integer',
        'version' => 'integer',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'days_remaining' => 'integer',
        'is_self_signed' => 'boolean',
        'is_wildcard' => 'boolean',
        'is_ev' => 'boolean',
        'san_entries' => 'array',
        'alert_threshold_warning' => 'integer',
        'alert_threshold_critical' => 'integer',
        'last_checked_at' => 'datetime',
        'consecutive_errors' => 'integer',
        'renewal_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con el servicio corporativo monitoreado.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(MonitoredService::class, 'service_id');
    }

    /**
     * Historial de eventos y renovaciones del certificado.
     */
    public function history(): HasMany
    {
        return $this->hasMany(SslCertificateHistory::class, 'ssl_certificate_id')->orderBy('occurred_at', 'desc');
    }

    /**
     * Scope: Solo certificados activos.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Certificados próximos a expirar (dentro del umbral de aviso).
     */
    public function scopeExpiring($query, int $days = 30)
    {
        return $query->where('is_active', true)
            ->where('days_remaining', '<=', $days)
            ->where('days_remaining', '>=', 0);
    }

    /**
     * Scope: Certificados en estado crítico (próximos a expirar en pocos días).
     */
    public function scopeCritical($query, int $days = 7)
    {
        return $query->where('is_active', true)
            ->where('days_remaining', '<=', $days)
            ->where('days_remaining', '>=', 0);
    }

    /**
     * Scope: Certificados ya expirados.
     */
    public function scopeExpired($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->where('days_remaining', '<', 0)
                  ->orWhere('last_check_status', 'expired');
            });
    }

    /**
     * Calcula y actualiza los días restantes hasta la fecha de caducidad.
     */
    public function calculateDaysRemaining(): int
    {
        if (!$this->valid_to) {
            $this->days_remaining = 0;
            return 0;
        }

        $now = Carbon::now();
        $diff = (int) $now->diffInDays($this->valid_to, false);
        $this->days_remaining = $diff;
        return $diff;
    }

    /**
     * Obtiene el estado visual consolidado del certificado.
     */
    public function getStatusMeta(): array
    {
        if ($this->last_check_status === 'error') {
            return [
                'status' => 'error',
                'label' => 'Error / Inalcanzable',
                'color' => 'red',
                'bg_class' => 'bg-red-950/80 text-red-400 border-red-500/40',
                'icon' => 'error'
            ];
        }

        if ($this->last_check_status === 'hostname_mismatch') {
            return [
                'status' => 'hostname_mismatch',
                'label' => 'Discrepancia de Nombre',
                'color' => 'amber',
                'bg_class' => 'bg-amber-950/80 text-amber-400 border-amber-500/40',
                'icon' => 'warning'
            ];
        }

        $days = $this->days_remaining;

        if ($days < 0 || $this->last_check_status === 'expired') {
            return [
                'status' => 'expired',
                'label' => 'Expirado',
                'color' => 'red',
                'bg_class' => 'bg-red-950/80 text-red-400 border-red-500/40',
                'icon' => 'dangerous'
            ];
        }

        if ($days <= ($this->alert_threshold_critical ?? 7)) {
            return [
                'status' => 'critical',
                'label' => 'Crítico (' . $days . 'd)',
                'color' => 'red',
                'bg_class' => 'bg-rose-950/80 text-rose-300 border-rose-500/50',
                'icon' => 'alarm'
            ];
        }

        if ($days <= ($this->alert_threshold_warning ?? 30)) {
            return [
                'status' => 'expiring_soon',
                'label' => 'Por Vencer (' . $days . 'd)',
                'color' => 'amber',
                'bg_class' => 'bg-amber-950/80 text-amber-300 border-amber-500/40',
                'icon' => 'schedule'
            ];
        }

        return [
            'status' => 'success',
            'label' => 'Válido (' . $days . 'd)',
            'color' => 'emerald',
            'bg_class' => 'bg-emerald-950/80 text-emerald-400 border-emerald-500/40',
            'icon' => 'verified_user'
        ];
    }
}
