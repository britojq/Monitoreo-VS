<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnmpDeviceOid extends Model
{
    use HasFactory;

    protected $table = 'snmp_device_oids';

    protected $fillable = [
        'snmp_device_id',
        'snmp_oid_id',
        'custom_oid',
        'is_active',
        'alert_threshold_warning',
        'alert_threshold_critical',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'alert_threshold_warning' => 'decimal:4',
        'alert_threshold_critical' => 'decimal:4',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(SnmpDevice::class, 'snmp_device_id');
    }

    public function oid(): BelongsTo
    {
        return $this->belongsTo(SnmpOid::class, 'snmp_oid_id');
    }

    public function getEffectiveOid(): string
    {
        return $this->custom_oid ?: $this->oid->oid;
    }
}
