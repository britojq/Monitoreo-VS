<?php

namespace App\Services;

use App\Models\BotSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LdapAuthService
{
    /**
     * Obtener configuración actual de LDAP directamente desde MariaDB (SSOT)
     * con fallback a variables de entorno si la base de datos estuviera inicializándose.
     */
    public function getConfig(): array
    {
        $default = [
            'enabled' => true,
            'host' => config('services.ldap.host', env('LDAP_HOST', '192.168.0.22')),
            'port' => (int) config('services.ldap.port', env('LDAP_PORT', 389)),
            'base_dn' => config('services.ldap.base_dn', env('LDAP_BASE_DN', base64_decode('ZGM9Y29ycG9lbGVjLGRjPWdvYixkYz12ZQ=='))),
            'allowed_areas' => 'ATIT, GPO TRAB INFRA TECNOL CARABOBO, INFRAESTRUCTURA, TELECOMUNICACIONES',
            'default_role' => 'operator',
            'updated_at' => null,
            'updated_by' => null,
        ];

        try {
            $c = BotSetting::get('ldap_config');
            if (is_array($c)) {
                $default['enabled'] = (bool)($c['enabled'] ?? true);
                $default['host'] = trim((string)($c['host'] ?? $default['host']));
                $default['port'] = max(1, min(65535, (int)($c['port'] ?? $default['port'])));
                $default['base_dn'] = trim((string)($c['base_dn'] ?? $default['base_dn']));
                $default['allowed_areas'] = is_array($c['allowed_areas'] ?? null)
                    ? implode(', ', $c['allowed_areas'])
                    : (string)($c['allowed_areas'] ?? $default['allowed_areas']);
                $default['default_role'] = in_array($c['default_role'] ?? 'operator', ['admin', 'operator'], true)
                    ? $c['default_role']
                    : 'operator';
                $default['updated_at'] = $c['updated_at'] ?? null;
                $default['updated_by'] = $c['updated_by'] ?? null;
            }
        } catch (\Throwable $e) {
            Log::error('Error leyendo config LDAP de MariaDB: ' . $e->getMessage());
        }

        // Si la base DN configurada contiene el placeholder 'empresa' o está vacía, auto-sanar con valor seguro
        if (empty($default['base_dn']) || stripos($default['base_dn'], 'dc=empresa') !== false) {
            $default['base_dn'] = base64_decode('ZGM9Y29ycG9lbGVjLGRjPWdvYixkYz12ZQ==');
        }

        return $default;
    }

    /**
     * Comprobar si la autenticación LDAP está habilitada en el sistema
     */
    public function isEnabled(): bool
    {
        return (bool) $this->getConfig()['enabled'];
    }

    /**
     * Actualizar y persistir la configuración de LDAP en MariaDB (SSOT)
     */
    public function updateConfig(array $attributes, ?string $updatedBy = null): bool
    {
        try {
            $currentLdap = $this->getConfig();

            $enabled = isset($attributes['enabled']) ? (bool)$attributes['enabled'] : ($currentLdap['enabled'] ?? true);
            $host = trim((string)($attributes['host'] ?? ($currentLdap['host'] ?? '192.168.0.22')));
            $port = max(1, min(65535, (int)($attributes['port'] ?? ($currentLdap['port'] ?? 389))));
            $baseDn = trim((string)($attributes['base_dn'] ?? ($currentLdap['base_dn'] ?? base64_decode('ZGM9Y29ycG9lbGVjLGRjPWdvYixkYz12ZQ=='))));
            if (empty($baseDn) || stripos($baseDn, 'dc=empresa') !== false) {
                $baseDn = base64_decode('ZGM9Y29ycG9lbGVjLGRjPWdvYixkYz12ZQ==');
            }
            $allowedAreas = trim((string)($attributes['allowed_areas'] ?? ($currentLdap['allowed_areas'] ?? 'ATIT, GPO TRAB INFRA TECNOL CARABOBO, INFRAESTRUCTURA, TELECOMUNICACIONES')));
            $defaultRole = in_array($attributes['default_role'] ?? '', ['admin', 'operator'], true)
                ? $attributes['default_role']
                : ($currentLdap['default_role'] ?? 'operator');

            $ldapData = [
                'enabled' => $enabled,
                'host' => $host,
                'port' => $port,
                'base_dn' => $baseDn,
                'allowed_areas' => $allowedAreas,
                'default_role' => $defaultRole,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $updatedBy ?? 'Administrador',
            ];

            BotSetting::set('ldap_config', $ldapData, 'ldap', 'Configuración de Conexión y Autenticación LDAP', 'json');
            BotSetting::syncToFile();

            return true;
        } catch (\Throwable $e) {
            Log::error('Error guardando config LDAP en MariaDB: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resuelve dinámicamente la Base DN consultando el RootDSE del servidor LDAP (RFC 4512)
     * o validando la Base DN configurada.
     */
    public function resolveBaseDn($conn, ?string $configuredBaseDn = null): string
    {
        $candidate = trim($configuredBaseDn ?? '');

        // Si la Base DN provista es válida y no es un placeholder genérico, verificarla en el servidor
        if (!empty($candidate) && stripos($candidate, 'dc=empresa') === false) {
            if ($conn) {
                $test = @ldap_read($conn, $candidate, '(objectClass=*)', ['dn'], 0, 1, 2);
                if ($test) {
                    return $candidate;
                }
            } else {
                return $candidate;
            }
        }

        // Auto-descubrimiento en tiempo real vía RootDSE (RFC 4512)
        if ($conn) {
            $sr = @ldap_read($conn, '', '(objectClass=*)', ['namingContexts', 'defaultNamingContext'], 0, 1, 3);
            if ($sr) {
                $entries = @ldap_get_entries($conn, $sr);
                if (!empty($entries[0]['defaultnamingcontext'][0])) {
                    $discovered = trim($entries[0]['defaultnamingcontext'][0]);
                    $this->healConfigBaseDn($discovered);
                    return $discovered;
                }
                if (!empty($entries[0]['namingcontexts']['count'])) {
                    for ($i = 0; $i < $entries[0]['namingcontexts']['count']; $i++) {
                        $nc = trim($entries[0]['namingcontexts'][$i]);
                        if (stripos($nc, 'dc=') === 0) {
                            $this->healConfigBaseDn($nc);
                            return $nc;
                        }
                    }
                }
            }
        }

        $fallback = base64_decode('ZGM9Y29ycG9lbGVjLGRjPWdvYixkYz12ZQ==');
        $this->healConfigBaseDn($fallback);
        return $fallback;
    }

    /**
     * Corrige silenciosamente la Base DN en MariaDB si tenía placeholders genéricos o estaba corrupta.
     */
    protected function healConfigBaseDn(string $realBaseDn): void
    {
        if (empty($realBaseDn) || stripos($realBaseDn, 'dc=empresa') !== false) {
            return;
        }

        try {
            $currentLdap = BotSetting::get('ldap_config');
            if (is_array($currentLdap)) {
                $current = $currentLdap['base_dn'] ?? '';
                if (empty($current) || stripos($current, 'dc=empresa') !== false) {
                    $currentLdap['base_dn'] = $realBaseDn;
                    $currentLdap['updated_at'] = date('Y-m-d H:i:s');
                    BotSetting::set('ldap_config', $currentLdap, 'ldap', 'Configuración de Conexión y Autenticación LDAP', 'json');
                    BotSetting::syncToFile();
                    Log::info("LDAP: Base DN auto-sanada dinámicamente en MariaDB -> {$realBaseDn}");
                }
            }
        } catch (\Throwable $e) {
            // Ignorar errores silenciosamente
        }
    }

    /**
     * Obtiene los dominios de correo permitidos a partir de los componentes dc= de la Base DN.
     * Ejemplo: "dc=empresa,dc=gob,dc=ve" -> ['empresa.gob.ve', 'empresa.com.ve']
     */
    public function getDomainsFromBaseDn(string $baseDn): array
    {
        if (preg_match_all('/dc=([^,]+)/i', $baseDn, $matches)) {
            $primaryDomain = strtolower(implode('.', $matches[1]));
            $domains = [$primaryDomain];

            if (str_ends_with($primaryDomain, '.gob.ve')) {
                $prefix = substr($primaryDomain, 0, -strlen('.gob.ve'));
                $domains[] = $prefix . '.com.ve';
            } elseif (str_ends_with($primaryDomain, '.com.ve')) {
                $prefix = substr($primaryDomain, 0, -strlen('.com.ve'));
                $domains[] = $prefix . '.gob.ve';
            }
            return array_values(array_unique(array_filter($domains)));
        }

        $fallbackDomain = implode('.', array_map(fn($part) => substr($part, 3), explode(',', base64_decode('ZGM9Y29ycG9lbGVjLGRjPWdvYixkYz12ZQ=='))));
        return [$fallbackDomain, str_replace('.gob.ve', '.com.ve', $fallbackDomain)];
    }

    /**
     * Probar en tiempo real la conectividad y validación de Base DN hacia el servidor LDAP
     */
    public function testConnection(?string $host = null, ?int $port = null, ?string $baseDn = null): array
    {
        $cfg = $this->getConfig();
        $testHost = trim($host ?: $cfg['host']);
        $testPort = (int)($port ?: $cfg['port']);
        $testBaseDn = trim($baseDn ?: $cfg['base_dn']);

        if (!function_exists('ldap_connect')) {
            return [
                'success' => false,
                'latency_ms' => 0,
                'message' => 'La extensión PHP LDAP no está disponible en este servidor.',
            ];
        }

        $start = microtime(true);
        $conn = @ldap_connect($testHost, $testPort);
        if (!$conn) {
            $latency = round((microtime(true) - $start) * 1000, 1);
            return [
                'success' => false,
                'latency_ms' => $latency,
                'message' => "No se pudo iniciar el socket LDAP hacia {$testHost}:{$testPort}.",
            ];
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 3);

        if (!@ldap_bind($conn)) {
            $latency = round((microtime(true) - $start) * 1000, 1);
            $err = ldap_error($conn);
            @ldap_unbind($conn);
            return [
                'success' => false,
                'latency_ms' => $latency,
                'message' => "Fallo de conexión o bind anónimo a {$testHost}:{$testPort} ({$err}).",
            ];
        }

        // Resolver dinámicamente Base DN si es necesario
        $effectiveBaseDn = $this->resolveBaseDn($conn, $testBaseDn);

        // Probar lectura de la Base DN
        $sr = @ldap_read($conn, $effectiveBaseDn, '(objectClass=*)', ['namingContexts', 'subschemaSubentry'], 0, 1, 3);
        $latency = round((microtime(true) - $start) * 1000, 1);

        if (!$sr) {
            $err = ldap_error($conn);
            @ldap_unbind($conn);
            return [
                'success' => false,
                'latency_ms' => $latency,
                'message' => "Servidor {$testHost}:{$testPort} conectado, pero la Base DN '{$effectiveBaseDn}' no fue localizada ({$err}).",
            ];
        }

        @ldap_unbind($conn);
        return [
            'success' => true,
            'latency_ms' => $latency,
            'resolved_base_dn' => $effectiveBaseDn,
            'message' => "¡Conexión y Base DN verificadas exitosamente! ({$latency} ms). Servidor {$testHost}:{$testPort} operativo (Base DN: {$effectiveBaseDn}).",
        ];
    }

    /**
     * Obtener conexión LDAP activa
     */
    protected function getConnection(?string $customHost = null, ?int $customPort = null)
    {
        if (!function_exists('ldap_connect')) {
            Log::error('LDAP: Extensión php-ldap no está disponible en el servidor.');
            return null;
        }

        $cfg = $this->getConfig();
        $targetHost = $customHost ?: $cfg['host'];
        $targetPort = $customPort ?: $cfg['port'];

        $conn = @ldap_connect($targetHost, $targetPort);
        if (!$conn) {
            Log::error("LDAP: No se pudo conectar a {$targetHost}:{$targetPort}");
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
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'La autenticación mediante Directorio Activo (LDAP) ha sido desactivada por el Administrador.',
                'data' => null,
                'area_authorized' => false,
            ];
        }

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

        $cfg = $this->getConfig();
        $baseDn = $this->resolveBaseDn($conn, $cfg['base_dn']);
        $domains = $this->getDomainsFromBaseDn($baseDn);
        $primaryDomain = $domains[0] ?? 'empresa.gob.ve';
        $rawAreas = explode(',', $cfg['allowed_areas'] ?? '');
        $allowedAreas = array_values(array_filter(array_map('trim', $rawAreas)));

        // Búsqueda flexible del usuario por UID, correo corporativo, prefijo de correo o cédula
        $escapedUser = ldap_escape($cleanUsername, '', LDAP_ESCAPE_FILTER);
        $filterList = [
            "(uid={$escapedUser})",
            "(mail={$escapedUser})",
        ];
        foreach ($domains as $d) {
            $filterList[] = "(mail={$escapedUser}@{$d})";
        }

        if (is_numeric($cleanUsername)) {
            $filterList[] = "(uid=A{$escapedUser})";
            $filterList[] = "(employeenumber={$escapedUser})";
            $filterList[] = "(employeenumber=1{$escapedUser})";
            $filterList[] = "(carlicense={$escapedUser})";
        }

        $filter = "(|" . implode('', array_unique($filterList)) . ")";
        $attributes = ['dn', 'cn', 'mail', 'givenname', 'sn', 'description', 'o', 'st', 'telephonenumber', 'uid', 'employeenumber'];
        
        $search = @ldap_search($conn, $baseDn, $filter, $attributes, 0, 5, 4);
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
        $entryMail = strtolower($entry['mail'][0] ?? ($entryUid . '@' . $primaryDomain));

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
            foreach ($allowedAreas as $area) {
                if (stripos($description, $area) !== false) {
                    $areaAuthorized = true;
                    break;
                }
            }
        }

        // Si pertenece a ATIT o está pre-autorizado, rol operador/admin; en caso contrario, rol usuario (autoconsulta)
        $targetRole = $areaAuthorized ? ($cfg['default_role'] ?? 'operator') : 'user';

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

        // Extracción y normalización de cédula de identidad desde employeenumber, carlicense o UID
        $rawEmp = $entry['employeenumber'][0] ?? ($entry['carlicense'][0] ?? null);
        $cedula = '';
        if ($rawEmp) {
            $cleanDigits = ltrim(preg_replace('/\D/', '', (string)$rawEmp), '0');
            if (!empty($cleanDigits)) {
                $cedula = 'V-' . number_format((int)$cleanDigits, 0, '', '.');
            }
        }
        if (empty($cedula) && preg_match('/^[a-zA-Z]?(\d{6,8})$/', $entryUid, $m)) {
            $cedula = 'V-' . number_format((int)$m[1], 0, '', '.');
        }

        @ldap_unbind($conn);

        return [
            'success' => true,
            'message' => 'Autenticación LDAP exitosa.',
            'data' => [
                'username' => $entryUid,
                'name' => $cn,
                'email' => $entryMail,
                'cedula' => $cedula,
                'phone' => $telephone,
                'personal_number' => $rawEmp,
                'dn' => $userDn,
                'description' => $description,
                'givenName' => $givenName,
                'sn' => $sn,
                'telephone' => $telephone,
                'st' => $st,
                'pre_authorized' => $isPreAuthorized,
                'area_authorized' => $areaAuthorized,
                'target_role' => $targetRole,
            ],
            'area_authorized' => $areaAuthorized,
            'target_role' => $targetRole,
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
        if (!$this->isEnabled()) {
            return [];
        }

        $cleanTerm = trim($term);
        if (strlen($cleanTerm) < 2) {
            return [];
        }

        $conn = $this->getConnection();
        if (!$conn) {
            return [];
        }

        $cfg = $this->getConfig();
        $baseDn = $this->resolveBaseDn($conn, $cfg['base_dn']);
        $domains = $this->getDomainsFromBaseDn($baseDn);
        $primaryDomain = $domains[0] ?? 'empresa.gob.ve';
        $rawAreas = explode(',', $cfg['allowed_areas'] ?? '');
        $allowedAreas = array_values(array_filter(array_map('trim', $rawAreas)));

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
            foreach ($domains as $d) {
                $variants[] = strtolower($cleanTerm) . '@' . $d;
            }
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
        $attributes = ['uid', 'cn', 'mail', 'description', 'o', 'st', 'telephonenumber', 'givenname', 'sn', 'employeenumber', 'carlicense', 'title', 'department'];

        $search = @ldap_search($conn, $baseDn, $filter, $attributes, 0, 20, 4);
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
                $mail = $e['mail'][0] ?? ($uid . '@' . $primaryDomain);
                $desc = $e['description'][0] ?? 'Sin descripción';
                $sede = $e['o'][0] ?? 'No especificada';
                $st = $e['st'][0] ?? '';

                // Extraer cédula de identidad desde employeenumber, carlicense o UID
                $rawEmp = $e['employeenumber'][0] ?? ($e['carlicense'][0] ?? null);
                $cedula = '';
                if ($rawEmp) {
                    $cleanDigits = ltrim(preg_replace('/\D/', '', (string)$rawEmp), '0');
                    if (!empty($cleanDigits)) {
                        $cedula = 'V-' . number_format((int)$cleanDigits, 0, '', '.');
                    }
                }
                if (empty($cedula) && preg_match('/^[a-zA-Z]?(\d{6,8})$/', $uid, $m)) {
                    $cedula = 'V-' . number_format((int)$m[1], 0, '', '.');
                }

                $dept = $e['department'][0] ?? ($e['description'][0] ?? 'ATIT Carabobo');
                $role = $e['title'][0] ?? 'Personal Corporativo';
                $phone = $e['telephonenumber'][0] ?? '';

                // Verificar si pertenece a las áreas estándar
                $isDefaultArea = false;
                foreach ($allowedAreas as $area) {
                    if (stripos($desc, $area) !== false) {
                        $isDefaultArea = true;
                        break;
                    }
                }

                $results[] = [
                    'uid' => strtoupper($uid),
                    'name' => $cn,
                    'email' => strtolower($mail),
                    'cedula' => $cedula,
                    'description' => trim($desc),
                    'department' => trim($dept),
                    'role' => trim($role),
                    'phone' => trim($phone),
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
        if (!$this->isEnabled()) {
            return null;
        }

        $cleanUid = trim($uid);
        if (empty($cleanUid)) {
            return null;
        }

        $conn = $this->getConnection();
        if (!$conn) {
            return null;
        }

        $cfg = $this->getConfig();
        $baseDn = $this->resolveBaseDn($conn, $cfg['base_dn']);
        $domains = $this->getDomainsFromBaseDn($baseDn);
        $primaryDomain = $domains[0] ?? 'empresa.gob.ve';

        $escaped = ldap_escape($cleanUid, '', LDAP_ESCAPE_FILTER);
        $filterList = [
            "(uid={$escaped})",
            "(mail={$escaped})",
        ];
        foreach ($domains as $d) {
            $filterList[] = "(mail={$escaped}@{$d})";
        }
        if (is_numeric($cleanUid)) {
            $filterList[] = "(uid=A{$escaped})";
            $filterList[] = "(employeenumber={$escaped})";
        }
        $filter = "(|" . implode('', $filterList) . ")";
        $attributes = ['uid', 'cn', 'mail', 'description', 'o', 'st', 'telephonenumber', 'givenname', 'sn'];

        $search = @ldap_search($conn, $baseDn, $filter, $attributes, 0, 5, 4);
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
        $mail = $e['mail'][0] ?? ($uidVal . '@' . $primaryDomain);
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

    /**
     * Buscar empleados en tiempo real en LDAP para conciliación de inventario y actas.
     *
     * @param string $query Término de búsqueda (nombre, apellido, cédula, usuario o correo)
     * @param int $limit Cantidad máxima de resultados
     * @return array<int, array{username: string, name: string, id_number: string, department: string, email: string, phone: string, role: string, source: string}>
     */
    public function searchEmployees(string $query, int $limit = 10): array
    {
        $clean = trim($query);
        if (mb_strlen($clean) < 2) {
            return [];
        }

        $results = [];
        $seen = [];

        // 1. Intento de búsqueda en LDAP Institucional si está disponible
        $conn = $this->getConnection();
        if ($conn) {
            try {
                $cfg = $this->getConfig();
                $baseDn = $this->resolveBaseDn($conn, $cfg['base_dn']);
                $escaped = ldap_escape($clean, '', LDAP_ESCAPE_FILTER);

                $filterParts = [
                    "(cn=*{$escaped}*)",
                    "(givenname=*{$escaped}*)",
                    "(sn=*{$escaped}*)",
                    "(uid=*{$escaped}*)",
                    "(mail=*{$escaped}*)",
                ];

                if (is_numeric($clean)) {
                    $filterParts[] = "(employeenumber=*{$escaped}*)";
                    $filterParts[] = "(carlicense=*{$escaped}*)";
                    $filterParts[] = "(uid=A{$escaped}*)";
                }

                $filter = "(|" . implode('', $filterParts) . ")";
                $attributes = ['uid', 'cn', 'givenname', 'sn', 'mail', 'description', 'o', 'st', 'telephonenumber', 'employeenumber', 'carlicense', 'title'];

                $search = @ldap_search($conn, $baseDn, $filter, $attributes, 0, $limit, 3);
                if ($search) {
                    $entries = @ldap_get_entries($conn, $search);
                    if ($entries && isset($entries['count'])) {
                        for ($i = 0; $i < $entries['count']; $i++) {
                            $e = $entries[$i];
                            $uid = strtoupper($e['uid'][0] ?? '');
                            $rawName = $e['cn'][0] ?? trim(($e['givenname'][0] ?? '') . ' ' . ($e['sn'][0] ?? ''));
                            $name = trim(preg_replace('/\s+/', ' ', $rawName));

                            if (empty($name)) {
                                continue;
                            }

                            // Extracción de cédula de identidad
                            $rawCedula = $e['employeenumber'][0] ?? ($e['carlicense'][0] ?? '');
                            $cedula = preg_replace('/[^0-9]/', '', (string)$rawCedula);
                            if (empty($cedula) && preg_match('/^A?(\d{6,9})$/i', $uid, $m)) {
                                $cedula = $m[1];
                            }

                            $dept = trim($e['description'][0] ?? ($e['o'][0] ?? ($e['st'][0] ?? '')));
                            $mail = strtolower($e['mail'][0] ?? '');
                            $phone = trim($e['telephonenumber'][0] ?? '');
                            $role = trim($e['title'][0] ?? 'Personal Corporativo');

                            $key = $uid ?: ($cedula ?: $name);
                            if (!isset($seen[$key])) {
                                $seen[$key] = true;
                                $results[] = [
                                    'username' => $uid,
                                    'name' => mb_strtoupper($name, 'UTF-8'),
                                    'id_number' => $cedula,
                                    'department' => $dept,
                                    'email' => $mail,
                                    'phone' => $phone,
                                    'role' => $role,
                                    'source' => 'ldap',
                                ];
                            }

                            if (count($results) >= $limit) {
                                break;
                            }
                        }
                    }
                }
            } catch (\Throwable $t) {
                Log::warning('LdapAuthService::searchEmployees exception: ' . $t->getMessage());
            } finally {
                @ldap_unbind($conn);
            }
        }

        // 2. Complementar o fallback con usuarios registrados en MariaDB
        if (count($results) < $limit) {
            $remaining = $limit - count($results);
            $localUsers = User::query()
                ->where(function ($q) use ($clean) {
                    $q->where('name', 'LIKE', "%{$clean}%")
                      ->orWhere('username', 'LIKE', "%{$clean}%")
                      ->orWhere('email', 'LIKE', "%{$clean}%")
                      ->orWhere('id_number', 'LIKE', "%{$clean}%");
                })
                ->where('is_active', true)
                ->limit($remaining)
                ->get();

            foreach ($localUsers as $u) {
                $uid = strtoupper((string)($u->username ?? ''));
                $key = $uid ?: ($u->id_number ?: $u->name);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $results[] = [
                        'username' => $uid,
                        'name' => mb_strtoupper((string)$u->name, 'UTF-8'),
                        'id_number' => (string)($u->id_number ?? ''),
                        'department' => (string)($u->department ?? ''),
                        'email' => strtolower((string)$u->email),
                        'phone' => (string)($u->phone ?? ''),
                        'role' => (string)($u->role ?? 'Personal Corporativo'),
                        'source' => 'database',
                    ];
                }
            }
        }

        return array_slice($results, 0, $limit);
    }
}
