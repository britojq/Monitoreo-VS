<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Sanea la base de datos eliminando valores de relleno ficticios ('1x GE' en estaciones de trabajo
     * y seriales sintéticos 'SN-NET-%' en equipos de usuario), preservando estrictamente la infraestructura real.
     */
    public function up(): void
    {
        // 1. Limpiar puertos falsos ('1x GE') en computadoras de escritorio y laptops (solo switches/routers tienen puertos de red)
        DB::table('inventory_assets')
            ->whereIn('type', ['desktop', 'laptop'])
            ->where('ports', '1x GE')
            ->update(['ports' => null]);

        // 2. Limpiar números de serie sintéticos ('SN-NET-%') generados automáticamente en puestos de trabajo
        // Los equipos sin serial de fabricante verificado deben tener NULL en cumplimiento de la Regla de Oro #6.
        DB::table('inventory_assets')
            ->whereIn('type', ['desktop', 'laptop'])
            ->where('serial_number', 'like', 'SN-NET-%')
            ->update(['serial_number' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Los datos ficticios eliminados no deben restaurarse
    }
};
