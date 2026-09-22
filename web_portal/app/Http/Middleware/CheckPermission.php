<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Verifica que el usuario autenticado cuente con el permiso granular requerido
     */
    public function handle(Request $request, Closure $next, string $permission): Response
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

        // Administradores tienen acceso global (*)
        if ($user->isAdmin() || $user->hasPermission($permission)) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => "Acceso Denegado: No cuenta con el permiso requerido [{$permission}].",
            ], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, "Acceso Denegado: Su cuenta no tiene asignado el permiso [{$permission}].");
    }
}
