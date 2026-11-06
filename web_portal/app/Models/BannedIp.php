<?php

declare(strict_types=1);

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

    /**
     * Obtiene la lista blanca de direcciones IP autorizadas desde la configuración.
     * Cumple con OWASP A05:2021 externalizando IPs para no exponer infraestructura en código.
     *
     * @return array<int, string>
     */
    public static function getWhitelistIps(): array
    {
        return (array) config('security.whitelist_ips', ['127.0.0.1', '::1']);
    }

    /**
     * Determina si una dirección IP específica se encuentra bloqueada.
     */
    public static function isBanned(?string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        $whitelist = static::getWhitelistIps();
        if (in_array($ip, $whitelist, true) || str_starts_with($ip, '127.')) {
            return false;
        }

        return self::where('ip_address', $ip)->exists();
    }
}
