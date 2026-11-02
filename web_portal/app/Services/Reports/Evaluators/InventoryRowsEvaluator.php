<?php

declare(strict_types=1);

namespace App\Services\Reports\Evaluators;

use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;

/**
 * Evaluador y extractor de filas tabulares normalizadas de inventario de equipos.
 */
class InventoryRowsEvaluator
{
    /**
     * Retorna los registros normalizados de Servidores (formato Centro de Datos de 17 columnas).
     */
    public function getServerRows(): array
    {
        $serverAssets = InventoryAsset::with(['site', 'components'])
            ->where(function ($q) {
                $q->where('type', 'server')
                    ->orWhere('name', 'like', '%Servidor%')
                    ->orWhere('name', 'like', '%MONITOREO ATIT%')
                    ->orWhere('name', 'like', '%MONITOREO-DEV%');
            })
            ->whereNotIn('type', ['switch', 'router'])
            ->get();

        $rows = [];
        $seenIps = [];

        foreach ($serverAssets as $a) {
            $ram = $a->components->where('type', 'ram')->first()?->capacity ?: '16 GB';
            $disk = $a->components->where('type', 'disk')->first()?->capacity ?: '1 TB';
            $cpu = $a->components->where('type', 'cpu')->first()?->name ?: 'INTEL CORE I7';

            $rows[] = [
                'rack' => $a->location_detail ?: 'RACK 01',
                'brand' => $a->brand ?: 'HP',
                'model' => $a->model ?: 'N/A',
                'serial' => $a->serial_number ?: 'N/A',
                'year' => $a->purchase_date ? $a->purchase_date->format('Y') : '2018',
                'ram' => $ram,
                'disk' => $disk,
                'os' => $a->operating_system ?: 'LINUX / DEBIAN',
                'physical' => 'SI',
                'virtualized' => 'NO',
                'hardware' => 'SERVIDOR',
                'cpu' => $cpu,
                'services' => $a->notes ?: 'MONITOREO / SERVICIOS NOC',
                'ip' => $a->ip_address ?: 'N/A',
                'mac' => $a->mac_address ?: 'N/A',
                'status' => $a->status_label,
                'admin' => $a->custodian_name ?: 'ADMINISTRADOR DE RED',
            ];
            if ($a->ip_address) {
                $seenIps[$a->ip_address] = true;
            }
        }

        $netServers = MonitoredNetworkDevice::where(function ($q) {
            $q->where('name', 'like', '%Servidor%')
                ->orWhere('name', 'like', '%MONITOREO%');
        })->get();

        foreach ($netServers as $ns) {
            if ($ns->ip && isset($seenIps[$ns->ip])) {
                continue;
            }
            $rows[] = [
                'rack' => 'RACK 01',
                'brand' => $ns->vendor_data ?: 'HEWLETT-PACKARD',
                'model' => $ns->model ?: 'SERVIDOR LOCAL',
                'serial' => $ns->serial ?: 'N/A',
                'year' => '2018',
                'ram' => '16 GB',
                'disk' => '1 TB',
                'os' => 'LINUX',
                'physical' => 'SI',
                'virtualized' => 'NO',
                'hardware' => 'SERVIDOR',
                'cpu' => 'INTEL CORE I7',
                'services' => $ns->notes ?: 'MONITOREO CONTINUO NOC',
                'ip' => $ns->ip ?: 'N/A',
                'mac' => $ns->mac ?: 'N/A',
                'status' => $ns->is_active ? 'OPERATIVO' : 'DAÑADO',
                'admin' => 'ADMINISTRADOR DE RED',
            ];
            if ($ns->ip) {
                $seenIps[$ns->ip] = true;
            }
        }

        return $rows;
    }

