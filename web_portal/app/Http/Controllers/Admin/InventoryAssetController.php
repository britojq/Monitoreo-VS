<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\AssetTimelineInterface;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GlpiInventoryLog;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryComponent;
use App\Models\InventoryComponentHistory;
use App\Models\InventoryPeripheral;
use App\Models\MaintenanceTicket;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;
use App\Models\User;
use App\Services\ClusterConfigService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InventoryAssetController extends Controller
{
    /**
     * Catálogo principal de Activos de TI con métricas, filtros y buscador en tiempo real.
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $typeFilter = $request->query('type');
        $siteFilter = $request->query('site_id');
        $search = trim((string)$request->query('search', ''));

        $query = InventoryAsset::with([
            'monitoredSite',
            'networkDevice',
            'components',
            'networkInterfaces',
            'peripherals',
            'componentHistories' => function ($q) {
                $q->with('registeredByUser')->orderBy('recorded_at', 'desc');
            },
            'assignments' => function ($q) {
                $q->orderBy('id', 'desc');
            },
            'maintenanceTickets' => function ($q) {
                $q->with('technician')->orderBy('id', 'desc');
            },
        ])->orderBy('id', 'desc');

        if (!empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        if (!empty($typeFilter)) {
            $query->where('type', $typeFilter);
        }

        if (!empty($siteFilter)) {
            $query->where('monitored_site_id', $siteFilter);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('asset_tag', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('mac_address', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhere('operating_system', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhere('custodian_name', 'like', "%{$search}%")
                  ->orWhereHas('networkDevice', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                         ->orWhere('ip', 'like', "%{$search}%")
                         ->orWhere('model', 'like', "%{$search}%");
                  })
                  ->orWhereHas('monitoredSite', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $assets = $query->paginate(15)->withQueryString();

        // Resumen de Métricas / KPIs del Parque de Equipos
        $totalAssets = InventoryAsset::count();
        $assignedAssets = InventoryAsset::where('status', 'assigned')->count();
        $inStockAssets = InventoryAsset::where('status', 'in_stock')->count();
        $maintenanceAssets = InventoryAsset::where('status', 'maintenance')->count();
        $unauthorizedAssets = InventoryAsset::where('status', 'unauthorized')->count();

        $sites = MonitoredSite::orderBy('name')->get();
        $technicians = User::where('is_active', true)->orderBy('name')->get();

        return view('admin.inventory.assets.index', compact(
            'assets',
            'totalAssets',
            'assignedAssets',
            'inStockAssets',
            'maintenanceAssets',
            'unauthorizedAssets',
            'sites',
            'technicians',
            'statusFilter',
            'typeFilter',
            'siteFilter',
            'search'
        ));
    }

    /**
     * Ficha Técnica Desplegable del Activo de TI: especificaciones de hardware, historial inmutable y asignaciones.
     */
    public function show(int $id): View
    {
        $asset = InventoryAsset::with([
            'monitoredSite',
            'components',
            'networkInterfaces',
            'componentHistories' => function ($q) {
                $q->orderBy('recorded_at', 'desc')->limit(30);
            },
            'assignments' => function ($q) {
                $q->orderBy('assigned_at', 'desc');
            },
            'maintenanceTickets' => function ($q) {
                $q->orderBy('id', 'desc');
            }
        ])->findOrFail($id);

        // Agrupar componentes por tipo (CPU, RAM, Disco)
        $cpus = $asset->components->where('type', 'cpu');
        $rams = $asset->components->where('type', 'ram');
        $disks = $asset->components->where('type', 'disk');
        $gpus = $asset->components->where('type', 'gpu');
        $otherComponents = $asset->components->whereNotIn('type', ['cpu', 'ram', 'disk', 'gpu']);

        // Extraer metadatos extendidos del último log crudo de GLPI (AnyDesk, Software, Pantallas)
        $extraData = [
            'anydesk_id' => null,
            'screen_info' => null,
            'uptime' => null,
            'video_gpu' => null,
            'usb_devices' => [],
            'software_count' => 0,
        ];

        $latestLog = GlpiInventoryLog::where(function ($q) use ($asset) {
            if ($asset->serial_number) {
                $q->where('serial_number', $asset->serial_number);
            }
            if ($asset->name) {
                $q->orWhere('device_name', $asset->name);
            }
            if ($asset->mac_address) {
                $q->orWhere('mac_address', $asset->mac_address);
            }
        })->latest('id')->first();

        if ($latestLog && !empty($latestLog->raw_payload)) {
            $parsed = @json_decode($latestLog->raw_payload, true);
            $content = $parsed['content'] ?? $parsed ?? [];

            // AnyDesk ID
            $extraData['anydesk_id'] = $content['remote_mgmt']['anydesk_id'] 
                ?? $content['remote_mgmt']['anydesk'] 
                ?? null;

            if (empty($extraData['anydesk_id']) && isset($content['remote_mgmt']) && is_array($content['remote_mgmt'])) {
                foreach ($content['remote_mgmt'] as $rm) {
                    if (is_array($rm) && isset($rm['id'])) {
                        $extraData['anydesk_id'] = $rm['id'];
                        break;
                    }
                }
            }

            // GPU / Video
            if (!empty($content['videos']) && is_array($content['videos'])) {
                $v = $content['videos'][0] ?? [];
                $extraData['video_gpu'] = ($v['name'] ?? '') . ($v['resolution'] ? " ({$v['resolution']})" : "");
            }

            // USB devices
            if (!empty($content['usbdevices']) && is_array($content['usbdevices'])) {
                $extraData['usb_devices'] = array_slice($content['usbdevices'], 0, 8);
            }

            // Conteo de software instalado
            if (!empty($content['softwares']) && is_array($content['softwares'])) {
                $extraData['software_count'] = count($content['softwares']);
            }
        }

        // Interfaces de red normalizadas (Multi-NIC)
        $interfaces = $asset->networkInterfaces;

        // Línea de Tiempo Cronológica Unificada (Fase C - Trazabilidad Forense ITAM)
        $timeline = app(AssetTimelineInterface::class)->getAssetTimeline($asset);

        return view('admin.inventory.assets.show', compact(
            'asset',
            'cpus',
            'rams',
            'disks',
            'gpus',
            'interfaces',
            'otherComponents',
            'extraData',
            'timeline'
        ));
    }

    /**
     * Formulario de alta manual de activo.
     */
    public function create(): View
    {
        $sites = MonitoredSite::orderBy('name')->get();
        return view('admin.inventory.assets.create', compact('sites'));
    }

    /**
     * Registrar un nuevo activo de forma manual.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:128',
            'asset_tag' => 'nullable|string|max:64|unique:inventory_assets,asset_tag',
            'type' => 'required|in:desktop,laptop,server,printer,network,peripheral,router,switch,firewall,access_point,ip_phone,ups',
            'status' => 'required|in:in_stock,assigned,maintenance,damaged,disposed,unauthorized',
            'brand' => 'nullable|string|max:64',
            'model' => 'nullable|string|max:64',
            'serial_number' => 'nullable|string|max:128|unique:inventory_assets,serial_number',
            'mac_address' => 'nullable|string|max:48',
            'ip_address' => 'nullable|ip',
            'monitored_site_id' => 'nullable|exists:monitored_sites,id',
            'location_detail' => 'nullable|string|max:191',
            'custodian_name' => 'nullable|string|max:128',
            'custodian_id_number' => 'nullable|string|max:32',
            'custodian_department' => 'nullable|string|max:128',
            'operating_system' => 'nullable|string|max:128',
            'notes' => 'nullable|string',
            // Periféricos de PC
            'include_peripherals' => 'nullable',
            'monitor_brand' => 'nullable|string|max:64',
            'monitor_model' => 'nullable|string|max:96',
            'monitor_serial' => 'nullable|string|max:128',
            'keyboard_brand' => 'nullable|string|max:64',
            'keyboard_serial' => 'nullable|string|max:128',
            'mouse_brand' => 'nullable|string|max:64',
            'mouse_serial' => 'nullable|string|max:128',
            'surge_protector_brand' => 'nullable|string|max:64',
            'surge_protector_serial' => 'nullable|string|max:128',
            // Red
            'ports' => 'nullable|string|max:100',
            'access_type' => 'nullable|string|in:SSH,TELNET,WEB,VNC,SIN SOPORTE',
            'access_port' => 'nullable|integer',
        ]);

        $assetData = collect($validated)->only([
            'name', 'asset_tag', 'type', 'status', 'brand', 'model', 'serial_number',
            'mac_address', 'ip_address', 'monitored_site_id', 'location_detail',
            'custodian_name', 'custodian_id_number', 'custodian_department',
            'operating_system', 'notes'
        ])->toArray();

        $asset = InventoryAsset::create($assetData);

        // Si es PC e indicó incluir periféricos de puesto de trabajo (o se proveyeron datos de periféricos)
        $hasPeripherals = $request->boolean('include_peripherals')
            || $request->input('scope_option') === 'pc_bundle'
            || $request->filled('monitor_brand')
            || $request->filled('monitor_serial')
            || $request->filled('keyboard_brand')
            || $request->filled('mouse_brand')
            || $request->filled('surge_protector_brand');

        if ($hasPeripherals) {
            // 1. Monitor
            if ($request->filled('monitor_brand') || $request->filled('monitor_model') || $request->filled('monitor_serial')) {
                InventoryPeripheral::create([
                    'asset_tag' => 'MON-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                    'type' => 'monitor',
                    'brand' => $request->input('monitor_brand') ?: 'Genérico',
                    'model' => $request->input('monitor_model') ?: 'Monitor Estándar',
                    'serial_number' => $request->input('monitor_serial'),
                    'status' => 'assigned',
                    'current_asset_id' => $asset->id,
                    'monitored_site_id' => $asset->monitored_site_id,
                ]);
            }
            // 2. Teclado
            if ($request->filled('keyboard_brand') || $request->filled('keyboard_serial')) {
                InventoryPeripheral::create([
                    'asset_tag' => 'KBD-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                    'type' => 'keyboard',
                    'brand' => $request->input('keyboard_brand') ?: 'Genérico',
                    'model' => 'Teclado Estándar USB',
                    'serial_number' => $request->input('keyboard_serial'),
                    'status' => 'assigned',
                    'current_asset_id' => $asset->id,
                    'monitored_site_id' => $asset->monitored_site_id,
                ]);
            }
            // 3. Mouse
            if ($request->filled('mouse_brand') || $request->filled('mouse_serial')) {
                InventoryPeripheral::create([
                    'asset_tag' => 'MSE-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                    'type' => 'mouse',
                    'brand' => $request->input('mouse_brand') ?: 'Genérico',
                    'model' => 'Mouse Óptico USB',
                    'serial_number' => $request->input('mouse_serial'),
                    'status' => 'assigned',
                    'current_asset_id' => $asset->id,
                    'monitored_site_id' => $asset->monitored_site_id,
                ]);
            }
            // 4. Protector de Voltaje / Regulador
            if ($request->filled('surge_protector_brand') || $request->filled('surge_protector_serial')) {
                InventoryPeripheral::create([
                    'asset_tag' => 'PWR-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                    'type' => 'surge_protector',
                    'brand' => $request->input('surge_protector_brand') ?: 'Genérico',
                    'model' => 'Protector de Voltaje / Regulador',
                    'serial_number' => $request->input('surge_protector_serial'),
                    'status' => 'assigned',
                    'current_asset_id' => $asset->id,
                    'monitored_site_id' => $asset->monitored_site_id,
                ]);
            }
        }

        // Si es un dispositivo de red y se proveyó IP, registrar/vincular en monitoreo de red
        if (in_array($asset->type, ['router', 'switch', 'server', 'printer', 'network'], true) && !empty($asset->ip_address)) {
            $siteId = $asset->monitored_site_id ?: 1;
            $nextDevNum = (MonitoredNetworkDevice::where('monitored_site_id', $siteId)->max('device_number') ?? 0) + 1;
            $netDev = MonitoredNetworkDevice::firstOrCreate(
                ['ip' => $asset->ip_address],
                [
                    'monitored_site_id' => $siteId,
                    'device_number' => $nextDevNum,
                    'name' => $asset->name,
                    'mac' => $asset->mac_address,
                    'model' => $asset->model,
                    'serial' => $asset->serial_number ?: '00000000',
                    'ports' => $request->input('ports', '1x GE'),
                    'access_type' => $request->input('access_type', 'SIN SOPORTE'),
                    'access_port' => $request->input('access_port'),
                    'notes' => $asset->notes ?: 'Creado desde Inventario ITAM',
                    'is_active' => true,
                ]
            );
            $asset->update(['monitored_network_device_id' => $netDev->id]);
        }

        // Auditoría
        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'created',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $asset->id,
            'entity_name' => $asset->name,
            'entity_label' => $asset->asset_tag,
            'description' => "Activo ITAM creado manualmente: {$asset->name} (Tag: {$asset->asset_tag})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.inventory.assets.index')
            ->with('success', "Activo '{$asset->display_identity}' registrado exitosamente en el inventario.");
    }

    /**
     * Formulario de edición de metadatos del activo.
     */
    public function edit(int $id): View
    {
        $asset = InventoryAsset::with(['peripherals', 'networkDevice'])->findOrFail($id);
        $sites = MonitoredSite::orderBy('name')->get();

        return view('admin.inventory.assets.edit', compact('asset', 'sites'));
    }

    /**
     * Actualizar metadatos de un activo.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $asset = InventoryAsset::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:128',
            'asset_tag' => "nullable|string|max:64|unique:inventory_assets,asset_tag,{$asset->id}",
            'type' => 'required|in:desktop,laptop,server,printer,network,peripheral,router,switch,firewall,access_point,ip_phone,ups',
            'status' => 'required|in:in_stock,assigned,maintenance,damaged,disposed,unauthorized',
            'brand' => 'nullable|string|max:64',
            'model' => 'nullable|string|max:64',
            'serial_number' => "nullable|string|max:128|unique:inventory_assets,serial_number,{$asset->id}",
            'mac_address' => 'nullable|string|max:48',
            'ip_address' => 'nullable|ip',
            'monitored_site_id' => 'nullable|exists:monitored_sites,id',
            'location_detail' => 'nullable|string|max:191',
            'custodian_name' => 'nullable|string|max:128',
            'custodian_id_number' => 'nullable|string|max:32',
            'custodian_department' => 'nullable|string|max:128',
            'operating_system' => 'nullable|string|max:128',
            'notes' => 'nullable|string',
            // Periféricos
            'monitor_brand' => 'nullable|string|max:64',
            'monitor_model' => 'nullable|string|max:96',
            'monitor_serial' => 'nullable|string|max:128',
            'keyboard_brand' => 'nullable|string|max:64',
            'keyboard_serial' => 'nullable|string|max:128',
            'mouse_brand' => 'nullable|string|max:64',
            'mouse_serial' => 'nullable|string|max:128',
            'surge_protector_brand' => 'nullable|string|max:64',
            'surge_protector_serial' => 'nullable|string|max:128',
        ]);

        $assetData = collect($validated)->only([
            'name', 'asset_tag', 'type', 'status', 'brand', 'model', 'serial_number',
            'mac_address', 'ip_address', 'monitored_site_id', 'location_detail',
            'custodian_name', 'custodian_id_number', 'custodian_department',
            'operating_system', 'notes'
        ])->toArray();

        $asset->update($assetData);

        // Actualizar periféricos si se enviaron
        $periphMap = [
            'monitor' => [
                'brand' => $request->input('monitor_brand'),
                'model' => $request->input('monitor_model'),
                'serial' => $request->input('monitor_serial'),
            ],
            'keyboard' => [
                'brand' => $request->input('keyboard_brand'),
                'model' => 'Teclado USB',
                'serial' => $request->input('keyboard_serial'),
            ],
            'mouse' => [
                'brand' => $request->input('mouse_brand'),
                'model' => 'Mouse USB',
                'serial' => $request->input('mouse_serial'),
            ],
            'surge_protector' => [
                'brand' => $request->input('surge_protector_brand'),
                'model' => 'Protector / Regulador',
                'serial' => $request->input('surge_protector_serial'),
            ],
        ];

        foreach ($periphMap as $pType => $pData) {
            if (!empty($pData['brand']) || !empty($pData['serial']) || !empty($pData['model'])) {
                $existing = InventoryPeripheral::where('current_asset_id', $asset->id)
                    ->where('type', $pType)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'brand' => $pData['brand'] ?: $existing->brand,
                        'model' => $pData['model'] ?: $existing->model,
                        'serial_number' => $pData['serial'] ?: $existing->serial_number,
                        'monitored_site_id' => $asset->monitored_site_id,
                    ]);
                } else {
                    $prefix = match($pType) {
                        'monitor' => 'MON',
                        'keyboard' => 'KBD',
                        'mouse' => 'MSE',
                        'surge_protector' => 'PWR',
                        default => 'PER',
                    };
                    InventoryPeripheral::create([
                        'asset_tag' => $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                        'type' => $pType,
                        'brand' => $pData['brand'] ?: 'Genérico',
                        'model' => $pData['model'] ?: 'Estándar',
                        'serial_number' => $pData['serial'],
                        'status' => 'assigned',
                        'current_asset_id' => $asset->id,
                        'monitored_site_id' => $asset->monitored_site_id,
                    ]);
                }
            }
        }

        // Auditoría
        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'updated',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $asset->id,
            'entity_name' => $asset->name,
            'entity_label' => $asset->asset_tag,
            'description' => "Activo ITAM actualizado: {$asset->name} (#{$asset->id})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.inventory.assets.show', $asset->id)
            ->with('success', "Activo '{$asset->name}' actualizado correctamente.");
    }

    /**
     * Aprobar e incorporar al inventario formal un equipo descubierto por GLPI.
     */
    public function approve(Request $request, int $id): RedirectResponse
    {
        $asset = InventoryAsset::findOrFail($id);

        $newStatus = $request->input('target_status', 'in_stock');
        if (!in_array($newStatus, ['in_stock', 'assigned', 'maintenance'])) {
            $newStatus = 'in_stock';
        }

        $asset->update([
            'status' => $newStatus,
            'asset_tag' => $request->input('asset_tag', $asset->asset_tag),
            'monitored_site_id' => $request->input('monitored_site_id', $asset->monitored_site_id),
            'location_detail' => $request->input('location_detail', $asset->location_detail),
            'custodian_name' => $request->input('custodian_name', $asset->custodian_name),
            'custodian_id_number' => $request->input('custodian_id_number', $asset->custodian_id_number),
            'custodian_department' => $request->input('custodian_department', $asset->custodian_department),
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'approved',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $asset->id,
            'entity_name' => $asset->name,
            'entity_label' => $asset->asset_tag,
            'description' => "Equipo descubierto por GLPI '{$asset->name}' (#{$asset->id}) aprobado e incorporado con estado: {$newStatus}",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', "Equipo '{$asset->name}' aprobado e incorporado formalmente al inventario.");
    }

    /**
     * Desincorporar o dar de baja un activo.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $asset = InventoryAsset::findOrFail($id);
        $name = $asset->name;

        // Registrar auditoría previa a la eliminación
        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'deleted',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $id,
            'entity_name' => $name,
            'description' => "Activo ITAM eliminado/desincorporado: {$name} (#{$id})",
            'ip_address' => $request->ip(),
        ]);

        $asset->components()->delete();
        $asset->componentHistories()->delete();
        $asset->delete();

        return redirect()->route('admin.inventory.assets.index')
            ->with('success', "Activo '{$name}' eliminado del catálogo.");
    }

    /**
     * Asociar o reemplazar un periférico de puesto (monitor, teclado, mouse, protector)
     */
    public function associatePeripheral(Request $request, int $id): RedirectResponse
    {
        $asset = InventoryAsset::findOrFail($id);

        $validated = $request->validate([
            'type' => 'required|in:monitor,keyboard,mouse,surge_protector,ups,printer,scanner,other',
            'brand' => 'nullable|string|max:64',
            'model' => 'nullable|string|max:96',
            'serial_number' => 'nullable|string|max:128',
            'status' => 'required|in:assigned,in_stock,in_maintenance,damaged',
            'notes' => 'nullable|string',
            'reason' => 'nullable|string|max:191',
        ]);

        $existing = InventoryPeripheral::where('current_asset_id', $asset->id)
            ->where('type', $validated['type'])
            ->first();

        $action = $existing ? 'replaced' : 'added';
        $oldData = $existing ? [
            'brand' => $existing->brand,
            'model' => $existing->model,
            'serial' => $existing->serial_number,
        ] : null;

        $newData = [
            'brand' => $validated['brand'] ?: 'Genérico',
            'model' => $validated['model'] ?: 'Estándar',
            'serial' => $validated['serial_number'],
        ];

        if ($existing) {
            $existing->update([
                'brand' => $validated['brand'],
                'model' => $validated['model'],
                'serial_number' => $validated['serial_number'],
                'status' => $validated['status'],
                'monitored_site_id' => $asset->monitored_site_id,
                'notes' => $validated['notes'] ?? $existing->notes,
            ]);
        } else {
            $prefix = match($validated['type']) {
                'monitor' => 'MON',
                'keyboard' => 'KBD',
                'mouse' => 'MSE',
                'surge_protector', 'ups' => 'PWR',
                default => 'PER',
            };
            InventoryPeripheral::create([
                'asset_tag' => $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                'type' => $validated['type'],
                'brand' => $validated['brand'] ?: 'Genérico',
                'model' => $validated['model'] ?: 'Estándar',
                'serial_number' => $validated['serial_number'],
                'status' => $validated['status'],
                'monitored_site_id' => $asset->monitored_site_id,
                'current_asset_id' => $asset->id,
                'notes' => $validated['notes'],
            ]);
        }

        // Registrar en el histórico inmutable de componentes
        InventoryComponentHistory::create([
            'inventory_asset_id' => $asset->id,
            'action' => $action,
            'component_type' => $validated['type'],
            'old_value' => $oldData,
            'new_value' => $newData,
            'reason' => $validated['reason'] ?: 'Asignación / reemplazo de periférico desde catálogo ITAM',
            'registered_by_user_id' => Auth::id(),
            'recorded_at' => now(),
        ]);

        return redirect()->back()->with('success', "Periférico [{$validated['type']}] guardado exitosamente para {$asset->display_identity}.");
    }

    /**
     * Desvincular periférico del activo
     */
    public function disassociatePeripheral(Request $request, int $id, int $peripheralId): RedirectResponse
    {
        $asset = InventoryAsset::findOrFail($id);
        $peripheral = InventoryPeripheral::where('current_asset_id', $asset->id)->findOrFail($peripheralId);

        $oldData = [
            'brand' => $peripheral->brand,
            'model' => $peripheral->model,
            'serial' => $peripheral->serial_number,
        ];

        $peripheral->update([
            'current_asset_id' => null,
            'status' => 'in_stock',
        ]);

        InventoryComponentHistory::create([
            'inventory_asset_id' => $asset->id,
            'action' => 'removed',
            'component_type' => $peripheral->type,
            'old_value' => $oldData,
            'new_value' => null,
            'reason' => $request->input('reason', 'Periférico desvinculado / reintegrado al stock'),
            'registered_by_user_id' => Auth::id(),
            'recorded_at' => now(),
        ]);

        return redirect()->back()->with('success', "Periférico [{$peripheral->type}] desvinculado correctamente.");
    }

    /**
     * Programar mantenimiento masivo para múltiples activos seleccionados
     */
    public function bulkMaintenance(Request $request): RedirectResponse
    {
        if (is_string($request->input('asset_ids'))) {
            $request->merge(['asset_ids' => array_filter(explode(',', $request->input('asset_ids')))]);
        } elseif ($request->filled('selected_asset_ids') && !$request->has('asset_ids')) {
            $raw = is_array($request->input('selected_asset_ids')) ? $request->input('selected_asset_ids') : explode(',', $request->input('selected_asset_ids'));
            $request->merge(['asset_ids' => array_filter($raw)]);
        }
        if ($request->filled('technician_user_id') && !$request->has('technician_id')) {
            $request->merge(['technician_id' => $request->input('technician_user_id')]);
        }

        $validated = $request->validate([
            'asset_ids' => 'required|array|min:1',
            'asset_ids.*' => 'exists:inventory_assets,id',
            'scheduled_at' => 'required|date',
            'type' => 'required|in:preventive,corrective',
            'priority' => 'required|in:low,medium,high,critical',
            'technician_id' => 'nullable|exists:users,id',
            'title' => 'nullable|string|max:191',
            'notes' => 'nullable|string',
        ]);

        $year = Carbon::parse($validated['scheduled_at'])->format('Y');
        $createdCount = 0;

        foreach ($validated['asset_ids'] as $assetId) {
            $asset = InventoryAsset::find($assetId);
            if (!$asset) continue;

            $countThisYear = MaintenanceTicket::where('ticket_code', 'like', "OS-{$year}-%")->count() + 1;
            $ticketCode = sprintf("OS-%s-%04d", $year, $countThisYear);

            MaintenanceTicket::create([
                'ticket_code' => $ticketCode,
                'inventory_asset_id' => $asset->id,
                'title' => ($validated['title'] ?? null) ?: "Mantenimiento {$validated['type']} programado",
                'description' => ($validated['notes'] ?? null) ?: "Mantenimiento masivo programado para la sede " . ($asset->monitoredSite?->name ?? 'General'),
                'type' => $validated['type'],
                'priority' => $validated['priority'],
                'status' => 'scheduled',
                'technician_user_id' => $validated['technician_id'] ?? null,
                'scheduled_at' => Carbon::parse($validated['scheduled_at']),
            ]);

            $asset->update(['status' => 'maintenance']);
            $createdCount++;
        }

        return redirect()->back()->with('success', "Se programó exitosamente el mantenimiento para {$createdCount} equipo(s).");
    }

    /**
     * Finalizar mantenimiento masivo para múltiples activos seleccionados.
     */
    public function bulkCompleteMaintenance(Request $request): RedirectResponse
    {
        if (is_string($request->input('asset_ids'))) {
            $request->merge(['asset_ids' => array_filter(explode(',', $request->input('asset_ids')))]);
        } elseif ($request->filled('selected_asset_ids') && !$request->has('asset_ids')) {
            $raw = is_array($request->input('selected_asset_ids')) ? $request->input('selected_asset_ids') : explode(',', $request->input('selected_asset_ids'));
            $request->merge(['asset_ids' => array_filter($raw)]);
        }
        if ($request->filled('technician_user_id') && !$request->has('technician_id')) {
            $request->merge(['technician_id' => $request->input('technician_user_id')]);
        }

        $validated = $request->validate([
            'asset_ids' => 'required|array|min:1',
            'asset_ids.*' => 'exists:inventory_assets,id',
            'completed_at' => 'nullable|date',
            'technician_id' => 'nullable|exists:users,id',
            'resolution_notes' => 'nullable|string',
        ]);

        $completedAt = !empty($validated['completed_at']) ? Carbon::parse($validated['completed_at']) : now();
        $notes = $validated['resolution_notes'] ?? 'Mantenimiento técnico ejecutado y certificado exitosamente.';
        $technicianId = $validated['technician_id'] ?? Auth::id();
        $year = $completedAt->format('Y');

        $completedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($validated['asset_ids'] as $assetId) {
                $asset = InventoryAsset::find($assetId);
                if (!$asset) continue;

                // Buscar ticket activo/programado para este equipo
                $ticket = MaintenanceTicket::where('inventory_asset_id', $asset->id)
                    ->whereIn('status', ['scheduled', 'in_progress'])
                    ->latest()
                    ->first();

                if ($ticket) {
                    $ticket->update([
                        'status' => 'completed',
                        'completed_at' => $completedAt,
                        'technician_user_id' => $technicianId,
                        'resolution_notes' => $notes,
                    ]);
                } else {
                    $countThisYear = MaintenanceTicket::where('ticket_code', 'like', "OS-{$year}-%")->count() + 1;
                    $ticketCode = sprintf("OS-%s-%04d", $year, $countThisYear);

                    MaintenanceTicket::create([
                        'ticket_code' => $ticketCode,
                        'inventory_asset_id' => $asset->id,
                        'title' => 'Mantenimiento Preventivo Ejecutado',
                        'description' => 'Mantenimiento masivo ejecutado para la sede ' . ($asset->monitoredSite?->name ?? 'General'),
                        'type' => 'preventive',
                        'priority' => 'medium',
                        'status' => 'completed',
                        'technician_user_id' => $technicianId,
                        'scheduled_at' => $completedAt,
                        'completed_at' => $completedAt,
                        'resolution_notes' => $notes,
                    ]);
                }

                // Restaurar el estado del activo
                $newStatus = !empty($asset->custodian_name) ? 'assigned' : 'active';
                $asset->update(['status' => $newStatus]);

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'user_name' => Auth::user()?->name ?? 'Sistema',
                    'user_email' => Auth::user()?->email,
                    'user_role' => Auth::user()?->role,
                    'event' => 'updated',
                    'module' => 'inventory',
                    'auditable_type' => InventoryAsset::class,
                    'auditable_id' => $asset->id,
                    'entity_name' => $asset->name,
                    'entity_label' => $asset->asset_tag,
                    'description' => "Mantenimiento finalizado para equipo '{$asset->name}'. Estado restaurado a: {$newStatus}",
                    'ip_address' => $request->ip(),
                ]);

                $completedCount++;
            }

            DB::commit();
            return redirect()->back()->with('success', "Se finalizó exitosamente el mantenimiento para {$completedCount} equipo(s). Estado operativo restaurado.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', "Error al finalizar el mantenimiento masivo: {$e->getMessage()}");
        }
    }

    /**
     * Procesar movilización masiva de múltiples activos seleccionados y emitir Pase de Movilización oficial.
     */
    public function bulkMobilize(Request $request): RedirectResponse|JsonResponse
    {
        if (is_string($request->input('asset_ids'))) {
            $request->merge(['asset_ids' => array_filter(explode(',', $request->input('asset_ids')))]);
        } elseif ($request->filled('selected_asset_ids') && !$request->has('asset_ids')) {
            $raw = is_array($request->input('selected_asset_ids')) ? $request->input('selected_asset_ids') : explode(',', $request->input('selected_asset_ids'));
            $request->merge(['asset_ids' => array_filter($raw)]);
        }

        $validated = $request->validate([
            'asset_ids' => 'required|array|min:1',
            'asset_ids.*' => 'exists:inventory_assets,id',
            'monitored_site_id' => 'required|exists:monitored_sites,id',
            'custodian_name' => 'required|string|max:128',
            'custodian_id_number' => 'nullable|string|max:32',
            'custodian_department' => 'nullable|string|max:128',
            'checkpoint_name' => 'nullable|string|max:128',
            'gate_operator_name' => 'nullable|string|max:128',
            'departure_time' => 'nullable|string|max:32',
            'entry_time' => 'nullable|string|max:32',
            'assigned_at' => 'nullable|date',
            'observations' => 'nullable|string',
        ], [
            'asset_ids.required' => 'Debe seleccionar al menos un equipo para movilizar.',
            'monitored_site_id.required' => 'La sede de destino es obligatoria.',
            'custodian_name.required' => 'El nombre del funcionario responsable del traslado es obligatorio.',
        ]);

        $assignedAt = !empty($validated['assigned_at']) ? Carbon::parse($validated['assigned_at']) : Carbon::now();
        $targetSite = MonitoredSite::findOrFail($validated['monitored_site_id']);
        $year = $assignedAt->format('Y');

        // Generar correlativo oficial único para Pase de Movilización
        $prefix = "PASE-MOV-{$year}";
        $countThisYear = InventoryAssignment::where('assignment_code', 'like', "{$prefix}-%")->count() + 1;
        $code = sprintf("%s-%04d", $prefix, $countThisYear);

        DB::beginTransaction();
        try {
            $assets = InventoryAsset::with(['components', 'peripherals'])->whereIn('id', $validated['asset_ids'])->get();
            if ($assets->isEmpty()) {
                throw new \RuntimeException('No se encontraron los activos seleccionados.');
            }

            $primaryAsset = $assets->first();
            $peripheralsList = [];
            $includeStandardKit = $request->has('include_standard_kit')
                ? $request->boolean('include_standard_kit')
                : true;

            // Recolectar periféricos hijos reales y accesorios del kit operativo para cada equipo
            $collectPeripheralsForAsset = function ($asset) use (&$peripheralsList, $includeStandardKit) {
                $hasMonitor = false;
                $hasKeyboard = false;
                $hasMouse = false;
                $hasUps = false;

                // 1. Anexar todos los periféricos hijos reales vinculados en MariaDB
                foreach ($asset->peripherals as $peripheral) {
                    $pType = strtoupper($peripheral->type ?? 'PERIFÉRICO');
                    if (str_contains($pType, 'MONITOR')) $hasMonitor = true;
                    if (str_contains($pType, 'TECLADO') || str_contains($pType, 'KEYBOARD')) $hasKeyboard = true;
                    if (str_contains($pType, 'MOUSE') || str_contains($pType, 'RATON')) $hasMouse = true;
                    if (str_contains($pType, 'PROTECTOR') || str_contains($pType, 'UPS') || str_contains($pType, 'REGULADOR')) $hasUps = true;

                    $serial = trim($peripheral->serial_number ?? '');
                    if (str_starts_with($serial, '[') || in_array(strtoupper($serial), ['S/N', 'N/A', 'SIN SERIAL', 'NO REPORTADO', 'SIN SERIAL REGISTRADO'])) {
                        $serial = '';
                    }

                    $typeMap = [
                        'PRINTER' => 'IMPRESORA',
                        'DESKTOP' => 'COMPUTADORA',
                        'LAPTOP' => 'PORTÁTIL',
                        'MONITOR' => 'MONITOR',
                        'KEYBOARD' => 'TECLADO',
                        'MOUSE' => 'MOUSE',
                        'SCANNER' => 'ESCÁNER',
                    ];
                    $pTypeClean = $typeMap[$pType] ?? $pType;

                    $peripheralsList[] = [
                        'type' => $pTypeClean,
                        'brand' => $peripheral->brand ?: '',
                        'model' => $peripheral->model ?: '',
                        'serial_number' => $serial,
                        'observations' => '',
                    ];
                }

                // 2. Para equipos sin accesorios USB/eléctricos registrados con serial, anexar kit operativo sin texto dummy
                if ($includeStandardKit && in_array($asset->type, ['desktop', 'laptop', 'server', 'workstation'])) {
                    if (!$hasMonitor && $asset->type !== 'laptop') {
                        $peripheralsList[] = [
                            'type' => 'MONITOR',
                            'brand' => 'AOC / HP',
                            'model' => 'ESTÁNDAR',
                            'serial_number' => '',
                            'observations' => '',
                        ];
                    }
                    if (!$hasKeyboard && $asset->type !== 'laptop') {
                        $peripheralsList[] = [
                            'type' => 'TECLADO',
                            'brand' => 'GENIUS / HP',
                            'model' => 'USB',
                            'serial_number' => '',
                            'observations' => '',
                        ];
                    }
                    if (!$hasMouse && $asset->type !== 'laptop') {
                        $peripheralsList[] = [
                            'type' => 'MOUSE',
                            'brand' => 'GENIUS / HP',
                            'model' => 'ÓPTICO USB',
                            'serial_number' => '',
                            'observations' => '',
                        ];
                    }
                    if (!$hasUps) {
                        $peripheralsList[] = [
                            'type' => 'PROTECTOR DE VOLTAJE',
                            'brand' => 'MARSRIVA / CDP',
                            'model' => 'AVR600',
                            'serial_number' => '',
                            'observations' => '',
                        ];
                    }
                }
            };

            // Recolectar periféricos del activo primario
            $collectPeripheralsForAsset($primaryAsset);

            // Para los activos secundarios seleccionados: listar su CPU y luego recolectar sus periféricos
            foreach ($assets->slice(1) as $extraAsset) {
                $extraSerial = trim($extraAsset->serial_number ?? '');
                if (str_starts_with($extraSerial, '[') || in_array(strtoupper($extraSerial), ['S/N', 'N/A', 'SIN SERIAL', 'NO REPORTADO'])) {
                    $extraSerial = '';
                }

                $peripheralsList[] = [
                    'type' => strtoupper($extraAsset->type_label ?? $extraAsset->type ?? 'EQUIPO'),
                    'brand' => $extraAsset->brand ?? '',
                    'model' => $extraAsset->model ?? '',
                    'serial_number' => $extraSerial,
                    'observations' => '',
                ];
                $collectPeripheralsForAsset($extraAsset);
            }

            $cpuComponent = $primaryAsset->components->where('type', 'cpu')->first();
            $ramComponents = $primaryAsset->components->where('type', 'ram');
            $diskComponents = $primaryAsset->components->where('type', 'disk');

            $hardwareSpecs = [
                'processor' => $cpuComponent?->name ?? 'N/A',
                'speed' => 'N/A',
                'ram' => $ramComponents->isNotEmpty() ? $ramComponents->pluck('capacity')->implode(' + ') : 'N/A',
                'disk' => $diskComponents->isNotEmpty() ? $diskComponents->pluck('capacity')->implode(' + ') : 'N/A',
                'os' => $primaryAsset->operating_system ?? 'N/A',
                'device_name' => $primaryAsset->device_name ?? $primaryAsset->name,
                'ip_address' => $primaryAsset->ip_address ?? 'N/A',
            ];

            $checkpointName = ($validated['checkpoint_name'] ?? null) ?: ($targetSite->name ?? 'Punto de Control Valle Seco');
            $departureTime = ($validated['departure_time'] ?? null) ?: Carbon::now()->format('H:i');

            $assignment = InventoryAssignment::create([
                'assignment_code' => $code,
                'inventory_asset_id' => $primaryAsset->id,
                'user_id' => null,
                'custodian_name' => $validated['custodian_name'],
                'custodian_id_number' => $validated['custodian_id_number'] ?? null,
                'custodian_department' => $validated['custodian_department'] ?? null,
                'custodian_role' => null,
                'phone_extension' => null,
                'monitored_site_id' => $targetSite->id,
                'movement_type' => 'transfer',
                'provenance' => 'stock',
                'provenance_other' => null,
                'checkpoint_name' => $checkpointName,
                'gate_operator_name' => $validated['gate_operator_name'] ?? null,
                'departure_time' => $departureTime,
                'entry_time' => $validated['entry_time'] ?? null,
                'control_number' => $code,
                'peripherals_json' => $peripheralsList,
                'hardware_specs' => $hardwareSpecs,
                'assigned_at' => $assignedAt,
                'returned_at' => null,
                'status' => 'active',
                'delivered_by_user_id' => Auth::id(),
                'observations' => ($validated['observations'] ?? null) ?: "Movilización masiva de {$assets->count()} equipo(s) con destino a {$targetSite->name}.",
            ]);

            // Actualizar sede física y ubicación en cada uno de los activos seleccionados
            foreach ($assets as $asset) {
                $asset->update([
                    'monitored_site_id' => $targetSite->id,
                    'location_detail' => $checkpointName,
                ]);

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'user_name' => Auth::user()->name ?? 'Sistema',
                    'user_email' => Auth::user()->email ?? 'system@local',
                    'user_role' => Auth::user()->role ?? 'admin',
                    'event' => 'updated',
                    'module' => 'inventory',
                    'auditable_type' => InventoryAsset::class,
                    'auditable_id' => $asset->id,
                    'entity_name' => $asset->asset_tag ?? $asset->name,
                    'entity_label' => $asset->display_identity,
                    'description' => "Equipo reubicado a la sede [{$targetSite->name}] mediante Pase de Movilización {$code}. Responsable: {$validated['custodian_name']}",
                    'new_values' => ['monitored_site_id' => $targetSite->id, 'location_detail' => $checkpointName],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name ?? 'Sistema',
                'user_email' => Auth::user()->email ?? 'system@local',
                'user_role' => Auth::user()->role ?? 'admin',
                'event' => 'created',
                'module' => 'inventory',
                'auditable_type' => InventoryAssignment::class,
                'auditable_id' => $assignment->id,
                'entity_name' => $assignment->assignment_code,
                'entity_label' => $assignment->custodian_name,
                'description' => "Pase de Movilización Masiva {$code} emitido para {$assets->count()} equipo(s) hacia {$targetSite->name} - Responsable: {$validated['custodian_name']}",
                'new_values' => $assignment->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();

            if (class_exists(\App\Services\TelegramNotificationService::class)) {
                app(\App\Services\TelegramNotificationService::class)->notifyAssetAssignment($assignment);
            }

            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Movilización de {$assets->count()} equipo(s) registrada exitosamente bajo el comprobante {$code}.",
                    'code' => $code,
                    'pdf_url' => route('admin.inventory.assignments.pdf-movilizacion', $assignment->id),
                    'assignment_id' => $assignment->id,
                ]);
            }

            return redirect()->back()
                ->with('success', "Movilización de {$assets->count()} equipo(s) registrada exitosamente bajo el comprobante {$code}.")
                ->with('bulk_mobilization_pdf', route('admin.inventory.assignments.pdf-movilizacion', $assignment->id))
                ->with('bulk_mobilization_code', $assignment->assignment_code);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Error al procesar la movilización masiva: {$e->getMessage()}",
                ], 422);
            }
            return redirect()->back()->with('error', "Error al procesar la movilización masiva: {$e->getMessage()}");
        }
    }

    /**
     * Subir y vincular hoja de asignación física digitalizada (PDF o Imagen escaneada)
     */
    public function uploadAssignmentScan(Request $request, int $id): RedirectResponse
    {
        $asset = InventoryAsset::findOrFail($id);

        $validated = $request->validate([
            'custodian_name' => 'required|string|max:128',
            'custodian_id_number' => 'nullable|string|max:32',
            'custodian_department' => 'nullable|string|max:128',
            'assigned_at' => 'required|date',
            'control_number' => 'nullable|string|max:64',
            'observations' => 'nullable|string',
            'scan_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:20480',
        ], [
            'custodian_name.required' => 'El nombre del funcionario asignado es obligatorio.',
            'assigned_at.required' => 'Debe indicar la fecha en que se firmó el documento físico.',
            'scan_file.required' => 'Debe adjuntar el archivo digitalizado (PDF o Imagen).',
            'scan_file.mimes' => 'El archivo debe ser un documento PDF o una imagen (JPG, PNG).',
            'scan_file.max' => 'El archivo no puede exceder los 20 MB.',
        ]);

        $file = $request->file('scan_file');
        $extension = $file->getClientOriginalExtension();
        $safeName = Str::slug($asset->display_identity ?: $asset->name);
        $fileName = "acta_fisica_{$safeName}_{$asset->id}_" . time() . ".{$extension}";
        $path = $file->storeAs('assignments/scans', $fileName, 'public');

        $year = Carbon::parse($validated['assigned_at'])->format('Y');
        $code = !empty($validated['control_number'])
            ? trim($validated['control_number'])
            : sprintf("UINF-4.05-%s-%04d", $year, InventoryAssignment::whereYear('created_at', $year)->count() + 1);

        // Si ya tenía un acta activa, la actualizamos; si no, creamos una nueva
        $existing = InventoryAssignment::where('inventory_asset_id', $asset->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            $existing->update([
                'custodian_name' => $validated['custodian_name'],
                'custodian_id_number' => $validated['custodian_id_number'] ?: $existing->custodian_id_number,
                'custodian_department' => $validated['custodian_department'] ?: $existing->custodian_department,
                'assigned_at' => Carbon::parse($validated['assigned_at']),
                'control_number' => $validated['control_number'] ?: $existing->control_number,
                'document_pdf_path' => $path,
                'observations' => $validated['observations'] ?: $existing->observations,
            ]);
            $assignment = $existing;
        } else {
            $assignment = InventoryAssignment::create([
                'assignment_code' => $code,
                'inventory_asset_id' => $asset->id,
                'user_id' => Auth::id(),
                'custodian_name' => $validated['custodian_name'],
                'custodian_id_number' => $validated['custodian_id_number'],
                'custodian_department' => $validated['custodian_department'],
                'monitored_site_id' => $asset->monitored_site_id,
                'movement_type' => 'assignment',
                'provenance' => 'fisico_existente',
                'control_number' => $validated['control_number'],
                'assigned_at' => Carbon::parse($validated['assigned_at']),
                'document_pdf_path' => $path,
                'status' => 'active',
                'observations' => $validated['observations'] ?: 'Hoja de asignación física digitalizada e incorporada al sistema',
            ]);
        }

        // Actualizar datos del activo
        $asset->update([
            'status' => 'assigned',
            'custodian_name' => $validated['custodian_name'],
            'custodian_id_number' => $validated['custodian_id_number'],
            'custodian_department' => $validated['custodian_department'],
        ]);

        // Registrar auditoría
        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'uploaded_physical_assignment',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $asset->id,
            'entity_name' => $asset->display_identity,
            'entity_label' => $code,
            'description' => "Se cargó hoja física digitalizada de asignación para '{$asset->display_identity}' (Asignado a: {$validated['custodian_name']}, Archivo: {$fileName})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', "Hoja de asignación física digitalizada exitosamente para '{$asset->display_identity}'. Activo marcado como ASIGNADO.");
    }
}
