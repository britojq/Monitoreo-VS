<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SslCertificateHistory extends Model
{
    use HasFactory;

    protected $table = 'ssl_certificate_history';

    public $timestamps = false;

    protected $fillable = [
        'ssl_certificate_id',
        'event_type',
        'previous_fingerprint',
        'new_fingerprint',
        'previous_valid_to',
        'new_valid_to',
        'days_remaining_at_event',
        'error_message',
        'occurred_at',
    ];

    protected $casts = [
        'previous_valid_to' => 'datetime',
        'new_valid_to' => 'datetime',
        'days_remaining_at_event' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(SslCertificate::class, 'ssl_certificate_id');
    }

    /**
     * Devuelve metadatos para presentación visual del evento.
     */
    public function getEventMeta(): array
    {
        return match ($this->event_type) {
            'initial_discovery' => [
                'label' => 'Descubrimiento Inicial',
                'color' => 'cyan',
                'icon' => 'radar',
                'badge' => 'bg-cyan-950/80 text-cyan-300 border-cyan-500/40'
            ],
            'renewal' => [
                'label' => 'Certificado Renovado',
                'color' => 'emerald',
                'icon' => 'autorenew',
                'badge' => 'bg-emerald-950/80 text-emerald-300 border-emerald-500/40'
            ],
            'expiration_warning' => [
                'label' => 'Aviso de Vencimiento',
                'color' => 'amber',
                'icon' => 'schedule',
                'badge' => 'bg-amber-950/80 text-amber-300 border-amber-500/40'
            ],
            'expired' => [
                'label' => 'Certificado Expirado',
                'color' => 'red',
                'icon' => 'dangerous',
                'badge' => 'bg-red-950/80 text-red-300 border-red-500/40'
            ],
            'issuer_changed' => [
                'label' => 'Cambio de Autoridad Emisora',
                'color' => 'purple',
                'icon' => 'security_update',
                'badge' => 'bg-purple-950/80 text-purple-300 border-purple-500/40'
            ],
            'hostname_mismatch' => [
                'label' => 'Discrepancia de Nombre',
                'color' => 'amber',
                'icon' => 'domain_verification',
                'badge' => 'bg-amber-950/80 text-amber-300 border-amber-500/40'
            ],
            'error' => [
                'label' => 'Fallo de Inspección',
                'color' => 'red',
                'icon' => 'error',
                'badge' => 'bg-red-950/80 text-red-300 border-red-500/40'
            ],
            'recovered' => [
                'label' => 'Inspección Recuperada',
                'color' => 'emerald',
                'icon' => 'check_circle',
                'badge' => 'bg-emerald-950/80 text-emerald-300 border-emerald-500/40'
            ],
            default => [
                'label' => ucfirst($this->event_type),
                'color' => 'gray',
                'icon' => 'info',
                'badge' => 'bg-gray-800 text-gray-300 border-gray-600'
            ],
        };
    }
}
