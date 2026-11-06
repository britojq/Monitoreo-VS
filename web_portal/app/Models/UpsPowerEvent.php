<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpsPowerEvent extends Model
{
    use HasFactory;

    protected $table = 'ups_power_events';

    protected $fillable = [
        'ups_device_id',
        'event_type',
        'started_at',
        'ended_at',
        'duration_seconds',
        'input_voltage',
        'status',
        'notified_group',
        'notified_target',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'ups_device_id' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
            'input_voltage' => 'float',
            'notified_group' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UpsDevice::class, 'ups_device_id');
    }

    /**
     * Retorna una representación legible del tiempo en batería.
     */
    public function getDurationLabelAttribute(): string
    {
        $sec = (int) ($this->duration_seconds ?? 0);
        if ($sec <= 0 && $this->status === 'ongoing') {
            $sec = (int) now()->diffInSeconds($this->started_at);
        }

        if ($sec < 60) {
            return "{$sec} seg";
        }

        $min = intdiv($sec, 60);
        $remSec = $sec % 60;
        if ($min < 60) {
            return $remSec > 0 ? "{$min} min {$remSec} seg" : "{$min} min";
        }

        $hours = intdiv($min, 60);
        $remMin = $min % 60;
        return "{$hours} h {$remMin} min";
    }

    /**
     * Etiqueta humana para el tipo de evento.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->event_type) {
            'outage' => 'Falla de Servicio Eléctrico (>45s)',
            default => 'Micro-Fluctuación de Voltaje (≤45s)',
        };
    }
}
