<?php

declare(strict_types=1);

namespace App\Actions\Deploy;

use App\Models\AuditLog;
use App\Models\DiscoveredDevice;
use App\Models\InventoryAsset;
use Illuminate\Support\Facades\Auth;

class BulkPromoteAssetsAction
{
    /**
     * Incorpora múltiples dispositivos descubiertos por lote al inventario oficial ITAM.
     *
     * @param array $validated
     * @param string|null $clientIp
     * @param string|null $userAgent
     * @return array
     */
    public function execute(array $validated, ?string $clientIp = null, ?string $userAgent = null): array
    {
        $siteId = (int) $validated['monitored_site_id'];
        $type = $validated['type'];
        $namePrefix = trim($validated['name_prefix'] ?? '');
        $notes = $validated['notes'] ?? 'Catalogado por lote desde Despliegue de Red';
        $isWorkstation = in_array($type, ['desktop', 'laptop', 'server'], true);
        $status = $isWorkstation ? 'in_stock' : 'in_use';

        $processedCount = 0;
        $updatedIpCount = 0;
        $createdCount = 0;
        $processedIps = [];

        foreach ($validated['devices'] as $idx => $d) {
            $ip = trim($d['ip']);
            $mac = !empty($d['mac']) && !str_starts_with($d['mac'], 'No') && !str_starts_with($d['mac'], 'IP:')
                ? strtoupper(trim($d['mac']))
                : null;
            $vendor = trim($d['vendor'] ?? '');
            $hostname = trim($d['hostname'] ?? '');

            // Determinar Nombre Etiqueta corporativo
            if (!empty($namePrefix)) {
                $name = count($validated['devices']) > 1
                    ? "{$namePrefix} " . str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT)
                    : $namePrefix;
            } elseif (!empty($hostname) && !str_starts_with($hostname, 'Host-')) {
                $name = $hostname;
            } else {
                $name = "PUESTO {$ip}";
            }

            // Prioridad 1: Buscar por MAC para deduplicar
            $asset = null;
            if ($mac) {
                $asset = InventoryAsset::where('mac_address', $mac)->first();
            }
            // Prioridad 2: Buscar por IP
            if (!$asset) {
                $asset = InventoryAsset::where('ip_address', $ip)->first();
            }

            if (!$asset) {
                $asset = InventoryAsset::create([
                    'name' => $name,
                    'type' => $type,
                    'status' => $status,
                    'ip_address' => $ip,
                    'mac_address' => $mac,
                    'monitored_site_id' => $siteId,
                    'brand' => $vendor ?: null,
                    'notes' => $notes,
                    'is_active' => true,
                ]);
                $createdCount++;
            } else {
                $oldIp = $asset->ip_address;
                $ipChanged = ($oldIp && $oldIp !== $ip);

                $asset->update([
                    'name' => $name,
                    'type' => $type,
                    'ip_address' => $ip,
                    'monitored_site_id' => $siteId,
                    'mac_address' => $mac ?: $asset->mac_address,
                    'brand' => $vendor ?: $asset->brand,
                    'notes' => $notes,
                ]);

                if ($ipChanged) {
                    $updatedIpCount++;
                    AuditLog::create([
                        'user_id' => Auth::id(),
                        'user_name' => Auth::user()?->name ?? 'Administrador',
                        'event' => 'ip_reassigned',
                        'module' => 'inventory',
                        'auditable_type' => InventoryAsset::class,
                        'auditable_id' => $asset->id,
                        'entity_name' => $name,
                        'description' => "Reasignación DHCP por lote para {$name}: IP migró de {$oldIp} a {$ip}.",
                        'ip_address' => $clientIp,
                        'user_agent' => $userAgent,
                    ]);
                }
            }

            // Conciliar DiscoveredDevice
            DiscoveredDevice::where('ip_address', $ip)->update([
                'classification_status' => 'clasificado',
                'is_authorized' => 1,
                'linked_network_device_id' => $asset->id,
                'site_id' => $siteId,
            ]);

            $processedCount++;
            $processedIps[] = $ip;
        }

        // Auditoría global por lote
        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Administrador',
            'event' => 'bulk_promote_to_asset',
            'module' => 'inventory',
            'description' => "Incorporación masiva de {$processedCount} dispositivos ({$createdCount} creados, {$updatedIpCount} IPs actualizadas) al inventario como {$type}.",
            'ip_address' => $clientIp,
            'user_agent' => $userAgent,
        ]);

        return [
            'success' => true,
            'count' => $processedCount,
            'is_workstation' => in_array($type, ['desktop', 'laptop', 'workstation'], true),
            'message' => "Se incorporaron {$processedCount} dispositivos al inventario ({$createdCount} nuevos, {$updatedIpCount} actualizados).",
            'processed_count' => $processedCount,
            'created_count' => $createdCount,
            'updated_count' => $updatedIpCount,
            'ips' => $processedIps,
        ];
    }
}
