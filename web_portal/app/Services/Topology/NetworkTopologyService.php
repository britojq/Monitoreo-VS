<?php

namespace App\Services\Topology;

use App\Contracts\NetworkTopologyInterface;
use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;
use App\Models\NetworkDeviceCheckHistory;
use App\Models\NetworkTopologyLink;
use App\Models\SnmpDevice;
use Illuminate\Support\Collection;

/**
 * Servicio unificado para la generación dinámica de la cartografía y topología de red.
 * Integra dispositivos SNMP, routers, switches y activos de cómputo ITAM (MariaDB SSOT).
 */
class NetworkTopologyService implements NetworkTopologyInterface
{
    /**
     * Construye y retorna la estructura de nodos y enlaces para Cytoscape.js.
     */
    public function getTopologyGraph(?int $siteId = null): array
    {
        // Auto-curación si no hay enlaces en MariaDB
        if (NetworkTopologyLink::count() === 0) {
            $this->rebuildTopology();
        }

        $nodes = [];
        $edges = [];
        $addedNodeIds = [];
        $addedIps = [];
        $nodeIps = [];
        $addedEdgeKeys = [];

        // 1. Estado en vivo de pings recientes
        $latestDevHistories = NetworkDeviceCheckHistory::whereIn('id', function ($q) {
            $q->selectRaw('MAX(id)')->from('network_device_check_histories')->groupBy('monitored_network_device_id');
        })->pluck('is_up', 'monitored_network_device_id');

        $netDevsByIp = MonitoredNetworkDevice::all()->keyBy('ip');

        // 2. Dispositivos SNMP
        $snmpQuery = SnmpDevice::with('site');
        if ($siteId) {
            $snmpQuery->where('site_id', $siteId);
        }
        $snmpDevices = $snmpQuery->get();

        $primarySwitchNodeId = null;

        foreach ($snmpDevices as $d) {
            $nodeId = 'snmp_' . $d->id;
            $addedNodeIds[$nodeId] = true;
            if ($d->ip_address) {
                $addedIps[$d->ip_address] = $nodeId;
                $nodeIps[$nodeId] = $d->ip_address;
            }

            $isCore = str_contains(strtolower($d->name), 'core') || str_contains(strtolower($d->model ?? ''), '3750') || $d->ip_address === '10.20.23.1';
            $isFirewall = ($d->device_type === 'firewall') || str_contains(strtolower($d->name), 'pfsense') || str_contains(strtolower($d->vendor ?? ''), 'pfsense');
            $isRouter = ($d->device_type === 'router') || str_contains(strtolower($d->name), 'router') || str_contains(strtolower($d->model ?? ''), '2901');

            $type = 'switch';
            if ($isFirewall) $type = 'firewall';
            elseif ($isCore) $type = 'core_switch';
            elseif ($isRouter) $type = 'router';

            if ($type === 'core_switch' || ($type === 'switch' && !$primarySwitchNodeId)) {
                $primarySwitchNodeId = $nodeId;
            }

            $isUp = false;
            if ($d->last_poll_status === 'success') {
                $isUp = true;
            } elseif ($d->last_poll_status === 'failed' || ($d->consecutive_failures ?? 0) > 0) {
                $isUp = false;
            } else {
                $ndObj = $netDevsByIp->get($d->ip_address);
                if ($ndObj && isset($latestDevHistories[$ndObj->id])) {
                    $isUp = (bool)$latestDevHistories[$ndObj->id];
                } else {
                    $isUp = (bool)$d->is_active;
                }
            }

            $nodes[] = [
                'data' => [
                    'id' => $nodeId,
                    'label' => $d->name, // Nombre Etiqueta corporativo (SSOT Regla #7)
                    'display_name' => $d->name,
                    'ip' => $d->ip_address,
                    'type' => $type,
                    'status' => $isUp ? 'up' : 'down',
                    'vendor' => $d->vendor ?? 'Cisco Systems',
                    'model' => $d->model ?? 'Dispositivo SNMP',
                    'uptime' => $d->uptime_formatted ?? 'N/A',
                    'site_name' => $d->site?->name ?? 'Sede Central',
                    'notes' => $d->notes ?? null,
                    'device_url' => route('admin.snmp.show', $d->id),
                    'source_module' => 'snmp',
                ],
                'classes' => "device-node node-{$type} " . ($isUp ? 'status-up' : 'status-down'),
            ];
        }

        // 3. Integración ITAM: Puestos de Trabajo y Activos Cómputo (Regla de Oro #8 Cero Silos & SSOT)
        $assetsQuery = InventoryAsset::with(['site', 'activeAssignment'])
            ->whereNotNull('ip_address')
            ->where('ip_address', '!=', '');

        if ($siteId) {
            $assetsQuery->where('monitored_site_id', $siteId);
        } else {
            // En vista global, mostrar puestos principales de Valle Seco (hasta 30)
            $assetsQuery->where('ip_address', 'like', '10.20.23.%')->take(30);
        }

        $assets = $assetsQuery->get();

        foreach ($assets as $ast) {
            if (isset($addedIps[$ast->ip_address])) {
                continue;
            }
            $nodeId = 'asset_' . $ast->id;
            if (isset($addedNodeIds[$nodeId])) continue;
            $addedNodeIds[$nodeId] = true;
            $addedIps[$ast->ip_address] = $nodeId;
            $nodeIps[$nodeId] = $ast->ip_address;

            // Determinar estado en vivo
            $netDev = $netDevsByIp->get($ast->ip_address);
            $netDevId = $ast->monitored_network_device_id ?: ($netDev?->id);
            $isUp = isset($latestDevHistories[$netDevId]) ? (bool)$latestDevHistories[$netDevId] : ($ast->status !== 'down');

            $responsable = $ast->activeAssignment?->recipient_name
                ?? $ast->custodian_name
                ?? 'No asignado';

            $type = match($ast->type) {
                'switch' => 'switch',
                'router' => 'router',
                'server' => 'server',
                'printer' => 'printer',
                default => 'workstation',
            };

            if ($type === 'switch' && !$primarySwitchNodeId) {
                $primarySwitchNodeId = $nodeId;
            }

            $nodes[] = [
                'data' => [
                    'id' => $nodeId,
                    'label' => $ast->name, // Nombre Etiqueta (SSOT Regla #7)
                    'display_name' => $ast->name,
                    'ip' => $ast->ip_address,
                    'type' => $type,
                    'status' => $isUp ? 'up' : 'down',
                    'vendor' => $ast->brand ?: 'Genérico',
                    'model' => $ast->model ?: 'Puesto de Trabajo',
                    'uptime' => 'Activo ITAM',
                    'site_name' => $ast->site?->name ?? 'Sede Central',
                    'responsable' => $responsable,
                    'asset_tag' => $ast->asset_tag,
                    'notes' => "Activo ITAM: {$ast->asset_tag} • Responsable: {$responsable}",
                    'device_url' => route('admin.inventory.assets.show', $ast->id),
                    'source_module' => 'inventory_asset',
                ],
                'classes' => "device-node node-{$type} " . ($isUp ? 'status-up' : 'status-down'),
            ];

            // Enlazar el puesto de trabajo al switch de su red/sede
            if ($primarySwitchNodeId && $primarySwitchNodeId !== $nodeId && in_array($type, ['workstation', 'server', 'printer'])) {
                $edgeKey = min($primarySwitchNodeId, $nodeId) . '---' . max($primarySwitchNodeId, $nodeId);
                if (!isset($addedEdgeKeys[$edgeKey])) {
                    $addedEdgeKeys[$edgeKey] = true;
                    $edges[] = [
                        'data' => [
                            'id' => 'edge_asset_' . $ast->id,
                            'source' => $primarySwitchNodeId,
                            'target' => $nodeId,
                            'label' => 'LAN Acceso',
                            'link_type' => 'ACCESS',
                            'status' => $isUp ? 'up' : 'down',
                        ],
                        'classes' => 'edge-link edge-access ' . ($isUp ? 'status-up' : 'status-down'),
                    ];
                }
            }
        }

        // 4. Dispositivos de red adicionales (MonitoredNetworkDevice que no están en SNMP ni en ITAM)
        $netDevQuery = MonitoredNetworkDevice::with('site');
        if ($siteId) {
            $netDevQuery->where('monitored_site_id', $siteId);
        } else {
            $netDevQuery->take(40);
        }
        $netDevices = $netDevQuery->get();

        foreach ($netDevices as $nd) {
            if (isset($addedIps[$nd->ip])) {
                continue;
            }
            $nodeId = 'netdev_' . $nd->id;
            if (isset($addedNodeIds[$nodeId])) continue;
            $addedNodeIds[$nodeId] = true;
            $addedIps[$nd->ip] = $nodeId;
            $nodeIps[$nodeId] = $nd->ip;

            $isSwitch = str_contains(strtolower($nd->name), 'sw')
                || str_contains(strtolower($nd->name), 'switch')
                || str_contains(strtolower($nd->model ?? ''), 'catalyst')
                || str_contains(strtolower($nd->model ?? ''), '2960')
                || str_contains(strtolower($nd->notes ?? ''), 'switch');
            $isRouter = str_contains(strtolower($nd->name), 'router');
            
            $type = 'host';
            if ($isSwitch) $type = 'switch';
            elseif ($isRouter) $type = 'router';

            if ($type === 'switch' && !$primarySwitchNodeId) {
                $primarySwitchNodeId = $nodeId;
            }

            $isUp = isset($latestDevHistories[$nd->id]) ? (bool)$latestDevHistories[$nd->id] : (bool)$nd->is_active;

            $nodes[] = [
                'data' => [
                    'id' => $nodeId,
                    'label' => $nd->name, // Nombre Etiqueta corporativo (SSOT Regla #7)
                    'display_name' => $nd->name,
                    'ip' => $nd->ip,
                    'type' => $type,
                    'status' => $isUp ? 'up' : 'down',
                    'vendor' => $nd->vendor_data ?? 'Cisco / Infraestructura',
                    'model' => $nd->model ?? 'Dispositivo de Red',
                    'uptime' => 'N/A',
                    'site_name' => $nd->site?->name ?? 'Sede Central',
                    'notes' => $nd->notes ?? null,
                    'device_url' => route('admin.devices.index'),
                    'source_module' => 'network_device',
                ],
                'classes' => "device-node node-{$type} " . ($isUp ? 'status-up' : 'status-down'),
            ];
        }

        // 5. Enlaces de Infraestructura (NetworkTopologyLink)
        $links = NetworkTopologyLink::with(['sourceDevice', 'targetDevice'])->get();
        $edgeId = 1;

        foreach ($links as $l) {
            $sourceId = 'snmp_' . $l->source_device_id;
            $targetId = $l->target_device_id ? ('snmp_' . $l->target_device_id) : null;

            if (!$targetId && preg_match('/\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b/', $l->target_hostname ?? '', $m)) {
                $targetIp = $m[1];
                if (isset($addedIps[$targetIp])) {
                    $targetId = $addedIps[$targetIp];
                }
            }

            if ($sourceId && $targetId && $sourceId !== $targetId && isset($addedNodeIds[$sourceId]) && isset($addedNodeIds[$targetId])) {
                $edgeKey = min($sourceId, $targetId) . '---' . max($sourceId, $targetId);
                if (isset($addedEdgeKeys[$edgeKey])) {
                    continue;
                }
                $addedEdgeKeys[$edgeKey] = true;

                $sIp = $nodeIps[$sourceId] ?? '';
                $tIp = $nodeIps[$targetId] ?? '';

                $edgeLabel = '';
                if (($sIp === '10.20.0.1' && $tIp === '10.20.23.1') || ($sIp === '10.20.23.1' && $tIp === '10.20.0.1')) $edgeLabel = 'WAN Troncal';
                elseif (($sIp === '10.20.0.1' && $tIp === '10.20.106.193') || ($sIp === '10.20.106.193' && $tIp === '10.20.0.1')) $edgeLabel = 'WAN Morón';
                elseif (($sIp === '10.20.23.1' && $tIp === '10.20.23.2') || ($sIp === '10.20.23.2' && $tIp === '10.20.23.1')) $edgeLabel = 'P48 (Uplink)';
                elseif (($sIp === '10.20.23.2' && $tIp === '10.20.23.4') || ($sIp === '10.20.23.4' && $tIp === '10.20.23.2')) $edgeLabel = 'P47 (SW02)';
                elseif (($sIp === '10.20.23.2' && $tIp === '10.20.23.5') || ($sIp === '10.20.23.5' && $tIp === '10.20.23.2')) $edgeLabel = 'P43 (SW03)';
                elseif (($sIp === '10.20.23.2' && $tIp === '10.20.27.83') || ($sIp === '10.20.27.83' && $tIp === '10.20.23.2')) $edgeLabel = 'P46 (Transmisión)';
                elseif (($sIp === '10.20.23.2' && $tIp === '10.20.107.131') || ($sIp === '10.20.107.131' && $tIp === '10.20.23.2')) $edgeLabel = 'P45 (Consolidado)';
                elseif (($sIp === '10.20.23.5' && $tIp === '10.20.23.252') || ($sIp === '10.20.23.252' && $tIp === '10.20.23.5')) $edgeLabel = 'Monitoreo';

                $edges[] = [
                    'data' => [
                        'id' => 'edge_' . ($l->id ?? $edgeId++),
                        'source' => $sourceId,
                        'target' => $targetId,
                        'label' => $edgeLabel,
                        'link_type' => strtoupper($l->link_type),
                        'status' => $l->link_status,
                    ],
                    'classes' => 'edge-link edge-' . strtolower($l->link_type) . ' ' . ($l->link_status === 'up' ? 'status-up' : 'status-down'),
                ];
            }
        }

        // 6. Si es vista macro (sin siteId), enlazar sedes remotas con el router troncal
        if (!$siteId) {
            $rootNodeId = $addedIps['10.20.23.1'] ?? ($addedIps['10.20.0.1'] ?? 'snmp_1');
            $sites = MonitoredSite::where('is_active', true)->where('id', '>', 1)->get();

            foreach ($sites as $st) {
                if (!empty($st->ip) && filter_var($st->ip, FILTER_VALIDATE_IP)) {
                    $siteNodeId = 'site_' . $st->id;
                    if (!isset($addedNodeIds[$siteNodeId])) {
                        $addedNodeIds[$siteNodeId] = true;
                        $nodes[] = [
                            'data' => [
                                'id' => $siteNodeId,
                                'label' => $st->name, // Nombre Etiqueta
                                'display_name' => $st->name,
                                'ip' => $st->ip,
                                'type' => 'router',
                                'status' => $st->last_check_status === 'DOWN' ? 'down' : 'up',
                                'vendor' => 'Gateway WAN',
                                'model' => 'Enlace Sede Remota',
                                'uptime' => 'N/A',
                                'site_name' => $st->name,
                                'notes' => "Enlace WAN oficial de sede {$st->name}",
                                'device_url' => route('admin.sites.index'),
                                'source_module' => 'monitored_site',
                            ],
                            'classes' => "device-node node-router " . ($st->last_check_status === 'DOWN' ? 'status-down' : 'status-up'),
                        ];

                        if ($rootNodeId && $rootNodeId !== $siteNodeId) {
                            $edgeKey = min($rootNodeId, $siteNodeId) . '---' . max($rootNodeId, $siteNodeId);
                            if (!isset($addedEdgeKeys[$edgeKey])) {
                                $addedEdgeKeys[$edgeKey] = true;
                                $edges[] = [
                                    'data' => [
                                        'id' => 'edge_site_' . $st->id,
                                        'source' => $rootNodeId,
                                        'target' => $siteNodeId,
                                        'label' => 'WAN ' . explode(' ', $st->name)[0],
                                        'link_type' => 'WAN',
                                        'status' => $st->last_check_status === 'DOWN' ? 'down' : 'up',
                                    ],
                                    'classes' => 'edge-link edge-wan ' . ($st->last_check_status === 'DOWN' ? 'status-down' : 'status-up'),
                                ];
                            }
                        }
                    }
                }
            }
        } else {
            $rootNodeId = $primarySwitchNodeId ?? ($addedIps['10.20.23.1'] ?? null);
        }

        return [
            'elements' => [
                'nodes' => $nodes,
                'edges' => $edges,
            ],
            'meta' => [
                'total_nodes' => count($nodes),
                'total_edges' => count($edges),
                'site_id' => $siteId,
                'root_id' => $rootNodeId,
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Reconstruye los enlaces de topología ejecutando el constructor backend.
     */
    public function rebuildTopology(): array
    {
        $builderScript = '/scripts/telegram-admin-bot/monitor/topology_builder.py';
        $pythonBin = '/scripts/telegram-admin-bot/venv/bin/python';

        if (file_exists($builderScript) && file_exists($pythonBin)) {
            $cmd = "{$pythonBin} {$builderScript} --build 2>&1";
            $output = @shell_exec($cmd);
            return [
                'success' => true,
                'message' => 'Topología de red re-evaluada con éxito.',
                'output' => $output ? trim($output) : '',
                'links_count' => NetworkTopologyLink::count(),
            ];
        }

        return [
            'success' => false,
            'message' => 'Script de reconstrucción no disponible en esta ruta.',
            'links_count' => NetworkTopologyLink::count(),
        ];
    }
}
