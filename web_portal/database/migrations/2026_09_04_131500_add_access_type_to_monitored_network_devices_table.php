<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monitored_network_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('monitored_network_devices', 'access_type')) {
                $table->string('access_type', 20)->default('SIN SOPORTE')->after('vendor_data');
            }
            if (!Schema::hasColumn('monitored_network_devices', 'access_port')) {
                $table->integer('access_port')->nullable()->after('access_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitored_network_devices', function (Blueprint $table) {
            $table->dropColumn(['access_type', 'access_port']);
        });
    }
};
