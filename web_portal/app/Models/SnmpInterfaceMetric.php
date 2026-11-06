<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnmpInterfaceMetric extends Model
{
    use HasFactory;

    protected $table = 'snmp_interface_metrics';
    public $timestamps = false;

    protected $fillable = [
        'snmp_interface_id',
        'in_octets',
        'out_octets',
        'in_unicast_pkts',
        'out_unicast_pkts',
        'in_discards',
        'out_discards',
        'in_errors',
        'out_errors',
        'in_bps',
        'out_bps',
        'in_utilization_pct',
        'out_utilization_pct',
        'collected_at',
    ];

    protected $casts = [
        'in_octets' => 'integer',
        'out_octets' => 'integer',
        'in_unicast_pkts' => 'integer',
        'out_unicast_pkts' => 'integer',
        'in_discards' => 'integer',
        'out_discards' => 'integer',
        'in_errors' => 'integer',
        'out_errors' => 'integer',
        'in_bps' => 'decimal:4',
        'out_bps' => 'decimal:4',
        'in_utilization_pct' => 'decimal:2',
        'out_utilization_pct' => 'decimal:2',
        'collected_at' => 'datetime',
    ];

    public function interface(): BelongsTo
    {
        return $this->belongsTo(SnmpInterface::class, 'snmp_interface_id');
    }
}
