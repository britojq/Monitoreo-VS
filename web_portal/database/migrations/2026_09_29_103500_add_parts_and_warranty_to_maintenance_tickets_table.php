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
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->json('parts_replaced_json')->nullable()->after('resolution_notes')->comment('Listado de partes o repuestos instalados/sustituidos');
            $table->boolean('warranty_applied')->default(false)->after('parts_replaced_json')->comment('Indica si fue cubierto por garantía');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropColumn(['parts_replaced_json', 'warranty_applied']);
        });
    }
};
