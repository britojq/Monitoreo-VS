<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoredSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'letter',
        'name',
        'ip',
        'phone_1',
        'phone_2',
        'phone_3',
        'phone_4',
        'phone_5',
        'phone_6',
        'phone_7',
        'phone_8',
        'address',
        'normal_state_msg',
        'error_state_msg',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(MonitoredSiteDevice::class)->orderBy('device_number');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SiteCheckHistory::class, 'monitored_site_id')->orderBy('checked_at', 'desc');
    }
}
