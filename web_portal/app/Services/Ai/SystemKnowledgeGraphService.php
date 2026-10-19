<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\SystemKnowledgeGraphInterface;
use App\Models\Alert;
use App\Models\DiscoverySubnet;
use App\Models\InventoryAsset;
use App\Models\MaintenanceTicket;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\NetworkTopologyLink;
use App\Models\SslCertificate;
use App\Models\UpsDevice;
use App\Models\UpsPowerEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Motor del Grafo de Conocimiento del Sistema (Graph RAG) en Laravel/PHP.
 * Mapea 100% las entidades y relaciones de MariaDB (SSOT) para eliminar ambigüedades.
 */
class SystemKnowledgeGraphService implements SystemKnowledgeGraphInterface
{
    protected const CACHE_KEY = 'system_knowledge_graph_data';
    protected const CACHE_TTL_SECONDS = 30;

    /**
     * Construye o refresca el grafo de infraestructura desde MariaDB (SSOT).
     */
    public function buildGraph(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && Cache::has(self::CACHE_KEY)) {
            return Cache::get(self::CACHE_KEY);
        }

        $nodes = [];
        $edges = [];

        // 1. SEDES (monitored_sites)
        $sites = MonitoredSite::all();
        foreach ($sites as $site) {
            $nodeId = "site_{$site->id}";
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'site',
                'label' => $site->name,
                'entity_id' => $site->id,
                'letter' => $site->letter,
                'ip' => $site->ip,
                'address' => $site->address,
                'is_active' => (bool) $site->is_active,
            ];
        }

        // 2. DISPOSITIVOS DE RED (monitored_network_devices)
        $devices = MonitoredNetworkDevice::all();
        foreach ($devices as $dev) {
            $nodeId = "device_{$dev->id}";
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'network_device',
                'label' => $dev->name ?: "Equipo {$dev->device_number}",
                'entity_id' => $dev->id,
                'ip' => $dev->ip ?: '',
                'mac' => $dev->mac ?: '',
                'model' => $dev->model ?: '',
                'access_type' => $dev->access_type ?: '',
                'access_port' => $dev->access_port,
                'is_active' => (bool) $dev->is_active,
                'notes' => $dev->notes ?: '',
            ];

            if ($dev->monitored_site_id && isset($nodes["site_{$dev->monitored_site_id}"])) {
                $edges[] = [
                    'source' => $nodeId,
                    'target' => "site_{$dev->monitored_site_id}",
                    'relation' => 'LOCATED_AT',
                ];
            }
        }

        // 3. UPS Y EVENTOS DE ENERGÍA
        $upsDevices = UpsDevice::all();
        foreach ($upsDevices as $ups) {
            $nodeId = "ups_{$ups->id}";
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'ups',
                'label' => $ups->name ?: 'UPS Central Valle Seco',
                'entity_id' => $ups->id,
                'model' => $ups->model ?: 'ZTG LV6KL 6kVA',
                'serial_number' => $ups->serial_number ?: 'LV6KL-VS-01',
                'input_voltage' => (float) ($ups->input_voltage ?? 0.0),
                'output_voltage' => (float) ($ups->output_voltage ?? 0.0),
                'load_percent' => (float) ($ups->load_percent ?? 0.0),
                'battery_percent' => (float) ($ups->battery_percent ?? 100.0),
                'battery_voltage' => (float) ($ups->battery_voltage ?? 0.0),
                'temperature_c' => (float) ($ups->temperature_c ?? 0.0),
                'is_online' => (bool) $ups->is_online,
            ];

            // Conectar UPS con Sede Principal
            if (isset($nodes['site_1'])) {
                $edges[] = [
                    'source' => $nodeId,
                    'target' => 'site_1',
                    'relation' => 'LOCATED_AT',
                ];
            }

            // Conectar UPS suministrando energía a dispositivos de Sede 1
            foreach ($devices as $dev) {
                if ($dev->monitored_site_id === 1) {
                    $edges[] = [
                        'source' => $nodeId,
                        'target' => "device_{$dev->id}",
                        'relation' => 'POWERS',
                    ];
                }
            }

            // Histórico de Eventos de Energía (ups_power_events)
            $eventsSummary = $this->getUpsPowerEventsSummary($ups->id);
            $eventNodeId = "power_events_ups_{$ups->id}";
            $nodes[$eventNodeId] = [
                'id' => $eventNodeId,
                'type' => 'power_event',
                'label' => "Histórico de Averías y Eventos Eléctricos (UPS {$ups->id})",
                'entity_id' => $ups->id,
                'total' => $eventsSummary['total'],
                'by_type' => $eventsSummary['by_type'],
                'recent' => $eventsSummary['recent'],
            ];

            $edges[] = [
                'source' => $nodeId,
                'target' => $eventNodeId,
                'relation' => 'RECORDS_EVENT',
            ];
        }

        // 4. INVENTARIO ITAM (inventory_assets)
        $assets = InventoryAsset::all();
        foreach ($assets as $asset) {
            $nodeId = "asset_{$asset->id}";
            $serial = trim((string) ($asset->serial_number ?? ''));
            $hasSerial = !empty($serial) && !in_array(strtoupper($serial), ['S/N', 'SIN SERIAL', 'N/A', 'PENDIENTE', 'NONE', '0']);

            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'inventory_asset',
                'label' => $asset->name ?: ($asset->asset_tag ?: "Activo {$asset->id}"),
                'entity_id' => $asset->id,
                'asset_tag' => $asset->asset_tag ?: '',
                'asset_type' => $asset->type ?: 'desktop',
                'brand' => $asset->brand ?: '',
                'model' => $asset->model ?: '',
                'serial_number' => $hasSerial ? $serial : null,
                'has_serial' => $hasSerial,
                'ip_address' => $asset->ip_address ?: '',
                'custodian_name' => $asset->custodian_name ?: '',
                'custodian_department' => $asset->custodian_department ?: '',
                'is_active' => (bool) $asset->is_active,
            ];

            if ($asset->monitored_site_id && isset($nodes["site_{$asset->monitored_site_id}"])) {
                $edges[] = [
                    'source' => $nodeId,
                    'target' => "site_{$asset->monitored_site_id}",
                    'relation' => 'LOCATED_AT',
                ];
            }

            if ($asset->monitored_network_device_id && isset($nodes["device_{$asset->monitored_network_device_id}"])) {
                $edges[] = [
                    'source' => "device_{$asset->monitored_network_device_id}",
                    'target' => $nodeId,
                    'relation' => 'HAS_ASSET',
                ];
            }
        }

        // 5. ENLACES DE TOPOLOGÍA (network_topology_links)
        $links = NetworkTopologyLink::all();
        foreach ($links as $link) {
            $sId = "device_{$link->source_device_id}";
            $tId = "device_{$link->target_device_id}";
            if (isset($nodes[$sId]) && isset($nodes[$tId])) {
                $edges[] = [
                    'source' => $sId,
                    'target' => $tId,
                    'relation' => 'CONNECTED_TO',
                    'link_type' => $link->link_type ?? 'ethernet',
                ];
            }
        }

        // 6. TICKETS DE MANTENIMIENTO TÉCNICO ITAM (maintenance_tickets)
        $tickets = MaintenanceTicket::with('asset')->orderBy('id', 'desc')->limit(200)->get();
        foreach ($tickets as $t) {
            $nodeId = "ticket_{$t->id}";
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'ticket',
                'label' => "Ticket {$t->ticket_code} ({$t->title})",
                'entity_id' => $t->id,
                'ticket_code' => $t->ticket_code,
                'title' => $t->title,
                'ticket_type' => $t->type,
                'status' => $t->status,
                'priority' => $t->priority,
                'cost' => (float) $t->cost,
                'asset_id' => $t->inventory_asset_id,
            ];

            if ($t->inventory_asset_id && isset($nodes["asset_{$t->inventory_asset_id}"])) {
                $edges[] = [
                    'source' => "asset_{$t->inventory_asset_id}",
                    'target' => $nodeId,
                    'relation' => 'HAS_TICKET',
                ];
            }
        }

        // 7. SUBREDES Y GESTIÓN IPAM (discovery_subnets)
        $subnets = DiscoverySubnet::all();
        foreach ($subnets as $sub) {
            $nodeId = "subnet_{$sub->id}";
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'subnet',
                'label' => "Subred {$sub->subnet}",
                'entity_id' => $sub->id,
                'subnet' => $sub->subnet,
                'scan_method' => $sub->scan_method,
                'is_active' => (bool) $sub->is_active,
            ];

            if ($sub->site_id && isset($nodes["site_{$sub->site_id}"])) {
                $edges[] = [
                    'source' => $nodeId,
                    'target' => "site_{$sub->site_id}",
                    'relation' => 'LOCATED_AT',
                ];
            }
        }

        // 8. CERTIFICADOS SSL (ssl_certificates)
        $certs = SslCertificate::all();
        foreach ($certs as $c) {
            $nodeId = "ssl_{$c->id}";
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'ssl_certificate',
                'label' => "Certificado {$c->domain}",
                'entity_id' => $c->id,
                'domain' => $c->domain,
                'port' => $c->port,
                'issuer_cn' => $c->issuer_cn,
                'days_remaining' => (int) $c->days_remaining,
                'status' => $c->last_check_status,
                'is_active' => (bool) $c->is_active,
            ];
        }

        // 9. SERVICIOS Y PROXIES (monitored_services, monitored_proxies)
        $services = MonitoredService::all();
        foreach ($services as $s) {
            $nodeId = "service_{$s->id}";
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'service',
                'label' => $s->name,
                'entity_id' => $s->id,
                'letter' => $s->letter,
                'host_ip' => $s->host_ip,
                'port' => $s->port,
                'is_active' => (bool) $s->is_active,
            ];
        }

        $proxies = MonitoredProxy::all();
        foreach ($proxies as $p) {
            $nodeId = "proxy_{$p->id}";
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'proxy',
                'label' => $p->name,
                'entity_id' => $p->id,
                'letter' => $p->letter,
                'ip_port' => $p->ip_port,
                'is_active' => (bool) $p->is_active,
            ];
        }

        $graphData = [
            'nodes' => $nodes,
            'edges' => $edges,
            'node_count' => count($nodes),
            'edge_count' => count($edges),
            'built_at' => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY, $graphData, self::CACHE_TTL_SECONDS);

        return $graphData;
    }

    /**
     * Resuelve el contexto relacional libre de ambigüedades para la consulta.
     */
    public function resolveQueryContext(string $query, ?string $activeNodeId = null): array
    {
        $graph = $this->buildGraph();
        $nodes = $graph['nodes'];
        $q = mb_strtolower(trim($query));

        // 1. Identificar si la consulta es sobre UPS / Respaldo Eléctrico
        $upsKeywords = ['ups', 'bateria', 'batería', 'averia', 'avería', 'corte', 'fluctuacion', 'fluctuación', 'voltaje', 'respaldo electrico', 'respaldo eléctrico', 'fallas del ups', 'fallas de ups'];
        $isUps = Str::contains($q, $upsKeywords);

        if ($isUps) {
            // Buscar nodo UPS
            $upsNode = null;
            foreach ($nodes as $n) {
                if ($n['type'] === 'ups') {
                    $upsNode = $n;
                    break;
                }
            }

            if ($upsNode) {
                $eventsSummary = $this->getUpsPowerEventsSummary($upsNode['entity_id']);
                $totalEvents = $eventsSummary['total'];
                $byType = $eventsSummary['by_type'];
                $recent = $eventsSummary['recent'];

                $lines = [
                    "[CONTEXTO_DEL_SISTEMA - DOMINIO DE ENERGÍA Y RESPALDO ELÉCTRICO (KNOWLEDGE GRAPH)]",
                    "⚡ IDENTIDAD DEL EQUIPO: {$upsNode['label']}",
                    "  • Tipo de Entidad: Sistema de Alimentación Ininterrumpida (UPS)",
                    "  • Modelo Oficial: {$upsNode['model']}",
                    "  • Serial de Chasis / Fábrica: {$upsNode['serial_number']}",
                    "  • Ubicación Física: Sede Principal Valle Seco (Cuarto de Telecomunicaciones)",
                    "  • Estado Operativo: " . ($upsNode['is_online'] ? 'EN LÍNEA (Normal)' : 'Bypass / Modo Red Comercial'),
                    "  • Nivel de Batería: {$upsNode['battery_percent']}%",
                    "  • Voltaje de Entrada: {$upsNode['input_voltage']} VAC | Voltaje de Salida: {$upsNode['output_voltage']} VAC",
                    "  • Carga Suministrada: {$upsNode['load_percent']}%",
                    "",
                    "📊 HISTORIAL DE AVERÍAS Y EVENTOS ELÉCTRICOS DETECTADOS POR EL UPS:",
                    "  • Total de Eventos Registrados en Histórico: {$totalEvents} incidencias registradas en MariaDB (SSOT).",
                ];

                if (!empty($byType)) {
                    $breakdownParts = [];
                    foreach ($byType as $type => $count) {
                        $breakdownParts[] = "{$count} de tipo '{$type}'";
                    }
                    $lines[] = "  • Desglose por Tipo de Avería: " . implode(', ', $breakdownParts) . ".";
                }

                if (!empty($recent)) {
                    $lines[] = "  • Muestra de Últimos Eventos Registrados en el Histórico:";
                    foreach (array_slice($recent, 0, 5) as $idx => $ev) {
                        $num = $idx + 1;
                        $lines[] = "    {$num}. Tipo: {$ev['event_type']} | Inicio: {$ev['started_at']} | Duración: {$ev['duration_seconds']}s | Voltaje In: {$ev['input_voltage']}V | Detalle: {$ev['description']}";
                    }
                } else {
                    $lines[] = "  • Actualmente no se registran eventos de corte o fluctuación pendientes.";
                }

                return [
                    'context' => implode("\n", $lines),
                    'active_node_id' => $upsNode['id'],
                ];
            }
        }

        // 2. Identificar si la consulta es de Switch específico (ej. SW03)
        if (preg_match('/\bsw(?:itch)?[-_\s]?0?(\d+)\b/i', $q, $matches)) {
            $num = ltrim($matches[1], '0') ?: '0';
            foreach ($nodes as $n) {
                if ($n['type'] === 'network_device') {
                    $lbl = mb_strtolower($n['label']);
                    if (str_contains($lbl, "sw{$num}") || str_contains($lbl, "sw0{$num}") || str_contains($lbl, "switch {$num}")) {
                        $lines = [
                            "[CONTEXTO_DEL_SISTEMA - FICHA TOPOLÓGICA DE {$n['label']} (KNOWLEDGE GRAPH)]",
                            "🏷️ Nombre Oficial: {$n['label']}",
                            "  • Tipo de Entidad: Dispositivo de Red (Switch)",
                            "  • Dirección IP: {$n['ip']}",
                            "  • Dirección MAC: {$n['mac']}",
                            "  • Modelo: {$n['model']}",
                            "  • Estado: " . ($n['is_active'] ? 'Operativo / En línea' : 'Inactivo'),
                            "  • Notas Técnicas: {$n['notes']}",
                        ];
                        return [
                            'context' => implode("\n", $lines),
                            'active_node_id' => $n['id'],
                        ];
                    }
                }
            }
        }

        // 3. Consultas cuantitativas de Inventario y Seriales
        $inventoryKeywords = ['serial', 'seriales', 'inventario', 'censo', 'cuantos equipos', 'cuántos equipos'];
        if (Str::contains($q, $inventoryKeywords)) {
            $totalAssets = 0;
            $withSerial = [];
            foreach ($nodes as $n) {
                if ($n['type'] === 'inventory_asset') {
                    $totalAssets++;
                    if (!empty($n['has_serial'])) {
                        $withSerial[] = $n;
                    }
                }
            }

            $countSerial = count($withSerial);
            $lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE INVENTARIO Y ACTIVOS ITAM (KNOWLEDGE GRAPH)]",
                "📊 RESUMEN CUANTITATIVO DE EQUIPOS Y SERIALES EN MARIADB (SSOT):",
                "  • Total de Equipos / Activos Físicos Registrados: {$totalAssets}",
                "  • Equipos con Serial de Chasis / CPU Asociado: {$countSerial} de {$totalAssets}",
            ];

            if ($countSerial === 0) {
                $lines[] = "  • Estado fáctico: Actualmente NO existe ningún equipo con serial de chasis o CPU asociado/registrado en el inventario del sistema (0 de 24 equipos).";
                $lines[] = "  • Observación técnica: Todos los equipos registrados en el inventario figuran actualmente sin serial cargado o pendientes por catalogación física.";
            } else {
                $lines[] = "  • Muestra de Equipos con Serial Registrado en el Sistema:";
                foreach (array_slice($withSerial, 0, 10) as $item) {
                    $lines[] = "    - {$item['label']} ({$item['asset_type']} {$item['brand']} {$item['model']}): Serial {$item['serial_number']}";
                }
            }

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => null,
            ];
        }

        // 4. Consultas de Tickets de Mantenimiento ITAM
        $ticketKeywords = [
            'ticket', 'tickets', 'mantenimiento', 'mantenimientos', 'reparacion', 'reparación',
            'reparaciones', 'orden de trabajo', 'ordenes de trabajo', 'órdenes de trabajo',
            'en reparacion', 'en reparación', 'en soporte', 'en mantenimiento', 'preventivo', 'correctivo'
        ];
        if (Str::contains($q, $ticketKeywords)) {
            $tickets = [];
            foreach ($nodes as $n) {
                if ($n['type'] === 'ticket') {
                    $tickets[] = $n;
                }
            }
            $totalTickets = count($tickets);

            $lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE TICKETS Y MANTENIMIENTO TÉCNICO ITAM (KNOWLEDGE GRAPH)]",
                "📊 ESTADO GENERAL DE TICKETS DE MANTENIMIENTO EN MARIADB (SSOT):",
                "  • Total de Tickets Registrados: {$totalTickets}",
            ];

            if ($totalTickets === 0) {
                $lines[] = "  • Estado fáctico: Actualmente NO existen tickets de soporte o mantenimiento registrados en el sistema.";
                $lines[] = "  • Observación técnica: Todos los equipos de la infraestructura se encuentran operativos y al día sin órdenes de trabajo pendientes ni mantenimientos preventivos/correctivos en curso.";
            } else {
                $byStatus = [];
                $byPriority = [];
                foreach ($tickets as $tk) {
                    $st = $tk['status'] ?? 'scheduled';
                    $pr = $tk['priority'] ?? 'medium';
                    $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
                    $byPriority[$pr] = ($byPriority[$pr] ?? 0) + 1;
                }

                $statusParts = [];
                foreach ($byStatus as $st => $cnt) {
                    $statusParts[] = "{$cnt} en '{$st}'";
                }
                $lines[] = "  • Desglose por Estado: " . implode(', ', $statusParts) . ".";

                $priorityParts = [];
                foreach ($byPriority as $pr => $cnt) {
                    $priorityParts[] = "{$cnt} prioridad '{$pr}'";
                }
                $lines[] = "  • Desglose por Prioridad: " . implode(', ', $priorityParts) . ".";

                $lines[] = "  • Muestra de Tickets Registrados en el Sistema:";
                foreach (array_slice($tickets, 0, 5) as $tk) {
                    $costFmt = number_format((float) ($tk['cost'] ?? 0), 2);
                    $lines[] = "    - Código: {$tk['ticket_code']} | Título: {$tk['title']} | Tipo: {$tk['ticket_type']} | Estado: {$tk['status']} | Prioridad: {$tk['priority']} | Costo: \${$costFmt}";
                }
            }

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => !empty($tickets) ? $tickets[0]['id'] : null,
            ];
        }

        // 5. Consultas de Alertas e Incidentes del NOC
        $alertKeywords = [
            'alerta', 'alertas', 'incidente', 'incidentes', 'falla noc', 'fallas noc',
            'alertas activas', 'alertas criticas', 'alertas críticas', 'averias del noc',
            'averías del noc', 'eventos noc', 'disparada', 'disparadas'
        ];
        if (Str::contains($q, $alertKeywords)) {
            $totalAlerts = Alert::count();
            $activeAlerts = Alert::whereIn('status', ['firing', 'active', 'acknowledged'])
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get();

            $firingCount = Alert::where('status', 'firing')->count();
            $ackCount = Alert::where('status', 'acknowledged')->count();
            $criticalCount = Alert::whereIn('status', ['firing', 'active', 'acknowledged'])->where('severity', 'critical')->count();
            $warningCount = Alert::whereIn('status', ['firing', 'active', 'acknowledged'])->where('severity', 'warning')->count();

            $lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE ALERTAS E INCIDENTES NOC (KNOWLEDGE GRAPH)]",
                "🚨 SUPERVISIÓN DE ALERTAS E INCIDENTES DEL NOC EN MARIADB (SSOT):",
                "  • Total de Alertas en Histórico: {$totalAlerts}",
                "  • Alertas Activas en Curso: " . $activeAlerts->count() . " ({$firingCount} disparadas en vivo, {$ackCount} reconocidas)",
                "  • Desglose por Severidad Activa: {$criticalCount} críticas, {$warningCount} advertencias",
            ];

            if ($activeAlerts->isEmpty()) {
                $lines[] = "  • Estado fáctico: Actualmente NO existen alertas activas ni incidentes abiertos en el NOC. Toda la infraestructura monitoreada se encuentra estable y operativa.";
            } else {
                $lines[] = "  • Listado de Alertas Activas en el Centro de Operaciones:";
                foreach ($activeAlerts->slice(0, 5) as $idx => $al) {
                    $num = $idx + 1;
                    $sev = Str::upper($al->severity ?: 'info');
                    $lines[] = "    {$num}. [{$sev}] {$al->entity_name} ({$al->entity_type}): {$al->message} | Estado: {$al->status} | Disparada: {$al->fired_at}";
                }
            }

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => null,
            ];
        }

        // 6. Consultas de Subredes y Gestión IPAM
        $subnetKeywords = [
            'subred', 'subredes', 'ipam', 'bloque ip', 'bloques ip', 'segmento',
            'segmentos', 'segmentos de red', 'rango ip', 'rangos ip', 'redes configuradas', 'plan de ip'
        ];
        if (Str::contains($q, $subnetKeywords)) {
            $subnets = [];
            foreach ($nodes as $n) {
                if ($n['type'] === 'subnet') {
                    $subnets[] = $n;
                }
            }
            $totalSubnets = count($subnets);

            // Segmentos operativos por sede
            $opSegments = \Illuminate\Support\Facades\DB::table('monitored_sites as s')
                ->join('monitored_network_devices as d', 'd.monitored_site_id', '=', 's.id')
                ->whereNotNull('d.ip')
                ->where('d.ip', '!=', '')
                ->selectRaw("s.name as site_name, s.ip as site_gateway, CONCAT(SUBSTRING_INDEX(d.ip, '.', 3), '.0/24') as subnet_calc, count(d.id) as device_count")
                ->groupBy('s.id', 's.name', 's.ip', 'subnet_calc')
                ->orderBy('s.id')
                ->get();

            $lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE SUBREDES Y PLAN IP / IPAM (KNOWLEDGE GRAPH)]",
                "🌐 PLAN DE SUBREDES Y DIRECCIONAMIENTO IP EN MARIADB (SSOT):",
            ];

            if ($opSegments->isNotEmpty()) {
                $lines[] = "📍 1. Segmentos de Red Operativos por Sede (Infraestructura Real):";
                foreach ($opSegments as $idx => $seg) {
                    $num = $idx + 1;
                    $lines[] = "    {$num}. Segmento: {$seg->subnet_calc} | Sede: {$seg->site_name} (Enlace: {$seg->site_gateway}) | Equipos Asociados: {$seg->device_count}";
                }
            }

            $lines[] = "\n🔍 2. Subredes Registradas en Motor de Descubrimiento (Discovery Scanner):";
            $lines[] = "  • Total de Bloques de Escaneo: {$totalSubnets}";
            if ($totalSubnets === 0) {
                $lines[] = "  • Actualmente no hay subredes de descubrimiento configuradas en el sistema.";
            } else {
                foreach ($subnets as $idx => $sn) {
                    $st = !empty($sn['is_active']) ? 'Activa' : 'Inactiva';
                    $lines[] = "    - Subred: {$sn['subnet']} | Método de Escaneo: {$sn['scan_method']} | Estado: {$st}";
                }
            }

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => !empty($subnets) ? $subnets[0]['id'] : null,
            ];
        }

        // 7. Consultas de Certificados SSL / TLS
        $sslKeywords = [
            'ssl', 'tls', 'certificado', 'certificados', 'vencimiento ssl', 'vencimiento de certificado',
            'caducidad', 'por vencer', 'por caducar', 'https'
        ];
        if (Str::contains($q, $sslKeywords)) {
            $certs = [];
            foreach ($nodes as $n) {
                if ($n['type'] === 'ssl_certificate') {
                    $certs[] = $n;
                }
            }
            $totalCerts = count($certs);

            $lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE CERTIFICADOS SSL / TLS (KNOWLEDGE GRAPH)]",
                "🔒 SUPERVISIÓN DE CERTIFICADOS SSL / TLS EN MARIADB (SSOT):",
                "  • Total de Certificados Monitoreados: {$totalCerts}",
            ];

            if ($totalCerts === 0) {
                $lines[] = "  • Actualmente no hay certificados SSL registrados en el sistema.";
            } else {
                $lines[] = "  • Listado de Certificados y Días de Vigencia Restante:";
                foreach ($certs as $idx => $c) {
                    $num = $idx + 1;
                    $days = (int) ($c['days_remaining'] ?? 0);
                    $statusLbl = $days > 30 ? 'Válido' : ($days > 0 ? 'Por Vencer' : 'Expirado / Error');
                    $issuer = $c['issuer_cn'] ?: 'Desconocido';
                    $lines[] = "    {$num}. Dominio: {$c['domain']}:{$c['port']} | Emisor: {$issuer} | Días Restantes: {$days} días | Estado: {$statusLbl}";
                }
            }

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => !empty($certs) ? $certs[0]['id'] : null,
            ];
        }

        // 8. Consultas de Servicios Corporativos, Proxies y Estado NOC
        $serviceKeywords = [
            'servicio', 'servicios', 'servicio corporativo', 'servicios corporativos',
            'servicios caidos', 'servicios caídos', 'servicios activos', 'servicios apagados',
            'cuantos servicios', 'cuántos servicios', 'que servicios', 'qué servicios',
            'caido', 'caído', 'caidos', 'caídos', 'caida', 'caída', 'caidas', 'caídas',
            'otrs', 'intranet', 'factura digital', 'claves', 'sao', 'surge',
            'te equivocaste', 'estas equivocada', 'estás equivocada', 'mentira',
            'reporte de servicios', '/servicios', 'fallas activas', 'servicios en linea', 'servicios en línea'
        ];
        if (Str::contains($q, $serviceKeywords)) {
            $snapshot = \App\Models\MonitoringSnapshot::latest('id')->first();
            $payload = $snapshot?->payload_json ?? [];

            $servicesList = $payload['services'] ?? [];
            $proxiesList = $payload['proxies'] ?? [];
            $sitesList = $payload['sites'] ?? [];

            if (empty($servicesList)) {
                $dbServices = \App\Models\MonitoredService::all();
                foreach ($dbServices as $s) {
                    $servicesList[] = [
                        'id' => $s->id,
                        'letter' => $s->letter ?? '?',
                        'name' => $s->name,
                        'is_up' => (bool) $s->is_active,
                        'type' => $s->type ?? 'Servicio',
                    ];
                }
            }

            $totalServices = count($servicesList);
            $downServices = array_values(array_filter($servicesList, fn($s) => empty($s['is_up'])));
            $upServices = array_values(array_filter($servicesList, fn($s) => !empty($s['is_up'])));
            $downSites = array_values(array_filter($sitesList, fn($st) => empty($st['is_up'])));

            $snapshotDt = $snapshot?->created_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s');

            $lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE SERVICIOS CORPORATIVOS Y ESTADO NOC (KNOWLEDGE GRAPH)]",
                "🕒 Telemetría Oficial Consolidada (Snapshot MariaDB): {$snapshotDt}",
                "📊 RESUMEN DE SERVICIOS: {$totalServices} total | " . count($upServices) . " Operativos / En Línea | " . count($downServices) . " CAÍDOS / FUERA DE LÍNEA.",
            ];

            if (!empty($downServices)) {
                $lines[] = "\n❌ SERVICIOS CAÍDOS DETECTADOS (" . count($downServices) . " fuera de línea):";
                foreach ($downServices as $s) {
                    $letter = $s['letter'] ?? '?';
                    $type = $s['type'] ?? 'Servicio';
                    $lines[] = "  • [{$letter}] {$s['name']} | Estado: ❌ CAÍDO / APAGADO | Tipo: {$type}";
                }
            } else {
                $lines[] = "\n✅ Estado: Todos los servicios corporativos se encuentran 100% operativos y en línea.";
            }

            if (!empty($downSites)) {
                $lines[] = "\n🏢 SEDES O ENLACES CAÍDOS (" . count($downSites) . " detectados):";
                foreach ($downSites as $st) {
                    $ip = $st['ip'] ?? '';
                    $lines[] = "  • {$st['name']} (IP: {$ip}) | Estado: ❌ FUERA DE LÍNEA / INACCESIBLE";
                }
            }

            if (!empty($upServices)) {
                $lines[] = "\n✅ SERVICIOS EN LÍNEA (" . count($upServices) . " operativos):";
                foreach ($upServices as $s) {
                    $letter = $s['letter'] ?? '?';
                    $lines[] = "  • [{$letter}] {$s['name']} | Estado: ✅ ACTIVO";
                }
            }

            if (!empty($proxiesList)) {
                $lines[] = "\n🌐 PROXIES DE NAVEGACIÓN (" . count($proxiesList) . " monitoreados):";
                foreach ($proxiesList as $p) {
                    $st = !empty($p['is_up']) ? '✅ ACTIVO' : '❌ CAÍDO';
                    $letter = $p['letter'] ?? '?';
                    $ipPort = $p['ip_port'] ?? '';
                    $lines[] = "  • [{$letter}] {$p['name']} ({$ipPort}) | {$st}";
                }
            }

            $downNames = implode(', ', array_map(fn($s) => $s['name'], $downServices));
            $lines[] = "\n📌 INSTRUCCIÓN OBLIGATORIA: Responde directamente indicando que hay exactamente " . count($downServices) . " servicios caídos ({$downNames}) y " . count($upServices) . " servicios operativos de un total de {$totalServices}. Bajo ninguna circunstancia niegues las caídas ni afirmes que no tienes acceso a la información.";

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => 'services_summary',
            ];
        }

        return [
            'context' => '',
            'active_node_id' => $activeNodeId,
        ];
    }

    /**
     * Retorna el histórico y telemetría de eventos de energía del UPS.
     */
    public function getUpsPowerEventsSummary(?int $upsId = null): array
    {
        $query = UpsPowerEvent::query();
        if ($upsId) {
            $query->where('ups_device_id', $upsId);
        }

        $total = $query->count();
        $byType = UpsPowerEvent::query()
            ->when($upsId, fn($q) => $q->where('ups_device_id', $upsId))
            ->selectRaw('event_type, count(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        $recent = UpsPowerEvent::query()
            ->when($upsId, fn($q) => $q->where('ups_device_id', $upsId))
            ->orderBy('started_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($ev) => [
                'id' => $ev->id,
                'event_type' => $ev->event_type,
                'started_at' => (string) $ev->started_at,
                'ended_at' => (string) $ev->ended_at,
                'duration_seconds' => $ev->duration_seconds,
                'input_voltage' => $ev->input_voltage,
                'description' => $ev->description ?: 'Evento de energía registrado',
            ])
            ->toArray();

        return [
            'total' => $total,
            'by_type' => $byType,
            'recent' => $recent,
        ];
    }
}
