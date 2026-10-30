<?php

namespace App\Services\Glpi;

use App\Models\InventoryAsset;
use App\Models\InventoryAssetNetworkInterface;
use App\Models\InventoryComponent;
use App\Models\InventoryComponentHistory;
use App\Models\InventoryPeripheral;

/**
 * Motor de Auditoría y Detección de Diferencias de Hardware (Diff Engine).
 * Responsabilidad Única (S): Detectar modificaciones físicas de componentes (CPU, RAM, Discos) y periféricos.
 */
class GlpiHardwareDiffEngine
{
    public function __construct(protected GlpiHardwareNormalizer $normalizer)
    {
    }

    /**
     * Sincroniza componentes de CPU, RAM y Discos auditando diferencias forenses.
     */
    public function diffAndSyncComponents(InventoryAsset $asset, array $norm, bool $isNewAsset): array
    {
        $detectedChanges = [];

        // 1. CPU(s)
        foreach ($norm['cpus'] as $c) {
            $cpuName = $c['name'] ?? $c['NAME'] ?? 'Procesador Genérico';
            $cores = $c['core'] ?? $c['cores'] ?? $c['CORE'] ?? null;
            $speed = $c['speed'] ?? $c['SPEED'] ?? null;
            $cap = $cores ? "{$cores} Cores" . ($speed ? " @ {$speed} MHz" : "") : ($speed ? "{$speed} MHz" : null);

            $existingCpu = $asset->components()->where('type', 'cpu')->first();
            if (!$existingCpu) {
                InventoryComponent::create([
                    'inventory_asset_id' => $asset->id,
                    'type' => 'cpu',
                    'name' => $cpuName,
                    'capacity' => $cap,
                    'status' => 'active',
                ]);
                if (!$isNewAsset) {
                    $detectedChanges[] = "CPU instalada: {$cpuName}";
                    $this->recordHistory($asset->id, 'added', 'cpu', null, ['name' => $cpuName], 'Detección por agente GLPI');
                }
            } elseif ($existingCpu->name !== $cpuName) {
                $oldName = $existingCpu->name;
                $existingCpu->update(['name' => $cpuName, 'capacity' => $cap]);
                if (!$isNewAsset) {
                    $detectedChanges[] = "CPU reemplazada: '{$oldName}' por '{$cpuName}'";
                    $this->recordHistory($asset->id, 'modified', 'cpu', ['name' => $oldName], ['name' => $cpuName], 'Reemplazo detectado por agente');
                }
            }
        }

        // 2. Memoria(s) RAM
        $totalRamMb = 0;
        foreach ($norm['memories'] as $idx => $m) {
            $capRaw = (int)($m['capacity'] ?? $m['CAPACITY'] ?? 0);
            if ($capRaw > 0) {
                $totalRamMb += $capRaw;
            }
        }

        if ($totalRamMb === 0 && !empty($norm['memory_total'])) {
            $totalRamMb = (int)$norm['memory_total'];
        }

        if ($totalRamMb > 0) {
            $totalRamStr = round($totalRamMb / 1024) . " GB Total";
            $existingRam = $asset->components()->where('type', 'ram')->first();
            if (!$existingRam) {
                InventoryComponent::create([
                    'inventory_asset_id' => $asset->id,
                    'type' => 'ram',
                    'name' => 'Memoria RAM',
                    'capacity' => $totalRamStr,
                    'status' => 'active',
                ]);
                if (!$isNewAsset) {
                    $detectedChanges[] = "RAM instalada: {$totalRamStr}";
                    $this->recordHistory($asset->id, 'added', 'ram', null, ['capacity' => $totalRamStr], 'Detección por agente GLPI');
                }
            } elseif ($existingRam->capacity !== $totalRamStr) {
                $oldCap = $existingRam->capacity;
                $existingRam->update(['capacity' => $totalRamStr]);
                if (!$isNewAsset) {
                    $isReduction = (int)$totalRamStr < (int)$oldCap;
                    $prefix = $isReduction ? "🚨 ALERTA FORENSE: Disminución de memoria RAM:" : "Memoria RAM alterada:";
                    $detectedChanges[] = "{$prefix} '{$oldCap}' ➔ '{$totalRamStr}'";
                    $this->recordHistory(
                        $asset->id,
                        $isReduction ? 'removed' : 'modified',
                        'ram',
                        ['capacity' => $oldCap],
                        ['capacity' => $totalRamStr],
                        $isReduction ? 'Sustracción o falla de módulo RAM' : 'Cambio de módulos RAM'
                    );
                }
            }
        }

        // 3. Disco(s) y Almacenamiento
        $seenDisks = [];
        foreach ($norm['drives'] as $d) {
            $diskModel = $d['model'] ?? $d['MODEL'] ?? $d['name'] ?? $d['NAME'] ?? null;
            $diskSizeMb = (int)($d['disksize'] ?? $d['DISKSIZE'] ?? $d['capacity'] ?? 0);
            $diskSerial = $d['serial'] ?? $d['SERIAL'] ?? $d['serialnumber'] ?? null;
            $diskMfr = $d['manufacturer'] ?? $d['MANUFACTURER'] ?? null;

            if ($diskModel && $diskMfr && !str_contains(strtolower($diskModel), strtolower($diskMfr))) {
                $displayName = "{$diskMfr} {$diskModel}";
            } else {
                $displayName = $diskModel ?: ($d['volumn'] ?? 'Disco de Almacenamiento');
            }

            if (!empty($displayName)) {
                $sizeStr = ($diskSizeMb >= 1000) ? round($diskSizeMb / 1000) . " GB" : ($diskSizeMb > 0 ? "{$diskSizeMb} MB" : null);
                $key = $diskSerial ?: $displayName;
                $seenDisks[] = $key;

                $existingDisk = $asset->components()->where('type', 'disk')->where(function($q) use ($diskSerial, $displayName, $diskModel) {
                    if ($diskSerial) {
                        $q->where('serial_number', $diskSerial);
                    } else {
                        $q->where('name', $displayName)->orWhere('name', $diskModel);
                    }
                })->first();

                if (!$existingDisk) {
                    InventoryComponent::create([
                        'inventory_asset_id' => $asset->id,
                        'type' => 'disk',
                        'name' => $displayName,
                        'capacity' => $sizeStr,
                        'serial_number' => $diskSerial,
                        'status' => 'active',
                    ]);
                    if (!$isNewAsset) {
                        $detectedChanges[] = "Nuevo disco detectado: {$displayName}" . ($sizeStr ? " ({$sizeStr})" : "");
                        $this->recordHistory($asset->id, 'added', 'disk', null, ['name' => $displayName, 'capacity' => $sizeStr], 'Nuevo disco instalado');
                    }
                } elseif ($existingDisk->status === 'removed') {
                    $existingDisk->update(['status' => 'active']);
                    if (!$isNewAsset) {
                        $detectedChanges[] = "Disco reincorporado: {$displayName} (S/N: {$diskSerial})";
                        $this->recordHistory($asset->id, 'added', 'disk', null, ['name' => $displayName], 'Disco reincorporado');
                    }
                }
            }
        }

        // 4. Detección forense de discos desconectados o sustraídos
        if (!$isNewAsset && !empty($seenDisks) && !empty($norm['has_storages'])) {
            $activeDisks = $asset->components()->where('type', 'disk')->where('status', 'active')->get();
            foreach ($activeDisks as $activeDisk) {
                $identifier = $activeDisk->serial_number ?: $activeDisk->name;
                if (!in_array($identifier, $seenDisks, true)) {
                    $activeDisk->update(['status' => 'removed']);
                    $detectedChanges[] = "🚨 ALERTA FORENSE: Disco retirado/desconectado: '{$activeDisk->name}' (S/N: {$activeDisk->serial_number})";
                    $this->recordHistory($asset->id, 'removed', 'disk', ['name' => $activeDisk->name, 'serial' => $activeDisk->serial_number], null, 'Disco no reportado en nuevo inventario GLPI');
                }
            }
        }

        return $detectedChanges;
    }

