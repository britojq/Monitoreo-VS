<?php

declare(strict_types=1);

namespace App\Services\Deployment;

/**
 * Utilidad de comparación de pertenencia de direcciones IP a segmentos de red CIDR.
 */
class SubnetCidrMatcher
{
    /**
     * Determina si una dirección IPv4 pertenece a una subred CIDR.
     */
    public static function matches(string $ip, string $range): bool
    {
        $ip = trim($ip);
        $range = trim($range);
        if (empty($ip) || empty($range)) {
            return false;
        }

        if (!str_contains($range, '/')) {
            $range .= '/32';
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
