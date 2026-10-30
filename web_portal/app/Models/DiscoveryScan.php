<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveryScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'scan_type',
        'subnet',
        'site_id',
        'started_at',
        'finished_at',
        'duration_seconds',
        'devices_found',
        'new_devices',
        'status',
        'error_message',
        'executed_by',
    ];

    protected function casts(): array
    {
        return [
            'site_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_seconds' => 'float',
            'devices_found' => 'integer',
            'new_devices' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'site_id');
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'running');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeRecent(Builder $query, int $limit = 20): Builder
    {
        return $query->orderByDesc('started_at')->limit($limit);
    }
}
