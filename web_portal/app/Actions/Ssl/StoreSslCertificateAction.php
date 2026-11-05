<?php

declare(strict_types=1);

namespace App\Actions\Ssl;

use App\Models\AuditLog;
use App\Models\SslCertificate;
use App\Models\User;
use App\Services\System\SonarPathService;
use Illuminate\Support\Facades\Process;

class StoreSslCertificateAction
{
    public function execute(array $validated, ?User $user, ?string $ip, ?string $userAgent): SslCertificate
    {
        $cert = SslCertificate::create([
            'domain' => strtolower(trim($validated['domain'])),
            'port' => $validated['port'] ?? 443,
            'service_id' => $validated['service_id'] ?? null,
            'alert_threshold_warning' => $validated['alert_threshold_warning'] ?? 30,
            'alert_threshold_critical' => $validated['alert_threshold_critical'] ?? 7,
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        $python = SonarPathService::pythonBinary();
        $script = SonarPathService::monitorScript('ssl_checker.py');

        // F-SEC-01: Ejecución estructurada mediante argv array. CERO concatenación shell.
        Process::run([
            $python,
            $script,
            '--check',
            $cert->domain,
            '--port',
            (string) $cert->port,
        ]);

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Administrador',
            'user_email' => $user?->email,
            'user_role' => $user?->role ?? 'admin',
            'event' => 'created',
            'module' => 'ssl',
            'auditable_type' => SslCertificate::class,
            'auditable_id' => $cert->id,
            'entity_name' => 'Certificado SSL',
            'entity_label' => "{$cert->domain}:{$cert->port}",
            'description' => "Certificado SSL registrado para '{$cert->domain}:{$cert->port}' por " . ($user?->name ?? 'Usuario'),
            'new_values' => ['domain' => $cert->domain, 'port' => $cert->port],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $cert;
    }
}
