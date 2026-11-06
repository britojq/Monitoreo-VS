<?php

declare(strict_types=1);

namespace App\Actions\NetRadar;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\System\SonarPathService;

class TriggerNetRadarScanAction
{
    public function execute(?User $user, ?string $ip, ?string $userAgent): array
    {
        $pythonBin = SonarPathService::pythonBinary();
        $scriptPath = SonarPathService::monitorScript('net_radar_engine.py');

        if (file_exists($pythonBin) && file_exists($scriptPath)) {
            // ARC-07: Redirección limpia a /dev/null para evitar fallos de permisos en /tmp/monitor y cumplir Regla #6 (Cero archivos planos)
            $cmd = sprintf(
                'sudo %s %s --once --cycle 10 > /dev/null 2>&1 &',
                escapeshellarg($pythonBin),
                escapeshellarg($scriptPath)
            );
            exec($cmd);

            if ($user) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                    'event' => 'scanned',
                    'module' => 'netradar',
                    'auditable_type' => 'NetRadar',
                    'auditable_id' => 0,
                    'entity_name' => 'NET Radar',
                    'entity_label' => 'Barrido en Vivo',
                    'description' => "El usuario {$user->name} disparó una captura manual de tráfico en NET Radar.",
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Captura y análisis de NET Radar iniciado en segundo plano. Los resultados se actualizarán automáticamente.'
            ];
        }

        return [
            'success' => false,
            'message' => 'El motor NET Radar no está disponible en la ruta especificada.'
        ];
    }
}
