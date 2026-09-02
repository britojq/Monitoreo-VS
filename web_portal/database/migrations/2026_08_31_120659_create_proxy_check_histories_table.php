<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxy_check_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitored_proxy_id')->constrained('monitored_proxies')->onDelete('cascade');
            $table->boolean('is_up')->default(true)->index();
            $table->decimal('latency_ms', 8, 2)->default(0.00);
            $table->string('http_code', 10)->nullable();
            $table->string('status_message', 255)->nullable();
            $table->timestamp('checked_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['monitored_proxy_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_check_histories');
    }
};
