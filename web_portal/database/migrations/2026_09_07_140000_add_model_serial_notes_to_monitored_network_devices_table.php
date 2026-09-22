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
            if (!Schema::hasColumn('monitored_network_devices', 'model')) {
                $table->string('model', 255)->nullable()->after('access_port');
            }
            if (!Schema::hasColumn('monitored_network_devices', 'serial')) {
                $table->string('serial', 100)->nullable()->after('model');
            }
            if (!Schema::hasColumn('monitored_network_devices', 'ports')) {
                $table->string('ports', 100)->nullable()->after('serial');
            }
            if (!Schema::hasColumn('monitored_network_devices', 'notes')) {
                $table->text('notes')->nullable()->after('ports');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitored_network_devices', function (Blueprint $table) {
            $table->dropColumn(['model', 'serial', 'ports', 'notes']);
        });
    }
};
