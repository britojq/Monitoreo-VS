<?php

namespace App\Contracts;

/**
 * Contrato para la ejecución segura de procesos del sistema operativo y comandos CLI.
 * Permite simular y mockear llamadas de consola en pruebas unitarias (Principio D).
 */
interface CommandRunnerInterface
{
    /**
     * Ejecuta un comando en el sistema operativo de forma síncrona.
     *
     * @param array $command Lista de argumentos para el proceso
     * @param int $timeout Segundos máximos de ejecución
     * @return array Resultado con claves ['success' => bool, 'output' => string, 'error' => string, 'exit_code' => int]
     */
    public function run(array $command, int $timeout = 60): array;
}
