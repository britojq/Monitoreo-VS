<?php

declare(strict_types=1);

namespace App\Actions\Deploy;

use App\Contracts\GlpiEndpointConfigInterface;
use App\Contracts\HardwareDriftDetectorInterface;
use App\Models\DiscoveredDevice;
use App\Models\InventoryAsset;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;
use App\Services\Deployment\AgentDeployService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class FetchDeployDashboardDataAction
{
    public function __construct(
        protected AgentDeployService $deployService,
        protected HardwareDriftDetectorInterface $driftDetector,
        protected GlpiEndpointConfigInterface $endpointConfig
    ) {}

    /**
     * Procesa y unifica los dispositivos descubiertos y de sedes para el dashboard de despliegue.
     *
     * @param Request $request
     * @return array
     */
    public function execute(Request $request): array
    {
        $search = $request->query('search');
        $osFilter = $request->query('os');
        $statusFilter = $request->query('status');
        $siteFilter = $request->query('site');
        $scanSubnet = trim((string) $request->query('scan_subnet', ''));

        // 1. Catálogo de Sedes oficiales para el filtro visual y despliegue
        $sedes = MonitoredSite::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        // Mapa de subredes conocidas por sede para escaneo rápido
        $siteSubnets = [];
        foreach ($sedes as $s) {
            if (!empty($s->ip) && filter_var($s->ip, FILTER_VALIDATE_IP)) {
                $siteSubnets[$s->id] = preg_replace('/\.\d+$/', '.0/24', $s->ip);
            }
        }
        // Segmentos prioritarios de infraestructura
        $siteSubnets[1] = '192.168.1.0/24';   // SEDE VALLE SECO (Edificio Central)
        $siteSubnets[2] = '192.168.1.0/24';   // CIAU VALLE SECO (Oficina Comercial)
        $siteSubnets[3] = '192.168.107.0/24';  // CIAU CONSOLIDADO
        $siteSubnets[4] = '192.168.107.0/24';  // CIAU PASEO MARIÑO
        $siteSubnets[5] = '192.168.106.0/24';  // CIAU MORON
        $siteSubnets[7] = '192.168.27.0/24';   // CORDINACION TRASMISION

        // 2. Activos de inventario existentes para cotejar estado GLPI por IP y por MAC (Deduplicación DHCP)
        $inventoryAssets = InventoryAsset::all()->keyBy('ip_address');
        $inventoryAssetsByMac = InventoryAsset::whereNotNull('mac_address')
            ->get()
            ->filter(fn($a) => !empty($a->mac_address) && !str_starts_with($a->mac_address, 'No') && !str_starts_with($a->mac_address, 'IP:'))
            ->keyBy(fn($a) => strtoupper(trim((string)$a->mac_address)));

        // 3. Obtener Equipos Monitoreados de Sedes (MonitoredSiteDevice)
        $monitoredQuery = MonitoredSiteDevice::with('site')->orderBy('name');
        if (!empty($siteFilter) && is_numeric($siteFilter)) {
            $monitoredQuery->where('monitored_site_id', $siteFilter);
        }
        $monitoredDevices = $monitoredQuery->get();

        // 4. Obtener Dispositivos Descubiertos por Escaneo de Red (DiscoveredDevice)
        $allDiscoveredByIp = DiscoveredDevice::all()->keyBy('ip_address');
        $discoveredDevices = collect();

        if (!empty($scanSubnet)) {
            $discoveredDevices = DiscoveredDevice::where('is_active', 1)->get()->filter(function ($dev) use ($scanSubnet) {
                return $this->deployService->ipInSubnet($dev->ip_address, $scanSubnet);
            });
        } elseif ($siteFilter === 'discovered') {
            $discoveredDevices = DiscoveredDevice::where('is_active', 1)->orderBy('last_seen', 'desc')->get();
        }

        $combinedByIp = [];

        // Procesar Equipos Monitoreados (Sedes Oficiales)
        if ($siteFilter !== 'discovered') {
            foreach ($monitoredDevices as $m) {
                if (empty($m->ip)) {
                    continue;
                }

                $ip = trim((string)$m->ip);

                if (!empty($scanSubnet)) {
                    if (!$this->deployService->ipInSubnet($ip, $scanSubnet)) {
                        continue;
                    }
                    $discoveredForCheck = $allDiscoveredByIp->get($ip);
                    if ($discoveredForCheck && !$discoveredForCheck->is_active) {
                        continue;
                    }
                }

                $asset = $inventoryAssets->get($ip);

                $isRegisteredNonPc = false;
                if ($asset && in_array($asset->type, ['switch', 'router', 'printer', 'ip_phone', 'firewall', 'access_point', 'ups', 'network', 'peripheral'], true)) {
                    $isRegisteredNonPc = true;
                } elseif (!$asset) {
                    $rawCheck = strtolower(($m->name ?? '') . ' ' . ($m->model ?? ''));
                    if (str_contains($rawCheck, 'switch') || str_contains($rawCheck, 'cisco') || str_contains($rawCheck, 'router') || str_contains($rawCheck, 'impresora')) {
                        $isRegisteredNonPc = true;
                    }
                }

                if ($isRegisteredNonPc) {
                    continue;
                }

                $rawOs = strtolower(($m->model ?? '') . ' ' . ($m->notes ?? '') . ' ' . ($m->name ?? '') . ' ' . ($asset->operating_system ?? ''));
                $ports = (string)($m->ports ?? '');
                $discovered = $allDiscoveredByIp->get($ip);

                $mac = $m->mac ?: ($discovered?->mac_address ?: 'No registrada');
                if ($discovered && $discovered->open_ports) {
                    $discPortsStr = is_array($discovered->open_ports) ? implode(' ', $discovered->open_ports) : (string)$discovered->open_ports;
                    $ports = trim($ports . ' ' . $discPortsStr);
                }
                if ($discovered && $discovered->os_detected) {
                    $rawOs .= ' ' . strtolower($discovered->os_detected);
                }

                $combinedByIp[$ip] = [
                    'id' => 'monitored-' . $m->id,
                    'raw_id' => $m->id,
                    'ip' => $ip,
                    'hostname' => $m->name ?: 'Equipo Sede',
                    'site_id' => $m->monitored_site_id,
                    'site_name' => $m->site?->name ?? 'Sede Corporativa',
                    'source' => $discovered ? 'both' : 'monitored',
                    'source_label' => $discovered ? 'Sede & Detectado en Red' : 'Equipo de Sede Monitoreado',
                    'source_badge' => 'cyan',
                    'mac' => $mac,
                    'vendor' => $m->model ?: 'Estación de Trabajo / PC',
                    'notes' => $m->notes,
                    'open_ports' => $ports,
                    'raw_os' => $rawOs,
                    'asset' => $asset,
                    'discovered_device_type' => $discovered?->device_type,
                    'last_seen' => $discovered?->last_seen ? $discovered->last_seen->diffForHumans() : 'Monitoreo Activo',
                    'is_new_discovery' => false,
                    'is_ip_reassigned' => false,
                    'previous_ip' => null,
                    'reassigned_asset_name' => null,
                ];
            }
        }

        // Procesar Dispositivos Descubiertos
        foreach ($discoveredDevices as $dev) {
            $ip = trim((string)$dev->ip_address);
            if (empty($ip)) {
                continue;
            }

            $cleanMac = (!empty($dev->mac_address) && !str_starts_with($dev->mac_address, 'No') && !str_starts_with($dev->mac_address, 'IP:'))
                ? strtoupper(trim($dev->mac_address))
                : null;
            $assetByMac = $cleanMac ? $inventoryAssetsByMac->get($cleanMac) : null;
            $asset = $inventoryAssets->get($ip) ?? $assetByMac;

            $isIpReassigned = ($assetByMac && !empty($assetByMac->ip_address) && $assetByMac->ip_address !== $ip);
            $previousIp = $isIpReassigned ? $assetByMac->ip_address : null;
            $reassignedAssetName = $isIpReassigned ? $assetByMac->name : null;

            if ($asset && in_array($asset->type, ['switch', 'router', 'printer', 'ip_phone', 'firewall', 'access_point', 'ups', 'network', 'peripheral'], true)) {
                continue;
            }
            $rawOs = strtolower(($dev->os_detected ?? '') . ' ' . ($dev->vendor ?? '') . ' ' . ($asset->operating_system ?? ''));
            $ports = is_array($dev->open_ports) ? implode(' ', $dev->open_ports) : (string)($dev->open_ports ?? '');

            if (isset($combinedByIp[$ip])) {
                $combinedByIp[$ip]['source'] = 'both';
                $combinedByIp[$ip]['source_label'] = 'Sede & Detectado en Red';
                if ($dev->mac_address && $combinedByIp[$ip]['mac'] === 'No registrada') {
                    $combinedByIp[$ip]['mac'] = $dev->mac_address;
                }
                if (!empty($ports)) {
                    $combinedByIp[$ip]['open_ports'] = trim(($combinedByIp[$ip]['open_ports'] ?? '') . ' ' . $ports);
                }
                $combinedByIp[$ip]['raw_os'] .= ' ' . $rawOs;
                if ($dev->os_detected && empty($combinedByIp[$ip]['os_detected'])) {
                    $combinedByIp[$ip]['os_detected'] = $dev->os_detected;
                }
            } else {
                $isScanMode = !empty($scanSubnet);
                $combinedByIp[$ip] = [
                    'id' => 'discovered-' . $dev->id,
                    'raw_id' => $dev->id,
                    'ip' => $ip,
                    'hostname' => $dev->hostname ?: ($asset->name ?? 'Host-' . str_replace('.', '-', $ip)),
                    'site_id' => 'discovered',
                    'site_name' => $isScanMode ? "Subred {$scanSubnet}" : 'Descubrimiento de Red (ARP)',
                    'source' => $isScanMode ? 'scan' : 'discovered',
                    'source_label' => $isScanMode ? "Nuevo Hallazgo en {$scanSubnet}" : 'Escaneo Automático de Red',
                    'source_badge' => $isScanMode ? 'emerald' : 'amber',
                    'mac' => $dev->mac_address ?: 'No registrada',
                    'vendor' => $dev->vendor ?: ($asset->brand ?? 'Genérico / Desconocido'),
                    'notes' => null,
                    'open_ports' => $ports,
                    'raw_os' => $rawOs,
                    'os_detected' => $dev->os_detected,
                    'asset' => $asset,
                    'discovered_device_type' => $dev->device_type,
                    'last_seen' => $dev->last_seen ? $dev->last_seen->diffForHumans() : 'Reciente',
                    'is_new_discovery' => $isScanMode,
                    'is_ip_reassigned' => $isIpReassigned,
                    'previous_ip' => $previousIp,
                    'reassigned_asset_name' => $reassignedAssetName,
                ];
            }
        }

        $devicesList = [];
        $totalWindows = 0;
        $totalLinux = 0;
        $totalReporting = 0;
        $totalPending = 0;
        $totalUnregistered = 0;
        $totalUnsupported = 0;
        $totalMonitored = 0;
        $totalDiscovered = 0;

        foreach ($combinedByIp as $item) {
            $rawOs = (string)$item['raw_os'];
            $ports = (string)$item['open_ports'];
            $asset = $item['asset'];

            $osType = 'unknown';
            $osLabel = 'Desconocido';
            $osArch = 'x86/x64';

            $assetOs = strtolower($asset->operating_system ?? '');
            if (!empty($assetOs) && !str_contains($assetOs, 'glpi agent')) {
                if (str_contains($assetOs, 'linux') || str_contains($assetOs, 'debian') || str_contains($assetOs, 'ubuntu')) {
                    $osType = 'linux';
                    $osLabel = $asset->operating_system;
                    $osArch = str_contains($assetOs, '64') ? '64-bit' : '32-bit';
                    $totalLinux++;
                } elseif (str_contains($assetOs, 'windows')) {
                    $osType = 'windows';
                    $osLabel = $asset->operating_system;
                    $osArch = (str_contains($assetOs, '64') && !str_contains($assetOs, '32')) ? '64-bit' : '32-bit / x86';
                    $totalWindows++;
                }
            }

            if ($osType === 'unknown' && !empty($item['os_detected'])) {
                $detOsLower = strtolower($item['os_detected']);
                if (str_contains($detOsLower, 'windows')) {
                    $osType = 'windows';
                    $osLabel = $item['os_detected'];
                    $osArch = str_contains($detOsLower, '64') ? '64-bit' : '32-bit / x86';
                    $totalWindows++;
                } elseif (str_contains($detOsLower, 'linux') || str_contains($detOsLower, 'unix')) {
                    $osType = 'linux';
                    $osLabel = $item['os_detected'];
                    $osArch = '64-bit';
                    $totalLinux++;
                }
            }

            if ($osType === 'unknown') {
                $isLinux = str_contains($rawOs, 'linux') || str_contains($rawOs, 'ubuntu') ||
                           str_contains($rawOs, 'xubuntu') || str_contains($rawOs, 'debian') ||
                           (str_contains($ports, '22') && !str_contains($ports, '445'));

                $isWindows = str_contains($rawOs, 'windows') || str_contains($rawOs, 'win7') ||
                             str_contains($rawOs, 'win10') || str_contains($rawOs, 'win11') ||
                             str_contains($ports, '445') || str_contains($ports, '135') || str_contains($ports, '3389');

                if ($isLinux) {
                    $osType = 'linux';
                    $osLabel = 'Linux / Debian';
                    $osArch = str_contains($rawOs, '64') ? '64-bit' : '32-bit';
                    $totalLinux++;
                } elseif ($isWindows) {
                    $osType = 'windows';
                    $osArch = (str_contains($rawOs, '64') && !str_contains($rawOs, '32')) || str_contains($rawOs, 'x64') ? '64-bit' : '32-bit / x86';
                    $osLabel = "Windows ({$osArch})";
                    $totalWindows++;
                }
            }

            if ($item['source'] === 'monitored' || $item['source'] === 'both') {
                $totalMonitored++;
            }
            if ($item['source'] === 'discovered' || $item['source'] === 'both') {
                $totalDiscovered++;
            }

            $isGlpiSupported = $this->deployService->isGlpiSupportedDevice($item, $asset, $osType);
            $isUnregistered = ($asset === null);
            $suggestedType = $this->deployService->suggestDeviceType($item);

            $isReporting = false;
            $lastSyncHuman = null;

            if ($asset && $asset->last_glpi_sync_at) {
                $isReporting = true;
                $lastSyncHuman = $asset->last_glpi_sync_at->diffForHumans();
                $totalReporting++;
            } elseif ($isUnregistered) {
                $totalUnregistered++;
            } elseif ($isGlpiSupported) {
                $totalPending++;
            } else {
                $totalUnsupported++;
            }

            if (!empty($search)) {
                $searchLower = strtolower($search);
                $match = str_contains(strtolower((string)$item['ip']), $searchLower) ||
                         str_contains(strtolower((string)$item['hostname']), $searchLower) ||
                         str_contains(strtolower((string)$item['site_name']), $searchLower) ||
                         str_contains(strtolower((string)$item['vendor']), $searchLower) ||
                         str_contains(strtolower((string)$item['mac']), $searchLower);
                if (!$match) {
                    continue;
                }
            }

            if (!empty($osFilter) && $osFilter !== 'all' && $osType !== $osFilter) {
                continue;
            }

            if ($statusFilter === 'reporting' && !$isReporting) {
                continue;
            } elseif ($statusFilter === 'pending' && ($isReporting || !$isGlpiSupported || $isUnregistered)) {
                continue;
            } elseif ($statusFilter === 'unregistered' && !$isUnregistered) {
                continue;
            } elseif ($statusFilter === 'unsupported' && ($isGlpiSupported || $isUnregistered)) {
                continue;
            }

            $devicesList[] = [
                'id' => $item['id'],
                'ip' => $item['ip'],
                'hostname' => $item['hostname'],
                'site_id' => $item['site_id'],
                'site_name' => $item['site_name'],
                'source' => $item['source'],
                'source_label' => $item['source_label'],
                'source_badge' => $item['source_badge'],
                'mac' => $item['mac'],
                'vendor' => $item['vendor'],
                'notes' => $item['notes'],
                'os_type' => $osType,
                'os_label' => $osLabel,
                'os_arch' => $osArch,
                'is_reporting' => $isReporting,
                'is_glpi_supported' => $isGlpiSupported,
                'is_unregistered' => $isUnregistered,
                'suggested_type' => $suggestedType,
                'is_new_discovery' => $item['is_new_discovery'] ?? false,
                'is_ip_reassigned' => $item['is_ip_reassigned'] ?? false,
                'previous_ip' => $item['previous_ip'] ?? null,
                'reassigned_asset_name' => $item['reassigned_asset_name'] ?? null,
                'last_sync' => $lastSyncHuman,
                'asset_id' => $asset?->id,
                'asset_status' => $asset?->status,
                'last_seen' => $item['last_seen'],
            ];
        }

        usort($devicesList, function ($a, $b) {
            if ($a['source'] !== $b['source']) {
                if ($a['source'] === 'monitored' || $a['source'] === 'both') return -1;
                return 1;
            }
            return ip2long($a['ip']) <=> ip2long($b['ip']);
        });

        $perPage = 15;
        $page = (int) $request->query('page', 1);
        $offset = ($page - 1) * $perPage;
        $sliced = array_slice($devicesList, $offset, $perPage);
        $paginatedDevices = new LengthAwarePaginator(
            $sliced,
            count($devicesList),
            $perPage,
            $page,
            ['path' => route('admin.inventory.deploy'), 'query' => $request->query()]
        );

        $stats = [
            'total' => count($combinedByIp),
            'monitored' => $totalMonitored,
            'discovered' => $totalDiscovered,
            'windows' => $totalWindows,
            'linux' => $totalLinux,
            'reporting' => $totalReporting,
            'pending' => $totalPending,
            'unregistered' => $totalUnregistered,
            'unsupported' => $totalUnsupported,
        ];

        $driftSummary = $this->driftDetector->getDriftSummary();
        $driftAssets = $this->driftDetector->getAssetsWithDrift();
        $glpiEndpoints = $this->endpointConfig->getEndpointsConfig();

        return [
            'paginatedDevices' => $paginatedDevices,
            'stats' => $stats,
            'sedes' => $sedes,
            'siteSubnets' => $siteSubnets,
            'scanSubnet' => $scanSubnet,
            'driftSummary' => $driftSummary,
            'driftAssets' => $driftAssets,
            'glpiEndpoints' => $glpiEndpoints,
        ];
    }
}
