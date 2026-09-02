<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCheckHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitored_service_id',
        'is_up',
        'latency_ms',
        'http_code',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(MonitoredService::class, 'monitored_service_id');
    }
}
