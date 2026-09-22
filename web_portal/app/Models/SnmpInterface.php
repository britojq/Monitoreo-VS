<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SnmpInterface extends Model
{
    use HasFactory;

    protected $table = 'snmp_interfaces';

    protected $fillable = [
        'snmp_device_id',
        'if_index',
        'if_name',
        'if_description',
        'if_alias',
        'if_type',
        'if_speed',
        'if_high_speed',
        'if_physical_address',
        'if_admin_status',
        'if_oper_status',
        'is_monitored',
        'last_in_octets',
        'last_out_octets',
        'last_polled_at',
    ];

    protected $casts = [
        'if_index' => 'integer',
        'if_type' => 'integer',
        'if_speed' => 'integer',
        'if_high_speed' => 'integer',
        'is_monitored' => 'boolean',
        'last_in_octets' => 'integer',
        'last_out_octets' => 'integer',
        'last_polled_at' => 'datetime',
    ];

    public function scopeMonitored($query)
    {
        return $query->where('is_monitored', true);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(SnmpDevice::class, 'snmp_device_id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(SnmpInterfaceMetric::class, 'snmp_interface_id');
    }

    public function latestMetric()
    {
        return $this->hasOne(SnmpInterfaceMetric::class, 'snmp_interface_id')->latestOfMany('collected_at');
    }

    /**
     * Calcula ancho de banda en bps y porcentaje de utilización respecto a métricas previas.
     */
    public function calculateBandwidth(?SnmpInterfaceMetric $prevMetric, int $newInOctets, int $newOutOctets, float $secondsDelta): array
    {
        if (!$prevMetric || $secondsDelta <= 0) {
            return [
                'in_bps' => 0.0,
                'out_bps' => 0.0,
                'in_utilization_pct' => 0.0,
                'out_utilization_pct' => 0.0,
            ];
        }

        // Manejo de Counter Wrap de 32 bits (2^32 = 4294967296)
        $deltaIn = $newInOctets >= $prevMetric->in_octets
            ? ($newInOctets - $prevMetric->in_octets)
            : (4294967296 - $prevMetric->in_octets + $newInOctets);

        $deltaOut = $newOutOctets >= $prevMetric->out_octets
            ? ($newOutOctets - $prevMetric->out_octets)
            : (4294967296 - $prevMetric->out_octets + $newOutOctets);

        $inBps = ($deltaIn * 8) / $secondsDelta;
        $outBps = ($deltaOut * 8) / $secondsDelta;

        $speed = $this->if_high_speed ? ($this->if_high_speed * 1000000) : ($this->if_speed ?: 0);

        $inUtil = ($speed > 0) ? min(100.0, round(($inBps / $speed) * 100, 2)) : 0.0;
        $outUtil = ($speed > 0) ? min(100.0, round(($outBps / $speed) * 100, 2)) : 0.0;

        return [
            'in_bps' => round($inBps, 4),
            'out_bps' => round($outBps, 4),
            'in_utilization_pct' => $inUtil,
            'out_utilization_pct' => $outUtil,
        ];
    }
}
