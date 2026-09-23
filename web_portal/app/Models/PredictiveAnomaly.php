<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PredictiveAnomaly extends Model
{
    use HasFactory;

    protected $table = 'predictive_anomalies';
    public $timestamps = false;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'anomaly_type',
        'metric_name',
        'confidence',
        'description',
        'predicted_impact',
        'detected_at',
        'acknowledged',
    ];

    protected $casts = [
        'confidence' => 'float',
        'acknowledged' => 'boolean',
        'detected_at' => 'datetime',
    ];

    public function getAnomalyLabelAttribute(): string
    {
        return match ($this->anomaly_type) {
            'trend_upward' => 'Tendencia Alcista Crítica',
            'seasonal_pattern' => 'Patrón Inusual / Cíclico',
            'correlation' => 'Correlación Anómala',
            'outlier' => 'Desviación Estadística (> 3σ)',
            default => 'Anomalía',
        };
    }

    public function getAnomalyBadgeColorAttribute(): string
    {
        return match ($this->anomaly_type) {
            'trend_upward' => 'bg-rose-500/20 text-rose-400 border-rose-500/30',
            'outlier' => 'bg-amber-500/20 text-amber-400 border-amber-500/30',
            'seasonal_pattern' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
            default => 'bg-cyan-500/20 text-cyan-400 border-cyan-500/30',
        };
    }
}
