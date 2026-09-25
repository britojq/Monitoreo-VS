<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnmpInterfaceHourlyRollup extends Model
{
    use HasFactory;

    protected $table = 'snmp_interface_hourly_rollups';

    protected $fillable = [
        'snmp_interface_id',
        'hour_timestamp',
        'avg_in_bps',
        'max_in_bps',
        'avg_out_bps',
        'max_out_bps',
        'avg_in_util_pct',
        'max_in_util_pct',
        'avg_out_util_pct',
        'max_out_util_pct',
        'samples_count',
    ];

    protected $casts = [
        'hour_timestamp' => 'datetime',
        'avg_in_bps' => 'decimal:2',
        'max_in_bps' => 'decimal:2',
        'avg_out_bps' => 'decimal:2',
        'max_out_bps' => 'decimal:2',
        'avg_in_util_pct' => 'decimal:2',
        'max_in_util_pct' => 'decimal:2',
        'avg_out_util_pct' => 'decimal:2',
        'max_out_util_pct' => 'decimal:2',
        'samples_count' => 'integer',
    ];

    public function interface(): BelongsTo
    {
        return $this->belongsTo(SnmpInterface::class, 'snmp_interface_id');
    }
}
