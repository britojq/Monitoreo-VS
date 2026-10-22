<?php

namespace App\Services\Deployment;

use App\Contracts\CommandRunnerInterface;
use App\Models\DiscoveredDevice;
use App\Models\InventoryAsset;

/**
 * Servicio de orquestación para el despliegue remoto del agente GLPI y escaneos de subred.
 * Cumple con el Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
 */
class AgentDeployService
{
    public function __construct(
        protected CommandRunnerInterface $commandRunner,
        protected \App\Contracts\GlpiEndpointConfigInterface $endpointConfig
    ) {}

    /**
     * Ejecuta el despliegue o conmutación remota hacia un equipo seleccionado.
     */
    public function deploy(
        string $host,
        string $os,
        string $user,
        string $target,
        string $action,
        ?string $password = null,
        ?string $domain = null,
        ?string $customIp = null
    ): array {
        $python = $this->getPythonBinary();
        $script = $this->getDeployerScriptPath();

        if (!$script) {
            return [
                'success' => false,
                'error' => 'El script de despliegue de agente (agent_deployer.py) no fue encontrado en el servidor.',
            ];
        }

        $targetInfo = $this->endpointConfig->resolveTargetUrl($target, $customIp);
        $serverUrl = $targetInfo['server_url'];
        $deployBaseUrl = $targetInfo['deploy_base_url'];

        $args = [
            $python,
            $script,
            '--host', $host,
            '--os', $os,
            '--user', $user,
            '--target', $target,
            '--server-url', $serverUrl,
            '--deploy-base', $deployBaseUrl,
            '--action', $action,
            '--json',
        ];

        if (!empty($password)) {
            $args[] = '--password';
            $args[] = $password;
        }

        if (!empty($domain) && $os === 'windows') {
            $args[] = '--domain';
            $args[] = $domain;
        }

        $result = $this->commandRunner->run($args, 120);

        $parsed = json_decode($result['output'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            return $parsed;
        }

        return [
            'success' => $result['success'],
            'stdout' => $result['output'],
            'stderr' => $result['error'],
        ];
    }

    /**
     * Escanea un segmento de red CIDR específico bajo demanda.
     */
    public function scanSubnet(string $rawSubnet): array
    {
        $rawSubnet = trim($rawSubnet);
        if (empty($rawSubnet)) {
            return [
                'success' => false,
                'error' => 'Debe especificar una subred a escanear.',
                'status_code' => 422,
            ];
        }

        if (preg_match('/^([0-9]{1,3}\.){3}0$/', $rawSubnet)) {
            $rawSubnet .= '/24';
        }

        if (!preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}$/', $rawSubnet)) {
            return [
                'success' => false,
                'error' => 'Formato de subred inválido. Utilice notación CIDR (ej: 192.168.106.0/24).',
                'status_code' => 422,
            ];
        }

        [$baseIp, $cidr] = explode('/', $rawSubnet, 2);
        $cidrInt = (int) $cidr;
        if ($cidrInt < 24 || $cidrInt > 30) {
            return [
                'success' => false,
                'error' => 'Por motivos de seguridad y estabilidad de red, solo se permite escanear segmentos entre /24 y /30.',
                'status_code' => 422,
            ];
        }

        $python = $this->getPythonBinary();
        $script = $this->getDiscoveryScriptPath();

        if (!$script) {
            return [
                'success' => false,
                'error' => 'El script de descubrimiento de red (network_discovery.py) no fue encontrado en el servidor.',
                'status_code' => 500,
            ];
        }

        $args = [$python, $script, '--subnet', $rawSubnet, '--manual'];
        $result = $this->commandRunner->run($args, 60);

        // Consultar cuántos dispositivos están en esa subred
        $allDiscovered = DiscoveredDevice::all();
        $discoveredInSubnet = $allDiscovered->filter(function ($dev) use ($rawSubnet) {
            return $this->ipInSubnet($dev->ip_address, $rawSubnet);
        });

        $count = $discoveredInSubnet->count();
        $redirectUrl = route('admin.inventory.deploy', ['scan_subnet' => $rawSubnet]);

        return [
            'success' => true,
            'subnet' => $rawSubnet,
            'count' => $count,
            'redirect_url' => $redirectUrl,
            'message' => "Escaneo completado. Se detectaron {$count} dispositivos en {$rawSubnet}.",
        ];
    }

