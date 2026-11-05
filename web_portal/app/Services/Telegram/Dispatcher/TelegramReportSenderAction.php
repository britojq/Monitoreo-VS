<?php

declare(strict_types=1);

namespace App\Services\Telegram\Dispatcher;

use App\Models\BotSetting;
use App\Models\MonitoredProxy;
use App\Models\TelegramDispatch;
use App\Models\User;
use App\Services\System\SonarPathService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Acción de despacho y transmisión física de reportes hacia Telegram con failover de proxies.
 */
class TelegramReportSenderAction
{
    /**
     * Carga configuración de tokens, proxies e identificadores directamente desde MariaDB (SSOT).
     */
    public function loadBotConfig(): array
    {
        try {
            $token = app(\App\Contracts\Services\BotTokenResolverInterface::class)->resolve();
        } catch (\Throwable) {
            $token = \App\Services\Security\HardwareVaultService::resolveBotToken();
        }

        $ownerId = (string) (BotSetting::get('owner_id') ?: '38914901');
        $allowedGroups = BotSetting::get('allowed_group_ids');
        $groupId = (!empty($allowedGroups) && is_array($allowedGroups)) ? (string)$allowedGroups[0] : '-1001383163558';
        $proxies = [];

        try {
            $dbProxies = MonitoredProxy::where('is_active', true)->orderBy('letter')->get();
            foreach ($dbProxies as $p) {
                $ip = trim($p->ip_port ?? '');
                $auth = trim($p->auth_userpass ?? '');
                if (!empty($ip)) {
                    if (!empty($auth) && str_contains($auth, ':') && $auth !== 'USUARIO:CLAVE') {
                        [$u, $pwd] = explode(':', $auth, 2);
                        $proxies[] = 'http://' . urlencode($u) . ':' . urlencode($pwd) . '@' . $ip;
                    } else {
                        $proxies[] = 'http://' . $ip;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug("No se pudieron cargar proxies de MariaDB en TelegramReportSenderAction: " . $e->getMessage());
        }

        return [
            'bot_token' => $token,
            'owner_id' => $ownerId,
            'group_id' => $groupId,
            'proxies' => $proxies,
        ];
    }

    /**
     * Resuelve los identificadores de chat de destino según la opción elegida.
     */
    public function resolveTargetChatIds(string $destination, array $config): array
    {
        $ownerId = (string)($config['owner_id'] ?? '38914901');
        $groupId = (string)($config['group_id'] ?? '-1001383163558');

        return match ($destination) {
            'owner' => [$ownerId],
            'group' => [$groupId],
            default => array_values(array_unique([$groupId, $ownerId])),
        };
    }

    /**
     * Genera una captura de pantalla en alta definición del dashboard mediante Playwright.
     */
    public function generateScreenshot(string $reportType): ?string
    {
        $pythonExec = SonarPathService::pythonBinary();
        $script = SonarPathService::monitorScript('web_screenshot.py');
        $baseDir = SonarPathService::baseDir();

        if (!file_exists($pythonExec) || !file_exists($script)) {
            Log::error("generateScreenshot: pythonExec ({$pythonExec}) o script ({$script}) no existen.");
            return null;
        }

        $captureMode = ($reportType === 'sedes') ? 'sedes' : 'servicios';
        $pythonCode = "import asyncio; from monitor.web_screenshot import capture_web_dashboard; " .
                      "p = asyncio.run(capture_web_dashboard('{$captureMode}')); print(str(p) if p else '')";

        // ARC-09: Erradicar @shell_exec y ejecutar mediante Process con variables de entorno y timeout
        $processResult = Process::timeout(60)
            ->env([
                'HOME' => '/var/www',
                'PYTHONPATH' => $baseDir,
                'PLAYWRIGHT_BROWSERS_PATH' => '/var/www/.cache/ms-playwright',
            ])
            ->run([
                $pythonExec,
                '-c',
                $pythonCode,
            ]);

        $rawOutput = trim($processResult->output() . "\n" . $processResult->errorOutput());
        $lines = explode("\n", trim($processResult->output()));
        $output = trim(end($lines));

        if (!empty($output) && file_exists($output) && filesize($output) > 1024) {
            return $output;
        }

        Log::warning("No se pudo generar la captura web para el reporte ({$reportType}). Detalle: {$rawOutput}");
        return null;
    }

    /**
     * Envío a la API de Telegram con soporte de multipart/form-data y failover a proxies.
     */
    public function sendToTelegram(
        string $token,
        string $method,
        array $payload,
        array $proxies,
        ?string $filePath = null,
        string $fileField = 'photo'
    ): bool {
        $url = "https://api.telegram.org/bot{$token}/{$method}";

        // 1. Conexión directa
        try {
            $req = Http::timeout(10);
            if ($filePath && file_exists($filePath)) {
                $req = $req->attach($fileField, file_get_contents($filePath), basename($filePath));
                $response = $req->post($url, $payload);
            } else {
                $response = $req->asForm()->post($url, $payload);
            }

            if ($response->successful() && ($response->json('ok') === true)) {
                return true;
            }
            Log::debug("Telegram direct {$method} non-ok response: " . $response->body());
        } catch (\Throwable $e) {
            Log::debug("Telegram direct {$method} error: " . $e->getMessage());
        }

        // 2. Conexión a través de proxies configurados
        foreach ($proxies as $proxyUrl) {
            try {
                $req = Http::withOptions(['proxy' => $proxyUrl])->timeout(10);
                if ($filePath && file_exists($filePath)) {
                    $req = $req->attach($fileField, file_get_contents($filePath), basename($filePath));
                    $response = $req->post($url, $payload);
                } else {
                    $response = $req->asForm()->post($url, $payload);
                }

                if ($response->successful() && ($response->json('ok') === true)) {
                    return true;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return false;
    }

    /**
     * Ejecuta el despacho del reporte hacia los canales y registra la auditoría.
     */
    public function execute(
        User $user,
        string $reportType,
        string $mode,
        ?string $ipAddress,
        string $observations,
        string $destination,
        bool $includeScreenshot,
        string $reportText
    ): array {
        $screenshotPath = null;
        if ($includeScreenshot) {
            $screenshotPath = $this->generateScreenshot($reportType);
            if (!$screenshotPath) {
                Log::warning("No se pudo generar la captura web para el reporte ({$reportType}). Se enviará solo texto.");
            }
        }

        $config = $this->loadBotConfig();
        $botToken = $config['bot_token'] ?? null;
        $proxies = $config['proxies'] ?? [];

        if (empty($botToken)) {
            return [
                'success' => false,
                'message' => 'No se encontró configurado el token del bot de Telegram en el servidor.',
                'dispatch' => null,
            ];
        }

        $targetChats = $this->resolveTargetChatIds($destination, $config);

        // DIRECTRIZ DE SEGURIDAD ESTRICTA (REGLA DE ORO #1)
        $isAutomatedTest = request()->hasHeader('X-Automated-Test') || request()->hasHeader('X-Playwright-Test');
        if ($isAutomatedTest) {
            $targetChats = array_filter($targetChats, fn($cid) => !str_starts_with((string)$cid, '-'));
            Log::info("Envío de reporte en modo prueba automatizada: destinos de grupo descartados.");
        }

        if (empty($targetChats)) {
            return [
                'success' => false,
                'message' => 'No se definieron destinatarios válidos para la transmisión del reporte.',
                'dispatch' => null,
            ];
        }

        $reportLabel = ($reportType === 'sedes') ? 'Sedes y Enlaces Regionales' : 'Servicios Corporativos';
        $successCount = 0;
        $failedChats = [];

        foreach ($targetChats as $chatId) {
            $msgSent = $this->sendToTelegram($botToken, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => $reportText,
                'parse_mode' => 'HTML',
            ], $proxies);

            $photoSent = false;
            if ($screenshotPath && file_exists($screenshotPath)) {
                $caption = "📸 <b>Captura en Tiempo Real</b>\n" .
                           "🏢 <b>SISTEMA DE MONITOREO VALLE SECO</b>\n" .
                           "📌 <i>{$reportLabel}</i>";

                $photoSent = $this->sendToTelegram($botToken, 'sendPhoto', [
                    'chat_id' => $chatId,
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                ], $proxies, $screenshotPath, 'photo');
            }

            if ($msgSent || $photoSent) {
                $successCount++;
            } else {
                $failedChats[] = $chatId;
            }
        }

        $overallSuccess = ($successCount > 0);
        $destLabel = match ($destination) {
            'group' => 'Grupo Corporativo',
            'owner' => 'Administrador / Privado',
            default => 'Grupo Corporativo y Administrador',
        };

        $cleanObservations = trim($observations);
        $responseMsg = $overallSuccess
            ? "Reporte enviado exitosamente a {$destLabel}." . ($screenshotPath ? " Con captura HD adjunta." : "") . (!empty($cleanObservations) ? " Con observaciones." : "")
            : "Fallo en la entrega a Telegram (" . implode(', ', $failedChats) . "). Verifique conectividad y proxies.";

        $dispatch = TelegramDispatch::create([
            'user_id' => $user->id,
            'report_type' => $reportType,
            'operator_name' => $user->full_title_name,
            'operator_ci' => $user->cedula,
            'operator_personal_number' => $user->personal_number,
            'operator_phone' => $user->phone,
            'status' => $overallSuccess ? 'success' : 'failed',
            'response_message' => $responseMsg,
            'ip_address' => $ipAddress,
        ]);

        if (!$overallSuccess) {
            return [
                'success' => false,
                'message' => 'No se pudo enviar el reporte por Telegram. Verifique la conectividad del servidor y los proxies.',
                'dispatch' => $dispatch,
            ];
        }

        return [
            'success' => true,
            'message' => "Reporte transmitido exitosamente a {$destLabel}.",
            'dispatch' => $dispatch,
            'has_screenshot' => !empty($screenshotPath),
            'has_observations' => !empty($cleanObservations),
            'destination_label' => $destLabel,
        ];
    }
}
