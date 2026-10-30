<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoredSiteDevice extends Model
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
        'model',
        'serial',
        'ports',
        'notes',
        'normal_state_msg',
        'error_state_msg',
        'is_active',
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
            'telegram_alert_enabled' => 'boolean',
            'down_since' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'monitored_site_id');
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

