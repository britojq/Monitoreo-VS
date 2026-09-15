<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoredNetworkDevice extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'monitored_site_id',
        'device_number',
        'name',
        'ip',
        'mac',
        'vendor_data',
        'access_type',
        'access_port',
        'model',
        'serial',
        'ports',
        'notes',
        'normal_state_msg',
        'error_state_msg',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'monitored_site_id' => 'integer',
            'is_active' => 'boolean',
            'device_number' => 'integer',
            'access_port' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function site(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'monitored_site_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(NetworkDeviceCheckHistory::class, 'monitored_network_device_id');
    }
}
