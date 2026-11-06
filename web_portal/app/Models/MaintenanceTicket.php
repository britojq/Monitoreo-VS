<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceTicket extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'ticket_code',
        'inventory_asset_id',
        'type',
        'status',
        'priority',
        'title',
        'description',
        'scheduled_at',
        'started_at',
        'completed_at',
        'technician_user_id',
        'resolution_notes',
        'parts_replaced_json',
        'warranty_applied',
        'cost',
    ];

    protected function casts(): array
    {
        return [
            'inventory_asset_id' => 'integer',
            'technician_user_id' => 'integer',
            'parts_replaced_json' => 'array',
            'warranty_applied' => 'boolean',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cost' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'inventory_asset_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'Programado',
            'in_progress' => 'En Proceso',
            'completed' => 'Finalizado',
            'canceled' => 'Cancelado',
            default => ucfirst($this->status),
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'preventive' => 'Preventivo',
            'corrective' => 'Correctivo',
            default => ucfirst($this->type),
        };
    }
}
