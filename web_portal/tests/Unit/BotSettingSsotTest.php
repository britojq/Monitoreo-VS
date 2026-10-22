<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Admin\AdminCronController;
use App\Models\BotSetting;
use App\Services\AiConfigService;
use App\Services\ClusterConfigService;
use App\Services\LdapAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class BotSettingSsotTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        BotSetting::clearRuntimeCache();
    }

    /**
     * Test 1: Verificar que BotSetting maneja correctamente tipos tipados (boolean, integer, json, text).
     */
    public function test_bot_setting_handles_strongly_typed_values(): void
    {
        BotSetting::set('test_bool_true', true, 'testing');
        BotSetting::set('test_bool_false', false, 'testing');
        BotSetting::set('test_int', 42, 'testing');
        BotSetting::set('test_json', ['a' => 1, 'b' => 'val'], 'testing');
        BotSetting::set('test_text', 'hola mundo', 'testing');

        $this->assertTrue(BotSetting::get('test_bool_true'));
        $this->assertFalse(BotSetting::get('test_bool_false'));
        $this->assertSame(42, BotSetting::get('test_int'));
        $this->assertSame(['a' => 1, 'b' => 'val'], BotSetting::get('test_json'));
        $this->assertSame('hola mundo', BotSetting::get('test_text'));
    }

    /**
     * Test 2: ClusterConfigService opera directamente contra MariaDB (BotSetting).
     */
    public function test_cluster_config_service_uses_mariadb_ssot(): void
    {
        $service = new ClusterConfigService();
        $config = $service->getConfig();

        $this->assertArrayHasKey('node_role', $config);
        $this->assertArrayHasKey('cluster_token', $config);
        $this->assertArrayHasKey('master_api_url', $config);

        // Actualizar configuración
        $ok = $service->updateConfig([
            'node_role' => 'slave',
            'cluster_token' => 'token_de_prueba_unitario_12345',
            'master_api_url' => 'http://192.168.1.250',
            'slave_sync_interval_minutes' => 5,
        ]);

        $this->assertTrue($ok);
        $this->assertSame('slave', $service->getNodeRole());
        $this->assertTrue($service->isSlave());
        $this->assertFalse($service->isMaster());
        $this->assertSame('token_de_prueba_unitario_12345', $service->getClusterToken());
        $this->assertSame('http://192.168.1.250', $service->getMasterApiUrl());

        // Verificar directamente en la tabla bot_settings de MariaDB
        $this->assertSame('slave', BotSetting::get('cluster_node_role'));
        $this->assertSame('token_de_prueba_unitario_12345', BotSetting::get('cluster_token'));
    }

    /**
     * Test 3: LdapAuthService opera directamente contra MariaDB (BotSetting).
     */
    public function test_ldap_auth_service_uses_mariadb_ssot(): void
    {
        $service = new LdapAuthService();
        $config = $service->getConfig();

        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('host', $config);
        $this->assertArrayHasKey('port', $config);
        $this->assertArrayHasKey('base_dn', $config);

        // Modificar configuración de LDAP en MariaDB
        $ok = $service->updateConfig([
            'enabled' => false,
            'host' => '192.168.0.99',
            'port' => 636,
            'base_dn' => 'dc=test,dc=gob,dc=ve',
            'allowed_areas' => 'SISTEMAS, REDES',
            'default_role' => 'admin',
        ], 'Admin Unit Test');

        $this->assertTrue($ok);
        $updated = $service->getConfig();
        $this->assertFalse($updated['enabled']);
        $this->assertSame('192.168.0.99', $updated['host']);
        $this->assertSame(636, $updated['port']);
        $this->assertSame('dc=test,dc=gob,dc=ve', $updated['base_dn']);
        $this->assertSame('admin', $updated['default_role']);

        // Verificar directamente en MariaDB bot_settings
        $dbLdap = BotSetting::get('ldap_config');
        $this->assertIsArray($dbLdap);
        $this->assertFalse($dbLdap['enabled']);
        $this->assertSame('192.168.0.99', $dbLdap['host']);
    }

    /**
     * Test 4: AdminCronController opera directamente contra MariaDB (BotSetting).
     */
    public function test_admin_cron_controller_uses_mariadb_ssot(): void
    {
        $controller = new AdminCronController();
        $config = $controller->getCronConfig();

        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('schedules', $config);
        $this->assertArrayHasKey('web_check_interval', $config);

        // Probar alternado de estado
        $request = Request::create('/cron/toggle', 'POST');
        $controller->toggle($request);

        $newConfig = $controller->getCronConfig();
        $this->assertNotSame($config['enabled'], $newConfig['enabled']);

        // Probar actualización de intervalo web
        $requestInterval = Request::create('/cron/interval/update', 'POST', ['interval_minutes' => 45]);
        $controller->updateWebCheckInterval($requestInterval);

        $this->assertSame(45, BotSetting::get('web_check_interval_minutes'));
        $this->assertSame(45, $controller->getCronConfig()['web_check_interval']);
    }

    /**
     * Test 5: AiConfigService opera directamente contra MariaDB (BotSetting).
     */
    public function test_ai_config_service_uses_mariadb_ssot(): void
    {
        $service = new AiConfigService();

        $service->updateConfig(true, 'Test Admin');
        $this->assertTrue($service->isEnabled());
        $this->assertTrue((bool) BotSetting::get('ai_web_enabled'));

        $service->updateConfig(false, 'Test Admin');
        $this->assertFalse($service->isEnabled());
        $this->assertFalse((bool) BotSetting::get('ai_web_enabled'));
    }

    /**
     * Test 6: syncToFile exporta unidireccionalmente sin romper config.json.
     */
    public function test_bot_setting_sync_to_file(): void
    {
        $tempPath = sys_get_temp_dir() . '/test_config_' . uniqid() . '.json';
        file_put_contents($tempPath, json_encode(['test_key' => 'initial']));

        $result = BotSetting::syncToFile($tempPath);
        $this->assertTrue($result);
        $this->assertFileExists($tempPath);

        $content = json_decode(file_get_contents($tempPath), true);
        $this->assertIsArray($content);
        $this->assertSame('initial', $content['test_key']);
        $this->assertArrayHasKey('node_role', $content);
        $this->assertArrayHasKey('cron_reports_enabled', $content);

        @unlink($tempPath);
    }
}
