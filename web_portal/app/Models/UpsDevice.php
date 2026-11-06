<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'inventory_asset_id',
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
        'group_alert_sent',
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
        'group_alert_sent' => 'boolean',
        'outage_since' => 'datetime',
        'last_seen_at' => 'datetime',
        'inventory_asset_id' => 'integer',
    ];

    public function inventoryAsset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'inventory_asset_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(UpsTelemetryHistory::class, 'ups_device_id')->orderBy('recorded_at', 'desc');
    }

    public function powerEvents(): HasMany
    {
        return $this->hasMany(UpsPowerEvent::class, 'ups_device_id')->orderBy('started_at', 'desc');
    }

    protected $appends = ['output_voltage_equipos', 'is_generic'];

    /**
     * Retorna verdadero si el UPS tiene asignada una etiqueta o modelo provisional genérico.
     */
    public function getIsGenericAttribute(): bool
    {
        $name = strtolower($this->name ?? '');
        $model = strtolower($this->model ?? '');
        return str_contains($name, 'nuevo ups') || str_contains($name, 'genérico') || str_contains($name, 'generico') || str_contains($model, 'genérico') || str_contains($model, 'generico') || str_contains($model, 'desconocido');
    }

    /**
     * Sincroniza y propaga en cascada la identidad del UPS hacia el inventario ITAM (Regla de Oro #8).
     */
    public function syncToInventoryPeripheral(): ?InventoryPeripheral
    {
        if (!$this->inventory_asset_id) {
            return null;
        }

        $asset = $this->inventoryAsset;
        if (!$asset) {
            return null;
        }

        $brand = 'Genérico';
        $model = $this->model ?? 'UPS Megatec';
        if (preg_match('/^([A-Za-z0-9]+)\s+(.+)$/', (string) $this->model, $matches)) {
            $brand = $matches[1];
            $model = $matches[2];
        } elseif (!empty($this->model)) {
            $brand = (string) $this->model;
        }

        // Buscar periférico tipo 'ups' asignado a este activo o crearlo
        $peripheral = InventoryPeripheral::firstOrNew([
            'current_asset_id' => $this->inventory_asset_id,
            'type' => 'ups',
        ]);

        if (!$peripheral->exists) {
            $peripheral->asset_tag = 'UPS-' . str_pad((string) (InventoryPeripheral::where('type', 'ups')->count() + 1), 5, '0', STR_PAD_LEFT);
            $peripheral->status = 'assigned';
            $peripheral->monitored_site_id = $asset->monitored_site_id;
        }

        $peripheral->brand = $brand;
        $peripheral->model = $model;
        $peripheral->serial_number = $this->serial_number;
        $peripheral->notes = "UPS de respaldo eléctrico de rack/servidor conectado por puerto serial {$this->serial_port}";
        $peripheral->save();

        return $peripheral;
    }

    /**
     * Retorna el voltaje de salida real entregado a los equipos (110V nominal).
     * En topologías 220V/208V bifásicas, la alimentación a racks se distribuye a 110V (Fase-Neutro / PDU).
     */
    public function getOutputVoltageEquiposAttribute(): float
    {
        $raw = (float) ($this->output_voltage ?? 208.0);
        return ($raw > 150.0) ? round($raw * (110.0 / 208.0), 1) : $raw;
    }
}
