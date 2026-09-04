<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LdapAuthService
{
    protected string $host = '10.20.0.22';
    protected int $port = 389;
    protected string $baseDn = 'dc=corpoelec,dc=gob,dc=ve';

    /**
     * Unidades organizacionales y áreas estrictamente autorizadas para el acceso
     */
    protected array $allowedAreas = [
        'ATIT',
        'GPO TRAB INFRA TECNOL CARABOBO',
    ];

    /**
     * Autenticar usuario contra LDAP y validar pertenencia a área permitida.
     *
     * @param string $username Código de empleado / UID (ej. A1746281)
     * @param string $password Contraseña de red corporativa
     * @return array{success: bool, message: string, data: array|null, area_authorized: bool}
     */
    public function authenticate(string $username, string $password): array
    {
        $cleanUsername = trim($username);
        $cleanPassword = trim($password);

        if (empty($cleanUsername) || empty($cleanPassword)) {
            return [
                'success' => false,
                'message' => 'El usuario y la contraseña son requeridos.',
                'data' => null,
                'area_authorized' => false,
            ];
        }

        if (!function_exists('ldap_connect')) {
            Log::error('LDAP: Extensión php-ldap no está disponible en el servidor.');
            return [
                'success' => false,
                'message' => 'El módulo LDAP no está activo en el servidor web.',
                'data' => null,
                'area_authorized' => false,
            ];
        }

        $conn = @ldap_connect($this->host, $this->port);
        if (!$conn) {
            return [
                'success' => false,
                'message' => "No se pudo conectar con el servidor LDAP corporativo ({$this->host}).",
                'data' => null,
                'area_authorized' => false,
            ];
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 4);

        // Paso 1: Bind anónimo para localizar la ficha del usuario
        $anonBind = @ldap_bind($conn);
        if (!$anonBind) {
            Log::warning('LDAP: Falló bind anónimo: ' . ldap_error($conn));
            return [
                'success' => false,
                'message' => 'No se pudo iniciar la consulta en el directorio corporativo.',
                'data' => null,
                'area_authorized' => false,
            ];
        }

        // Búsqueda del usuario por UID (escape seguro contra inyecciones LDAP)
        $escapedUser = ldap_escape($cleanUsername, '', LDAP_ESCAPE_FILTER);
        $filter = "(uid={$escapedUser})";
        $attributes = ['dn', 'cn', 'mail', 'givenname', 'sn', 'description', 'o', 'st', 'telephonenumber', 'uid'];
        
        $search = @ldap_search($conn, $this->baseDn, $filter, $attributes);
        if (!$search) {
            @ldap_unbind($conn);
            return [
                'success' => false,
                'message' => 'Error al consultar la cuenta en el directorio corporativo.',
                'data' => null,
                'area_authorized' => false,
            ];
        }

        $entries = ldap_get_entries($conn, $search);
        if (!$entries || $entries['count'] === 0) {
            @ldap_unbind($conn);
            return [
                'success' => false,
                'message' => 'El usuario ingresado no existe en el directorio corporativo LDAP.',
                'data' => null,
                'area_authorized' => false,
            ];
        }

        $entry = $entries[0];
        $userDn = $entry['dn'];

        // Paso 2: Validar pertenencia a áreas autorizadas (ATIT / Infraestructura)
        $description = '';
        if (isset($entry['description'][0])) {
            $description = $entry['description'][0];
        }

        $areaAuthorized = false;
        foreach ($this->allowedAreas as $area) {
            if (stripos($description, $area) !== false) {
                $areaAuthorized = true;
                break;
            }
        }

        if (!$areaAuthorized) {
            @ldap_unbind($conn);
            Log::warning("LDAP: Acceso denegado a usuario {$cleanUsername} por pertenecer a área no autorizada. Descripción: [{$description}]");
            return [
                'success' => false,
                'message' => 'Acceso Denegado: Su cuenta corporativa no pertenece a las áreas autorizadas de infraestructura (ATIT / Infraestructura Carabobo). Debe solicitar el acceso al Administrador del Sistema.',
                'data' => null,
                'area_authorized' => false,
            ];
        }

        // Paso 3: Validar contraseña mediante bind autenticado con su DN
        $userBind = @ldap_bind($conn, $userDn, $cleanPassword);
        if (!$userBind) {
            @ldap_unbind($conn);
            return [
                'success' => false,
                'message' => 'Contraseña corporativa incorrecta para el usuario LDAP.',
                'data' => null,
                'area_authorized' => true,
            ];
        }

        // Extracción de datos del usuario
        $cn = $entry['cn'][0] ?? $cleanUsername;
        $mail = $entry['mail'][0] ?? ($cleanUsername . '@corpoelec.gob.ve');
        $givenName = $entry['givenname'][0] ?? '';
        $sn = $entry['sn'][0] ?? '';
        $telephone = $entry['telephonenumber'][0] ?? '';
        $st = $entry['st'][0] ?? '';

        @ldap_unbind($conn);

        return [
            'success' => true,
            'message' => 'Autenticación LDAP exitosa.',
            'data' => [
                'username' => strtoupper($cleanUsername),
                'name' => $cn,
                'email' => strtolower($mail),
                'dn' => $userDn,
                'description' => $description,
                'givenName' => $givenName,
                'sn' => $sn,
                'telephone' => $telephone,
                'st' => $st,
            ],
            'area_authorized' => true,
        ];
    }
}
