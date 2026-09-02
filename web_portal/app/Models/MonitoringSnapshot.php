<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonitoringSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'global_status',
        'services_online',
        'services_total',
        'sites_online',
        'sites_total',
        'proxies_online',
        'proxies_total',
        'payload_json',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'services_online' => 'integer',
            'services_total' => 'integer',
            'sites_online' => 'integer',
            'sites_total' => 'integer',
            'proxies_online' => 'integer',
            'proxies_total' => 'integer',
        ];
    }
}
