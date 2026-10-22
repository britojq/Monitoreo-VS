<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\IpamGridInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador para la Gestión Visual de Direcciones IP (IPAM Grid).
 * Principio S (Single Responsibility) y D (Dependency Inversion).
 */
class AdminIpamController extends Controller
{
    public function __construct(
        protected IpamGridInterface $ipamService
    ) {}

    /**
     * Muestra la interfaz gráfica de la matriz interactiva IPAM de 256 bloques.
     */
    public function index(Request $request): View
    {
        $subnets = $this->ipamService->getAvailableSubnets();
        $defaultSubnet = $subnets[0]['cidr'] ?? '192.168.1.0/24';
        $selectedSubnet = trim($request->query('subnet', $defaultSubnet));

        $grid = $this->ipamService->getSubnetGrid($selectedSubnet);

        return view('admin.ipam.index', compact('subnets', 'selectedSubnet', 'grid'));
    }

    /**
     * Endpoint AJAX para recargar la cuadrícula y métricas de una subred en tiempo real sin recargar página.
     */
    public function grid(Request $request): JsonResponse
    {
        $subnet = trim((string) $request->query('subnet', '192.168.1.0/24'));
        $grid = $this->ipamService->getSubnetGrid($subnet);

        return response()->json($grid);
    }
}
