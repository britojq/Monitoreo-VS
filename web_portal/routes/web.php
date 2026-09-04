<?php

use App\Http\Controllers\Admin\AdminBanController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminProxyController;
use App\Http\Controllers\Admin\AdminServiceController;
use App\Http\Controllers\Admin\AdminSiteController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\SyncController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicMonitoringController;
use Illuminate\Support\Facades\Route;

// --- VISTAS PÚBLICAS (Sin Autenticación) ---
Route::get('/', [PublicMonitoringController::class, 'index'])->name('home');
Route::get('/api/status', [PublicMonitoringController::class, 'apiStatus'])->name('api.status');

// --- PREVISUALIZACIÓN DE PANTALLA DE SEGURIDAD Y BANEO (Demostración) ---
Route::get('/preview/banned', function () {
    return response()->view('errors.403', [
        'exception' => new \Symfony\Component\HttpKernel\Exception\HttpException(
            403,
            'Acceso Denegado: Su cuenta de usuario y su dirección IP han sido suspendidas por intentar manipular configuraciones administrativas críticas sin autorización.'
        )
    ], 403);
})->name('preview.banned');

Route::get('/preview-403', function () {
    abort(403, 'Acceso Denegado: Su cuenta de usuario y su dirección IP han sido suspendidas por intentar manipular funciones administrativas no autorizadas.');
});

// --- AUTENTICACIÓN ---
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// --- PANEL ADMINISTRATIVO (Requiere Autenticación) ---
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

    // 1. RUTAS DE MONITOREO Y CONSULTA (Accesibles para Operadores y Administradores)
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('services', [AdminServiceController::class, 'index'])->name('services.index');
    Route::get('services/{service}/history', [AdminServiceController::class, 'history'])->name('services.history');

    Route::get('sites', [AdminSiteController::class, 'index'])->name('sites.index');
    Route::get('sites/{site}/history', [AdminSiteController::class, 'history'])->name('sites.history');

    Route::get('proxies', [AdminProxyController::class, 'index'])->name('proxies.index');
    Route::get('proxies/{proxy}/history', [AdminProxyController::class, 'history'])->name('proxies.history');

    // 2. RUTAS EXCLUSIVAS DE ADMINISTRACIÓN (Protegidas por Middleware 'admin')
    // Cualquier intento de un operador de acceder o invocar estas rutas provocará su BANEO INMEDIATO
    Route::middleware(['admin'])->group(function () {

        // Gestión de Usuarios (CRUD)
        Route::resource('users', AdminUserController::class)->except(['create', 'show', 'edit']);

        // Gestión y Desbaneo de Usuarios e IPs (CRUD)
        Route::get('bans', [AdminBanController::class, 'index'])->name('bans.index');
        Route::post('bans/unban/user/{user}', [AdminBanController::class, 'unbanUser'])->name('bans.unban.user');
        Route::post('bans/unban/ip/{id}', [AdminBanController::class, 'unbanIp'])->name('bans.unban.ip');
        Route::post('bans/unban/all/{userId}', [AdminBanController::class, 'unbanAll'])->name('bans.unban.all');
        Route::post('bans/ban-ip', [AdminBanController::class, 'banIp'])->name('bans.ban.ip');

        // Modificaciones de Servicios
        Route::post('services', [AdminServiceController::class, 'store'])->name('services.store');
        Route::put('services/{service}', [AdminServiceController::class, 'update'])->name('services.update');
        Route::delete('services/{service}', [AdminServiceController::class, 'destroy'])->name('services.destroy');
        Route::post('services/{service}/toggle', [AdminServiceController::class, 'toggle'])->name('services.toggle');

        // Modificaciones de Sedes
        Route::post('sites', [AdminSiteController::class, 'store'])->name('sites.store');
        Route::put('sites/{site}', [AdminSiteController::class, 'update'])->name('sites.update');
        Route::delete('sites/{site}', [AdminSiteController::class, 'destroy'])->name('sites.destroy');
        Route::post('sites/{site}/toggle', [AdminSiteController::class, 'toggle'])->name('sites.toggle');

        // Modificaciones de Proxies
        Route::post('proxies', [AdminProxyController::class, 'store'])->name('proxies.store');
        Route::put('proxies/{proxy}', [AdminProxyController::class, 'update'])->name('proxies.update');
        Route::delete('proxies/{proxy}', [AdminProxyController::class, 'destroy'])->name('proxies.destroy');
        Route::post('proxies/{proxy}/toggle', [AdminProxyController::class, 'toggle'])->name('proxies.toggle');

        // Disparadores Operativos Manuales
        Route::post('sync', [SyncController::class, 'triggerSyncManual'])->name('sync.manual');
        Route::post('scan-now', [SyncController::class, 'triggerScanNow'])->name('sync.scan');
    });
});
