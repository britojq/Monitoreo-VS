<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\System\SonarPathService;
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
}
