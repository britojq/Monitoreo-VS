<?php

namespace App\Services\Glpi;

/**
 * Normalizador de Datos de Hardware y Telemetría GLPI.
 * Responsabilidad Única (S): Homologación de capacidades, arquitectura de SO, interfaces de red e identificación de equipos.
 */
class GlpiHardwareNormalizer
{
    /**
     * Normaliza los diferentes dialectos de GLPI Agent (content vs root) en una estructura homogénea.
     */
    public function normalize(array $parsed, string $fallbackIp): array
    {
        $content = $parsed['content'] ?? $parsed;

        $hw = $content['hardware'] ?? $content['HARDWARE'] ?? [];
        $bios = $content['bios'] ?? $content['BIOS'] ?? [];
        $networks = $content['networks'] ?? $content['NETWORKS'] ?? [];
        $cpus = $content['cpus'] ?? $content['CPUS'] ?? [];
        $memories = $content['memories'] ?? $content['MEMORIES'] ?? [];
        $drives = !empty($content['storages']) ? $content['storages'] : (!empty($content['STORAGES']) ? $content['STORAGES'] : ($content['drives'] ?? $content['DRIVES'] ?? []));
        $monitors = $content['monitors'] ?? $content['MONITORS'] ?? [];
        $printers = $content['printers'] ?? $content['PRINTERS'] ?? [];

        // Normalizar arrays si vienen unitarios
        if (isset($networks['MAC']) || isset($networks['mac'])) {
            $networks = [$networks];
        }
        if (isset($cpus['name']) || isset($cpus['NAME'])) {
            $cpus = [$cpus];
        }
        if (isset($memories['capacity']) || isset($memories['CAPACITY'])) {
            $memories = [$memories];
        }
        if (isset($drives['model']) || isset($drives['MODEL'])) {
            $drives = [$drives];
        }
        if (isset($monitors['caption']) || isset($monitors['CAPTION']) || isset($monitors['serial'])) {
            $monitors = [$monitors];
        }
        if (isset($printers['name']) || isset($printers['NAME'])) {
            $printers = [$printers];
        }

        // Nombre
        $name = $hw['name'] ?? $hw['NAME'] ?? $content['deviceid'] ?? 'GLPI-DISCOVERED-' . substr(md5(uniqid()), 0, 6);

        // Serial
        $serial = $bios['ssn'] ?? $bios['SSN'] ?? $hw['uuid'] ?? $hw['UUID'] ?? null;
        if ($this->isGenericSerial($serial)) {
            $serial = null;
        }

        // Asset Tag (código patrimonial en BIOS si existe)
        $assetTag = $bios['assettag'] ?? $bios['ASSETTAG'] ?? null;
        if ($this->isGenericSerial($assetTag)) {
            $assetTag = null;
        }

        // Detalle de ubicación o descripción de equipo
        $description = !empty($hw['description']) ? trim((string)$hw['description']) : (!empty($hw['DESCRIPTION']) ? trim((string)$hw['DESCRIPTION']) : null);

        // Multi-NIC: MAC, IP e interfaces completas con puntuación inteligente
        $normalizedNetworks = [];
        $primaryMac = null;
        $primaryIp = null;
        $bestScore = -999;
        $bestIndex = -1;

        foreach ($networks as $net) {
            $mac = $net['mac'] ?? $net['MAC'] ?? null;
            $ip = $net['ipaddress'] ?? $net['IPADDRESS'] ?? null;
            $mask = $net['ipmask'] ?? $net['IPMASK'] ?? $net['ipsubnet'] ?? $net['IPSUBNET'] ?? null;
            $gateway = $net['ipgateway'] ?? $net['IPGATEWAY'] ?? null;
            $dns = $net['ipdhcp'] ?? $net['IPDHCP'] ?? null;
            $status = strtolower((string)($net['status'] ?? $net['STATUS'] ?? 'up'));
            $desc = trim((string)($net['description'] ?? $net['DESCRIPTION'] ?? ''));
            $speed = $net['speed'] ?? $net['SPEED'] ?? null;
            $type = $net['type'] ?? $net['TYPE'] ?? null;

            if (empty($mac) || str_starts_with(strtolower($mac), '00:00:00') || $mac === 'N/A') {
                continue;
            }

            $score = 0;
            if ($status === 'up') {
                $score += 100;
            }

            if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                if ($ip === '127.0.0.1' || str_starts_with($ip, '127.')) {
                    $score -= 1000;
                } elseif (str_starts_with($ip, '10.20.')) {
                    $score += 80; // Red corporativa preferente
                } elseif (str_starts_with($ip, '10.')) {
                    $score += 60;
                } elseif (str_starts_with($ip, '172.')) {
                    $score += 40;
                } elseif (str_starts_with($ip, '192.168.')) {
                    $score += 30;
                } elseif (str_starts_with($ip, '100.')) {
                    $score -= 20; // VPN / Tailscale
                }
            } else {
                $ip = null;
            }

            $lowerDesc = strtolower($desc);
            if (str_contains($lowerDesc, 'eth') || str_contains($lowerDesc, 'enp') || str_contains($lowerDesc, 'eno') || str_contains($lowerDesc, 'ethernet')) {
                $score += 25;
                $ifType = 'ethernet';
            } elseif (str_contains($lowerDesc, 'wl') || str_contains($lowerDesc, 'wifi')) {
                $score += 10;
                $ifType = 'wifi';
            } elseif (str_contains($lowerDesc, 'tailscale') || str_contains($lowerDesc, 'tun') || str_contains($lowerDesc, 'tap') || str_contains($lowerDesc, 'docker') || str_contains($lowerDesc, 'veth')) {
                $score -= 50;
                $ifType = 'virtual';
            } elseif (str_contains($lowerDesc, 'loopback') || str_contains($lowerDesc, 'lo')) {
                $score -= 100;
                $ifType = 'loopback';
            } else {
                $ifType = $type ?: 'ethernet';
            }

            $cleanMac = strtoupper($mac);
            $ifName = $desc ?: ($net['name'] ?? $net['NAME'] ?? ('eth' . count($normalizedNetworks)));

            $normalizedNetworks[] = [
                'interface_name' => $ifName,
                'mac_address' => $cleanMac,
                'ip_address' => $ip,
                'subnet_mask' => $mask,
                'gateway' => $gateway,
                'dns' => $dns,
                'interface_type' => $ifType,
                'speed' => $speed ? (string)$speed : null,
                'status' => $status,
                'score' => $score,
                'is_primary' => false,
            ];

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = count($normalizedNetworks) - 1;
                $primaryMac = $cleanMac;
                if (!empty($ip)) {
                    $primaryIp = $ip;
                }
            }
        }

        if ($bestIndex >= 0 && isset($normalizedNetworks[$bestIndex])) {
            $normalizedNetworks[$bestIndex]['is_primary'] = true;
        }

        $ipAddress = $primaryIp ?: ($fallbackIp !== '127.0.0.1' ? $fallbackIp : null);

        if ($bestIndex >= 0 && !empty($ipAddress) && empty($normalizedNetworks[$bestIndex]['ip_address'])) {
            $normalizedNetworks[$bestIndex]['ip_address'] = $ipAddress;
        }

        // Marca y Modelo
        $brand = $bios['smanufacturer'] ?? $bios['SMANUFACTURER'] ?? $hw['manufacturer'] ?? null;
        $model = $bios['smodel'] ?? $bios['SMODEL'] ?? $hw['model'] ?? null;

        // Sistema Operativo (priorizar sección operatingsystem moderna)
        $osSection = $content['operatingsystem'] ?? $content['OPERATINGSYSTEM'] ?? [];
        $osFullName = $osSection['full_name'] ?? $osSection['name'] ?? null;
        $osName = $osFullName ?: ($hw['osname'] ?? $hw['OSNAME'] ?? '');
        $osVersion = $osSection['version'] ?? $hw['osversion'] ?? $hw['OSVERSION'] ?? '';
        $osArch = $osSection['arch'] ?? '';

        $os = trim("{$osName} {$osVersion}");
        if ($osArch && !str_contains($os, $osArch)) {
            $os .= " ({$osArch})";
        }
        $hasRealOs = !empty($os);
        $os = $os ?: 'Linux / Windows (GLPI Agent)';
        $kernel = $osSection['kernel_version'] ?? $hw['oscomments'] ?? $hw['OSCOMMENTS'] ?? null;

        // Heurística de tipo (laptop, server, desktop, printer)
        $chassis = strtolower((string)($hw['chassis_type'] ?? $hw['CHASSIS_TYPE'] ?? ''));
        $type = $content['type'] ?? $hw['type'] ?? 'desktop';
        if ($type === 'desktop') {
            if (str_contains($chassis, 'laptop') || str_contains($chassis, 'notebook') || str_contains(strtolower($name), 'lap') || str_contains(strtolower($name), 'nb')) {
                $type = 'laptop';
            } elseif (str_contains($chassis, 'server') || str_contains(strtolower($osName), 'server')) {
                $type = 'server';
            }
        }

        $memoryTotal = (int)($hw['memory'] ?? $hw['MEMORY'] ?? 0);
        $hasStorages = !empty($content['storages']) || !empty($content['STORAGES']);

        return [
            'name' => $name,
            'serial_number' => $serial,
            'asset_tag' => $assetTag,
            'location_detail' => $description,
            'mac_address' => $primaryMac,
            'ip_address' => $ipAddress,
            'network_interfaces' => $normalizedNetworks,
            'brand' => $brand,
            'model' => $model,
            'type' => $type,
            'operating_system' => $os,
            'has_real_os' => $hasRealOs,
            'os_kernel' => $kernel,
            'cpus' => $cpus,
            'memories' => $memories,
            'memory_total' => $memoryTotal,
            'drives' => $drives,
            'has_storages' => $hasStorages,
            'monitors' => $monitors,
            'printers' => $printers,
        ];
    }

    /**
     * Filtra cadenas genéricas devueltas por BIOS virtuales o sin configurar.
     */
    public function isGenericSerial(?string $serial): bool
    {
        if (empty($serial)) {
            return true;
        }

        $s = strtolower(trim($serial));
        if (preg_match('/^0+$/', $s)) {
            return true;
        }

        $generics = [
            'to be filled by o.e.m.',
            'default string',
            'none',
            'system serial number',
            'chassis serial number',
            '0123456789',
            '1234567890',
            'n/a',
            'unknown',
        ];

        return in_array($s, $generics, true);
    }
}
