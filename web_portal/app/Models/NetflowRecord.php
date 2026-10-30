<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetflowRecord extends Model
{
    use HasFactory;

    protected $table = 'netflow_records';
    public $timestamps = false;

    protected $fillable = [
        'exporter_ip',
        'src_ip',
        'dst_ip',
        'src_port',
        'dst_port',
        'protocol',
        'bytes',
        'packets',
        'direction',
        'window_start',
        'window_end',
    ];

    protected $casts = [
        'protocol' => 'integer',
        'bytes' => 'integer',
        'packets' => 'integer',
        'window_start' => 'datetime',
        'window_end' => 'datetime',
    ];

    public function getProtocolNameAttribute(): string
    {
        return match ($this->protocol) {
            6 => 'TCP',
            17 => 'UDP',
            1 => 'ICMP',
            default => "Proto({$this->protocol})",
        };
    }
}
