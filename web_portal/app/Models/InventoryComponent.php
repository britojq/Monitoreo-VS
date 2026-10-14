<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryComponent extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'inventory_asset_id',
        'type',
        'name',
        'brand',
        'model',
        'capacity',
        'serial_number',
        'slot_or_bus',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'inventory_asset_id' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'inventory_asset_id');
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'cpu' => 'memory',
            'ram' => 'developer_board',
            'disk' => 'hard_drive',
            'gpu' => 'videogame_asset',
            'network_card' => 'settings_ethernet',
            'motherboard' => 'dashboard',
            'power_supply' => 'power',
            default => 'extension',
        };
    }
}
