<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveredDeviceHistory extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'discovered_device_history';

    protected $fillable = [
        'discovered_device_id',
        'event_type',
        'previous_value',
        'new_value',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'discovered_device_id' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    protected $appends = ['event_type_label'];

    public function getEventTypeLabelAttribute(): string
    {
        return match ($this->event_type) {
            'discovered' => 'Detectado en Red',
            'classified' => 'Clasificación Modificada',
            'authorized' => 'Aprobado y Autorizado',
            'rogue_marked' => 'Marcado como Intruso',
            'ip_change' => 'Cambio de Dirección IP',
            'mac_change' => 'Cambio de Dirección MAC',
            'hostname_change' => 'Cambio de Hostname',
            default => ucfirst(str_replace('_', ' ', (string)$this->event_type)),
        };
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(DiscoveredDevice::class, 'discovered_device_id');
    }
}
