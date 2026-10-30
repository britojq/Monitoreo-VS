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
        '192.168.1.220', // Servidor de Desarrollo (Esclavo)
        '192.168.1.250', // Servidor de Producción (Master)
        '192.168.1.1',   // Gateway Corporativo
    ];

    public static function isBanned(?string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        if (in_array($ip, self::WHITELIST_IPS, true) || str_starts_with($ip, '127.') || str_starts_with($ip, '192.168.1.')) {
            return false;
        }

        return self::where('ip_address', $ip)->exists();
    }
}
