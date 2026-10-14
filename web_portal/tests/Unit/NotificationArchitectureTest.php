<?php

namespace Tests\Unit;

use App\Contracts\NotifierInterface;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Services\Notification\Formatters\AuthNotificationFormatter;
use App\Services\Notification\Formatters\GlpiNotificationFormatter;
use App\Services\Notification\Formatters\ItamNotificationFormatter;
use App\Services\Notification\TelegramTransport;
use App\Services\TelegramNotificationService;
use Tests\TestCase;

class NotificationArchitectureTest extends TestCase
{
    /**
     * Prueba que el contenedor resuelve NotifierInterface hacia TelegramTransport (Principio D).
     */
    public function test_notifier_interface_binds_to_telegram_transport(): void
    {
        $notifier = app(NotifierInterface::class);
        $this->assertInstanceOf(TelegramTransport::class, $notifier);
        $this->assertInstanceOf(NotifierInterface::class, $notifier);
    }

    /**
     * Prueba que TelegramTransport respeta la Regla de Oro #1: rechaza chat IDs negativos (grupos).
     */
    public function test_telegram_transport_rejects_negative_group_chat_ids(): void
    {
        $transport = new TelegramTransport();
        $result = $transport->send('-1001383163558', 'Mensaje de prueba');
        $this->assertFalse($result, 'El transporte debe rechazar cualquier ID de grupo negativo.');
    }

    /**
     * Prueba que los formatters construyen HTML válido y tipado.
     */
    public function test_formatters_generate_html_strings(): void
    {
        $authFormatter = new AuthNotificationFormatter();
        $user = new User([
            'name' => 'Usuario Test',
            'username' => 'utest',
            'email' => 'utest@empresa.local',
            'role' => 'operator',
        ]);

        $htmlLogin = $authFormatter->formatUserLogin($user, '10.20.23.100', 'local');
        $this->assertStringContainsString('INICIO DE SESIÓN', $htmlLogin);
        $this->assertStringContainsString('Usuario Test', $htmlLogin);
        $this->assertStringContainsString('10.20.23.100', $htmlLogin);

        $glpiFormatter = new GlpiNotificationFormatter();
        $asset = new InventoryAsset([
            'name' => 'PC-TEST-01',
            'brand' => 'Dell',
            'model' => 'OptiPlex 7050',
            'serial_number' => 'SN123456',
            'ip_address' => '10.20.23.50',
            'operating_system' => 'Debian 12',
        ]);
        $htmlAsset = $glpiFormatter->formatNewAsset($asset);
        $this->assertStringContainsString('NUEVO ACTIVO DETECTADO', $htmlAsset);
        $this->assertStringContainsString('PC-TEST-01', $htmlAsset);

        $itamFormatter = new ItamNotificationFormatter();
        $ticket = new MaintenanceTicket([
            'ticket_code' => 'TCK-2026-001',
            'title' => 'Reemplazo de fuente de poder',
            'priority' => 'alta',
        ]);
        $ticket->setRelation('asset', $asset);
        $htmlTicket = $itamFormatter->formatMaintenanceTicket($ticket);
        $this->assertStringContainsString('TCK-2026-001', $htmlTicket);
    }

    /**
     * Prueba que TelegramNotificationService funciona como fachada desacoplada sin errores.
     */
    public function test_telegram_notification_service_facade_delegates_cleanly(): void
    {
        $service = app(TelegramNotificationService::class);
        $this->assertInstanceOf(TelegramNotificationService::class, $service);
        $this->assertInstanceOf(TelegramTransport::class, $service->getTransport());

        // sendMessage en entorno de testing retorna true sin enviar peticiones reales
        $result = $service->sendMessage('Prueba unitaria');
        $this->assertTrue($result);
    }
}
