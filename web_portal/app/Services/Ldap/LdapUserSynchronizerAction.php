<?php

declare(strict_types=1);

namespace App\Services\Ldap;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Acción de autenticación de credenciales, verificación de autorización y
 * extracción de datos de usuario de Directorio Activo (LDAP).
 */
class LdapUserSynchronizerAction
{
    public function __construct(
        protected LdapConnectionManager $connectionManager
    ) {}

    /**
     * Autenticar usuario contra LDAP y validar pertenencia a área permitida o pre-autorizada.
     *
     * @param string $username Código de empleado / UID (ej. U1234567)
     * @param string $password Contraseña de red corporativa
     * @return array{success: bool, message: string, data: array|null, area_authorized: bool}
     */
    public function authenticate(string $username, string $password): array
    {
        if (!$this->connectionManager->isEnabled()) {
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

        $conn = $this->connectionManager->getConnection();
        if (!$conn) {
            return [
                'success' => false,
                'message' => 'No se pudo establecer comunicación con el servidor LDAP corporativo.',
                'data' => null,
                'area_authorized' => false,
            ];
        }

        $cfg = $this->connectionManager->getConfig();
        $baseDn = $this->connectionManager->resolveBaseDn($conn, $cfg['base_dn']);
        $domains = $this->connectionManager->getDomainsFromBaseDn($baseDn);
        $primaryDomain = $domains[0] ?? 'empresa.gob.ve';
        $rawAreas = explode(',', $cfg['allowed_areas'] ?? '');
        $allowedAreas = array_values(array_filter(array_map('trim', $rawAreas)));

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

        $targetRole = $areaAuthorized ? ($cfg['default_role'] ?? 'operator') : 'user';

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

        $cn = $entry['cn'][0] ?? $entryUid;
        $givenName = $entry['givenname'][0] ?? '';
        $sn = $entry['sn'][0] ?? '';
        $telephone = $entry['telephonenumber'][0] ?? '';
        $st = $entry['st'][0] ?? '';

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
}
