<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabla normalizada de interfaces de red por activo (Multi-NIC)
        Schema::create('inventory_asset_network_interfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_asset_id')
                ->constrained('inventory_assets')
                ->cascadeOnDelete();
            $table->string('interface_name', 64)->default('eth0')->comment('Nombre lógico: eth0, eno1, wlan0, etc.');
            $table->string('mac_address', 48)->nullable()->index('idx_ian_mac');
            $table->string('ip_address', 45)->nullable()->index('idx_ian_ip');
            $table->string('subnet_mask', 45)->nullable();
            $table->string('gateway', 45)->nullable();
            $table->string('dns', 128)->nullable();
            $table->string('interface_type', 32)->default('ethernet')->comment('ethernet, wifi, virtual, loopback, bridge, vlan');
            $table->string('speed', 32)->nullable()->comment('Ej: 1000 Mbps, 10 Gbps');
            $table->string('status', 32)->default('up')->comment('up, down');
            $table->boolean('is_primary')->default(false)->index('idx_ian_is_primary');
            $table->timestamps();

            // Índices compuestos con nombres cortos (< 64 caracteres)
            $table->index(['inventory_asset_id', 'is_primary'], 'idx_ian_asset_primary');
            $table->index(['inventory_asset_id', 'mac_address'], 'idx_ian_asset_mac');
            $table->index(['inventory_asset_id', 'ip_address'], 'idx_ian_asset_ip');
        });

        // 2. Poblar retroactivamente la interfaz primaria de activos existentes
        $assets = DB::table('inventory_assets')
            ->select('id', 'mac_address', 'ip_address', 'created_at')
            ->whereNotNull('mac_address')
            ->orWhereNotNull('ip_address')
            ->get();

        $rows = [];
        $now = now();
        foreach ($assets as $asset) {
            $cleanMac = trim((string)$asset->mac_address);
            $cleanIp = trim((string)$asset->ip_address);
            if ($cleanMac === '' && $cleanIp === '') {
                continue;
            }

            $rows[] = [
                'inventory_asset_id' => $asset->id,
                'interface_name' => 'eth0',
                'mac_address' => $cleanMac ?: null,
                'ip_address' => ($cleanIp !== '' && $cleanIp !== '127.0.0.1') ? $cleanIp : null,
                'subnet_mask' => null,
                'gateway' => null,
                'dns' => null,
                'interface_type' => 'ethernet',
                'speed' => null,
                'status' => 'up',
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('inventory_asset_network_interfaces')->insert($chunk);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_asset_network_interfaces');
    }
};
