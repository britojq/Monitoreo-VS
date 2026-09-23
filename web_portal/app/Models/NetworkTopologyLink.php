<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkTopologyLink extends Model
{
    use HasFactory;

    protected $table = 'network_topology_links';

    protected $fillable = [
        'source_device_id',
        'source_interface_id',
        'target_device_id',
        'target_mac',
        'target_hostname',
        'link_type',
        'link_status',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(SnmpDevice::class, 'source_device_id');
    }

    public function targetDevice(): BelongsTo
    {
        return $this->belongsTo(SnmpDevice::class, 'target_device_id');
    }

    public function sourceInterface(): BelongsTo
    {
        return $this->belongsTo(SnmpInterface::class, 'source_interface_id');
    }
}
