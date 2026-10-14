<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Corregir el disparador en MariaDB para que NO fuerce el estado 'assigned'
        // simplemente por tener una sede física asignada. El estado 'assigned' debe
        // pertenecer exclusivamente a equipos que cuenten con un Acta de Asignación.
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

                IF NEW.status IS NULL THEN
                    SET NEW.status = 'in_stock';
                END IF;

                IF NEW.asset_tag IS NULL OR NEW.asset_tag = '' THEN
                    SET NEW.asset_tag = CONCAT('AST-', LPAD(FLOOR(RAND() * 89999 + 10000), 5, '0'));
                END IF;
            END;
        ");

        // 2. Saneamiento de datos: los equipos que no cuenten con un Acta de Asignación
        // activa en la tabla inventory_assignments deben pasar a su condición real 'in_stock' (Sin Asignación).
        DB::statement("
            UPDATE inventory_assets
            SET status = 'in_stock'
            WHERE status = 'assigned'
              AND id NOT IN (
                  SELECT inventory_asset_id FROM inventory_assignments WHERE status = 'active'
              );
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
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
    }
};
