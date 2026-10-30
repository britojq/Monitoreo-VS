<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetRadarEvent extends Model
{
    use HasFactory;

    protected $table = 'net_radar_events';

    public $timestamps = false;

    protected $fillable = [
        'host_ip',
        'event_type',       // windows_update, linux_repo, high_bandwidth, suspicious_traffic, new_host
        'severity',         // info, warning, critical
        'target_domain',
        'bytes_transferred',
        'description',
        'created_at',
    ];

    protected $casts = [
        'bytes_transferred' => 'integer',
        'created_at' => 'datetime',
    ];

    public function host()
    {
        return $this->belongsTo(NetRadarHost::class, 'host_ip', 'ip');
    }

    public function getFormattedBytesAttribute(): string
    {
        $bytes = $this->bytes_transferred ?? 0;
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
}
