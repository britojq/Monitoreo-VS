<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpsTelemetryHistory extends Model
{
    use HasFactory;

    protected $table = 'ups_telemetry_histories';
    public $timestamps = false;

    protected $fillable = [
        'ups_device_id',
        'input_voltage',
        'output_voltage',
        'load_percent',
        'battery_percent',
        'battery_voltage',
        'temperature_c',
        'is_on_battery',
        'is_battery_low',
        'is_bypass',
        'recorded_at',
    ];

    protected $casts = [
        'input_voltage' => 'float',
        'output_voltage' => 'float',
        'load_percent' => 'integer',
        'battery_percent' => 'integer',
        'battery_voltage' => 'float',
        'temperature_c' => 'float',
        'is_on_battery' => 'boolean',
        'is_battery_low' => 'boolean',
        'is_bypass' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(UpsDevice::class, 'ups_device_id');
    }

    protected $appends = ['output_voltage_equipos'];

    /**
     * Retorna el voltaje de salida real entregado a los equipos (110V nominal).
     */
    public function getOutputVoltageEquiposAttribute(): float
    {
        $raw = (float) ($this->output_voltage ?? 208.0);
        return ($raw > 150.0) ? round($raw * (110.0 / 208.0), 1) : $raw;
    }
}
