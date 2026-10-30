<?php

namespace App\Services\Hardware;

use App\Contracts\HardwareInspectorInterface;

/**
 * Servicio de infraestructura para inspección de hardware físico en sistemas Linux.
 * Implementa HardwareInspectorInterface aislando llamadas a glob() y acceso a /dev/ del controlador web.
 */
class LinuxHardwareInspector implements HardwareInspectorInterface
{
    /**
     * Retorna la lista estructurada de puertos seriales físicos y adaptadores USB detectados.
     *
     * @return array Lista de arrays con claves ['path', 'type', 'label']
     */
    public function detectSerialPorts(): array
    {
        $ports = [];

        // 1. Adaptadores USB-Serial (mayor prioridad)
        foreach (glob('/dev/ttyUSB*') ?: [] as $p) {
            $ports[] = [
                'path' => $p,
                'type' => 'USB-Serial',
                'label' => "{$p} (Adaptador USB)",
            ];
        }

        // 2. Dispositivos USB CDC-ACM
        foreach (glob('/dev/ttyACM*') ?: [] as $p) {
            $ports[] = [
                'path' => $p,
                'type' => 'CDC-ACM',
                'label' => "{$p} (Dispositivo USB ACM)",
            ];
        }

        // 3. Puertos COM nativos integrados en placa base
        foreach (glob('/dev/ttyS[0-3]') ?: [] as $p) {
            $ports[] = [
                'path' => $p,
                'type' => 'UART Integrado',
                'label' => "{$p} (Puerto COM Físico)",
            ];
        }

        return $ports;
    }

    /**
     * Verifica si un puerto serial o dispositivo de hardware existe y está accesible.
     *
     * @param string $devicePath Ruta física (ej: /dev/ttyUSB0, /dev/ttyS0)
     * @return bool
     */
    public function isPortAvailable(string $devicePath): bool
    {
        return !empty($devicePath) && file_exists($devicePath);
    }
}
