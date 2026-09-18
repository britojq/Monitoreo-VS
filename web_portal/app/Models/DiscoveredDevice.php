<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscoveredDevice extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'mac_address',
        'ip_address',
        'hostname',
        'vendor',
        'oui_prefix',
        'device_type',
        'os_detected',
        'open_ports',
        'site_id',
        'classification_status',
        'classified_by',
        'classified_at',
        'linked_network_device_id',
        'first_seen',
        'last_seen',
        'seen_count',
        'is_active',
        'discovery_method',
        'is_authorized',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'site_id' => 'integer',
            'classified_by' => 'integer',
            'linked_network_device_id' => 'integer',
            'seen_count' => 'integer',
            'is_active' => 'boolean',
            'is_authorized' => 'boolean',
            'open_ports' => 'array',
            'first_seen' => 'datetime',
            'last_seen' => 'datetime',
            'classified_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'site_id');
    }

    public function classifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classified_by');
    }

    public function linkedNetworkDevice(): BelongsTo
    {
        return $this->belongsTo(MonitoredNetworkDevice::class, 'linked_network_device_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(DiscoveredDeviceHistory::class, 'discovered_device_id')->orderByDesc('occurred_at');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('classification_status', 'pendiente');
    }

    public function scopeRogue(Builder $query): Builder
    {
        return $query->where('classification_status', 'rogue');
    }

    public function scopeAuthorized(Builder $query): Builder
    {
        return $query->where('is_authorized', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Marca el dispositivo como no autorizado o intruso (Rogue).
     */
    public function markAsRogue(?int $userId = null, ?string $reason = null): void
    {
        $prev = $this->classification_status;
        $this->update([
            'classification_status' => 'rogue',
            'is_authorized' => false,
            'classified_by' => $userId,
            'classified_at' => now(),
            'notes' => $reason ? ($this->notes ? "{$this->notes}\n[Rogue]: {$reason}" : "[Rogue]: {$reason}") : $this->notes,
        ]);

        DiscoveredDeviceHistory::create([
            'discovered_device_id' => $this->id,
            'event_type' => 'marked_rogue',
            'previous_value' => $prev,
            'new_value' => 'rogue: ' . ($reason ?? 'Marcado manualmente'),
            'occurred_at' => now(),
        ]);
    }

    /**
     * Aprueba y autoriza el dispositivo en la red corporativa.
     */
    public function authorizeDevice(?int $userId = null, string $deviceType = 'workstation', ?string $notes = null): void
    {
        $this->update([
            'classification_status' => 'clasificado',
            'is_authorized' => true,
            'device_type' => $deviceType,
            'classified_by' => $userId,
            'classified_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);

        DiscoveredDeviceHistory::create([
            'discovered_device_id' => $this->id,
            'event_type' => 'approved',
            'previous_value' => 'no autorizado',
            'new_value' => "autorizado ({$deviceType})",
            'occurred_at' => now(),
        ]);
    }

    /**
     * Etiqueta en español para tipo de dispositivo de red
     */
    public function getDeviceTypeLabelAttribute(): string
    {
        return match (strtolower($this->device_type ?? '')) {
            'workstation' => 'Estación de Trabajo',
            'server' => 'Servidor',
            'switch' => 'Switch de Red',
            'router' => 'Enrutador / Gateway',
            'printer' => 'Impresora de Red',
            'ap' => 'Punto de Acceso Wi-Fi',
            'firewall' => 'Firewall / Seguridad',
            'phone' => 'Telefonía IP',
            default => ucfirst($this->device_type ?: 'Dispositivo'),
        };
    }

    /**
     * Etiqueta en español para estado de clasificación
     */
    public function getClassificationStatusLabelAttribute(): string
    {
        if ($this->classification_status === 'rogue') {
            return 'Intruso (Rogue)';
        }
        if ($this->is_authorized || $this->classification_status === 'clasificado') {
            return 'Autorizado';
        }
        if ($this->classification_status === 'pendiente') {
            return 'Pendiente';
        }
        if ($this->classification_status === 'byod') {
            return 'Móvil / BYOD';
        }
        return ucfirst($this->classification_status ?: 'Sin clasificar');
    }
}
