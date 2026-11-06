<?php

namespace Database\Seeders;

use App\Models\BotMessageTemplate;
use Illuminate\Database\Seeder;

class BotMessageTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $keys = ['servicios', 'sedes', 'debug_servicios', 'debug_sedes'];

        foreach ($keys as $key) {
            $data = BotMessageTemplate::getDefaultFactoryValues($key);
            if ($data) {
                $data['updated_by'] = 'Sistema (Inicialización)';
                BotMessageTemplate::updateOrCreate(
                    ['template_key' => $key],
                    $data
                );
            }
        }
    }
}
