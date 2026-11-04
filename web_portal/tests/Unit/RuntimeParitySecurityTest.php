<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cluster\RuntimeParityInspector;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RuntimeParitySecurityTest extends TestCase
{
    public function test_runtime_parity_inspector_rejects_out_of_range_ports(): void
    {
        $inspector = new RuntimeParityInspector();
        $reflector = new \ReflectionClass($inspector);
        $method = $reflector->getMethod('isLocalPortListening');
        $method->setAccessible(true);

        // F-03: Puertos fuera de rango deben retornar false sin ejecutar subshell
        $this->assertFalse($method->invoke($inspector, 0));
        $this->assertFalse($method->invoke($inspector, -1));
        $this->assertFalse($method->invoke($inspector, 65536));
        $this->assertFalse($method->invoke($inspector, 999999));
    }

    public function test_runtime_parity_inspector_uses_safe_process_for_ports(): void
    {
        Process::fake([
            '*' => Process::result(
                output: "Netid State Recv-Q Send-Q Local Address:Port Peer Address:Port\ntcp LISTEN 0 128 0.0.0.0:8080 0.0.0.0:*\n",
                exitCode: 0
            ),
        ]);

        $inspector = new RuntimeParityInspector();
        $reflector = new \ReflectionClass($inspector);
        $method = $reflector->getMethod('isLocalPortListening');
        $method->setAccessible(true);

        $listening = $method->invoke($inspector, 8080);
        $notListening = $method->invoke($inspector, 9090);

        $this->assertTrue($listening);
        $this->assertFalse($notListening);

        Process::assertRan(function ($process) {
            return $process->command === ['ss', '-tuln'];
        });
    }
}