    /**
     * Consulta rápida de verificación de reporte en base de datos.
     */
    public function quickStatus(?string $ip): array
    {
        $ip = trim((string) $ip);
        if (empty($ip)) {
            return [
                'reporting' => false,
                'message' => 'IP no especificada',
                'status_code' => 400,
            ];
        }

        $asset = InventoryAsset::where('ip_address', $ip)->first();

        if ($asset && $asset->last_glpi_sync_at) {
            return [
                'reporting' => true,
                'asset_id' => $asset->id,
                'name' => $asset->name,
                'os' => $asset->operating_system,
                'last_sync' => $asset->last_glpi_sync_at->diffForHumans(),
            ];
        }

        return [
            'reporting' => false,
            'message' => 'Aún no se registra reporte de telemetría para este equipo.',
        ];
    }

    /**
     * Determina si un dispositivo es compatible con la instalación de Agente GLPI.
     * Solo las estaciones de trabajo (PC, laptops, desktops o servidores) son compatibles.
     */
    public function isGlpiSupportedDevice(array $item, ?InventoryAsset $asset, string $osType): bool
    {
        // 1. Si existe activo en inventario ITAM, cotejar contra su tipo formal en la base de datos central
        if ($asset) {
            if (in_array($asset->type, ['desktop', 'laptop', 'workstation', 'server'], true)) {
                return true;
            }
            if (in_array($asset->type, ['router', 'switch', 'printer', 'ip_phone', 'firewall', 'access_point', 'ups'], true)) {
                return false;
            }
        }

        // Inspección de textos (nombre, modelo, notas, vendor)
        $haystack = strtolower(
            ($item['hostname'] ?? '') . ' ' .
            ($item['vendor'] ?? '') . ' ' .
            ($item['notes'] ?? '') . ' ' .
            ($item['raw_os'] ?? '')
        );

        // Prevalencia absoluta para modelos reconocidos de computadoras comerciales
        $isExplicitPcModel = (
            str_contains($haystack, 'prodesk') ||
            str_contains($haystack, 'elitedesk') ||
            str_contains($haystack, 'optiplex') ||
            str_contains($haystack, 'thinkcentre') ||
            str_contains($haystack, 'workstation') ||
            str_contains($haystack, 'micro-star') ||
            str_contains($haystack, 'msi') ||
            str_contains($haystack, 'foxconn') ||
            str_contains($haystack, 'hon hai') ||
            str_contains($haystack, 'estación de trabajo') ||
            str_contains($haystack, 'estacion de trabajo') ||
            str_contains($haystack, 'thinkpad') ||
            str_contains($haystack, 'latitude')
        );

        if ($isExplicitPcModel) {
            return true;
        }

        // Si se confirmó Windows o Linux por escaneo o formal, es soportado
        if ($osType === 'windows' || $osType === 'linux') {
            $discoveredType = strtolower($item['discovered_device_type'] ?? '');
            if (!in_array($discoveredType, ['router', 'switch', 'printer', 'ap', 'camera', 'ups', 'phone'], true)) {
                return true;
            }
        }

        // 2. Comprobar por tipo de dispositivo descubierto (DiscoveredDevice)
        $discoveredType = strtolower($item['discovered_device_type'] ?? '');
        if (in_array($discoveredType, ['router', 'switch', 'printer', 'phone', 'ap', 'camera', 'ups'], true)) {
            return false;
        }

        // Equipos de telecomunicaciones, conmutación, impresión o telefonía VoIP
        if (
            str_contains($haystack, 'router') ||
            str_contains($haystack, 'switch') ||
            str_contains($haystack, 'impresora') ||
            str_contains($haystack, 'printer') ||
            str_contains($haystack, 'telefono') ||
            str_contains($haystack, 'teléfono') ||
            str_contains($haystack, 'voip') ||
            str_contains($haystack, 'ip phone') ||
            str_contains($haystack, 'gateway') ||
            str_contains($haystack, 'access point') ||
            str_contains($haystack, 'cisco')
        ) {
            // A menos que sea explícitamente una PC / Estación de Trabajo
            if (
                !str_contains($haystack, 'estación de trabajo') &&
                !str_contains($haystack, 'estacion de trabajo') &&
                !str_contains($haystack, 'thinkcentre') &&
                !str_contains($haystack, 'elitedesk') &&
                !str_contains($haystack, 'prodesk') &&
                !str_contains($haystack, 'optiplex')
            ) {
                return false;
            }
        }

        // Si explícitamente se identifica como Estación de Trabajo PC o equipo de usuario
        if (
            str_contains($haystack, 'estación de trabajo') ||
            str_contains($haystack, 'estacion de trabajo') ||
            str_contains($haystack, 'taquilla') ||
            str_contains($haystack, 'jefe') ||
            str_contains($haystack, 'atu') ||
            str_contains($haystack, 'equipo 0') ||
            str_contains($haystack, 'thinkcentre') ||
            str_contains($haystack, 'elitedesk') ||
            str_contains($haystack, 'prodesk') ||
            str_contains($haystack, 'optiplex') ||
            str_contains($haystack, 'workstation') ||
            str_contains($haystack, 'desktop') ||
            str_contains($haystack, 'laptop')
        ) {
            return true;
        }

        // Si tiene sistema operativo Windows o Linux detectado, es una estación de trabajo
        if ($osType === 'windows' || $osType === 'linux') {
            return true;
        }

        // Dispositivos sin SO detectado y sin identidad de PC se marcan sin soporte
        return false;
    }

