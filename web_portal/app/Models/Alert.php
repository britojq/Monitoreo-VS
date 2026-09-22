<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alert extends Model
{
    use HasFactory;

    protected $table = 'alerts';

    protected $fillable = [
        'alert_rule_id',
        'entity_type',
        'entity_id',
        'entity_name',
        'condition_type',
        'severity',
        'status',
        'current_escalation_level',
        'value_at_trigger',
        'threshold_value',
        'message',
        'correlation_group_id',
        'is_correlated_suppressed',
        'parent_alert_id',
        'fired_at',
        'acknowledged_at',
        'resolved_at',
        'acknowledged_by',
        'resolved_by',
        'last_notified_at',
        'notification_count',
        'duration_seconds',
        'notes',
    ];

    protected $casts = [
        'value_at_trigger' => 'float',
        'threshold_value' => 'float',
        'is_correlated_suppressed' => 'boolean',
        'fired_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_notified_at' => 'datetime',
        'current_escalation_level' => 'integer',
        'notification_count' => 'integer',
        'duration_seconds' => 'integer',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    public function correlationGroup(): BelongsTo
    {
        return $this->belongsTo(AlertCorrelationGroup::class, 'correlation_group_id');
    }

    public function parentAlert(): BelongsTo
    {
        return $this->belongsTo(Alert::class, 'parent_alert_id');
    }

    public function childAlerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'parent_alert_id');
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AlertNotification::class, 'alert_id')->orderBy('sent_at', 'desc');
    }

    /**
     * Helper to compute dynamic duration in seconds if active or stored
     */
    public function getDurationSecondsAttribute($value): int
    {
        if ($this->resolved_at && $this->fired_at) {
            return (int) $this->fired_at->diffInSeconds($this->resolved_at);
        }
        if ($this->fired_at) {
            return (int) $this->fired_at->diffInSeconds(now());
        }
        return (int) ($value ?? 0);
    }

    /**
     * Human-readable formatted duration string in Spanish
     */
    public function getDurationFormattedAttribute(): string
    {
        $seconds = $this->duration_seconds;
        if ($seconds < 60) {
            return "{$seconds} seg";
        }
        $minutes = floor($seconds / 60);
        $remainingSecs = $seconds % 60;
        if ($minutes < 60) {
            return "{$minutes} min" . ($remainingSecs > 0 ? " {$remainingSecs} seg" : "");
        }
        $hours = floor($minutes / 60);
        $remainingMins = $minutes % 60;
        if ($hours < 24) {
            return "{$hours}h" . ($remainingMins > 0 ? " {$remainingMins} min" : "");
        }
        $days = floor($hours / 24);
        $remainingHours = $hours % 24;
        return "{$days}d" . ($remainingHours > 0 ? " {$remainingHours}h" : "");
    }

    /**
     * Etiqueta en español para severidad
     */
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

    /**
     * Etiqueta en español para estado del incidente
     */
    public function getStatusLabelAttribute(): string
    {
        return match (strtolower($this->status ?? '')) {
            'firing' => 'Disparada / Activa',
            'acknowledged' => 'Reconocida',
            'suppressed' => 'Suprimida',
            'resolved' => 'Resuelta',
            'auto_resolved' => 'Auto-Resuelta',
            default => ucfirst($this->status ?? 'Desconocido'),
        };
    }

    /**
     * Etiqueta en español para tipo de entidad
     */
    public function getEntityTypeLabelAttribute(): string
    {
        return match (strtolower($this->entity_type ?? '')) {
            'service' => 'Servicio Web',
            'site' => 'Sede / Enlace WAN',
            'snmp_device' => 'Dispositivo de Red (SNMP)',
            'network_device' => 'Dispositivo de Red',
            'snmp_interface' => 'Interfaz de Red',
            'ssl_certificate' => 'Certificado SSL/TLS',
            'discovered_device' => 'Dispositivo Descubierto',
            default => ucfirst($this->entity_type ?? 'Recurso'),
        };
    }

    /**
     * Etiqueta en español para condición del incidente
     */
    public function getConditionLabelAttribute(): string
    {
        return match (strtolower($this->condition_type ?? '')) {
            'is_down' => 'Servicio Caído / Sin Respuesta',
            'service_latency' => 'Latencia Web Degradada',
            'latency_high' => 'Latencia WAN Elevada',
            'packet_loss_high', 'wan_packet_loss' => 'Pérdida Crítica de Paquetes',
            'wan_latency' => 'Latencia WAN Elevada',
            'jitter_high' => 'Jitter de Red Elevado',
            'cpu_high', 'snmp_cpu' => 'Sobrecarga de CPU',
            'memory_high', 'snmp_memory' => 'Saturación de Memoria',
            'temperature_high', 'snmp_temperature' => 'Temperatura Crítica',
            'interface_down', 'snmp_interface_status' => 'Interfaz Desconectada (Down)',
            'interface_utilization_high' => 'Saturación de Ancho de Banda',
            'cert_expiring', 'cert_expiring_soon', 'ssl_days_remaining' => 'Certificado Próximo a Vencer',
            'cert_expired' => 'Certificado SSL Expirado',
            'new_device', 'rogue_device_detected' => 'Dispositivo Intruso No Autorizado',
            'device_disappeared', 'device_missing_hours' => 'Dispositivo Crítico Desconectado',
            'config_changed' => 'Cambio de Configuración Detectado',
            'battery_low' => 'Batería de Respaldo Crítica',
            'snmp_unreachable' => 'Dispositivo SNMP Inaccesible',
            default => ucfirst(str_replace('_', ' ', $this->condition_type ?? 'Anomalía')),
        };
    }

    /**
     * Etiqueta concisa en español para widgets compactos
     */
    public function getConditionShortLabelAttribute(): string
    {
        return match (strtolower($this->condition_type ?? '')) {
            'is_down' => 'Caído',
            'service_latency', 'latency_high', 'wan_latency' => 'Latencia',
            'packet_loss_high', 'wan_packet_loss' => 'Pérdida Pqts',
            'jitter_high' => 'Jitter Alto',
            'cpu_high', 'snmp_cpu' => 'CPU Alta',
            'memory_high', 'snmp_memory' => 'Memoria Alta',
            'temperature_high', 'snmp_temperature' => 'Temp. Crítica',
            'interface_down', 'snmp_interface_status' => 'Puerto Down',
            'interface_utilization_high' => 'Saturación',
            'cert_expiring', 'cert_expiring_soon', 'ssl_days_remaining' => 'SSL x Vencer',
            'cert_expired' => 'SSL Expirado',
            'new_device', 'rogue_device_detected' => 'Intruso',
            'device_disappeared', 'device_missing_hours' => 'Desconectado',
            'config_changed' => 'Config Mod',
            'battery_low' => 'Batería Baja',
            'snmp_unreachable' => 'Sin SNMP',
            default => ucfirst(str_replace('_', ' ', $this->condition_type ?? 'Alerta')),
        };
    }

    /**
     * Icono representativo para la condición
     */
    public function getConditionIconAttribute(): string
    {
        return match (strtolower($this->condition_type ?? '')) {
            'is_down' => 'cloud_off',
            'service_latency', 'latency_high', 'wan_latency' => 'speed',
            'packet_loss_high', 'wan_packet_loss' => 'network_check',
            'jitter_high' => 'show_chart',
            'cpu_high', 'snmp_cpu' => 'memory',
            'memory_high', 'snmp_memory' => 'storage',
            'temperature_high', 'snmp_temperature' => 'device_thermostat',
            'interface_down', 'snmp_interface_status' => 'cable',
            'interface_utilization_high' => 'swap_vert',
            'cert_expiring', 'cert_expiring_soon', 'ssl_days_remaining' => 'lock_clock',
            'cert_expired' => 'lock_reset',
            'new_device', 'rogue_device_detected' => 'gpm_bad',
            'device_disappeared', 'device_missing_hours' => 'portable_wifi_off',
            'config_changed' => 'settings_suggest',
            'battery_low' => 'battery_alert',
            'snmp_unreachable' => 'wifi_off',
            default => 'warning',
        };
    }

    /**
     * Estilo CSS para badge compacto de condición
     */
    public function getConditionBadgeClassAttribute(): string
    {
        return match (strtolower($this->condition_type ?? '')) {
            'cert_expired', 'is_down', 'device_disappeared', 'device_missing_hours' => 'bg-red-950/80 text-red-300 border border-red-500/40',
            'cert_expiring', 'cert_expiring_soon', 'ssl_days_remaining', 'packet_loss_high', 'wan_packet_loss', 'battery_low' => 'bg-amber-950/80 text-amber-300 border border-amber-500/40',
            'service_latency', 'latency_high', 'wan_latency', 'cpu_high', 'snmp_cpu', 'memory_high', 'snmp_memory', 'interface_utilization_high' => 'bg-sky-950/80 text-sky-300 border border-sky-500/40',
            'new_device', 'rogue_device_detected', 'temperature_high', 'snmp_temperature', 'interface_down', 'snmp_interface_status' => 'bg-purple-950/80 text-purple-300 border border-purple-500/40',
            default => 'bg-slate-900/80 text-slate-300 border border-slate-700/40',
        };
    }

    /**
     * Acknowledge this alert
     */
    public function acknowledge(int $userId, ?string $notes = null): bool
    {
        $this->status = 'acknowledged';
        $this->acknowledged_by = $userId;
        $this->acknowledged_at = Carbon::now();
        if ($notes) {
            $this->notes = trim(($this->notes ? $this->notes . "\n" : '') . "[Ack: " . Carbon::now()->format('Y-m-d H:i:s') . "] " . $notes);
        }
        return $this->save();
    }

    /**
     * Resolve this alert (auto or manual)
     */
    public function resolve(string $mode = 'manual', ?string $notes = null): bool
    {
        $this->status = ($mode === 'auto') ? 'auto_resolved' : 'resolved';
        $this->resolved_by = $mode;
        $this->resolved_at = Carbon::now();
        $this->duration_seconds = $this->fired_at ? (int) $this->fired_at->diffInSeconds($this->resolved_at) : 0;
        if ($notes) {
            $this->notes = trim(($this->notes ? $this->notes . "\n" : '') . "[Resuelto ({$mode}): " . Carbon::now()->format('Y-m-d H:i:s') . "] " . $notes);
        }
        return $this->save();
    }

    /**
     * Check if alert is suppressed (either explicitly or via parent correlation)
     */
    public function isSuppressed(): bool
    {
        return $this->status === 'suppressed' || (bool) $this->is_correlated_suppressed;
    }

    // Scopes
    public function scopeFiring($query)
    {
        return $query->where('status', 'firing');
    }

    public function scopeAcknowledged($query)
    {
        return $query->where('status', 'acknowledged');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['firing', 'acknowledged']);
    }

    public function scopeResolved($query)
    {
        return $query->whereIn('status', ['resolved', 'auto_resolved']);
    }

    public function scopeCriticalOrEmergency($query)
    {
        return $query->whereIn('severity', ['critical', 'emergency']);
    }
}
