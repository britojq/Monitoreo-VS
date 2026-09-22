<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_site_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitored_site_id')->constrained('monitored_sites')->onDelete('cascade');
            $table->integer('device_number');
            $table->string('name');
            $table->string('ip');
            $table->string('normal_state_msg')->nullable();
            $table->string('error_state_msg')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['monitored_site_id', 'device_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_site_devices');
    }
};
