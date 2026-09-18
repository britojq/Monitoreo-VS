<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SnmpOid extends Model
{
    use HasFactory;

    protected $table = 'snmp_oids';

    protected $fillable = [
        'name',
        'oid',
        'mib',
        'vendor',
        'data_type',
        'unit',
        'is_standard',
        'is_counter_wrap',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_standard' => 'boolean',
        'is_counter_wrap' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeStandard($query)
    {
        return $query->where('is_standard', true);
    }

    public function scopeVendor($query, string $vendor)
    {
        return $query->where('vendor', $vendor);
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(SnmpDevice::class, 'snmp_device_oids')
            ->withPivot(['custom_oid', 'is_active', 'alert_threshold_warning', 'alert_threshold_critical'])
            ->withTimestamps();
    }

    public function deviceOids(): HasMany
    {
        return $this->hasMany(SnmpDeviceOid::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(SnmpMetricHistory::class);
    }
}
