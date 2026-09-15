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
        // 1. Ampliar campos técnicos en monitored_site_devices para unificar esquema
        Schema::table('monitored_site_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('monitored_site_devices', 'mac')) {
                $table->string('mac', 50)->nullable()->after('ip');
            }
            if (!Schema::hasColumn('monitored_site_devices', 'vendor_data')) {
                $table->string('vendor_data', 255)->nullable()->after('mac');
            }
            if (!Schema::hasColumn('monitored_site_devices', 'model')) {
                $table->string('model', 255)->nullable()->after('vendor_data');
            }
            if (!Schema::hasColumn('monitored_site_devices', 'serial')) {
                $table->string('serial', 100)->nullable()->after('model');
            }
            if (!Schema::hasColumn('monitored_site_devices', 'ports')) {
                $table->string('ports', 100)->nullable()->after('serial');
            }
            if (!Schema::hasColumn('monitored_site_devices', 'access_type')) {
                $table->string('access_type', 50)->default('SIN SOPORTE')->after('ports');
            }
            if (!Schema::hasColumn('monitored_site_devices', 'access_port')) {
                $table->unsignedInteger('access_port')->nullable()->after('access_type');
            }
            if (!Schema::hasColumn('monitored_site_devices', 'notes')) {
                $table->text('notes')->nullable()->after('access_port');
            }
        });

        // 2. Asociar monitored_network_devices a monitored_sites (relación opcional de sede)
        Schema::table('monitored_network_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('monitored_network_devices', 'monitored_site_id')) {
                $table->foreignId('monitored_site_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('monitored_sites')
                    ->nullOnDelete();
            }
        });

        // 3. Limpiar registros "fantasmas" / slots vacíos generados por el legado de monitoreo.conf
        DB::table('monitored_site_devices')
            ->where('name', 'NO CONFIGURADO')
            ->orWhere('name', 'SIN CONFIGURAR')
            ->orWhere('ip', '0.0.0.0')
            ->delete();

        // 4. Asignar sede Valle Seco (ID: 1) a los dispositivos de red locales existentes si no tienen sede
        $valleSecoSite = DB::table('monitored_sites')->where('name', 'LIKE', '%VALLE SECO%')->first();
        if ($valleSecoSite) {
            DB::table('monitored_network_devices')
                ->whereNull('monitored_site_id')
                ->update(['monitored_site_id' => $valleSecoSite->id]);
        }

        // 5. Limpiar sedes inactivas con nombre NO CONFIGURADO o IP 0.0.0.0
        DB::table('monitored_sites')
            ->where('name', 'NO CONFIGURADO')
            ->orWhere(function ($q) {
                $q->where('ip', '0.0.0.0')->where('is_active', false);
            })
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitored_network_devices', function (Blueprint $table) {
            if (Schema::hasColumn('monitored_network_devices', 'monitored_site_id')) {
                $table->dropForeign(['monitored_site_id']);
                $table->dropColumn('monitored_site_id');
            }
        });

        Schema::table('monitored_site_devices', function (Blueprint $table) {
            $cols = ['mac', 'vendor_data', 'model', 'serial', 'ports', 'access_type', 'access_port', 'notes'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('monitored_site_devices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
