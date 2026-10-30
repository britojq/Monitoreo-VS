<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTermsController extends Controller
{
    /**
     * Módulo de Aceptación de Términos de Uso y Políticas de Seguridad
     */
    public function index(Request $request): View
    {
        $query = User::query()->orderBy('name', 'asc');

        // Búsqueda por nombre, email o username
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Filtro por estado de aceptación
        if ($status = $request->input('status')) {
            if ($status === 'accepted') {
                $query->whereNotNull('terms_accepted_at');
            } elseif ($status === 'pending') {
                $query->whereNull('terms_accepted_at');
            }
        }

        // Filtro por rol
        if ($role = $request->input('role')) {
            if ($role !== 'all') {
                $query->where('role', $role);
            }
        }

        $users = $query->paginate(15)->withQueryString();

        // KPIs Estadísticos
        $totalUsers = User::count();
        $acceptedCount = User::whereNotNull('terms_accepted_at')->count();
        $pendingCount = User::whereNull('terms_accepted_at')->count();
        $acceptanceRate = $totalUsers > 0 ? round(($acceptedCount / $totalUsers) * 100, 1) : 0;

        return view('admin.terms.index', compact(
            'users',
            'totalUsers',
            'acceptedCount',
            'pendingCount',
            'acceptanceRate'
        ));
    }

    /**
     * Registro de aceptación de términos por parte del usuario autenticado
     */
    public function accept(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 401);
        }

        $ip = $request->ip() ?? '127.0.0.1';
        $now = now();

        $user->update([
            'terms_accepted_at' => $now,
            'terms_accepted_ip' => $ip,
            'terms_version' => '1.0',
        ]);

        // Registrar en Auditoría del Sistema
        AuditService::log(
            event: 'terms_accepted',
            module: 'security',
            auditable: $user,
            entityName: 'Términos de Uso y Seguridad',
            entityLabel: $user->name,
            description: "Aceptación formal de lineamientos de seguridad, custodia de registros y marco legal (Ley Contra Delitos Informáticos).",
            newValues: [
                'terms_accepted_at' => $now->toIso8601String(),
                'ip_address' => $ip,
                'version' => '1.0',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Lineamientos de seguridad y términos de uso aceptados formalmente.',
            'accepted_at' => $now->timezone('America/Caracas')->format('d/m/Y h:i:s A'),
        ]);
    }

    /**
     * Revocar/Resetear aceptación individual para forzar nueva firma
     */
    public function reset(User $user): RedirectResponse
    {
        $user->update([
            'terms_accepted_at' => null,
            'terms_accepted_ip' => null,
        ]);

        AuditService::log(
            event: 'terms_revoked',
            module: 'security',
            auditable: $user,
            entityName: 'Términos de Uso y Seguridad',
            entityLabel: $user->name,
            description: "Reinicio forzado de aceptación de términos de uso para el usuario [{$user->name}]."
        );

        return back()->with('success', "Se ha solicitado una nueva aceptación de términos al usuario {$user->name}.");
    }

    /**
     * Revocar aceptación a todos los usuarios (ej: actualización de términos)
     */
    public function resetAll(): RedirectResponse
    {
        User::query()->update([
            'terms_accepted_at' => null,
            'terms_accepted_ip' => null,
        ]);

        AuditService::log(
            event: 'terms_revoked_all',
            module: 'security',
            entityName: 'Términos de Uso y Seguridad',
            entityLabel: 'Todos los Usuarios',
            description: "Reinicio masivo de aceptación de términos de uso para todos los usuarios del sistema."
        );

        return back()->with('success', "Se ha reiniciado el estado de términos de uso para todos los usuarios. Deberán aceptarlos nuevamente.");
    }
}