    /**
     * Sincroniza monitores e impresoras locales detectados por el agente GLPI.
     */
    public function syncPeripherals(InventoryAsset $asset, array $norm, bool $isNewAsset): void
    {
        // 1. Monitores (leídos por EDID/DDC)
        foreach ($norm['monitors'] as $m) {
            $brand = trim((string)($m['manufacturer'] ?? $m['MANUFACTURER'] ?? 'Genérico'));
            $model = trim((string)($m['caption'] ?? $m['CAPTION'] ?? $m['description'] ?? 'Monitor'));
            $rawSerial = trim((string)($m['serial'] ?? $m['SERIAL'] ?? ''));
            $serial = !$this->normalizer->isGenericSerial($rawSerial) ? $rawSerial : null;

            if (empty($model) && empty($brand)) {
                continue;
            }

            $existing = null;
            if (!empty($serial)) {
                $existing = InventoryPeripheral::where('serial_number', $serial)->first();
            }
            if (!$existing) {
                $existing = InventoryPeripheral::where('current_asset_id', $asset->id)
                    ->where('type', 'monitor')
                    ->where('model', $model)
                    ->first();
            }

            if (!$existing) {
                InventoryPeripheral::create([
                    'asset_tag' => 'MON-' . strtoupper(substr(md5(($serial ?: $model) . $asset->id), 0, 6)),
                    'type' => 'monitor',
                    'brand' => $brand ?: 'Genérico',
                    'model' => $model,
                    'serial_number' => $serial,
                    'status' => 'assigned',
                    'current_asset_id' => $asset->id,
                    'monitored_site_id' => $asset->monitored_site_id,
                    'notes' => $serial 
                        ? 'Monitor detectado por Agente GLPI (EDID/DDC)' 
                        : 'Monitor detectado por GLPI [Serial no reportado por hardware]',
                ]);
                if (!$isNewAsset) {
                    $this->recordHistory($asset->id, 'added', 'monitor', null, ['brand' => $brand, 'model' => $model, 'serial' => $serial], 'Nuevo monitor detectado');
                }
            } else {
                $oldData = ['model' => $existing->model, 'serial' => $existing->serial_number];
                $isReplaced = ($existing->model !== $model) || ($serial && $existing->serial_number && $existing->serial_number !== $serial);
                $existing->update([
                    'current_asset_id' => $asset->id,
                    'monitored_site_id' => $asset->monitored_site_id,
                    'status' => 'assigned',
                    'brand' => $brand ?: $existing->brand,
                    'serial_number' => $serial ?: $existing->serial_number,
                ]);
                if (!$isNewAsset && $isReplaced) {
                    $this->recordHistory($asset->id, 'replaced', 'monitor', $oldData, ['model' => $model, 'serial' => $serial], 'Cambio de monitor detectado');
                }
            }
        }

        // 2. Impresoras locales (Periféricos físicos conectados por USB / puerto local o con serial de hardware)
        // Purgar colas temporales de red mDNS/DNS-SD o impresoras fantasma sin serial previamente registradas para este equipo
        $ghostPrinters = InventoryPeripheral::where('current_asset_id', $asset->id)
            ->where('type', 'printer')
            ->whereNull('serial_number')
            ->get();

        foreach ($ghostPrinters as $gp) {
            $modelName = (string)$gp->model;
            $notes = (string)$gp->notes;
            if (
                preg_match('/_[0-9a-fA-F]{2}_[0-9a-fA-F]{2}_[0-9a-fA-F]{2}$/i', $modelName)
                || preg_match('/_[0-9a-fA-F]{6,}$/i', $modelName)
                || str_contains($notes, '[Serial no reportado]')
            ) {
                $gp->delete();
            }
        }

        foreach ($norm['printers'] as $p) {
            $name = trim((string)($p['name'] ?? $p['NAME'] ?? ''));
            $port = trim((string)($p['port'] ?? $p['PORT'] ?? ''));
            $serial = trim((string)($p['serial'] ?? $p['SERIAL'] ?? ''));
            $driver = trim((string)($p['driver'] ?? ''));

            if (empty($name)) {
                continue;
            }

            // 1. Descartar impresoras virtuales del sistema
            $isVirtual = preg_match('/(pdf|xps|onenote|fax|document writer|root print|microsoft print|cups-pdf|@)/i', $name . ' ' . $driver);
            if ($isVirtual) {
                continue;
            }

            // 2. Descartar colas de difusión de red mDNS / DNS-SD / Bonjour / Avahi (ej: Canon_MF270_Series_35_f7_73)
            $isMdnsBroadcast = preg_match('/_[0-9a-fA-F]{2}_[0-9a-fA-F]{2}_[0-9a-fA-F]{2}$/i', $name)
                || preg_match('/_[0-9a-fA-F]{6,}$/i', $name);
            if ($isMdnsBroadcast) {
                continue;
            }

            // 3. Descartar colas explícitas de red basadas en protocolos remotos en el puerto
            $isNetworkProtocol = preg_match('/^(ipp|ipps|dnssd|mdns|socket|lpd|http|https|smb):/i', $port)
                || ($p['network'] ?? false) === true;

            // 4. Detectar conexión física local (USB, Paralelo, Serie)
            $cleanSerial = !$this->normalizer->isGenericSerial($serial) ? $serial : null;
            $isUsb = str_starts_with(strtolower($port), 'usb') 
                || str_contains(strtolower($port), 'usb://') 
                || str_contains(strtolower($name), 'usb')
                || preg_match('/^(usb|dot4|lpt[1-9]|com[1-9])/i', $port);

            // CONDICIÓN ESTRICTA: Para ser considerado periférico local de un puesto de trabajo,
            // DEBE tener conexión física local directa (USB) O poseer un serial de hardware real verificado.
            // Si es un protocolo de red, o no tiene puerto USB y carece de serial, es una cola de red remota y NO un periférico local.
            if ($isNetworkProtocol && empty($cleanSerial)) {
                continue;
            }

            if (!$isUsb && empty($cleanSerial)) {
                continue;
            }

            $brand = null;
            foreach (['Canon', 'HP', 'Epson', 'Xerox', 'Brother', 'Lexmark', 'Samsung', 'Zebra', 'Kyocera', 'Ricoh', 'Konica', 'Pantum'] as $b) {
                if (stripos($name, $b) !== false || stripos($driver, $b) !== false) {
                    $brand = $b;
                    break;
                }
            }

            $existing = null;
            if (!empty($cleanSerial)) {
                $existing = InventoryPeripheral::where('serial_number', $cleanSerial)->first();
            }
            if (!$existing) {
                $existing = InventoryPeripheral::where('current_asset_id', $asset->id)
                    ->where('type', 'printer')
                    ->where('model', $name)
                    ->first();
            }

            if (!$existing) {
                InventoryPeripheral::create([
                    'asset_tag' => 'PRN-' . strtoupper(substr(md5(($cleanSerial ?: $name) . $asset->id), 0, 6)),
                    'type' => 'printer',
                    'brand' => $brand ?: 'Genérica',
                    'model' => $name,
                    'serial_number' => $cleanSerial,
                    'status' => 'assigned',
                    'current_asset_id' => $asset->id,
                    'monitored_site_id' => $asset->monitored_site_id,
                    'notes' => $cleanSerial 
                        ? 'Impresora USB detectada por GLPI' 
                        : 'Impresora local detectada por GLPI',
                ]);
            } else {
                $existing->update([
                    'current_asset_id' => $asset->id,
                    'monitored_site_id' => $asset->monitored_site_id,
                    'status' => 'assigned',
                    'serial_number' => $cleanSerial ?: $existing->serial_number,
                ]);
            }
        }
    }

