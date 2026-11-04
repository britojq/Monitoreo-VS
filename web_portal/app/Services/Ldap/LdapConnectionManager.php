<?php

declare(strict_types=1);

namespace App\Services\Ldap;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Gestor de conexión, sondeo y configuración de Directorio Activo (LDAP).
 * Opera directamente contra MariaDB SSOT (tabla bot_settings).
 */
class LdapConnectionManager
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
            'bind_dn' => '',
            'bind_password' => '',
            'has_bind_password' => false,
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
                $default['bind_dn'] = trim((string)($c['bind_dn'] ?? ''));
                $hasPw = !empty($c['bind_password_encrypted']);
                $default['has_bind_password'] = $hasPw;
                $default['bind_password'] = $hasPw ? '••••••••' : '';
                $default['updated_at'] = $c['updated_at'] ?? null;
                $default['updated_by'] = $c['updated_by'] ?? null;
            }
        } catch (\Throwable $e) {
            Log::error('Error leyendo config LDAP de MariaDB: ' . $e->getMessage());
        }

        if (empty($default['base_dn']) || stripos($default['base_dn'], 'dc=empresa') !== false) {
            $default['base_dn'] = base64_decode('ZGM9Y29ycG9lbGVjLGRjPWdvYixkYz12ZQ==');
        }

        return $default;
    }

    /**
     * Obtener de forma segura y descifrada la contraseña de enlace (Bind Password)
     * protegida con cifrado AES-256 en MariaDB (SEC-004 / CWE-256).
     */
    public function getDecryptedBindPassword(): ?string
    {
        try {
            $c = BotSetting::get('ldap_config');
            if (is_array($c) && !empty($c['bind_password_encrypted'])) {
                return Crypt::decryptString((string) $c['bind_password_encrypted']);
            }
        } catch (\Throwable $e) {
            Log::error('Error desencriptando bind_password de LDAP: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Comprobar si la autenticación LDAP está habilitada en el sistema.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->getConfig()['enabled'];
    }

    /**
     * Actualizar y persistir la configuración de LDAP en MariaDB (SSOT).
     * Las contraseñas de servicio se cifran mediante AES-256-GCM (SEC-004).
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

            $bindDn = trim((string)($attributes['bind_dn'] ?? ($currentLdap['bind_dn'] ?? '')));

            // Recuperar contraseña previa cifrada si existe
            $currentRaw = BotSetting::get('ldap_config');
            $bindPasswordEncrypted = is_array($currentRaw) ? ($currentRaw['bind_password_encrypted'] ?? null) : null;

            if (array_key_exists('bind_password', $attributes)) {
                $rawPw = (string) $attributes['bind_password'];
                if ($rawPw === '') {
                    $bindPasswordEncrypted = null;
                } elseif ($rawPw !== '••••••••' && !str_starts_with($rawPw, '[ENCRYPTED')) {
                    // Cifrado criptográfico AES-256 (SEC-004)
                    $bindPasswordEncrypted = Crypt::encryptString($rawPw);
                }
            }

            $ldapData = [
                'enabled' => $enabled,
                'host' => $host,
                'port' => $port,
                'base_dn' => $baseDn,
                'allowed_areas' => $allowedAreas,
                'default_role' => $defaultRole,
                'bind_dn' => $bindDn,
                'bind_password_encrypted' => $bindPasswordEncrypted,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $updatedBy ?? 'Administrador',
            ];

            BotSetting::set('ldap_config', $ldapData, 'ldap', 'Configuración de Conexión y Autenticación LDAP', 'json');

            return true;
        } catch (\Throwable $e) {
            Log::error('Error guardando config LDAP en MariaDB: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resuelve dinámicamente la Base DN consultando el RootDSE del servidor LDAP (RFC 4512).
     */
    public function resolveBaseDn($conn, ?string $configuredBaseDn = null): string
    {
        $candidate = trim($configuredBaseDn ?? '');

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
    public function healConfigBaseDn(string $realBaseDn): void
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
                    Log::info("LDAP: Base DN auto-sanada dinámicamente en MariaDB -> {$realBaseDn}");
                }
            }
        } catch (\Throwable $e) {
            // Ignorar errores silenciosamente
        }
    }

    /**
     * Obtiene los dominios de correo permitidos a partir de los componentes dc= de la Base DN.
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
     * Obtener conexión LDAP activa con autenticación de enlace (Bind) o anónima.
     */
    public function getConnection(?string $customHost = null, ?int $customPort = null)
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

        $bindDn = $cfg['bind_dn'] ?? '';
        $bindPassword = $this->getDecryptedBindPassword();

        if (!empty($bindDn) && !empty($bindPassword)) {
            if (!@ldap_bind($conn, $bindDn, $bindPassword)) {
                Log::warning('LDAP: Falló bind autenticado (' . $bindDn . '): ' . ldap_error($conn));
                @ldap_unbind($conn);
                return null;
            }
        } else {
            if (!@ldap_bind($conn)) {
                Log::warning('LDAP: Falló bind anónimo: ' . ldap_error($conn));
                @ldap_unbind($conn);
                return null;
            }
        }

        return $conn;
    }

    /**
     * Probar en tiempo real la conectividad y validación de Base DN hacia el servidor LDAP.
     */
    public function testConnection(
        ?string $host = null,
        ?int $port = null,
        ?string $baseDn = null,
        ?string $bindDn = null,
        ?string $bindPassword = null
    ): array {
        $cfg = $this->getConfig();
        $testHost = trim($host ?: $cfg['host']);
        $testPort = (int)($port ?: $cfg['port']);
        $testBaseDn = trim($baseDn ?: $cfg['base_dn']);
        $testBindDn = trim($bindDn ?? ($cfg['bind_dn'] ?? ''));
        $testBindPw = $bindPassword !== null ? $bindPassword : $this->getDecryptedBindPassword();

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

        $bindSuccess = false;
        if (!empty($testBindDn) && !empty($testBindPw) && $testBindPw !== '••••••••') {
            $bindSuccess = (bool) @ldap_bind($conn, $testBindDn, $testBindPw);
        } else {
            $bindSuccess = (bool) @ldap_bind($conn);
        }

        if (!$bindSuccess) {
            $latency = round((microtime(true) - $start) * 1000, 1);
            $err = ldap_error($conn);
            @ldap_unbind($conn);
            return [
                'success' => false,
                'latency_ms' => $latency,
                'message' => "Fallo de conexión o enlace (bind) a {$testHost}:{$testPort} ({$err}).",
            ];
        }

        $effectiveBaseDn = $this->resolveBaseDn($conn, $testBaseDn);
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
}
