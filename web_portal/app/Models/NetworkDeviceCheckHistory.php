<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkDeviceCheckHistory extends Model
{
    protected $fillable = [
        'monitored_network_device_id',
        'is_up',
        'latency_ms',
        'status_message',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_up' => 'boolean',
            'latency_ms' => 'float',
            'checked_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(MonitoredNetworkDevice::class, 'monitored_network_device_id');
    }
}
