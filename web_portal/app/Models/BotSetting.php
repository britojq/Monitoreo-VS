<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class BotSetting extends Model
{
    use HasFactory;

    protected $table = 'bot_settings';

    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_group',
        'title',
        'description',
        'type',
    ];

    /**
     * Cache estático en tiempo de ejecución para evitar consultas SQL redundantes
     * en el ciclo de vida de la misma petición.
     */
    protected static array $runtimeCache = [];

    /**
     * Obtiene el valor tipado de un parámetro por clave desde MariaDB (SSOT).
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$runtimeCache)) {
            return static::$runtimeCache[$key];
        }

        $setting = static::where('setting_key', $key)->first();
        if (!$setting) {
            return $default;
        }

        $value = match ($setting->type) {
            'boolean' => filter_var($setting->setting_value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->setting_value,
            'json' => json_decode($setting->setting_value ?? '', true) ?? $default,
            default => $setting->setting_value,
        };

        static::$runtimeCache[$key] = $value;
        return $value;
    }

    /**
     * Establece o actualiza un parámetro por clave en MariaDB como Única Fuente de Verdad.
     */
    public static function set(string $key, mixed $value, ?string $group = null, ?string $title = null, ?string $type = null): self
    {
        if ($type === null) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_array($value) => 'json',
                default => 'text',
            };
        }

        $valStr = is_bool($value)
            ? ($value ? '1' : '0')
            : (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $value);

        $payload = ['setting_value' => $valStr];
        if ($group !== null) {
            $payload['setting_group'] = $group;
        }
        if ($title !== null) {
            $payload['title'] = $title;
        }
        if ($type !== null) {
            $payload['type'] = $type;
        }

        $record = static::updateOrCreate(['setting_key' => $key], $payload);

        // Actualizar cache en tiempo de ejecución
        static::$runtimeCache[$key] = match ($type) {
            'boolean' => filter_var($valStr, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $valStr,
            'json' => is_array($value) ? $value : json_decode($valStr, true),
            default => $valStr,
        };

        return $record;
    }

    /**
     * Obtiene todos los parámetros pertenecientes a un grupo como arreglo asociativo clave => valor.
     */
    public static function getGroup(string $group): array
    {
        $rows = static::where('setting_group', $group)->get();
        $result = [];
        foreach ($rows as $row) {
            $val = match ($row->type) {
                'boolean' => filter_var($row->setting_value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $row->setting_value,
                'json' => json_decode($row->setting_value ?? '', true),
                default => $row->setting_value,
            };
            $result[$row->setting_key] = $val;
            static::$runtimeCache[$row->setting_key] = $val;
        }
        return $result;
    }

    /**
     * Limpia la memoria en tiempo de ejecución (esencial para pruebas automatizadas).
     */
    public static function clearRuntimeCache(): void
    {
        static::$runtimeCache = [];
    }

    /**
     * Exporta el estado consolidado de las configuraciones de MariaDB hacia config.json.
     * Esta exportación es estrictamente UNIDIRECCIONAL para permitir que scripts legados
     * o demonios en Python puedan leer parámetros sin que config.json sea la entrada autoritativa.
     * MariaDB es la ÚNICA FUENTE DE VERDAD (SSOT).
     */
    public static function syncToFile(?string $path = null): bool
    {
        $targetPath = $path ?: (file_exists('/scripts/telegram-admin-bot/config/config.json')
            ? '/scripts/telegram-admin-bot/config/config.json'
            : base_path('../config/config.json'));

        try {
            $currentData = [];
            if (file_exists($targetPath)) {
                $raw = @file_get_contents($targetPath);
                $currentData = json_decode($raw, true) ?: [];
            }

            // Poblar con las variables autoritativas de MariaDB
            $allSettings = static::all();
            foreach ($allSettings as $s) {
                $parsedVal = match ($s->type) {
                    'boolean' => filter_var($s->setting_value, FILTER_VALIDATE_BOOLEAN),
                    'integer' => (int) $s->setting_value,
                    'json' => json_decode($s->setting_value ?? '', true),
                    default => $s->setting_value,
                };

                match ($s->setting_key) {
                    'cluster_node_role' => $currentData['node_role'] = $parsedVal,
                    'cluster_token' => $currentData['cluster_token'] = $parsedVal,
                    'cluster_master_api_url' => $currentData['master_api_url'] = $parsedVal,
                    'cluster_last_sync_status' => $currentData['cluster_last_sync_status'] = $parsedVal,
                    'cluster_last_sync_at' => $currentData['cluster_last_sync_at'] = $parsedVal,
                    'cluster_slave_sync_interval_minutes' => $currentData['slave_sync_interval_minutes'] = $parsedVal,
                    'cluster_updated_at' => $currentData['cluster_updated_at'] = $parsedVal,
                    'ldap_config' => $currentData['ldap_config'] = $parsedVal,
                    'cron_reports_enabled' => $currentData['cron_reports_enabled'] = $parsedVal,
                    'cron_schedules' => $currentData['cron_schedules'] = $parsedVal,
                    'cron_reports_updated_at' => $currentData['cron_reports_updated_at'] = $parsedVal,
                    'cron_reports_updated_by' => $currentData['cron_reports_updated_by'] = $parsedVal,
                    'web_check_interval_minutes' => $currentData['web_check_interval_minutes'] = $parsedVal,
                    'web_check_interval_updated_at' => $currentData['web_check_interval_updated_at'] = $parsedVal,
                    'web_check_interval_updated_by' => $currentData['web_check_interval_updated_by'] = $parsedVal,
                    'ai_web_enabled' => $currentData['ai_web_enabled'] = $parsedVal,
                    'ai_web_updated_at' => $currentData['ai_web_updated_at'] = $parsedVal,
                    'ai_web_updated_by' => $currentData['ai_web_updated_by'] = $parsedVal,
                    'bot_token' => $currentData['bot_token'] = $parsedVal,
                    'owner_id' => $currentData['owner_id'] = $parsedVal,
                    'allowed_user_ids' => $currentData['allowed_user_ids'] = $parsedVal,
                    'allowed_group_ids' => $currentData['allowed_group_ids'] = $parsedVal,
                    'auto_update_enabled' => $currentData['auto_update_enabled'] = $parsedVal,
                    'auto_update_interval_hours' => $currentData['auto_update_interval_hours'] = $parsedVal,
                    'git_repo_url' => $currentData['git_repo_url'] = $parsedVal,
                    'git_branch' => $currentData['git_branch'] = $parsedVal,
                    'ups_notify_micro_fluctuations' => $currentData['ups_notify_micro_fluctuations'] = $parsedVal,
                    'ups_fluctuation_cooldown_seconds' => $currentData['ups_fluctuation_cooldown_seconds'] = $parsedVal,
                    'monitor_debug_mode' => $currentData['monitor_debug_mode'] = $parsedVal,
                    default => null,
                };
            }

            $json = json_encode($currentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return @file_put_contents($targetPath, $json . "\n") !== false;
        } catch (\Throwable $e) {
            Log::error('Error exportando configuraciones de MariaDB a config.json: ' . $e->getMessage());
            return false;
        }
    }
}
