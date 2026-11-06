<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('bot_settings')->updateOrInsert(
            ['setting_key' => 'telegram_group_ai_enabled'],
            [
                'setting_value' => '0',
                'setting_group' => 'ai',
                'title' => 'Asistente IA en Grupo Corporativo de Telegram',
                'description' => 'Habilita o deshabilita la atención del asistente inteligente de IA en el grupo corporativo de Telegram. Por defecto permanece desactivado y solo puede ser conmutado por el Administrador.',
                'type' => 'boolean',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        // Desactivar formalmente la IA en la web en bot_settings (SSOT)
        DB::table('bot_settings')->updateOrInsert(
            ['setting_key' => 'ai_web_enabled'],
            [
                'setting_value' => '0',
                'setting_group' => 'ai',
                'title' => 'Asistente Corporativo de IA en Portal Web',
                'description' => 'Desactivado administrativamente para reservar las líneas de inferencia exclusivamente a Telegram.',
                'type' => 'boolean',
                'updated_at' => $now,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('bot_settings')->where('setting_key', 'telegram_group_ai_enabled')->delete();
    }
};
