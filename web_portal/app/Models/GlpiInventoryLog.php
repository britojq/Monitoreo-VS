<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlpiInventoryLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ip_address',
        'user_agent',
        'device_name',
        'serial_number',
        'mac_address',
        'payload_format',
        'raw_payload',
        'processed',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'processed' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
