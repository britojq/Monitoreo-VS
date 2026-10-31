<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\Telegram\Compiler\NocTelegramReportCompiler;
use App\Services\Telegram\Dispatcher\TelegramReportSenderAction;
use Illuminate\Support\Facades\Log;

/**
 * Servicio coordinador y fachada delgada para la compilación y despacho
 * de reportes oficiales de Telegram del NOC.
 */
class TelegramReportDispatchService
{
    public function __construct(
        protected TelegramNotificationService $telegramService,
        protected NocTelegramReportCompiler $compiler,
        protected TelegramReportSenderAction $senderAction
    ) {}

    /**
     * Compila y retorna el texto íntegro del reporte oficial para previsualización.
     */
    public function compileReportText(
        User $user,
        string $reportType = 'servicios',
        string $mode = 'instant',
        ?string $observations = ''
    ): string {
        return $this->compiler->compileReportText($user, $reportType, $mode, $observations);
    }

    /**
     * Envía un reporte oficial a Telegram firmado por el usuario en sesión.
     */
    public function dispatch(
        User $user,
        string $reportType = 'servicios',
        string $mode = 'instant',
        ?string $ipAddress = null,
        string $observations = '',
        string $destination = 'both',
        bool $includeScreenshot = true
    ): array {
        // 1. Validar que el usuario tenga su ficha completa
        if (!$user->hasCompleteAtitProfile()) {
            return [
                'success' => false,
                'message' => 'Ficha incompleta. Debe registrar su Cédula, N° de Personal y Teléfono en su perfil antes de enviar reportes.',
                'dispatch' => null,
            ];
        }

        try {
            @set_time_limit(75);

            // 2. Compilar el reporte
            $reportText = $this->compiler->compileReportText($user, $reportType, $mode, $observations);

            if (empty($reportText)) {
                return [
                    'success' => false,
                    'message' => 'No se pudo compilar el reporte. No se encontraron datos de monitoreo disponibles.',
                    'dispatch' => null,
                ];
            }

            // 3. Ejecutar la acción de despacho físico
            return $this->senderAction->execute(
                $user,
                $reportType,
                $mode,
                $ipAddress,
                $observations,
                $destination,
                $includeScreenshot,
                $reportText
            );
        } catch (\Throwable $e) {
            Log::error("Error en TelegramReportDispatchService: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [
                'success' => false,
                'message' => 'Excepción durante el envío: ' . $e->getMessage(),
                'dispatch' => null,
            ];
        }
    }
}
