<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Contracts\ToolExecutorInterface;
use App\Models\InventoryAsset;
use App\Models\MonitoredProxy;
use App\Models\MonitoredSite;
use App\Models\UpsDevice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ejecutor de Herramientas del Servidor MCP para el Asistente Técnico ATIT.
 * Principio de Responsabilidad Única (SRP) y Extensión Limpia (OCP).
 *
 * Implementa las 4 herramientas operativas de consulta a MariaDB (SSOT),
 * aplicando sanitización estricta de parámetros y blindaje contra SQLi / excepciones.
 */
class NocToolExecutor implements ToolExecutorInterface
{
    /**
     * Despacha y ejecuta dinámicamente una herramienta por nombre con sus argumentos sanitizados.
     */
    public function executeTool(string $toolName, array $arguments = []): array
    {
        return match ($toolName) {
            'get_inventory_summary' => $this->getInventorySummary(
                isset($arguments['site_name']) ? (string) $arguments['site_name'] : null
            ),
            'get_ups_telemetry' => $this->getUpsTelemetry(
                isset($arguments['ups_id']) ? (string) $arguments['ups_id'] : null
            ),
            'search_itam_asset' => $this->searchItamAsset(
                isset($arguments['query']) ? (string) $arguments['query'] : null,
                isset($arguments['filter_type']) ? (string) $arguments['filter_type'] : 'all'
            ),
            'get_noc_site_status' => $this->getNocSiteStatus(
                isset($arguments['site_name']) ? (string) $arguments['site_name'] : null
            ),
            'get_proxy_status' => $this->getProxyStatus(
                isset($arguments['proxy_id']) ? (string) $arguments['proxy_id'] : null
            ),
            default => [
                'found' => false,
                'error' => "La herramienta solicitada '{$toolName}' no está autorizada o no existe en el catálogo operativo del NOC."
            ]
        };
    }

    /**
     * Herramienta 1: Obtiene la telemetría eléctrica en tiempo real del UPS ZTG 6kVA.
     */
    public function getUpsTelemetry(?string $upsId = null): array
    {
        try {
            $query = UpsDevice::query();

            if (!empty($upsId)) {
                $cleanId = trim($upsId);
                $cleanId = preg_replace('/[^\w\s\.\-]/u', '', $cleanId);
                $query->where(function ($q) use ($cleanId) {
                    $q->where('serial_number', $cleanId)
                      ->orWhere('name', 'like', "%{$cleanId}%")
                      ->orWhere('model', 'like', "%{$cleanId}%");
                });
            }

            $ups = $query->first();

            if (!$ups) {
                return [
                    'found' => false,
                    'error' => "No se encontró ningún UPS con el identificador especificado: '{$upsId}'."
                ];
            }

            $mode = 'LINE_MODE';
            if ($ups->is_on_battery) {
                $mode = 'BATTERY_MODE';
            } elseif ($ups->is_bypass) {
                $mode = 'BYPASS_MODE';
            } elseif ($ups->is_ups_failed) {
                $mode = 'FAULT_MODE';
            }

            $latestEvent = $ups->powerEvents()->orderByDesc('started_at')->first();

            return [
                'found' => true,
                'ups_id' => $ups->serial_number,
                'name' => $ups->name,
                'model' => $ups->model,
                'status' => $ups->is_online ? 'ONLINE' : 'OFFLINE',
                'mode' => $mode,
                'input_voltage_vac' => (float) $ups->input_voltage,
                'output_voltage_vac' => (float) $ups->output_voltage,
                'load_percent' => (int) $ups->load_percent,
                'battery_percent' => (int) $ups->battery_percent,
                'battery_voltage_vdc' => (float) $ups->battery_voltage,
                'frequency_hz' => (float) $ups->frequency,
                'temperature_c' => (float) $ups->temperature_c,
                'is_battery_low' => (bool) $ups->is_battery_low,
                'is_ups_failed' => (bool) $ups->is_ups_failed,
                'beeper_active' => (bool) $ups->beeper_on,
                'last_telemetry_seen' => $ups->last_seen_at ? $ups->last_seen_at->toDateTimeString() : null,
                'latest_power_event' => $latestEvent ? [
                    'event_type' => $latestEvent->event_type,
                    'description' => $latestEvent->description,
                    'started_at' => (string) $latestEvent->started_at,
                ] : null,
            ];
        } catch (\Throwable $e) {
            Log::error('MCP Error en getUpsTelemetry', ['error' => $e->getMessage()]);
            return ['found' => false, 'error' => 'Error al consultar la telemetría del UPS en el centro de operaciones.'];
        }
    }

