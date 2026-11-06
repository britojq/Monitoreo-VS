<?php

declare(strict_types=1);

namespace App\Services\Reports\Evaluators;

use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Models\NetRadarHost;
use Carbon\Carbon;

/**
 * Evaluador de datos para el Catálogo de Direccionamiento IPAM (Subred 192.168.1.0/24).
 */
class IpamReportEvaluator
{
    /**
     * Retorna los datos normalizados de la Matriz IPAM (256 Bloques).
     */
    public function getIpamData(): array
    {
        $assetsByIp = InventoryAsset::whereNotNull('ip_address')->get()->keyBy('ip_address');
        $netDevicesByIp = MonitoredNetworkDevice::all()->keyBy('ip');
        $hostsByIp = NetRadarHost::all()->keyBy('ip');

        $rows = [];
        for ($i = 1; $i <= 254; $i++) {
            $ip = "192.168.1.{$i}";
            $asset = $assetsByIp->get($ip);
            $netDev = $netDevicesByIp->get($ip);
            $host = $hostsByIp->get($ip);

            $isOnline = false;
            $mac = 'N/A';
            $vendor = 'N/A';

            if ($host) {
                $isOnline = $host->last_seen_at ? Carbon::parse($host->last_seen_at)->gt(now()->subHours(24)) : true;
                $mac = $host->mac ?: $mac;
                $vendor = $host->vendor ?: $vendor;
            } elseif ($netDev) {
                $isOnline = (bool)$netDev->is_active;
                $mac = $netDev->mac ?: $mac;
                $vendor = $netDev->vendor_data ?: $vendor;
            } elseif ($asset) {
                $isOnline = $asset->status !== 'down';
                $mac = $asset->mac_address ?: $mac;
                $vendor = $asset->brand ?: $vendor;
            }

            $entityName = $asset?->name ?? ($netDev?->name ?? ($host?->hostname ?? null));
            $statusAssignment = 'Libre';
            if ($asset || $netDev) {
                $statusAssignment = 'Asignada (ITAM/NOC)';
            } elseif ($isOnline) {
                $statusAssignment = 'Host Activo (Sin ITAM)';
            }

            $authStatus = ($asset || $netDev) ? 'Autorizado' : ($isOnline ? 'Alerta Rogue / No Censado' : 'Disponible');

            if ($asset || $netDev || $isOnline) {
                $rows[] = [
                    'ip_address' => $ip,
                    'ping_status' => $isOnline ? 'Activo (En Línea)' : 'Inactivo',
                    'assignment_type' => $statusAssignment,
                    'entity_name' => $entityName ?: 'Desconocido',
                    'mac_address' => $mac,
                    'vendor' => $vendor,
                    'auth_status' => $authStatus,
                    'last_seen' => $isOnline ? 'En línea' : 'Desconectado',
                ];
            }
        }

        return [
            'title' => 'Catálogo de Direccionamiento IPAM • Subred 192.168.1.0/24',
            'headers' => [
                'Dirección IP',
                'Estado Ping/ARP',
                'Tipo de Asignación',
                'Entidad / Nombre Etiqueta',
                'Dirección MAC',
                'Fabricante / OUI',
                'Estatus de Autorización',
                'Última Detección',
            ],
            'rows' => $rows,
            'summary' => [
                'Segmento de Red' => '192.168.1.0/24 (Valle Seco)',
                'Total Hosts Censados' => count($rows),
                'Fecha de Generación' => now()->format('d/m/Y H:i:s'),
            ],
        ];
    }
}