    /**
     * Retorna los registros normalizados de Telecomunicaciones (Switches, Routers, Telefonía IP, Firewalls y Red).
     */
    public function getTelecomRows(): array
    {
        $telecomAssets = InventoryAsset::with(['site', 'components'])
            ->where(function ($q) {
                $q->whereIn('type', ['switch', 'router', 'ip_phone', 'firewall', 'printer'])
                    ->orWhere('name', 'like', '%SW%')
                    ->orWhere('name', 'like', '%ROUTER%')
                    ->orWhere('name', 'like', '%PFsense%')
                    ->orWhere('name', 'like', '%AVAYA%');
            })
            ->where('name', 'not like', '%Servidor%')
            ->where('name', 'not like', '%MONITOREO-DEV%')
            ->get();

        $rows = [];
        $seenIps = [];

        foreach ($telecomAssets as $a) {
            $ram = $a->components->where('type', 'ram')->first()?->capacity ?: 'N/A';
            $disk = $a->components->where('type', 'disk')->first()?->capacity ?: 'N/A';
            $cpu = $a->components->where('type', 'cpu')->first()?->name ?: 'N/A';

            $hardware = match ($a->type) {
                'switch' => 'CONMUTADOR (SWITCH)',
                'router' => 'ENRUTADOR (ROUTER)',
                'ip_phone' => 'TELEFONÍA VOIP',
                'printer' => 'IMPRESORA DE RED',
                'firewall' => 'FIREWALL / GATEWAY',
                default => strtoupper($a->type_label),
            };

            $rows[] = [
                'rack' => $a->location_detail ?: 'RACK 01',
                'brand' => $a->brand ?: 'CISCO',
                'model' => $a->model ?: 'N/A',
                'serial' => $a->serial_number ?: 'N/A',
                'year' => $a->purchase_date ? $a->purchase_date->format('Y') : '2014',
                'ram' => $ram,
                'disk' => $disk,
                'os' => $a->operating_system ?: 'FIRMWARE / IOS',
                'physical' => 'SI',
                'virtualized' => 'NO',
                'hardware' => $hardware,
                'cpu' => $cpu,
                'services' => $a->notes ?: 'RED WAN / LAN',
                'ip' => $a->ip_address ?: 'N/A',
                'mac' => $a->mac_address ?: 'N/A',
                'status' => $a->status_label,
                'admin' => $a->custodian_name ?: 'ADMINISTRADOR DE RED',
            ];
            if ($a->ip_address) {
                $seenIps[$a->ip_address] = true;
            }
        }

        $netDevices = MonitoredNetworkDevice::all();
        foreach ($netDevices as $nd) {
            if ($nd->ip && isset($seenIps[$nd->ip])) {
                continue;
            }
            if (stripos($nd->name, 'Servidor') !== false || stripos($nd->name, 'MONITOREO ATIT') !== false || stripos($nd->name, 'MONITOREO-DEV') !== false) {
                continue;
            }
            if (stripos($nd->model ?? '', 'Estación') !== false || stripos($nd->model ?? '', 'EliteDesk') !== false || stripos($nd->model ?? '', 'ThinkCentre') !== false) {
                continue;
            }

            $rows[] = [
                'rack' => 'RACK 01',
                'brand' => $nd->vendor_data ?: 'CISCO',
                'model' => $nd->model ?: 'DISPOSITIVO DE RED',
                'serial' => $nd->serial ?: 'N/A',
                'year' => '2014',
                'ram' => 'N/A',
                'disk' => 'N/A',
                'os' => 'FIRMWARE / IOS',
                'physical' => 'SI',
                'virtualized' => 'NO',
                'hardware' => 'DISPOSITIVO DE RED',
                'cpu' => 'INTEGRADO',
                'services' => $nd->notes ?: 'CONECTIVIDAD NOC',
                'ip' => $nd->ip ?: 'N/A',
                'mac' => $nd->mac ?: 'N/A',
                'status' => $nd->is_active ? 'OPERATIVO' : 'DAÑADO',
                'admin' => 'ADMINISTRADOR DE RED',
            ];
            if ($nd->ip) {
                $seenIps[$nd->ip] = true;
            }
        }

        return $rows;
    }

    /**
     * Retorna los registros normalizados para la hoja 'Centro de Datos' (Servidores + Telecomunicaciones).
     */
    public function getCenterDataRows(): array
    {
        return array_merge($this->getServerRows(), $this->getTelecomRows());
    }

    /**
     * Retorna los registros normalizados para la hoja 'Estaciones de Trabajo'.
     */
    public function getWorkstationRows(): array
    {
        $assets = InventoryAsset::with(['site', 'components', 'peripherals', 'activeAssignment'])
            ->whereIn('type', ['workstation', 'desktop', 'laptop', 'all_in_one'])
            ->where('name', 'not like', '%Servidor%')
            ->where('name', 'not like', '%MONITOREO-DEV%')
            ->where('name', 'not like', '%PFsense%')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($assets as $a) {
            $responsable = $a->activeAssignment?->custodian_name ?: ($a->custodian_name ?: 'NO ASIGNADO');
            $cedula = $a->activeAssignment?->custodian_id_number ?: ($a->custodian_id_number ?: 'N/A');
            $telefono = $a->activeAssignment?->phone_extension ?: 'N/A';
            $estado = $a->site?->name ?: 'CARABOBO';
            $unidad = $a->activeAssignment?->custodian_department ?: ($a->custodian_department ?: 'OPERACIONES');

            $ram = $a->components->where('type', 'ram')->first()?->capacity ?: '4';
            $disk = $a->components->where('type', 'disk')->first()?->capacity ?: '500GB';
            $os = $a->operating_system ?: 'XUBUNTU';

            $rows[] = [
                'type' => strtoupper($a->type_label),
                'brand' => $a->brand ?: 'HP',
                'model' => $a->model ?: 'ELITEDESK',
                'serial' => $a->serial_number ?: 'N/A',
                'year' => $a->purchase_date ? $a->purchase_date->format('Y') : '2014',
                'ram' => $ram,
                'disk' => $disk,
                'os' => $os,
                'apps' => 'SAP/OFFICE',
                'custodian' => $responsable,
                'id_number' => $cedula,
                'phone' => $telefono,
                'state' => $estado,
                'unit' => $unidad,
                'status' => $a->status_label,
                'observation' => $a->notes ?: 'VALIDAR',
            ];

            foreach ($a->peripherals as $p) {
                $rows[] = [
                    'type' => strtoupper($p->type_label),
                    'brand' => $p->brand ?: 'HP',
                    'model' => $p->model ?: 'N/A',
                    'serial' => $p->serial_number ?: 'N/A',
                    'year' => '2014',
                    'ram' => 'N/A',
                    'disk' => 'N/A',
                    'os' => 'N/A',
                    'apps' => 'N/A',
                    'custodian' => $responsable,
                    'id_number' => $cedula,
                    'phone' => $telefono,
                    'state' => $estado,
                    'unit' => $unidad,
                    'status' => $p->status_label,
                    'observation' => $p->notes ?: 'VALIDAR',
                ];
            }
        }

        return $rows;
    }
}
