<?php

declare(strict_types=1);

namespace App\Services\Mcp;

/**
 * Catálogo canónico de esquemas JSON de herramientas para el Asistente Corporativo (Servidor MCP).
 */
class CorporateAiToolCatalog
{
    /**
     * Retorna la lista formal de herramientas disponibles para invocación.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getSchemas(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_inventory_summary',
                    'description' => 'Obtiene el resumen consolidado y conteo exacto de activos en inventario ITAM por tipo y sede.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'site_name' => [
                                'type' => 'string',
                                'description' => 'Nombre opcional de la sede para filtrar (ej: "Valle Seco", "Catatumbo", "Guanta").',
                            ],
                            'category' => [
                                'type' => 'string',
                                'description' => 'Categoría opcional (ej: "computacion", "redes", "servidores", "impresoras").',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_itam_asset',
                    'description' => 'Busca un equipo o activo ITAM específico por IP, número de serie, nombre de equipo o responsable.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Término de búsqueda: dirección IP (ej: "192.168.1.15"), serial (ej: "2UA9361LKS"), nombre de equipo o nombre del funcionario responsable.',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_ups_telemetry',
                    'description' => 'Obtiene la telemetría eléctrica en tiempo real del UPS principal: voltajes de entrada/salida, carga, batería y estado de línea.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'device_id' => [
                                'type' => 'integer',
                                'description' => 'ID opcional del UPS. Si se omite, retorna el UPS principal de la Sede Valle Seco.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_noc_site_status',
                    'description' => 'Obtiene el estado de conectividad, latencia y disponibilidad de las sedes monitoreadas en la WAN.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'site_id' => [
                                'type' => 'integer',
                                'description' => 'ID de la sede a consultar. Si se omite, retorna el resumen de todas las sedes.',
                            ],
                            'status_filter' => [
                                'type' => 'string',
                                'description' => 'Filtro por estado: "ONLINE", "OFFLINE", "DEGRADED".',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_proxy_status',
                    'description' => 'Obtiene el estado operacional, versión y disponibilidad de los servidores proxy corporativos.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'proxy_id' => [
                                'type' => 'integer',
                                'description' => 'ID opcional del proxy. Si se omite, retorna todos los proxies registrados.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
