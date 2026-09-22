<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Modificar tabla monitored_services
        Schema::table('monitored_services', function (Blueprint $table) {
            if (!Schema::hasColumn('monitored_services', 'scope')) {
                $table->string('scope', 30)->default('corporativo')->after('type');
            }
        });

        // Permitir que letter sea nullable y eliminar restricción UNIQUE si existe
        try {
            DB::statement("ALTER TABLE monitored_services DROP INDEX monitored_services_letter_unique");
        } catch (\Throwable $e) {
            // Ya eliminada o no existe
        }
        DB::statement("ALTER TABLE monitored_services MODIFY letter VARCHAR(10) NULL");

        // Asignar scope regional a los servicios regionales conocidos
        DB::table('monitored_services')
            ->whereIn('letter', ['L', 'M', 'N', 'O', 'Q', 'R', 'S', 'T'])
            ->update(['scope' => 'regional']);

        // 2. Modificar tabla monitored_sites
        try {
            DB::statement("ALTER TABLE monitored_sites DROP INDEX monitored_sites_letter_unique");
        } catch (\Throwable $e) {
            // Ya eliminada o no existe
        }
        DB::statement("ALTER TABLE monitored_sites MODIFY letter VARCHAR(10) NULL");

        // 3. Modificar tabla monitored_site_devices (hacer device_number nullable)
        try {
            DB::statement("ALTER TABLE monitored_site_devices MODIFY device_number INT(11) NULL");
        } catch (\Throwable $e) {
            // Si ya permite null
        }
    }

    public function down(): void
    {
        Schema::table('monitored_services', function (Blueprint $table) {
            if (Schema::hasColumn('monitored_services', 'scope')) {
                $table->dropColumn('scope');
            }
        });
    }
};
