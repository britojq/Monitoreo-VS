<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnmpMetricHistory extends Model
{
    use HasFactory;

    protected $table = 'snmp_metrics_history';
    public $timestamps = false;

    protected $fillable = [
        'snmp_device_id',
        'snmp_oid_id',
        'metric_value',
        'metric_value_raw',
        'collected_at',
    ];

    protected $casts = [
        'metric_value' => 'decimal:6',
        'collected_at' => 'datetime',
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
