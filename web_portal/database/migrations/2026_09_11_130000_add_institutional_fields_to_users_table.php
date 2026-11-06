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
        Schema::table('users', function (Blueprint $table) {
            $table->string('academic_title', 50)->nullable()->after('name')->comment('Título académico (ej: Ing., Lic., T.S.U.)');
            $table->string('cedula', 30)->nullable()->after('academic_title')->comment('Cédula de Identidad');
            $table->string('personal_number', 50)->nullable()->after('cedula')->comment('N° de Personal / Ficha');
            $table->string('phone', 50)->nullable()->after('personal_number')->comment('Teléfono de Contacto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['academic_title', 'cedula', 'personal_number', 'phone']);
        });
    }
};
