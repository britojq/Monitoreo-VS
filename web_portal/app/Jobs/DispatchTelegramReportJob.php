<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\TelegramReportDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchTelegramReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 2;

    public function __construct(
        public readonly int $userId,
        public readonly string $reportType = 'servicios',
        public readonly string $mode = 'instant',
        public readonly ?string $ipAddress = null,
        public readonly string $observations = '',
        public readonly string $destination = 'both',
        public readonly bool $includeScreenshot = true,
    ) {}

    public function handle(TelegramReportDispatchService $dispatchService): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::error("DispatchTelegramReportJob: Usuario ID {$this->userId} no encontrado en base de datos.");
            return;
        }

        Log::info("Iniciando despacho asíncrono de reporte Telegram ({$this->reportType}) para operador {$user->name}...");

        $result = $dispatchService->dispatch(
            $user,
            $this->reportType,
            $this->mode,
            $this->ipAddress,
            $this->observations,
            $this->destination,
            $this->includeScreenshot
        );

        if ($result['success'] ?? false) {
            Log::info("DispatchTelegramReportJob: Reporte ({$this->reportType}) despachado exitosamente.");
        } else {
            Log::error("DispatchTelegramReportJob: Fallo despachando reporte ({$this->reportType}): " . ($result['message'] ?? 'Desconocido'));
        }
    }
}
