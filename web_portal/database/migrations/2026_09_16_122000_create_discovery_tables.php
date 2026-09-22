<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Network Discovery & Anti-Rogue.
     */
    public function up(): void
    {
        // 1. Catálogo OUI de Fabricantes
        if (!Schema::hasTable('oui_vendors')) {
            Schema::create('oui_vendors', function (Blueprint $table) {
                $table->id();
                $table->string('oui_prefix', 8)->unique()->comment('Prefijo OUI en mayúsculas, ej: 00:1A:2B');
                $table->string('vendor_name', 255);
                $table->string('country', 3)->nullable();
                $table->timestamps();
                $table->index('oui_prefix');
            });
        }

        // 2. Subredes para Auto-Discovery
        if (!Schema::hasTable('discovery_subnets')) {
            Schema::create('discovery_subnets', function (Blueprint $table) {
                $table->id();
                $table->string('subnet', 50)->unique()->comment('Subred en formato CIDR, ej: 10.20.23.0/24');
                $table->foreignId('site_id')->nullable()->constrained('monitored_sites')->nullOnDelete();
                $table->enum('scan_method', ['arp_sweep', 'nmap', 'both'])->default('arp_sweep');
                $table->unsignedInteger('scan_interval_minutes')->default(15);
                $table->time('scan_window_start')->nullable();
                $table->time('scan_window_end')->nullable();
                $table->unsignedInteger('rate_limit_pps')->default(100);
                $table->string('nmap_options', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_scan_at')->nullable();
                $table->timestamps();

                $table->index('is_active');
                $table->index('site_id');
            });
        }

        // 3. Auditoría de Escaneos Realizados
        if (!Schema::hasTable('discovery_scans')) {
            Schema::create('discovery_scans', function (Blueprint $table) {
                $table->id();
                $table->enum('scan_type', ['arp_sweep', 'nmap_scan', 'full_scan', 'passive_snmp'])->default('arp_sweep');
                $table->string('subnet', 50);
                $table->foreignId('site_id')->nullable()->constrained('monitored_sites')->nullOnDelete();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('finished_at')->nullable();
                $table->decimal('duration_seconds', 8, 2)->nullable();
                $table->unsignedInteger('devices_found')->default(0);
                $table->unsignedInteger('new_devices')->default(0);
                $table->enum('status', ['running', 'completed', 'failed', 'timeout', 'rate_limited'])->default('running');
                $table->text('error_message')->nullable();
                $table->enum('executed_by', ['cron', 'manual', 'api'])->default('cron');
                $table->timestamps();

                $table->index('scan_type');
                $table->index('subnet');
                $table->index('started_at');
                $table->index('status');
            });
        }

        // 4. Dispositivos Descubiertos en Red
        if (!Schema::hasTable('discovered_devices')) {
            Schema::create('discovered_devices', function (Blueprint $table) {
                $table->id();
                $table->string('mac_address', 17)->unique()->comment('Dirección MAC en formato XX:XX:XX:XX:XX:XX');
                $table->string('ip_address', 45);
                $table->string('hostname', 255)->nullable();
                $table->string('vendor', 255)->nullable();
                $table->string('oui_prefix', 8)->nullable();
                $table->enum('device_type', [
                    'router', 'switch', 'firewall', 'server', 'workstation',
                    'printer', 'ap', 'camera', 'ups', 'unknown'
                ])->default('unknown');
                $table->string('os_detected', 255)->nullable();
                $table->json('open_ports')->nullable();
                $table->foreignId('site_id')->nullable()->constrained('monitored_sites')->nullOnDelete();
                $table->enum('classification_status', [
                    'pendiente', 'clasificado', 'ignorado', 'rogue', 'byod'
                ])->default('pendiente');
                $table->foreignId('classified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('classified_at')->nullable();
                $table->foreignId('linked_network_device_id')->nullable()->constrained('monitored_network_devices')->nullOnDelete();
                $table->timestamp('first_seen')->useCurrent();
                $table->timestamp('last_seen')->useCurrent();
                $table->unsignedInteger('seen_count')->default(1);
                $table->boolean('is_active')->default(true);
                $table->enum('discovery_method', [
                    'arp_sweep', 'nmap_scan', 'snmp_walk', 'manual', 'passive'
                ])->default('arp_sweep');
                $table->boolean('is_authorized')->default(false)->comment('True si fue aprobado como dispositivo legítimo');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('ip_address');
                $table->index('classification_status');
                $table->index('last_seen');
                $table->index('is_active');
                $table->index('is_authorized');
            });
        }

        // 5. Historial de Cambios en Dispositivos Descubiertos
        if (!Schema::hasTable('discovered_device_history')) {
            Schema::create('discovered_device_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('discovered_device_id')->constrained('discovered_devices')->cascadeOnDelete();
                $table->enum('event_type', [
                    'first_seen', 'ip_changed', 'hostname_changed',
                    'went_offline', 'came_online', 'classified', 'marked_rogue', 'approved'
                ]);
                $table->text('previous_value')->nullable();
                $table->text('new_value')->nullable();
                $table->timestamp('occurred_at')->useCurrent();

                $table->index('discovered_device_id');
                $table->index('event_type');
                $table->index('occurred_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discovered_device_history');
        Schema::dropIfExists('discovered_devices');
        Schema::dropIfExists('discovery_scans');
        Schema::dropIfExists('discovery_subnets');
        Schema::dropIfExists('oui_vendors');
    }
};
