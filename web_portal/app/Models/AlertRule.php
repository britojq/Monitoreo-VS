<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertRule extends Model
{
    use HasFactory;

    protected $table = 'alert_rules';

    protected $fillable = [
        'name',
        'description',
        'entity_type',
        'entity_id',
        'condition_type',
        'threshold_value',
        'comparison',
        'duration_seconds',
        'severity',
        'cooldown_minutes',
        'max_alerts_per_hour',
        'auto_resolve',
        'is_active',
    ];

    protected $casts = [
        'threshold_value' => 'float',
        'duration_seconds' => 'integer',
        'cooldown_minutes' => 'integer',
        'max_alerts_per_hour' => 'integer',
        'auto_resolve' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function escalationLevels(): HasMany
    {
        return $this->hasMany(AlertEscalationLevel::class, 'alert_rule_id')->orderBy('level');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'alert_rule_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEntity($query, string $type, ?int $id = null)
    {
        return $query->where('entity_type', $type)
            ->where(function ($q) use ($id) {
                $q->whereNull('entity_id');
                if ($id !== null) {
                    $q->orWhere('entity_id', $id);
                }
            });
    }

    public function getSeverityLabelAttribute(): string
    {
        return match (strtolower($this->severity ?? '')) {
            'emergency' => 'Emergencia',
            'critical' => 'Crítica',
            'warning' => 'Advertencia',
            'info' => 'Información',
            default => ucfirst($this->severity ?? 'Indefinida'),
        };
    }

    public function getEntityTypeLabelAttribute(): string
    {
        return match (strtolower($this->entity_type ?? '')) {
            'service' => 'Servicio Web',
            'site' => 'Sede / Enlace WAN',
            'snmp_device' => 'Dispositivo de Red (SNMP)',
            'network_device' => 'Dispositivo de Red',
            'interface' => 'Interfaz de Red',
            'snmp_interface' => 'Interfaz de Red',
            'ssl_certificate' => 'Certificado SSL/TLS',
            'discovered_device' => 'Dispositivo Descubierto',
            default => ucfirst($this->entity_type ?? 'Recurso'),
        };
    }

    public function getConditionLabelAttribute(): string
    {
        return match (strtolower($this->condition_type ?? '')) {
            'is_down' => 'Caído / Sin Respuesta',
            'service_latency' => 'Latencia Web Degradada',
            'latency_high' => 'Latencia Alta',
            'packet_loss_high' => 'Pérdida Crítica de Paquetes',
            'cpu_high' => 'Uso Alto de CPU',
            'memory_high' => 'Uso Alto de Memoria',
            'temperature_high' => 'Temperatura Crítica',
            'cert_expiring' => 'Certificado por Vencer',
            'cert_expired' => 'Certificado Vencido',
            'interface_down' => 'Interfaz Caída (Down)',
            'new_device' => 'Dispositivo No Autorizado (Rogue)',
            default => ucfirst(str_replace('_', ' ', (string)$this->condition_type)),
        };
    }
}
