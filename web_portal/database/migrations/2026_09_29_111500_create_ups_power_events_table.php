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
        // 1. Agregar bandera group_alert_sent a ups_devices si no existe
        if (Schema::hasTable('ups_devices') && !Schema::hasColumn('ups_devices', 'group_alert_sent')) {
            Schema::table('ups_devices', function (Blueprint $table) {
                $table->boolean('group_alert_sent')->default(false)->after('last_alert_state');
            });
        }

        // 2. Crear tabla ups_power_events para registro de micro-fluctuaciones y cortes
        if (!Schema::hasTable('ups_power_events')) {
            Schema::create('ups_power_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ups_device_id')->default(1)->index();
                $table->string('event_type', 32)->default('fluctuation')->index(); // 'fluctuation' (<=45s) | 'outage' (>45s)
                $table->timestamp('started_at')->useCurrent()->index();
                $table->timestamp('ended_at')->nullable();
                $table->integer('duration_seconds')->default(0);
                $table->decimal('input_voltage', 6, 2)->nullable();
                $table->string('status', 32)->default('resolved')->index(); // 'ongoing' | 'resolved'
                $table->boolean('notified_group')->default(false);
                $table->string('notified_target', 32)->default('owner'); // 'owner' | 'group'
                $table->text('description')->nullable();
                $table->timestamps();

                $table->foreign('ups_device_id')
                    ->references('id')
                    ->on('ups_devices')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ups_power_events');

        if (Schema::hasTable('ups_devices') && Schema::hasColumn('ups_devices', 'group_alert_sent')) {
            Schema::table('ups_devices', function (Blueprint $table) {
                $table->dropColumn('group_alert_sent');
            });
        }
    }
};
