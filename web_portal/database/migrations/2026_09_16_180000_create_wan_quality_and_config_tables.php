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
        // 1. Modificación de site_check_histories: métricas avanzadas de calidad WAN
        Schema::table('site_check_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('site_check_histories', 'packet_loss_pct')) {
                $table->decimal('packet_loss_pct', 5, 2)->nullable()->after('latency_ms')->comment('Porcentaje de pérdida de paquetes');
            }
            if (!Schema::hasColumn('site_check_histories', 'jitter_ms')) {
                $table->decimal('jitter_ms', 10, 4)->nullable()->after('packet_loss_pct')->comment('Jitter / desviación de latencia');
            }
            if (!Schema::hasColumn('site_check_histories', 'min_rtt_ms')) {
                $table->decimal('min_rtt_ms', 10, 4)->nullable()->after('jitter_ms')->comment('RTT mínimo');
            }
            if (!Schema::hasColumn('site_check_histories', 'max_rtt_ms')) {
                $table->decimal('max_rtt_ms', 10, 4)->nullable()->after('min_rtt_ms')->comment('RTT máximo');
            }
            if (!Schema::hasColumn('site_check_histories', 'mdev_ms')) {
                $table->decimal('mdev_ms', 10, 4)->nullable()->after('max_rtt_ms')->comment('Mean deviation');
            }
        });

        // 2. Tabla: device_configurations (Historial de respaldos de configuraciones)
        if (!Schema::hasTable('device_configurations')) {
            Schema::create('device_configurations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('network_device_id')->nullable()->index();
                $table->unsignedBigInteger('snmp_device_id')->nullable()->index();
                $table->string('device_name', 255)->nullable();
                $table->string('device_ip', 45)->nullable();
                $table->enum('device_type', ['cisco_router', 'cisco_switch', 'pfsense', 'linux_server', 'other'])->default('other');
                $table->longText('config_text');
                $table->string('config_hash', 64)->index()->comment('SHA-256 de la configuración normalizada');
                $table->unsignedInteger('config_size_bytes')->nullable();
                $table->timestamp('captured_at')->useCurrent()->index();
                $table->enum('captured_by', ['cron', 'manual'])->default('cron');
                $table->enum('status', ['success', 'failed', 'partial'])->default('success');
                $table->text('error_message')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('network_device_id')
                    ->references('id')
                    ->on('monitored_network_devices')
                    ->onDelete('set null');

                $table->foreign('snmp_device_id')
                    ->references('id')
                    ->on('snmp_devices')
                    ->onDelete('set null');
            });
        }

        // 3. Tabla: config_change_logs (Detección de diferencias y auditoría de cambios)
        if (!Schema::hasTable('config_change_logs')) {
            Schema::create('config_change_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('device_configuration_id')->index();
                $table->unsignedBigInteger('previous_config_id')->nullable()->index();
                $table->unsignedBigInteger('network_device_id')->nullable()->index();
                $table->unsignedBigInteger('snmp_device_id')->nullable()->index();
                $table->enum('change_type', ['initial', 'modified', 'reverted'])->default('modified');
                $table->text('diff_summary')->nullable();
                $table->longText('diff_unified')->nullable()->comment('Diff unificado estilo git');
                $table->unsignedInteger('lines_added')->default(0);
                $table->unsignedInteger('lines_removed')->default(0);
                $table->timestamp('detected_at')->useCurrent()->index();
                $table->boolean('alerted')->default(false);
                $table->timestamps();

                $table->foreign('device_configuration_id')
                    ->references('id')
                    ->on('device_configurations')
                    ->onDelete('cascade');

                $table->foreign('previous_config_id')
                    ->references('id')
                    ->on('device_configurations')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_change_logs');
        Schema::dropIfExists('device_configurations');

        Schema::table('site_check_histories', function (Blueprint $table) {
            $table->dropColumn(['packet_loss_pct', 'jitter_ms', 'min_rtt_ms', 'max_rtt_ms', 'mdev_ms']);
        });
    }
};
