<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Services\BotTokenResolverInterface;
use App\Contracts\Services\HardwareVaultReaderInterface;
use App\DTOs\Security\HardwareVaultCredentialsDto;
use App\Exceptions\Security\HardwareVaultUnavailableException;
use App\Services\Notification\TelegramTransport;
use App\Services\Security\HardwareVaultReaderService;
use App\Services\Security\HardwareVaultService;
use App\Services\Security\OperationalBotTokenResolver;
use Tests\TestCase;

/**
 * Suite de Pruebas Unitarias para la Fase 1: Bóveda de Hardware y Tokens (SOLID).
 */
class HardwareVaultSolidTest extends TestCase
{
    public function test_credentials_dto_immutability_and_validation(): void
    {
        $dto = new HardwareVaultCredentialsDto('0000000000:AAEDummyTestTokenForMockVerificationAA', 38914901);
        $this->assertSame('0000000000:AAEDummyTestTokenForMockVerificationAA', $dto->botToken);
        $this->assertSame(38914901, $dto->ownerId);
        $this->assertTrue($dto->isValid());

        $invalidDto = new HardwareVaultCredentialsDto('invalid_token', 38914901);
        $this->assertFalse($invalidDto->isValid());
    }

    public function test_hardware_vault_reader_unwraps_operational_token(): void
    {
        $reader = new HardwareVaultReaderService();
        $token = $reader->unwrap();

        $this->assertNotNull($token, 'El token operacional debe poder desencriptarse de .sys_anchor');
        $this->assertStringContainsString(':', $token);
        $this->assertStringStartsWith('1595888148:', $token, 'El token debe ser de @ValleSeco_bot');
    }

    public function test_operational_resolver_throws_exception_on_unavailable_vault_with_zero_sentinel_fallback(): void
    {
        $mockReader = new class implements HardwareVaultReaderInterface {
            public function unwrap(): ?string
            {
                return null;
            }

            public function isAvailable(): bool
            {
                return false;
            }
        };

        $resolver = new OperationalBotTokenResolver($mockReader);

        $this->expectException(HardwareVaultUnavailableException::class);
        $resolver->resolve();
    }

    public function test_legacy_facade_no_longer_returns_sentinel_token(): void
    {
        $sentinel = HardwareVaultService::getSentinelToken();
        $this->assertSame('', $sentinel, 'El token sentinela no debe ser retornado por ningún servicio web');
    }

    public function test_telegram_transport_uses_resolver_and_strictly_blocks_groups(): void
    {
        $transport = app(\App\Contracts\NotifierInterface::class);
        $this->assertInstanceOf(TelegramTransport::class, $transport);

        $this->assertStringStartsWith('1595888148:', (string) $transport->getBotToken());

        // Regla #1: Prohibición absoluta de envíos a grupos corporativos
        $this->assertFalse($transport->send('-1001383163558', 'Test'));
        $this->assertFalse($transport->send('-999999999', 'Test'));
    }

    public function test_vault_permissions_allow_www_data_read(): void
    {
        $vaultDir = '/opt/sonar/system/vault';
        $anchorFile = '/opt/sonar/system/vault/.sys_anchor';

        $this->assertFileExists($vaultDir);
        $this->assertFileExists($anchorFile);

        $dirPerms = substr(sprintf('%o', fileperms($vaultDir)), -4);
        $filePerms = substr(sprintf('%o', fileperms($anchorFile)), -4);

        $this->assertSame('0750', $dirPerms, 'El directorio vault debe ser 0750');
        $this->assertSame('0640', $filePerms, 'El archivo .sys_anchor debe ser 0640');
    }

    public function test_operational_resolver_gets_owner_id_dynamically_from_database_and_never_hardcoded(): void
    {
        $mockReader = new class implements HardwareVaultReaderInterface {
            public function unwrap(): ?string { return '1595888148:TEST'; }
            public function isAvailable(): bool { return true; }
        };

        $resolver = new OperationalBotTokenResolver($mockReader);
        $ownerId = $resolver->getOwnerId();

        $this->assertIsInt($ownerId);
        $this->assertSame((int) \App\Models\BotSetting::get('owner_id'), $ownerId);
    }
}
