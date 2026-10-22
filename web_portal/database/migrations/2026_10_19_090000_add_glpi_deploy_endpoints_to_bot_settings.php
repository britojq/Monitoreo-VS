<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use App\Models\BotSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Carga y asegura la persistencia de las configuraciones de endpoints GLPI
     * en MariaDB (bot_settings) como Única Fuente de Verdad (SSOT).
     */
    public function up(): void
    {
        $settings = [
            [
                'setting_key' => 'glpi_receiver_prod_url',
                'setting_value' => 'http://192.168.1.250/api/v1/inventory/receiver/d8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96',
                'setting_group' => 'glpi_deploy',
                'title' => 'URL Receptor GLPI - Producción',
                'description' => 'URL del endpoint receptor de inventario GLPI para el entorno de Producción.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'glpi_receiver_dev_url',
                'setting_value' => 'http://192.168.1.220/api/v1/inventory/receiver/d8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96',
                'setting_group' => 'glpi_deploy',
                'title' => 'URL Receptor GLPI - Desarrollo',
                'description' => 'URL del endpoint receptor de inventario GLPI para el entorno de Desarrollo.',
                'type' => 'text',
            ],
            [
                'setting_key' => 'glpi_receiver_token',
                'setting_value' => 'd8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96',
                'setting_group' => 'glpi_deploy',
                'title' => 'Token Receptor GLPI',
                'description' => 'Token criptográfico de seguridad para la recepción de reportes de agentes GLPI.',
                'type' => 'text',
            ],
        ];

        foreach ($settings as $item) {
            BotSetting::updateOrCreate(
                ['setting_key' => $item['setting_key']],
                $item
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        BotSetting::where('setting_group', 'glpi_deploy')->delete();
    }
};
