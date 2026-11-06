<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeviceConfiguration extends Model
{
    use HasFactory;

    protected $table = 'device_configurations';

    protected $fillable = [
        'network_device_id',
        'snmp_device_id',
        'device_name',
        'device_ip',
        'device_type',
        'config_text',
        'config_hash',
        'config_size_bytes',
        'captured_at',
        'captured_by',
        'status',
        'error_message',
        'notes',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'config_size_bytes' => 'integer',
    ];

    /**
     * Relación con el dispositivo de red tradicional.
     */
    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(MonitoredNetworkDevice::class, 'network_device_id');
    }

    /**
     * Relación con el dispositivo SNMP.
     */
    public function snmpDevice(): BelongsTo
    {
        return $this->belongsTo(SnmpDevice::class, 'snmp_device_id');
    }

    /**
     * Historial de cambios asociados a esta versión.
     */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(ConfigChangeLog::class, 'device_configuration_id');
    }

    /**
     * Último log de cambio asociado.
     */
    public function latestChangeLog(): HasOne
    {
        return $this->hasOne(ConfigChangeLog::class, 'device_configuration_id')->latestOfMany('detected_at');
    }

    /**
     * Scope para respaldos exitosos.
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope para ordenar por captura reciente.
     */
    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderBy('captured_at', 'desc')->limit($limit);
    }

    /**
     * Formateo legible del tamaño de archivo.
     */
    public function getSizeFormattedAttribute(): string
    {
        $bytes = $this->config_size_bytes ?? strlen($this->config_text ?? '');
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Huella hash acortada para tablas.
     */
    public function getShortHashAttribute(): string
    {
        return substr($this->config_hash ?? '', 0, 12);
    }

    /**
     * Conteo de líneas de la configuración.
     */
    public function getLineCountAttribute(): int
    {
        if (empty($this->config_text)) {
            return 0;
        }
        return substr_count($this->config_text, "\n") + 1;
    }

    /**
     * Metadatos visuales del tipo de dispositivo.
     */
    public function getDeviceTypeBadgeAttribute(): array
    {
        return match ($this->device_type) {
            'cisco_switch' => [
                'label' => 'Switch Cisco',
                'class' => 'bg-cyan-500/10 border-cyan-500/30 text-cyan-300',
            ],
            'cisco_router' => [
                'label' => 'Router Cisco',
                'class' => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300',
            ],
            'pfsense' => [
                'label' => 'pfSense Firewall',
                'class' => 'bg-amber-500/10 border-amber-500/30 text-amber-300',
            ],
            'linux_server' => [
                'label' => 'Servidor Linux',
                'class' => 'bg-purple-500/10 border-purple-500/30 text-purple-300',
            ],
            default => [
                'label' => ucfirst(str_replace('_', ' ', $this->device_type ?? 'Equipo')),
                'class' => 'bg-slate-800 border-slate-700 text-slate-300',
            ],
        };
    }

    /**
     * Nombre descriptivo consolidado del equipo.
     */
    public function getResolvedNameAttribute(): string
    {
        if (!empty($this->device_name)) {
            return $this->device_name;
        }
        if ($this->snmpDevice) {
            return $this->snmpDevice->name;
        }
        if ($this->networkDevice) {
            return $this->networkDevice->name;
        }
        return $this->device_ip ?? 'Dispositivo desconocido';
    }

    /**
     * Etiqueta en español para estado del respaldo
     */
    public function getStatusLabelAttribute(): string
    {
        return match (strtolower($this->status ?? '')) {
            'success' => 'Respaldado con Éxito',
            'failed' => 'Falla de Respaldo',
            'pending' => 'Pendiente',
            'in_progress' => 'En Progreso',
            default => ucfirst($this->status ?? 'Indefinido'),
        };
    }
}
