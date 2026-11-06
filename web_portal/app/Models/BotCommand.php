<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotCommand extends Model
{
    use HasFactory;

    protected $table = 'bot_commands';

    protected $fillable = [
        'command',
        'title',
        'description',
        'help_text',
        'category',
        'access_level',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Obtiene el mapa completo de comandos activos para exportación o consulta.
     */
    public static function getActiveCommandsMap(): array
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('command')
            ->map(function ($item) {
                return [
                    'title' => $item->title,
                    'description' => $item->description,
                    'help_text' => $item->help_text,
                    'category' => $item->category,
                    'access_level' => $item->access_level,
                ];
            })
            ->toArray();
    }
}
