<?php

use App\Http\Controllers\Api\ClusterApiController;
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
});
