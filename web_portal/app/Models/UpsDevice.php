<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UpsDevice extends Model
{
    use HasFactory;

    protected $table = 'ups_devices';

    protected $fillable = [
        'name',
        'model',
        'serial_number',
        'serial_port',
        'baud_rate',
        'rating_voltage',
        'rating_current',
        'rating_battery_voltage',
        'rating_frequency',
        'firmware_version',
        'input_voltage',
        'input_fault_voltage',
        'output_voltage',
        'load_percent',
        'frequency',
        'battery_voltage',
        'battery_percent',
        'temperature_c',
        'is_online',
        'is_on_battery',
        'is_battery_low',
        'is_bypass',
        'is_ups_failed',
        'beeper_on',
        'telegram_alert_enabled',
        'telegram_alert_target',
        'last_alert_state',
        'outage_since',
        'last_seen_at',
    ];

    protected $casts = [
        'rating_voltage' => 'float',
        'rating_current' => 'float',
        'rating_battery_voltage' => 'float',
        'rating_frequency' => 'float',
        'input_voltage' => 'float',
        'input_fault_voltage' => 'float',
        'output_voltage' => 'float',
        'load_percent' => 'integer',
        'frequency' => 'float',
        'battery_voltage' => 'float',
        'battery_percent' => 'integer',
        'temperature_c' => 'float',
        'is_online' => 'boolean',
        'is_on_battery' => 'boolean',
        'is_battery_low' => 'boolean',
        'is_bypass' => 'boolean',
        'is_ups_failed' => 'boolean',
        'beeper_on' => 'boolean',
        'telegram_alert_enabled' => 'boolean',
        'outage_since' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function histories(): HasMany
    {
        return $this->hasMany(UpsTelemetryHistory::class, 'ups_device_id')->orderBy('recorded_at', 'desc');
    }
}
