<?php

declare(strict_types=1);

namespace App\Actions\Ups;

use App\Models\AuditLog;
use App\Models\UpsDevice;
use App\Models\User;

class UpdateUpsSettingsAction
{
    public function execute(array $validated, ?User $user, ?string $ip, ?string $userAgent): array
    {
        $device = UpsDevice::firstOrFail();

        $oldEnabled = $device->telegram_alert_enabled;
        $oldTarget = $device->telegram_alert_target;
        $oldPort = $device->serial_port;
        $oldBaud = $device->baud_rate;
        $oldName = $device->name;
        $oldModel = $device->model;
        $oldSerial = $device->serial_number;

        $updates = [
            'telegram_alert_enabled' => $validated['telegram_alert_enabled'],
            'telegram_alert_target' => $validated['telegram_alert_target'],
        ];

        if (array_key_exists('name', $validated) && !empty($validated['name'])) {
            $updates['name'] = trim($validated['name']);
        }
        if (array_key_exists('model', $validated) && !empty($validated['model'])) {
            $updates['model'] = trim($validated['model']);
        }
        if (array_key_exists('serial_number', $validated)) {
            $updates['serial_number'] = !empty($validated['serial_number']) ? trim($validated['serial_number']) : null;
        }
        if (!empty($validated['rating_voltage'])) {
            $updates['rating_voltage'] = (float) $validated['rating_voltage'];
        }
        if (!empty($validated['rating_battery_voltage'])) {
            $updates['rating_battery_voltage'] = (float) $validated['rating_battery_voltage'];
        }
        if (array_key_exists('inventory_asset_id', $validated)) {
            $updates['inventory_asset_id'] = $validated['inventory_asset_id'] ? (int) $validated['inventory_asset_id'] : null;
        }
        if (!empty($validated['serial_port'])) {
            $updates['serial_port'] = trim($validated['serial_port']);
        }
        if (!empty($validated['baud_rate'])) {
            $updates['baud_rate'] = (int) $validated['baud_rate'];
        }

        $device->update($updates);
        $syncedPeripheral = $device->syncToInventoryPeripheral();

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Usuario',
            'user_email' => $user?->email ?? '',
            'user_role' => $user?->role ?? 'admin',
            'event' => 'updated',
            'module' => 'settings',
            'auditable_type' => UpsDevice::class,
            'auditable_id' => $device->id,
            'entity_name' => 'Supervisión UPS',
            'entity_label' => $device->name,
            'description' => "Identidad y configuración de UPS actualizada. Modelo: {$device->model}, Puerto: {$device->serial_port} @{$device->baud_rate} bps, Sincronizado a ITAM: " . ($syncedPeripheral ? 'Sí (' . $syncedPeripheral->asset_tag . ')' : 'No'),
            'old_values' => ['name' => $oldName, 'model' => $oldModel, 'serial' => $oldSerial, 'port' => $oldPort, 'baud' => $oldBaud, 'enabled' => $oldEnabled, 'target' => $oldTarget],
            'new_values' => ['name' => $device->name, 'model' => $device->model, 'serial' => $device->serial_number, 'port' => $device->serial_port, 'baud' => $device->baud_rate, 'enabled' => $device->telegram_alert_enabled, 'target' => $device->telegram_alert_target],
            'changed_fields' => array_keys($updates),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return [
            'success' => true,
            'message' => 'Identidad y configuración de UPS actualizada y sincronizada con éxito al inventario ITAM.',
            'name' => $device->name,
            'model' => $device->model,
            'serial_number' => $device->serial_number,
            'serial_port' => $device->serial_port,
            'baud_rate' => $device->baud_rate,
            'rating_voltage' => $device->rating_voltage,
            'rating_battery_voltage' => $device->rating_battery_voltage,
            'telegram_alert_enabled' => (bool) $device->telegram_alert_enabled,
            'telegram_alert_target' => $device->telegram_alert_target,
        ];
    }
}
