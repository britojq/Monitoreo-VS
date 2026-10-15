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
     * Buscar usuarios en el servidor LDAP corporativo (AJAX)
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

        $results = [];
        try {
            $results = $ldapService->searchUsers($q);
        } catch (\Throwable $e) {
            Log::warning("Fallo al consultar LDAP para término [{$q}]: " . $e->getMessage());
        }

        $existingUsers = User::whereIn('username', array_column($results, 'uid'))
            ->orWhereIn('email', array_column($results, 'email'))
            ->get()
            ->keyBy('username');

        foreach ($results as &$r) {
            $existing = $existingUsers->get($r['uid']) ?? User::where('email', $r['email'])->first();
            if ($existing) {
                $r['already_authorized'] = true;
                $r['current_role'] = $existing->role;
                $r['is_active'] = (bool) $existing->is_active;
                $r['user_id'] = $existing->id;
                if (!empty($existing->cedula)) {
                    $r['cedula'] = $existing->cedula;
                }
            } else {
                $r['already_authorized'] = false;
                $r['current_role'] = null;
                $r['is_active'] = null;
                $r['user_id'] = null;
            }
        }
        unset($r);

        if (empty($results)) {
            $cleanQ = preg_replace('/[^0-9]/', '', $q);
            $localMatches = User::where(function ($query) use ($q, $cleanQ) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('username', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%");
                if (!empty($cleanQ)) {
                    $query->orWhere('cedula', 'like', "%{$cleanQ}%")
                          ->orWhere('cedula', 'like', "%{$q}%");
                }
            })->take(10)->get();

            foreach ($localMatches as $u) {
                $results[] = [
                    'uid' => strtoupper($u->username),
                    'name' => $u->name,
                    'email' => strtolower($u->email),
                    'cedula' => $u->cedula ?? '',
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
                    'source' => 'local',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'count' => count($results),
            'results' => $results,
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
