<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Admin\AdminNetworkDeviceController;
use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class ZeroDummyDataAndTypeClassificationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test 1: TAQUILLA SW-01 no debe clasificarse como switch por contener 'SW' en su nombre.
     */
    public function test_taquilla_sw_is_not_classified_as_switch(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $site = MonitoredSite::first();
        $this->assertNotNull($site);

        $controller = new AdminNetworkDeviceController();

        $ip = '10.20.98.' . rand(10, 240);
        $request = Request::create(route('admin.devices.store'), 'POST', [
            'monitored_site_id' => $site->id,
            'name' => 'TAQUILLA SW-01',
            'ip' => $ip,
            'access_type' => 'SSH',
            'is_active' => true,
        ]);
        $request->setUserResolver(fn() => $admin);

        $response = $controller->store($request);

        $device = MonitoredNetworkDevice::where('ip', $ip)->first();
        $this->assertNotNull($device);

        $asset = InventoryAsset::find($device->id);
        $this->assertNotNull($asset);

        // Debe ser desktop, NO switch
        $this->assertSame('desktop', $asset->type, 'TAQUILLA SW-01 no debe ser inferido como switch.');
        $this->assertNull($asset->ports, 'Una estación de trabajo desktop no debe tener puertos de red como 1x GE.');
    }

    /**
     * Test 2: Un equipo con nombre explícito de switch (ej. SW01 o SWITCH PISO 1) sí se infiere como switch.
     */
    public function test_real_switch_naming_infers_switch_type(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $site = MonitoredSite::first();
        $this->assertNotNull($site);

        $controller = new AdminNetworkDeviceController();

        $ip = '10.20.98.' . rand(10, 240);
        $request = Request::create(route('admin.devices.store'), 'POST', [
            'monitored_site_id' => $site->id,
            'name' => 'SW01 CORE TEST',
            'ip' => $ip,
            'access_type' => 'SSH',
            'is_active' => true,
        ]);
        $request->setUserResolver(fn() => $admin);

        $controller->store($request);

        $device = MonitoredNetworkDevice::where('ip', $ip)->first();
        $this->assertNotNull($device);

        $asset = InventoryAsset::find($device->id);
        $this->assertNotNull($asset);

        $this->assertSame('switch', $asset->type);
    }

    /**
     * Test 3: Cero registros con serial '00000000' o '1x GE' en puestos de trabajo de la base de datos real.
     */
    public function test_zero_dummy_data_in_workstations(): void
    {
        $dummySerials = InventoryAsset::whereIn('type', ['desktop', 'laptop'])
            ->where('serial_number', '00000000')
            ->count();
        $this->assertSame(0, $dummySerials, 'No deben existir estaciones con serial 00000000.');

        $dummyPorts = InventoryAsset::whereIn('type', ['desktop', 'laptop'])
            ->where('ports', '1x GE')
            ->count();
        $this->assertSame(0, $dummyPorts, 'No deben existir estaciones de trabajo con ports 1x GE.');

        $syntheticSerials = InventoryAsset::whereIn('type', ['desktop', 'laptop'])
            ->where('serial_number', 'like', 'SN-NET-%')
            ->count();
        $this->assertSame(0, $syntheticSerials, 'No deben existir estaciones con seriales sintéticos SN-NET.');
    }

    /**
     * Test 4: Router Principal preserva su integridad absoluta (SN-NET-00001, TELNET, 23).
     */
    public function test_router_principal_maintains_integrity(): void
    {
        $router = InventoryAsset::find(1);
        $this->assertNotNull($router);
        $this->assertSame('ROUTER PRINCIPAL', $router->name);
        $this->assertSame('router', $router->type);
        $this->assertSame('SN-NET-00001', $router->serial_number);
        $this->assertSame('TELNET', $router->access_type);
        $this->assertSame(23, $router->access_port);
    }

    /**
     * Test 5: Auditoría global de paridad: Cero datos ficticios o de prueba en MariaDB (SSOT)
     * invocando el comando oficial de verificación.
     */
    public function test_entire_mariadb_database_has_zero_dummy_or_test_records(): void
    {
        $command = new \App\Console\Commands\VerifyZeroDummyDataCommand();
        $command->setLaravel($this->app);
        // Usar buffered output para capturar salida sin requerir terminal interactivo
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $command->setOutput(new \Illuminate\Console\OutputStyle(new \Symfony\Component\Console\Input\ArrayInput([]), $output));
        $exitCode = $command->handle();

        $this->assertSame(0, $exitCode, 'VerifyZeroDummyDataCommand debe finalizar con código 0 (éxito absoluto sin datos de prueba).');

        // Certificar que audit_logs no contenga prefijos o identificadores de prueba
        $testAuditCount = \App\Models\AuditLog::where('entity_label', 'like', '%TEST-%')
            ->orWhere('description', 'like', '%TEST-%')
            ->orWhere('description', 'like', '%TEST DUAL%')
            ->orWhere('entity_label', 'like', '%TEST DUAL%')
            ->orWhere('entity_label', 'like', '%TEST-DUAL%')
            ->orWhere('new_values', 'like', '%TEST-DUAL%')
            ->count();
        $this->assertSame(0, $testAuditCount, 'audit_logs no debe contener ningún registro con prefijo TEST o TEST-DUAL.');

        // Certificar que no existan seriales sintéticos SN-NET en ningún dispositivo excepto el Router Principal
        $syntheticSerials = \App\Models\MonitoredNetworkDevice::where('serial', 'like', 'SN-NET-%')
            ->where('id', '!=', 1)
            ->count();
        $this->assertSame(0, $syntheticSerials, 'monitored_network_devices no debe contener seriales sintéticos SN-NET fuera del Router Principal.');
    }
}
