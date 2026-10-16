<?php

namespace App\Services\Ipam;

use App\Contracts\IpamGridInterface;
use App\Models\DiscoveredDevice;
use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;

/**
 * Servicio de Generación y Gestión Visual de Direcciones IP (IPAM Grid).
 * Responsabilidad Única (S): Mapeo de subredes, cálculo de ocupación y cruce de datos NOC + ITAM.
 */
class IpamGridService implements IpamGridInterface
{
    /**
     * Subredes oficiales conocidas por ID de Sede en la infraestructura.
     */
    protected array $siteSubnetsMap = [
        1 => '10.20.23.0/24',   // SEDE VALLE SECO (Edificio Central)
        2 => '10.20.23.0/24',   // CIAU VALLE SECO (Oficina Comercial)
        3 => '10.20.107.0/24',  // CIAU CONSOLIDADO
        4 => '10.20.107.0/24',  // CIAU PASEO MARIÑO
        5 => '10.20.106.0/24',  // CIAU MORON
        7 => '10.20.27.0/24',   // CORDINACION TRASMISION
    ];

    /**
     * Retorna la matriz completa de 256 celdas (red, broadcast y 254 hosts) para un segmento CIDR.
     */
    public function getSubnetGrid(string $cidr): array
    {
        $cleanCidr = trim($cidr) ?: '10.20.23.0/24';
        
        // Extraer prefijo de red /24 (ej: '10.20.23.')
        if (preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3})\.\d{1,3}(\/\d{1,2})?$/', $cleanCidr, $matches)) {
            $basePrefix = $matches[1] . '.';
            $normalizedCidr = $matches[1] . '.0/24';
        } else {
            $basePrefix = '10.20.23.';
            $normalizedCidr = '10.20.23.0/24';
        }

        // Cargar en bloque entidades de MariaDB (SSOT) pertenecientes a este prefijo
        $assets = InventoryAsset::with(['site', 'activeAssignment'])
            ->where('ip_address', 'like', "{$basePrefix}%")
            ->get()
            ->keyBy('ip_address');

        $netDevs = MonitoredNetworkDevice::with('site')
            ->where('ip', 'like', "{$basePrefix}%")
            ->get()
            ->keyBy('ip');

        $discovered = DiscoveredDevice::where('ip_address', 'like', "{$basePrefix}%")
            ->get()
            ->keyBy('ip_address');

        $cells = [];
        $officialUp = 0;
        $officialDown = 0;
        $rogueCount = 0;
        $freeCount = 0;

        for ($i = 0; $i <= 255; $i++) {
            $ip = "{$basePrefix}{$i}";

            if ($i === 0) {
                $cells[] = [
                    'octet' => 0,
                    'ip' => $ip,
                    'status' => 'network_id',
                    'state' => 'network_id',
                    'label' => 'Network ID',
                    'display_label' => 'Identificador de Red',
                    'badge' => 'Red',
                    'source' => 'reserved',
                    'is_reserved' => true,
                    'is_official' => false,
                    'is_rogue' => false,
                    'is_gateway' => false,
                    'title' => 'Dirección de Red (Reservada)',
                ];
                continue;
            }

            if ($i === 255) {
                $cells[] = [
                    'octet' => 255,
                    'ip' => $ip,
                    'status' => 'broadcast',
                    'state' => 'broadcast',
                    'label' => 'Broadcast',
                    'display_label' => 'Difusión Broadcast',
                    'badge' => 'Broadcast',
                    'source' => 'reserved',
                    'is_reserved' => true,
                    'is_official' => false,
                    'is_rogue' => false,
                    'is_gateway' => false,
                    'title' => 'Dirección de Broadcast (Reservada)',
                ];
                continue;
            }

            $asset = $assets->get($ip);
            $netDev = $netDevs->get($ip);
            $disc = $discovered->get($ip);

            // Caso 1: Dispositivo Oficial Registrado en ITAM o Monitoreo
            if ($asset || $netDev) {
                $isDown = false;
                if ($netDev && $netDev->last_check_status === 'DOWN') {
                    $isDown = true;
                } elseif ($asset && $asset->status === 'down') {
                    $isDown = true;
                }

                $status = $isDown ? 'official_down' : 'official_up';
                if ($isDown) {
                    $officialDown++;
                } else {
                    $officialUp++;
                }

                $primaryName = $asset?->name ?? $netDev?->name ?? 'Equipo Registrado';
                $siteName = $asset?->site?->name ?? $netDev?->site?->name ?? 'Sede no asignada';
                $mac = $asset?->mac_address ?? $netDev?->mac ?? ($disc?->mac_address);
                $type = $asset?->type ?? $netDev?->type ?? 'device';
                $responsable = $asset?->activeAssignment?->recipient_name
                    ?? $asset?->custodian_name
                    ?? 'No asignado';

                $cells[] = [
                    'octet' => $i,
                    'ip' => $ip,
                    'status' => $status,
                    'state' => $status,
                    'badge' => $isDown ? 'DOWN' : 'Registrado UP',
                    'source' => $asset ? 'inventory_asset' : 'network_device',
                    'is_reserved' => false,
                    'is_official' => true,
                    'is_rogue' => false,
                    'is_gateway' => $i === 1,
                    'name' => $primaryName, // Nombre Etiqueta visible (SSOT Regla #7)
                    'display_label' => $primaryName,
                    'asset_id' => $asset?->id,
                    'device_id' => $netDev?->id,
                    'mac' => $mac,
                    'type' => $type,
                    'site_name' => $siteName,
                    'responsable' => $responsable,
                    'title' => "{$primaryName} ({$ip}) • {$siteName}",
                ];
                continue;
            }

            // Caso 2: Detectado en red (ARP / Ping) pero NO registrado en Inventario (Rogue / Alerta)
            if ($disc) {
                $rogueCount++;
                $cells[] = [
                    'octet' => $i,
                    'ip' => $ip,
                    'status' => 'rogue',
                    'state' => 'rogue',
                    'badge' => 'No Catalogado',
                    'source' => 'discovered_rogue',
                    'is_reserved' => false,
                    'is_official' => false,
                    'is_rogue' => true,
                    'is_gateway' => false,
                    'name' => $disc->hostname ?: 'Equipo No Catalogado',
                    'display_label' => $disc->hostname ?: 'Equipo No Catalogado',
                    'hostname' => $disc->hostname ?: 'Equipo No Catalogado',
                    'mac' => $disc->mac_address,
                    'vendor' => $disc->vendor ?: 'Desconocido',
                    'detected_at' => $disc->last_seen_at?->format('d/m/Y H:i'),
                    'title' => "No Catalogado: {$disc->hostname} ({$ip})",
                ];
                continue;
            }

            // Caso 3: Dirección IP Disponible / Libre
            $freeCount++;
            $cells[] = [
                'octet' => $i,
                'ip' => $ip,
                'status' => 'free',
                'state' => 'free',
                'badge' => 'Libre',
                'source' => 'free',
                'is_reserved' => false,
                'is_official' => false,
                'is_rogue' => false,
                'is_gateway' => false,
                'name' => 'Disponible',
                'display_label' => 'Disponible',
                'title' => "Disponible: {$ip}",
            ];
        }

        $totalUsed = $officialUp + $officialDown + $rogueCount;
        $utilizationPct = round(($totalUsed / 254) * 100, 1);

        $stats = [
            'total' => 256,
            'total_hosts' => 254,
            'usable_capacity' => 254,
            'total_used' => $totalUsed,
            'occupied' => $totalUsed,
            'official_count' => $officialUp + $officialDown,
            'official_up_count' => $officialUp,
            'official_down_count' => $officialDown,
            'rogue' => $rogueCount,
            'rogue_count' => $rogueCount,
            'free' => $freeCount,
            'free_count' => $freeCount,
            'reserved' => 2,
            'utilization_pct' => $utilizationPct,
            'percent_used' => $utilizationPct,
        ];

        return [
            'cidr' => $normalizedCidr,
            'subnet' => $normalizedCidr,
            'network_ip' => $basePrefix . '0',
            'broadcast_ip' => $basePrefix . '255',
            'base_ip' => rtrim($basePrefix, '.'),
            'site' => [
                'name' => 'Sede Valle Seco',
            ],
            'metrics' => $stats,
            'stats' => $stats,
            'cells' => $cells,
        ];
    }

    /**
     * Retorna la lista de subredes corporativas conocidas derivadas de sedes oficiales en MariaDB.
     */
    public function getAvailableSubnets(): array
    {
        $sites = MonitoredSite::where('is_active', true)->orderBy('sort_order')->get();
        $subnets = [];
        $seenCidrs = [];

        foreach ($sites as $site) {
            $cidr = $this->siteSubnetsMap[$site->id] ?? null;

            if (!$cidr && !empty($site->ip) && filter_var($site->ip, FILTER_VALIDATE_IP)) {
                $cidr = preg_replace('/\.\d+$/', '.0/24', $site->ip);
            }

            if ($cidr && !in_array($cidr, $seenCidrs, true)) {
                $seenCidrs[] = $cidr;
                $subnets[] = [
                    'site_id' => $site->id,
                    'site_name' => $site->name,
                    'cidr' => $cidr,
                    'gateway_ip' => $site->ip ?: preg_replace('/\.0\/24$/', '.1', $cidr),
                ];
            }
        }

        // Subred principal por defecto asegurada
        if (!in_array('10.20.23.0/24', $seenCidrs, true)) {
            array_unshift($subnets, [
                'site_id' => 1,
                'site_name' => 'SEDE VALLE SECO (Edificio Central)',
                'cidr' => '10.20.23.0/24',
                'gateway_ip' => '10.20.23.1',
            ]);
        }

        return $subnets;
    }
}
