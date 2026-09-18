<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class SnmpDevice extends Model
{
    use HasFactory;

    protected $table = 'snmp_devices';

    protected $fillable = [
        'name',
        'ip_address',
        'snmp_version',
        'snmp_community_encrypted',
        'snmp_v3_username',
        'snmp_v3_auth_protocol',
        'snmp_v3_auth_password_encrypted',
        'snmp_v3_priv_protocol',
        'snmp_v3_priv_password_encrypted',
        'snmp_port',
        'snmp_timeout_seconds',
        'snmp_retries',
        'device_type',
        'vendor',
        'model',
        'firmware_version',
        'serial_number',
        'sys_name',
        'sys_description',
        'sys_object_id',
        'sys_uptime',
        'sys_location',
        'sys_contact',
        'site_id',
        'discovered_device_id',
        'network_device_id',
        'poll_interval_seconds',
        'is_active',
        'last_poll_at',
        'last_poll_status',
        'consecutive_failures',
        'ssh_enabled',
        'ssh_username',
        'ssh_password_encrypted',
        'ssh_enable_secret_encrypted',
        'ssh_port',
        'custom_oids',
        'notes',
    ];

    protected $casts = [
        'snmp_community_encrypted' => 'encrypted',
        'snmp_v3_auth_password_encrypted' => 'encrypted',
        'snmp_v3_priv_password_encrypted' => 'encrypted',
        'ssh_password_encrypted' => 'encrypted',
        'ssh_enable_secret_encrypted' => 'encrypted',
        'custom_oids' => 'array',
        'is_active' => 'boolean',
        'ssh_enabled' => 'boolean',
        'last_poll_at' => 'datetime',
        'snmp_port' => 'integer',
        'snmp_timeout_seconds' => 'integer',
        'snmp_retries' => 'integer',
        'poll_interval_seconds' => 'integer',
        'consecutive_failures' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function shouldPoll(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!$this->last_poll_at) {
            return true;
        }

        return $this->last_poll_at->addSeconds($this->poll_interval_seconds)->isPast();
    }

    public function getCommunity(): string
    {
        return $this->snmp_community_encrypted ?: 'public';
    }

    public function setCommunity(string $value): void
    {
        $this->snmp_community_encrypted = $value;
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'site_id');
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(MonitoredNetworkDevice::class, 'network_device_id');
    }

    public function discoveredDevice(): BelongsTo
    {
        return $this->belongsTo(DiscoveredDevice::class, 'discovered_device_id');
    }

    public function oids(): BelongsToMany
    {
        return $this->belongsToMany(SnmpOid::class, 'snmp_device_oids')
            ->withPivot(['custom_oid', 'is_active', 'alert_threshold_warning', 'alert_threshold_critical'])
            ->withTimestamps();
    }

    public function deviceOids(): HasMany
    {
        return $this->hasMany(SnmpDeviceOid::class);
    }

    public function interfaces(): HasMany
    {
        return $this->hasMany(SnmpInterface::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(SnmpMetricHistory::class);
    }

    public function activationLogs(): HasMany
    {
        return $this->hasMany(SnmpActivationLog::class);
    }

    public function traps(): HasMany
    {
        return $this->hasMany(SnmpTrapReceived::class);
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(DeviceConfiguration::class, 'snmp_device_id');
    }

    public function latestConfiguration(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DeviceConfiguration::class, 'snmp_device_id')->latestOfMany('captured_at');
    }

    /**
     * Etiqueta en español para tipo de dispositivo SNMP
     */
    public function getDeviceTypeLabelAttribute(): string
    {
        return match (strtolower($this->device_type ?? '')) {
            'router' => 'Enrutador / Router',
            'switch' => 'Switch Gestionable',
            'firewall' => 'Cortafuegos / Firewall',
            'server' => 'Servidor de Red',
            'ups' => 'Sistema UPS / Respaldo',
            'printer' => 'Impresora de Red',
            'ap' => 'Punto de Acceso Wi-Fi',
            default => ucfirst($this->device_type ?: 'Dispositivo'),
        };
    }

    /**
     * Etiqueta en español para estado del sondeo SNMP
     */
    public function getStatusLabelAttribute(): string
    {
        return match (strtolower($this->last_poll_status ?? '')) {
            'success' => 'Respondiendo (Operativo)',
            'timeout' => 'Sin Respuesta / Timeout',
            'auth_error' => 'Error de Autenticación',
            'offline' => 'Inactivo / Apagado',
            default => ucfirst($this->last_poll_status ?: 'Sin sondeo'),
        };
    }
}
