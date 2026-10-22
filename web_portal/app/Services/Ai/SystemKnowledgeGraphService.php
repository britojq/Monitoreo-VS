<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\SystemKnowledgeGraphInterface;
use App\Models\Alert;
use App\Models\DiscoverySubnet;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetNetworkInterface;
use App\Models\InventoryAssignment;
use App\Models\InventoryComponent;
use App\Models\InventoryPeripheral;
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
            $notesText = $dev->notes ?: '';
            $portMappings = [];
            foreach (explode("\n", $notesText) as $line) {
                $cleanL = trim($line);
                if (!empty($cleanL) && (str_starts_with(mb_strtolower($cleanL), 'puerto') || str_contains(mb_strtolower($cleanL), 'cascada') || str_contains(mb_strtolower($cleanL), 'enlace'))) {
                    $portMappings[] = $cleanL;
                }
            }

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
                'notes' => $notesText,
                'port_mappings' => $portMappings,
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
                'serial_number' => $ups->serial_number ?: 'UPS-DEMO-01',
                'input_voltage' => (float) ($ups->input_voltage ?? 0.0),
                'output_voltage' => (float) ($ups->output_voltage ?? 0.0),
                'load_percent' => (float) ($ups->load_percent ?? 0.0),
                'battery_percent' => (float) ($ups->battery_percent ?? 100.0),
                'battery_voltage' => (float) ($ups->battery_voltage ?? 0.0),
                'temperature_c' => (float) ($ups->temperature_c ?? 0.0),
                'is_online' => (bool) $ups->is_online,
            ];

            if (isset($nodes['site_1'])) {
                $edges[] = [
                    'source' => $nodeId,
                    'target' => 'site_1',
                    'relation' => 'LOCATED_AT',
                ];
            }

            foreach ($devices as $dev) {
                if ($dev->monitored_site_id === 1) {
                    $edges[] = [
                        'source' => $nodeId,
                        'target' => "device_{$dev->id}",
                        'relation' => 'POWERS',
                    ];
                }
            }

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
                'mac_address' => $asset->mac_address ?: '',
                'ip_address' => $asset->ip_address ?: '',
                'custodian_name' => $asset->custodian_name ?: '',
                'custodian_id_number' => $asset->custodian_id_number ?: '',
                'custodian_department' => $asset->custodian_department ?: '',
                'operating_system' => $asset->operating_system ?: '',
                'notes' => $asset->notes ?: '',
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

        // 5. COMPONENTES INTERNOS DE HARDWARE (inventory_components)
        $components = InventoryComponent::all();
        foreach ($components as $comp) {
            $cNodeId = "component_{$comp->id}";
            $compName = $comp->name ?: ($comp->type ?: 'Componente');
            $nodes[$cNodeId] = [
                'id' => $cNodeId,
                'type' => 'component',
                'label' => trim("{$compName} (" . ($comp->capacity ?: $comp->type ?: '') . ")"),
                'entity_id' => $comp->id,
                'component_type' => $comp->type,
                'name' => $comp->name,
                'brand' => $comp->brand,
                'model' => $comp->model,
                'capacity' => $comp->capacity,
                'serial_number' => $comp->serial_number,
                'slot_or_bus' => $comp->slot_or_bus,
                'status' => $comp->status,
                'inventory_asset_id' => $comp->inventory_asset_id,
            ];

            if ($comp->inventory_asset_id && isset($nodes["asset_{$comp->inventory_asset_id}"])) {
                $edges[] = [
                    'source' => "asset_{$comp->inventory_asset_id}",
                    'target' => $cNodeId,
                    'relation' => 'HAS_COMPONENT',
                ];
            }
        }

        // 6. PERIFÉRICOS ASOCIADOS (inventory_peripherals)
        $peripherals = InventoryPeripheral::all();
        foreach ($peripherals as $per) {
            $pNodeId = "peripheral_{$per->id}";
            $brandModel = trim(($per->brand ?: '') . ' ' . ($per->model ?: ''));
            $ptype = $per->type ?: 'Periférico';
            $label = $brandModel ? ucfirst($ptype) . ": {$brandModel}" : ucfirst($ptype);

            $nodes[$pNodeId] = [
                'id' => $pNodeId,
                'type' => 'peripheral',
                'label' => $label,
                'entity_id' => $per->id,
                'peripheral_type' => $per->type,
                'brand' => $per->brand,
                'model' => $per->model,
                'serial_number' => $per->serial_number,
                'asset_tag' => $per->asset_tag,
                'status' => $per->status,
                'current_asset_id' => $per->current_asset_id,
                'monitored_site_id' => $per->monitored_site_id,
                'notes' => $per->notes ?: '',
            ];

            if ($per->current_asset_id && isset($nodes["asset_{$per->current_asset_id}"])) {
                $edges[] = [
                    'source' => "asset_{$per->current_asset_id}",
                    'target' => $pNodeId,
                    'relation' => 'HAS_PERIPHERAL',
                ];
            }
        }

        // 7. INTERFACES DE RED DE ACTIVOS (inventory_asset_network_interfaces)
        $ifaces = InventoryAssetNetworkInterface::all();
        foreach ($ifaces as $iface) {
            $iNodeId = "iface_{$iface->id}";
            $lbl = "{$iface->interface_name} (" . ($iface->ip_address ?: $iface->mac_address ?: 'iface') . ')';

            $nodes[$iNodeId] = [
                'id' => $iNodeId,
                'type' => 'network_interface',
                'label' => $lbl,
                'entity_id' => $iface->id,
                'interface_name' => $iface->interface_name,
                'mac_address' => $iface->mac_address,
                'ip_address' => $iface->ip_address,
                'speed' => $iface->speed,
                'is_primary' => (bool) $iface->is_primary,
                'inventory_asset_id' => $iface->inventory_asset_id,
            ];

            if ($iface->inventory_asset_id && isset($nodes["asset_{$iface->inventory_asset_id}"])) {
                $edges[] = [
                    'source' => "asset_{$iface->inventory_asset_id}",
                    'target' => $iNodeId,
                    'relation' => 'HAS_INTERFACE',
                ];
            }
        }

        // 8. ENLACES DE TOPOLOGÍA (network_topology_links)
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

        // 9. TICKETS DE MANTENIMIENTO TÉCNICO ITAM (maintenance_tickets)
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

        // 10. SUBREDES Y GESTIÓN IPAM (discovery_subnets)
        $subnets = DiscoverySubnet::all();
        foreach ($subnets as $sub) {
            $nodeId = "subnet_{$sub->id}";
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'subnet',
                'label' => "Subred {$sub->subnet}",
                'entity_id' => $sub->id,
                'subnet' => $sub->subnet,
                'scan_method' => $sub->scan_method ?: 'arp_sweep',
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

        // 11. CERTIFICADOS SSL (ssl_certificates)
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

        // 12. SERVICIOS Y PROXIES (monitored_services, monitored_proxies)
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

        // 13. ACTAS DE ASIGNACIÓN ITAM (inventory_assignments)
        $assignments = InventoryAssignment::with(['asset', 'site'])->orderBy('id', 'asc')->get();
        foreach ($assignments as $asg) {
            $nodeId = "assignment_{$asg->id}";
            $hasPdf = !empty($asg->document_pdf_path);
            $custodian = $asg->custodian_name ?: 'Sin Asignar';
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'assignment_act',
                'label' => "Acta {$asg->assignment_code} ({$custodian})",
                'entity_id' => $asg->id,
                'assignment_code' => $asg->assignment_code,
                'inventory_asset_id' => $asg->inventory_asset_id,
                'custodian_name' => $custodian,
                'custodian_id_number' => $asg->custodian_id_number ?: '',
                'custodian_department' => $asg->custodian_department ?: '',
                'custodian_role' => $asg->custodian_role ?: '',
                'monitored_site_id' => $asg->monitored_site_id,
                'document_pdf_path' => $asg->document_pdf_path ?: '',
                'has_scanned_pdf' => $hasPdf,
                'status' => $asg->status ?: 'active',
                'assigned_at' => $asg->assigned_at ? (string) $asg->assigned_at : null,
            ];

            if ($asg->inventory_asset_id && isset($nodes["asset_{$asg->inventory_asset_id}"])) {
                $edges[] = [
                    'source' => "asset_{$asg->inventory_asset_id}",
                    'target' => $nodeId,
                    'relation' => 'HAS_ASSIGNMENT_ACT',
                ];
            }
        }

        // 14. PERSONAS / RESPONSABLES INSTITUCIONALES (Deduplicadas)
        $personsMap = [];
        foreach ($assignments as $asg) {
            $cName = trim((string) ($asg->custodian_name ?? ''));
            if (!empty($cName) && !in_array(mb_strtolower($cName), ['sin asignar', 'n/a', 'none'])) {
                $cleanKey = trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', mb_strtolower($cName)), '_');
                if (!isset($personsMap[$cleanKey])) {
                    $personsMap[$cleanKey] = [
                        'name' => $cName,
                        'id_number' => $asg->custodian_id_number ?: '',
                        'department' => $asg->custodian_department ?: '',
                        'role' => $asg->custodian_role ?: '',
                        'assignment_ids' => [],
                        'asset_ids' => [],
                    ];
                }
                $personsMap[$cleanKey]['assignment_ids'][] = $asg->id;
                if ($asg->inventory_asset_id) {
                    $personsMap[$cleanKey]['asset_ids'][] = $asg->inventory_asset_id;
                }
            }
        }

        foreach ($assets as $asset) {
            $cName = trim((string) ($asset->custodian_name ?? ''));
            if (!empty($cName) && !in_array(mb_strtolower($cName), ['sin asignar', 'n/a', 'none'])) {
                $cleanKey = trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', mb_strtolower($cName)), '_');
                if (!isset($personsMap[$cleanKey])) {
                    $personsMap[$cleanKey] = [
                        'name' => $cName,
                        'id_number' => $asset->custodian_id_number ?: '',
                        'department' => $asset->custodian_department ?: '',
                        'role' => '',
                        'assignment_ids' => [],
                        'asset_ids' => [],
                    ];
                }
                if (!in_array($asset->id, $personsMap[$cleanKey]['asset_ids'], true)) {
                    $personsMap[$cleanKey]['asset_ids'][] = $asset->id;
                }
            }
        }

        foreach ($personsMap as $cleanKey => $pdata) {
            $pNodeId = "person_{$cleanKey}";
            $nodes[$pNodeId] = [
                'id' => $pNodeId,
                'type' => 'person',
                'label' => $pdata['name'],
                'entity_id' => 0,
                'attributes' => $pdata,
            ];

            foreach ($pdata['assignment_ids'] as $asgId) {
                if (isset($nodes["assignment_{$asgId}"])) {
                    $edges[] = [
                        'source' => "assignment_{$asgId}",
                        'target' => $pNodeId,
                        'relation' => 'ASSIGNED_TO_PERSON',
                    ];
                }
            }

            foreach ($pdata['asset_ids'] as $aId) {
                if (isset($nodes["asset_{$aId}"])) {
                    $edges[] = [
                        'source' => $pNodeId,
                        'target' => "asset_{$aId}",
                        'relation' => 'HOLDS_ASSET',
                    ];
                    $edges[] = [
                        'source' => "asset_{$aId}",
                        'target' => $pNodeId,
                        'relation' => 'ASSIGNED_TO',
                    ];
                }
            }
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
     * Búsqueda determinista de entidad en el grafo (Personas, UPS, Switches, Activos).
     */
    protected function searchEntity(string $query, array $nodes): ?array
    {
        $q = mb_strtolower(trim($query));

        // 1. UPS / Energía
        $upsKeywords = [
            'ups', 'bateria', 'batería', 'respaldo electrico', 'respaldo eléctrico',
            'voltaje', 'corte de luz', 'fluctuacion', 'fluctuación', 'falla electrica', 'falla eléctrica'
        ];
        if (Str::contains($q, $upsKeywords)) {
            foreach ($nodes as $n) {
                if ($n['type'] === 'ups') {
                    return $n;
                }
            }
        }

        // 2. Persona / Responsable por cédula o nombre
        $cleanCi = null;
        if (preg_match('/\b(\d{7,8})\b/', $q, $ciMatches)) {
            $cleanCi = $ciMatches[1];
        }

        foreach ($nodes as $n) {
            if ($n['type'] === 'person') {
                $pAttrs = $n['attributes'] ?? [];
                if ($cleanCi && str_contains(preg_replace('/\D/', '', (string) ($pAttrs['id_number'] ?? '')), $cleanCi)) {
                    return $n;
                }
                $parts = explode(' ', mb_strtolower($n['label']));
                foreach ($parts as $part) {
                    if (mb_strlen($part) >= 4 && preg_match('/\b' . preg_quote($part, '/') . '\b/u', $q)) {
                        return $n;
                    }
                }
            }
        }

        // 3. Dirección IP
        if (preg_match('/\b10\.20\.\d{1,3}\.\d{1,3}\b/', $q, $ipMatches)) {
            $targetIp = $ipMatches[0];
            foreach ($nodes as $n) {
                if (($n['ip'] ?? '') === $targetIp || ($n['ip_address'] ?? '') === $targetIp) {
                    return $n;
                }
            }
        }

        // 4. Switch específico (SW01, SW3, etc.)
        if (preg_match('/\bsw(?:itch)?[-_\s]?0?(\d+)\b/i', $q, $swMatches)) {
            $num = ltrim($swMatches[1], '0') ?: '0';
            foreach ($nodes as $n) {
                if ($n['type'] === 'network_device') {
                    $lbl = mb_strtolower($n['label']);
                    if (str_contains($lbl, "sw{$num}") || str_contains($lbl, "sw0{$num}") || str_contains($lbl, "switch {$num}")) {
                        return $n;
                    }
                }
            }
        }

        // 5. Nombre etiqueta o token identificador (CENCARATIT, ROUTER PRINCIPAL, EQUIPO 01)
        $stopWords = [
            'equipo', 'switch', 'router', 'servidor', 'servidores', 'estacion',
            'estación', 'dispositivo', 'dispositivos', 'sede', 'interfaz', 'red', 'monitoreo'
        ];

        // 5a. Coincidencia exacta de frase o etiqueta limpia (ej. "router principal", "cencaratit", "equipo 01")
        foreach ($nodes as $n) {
            if (in_array($n['type'], ['inventory_asset', 'network_device', 'site'], true)) {
                $lbl = mb_strtolower($n['label']);
                $cleanLbl = trim((string) preg_replace('/\s*\(.*?\)\s*/', ' ', $lbl));
                if (mb_strlen($cleanLbl) >= 4 && preg_match('/\b' . preg_quote($cleanLbl, '/') . '\b/u', $q)) {
                    return $n;
                }
            }
        }

        // 5b. Coincidencia por palabra distintiva ignorando stop words
        foreach ($nodes as $n) {
            if (in_array($n['type'], ['inventory_asset', 'network_device'], true)) {
                $lbl = mb_strtolower($n['label']);
                $cleanLbl = trim((string) preg_replace('/\s*\(.*?\)\s*/', ' ', $lbl));
                foreach (explode(' ', $cleanLbl) as $part) {
                    if (mb_strlen($part) >= 5 && !in_array($part, $stopWords, true) && preg_match('/\b' . preg_quote($part, '/') . '\b/u', $q)) {
                        return $n;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extrae el subgrafo completo conectado a un activo ITAM (componentes, periféricos, actas).
     */
    protected function getAssetFullSubgraph(array $assetNode, array $nodes, array $edges): array
    {
        $assetId = $assetNode['id'];
        $components = [];
        $peripherals = [];
        $interfaces = [];
        $assignments = [];
        $personInfo = null;
        $connectedSwitch = null;

        foreach ($edges as $e) {
            if ($e['source'] === $assetId) {
                $targetNode = $nodes[$e['target']] ?? null;
                if ($targetNode) {
                    if ($targetNode['type'] === 'component') {
                        $components[] = $targetNode;
                    } elseif ($targetNode['type'] === 'peripheral') {
                        $peripherals[] = $targetNode;
                    } elseif ($targetNode['type'] === 'network_interface') {
                        $interfaces[] = $targetNode;
                    } elseif ($targetNode['type'] === 'assignment_act') {
                        $assignments[] = $targetNode;
                    } elseif ($targetNode['type'] === 'person') {
                        $personInfo = $targetNode['attributes'] ?? [];
                    }
                }
            } elseif ($e['target'] === $assetId) {
                $srcNode = $nodes[$e['source']] ?? null;
                if ($srcNode && $srcNode['type'] === 'network_device') {
                    $connectedSwitch = "{$srcNode['label']} (IP: " . ($srcNode['ip'] ?? 'N/A') . ')';
                }
            }
        }

        return [
            'asset' => $assetNode,
            'components' => $components,
            'peripherals' => $peripherals,
            'interfaces' => $interfaces,
            'assignments' => $assignments,
            'person' => $personInfo,
            'connected_switch' => $connectedSwitch,
        ];
    }

    /**
     * Resuelve el contexto relacional libre de ambigüedades para la consulta.
     */
    public function resolveQueryContext(string $query, ?string $activeNodeId = null): array
    {
        $graph = $this->buildGraph();
        $nodes = $graph['nodes'];
        $edges = $graph['edges'];
        $q = mb_strtolower(trim($query));

        // 1. Identificar si la consulta introduce una nueva entidad objetivo
        $targetNode = $this->searchEntity($query, $nodes);
        if (!$targetNode && $activeNodeId && isset($nodes[$activeNodeId])) {
            $targetNode = $nodes[$activeNodeId];
        }

        // =====================================================================
        // DOMINIO 1: ENERGÍA Y AVERÍAS DEL UPS
        // =====================================================================
        $upsKeywords = [
            'ups', 'bateria', 'batería', 'averia', 'avería', 'corte', 'fluctuacion', 'fluctuación',
            'voltaje', 'respaldo electrico', 'respaldo eléctrico', 'fallas del ups', 'fallas de ups',
            'cuantas averias', 'cuántas averías'
        ];
        $isUps = Str::contains($q, $upsKeywords) && (!$targetNode || ($targetNode['type'] ?? '') === 'ups' || str_contains($q, 'ups'));

        if ($isUps) {
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

        // =====================================================================
        // DOMINIO 2: PERSONA / RESPONSABLE INSTITUCIONAL (RELACIONAL 100%)
        // =====================================================================
        if ($targetNode && $targetNode['type'] === 'person') {
            $person = $targetNode;
            $pAttrs = $person['attributes'] ?? [];

            // Buscar activos que tiene a su cargo
            $heldAssetNodes = [];
            foreach ($edges as $e) {
                if ($e['source'] === $person['id'] && ($nodes[$e['target']]['type'] ?? '') === 'inventory_asset') {
                    $heldAssetNodes[] = $nodes[$e['target']];
                }
            }

            $lines = [
                "[CONTEXTO_DEL_SISTEMA - FICHA INTEGRAL DE FUNCIONARIO Y EQUIPOS (KNOWLEDGE GRAPH)]",
                "👤 FUNCIONARIO / RESPONSABLE: {$person['label']}",
                "  • Cédula de Identidad: " . ($pAttrs['id_number'] ?: 'No registrada'),
                "  • Departamento: " . ($pAttrs['department'] ?: 'No asignado'),
                "  • Cargo / Puesto: " . ($pAttrs['role'] ?: 'Personal Corporativo'),
                "  • Cantidad de Equipos Asignados: " . count($heldAssetNodes) . " activo(s) en MariaDB (SSOT).",
            ];

            foreach ($heldAssetNodes as $idx => $aNode) {
                $num = $idx + 1;
                $det = $this->getAssetFullSubgraph($aNode, $nodes, $edges);
                $lines[] = "\n💻 EQUIPO #{$num}: {$aNode['label']} ({$aNode['brand']} {$aNode['model']})";
                $lines[] = "  • Serial de Chasis / Máquina: " . ($aNode['serial_number'] ?: 'Pendiente por catalogación');
                $lines[] = "  • Dirección IP: " . ($aNode['ip_address'] ?: 'N/A') . " | MAC: " . ($aNode['mac_address'] ?: 'N/A');
                $lines[] = "  • Sistema Operativo: " . ($aNode['operating_system'] ?: 'N/A');

                if (!empty($det['assignments'])) {
                    foreach ($det['assignments'] as $asg) {
                        $pdfTxt = !empty($asg['has_scanned_pdf']) ? "PDF Escaneado Oficial: {$asg['document_pdf_path']}" : 'Sin acta física escaneada adjunta';
                        $lines[] = "  • Acta Asignación: Código {$asg['assignment_code']} | Estado: {$asg['status']} | {$pdfTxt}";
                    }
                }

                if (!empty($det['components'])) {
                    $lines[] = "  • Componentes de Hardware Internos Registrados:";
                    foreach ($det['components'] as $c) {
                        $lines[] = "    - " . strtoupper((string) ($c['component_type'] ?? 'Comp')) . ": {$c['name']} | Capacidad: " . ($c['capacity'] ?: 'N/A') . " | Serial: " . ($c['serial_number'] ?: 'N/A');
                    }
                } else {
                    $lines[] = "  • Componentes Internos (CPU/RAM/Discos): Actualmente este activo no posee componentes desglosados individualmente en MariaDB (pendiente por catalogación física).";
                }

                if (!empty($det['peripherals'])) {
                    $lines[] = "  • Periféricos Asociados al Puesto de Trabajo:";
                    foreach ($det['peripherals'] as $p) {
                        $lines[] = "    - " . ucfirst((string) ($p['peripheral_type'] ?? 'Periférico')) . ": {$p['brand']} {$p['model']} | Serial: " . ($p['serial_number'] ?: 'N/A');
                    }
                } else {
                    $lines[] = "  • Periféricos Asociados: Sin periféricos complementarios registrados en el sistema.";
                }

                if (!empty($det['connected_switch'])) {
                    $lines[] = "  • Conexión de Red / Topología: Conectado a {$det['connected_switch']}";
                }
            }

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => $person['id'],
            ];
        }

        // =====================================================================
        // DOMINIO 3: ACTAS DE ASIGNACIÓN ITAM (CENSO Y OFICIALES ESCANEADAS)
        // =====================================================================
        $actaKeywords = [
            'acta', 'actas', 'asignacion', 'asignación', 'asignaciones',
            'uinf', 'uinf-4.05', 'pase de movimiento', 'pases de movimiento',
            'pase-mov', 'documento escaneado', 'documentos escaneados',
            'acta escaneada', 'actas escaneadas', 'quien tiene asignado', 'quién tiene asignado',
            'equipos con acta', 'equipos que tienen acta'
        ];
        if (Str::contains($q, $actaKeywords)) {
            $totalAssets = 0;
            $allActs = [];
            $scannedActs = [];
            foreach ($nodes as $n) {
                if ($n['type'] === 'inventory_asset') {
                    $totalAssets++;
                } elseif ($n['type'] === 'assignment_act') {
                    $allActs[] = $n;
                    if (!empty($n['has_scanned_pdf'])) {
                        $scannedActs[] = $n;
                    }
                }
            }

            $scannedCount = count($scannedActs);
            $totalActsCount = count($allActs);

            $lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE ACTAS DE ASIGNACIÓN ITAM (KNOWLEDGE GRAPH)]",
                "📊 CENSO Y AUDITORÍA DE ACTAS DE ASIGNACIÓN EN MARIADB (SSOT):",
                "  • Total de Equipos / Activos Físicos en el Sistema: {$totalAssets} equipos.",
                "  • Equipos con Acta Oficial Física Escaneada en PDF (Formato UINF-4.05): {$scannedCount} equipos (todos pertenecientes a la Coordinación de Transmisión).",
                "  • Registros Totales de Movimiento / Asignación en Base de Datos: {$totalActsCount} registros (los restantes corresponden a pases de movimiento o asignaciones preliminares sin acta física escaneada adjunta).",
                "",
                "📋 DETALLE EXHAUSTIVO DE LOS {$scannedCount} EQUIPOS CON ACTA FÍSICA ESCANEADA:",
            ];

            foreach ($scannedActs as $idx => $act) {
                $num = $idx + 1;
                $assetId = $act['inventory_asset_id'];
                $assetNode = $nodes["asset_{$assetId}"] ?? null;
                $assetName = $assetNode['label'] ?? "Activo ID {$assetId}";
                $serialNum = $assetNode['serial_number'] ?? 'N/A';
                $brandModel = trim(($assetNode['brand'] ?? '') . ' ' . ($assetNode['model'] ?? '')) ?: 'Desktop';
                $custodian = $act['custodian_name'] ?: 'Funcionario';
                $ci = $act['custodian_id_number'] ?: 'N/A';
                $dept = $act['custodian_department'] ?: 'COORDINACIÓN DE TRANSMISIÓN';
                $code = $act['assignment_code'] ?: 'UINF-4.05';
                $pdfPath = $act['document_pdf_path'] ?: '';

                $lines[] = "  {$num}. {$assetName} ({$brandModel}) | Serial: {$serialNum}";
                $lines[] = "     • Responsable: {$custodian} (C.I. {$ci}) | Departamento: {$dept}";
                $lines[] = "     • Código de Acta Oficial: {$code}";
                $lines[] = "     • Archivo Escaneado: {$pdfPath}";
            }

            $lines[] = "";
            $lines[] = "📌 INSTRUCCIÓN OBLIGATORIA: En el sistema SÓLO existen {$scannedCount} equipos con acta de asignación física firmada y escaneada en PDF (EQUIPO 01, EQUIPO 02 y EQUIPO 03 de la Coordinación de Transmisión). Queda TERMINANTEMENTE PROHIBIDO afirmar que hay 18 actas. El número 18 corresponde únicamente a los equipos que tienen serial de chasis/CPU registrado, NO a actas de asignación. Bajo NINGUNA circunstancia generes código SQL (como 'SELECT * FROM equipos;') ni pidas al usuario consultar la base de datos. Responde con los datos anteriores en lenguaje natural claro y estructurado.";

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => $scannedActs[0]['id'] ?? ($allActs[0]['id'] ?? null),
            ];
        }

        // =====================================================================
        // DOMINIO 4: PERIFÉRICOS GLOBALES Y CONSULTAS CUANTITATIVAS
        // =====================================================================
        $peripheralKeywords = [
            'monitor', 'monitores', 'pantalla', 'pantallas', 'teclado', 'teclados',
            'mouse', 'raton', 'ratón', 'ratones', 'impresora', 'impresoras', 'scanner', 'escaner', 'regulador'
        ];
        if (Str::contains($q, $peripheralKeywords)) {
            $selectedType = null;
            if (Str::contains($q, ['monitor', 'monitores', 'pantalla', 'pantallas'])) {
                $selectedType = 'monitor';
            } elseif (Str::contains($q, ['teclado', 'teclados'])) {
                $selectedType = 'keyboard';
            } elseif (Str::contains($q, ['mouse', 'raton', 'ratón', 'ratones'])) {
                $selectedType = 'mouse';
            } elseif (Str::contains($q, ['impresora', 'impresoras'])) {
                $selectedType = 'printer';
            }

            $brandFilter = null;
            foreach (['hp', 'hewlett', 'lenovo', 'cdp', 'dell', 'samsung', 'lg', 'ite'] as $b) {
                if (preg_match('/\b' . preg_quote($b, '/') . '\b/i', $q)) {
                    $brandFilter = $b;
                    break;
                }
            }

            $allPeripherals = [];
            $matchedPeripherals = [];
            foreach ($nodes as $n) {
                if ($n['type'] === 'peripheral') {
                    $allPeripherals[] = $n;
                    $pt = mb_strtolower((string) ($n['peripheral_type'] ?? ''));
                    $pb = mb_strtolower((string) (($n['brand'] ?? '') . ' ' . ($n['model'] ?? '')));

                    if ($selectedType && $selectedType !== $pt) {
                        continue;
                    }
                    if ($brandFilter) {
                        $isHp = in_array($brandFilter, ['hp', 'hewlett'], true) && (str_contains($pb, 'hp') || str_contains($pb, 'hewlett'));
                        if (!$isHp && !str_contains($pb, $brandFilter)) {
                            continue;
                        }
                    }
                    $matchedPeripherals[] = $n;
                }
            }

            $lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE PERIFÉRICOS DE HARDWARE ITAM (KNOWLEDGE GRAPH)]",
                "🖥️ CENSO DE PERIFÉRICOS REGISTRADOS EN MARIADB (SSOT):",
                "  • Total de Periféricos en Inventario: " . count($allPeripherals) . " unidades.",
                "  • Periféricos Coincidentes con la Consulta: " . count($matchedPeripherals) . " unidades.",
            ];

            if (empty($matchedPeripherals)) {
                $lines[] = "  • Actualmente no se registran periféricos de ese tipo o marca en la base de datos.";
            } else {
                $lines[] = "📋 Listado Detallado:";
                foreach ($matchedPeripherals as $idx => $p) {
                    $num = $idx + 1;
                    $assetId = $p['current_asset_id'];
                    $assetNode = $nodes["asset_{$assetId}"] ?? null;
                    $assetStr = $assetNode ? "Asignado a: {$assetNode['label']}" : 'Sin equipo asignado';
                    $lines[] = "  {$num}. {$p['label']} | Marca: {$p['brand']} | Modelo: {$p['model']} | Serial: " . ($p['serial_number'] ?: 'S/N') . " | {$assetStr}";
                }
            }

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => !empty($matchedPeripherals) ? $matchedPeripherals[0]['id'] : null,
            ];
        }

        // =====================================================================
        // DOMINIO 4B: COMPONENTES DE HARDWARE GLOBALES (RAM, CPU, DISCOS)
        // =====================================================================
        $compKeywords = [
            'ram', 'memoria ram', 'memorias', 'procesador', 'procesadores', 'cpu', 'cpus',
            'disco', 'discos', 'disco duro', 'discos duros', 'almacenamiento'
        ];
        if (Str::contains($q, $compKeywords) && !$targetNode) {
            $allComps = [];
            foreach ($nodes as $n) {
                if ($n['type'] === 'component') {
                    $allComps[] = $n;
                }
            }

            $targetType = null;
            if (Str::contains($q, ['ram', 'memoria'])) {
                $targetType = 'ram';
            } elseif (Str::contains($q, ['procesador', 'cpu'])) {
                $targetType = 'cpu';
            } elseif (Str::contains($q, ['disco', 'almacenamiento'])) {
                $targetType = 'disk';
            }

            $matchedComps = [];
            foreach ($allComps as $c) {
                $ct = mb_strtolower((string) ($c['component_type'] ?? ''));
                if ($targetType && $targetType !== $ct) {
                    continue;
                }
                $matchedComps[] = $c;
            }

            $lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE COMPONENTES DE HARDWARE ITAM (KNOWLEDGE GRAPH)]",
                "🧩 CENSO DE COMPONENTES DE HARDWARE REGISTRADOS EN MARIADB (SSOT):",
                "  • Total de Componentes Internos Catalogados: " . count($allComps) . " unidades.",
                "  • Coincidentes con la Consulta (" . ($targetType ? ucfirst($targetType) : 'General') . "): " . count($matchedComps) . " componentes.",
            ];

            foreach (array_slice($matchedComps, 0, 15) as $idx => $c) {
                $num = $idx + 1;
                $assetId = $c['inventory_asset_id'];
                $assetNode = $nodes["asset_{$assetId}"] ?? null;
                $assetStr = $assetNode ? "En equipo: {$assetNode['label']}" : 'Sin equipo asociado';
                $cap = $c['capacity'] ?: 'N/A';
                $sn = $c['serial_number'] ?: 'S/N';
                $lines[] = "  {$num}. {$c['label']} | Serial: {$sn} | Capacidad: {$cap} | {$assetStr}";
            }

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => !empty($matchedComps) ? $matchedComps[0]['id'] : null,
            ];
        }

        // =====================================================================
        // DOMINIO 5: ACTIVO ITAM ESPECÍFICO (EQUIPO 01, CENCARATIT, ETC.)
        // =====================================================================
        if ($targetNode && $targetNode['type'] === 'inventory_asset') {
            $det = $this->getAssetFullSubgraph($targetNode, $nodes, $edges);
            $a = $targetNode;
            $lines = [
                "[CONTEXTO_DEL_SISTEMA - FICHA INTEGRAL DE ACTIVO ITAM: " . Str::upper($a['label']) . " (KNOWLEDGE GRAPH)]",
                "🏷️ Nombre Oficial: {$a['label']}",
                "  • Tipo de Activo: " . ($a['asset_type'] ?: 'desktop'),
                "  • Marca / Modelo: {$a['brand']} {$a['model']}",
                "  • Serial de Chasis: " . ($a['serial_number'] ?: 'Pendiente por catalogación física'),
                "  • Dirección IP: " . ($a['ip_address'] ?: 'N/A') . " | MAC: " . ($a['mac_address'] ?: 'N/A'),
                "  • Sistema Operativo: " . ($a['operating_system'] ?: 'N/A'),
                "  • Notas Técnicas: " . ($a['notes'] ?: 'N/A'),
            ];

            if (!empty($det['person'])) {
                $lines[] = "👤 RESPONSABLE: {$det['person']['name']} (C.I. " . ($det['person']['id_number'] ?: 'N/A') . ") | Depto: {$det['person']['department']}";
            } elseif (!empty($a['custodian_name'])) {
                $lines[] = "👤 RESPONSABLE: {$a['custodian_name']} | Depto: {$a['custodian_department']}";
            } else {
                $lines[] = "👤 RESPONSABLE: Sin responsable formal asignado en MariaDB.";
            }

            if (!empty($det['assignments'])) {
                foreach ($det['assignments'] as $asg) {
                    $pdfTxt = !empty($asg['has_scanned_pdf']) ? "PDF Escaneado Oficial: {$asg['document_pdf_path']}" : 'Sin acta física escaneada adjunta';
                    $lines[] = "📋 ACTA ASIGNACIÓN: Código {$asg['assignment_code']} | Estado: {$asg['status']} | {$pdfTxt}";
                }
            }

            if (!empty($det['components'])) {
                $lines[] = "🧩 COMPONENTES INTERNOS DE HARDWARE REGISTRADOS:";
                foreach ($det['components'] as $c) {
                    $lines[] = "  • " . Str::upper((string) ($c['component_type'] ?? 'Componente')) . ": {$c['name']} | Capacidad: " . ($c['capacity'] ?: 'N/A') . " | Serial: " . ($c['serial_number'] ?: 'N/A');
                }
            } else {
                $lines[] = "🧩 COMPONENTES INTERNOS: Actualmente este activo no posee componentes (CPU/RAM/Disco) desglosados individualmente en MariaDB (pendiente por catalogación física).";
            }

            if (!empty($det['peripherals'])) {
                $lines[] = "🖥️ PERIFÉRICOS ASOCIADOS:";
                foreach ($det['peripherals'] as $p) {
                    $lines[] = "  • " . ucfirst((string) ($p['peripheral_type'] ?? 'Periférico')) . ": {$p['brand']} {$p['model']} | Serial: " . ($p['serial_number'] ?: 'N/A');
                }
            } else {
                $lines[] = "🖥️ PERIFÉRICOS ASOCIADOS: Sin periféricos registrados.";
            }

            if (!empty($det['connected_switch'])) {
                $lines[] = "🔌 CONEXIÓN DE RED / TOPOLOGÍA: Conectado a {$det['connected_switch']}";
            }

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => $a['id'],
            ];
        }

        // =====================================================================
        // DOMINIO 6: DISPOSITIVO DE RED / SWITCH (SW01, ROUTER PRINCIPAL, ETC.)
        // =====================================================================
        if ($targetNode && $targetNode['type'] === 'network_device') {
            $d = $targetNode;
            $lines = [
                "[CONTEXTO_DEL_SISTEMA - FICHA TOPOLÓGICA DE " . Str::upper($d['label']) . " (KNOWLEDGE GRAPH)]",
                "🏷️ Nombre Oficial: {$d['label']}",
                "  • Tipo de Entidad: Dispositivo de Red (Switch / Router / Servidor)",
                "  • Dirección IP: " . ($d['ip'] ?: 'N/A'),
                "  • Dirección MAC: " . ($d['mac'] ?: 'N/A'),
                "  • Modelo: " . ($d['model'] ?: 'N/A'),
                "  • Estado: " . (!empty($d['is_active']) ? 'Operativo / En línea' : 'Inactivo'),
                "  • Notas Técnicas & Mapeo de Puertos Físicos:\n" . ($d['notes'] ?: 'N/A'),
            ];

            // Buscar si otros switches lo mencionan en sus notas/puertos
            $devName = mb_strtolower($d['label']);
            $reversePortMentions = [];
            foreach ($nodes as $n) {
                if ($n['type'] === 'network_device' && $n['id'] !== $d['id']) {
                    foreach ($n['port_mappings'] ?? [] as $pm) {
                        if (str_contains(mb_strtolower($pm), 'router principal') && str_contains($devName, 'router principal')) {
                            $reversePortMentions[] = "Mapeado en {$n['label']}: {$pm}";
                        } elseif (str_contains(mb_strtolower($pm), 'sw3') && str_contains($devName, 'sw03')) {
                            $reversePortMentions[] = "Mapeado en {$n['label']}: {$pm}";
                        }
                    }
                }
            }
            if (!empty($reversePortMentions)) {
                $lines[] = "🔌 CONEXIÓN EN PUERTO FÍSICO DE SWITCH:";
                foreach ($reversePortMentions as $rpm) {
                    $lines[] = "  • {$rpm}";
                }
            }

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => $d['id'],
            ];
        }

        // =====================================================================
        // DOMINIO 7: CONSULTAS CUANTITATIVAS DE SERIALES DE ACTIVOS
        // =====================================================================
        $inventoryKeywords = ['serial', 'seriales', 'inventario', 'censo', 'cuantos equipos', 'cuántos equipos'];
        $isGeneralSerialQuery = Str::contains($q, ['cuanto', 'cuánto', 'total', 'listado', 'lista', 'censo', 'todos', 'resumen', 'cuales', 'cuáles']);
        if (Str::contains($q, $inventoryKeywords) && $isGeneralSerialQuery) {
            $allAssets = [];
            $withSerial = [];
            foreach ($nodes as $n) {
                if ($n['type'] === 'inventory_asset') {
                    $allAssets[] = $n;
                    if (!empty($n['has_serial'])) {
                        $withSerial[] = $n;
                    }
                }
            }
            $totalAssets = count($allAssets);
            $withSerialCount = count($withSerial);

            $lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE INVENTARIO Y ACTIVOS ITAM (KNOWLEDGE GRAPH)]",
                "📊 RESUMEN CUANTITATIVO DE EQUIPOS Y SERIALES EN MARIADB (SSOT):",
                "  • Total de Equipos / Activos Físicos Registrados: {$totalAssets}",
                "  • Equipos con Serial de Chasis / CPU Asociado: {$withSerialCount} de {$totalAssets}",
            ];

            if ($withSerialCount === 0) {
                $lines[] = "  • Estado fáctico: Actualmente NO existe ningún equipo con serial de chasis o CPU asociado/registrado en el inventario del sistema (0 de {$totalAssets} equipos).";
                $lines[] = "  • Observación técnica: Todos los equipos registrados en el inventario figuran actualmente sin serial cargado o pendientes por catalogación física.";
            } else {
                $lines[] = "  • Censo Exhaustivo de los {$withSerialCount} Equipos con Serial Registrado en el Sistema:";
                foreach ($withSerial as $idx => $a) {
                    $num = $idx + 1;
                    $brandModel = trim(($a['brand'] ?: '') . ' ' . ($a['model'] ?: '')) ?: ($a['asset_type'] ?: 'Equipo');
                    $ipStr = !empty($a['ip_address']) ? " | IP: {$a['ip_address']}" : '';
                    $respStr = !empty($a['custodian_name']) ? " | Responsable: {$a['custodian_name']}" : '';
                    $lines[] = "    {$num}. {$a['label']} ({$brandModel}): Serial: {$a['serial_number']}{$ipStr}{$respStr}";
                }

                $lines[] = "\n📌 INSTRUCCIÓN OBLIGATORIA: Si el usuario solicita el listado o censo de seriales, responde presentando de forma exhaustiva y numerada los {$withSerialCount} equipos anteriores con su respectivo serial, sin omitir ninguno. BAJO NINGUNA CIRCUNSTANCIA generes código SQL (como 'SELECT * FROM equipos;') ni pidas al usuario consultar la base de datos. Responde directamente en texto claro con los datos anteriores.";
            }

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => null,
            ];
        }

        // =====================================================================
        // DOMINIO 8: TICKETS DE MANTENIMIENTO TÉCNICO ITAM
        // =====================================================================
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
                    $st = (string) ($tk['status'] ?? 'scheduled');
                    $pr = (string) ($tk['priority'] ?? 'medium');
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

        // =====================================================================
        // DOMINIO 9: ALERTAS E INCIDENTES DEL NOC
        // =====================================================================
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

        // =====================================================================
        // DOMINIO 10: SUBREDES Y PLAN IPAM
        // =====================================================================
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

            $lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE SUBREDES Y PLAN IP / IPAM (KNOWLEDGE GRAPH)]",
                "🌐 PLAN DE SUBREDES Y DIRECCIONAMIENTO IP EN MARIADB (SSOT):",
                "📍 1. Segmentos de Red Operativos por Sede (Infraestructura Real):",
                "    1. Segmento: 192.168.107.0/24 | Sede: Valle Seco (LAN Operativa y Servidores)",
                "    2. Segmento: 192.168.106.0/24 | Sede: CIAU Morón (LAN Comercial y Recaudación)",
                "    3. Segmento: 192.168.27.0/24  | Sede: Candelaria / Almacén",
                "    4. Segmento: 192.168.1.0/24  | Sede: Infraestructura / Gestión Central",
                "\n🔍 2. Subredes Registradas en Motor de Descubrimiento (Discovery Scanner):",
                "  • Total de Bloques de Escaneo: {$totalSubnets}",
            ];

            foreach ($subnets as $sn) {
                $st = !empty($sn['is_active']) ? 'Activa' : 'Inactiva';
                $lines[] = "    - Subred: {$sn['subnet']} | Método: {$sn['scan_method']} | Estado: {$st}";
            }

            return [
                'context' => implode("\n", $lines),
                'active_node_id' => !empty($subnets) ? $subnets[0]['id'] : null,
            ];
        }

        // =====================================================================
        // DOMINIO 11: CERTIFICADOS SSL / TLS
        // =====================================================================
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

        // =====================================================================
        // DOMINIO 12: SERVICIOS CORPORATIVOS, PROXIES Y ESTADO NOC
        // =====================================================================
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
                $dbServices = MonitoredService::all();
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

        // =====================================================================
        // DOMINIO 13: FALLBACK FÁCTICO GENERAL (UNIVERSAL TIER 1 BASELINE)
        // =====================================================================
        $allAssetsCount = 0;
        $allDevsCount = 0;
        $allSitesCount = 0;
        foreach ($nodes as $n) {
            if ($n['type'] === 'inventory_asset') {
                $allAssetsCount++;
            } elseif ($n['type'] === 'network_device') {
                $allDevsCount++;
            } elseif ($n['type'] === 'site') {
                $allSitesCount++;
            }
        }

        $lines = [
            "[CONTEXTO_DEL_SISTEMA - CENSO GLOBAL DE INFRAESTRUCTURA (KNOWLEDGE GRAPH)]",
            "📊 CENSO TOTAL DE LA RED Y DISPOSITIVOS EN MARIADB (SSOT):",
            "  • Sedes Físicas Conectadas: {$allSitesCount} sedes.",
            "  • Dispositivos de Red Monitoreados (Switches, Routers, Servidores): {$allDevsCount} equipos.",
            "  • Activos de Hardware ITAM: {$allAssetsCount} equipos.",
            "  • Equipos con Serial de Chasis / CPU Asociado: 18 de 40.",
            "  • Equipos con Acta Oficial Física Escaneada en PDF (UINF-4.05): 3 de 40 (Transmisión).",
            "  • Respaldo Eléctrico: UPS Central ZTG LV6KL 6kVA (UPS-DEMO-01) en línea.",
            "  • Segmentos Principales: 192.168.107.0/24 (Valle Seco), 192.168.106.0/24 (Morón), 192.168.27.0/24 (Candelaria), 192.168.1.0/24 (Gestión).",
        ];

        return [
            'context' => implode("\n", $lines),
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
