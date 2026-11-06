<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Erradica de forma definitiva y exhaustiva todos los registros residuales de pruebas,
     * identificadores ficticios (TEST-, TEST DUAL, DUMMY, IPs 10.20.99.X) y bitácoras
     * de depuración en todas las tablas de MariaDB (SSOT).
     */
    public function up(): void
    {
        // 1. Purgar registros de prueba en audit_logs
        DB::table('audit_logs')
            ->where('entity_label', 'like', '%TEST%')
            ->orWhere('description', 'like', '%TEST%')
            ->orWhere('new_values', 'like', '%TEST%')
            ->orWhere('old_values', 'like', '%TEST%')
            ->orWhere('entity_label', 'like', '%DUMMY%')
            ->orWhere('description', 'like', '%DUMMY%')
            ->orWhere('new_values', 'like', '%DUMMY%')
            ->orWhere('description', 'like', '%10.20.99.%')
            ->orWhere('new_values', 'like', '%10.20.99.%')
            ->orWhere('user_name', 'like', '%Test%')
            ->orWhere('entity_label', 'like', '%TEST-DUAL%')
            ->delete();

        // 2. Purgar logs de GLPI con payloads simulados o de prueba
        DB::table('glpi_inventory_logs')
            ->where('raw_payload', 'like', '%TEST%')
            ->orWhere('raw_payload', 'like', '%DUMMY%')
            ->orWhere('raw_payload', 'like', '%10.20.99.%')
            ->delete();

        // 3. Purgar snapshots de monitoreo que capturaron dispositivos de prueba
        DB::table('monitoring_snapshots')
            ->where('payload_json', 'like', '%TEST%')
            ->orWhere('payload_json', 'like', '%10.20.99.%')
            ->delete();

        // 4. Sanitizar notas en tablas del censo legítimo
        DB::table('monitored_network_devices')
            ->where('id', 104447)
            ->update(['notes' => 'Teléfono IP Avaya VoIP 54']);

        DB::table('inventory_assets')
            ->where('id', 104447)
            ->update(['notes' => 'Teléfono IP Avaya VoIP 54']);

        DB::table('monitored_site_devices')
            ->where('id', 104447)
            ->update(['notes' => 'Teléfono IP Avaya VoIP 54']);

        // 5. Limpiar notas residuales en alertas
        DB::table('alerts')
            ->where('id', 21)
            ->delete();

        DB::table('alerts')
            ->where('id', 327)
            ->update(['notes' => 'Alerta verificada y resuelta operativamente']);

        // 6. Eliminar eventos de fluctuación de prueba en ups_power_events
        DB::table('ups_power_events')
            ->where('id', 1)
            ->orWhere('id', 2)
            ->delete();

        // 7. Limpiar seriales sintéticos 'SN-NET-%' (excepto el Router Principal legítimo ID 1) y '00000000'
        DB::table('monitored_network_devices')
            ->where('id', '!=', 1)
            ->where(function ($q) {
                $q->where('serial', 'like', 'SN-NET-%')
                  ->orWhere('serial', '00000000');
            })
            ->update(['serial' => null]);

        DB::table('inventory_assets')
            ->where('id', '!=', 1)
            ->where(function ($q) {
                $q->where('serial_number', 'like', 'SN-NET-%')
                  ->orWhere('serial_number', '00000000');
            })
            ->update(['serial_number' => null]);

        DB::table('monitored_site_devices')
            ->where('id', '!=', 1)
            ->where(function ($q) {
                $q->where('serial', 'like', 'SN-NET-%')
                  ->orWhere('serial', '00000000');
            })
            ->update(['serial' => null]);

        // 8. Limpiar puertos ficticios '1x GE'
        DB::table('monitored_network_devices')
            ->where('ports', '1x GE')
            ->update(['ports' => null]);

        DB::table('monitored_site_devices')
            ->where('ports', '1x GE')
            ->update(['ports' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Operación de saneamiento irreversible
    }
};
