<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Phase 7: Topology, Wake-on-LAN, Predictive AI, and Lifecycle.
     */
    public function up(): void
    {
        // 1. Enlaces de Topología de Red (LLDP / CDP / FDB)
        if (!Schema::hasTable('network_topology_links')) {
            Schema::create('network_topology_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_device_id')->comment('snmp_device_id emisor');
                $table->unsignedBigInteger('source_interface_id')->nullable();
                $table->unsignedBigInteger('target_device_id')->nullable()->comment('snmp_device_id destino');
                $table->string('target_mac', 17)->nullable();
                $table->string('target_hostname', 255)->nullable();
                $table->enum('link_type', ['lldp', 'cdp', 'manual', 'fdb_inferred'])->default('manual');
                $table->enum('link_status', ['up', 'down', 'unknown'])->default('unknown');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->index('source_device_id');
                $table->index('target_device_id');
            });
        }

        // 2. Dispositivos Wake-on-LAN (WoL)
        if (!Schema::hasTable('wol_devices')) {
            Schema::create('wol_devices', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('mac_address', 17)->unique();
                $table->string('ip_address', 45)->nullable();
                $table->string('broadcast_address', 45)->nullable()->default('255.255.255.255');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->unsignedBigInteger('discovered_device_id')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->timestamp('last_woken_at')->nullable();
                $table->timestamps();

                $table->index('site_id');
                $table->index('discovered_device_id');
            });
        }

        // 3. Anomalías y Tendencias Predictivas (IA)
        if (!Schema::hasTable('predictive_anomalies')) {
            Schema::create('predictive_anomalies', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 50);
                $table->unsignedBigInteger('entity_id');
                $table->enum('anomaly_type', ['trend_upward', 'seasonal_pattern', 'correlation', 'outlier'])->default('outlier');
                $table->string('metric_name', 100)->nullable();
                $table->decimal('confidence', 5, 2)->nullable()->comment('0.00 a 100.00 %');
                $table->text('description')->nullable();
                $table->string('predicted_impact', 255)->nullable();
                $table->timestamp('detected_at')->useCurrent();
                $table->boolean('acknowledged')->default(false);

                $table->index(['entity_type', 'entity_id']);
                $table->index('detected_at');
            });
        }

        // 4. Inventario y Ciclo de Vida de Hardware
        if (!Schema::hasTable('hardware_lifecycle')) {
            Schema::create('hardware_lifecycle', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 50);
                $table->unsignedBigInteger('entity_id');
                $table->string('serial_number', 255)->nullable();
                $table->date('purchase_date')->nullable();
                $table->date('warranty_end_date')->nullable();
                $table->date('eol_date')->nullable()->comment('End of Life fabricante');
                $table->date('eos_date')->nullable()->comment('End of Support');
                $table->date('battery_last_replaced')->nullable()->comment('Para UPS');
                $table->enum('disk_health_status', ['ok', 'warning', 'failing', 'unknown'])->default('unknown');
                $table->string('firmware_version', 255)->nullable();
                $table->string('firmware_latest', 255)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['entity_type', 'entity_id']);
                $table->index('eol_date');
                $table->index('warranty_end_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hardware_lifecycle');
        Schema::dropIfExists('predictive_anomalies');
        Schema::dropIfExists('wol_devices');
        Schema::dropIfExists('network_topology_links');
    }
};
