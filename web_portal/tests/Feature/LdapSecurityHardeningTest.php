<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\User;
use App\Services\Ldap\LdapConnectionManager;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class LdapSecurityHardeningTest extends TestCase
{
    protected LdapConnectionManager $ldapManager;
    protected ?array $originalConfig = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ldapManager = app(LdapConnectionManager::class);
        $this->originalConfig = BotSetting::get('ldap_config');
    }

    protected function tearDown(): void
    {
        if ($this->originalConfig !== null) {
            BotSetting::set('ldap_config', $this->originalConfig, 'ldap', 'Configuración de Conexión y Autenticación LDAP', 'json');
        }
        parent::tearDown();
    }

    public function test_ldap_bind_password_is_encrypted_in_bot_settings(): void
    {
        $rawPassword = 'SuperSecretBindPassword2026!';
        $ok = $this->ldapManager->updateConfig([
            'enabled' => true,
            'host' => '192.168.0.22',
            'port' => 389,
            'base_dn' => 'dc=empresa,dc=gob,dc=ve',
            'bind_dn' => 'cn=service_account,dc=empresa,dc=gob,dc=ve',
            'bind_password' => $rawPassword,
        ]);

        $this->assertTrue($ok);

        // Verificar almacenamiento crudo en MariaDB
        $raw = BotSetting::get('ldap_config');
        $this->assertIsArray($raw);
        $this->assertArrayHasKey('bind_password_encrypted', $raw);
        $this->assertNotEmpty($raw['bind_password_encrypted']);

        // Comprobar que la contraseña NO se encuentra en texto plano
        $this->assertNotEquals($rawPassword, $raw['bind_password_encrypted']);
        $this->assertStringNotContainsString($rawPassword, json_encode($raw));
    }

    public function test_ldap_get_config_masks_bind_password_for_safety(): void
    {
        $this->ldapManager->updateConfig([
            'bind_password' => 'ConfidentialPass123',
        ]);

        $config = $this->ldapManager->getConfig();
        $this->assertTrue($config['has_bind_password']);
        $this->assertEquals('••••••••', $config['bind_password']);
    }

    public function test_ldap_get_decrypted_bind_password_recovers_original_secret(): void
    {
        $rawPassword = 'PlainTextBindPassword#456';
        $this->ldapManager->updateConfig([
            'bind_password' => $rawPassword,
        ]);

        $recovered = $this->ldapManager->getDecryptedBindPassword();
        $this->assertEquals($rawPassword, $recovered);
    }

    public function test_ldap_masked_password_does_not_overwrite_existing_secret(): void
    {
        $rawPassword = 'OriginalSecretBeforeMask';
        $this->ldapManager->updateConfig([
            'bind_password' => $rawPassword,
        ]);

        // Simular que el frontend envía los puntos de enmascaramiento
        $this->ldapManager->updateConfig([
            'bind_password' => '••••••••',
        ]);

        $recovered = $this->ldapManager->getDecryptedBindPassword();
        $this->assertEquals($rawPassword, $recovered);
    }

    public function test_ldap_empty_password_clears_bind_credential(): void
    {
        $this->ldapManager->updateConfig([
            'bind_password' => 'ToBeDeleted',
        ]);
        $this->assertNotNull($this->ldapManager->getDecryptedBindPassword());

        // Limpiar contraseña
        $this->ldapManager->updateConfig([
            'bind_password' => '',
        ]);

        $this->assertNull($this->ldapManager->getDecryptedBindPassword());
        $config = $this->ldapManager->getConfig();
        $this->assertFalse($config['has_bind_password']);
    }

    public function test_admin_controller_accepts_and_encrypts_ldap_bind_password(): void
    {
        $admin = User::first() ?? User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.ldap.update'), [
            'ldap_enabled' => '1',
            'ldap_host' => '192.168.0.22',
            'ldap_port' => 389,
            'ldap_base_dn' => 'dc=empresa,dc=gob,dc=ve',
            'ldap_default_role' => 'operator',
            'ldap_bind_dn' => 'cn=admin_test,dc=local',
            'ldap_bind_password' => 'ControllerSecret789!',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'enabled' => true,
        ]);

        $this->assertEquals('ControllerSecret789!', $this->ldapManager->getDecryptedBindPassword());
    }
}
