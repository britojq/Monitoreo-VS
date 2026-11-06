<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PredictiveAnomaly extends Model
{
    use HasFactory;

    protected $table = 'predictive_anomalies';
    public $timestamps = false;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'anomaly_type',
        'metric_name',
        'confidence',
        'description',
        'predicted_impact',
        'detected_at',
        'acknowledged',
    ];

    protected $casts = [
        'confidence' => 'float',
        'acknowledged' => 'boolean',
        'detected_at' => 'datetime',
    ];

    public function getAnomalyLabelAttribute(): string
    {
        return match ($this->anomaly_type) {
            'trend_upward' => 'Tendencia Alcista Crítica',
            'seasonal_pattern' => 'Patrón Inusual / Cíclico',
            'correlation' => 'Correlación Anómala',
            'outlier' => 'Desviación Estadística (> 3σ)',
            default => 'Anomalía',
        };
    }

    public function getAnomalyBadgeColorAttribute(): string
    {
        return match ($this->anomaly_type) {
            'trend_upward' => 'bg-rose-500/20 text-rose-400 border-rose-500/30',
            'outlier' => 'bg-amber-500/20 text-amber-400 border-amber-500/30',
            'seasonal_pattern' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
            default => 'bg-cyan-500/20 text-cyan-400 border-cyan-500/30',
        };
    }

    public function getResolvedEntityAttribute(): ?array
    {
        switch ($this->entity_type) {
            case 'network_device':
                $dev = \App\Models\InventoryAsset::find($this->entity_id) ?? \App\Models\MonitoredNetworkDevice::find($this->entity_id);
                if ($dev) {
                    return [
                        'name' => $dev->name,
                        'ip' => $dev->ip_address ?? $dev->ip ?? null,
                        'url' => route('admin.devices.index', ['search' => $dev->name]),
                        'type_label' => 'Equipo de Red'
                    ];
                }
                return null;

            case 'snmp_device':
                $dev = \App\Models\SnmpDevice::find($this->entity_id);
                if ($dev) {
                    return [
                        'name' => $dev->name,
                        'ip' => $dev->ip_address,
                        'url' => route('admin.discovery.index', ['tab' => 'snmp', 'search' => $dev->name]),
                        'type_label' => 'Dispositivo SNMP'
                    ];
                }
                return null;

            case 'service':
                $srv = \App\Models\MonitoredService::find($this->entity_id);
                if ($srv) {
                    return [
                        'name' => $srv->name,
                        'ip' => $srv->host_ip,
                        'url' => route('admin.services.index', ['search' => $srv->name]),
                        'type_label' => 'Servicio Web'
                    ];
                }
                return null;

            case 'site':
                $site = \App\Models\MonitoredSite::find($this->entity_id);
                if ($site) {
                    return [
                        'name' => $site->name,
                        'ip' => $site->target_ip,
                        'url' => route('admin.sites.index', ['search' => $site->name]),
                        'type_label' => 'Sede WAN'
                    ];
                }
                return null;

            default:
                return null;
        }
    }
}
