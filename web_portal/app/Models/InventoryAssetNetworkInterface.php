<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAssetNetworkInterface extends Model
{
    use HasFactory;

    protected $table = 'inventory_asset_network_interfaces';

    protected $fillable = [
        'inventory_asset_id',
        'interface_name',
        'mac_address',
        'ip_address',
        'subnet_mask',
        'gateway',
        'dns',
        'interface_type',
        'speed',
        'status',
        'is_primary',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inventory_asset_id' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Relación con el activo padre de inventario.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'inventory_asset_id');
    }

    /**
     * Scope para filtrar la interfaz principal.
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope para interfaces activas (UP).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'up');
    }

    /**
     * Etiqueta amigable de la interfaz para visualización.
     */
    public function getDisplayLabelAttribute(): string
    {
        $label = $this->interface_name ?: 'eth0';
        if ($this->ip_address) {
            $label .= " ({$this->ip_address})";
        }
        if ($this->is_primary) {
            $label .= ' [Principal]';
        }
        return $label;
    }
}