    /**
     * Sugiere heurísticamente el tipo de dispositivo para activos descubiertos no catalogados.
     */
    public function suggestDeviceType(array $item): array
    {
        $ports = $item['open_ports'] ?? '';
        $vendor = strtolower($item['vendor'] ?? '');
        $hostname = strtolower($item['hostname'] ?? '');
        $rawOs = strtolower($item['raw_os'] ?? '');
        $haystack = "{$vendor} {$hostname} {$rawOs}";

        // 0. Modelos Explícitos de Computadoras de Escritorio / Estaciones de Trabajo
        if (
            str_contains($haystack, 'prodesk') ||
            str_contains($haystack, 'elitedesk') ||
            str_contains($haystack, 'optiplex') ||
            str_contains($haystack, 'thinkcentre') ||
            str_contains($haystack, 'workstation') ||
            str_contains($haystack, 'micro-star') ||
            str_contains($haystack, 'msi') ||
            str_contains($haystack, 'estación de trabajo') ||
            str_contains($haystack, 'estacion de trabajo') ||
            str_contains($haystack, 'thinkpad') ||
            str_contains($haystack, 'latitude')
        ) {
            return [
                'type' => 'desktop',
                'label' => 'Estación de Trabajo (PC)',
                'icon' => 'desktop_windows',
                'badge' => 'blue',
                'is_workstation' => true,
            ];
        }

        // 1. Impresoras de Red
        if (
            str_contains($ports, '9100') ||
            str_contains($ports, '515') ||
            str_contains($ports, '631') ||
            str_contains($haystack, 'printer') ||
            str_contains($haystack, 'impresora') ||
            str_contains($haystack, 'laserjet') ||
            str_contains($haystack, 'deskjet') ||
            str_contains($haystack, 'epson') ||
            str_contains($haystack, 'canon') ||
            str_contains($haystack, 'kyocera') ||
            str_contains($haystack, 'brother') ||
            str_contains($haystack, 'zebra') ||
            str_contains($haystack, 'ricoh')
        ) {
            return [
                'type' => 'printer',
                'label' => 'Impresora de Red',
                'icon' => 'print',
                'badge' => 'amber',
                'is_workstation' => false,
            ];
        }

        // 2. Teléfonos VoIP
        if (
            str_contains($ports, '5060') ||
            str_contains($ports, '5061') ||
            str_contains($haystack, 'voip') ||
            str_contains($haystack, 'ip phone') ||
            str_contains($haystack, 'grandstream') ||
            str_contains($haystack, 'polycom') ||
            str_contains($haystack, 'yealink') ||
            str_contains($haystack, 'fanvil')
        ) {
            return [
                'type' => 'ip_phone',
                'label' => 'Teléfono VoIP',
                'icon' => 'phone_in_talk',
                'badge' => 'indigo',
                'is_workstation' => false,
            ];
        }

        // 3. Switches de Red
        if (
            str_contains($haystack, 'switch') ||
            str_contains($haystack, 'catalyst') ||
            str_contains($haystack, 'procurve') ||
            (str_contains($ports, '161') && (str_contains($haystack, 'cisco') || str_contains($haystack, 'd-link') || str_contains($haystack, 'tp-link')))
        ) {
            return [
                'type' => 'switch',
                'label' => 'Switch de Red',
                'icon' => 'alt_route',
                'badge' => 'cyan',
                'is_workstation' => false,
            ];
        }

        // 4. Routers / Gateways
        if (
            str_contains($haystack, 'router') ||
            str_contains($haystack, 'gateway') ||
            str_contains($haystack, 'mikrotik') ||
            str_contains($haystack, 'fortigate') ||
            str_contains($haystack, 'pfsense')
        ) {
            return [
                'type' => 'router',
                'label' => 'Router / Gateway',
                'icon' => 'router',
                'badge' => 'purple',
                'is_workstation' => false,
            ];
        }

        // 5. Access Points Inalámbricos
        if (
            str_contains($haystack, 'access point') ||
            str_contains($haystack, 'ap') ||
            str_contains($haystack, 'unifi') ||
            str_contains($haystack, 'ubiquiti') ||
            str_contains($haystack, 'aruba')
        ) {
            return [
                'type' => 'access_point',
                'label' => 'Punto de Acceso (AP)',
                'icon' => 'wifi',
                'badge' => 'sky',
                'is_workstation' => false,
            ];
        }

        // 6. Servidores
        if (
            str_contains($haystack, 'server') ||
            str_contains($haystack, 'servidor') ||
            str_contains($haystack, 'proxmox') ||
            str_contains($haystack, 'esxi')
        ) {
            return [
                'type' => 'server',
                'label' => 'Servidor',
                'icon' => 'dns',
                'badge' => 'emerald',
                'is_workstation' => true,
            ];
        }

        // 7. Estaciones de Trabajo / PCs
        if (
            str_contains($ports, '445') ||
            str_contains($ports, '135') ||
            str_contains($ports, '3389') ||
            str_contains($ports, '5900') ||
            str_contains($haystack, 'windows') ||
            str_contains($haystack, 'desktop') ||
            str_contains($haystack, 'workstation') ||
            str_contains($haystack, 'micro-star') ||
            str_contains($haystack, 'msi') ||
            str_contains($haystack, 'foxconn') ||
            str_contains($haystack, 'hon hai') ||
            str_contains($haystack, 'taquilla') ||
            str_contains($haystack, 'thinkcentre') ||
            str_contains($haystack, 'optiplex') ||
            str_contains($haystack, 'prodesk') ||
            str_contains($haystack, 'elitedesk')
        ) {
            return [
                'type' => 'desktop',
                'label' => 'Estación de Trabajo (PC)',
                'icon' => 'desktop_windows',
                'badge' => 'blue',
                'is_workstation' => true,
            ];
        }

        // Default no determinado
        return [
            'type' => 'desktop',
            'label' => 'Sin Clasificar (Sugerido: PC)',
            'icon' => 'help',
            'badge' => 'slate',
            'is_workstation' => false,
        ];
    }

