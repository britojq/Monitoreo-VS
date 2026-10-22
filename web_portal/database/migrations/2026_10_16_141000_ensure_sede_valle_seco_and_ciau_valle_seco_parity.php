<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Restaura la identidad de 'SEDE VALLE SECO' como edificio principal (ID: 1)
     * e incorpora 'CIAU VALLE SECO' como oficina comercial (ID: 2) compartiendo el segmento 192.168.1.0/24.
     */
    public function up(): void
    {
        // 1. Actualizar Site ID 1 como SEDE VALLE SECO (Edificio Central)
        DB::table('monitored_sites')->where('id', 1)->update([
            'letter' => 'A',
            'name' => 'SEDE VALLE SECO',
            'ip' => '192.168.1.1',
            'address' => 'Urb. Colinas de Valle Seco, final Av. Bolívar. Edificio CALIFE., Puerto Cabello',
            'normal_state_msg' => '✅ - SEDE VALLE SECO',
            'error_state_msg' => '❌ - SEDE VALLE SECO',
            'is_active' => 1,
            'sort_order' => 1,
            'updated_at' => now(),
        ]);

        // 2. Insertar o actualizar CIAU VALLE SECO (Oficina Comercial interna)
        $existingCiau = DB::table('monitored_sites')->where('name', 'CIAU VALLE SECO')->first();
        if ($existingCiau) {
            DB::table('monitored_sites')->where('id', $existingCiau->id)->update([
                'letter' => 'B',
                'name' => 'CIAU VALLE SECO',
                'ip' => '192.168.1.1',
                'phone_1' => '0242-3610061',
                'phone_2' => '0242-3613722',
                'phone_3' => '0242-3612410',
                'phone_4' => '0242-3614625',
                'phone_5' => '0242-3616717',
                'phone_6' => '0242-3610539',
                'phone_7' => '0242-3612140',
                'phone_8' => '0242-3612140',
                'address' => 'Urb. Colinas de Valle Seco, final Av. Bolívar. Edificio CALIFE., Oficina Comercial CIAU, Puerto Cabello',
                'normal_state_msg' => '✅ - CIAU VALLE SECO',
                'error_state_msg' => '❌ - CIAU VALLE SECO',
                'is_active' => 1,
                'sort_order' => 2,
                'updated_at' => now(),
            ]);
        } else {
            // Verificar si el ID 2 está ocupado
            $id2Occupied = DB::table('monitored_sites')->where('id', 2)->exists();
            $targetId = $id2Occupied ? null : 2;

            $insertData = [
                'letter' => 'B',
                'name' => 'CIAU VALLE SECO',
                'ip' => '192.168.1.1',
                'phone_1' => '0242-3610061',
                'phone_2' => '0242-3613722',
                'phone_3' => '0242-3612410',
                'phone_4' => '0242-3614625',
                'phone_5' => '0242-3616717',
                'phone_6' => '0242-3610539',
                'phone_7' => '0242-3612140',
                'phone_8' => '0242-3612140',
                'address' => 'Urb. Colinas de Valle Seco, final Av. Bolívar. Edificio CALIFE., Oficina Comercial CIAU, Puerto Cabello',
                'normal_state_msg' => '✅ - CIAU VALLE SECO',
                'error_state_msg' => '❌ - CIAU VALLE SECO',
                'is_active' => 1,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($targetId !== null) {
                $insertData['id'] = $targetId;
            }

            DB::table('monitored_sites')->insert($insertData);
        }

        // 3. Normalizar sort_order de las demás sedes oficiales
        DB::table('monitored_sites')->where('id', 3)->update(['sort_order' => 3, 'letter' => 'C']);
        DB::table('monitored_sites')->where('id', 4)->update(['sort_order' => 4, 'letter' => 'D']);
        DB::table('monitored_sites')->where('id', 5)->update(['sort_order' => 5, 'letter' => 'E']);
        DB::table('monitored_sites')->where('id', 7)->update(['sort_order' => 6, 'letter' => 'G']);

        // 4. Actualizar location_detail de equipos troncales del ID 1 a SEDE VALLE SECO
        DB::table('inventory_assets')
            ->where('monitored_site_id', 1)
            ->where('location_detail', 'CIAU VALLE SECO')
            ->update(['location_detail' => 'SEDE VALLE SECO']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir nombre de ID 1 si fuese necesario
        DB::table('monitored_sites')->where('id', 1)->update([
            'name' => 'CIAU VALLE SECO',
            'normal_state_msg' => '✅ - CIAU VALLE SECO',
            'error_state_msg' => '❌ - CIAU VALLE SECO',
        ]);

        DB::table('monitored_sites')->where('name', 'CIAU VALLE SECO')->where('id', '!=', 1)->delete();
    }
};
