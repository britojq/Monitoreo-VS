<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryPeripheral extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'asset_tag',
        'type',
        'brand',
        'model',
        'serial_number',
        'status',
        'monitored_site_id',
        'current_asset_id',
        'current_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'monitored_site_id' => 'integer',
            'current_asset_id' => 'integer',
            'current_user_id' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'monitored_site_id');
    }

    public function currentAsset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'current_asset_id');
    }

    public function currentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'monitor' => 'monitor',
            'keyboard' => 'keyboard',
            'mouse' => 'mouse',
            'printer' => 'print',
            'headset' => 'headphones',
            'ups' => 'battery_charging_full',
            'surge_protector' => 'bolt',
            'scanner' => 'document_scanner',
            default => 'devices_other',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'monitor' => 'Monitor / Pantalla',
            'keyboard' => 'Teclado',
            'mouse' => 'Mouse / Ratón',
            'printer' => 'Impresora Local',
            'headset' => 'Auriculares / Headset',
            'ups' => 'Sistema UPS',
            'surge_protector' => 'Regulador / Protector',
            'scanner' => 'Escáner / Lector',
            default => ucfirst(str_replace('_', ' ', $this->type ?? 'Periférico')),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'in_stock' => 'En Stock (Disponible)',
            'assigned' => 'Asignado',
            'in_maintenance' => 'En Mantenimiento',
            'damaged' => 'Dañado / Falla',
            'decommissioned' => 'Desincorporado',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
