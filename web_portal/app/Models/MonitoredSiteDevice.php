<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoredSiteDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitored_site_id',
        'device_number',
        'name',
        'ip',
        'normal_state_msg',
        'error_state_msg',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'device_number' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'monitored_site_id');
    }
}
