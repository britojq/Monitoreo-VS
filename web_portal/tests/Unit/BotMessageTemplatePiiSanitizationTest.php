<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\BotMessageTemplate;
use Tests\TestCase;

class BotMessageTemplatePiiSanitizationTest extends TestCase
{
    public function test_factory_templates_contain_no_personal_data(): void
    {
        foreach (['servicios', 'sedes'] as $key) {
            $tpl = BotMessageTemplate::getDefaultFactoryValues($key);
            $this->assertNotNull($tpl);

            $signature = $tpl['default_signature'] ?? '';

            // Verificar ausencia estricta de PII (DOC-L2)
            $this->assertStringNotContainsString('12345678', $signature);
            $this->assertStringNotContainsString('144306', $signature);
            $this->assertStringNotContainsString('02423602039', $signature);
            $this->assertStringNotContainsString('C.I:', $signature);

            // Verificar presencia de firma institucional neutra
            $this->assertStringContainsString('Personal de Guardia ATIT', $signature);
            $this->assertStringContainsString('Infraestructura Tecnológica', $signature);
        }
    }

    public function test_database_templates_contain_no_personal_data(): void
    {
        $templates = BotMessageTemplate::whereIn('template_key', ['servicios', 'sedes'])->get();

        foreach ($templates as $tpl) {
            $signature = $tpl->default_signature ?? '';

            $this->assertStringNotContainsString('12345678', $signature);
            $this->assertStringNotContainsString('144306', $signature);
            $this->assertStringNotContainsString('02423602039', $signature);
            $this->assertStringNotContainsString('C.I:', $signature);
        }
    }
}
