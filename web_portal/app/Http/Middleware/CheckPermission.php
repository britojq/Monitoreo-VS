<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Verifica que el usuario autenticado cuente con el permiso granular requerido (o al menos uno si se especifican varios)
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(Response::HTTP_UNAUTHORIZED, 'No autenticado.');
        }

        if (!$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            abort(Response::HTTP_FORBIDDEN, 'Su cuenta de usuario se encuentra suspendida.');
        }

        $allPerms = [];
        foreach ($permissions as $p) {
            foreach (preg_split('/[,|]/', (string) $p) as $sub) {
                if (trim($sub) !== '') {
                    $allPerms[] = trim($sub);
                }
            }
        }

        // Administradores tienen acceso global (*)
        if ($user->isAdmin() || $user->hasAnyPermission($allPerms)) {
            return $next($request);
        }

        $permList = implode(', ', $allPerms);
        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => "Acceso Denegado: No cuenta con el permiso requerido [{$permList}].",
            ], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, "Acceso Denegado: Su cuenta no tiene asignado el permiso [{$permList}].");
    }
}