    /**
     * Sincroniza las interfaces de red normalizadas (Multi-NIC) del activo.
     */
    public function syncNetworkInterfaces(InventoryAsset $asset, array $norm, bool $isNewAsset): void
    {
        $interfaces = $norm['network_interfaces'] ?? [];
        if (empty($interfaces)) {
            return;
        }

        foreach ($interfaces as $net) {
            $mac = $net['mac_address'] ?? null;
            $ifName = $net['interface_name'] ?? 'eth0';
            $ip = $net['ip_address'] ?? null;
            $isPrimary = (bool)($net['is_primary'] ?? false);

            if (empty($mac) && empty($ip)) {
                continue;
            }

            // Buscar interfaz existente por MAC o nombre dentro del activo
            $existing = null;
            if (!empty($mac)) {
                $existing = InventoryAssetNetworkInterface::where('inventory_asset_id', $asset->id)
                    ->where('mac_address', $mac)
                    ->first();
            }
            if (!$existing && !empty($ifName)) {
                $existing = InventoryAssetNetworkInterface::where('inventory_asset_id', $asset->id)
                    ->where('interface_name', $ifName)
                    ->first();
            }

            $data = [
                'interface_name' => $ifName,
                'mac_address' => $mac,
                'ip_address' => ($ip && $ip !== '127.0.0.1') ? $ip : null,
                'subnet_mask' => $net['subnet_mask'] ?? null,
                'gateway' => $net['gateway'] ?? null,
                'dns' => $net['dns'] ?? null,
                'interface_type' => $net['interface_type'] ?? 'ethernet',
                'speed' => $net['speed'] ?? null,
                'status' => $net['status'] ?? 'up',
                'is_primary' => $isPrimary,
            ];

            if (!$existing) {
                InventoryAssetNetworkInterface::create(array_merge([
                    'inventory_asset_id' => $asset->id,
                ], $data));

                if (!$isNewAsset) {
                    $this->recordHistory(
                        $asset->id,
                        'added',
                        'network_interface',
                        null,
                        ['interface' => $ifName, 'ip' => $ip, 'mac' => $mac],
                        'Nueva interfaz de red detectada por GLPI'
                    );
                }
            } else {
                $oldIp = $existing->ip_address;
                $oldStatus = $existing->status;
                $oldData = [
                    'ip' => $oldIp,
                    'status' => $oldStatus,
                ];
                $existing->update($data);

                if (!$isNewAsset && ($oldIp !== $ip && !empty($ip))) {
                    $this->recordHistory(
                        $asset->id,
                        'modified',
                        'network_interface',
                        $oldData,
                        ['ip' => $ip, 'status' => $net['status'] ?? 'up'],
                        "Cambio de IP detectado en interfaz {$ifName}"
                    );
                }
            }
        }
    }

    /**
     * Registra un evento en la tabla inmutable de historial de componentes.
     */
    public function recordHistory(int $assetId, string $action, string $componentType, ?array $oldVal, ?array $newVal, ?string $reason): void
    {
        InventoryComponentHistory::create([
            'inventory_asset_id' => $assetId,
            'action' => $action,
            'component_type' => $componentType,
            'old_value' => $oldVal,
            'new_value' => $newVal,
            'reason' => $reason,
            'recorded_at' => now(),
        ]);
    }
}
