<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetRadarSnapshot extends Model
{
    use HasFactory;

    protected $table = 'net_radar_snapshots';

    public $timestamps = false;

    protected $fillable = [
        'total_hosts',
        'active_hosts',
        'windows_updating_hosts',
        'linux_updating_hosts',
        'total_bytes_in',
        'total_bytes_out',
        'top_protocols',
        'top_talkers',
        'created_at',
    ];

    protected $casts = [
        'total_hosts' => 'integer',
        'active_hosts' => 'integer',
        'windows_updating_hosts' => 'integer',
        'linux_updating_hosts' => 'integer',
        'total_bytes_in' => 'integer',
        'total_bytes_out' => 'integer',
        'top_protocols' => 'array',
        'top_talkers' => 'array',
        'created_at' => 'datetime',
    ];

    public static function formatBytes(int $bytes): string
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

    public function getFormattedTotalBytesInAttribute(): string
    {
        return self::formatBytes($this->total_bytes_in ?? 0);
    }

    public function getFormattedTotalBytesOutAttribute(): string
    {
        return self::formatBytes($this->total_bytes_out ?? 0);
    }
}
