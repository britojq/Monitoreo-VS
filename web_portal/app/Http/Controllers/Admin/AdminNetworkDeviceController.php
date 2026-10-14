<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryAsset;
use App\Models\InventoryComponentHistory;
use App\Models\InventoryPeripheral;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;
use App\Models\MonitoringSnapshot;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminNetworkDeviceController extends Controller
{
    /**
     * Muestra el listado centralizado de dispositivos de red de todas las sedes.
     * Integración unificada NOC + ITAM con fichas técnicas, Asignacion de Activos, inventario y telemetría.
     * Accesible para Operadores y Administradores.
     */
    public function index(Request $request): View
    {
        $siteFilter = $request->query('site_id');
        $typeFilter = $request->query('type');
        $statusFilter = $request->query('status');
        $search = $request->query('search');

        $query = InventoryAsset::with([
            'site',
            'components',
            'networkInterfaces',
            'peripherals',
            'assignments' => fn($q) => $q->orderBy('assigned_at', 'desc'),
            'maintenanceTickets' => fn($q) => $q->orderBy('created_at', 'desc'),
            'componentHistories' => fn($q) => $q->orderBy('recorded_at', 'desc'),
            'discoveredDevice',
            'snmpDevice.interfaces',
            'netRadarHost',
            'wolDevice',
        ]);

        if (!empty($siteFilter)) {
            $query->where('monitored_site_id', $siteFilter);
        }

        if (!empty($typeFilter)) {
            $query->where('type', $typeFilter);
        }

        // Base query para métricas superiores (conserva filtros de sede y tipo si están seleccionados)
        $baseQuery = clone $query;
        if (!empty($search)) {
            $baseQuery->search($search);
        }

        $totalCount = (clone $baseQuery)->count();
        $activeCount = (clone $baseQuery)->where('is_active', true)->count();
        $inactiveCount = (clone $baseQuery)->where('is_active', false)->count();
        $withAssignmentCount = (clone $baseQuery)->where(function ($q) {
            $q->whereHas('assignments', fn($qa) => $qa->where('status', 'active'))
              ->orWhere('status', 'assigned');
        })->count();
        $withoutAssignmentCount = (clone $baseQuery)->whereDoesntHave('assignments', fn($qa) => $qa->where('status', 'active'))
            ->where('status', '!=', 'assigned')
            ->count();
        $maintenanceCount = (clone $baseQuery)->where('status', 'maintenance')->count();

        // Aplicación del filtro de estado específico para la tabla paginada
        if (!empty($statusFilter)) {
            if ($statusFilter === 'active') {
                $query->where('is_active', true);
            } elseif ($statusFilter === 'inactive') {
                $query->where('is_active', false);
            } elseif ($statusFilter === 'assigned' || $statusFilter === 'with_assignment') {
                $query->where(function ($q) {
                    $q->whereHas('assignments', fn($qa) => $qa->where('status', 'active'))
                      ->orWhere('status', 'assigned');
                });
            } elseif ($statusFilter === 'unassigned' || $statusFilter === 'without_assignment') {
                $query->whereDoesntHave('assignments', fn($qa) => $qa->where('status', 'active'))
                      ->where('status', '!=', 'assigned');
            } else {
                $query->where('status', $statusFilter);
            }
        }

        if (!empty($search)) {
            $query->search($search);
        }

        $assignedCount = $withAssignmentCount;
        $unassignedCount = $withoutAssignmentCount;

        $devices = $query->orderBy('monitored_site_id')->orderBy('device_number')->paginate(15)->withQueryString();
        $sites = MonitoredSite::where('is_active', true)->orderBy('sort_order')->get();
        $technicians = User::where('is_active', true)->orderBy('name')->get();

        $latestSnapshot = MonitoringSnapshot::latest()->first();
        $snapshotDevices = ($latestSnapshot && isset($latestSnapshot->payload_json['network_devices']))
            ? collect($latestSnapshot->payload_json['network_devices'])->keyBy('id')
            : collect();

        return view('admin.devices.index', compact(
            'devices',
            'sites',
            'siteFilter',
            'typeFilter',
            'statusFilter',
            'search',
            'snapshotDevices',
            'totalCount',
            'activeCount',
            'inactiveCount',
            'assignedCount',
            'withAssignmentCount',
            'withoutAssignmentCount',
            'unassignedCount',
            'maintenanceCount',
            'technicians'
        ));
    }

    /**
     * Registra un nuevo dispositivo de red y lo asigna a una sede.
     * EXCLUSIVO PARA ADMINISTRADORES.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            abort(403, 'Acceso Denegado: Solo administradores pueden agregar dispositivos.');
        }

        $typeInput = $request->input('type');
        $isPassive = in_array($typeInput, ['monitor', 'keyboard', 'mouse', 'headset', 'scanner', 'surge_protector', 'other_peripheral'], true);

        $validated = $request->validate([
            'monitored_site_id' => ['required', 'exists:monitored_sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in(['assigned', 'in_stock', 'in_maintenance', 'in_transit', 'damaged', 'decommissioned'])],
            'ip' => $isPassive 
                ? ['nullable', 'string', 'max:255', 'unique:monitored_network_devices,ip'] 
                : ['required', 'string', 'max:255', 'unique:monitored_network_devices,ip'],
            'mac' => ['nullable', 'string', 'max:50'],
            'vendor_data' => ['nullable', 'string', 'max:255'],
            'access_type' => $isPassive 
                ? ['nullable', 'string', Rule::in(['TELNET', 'SSH', 'WEB', 'VNC', 'SSH+VNC', 'SIN SOPORTE'])] 
                : ['required', 'string', Rule::in(['TELNET', 'SSH', 'WEB', 'VNC', 'SSH+VNC', 'SIN SOPORTE'])],
            'access_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'vnc_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'web_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'telnet_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_username' => ['nullable', 'string', 'max:100'],
            'ssh_password' => ['nullable', 'string', 'max:255'],
            'ssh_enable_secret' => ['nullable', 'string', 'max:255'],
            'vnc_password' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial' => ['nullable', 'string', 'max:100'],
            'ports' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'telegram_alert_enabled' => ['nullable', 'boolean'],
            'telegram_alert_target' => ['nullable', 'string', Rule::in(['owner', 'group'])],
            'deploy_glpi' => ['nullable', 'boolean'],
            'glpi_os' => ['nullable', 'string', Rule::in(['windows', 'linux'])],
            'glpi_user' => ['nullable', 'string', 'max:100'],
            'glpi_password' => ['nullable', 'string', 'max:255'],
            'glpi_domain' => ['nullable', 'string', 'max:100'],
            'glpi_target' => ['nullable', 'string', Rule::in(['dev', 'prod'])],
            'custodian_name' => ['nullable', 'string', 'max:128'],
            'custodian_id_number' => ['nullable', 'string', 'max:32'],
            'custodian_department' => ['nullable', 'string', 'max:128'],
            'custodian_role' => ['nullable', 'string', 'max:96'],
            'user_id' => ['nullable', 'exists:users,id'],
            'has_peripherals_form' => ['nullable', 'boolean'],
            'monitor_brand' => ['nullable', 'string', 'max:64'],
            'monitor_model' => ['nullable', 'string', 'max:96'],
            'monitor_serial' => ['nullable', 'string', 'max:128'],
            'keyboard_brand' => ['nullable', 'string', 'max:64'],
            'keyboard_model' => ['nullable', 'string', 'max:96'],
            'keyboard_serial' => ['nullable', 'string', 'max:128'],
            'mouse_brand' => ['nullable', 'string', 'max:64'],
            'mouse_model' => ['nullable', 'string', 'max:96'],
            'mouse_serial' => ['nullable', 'string', 'max:128'],
            'protector_type' => ['nullable', 'string', Rule::in(['surge_protector', 'ups'])],
            'protector_brand' => ['nullable', 'string', 'max:64'],
            'protector_model' => ['nullable', 'string', 'max:96'],
            'protector_serial' => ['nullable', 'string', 'max:128'],
            'extra_peripherals' => ['nullable', 'array'],
            'extra_peripherals.*.id' => ['nullable', 'integer'],
            'extra_peripherals.*.type' => ['nullable', 'string', 'max:50'],
            'extra_peripherals.*.brand' => ['nullable', 'string', 'max:64'],
            'extra_peripherals.*.model' => ['nullable', 'string', 'max:96'],
            'extra_peripherals.*.serial' => ['nullable', 'string', 'max:128'],
            'extra_peripherals.*.remove' => ['nullable', 'boolean'],
        ]);

        $validated['access_type'] = !empty($validated['access_type']) ? strtoupper(trim($validated['access_type'])) : 'SIN SOPORTE';
        if ($validated['access_type'] === 'SSH+VNC') {
            $validated['ssh_port'] = $validated['ssh_port'] ?? ($validated['access_port'] ?? 22);
            $validated['vnc_port'] = $validated['vnc_port'] ?? 5900;
            $validated['access_port'] = $validated['ssh_port'];
        } elseif ($validated['access_type'] === 'SSH') {
            $validated['ssh_port'] = $validated['ssh_port'] ?? ($validated['access_port'] ?? 22);
            $validated['access_port'] = $validated['ssh_port'];
        } elseif ($validated['access_type'] === 'VNC') {
            $validated['vnc_port'] = $validated['vnc_port'] ?? ($validated['access_port'] ?? 5900);
            $validated['access_port'] = $validated['vnc_port'];
        } elseif ($validated['access_type'] === 'TELNET') {
            $validated['telnet_port'] = $validated['telnet_port'] ?? ($validated['access_port'] ?? 23);
            $validated['access_port'] = $validated['telnet_port'];
        } elseif ($validated['access_type'] === 'WEB') {
            $validated['web_port'] = $validated['web_port'] ?? ($validated['access_port'] ?? 80);
            $validated['access_port'] = $validated['web_port'];
        } else {
            $validated['access_port'] = null;
        }

        if ($request->filled('ssh_password')) {
            $validated['ssh_password_encrypted'] = $request->input('ssh_password');
        }
        if ($request->filled('ssh_enable_secret')) {
            $validated['ssh_enable_secret_encrypted'] = $request->input('ssh_enable_secret');
        }
        if ($request->filled('vnc_password')) {
            $validated['vnc_password_encrypted'] = $request->input('vnc_password');
        }

        // Si no tiene IP o es periférico pasivo, forzar ping ICMP inactivo
        if ($isPassive || empty($validated['ip'])) {
            $validated['is_active'] = false;
        } else {
            $validated['is_active'] = $request->boolean('is_active', true);
        }

        $validated['telegram_alert_enabled'] = $request->boolean('telegram_alert_enabled');
        $validated['telegram_alert_target'] = in_array($request->input('telegram_alert_target'), ['owner', 'group']) ? $request->input('telegram_alert_target') : 'owner';
        $nextNum = (MonitoredNetworkDevice::where('monitored_site_id', $validated['monitored_site_id'])->max('device_number') ?? 0) + 1;
        $validated['device_number'] = $nextNum;
        $validated['sort_order'] = MonitoredNetworkDevice::count() + 1;

        $explicitType = $validated['type'] ?? null;
        $custodianName = $request->input('custodian_name');
        $custodianIdNumber = $request->input('custodian_id_number');
        $custodianDept = $request->input('custodian_department');
        $custodianRole = $request->input('custodian_role');
        $userId = $request->input('user_id');

        $initialStatus = $validated['status'] ?? ($isPassive ? 'in_stock' : (!empty($custodianName) ? 'assigned' : 'in_stock'));
        if (!empty($custodianName)) {
            $initialStatus = 'assigned';
        }

        unset(
            $validated['type'], 
            $validated['status'], 
            $validated['deploy_glpi'], 
            $validated['glpi_os'], 
            $validated['glpi_user'], 
            $validated['glpi_password'], 
            $validated['glpi_domain'], 
            $validated['glpi_target'],
            $validated['custodian_name'],
            $validated['custodian_id_number'],
            $validated['custodian_department'],
            $validated['custodian_role'],
            $validated['user_id'],
            $validated['has_peripherals_form'],
            $validated['monitor_brand'],
            $validated['monitor_model'],
            $validated['monitor_serial'],
            $validated['keyboard_brand'],
            $validated['keyboard_model'],
            $validated['keyboard_serial'],
            $validated['mouse_brand'],
            $validated['mouse_model'],
            $validated['mouse_serial'],
            $validated['protector_type'],
            $validated['protector_brand'],
            $validated['protector_model'],
            $validated['protector_serial'],
            $validated['extra_peripherals']
        );

        $device = MonitoredNetworkDevice::create($validated);

        // Determinar tipo canónico para censo de activos
        $type = $explicitType;
        if (empty($type)) {
            $upper = strtoupper($device->name . ' ' . ($device->model ?? ''));
            $type = 'desktop';
            if (preg_match('/\bROUTER\b/i', $upper)) {
                $type = 'router';
            } elseif (preg_match('/\b(SWITCH|CONMUTADOR)\b/i', $upper) || preg_match('/\bSW\d+\b/i', $upper)) {
                $type = 'switch';
            } elseif (preg_match('/\b(IMPRESORA|PRINTER)\b/i', $upper)) {
                $type = 'printer';
            } elseif (preg_match('/\b(SERVIDOR|SERVER)\b/i', $upper)) {
                $type = 'server';
            }
        }

        \App\Models\InventoryAsset::where('id', $device->id)->update([
            'type' => $type,
            'status' => $initialStatus,
            'custodian_name' => !empty($custodianName) ? $custodianName : null,
            'custodian_id_number' => !empty($custodianIdNumber) ? $custodianIdNumber : null,
            'custodian_department' => !empty($custodianDept) ? $custodianDept : null,
            'current_user_id' => !empty($userId) ? $userId : null,
            'monitored_network_device_id' => $device->id,
            'location_detail' => $device->site?->name ?? 'Sede Regional',
        ]);

        // Si tiene asignación inicial formal, registrar acta en InventoryAssignment
        if (!empty($custodianName)) {
            try {
                $year = now()->format('Y');
                $prefix = "UINF-4.05-{$year}";
                $countThisYear = \App\Models\InventoryAssignment::where('assignment_code', 'like', "{$prefix}-%")->count() + 1;
                $code = sprintf("%s-%04d", $prefix, $countThisYear);

                \App\Models\InventoryAssignment::create([
                    'assignment_code' => $code,
                    'inventory_asset_id' => $device->id,
                    'user_id' => !empty($userId) ? $userId : null,
                    'custodian_name' => $custodianName,
                    'custodian_id_number' => $custodianIdNumber,
                    'custodian_department' => $custodianDept,
                    'custodian_role' => $custodianRole,
                    'monitored_site_id' => $device->monitored_site_id,
                    'movement_type' => 'assignment',
                    'provenance' => 'nuevo',
                    'checkpoint_name' => $device->site?->name ?? 'Valle Seco',
                    'control_number' => $code,
                    'assigned_at' => now(),
                    'status' => 'active',
                    'delivered_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'observations' => 'Asignación inicial registrada en la creación del dispositivo.',
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Fallo al registrar InventoryAssignment inicial para {$device->name}: " . $e->getMessage());
            }
        }

        // Si es periférico de puesto, reflejarlo en inventory_peripherals para actas UINF-4.05
        if (in_array($type, ['monitor', 'keyboard', 'mouse', 'headset', 'scanner', 'surge_protector'], true)) {
            \App\Models\InventoryPeripheral::create([
                'asset_tag' => 'PER-' . str_pad($device->id, 5, '0', STR_PAD_LEFT),
                'type' => $type,
                'brand' => $device->vendor_data,
                'model' => $device->model,
                'serial_number' => $device->serial,
                'status' => $initialStatus,
                'monitored_site_id' => $device->monitored_site_id,
                'notes' => $device->notes,
            ]);
        }

        // Sincronizar periféricos asociados al puesto (Monitor, Teclado, Mouse, Protector/UPS + Extras)
        $asset = \App\Models\InventoryAsset::find($device->id);
        if ($asset && ($request->has('has_peripherals_form') || $request->hasAny(['monitor_brand', 'keyboard_brand', 'mouse_brand', 'protector_brand', 'extra_peripherals']))) {
            $this->syncPeripheralsFromRequest($request, $asset);
        }

        // Si se solicitó despliegue GLPI en PC/Laptop con IP
        if ($request->boolean('deploy_glpi') && !empty($device->ip) && in_array($type, ['desktop', 'laptop'], true)) {
            try {
                app(\App\Services\Deployment\AgentDeployService::class)->deploy(
                    host: $device->ip,
                    os: $request->input('glpi_os', 'windows'),
                    user: $request->input('glpi_user', 'Administrador'),
                    target: $request->input('glpi_target', 'dev'),
                    action: 'install',
                    password: $request->input('glpi_password'),
                    domain: $request->input('glpi_domain')
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Fallo al disparar despliegue GLPI para {$device->ip}: " . $e->getMessage());
            }
        }

        app(SyncController::class)->exportToConfigFiles();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Dispositivo [{$device->name}] registrado exitosamente.",
                'device' => $device,
            ]);
        }

        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$device->name}] creado y asignado exitosamente.");
    }

    /**
     * Actualiza la información técnica y de red de un dispositivo.
     * EXCLUSIVO PARA ADMINISTRADORES.
     */
    public function update(Request $request, MonitoredNetworkDevice $device): RedirectResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            abort(403, 'Acceso Denegado: Solo administradores pueden modificar los dispositivos.');
        }

        $validated = $request->validate([
            'monitored_site_id' => ['nullable', 'exists:monitored_sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'ip' => ['required', 'string', 'max:255', Rule::unique('monitored_network_devices', 'ip')->ignore($device->id)],
            'mac' => ['nullable', 'string', 'max:50'],
            'vendor_data' => ['nullable', 'string', 'max:255'],
            'access_type' => ['required', 'string', Rule::in(['TELNET', 'SSH', 'WEB', 'VNC', 'SSH+VNC', 'SIN SOPORTE'])],
            'access_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'vnc_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'web_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'telnet_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_username' => ['nullable', 'string', 'max:100'],
            'ssh_password' => ['nullable', 'string', 'max:255'],
            'ssh_enable_secret' => ['nullable', 'string', 'max:255'],
            'clear_ssh_credentials' => ['nullable', 'boolean'],
            'vnc_password' => ['nullable', 'string', 'max:255'],
            'clear_vnc_credentials' => ['nullable', 'boolean'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial' => ['nullable', 'string', 'max:100'],
            'ports' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'telegram_alert_enabled' => ['nullable', 'boolean'],
            'telegram_alert_target' => ['nullable', 'string', Rule::in(['owner', 'group'])],
            'status' => ['nullable', 'string', Rule::in(['assigned', 'in_stock', 'in_maintenance', 'in_transit', 'damaged', 'decommissioned'])],
            'assignment_action' => ['nullable', 'string', Rule::in(['keep', 'assign', 'release'])],
            'custodian_name' => ['nullable', 'string', 'max:128'],
            'custodian_id_number' => ['nullable', 'string', 'max:32'],
            'custodian_department' => ['nullable', 'string', 'max:128'],
            'custodian_role' => ['nullable', 'string', 'max:96'],
            'user_id' => ['nullable', 'exists:users,id'],
            'monitor_brand' => ['nullable', 'string', 'max:64'],
            'monitor_model' => ['nullable', 'string', 'max:96'],
            'monitor_serial' => ['nullable', 'string', 'max:128'],
            'remove_monitor' => ['nullable', 'boolean'],
            'keyboard_brand' => ['nullable', 'string', 'max:64'],
            'keyboard_model' => ['nullable', 'string', 'max:96'],
            'keyboard_serial' => ['nullable', 'string', 'max:128'],
            'remove_keyboard' => ['nullable', 'boolean'],
            'mouse_brand' => ['nullable', 'string', 'max:64'],
            'mouse_model' => ['nullable', 'string', 'max:96'],
            'mouse_serial' => ['nullable', 'string', 'max:128'],
            'remove_mouse' => ['nullable', 'boolean'],
            'protector_type' => ['nullable', 'string', Rule::in(['surge_protector', 'ups'])],
            'protector_brand' => ['nullable', 'string', 'max:64'],
            'protector_model' => ['nullable', 'string', 'max:96'],
            'protector_serial' => ['nullable', 'string', 'max:128'],
            'remove_protector' => ['nullable', 'boolean'],
            'has_peripherals_form' => ['nullable', 'boolean'],
            'extra_peripherals' => ['nullable', 'array'],
            'extra_peripherals.*.id' => ['nullable', 'integer'],
            'extra_peripherals.*.type' => ['nullable', 'string', 'max:50'],
            'extra_peripherals.*.brand' => ['nullable', 'string', 'max:64'],
            'extra_peripherals.*.model' => ['nullable', 'string', 'max:96'],
            'extra_peripherals.*.serial' => ['nullable', 'string', 'max:128'],
            'extra_peripherals.*.remove' => ['nullable', 'boolean'],
        ]);

        $validated['access_type'] = strtoupper(trim($validated['access_type']));
        if ($validated['access_type'] === 'SSH+VNC') {
            $validated['ssh_port'] = $validated['ssh_port'] ?? ($validated['access_port'] ?? 22);
            $validated['vnc_port'] = $validated['vnc_port'] ?? 5900;
            $validated['access_port'] = $validated['ssh_port'];
        } elseif ($validated['access_type'] === 'SSH') {
            $validated['ssh_port'] = $validated['ssh_port'] ?? ($validated['access_port'] ?? 22);
            $validated['access_port'] = $validated['ssh_port'];
        } elseif ($validated['access_type'] === 'VNC') {
            $validated['vnc_port'] = $validated['vnc_port'] ?? ($validated['access_port'] ?? 5900);
            $validated['access_port'] = $validated['vnc_port'];
        } elseif ($validated['access_type'] === 'TELNET') {
            $validated['telnet_port'] = $validated['telnet_port'] ?? ($validated['access_port'] ?? 23);
            $validated['access_port'] = $validated['telnet_port'];
        } elseif ($validated['access_type'] === 'WEB') {
            $validated['web_port'] = $validated['web_port'] ?? ($validated['access_port'] ?? 80);
            $validated['access_port'] = $validated['web_port'];
        } else {
            $validated['access_port'] = null;
        }

        if ($request->filled('ssh_password')) {
            $validated['ssh_password_encrypted'] = $request->input('ssh_password');
        }
        if ($request->filled('ssh_enable_secret')) {
            $validated['ssh_enable_secret_encrypted'] = $request->input('ssh_enable_secret');
        }
        if ($request->boolean('clear_ssh_credentials')) {
            $validated['ssh_username'] = null;
            $validated['ssh_password_encrypted'] = null;
            $validated['ssh_enable_secret_encrypted'] = null;
        }
        if ($request->filled('vnc_password')) {
            $validated['vnc_password_encrypted'] = $request->input('vnc_password');
        }
        if ($request->boolean('clear_vnc_credentials')) {
            $validated['vnc_password_encrypted'] = null;
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['telegram_alert_enabled'] = $request->boolean('telegram_alert_enabled');
        $validated['telegram_alert_target'] = in_array($request->input('telegram_alert_target'), ['owner', 'group']) ? $request->input('telegram_alert_target') : 'owner';
        $oldIp = $device->ip;

        $oldSerial = trim((string)($device->serial ?? ''));
        $newSerial = trim((string)($validated['serial'] ?? ''));

        $explicitType = $validated['type'] ?? null;
        unset(
            $validated['type'],
            $validated['status'],
            $validated['assignment_action'],
            $validated['custodian_name'],
            $validated['custodian_id_number'],
            $validated['custodian_department'],
            $validated['custodian_role'],
            $validated['user_id'],
            $validated['monitor_brand'],
            $validated['monitor_model'],
            $validated['monitor_serial'],
            $validated['remove_monitor'],
            $validated['keyboard_brand'],
            $validated['keyboard_model'],
            $validated['keyboard_serial'],
            $validated['remove_keyboard'],
            $validated['mouse_brand'],
            $validated['mouse_model'],
            $validated['mouse_serial'],
            $validated['remove_mouse'],
            $validated['protector_type'],
            $validated['protector_brand'],
            $validated['protector_model'],
            $validated['protector_serial'],
            $validated['remove_protector'],
            $validated['has_peripherals_form'],
            $validated['extra_peripherals']
        );

        $device->update($validated);

        // Sincronizar en monitored_site_devices
        $siteId = $validated['monitored_site_id'] ?? $device->monitored_site_id;
        $asset = InventoryAsset::find($device->id);

        // Actualizar tipo de hardware en inventario
        $type = $explicitType ?: ($asset?->type ?: null);
        if (empty($type)) {
            $upper = strtoupper($device->name . ' ' . ($device->model ?? ''));
            $type = 'desktop';
            if (preg_match('/\bROUTER\b/i', $upper)) {
                $type = 'router';
            } elseif (preg_match('/\b(SWITCH|CONMUTADOR)\b/i', $upper) || preg_match('/\bSW\d+\b/i', $upper)) {
                $type = 'switch';
            } elseif (preg_match('/\b(IMPRESORA|PRINTER)\b/i', $upper)) {
                $type = 'printer';
            } elseif (preg_match('/\b(SERVIDOR|SERVER)\b/i', $upper)) {
                $type = 'server';
            }
        }

        // Auditoría inmutable de modificación manual de Serial
        if ($newSerial !== '' && $newSerial !== $oldSerial) {
            $hasGlpi = $asset && ($asset->last_glpi_sync_at !== null);
            InventoryComponentHistory::create([
                'inventory_asset_id' => $device->id,
                'action' => 'modified',
                'component_type' => 'serial',
                'old_value' => ['serial' => $oldSerial ?: 'Sin Serial'],
                'new_value' => ['serial' => $newSerial],
                'reason' => 'Modificación manual de número de serie por el administrador' . ($hasGlpi ? ' (⚠️ Advertencia: Dispositivo con agente GLPI activo; el serial será actualizado en el próximo reporte automático)' : ''),
                'registered_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
                'recorded_at' => now(),
            ]);
        }

        $assetUpdate = [
            'type' => $type,
            'monitored_site_id' => $device->monitored_site_id,
            'name' => $device->name,
            'ip_address' => $device->ip,
            'mac_address' => $device->mac,
            'brand' => $device->vendor_data,
            'model' => $device->model,
            'serial_number' => $device->serial,
            'notes' => $device->notes,
        ];

        $assignmentAction = $request->input('assignment_action', 'keep');
        if ($assignmentAction === 'release') {
            $assetUpdate['status'] = 'in_stock';
            $assetUpdate['custodian_name'] = null;
            $assetUpdate['custodian_id_number'] = null;
            $assetUpdate['custodian_department'] = null;
            $assetUpdate['current_user_id'] = null;

            try {
                $year = now()->format('Y');
                $count = \App\Models\InventoryAssignment::where('assignment_code', 'like', "ACTA-DEV-{$year}-%")->count() + 1;
                $code = sprintf("ACTA-DEV-%s-%04d", $year, $count);

                \App\Models\InventoryAssignment::create([
                    'assignment_code' => $code,
                    'inventory_asset_id' => $device->id,
                    'movement_type' => 'return',
                    'custodian_name' => $asset?->custodian_name ?? 'Almacén / Stock',
                    'monitored_site_id' => $device->monitored_site_id,
                    'provenance' => 'stock',
                    'assigned_at' => now(),
                    'returned_at' => now(),
                    'status' => 'active',
                    'delivered_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'observations' => 'Devolución a stock ejecutada desde edición de dispositivo.',
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Fallo al registrar devolución en InventoryAssignment para {$device->name}: " . $e->getMessage());
            }
        } elseif ($assignmentAction === 'assign' && $request->filled('custodian_name')) {
            $custodianName = $request->input('custodian_name');
            $custodianIdNumber = $request->input('custodian_id_number');
            $custodianDept = $request->input('custodian_department');
            $custodianRole = $request->input('custodian_role');
            $userId = $request->input('user_id');

            $assetUpdate['status'] = 'assigned';
            $assetUpdate['custodian_name'] = $custodianName;
            $assetUpdate['custodian_id_number'] = $custodianIdNumber;
            $assetUpdate['custodian_department'] = $custodianDept;
            $assetUpdate['current_user_id'] = $userId;

            try {
                $year = now()->format('Y');
                $count = \App\Models\InventoryAssignment::where('assignment_code', 'like', "UINF-4.05-{$year}-%")->count() + 1;
                $code = sprintf("UINF-4.05-%s-%04d", $year, $count);

                \App\Models\InventoryAssignment::create([
                    'assignment_code' => $code,
                    'inventory_asset_id' => $device->id,
                    'user_id' => $userId,
                    'custodian_name' => $custodianName,
                    'custodian_id_number' => $custodianIdNumber,
                    'custodian_department' => $custodianDept,
                    'custodian_role' => $custodianRole,
                    'monitored_site_id' => $device->monitored_site_id,
                    'movement_type' => 'assignment',
                    'provenance' => 'stock',
                    'assigned_at' => now(),
                    'status' => 'active',
                    'delivered_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'observations' => 'Asignación registrada desde edición de dispositivo.',
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Fallo al registrar InventoryAssignment para {$device->name}: " . $e->getMessage());
            }
        } elseif ($request->filled('status')) {
            $assetUpdate['status'] = $request->input('status');
        }

        if ($asset) {
            $asset->update($assetUpdate);

            // Sincronizar periféricos asociados al puesto (Monitor, Teclado, Mouse, Protector/UPS)
            if ($request->has('has_peripherals_form') || $request->hasAny(['monitor_brand', 'keyboard_brand', 'mouse_brand', 'protector_brand', 'remove_monitor', 'remove_keyboard', 'remove_mouse', 'remove_protector'])) {
                $this->syncPeripheralsFromRequest($request, $asset);
            }
        }

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$device->name}] actualizado y sincronizado exitosamente.");
    }

    /**
     * Sincroniza los periféricos de puesto asociados al activo ITAM.
     */
    protected function syncPeripheralsFromRequest(Request $request, InventoryAsset $asset): void
    {
        $groups = [
            [
                'type' => 'monitor',
                'brand' => trim((string)$request->input('monitor_brand', '')),
                'model' => trim((string)$request->input('monitor_model', '')),
                'serial' => trim((string)$request->input('monitor_serial', '')),
                'remove' => $request->boolean('remove_monitor'),
            ],
            [
                'type' => 'keyboard',
                'brand' => trim((string)$request->input('keyboard_brand', '')),
                'model' => trim((string)$request->input('keyboard_model', '')),
                'serial' => trim((string)$request->input('keyboard_serial', '')),
                'remove' => $request->boolean('remove_keyboard'),
            ],
            [
                'type' => 'mouse',
                'brand' => trim((string)$request->input('mouse_brand', '')),
                'model' => trim((string)$request->input('mouse_model', '')),
                'serial' => trim((string)$request->input('mouse_serial', '')),
                'remove' => $request->boolean('remove_mouse'),
            ],
            [
                'type' => in_array($request->input('protector_type'), ['ups', 'surge_protector'], true) ? $request->input('protector_type') : 'surge_protector',
                'match_types' => ['surge_protector', 'ups'],
                'brand' => trim((string)$request->input('protector_brand', '')),
                'model' => trim((string)$request->input('protector_model', '')),
                'serial' => trim((string)$request->input('protector_serial', '')),
                'remove' => $request->boolean('remove_protector'),
            ],
        ];

        foreach ($groups as $g) {
            $type = $g['type'];
            $matchTypes = $g['match_types'] ?? [$type];
            $brand = $g['brand'];
            $model = $g['model'];
            $serial = $g['serial'];
            $remove = $g['remove'];

            $existing = InventoryPeripheral::where('current_asset_id', $asset->id)
                ->whereIn('type', $matchTypes)
                ->first();

            $hasData = ($brand !== '' || $model !== '' || $serial !== '');

            if ($remove || (!$hasData && $existing)) {
                if ($existing) {
                    $oldData = [
                        'brand' => $existing->brand,
                        'model' => $existing->model,
                        'serial' => $existing->serial_number,
                    ];
                    $existing->update([
                        'current_asset_id' => null,
                        'status' => 'in_stock',
                    ]);
                    InventoryComponentHistory::create([
                        'inventory_asset_id' => $asset->id,
                        'action' => 'removed',
                        'component_type' => $existing->type,
                        'old_value' => $oldData,
                        'new_value' => null,
                        'reason' => 'Periférico desvinculado desde edición de puesto',
                        'registered_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
                        'recorded_at' => now(),
                    ]);
                }
            } elseif ($hasData) {
                $newData = [
                    'brand' => $brand ?: 'Genérico',
                    'model' => $model ?: 'Estándar',
                    'serial' => $serial ?: null,
                ];

                if ($existing) {
                    $changed = ($existing->brand !== $newData['brand']) ||
                               ($existing->model !== $newData['model']) ||
                               ($existing->serial_number !== $newData['serial']) ||
                               ($existing->type !== $type);

                    if ($changed) {
                        $oldData = [
                            'brand' => $existing->brand,
                            'model' => $existing->model,
                            'serial' => $existing->serial_number,
                        ];
                        $existing->update([
                            'type' => $type,
                            'brand' => $newData['brand'],
                            'model' => $newData['model'],
                            'serial_number' => $newData['serial'],
                            'status' => 'assigned',
                            'monitored_site_id' => $asset->monitored_site_id,
                        ]);
                        InventoryComponentHistory::create([
                            'inventory_asset_id' => $asset->id,
                            'action' => 'replaced',
                            'component_type' => $type,
                            'old_value' => $oldData,
                            'new_value' => $newData,
                            'reason' => 'Periférico actualizado desde edición de puesto',
                            'registered_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
                            'recorded_at' => now(),
                        ]);
                    }
                } else {
                    $prefix = match($type) {
                        'monitor' => 'MON',
                        'keyboard' => 'KBD',
                        'mouse' => 'MSE',
                        'surge_protector', 'ups' => 'PWR',
                        default => 'PER',
                    };
                    $tag = $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
                    InventoryPeripheral::create([
                        'asset_tag' => $tag,
                        'type' => $type,
                        'brand' => $newData['brand'],
                        'model' => $newData['model'],
                        'serial_number' => $newData['serial'],
                        'status' => 'assigned',
                        'monitored_site_id' => $asset->monitored_site_id,
                        'current_asset_id' => $asset->id,
                    ]);
                    InventoryComponentHistory::create([
                        'inventory_asset_id' => $asset->id,
                        'action' => 'added',
                        'component_type' => $type,
                        'old_value' => null,
                        'new_value' => $newData,
                        'reason' => 'Periférico asociado desde edición de puesto',
                        'registered_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
                        'recorded_at' => now(),
                    ]);
                }
            }
        }

        // Periféricos adicionales dinámicos (Impresoras, Escáneres, Auriculares, etc.)
        $extraList = $request->input('extra_peripherals', []);
        if (is_array($extraList)) {
            foreach ($extraList as $extra) {
                if (!is_array($extra)) continue;
                $extraId = !empty($extra['id']) ? (int)$extra['id'] : null;
                $extraType = !empty($extra['type']) ? trim((string)$extra['type']) : 'other_peripheral';
                $extraBrand = trim((string)($extra['brand'] ?? ''));
                $extraModel = trim((string)($extra['model'] ?? ''));
                $extraSerial = trim((string)($extra['serial'] ?? ''));
                $extraRemove = !empty($extra['remove']);

                if ($extraId) {
                    $existingExtra = InventoryPeripheral::where('id', $extraId)
                        ->where('current_asset_id', $asset->id)
                        ->first();
                    if ($existingExtra) {
                        if ($extraRemove) {
                            $oldData = [
                                'brand' => $existingExtra->brand,
                                'model' => $existingExtra->model,
                                'serial' => $existingExtra->serial_number,
                            ];
                            $existingExtra->update([
                                'current_asset_id' => null,
                                'status' => 'in_stock',
                            ]);
                            InventoryComponentHistory::create([
                                'inventory_asset_id' => $asset->id,
                                'action' => 'removed',
                                'component_type' => $existingExtra->type,
                                'old_value' => $oldData,
                                'new_value' => null,
                                'reason' => 'Periférico adicional desvinculado desde puesto',
                                'registered_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
                                'recorded_at' => now(),
                            ]);
                        } else {
                            $hasData = ($extraBrand !== '' || $extraModel !== '' || $extraSerial !== '');
                            if ($hasData) {
                                $newData = [
                                    'brand' => $extraBrand ?: 'Genérico',
                                    'model' => $extraModel ?: 'Estándar',
                                    'serial' => $extraSerial ?: null,
                                ];
                                $changed = ($existingExtra->brand !== $newData['brand']) ||
                                           ($existingExtra->model !== $newData['model']) ||
                                           ($existingExtra->serial_number !== $newData['serial']) ||
                                           ($existingExtra->type !== $extraType);
                                if ($changed) {
                                    $oldData = [
                                        'brand' => $existingExtra->brand,
                                        'model' => $existingExtra->model,
                                        'serial' => $existingExtra->serial_number,
                                    ];
                                    $existingExtra->update([
                                        'type' => $extraType,
                                        'brand' => $newData['brand'],
                                        'model' => $newData['model'],
                                        'serial_number' => $newData['serial'],
                                        'status' => 'assigned',
                                        'monitored_site_id' => $asset->monitored_site_id,
                                    ]);
                                    InventoryComponentHistory::create([
                                        'inventory_asset_id' => $asset->id,
                                        'action' => 'replaced',
                                        'component_type' => $extraType,
                                        'old_value' => $oldData,
                                        'new_value' => $newData,
                                        'reason' => 'Periférico adicional actualizado desde puesto',
                                        'registered_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
                                        'recorded_at' => now(),
                                    ]);
                                }
                            }
                        }
                    }
                } else {
                    $hasData = ($extraBrand !== '' || $extraModel !== '' || $extraSerial !== '');
                    if ($hasData && !$extraRemove) {
                        $newData = [
                            'brand' => $extraBrand ?: 'Genérico',
                            'model' => $extraModel ?: 'Estándar',
                            'serial' => $extraSerial ?: null,
                        ];
                        $prefix = match($extraType) {
                            'printer' => 'PRN',
                            'scanner' => 'SCN',
                            'headset' => 'HDS',
                            'monitor' => 'MON',
                            'webcam' => 'CAM',
                            default => 'PER',
                        };
                        $tag = $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
                        InventoryPeripheral::create([
                            'asset_tag' => $tag,
                            'type' => $extraType,
                            'brand' => $newData['brand'],
                            'model' => $newData['model'],
                            'serial_number' => $newData['serial'],
                            'status' => 'assigned',
                            'monitored_site_id' => $asset->monitored_site_id,
                            'current_asset_id' => $asset->id,
                        ]);
                        InventoryComponentHistory::create([
                            'inventory_asset_id' => $asset->id,
                            'action' => 'added',
                            'component_type' => $extraType,
                            'old_value' => null,
                            'new_value' => $newData,
                            'reason' => 'Periférico adicional asociado desde gestión de puesto',
                            'registered_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
                            'recorded_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Elimina un dispositivo de red y su reflejo en la sede.
     * EXCLUSIVO PARA ADMINISTRADORES.
     */
    public function destroy(Request $request, MonitoredNetworkDevice $device): RedirectResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            abort(403, 'Acceso Denegado: Solo administradores pueden eliminar dispositivos.');
        }

        $name = $device->name;
        $device->delete();

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$name}] eliminado exitosamente.");
    }

    /**
     * Activa o desactiva el monitoreo de un dispositivo.
     * EXCLUSIVO PARA ADMINISTRADORES.
     */
    public function toggle(Request $request, MonitoredNetworkDevice $device): RedirectResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            abort(403, 'Acceso Denegado: Solo administradores pueden modificar el estado del dispositivo.');
        }

        $device->is_active = !$device->is_active;
        $device->save();

        app(SyncController::class)->exportToConfigFiles();

        $status = $device->is_active ? 'activado' : 'desactivado';
        return back()->with('success', "Dispositivo [{$device->name}] {$status} exitosamente.");
    }

    /**
     * Retorna datos históricos para gráficos y telemetría.
     * Accesible para Operadores y Administradores.
     */
    public function history(Request $request, MonitoredNetworkDevice $device): JsonResponse
    {
        $range = $request->query('range', '24h');
        $query = $device->histories()->reorder('checked_at', 'asc');

        switch ($range) {
            case '6h':
                $query->where('checked_at', '>=', now()->subHours(6));
                break;
            case '7d':
                $query->where('checked_at', '>=', now()->subDays(7));
                break;
            case '30d':
                $query->where('checked_at', '>=', now()->subDays(30));
                break;
            case '24h':
            default:
                $query->where('checked_at', '>=', now()->subHours(24));
                break;
        }

        $records = $query->get();

        $totalChecks = $records->count();
        $upChecks = $records->where('is_up', true)->count();
        $downChecks = $totalChecks - $upChecks;
        $uptimePct = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 2) : 0.0;

        $upRecords = $records->where('is_up', true);
        $avgLatency = $upRecords->count() > 0 ? round($upRecords->avg('latency_ms'), 2) : 0.0;
        $maxLatency = $upRecords->count() > 0 ? round($upRecords->max('latency_ms'), 2) : 0.0;
        $minLatency = $upRecords->count() > 0 ? round($upRecords->min('latency_ms'), 2) : 0.0;

        $labels = [];
        $latencies = [];
        $statuses = [];
        $points = [];

        foreach ($records as $r) {
            $formattedTime = ($range == '7d' || $range == '30d')
                ? $r->checked_at->format('d/m H:i')
                : $r->checked_at->format('H:i');

            $labels[] = $formattedTime;
            $latencies[] = $r->is_up ? (float) $r->latency_ms : 0.0;
            $statuses[] = $r->is_up ? 1 : 0;
            $points[] = [
                'time' => $r->checked_at->format('Y-m-d H:i:s'),
                'time_human' => $r->checked_at->diffForHumans(),
                'is_up' => $r->is_up,
                'latency_ms' => (float) $r->latency_ms,
                'status_message' => $r->status_message,
            ];
        }

        $incidents = $records->where('is_up', false)->sortByDesc('checked_at')->values()->map(function($r) {
            return [
                'time' => $r->checked_at->format('Y-m-d H:i:s'),
                'time_human' => $r->checked_at->diffForHumans(),
                'status_message' => $r->status_message ?: 'Timeout / Sin respuesta',
            ];
        });

        return response()->json([
            'success' => true,
            'device' => [
                'id' => $device->id,
                'device_number' => $device->device_number,
                'name' => $device->name,
                'ip' => $device->ip,
                'mac' => $device->mac,
                'vendor_data' => $device->vendor_data,
                'model' => $device->model,
                'serial' => $device->serial,
                'ports' => $device->ports,
                'notes' => $device->notes,
                'access_type' => $device->access_type,
                'access_port' => $device->access_port,
                'is_active' => $device->is_active,
            ],
            'range' => $range,
            'stats' => [
                'total_checks' => $totalChecks,
                'up_checks' => $upChecks,
                'down_checks' => $downChecks,
                'uptime_percentage' => $uptimePct,
                'avg_latency' => $avgLatency,
                'max_latency' => $maxLatency,
                'min_latency' => $minLatency,
                'last_check' => $records->last() ? $records->last()->checked_at->format('Y-m-d H:i:s') : null,
                'last_check_human' => $records->last() ? $records->last()->checked_at->diffForHumans() : null,
            ],
            'labels' => $labels,
            'latencies' => $latencies,
            'statuses' => $statuses,
            'points' => $points,
            'incidents' => $incidents,
        ]);
    }
}
