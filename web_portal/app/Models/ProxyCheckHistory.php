<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxyCheckHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitored_proxy_id',
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

    public function proxy(): BelongsTo
    {
        return $this->belongsTo(MonitoredProxy::class, 'monitored_proxy_id');
    }
}
