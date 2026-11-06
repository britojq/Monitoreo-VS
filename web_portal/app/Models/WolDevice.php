<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WolDevice extends Model
{
    use HasFactory;

    protected $table = 'wol_devices';

    protected $fillable = [
        'name',
        'mac_address',
        'ip_address',
        'broadcast_address',
        'site_id',
        'discovered_device_id',
        'is_enabled',
        'last_woken_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_woken_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'site_id');
    }

    public function discoveredDevice(): BelongsTo
    {
        return $this->belongsTo(DiscoveredDevice::class, 'discovered_device_id');
    }

    /**
     * Formatea la dirección MAC en formato estándar XX:XX:XX:XX:XX:XX
     */
    public function getFormattedMacAttribute(): string
    {
        $clean = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $this->mac_address));
        return implode(':', str_split($clean, 2));
    }
}
