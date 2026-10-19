<?php

declare(strict_types=1);

namespace App\Services\Deployment;

use App\Contracts\GlpiEndpointConfigInterface;
use App\Models\BotSetting;
use Illuminate\Support\Facades\Log;

class GlpiEndpointConfigService implements GlpiEndpointConfigInterface
{
    protected const DEFAULT_TOKEN = 'd8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96';
    protected const DEFAULT_PROD_URL = 'http://10.20.23.252/api/v1/inventory/receiver/d8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96';
    protected const DEFAULT_DEV_URL = 'http://10.20.23.221/api/v1/inventory/receiver/d8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96';

    /**
     * Valida si un host o IP es enrutable por clientes remotos en la red (rechaza loopback y local).
     */
    public function isRoutableHost(?string $host): bool
    {
        if (empty($host)) {
            return false;
        }

        $host = strtolower(trim($host));

        // Descartar localhost y nombres locales no resolubles remotamente
        if (in_array($host, ['localhost', 'monitoreo-vs.local', '::1', '0.0.0.0'], true)) {
            return false;
        }

        // Si es una dirección IP
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Descartar rango loopback (127.0.0.0/8) y link-local / APIPA (169.254.0.0/16)
            if (str_starts_with($host, '127.') || str_starts_with($host, '169.254.')) {
                return false;
            }
            return true;
        }

