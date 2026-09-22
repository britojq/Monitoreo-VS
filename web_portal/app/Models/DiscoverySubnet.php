<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscoverySubnet extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'subnet',
        'site_id',
        'scan_method',
        'scan_interval_minutes',
        'scan_window_start',
        'scan_window_end',
        'rate_limit_pps',
        'nmap_options',
        'is_active',
        'last_scan_at',
    ];

    protected function casts(): array
    {
        return [
            'site_id' => 'integer',
            'scan_interval_minutes' => 'integer',
            'rate_limit_pps' => 'integer',
            'is_active' => 'boolean',
            'last_scan_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'site_id');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(DiscoveryScan::class, 'subnet', 'subnet');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
