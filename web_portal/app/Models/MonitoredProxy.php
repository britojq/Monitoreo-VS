<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonitoredProxy extends Model
{
    use HasFactory;

    protected $fillable = [
        'letter',
        'name',
        'ip_port',
        'auth_userpass',
        'test_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function histories()
    {
        return $this->hasMany(ProxyCheckHistory::class, 'monitored_proxy_id')->orderBy('checked_at', 'desc');
    }
}
