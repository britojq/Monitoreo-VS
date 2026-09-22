<?php

namespace App\Services;

class PermissionService
{
    /**
     * Catálogo maestro de permisos granulares clasificados por módulo
     */
    public const MODULES = [
        'infra' => [
            'name' => 'Infraestructura y Monitoreo',
            'icon' => 'dns',
            'description' => 'Supervisión y gestión de Servicios, Sedes, Dispositivos y Proxies.',
            'permissions' => [
                'infra.view' => [
                    'name' => 'Consultar Infraestructura',
                    'description' => 'Ver estado, latencias e históricos de servicios, sedes, dispositivos y proxies.',
                    'default_operator' => true,
                ],
                'infra.manage_services' => [
                    'name' => 'Gestionar Servicios',
                    'description' => 'Crear, editar, activar/pausar y eliminar servicios monitoreados.',
                    'default_operator' => false,
                ],
                'infra.manage_sites' => [
                    'name' => 'Gestionar Sedes & Enlaces',
                    'description' => 'Crear, editar, activar/pausar y eliminar sedes y enlaces WAN.',
                    'default_operator' => false,
                ],
                'infra.manage_devices' => [
                    'name' => 'Gestionar Dispositivos',
                    'description' => 'Crear, editar, alternar monitoreo y eliminar dispositivos de red.',
                    'default_operator' => false,
                ],
                'infra.manage_proxies' => [
                    'name' => 'Gestionar Proxies',
                    'description' => 'Crear, editar, verificar y eliminar servidores proxy corporativos.',
                    'default_operator' => false,
                ],
                'infra.scan_now' => [
                    'name' => 'Escanear Ahora (Barrido Manual)',
                    'description' => 'Disparar escaneo completo inmediato de toda la infraestructura.',
                    'default_operator' => false,
                ],
            ],
        ],
        'remote' => [
            'name' => 'Accesos y Terminales Remotas',
            'icon' => 'terminal',
            'description' => 'Acceso directo a consolas remotas de administración.',
            'permissions' => [
                'remote.ssh' => [
                    'name' => 'Terminal SSH',
                    'description' => 'Iniciar terminal SSH interactiva web en routers y conmutadores.',
                    'default_operator' => true,
                ],
                'remote.telnet' => [
                    'name' => 'Terminal Telnet',
                    'description' => 'Iniciar terminal Telnet interactiva web en equipos de red.',
                    'default_operator' => true,
                ],
                'remote.vnc' => [
                    'name' => 'Visor Web noVNC',
                    'description' => 'Conectar escritorio remoto integrado en estaciones autorizadas.',
                    'default_operator' => true,
                ],
            ],
        ],
        'cisco' => [
            'name' => 'Respaldos y GitOps (Cisco IOS)',
            'icon' => 'settings_backup_restore',
            'description' => 'Control de versiones y respaldos de configuración running-config.',
            'permissions' => [
                'cisco.view' => [
                    'name' => 'Consultar Respaldos y Diff',
                    'description' => 'Ver historial de configuraciones respaldadas y visor comparativo.',
                    'default_operator' => true,
                ],
                'cisco.backup_run' => [
                    'name' => 'Ejecutar Respaldo Manual',
                    'description' => 'Disparar respaldo en caliente via SSH de running-config en conmutadores.',
                    'default_operator' => false,
                ],
                'cisco.credentials_edit' => [
                    'name' => 'Editar Credenciales SSH de Equipos',
                    'description' => 'Configurar o modificar usuarios y contraseñas de respaldo cifradas en AES-256.',
                    'default_operator' => false,
                ],
            ],
        ],
        'snmp' => [
            'name' => 'Telemetría e Interfaces SNMP',
            'icon' => 'sensors',
            'description' => 'Supervisión de ancho de banda, consumo in/out y errores en puertos.',
            'permissions' => [
                'snmp.view' => [
                    'name' => 'Consultar Telemetría SNMP',
                    'description' => 'Visualizar métricas, gráficos de tráfico y estado de interfaces.',
                    'default_operator' => true,
                ],
                'snmp.manage' => [
                    'name' => 'Gestionar Dispositivos SNMP',
                    'description' => 'Registrar o editar comunidades, versiones SNMP y OIDs personalizados.',
                    'default_operator' => false,
                ],
                'snmp.poll' => [
                    'name' => 'Forzar Sondeo SNMP en Caliente',
                    'description' => 'Disparar recolección inmediata de métricas e interfaces.',
                    'default_operator' => false,
                ],
            ],
        ],
        'discovery' => [
            'name' => 'Descubrimiento de Red & Anti-Rogue',
            'icon' => 'radar',
            'description' => 'Escaneo CIDR multi-subred y detección de intrusiones en la LAN.',
            'permissions' => [
                'discovery.view' => [
                    'name' => 'Consultar Equipos Descubiertos',
                    'description' => 'Ver catálogo de dispositivos detectados, fabricantes y subredes.',
                    'default_operator' => true,
                ],
                'discovery.scan' => [
                    'name' => 'Lanzar Escaneo de Red',
                    'description' => 'Iniciar barrido CIDR en subredes configuradas.',
                    'default_operator' => false,
                ],
                'discovery.authorize' => [
                    'name' => 'Autorizar o Marcar Rogue',
                    'description' => 'Aprobar dispositivos detectados o marcarlos como amenazas rogue.',
                    'default_operator' => false,
                ],
            ],
        ],
        'netradar' => [
            'name' => 'NET Radar & Monitoreo de Tráfico',
            'icon' => 'troubleshoot',
            'description' => 'Inspección de ancho de banda, hosts activos y detección de actualizaciones (Windows Update / Repositorios Linux).',
            'permissions' => [
                'netradar.view' => [
                    'name' => 'Consultar NET Radar',
                    'description' => 'Visualizar hosts activos, volumen de tráfico y estado de actualizaciones.',
                    'default_operator' => true,
                ],
                'netradar.export' => [
                    'name' => 'Exportar Reporte de Red',
                    'description' => 'Descargar reportes estructurados (.md) y telemetría de red.',
                    'default_operator' => true,
                ],
                'netradar.scan' => [
                    'name' => 'Captura y Análisis en Vivo',
                    'description' => 'Forzar captura activa inmediata de tráfico de red.',
                    'default_operator' => false,
                ],
            ],
        ],
        'ssl' => [
            'name' => 'Auditoría de Certificados SSL/TLS',
            'icon' => 'lock',
            'description' => 'Control de vigencia y alertas de caducidad en sitios seguros.',
            'permissions' => [
                'ssl.view' => [
                    'name' => 'Consultar Certificados',
                    'description' => 'Ver estado de certificados, emisores y días restantes de vigencia.',
                    'default_operator' => true,
                ],
                'ssl.manage' => [
                    'name' => 'Registrar y Eliminar Certificados',
                    'description' => 'Agregar nuevos dominios HTTPS al catálogo de auditoría SSL.',
                    'default_operator' => false,
                ],
                'ssl.recheck' => [
                    'name' => 'Re-inspeccionar Certificados',
                    'description' => 'Forzar verificación criptográfica manual de certificados en caliente.',
                    'default_operator' => false,
                ],
            ],
        ],
        'alerts' => [
            'name' => 'Alertas, Tormentas & Mantenimiento',
            'icon' => 'notifications_active',
            'description' => 'Gestión de incidencias, silenciamiento y ventanas programadas.',
            'permissions' => [
                'alerts.view' => [
                    'name' => 'Consultar Alertas',
                    'description' => 'Visualizar alertas activas, eventos de tormenta e historiales.',
                    'default_operator' => true,
                ],
                'alerts.ack' => [
                    'name' => 'Reconocer (ACK) y Silenciar',
                    'description' => 'Confirmar recepción de incidencias y silenciarlas temporalmente.',
                    'default_operator' => true,
                ],
                'alerts.rules' => [
                    'name' => 'Gestionar Reglas de Alerta',
                    'description' => 'Crear, modificar o eliminar umbrales y reglas de correlación.',
                    'default_operator' => false,
                ],
                'alerts.maintenance' => [
                    'name' => 'Ventanas de Mantenimiento',
                    'description' => 'Programar o cancelar períodos de mantenimiento programado.',
                    'default_operator' => false,
                ],
            ],
        ],
        'telegram' => [
            'name' => 'Telegram y Reportes Oficiales',
            'icon' => 'send',
            'description' => 'Despacho de estatus y configuración del bot oficial.',
            'permissions' => [
                'telegram.dispatch' => [
                    'name' => 'Despachar Reporte Oficial',
                    'description' => 'Enviar reportes consolidados manuales al grupo corporativo.',
                    'default_operator' => true,
                ],
                'telegram.templates' => [
                    'name' => 'Editar Plantillas de Mensajes',
                    'description' => 'Modificar la redacción y formatos de los reportes oficiales.',
                    'default_operator' => false,
                ],
                'telegram.commands' => [
                    'name' => 'Configurar Comandos del Bot',
                    'description' => 'Habilitar o deshabilitar comandos dinámicos del bot en Telegram.',
                    'default_operator' => false,
                ],
            ],
        ],
        'ai' => [
            'name' => 'Asistente Virtual Corporativo',
            'icon' => 'smart_toy',
            'description' => 'Consultas al motor local de inteligencia artificial.',
            'permissions' => [
                'ai.chat' => [
                    'name' => 'Interactuar con el Asistente',
                    'description' => 'Hacer preguntas y diagnósticos al motor local de IA.',
                    'default_operator' => true,
                ],
            ],
        ],
        'security' => [
            'name' => 'Seguridad y Administración General',
            'icon' => 'admin_panel_settings',
            'description' => 'Control de usuarios, auditoría forense y configuración de clúster.',
            'permissions' => [
                'security.users' => [
                    'name' => 'Gestión de Usuarios',
                    'description' => 'Crear, suspender y administrar cuentas locales y LDAP.',
                    'default_operator' => false,
                ],
                'security.permissions' => [
                    'name' => 'Gestionar Permisos',
                    'description' => 'Modificar los permisos y capacidades de otros usuarios.',
                    'default_operator' => false,
                ],
                'security.bans' => [
                    'name' => 'Gestión de Baneos e IPs',
                    'description' => 'Consultar y desbloquear direcciones IP y usuarios sancionados.',
                    'default_operator' => false,
                ],
                'security.audit' => [
                    'name' => 'Consultar Pista de Auditoría',
                    'description' => 'Ver el registro inmutable de acciones, inicios de sesión y cambios.',
                    'default_operator' => false,
                ],
                'security.audit_export' => [
                    'name' => 'Exportar Pista de Auditoría',
                    'description' => 'Descargar eventos de auditoría y forenses en formato CSV o JSON.',
                    'default_operator' => false,
                ],
                'security.advanced' => [
                    'name' => 'Configuración Avanzada',
                    'description' => 'Ajustar parámetros de clúster, credenciales LDAP y cron.',
                    'default_operator' => false,
                ],
            ],
        ],
    ];

    /**
     * Retorna todos los módulos y permisos
     */
    public static function getModules(): array
    {
        return self::MODULES;
    }

    /**
     * Retorna una lista plana de todas las claves de permisos válidas
     */
    public static function getAllPermissionKeys(): array
    {
        $keys = [];
        foreach (self::MODULES as $module) {
            foreach (array_keys($module['permissions']) as $key) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Retorna la lista de permisos predeterminados para el rol operador
     */
    public static function getDefaultOperatorPermissions(): array
    {
        $defaults = [];
        foreach (self::MODULES as $module) {
            foreach ($module['permissions'] as $key => $perm) {
                if (!empty($perm['default_operator'])) {
                    $defaults[] = $key;
                }
            }
        }
        return $defaults;
    }

    /**
     * Comprueba si una clave de permiso es válida
     */
    public static function isValidPermission(string $permissionKey): bool
    {
        return in_array($permissionKey, self::getAllPermissionKeys(), true);
    }

    /**
     * Sanitiza y filtra una lista de permisos para descartar claves inexistentes
     */
    public static function sanitizePermissions(array $permissions): array
    {
        $validKeys = self::getAllPermissionKeys();
        return array_values(array_unique(array_intersect($permissions, $validKeys)));
    }
}
