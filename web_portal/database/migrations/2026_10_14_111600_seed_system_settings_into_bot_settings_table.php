<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\BotSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Carga y asegura la persistencia de todas las configuraciones del sistema
     * en MariaDB (bot_settings) como Única Fuente de Verdad (SSOT).
     */
    public function up(): void
    {
        $jsonPath = file_exists('/scripts/telegram-admin-bot/config/config.json')
            ? '/scripts/telegram-admin-bot/config/config.json'
            : base_path('../config/config.json');

        $data = [];
        if (file_exists($jsonPath)) {
            $raw = @file_get_contents($jsonPath);
            $data = json_decode($raw, true) ?: [];
        }

        $settings = [
            // Cluster
            [
                'setting_key' => 'cluster_node_role',
                'setting_value' => (string) ($data['node_role'] ?? 'master'),
                'setting_group' => 'cluster',
                'title' => 'Rol del Nodo en Cluster',
                'description' => 'Determina si el nodo opera como master (activo/origen) o slave (esclavo/réplica).',
                'type' => 'text',
            ],
            [
                'setting_key' => 'cluster_token',
                'setting_value' => (string) ($data['cluster_token'] ?? ''),
                'setting_group' => 'cluster',
                'title' => 'Token de Autenticación de Cluster',
                'description' => 'Token criptográfico para handshake seguro entre Master y Esclavos.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'cluster_master_api_url',
                'setting_value' => (string) ($data['master_api_url'] ?? 'http://192.168.1.250'),
                'setting_group' => 'cluster',
                'title' => 'URL API del Nodo Maestro',
                'description' => 'Dirección base del servidor de producción / master para sincronización.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'cluster_last_sync_status',
                'setting_value' => (string) ($data['cluster_last_sync_status'] ?? 'master_active'),
                'setting_group' => 'cluster',
                'title' => 'Estado de Sincronización del Cluster',
                'description' => 'Último estado reportado en la sincronización del cluster.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'cluster_last_sync_at',
                'setting_value' => $data['cluster_last_sync_at'] ?? null,
                'setting_group' => 'cluster',
                'title' => 'Última Sincronización de Cluster',
                'description' => 'Marca temporal de la última sincronización ejecutada.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'cluster_slave_sync_interval_minutes',
                'setting_value' => (string) ($data['slave_sync_interval_minutes'] ?? 2),
                'setting_group' => 'cluster',
                'title' => 'Intervalo de Sincronización de Esclavo (minutos)',
                'description' => 'Frecuencia en minutos con la que el nodo esclavo consulta al maestro.',
                'type' => 'integer',
            ],
            [
                'setting_key' => 'cluster_updated_at',
                'setting_value' => $data['cluster_updated_at'] ?? date('Y-m-d H:i:s'),
                'setting_group' => 'cluster',
                'title' => 'Fecha de Actualización del Cluster',
                'description' => 'Marca temporal del último cambio en la configuración de cluster.',
                'type' => 'text',
            ],

            // LDAP
            [
                'setting_key' => 'ldap_config',
                'setting_value' => json_encode($data['ldap_config'] ?? [
                    'enabled' => true,
                    'host' => '192.168.0.22',
                    'port' => 389,
                    'base_dn' => base64_decode('ZGM9Y29ycG9lbGVjLGRjPWdvYixkYz12ZQ=='),
                    'allowed_areas' => 'ATIT, GPO TRAB INFRA TECNOL CARABOBO, INFRAESTRUCTURA, TELECOMUNICACIONES',
                    'default_role' => 'operator',
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => 'Sistema',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'setting_group' => 'ldap',
                'title' => 'Configuración de Conexión y Autenticación LDAP',
                'description' => 'Parámetros del Directorio Activo institucional (host, puerto, base DN, áreas y rol).',
                'type' => 'json',
            ],

            // Cron y Reportes Programados
            [
                'setting_key' => 'cron_reports_enabled',
                'setting_value' => isset($data['cron_reports_enabled']) ? ($data['cron_reports_enabled'] ? '1' : '0') : '0',
                'setting_group' => 'cron',
                'title' => 'Envíos Automáticos de Reportes Programados',
                'description' => 'Activa o pausa el despacho automático de boletines de estado por Telegram.',
                'type' => 'boolean',
            ],
            [
                'setting_key' => 'cron_schedules',
                'setting_value' => json_encode($data['cron_schedules'] ?? ['07:30', '16:00']),
                'setting_group' => 'cron',
                'title' => 'Horarios de Envío de Reportes',
                'description' => 'Lista de horas programadas para el despacho de boletines de monitoreo.',
                'type' => 'json',
            ],
            [
                'setting_key' => 'cron_reports_updated_at',
                'setting_value' => $data['cron_reports_updated_at'] ?? null,
                'setting_group' => 'cron',
                'title' => 'Última Actualización de Cron',
                'description' => 'Marca temporal del último ajuste de programación cron.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'cron_reports_updated_by',
                'setting_value' => $data['cron_reports_updated_by'] ?? 'Sistema',
                'setting_group' => 'cron',
                'title' => 'Usuario que Actualizó Cron',
                'description' => 'Nombre del usuario que realizó la última modificación de horarios.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'web_check_interval_minutes',
                'setting_value' => (string) ($data['web_check_interval_minutes'] ?? 60),
                'setting_group' => 'cron',
                'title' => 'Intervalo de Sondeo Web (minutos)',
                'description' => 'Frecuencia de sondeo para refresco de métricas en el portal.',
                'type' => 'integer',
            ],
            [
                'setting_key' => 'web_check_interval_updated_at',
                'setting_value' => $data['web_check_interval_updated_at'] ?? null,
                'setting_group' => 'cron',
                'title' => 'Última Actualización de Intervalo Web',
                'description' => 'Fecha de modificación del intervalo de monitoreo web.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'web_check_interval_updated_by',
                'setting_value' => $data['web_check_interval_updated_by'] ?? 'Sistema',
                'setting_group' => 'cron',
                'title' => 'Usuario que Actualizó Intervalo Web',
                'description' => 'Usuario que configuró el intervalo web.',
                'type' => 'text',
            ],

            // Asistente Corporativo de IA en Portal Web
            [
                'setting_key' => 'ai_web_enabled',
                'setting_value' => isset($data['ai_web_enabled']) ? ($data['ai_web_enabled'] ? '1' : '0') : '0',
                'setting_group' => 'ai',
                'title' => 'Asistente Corporativo de IA en Portal Web',
                'description' => 'Habilita o deshabilita la interfaz del asistente virtual en la web.',
                'type' => 'boolean',
            ],
            [
                'setting_key' => 'ai_web_updated_at',
                'setting_value' => $data['ai_web_updated_at'] ?? null,
                'setting_group' => 'ai',
                'title' => 'Fecha de Actualización de IA Web',
                'description' => 'Marca temporal del último cambio de estado del asistente corporativo.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'ai_web_updated_by',
                'setting_value' => $data['ai_web_updated_by'] ?? 'Sistema',
                'setting_group' => 'ai',
                'title' => 'Usuario que Actualizó IA Web',
                'description' => 'Usuario que activó o pausó el asistente corporativo.',
                'type' => 'text',
            ],

            // Parámetros Generales de Bot de Telegram
            [
                'setting_key' => 'bot_token',
                'setting_value' => (string) ($data['bot_token'] ?? ''),
                'setting_group' => 'bot',
                'title' => 'Token API de Telegram',
                'description' => 'Token del bot corporativo para comunicación con la API de Telegram.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'owner_id',
                'setting_value' => (string) ($data['owner_id'] ?? 38914901),
                'setting_group' => 'bot',
                'title' => 'Telegram ID del Administrador / Creador',
                'description' => 'Identificador numérico exclusivo del Administrador para alertas de seguridad.',
                'type' => 'integer',
            ],
            [
                'setting_key' => 'allowed_user_ids',
                'setting_value' => json_encode($data['allowed_user_ids'] ?? [38914901]),
                'setting_group' => 'bot',
                'title' => 'IDs de Usuarios Telegram Autorizados',
                'description' => 'Lista de identificadores de usuarios autorizados a ejecutar comandos del bot.',
                'type' => 'json',
            ],
            [
                'setting_key' => 'allowed_group_ids',
                'setting_value' => json_encode($data['allowed_group_ids'] ?? [-1001383163558]),
                'setting_group' => 'bot',
                'title' => 'IDs de Grupos de Telegram Autorizados',
                'description' => 'Lista de identificadores de grupos autorizados para recibir boletines NOC.',
                'type' => 'json',
            ],
            [
                'setting_key' => 'auto_update_enabled',
                'setting_value' => isset($data['auto_update_enabled']) ? ($data['auto_update_enabled'] ? '1' : '0') : '1',
                'setting_group' => 'bot',
                'title' => 'Actualización Automática de Código',
                'description' => 'Indica si el sistema verifica y aplica actualizaciones periódicas.',
                'type' => 'boolean',
            ],
            [
                'setting_key' => 'auto_update_interval_hours',
                'setting_value' => (string) ($data['auto_update_interval_hours'] ?? 48),
                'setting_group' => 'bot',
                'title' => 'Intervalo de Actualización Automática (horas)',
                'description' => 'Frecuencia en horas para comprobar actualizaciones en el repositorio.',
                'type' => 'integer',
            ],
            [
                'setting_key' => 'git_repo_url',
                'setting_value' => (string) ($data['git_repo_url'] ?? ''),
                'setting_group' => 'bot',
                'title' => 'URL del Repositorio Git',
                'description' => 'Repositorio institucional de despliegue.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'git_branch',
                'setting_value' => (string) ($data['git_branch'] ?? 'master'),
                'setting_group' => 'bot',
                'title' => 'Rama Git Activa',
                'description' => 'Rama del repositorio utilizada para seguimiento de código.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'ups_notify_micro_fluctuations',
                'setting_value' => isset($data['ups_notify_micro_fluctuations']) ? ($data['ups_notify_micro_fluctuations'] ? '1' : '0') : '0',
                'setting_group' => 'ups',
                'title' => 'Notificar Microfluctuaciones de UPS',
                'description' => 'Emite alertas por fluctuaciones menores de voltaje en sistemas UPS.',
                'type' => 'boolean',
            ],
            [
                'setting_key' => 'ups_fluctuation_cooldown_seconds',
                'setting_value' => (string) ($data['ups_fluctuation_cooldown_seconds'] ?? 1800),
                'setting_group' => 'ups',
                'title' => 'Tiempo de Enfriamiento entre Alertas de UPS (segundos)',
                'description' => 'Tiempo mínimo entre alertas sucesivas de eventos eléctricos en UPS.',
                'type' => 'integer',
            ],
            [
                'setting_key' => 'monitor_debug_mode',
                'setting_value' => isset($data['monitor_debug_mode']) ? ($data['monitor_debug_mode'] ? '1' : '0') : '0',
                'setting_group' => 'bot',
                'title' => 'Modo Depuración del Monitor',
                'description' => 'Habilita telemetría detallada en los reportes de diagnóstico.',
                'type' => 'boolean',
            ],
        ];

        foreach ($settings as $setting) {
            $now = date('Y-m-d H:i:s');
            $existing = DB::table('bot_settings')->where('setting_key', $setting['setting_key'])->first();
            if ($existing) {
                // Preservar estrictamente el valor existente en producción, actualizando solo metadatos
                DB::table('bot_settings')->where('setting_key', $setting['setting_key'])->update([
                    'setting_group' => $setting['setting_group'],
                    'title' => $setting['title'],
                    'description' => $setting['description'],
                    'type' => $setting['type'],
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('bot_settings')->insert([
                    'setting_key' => $setting['setting_key'],
                    'setting_value' => $setting['setting_value'],
                    'setting_group' => $setting['setting_group'],
                    'title' => $setting['title'],
                    'description' => $setting['description'],
                    'type' => $setting['type'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No destruir configuraciones del sistema en rollback
    }
};
