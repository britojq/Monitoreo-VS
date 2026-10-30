<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Purga definitiva de registros de prueba, asignaciones autogeneradas sin acta física
     * y periféricos residuales para cumplir estrictamente con el censo oficial auditado.
     */
    public function up(): void
    {
        DB::transaction(function () {
            // 1. Purgar de inventory_assignments los 7 pases de movimiento de prueba de José Brito
            DB::table('inventory_assignments')
                ->whereIn('assignment_code', [
                    'PASE-MOV-2026-0001',
                    'PASE-MOV-2026-0002',
                    'PASE-MOV-2026-0003',
                    'PASE-MOV-2026-0004',
                    'PASE-MOV-2026-0005',
                    'PASE-MOV-2026-0006',
                    'PASE-MOV-2026-0007',
                ])
                ->delete();

            // 2. Purgar actas autogeneradas sin respaldo de documento físico escaneado
            DB::table('inventory_assignments')
                ->whereIn('assignment_code', [
                    'UINF-4.05-2026-0001',
                    'PASE-MOV-2026-0008',
                    'UINF-4.05-2026-0010',
                    'UINF-4.05-2026-0011',
                    'UINF-4.05-2026-0012',
                    'UINF-4.05-2026-0013',
                ])
                ->delete();

            // 3. Purgar periférico dummy de prueba residual
            DB::table('inventory_peripherals')
                ->where('serial_number', 'V1NBK49-TEST')
                ->orWhere('asset_tag', 'NULL')
                ->delete();

            // 4. Limpiar campos de custodio en inventory_assets que no cuenten con acta física respaldada
            DB::table('inventory_assets')
                ->whereIn('id', [104950, 104952, 104953, 104954, 104955, 11519])
                ->update([
                    'custodian_name' => null,
                    'custodian_id_number' => null,
                    'custodian_department' => null,
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * Revertir la purga (operación irreversible por ser saneamiento forense).
     */
    public function down(): void
    {
        // No-op: los datos eliminados eran pruebas y registros sin respaldo oficial.
    }
};
