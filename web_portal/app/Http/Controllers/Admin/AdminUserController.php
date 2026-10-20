<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Users\AuthorizeLdapUserAction;
use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Actions\Users\UpdateUserPermissionsAction;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\AuthorizeLdapUserRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserPermissionsRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\User;
use App\Services\LdapAuthService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    private readonly UserRepositoryInterface $repository;

    public function __construct(
        ?UserRepositoryInterface $repository = null
    ) {
        $this->repository = $repository ?? app(UserRepositoryInterface::class);
    }

    public function index(): View
    {
        $users = $this->repository->getAllOrdered();

        return view('admin.users.index', compact('users'));
    }

    /**
     * Buscar personal / usuarios unificado (MariaDB SSOT + Directorio Activo LDAP)
     */
    public function searchLdapUsers(Request $request, ?LdapAuthService $ldapService = null): JsonResponse
    {
        $ldapService = $ldapService ?? app(LdapAuthService::class);
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrese al menos 2 caracteres para realizar la búsqueda.',
                'results' => [],
            ]);
        }

        $cleanQ = preg_replace('/[^0-9]/', '', $q);
        $cedVariants = array_values(array_filter(array_unique([
            $cleanQ,
            (strlen($cleanQ) === 8 && str_starts_with($cleanQ, '1')) ? substr($cleanQ, 1) : null,
            (strlen($cleanQ) === 7) ? '1' . $cleanQ : null,
        ])));
        $finalResults = [];
        $seenKeys = [];

        // 1. MariaDB SSOT: Histórico de Asignaciones (Actas UINF-4.05)
        try {
            $assignments = InventoryAssignment::whereNotNull('custodian_name')
                ->where('custodian_name', '!=', '')
                ->where(function ($query) use ($q, $cleanQ, $cedVariants) {
                    $query->where('custodian_name', 'like', "%{$q}%");
                    foreach ($cedVariants as $cv) {
                        $query->orWhere('custodian_id_number', 'like', "%{$cv}%");
                    }
                    $query->orWhere('custodian_id_number', 'like', "%{$q}%");
                })
                ->orderByDesc('id')
                ->take(15)
                ->get();

            foreach ($assignments as $a) {
                $name = trim((string) ($a->custodian_name ?? ''));
                $cedula = trim((string) ($a->custodian_id_number ?? ''));
                $cleanCed = preg_replace('/[^0-9]/', '', $cedula);
                $key = !empty($cleanCed) ? 'ced_' . $cleanCed : 'name_' . mb_strtoupper($name);

                if (isset($seenKeys[$key]) || empty($name)) {
                    continue;
                }
                $seenKeys[$key] = true;

                $finalResults[] = [
                    'uid' => $a->user_id ? 'U' . $a->user_id : '',
                    'name' => $name,
                    'email' => '',
                    'cedula' => $cedula,
                    'description' => 'Personal Asignado en ITAM',
                    'department' => $a->custodian_department ?? 'Sin Departamento',
                    'role' => $a->custodian_role ?? 'Personal Asignado',
                    'phone' => $a->phone_extension ?? '',
                    'sede' => '',
                    'state' => '',
                    'is_default_area' => true,
                    'already_authorized' => true,
                    'current_role' => null,
                    'is_active' => true,
                    'user_id' => $a->user_id,
                    'source' => 'inventory_assignment',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("Error consultando inventory_assignments para [{$q}]: " . $e->getMessage());
        }

        // 2. MariaDB SSOT: Activos de Inventario
        try {
            $assets = InventoryAsset::whereNotNull('custodian_name')
                ->where('custodian_name', '!=', '')
                ->where(function ($query) use ($q, $cleanQ, $cedVariants) {
                    $query->where('custodian_name', 'like', "%{$q}%");
                    foreach ($cedVariants as $cv) {
                        $query->orWhere('custodian_id_number', 'like', "%{$cv}%");
                    }
                    $query->orWhere('custodian_id_number', 'like', "%{$q}%");
                })
                ->orderByDesc('id')
                ->take(10)
                ->get();

            foreach ($assets as $ast) {
                $name = trim((string) ($ast->custodian_name ?? ''));
                $cedula = trim((string) ($ast->custodian_id_number ?? ''));
                $cleanCed = preg_replace('/[^0-9]/', '', $cedula);
                $key = !empty($cleanCed) ? 'ced_' . $cleanCed : 'name_' . mb_strtoupper($name);

                if (isset($seenKeys[$key]) || empty($name)) {
                    continue;
                }
                $seenKeys[$key] = true;

                $finalResults[] = [
                    'uid' => '',
                    'name' => $name,
                    'email' => '',
                    'cedula' => $cedula,
                    'description' => 'Personal en Activos ITAM',
                    'department' => $ast->custodian_department ?? 'Sin Departamento',
                    'role' => $ast->custodian_role ?? 'Personal Asignado',
                    'phone' => '',
                    'sede' => '',
                    'state' => '',
                    'is_default_area' => true,
                    'already_authorized' => true,
                    'current_role' => null,
                    'is_active' => true,
                    'user_id' => null,
                    'source' => 'inventory_asset',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("Error consultando inventory_assets para [{$q}]: " . $e->getMessage());
        }

        // 3. MariaDB SSOT: Usuarios Registrados del Sistema
        try {
            $localMatches = User::where(function ($query) use ($q, $cleanQ, $cedVariants) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('username', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%");
                foreach ($cedVariants as $cv) {
                    $query->orWhere('cedula', 'like', "%{$cv}%");
                }
                $query->orWhere('cedula', 'like', "%{$q}%");
            })->take(10)->get();

            foreach ($localMatches as $u) {
                $name = trim((string) $u->name);
                $cedula = trim((string) ($u->cedula ?? ''));
                $cleanCed = preg_replace('/[^0-9]/', '', $cedula);
                $key = !empty($cleanCed) ? 'ced_' . $cleanCed : 'name_' . mb_strtoupper($name);

                if (isset($seenKeys[$key])) {
                    continue;
                }
                $seenKeys[$key] = true;

                $finalResults[] = [
                    'uid' => strtoupper($u->username),
                    'name' => $name,
                    'email' => strtolower($u->email),
                    'cedula' => $cedula,
                    'description' => $u->academic_title ?? 'Usuario Registrado',
                    'department' => 'ATIT Carabobo',
                    'role' => $u->role === 'admin' ? 'Administrador de Sistemas' : 'Operador de Monitoreo',
                    'phone' => $u->phone ?? '',
                    'sede' => 'Sede Valle Seco',
                    'state' => 'Carabobo',
                    'is_default_area' => true,
                    'already_authorized' => true,
                    'current_role' => $u->role,
                    'is_active' => (bool) $u->is_active,
                    'user_id' => $u->id,
                    'source' => 'local_user',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("Error consultando users para [{$q}]: " . $e->getMessage());
        }

        // 4. Directorio Activo LDAP Corporativo
        $ldapResults = [];
        try {
            $ldapResults = $ldapService->searchUsers($q);
        } catch (\Throwable $e) {
            Log::warning("Fallo al consultar LDAP para término [{$q}]: " . $e->getMessage());
        }

        if (!empty($ldapResults)) {
            $existingUsers = User::whereIn('username', array_column($ldapResults, 'uid'))
                ->orWhereIn('email', array_column($ldapResults, 'email'))
                ->get()
                ->keyBy('username');

            foreach ($ldapResults as $r) {
                $name = trim((string) ($r['name'] ?? ''));
                $cedula = trim((string) ($r['cedula'] ?? ''));
                $cleanCed = preg_replace('/[^0-9]/', '', $cedula);
                $key = !empty($cleanCed) ? 'ced_' . $cleanCed : 'name_' . mb_strtoupper($name);

                $existing = $existingUsers->get($r['uid'] ?? '') ?? User::where('email', $r['email'] ?? '')->first();
                $r['already_authorized'] = (bool) $existing;
                $r['current_role'] = $existing?->role;
                $r['is_active'] = $existing ? (bool) $existing->is_active : null;
                $r['user_id'] = $existing?->id;
                if (!empty($existing?->cedula)) {
                    $r['cedula'] = $existing->cedula;
                }
                $r['source'] = 'ldap';

                if (isset($seenKeys[$key])) {
                    // Enriquecer registro existente con datos de LDAP si estaban vacíos
                    foreach ($finalResults as &$existingItem) {
                        $exCed = preg_replace('/[^0-9]/', '', (string) ($existingItem['cedula'] ?? ''));
                        $exKey = !empty($exCed) ? 'ced_' . $exCed : 'name_' . mb_strtoupper((string) $existingItem['name']);
                        if ($exKey === $key) {
                            if (empty($existingItem['email']) && !empty($r['email'])) {
                                $existingItem['email'] = $r['email'];
                            }
                            if (empty($existingItem['uid']) && !empty($r['uid'])) {
                                $existingItem['uid'] = $r['uid'];
                            }
                            if (empty($existingItem['phone']) && !empty($r['phone'])) {
                                $existingItem['phone'] = $r['phone'];
                            }
                            break;
                        }
                    }
                    unset($existingItem);
                    continue;
                }

                $seenKeys[$key] = true;
                $finalResults[] = $r;
            }
        }

        return response()->json([
            'success' => true,
            'count' => count($finalResults),
            'results' => array_values($finalResults),
        ]);
    }

    /**
     * Pre-autorizar o registrar manualmente a un usuario desde LDAP
     */
    public function authorizeLdapUser(Request $request, ?AuthorizeLdapUserAction $action = null)
    {
        $action = $action ?? app(AuthorizeLdapUserAction::class);
        $formRequest = $request instanceof AuthorizeLdapUserRequest
            ? $request
            : AuthorizeLdapUserRequest::createFrom($request);

        if (!$request instanceof AuthorizeLdapUserRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $validated = $formRequest->validated();
        $result = $action->execute((string) $validated['uid'], (string) $validated['role']);

        if (!$result['success']) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 404);
            }
            return back()->with('error', $result['message']);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'user' => $result['user'],
            ]);
        }

        return redirect()->route('admin.users.index')->with('success', $result['message']);
    }

    public function store(Request $request, ?CreateUserAction $action = null): RedirectResponse
    {
        $action = $action ?? app(CreateUserAction::class);
        $formRequest = $request instanceof StoreUserRequest
            ? $request
            : StoreUserRequest::createFrom($request);

        if (!$request instanceof StoreUserRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $action->execute($formRequest->validated());

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado exitosamente.');
    }

    public function update(Request $request, User $user, ?UpdateUserAction $action = null): RedirectResponse
    {
        $action = $action ?? app(UpdateUserAction::class);
        $formRequest = $request instanceof UpdateUserRequest
            ? $request
            : UpdateUserRequest::createFrom($request);

        if (!$request instanceof UpdateUserRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $action->execute($user, $formRequest->validated(), $request->boolean('is_active'));

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado exitosamente.');
    }

    public function destroy(User $user, ?DeleteUserAction $action = null): RedirectResponse
    {
        $action = $action ?? app(DeleteUserAction::class);
        $result = $action->execute($user);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('admin.users.index')->with('success', $result['message']);
    }

    /**
     * Consultar catálogo y permisos asignados a un usuario (AJAX)
     */
    public function getPermissions(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'role' => $user->role,
                'is_admin' => $user->isAdmin(),
                'is_default' => is_null($user->permissions),
            ],
            'modules' => PermissionService::getModules(),
            'default_operator' => PermissionService::getDefaultOperatorPermissions(),
            'assigned_permissions' => $user->permissions,
            'effective_permissions' => $user->getEffectivePermissions(),
        ]);
    }

    /**
     * Actualizar la matriz de permisos granulares de un usuario (AJAX)
     */
    public function updatePermissions(Request $request, User $user, ?UpdateUserPermissionsAction $action = null): JsonResponse
    {
        $action = $action ?? app(UpdateUserPermissionsAction::class);
        $formRequest = $request instanceof UpdateUserPermissionsRequest
            ? $request
            : UpdateUserPermissionsRequest::createFrom($request);

        if (!$request instanceof UpdateUserPermissionsRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $result = $action->execute(
            $user,
            $formRequest->boolean('reset_to_default'),
            $formRequest->input('permissions')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status_code']);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'effective_permissions' => $result['effective_permissions'],
            'is_default' => $result['is_default'],
        ]);
    }
}
