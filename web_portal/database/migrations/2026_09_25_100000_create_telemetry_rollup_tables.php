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
        if (!Schema::hasTable('snmp_metric_hourly_rollups')) {
            Schema::create('snmp_metric_hourly_rollups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('snmp_device_id')->constrained('snmp_devices')->onDelete('cascade');
                $table->foreignId('snmp_oid_id')->constrained('snmp_oids')->onDelete('cascade');
                $table->dateTime('hour_timestamp')->index();
                $table->decimal('avg_value', 16, 4)->default(0);
                $table->decimal('min_value', 16, 4)->default(0);
                $table->decimal('max_value', 16, 4)->default(0);
                $table->unsignedInteger('samples_count')->default(0);
                $table->timestamps();

                $table->unique(['snmp_device_id', 'snmp_oid_id', 'hour_timestamp'], 'snmp_metric_rollup_unique');
            });
        }

        if (!Schema::hasTable('snmp_interface_hourly_rollups')) {
            Schema::create('snmp_interface_hourly_rollups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('snmp_interface_id')->constrained('snmp_interfaces')->onDelete('cascade');
                $table->dateTime('hour_timestamp')->index();
                $table->decimal('avg_in_bps', 18, 2)->default(0);
                $table->decimal('max_in_bps', 18, 2)->default(0);
                $table->decimal('avg_out_bps', 18, 2)->default(0);
                $table->decimal('max_out_bps', 18, 2)->default(0);
                $table->decimal('avg_in_util_pct', 5, 2)->default(0);
                $table->decimal('max_in_util_pct', 5, 2)->default(0);
                $table->decimal('avg_out_util_pct', 5, 2)->default(0);
                $table->decimal('max_out_util_pct', 5, 2)->default(0);
                $table->unsignedInteger('samples_count')->default(0);
                $table->timestamps();

                $table->unique(['snmp_interface_id', 'hour_timestamp'], 'snmp_iface_rollup_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snmp_interface_hourly_rollups');
        Schema::dropIfExists('snmp_metric_hourly_rollups');
    }
};
