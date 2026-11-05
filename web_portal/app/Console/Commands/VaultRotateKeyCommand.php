<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Security\RotateApplicationKeyAction;
use Illuminate\Console\Command;

class VaultRotateKeyCommand extends Command
{
    /**
     * Nombre y firma del comando Artisan.
     *
     * @var string
     */
    protected $signature = 'vault:rotate-key
                            {--new-key= : Nueva clave simétrica base64 (32 bytes). Si se omite, se genera automáticamente}
                            {--dry-run : Realiza una simulación transaccional con rollback sin alterar la base de datos ni .env}
                            {--force : Fuerza la ejecución sin confirmación interactiva}';

    /**
     * Descripción del comando en Artisan.
     *
     * @var string
     */
    protected $description = 'Rota de forma atómica la APP_KEY y re-cifra las credenciales de SNMP y SSH en MariaDB (NIST SP 800-57)';

    /**
     * Ejecuta el comando.
     */
    public function handle(RotateApplicationKeyAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $newKey = (string) $this->option('new-key');

        if (empty($newKey)) {
            $newKey = 'base64:' . base64_encode(random_bytes(32));
            $this->line("<comment>Clave generada automáticamente:</comment> {$newKey}");
        }

        if (!$dryRun && !$force) {
            $confirmed = $this->confirm(
                'Esta operación re-cifrará todos los secretos en MariaDB y actualizará el archivo .env. ¿Desea continuar?',
                false
            );

            if (!$confirmed) {
                $this->warn('Operación cancelada por el usuario.');
                return self::FAILURE;
            }
        }

        $this->info($dryRun ? 'Iniciando simulación de rotación de clave (--dry-run)...' : 'Iniciando rotación atómica de clave simétrica...');

        try {
            $result = $action->execute($newKey, $dryRun);

            $this->newLine();
            $this->info("✔ {$result['message']}");
            $this->line("  • Registros procesados: {$result['migrated_records']}");

            if (!empty($result['details'])) {
                foreach ($result['details'] as $table => $count) {
                    $this->line("    - {$table}: {$count} filas actualizadas");
                }
            }

            if (!$dryRun) {
                $this->callSilent('config:clear');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("✘ ERROR CRÍTICO DURANTE LA ROTACIÓN: {$e->getMessage()}");
            $this->warn('La transacción en MariaDB fue revertida automáticamente (Rollback). Ningún dato fue corrompido.');
            return self::FAILURE;
        }
    }
}
