<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ups_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('UPS ZTG LV6KL - Sede Valle Seco');
            $table->string('model')->default('ZTG LV6KL 6kVA');
            $table->string('serial_number')->nullable();
            $table->string('serial_port')->default('/dev/ttyS0');
            $table->integer('baud_rate')->default(2400);
            
            // Valores nominales de placa
            $table->decimal('rating_voltage', 6, 1)->default(208.0);
            $table->decimal('rating_current', 6, 1)->default(28.0);
            $table->decimal('rating_battery_voltage', 6, 1)->default(192.0);
            $table->decimal('rating_frequency', 5, 1)->default(60.0);
            $table->string('firmware_version')->default('R1.01.55');

            // Telemetría en tiempo real
            $table->decimal('input_voltage', 6, 1)->nullable();
            $table->decimal('input_fault_voltage', 6, 1)->nullable();
            $table->decimal('output_voltage', 6, 1)->nullable();
            $table->integer('load_percent')->nullable();
            $table->decimal('frequency', 5, 1)->nullable();
            $table->decimal('battery_voltage', 6, 2)->nullable();
            $table->integer('battery_percent')->nullable();
            $table->decimal('temperature_c', 5, 1)->nullable();

            // Banderas de estado operativo
            $table->boolean('is_online')->default(true);
            $table->boolean('is_on_battery')->default(false);
            $table->boolean('is_battery_low')->default(false);
            $table->boolean('is_bypass')->default(false);
            $table->boolean('is_ups_failed')->default(false);
            $table->boolean('beeper_on')->default(true);

            // Alertas Telegram reactivas
            $table->boolean('telegram_alert_enabled')->default(true);
            $table->string('telegram_alert_target', 20)->default('owner'); // 'owner' o 'group'
            $table->string('last_alert_state', 20)->nullable(); // 'NORMAL', 'ON_BATTERY', 'BATTERY_LOW'
            $table->dateTime('outage_since')->nullable();
            $table->dateTime('last_seen_at')->nullable();

            $table->timestamps();
        });

        Schema::create('ups_telemetry_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ups_device_id')->constrained('ups_devices')->cascadeOnDelete();
            $table->decimal('input_voltage', 6, 1);
            $table->decimal('output_voltage', 6, 1);
            $table->integer('load_percent');
            $table->integer('battery_percent');
            $table->decimal('battery_voltage', 6, 2);
            $table->decimal('temperature_c', 5, 1);
            $table->boolean('is_on_battery')->default(false);
            $table->boolean('is_battery_low')->default(false);
            $table->boolean('is_bypass')->default(false);
            $table->timestamp('recorded_at')->useCurrent()->index();
        });

        // Insertar registro inicial del UPS ZTG LV6KL
        DB::table('ups_devices')->insert([
            'name' => 'UPS ZTG LV6KL - Sede Valle Seco',
            'model' => 'ZTG LV6KL 6kVA',
            'serial_number' => 'LV6KL-VS-01',
            'serial_port' => '/dev/ttyS0',
            'baud_rate' => 2400,
            'rating_voltage' => 208.0,
            'rating_current' => 28.0,
            'rating_battery_voltage' => 192.0,
            'rating_frequency' => 60.0,
            'firmware_version' => 'R1.01.55',
            'input_voltage' => 224.8,
            'input_fault_voltage' => 0.0,
            'output_voltage' => 207.8,
            'load_percent' => 8,
            'frequency' => 60.0,
            'battery_voltage' => 2.25,
            'battery_percent' => 100,
            'temperature_c' => 43.0,
            'is_online' => true,
            'is_on_battery' => false,
            'is_battery_low' => false,
            'is_bypass' => false,
            'is_ups_failed' => false,
            'beeper_on' => true,
            'telegram_alert_enabled' => true,
            'telegram_alert_target' => 'owner',
            'last_alert_state' => 'NORMAL',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ups_telemetry_histories');
        Schema::dropIfExists('ups_devices');
    }
};
