<?php

use App\Http\Controllers\Api\ClusterApiController;
use App\Http\Controllers\Api\GlpiReceiverController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas API de Clúster (Master / Slave)
|--------------------------------------------------------------------------
| Ocultas al público general. Requieren cabecera 'X-Cluster-Token' válida;
| de lo contrario retornan 404 Not Found.
|
*/

Route::middleware(['cluster.token'])->prefix('cluster')->group(function () {
    Route::match(['get', 'post'], 'ping', [ClusterApiController::class, 'ping'])->name('api.cluster.ping');
    Route::match(['get', 'post'], 'telemetry', [ClusterApiController::class, 'telemetry'])->name('api.cluster.telemetry');
    Route::match(['get', 'post'], 'diagnostics', [ClusterApiController::class, 'diagnostics'])->name('api.cluster.diagnostics');
    Route::match(['get', 'post'], 'verify', [ClusterApiController::class, 'verify'])->name('api.cluster.verify');
    Route::match(['get', 'post'], 'audit-trail', [ClusterApiController::class, 'auditTrail'])->name('api.cluster.audit-trail');
});

/*
|--------------------------------------------------------------------------
| Rutas API de Ingestión de Inventario (ITAM / GLPI Agent)
|--------------------------------------------------------------------------
| Receptor unificado para agentes GLPI (Linux y Windows) y autodescubrimiento.
|
*/
Route::match(['get', 'post'], 'v1/inventory/receiver/{token?}', [GlpiReceiverController::class, 'handle'])->name('api.inventory.receiver');
