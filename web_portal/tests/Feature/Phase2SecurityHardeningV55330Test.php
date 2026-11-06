<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\NetRadar\TriggerNetRadarScanAction;
use App\Actions\Snmp\ActivateRemoteSnmpAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class Phase2SecurityHardeningV55330Test extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role', 'admin')->first() ?? User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'terms_accepted_at' => now(),
        ]);

        if (!$this->adminUser->terms_accepted_at) {
            $this->adminUser->update(['terms_accepted_at' => now()]);
        }
    }

    /**
     * SEC-15: Verificar que la activación SNMP remota erradica contraseñas y secretos
     * de /proc/<pid>/cmdline utilizando Process::input() con --stdin-auth.
     */
    public function test_activate_remote_snmp_passes_credentials_via_stdin_not_in_argv(): void
    {
        Process::fake([
            '*' => Process::result(
                output: json_encode([
                    'status' => 'success',
                    'output' => 'snmp-server community SafeComm RO configured successfully',
                ], JSON_THROW_ON_ERROR),
                exitCode: 0
            ),
        ]);

        $this->actingAs($this->adminUser);

        $action = $this->app->make(ActivateRemoteSnmpAction::class);
        $result = $action->execute([
            'ip_address' => '192.168.1.200',
            'method' => 'ssh',
            'username' => 'cisco_admin',
            'password' => 'SuperSecretPass#9988',
            'secret' => 'EnableSecret#7766',
            'community' => 'SafeComm',
            'port' => 22,
        ], '192.168.1.50', 'TestAuditAgent/2.0');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(200, $result['status_code']);

        // Verificar que el comando ejecutado no expone contraseñas en argv y usa --stdin-auth
        Process::assertRan(function ($process, $processResult) {
            $command = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;

            // Argv NO debe contener ni el password, ni el enable secret, ni la flag --password
            $argvExposesPassword = str_contains($command, 'SuperSecretPass#9988');
            $argvExposesSecret = str_contains($command, 'EnableSecret#7766');
            $argvHasPasswordFlag = str_contains($command, '--password');
            $argvHasSecretFlag = str_contains($command, '--secret');

            // Argv DEBE contener la flag --stdin-auth
            $hasStdinFlag = str_contains($command, '--stdin-auth');

            // Stdin DEBE contener el payload JSON con las credenciales
            $stdinPayload = (string) $process->input;
            $stdinHasPassword = str_contains($stdinPayload, 'SuperSecretPass#9988');
            $stdinHasSecret = str_contains($stdinPayload, 'EnableSecret#7766');
            $stdinHasCommunity = str_contains($stdinPayload, 'SafeComm');

            return !$argvExposesPassword
                && !$argvExposesSecret
                && !$argvHasPasswordFlag
                && !$argvHasSecretFlag
                && $hasStdinFlag
                && $stdinHasPassword
                && $stdinHasSecret
                && $stdinHasCommunity;
        });

        // Verificar registro de auditoría en MariaDB SSOT
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'event' => 'remote_activation',
            'module' => 'snmp',
            'entity_name' => '192.168.1.200',
        ]);
    }

    /**
     * ARC-07: Verificar que TriggerNetRadarScanAction no redirige salida a archivos planos
     * en /tmp/monitor/net_radar_scan.log evitando fallos de permisos y cumpliendo Regla #6.
     */
    public function test_net_radar_scan_does_not_create_flat_log_in_tmp(): void
    {
        $logPath = '/tmp/monitor/net_radar_scan.log';
        if (file_exists($logPath)) {
            @unlink($logPath);
        }

        $this->actingAs($this->adminUser);

        $action = $this->app->make(TriggerNetRadarScanAction::class);
        $result = $action->execute($this->adminUser, '192.168.1.50', 'TestAuditAgent/2.0');

        $this->assertTrue($result['success']);
        $this->assertFileDoesNotExist($logPath);

        // Verificar registro de auditoría en MariaDB SSOT
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'event' => 'scanned',
            'module' => 'netradar',
            'entity_name' => 'NET Radar',
        ]);
    }
}
