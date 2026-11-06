<?php

namespace App\Contracts;

use Illuminate\Http\Request;

interface ClusterAuditLoggerInterface
{
    /**
     * Registra un evento de acceso a la API de Clúster en audit_logs de forma inmutable.
     *
     * @param Request $request
     * @param string $event
     * @param string $description
     * @param array $details
     * @return void
     */
    public function logAccess(Request $request, string $event, string $description, array $details = []): void;
}
