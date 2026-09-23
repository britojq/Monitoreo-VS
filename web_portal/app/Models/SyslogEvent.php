<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyslogEvent extends Model
{
    use HasFactory;

    protected $table = 'syslog_events';
    public $timestamps = false;

    protected $fillable = [
        'source_ip',
        'hostname',
        'facility',
        'severity',
        'program',
        'message',
        'raw_message',
        'received_at',
    ];

    protected $casts = [
        'facility' => 'integer',
        'severity' => 'integer',
        'received_at' => 'datetime',
    ];

    public function getSeverityLabelAttribute(): string
    {
        return match ($this->severity) {
            0 => 'Emergency',
            1 => 'Alert',
            2 => 'Critical',
            3 => 'Error',
            4 => 'Warning',
            5 => 'Notice',
            6 => 'Informational',
            7 => 'Debug',
            default => 'Unknown',
        };
    }

    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            0, 1, 2 => 'bg-rose-500/20 text-rose-400 border-rose-500/30',
            3 => 'bg-amber-500/20 text-amber-400 border-amber-500/30',
            4 => 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
            5, 6 => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
            7 => 'bg-slate-500/20 text-slate-400 border-slate-500/30',
            default => 'bg-slate-500/20 text-slate-400 border-slate-500/30',
        };
    }

    public function getFacilityLabelAttribute(): string
    {
        $facilities = [
            0 => 'kern', 1 => 'user', 2 => 'mail', 3 => 'daemon',
            4 => 'auth', 5 => 'syslog', 6 => 'lpr', 7 => 'news',
            8 => 'uucp', 9 => 'cron', 10 => 'authpriv', 11 => 'ftp',
            16 => 'local0', 17 => 'local1', 18 => 'local2', 19 => 'local3',
            20 => 'local4', 21 => 'local5', 22 => 'local6', 23 => 'local7',
        ];
        return $facilities[$this->facility] ?? "fac({$this->facility})";
    }
}
