<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class LdapAuthService
{
    protected string $host;
    protected int $port;
    protected string $baseDn;

    /**
     * Unidades organizacionales y áreas estrictamente autorizadas para el auto-registro
     */
    protected array $allowedAreas = [
        'ATIT',
        'GPO TRAB INFRA TECNOL CARABOBO',
        'INFRAESTRUCTURA',
        'TELECOMUNICACIONES',
    ];

    public function __construct()
    {
        $this->host = config('services.ldap.host', env('LDAP_HOST', '10.20.0.22'));
        $this->port = (int) config('services.ldap.port', env('LDAP_PORT', 389));
        $this->baseDn = config('services.ldap.base_dn', env('LDAP_BASE_DN', 'dc=corpoelec,dc=gob,dc=ve'));
    }

    /**
     * Obtener conexión LDAP activa
     */
    protected function getConnection()
    {
        if (!function_exists('ldap_connect')) {
            Log::error('LDAP: Extensión php-ldap no está disponible en el servidor.');
            return null;
        }

        $conn = @ldap_connect($this->host, $this->port);
        if (!$conn) {
            Log::error("LDAP: No se pudo conectar a {$this->host}:{$this->port}");
            return null;
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 4);

        if (!@ldap_bind($conn)) {
            Log::warning('LDAP: Falló bind anónimo: ' . ldap_error($conn));
            @ldap_unbind($conn);
            return null;
        }

        return $conn;
    }

    /**
     * Autenticar usuario contra LDAP y validar pertenencia a área permitida o pre-autorizada.
     *
     * @param string $username Código de empleado / UID (ej. U1234567)
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

        $conn = $this->getConnection();
        if (!$conn) {
            return [
                'success' => false,
                'message' => 'No se pudo establecer comunicación con el servidor LDAP corporativo.',
                'data' => null,
                'area_authorized' => false,
            ];
        }

        // Búsqueda flexible del usuario por UID, correo corporativo, prefijo de correo o cédula
        $escapedUser = ldap_escape($cleanUsername, '', LDAP_ESCAPE_FILTER);
        $filterList = [
            "(uid={$escapedUser})",
            "(mail={$escapedUser})",
            "(mail={$escapedUser}@corpoelec.gob.ve)",
            "(mail={$escapedUser}@corpoelec.com.ve)",
        ];

        if (is_numeric($cleanUsername)) {
            $filterList[] = "(uid=A{$escapedUser})";
            $filterList[] = "(employeenumber={$escapedUser})";
            $filterList[] = "(employeenumber=1{$escapedUser})";
            $filterList[] = "(carlicense={$escapedUser})";
        }

        $filter = "(|" . implode('', array_unique($filterList)) . ")";
        $attributes = ['dn', 'cn', 'mail', 'givenname', 'sn', 'description', 'o', 'st', 'telephonenumber', 'uid', 'employeenumber'];
        
        $search = @ldap_search($conn, $this->baseDn, $filter, $attributes, 0, 5, 4);
        if (!$search) {
            $ldapErr = ldap_error($conn);
            Log::error("LDAP: Error al consultar cuenta [{$cleanUsername}]: {$ldapErr}");
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
        $entryUid = strtoupper($entry['uid'][0] ?? $cleanUsername);
        $entryMail = strtolower($entry['mail'][0] ?? ($entryUid . '@corpoelec.gob.ve'));

        // Paso 2: Validar pertenencia a áreas autorizadas o si fue PRE-AUTORIZADO por el Administrador
        $upperUsername = strtoupper($cleanUsername);

        $isPreAuthorized = User::where(function ($query) use ($entryUid, $entryMail, $cleanUsername, $upperUsername) {
            $query->where('username', $entryUid)
                  ->orWhere('username', $upperUsername)
                  ->orWhere('username', $cleanUsername);
            if (!empty($entryMail)) {
                $query->orWhere('email', $entryMail);
            }
        })->where('is_active', true)->exists();

        $description = $entry['description'][0] ?? '';

        $areaAuthorized = $isPreAuthorized;
        if (!$areaAuthorized) {
            foreach ($this->allowedAreas as $area) {
                if (stripos($description, $area) !== false) {
                    $areaAuthorized = true;
                    break;
                }
            }
        }

        if (!$areaAuthorized) {
            @ldap_unbind($conn);
            Log::warning("LDAP: Acceso denegado a usuario {$cleanUsername} por área no autorizada. Descripción: [{$description}]");
            return [
                'success' => false,
                'message' => 'Acceso Denegado: Su cuenta corporativa no pertenece a las áreas autorizadas de infraestructura (ATIT / Infraestructura Carabobo). Debe solicitar el acceso al Administrador del Sistema.',
                'data' => null,
                'area_authorized' => false,
            ];
        }

        // Paso 3: Validar contraseña mediante bind con el DN del usuario
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
        $cn = $entry['cn'][0] ?? $entryUid;
        $givenName = $entry['givenname'][0] ?? '';
        $sn = $entry['sn'][0] ?? '';
        $telephone = $entry['telephonenumber'][0] ?? '';
        $st = $entry['st'][0] ?? '';

        @ldap_unbind($conn);

        return [
            'success' => true,
            'message' => 'Autenticación LDAP exitosa.',
            'data' => [
                'username' => $entryUid,
                'name' => $cn,
                'email' => $entryMail,
                'dn' => $userDn,
                'description' => $description,
                'givenName' => $givenName,
                'sn' => $sn,
                'telephone' => $telephone,
                'st' => $st,
                'pre_authorized' => $isPreAuthorized,
            ],
            'area_authorized' => true,
        ];
    }

    /**
     * Buscar usuarios en LDAP por UID o correo para el módulo administrativo de autorización
     *
     * @param string $term Término de búsqueda (ej. U1234567, 1234567, correo)
     * @return array Lista de usuarios encontrados con sus metadatos
     */
    public function searchUsers(string $term): array
    {
        $cleanTerm = trim($term);
        if (strlen($cleanTerm) < 2) {
            return [];
        }

        $conn = $this->getConnection();
        if (!$conn) {
            return [];
        }

        $variants = [
            $cleanTerm,
            strtoupper($cleanTerm),
            strtolower($cleanTerm),
        ];

        if (is_numeric($cleanTerm)) {
            $variants[] = 'A' . $cleanTerm;
            $variants[] = '1' . $cleanTerm;
            $variants[] = '11' . $cleanTerm;
        } elseif (!str_contains($cleanTerm, '@')) {
            $variants[] = strtolower($cleanTerm) . '@corpoelec.gob.ve';
            $variants[] = strtolower($cleanTerm) . '@corpoelec.com.ve';
        }

        $filterParts = [];
        foreach (array_unique($variants) as $v) {
            $esc = ldap_escape($v, '', LDAP_ESCAPE_FILTER);
            $filterParts[] = "(uid={$esc})";
            $filterParts[] = "(mail={$esc})";
            $filterParts[] = "(employeenumber={$esc})";
            $filterParts[] = "(cn=*{$esc}*)";
        }

        $filter = "(|" . implode('', $filterParts) . ")";
        $attributes = ['uid', 'cn', 'mail', 'description', 'o', 'st', 'telephonenumber', 'givenname', 'sn'];

        $search = @ldap_search($conn, $this->baseDn, $filter, $attributes, 0, 20, 4);
        if (!$search) {
            @ldap_unbind($conn);
            return [];
        }

        $entries = ldap_get_entries($conn, $search);
        $results = [];

        if ($entries && $entries['count'] > 0) {
            for ($i = 0; $i < $entries['count']; $i++) {
                $e = $entries[$i];
                $uid = $e['uid'][0] ?? null;
                if (!$uid) continue;

                $cn = $e['cn'][0] ?? $uid;
                $mail = $e['mail'][0] ?? ($uid . '@corpoelec.gob.ve');
                $desc = $e['description'][0] ?? 'Sin descripción';
                $sede = $e['o'][0] ?? 'No especificada';
                $st = $e['st'][0] ?? '';

                // Verificar si pertenece a las áreas estándar
                $isDefaultArea = false;
                foreach ($this->allowedAreas as $area) {
                    if (stripos($desc, $area) !== false) {
                        $isDefaultArea = true;
                        break;
                    }
                }

                $results[] = [
                    'uid' => strtoupper($uid),
                    'name' => $cn,
                    'email' => strtolower($mail),
                    'description' => trim($desc),
                    'sede' => trim($sede),
                    'state' => trim($st),
                    'is_default_area' => $isDefaultArea,
                ];
            }
        }

        @ldap_unbind($conn);

        return $results;
    }

    /**
     * Obtener los datos completos de un usuario por su UID exacto
     */
    public function findUserByUid(string $uid): ?array
    {
        $cleanUid = trim($uid);
        if (empty($cleanUid)) {
            return null;
        }

        $conn = $this->getConnection();
        if (!$conn) {
            return null;
        }

        $escaped = ldap_escape($cleanUid, '', LDAP_ESCAPE_FILTER);
        $filterList = [
            "(uid={$escaped})",
            "(mail={$escaped})",
        ];
        if (is_numeric($cleanUid)) {
            $filterList[] = "(uid=A{$escaped})";
            $filterList[] = "(employeenumber={$escaped})";
        }
        $filter = "(|" . implode('', $filterList) . ")";
        $attributes = ['uid', 'cn', 'mail', 'description', 'o', 'st', 'telephonenumber', 'givenname', 'sn'];

        $search = @ldap_search($conn, $this->baseDn, $filter, $attributes, 0, 5, 4);
        if (!$search) {
            @ldap_unbind($conn);
            return null;
        }

        $entries = ldap_get_entries($conn, $search);
        if (!$entries || $entries['count'] === 0) {
            @ldap_unbind($conn);
            return null;
        }

        $e = $entries[0];
        $uidVal = $e['uid'][0] ?? $cleanUid;
        $cn = $e['cn'][0] ?? $uidVal;
        $mail = $e['mail'][0] ?? ($uidVal . '@corpoelec.gob.ve');
        $desc = $e['description'][0] ?? 'Sin descripción';
        $sede = $e['o'][0] ?? 'No especificada';
        $st = $e['st'][0] ?? '';

        @ldap_unbind($conn);

        return [
            'uid' => strtoupper($uidVal),
            'name' => $cn,
            'email' => strtolower($mail),
            'description' => trim($desc),
            'sede' => trim($sede),
            'state' => trim($st),
        ];
    }
}
