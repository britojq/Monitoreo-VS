<?php

declare(strict_types=1);

namespace App\Services\Deployment;

use App\Contracts\CommandRunnerInterface;
use App\Contracts\GlpiEndpointConfigInterface;
use App\Models\DiscoveredDevice;
use App\Models\InventoryAsset;
use App\Services\System\SonarPathService;

/**
 * Servicio de orquestación para el despliegue remoto del agente GLPI y escaneos de subred.
 * Cumple con el Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
 */
class AgentDeployService
{
    public function __construct(
        protected CommandRunnerInterface $commandRunner,
        protected GlpiEndpointConfigInterface $endpointConfig
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
     * Delega a DeviceClassificationHeuristic.
     */
    public function isGlpiSupportedDevice(array $item, ?InventoryAsset $asset, string $osType): bool
    {
        return DeviceClassificationHeuristic::isGlpiSupportedDevice($item, $asset, $osType);
    }

    /**
     * Sugiere heurísticamente el tipo de dispositivo para activos descubiertos no catalogados.
     * Delega a DeviceClassificationHeuristic.
     */
    public function suggestDeviceType(array $item): array
    {
        return DeviceClassificationHeuristic::suggestDeviceType($item);
    }

    /**
     * Determina si una dirección IPv4 pertenece a una subred CIDR.
     * Delega a SubnetCidrMatcher.
     */
    public function ipInSubnet(string $ip, string $range): bool
    {
        return SubnetCidrMatcher::matches($ip, $range);
    }

    /**
     * Obtiene el binario de Python a utilizar.
     */
    public function getPythonBinary(): string
    {
        return SonarPathService::pythonBinary();
    }

    /**
     * Obtiene la ruta al script de despliegue de agente.
     */
    public function getDeployerScriptPath(): ?string
    {
        $path = SonarPathService::monitorScript('agent_deployer.py');
        return file_exists($path) ? $path : null;
    }

    /**
     * Obtiene la ruta al script de descubrimiento de red.
     */
    public function getDiscoveryScriptPath(): ?string
    {
        $path = SonarPathService::monitorScript('network_discovery.py');
        return file_exists($path) ? $path : null;
    }
}
