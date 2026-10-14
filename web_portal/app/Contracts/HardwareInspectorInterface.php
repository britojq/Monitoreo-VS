<?php

namespace App\Contracts;

/**
 * Contrato para la inspección y detección de hardware físico en el sistema operativo.
 * Cumple con el Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
 */
interface HardwareInspectorInterface
{
    /**
     * Retorna la lista estructurada de puertos seriales físicos y adaptadores USB detectados.
     *
     * @return array Lista de arrays con claves ['path', 'type', 'label']
     */
    public function detectSerialPorts(): array;

    /**
     * Verifica si un puerto serial o dispositivo de hardware existe y está accesible.
     *
     * @param string $devicePath Ruta física (ej: /dev/ttyUSB0, /dev/ttyS0)
     * @return bool
     */
    public function isPortAvailable(string $devicePath): bool;
}
