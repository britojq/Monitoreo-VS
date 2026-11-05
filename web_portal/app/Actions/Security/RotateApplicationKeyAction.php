<?php

declare(strict_types=1);

namespace App\Actions\Security;

use App\Models\AuditLog;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RotateApplicationKeyAction
{
    /**
     * Mapa de tablas y columnas con contenido sensible cifrado simétricamente.
     *
     * @var array<string, list<string>>
     */
    protected array $targets = [
        'snmp_devices' => [
            'snmp_community_encrypted',
            'snmp_v3_auth_password_encrypted',
            'snmp_v3_priv_password_encrypted',
            'ssh_password_encrypted',
            'ssh_enable_secret_encrypted',
        ],
        'monitored_network_devices' => [
            'ssh_password_encrypted',
            'ssh_enable_secret_encrypted',
            'vnc_password_encrypted',
        ],
        'inventory_assets' => [
            'ssh_password_encrypted',
            'ssh_enable_secret_encrypted',
            'vnc_password_encrypted',
        ],
    ];

    /**
     * Ejecuta la rotación transaccional de clave de cifrado simétrica (NIST SP 800-57).
     *
     * @param string $newKey Clave nueva en formato base64:XXXX o string de 32 bytes
     * @param bool $dryRun Si es true, simula la rotación sin mutar .env ni la BD
     * @param string|null $envPath Ruta personalizada al archivo .env
     * @return array<string, mixed> Resumen de la operación
     *
     * @throws InvalidArgumentException Si la clave es inválida
     * @throws RuntimeException Si ocurre un error irrecuperable
     */
    public function execute(string $newKey, bool $dryRun = false, ?string $envPath = null): array
    {
        $currentRawKey = (string) config('app.key');
        $cipher = (string) config('app.cipher', 'AES-256-CBC');

        $currentBinaryKey = $this->parseKey($currentRawKey);
        $newBinaryKey = $this->parseKey($newKey);

        $currentEncrypter = new Encrypter($currentBinaryKey, $cipher);
        $newEncrypter = new Encrypter($newBinaryKey, $cipher);

        $envFile = $envPath ?: base_path('.env');
        $migratedRecords = 0;
        $details = [];

        DB::beginTransaction();

        try {
            foreach ($this->targets as $table => $columns) {
                $rows = DB::table($table)->get();
                $tableUpdatedCount = 0;

                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $value = $row->{$column} ?? null;
                        if (!empty($value) && is_string($value)) {
                            try {
                                $plaintext = $currentEncrypter->decryptString($value);
                                $updates[$column] = $newEncrypter->encryptString($plaintext);
                            } catch (DecryptException) {
                                // Intentar descifrado con deserialización como fallback
                                try {
                                    $plaintext = (string) $currentEncrypter->decrypt($value, true);
                                    $updates[$column] = $newEncrypter->encryptString($plaintext);
                                } catch (\Throwable $e) {
                                    throw new RuntimeException(
                                        "Error al descifrar registro ID {$row->id} en tabla '{$table}', columna '{$column}': {$e->getMessage()}"
                                    );
                                }
                            }
                        }
                    }

                    if (!empty($updates)) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                        $tableUpdatedCount++;
                        $migratedRecords++;
                    }
                }

                $details[$table] = $tableUpdatedCount;
            }

            if ($dryRun) {
                DB::rollBack();

                return [
                    'success' => true,
                    'dry_run' => true,
                    'migrated_records' => $migratedRecords,
                    'details' => $details,
                    'message' => 'Simulación completada con éxito. Cero cambios aplicados en BD y .env.',
                ];
            }

            // Actualización atómica del archivo .env
            $this->updateEnvAppKey($envFile, $newKey);

            DB::commit();

            // Registrar en auditoría MariaDB SSOT (Regla de Oro #6)
            try {
                AuditLog::create([
                    'event_type' => 'VAULT_KEY_ROTATED',
                    'module' => 'system',
                    'action' => 'rotate_encryption_key',
                    'description' => "Rotación atómica de APP_KEY completada. {$migratedRecords} registros re-cifrados (NIST SP 800-57).",
                    'metadata' => [
                        'migrated_records' => $migratedRecords,
                        'details' => $details,
                        'cipher' => $cipher,
                    ],
                ]);
            } catch (\Throwable) {
                // No abortar si la auditoría falla tras el commit
            }

            return [
                'success' => true,
                'dry_run' => false,
                'migrated_records' => $migratedRecords,
                'details' => $details,
                'message' => "Rotación exitosa. {$migratedRecords} registros re-cifrados y APP_KEY actualizada.",
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Extraer y validar clave binaria de 32 bytes para AES-256.
     */
    public function parseKey(string $key): string
    {
        $key = trim($key);
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded === false || strlen($decoded) !== 32) {
                throw new InvalidArgumentException('La clave base64 debe decodificarse exactamente a 32 bytes (AES-256).');
            }
            return $decoded;
        }

        if (strlen($key) !== 32) {
            throw new InvalidArgumentException('La clave sin prefijo base64 debe contener exactamente 32 caracteres binarios.');
        }

        return $key;
    }

    /**
     * Actualizar la variable APP_KEY en el archivo .env de forma atómica.
     */
    protected function updateEnvAppKey(string $envFile, string $newKey): void
    {
        if (!file_exists($envFile) || !is_writable($envFile)) {
            throw new RuntimeException("El archivo de entorno '{$envFile}' no existe o no tiene permisos de escritura.");
        }

        $content = (string) file_get_contents($envFile);
        $pattern = '/^APP_KEY=.*$/m';

        if (preg_match($pattern, $content)) {
            $newContent = (string) preg_replace($pattern, "APP_KEY={$newKey}", $content);
        } else {
            $newContent = "APP_KEY={$newKey}\n" . $content;
        }

        file_put_contents($envFile, $newContent, LOCK_EX);
    }
}
