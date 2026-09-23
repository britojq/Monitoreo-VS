<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HardwareLifecycle extends Model
{
    use HasFactory;

    protected $table = 'hardware_lifecycle';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'serial_number',
        'purchase_date',
        'warranty_end_date',
        'eol_date',
        'eos_date',
        'battery_last_replaced',
        'disk_health_status',
        'firmware_version',
        'firmware_latest',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_end_date' => 'date',
        'eol_date' => 'date',
        'eos_date' => 'date',
        'battery_last_replaced' => 'date',
    ];

    public function getWarrantyDaysRemainingAttribute(): ?int
    {
        if (!$this->warranty_end_date) {
            return null;
        }
        return (int) Carbon::now()->diffInDays($this->warranty_end_date, false);
    }

    public function getEolDaysRemainingAttribute(): ?int
    {
        if (!$this->eol_date) {
            return null;
        }
        return (int) Carbon::now()->diffInDays($this->eol_date, false);
    }

    public function getWarrantyStatusAttribute(): string
    {
        $days = $this->warranty_days_remaining;
        if ($days === null) {
            return 'Sin Registro';
        }
        if ($days < 0) {
            return 'Vencida';
        }
        if ($days <= 30) {
            return 'Por Vencer (' . $days . 'd)';
        }
        return 'Vigente (' . $days . 'd)';
    }

    public function getDiskHealthColorAttribute(): string
    {
        return match ($this->disk_health_status) {
            'ok' => 'text-emerald-400 bg-emerald-500/20 border-emerald-500/30',
            'warning' => 'text-amber-400 bg-amber-500/20 border-amber-500/30',
            'failing' => 'text-rose-400 bg-rose-500/20 border-rose-500/30',
            default => 'text-slate-400 bg-slate-500/20 border-slate-500/30',
        };
    }
}
