<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SystemVersionCommand extends Command
{
    /**
     * Nombre y firma del comando en Artisan.
     *
     * @var string
     */
    protected $signature = 'system:version 
                            {--bump= : Tipo de incremento: fix (+10 parche), feat (+1 menor, parche 0), both (+1 menor, parche 10)}
                            {--status : Consultar el estado y sincronización de versión en todos los archivos}
                            {--dry-run : Simular el cálculo y actualización sin alterar ningún archivo}
                            {--set= : Establecer una versión específica (formato X.Y.Z)}';

    /**
     * Descripción del comando en Artisan.
     *
     * @var string
     */
    protected $description = 'Motor de Versionado Automático (Regla de Oro #12) - Plataforma Integral Valle Seco';

    /**
     * Ejecuta el comando invocando el motor tools/bump_version.py.
     */
    public function handle(): int
    {
        $baseDir = is_dir('/scripts/telegram-admin-bot') 
            ? '/scripts/telegram-admin-bot' 
            : base_path('..');

        $scriptPath = $baseDir . '/tools/bump_version.py';

        if (!file_exists($scriptPath)) {
            $this->error("El motor de versionado no existe en: {$scriptPath}");
            return 1;
        }

        $cmd = ['python3', $scriptPath];

        if ($this->option('status')) {
            $cmd[] = '--status';
        } elseif ($this->option('set')) {
            $cmd[] = '--set';
            $cmd[] = (string) $this->option('set');
        } elseif ($this->option('bump')) {
            $cmd[] = '--type';
            $cmd[] = (string) $this->option('bump');
        } else {
            // Si no se especifica opción, mostrar el estado actual
            $cmd[] = '--status';
        }

        if ($this->option('dry-run')) {
            $cmd[] = '--dry-run';
        }

        $process = new Process($cmd, $baseDir);
        $process->setTimeout(60);

        $process->run(function ($type, $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? 0 : 1;
    }
}
