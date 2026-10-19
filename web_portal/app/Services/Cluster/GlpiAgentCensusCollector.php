<?php

namespace App\Services\Cluster;

use App\Models\InventoryAsset;

class GlpiAgentCensusCollector
{
    /**
     * Recopila el censo y estado de salud de agentes GLPI en la infraestructura.
     *
     * @return array
     */
    public function collect(): array
    {
        $totalAssets = InventoryAsset::count();
        $reportingAssetsQuery = InventoryAsset::whereNotNull('last_glpi_sync_at');
        $totalGlpiAgents = (clone $reportingAssetsQuery)->count();

        $now = now();
        $active24h = (clone $reportingAssetsQuery)->where('last_glpi_sync_at', '>=', $now->copy()->subHours(24))->count();
        $offline = (clone $reportingAssetsQuery)->where('last_glpi_sync_at', '<', $now->copy()->subHours(24))->count();

        // Desglose por Sistema Operativo
        $win32 = (clone $reportingAssetsQuery)
            ->where(function ($q) {
                $q->where('operating_system', 'LIKE', '%Windows%')
                  ->where(function ($sq) {
                      $sq->where('operating_system', 'LIKE', '%32%')
                        ->orWhere('operating_system', 'LIKE', '%x86%')
                        ->orWhere('os_kernel', 'LIKE', '%32%')
                        ->orWhere('os_kernel', 'LIKE', '%x86%');
                  });
            })->count();

        $win64 = (clone $reportingAssetsQuery)
            ->where(function ($q) {
                $q->where('operating_system', 'LIKE', '%Windows%')
                  ->where(function ($sq) {
                      $sq->where('operating_system', 'LIKE', '%64%')
                        ->orWhere('os_kernel', 'LIKE', '%64%');
                  });
            })->count();

        $linux = (clone $reportingAssetsQuery)
            ->where(function ($q) {
                $q->where('operating_system', 'LIKE', '%Linux%')
                  ->orWhere('operating_system', 'LIKE', '%Ubuntu%')
                  ->orWhere('operating_system', 'LIKE', '%Debian%')
                  ->orWhere('operating_system', 'LIKE', '%Xubuntu%');
            })->count();

        $other = max(0, $totalGlpiAgents - ($win32 + $win64 + $linux));

        // Últimos 10 agentes en reportar
        $recentAgents = (clone $reportingAssetsQuery)
            ->orderBy('last_glpi_sync_at', 'desc')
            ->take(10)
            ->get(['id', 'asset_tag', 'name', 'ip_address', 'operating_system', 'last_glpi_sync_at'])
            ->map(fn($a) => [
                'id' => $a->id,
                'asset_tag' => $a->asset_tag,
                'name' => $a->name,
                'ip_address' => $a->ip_address,
                'operating_system' => $a->operating_system,
                'last_sync_at' => $a->last_glpi_sync_at ? $a->last_glpi_sync_at->toIso8601String() : null,
            ]);

        return [
            'total_inventory_assets' => $totalAssets,
            'glpi_installed_total' => $totalGlpiAgents,
            'glpi_reporting_24h' => $active24h,
            'glpi_offline_count' => $offline,
            'adoption_percentage' => $totalAssets > 0 ? round(($totalGlpiAgents / $totalAssets) * 100, 1) : 0.0,
            'breakdown_by_os' => [
                'windows_x86' => $win32,
                'windows_x64' => $win64,
                'linux_debian_ubuntu' => $linux,
                'other_or_unclassified' => $other,
            ],
            'recent_agents' => $recentAgents,
            'receiver_endpoint' => url('/api/v1/inventory/receiver/' . (\App\Models\BotSetting::get('glpi_receiver_token', 'd8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96'))),
            'token_tag' => 'ValleSeco-ITAM',
        ];
    }
}
