<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetRadarHost extends Model
{
    use HasFactory;

    protected $table = 'net_radar_hosts';

    protected $fillable = [
        'ip',
        'mac',
        'hostname',
        'vendor',
        'os_detected',
        'bytes_in',
        'bytes_out',
        'total_bytes',
        'packet_count',
        'is_local',
        'update_status',     // none, checking, downloading
        'last_update_type',  // windows_update, linux_repo
        'last_update_target',
        'update_bytes',
        'last_update_at',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'is_local' => 'boolean',
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
        'total_bytes' => 'integer',
        'packet_count' => 'integer',
        'update_bytes' => 'integer',
        'last_update_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Devuelve el total de bytes formateado para lectura humana (KB, MB, GB).
     */
    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    public function getFormattedTotalBytesAttribute(): string
    {
        return $this->formatBytes($this->total_bytes ?? 0);
    }

    public function getFormattedBytesInAttribute(): string
    {
        return $this->formatBytes($this->bytes_in ?? 0);
    }

    public function getFormattedBytesOutAttribute(): string
    {
        return $this->formatBytes($this->bytes_out ?? 0);
    }

    public function getFormattedUpdateBytesAttribute(): string
    {
        return $this->formatBytes($this->update_bytes ?? 0);
    }

    /**
     * Determina si el host fue visto recientemente (últimos 15 minutos).
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(15));
    }

    /**
     * Retorna eventos forenses asociados a esta IP.
     */
    public function events()
    {
        return $this->hasMany(NetRadarEvent::class, 'host_ip', 'ip')->latest('created_at');
    }
}
