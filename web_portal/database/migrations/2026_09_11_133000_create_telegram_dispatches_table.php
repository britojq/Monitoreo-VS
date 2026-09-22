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
        Schema::create('telegram_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_type', 50)->comment('servicios, sedes, completo');
            $table->string('operator_name')->comment('Nombre del operador firmante');
            $table->string('operator_ci', 30)->nullable()->comment('Cédula del operador');
            $table->string('operator_personal_number', 50)->nullable()->comment('N° de personal del operador');
            $table->string('operator_phone', 50)->nullable()->comment('Teléfono del operador');
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->text('response_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['report_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_dispatches');
    }
};
