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
        if (!Schema::hasTable('network_device_check_histories')) {
            Schema::create('network_device_check_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('monitored_network_device_id')->constrained('monitored_network_devices')->onDelete('cascade');
                $table->boolean('is_up')->default(true);
                $table->decimal('latency_ms', 8, 2)->default(0.00);
                $table->string('status_message')->nullable();
                $table->timestamp('checked_at')->useCurrent();
                $table->timestamps();

                $table->index('monitored_network_device_id', 'ndch_device_id_idx');
                $table->index('checked_at', 'ndch_checked_at_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_device_check_histories');
    }
};
