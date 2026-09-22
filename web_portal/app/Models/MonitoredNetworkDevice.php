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
        'ssh_username',
        'ssh_password_encrypted',
        'ssh_enable_secret_encrypted',
        'model',
        'serial',
        'ports',
        'notes',
        'normal_state_msg',
        'error_state_msg',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'monitored_site_id' => 'integer',
            'is_active' => 'boolean',
            'device_number' => 'integer',
            'access_port' => 'integer',
            'sort_order' => 'integer',
            'ssh_password_encrypted' => 'encrypted',
            'ssh_enable_secret_encrypted' => 'encrypted',
        ];
    }

    protected $hidden = [
        'ssh_password_encrypted',
        'ssh_enable_secret_encrypted',
    ];

    protected $appends = [
        'has_ssh_credentials',
    ];

    public function hasSshCredentials(): bool
    {
        return !empty($this->ssh_username) && !empty($this->ssh_password_encrypted);
    }

    public function getHasSshCredentialsAttribute(): bool
    {
        return $this->hasSshCredentials();
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
}
