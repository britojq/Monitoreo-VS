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
        // Deshabilitar temporalmente chequeo de claves foráneas para reorganización atómica
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        // 1. Ampliar y acondicionar inventory_assets para ser la ÚNICA tabla maestra
        Schema::table('inventory_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_assets', 'device_number')) {
                $table->integer('device_number')->nullable()->after('monitored_site_id');
            }
            if (!Schema::hasColumn('inventory_assets', 'access_type')) {
                $table->string('access_type', 50)->default('SIN SOPORTE')->after('device_number');
            }
            if (!Schema::hasColumn('inventory_assets', 'access_port')) {
                $table->unsignedInteger('access_port')->nullable()->after('access_type');
            }
            if (!Schema::hasColumn('inventory_assets', 'ssh_username')) {
                $table->string('ssh_username', 100)->nullable()->after('access_port');
            }
            if (!Schema::hasColumn('inventory_assets', 'ssh_password_encrypted')) {
                $table->text('ssh_password_encrypted')->nullable()->after('ssh_username');
            }
            if (!Schema::hasColumn('inventory_assets', 'ssh_enable_secret_encrypted')) {
                $table->text('ssh_enable_secret_encrypted')->nullable()->after('ssh_password_encrypted');
            }
            if (!Schema::hasColumn('inventory_assets', 'ports')) {
                $table->string('ports', 100)->nullable()->after('ssh_enable_secret_encrypted');
            }
            if (!Schema::hasColumn('inventory_assets', 'normal_state_msg')) {
                $table->string('normal_state_msg', 255)->nullable()->after('notes');
            }
            if (!Schema::hasColumn('inventory_assets', 'error_state_msg')) {
                $table->string('error_state_msg', 255)->nullable()->after('normal_state_msg');
            }
            if (!Schema::hasColumn('inventory_assets', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('error_state_msg');
            }
            if (!Schema::hasColumn('inventory_assets', 'telegram_alert_enabled')) {
                $table->boolean('telegram_alert_enabled')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('inventory_assets', 'telegram_alert_target')) {
                $table->string('telegram_alert_target', 20)->default('owner')->after('telegram_alert_enabled');
            }
            if (!Schema::hasColumn('inventory_assets', 'last_alert_state')) {
                $table->string('last_alert_state', 10)->nullable()->after('telegram_alert_target');
            }
            if (!Schema::hasColumn('inventory_assets', 'down_since')) {
                $table->timestamp('down_since')->nullable()->after('last_alert_state');
            }
            if (!Schema::hasColumn('inventory_assets', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('down_since');
            }
        });

        // Modificar longitud de columnas para máxima compatibilidad
        DB::statement('ALTER TABLE inventory_assets MODIFY name VARCHAR(255) NOT NULL;');
        DB::statement('ALTER TABLE inventory_assets MODIFY brand VARCHAR(255) NULL;');
        DB::statement('ALTER TABLE inventory_assets MODIFY model VARCHAR(255) NULL;');

        // 2. Si monitored_network_devices es tabla física, sincronizar campos técnicos a inventory_assets
        $isTablePhysical = DB::selectOne("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'monitored_network_devices'");
        if ($isTablePhysical && $isTablePhysical->TABLE_TYPE === 'BASE TABLE') {
            $networkDevices = DB::table('monitored_network_devices')->get();
            foreach ($networkDevices as $nd) {
                $matchingAsset = DB::table('inventory_assets')
                    ->where('ip_address', $nd->ip)
                    ->orWhere('monitored_network_device_id', $nd->id)
                    ->first();

                if ($matchingAsset) {
                    DB::table('inventory_assets')->where('id', $matchingAsset->id)->update([
                        'device_number' => $nd->device_number,
                        'access_type' => $nd->access_type,
                        'access_port' => $nd->access_port,
                        'ssh_username' => $nd->ssh_username,
                        'ssh_password_encrypted' => $nd->ssh_password_encrypted,
                        'ssh_enable_secret_encrypted' => $nd->ssh_enable_secret_encrypted,
                        'ports' => $nd->ports,
                        'normal_state_msg' => $nd->normal_state_msg ?: "✅ - {$nd->name}",
                        'error_state_msg' => $nd->error_state_msg ?: "❌ - {$nd->name}",
                        'is_active' => $nd->is_active,
                        'telegram_alert_enabled' => $nd->telegram_alert_enabled,
                        'telegram_alert_target' => $nd->telegram_alert_target,
                        'last_alert_state' => $nd->last_alert_state,
                        'down_since' => $nd->down_since,
                        'sort_order' => $nd->sort_order,
                        'brand' => $matchingAsset->brand ?: $nd->vendor_data,
                        'model' => $matchingAsset->model ?: $nd->model,
                        'serial_number' => ($nd->serial && $nd->serial !== '00000000') ? $nd->serial : $matchingAsset->serial_number,
                        'monitored_network_device_id' => $nd->id,
                        'monitored_site_id' => $nd->monitored_site_id ?: $matchingAsset->monitored_site_id,
                    ]);
                }
            }

            // Servidor de desarrollo local (192.168.1.220)
            $devAsset = DB::table('inventory_assets')->where('ip_address', '192.168.1.220')->first();
            if ($devAsset) {
                DB::table('inventory_assets')->where('id', $devAsset->id)->update([
                    'device_number' => 8,
                    'access_type' => 'SSH',
                    'access_port' => 22,
                    'is_active' => 1,
                    'sort_order' => 25,
                    'monitored_site_id' => 1,
                    'normal_state_msg' => '✅ - CENCARATIT (Desarrollo)',
                    'error_state_msg' => '❌ - CENCARATIT (Desarrollo)',
                ]);
            }

            // 3. Alinear IDs primarios de inventory_assets con monitored_network_devices.id
            // Mapeo unificado para preservar integridad absoluta con los 161,796 historiales
            $idMapping = [
                // Asset actual -> ID objetivo de monitoreo
                186 => 1,     // ROUTER PRINCIPAL
                187 => 2,     // SW01 - Cisco Principal
                188 => 3,     // SW02 - Cisco Piso 1
                189 => 4,     // SW03 - Cisco Servidores
                190 => 5,     // ROUTER WIFI SIGECOM
                191 => 6,     // EQUIPO MONITOREO ATIT
                192 => 7,     // SW04 - Hon Hai Acceso
                193 => 11502, // JEFE OFICINA (Sede 3)
                194 => 11503, // TAQUILLA 01 (Sede 3)
                195 => 11504, // TAQUILLA 02 (Sede 3)
                196 => 11505, // ATU 01 (Sede 3)
                197 => 11506, // IMPRESORA RED (Sede 3)
                151 => 11507, // JEFE OFICINA / CENCARCOM012010 (Sede 4)
                198 => 11508, // TAQUILLA 01 (Sede 4)
                199 => 11509, // TAQUILLA 02 (Sede 4)
                200 => 11510, // JEFE OFICINA (Sede 5)
                201 => 11511, // TAQUILLA 01 (Sede 5)
                202 => 11512, // TAQUILLA 02 (Sede 5)
                203 => 11513, // TAQUILLA 03 (Sede 5)
                204 => 11514, // ATU 01 (Sede 5)
                205 => 11515, // IMPRESORA RED (Sede 5)
                206 => 11516, // EQUIPO 01 (Sede 7)
                207 => 11517, // EQUIPO 02 (Sede 7)
                208 => 11518, // EQUIPO 03 (Sede 7)
            ];

            // A) Mover temporalmente el servidor de desarrollo (ID 1) a 999999 para liberar ID 1
            $currentDev = DB::table('inventory_assets')->where('id', 1)->first();
            if ($currentDev) {
                DB::table('inventory_components')->where('inventory_asset_id', 1)->update(['inventory_asset_id' => 999999]);
                DB::table('inventory_peripherals')->where('current_asset_id', 1)->update(['current_asset_id' => 999999]);
                DB::table('inventory_components_histories')->where('inventory_asset_id', 1)->update(['inventory_asset_id' => 999999]);
                DB::table('inventory_assignments')->where('inventory_asset_id', 1)->update(['inventory_asset_id' => 999999]);
                DB::table('maintenance_tickets')->where('inventory_asset_id', 1)->update(['inventory_asset_id' => 999999]);
                DB::table('inventory_assets')->where('id', 1)->update(['id' => 999999]);
            }

            // B) Reasignar cada activo a su ID oficial
            foreach ($idMapping as $oldId => $targetId) {
                $exists = DB::table('inventory_assets')->where('id', $oldId)->exists();
                if ($exists) {
                    DB::table('inventory_components')->where('inventory_asset_id', $oldId)->update(['inventory_asset_id' => $targetId]);
                    DB::table('inventory_peripherals')->where('current_asset_id', $oldId)->update(['current_asset_id' => $targetId]);
                    DB::table('inventory_components_histories')->where('inventory_asset_id', $oldId)->update(['inventory_asset_id' => $targetId]);
                    DB::table('inventory_assignments')->where('inventory_asset_id', $oldId)->update(['inventory_asset_id' => $targetId]);
                    DB::table('maintenance_tickets')->where('inventory_asset_id', $oldId)->update(['inventory_asset_id' => $targetId]);
                    DB::table('inventory_assets')->where('id', $oldId)->update(['id' => $targetId]);
                }
            }

            // C) Mover el servidor de desarrollo de 999999 a 11519
            $tempDev = DB::table('inventory_assets')->where('id', 999999)->first();
            if ($tempDev) {
                DB::table('inventory_components')->where('inventory_asset_id', 999999)->update(['inventory_asset_id' => 11519]);
                DB::table('inventory_peripherals')->where('current_asset_id', 999999)->update(['current_asset_id' => 11519]);
                DB::table('inventory_components_histories')->where('inventory_asset_id', 999999)->update(['inventory_asset_id' => 11519]);
                DB::table('inventory_assignments')->where('inventory_asset_id', 999999)->update(['inventory_asset_id' => 11519]);
                DB::table('maintenance_tickets')->where('inventory_asset_id', 999999)->update(['inventory_asset_id' => 11519]);
                DB::table('inventory_assets')->where('id', 999999)->update(['id' => 11519]);
            }

            // Sincronizar campo monitored_network_device_id consigo mismo
            DB::statement('UPDATE inventory_assets SET monitored_network_device_id = id;');
            DB::statement('ALTER TABLE inventory_assets AUTO_INCREMENT = 12000;');

            // 4. Repuntar Foreign Keys a inventory_assets(id)
            // Primero eliminar constraints existentes
            $foreignKeysToDrop = [
                ['inventory_assets', 'inventory_assets_monitored_network_device_id_foreign'],
                ['network_device_check_histories', 'ndch_device_fk'],
                ['device_configurations', 'device_configurations_network_device_id_foreign'],
                ['snmp_devices', 'snmp_devices_network_device_id_foreign'],
                ['discovered_devices', 'discovered_devices_linked_network_device_id_foreign'],
            ];

            foreach ($foreignKeysToDrop as [$table, $fkName]) {
                $exists = DB::selectOne("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = ? 
                      AND CONSTRAINT_NAME = ?
                ", [$table, $fkName]);

                if ($exists) {
                    DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`;");
                }
            }

            // Eliminar tablas físicas redundantes
            DB::statement('DROP TABLE IF EXISTS monitored_site_devices;');
            DB::statement('DROP TABLE IF EXISTS monitored_network_devices;');

            // Recrear las claves foráneas apuntando directamente a inventory_assets(id)
            DB::statement('
                ALTER TABLE network_device_check_histories 
                ADD CONSTRAINT ndch_device_fk 
                FOREIGN KEY (monitored_network_device_id) REFERENCES inventory_assets(id) ON DELETE CASCADE;
            ');

            DB::statement('
                ALTER TABLE device_configurations 
                ADD CONSTRAINT device_configurations_network_device_id_foreign 
                FOREIGN KEY (network_device_id) REFERENCES inventory_assets(id) ON DELETE CASCADE;
            ');

            DB::statement('
                ALTER TABLE snmp_devices 
                ADD CONSTRAINT snmp_devices_network_device_id_foreign 
                FOREIGN KEY (network_device_id) REFERENCES inventory_assets(id) ON DELETE SET NULL;
            ');

            DB::statement('
                ALTER TABLE discovered_devices 
                ADD CONSTRAINT discovered_devices_linked_network_device_id_foreign 
                FOREIGN KEY (linked_network_device_id) REFERENCES inventory_assets(id) ON DELETE SET NULL;
            ');
        }

        // 5. Crear Vistas SQL Updatable 100% compatibles con el ecosistema
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

        // 6. Trigger para auto-clasificación inteligente en inventory_assets si se inserta desde legacy
        DB::statement('DROP TRIGGER IF EXISTS trg_inventory_assets_before_insert;');
        DB::statement("
            CREATE TRIGGER trg_inventory_assets_before_insert
            BEFORE INSERT ON inventory_assets
            FOR EACH ROW
            BEGIN
                IF NEW.type = 'desktop' OR NEW.type IS NULL THEN
                    IF UPPER(NEW.name) LIKE '%ROUTER%' THEN
                        SET NEW.type = 'router';
                    ELSEIF UPPER(NEW.name) LIKE '%SWITCH%' OR UPPER(NEW.name) LIKE '%SW%' THEN
                        SET NEW.type = 'switch';
                    ELSEIF UPPER(NEW.name) LIKE '%IMPRESORA%' OR UPPER(NEW.name) LIKE '%PRINTER%' THEN
                        SET NEW.type = 'printer';
                    ELSEIF UPPER(NEW.name) LIKE '%SERVIDOR%' OR UPPER(NEW.name) LIKE '%SERVER%' OR UPPER(NEW.name) LIKE '%MONITOREO%' THEN
                        SET NEW.type = 'server';
                    END IF;
                END IF;

                IF NEW.monitored_site_id IS NOT NULL AND (NEW.status IS NULL OR NEW.status = 'in_stock') THEN
                    SET NEW.status = 'assigned';
                END IF;

                IF NEW.asset_tag IS NULL OR NEW.asset_tag = '' THEN
                    SET NEW.asset_tag = CONCAT('AST-', LPAD(FLOOR(RAND() * 89999 + 10000), 5, '0'));
                END IF;
            END;
        ");

        // Reactivar chequeo de claves foráneas
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        DB::statement('DROP TRIGGER IF EXISTS trg_inventory_assets_before_insert;');
        DB::statement('DROP VIEW IF EXISTS monitored_site_devices;');
        DB::statement('DROP VIEW IF EXISTS monitored_network_devices;');

        // Recrear tablas físicas a partir de inventory_assets si fuera necesario revertir
        Schema::create('monitored_network_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monitored_site_id')->nullable();
            $table->integer('device_number')->default(1);
            $table->string('name');
            $table->string('ip')->unique();
            $table->string('mac')->nullable();
            $table->string('vendor_data')->nullable();
            $table->string('access_type', 20)->default('SIN SOPORTE');
            $table->integer('access_port')->nullable();
            $table->string('ssh_username')->nullable();
            $table->text('ssh_password_encrypted')->nullable();
            $table->text('ssh_enable_secret_encrypted')->nullable();
            $table->string('model')->nullable();
            $table->string('serial')->nullable();
            $table->string('ports')->nullable();
            $table->text('notes')->nullable();
            $table->string('normal_state_msg')->nullable();
            $table->string('error_state_msg')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('telegram_alert_enabled')->default(false);
            $table->string('telegram_alert_target', 20)->default('owner');
            $table->string('last_alert_state', 10)->nullable();
            $table->timestamp('down_since')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('monitored_site_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monitored_site_id');
            $table->integer('device_number')->nullable();
            $table->string('name');
            $table->string('ip');
            $table->string('mac')->nullable();
            $table->string('vendor_data')->nullable();
            $table->string('model')->nullable();
            $table->string('serial')->nullable();
            $table->string('ports')->nullable();
            $table->string('access_type', 50)->default('SIN SOPORTE');
            $table->unsignedInteger('access_port')->nullable();
            $table->text('notes')->nullable();
            $table->string('normal_state_msg')->nullable();
            $table->string('error_state_msg')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('telegram_alert_enabled')->default(false);
            $table->string('telegram_alert_target', 20)->default('owner');
            $table->string('last_alert_state', 10)->nullable();
            $table->timestamp('down_since')->nullable();
            $table->timestamps();
        });

        // Repoblar tablas desde inventory_assets
        $assets = DB::table('inventory_assets')->get();
        foreach ($assets as $a) {
            DB::table('monitored_network_devices')->insert([
                'id' => $a->id,
                'monitored_site_id' => $a->monitored_site_id,
                'device_number' => $a->device_number ?? 1,
                'name' => $a->name,
                'ip' => $a->ip_address,
                'mac' => $a->mac_address,
                'vendor_data' => $a->brand,
                'access_type' => $a->access_type,
                'access_port' => $a->access_port,
                'ssh_username' => $a->ssh_username,
                'ssh_password_encrypted' => $a->ssh_password_encrypted,
                'ssh_enable_secret_encrypted' => $a->ssh_enable_secret_encrypted,
                'model' => $a->model,
                'serial' => $a->serial_number,
                'ports' => $a->ports,
                'notes' => $a->notes,
                'normal_state_msg' => $a->normal_state_msg,
                'error_state_msg' => $a->error_state_msg,
                'is_active' => $a->is_active,
                'telegram_alert_enabled' => $a->telegram_alert_enabled,
                'telegram_alert_target' => $a->telegram_alert_target,
                'last_alert_state' => $a->last_alert_state,
                'down_since' => $a->down_since,
                'sort_order' => $a->sort_order,
                'created_at' => $a->created_at,
                'updated_at' => $a->updated_at,
            ]);

            DB::table('monitored_site_devices')->insert([
                'id' => $a->id,
                'monitored_site_id' => $a->monitored_site_id ?: 1,
                'device_number' => $a->device_number,
                'name' => $a->name,
                'ip' => $a->ip_address,
                'mac' => $a->mac_address,
                'vendor_data' => $a->brand,
                'model' => $a->model,
                'serial' => $a->serial_number,
                'ports' => $a->ports,
                'access_type' => $a->access_type,
                'access_port' => $a->access_port,
                'notes' => $a->notes,
                'normal_state_msg' => $a->normal_state_msg,
                'error_state_msg' => $a->error_state_msg,
                'is_active' => $a->is_active,
                'telegram_alert_enabled' => $a->telegram_alert_enabled,
                'telegram_alert_target' => $a->telegram_alert_target,
                'last_alert_state' => $a->last_alert_state,
                'down_since' => $a->down_since,
                'created_at' => $a->created_at,
                'updated_at' => $a->updated_at,
            ]);
        }

        // Restaurar FKs a monitored_network_devices
        DB::statement('
            ALTER TABLE network_device_check_histories 
            ADD CONSTRAINT ndch_device_fk 
            FOREIGN KEY (monitored_network_device_id) REFERENCES monitored_network_devices(id) ON DELETE CASCADE;
        ');

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }
};
