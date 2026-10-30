<?php

namespace App\Services;

use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\User;
use App\Models\DiscoveredDevice;
use App\Models\SnmpDevice;
use App\Models\NetRadarHost;
use App\Models\WolDevice;
use App\Models\MonitoredNetworkDevice;

class EntityResolverService
{
    /**
     * Resuelve un activo del inventario ITAM a partir de su dirección IP o dirección MAC.
     */
    public function resolve(?string $ip, ?string $mac = null): ?InventoryAsset
    {
        if (empty($ip) && empty($mac)) {
            return null;
        }

        $cleanIp = !empty($ip) ? trim($ip) : null;
        $cleanMac = (!empty($mac) && !str_starts_with($mac, 'IP:')) ? strtoupper(trim($mac)) : null;

        $query = InventoryAsset::query();

        if ($cleanIp && $cleanMac) {
            $query->where(function ($q) use ($cleanIp, $cleanMac) {
                $q->where('ip_address', $cleanIp)
                  ->orWhere('mac_address', $cleanMac);
            });
        } elseif ($cleanIp) {
            $query->where('ip_address', $cleanIp);
        } elseif ($cleanMac) {
            $query->where('mac_address', $cleanMac);
        }

        return $query->with(['site', 'currentUser'])->first();
    }

    /**
     * Obtiene el perfil integrado y la telemetría cruzada de un activo.
     */
    public function getUnifiedProfile(InventoryAsset $asset): array
    {
        $ip = $asset->ip_address;
        $mac = $asset->mac_address;

        // 1. Radar / Descubrimiento
        $discovered = DiscoveredDevice::where(function ($q) use ($asset, $ip, $mac) {
            $q->where('linked_network_device_id', $asset->id);
            if (!empty($ip)) {
                $q->orWhere('ip_address', $ip);
            }
            if (!empty($mac) && !str_starts_with($mac, 'IP:')) {
                $q->orWhere('mac_address', $mac);
            }
        })->first();

        // 2. Telemetría SNMP
        $snmp = SnmpDevice::where('network_device_id', $asset->id)
            ->orWhere(function ($q) use ($ip) {
                if (!empty($ip)) {
                    $q->where('ip_address', $ip);
                }
            })
            ->withCount('interfaces')
            ->first();

        // 3. Flujo NetRadar
        $netRadar = null;
        if (!empty($ip)) {
            $netRadar = NetRadarHost::where('ip', $ip)->first();
        }

        // 4. Wake-on-LAN
        $wol = null;
        if (!empty($mac) && !str_starts_with($mac, 'IP:')) {
            $wol = WolDevice::where('mac_address', $mac)->first();
        }

        // 5. Estado NOC Ping
        $noc = MonitoredNetworkDevice::find($asset->id);

        return [
            'asset' => $asset,
            'display_identity' => $asset->display_identity,
            'technical_subtitle' => $asset->technical_subtitle,
            'discovered' => $discovered,
            'snmp' => $snmp,
            'net_radar' => $netRadar,
            'wol' => $wol,
            'noc' => $noc,
        ];
    }

    /**
     * Concilia y vincula automáticamente los puestos de trabajo y asignaciones de inventario
     * con la cuenta del usuario en MariaDB (SSOT) a partir de su cédula y nombre oficial.
     */
    public function reconcileUserAssets(User $user): int
    {
        $userId = $user->id;
        $name = trim((string) ($user->name ?? ''));
        $cedula = trim((string) ($user->cedula ?? ''));
        $rawCedulaDigits = preg_replace('/\D/', '', $cedula);

        $cedulaVariants = array_values(array_filter([
            $cedula,
            $rawCedulaDigits,
            !empty($rawCedulaDigits) ? 'V-' . $rawCedulaDigits : null,
            !empty($rawCedulaDigits) ? 'V-' . number_format((int)$rawCedulaDigits, 0, '', '.') : null,
        ]));

        $updatedCount = 0;

        // 1. Vincular puestos de trabajo en inventory_assets
        $assetQuery = InventoryAsset::query()
            ->where(function ($q) use ($cedulaVariants, $name) {
                $hasFilter = false;
                if (!empty($cedulaVariants)) {
                    $q->whereIn('custodian_id_number', $cedulaVariants);
                    $hasFilter = true;
                }
                if (!empty($name) && !in_array(strtolower($name), ['usuario', 'operador', 'admin', 'user'])) {
                    if ($hasFilter) {
                        $q->orWhere('custodian_name', $name);
                    } else {
                        $q->where('custodian_name', $name);
                    }
                }
            })
            ->where(function ($q) use ($userId) {
                $q->whereNull('current_user_id')
                  ->orWhere('current_user_id', '!=', $userId);
            });

        $assetsToLink = $assetQuery->get();
        foreach ($assetsToLink as $asset) {
            $asset->update(['current_user_id' => $userId]);
            $updatedCount++;
        }

        // 2. Vincular actas activas en inventory_assignments
        $assignmentQuery = InventoryAssignment::query()
            ->where('status', 'active')
            ->where(function ($q) use ($cedulaVariants, $name) {
                $hasFilter = false;
                if (!empty($cedulaVariants)) {
                    $q->whereIn('custodian_id_number', $cedulaVariants);
                    $hasFilter = true;
                }
                if (!empty($name) && !in_array(strtolower($name), ['usuario', 'operador', 'admin', 'user'])) {
                    if ($hasFilter) {
                        $q->orWhere('custodian_name', $name);
                    } else {
                        $q->where('custodian_name', $name);
                    }
                }
            })
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', '!=', $userId);
            });

        $assignmentsToLink = $assignmentQuery->get();
        foreach ($assignmentsToLink as $assignment) {
            $assignment->update(['user_id' => $userId]);
            $updatedCount++;
        }

        return $updatedCount;
    }
}