        // Si es FQDN o dominio que no contenga localhost ni termine en .local
        return !str_contains($host, 'localhost') && !str_ends_with($host, '.local');
    }

    /**
     * Retorna la IP o hostname enrutable vivo del servidor actual donde se ejecuta Laravel.
     * Garantiza que clientes remotos jamás reciban 127.0.0.1 (loopback) para evitar auto-referencias.
     */
    public function getCurrentHost(): string
    {
        // 1. Si existe petición HTTP y el host es enrutable (IP o dominio público/LAN)
        if (app()->bound('request')) {
            $host = request()->getHost();
            if ($this->isRoutableHost($host)) {
                return $host;
            }
        }

        // 2. Si SERVER_ADDR del servidor web es enrutable
        if (!empty($_SERVER['SERVER_ADDR']) && $this->isRoutableHost((string) $_SERVER['SERVER_ADDR'])) {
            return (string) $_SERVER['SERVER_ADDR'];
        }

        // 3. Obtener la IP primaria del sistema operativo mediante hostname -I
        try {
            $output = @shell_exec('hostname -I 2>/dev/null');
            if ($output) {
                $ips = preg_split('/\s+/', trim($output));
                // Prioridad 1: Buscar IP en el segmento de gestión corporativo (10.20.23.X)
                foreach ($ips as $ip) {
                    if (str_starts_with($ip, '10.20.23.') && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        return $ip;
                    }
                }
                // Prioridad 2: Cualquier IPv4 válida y enrutable
                foreach ($ips as $ip) {
                    if ($this->isRoutableHost($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        return $ip;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error detectando IP local en GlpiEndpointConfigService: ' . $e->getMessage());
        }

        // 4. Fallback según el rol del nodo en cluster o IP estándar de desarrollo
        $nodeRole = (string) BotSetting::get('cluster_node_role', 'dev');
        return ($nodeRole === 'master') ? '10.20.23.252' : '10.20.23.221';
    }

    /**
     * Retorna la URL receptora completa correspondiente al servidor actual.
     */
    public function getCurrentHostUrl(): string
    {
        $host = $this->getCurrentHost();
        $token = $this->getToken();

        return "http://{$host}/api/v1/inventory/receiver/{$token}";
    }

    /**
     * Retorna la configuración completa de endpoints GLPI persistida en MariaDB.
     */
    public function getEndpointsConfig(): array
    {
        $currentHost = $this->getCurrentHost();
        $token = $this->getToken();
        $prodUrl = (string) BotSetting::get('glpi_receiver_prod_url', self::DEFAULT_PROD_URL);
        $devUrl = (string) BotSetting::get('glpi_receiver_dev_url', self::DEFAULT_DEV_URL);

        $prodHost = $this->extractHostFromUrl($prodUrl, '10.20.23.252');
        $devHost = $this->extractHostFromUrl($devUrl, '10.20.23.221');

        return [
            'current_host' => $currentHost,
            'current_url' => "http://{$currentHost}/api/v1/inventory/receiver/{$token}",
            'current_deploy_base' => "http://{$currentHost}",
            'prod_url' => $prodUrl,
            'prod_host' => $prodHost,
            'prod_deploy_base' => $this->extractDeployBase($prodUrl, 'http://10.20.23.252'),
            'dev_url' => $devUrl,
            'dev_host' => $devHost,
            'dev_deploy_base' => $this->extractDeployBase($devUrl, 'http://10.20.23.221'),
            'token' => $token,
        ];
    }

    /**
     * Resuelve la URL de destino según la opción seleccionada.
     */
    public function resolveTargetUrl(string $target, ?string $customIp = null): array
    {
        $config = $this->getEndpointsConfig();
        $normTarget = strtolower(trim($target));

        // 1. Destino Personalizado / Manual
        if ($normTarget === 'custom' || $normTarget === 'manual') {
            $rawInput = trim((string) $customIp);
            if (!empty($rawInput)) {
                $cleanHost = preg_replace('#^https?://#i', '', $rawInput);
                $cleanHost = explode('/', (string) $cleanHost)[0];
                $token = $config['token'];

                return [
                    'server_url' => "http://{$cleanHost}/api/v1/inventory/receiver/{$token}",
                    'deploy_base_url' => "http://{$cleanHost}",
                    'target_label' => "Personalizado ({$cleanHost})",
                    'target_host' => $cleanHost,
                ];
            }
            // Si vino vacío, fallback al host actual
            $normTarget = 'auto';
        }

        // 2. Destino Producción
        if (in_array($normTarget, ['prod', 'production', 'produccion'], true)) {
            return [
                'server_url' => $config['prod_url'],
                'deploy_base_url' => $config['prod_deploy_base'],
                'target_label' => "Producción ({$config['prod_host']})",
                'target_host' => $config['prod_host'],
            ];
        }

        // 3. Destino Desarrollo
        if (in_array($normTarget, ['dev', 'development', 'desarrollo'], true)) {
            return [
                'server_url' => $config['dev_url'],
                'deploy_base_url' => $config['dev_deploy_base'],
                'target_label' => "Desarrollo ({$config['dev_host']})",
                'target_host' => $config['dev_host'],
            ];
        }

        // 4. Destino Auto / Servidor Actual (Por Defecto)
        return [
            'server_url' => $config['current_url'],
            'deploy_base_url' => $config['current_deploy_base'],
            'target_label' => "Este Servidor (Auto-detectado: {$config['current_host']})",
            'target_host' => $config['current_host'],
        ];
    }

    /**
     * Actualiza las configuraciones de endpoints GLPI en MariaDB (SSOT).
     */
    public function updateEndpoints(array $data): bool
    {
        if (!empty($data['glpi_receiver_prod_url'])) {
            BotSetting::set(
                'glpi_receiver_prod_url',
                trim((string) $data['glpi_receiver_prod_url']),
                'glpi_deploy',
                'URL Receptor GLPI - Producción',
                'text'
            );
        }

        if (!empty($data['glpi_receiver_dev_url'])) {
            BotSetting::set(
                'glpi_receiver_dev_url',
                trim((string) $data['glpi_receiver_dev_url']),
                'glpi_deploy',
                'URL Receptor GLPI - Desarrollo',
                'text'
            );
        }

        if (!empty($data['glpi_receiver_token'])) {
            BotSetting::set(
                'glpi_receiver_token',
                trim((string) $data['glpi_receiver_token']),
                'glpi_deploy',
                'Token Receptor GLPI',
                'text'
            );
        }

        return true;
    }

    /**
     * Retorna el token actual persistido en MariaDB.
     */
    protected function getToken(): string
    {
        return (string) BotSetting::get('glpi_receiver_token', self::DEFAULT_TOKEN);
    }

    /**
     * Extrae el hostname o IP de una URL completa.
     */
    protected function extractHostFromUrl(string $url, string $fallback): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return !empty($host) ? (string) $host : $fallback;
    }

    /**
     * Extrae el esquema y host base para descargas de instaladores (http://IP[:PORT]).
     */
    protected function extractDeployBase(string $url, string $fallback): string
    {
        $parsed = parse_url($url);
        if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
            $base = $parsed['scheme'] . '://' . $parsed['host'];
            if (!empty($parsed['port'])) {
                $base .= ':' . $parsed['port'];
            }
            return $base;
        }

        return $fallback;
    }
}
