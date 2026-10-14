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
        Schema::table('inventory_assignments', function (Blueprint $table) {
            $table->string('provenance', 32)->default('stock')->after('movement_type')->comment('nuevo, reparacion, stock, otro');
            $table->string('provenance_other', 128)->nullable()->after('provenance');
            $table->string('phone_extension', 32)->nullable()->after('custodian_role');
            $table->string('checkpoint_name', 128)->nullable()->after('monitored_site_id');
            $table->string('gate_operator_name', 128)->nullable()->after('checkpoint_name');
            $table->string('departure_time', 32)->nullable()->after('gate_operator_name');
            $table->string('entry_time', 32)->nullable()->after('departure_time');
            $table->string('control_number', 64)->nullable()->after('entry_time');
            $table->json('hardware_specs')->nullable()->after('peripherals_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'provenance',
                'provenance_other',
                'phone_extension',
                'checkpoint_name',
                'gate_operator_name',
                'departure_time',
                'entry_time',
                'control_number',
                'hardware_specs',
            ]);
        });
    }
};