    /**
     * Determina si una dirección IPv4 pertenece a una subred CIDR.
     */
    public function ipInSubnet(string $ip, string $range): bool
    {
        $ip = trim($ip);
        $range = trim($range);
        if (empty($ip) || empty($range)) {
            return false;
        }

        if (!str_contains($range, '/')) {
            $range .= '/32';
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * Obtiene el binario de Python a utilizar.
     */
    public function getPythonBinary(): string
    {
        if (is_executable('/scripts/telegram-admin-bot/venv/bin/python')) {
            return '/scripts/telegram-admin-bot/venv/bin/python';
        }

        if (function_exists('base_path') && is_executable(base_path('venv/bin/python'))) {
            return base_path('venv/bin/python');
        }

        return '/usr/bin/python3';
    }

    /**
     * Obtiene la ruta al script de despliegue de agente.
     */
    public function getDeployerScriptPath(): ?string
    {
        if (file_exists('/scripts/telegram-admin-bot/monitor/agent_deployer.py')) {
            return '/scripts/telegram-admin-bot/monitor/agent_deployer.py';
        }

        if (function_exists('base_path') && file_exists(base_path('monitor/agent_deployer.py'))) {
            return base_path('monitor/agent_deployer.py');
        }

        return null;
    }

    /**
     * Obtiene la ruta al script de descubrimiento de red.
     */
    public function getDiscoveryScriptPath(): ?string
    {
        if (file_exists('/scripts/telegram-admin-bot/monitor/network_discovery.py')) {
            return '/scripts/telegram-admin-bot/monitor/network_discovery.py';
        }

        if (function_exists('base_path') && file_exists(base_path('monitor/network_discovery.py'))) {
            return base_path('monitor/network_discovery.py');
        }

        return null;
    }
}
