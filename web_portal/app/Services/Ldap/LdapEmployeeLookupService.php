<?php

declare(strict_types=1);

namespace App\Services\Ldap;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de búsqueda y conciliación de empleados entre LDAP y base de datos local.
 */
class LdapEmployeeLookupService
{
    public function __construct(
        protected LdapConnectionManager $connectionManager
    ) {}

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
        $conn = $this->connectionManager->getConnection();
        if ($conn) {
            try {
                $cfg = $this->connectionManager->getConfig();
                $baseDn = $this->connectionManager->resolveBaseDn($conn, $cfg['base_dn']);
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
                Log::warning('LdapEmployeeLookupService::searchEmployees exception: ' . $t->getMessage());
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
