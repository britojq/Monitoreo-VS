<?php

use App\Http\Controllers\Admin\AdminAdvancedSettingsController;
use App\Http\Controllers\Admin\AdminAlertController;
use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\AdminBanController;
use App\Http\Controllers\Admin\AdminBotCommandController;
use App\Http\Controllers\Admin\AdminBotTemplateController;
use App\Http\Controllers\Admin\AdminClusterController;
use App\Http\Controllers\Admin\AdminConfigController;
use App\Http\Controllers\Admin\AdminCronController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDiscoveryController;
use App\Http\Controllers\Admin\AdminLdapController;
use App\Http\Controllers\Admin\AdminNetRadarController;
use App\Http\Controllers\Admin\AdminNetworkDeviceController;
use App\Http\Controllers\Admin\AdminProxyController;
use App\Http\Controllers\Admin\AdminServiceController;
use App\Http\Controllers\Admin\AdminSiteController;
use App\Http\Controllers\Admin\AdminSnmpController;
use App\Http\Controllers\Admin\AdminSslController;
use App\Http\Controllers\Admin\AdminTelegramDispatchController;
use App\Http\Controllers\Admin\AdminTermsController;
use App\Http\Controllers\Admin\AdminTrapController;
use App\Http\Controllers\Admin\AdminSyslogController;
use App\Http\Controllers\Admin\AdminNetflowController;
use App\Http\Controllers\Admin\AdminTopologyController;
use App\Http\Controllers\Admin\AdminWolController;
use App\Http\Controllers\Admin\AdminPredictiveController;
use App\Http\Controllers\Admin\AdminLifecycleController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\HelpController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SshSessionController;
use App\Http\Controllers\Admin\SyncController;
use App\Http\Controllers\Admin\TelnetSessionController;
use App\Http\Controllers\Admin\VncSessionController;
use App\Http\Controllers\AiChatController;
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
Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

// --- ASISTENTE IA (Estrictamente Protegido por Autenticación) ---
Route::middleware(['auth'])->group(function () {
    Route::post('/ai/chat', [AiChatController::class, 'chat'])->name('ai.chat');
});

