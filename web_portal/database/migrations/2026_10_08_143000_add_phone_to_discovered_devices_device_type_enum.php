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
        DB::statement("ALTER TABLE discovered_devices MODIFY COLUMN device_type ENUM('router','switch','firewall','server','workstation','printer','ap','camera','ups','phone','unknown') NOT NULL DEFAULT 'unknown'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE discovered_devices SET device_type = 'unknown' WHERE device_type = 'phone'");
        DB::statement("ALTER TABLE discovered_devices MODIFY COLUMN device_type ENUM('router','switch','firewall','server','workstation','printer','ap','camera','ups','unknown') NOT NULL DEFAULT 'unknown'");
    }
};
