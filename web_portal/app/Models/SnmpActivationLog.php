<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnmpActivationLog extends Model
{
    use HasFactory;

    protected $table = 'snmp_activation_log';
    public $timestamps = false;

    protected $fillable = [
        'snmp_device_id',
        'discovered_device_id',
        'ip_address',
        'activation_method',
        'community_set',
        'commands_executed',
        'status',
        'response_output',
        'error_message',
        'executed_by',
        'executed_at',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
    ];

    public function snmpDevice(): BelongsTo
    {
        return $this->belongsTo(SnmpDevice::class, 'snmp_device_id');
    }

    public function discoveredDevice(): BelongsTo
    {
        return $this->belongsTo(DiscoveredDevice::class, 'discovered_device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
