<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('global_status')->default('OPERACIONAL');
            $table->integer('services_online')->default(0);
            $table->integer('services_total')->default(0);
            $table->integer('sites_online')->default(0);
            $table->integer('sites_total')->default(0);
            $table->integer('proxies_online')->default(0);
            $table->integer('proxies_total')->default(0);
            $table->json('payload_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_snapshots');
    }
};
