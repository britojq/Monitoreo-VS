<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryComponentHistory extends Model
{
    use HasFactory;

    protected $table = 'inventory_components_histories';

    public $timestamps = false;

    protected $fillable = [
        'inventory_asset_id',
        'action',
        'component_type',
        'old_value',
        'new_value',
        'reason',
        'registered_by_user_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'inventory_asset_id' => 'integer',
            'registered_by_user_id' => 'integer',
            'old_value' => 'array',
            'new_value' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'inventory_asset_id');
    }

    public function registeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }
}
