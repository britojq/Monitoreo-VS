<?php

declare(strict_types=1);

namespace App\Services\Deployment;

use App\Models\InventoryAsset;

/**
 * Heurísticas de clasificación y compatibilidad de dispositivos para despliegue de agentes e inventario.
 */
class DeviceClassificationHeuristic
{
    /**
     * Determina si un dispositivo es compatible con la instalación de Agente GLPI.
     * Solo las estaciones de trabajo (PC, laptops, desktops o servidores) son compatibles.
     */
    public static function isGlpiSupportedDevice(array $item, ?InventoryAsset $asset, string $osType): bool
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
    public static function suggestDeviceType(array $item): array
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
}