// --- PANEL ADMINISTRATIVO (Requiere Autenticación) ---
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

    // 1. RUTAS DE MONITOREO Y CONSULTA (Accesibles para Operadores y Administradores)
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard', [AdminDashboardController::class, 'index']);

    Route::get('services', [AdminServiceController::class, 'index'])->name('services.index');
    Route::get('services/{service}/history', [AdminServiceController::class, 'history'])->name('services.history');

    Route::get('sites', [AdminSiteController::class, 'index'])->name('sites.index');
    Route::get('sites/{site}/history', [AdminSiteController::class, 'history'])->name('sites.history');

    Route::get('devices', [AdminNetworkDeviceController::class, 'index'])->name('devices.index');
    Route::get('devices/{device}/history', [AdminNetworkDeviceController::class, 'history'])->name('devices.history');

    Route::get('proxies', [AdminProxyController::class, 'index'])->name('proxies.index');
    Route::get('proxies/{proxy}/history', [AdminProxyController::class, 'history'])->name('proxies.history');

    // Perfil de Usuario (Accesible para Administradores y Operadores)
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('profile', [ProfileController::class, 'update'])->name('profile.update');

    // Manual de Ayuda para Usuarios y Operadores
    Route::get('ayuda', [HelpController::class, 'index'])->name('help.index');

    // Envíos Manuales de Reportes Oficiales a Telegram (Accesible para Operadores y Administradores)
    Route::post('telegram/dispatch', [AdminTelegramDispatchController::class, 'dispatch'])->name('telegram.dispatch');
    Route::post('telegram/dispatch/preview', [AdminTelegramDispatchController::class, 'preview'])->name('telegram.dispatch.preview');
    Route::get('telegram/dispatch/status', [AdminTelegramDispatchController::class, 'getLatestDispatch'])->name('telegram.dispatch.status');

    // Control Remoto VNC (Accesible para Operadores y Administradores)
    Route::post('vnc/session', [VncSessionController::class, 'createSession'])->name('vnc.session');
    Route::get('vnc/viewer', [VncSessionController::class, 'viewer'])->name('vnc.viewer');

    // Control Remoto Telnet (Accesible para Operadores y Administradores)
    Route::post('telnet/session', [TelnetSessionController::class, 'createSession'])->name('telnet.session');
    Route::get('telnet/terminal', [TelnetSessionController::class, 'terminal'])->name('telnet.terminal');

    // Control Remoto SSH (Accesible para Operadores y Administradores)
    Route::post('ssh/session', [SshSessionController::class, 'createSession'])->name('ssh.session');
    Route::get('ssh/terminal', [SshSessionController::class, 'terminal'])->name('ssh.terminal');

    // Aceptación de Términos de Uso y Seguridad (Accesible para todos los usuarios autenticados)
    Route::post('terms/accept', [AdminTermsController::class, 'accept'])->name('terms.accept');

    // Auto-Discovery de Red y Detección Anti-Rogue (Consulta para Operador y Administrador)
    Route::get('discovery', [AdminDiscoveryController::class, 'index'])->name('discovery.index');
    Route::get('discovery/history/{id}', [AdminDiscoveryController::class, 'history'])->name('discovery.history');

    // Monitoreo y Telemetría SNMP (Consulta para Operador y Administrador)
    Route::get('snmp', [AdminSnmpController::class, 'index'])->name('snmp.index');
    Route::get('snmp/{id}', [AdminSnmpController::class, 'show'])->name('snmp.show');
    Route::get('snmp/{id}/interfaces', [AdminSnmpController::class, 'interfaces'])->name('snmp.interfaces');
    Route::get('snmp/interfaces/{id}/metrics', [AdminSnmpController::class, 'interfaceMetrics'])->name('snmp.interface.metrics');

    // Monitoreo de Certificados SSL/TLS (Consulta para Operador y Administrador)
    Route::get('ssl', [AdminSslController::class, 'index'])->name('ssl.index');
    Route::get('ssl/{id}', [AdminSslController::class, 'show'])->name('ssl.show');

    // Sistema de Alertas, Escalación y Correlación (Consulta y Gestión para Operador y Administrador)
    Route::get('alerts', [AdminAlertController::class, 'index'])->name('alerts.index');
    Route::get('alerts/{id}', [AdminAlertController::class, 'show'])->name('alerts.show');
    Route::post('alerts/{id}/ack', [AdminAlertController::class, 'acknowledge'])->name('alerts.ack');
    Route::post('alerts/{id}/silence', [AdminAlertController::class, 'silence'])->name('alerts.silence');

    // Respaldos y Auditoría de Configuraciones de Red (Consulta para Operador y Administrador)
    Route::get('configs', [AdminConfigController::class, 'index'])->name('configs.index');
    Route::get('configs/{id}', [AdminConfigController::class, 'show'])->name('configs.show');
    Route::get('configs/{id}/diff', [AdminConfigController::class, 'diff'])->name('configs.diff');

    // NET Radar & Monitoreo de Tráfico / Actualizaciones (Consulta y Exportación para Operador y Administrador)
    Route::get('netradar', [AdminNetRadarController::class, 'index'])->name('netradar.index')->middleware('permission:netradar.view');
    Route::get('netradar/data', [AdminNetRadarController::class, 'data'])->name('netradar.data')->middleware('permission:netradar.view');
    Route::get('netradar/events', [AdminNetRadarController::class, 'events'])->name('netradar.events')->middleware('permission:netradar.view');
    Route::get('netradar/export-report', [AdminNetRadarController::class, 'exportReport'])->name('netradar.exportReport')->middleware('permission:netradar.export');
    Route::post('netradar/scan', [AdminNetRadarController::class, 'scanNow'])->name('netradar.scan')->middleware('permission:netradar.scan');

    // Telemetría Push en Tiempo Real (Fase 6: Traps, Syslog, NetFlow)
    Route::get('traps', [AdminTrapController::class, 'index'])->name('traps.index');
    Route::get('traps/{id}', [AdminTrapController::class, 'show'])->name('traps.show');

    Route::get('syslog', [AdminSyslogController::class, 'index'])->name('syslog.index');
    Route::get('syslog/live', [AdminSyslogController::class, 'live'])->name('syslog.live');

    Route::get('netflow', [AdminNetflowController::class, 'index'])->name('netflow.index');
    Route::get('netflow/top-talkers', [AdminNetflowController::class, 'topTalkers'])->name('netflow.top');

    // Topología Visual, Wake-on-LAN, IA Predictiva y Ciclo de Vida (Fase 7)
    Route::get('topology', [AdminTopologyController::class, 'index'])->name('topology.index');
    Route::get('topology/data', [AdminTopologyController::class, 'data'])->name('topology.data');

    Route::get('wol', [AdminWolController::class, 'index'])->name('wol.index');

    Route::get('predictive', [AdminPredictiveController::class, 'index'])->name('predictive.index');

    Route::get('lifecycle', [AdminLifecycleController::class, 'index'])->name('lifecycle.index');


    // 2. RUTAS EXCLUSIVAS DE ADMINISTRACIÓN (Protegidas por Middleware 'admin')
    // Cualquier intento de un operador de acceder o invocar estas rutas provocará su BANEO INMEDIATO
    Route::middleware(['admin'])->group(function () {

        // Procesamiento de SNMP Traps (Exclusivo Administrador)
        Route::post('traps/{id}/process', [AdminTrapController::class, 'markProcessed'])->name('traps.process');

        // Topología, WoL (Disparo) e IA Predictiva (Acciones Operativas - Exclusivo Administrador)
        Route::post('topology/rebuild', [AdminTopologyController::class, 'rebuild'])->name('topology.rebuild');
        Route::post('wol/{id}/wake', [AdminWolController::class, 'wake'])->name('wol.wake');

        Route::post('predictive/run', [AdminPredictiveController::class, 'runAnalysis'])->name('predictive.run');
        Route::post('predictive/{id}/ack', [AdminPredictiveController::class, 'acknowledge'])->name('predictive.acknowledge');
        Route::delete('predictive/{id}', [AdminPredictiveController::class, 'destroy'])->name('predictive.destroy');

        // Gestión y Auditoría de Términos de Uso (Exclusivo Administrador)
        Route::get('terms', [AdminTermsController::class, 'index'])->name('terms.index');
        Route::post('terms/{user}/reset', [AdminTermsController::class, 'reset'])->name('terms.reset');
        Route::post('terms/reset-all', [AdminTermsController::class, 'resetAll'])->name('terms.resetAll');

        // Búsqueda y Autorización Manual de Usuarios LDAP
        Route::get('users/ldap/search', [AdminUserController::class, 'searchLdapUsers'])->name('users.ldap.search');
        Route::post('users/ldap/authorize', [AdminUserController::class, 'authorizeLdapUser'])->name('users.ldap.authorize');

        // Gestión de Usuarios (CRUD)
        Route::resource('users', AdminUserController::class)->except(['create', 'show', 'edit']);
        Route::get('users/{user}/permissions', [AdminUserController::class, 'getPermissions'])->name('users.permissions.get');
        Route::post('users/{user}/permissions', [AdminUserController::class, 'updatePermissions'])->name('users.permissions.update');

        // Gestión y Desbaneo de Usuarios e IPs (CRUD)
        Route::get('bans', [AdminBanController::class, 'index'])->name('bans.index');
        Route::post('bans/unban/user/{user}', [AdminBanController::class, 'unbanUser'])->name('bans.unban.user');
        Route::post('bans/unban/ip/{id}', [AdminBanController::class, 'unbanIp'])->name('bans.unban.ip');
        Route::post('bans/unban/all/{userId}', [AdminBanController::class, 'unbanAll'])->name('bans.unban.all');
        Route::post('bans/ban-ip', [AdminBanController::class, 'banIp'])->name('bans.ban.ip');
        Route::post('bans/fail2ban/unban', [AdminBanController::class, 'unbanFail2banIp'])->name('bans.fail2ban.unban');
        Route::post('bans/fail2ban/ban', [AdminBanController::class, 'banFail2banIp'])->name('bans.fail2ban.ban');
        Route::post('bans/fail2ban/reload', [AdminBanController::class, 'reloadFail2ban'])->name('bans.fail2ban.reload');

        // Registro y Pista de Auditoría del Sistema (Exclusivo Administrador)
        Route::get('audit', [AdminAuditController::class, 'index'])->name('audit.index');
        Route::get('audit/export', [AdminAuditController::class, 'export'])->name('audit.export');
        Route::get('audit/{audit}', [AdminAuditController::class, 'show'])->name('audit.show');

        // Plantillas de Mensajería y Reportes del Bot (Consulta y Preview)
        Route::get('bot/templates', [AdminBotTemplateController::class, 'index'])->name('bot.templates.index');
        Route::post('bot/templates/preview', [AdminBotTemplateController::class, 'preview'])->name('bot.templates.preview');

        // Comandos y Configuración del Bot (Consulta)
        Route::get('bot/commands', [AdminBotCommandController::class, 'index'])->name('bot.commands.index');

        // Auto-Discovery de Red (Escaneo Operativo en Segundo Plano)
        Route::post('discovery/scan', [AdminDiscoveryController::class, 'scanNow'])->name('discovery.scan');

        // Sondeos SNMP y Descubrimiento de Interfaces (Operativo)
        Route::post('snmp/{id}/poll', [AdminSnmpController::class, 'triggerPoll'])->name('snmp.poll');
        Route::post('snmp/{id}/discover-interfaces', [AdminSnmpController::class, 'triggerInterfaceDiscovery'])->name('snmp.discover-interfaces');

        // Re-inspección Operativa de Certificados SSL/TLS
        Route::post('ssl/recheck-all', [AdminSslController::class, 'recheckAll'])->name('ssl.recheck_all');
        Route::post('ssl/{id}/recheck', [AdminSslController::class, 'recheck'])->name('ssl.recheck');

        // Evaluación y Resolución de Alertas (Operativo)
        Route::post('alerts/evaluate-now', [AdminAlertController::class, 'evaluateNow'])->name('alerts.evaluate_now');
        Route::post('alerts/{id}/resolve', [AdminAlertController::class, 'resolve'])->name('alerts.resolve');

        // Configuración Avanzada del Sistema & Telemetría (Exclusivo Administrador)
        Route::get('settings/advanced', [AdminAdvancedSettingsController::class, 'index'])->name('settings.advanced');

        // Operaciones Mutantes de Infraestructura (Exclusivas del Servidor MASTER)
        Route::middleware(['node.master'])->group(function () {
            // Modificaciones de Servicios
            Route::post('services', [AdminServiceController::class, 'store'])->name('services.store');
            Route::put('services/{service}', [AdminServiceController::class, 'update'])->name('services.update');
            Route::delete('services/{service}', [AdminServiceController::class, 'destroy'])->name('services.destroy');
            Route::post('services/{service}/toggle', [AdminServiceController::class, 'toggle'])->name('services.toggle');

            // Modificaciones de Sedes y Equipos
            Route::post('sites', [AdminSiteController::class, 'store'])->name('sites.store');
            Route::put('sites/{site}', [AdminSiteController::class, 'update'])->name('sites.update');
            Route::delete('sites/{site}', [AdminSiteController::class, 'destroy'])->name('sites.destroy');
            Route::post('sites/{site}/toggle', [AdminSiteController::class, 'toggle'])->name('sites.toggle');
            Route::post('sites/{site}/devices', [AdminSiteController::class, 'addDevice'])->name('sites.devices.store');
            Route::delete('sites/devices/{device}', [AdminSiteController::class, 'deleteDevice'])->name('sites.devices.destroy');

            // Modificaciones de Dispositivos de Red
            Route::post('devices', [AdminNetworkDeviceController::class, 'store'])->name('devices.store');
            Route::put('devices/{device}', [AdminNetworkDeviceController::class, 'update'])->name('devices.update');
            Route::delete('devices/{device}', [AdminNetworkDeviceController::class, 'destroy'])->name('devices.destroy');
            Route::post('devices/{device}/toggle', [AdminNetworkDeviceController::class, 'toggle'])->name('devices.toggle');

            // Modificaciones de Proxies
            Route::post('proxies', [AdminProxyController::class, 'store'])->name('proxies.store');
            Route::put('proxies/{proxy}', [AdminProxyController::class, 'update'])->name('proxies.update');
            Route::delete('proxies/{proxy}', [AdminProxyController::class, 'destroy'])->name('proxies.destroy');
            Route::post('proxies/{proxy}/toggle', [AdminProxyController::class, 'toggle'])->name('proxies.toggle');

            // Sincronización a .conf y frecuencia de monitoreo
            Route::post('sync', [SyncController::class, 'triggerSyncManual'])->name('sync.manual');
            Route::post('cron/interval/update', [AdminCronController::class, 'updateWebCheckInterval'])->name('cron.interval.update');

            // Modificaciones de Ciclo de Vida y Garantías (Hardware Lifecycle)
            Route::post('lifecycle', [AdminLifecycleController::class, 'store'])->name('lifecycle.store');
            Route::delete('lifecycle/{id}', [AdminLifecycleController::class, 'destroy'])->name('lifecycle.destroy');

            // Modificaciones de Wake-on-LAN (Registro y Eliminación)
            Route::post('wol', [AdminWolController::class, 'store'])->name('wol.store');
            Route::delete('wol/{id}', [AdminWolController::class, 'destroy'])->name('wol.destroy');

            // Modificaciones de Plantillas y Configuración del Bot
            Route::put('bot/templates/{template}', [AdminBotTemplateController::class, 'update'])->name('bot.templates.update');
            Route::post('bot/templates/{template}/reset', [AdminBotTemplateController::class, 'reset'])->name('bot.templates.reset');
            Route::put('bot/commands/{command}', [AdminBotCommandController::class, 'update'])->name('bot.commands.update');
            Route::post('bot/commands/{command}/toggle', [AdminBotCommandController::class, 'toggle'])->name('bot.commands.toggle');
            Route::post('bot/settings', [AdminBotCommandController::class, 'updateSettings'])->name('bot.settings.update');

            // Auto-Discovery: Clasificación, Intrusos y Subredes
            Route::post('discovery/authorize/{id}', [AdminDiscoveryController::class, 'authorizeDevice'])->name('discovery.authorize');
            Route::post('discovery/rogue/{id}', [AdminDiscoveryController::class, 'markRogue'])->name('discovery.rogue');
            Route::post('discovery/update/{id}', [AdminDiscoveryController::class, 'update'])->name('discovery.update');
            Route::post('discovery/subnet', [AdminDiscoveryController::class, 'storeSubnet'])->name('discovery.subnet.store');

            // Dispositivos y Configuración SNMP
            Route::post('snmp', [AdminSnmpController::class, 'store'])->name('snmp.store');
            Route::put('snmp/{id}', [AdminSnmpController::class, 'update'])->name('snmp.update');
            Route::delete('snmp/{id}', [AdminSnmpController::class, 'destroy'])->name('snmp.destroy');
            Route::post('snmp/{id}/toggle-interface', [AdminSnmpController::class, 'toggleInterfaceMonitoring'])->name('snmp.toggle-interface');
            Route::post('snmp/activate', [AdminSnmpController::class, 'activateRemote'])->name('snmp.activate');

            // Certificados SSL/TLS
            Route::post('ssl', [AdminSslController::class, 'store'])->name('ssl.store');
            Route::delete('ssl/{id}', [AdminSslController::class, 'destroy'])->name('ssl.destroy');

            // Reglas de Alertas, Mantenimientos y Correlación
            Route::post('alerts/rules', [AdminAlertController::class, 'storeRule'])->name('alerts.rules.store');
            Route::delete('alerts/rules/{id}', [AdminAlertController::class, 'destroyRule'])->name('alerts.rules.destroy');
            Route::post('alerts/maintenance', [AdminAlertController::class, 'storeMaintenance'])->name('alerts.maintenance.store');
            Route::delete('alerts/maintenance/{id}', [AdminAlertController::class, 'destroyMaintenance'])->name('alerts.maintenance.destroy');
            Route::post('alerts/correlation', [AdminAlertController::class, 'storeCorrelation'])->name('alerts.correlation.store');
            Route::delete('alerts/correlation/{id}', [AdminAlertController::class, 'destroyCorrelation'])->name('alerts.correlation.destroy');

            // Respaldos de Configuraciones WAN
            Route::post('configs/backup-all', [AdminConfigController::class, 'backupAll'])->name('configs.backup_all');
            Route::post('configs/backup-device', [AdminConfigController::class, 'backupDevice'])->name('configs.backup_device');
            Route::delete('configs/{id}', [AdminConfigController::class, 'destroy'])->name('configs.destroy');
        });

        // Disparador de Escaneo / Sincronización Manual (Disponible en Master y Slave)
        Route::post('scan-now', [SyncController::class, 'triggerScanNow'])->name('sync.scan');

        // Control de Envíos Programados por Cron (Telegram)
        Route::post('cron/toggle', [AdminCronController::class, 'toggle'])->name('cron.toggle');
        Route::post('cron/schedules/add', [AdminCronController::class, 'addSchedule'])->name('cron.schedules.add');
        Route::post('cron/schedules/remove', [AdminCronController::class, 'removeSchedule'])->name('cron.schedules.remove');

        // Configuración de Rol de Nodo & Clúster (Master / Slave)
        Route::post('cluster/update', [AdminClusterController::class, 'update'])->name('cluster.update');
        Route::post('cluster/test', [AdminClusterController::class, 'testConnection'])->name('cluster.test');
        Route::post('cluster/generate-token', [AdminClusterController::class, 'generateToken'])->name('cluster.generate-token');

        // Configuración de Directorio Activo & Autenticación LDAP
        Route::post('ldap/update', [AdminLdapController::class, 'update'])->name('ldap.update');
        Route::post('ldap/test-connection', [AdminLdapController::class, 'testConnection'])->name('ldap.testConnection');
    });
});
