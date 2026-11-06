<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * FASE 6: Telemetría Push en Tiempo Real (NetFlow + Syslog)
     */
    public function up(): void
    {
        // 1. Tabla netflow_records (agregada por ventana de 1 minuto)
        if (!Schema::hasTable('netflow_records')) {
            Schema::create('netflow_records', function (Blueprint $table) {
                $table->id();
                $table->string('exporter_ip', 45);
                $table->string('src_ip', 45);
                $table->string('dst_ip', 45);
                $table->unsignedInteger('src_port')->nullable();
                $table->unsignedInteger('dst_port')->nullable();
                $table->unsignedTinyInteger('protocol')->nullable()->comment('6=TCP, 17=UDP, 1=ICMP');
                $table->unsignedBigInteger('bytes')->default(0);
                $table->unsignedBigInteger('packets')->default(0);
                $table->enum('direction', ['ingress', 'egress'])->nullable();
                $table->timestamp('window_start')->nullable();
                $table->timestamp('window_end')->nullable();

                $table->index(['exporter_ip', 'window_start'], 'idx_netflow_exporter');
                $table->index(['src_ip', 'window_start'], 'idx_netflow_src');
                $table->index(['dst_ip', 'window_start'], 'idx_netflow_dst');
                $table->index(['dst_port', 'window_start'], 'idx_netflow_ports');
            });
        }

        // 2. Tabla syslog_events (RFC 3164 / RFC 5424)
        if (!Schema::hasTable('syslog_events')) {
            Schema::create('syslog_events', function (Blueprint $table) {
                $table->id();
                $table->string('source_ip', 45);
                $table->string('hostname', 255)->nullable();
                $table->unsignedInteger('facility')->nullable();
                $table->unsignedInteger('severity')->nullable()->comment('0=emergency ... 7=debug');
                $table->string('program', 255)->nullable();
                $table->text('message');
                $table->text('raw_message')->nullable();
                $table->timestamp('received_at')->useCurrent();

                $table->index(['source_ip', 'received_at'], 'idx_syslog_source');
                $table->index('severity', 'idx_syslog_severity');
                $table->index('program', 'idx_syslog_program');
                $table->index('received_at', 'idx_syslog_received');
            });
        }

        // 3. Tabla netflow_top_talkers (materializada cada 5 minutos)
        if (!Schema::hasTable('netflow_top_talkers')) {
            Schema::create('netflow_top_talkers', function (Blueprint $table) {
                $table->id();
                $table->timestamp('window_start')->nullable();
                $table->timestamp('window_end')->nullable();
                $table->enum('rank_type', ['src_ip', 'dst_ip', 'src_as', 'dst_as', 'protocol']);
                $table->string('rank_value', 255);
                $table->unsignedBigInteger('bytes')->default(0);
                $table->unsignedBigInteger('packets')->default(0);
                $table->decimal('percentage', 5, 2)->nullable();

                $table->index(['window_start', 'rank_type'], 'idx_top_talkers_window');
                $table->index('rank_value', 'idx_top_talkers_value');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('netflow_top_talkers');
        Schema::dropIfExists('syslog_events');
        Schema::dropIfExists('netflow_records');
    }
};
