<?php

namespace App\Services\System;

use App\Contracts\CommandRunnerInterface;
use Symfony\Component\Process\Process;

/**
 * Servicio de infraestructura para la ejecución segura de comandos y procesos del sistema operativo.
 * Implementa CommandRunnerInterface cumpliendo con el Principio de Inversión de Dependencias (D).
 */
class SystemCommandRunner implements CommandRunnerInterface
{
    /**
     * Ejecuta un comando en el sistema operativo de forma síncrona y estructurada.
     *
     * @param array $command Lista de argumentos para el proceso
     * @param int $timeout Segundos máximos de ejecución
     * @return array Resultado con claves ['success' => bool, 'output' => string, 'error' => string, 'exit_code' => int]
     */
    public function run(array $command, int $timeout = 60): array
    {
        try {
            $process = new Process($command);
            $process->setTimeout($timeout);
            $process->run();

            return [
                'success' => $process->isSuccessful(),
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode() ?? 1,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'Error al ejecutar proceso del sistema: ' . $e->getMessage(),
                'exit_code' => -1,
            ];
        }
    }
}
