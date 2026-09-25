<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BannedIp extends Model
{
    use HasFactory;

    protected $table = 'banned_ips';

    protected $fillable = [
        'ip_address',
        'reason',
        'user_id',
        'banned_at',
    ];

    protected function casts(): array
    {
        return [
            'banned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected const WHITELIST_IPS = [
        '127.0.0.1',
        '::1',
        '10.20.23.221', // Servidor de Desarrollo (Esclavo)
        '10.20.23.252', // Servidor de Producción (Master)
        '10.20.23.1',   // Gateway Corporativo
    ];

    public static function isBanned(?string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        if (in_array($ip, self::WHITELIST_IPS, true) || str_starts_with($ip, '127.') || str_starts_with($ip, '10.20.23.')) {
            return false;
        }

        return self::where('ip_address', $ip)->exists();
    }
}
