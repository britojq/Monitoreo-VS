<?php

declare(strict_types=1);

namespace App\Actions\Ssl;

use App\Models\AuditLog;
use App\Models\SslCertificate;
use App\Models\User;
use App\Services\System\SonarPathService;
use Illuminate\Support\Facades\Process;

class RecheckSslCertificateAction
{
    public function recheckSingle(int $id, ?User $user, ?string $ip, ?string $userAgent): SslCertificate
    {
        $cert = SslCertificate::findOrFail($id);

        $python = SonarPathService::pythonBinary();
        $script = SonarPathService::monitorScript('ssl_checker.py');
        Process::run("{$python} {$script} --check {$cert->domain} --port {$cert->port}");

        $cert->refresh();

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'rechecked',
            'module' => 'ssl',
            'auditable_type' => SslCertificate::class,
            'auditable_id' => $cert->id,
            'entity_name' => 'Certificado SSL',
            'entity_label' => "{$cert->domain}:{$cert->port}",
            'description' => "Certificado SSL para '{$cert->domain}' re-inspeccionado por " . ($user?->name ?? 'Usuario') . ". Estado: {$cert->last_check_status}",
            'new_values' => ['domain' => $cert->domain, 'status' => $cert->last_check_status],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $cert;
    }

    public function recheckAll(?User $user, ?string $ip, ?string $userAgent): void
    {
        $python = SonarPathService::pythonBinary();
        $script = SonarPathService::monitorScript('ssl_checker.py');
        Process::run("{$python} {$script} --check-all");

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'rechecked',
            'module' => 'ssl',
            'auditable_type' => SslCertificate::class,
            'auditable_id' => null,
            'entity_name' => 'Certificados SSL',
            'entity_label' => 'Inspección Masiva',
            'description' => "Todos los certificados SSL activos fueron re-inspeccionados por " . ($user?->name ?? 'Usuario'),
            'new_values' => ['action' => 'recheck_all'],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
