<?php

namespace App\Services\Cluster;

use App\Contracts\ClusterAuditLoggerInterface;
use App\Models\AuditLog;
use App\Services\ClusterConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClusterAuditLoggerService implements ClusterAuditLoggerInterface
{
    protected ClusterConfigService $clusterConfig;

    public function __construct(ClusterConfigService $clusterConfig)
    {
        $this->clusterConfig = $clusterConfig;
    }

    /**
     * Registra un evento de acceso a la API de Clúster en audit_logs de forma inmutable.
     */
    public function logAccess(Request $request, string $event, string $description, array $details = []): void
    {
        try {
            $nodeRole = $this->clusterConfig->getNodeRole();
            $ip = $request->ip() ?: '127.0.0.1';
            $ua = $request->userAgent() ?: 'ClusterAPI/Client';

            AuditLog::create([
                'user_id' => null,
                'user_name' => 'Cluster API (Nodo ' . strtoupper($nodeRole) . ')',
                'user_email' => 'cluster@monitoreo.vs',
                'user_role' => 'system',
                'event' => $event,
                'module' => 'Cluster API',
                'auditable_type' => 'ClusterNode',
                'auditable_id' => null,
                'entity_name' => gethostname(),
                'entity_label' => 'Nodo ' . strtoupper($nodeRole),
                'description' => $description,
                'old_values' => null,
                'new_values' => $details,
                'changed_fields' => null,
                'ip_address' => $ip,
                'user_agent' => substr($ua, 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Registrar en log de Laravel si ocurre algún error en la base de datos sin romper la petición
            Log::error('Fallo al registrar auditoría de clúster: ' . $e->getMessage());
        }
    }
}
