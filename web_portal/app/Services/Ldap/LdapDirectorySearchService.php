<?php

declare(strict_types=1);

namespace App\Services\Ldap;

/**
 * Servicio de búsqueda y resolución de identidades en el Directorio Activo LDAP.
 */
class LdapDirectorySearchService
{
    public function __construct(
        protected LdapConnectionManager $connectionManager,
        protected LdapEmployeeLookupService $employeeLookup
    ) {}

    /**
     * Buscar usuarios en LDAP por UID o correo para el módulo administrativo de autorización.
     *
     * @param string $term Término de búsqueda (ej. U1234567, 1234567, correo)
     * @return array Lista de usuarios encontrados con sus metadatos
     */
    public function searchUsers(string $term): array
    {
        if (!$this->connectionManager->isEnabled()) {
            return [];
        }

        $cleanTerm = trim($term);
        if (strlen($cleanTerm) < 2) {
            return [];
        }

        $conn = $this->connectionManager->getConnection();
        if (!$conn) {
            return [];
        }

        $cfg = $this->connectionManager->getConfig();
        $baseDn = $this->connectionManager->resolveBaseDn($conn, $cfg['base_dn']);
        $domains = $this->connectionManager->getDomainsFromBaseDn($baseDn);
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
                if (!$uid) {
                    continue;
                }

                $cn = $e['cn'][0] ?? $uid;
                $mail = $e['mail'][0] ?? ($uid . '@' . $primaryDomain);
                $desc = $e['description'][0] ?? 'Sin descripción';
                $sede = $e['o'][0] ?? 'No especificada';
                $st = $e['st'][0] ?? '';

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
     * Obtener los datos completos de un usuario por su UID exacto.
     */
    public function findUserByUid(string $uid): ?array
    {
        if (!$this->connectionManager->isEnabled()) {
            return null;
        }

        $cleanUid = trim($uid);
        if (empty($cleanUid)) {
            return null;
        }

        $conn = $this->connectionManager->getConnection();
        if (!$conn) {
            return null;
        }

        $cfg = $this->connectionManager->getConfig();
        $baseDn = $this->connectionManager->resolveBaseDn($conn, $cfg['base_dn']);
        $domains = $this->connectionManager->getDomainsFromBaseDn($baseDn);
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
     */
    public function searchEmployees(string $query, int $limit = 10): array
    {
        return $this->employeeLookup->searchEmployees($query, $limit);
    }
}
