<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Security\RotateApplicationKeyAction;
use App\Models\SnmpDevice;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Phase3VaultRotateKeyV55420Test extends TestCase
{
    use DatabaseTransactions;

    /**
     * TECH-SEC-13 (NIST SP 800-57): Verificar que la ejecución con --dry-run
     * simule el re-cifrado completo de secrets sin mutar .env ni la base de datos.
     */
    public function test_vault_rotate_key_dry_run_does_not_modify_env_or_database(): void
    {
        $originalAppKey = (string) config('app.key');

        $exitCode = Artisan::call('vault:rotate-key', [
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Simulación completada con éxito', $output);
        $this->assertStringContainsString('Cero cambios aplicados', $output);

        // Verificar que la configuración y los datos no cambiaron
        $this->assertSame($originalAppKey, config('app.key'));

        $firstDevice = SnmpDevice::first();
        if ($firstDevice) {
            $this->assertNotNull($firstDevice->snmp_community_encrypted);
            $this->assertSame('public', $firstDevice->snmp_community_encrypted);
        }
    }

    /**
     * TECH-SEC-13: Verificar validación estricta de formato y tamaño de clave (AES-256 / 32 bytes).
     */
    public function test_key_validation_rejects_invalid_lengths(): void
    {
        $action = new RotateApplicationKeyAction();

        // 1. Clave corta (menos de 32 bytes)
        $this->expectException(\InvalidArgumentException::class);
        $action->parseKey('base64:' . base64_encode('short_key'));
    }

    /**
     * TECH-SEC-13: Verificar rotación atómica funcional con archivo .env temporal
     * comprobando que el registro re-cifrado sea legible con la nueva clave.
     */
    public function test_vault_rotate_key_action_migrates_data_atomically(): void
    {
        $action = new RotateApplicationKeyAction();
        $tempEnv = tempnam(sys_get_temp_dir(), 'env_test_');
        $currentKey = (string) config('app.key');
        file_put_contents($tempEnv, "APP_NAME=Sonar\nAPP_KEY={$currentKey}\n");

        $newKey = 'base64:' . base64_encode(random_bytes(32));

        // Ejecutar rotación real sobre el archivo temporal de entorno
        $result = $action->execute($newKey, false, $tempEnv);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['dry_run']);
        $this->assertGreaterThanOrEqual(0, $result['migrated_records']);

        // Verificar que el archivo temporal se actualizó
        $updatedEnvContent = (string) file_get_contents($tempEnv);
        $this->assertStringContainsString("APP_KEY={$newKey}", $updatedEnvContent);

        // Verificar que un registro en la base de datos se descifra con la nueva clave
        $binaryNewKey = $action->parseKey($newKey);
        $newEncrypter = new Encrypter($binaryNewKey, (string) config('app.cipher', 'AES-256-CBC'));

        $rawCommunity = \Illuminate\Support\Facades\DB::table('snmp_devices')
            ->whereNotNull('snmp_community_encrypted')
            ->value('snmp_community_encrypted');

        if (!empty($rawCommunity)) {
            $decryptedWithNewKey = $newEncrypter->decryptString((string) $rawCommunity);
            $this->assertSame('public', $decryptedWithNewKey);
        }

        // Limpiar archivo temporal
        @unlink($tempEnv);
    }

    /**
     * Garantía de Inmutabilidad Criptográfica: Verificar que el archivo de auditoría
     * docs/REPORTE_AUDITORIA_SEGURIDAD_ARQUITECTURA_v5.54.20.md se mantenga intacto.
     */
    public function test_audit_report_hash_remains_strictly_immutable(): void
    {
        $auditReport = base_path('../docs/REPORTE_AUDITORIA_SEGURIDAD_ARQUITECTURA_v5.54.20.md');
        $this->assertFileExists($auditReport);

        $expectedHash = '88d8174095afbe98c7ad47e6c9cd86e9bc5c1d3c6690d13de9d52fff9f6cd165';
        $actualHash = hash_file('sha256', $auditReport);

        $this->assertSame(
            $expectedHash,
            $actualHash,
            'CRÍTICO: El archivo de auditoría ha sido alterado o manipulado.'
        );
    }
}
