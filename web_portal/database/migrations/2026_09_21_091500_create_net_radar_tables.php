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
        // 1. Tabla de Hosts y Equipos Monitoreados por NET Radar
        Schema::create('net_radar_hosts', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('mac', 17)->nullable()->index();
            $table->string('hostname')->nullable();
            $table->string('vendor')->nullable();
            $table->string('os_detected', 50)->default('Desconocido');
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0)->index();
            $table->unsignedBigInteger('packet_count')->default(0);
            $table->boolean('is_local')->default(true);
            $table->string('update_status', 50)->default('none'); // none, checking, downloading
            $table->string('last_update_type', 50)->nullable();  // windows_update, linux_repo
            $table->string('last_update_target')->nullable();    // delivery.mp.microsoft.com, deb.debian.org
            $table->unsignedBigInteger('update_bytes')->default(0);
            $table->timestamp('last_update_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });

        // 2. Tabla de Eventos y Detecciones de Tráfico (Windows Update, Repositorios, etc.)
        Schema::create('net_radar_events', function (Blueprint $table) {
            $table->id();
            $table->string('host_ip', 45)->index();
            $table->string('event_type', 50)->index(); // windows_update, linux_repo, high_bandwidth, suspicious_traffic, new_host
            $table->string('severity', 20)->default('info'); // info, warning, critical
            $table->string('target_domain')->nullable();
            $table->unsignedBigInteger('bytes_transferred')->default(0);
            $table->text('description');
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // 3. Tabla de Snapshots Periódicos de Tráfico y Métricas Consolidadas
        Schema::create('net_radar_snapshots', function (Blueprint $table) {
            $table->id();
            $table->integer('total_hosts')->default(0);
            $table->integer('active_hosts')->default(0);
            $table->integer('windows_updating_hosts')->default(0);
            $table->integer('linux_updating_hosts')->default(0);
            $table->unsignedBigInteger('total_bytes_in')->default(0);
            $table->unsignedBigInteger('total_bytes_out')->default(0);
            $table->json('top_protocols')->nullable();
            $table->json('top_talkers')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('net_radar_snapshots');
        Schema::dropIfExists('net_radar_events');
        Schema::dropIfExists('net_radar_hosts');
    }
};
