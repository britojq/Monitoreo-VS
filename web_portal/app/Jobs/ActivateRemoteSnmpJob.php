<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Snmp\ActivateRemoteSnmpAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ActivateRemoteSnmpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(
        public readonly array $validated,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}

    public function handle(ActivateRemoteSnmpAction $action): void
    {
        $targetIp = (string) ($this->validated['ip_address'] ?? 'unknown');
        $method = (string) ($this->validated['method'] ?? 'ssh');
        Log::info("Iniciando activación SNMP asíncrona ({$method}) para {$targetIp}...");

        $result = $action->execute(
            $this->validated,
            $this->ipAddress,
            $this->userAgent
        );

        if (($result['status_code'] ?? 200) === 200) {
            Log::info("Activación SNMP asíncrona exitosa para {$targetIp}.");
        } else {
            Log::error("Fallo en activación SNMP asíncrona para {$targetIp}: " . ($result['error'] ?? 'Desconocido'));
        }
    }
}
