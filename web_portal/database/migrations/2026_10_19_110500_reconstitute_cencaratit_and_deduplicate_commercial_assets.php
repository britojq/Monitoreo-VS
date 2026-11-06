<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Restituye la identidad del Servidor de Desarrollo CENCARATIT (ID 11519, IP 192.168.1.220),
     * preserva la identidad única del ROUTER PRINCIPAL (ID 1, IP 192.168.1.1, TELNET:23),
     * reasigna y purga el activo parásito 111063 de GLPI,
     * y consolida los 17 pares de duplicados de oficinas comerciales (Sedes 3, 4, 5 y 7).
     */
    public function up(): void
    {
        DB::transaction(function () {
            // 1. Restituir Activo 11519: Servidor de Desarrollo CENCARATIT
            DB::table('inventory_assets')->where('id', 11519)->update([
                'name' => 'CENCARATIT (Servidor Desarrollo)',
                'type' => 'desktop',
                'status' => 'assigned',
                'brand' => 'LENOVO',
                'model' => 'ThinkCentre M910s',
                'serial_number' => 'MJ09RSFL',
                'mac_address' => '00:D8:61:44:95:B6',
                'ip_address' => '192.168.1.220',
                'monitored_site_id' => 1,
                'device_number' => 8,
                'access_type' => 'SSH+VNC',
                'access_port' => 22,
                'ssh_port' => 22,
                'vnc_port' => 5900,
                'web_port' => null,
                'telnet_port' => null,
                'ports' => null,
                'location_detail' => 'Laboratorio de Desarrollo, Sede Valle Seco',
                'custodian_name' => 'José Alfonso Brito Heredia',
                'custodian_id_number' => '12345678',
                'custodian_department' => 'GPO TRAB INFRA TECNOL CARABOBOATIT',
                'operating_system' => 'Debian GNU/Linux 13 (trixie) 13.7 (x86_64)',
                'os_kernel' => '6.12.111+deb13-amd64',
                'notes' => 'Servidor Local de Desarrollo - Nodo Esclavo Monitoreo Valle Seco | Hostname NetBIOS: CENCARATIT',
                'normal_state_msg' => '✅ - CENCARATIT (Servidor Desarrollo)',
                'error_state_msg' => '❌ - CENCARATIT (Servidor Desarrollo)',
                'is_active' => 1,
                'updated_at' => now(),
            ]);

            // 2. Re-afirmar Activo 1: ROUTER PRINCIPAL (Sede Valle Seco, Gateway)
            DB::table('inventory_assets')->where('id', 1)->update([
                'name' => 'ROUTER PRINCIPAL',
                'type' => 'router',
                'ip_address' => '192.168.1.1',
                'asset_tag' => 'NET-00001',
                'brand' => 'Cisco Systems',
                'model' => 'Router Gateway',
                'serial_number' => 'SN-NET-00001',
                'mac_address' => '00:1A:2B:3C:4D:5E',
                'ports' => '3x GE, 4x EHWIC',
                'access_type' => 'TELNET',
                'access_port' => 23,
                'telnet_port' => 23,
                'ssh_port' => 22,
                'vnc_port' => null,
                'web_port' => null,
                'monitored_site_id' => 1,
                'device_number' => 1,
                'notes' => 'Router Gateway principal de la Sede Valle Seco.',
                'normal_state_msg' => '✅ - ROUTER PRINCIPAL',
                'error_state_msg' => '❌ - ROUTER PRINCIPAL',
                'is_active' => 1,
                'updated_at' => now(),
            ]);

            // 3. Saneamiento del activo parásito 111063 (creado por GLPI durante la colisión)
            $rogue = DB::table('inventory_assets')->where('id', 111063)->first();
            if ($rogue) {
                // Transferir periféricos auténticos a 11519
                DB::table('inventory_peripherals')
                    ->where('current_asset_id', 111063)
                    ->whereIn('id', [3, 876]) // Monitor HP V193b e Impresora Canon
                    ->update(['current_asset_id' => 11519]);

                // Eliminar periférico GTG duplicado (ID 2987) ya que 11519 ya tiene el ID 2
                DB::table('inventory_peripherals')
                    ->where('current_asset_id', 111063)
                    ->where('id', 2987)
                    ->delete();

                // Eliminar cualquier otro periférico residual
                DB::table('inventory_peripherals')->where('current_asset_id', 111063)->update(['current_asset_id' => 11519]);

                // Purgar componentes duplicados de 111063
                DB::table('inventory_components_histories')->where('inventory_asset_id', 111063)->delete();
                DB::table('inventory_components')->where('inventory_asset_id', 111063)->delete();
                DB::table('inventory_asset_network_interfaces')->where('inventory_asset_id', 111063)->delete();

                // Purgar el activo parásito
                DB::table('inventory_assets')->where('id', 111063)->delete();
            }

            // 4. Consolidación de duplicados en oficinas comerciales (Sedes 3, 4, 5 y 7)
            // Mapeo unívoco: [ID duplicado viejo (115xx) => ID canónico activo (8..24)]
            $duplicatePairs = [
                // Sede 3 - CIAU CONSOLIDADO
                11502 => 8,  // JEFE OFICINA
                11503 => 9,  // TAQUILLA 01
                11504 => 10, // TAQUILLA 02
                11505 => 11, // ATU 01
                11506 => 12, // IMPRESORA RED
                // Sede 4 - CIAU PASEO MARIÑO
                11507 => 13, // JEFE OFICINA
                11508 => 14, // TAQUILLA 01
                11509 => 15, // TAQUILLA 02
                // Sede 5 - CIAU MORON
                11510 => 16, // JEFE OFICINA
                11511 => 17, // TAQUILLA 01
                11512 => 18, // TAQUILLA 02
                11513 => 19, // TAQUILLA 03
                11514 => 20, // ATU 01
                11515 => 21, // IMPRESORA RED
                // Sede 7 - CORDINACION TRASMISION
                11516 => 22, // EQUIPO 01
                11517 => 23, // EQUIPO 02
                11518 => 24, // EQUIPO 03
            ];

            foreach ($duplicatePairs as $oldId => $canonicalId) {
                // Traspasar Actas oficiales de asignación (UINF-4.05 / PASE-MOV)
                DB::table('inventory_assignments')
                    ->where('inventory_asset_id', $oldId)
                    ->update(['inventory_asset_id' => $canonicalId]);

                // Traspasar Periféricos
                DB::table('inventory_peripherals')
                    ->where('current_asset_id', $oldId)
                    ->update(['current_asset_id' => $canonicalId]);

                // Traspasar Componentes de hardware
                DB::table('inventory_components')
                    ->where('inventory_asset_id', $oldId)
                    ->update(['inventory_asset_id' => $canonicalId]);

                // Traspasar Historiales de componentes
                DB::table('inventory_components_histories')
                    ->where('inventory_asset_id', $oldId)
                    ->update(['inventory_asset_id' => $canonicalId]);

                // Traspasar Interfaces de red
                DB::table('inventory_asset_network_interfaces')
                    ->where('inventory_asset_id', $oldId)
                    ->update(['inventory_asset_id' => $canonicalId]);

                // Traspasar Tickets de mantenimiento
                DB::table('maintenance_tickets')
                    ->where('inventory_asset_id', $oldId)
                    ->update(['inventory_asset_id' => $canonicalId]);

                // Traspasar Dispositivos SNMP vinculados
                DB::table('snmp_devices')
                    ->where('network_device_id', $oldId)
                    ->update(['network_device_id' => $canonicalId]);

                // Traspasar Configuraciones de dispositivo
                DB::table('device_configurations')
                    ->where('network_device_id', $oldId)
                    ->update(['network_device_id' => $canonicalId]);

                // Traspasar Dispositivos descubiertos
                DB::table('discovered_devices')
                    ->where('linked_network_device_id', $oldId)
                    ->update(['linked_network_device_id' => $canonicalId]);

                // Traspasar Historial de chequeos de telemetría
                DB::table('network_device_check_histories')
                    ->where('monitored_network_device_id', $oldId)
                    ->update(['monitored_network_device_id' => $canonicalId]);

                // Traspasar Bitácora de auditoría inmutable
                DB::table('audit_logs')
                    ->where('auditable_type', 'like', '%Asset%')
                    ->where('auditable_id', (string) $oldId)
                    ->update(['auditable_id' => (string) $canonicalId]);

                // Eliminar el registro duplicado viejo en inventory_assets
                DB::table('inventory_assets')->where('id', $oldId)->delete();
            }

            // Sincronizar monitored_network_device_id con el id propio
            DB::statement('UPDATE inventory_assets SET monitored_network_device_id = id WHERE monitored_network_device_id IS NULL OR monitored_network_device_id != id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Operación de saneamiento de integridad - irreversible por diseño relacional
    }
};
