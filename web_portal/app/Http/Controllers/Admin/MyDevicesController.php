<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MyDevicesController extends Controller
{
    /**
     * Muestra el catálogo de puestos de trabajo y equipos asignados al usuario autenticado.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $userId = $user->id;
        $cedula = trim((string) ($user->cedula ?? ''));
        $name = trim((string) ($user->name ?? ''));

        $devices = InventoryAsset::with([
            'peripherals',
            'activeAssignment',
            'site',
            'networkInterfaces',
        ])
        ->where(function ($query) use ($userId, $cedula, $name) {
            $query->where('current_user_id', $userId);
            if (!empty($cedula)) {
                $query->orWhere('custodian_id_number', $cedula);
            }
            if (!empty($name)) {
                $query->orWhere('custodian_name', $name);
            }
            $query->orWhereHas('assignments', function ($q) use ($userId, $cedula, $name) {
                $q->where('status', 'active')
                  ->where(function ($sub) use ($userId, $cedula, $name) {
                      $sub->where('user_id', $userId);
                      if (!empty($cedula)) {
                          $sub->orWhere('custodian_id_number', $cedula);
                      }
                      if (!empty($name)) {
                          $sub->orWhere('custodian_name', $name);
                      }
                  });
            });
        })
        ->orderBy('name')
        ->get();

        $totalDevices = $devices->count();
        $totalPeripherals = $devices->sum(function ($d) {
            if ($d->peripherals->isNotEmpty()) {
                return $d->peripherals->count();
            }
            if ($d->activeAssignment && is_array($d->activeAssignment->peripherals_json)) {
                return count($d->activeAssignment->peripherals_json);
            }
            return 0;
        });
        $totalActsWithPdf = $devices->filter(function ($d) {
            $acta = $d->activeAssignment;
            if (!$acta || empty($acta->document_pdf_path)) {
                return false;
            }
            return $this->resolvePdfPath($acta->document_pdf_path) !== null;
        })->count();

        return view('admin.inventory.my_devices', [
            'devices' => $devices,
            'totalDevices' => $totalDevices,
            'totalPeripherals' => $totalPeripherals,
            'totalActsWithPdf' => $totalActsWithPdf,
            'user' => $user,
        ]);
    }

    /**
     * Visualización o descarga segura del Acta de Asignación en PDF.
     */
    public function downloadActa(Request $request, InventoryAssignment $assignment): BinaryFileResponse
    {
        $user = $request->user();

        // Control de Acceso: El usuario solo puede consultar sus propias actas a menos que sea Administrador/Operador
        $isOwner = ($assignment->user_id === $user->id) ||
                   (!empty($user->cedula) && $assignment->custodian_id_number === $user->cedula) ||
                   (!empty($user->name) && $assignment->custodian_name === $user->name);

        if (!$isOwner && !$user->isAdmin() && !$user->hasPermission('inventory.assign')) {
            abort(403, 'No tiene autorización para consultar esta acta de entrega.');
        }

        $pdfPath = $assignment->document_pdf_path;
        if (empty($pdfPath)) {
            abort(404, 'No existe documento digitalizado para esta acta.');
        }

        $fullPath = $this->resolvePdfPath($pdfPath);
        if (!$fullPath) {
            abort(404, 'El archivo físico del acta no fue localizado en el servidor de almacenamiento.');
        }

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Acta_' . ($assignment->assignment_code ?: 'UINF-4.05') . '.pdf"',
        ]);
    }

    /**
     * Resuelve la ruta física del archivo PDF en los directorios de almacenamiento.
     */
    private function resolvePdfPath(string $rawPath): ?string
    {
        $clean = ltrim($rawPath, '/');

        $candidates = [
            storage_path('app/public/' . $clean),
            public_path('storage/' . $clean),
            public_path($clean),
            '/home/data_monitoreo/storage/app/public/' . $clean,
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand) && is_file($cand)) {
                return $cand;
            }
        }

        return null;
    }
}
