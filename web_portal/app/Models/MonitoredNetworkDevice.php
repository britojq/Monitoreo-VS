<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonitoredNetworkDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_number',
        'name',
        'ip',
        'mac',
        'vendor_data',
        'access_type',
        'access_port',
        'normal_state_msg',
        'error_state_msg',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'device_number' => 'integer',
            'access_port' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
