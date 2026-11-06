<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Reclasificar en inventory_assets el activo 192.168.1.232 (ID 7)
        DB::table('inventory_assets')
            ->where('id', 7)
            ->orWhere('ip_address', '192.168.1.232')
            ->update([
                'name' => 'CENCARCOMG206',
                'type' => 'desktop',
                'brand' => 'LENOVO',
                'model' => 'ThinkCentre M910s',
                'serial_number' => 'MJ07BWNE',
                'mac_address' => 'F4:93:9F:ED:4B:B7',
                'operating_system' => 'Microsoft Windows 7 Ultimate (64-bit)',
                'os_kernel' => '6.1.7601',
                'ports' => null,
                'access_type' => 'VNC',
                'access_port' => 5900,
                'vnc_port' => 5900,
                'notes' => 'Estación de Trabajo Comercial / Operativa | Hostname: CENCARCOMG206',
                'normal_state_msg' => '✅ - CENCARCOMG206',
                'error_state_msg' => '❌ - CENCARCOMG206',
                'updated_at' => now(),
            ]);

        // 2. Sincronizar discovered_devices para mantener coherencia de ecosistema (Regla de Oro #8)
        DB::table('discovered_devices')
            ->where('ip_address', '192.168.1.232')
            ->update([
                'hostname' => 'CENCARCOMG206',
                'device_type' => 'workstation',
                'vendor' => 'LENOVO',
                'os_detected' => 'Microsoft Windows 7 Ultimate (64-bit)',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('inventory_assets')
            ->where('id', 7)
            ->update([
                'name' => 'SW04 - Hon Hai Acceso',
                'type' => 'switch',
                'brand' => 'Hon Hai Precision',
                'model' => 'Switch de Red',
                'ports' => '16x FE',
                'notes' => 'Conmutador de periféricos y salas de lectura. | Hostname NetBIOS: CENCARCOMG206',
                'normal_state_msg' => '✅ - SW04 - Hon Hai Acceso',
                'error_state_msg' => '❌ - SW04 - Hon Hai Acceso',
                'updated_at' => now(),
            ]);
    }
};
