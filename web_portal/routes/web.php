<?php

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

// --- AUTENTICACIÓN ---
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// --- PANEL ADMINISTRATIVO (Requiere Autenticación) ---
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Gestión de Usuarios (CRUD)
    Route::resource('users', AdminUserController::class)->except(['create', 'show', 'edit']);

    // Gestión de Servicios (CRUD & Telemetría)
    Route::resource('services', AdminServiceController::class)->except(['create', 'show', 'edit']);
    Route::post('services/{service}/toggle', [AdminServiceController::class, 'toggle'])->name('services.toggle');
    Route::get('services/{service}/history', [AdminServiceController::class, 'history'])->name('services.history');

    // Gestión de Sedes y Enlaces (CRUD & Telemetría)
    Route::resource('sites', AdminSiteController::class)->except(['create', 'show', 'edit']);
    Route::post('sites/{site}/toggle', [AdminSiteController::class, 'toggle'])->name('sites.toggle');
    Route::get('sites/{site}/history', [AdminSiteController::class, 'history'])->name('sites.history');

    // Gestión de Proxies (CRUD & Telemetría)
    Route::resource('proxies', AdminProxyController::class)->except(['create', 'show', 'edit']);
    Route::post('proxies/{proxy}/toggle', [AdminProxyController::class, 'toggle'])->name('proxies.toggle');
    Route::get('proxies/{proxy}/history', [AdminProxyController::class, 'history'])->name('proxies.history');

    // Sincronización y Escaneo Manual
    Route::post('sync', [SyncController::class, 'triggerSyncManual'])->name('sync.manual');
    Route::post('scan-now', [SyncController::class, 'triggerScanNow'])->name('sync.scan');
});
