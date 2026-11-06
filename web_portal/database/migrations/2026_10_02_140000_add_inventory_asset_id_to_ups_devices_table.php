<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('ups_devices') && !Schema::hasColumn('ups_devices', 'inventory_asset_id')) {
            Schema::table('ups_devices', function (Blueprint $table) {
                $table->foreignId('inventory_asset_id')
                    ->nullable()
                    ->after('baud_rate')
                    ->constrained('inventory_assets')
                    ->nullOnDelete();
            });

            // Asociar automáticamente con el servidor de producción (EQUIPO MONITOREO ATIT / 192.168.1.250)
            $prodAsset = DB::table('inventory_assets')
                ->where('ip_address', '192.168.1.250')
                ->orWhere('name', 'like', '%MONITOREO%')
                ->first();

            if ($prodAsset) {
                DB::table('ups_devices')
                    ->whereNull('inventory_asset_id')
                    ->update(['inventory_asset_id' => $prodAsset->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ups_devices') && Schema::hasColumn('ups_devices', 'inventory_asset_id')) {
            Schema::table('ups_devices', function (Blueprint $table) {
                $table->dropForeign(['inventory_asset_id']);
                $table->dropColumn('inventory_asset_id');
            });
        }
    }
};
