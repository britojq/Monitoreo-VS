<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Garantiza la restauración y paridad absoluta del ROUTER PRINCIPAL (ID 1, IP 192.168.1.1, TELNET:23)
     * y la correcta etiqueta de activo (asset_tag) del Servidor de Desarrollo CENCARATIT (ID 11519)
     * en monitored_network_devices e inventory_assets tanto en Producción como en Desarrollo.
     */
    public function up(): void
    {
        DB::transaction(function () {
            // 1. Asegurar que el Servidor de Desarrollo (ID 11519) tenga su asset_tag legítimo 'PC-DEV-DEBIAN-01'
            DB::table('inventory_assets')->where('id', 11519)->update([
                'asset_tag' => 'PC-DEV-DEBIAN-01',
                'updated_at' => now(),
            ]);

            // 2. Restaurar en monitored_network_devices (ID 1)
            DB::table('monitored_network_devices')->updateOrInsert(
                ['id' => 1],
                [
                    'monitored_site_id' => 1,
                    'device_number' => 1,
                    'name' => 'ROUTER PRINCIPAL',
                    'ip' => '192.168.1.1',
                    'mac' => '00:1A:2B:3C:4D:5E',
                    'vendor_data' => 'Cisco Systems',
                    'access_type' => 'TELNET',
                    'access_port' => 23,
                    'ssh_port' => 22,
                    'vnc_port' => null,
                    'web_port' => null,
                    'telnet_port' => 23,
                    'ssh_username' => null,
                    'ssh_password_encrypted' => null,
                    'ssh_enable_secret_encrypted' => null,
                    'vnc_password_encrypted' => null,
                    'model' => 'Router Gateway',
                    'serial' => 'SN-NET-00001',
                    'ports' => '3x GE, 4x EHWIC',
                    'notes' => "Gateway principal de la sede Valle Seco.\nEnlace WAN corporativo.",
                    'normal_state_msg' => '✅ - ROUTER PRINCIPAL',
                    'error_state_msg' => '❌ - ROUTER PRINCIPAL',
                    'is_active' => 1,
                    'telegram_alert_enabled' => 0,
                    'telegram_alert_target' => 'owner',
                    'sort_order' => 1,
                    'updated_at' => now(),
                ]
            );

            // 3. Restaurar en inventory_assets (ID 1)
            DB::table('inventory_assets')->updateOrInsert(
                ['id' => 1],
                [
                    'asset_tag' => 'NET-00001',
                    'name' => 'ROUTER PRINCIPAL',
                    'type' => 'router',
                    'status' => 'in_stock',
                    'brand' => 'Cisco Systems',
                    'model' => 'Router Gateway',
                    'serial_number' => 'SN-NET-00001',
                    'mac_address' => '00:1A:2B:3C:4D:5E',
                    'ip_address' => '192.168.1.1',
                    'monitored_site_id' => 1,
                    'device_number' => 1,
                    'access_type' => 'TELNET',
                    'access_port' => 23,
                    'ssh_port' => 22,
                    'vnc_port' => null,
                    'web_port' => null,
                    'telnet_port' => 23,
                    'ports' => '3x GE, 4x EHWIC',
                    'location_detail' => 'SEDE VALLE SECO',
                    'monitored_network_device_id' => 1,
                    'notes' => "Gateway principal de la sede Valle Seco.\nEnlace WAN corporativo.",
                    'normal_state_msg' => '✅ - ROUTER PRINCIPAL',
                    'error_state_msg' => '❌ - ROUTER PRINCIPAL',
                    'is_active' => 1,
                    'sort_order' => 1,
                    'updated_at' => now(),
                ]
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No destructivo para preservar integridad de auditoría histórica
    }
};
