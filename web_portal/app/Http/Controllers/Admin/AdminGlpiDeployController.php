<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\GlpiAgentTriggerInterface;
use App\Contracts\GlpiEndpointConfigInterface;
use App\Contracts\HardwareDriftDetectorInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeployAgentRequest;
use App\Http\Requests\Admin\ScanSubnetRequest;
use App\Http\Requests\Admin\UpdateGlpiEndpointsRequest;
use App\Models\AuditLog;
use App\Models\DiscoveredDevice;
use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;
use App\Services\Deployment\AgentDeployService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controlador para la interfaz de despliegue remoto de agentes GLPI.
 * Diseñado bajo arquitectura Slim Controller (Principio S y D).
 * La ejecución de procesos y validación se delegan a AgentDeployService y FormRequests.
 */
class AdminGlpiDeployController extends Controller
{
    public function __construct(
        protected AgentDeployService $deployService,
        protected HardwareDriftDetectorInterface $driftDetector,
        protected GlpiAgentTriggerInterface $agentTrigger,
        protected GlpiEndpointConfigInterface $endpointConfig
    ) {}

    /**
     * Muestra la interfaz visual interactiva de Despliegue Remoto de Agente GLPI.
     * Unifica los Equipos Formalmente Monitoreados de Sedes (CIAU) y los descubiertos por red (ARP).
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $osFilter = $request->query('os');
        $statusFilter = $request->query('status'); // reporting, pending, unsupported
        $siteFilter = $request->query('site'); // all, monitored, discovered, or site_id
        $scanSubnet = trim($request->query('scan_subnet', ''));

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
            ->keyBy(fn($a) => strtoupper(trim($a->mac_address)));

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

                $ip = trim($m->ip);

                // Si hay escaneo de subred activo, excluir equipos fuera de esa subred o apagados
                if (!empty($scanSubnet)) {
                    if (!$this->deployService->ipInSubnet($ip, $scanSubnet)) {
                        continue;
                    }
                    $discoveredForCheck = $allDiscoveredByIp->get($ip);
                    if ($discoveredForCheck && !$discoveredForCheck->is_active) {
                        continue; // PC apagada: no mostrar
                    }
                }

                $asset = $inventoryAssets->get($ip);

                // Excluir equipos de sede ya registrados formalmente que NO son PC (Switches, Routers, Impresoras, etc.)
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
                    continue; // Excluir de la vista de despliegue de agente GLPI (Routers, Switches, etc.)
                }

                $rawOs = strtolower(($m->model ?? '') . ' ' . ($m->notes ?? '') . ' ' . ($m->name ?? '') . ' ' . ($asset->operating_system ?? ''));
                $ports = $m->ports ?? '';
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

        // Procesar Dispositivos Descubiertos (SOLO si se escaneó una subred o se filtró 'discovered')
        foreach ($discoveredDevices as $dev) {
            $ip = trim($dev->ip_address);
            if (empty($ip)) {
                continue;
            }

            $cleanMac = (!empty($dev->mac_address) && !str_starts_with($dev->mac_address, 'No') && !str_starts_with($dev->mac_address, 'IP:')) ? strtoupper(trim($dev->mac_address)) : null;
            $assetByMac = $cleanMac ? $inventoryAssetsByMac->get($cleanMac) : null;
            $asset = $inventoryAssets->get($ip) ?? $assetByMac;

            // Detección de reasignación de IP por DHCP (La MAC ya existía con otra IP)
            $isIpReassigned = ($assetByMac && !empty($assetByMac->ip_address) && $assetByMac->ip_address !== $ip);
            $previousIp = $isIpReassigned ? $assetByMac->ip_address : null;
            $reassignedAssetName = $isIpReassigned ? $assetByMac->name : null;

            // Excluir dispositivos descubiertos que ya están registrados en inventario como no-PC
            if ($asset && in_array($asset->type, ['switch', 'router', 'printer', 'ip_phone', 'firewall', 'access_point', 'ups', 'network', 'peripheral'], true)) {
                continue;
            }
            $rawOs = strtolower(($dev->os_detected ?? '') . ' ' . ($dev->vendor ?? '') . ' ' . ($asset->operating_system ?? ''));
            $ports = is_array($dev->open_ports) ? implode(' ', $dev->open_ports) : (string)($dev->open_ports ?? '');

            if (isset($combinedByIp[$ip])) {
                // Ya existe como equipo monitoreado formal: enriquecer datos
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

        // Deducción de SO, conteos y filtros
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
            $rawOs = $item['raw_os'];
            $ports = $item['open_ports'];
            $asset = $item['asset'];

            $osType = 'unknown';
            $osLabel = 'Desconocido';
            $osArch = 'x86/x64';

            // 1. Detección autoritativa si el activo ya tiene SO registrado por GLPI
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

            // 2. Detección por huella digital de escaneo (os_detected en DiscoveredDevice)
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

            // 3. Detección heurística por red y notas si aún no se determinó
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

            // Determinar si es un equipo soportado (Estación de Trabajo PC) o sin soporte
            $isGlpiSupported = $this->deployService->isGlpiSupportedDevice($item, $asset, $osType);
            $isUnregistered = ($asset === null);
            $suggestedType = $this->deployService->suggestDeviceType($item);

            // Estado del Agente GLPI
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

            // Aplicar Filtro de Búsqueda
            if (!empty($search)) {
                $searchLower = strtolower($search);
                $match = str_contains(strtolower($item['ip']), $searchLower) ||
                         str_contains(strtolower($item['hostname']), $searchLower) ||
                         str_contains(strtolower($item['site_name']), $searchLower) ||
                         str_contains(strtolower($item['vendor']), $searchLower) ||
                         str_contains(strtolower($item['mac']), $searchLower);
                if (!$match) {
                    continue;
                }
            }

            // Aplicar Filtro de SO
            if (!empty($osFilter) && $osFilter !== 'all' && $osType !== $osFilter) {
                continue;
            }

            // Aplicar Filtro de Estado GLPI
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

        // Ordenar primero los equipos monitoreados de sedes, luego por IP
        usort($devicesList, function ($a, $b) {
            if ($a['source'] !== $b['source']) {
                if ($a['source'] === 'monitored' || $a['source'] === 'both') return -1;
                return 1;
            }
            return ip2long($a['ip']) <=> ip2long($b['ip']);
        });

        // Paginación manual de la colección
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

        return view('admin.inventory.deploy.index', compact('paginatedDevices', 'stats', 'sedes', 'siteSubnets', 'scanSubnet', 'driftSummary', 'driftAssets', 'glpiEndpoints'));
    }

    /**
     * Actualiza las configuraciones de endpoints y tokens GLPI en MariaDB.
     */
    public function updateEndpoints(UpdateGlpiEndpointsRequest $request): JsonResponse
    {
        $this->endpointConfig->updateEndpoints($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Configuración de endpoints GLPI actualizada correctamente en MariaDB.',
            'endpoints' => $this->endpointConfig->getEndpointsConfig(),
        ]);
    }

    /**
     * Ejecuta el despliegue o conmutación remota vía AJAX hacia un equipo seleccionado.
     */
    public function deploy(DeployAgentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->deployService->deploy(
            $validated['host'],
            $validated['os'],
            $validated['user'],
            $validated['target'],
            $validated['action'],
            $validated['password'] ?? null,
            $validated['domain'] ?? null,
            $validated['custom_ip'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Consulta rápida de verificación en base de datos.
     */
    public function quickStatus(Request $request): JsonResponse
    {
        $result = $this->deployService->quickStatus($request->input('ip'));
        $statusCode = $result['status_code'] ?? 200;
        unset($result['status_code']);

        return response()->json($result, $statusCode);
    }

    /**
     * Incorpora un dispositivo descubierto al inventario oficial ITAM y red.
     */
    public function promoteToAsset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => ['required', 'ip'],
            'mac' => ['nullable', 'string', 'max:48'],
            'name' => ['required', 'string', 'max:128'],
            'type' => ['required', 'string', 'in:desktop,laptop,server,printer,router,switch,firewall,access_point,ip_phone,ups'],
            'monitored_site_id' => ['required', 'exists:monitored_sites,id'],
            'brand' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
        ]);

        $ip = trim($validated['ip']);
        $mac = !empty($validated['mac']) && !str_starts_with($validated['mac'], 'No') && !str_starts_with($validated['mac'], 'IP:') ? trim($validated['mac']) : null;
        $siteId = (int)$validated['monitored_site_id'];
        $type = $validated['type'];
        $name = trim($validated['name']);

        // 1. Crear o actualizar activo en inventory_assets (Deduplicación por MAC)
        $cleanMac = (!empty($validated['mac']) && !str_starts_with($validated['mac'], 'No') && !str_starts_with($validated['mac'], 'IP:')) ? strtoupper(trim($validated['mac'])) : null;

        $asset = null;
        if ($cleanMac) {
            $asset = InventoryAsset::where('mac_address', $cleanMac)->first();
        }
        if (!$asset) {
            $asset = InventoryAsset::where('ip_address', $ip)->first();
        }

        $isWorkstation = in_array($type, ['desktop', 'laptop', 'server'], true);
        $status = $isWorkstation ? 'in_stock' : 'in_use';
        $ipChanged = false;
        $oldIp = null;

        $defaultAccessType = ($type === 'printer') ? 'WEB' : 'SIN SOPORTE';
        $defaultAccessPort = ($type === 'printer') ? 80 : null;
        $defaultWebPort = ($type === 'printer') ? 80 : null;

        if (!$asset) {
            $asset = InventoryAsset::create([
                'name' => $name,
                'type' => $type,
                'status' => $status,
                'ip_address' => $ip,
                'mac_address' => $cleanMac,
                'monitored_site_id' => $siteId,
                'brand' => $validated['brand'] ?? null,
                'model' => $validated['model'] ?? null,
                'notes' => $validated['notes'] ?? 'Catalogado e incorporado desde Despliegue / Escaneo de Red',
                'is_active' => true,
                'access_type' => $defaultAccessType,
                'access_port' => $defaultAccessPort,
                'web_port' => $defaultWebPort,
            ]);
        } else {
            $oldIp = $asset->ip_address;
            $ipChanged = ($oldIp && $oldIp !== $ip);

            $updateData = [
                'name' => $name,
                'type' => $type,
                'ip_address' => $ip,
                'monitored_site_id' => $siteId,
                'mac_address' => $cleanMac ?: $asset->mac_address,
                'brand' => $validated['brand'] ?? $asset->brand,
                'model' => $validated['model'] ?? $asset->model,
                'notes' => $validated['notes'] ?? $asset->notes,
            ];

            if ($type === 'printer' && (empty($asset->access_type) || $asset->access_type === 'SIN SOPORTE')) {
                $updateData['access_type'] = 'WEB';
                $updateData['access_port'] = 80;
                $updateData['web_port'] = 80;
            }

            $asset->update($updateData);

            if ($ipChanged) {
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'user_name' => Auth::user()?->name ?? 'Administrador',
                    'event' => 'ip_reassigned',
                    'module' => 'inventory',
                    'auditable_type' => InventoryAsset::class,
                    'auditable_id' => $asset->id,
                    'entity_name' => $name,
                    'description' => "Reasignación DHCP detectada para {$name} (MAC {$cleanMac}): IP migró de {$oldIp} a {$ip}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        // 2. Si es dispositivo de infraestructura de red o impresora, vincular en MonitoredNetworkDevice
        if (in_array($type, ['router', 'switch', 'server', 'printer', 'firewall', 'access_point'], true)) {
            $nextDevNum = (MonitoredNetworkDevice::where('monitored_site_id', $siteId)->max('device_number') ?? 0) + 1;
            $netDev = MonitoredNetworkDevice::firstOrCreate(
                ['ip' => $ip],
                [
                    'monitored_site_id' => $siteId,
                    'device_number' => $nextDevNum,
                    'name' => $name,
                    'mac' => $cleanMac,
                    'model' => $validated['model'] ?? ucfirst($type),
                    'serial' => !empty($validated['serial']) ? $validated['serial'] : ($asset->serial_number ?: null),
                    'ports' => !empty($validated['ports']) ? $validated['ports'] : null,
                    'access_type' => $defaultAccessType,
                    'access_port' => $defaultAccessPort,
                    'web_port' => $defaultWebPort,
                    'notes' => $validated['notes'] ?? 'Incorporado desde Escaneo de Red',
                    'is_active' => true,
                ]
            );
            $asset->update(['monitored_network_device_id' => $netDev->id]);
        }

        // 3. Auto-conciliar en DiscoveredDevice
        DiscoveredDevice::where('ip_address', $ip)->update([
            'classification_status' => 'clasificado',
            'is_authorized' => 1,
            'linked_network_device_id' => $asset->id,
            'site_id' => $siteId,
            'device_type' => $this->mapAssetTypeToDiscoveredDeviceType($type),
        ]);

        // 4. Auditoría
        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Administrador',
            'event' => 'promote_to_asset',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $asset->id,
            'entity_name' => $name,
            'description' => "Dispositivo {$ip} ({$name}) catalogado e incorporado al inventario como {$type} desde Despliegue.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $action = $isWorkstation ? 'registered' : 'moved';
        $typeLabels = [
            'printer' => 'Impresora de Red',
            'switch' => 'Switch de Red',
            'router' => 'Router / Gateway',
            'ip_phone' => 'Teléfono VoIP',
            'access_point' => 'Punto de Acceso (AP)',
            'desktop' => 'Estación de Trabajo (PC)',
            'laptop' => 'Computadora Portátil',
            'server' => 'Servidor',
            'ups' => 'UPS / Respaldo Eléctrico',
            'firewall' => 'Firewall',
        ];
        $typeLabel = $typeLabels[$type] ?? ucfirst($type);

        if ($action === 'moved') {
            $msg = "El equipo '{$name}' ({$ip}) fue catalogado exitosamente como {$typeLabel} y movido a Equipos & Activos.";
        } else {
            $msg = "El puesto '{$name}' ({$ip}) fue registrado en inventario como {$typeLabel} y quedó habilitado para despliegue de agente GLPI.";
        }

        if ($ipChanged) {
            $msg .= " (IP reasignada por DHCP actualizada: antes {$oldIp})";
        }

        return response()->json([
            'success' => true,
            'action' => $action,
            'message' => $msg,
            'asset_id' => $asset->id,
            'ip' => $ip,
            'name' => $name,
            'type' => $type,
            'ip_changed' => $ipChanged,
            'previous_ip' => $oldIp,
        ]);
    }

    /**
     * Incorpora múltiples dispositivos descubiertos por lote al inventario oficial ITAM.
     */
    public function bulkPromote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'devices' => ['required', 'array', 'min:1'],
            'devices.*.ip' => ['required', 'ip'],
            'devices.*.mac' => ['nullable', 'string', 'max:48'],
            'devices.*.hostname' => ['nullable', 'string', 'max:128'],
            'devices.*.vendor' => ['nullable', 'string', 'max:64'],
            'type' => ['required', 'string', 'in:desktop,laptop,server,printer,router,switch,firewall,access_point,ip_phone,ups'],
            'monitored_site_id' => ['required', 'exists:monitored_sites,id'],
            'name_prefix' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
        ]);

        $siteId = (int)$validated['monitored_site_id'];
        $type = $validated['type'];
        $namePrefix = trim($validated['name_prefix'] ?? '');
        $notes = $validated['notes'] ?? 'Catalogado por lote desde Despliegue de Red';
        $isWorkstation = in_array($type, ['desktop', 'laptop', 'server'], true);
        $status = $isWorkstation ? 'in_stock' : 'in_use';

        $processedCount = 0;
        $updatedIpCount = 0;
        $createdCount = 0;
        $processedIps = [];

        foreach ($validated['devices'] as $idx => $d) {
            $ip = trim($d['ip']);
            $mac = !empty($d['mac']) && !str_starts_with($d['mac'], 'No') && !str_starts_with($d['mac'], 'IP:') ? strtoupper(trim($d['mac'])) : null;
            $vendor = trim($d['vendor'] ?? '');
            $hostname = trim($d['hostname'] ?? '');

            // Determinar Nombre Etiqueta corporativo
            if (!empty($namePrefix)) {
                $name = count($validated['devices']) > 1 ? "{$namePrefix} " . str_pad($idx + 1, 2, '0', STR_PAD_LEFT) : $namePrefix;
            } elseif (!empty($hostname) && !str_starts_with($hostname, 'Host-')) {
                $name = $hostname;
            } else {
                $name = "PUESTO {$ip}";
            }

            // Prioridad 1: Buscar por MAC para deduplicar
            $asset = null;
            if ($mac) {
                $asset = InventoryAsset::where('mac_address', $mac)->first();
            }
            // Prioridad 2: Buscar por IP
            if (!$asset) {
                $asset = InventoryAsset::where('ip_address', $ip)->first();
            }

            if (!$asset) {
                $asset = InventoryAsset::create([
                    'name' => $name,
                    'type' => $type,
                    'status' => $status,
                    'ip_address' => $ip,
                    'mac_address' => $mac,
                    'monitored_site_id' => $siteId,
                    'brand' => $vendor ?: null,
                    'notes' => $notes,
                    'is_active' => true,
                ]);
                $createdCount++;
            } else {
                $oldIp = $asset->ip_address;
                $ipChanged = ($oldIp && $oldIp !== $ip);

                $asset->update([
                    'name' => $asset->name ?: $name,
                    'type' => $type,
                    'ip_address' => $ip,
                    'monitored_site_id' => $siteId,
                    'mac_address' => $mac ?: $asset->mac_address,
                    'notes' => $notes,
                ]);

                if ($ipChanged) {
                    $updatedIpCount++;
                    AuditLog::create([
                        'user_id' => Auth::id(),
                        'user_name' => Auth::user()?->name ?? 'Administrador',
                        'event' => 'ip_reassigned',
                        'module' => 'inventory',
                        'auditable_type' => InventoryAsset::class,
                        'auditable_id' => $asset->id,
                        'entity_name' => $asset->name,
                        'description' => "Reasignación DHCP detectada en lote para {$asset->name} (MAC {$mac}): IP migró de {$oldIp} a {$ip}.",
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                }
            }

            // Auto-conciliar en DiscoveredDevice
            DiscoveredDevice::where('ip_address', $ip)->update([
                'classification_status' => 'clasificado',
                'is_authorized' => 1,
                'linked_network_device_id' => $asset->id,
                'site_id' => $siteId,
                'device_type' => $this->mapAssetTypeToDiscoveredDeviceType($type),
            ]);

            $processedCount++;
            $processedIps[] = $ip;
        }

        // Auditoría general del lote
        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Administrador',
            'event' => 'bulk_promote_assets',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => 0,
            'entity_name' => "Lote de {$processedCount} dispositivos",
            'description' => "Catalogación masiva: {$createdCount} nuevos activos creados, {$updatedIpCount} IPs migradas/actualizadas. Tipo: {$type}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $action = $isWorkstation ? 'registered' : 'moved';
        $msg = "Se procesaron exitosamente {$processedCount} equipos en el inventario ({$createdCount} creados, {$updatedIpCount} IPs actualizadas).";

        return response()->json([
            'success' => true,
            'action' => $action,
            'message' => $msg,
            'count' => $processedCount,
            'ips' => $processedIps,
            'is_workstation' => $isWorkstation,
        ]);
    }

    /**
     * Mapea el tipo de activo de inventario (ITAM) a los valores válidos del enum device_type de DiscoveredDevice.
     */
    private function mapAssetTypeToDiscoveredDeviceType(string $assetType): string
    {
        return match ($assetType) {
            'desktop', 'laptop' => 'workstation',
            'server' => 'server',
            'printer' => 'printer',
            'router' => 'router',
            'switch' => 'switch',
            'firewall' => 'firewall',
            'access_point' => 'ap',
            'ip_phone' => 'phone',
            'ups' => 'ups',
            'camera' => 'camera',
            default => 'unknown',
        };
    }

    /**
     * Determina si un dispositivo es compatible con la instalación de Agente GLPI.
     */
    public function isGlpiSupportedDevice(array $item, ?InventoryAsset $asset, string $osType): bool
    {
        return $this->deployService->isGlpiSupportedDevice($item, $asset, $osType);
    }

    /**
     * Escanea un segmento de red CIDR específico bajo demanda y devuelve los resultados vía AJAX.
     */
    public function scanSubnet(ScanSubnetRequest $request): JsonResponse
    {
        $result = $this->deployService->scanSubnet($request->input('subnet'));
        $statusCode = $result['status_code'] ?? 200;
        unset($result['status_code']);

        return response()->json($result, $statusCode);
    }

    /**
     * Determina si una dirección IPv4 pertenece a una subred CIDR.
     */
    public function ipInSubnet(string $ip, string $range): bool
    {
        return $this->deployService->ipInSubnet($ip, $range);
    }

    /**
     * Envía una señal HTTP de sondeo inmediato (puerto 62354) al agente GLPI del equipo remoto.
     */
    public function triggerAgent(Request $request): JsonResponse
    {
        $ip = trim((string) $request->input('ip'));
        if (empty($ip)) {
            return response()->json([
                'success' => false,
                'message' => 'Dirección IP no especificada para el sondeo del agente.',
            ], 422);
        }

        $result = $this->agentTrigger->triggerImmediateInventory($ip);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Devuelve el resumen y la lista de discrepancias forenses de hardware (Hardware Drift) vía AJAX.
     */
    public function driftSummary(Request $request): JsonResponse
    {
        $siteId = $request->query('site_id') ? (int) $request->query('site_id') : null;
        $summary = $this->driftDetector->getDriftSummary();
        $assets = $this->driftDetector->getAssetsWithDrift($siteId);

        return response()->json([
            'summary' => $summary,
            'assets' => $assets,
        ]);
    }

    /**
     * Registra la justificación y regularización formal de una discrepancia de hardware.
     */
    public function acknowledgeDrift(Request $request): JsonResponse
    {
        $request->validate([
            'asset_id' => 'required|integer|exists:inventory_assets,id',
            'reason' => 'required|string|max:500',
        ]);

        $assetId = (int) $request->input('asset_id');
        $reason = (string) $request->input('reason');
        $userId = Auth::id() ?? 1;

        $ok = $this->driftDetector->acknowledgeDrift($assetId, $reason, $userId);

        if (!$ok) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo regularizar la discrepancia del activo.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Discrepancia de hardware regularizada y asentada en la bitácora de auditoría.',
        ]);
    }
}
