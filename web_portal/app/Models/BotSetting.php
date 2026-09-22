<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * Obtiene el valor de un parámetro por clave con fallback.
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('setting_key', $key)->first();
        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->setting_value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->setting_value,
            'json' => json_decode($setting->setting_value, true),
            default => $setting->setting_value,
        };
    }

    /**
     * Establece o actualiza un parámetro por clave.
     */
    public static function set(string $key, $value, ?string $group = null, ?string $title = null, ?string $type = null): self
    {
        $valStr = is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : (string) $value);

        return static::updateOrCreate(
            ['setting_key' => $key],
            array_filter([
                'setting_value' => $valStr,
                'setting_group' => $group,
                'title' => $title,
                'type' => $type,
            ], fn($v) => !is_null($v))
        );
    }
}
