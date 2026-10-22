<?php

namespace Tests\Unit;

use App\Contracts\CommandRunnerInterface;
use App\Contracts\HardwareInspectorInterface;
use App\Models\InventoryAsset;
use App\Services\Deployment\AgentDeployService;
use App\Services\Hardware\LinuxHardwareInspector;
use App\Services\Hardware\UpsHardwareService;
use App\Services\System\SystemCommandRunner;
use Tests\TestCase;

class HardwareAndDeployArchitectureTest extends TestCase
{
    public function test_container_resolves_command_runner_and_hardware_inspector_interfaces(): void
    {
        $commandRunner = app(CommandRunnerInterface::class);
        $this->assertInstanceOf(SystemCommandRunner::class, $commandRunner);

        $hardwareInspector = app(HardwareInspectorInterface::class);
        $this->assertInstanceOf(LinuxHardwareInspector::class, $hardwareInspector);
    }

    public function test_system_command_runner_executes_safely(): void
    {
        $runner = new SystemCommandRunner();
        $result = $runner->run(['echo', 'SOLID_PROCESS_TEST'], 5);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('SOLID_PROCESS_TEST', $result['output']);
        $this->assertSame(0, $result['exit_code']);
    }

    public function test_linux_hardware_inspector_detects_ports_with_valid_schema(): void
    {
        $inspector = new LinuxHardwareInspector();
        $ports = $inspector->detectSerialPorts();

        $this->assertIsArray($ports);
        foreach ($ports as $port) {
            $this->assertArrayHasKey('path', $port);
            $this->assertArrayHasKey('type', $port);
            $this->assertArrayHasKey('label', $port);
        }
    }

    public function test_ups_hardware_service_runtime_calculation(): void
    {
        $inspector = $this->createMock(HardwareInspectorInterface::class);
        $commandRunner = $this->createMock(CommandRunnerInterface::class);

        $upsService = new UpsHardwareService($inspector, $commandRunner);

        $runtimeMinutes = $upsService->calculateEstimatedRuntime(100, 8);
        $this->assertGreaterThanOrEqual(60, $runtimeMinutes);
        $this->assertLessThanOrEqual(120, $runtimeMinutes);

        $humanRuntime = $upsService->formatRuntime(90);
        $this->assertSame('1h 30m', $humanRuntime);

        $humanMinutes = $upsService->formatRuntime(45);
        $this->assertSame('45 min', $humanMinutes);
    }

    public function test_ups_hardware_service_test_serial_with_mocked_runner(): void
    {
        $inspector = $this->createMock(HardwareInspectorInterface::class);
        $commandRunner = $this->createMock(CommandRunnerInterface::class);

        $commandRunner->expects($this->once())
            ->method('run')
            ->willReturn([
                'success' => true,
                'output' => "ESTADO EN TIEMPO REAL: 220V",
                'error' => '',
                'exit_code' => 0,
            ]);

        $upsService = new UpsHardwareService($inspector, $commandRunner);
        $response = $upsService->testSerial('/dev/ttyS0', 2400);

        $this->assertTrue($response['success']);
        $this->assertSame('/dev/ttyS0', $response['port']);
        $this->assertStringContainsString('Comunicación exitosa', $response['message']);
    }

    public function test_agent_deploy_service_ip_in_subnet(): void
    {
        $commandRunner = $this->createMock(CommandRunnerInterface::class);
        $endpointConfig = $this->createMock(\App\Contracts\GlpiEndpointConfigInterface::class);
        $deployService = new AgentDeployService($commandRunner, $endpointConfig);

        $this->assertTrue($deployService->ipInSubnet('192.168.1.5', '192.168.1.0/24'));
        $this->assertTrue($deployService->ipInSubnet('192.168.1.254', '192.168.1.0/24'));
        $this->assertFalse($deployService->ipInSubnet('192.168.106.5', '192.168.1.0/24'));
        $this->assertFalse($deployService->ipInSubnet('invalid-ip', '192.168.1.0/24'));
    }

    public function test_agent_deploy_service_supported_device_classification(): void
    {
        $commandRunner = $this->createMock(CommandRunnerInterface::class);
        $endpointConfig = $this->createMock(\App\Contracts\GlpiEndpointConfigInterface::class);
        $deployService = new AgentDeployService($commandRunner, $endpointConfig);

        // Estación de trabajo PC debe ser soportada
        $pcItem = [
            'hostname' => 'TAQUILLA 01',
            'vendor' => 'HP Inc.',
            'notes' => 'Atención al usuario',
            'raw_os' => 'windows 10 pro x64',
            'discovered_device_type' => 'workstation',
        ];
        $this->assertTrue($deployService->isGlpiSupportedDevice($pcItem, null, 'windows'));

        // Router o Switch no debe tener soporte de agente GLPI
        $routerItem = [
            'hostname' => 'ROUTER PRINCIPAL',
            'vendor' => 'Cisco Systems',
            'notes' => 'Enlace central',
            'raw_os' => 'cisco ios',
            'discovered_device_type' => 'router',
        ];
        $this->assertFalse($deployService->isGlpiSupportedDevice($routerItem, null, 'unknown'));
    }

    public function test_agent_deploy_service_executes_deploy_via_runner(): void
    {
        $commandRunner = $this->createMock(CommandRunnerInterface::class);
        $endpointConfig = $this->createMock(\App\Contracts\GlpiEndpointConfigInterface::class);
        $endpointConfig->expects($this->once())
            ->method('resolveTargetUrl')
            ->with('dev', null)
            ->willReturn([
                'server_url' => 'http://192.168.1.220/api/v1/inventory/receiver/d8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96',
                'deploy_base_url' => 'http://192.168.1.220',
            ]);

        $commandRunner->expects($this->once())
            ->method('run')
            ->willReturn([
                'success' => true,
                'output' => json_encode(['success' => true, 'installed' => true]),
                'error' => '',
                'exit_code' => 0,
            ]);

        $deployService = new AgentDeployService($commandRunner, $endpointConfig);
        $result = $deployService->deploy('192.168.1.199', 'windows', 'admin', 'dev', 'install', 'secret');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['installed']);
    }
}
