<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class Phase3QueueWorkerGovernanceV55360Test extends TestCase
{
    /**
     * TECH-OPS-02: Verificar que la plantilla systemd de sonar-queue-worker
     * define límites estrictos de gobernanza de memoria y reinicio resiliente.
     */
    public function test_sonar_queue_worker_template_enforces_memory_limits(): void
    {
        $templatePath = base_path('../system/templates/systemd/sonar-queue-worker.service');
        $this->assertFileExists($templatePath);

        $content = file_get_contents($templatePath);
        $this->assertStringContainsString('MemoryMax=512M', $content);
        $this->assertStringContainsString('MemoryHigh=400M', $content);
        $this->assertStringContainsString('Restart=always', $content);
        $this->assertStringContainsString('RestartSec=5', $content);
        $this->assertStringContainsString('ExecStart=/usr/bin/php', $content);
        $this->assertStringContainsString('artisan queue:work', $content);
    }

    /**
     * TECH-OPS-02: Verificar que el servicio systemd en caliente aplica los límites de memoria.
     */
    public function test_sonar_queue_worker_systemd_runtime_enforces_memory_cap(): void
    {
        $result = Process::run(['systemctl', 'show', 'sonar-queue-worker.service', '-p', 'ActiveState', '-p', 'MemoryMax', '-p', 'MemoryHigh']);

        $output = $result->output();
        $this->assertStringContainsString('ActiveState=active', $output);
        // 512M en bytes es 536870912
        $this->assertStringContainsString('MemoryMax=536870912', $output);
        // 400M en bytes es 419430400
        $this->assertStringContainsString('MemoryHigh=419430400', $output);
    }
}
