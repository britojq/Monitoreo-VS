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
        // Buscar proxy duplicado con letra 'D' o apuntando al mismo IP que Proxy A (10.20.0.89:8080) pero con id distinto de 1
        $proxyD = DB::table('monitored_proxies')
            ->where('letter', 'D')
            ->orWhere(function ($query) {
                $query->where('ip_port', '10.20.0.89:8080')
                      ->where('id', '>', 1);
            })
            ->first();

        if ($proxyD) {
            // Eliminar historiales asociados al proxy duplicado
            DB::table('proxy_check_histories')
                ->where('monitored_proxy_id', $proxyD->id)
                ->delete();

            // Eliminar registro del proxy duplicado
            DB::table('monitored_proxies')
                ->where('id', $proxyD->id)
                ->delete();
        }

        // Sincronizar archivo de configuración bot.conf
        try {
            if (class_exists(\App\Http\Controllers\Admin\SyncController::class)) {
                app(\App\Http\Controllers\Admin\SyncController::class)->exportToConfigFiles();
            }
        } catch (\Throwable $e) {
            // Silencioso en caso de entorno CLI restringido
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No recrear duplicado por diseño de saneamiento de red
    }
};
