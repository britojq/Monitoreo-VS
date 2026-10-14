<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Agregar columnas dedicadas de protocolos en inventory_assets
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->unsignedInteger('ssh_port')->nullable()->after('access_port');
            $table->unsignedInteger('vnc_port')->nullable()->after('ssh_port');
            $table->unsignedInteger('web_port')->nullable()->after('vnc_port');
            $table->unsignedInteger('telnet_port')->nullable()->after('web_port');
            $table->text('vnc_password_encrypted')->nullable()->after('ssh_enable_secret_encrypted');
        });

        // 2. Poblar datos iniciales basados en access_type y access_port existentes
        DB::statement("
            UPDATE inventory_assets 
            SET 
                ssh_port = CASE 
                    WHEN access_type = 'SSH' THEN COALESCE(access_port, 22)
                    WHEN access_type = 'SSH+VNC' THEN 22
                    ELSE ssh_port
                END,
                vnc_port = CASE 
                    WHEN access_type = 'VNC' THEN COALESCE(access_port, 5900)
                    WHEN access_type = 'SSH+VNC' THEN 5900
                    ELSE vnc_port
                END,
                telnet_port = CASE 
                    WHEN access_type = 'TELNET' THEN COALESCE(access_port, 23)
                    ELSE telnet_port
                END,
                web_port = CASE 
                    WHEN access_type = 'WEB' THEN COALESCE(access_port, 80)
                    ELSE web_port
                END
        ");

        // 3. Recrear Vista SQL monitored_network_devices proyectando las nuevas columnas
        DB::statement('DROP VIEW IF EXISTS monitored_network_devices;');
        DB::statement('
            CREATE VIEW monitored_network_devices AS
            SELECT 
                id,
                monitored_site_id,
                device_number,
                name,
                ip_address AS ip,
                mac_address AS mac,
                brand AS vendor_data,
                access_type,
                access_port,
                ssh_port,
                vnc_port,
                web_port,
                telnet_port,
                ssh_username,
                ssh_password_encrypted,
                ssh_enable_secret_encrypted,
                vnc_password_encrypted,
                model,
                serial_number AS serial,
                ports,
                notes,
                normal_state_msg,
                error_state_msg,
                is_active,
                telegram_alert_enabled,
                telegram_alert_target,
                last_alert_state,
                down_since,
                sort_order,
                created_at,
                updated_at
            FROM inventory_assets;
        ');

        // 4. Recrear Vista SQL monitored_site_devices
        DB::statement('DROP VIEW IF EXISTS monitored_site_devices;');
        DB::statement('
            CREATE VIEW monitored_site_devices AS
            SELECT 
                id,
                monitored_site_id,
                device_number,
                name,
                ip_address AS ip,
                mac_address AS mac,
                brand AS vendor_data,
                model,
                serial_number AS serial,
                ports,
                access_type,
                access_port,
                ssh_port,
                vnc_port,
                web_port,
                telnet_port,
                notes,
                normal_state_msg,
                error_state_msg,
                is_active,
                telegram_alert_enabled,
                telegram_alert_target,
                last_alert_state,
                down_since,
                created_at,
                updated_at
            FROM inventory_assets;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir vistas al esquema original
        DB::statement('DROP VIEW IF EXISTS monitored_network_devices;');
        DB::statement('
            CREATE VIEW monitored_network_devices AS
            SELECT 
                id,
                monitored_site_id,
                device_number,
                name,
                ip_address AS ip,
                mac_address AS mac,
                brand AS vendor_data,
                access_type,
                access_port,
                ssh_username,
                ssh_password_encrypted,
                ssh_enable_secret_encrypted,
                model,
                serial_number AS serial,
                ports,
                notes,
                normal_state_msg,
                error_state_msg,
                is_active,
                telegram_alert_enabled,
                telegram_alert_target,
                last_alert_state,
                down_since,
                sort_order,
                created_at,
                updated_at
            FROM inventory_assets;
        ');

        DB::statement('DROP VIEW IF EXISTS monitored_site_devices;');
        DB::statement('
            CREATE VIEW monitored_site_devices AS
            SELECT 
                id,
                monitored_site_id,
                device_number,
                name,
                ip_address AS ip,
                mac_address AS mac,
                brand AS vendor_data,
                model,
                serial_number AS serial,
                ports,
                access_type,
                access_port,
                notes,
                normal_state_msg,
                error_state_msg,
                is_active,
                telegram_alert_enabled,
                telegram_alert_target,
                last_alert_state,
                down_since,
                created_at,
                updated_at
            FROM inventory_assets;
        ');

        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropColumn([
                'ssh_port',
                'vnc_port',
                'web_port',
                'telnet_port',
                'vnc_password_encrypted',
            ]);
        });
    }
};
