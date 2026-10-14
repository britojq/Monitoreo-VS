<?php

namespace App\Contracts;

/**
 * Contrato para el despacho y sondeo remoto de agentes GLPI (puerto 62354).
 * Principio I (Interface Segregation) y D (Dependency Inversion).
 */
interface GlpiAgentTriggerInterface
{
    /**
     * Consulta el estado del agente GLPI remoto en el puerto 62354.
     *
     * @return array{online: bool, status: string, message: string, raw?: mixed}
     */
    public function checkAgentStatus(string $ip): array;

    /**
     * Envía una señal al agente GLPI remoto para que ejecute inmediatamente
     * la recolección de inventario y reporte al servidor (GET http://<ip>:62354/now).
     *
     * @return array{success: bool, message: string, status_code?: int}
     */
    public function triggerImmediateInventory(string $ip): array;
}
