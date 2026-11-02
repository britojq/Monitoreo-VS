<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato formal para el Ejecutor de Herramientas del Servidor MCP.
 * Principio de Responsabilidad Única (SRP) e Inversión de Dependencias (DIP).
 */
interface ToolExecutorInterface
{
    /**
     * Despacha y ejecuta dinámicamente una herramienta por su nombre con sus argumentos sanitizados.
     *
     * @param string $toolName Nombre de la función invocada por la IA.
     * @param array<string, mixed> $arguments Parámetros provistos por el Tool Call.
     * @return array<string, mixed> Payload JSON-serializable de resultado o error controlado.
     */
    public function executeTool(string $toolName, array $arguments = []): array;

    /**
     * Herramienta 1: Obtiene la telemetría eléctrica en tiempo real del UPS ZTG 6kVA.
     *
     * @param string|null $upsId Identificador opcional del equipo (por defecto LV6KL-VS-01).
     * @return array<string, mixed>
     */
    public function getUpsTelemetry(?string $upsId = null): array;

    /**
     * Herramienta 2: Busca un activo o puesto de trabajo en el inventario ITAM por IP, serial, nombre o responsable.
     * Si no se provee término o se solicita consolidado general, devuelve el censo/resumen de inventario.
     *
     * @param string|null $query Término de búsqueda (IP, Serial, Nombre Etiqueta, Responsable).
     * @param string|null $filterType Filtro opcional por tipo de dispositivo ('all', 'desktop', 'switch', etc.).
     * @return array<string, mixed>
     */
    public function searchItamAsset(?string $query = null, ?string $filterType = 'all'): array;

    /**
     * Herramienta 3: Consulta la telemetría operativa y conectividad WAN/LAN de sedes del NOC.
     *
     * @param string|null $siteName Nombre o inicial de la sede. Si es null, retorna todas las sedes.
     * @return array<string, mixed>
     */
    public function getNocSiteStatus(?string $siteName = null): array;

    /**
     * Herramienta 4: Consulta el estado operativo y rendimiento de los proxies pfSense regionales.
     *
     * @param string|null $proxyId Nombre o IP del proxy. Si es null, retorna todos los proxies activos.
     * @return array<string, mixed>
     */
    public function getProxyStatus(?string $proxyId = null): array;

    /**
     * Herramienta 5: Devuelve el censo cuantitativo y resumen consolidado del inventario ITAM.
     * Total de activos, desglose por tipo (workstations, switches, routers, servidores, periféricos),
     * equipos con serial auditado y actas físicas escaneadas directamente desde MariaDB (SSOT).
     *
     * @param string|null $siteName Filtro opcional por sede (ej. 'Valle Seco' o null para consolidado global).
     * @return array<string, mixed>
     */
    public function getInventorySummary(?string $siteName = null): array;
}