    /**
     * Herramienta 2: Busca un activo o puesto de trabajo en el inventario ITAM por IP, serial, nombre o responsable.
     * Si no se provee término o se solicita consolidado general, devuelve el censo/resumen de inventario.
     */
    public function searchItamAsset(?string $query = null, ?string $filterType = 'all'): array
    {
        try {
            $cleanQuery = trim((string) ($query ?? ''));
            // Sanitización estricta contra inyección o caracteres maliciosos
            $cleanQuery = preg_replace('/[^\w\s\.\-\/]/u', '', $cleanQuery);
            $cleanQuery = Str::substr($cleanQuery, 0, 100);

            // Si la consulta es vacía o genérica (all, consolidado, general, sede, inventario), delegar a getInventorySummary
            if (empty($cleanQuery) || in_array(Str::lower($cleanQuery), ['all', 'todos', 'consolidado', 'general', 'sede', 'inventario', 'total'], true)) {
                return $this->getInventorySummary();
            }

            $assetsQuery = InventoryAsset::query()
                ->with(['site:id,name', 'activeAssignment'])
                ->search($cleanQuery);

            if (!empty($filterType) && $filterType !== 'all') {
                $assetsQuery->where('type', $filterType);
            }

            $assets = $assetsQuery->take(5)->get();

            if ($assets->isEmpty()) {
                return [
                    'found' => false,
                    'message' => "No se encontró ningún activo en el inventario centralizado para el criterio '{$cleanQuery}'."
                ];
            }

            $results = $assets->map(function (InventoryAsset $asset) {
                $activeAssignment = $asset->activeAssignment;
                return [
                    'asset_tag' => $asset->asset_tag,
                    'nombre_etiqueta' => $asset->name,
                    'marca' => $asset->brand,
                    'modelo' => $asset->model,
                    'serial' => $asset->serial_number ?: 'Sin serial registrado',
                    'ip' => $asset->ip_address ?: 'Sin IP asignada',
                    'mac' => $asset->mac_address ?: 'Sin MAC',
                    'tipo' => $asset->type,
                    'estado' => $asset->status,
                    'sede' => $asset->site?->name ?? 'Infraestructura General',
                    'responsable' => $asset->custodian_name ?: 'No asignado',
                    'departamento' => $asset->custodian_department ?: 'N/D',
                    'sistema_operativo' => $asset->operating_system ?: 'No especificado',
                    'acta_oficial' => $activeAssignment ? [
                        'codigo_acta' => $activeAssignment->assignment_code,
                        'responsable' => $activeAssignment->custodian_name,
                        'tiene_pdf_firmado' => !empty($activeAssignment->document_pdf_path),
                        'ruta_archivo_pdf' => $activeAssignment->document_pdf_path,
                    ] : null,
                ];
            })->values()->toArray();

            return [
                'found' => true,
                'total_matches' => count($results),
                'assets' => $results,
            ];
        } catch (\Throwable $e) {
            Log::error('MCP Error en searchItamAsset', ['error' => $e->getMessage(), 'query' => $query]);
            return ['found' => false, 'error' => 'Error al consultar el inventario de activos en MariaDB.'];
        }
    }

    /**
     * Herramienta 3: Consulta la telemetría operativa y conectividad WAN/LAN de sedes del NOC.
     */
    public function getNocSiteStatus(?string $siteName = null): array
    {
        try {
            $query = MonitoredSite::query()->where('is_active', true);

            if (!empty($siteName)) {
                $cleanSite = trim($siteName);
                $cleanSite = preg_replace('/[^\w\s\.\-]/u', '', $cleanSite);
                $query->where(function ($q) use ($cleanSite) {
                    $q->where('name', 'like', "%{$cleanSite}%")
                      ->orWhere('letter', $cleanSite);
                });
            }

            $sites = $query->with(['histories' => fn($q) => $q->latest('checked_at')->take(1)])->get();

            if ($sites->isEmpty()) {
                return [
                    'found' => false,
                    'error' => "No se encontró ninguna sede activa registrada con el nombre '{$siteName}'."
                ];
            }

            $siteData = $sites->map(function (MonitoredSite $site) {
                $latest = $site->histories->first();
                return [
                    'id' => $site->id,
                    'nombre' => $site->name,
                    'letra_identificadora' => $site->letter,
                    'ip_enlace' => $site->ip,
                    'estado' => ($latest && $latest->is_up) ? 'ONLINE' : 'OFFLINE',
                    'latencia_ms' => $latest ? (float) $latest->latency_ms : null,
                    'perdida_paquetes_pct' => $latest ? (float) $latest->packet_loss_pct : null,
                    'jitter_ms' => $latest ? (float) $latest->jitter_ms : null,
                    'equipos_online' => $latest ? (int) $latest->devices_online : 0,
                    'equipos_totales' => $latest ? (int) $latest->devices_total : 0,
                    'mensaje_estado' => $latest?->status_message ?? 'Sin telemetría reciente',
                    'ultimo_chequeo' => $latest?->checked_at?->toDateTimeString() ?? null,
                ];
            })->values()->toArray();

            $onlineCount = count(array_filter($siteData, fn($s) => $s['estado'] === 'ONLINE'));

            return [
                'found' => true,
                'total_sedes' => count($siteData),
                'sedes_online' => $onlineCount,
                'sedes_offline' => count($siteData) - $onlineCount,
                'sedes' => $siteData,
            ];
        } catch (\Throwable $e) {
            Log::error('MCP Error en getNocSiteStatus', ['error' => $e->getMessage()]);
            return ['found' => false, 'error' => 'Error al consultar el estado de las sedes del NOC.'];
        }
    }

