<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;
use App\Models\NetworkTopologyLink;
use App\Models\SnmpDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminTopologyController extends Controller
{
    /**
     * Muestra el mapa interactivo de topología de red con Cytoscape.js.
     */
    public function index(): View
    {
        $totalDevices = SnmpDevice::count() + MonitoredNetworkDevice::count();
        $totalLinks = NetworkTopologyLink::count();
        $activeLinks = NetworkTopologyLink::where('link_status', 'up')->count();
        $coreDevices = SnmpDevice::where('name', 'like', '%Core%')->orWhere('model', 'like', '%3750%')->count();

        $sites = MonitoredSite::where('is_active', true)->orderBy('name')->get();

        return view('admin.topology.index', compact(
            'totalDevices',
            'totalLinks',
            'activeLinks',
            'coreDevices',
            'sites'
        ));
    }

    /**
     * Devuelve los elementos de grafo (nodes & edges) para Cytoscape.js en JSON.
     */
    public function data(Request $request): JsonResponse
    {
        $nodes = [];
        $edges = [];
        $addedNodeIds = [];

        // 1. Nodos SNMP
        $snmpDevices = SnmpDevice::all();
        $addedIps = [];

        foreach ($snmpDevices as $d) {
            $nodeId = 'snmp_' . $d->id;
            $addedNodeIds[$nodeId] = true;
            if ($d->ip_address) {
                $addedIps[$d->ip_address] = $nodeId;
            }

            $isCore = str_contains(strtolower($d->name), 'core') || str_contains(strtolower($d->model ?? ''), '3750') || $d->ip_address === '10.20.23.1';
            $isFirewall = ($d->device_type === 'firewall') || str_contains(strtolower($d->name), 'pfsense') || str_contains(strtolower($d->vendor ?? ''), 'pfsense');
            $isRouter = ($d->device_type === 'router') || str_contains(strtolower($d->name), 'router') || str_contains(strtolower($d->model ?? ''), '2901');

            $type = 'switch';
            if ($isFirewall) $type = 'firewall';
            elseif ($isCore) $type = 'core_switch';
            elseif ($isRouter) $type = 'router';

            $nodes[] = [
                'data' => [
                    'id' => $nodeId,
                    'label' => $d->name,
                    'ip' => $d->ip_address,
                    'type' => $type,
                    'status' => $d->last_poll_status === 'success' ? 'up' : 'down',
                    'vendor' => $d->vendor ?? 'Cisco',
                    'model' => $d->model ?? 'Dispositivo',
                    'uptime' => $d->uptime_formatted ?? 'N/A',
                    'notes' => $d->notes ?? null,
                ],
                'classes' => "device-node node-{$type} " . ($d->last_poll_status === 'success' ? 'status-up' : 'status-down'),
            ];
        }

        // 2. Nodos de Equipos de Red Adicionales (excluyendo IPs ya representadas en SNMP)
        $netDevices = MonitoredNetworkDevice::take(30)->get();
        foreach ($netDevices as $nd) {
            // Omitir si la IP física ya está representada en el grafo
            if (isset($addedIps[$nd->ip])) {
                continue;
            }
            $nodeId = 'netdev_' . $nd->id;
            if (isset($addedNodeIds[$nodeId])) continue;
            $addedNodeIds[$nodeId] = true;
            $addedIps[$nd->ip] = $nodeId;

            $isSwitch = str_contains(strtolower($nd->name), 'sw') 
                || str_contains(strtolower($nd->name), 'switch')
                || str_contains(strtolower($nd->model ?? ''), 'catalyst')
                || str_contains(strtolower($nd->model ?? ''), '2960')
                || str_contains(strtolower($nd->notes ?? ''), 'switch');
            $isRouter = str_contains(strtolower($nd->name), 'router');
            $type = 'host';
            if ($isSwitch) $type = 'switch';
            elseif ($isRouter) $type = 'router';

            $nodes[] = [
                'data' => [
                    'id' => $nodeId,
                    'label' => $nd->name,
                    'ip' => $nd->ip,
                    'type' => $type,
                    'status' => $nd->is_up ? 'up' : 'down',
                    'vendor' => $nd->vendor_data ?? 'Host/Terminal',
                    'model' => $nd->model ?? 'Endpoint',
                    'uptime' => 'N/A',
                    'notes' => $nd->notes ?? null,
                ],
                'classes' => "device-node node-{$type} " . ($nd->is_up ? 'status-up' : 'status-down'),
            ];
        }

        // 3. Enlaces (Edges) de Topología
        $links = NetworkTopologyLink::with(['sourceDevice', 'targetDevice'])->get();
        $edgeId = 1;
        $addedEdgeKeys = [];

        foreach ($links as $l) {
            $sourceId = 'snmp_' . $l->source_device_id;
            $targetId = $l->target_device_id ? ('snmp_' . $l->target_device_id) : null;

            // Si el target no es SNMP pero existe IP exacta en el hostname
            if (!$targetId && preg_match('/\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b/', $l->target_hostname ?? '', $m)) {
                $targetIp = $m[1];
                if (isset($addedIps[$targetIp])) {
                    $targetId = $addedIps[$targetIp];
                }
            }

            // Validar que ambos extremos existan, sean diferentes y no dupliquen el enlace
            if ($sourceId && $targetId && $sourceId !== $targetId && isset($addedNodeIds[$sourceId]) && isset($addedNodeIds[$targetId])) {
                $edgeKey = min($sourceId, $targetId) . '---' . max($sourceId, $targetId);
                if (isset($addedEdgeKeys[$edgeKey])) {
                    continue; // Evitar enlaces dobles paralelos que forman bucles visuales
                }
                $addedEdgeKeys[$edgeKey] = true;

                // Identificar etiqueta de puerto o naturaleza del enlace
                $edgeLabel = '';
                if ($sourceId === 'snmp_4' && $targetId === 'snmp_1') $edgeLabel = 'WAN Troncal';
                elseif ($sourceId === 'snmp_4' && $targetId === 'snmp_9') $edgeLabel = 'WAN Morón';
                elseif ($sourceId === 'snmp_1' && $targetId === 'snmp_5') $edgeLabel = 'P48 (Uplink)';
                elseif ($sourceId === 'snmp_5' && $targetId === 'snmp_2') $edgeLabel = 'P47 (SW02)';
                elseif ($sourceId === 'snmp_5' && $targetId === 'snmp_6') $edgeLabel = 'P43 (SW03)';
                elseif ($sourceId === 'snmp_5' && $targetId === 'snmp_7') $edgeLabel = 'P46 (Transmisión)';
                elseif ($sourceId === 'snmp_5' && $targetId === 'snmp_8') $edgeLabel = 'P45 (Consolidado)';
                elseif ($sourceId === 'snmp_6' && $targetId === 'snmp_3') $edgeLabel = 'Monitoreo';
                elseif ($sourceId === 'snmp_2' && str_contains($l->target_hostname ?? '', '64')) $edgeLabel = 'WiFi';
                elseif ($sourceId === 'snmp_2' && str_contains($l->target_hostname ?? '', '232')) $edgeLabel = 'Acceso';

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

        return response()->json([
            'elements' => [
                'nodes' => $nodes,
                'edges' => $edges,
            ],
            'meta' => [
                'total_nodes' => count($nodes),
                'total_edges' => count($edges),
                'generated_at' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Reconstruye los enlaces de topología ejecutando el constructor backend.
     */
    public function rebuild(): RedirectResponse
    {
        $cmd = '/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/topology_builder.py --build 2>&1';
        $output = shell_exec($cmd);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'topology_rebuilt',
            'details' => 'Topología de red re-evaluada y reconstruida manualmente.',
            'ip_address' => request()->ip(),
        ]);

        return redirect()->route('admin.topology.index')
            ->with('status', 'Topología visual actualizada correctamente. ' . ($output ? trim(explode("\n", trim($output))[0]) : ''));
    }
}
