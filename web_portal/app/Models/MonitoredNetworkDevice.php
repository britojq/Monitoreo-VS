<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoredNetworkDevice extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'monitored_site_id',
        'device_number',
        'name',
        'ip',
        'mac',
        'vendor_data',
        'access_type',
        'access_port',
        'ssh_port',
        'vnc_port',
        'web_port',
        'telnet_port',
        'ssh_username',
        'ssh_password_encrypted',
        'ssh_enable_secret_encrypted',
        'vnc_password_encrypted',
        'model',
        'serial',
        'ports',
        'notes',
        'normal_state_msg',
        'error_state_msg',
        'is_active',
        'sort_order',
        'telegram_alert_enabled',
        'telegram_alert_target',
        'last_alert_state',
        'down_since',
    ];

    protected function casts(): array
    {
        return [
            'monitored_site_id' => 'integer',
            'is_active' => 'boolean',
            'device_number' => 'integer',
            'access_port' => 'integer',
            'ssh_port' => 'integer',
            'vnc_port' => 'integer',
            'web_port' => 'integer',
            'telnet_port' => 'integer',
            'sort_order' => 'integer',
            'ssh_password_encrypted' => 'encrypted',
            'ssh_enable_secret_encrypted' => 'encrypted',
            'vnc_password_encrypted' => 'encrypted',
            'telegram_alert_enabled' => 'boolean',
            'down_since' => 'datetime',
        ];
    }

    protected $hidden = [
        'ssh_password_encrypted',
        'ssh_enable_secret_encrypted',
        'vnc_password_encrypted',
    ];

    protected $appends = [
        'has_ssh_credentials',
        'has_vnc_credentials',
        'ssh_port_resolved',
        'vnc_port_resolved',
        'web_port_resolved',
        'telnet_port_resolved',
    ];

    public function hasSshCredentials(): bool
    {
        return !empty($this->ssh_username) && !empty($this->ssh_password_encrypted);
    }

    public function getHasSshCredentialsAttribute(): bool
    {
        return $this->hasSshCredentials();
    }

    public function getHasVncCredentialsAttribute(): bool
    {
        return !empty($this->vnc_password_encrypted);
    }

    public function getSshPortResolvedAttribute(): int
    {
        return $this->ssh_port ?: (($this->access_type === 'SSH' || $this->access_type === 'SSH+VNC') ? ($this->access_port ?: 22) : 22);
    }

    public function getVncPortResolvedAttribute(): int
    {
        return $this->vnc_port ?: (($this->access_type === 'VNC' || $this->access_type === 'SSH+VNC') ? ($this->access_port ?: 5900) : 5900);
    }

    public function getWebPortResolvedAttribute(): int
    {
        return $this->web_port ?: ($this->access_type === 'WEB' ? ($this->access_port ?: 80) : 80);
    }

    public function getTelnetPortResolvedAttribute(): int
    {
        return $this->telnet_port ?: ($this->access_type === 'TELNET' ? ($this->access_port ?: 23) : 23);
    }

    public function hasSsh(): bool
    {
        return in_array($this->access_type, ['SSH', 'SSH+VNC']) || !empty($this->ssh_port);
    }

    public function hasVnc(): bool
    {
        return in_array($this->access_type, ['VNC', 'SSH+VNC']) || !empty($this->vnc_port);
    }

    public function hasWeb(): bool
    {
        return $this->access_type === 'WEB' || !empty($this->web_port);
    }

    public function hasTelnet(): bool
    {
        return $this->access_type === 'TELNET' || !empty($this->telnet_port);
    }

    public function site(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'monitored_site_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(NetworkDeviceCheckHistory::class, 'monitored_network_device_id');
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(DeviceConfiguration::class, 'network_device_id');
    }

    public function latestConfiguration(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DeviceConfiguration::class, 'network_device_id')->latestOfMany('captured_at');
    }

    /**
     * Identidad operativa principal (Nombre Etiqueta)
     */
    public function getDisplayIdentityAttribute(): string
    {
        if (!empty($this->normal_state_msg)) {
            $clean = trim(preg_replace('/^[✅❌⚠️\s\-]+/u', '', $this->normal_state_msg));
            if (!empty($clean)) {
                return $clean;
            }
        }
        return $this->name;
    }

    /**
     * Subtítulo técnico (Nombre de Equipo / Hostname)
     */
    public function getTechnicalSubtitleAttribute(): ?string
    {
        $display = $this->display_identity;
        if (!empty($this->notes) && preg_match('/(?:Hostname|NetBIOS):\s*([^\s\|]+)/i', $this->notes, $matches)) {
            return $matches[1];
        }
        if (!empty($this->name) && $this->name !== $display) {
            return $this->name;
        }
        return null;
    }
}

