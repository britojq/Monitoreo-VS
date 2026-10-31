<?php

declare(strict_types=1);

namespace App\Services\Reports\Evaluators;

use App\Models\InventoryAsset;

/**
 * Evaluador de datos para el Censo Maestro NOC + ITAM.
 */
class CensusReportEvaluator
{
    public function __construct(
        protected InventoryRowsEvaluator $rowsEvaluator
    ) {}

    /**
     * Retorna los datos normalizados para el Censo Maestro NOC + ITAM.
     */
    public function getCensusData(): array
    {
        $assets = InventoryAsset::with(['site', 'activeAssignment', 'peripherals'])
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($assets as $a) {
            $responsable = $a->activeAssignment?->recipient_name
                ?? $a->custodian_name
                ?? 'No asignado';

            $depto = $a->activeAssignment?->custodian_department ?? 'N/A';
            $peripheralsCount = $a->peripherals->count();

            $rows[] = [
                'display_identity' => $a->display_identity ?? $a->name,
                'technical_subtitle' => $a->technical_subtitle ?? 'N/A',
                'type_label' => $a->type_label,
                'site_name' => $a->site?->name ?? 'Sede Central',
                'ip_address' => $a->ip_address ?: 'Sin IP fija',
                'mac_address' => $a->mac_address ?: 'N/A',
                'brand' => $a->brand ?: 'Genérico',
                'model' => $a->model ?: 'N/A',
                'serial_number' => $a->serial_number ?: 'N/A',
                'asset_tag' => $a->asset_tag ?: 'N/A',
                'status_label' => $a->status_label,
                'responsable' => $responsable,
                'department' => $depto,
                'peripherals_info' => $peripheralsCount > 0 ? "{$peripheralsCount} periférico(s)" : 'Ninguno',
            ];
        }

        return [
            'title' => 'Censo Maestro NOC + ITAM • Inventario Unificado',
            'headers' => [
                'Nombre Etiqueta',
                'Nombre de Equipo',
                'Tipo de Activo',
                'Sede Física',
                'Dirección IP',
                'Dirección MAC',
                'Fabricante',
                'Modelo',
                'Número de Serie',
                'Etiqueta Bien',
                'Estado Operativo',
                'Funcionario Responsable',
                'Departamento',
                'Periféricos',
            ],
            'rows' => $rows,
            'summary' => [
                'Total Activos Registrados' => count($rows),
                'Sede Principal' => 'Sede Valle Seco',
                'Fecha de Generación' => now()->format('d/m/Y H:i:s'),
            ],
        ];
    }

    public function getServerRows(): array
    {
        return $this->rowsEvaluator->getServerRows();
    }

    public function getTelecomRows(): array
    {
        return $this->rowsEvaluator->getTelecomRows();
    }

    public function getCenterDataRows(): array
    {
        return $this->rowsEvaluator->getCenterDataRows();
    }

    public function getWorkstationRows(): array
    {
        return $this->rowsEvaluator->getWorkstationRows();
    }
}
