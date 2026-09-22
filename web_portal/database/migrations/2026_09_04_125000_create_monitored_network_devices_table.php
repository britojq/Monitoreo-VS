<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('monitored_network_devices')) {
            Schema::create('monitored_network_devices', function (Blueprint $table) {
                $table->id();
                $table->integer('device_number');
                $table->string('name', 255);
                $table->string('ip', 255)->unique();
                $table->string('mac', 50)->nullable();
                $table->string('vendor_data', 255)->nullable();
                $table->string('normal_state_msg', 255)->nullable();
                $table->string('error_state_msg', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_network_devices');
    }
};
