<?php

declare(strict_types=1);

namespace App\Actions\Deploy;

use App\DTOs\Deploy\PromoteAssetDto;
use App\Models\AuditLog;
use App\Models\DiscoveredDevice;
use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use Illuminate\Support\Facades\Auth;

class PromoteGlpiAssetAction
{
    /**
     * Incorpora un dispositivo descubierto al inventario oficial ITAM y red.
     *
     * @param PromoteAssetDto|array $payload
     * @param string|null $clientIp
     * @param string|null $userAgent
     * @return array
     */
    public function execute(PromoteAssetDto|array $payload, ?string $clientIp = null, ?string $userAgent = null): array
    {
        $dto = is_array($payload) ? PromoteAssetDto::fromArray($payload) : $payload;

        $ip = $dto->ip;
        $cleanMac = (!empty($dto->mac) && !str_starts_with($dto->mac, 'No') && !str_starts_with($dto->mac, 'IP:'))
            ? strtoupper(trim($dto->mac))
            : null;

        $siteId = $dto->monitoredSiteId;
        $type = $dto->type;
        $name = $dto->name;

        // 1. Crear o actualizar activo en inventory_assets (Deduplicación por MAC)
        $asset = null;
        if ($cleanMac) {
            $asset = InventoryAsset::where('mac_address', $cleanMac)->first();
        }
        if (!$asset) {
            $asset = InventoryAsset::where('ip_address', $ip)->first();
        }

        $isWorkstation = in_array($type, ['desktop', 'laptop', 'server'], true);
        $status = $isWorkstation ? 'in_stock' : 'in_use';
        $ipChanged = false;
        $oldIp = null;

        $defaultAccessType = ($type === 'printer') ? 'WEB' : 'SIN SOPORTE';
        $defaultAccessPort = ($type === 'printer') ? 80 : null;
        $defaultWebPort = ($type === 'printer') ? 80 : null;

        if (!$asset) {
            $asset = InventoryAsset::create([
                'name' => $name,
                'type' => $type,
                'status' => $status,
                'ip_address' => $ip,
                'mac_address' => $cleanMac,
                'monitored_site_id' => $siteId,
                'brand' => $dto->brand,
                'model' => $dto->model,
                'notes' => $dto->notes ?? 'Catalogado e incorporado desde Despliegue / Escaneo de Red',
                'is_active' => true,
                'access_type' => $defaultAccessType,
                'access_port' => $defaultAccessPort,
                'web_port' => $defaultWebPort,
            ]);
        } else {
            $oldIp = $asset->ip_address;
            $ipChanged = ($oldIp && $oldIp !== $ip);

            $updateData = [
                'name' => $name,
                'type' => $type,
                'ip_address' => $ip,
                'monitored_site_id' => $siteId,
                'mac_address' => $cleanMac ?: $asset->mac_address,
                'brand' => $dto->brand ?? $asset->brand,
                'model' => $dto->model ?? $asset->model,
                'notes' => $dto->notes ?? $asset->notes,
            ];

            if ($type === 'printer' && (empty($asset->access_type) || $asset->access_type === 'SIN SOPORTE')) {
                $updateData['access_type'] = 'WEB';
                $updateData['access_port'] = 80;
                $updateData['web_port'] = 80;
            }

            $asset->update($updateData);

            if ($ipChanged) {
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'user_name' => Auth::user()?->name ?? 'Administrador',
                    'event' => 'ip_reassigned',
                    'module' => 'inventory',
                    'auditable_type' => InventoryAsset::class,
                    'auditable_id' => $asset->id,
                    'entity_name' => $name,
                    'description' => "Reasignación DHCP detectada para {$name} (MAC {$cleanMac}): IP migró de {$oldIp} a {$ip}.",
                    'ip_address' => $clientIp,
                    'user_agent' => $userAgent,
                ]);
            }
        }

        // 2. Si es dispositivo de infraestructura de red o impresora, vincular en MonitoredNetworkDevice
        if (in_array($type, ['router', 'switch', 'server', 'printer', 'firewall', 'access_point'], true)) {
            $nextDevNum = (MonitoredNetworkDevice::where('monitored_site_id', $siteId)->max('device_number') ?? 0) + 1;
            $netDev = MonitoredNetworkDevice::firstOrCreate(
                ['ip' => $ip],
                [
                    'monitored_site_id' => $siteId,
                    'device_number' => $nextDevNum,
                    'name' => $name,
                    'mac' => $cleanMac,
                    'model' => $dto->model ?? ucfirst($type),
                    'serial' => !empty($dto->serial) ? $dto->serial : ($asset->serial_number ?: null),
                    'ports' => !empty($dto->ports) ? $dto->ports : null,
                    'access_type' => $defaultAccessType,
                    'access_port' => $defaultAccessPort,
                    'web_port' => $defaultWebPort,
                    'notes' => $dto->notes ?? 'Incorporado desde Escaneo de Red',
                    'is_active' => true,
                ]
            );
            $asset->update(['monitored_network_device_id' => $netDev->id]);
        }

        // 3. Auto-conciliar en DiscoveredDevice
        DiscoveredDevice::where('ip_address', $ip)->update([
            'classification_status' => 'clasificado',
            'is_authorized' => 1,
            'linked_network_device_id' => $asset->id,
            'site_id' => $siteId,
            'device_type' => $this->mapAssetTypeToDiscoveredDeviceType($type),
        ]);

        // 4. Auditoría formal
        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Administrador',
            'event' => 'promote_to_asset',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $asset->id,
            'entity_name' => $name,
            'description' => "Dispositivo {$ip} ({$name}) catalogado e incorporado al inventario como {$type} desde Despliegue.",
            'ip_address' => $clientIp,
            'user_agent' => $userAgent,
        ]);

        $action = $isWorkstation ? 'registered' : 'moved';
        $typeLabels = [
            'printer' => 'Impresora de Red',
            'switch' => 'Switch de Red',
            'router' => 'Router / Gateway',
            'ip_phone' => 'Teléfono VoIP',
            'access_point' => 'Punto de Acceso (AP)',
            'desktop' => 'Estación de Trabajo (PC)',
            'laptop' => 'Computadora Portátil',
            'server' => 'Servidor',
            'ups' => 'UPS / Respaldo Eléctrico',
            'firewall' => 'Firewall',
        ];
        $typeLabel = $typeLabels[$type] ?? ucfirst($type);

        if ($action === 'moved') {
            $msg = "El equipo '{$name}' ({$ip}) fue catalogado exitosamente como {$typeLabel} y movido a Equipos & Activos.";
        } else {
            $msg = "El puesto '{$name}' ({$ip}) fue registrado en inventario como {$typeLabel} y quedó habilitado para despliegue de agente GLPI.";
        }

        if ($ipChanged) {
            $msg .= " (IP reasignada por DHCP actualizada: antes {$oldIp})";
        }

        return [
            'success' => true,
            'action' => $action,
            'message' => $msg,
            'asset_id' => $asset->id,
            'ip' => $ip,
            'name' => $name,
            'type' => $type,
            'ip_changed' => $ipChanged,
            'previous_ip' => $oldIp,
        ];
    }

    public function mapAssetTypeToDiscoveredDeviceType(string $assetType): string
    {
        return match (strtolower(trim($assetType))) {
            'desktop', 'laptop', 'workstation', 'windows', 'pc' => 'workstation',
            'server', 'linux' => 'server',
            'printer' => 'printer',
            'switch' => 'switch',
            'router', 'gateway' => 'router',
            'firewall' => 'firewall',
            'access_point', 'ap' => 'ap',
            'camera' => 'camera',
            'ups' => 'ups',
            'ip_phone', 'phone', 'voip' => 'phone',
            default => 'unknown',
        };
    }
}
