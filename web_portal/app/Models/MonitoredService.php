<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonitoredService extends Model
{
    use HasFactory;

    protected $fillable = [
        'letter',
        'name',
        'type',
        'scope',
        'host_ip',
        'web_url',
        'port',
        'credentials',
        'check_interface',
        'dns_test_domain',
        'normal_state_msg',
        'error_state_msg',
        'is_active',
        'sort_order',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'port' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function histories()
    {
        return $this->hasMany(ServiceCheckHistory::class, 'monitored_service_id')->orderBy('checked_at', 'desc');
    }
}
