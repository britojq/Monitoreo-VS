<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigChangeLog extends Model
{
    use HasFactory;

    protected $table = 'config_change_logs';

    protected $fillable = [
        'device_configuration_id',
        'previous_config_id',
        'network_device_id',
        'snmp_device_id',
        'change_type',
        'diff_summary',
        'diff_unified',
        'lines_added',
        'lines_removed',
        'detected_at',
        'alerted',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'alerted' => 'boolean',
        'lines_added' => 'integer',
        'lines_removed' => 'integer',
    ];

    /**
     * Configuración actual resultante.
     */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(DeviceConfiguration::class, 'device_configuration_id');
    }

    /**
     * Versión previa con la cual se comparó.
     */
    public function previousConfiguration(): BelongsTo
    {
        return $this->belongsTo(DeviceConfiguration::class, 'previous_config_id');
    }

    /**
     * Dispositivo de red asociado.
     */
    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(MonitoredNetworkDevice::class, 'network_device_id');
    }

    /**
     * Dispositivo SNMP asociado.
     */
    public function snmpDevice(): BelongsTo
    {
        return $this->belongsTo(SnmpDevice::class, 'snmp_device_id');
    }

    /**
     * Metadatos visuales del tipo de cambio.
     */
    public function getChangeTypeBadgeAttribute(): array
    {
        return match ($this->change_type) {
            'initial' => [
                'label' => 'Línea Base Inicial',
                'class' => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300',
                'color' => 'emerald',
                'bg' => 'bg-emerald-500/10',
                'text' => 'text-emerald-400',
                'border' => 'border-emerald-500/30',
                'icon' => 'fa-flag',
            ],
            'modified' => [
                'label' => 'Modificada',
                'class' => 'bg-amber-500/10 border-amber-500/30 text-amber-300',
                'color' => 'amber',
                'bg' => 'bg-amber-500/10',
                'text' => 'text-amber-400',
                'border' => 'border-amber-500/30',
                'icon' => 'fa-pen-to-square',
            ],
            'reverted' => [
                'label' => 'Revertida a Anterior',
                'class' => 'bg-purple-500/10 border-purple-500/30 text-purple-300',
                'color' => 'purple',
                'bg' => 'bg-purple-500/10',
                'text' => 'text-purple-400',
                'border' => 'border-purple-500/30',
                'icon' => 'fa-rotate-left',
            ],
            default => [
                'label' => ucfirst($this->change_type),
                'class' => 'bg-slate-800 border-slate-700 text-slate-300',
                'color' => 'slate',
                'bg' => 'bg-slate-500/10',
                'text' => 'text-slate-400',
                'border' => 'border-slate-500/30',
                'icon' => 'fa-circle-info',
            ],
        };
    }
}
