<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LdapAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('id')->get();
        return view('admin.users.index', compact('users'));
    }

    /**
     * Buscar usuarios en el servidor LDAP corporativo (AJAX)
     */
    public function searchLdapUsers(Request $request, LdapAuthService $ldapService): JsonResponse
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrese al menos 2 caracteres para realizar la búsqueda.',
                'results' => [],
            ]);
        }

        $results = $ldapService->searchUsers($q);

        // Enriquecer cada resultado con su estado actual en la BD local
        $existingUsers = User::whereIn('username', array_column($results, 'uid'))
            ->orWhereIn('email', array_column($results, 'email'))
            ->get()
            ->keyBy('username');

        foreach ($results as &$r) {
            $existing = $existingUsers->get($r['uid']) ?? User::where('email', $r['email'])->first();
            if ($existing) {
                $r['already_authorized'] = true;
                $r['current_role'] = $existing->role;
                $r['is_active'] = $existing->is_active;
                $r['user_id'] = $existing->id;
            } else {
                $r['already_authorized'] = false;
                $r['current_role'] = null;
                $r['is_active'] = null;
                $r['user_id'] = null;
            }
        }
        unset($r);

        return response()->json([
            'success' => true,
            'count' => count($results),
            'results' => $results,
        ]);
    }

    /**
     * Pre-autorizar o registrar manualmente a un usuario desde LDAP
     */
    public function authorizeLdapUser(Request $request, LdapAuthService $ldapService)
    {
        $validated = $request->validate([
            'uid' => ['required', 'string', 'max:50'],
            'role' => ['required', 'string', Rule::in(['admin', 'operator'])],
        ]);

        $uid = strtoupper(trim($validated['uid']));
        $role = $validated['role'];

        $ldapUser = $ldapService->findUserByUid($uid);
        if (!$ldapUser) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "El usuario {$uid} no fue encontrado en el servidor LDAP corporativo.",
                ], 404);
            }
            return back()->with('error', "El usuario {$uid} no fue encontrado en el servidor LDAP.");
        }

        $user = User::where('username', $uid)
            ->orWhere('email', $ldapUser['email'])
            ->first();

        if ($user) {
            $user->update([
                'name' => $ldapUser['name'],
                'username' => $uid,
                'email' => $ldapUser['email'],
                'role' => $role,
                'is_active' => true,
                'ban_reason' => null,
                'banned_at' => null,
            ]);
            $msg = "El usuario LDAP {$ldapUser['name']} ({$uid}) fue actualizado y reactivado exitosamente con rol '{$role}'.";
        } else {
            $user = User::create([
                'name' => $ldapUser['name'],
                'username' => $uid,
                'email' => $ldapUser['email'],
                'password' => Hash::make(\Illuminate\Support\Str::random(32)),
                'role' => $role,
                'is_active' => true,
            ]);
            $msg = "El usuario LDAP {$ldapUser['name']} ({$uid}) ha sido autorizado e incorporado exitosamente con rol '{$role}'.";
        }

        // Bitácora de auditoría
        $now = now()->format('Y-m-d H:i:s');
        $adminName = Auth::user() ? Auth::user()->name : 'Admin';
        $logLine = sprintf(
            "[%s] 👤 AUTORIZACIÓN MANUAL LDAP | Admin: %s | Usuario: %s (UID: %s, Email: %s, Rol: %s) | Área: %s\n",
            $now,
            $adminName,
            $ldapUser['name'],
            $uid,
            $ldapUser['email'],
            $role,
            $ldapUser['description']
        );
        @file_put_contents('/scripts/telegram-admin-bot/audit/intentos_acceso.log', $logLine, FILE_APPEND | LOCK_EX);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'user' => $user,
            ]);
        }

        return redirect()->route('admin.users.index')->with('success', $msg);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', Rule::in(['admin', 'operator'])],
            'is_active' => ['boolean'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $request->boolean('is_active', true);

        User::create($validated);

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado exitosamente.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', 'string', Rule::in(['admin', 'operator'])],
            'is_active' => ['boolean'],
        ]);

        // Si el usuario es de origen LDAP, la contraseña NUNCA se modifica localmente
        if ($user->isLdapUser()) {
            unset($validated['password']);
        } elseif (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Si es el superadmin principal, no permitir cambiar rol a operador o desactivar
        if ($user->email === 'britojq@gmail.com') {
            $validated['role'] = 'admin';
            $validated['is_active'] = true;
        } else {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $user->update($validated);

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado exitosamente.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'No puede eliminar su propia cuenta de usuario en sesión.');
        }

        if ($user->email === 'britojq@gmail.com') {
            return back()->with('error', 'El Administrador Principal del sistema no puede ser eliminado.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Usuario eliminado exitosamente.');
    }
}