    /**
     * Herramienta 4: Consulta el estado operativo y rendimiento de los proxies pfSense regionales.
     */
    public function getProxyStatus(?string $proxyId = null): array
    {
        try {
            $query = MonitoredProxy::query()->where('is_active', true);

            if (!empty($proxyId)) {
                $cleanProxy = trim($proxyId);
                $cleanProxy = preg_replace('/[^\w\s\.\:\-]/u', '', $cleanProxy);
                $query->where(function ($q) use ($cleanProxy) {
                    $q->where('name', 'like', "%{$cleanProxy}%")
                      ->orWhere('ip_port', 'like', "%{$cleanProxy}%")
                      ->orWhere('letter', $cleanProxy);
                });
            }

            $proxies = $query->with(['histories' => fn($q) => $q->latest('checked_at')->take(1)])->get();

            if ($proxies->isEmpty()) {
                return [
                    'found' => false,
                    'error' => "No se encontró ningún proxy activo con el criterio '{$proxyId}'."
                ];
            }

            $proxyData = $proxies->map(function (MonitoredProxy $proxy) {
                $latest = $proxy->histories->first();
                return [
                    'id' => $proxy->id,
                    'nombre' => $proxy->name,
                    'letra' => $proxy->letter,
                    'ip_puerto' => $proxy->ip_port,
                    'estado' => ($latest && $latest->is_up) ? 'ONLINE' : 'OFFLINE',
                    'latencia_ms' => $latest ? (float) $latest->latency_ms : null,
                    'http_code' => $latest ? (int) $latest->http_code : null,
                    'mensaje' => $latest?->status_message ?? 'Sin verificación reciente',
                    'ultimo_chequeo' => $latest?->checked_at?->toDateTimeString() ?? null,
                ];
            })->values()->toArray();

            $onlineCount = count(array_filter($proxyData, fn($p) => $p['estado'] === 'ONLINE'));

            return [
                'found' => true,
                'total_proxies' => count($proxyData),
                'proxies_online' => $onlineCount,
                'proxies_offline' => count($proxyData) - $onlineCount,
                'proxies' => $proxyData,
            ];
        } catch (\Throwable $e) {
            Log::error('MCP Error en getProxyStatus', ['error' => $e->getMessage()]);
            return ['found' => false, 'error' => 'Error al consultar el estado de los proxies regionales.'];
        }
    }

    /**
     * Herramienta 5: Devuelve el censo cuantitativo y resumen consolidado del inventario ITAM.
     */
    public function getInventorySummary(?string $siteName = null): array
    {
        try {
            $query = InventoryAsset::query();
            if (!empty($siteName) && !in_array(Str::lower(trim($siteName)), ['all', 'todos', 'consolidado'], true)) {
                $cleanSite = trim($siteName);
                $cleanSite = preg_replace('/[^\w\s\.\-]/u', '', $cleanSite);
                $query->whereHas('site', function ($q) use ($cleanSite) {
                    $q->where('name', 'like', "%{$cleanSite}%")
                      ->orWhere('letter', $cleanSite);
                });
            }

            $totalAssets = (clone $query)->count();

            // Desglose exacto por tipo desde MariaDB
            $byType = (clone $query)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            // Total con serial de chasis auditado
            $withSerial = (clone $query)
                ->whereNotNull('serial_number')
                ->where('serial_number', '!=', '')
                ->where('serial_number', 'not like', '%sin serial%')
                ->count();

            // Total con actas físicas firmadas en PDF
            $withActa = (clone $query)
                ->whereHas('activeAssignment', function ($q) {
                    $q->whereNotNull('document_pdf_path')->where('document_pdf_path', '!=', '');
                })
                ->count();

            return [
                'found' => true,
                'sede' => $siteName ?: 'Sede Valle Seco (Consolidado General)',
                'total_equipos' => $totalAssets,
                'desglose_por_tipo' => [
                    'estaciones_trabajo_desktop' => $byType['desktop'] ?? ($byType['workstation'] ?? 0),
                    'switches_de_red' => $byType['switch'] ?? 0,
                    'routers_principales' => $byType['router'] ?? 0,
                    'servidores' => $byType['server'] ?? 0,
                    'impresoras' => $byType['printer'] ?? 0,
                    'telefonia_ip' => $byType['ip_phone'] ?? 0,
                    'otros_perifericos' => $byType['other'] ?? 0,
                ],
                'equipos_con_serial_auditado' => $withSerial,
                'equipos_con_acta_fisica_firmada' => $withActa,
                'nota_auditoria' => "Datos fácticos certificados en tiempo real desde MariaDB (SSOT). Total oficial: {$totalAssets} activos.",
            ];
        } catch (\Throwable $e) {
            Log::error('MCP Error en getInventorySummary', ['error' => $e->getMessage()]);
            return ['found' => false, 'error' => 'Error al calcular el consolidado de inventario en MariaDB.'];
        }
    }
}
