<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LdapAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminLdapController extends Controller
{
    protected LdapAuthService $ldapService;

    public function __construct(LdapAuthService $ldapService)
    {
        $this->ldapService = $ldapService;
    }

    /**
     * Actualiza la configuración de Directorio Activo / LDAP.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'ldap_enabled' => ['required', 'in:0,1,true,false'],
            'ldap_host' => ['required', 'string', 'max:255'],
            'ldap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ldap_base_dn' => ['required', 'string', 'max:255'],
            'ldap_allowed_areas' => ['nullable', 'string', 'max:1000'],
            'ldap_default_role' => ['required', 'in:operator,admin'],
        ], [
            'ldap_enabled.required' => 'Debe indicar si el servicio LDAP estará activo o inactivo.',
            'ldap_host.required' => 'La dirección IP o nombre de host del servidor LDAP es obligatoria.',
            'ldap_port.required' => 'El puerto de conexión LDAP es obligatorio.',
            'ldap_port.integer' => 'El puerto debe ser un número entero válido (ej. 389 o 636).',
            'ldap_port.min' => 'El puerto debe ser mayor o igual a 1.',
            'ldap_port.max' => 'El puerto debe ser menor o igual a 65535.',
            'ldap_base_dn.required' => 'El Base DN de búsqueda es obligatorio (ej. dc=corpoelec,dc=gob,dc=ve).',
            'ldap_default_role.in' => 'El rol por defecto debe ser Operador o Administrador.',
        ]);

        $enabled = filter_var($validated['ldap_enabled'], FILTER_VALIDATE_BOOLEAN);
        $updaterName = auth()->user()?->name ?: (auth()->user()?->username ?: 'Administrador');

        $ok = $this->ldapService->updateConfig([
            'enabled' => $enabled,
            'host' => trim($validated['ldap_host']),
            'port' => (int) $validated['ldap_port'],
            'base_dn' => trim($validated['ldap_base_dn']),
            'allowed_areas' => trim($validated['ldap_allowed_areas'] ?? ''),
            'default_role' => $validated['ldap_default_role'],
        ], $updaterName);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $ok,
                'message' => $ok
                    ? 'Parámetros de autenticación LDAP actualizados correctamente.'
                    : 'No se pudo guardar la configuración de LDAP en config.json.',
                'enabled' => $enabled,
            ]);
        }

        if ($ok) {
            $statusText = $enabled ? 'HABILITADA' : 'DESHABILITADA';
            return back()->with('success', "Configuración de Directorio Activo (LDAP) actualizada exitosamente. Autenticación LDAP: {$statusText}.");
        }

        return back()->with('error', 'Ocurrió un error al persistir la configuración de LDAP.');
    }

    /**
     * Prueba en tiempo real la conectividad y validación de Base DN.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $host = $request->input('host');
        $port = $request->filled('port') ? (int) $request->input('port') : null;
        $baseDn = $request->input('base_dn');

        $result = $this->ldapService->testConnection($host, $port, $baseDn);

        return response()->json($result);
    }
}
