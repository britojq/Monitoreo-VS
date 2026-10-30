<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnmpTrapReceived extends Model
{
    use HasFactory;

    protected $table = 'snmp_traps_received';
    public $timestamps = false;

    protected $fillable = [
        'snmp_device_id',
        'source_ip',
        'trap_oid',
        'trap_type',
        'varbinds',
        'severity',
        'processed',
        'alert_id',
        'received_at',
    ];

    protected $casts = [
        'varbinds' => 'array',
        'processed' => 'boolean',
        'received_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(SnmpDevice::class, 'snmp_device_id');
    }
}
