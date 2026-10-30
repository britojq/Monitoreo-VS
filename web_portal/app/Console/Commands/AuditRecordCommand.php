<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class AuditRecordCommand extends Command
{
    /**
     * Nombre y firma del comando en Artisan.
     *
     * @var string
     */
    protected $signature = 'audit:record
                            {--event= : Nombre del evento de auditoría (ej: DEPLOY_SUCCESS)}
                            {--module=system : Módulo al que pertenece el evento (ej: system, cluster, bot)}
                            {--description= : Descripción detallada del evento}
                            {--user=Administrador : Nombre o identidad del ejecutor}
                            {--payload= : JSON opcional con valores o metadatos adicionales}';

    /**
     * Descripción del comando en Artisan.
     *
     * @var string
     */
    protected $description = 'Registra un evento de auditoría estructurado directamente en MariaDB SSOT (audit_logs)';

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $event = (string) ($this->option('event') ?? 'SYSTEM_EVENT');
        $module = (string) ($this->option('module') ?? 'system');
        $description = (string) ($this->option('description') ?? 'Evento de sistema registrado vía CLI');
        $user = (string) ($this->option('user') ?? 'Administrador');
        $payloadRaw = $this->option('payload');

        $payload = [];
        if (!empty($payloadRaw)) {
            $decoded = json_decode((string) $payloadRaw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        try {
            AuditLog::create([
                'user_id' => null,
                'user_name' => $user,
                'user_email' => 'admin@valleseco.local',
                'user_role' => 'system_admin',
                'event' => $event,
                'module' => $module,
                'entity_name' => 'SystemDeployment',
                'entity_label' => $event,
                'description' => $description,
                'new_values' => !empty($payload) ? $payload : null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'CLI / Artisan audit:record',
            ]);

            $this->info("✓ Evento de auditoría '{$event}' registrado exitosamente en MariaDB.");
            return 0;
        } catch (\Throwable $e) {
            $this->error("❌ Error al registrar evento de auditoría: " . $e->getMessage());
            return 1;
        }
    }
}
