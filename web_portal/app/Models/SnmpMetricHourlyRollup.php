<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnmpMetricHourlyRollup extends Model
{
    use HasFactory;

    protected $table = 'snmp_metric_hourly_rollups';

    protected $fillable = [
        'snmp_device_id',
        'snmp_oid_id',
        'hour_timestamp',
        'avg_value',
        'min_value',
        'max_value',
        'samples_count',
    ];

    protected $casts = [
        'hour_timestamp' => 'datetime',
        'avg_value' => 'decimal:4',
        'min_value' => 'decimal:4',
        'max_value' => 'decimal:4',
        'samples_count' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(SnmpDevice::class, 'snmp_device_id');
    }

    public function oid(): BelongsTo
    {
        return $this->belongsTo(SnmpOid::class, 'snmp_oid_id');
    }
}
