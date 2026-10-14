<?php

namespace App\Services\Hardware;

use App\Contracts\CommandRunnerInterface;
use App\Contracts\HardwareInspectorInterface;

/**
 * Servicio de dominio para la gestión, telemetría y comunicación con el hardware de UPS.
 * Aísla toda ejecución de procesos, comandos CLI y detección de puertos fuera de la capa HTTP.
 */
class UpsHardwareService
{
    public function __construct(
        protected HardwareInspectorInterface $inspector,
        protected CommandRunnerInterface $commandRunner
    ) {}

    /**
     * Retorna la lista estructurada de puertos seriales físicos y adaptadores USB detectados.
     */
    public function listAvailablePorts(?string $currentPort = null, ?int $currentBaud = null): array
    {
        $ports = $this->inspector->detectSerialPorts();
        $found = [];

        foreach ($ports as $port) {
            $found[$port['path']] = true;
        }

        if (!empty($currentPort) && !isset($found[$currentPort])) {
            array_unshift($ports, [
                'path' => $currentPort,
                'type' => 'Personalizado',
                'label' => "{$currentPort} (Asignado)",
            ]);
        }

        return [
            'success' => true,
            'ports' => $ports,
            'current_port' => $currentPort ?? '/dev/ttyS0',
            'current_baud' => $currentBaud ?? 2400,
        ];
    }

    /**
     * Prueba comunicación serial en vivo hacia el UPS con trama Megatec Q1.
     */
    public function testSerial(string $port, int $baud): array
    {
        $python = $this->getPythonBinary();
        $script = $this->getUpsScriptPath();

        if (!$script) {
            return [
                'success' => false,
                'port' => $port,
                'baud' => $baud,
                'message' => 'El script de telemetría de UPS no fue encontrado en el servidor.',
                'raw_output' => '',
            ];
        }

        $cmd = [$python, $script, '--test-port', $port, '--baud', (string) $baud];
        $result = $this->commandRunner->run($cmd, 15);
        $fullOutput = trim($result['output'] . ($result['error'] ? "\n" . $result['error'] : ''));

        $success = ($result['exit_code'] === 0) && str_contains($fullOutput, 'ESTADO EN TIEMPO REAL');

        return [
            'success' => $success,
            'port' => $port,
            'baud' => $baud,
            'message' => $success
                ? '¡Comunicación exitosa! El UPS respondió correctamente a la trama Megatec Q1 en ' . $port . ' @ ' . $baud . ' bps.'
                : 'Sin respuesta del UPS en ' . $port . ' @ ' . $baud . ' bps. Verifique la conexión física o el conector serial.',
            'raw_output' => $fullOutput,
        ];
    }

    /**
     * Interroga en vivo al hardware del UPS enviando instrucciones Megatec Q1, I y F.
     */
    public function interrogateHardware(string $port, int $baud): array
    {
        $python = $this->getPythonBinary();
        $script = $this->getUpsScriptPath();

        if (!$script) {
            return [
                'success' => false,
                'port' => $port,
                'baud' => $baud,
                'message' => 'El script de telemetría de UPS no fue encontrado en el servidor.',
                'raw_output' => '',
            ];
        }

        $cmd = [$python, $script, '--info', '--test-port', $port, '--baud', (string) $baud];
        $result = $this->commandRunner->run($cmd, 15);
        $fullOutput = trim($result['output'] . ($result['error'] ? "\n" . $result['error'] : ''));

        $parsed = json_decode($fullOutput, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            $isSuccess = (bool) ($parsed['success'] ?? false);
            return [
                'success' => $isSuccess,
                'port' => $port,
                'baud' => $baud,
                'data' => $parsed,
                'message' => $isSuccess
                    ? '¡Datos de hardware extraídos exitosamente desde el puerto serial!'
                    : 'El UPS no respondió a las consultas de identificación en ' . $port . '.',
            ];
        }

        return [
            'success' => false,
            'port' => $port,
            'baud' => $baud,
            'message' => 'Sin respuesta válida de hardware en ' . $port . ' @ ' . $baud . ' bps.',
            'raw_output' => $fullOutput,
        ];
    }

    /**
     * Ejecuta escaneo completo de todos los puertos disponibles buscando el UPS.
     */
    public function scanPorts(): array
    {
        $python = $this->getPythonBinary();
        $script = $this->getUpsScriptPath();

        if (!$script) {
            return [
                'success' => false,
                'message' => 'El script de telemetría de UPS no fue encontrado en el servidor.',
                'raw_output' => '',
            ];
        }

        $cmd = [$python, $script, '--scan'];
        $result = $this->commandRunner->run($cmd, 30);
        $fullOutput = trim($result['output'] . ($result['error'] ? "\n" . $result['error'] : ''));

        $detected = ($result['exit_code'] === 0) && str_contains($fullOutput, 'UPS DETECTADO EXITOSAMENTE');

        return [
            'success' => $detected,
            'message' => $detected
                ? '¡UPS detectado automáticamente durante el escaneo!'
                : 'El escaneo finalizó sin detectar respuesta de ningún UPS en los puertos disponibles.',
            'raw_output' => $fullOutput,
        ];
    }

    /**
     * Calcula la autonomía estimada en minutos para banco de 16 baterías AGM (192Vdc).
     */
    public function calculateEstimatedRuntime(int $batteryPercent, int $loadPercent): int
    {
        $loadPct = max(5, $loadPercent);
        $batPct = max(1, $batteryPercent);

        // Banco típico de 16x 12V 7.2Ah = ~1380 Wh utilizables
        // A carga del 8% (~480W), tiempo estimado ~80-100 min al 100% de batería.
        $baseRuntimeAtFullBat = 650.0 / $loadPct;
        $estMinutes = round(($batPct / 100.0) * $baseRuntimeAtFullBat);

        return (int) max(1, min(600, $estMinutes));
    }

    /**
     * Formatea minutos a cadena legible.
     */
    public function formatRuntime(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} min";
        }
        $h = floor($minutes / 60);
        $m = $minutes % 60;
        return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
    }

    /**
     * Obtiene el binario de Python a utilizar.
     */
    public function getPythonBinary(): string
    {
        if (is_executable('/scripts/telegram-admin-bot/venv/bin/python')) {
            return '/scripts/telegram-admin-bot/venv/bin/python';
        }

        if (function_exists('base_path') && is_executable(base_path('venv/bin/python'))) {
            return base_path('venv/bin/python');
        }

        return '/usr/bin/python3';
    }

    /**
     * Obtiene la ruta al script de telemetría de UPS.
     */
    public function getUpsScriptPath(): ?string
    {
        if (file_exists('/scripts/telegram-admin-bot/monitor/ups_service.py')) {
            return '/scripts/telegram-admin-bot/monitor/ups_service.py';
        }

        if (function_exists('base_path') && file_exists(base_path('monitor/ups_service.py'))) {
            return base_path('monitor/ups_service.py');
        }

        return null;
    }
}
