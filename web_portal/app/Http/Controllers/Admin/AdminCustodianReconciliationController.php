<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\ReconcileCustodianFromLdapAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReconcileCustodianRequest;
use App\Services\LdapAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCustodianReconciliationController extends Controller
{
    public function __construct(
        private readonly LdapAuthService $ldapService,
        private readonly ReconcileCustodianFromLdapAction $reconcileAction
    ) {}

    /**
     * Búsqueda en tiempo real de empleados en Directorio Activo (LDAP).
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->input('q', '');
        if (mb_strlen(trim($query)) < 2) {
            return response()->json([
                'success' => true,
                'count' => 0,
                'data' => [],
            ]);
        }

        $results = $this->ldapService->searchEmployees($query, 12);

        return response()->json([
            'success' => true,
            'count' => count($results),
            'data' => $results,
        ]);
    }

    /**
     * Concilia atómicamente la identidad del responsable del equipo con LDAP,
     * garantizando que el PDF escaneado preexistente no se pierde ni requiere re-subida.
     */
    public function reconcile(ReconcileCustodianRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $assetId = (int) $validated['asset_id'];

        $ldapData = [
            'name' => $validated['custodian_name'],
            'id_number' => $validated['custodian_id_number'] ?? null,
            'department' => $validated['custodian_department'] ?? null,
            'role' => $validated['custodian_role'] ?? 'Personal Corporativo',
            'phone' => $validated['phone_extension'] ?? null,
            'username' => $validated['username'] ?? null,
        ];

        $result = $this->reconcileAction->execute($assetId, $ldapData, auth()->id());

        return response()->json([
            'success' => true,
            'message' => sprintf(
                "Responsable conciliado exitosamente con LDAP: '%s'. La planilla física escaneada se mantiene resguardada.",
                $result['new_name']
            ),
            'data' => [
                'asset_id' => $result['asset']->id,
                'asset_tag' => $result['asset']->asset_tag,
                'custodian_name' => $result['asset']->custodian_name,
                'custodian_id_number' => $result['asset']->custodian_id_number,
                'custodian_department' => $result['asset']->custodian_department,
                'has_scan' => !empty($result['assignment']?->document_pdf_path),
                'assignment_code' => $result['assignment']?->assignment_code,
            ],
        ]);
    }
}
