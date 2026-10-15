<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Maintenance\CreateMaintenanceTicketAction;
use App\Actions\Maintenance\DeleteMaintenanceTicketAction;
use App\Actions\Maintenance\UpdateMaintenanceTicketAction;
use App\Contracts\Repositories\MaintenanceTicketRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreMaintenanceTicketRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceTicketRequest;
use App\Models\InventoryAsset;
use App\Models\MaintenanceTicket;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceTicketController extends Controller
{
    private readonly MaintenanceTicketRepositoryInterface $repository;

    public function __construct(
        ?MaintenanceTicketRepositoryInterface $repository = null
    ) {
        $this->repository = $repository ?? app(MaintenanceTicketRepositoryInterface::class);
    }

    /**
     * Listado general de Órdenes de Servicio y Mantenimiento Técnico.
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $typeFilter = $request->query('type');
        $priorityFilter = $request->query('priority');
        $search = trim((string) $request->query('search', ''));

        $tickets = $this->repository->paginateFiltered(
            $statusFilter ? (string) $statusFilter : null,
            $typeFilter ? (string) $typeFilter : null,
            $priorityFilter ? (string) $priorityFilter : null,
            $search !== '' ? $search : null,
            15
        )->withQueryString();

        $stats = $this->repository->getStats();

        return view('admin.inventory.maintenance.index', compact('tickets', 'stats', 'statusFilter', 'typeFilter', 'priorityFilter', 'search'));
    }

    /**
     * Formulario para abrir una nueva orden de servicio o programar mantenimiento.
     */
    public function create(Request $request): View
    {
        $preselectedAssetId = $request->query('asset_id');
        $preselectedAsset = null;
        if ($preselectedAssetId) {
            $preselectedAsset = InventoryAsset::find($preselectedAssetId);
        }

        $assets = InventoryAsset::orderBy('name')->get();
        $technicians = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email', 'role']);

        return view('admin.inventory.maintenance.create', compact('assets', 'preselectedAsset', 'technicians'));
    }

    /**
     * Guarda la nueva orden de servicio técnico.
     */
    public function store(Request $request, ?CreateMaintenanceTicketAction $action = null): RedirectResponse
    {
        $action = $action ?? app(CreateMaintenanceTicketAction::class);
        $formRequest = $request instanceof StoreMaintenanceTicketRequest
            ? $request
            : StoreMaintenanceTicketRequest::createFrom($request);

        if (!$request instanceof StoreMaintenanceTicketRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        try {
            $ticket = $action->execute(
                $formRequest->validated(),
                $request->ip(),
                $request->userAgent()
            );

            return redirect()->route('admin.inventory.maintenance.show', $ticket->id)
                ->with('success', "Ticket {$ticket->ticket_code} registrado exitosamente.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', "Error al registrar el ticket: {$e->getMessage()}");
        }
    }

    /**
     * Ficha de la Orden de Servicio con bitácora técnica y resolución.
     */
    public function show(MaintenanceTicket $ticket): View
    {
        $ticket->load(['asset.components', 'technician']);

        return view('admin.inventory.maintenance.show', compact('ticket'));
    }

    /**
     * Formulario de edición y actualización de estado técnico.
     */
    public function edit(MaintenanceTicket $ticket): View
    {
        $ticket->load(['asset', 'technician']);
        $technicians = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email', 'role']);

        return view('admin.inventory.maintenance.edit', compact('ticket', 'technicians'));
    }

    /**
     * Actualiza el ticket, registra resolución, repuestos y costos.
     */
    public function update(Request $request, MaintenanceTicket $ticket, ?UpdateMaintenanceTicketAction $action = null): RedirectResponse
    {
        $action = $action ?? app(UpdateMaintenanceTicketAction::class);
        $formRequest = $request instanceof UpdateMaintenanceTicketRequest
            ? $request
            : UpdateMaintenanceTicketRequest::createFrom($request);

        if (!$request instanceof UpdateMaintenanceTicketRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        try {
            $updated = $action->execute(
                $ticket,
                $formRequest->validated(),
                $request->boolean('warranty_applied'),
                $request->ip(),
                $request->userAgent()
            );

            return redirect()->route('admin.inventory.maintenance.show', $updated->id)
                ->with('success', "Ticket {$updated->ticket_code} actualizado correctamente.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', "Error al actualizar ticket: {$e->getMessage()}");
        }
    }

    /**
     * Renderizado y descarga de la Orden Oficial de Servicio Técnico en PDF.
     */
    public function pdf(Request $request, MaintenanceTicket $ticket)
    {
        $ticket->load(['asset.components', 'technician']);

        $empresaPath = public_path('assets/img/inventory/empresa_logo.png');
        $empresaLogoBase64 = file_exists($empresaPath) ? base64_encode(file_get_contents($empresaPath)) : null;

        if ($request->has('download')) {
            $pdf = Pdf::loadView('admin.inventory.pdf.orden_servicio', [
                'ticket' => $ticket,
                'empresaLogoBase64' => $empresaLogoBase64,
                'isPdf' => true,
            ]);
            $pdf->setPaper('letter', 'portrait');
            return $pdf->download("ORDEN-SERVICIO-{$ticket->ticket_code}.pdf");
        }

        return view('admin.inventory.pdf.orden_servicio', [
            'ticket' => $ticket,
            'empresaLogoBase64' => $empresaLogoBase64,
            'isPdf' => false,
        ]);
    }

    /**
     * Elimina un ticket de mantenimiento.
     */
    public function destroy(MaintenanceTicket $ticket, ?DeleteMaintenanceTicketAction $action = null): RedirectResponse
    {
        $action = $action ?? app(DeleteMaintenanceTicketAction::class);
        $code = $action->execute($ticket, request()->ip(), request()->userAgent());

        return redirect()->route('admin.inventory.maintenance.index')
            ->with('success', "Ticket {$code} eliminado.");
    }
}
