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
        // 1. OIDs de Monitoreo (Catálogo Global)
        if (!Schema::hasTable('snmp_oids')) {
            Schema::create('snmp_oids', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('oid')->unique();
                $table->string('mib')->nullable();
                $table->string('vendor')->nullable()->comment('Cisco, pfSense, APC, standard');
                $table->enum('data_type', ['counter', 'gauge', 'string', 'timeticks', 'ipaddress', 'octets', 'integer']);
                $table->string('unit', 50)->nullable()->comment('%, ms, bytes, °C');
                $table->boolean('is_standard')->default(false);
                $table->boolean('is_counter_wrap')->default(false)->comment('True si es counter32 que se reinicia');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('vendor', 'idx_snmp_oids_vendor');
                $table->index('is_active', 'idx_snmp_oids_active');
            });
        }

        // 2. Dispositivos con Monitoreo SNMP
        if (!Schema::hasTable('snmp_devices')) {
            Schema::create('snmp_devices', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('ip_address', 45)->unique();
                $table->enum('snmp_version', ['v2c', 'v3'])->default('v2c');
                $table->text('snmp_community_encrypted')->nullable()->comment('Cifrado con Crypt::encryptString()');
                $table->string('snmp_v3_username')->nullable();
                $table->enum('snmp_v3_auth_protocol', ['MD5', 'SHA', 'SHA-256', 'SHA-512'])->nullable();
                $table->text('snmp_v3_auth_password_encrypted')->nullable();
                $table->enum('snmp_v3_priv_protocol', ['DES', 'AES', 'AES-256'])->nullable();
                $table->text('snmp_v3_priv_password_encrypted')->nullable();
                $table->unsignedInteger('snmp_port')->default(161);
                $table->unsignedInteger('snmp_timeout_seconds')->default(5);
                $table->unsignedInteger('snmp_retries')->default(2);
                $table->enum('device_type', ['router', 'switch', 'firewall', 'server', 'ups', 'ap', 'unknown'])->default('unknown');
                $table->string('vendor', 100)->nullable();
                $table->string('model')->nullable();
                $table->string('firmware_version')->nullable();
                $table->string('serial_number')->nullable();
                $table->string('sys_name')->nullable();
                $table->text('sys_description')->nullable();
                $table->string('sys_object_id')->nullable();
                $table->unsignedBigInteger('sys_uptime')->nullable();
                $table->string('sys_location')->nullable();
                $table->string('sys_contact')->nullable();

                // Relaciones opcionales
                $table->foreignId('site_id')->nullable()->constrained('monitored_sites')->nullOnDelete();
                $table->foreignId('discovered_device_id')->nullable()->constrained('discovered_devices')->nullOnDelete();
                $table->foreignId('network_device_id')->nullable()->constrained('monitored_network_devices')->nullOnDelete();

                $table->unsignedInteger('poll_interval_seconds')->default(60);
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_poll_at')->nullable();
                $table->enum('last_poll_status', ['success', 'timeout', 'auth_error', 'unknown_oid', 'error'])->nullable();
                $table->unsignedInteger('consecutive_failures')->default(0);

                // Soporte SSH/Telnet para activación remota
                $table->boolean('ssh_enabled')->default(false);
                $table->string('ssh_username')->nullable();
                $table->text('ssh_password_encrypted')->nullable();
                $table->unsignedInteger('ssh_port')->default(22);

                $table->json('custom_oids')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('is_active', 'idx_snmp_dev_active');
                $table->index('device_type', 'idx_snmp_dev_type');
                $table->index('last_poll_at', 'idx_snmp_dev_poll');
            });
        }

        // 3. Relación Dispositivo - OIDs Específicos & Umbrales
        if (!Schema::hasTable('snmp_device_oids')) {
            Schema::create('snmp_device_oids', function (Blueprint $table) {
                $table->id();
                $table->foreignId('snmp_device_id')->constrained('snmp_devices')->cascadeOnDelete();
                $table->foreignId('snmp_oid_id')->constrained('snmp_oids')->cascadeOnDelete();
                $table->string('custom_oid')->nullable()->comment('Override de OID base');
                $table->boolean('is_active')->default(true);
                $table->decimal('alert_threshold_warning', 15, 4)->nullable();
                $table->decimal('alert_threshold_critical', 15, 4)->nullable();
                $table->timestamps();

                $table->unique(['snmp_device_id', 'snmp_oid_id'], 'uniq_device_oid');
            });
        }

        // 4. Histórico de Métricas SNMP
        if (!Schema::hasTable('snmp_metrics_history')) {
            Schema::create('snmp_metrics_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('snmp_device_id')->constrained('snmp_devices')->cascadeOnDelete();
                $table->foreignId('snmp_oid_id')->constrained('snmp_oids')->cascadeOnDelete();
                $table->decimal('metric_value', 20, 6)->nullable();
                $table->text('metric_value_raw')->nullable()->comment('Strings, timeticks o valores no numéricos');
                $table->timestamp('collected_at')->useCurrent();

                $table->index(['snmp_device_id', 'collected_at'], 'idx_snmp_metric_dev_time');
                $table->index(['snmp_oid_id', 'collected_at'], 'idx_snmp_metric_oid_time');
            });
        }

        // 5. Interfaces de Red Descubiertas vía SNMP (ifTable)
        if (!Schema::hasTable('snmp_interfaces')) {
            Schema::create('snmp_interfaces', function (Blueprint $table) {
                $table->id();
                $table->foreignId('snmp_device_id')->constrained('snmp_devices')->cascadeOnDelete();
                $table->unsignedInteger('if_index');
                $table->string('if_name')->nullable();
                $table->string('if_description')->nullable();
                $table->string('if_alias')->nullable();
                $table->unsignedInteger('if_type')->nullable();
                $table->unsignedBigInteger('if_speed')->nullable();
                $table->unsignedBigInteger('if_high_speed')->nullable();
                $table->string('if_physical_address', 17)->nullable();
                $table->enum('if_admin_status', ['up', 'down', 'testing'])->nullable();
                $table->enum('if_oper_status', ['up', 'down', 'testing', 'unknown', 'dormant', 'notPresent', 'lowerLayerDown'])->nullable();
                $table->boolean('is_monitored')->default(false);
                $table->unsignedBigInteger('last_in_octets')->nullable();
                $table->unsignedBigInteger('last_out_octets')->nullable();
                $table->timestamp('last_polled_at')->nullable();
                $table->timestamps();

                $table->unique(['snmp_device_id', 'if_index'], 'uniq_device_if');
            });
        }

        // 6. Métricas por Interfaz (Delta bps, Paquetes, Descartes y Errores)
        if (!Schema::hasTable('snmp_interface_metrics')) {
            Schema::create('snmp_interface_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('snmp_interface_id')->constrained('snmp_interfaces')->cascadeOnDelete();
                $table->unsignedBigInteger('in_octets')->nullable();
                $table->unsignedBigInteger('out_octets')->nullable();
                $table->unsignedBigInteger('in_unicast_pkts')->nullable();
                $table->unsignedBigInteger('out_unicast_pkts')->nullable();
                $table->unsignedInteger('in_discards')->nullable();
                $table->unsignedInteger('out_discards')->nullable();
                $table->unsignedInteger('in_errors')->nullable();
                $table->unsignedInteger('out_errors')->nullable();
                $table->decimal('in_bps', 15, 4)->nullable()->comment('Calculado por delta de tiempo');
                $table->decimal('out_bps', 15, 4)->nullable();
                $table->decimal('in_utilization_pct', 5, 2)->nullable();
                $table->decimal('out_utilization_pct', 5, 2)->nullable();
                $table->timestamp('collected_at')->useCurrent();

                $table->index(['snmp_interface_id', 'collected_at'], 'idx_snmp_if_metric_time');
            });
        }

        // 7. Bitácora de Activación Remota SNMP (SSH / Telnet / API)
        if (!Schema::hasTable('snmp_activation_log')) {
            Schema::create('snmp_activation_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('snmp_device_id')->nullable()->constrained('snmp_devices')->nullOnDelete();
                $table->foreignId('discovered_device_id')->nullable()->constrained('discovered_devices')->nullOnDelete();
                $table->string('ip_address', 45);
                $table->enum('activation_method', ['ssh_cisco', 'telnet_cisco', 'api_pfsense', 'manual', 'auto']);
                $table->string('community_set')->nullable();
                $table->text('commands_executed')->nullable();
                $table->enum('status', ['success', 'failed', 'timeout', 'auth_error']);
                $table->text('response_output')->nullable();
                $table->text('error_message')->nullable();
                $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('executed_at')->useCurrent();

                $table->index('ip_address', 'idx_act_log_ip');
                $table->index('status', 'idx_act_log_status');
                $table->index('executed_at', 'idx_act_log_date');
            });
        }

        // 8. Traps SNMP Recibidos (Fase 2 / Fase 6)
        if (!Schema::hasTable('snmp_traps_received')) {
            Schema::create('snmp_traps_received', function (Blueprint $table) {
                $table->id();
                $table->foreignId('snmp_device_id')->nullable()->constrained('snmp_devices')->nullOnDelete();
                $table->string('source_ip', 45);
                $table->string('trap_oid');
                $table->string('trap_type', 100)->nullable()->comment('linkDown, linkUp, coldStart, etc.');
                $table->json('varbinds')->nullable();
                $table->enum('severity', ['info', 'warning', 'critical', 'emergency'])->default('info');
                $table->boolean('processed')->default(false);
                $table->unsignedBigInteger('alert_id')->nullable();
                $table->timestamp('received_at')->useCurrent();

                $table->index('source_ip', 'idx_trap_source');
                $table->index('trap_type', 'idx_trap_type');
                $table->index('received_at', 'idx_trap_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snmp_traps_received');
        Schema::dropIfExists('snmp_activation_log');
        Schema::dropIfExists('snmp_interface_metrics');
        Schema::dropIfExists('snmp_interfaces');
        Schema::dropIfExists('snmp_metrics_history');
        Schema::dropIfExists('snmp_device_oids');
        Schema::dropIfExists('snmp_devices');
        Schema::dropIfExists('snmp_oids');
    }
};
