<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetflowTopTalker extends Model
{
    use HasFactory;

    protected $table = 'netflow_top_talkers';
    public $timestamps = false;

    protected $fillable = [
        'window_start',
        'window_end',
        'rank_type',
        'rank_value',
        'bytes',
        'packets',
        'percentage',
    ];

    protected $casts = [
        'bytes' => 'integer',
        'packets' => 'integer',
        'percentage' => 'float',
        'window_start' => 'datetime',
        'window_end' => 'datetime',
    ];

    public function getFormattedBytesAttribute(): string
    {
        $bytes = $this->bytes;
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
