<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteCheckHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitored_site_id',
        'is_up',
        'latency_ms',
        'devices_online',
        'devices_total',
        'status_message',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_up' => 'boolean',
            'latency_ms' => 'float',
            'devices_online' => 'integer',
            'devices_total' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'monitored_site_id');
    }
}
