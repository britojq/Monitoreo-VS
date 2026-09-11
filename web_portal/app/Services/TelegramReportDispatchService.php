<?php

namespace App\Services;

use App\Models\BotMessageTemplate;
use App\Models\MonitoringSnapshot;
use App\Models\TelegramDispatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramReportDispatchService
{
    protected TelegramNotificationService $telegramService;

    public function __construct(TelegramNotificationService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Compila y retorna el texto íntegro del reporte oficial para previsualización.
     */
    public function compileReportText(
        User $user,
        string $reportType = 'servicios',
        string $mode = 'instant',
        ?string $observations = ''
    ): string {
        $cleanObservations = trim((string) $observations);
        if ($mode === 'live') {
            return $this->compileLiveReport($reportType, $user, $cleanObservations);
        }
        return $this->compileSnapshotReport($reportType, $user, $cleanObservations);
    }

    /**
     * Envía un reporte oficial a Telegram firmado por el usuario en sesión.
     *
     * @param User $user Usuario autenticado firmante
     * @param string $reportType 'servicios' | 'sedes'
     * @param string $mode 'instant' (desde snapshot) | 'live' (escaneo físico)
     * @param string|null $ipAddress Dirección IP del operador
     * @param string $observations Observaciones operativas opcionales (antes de ATIT)
     * @param string $destination 'both' | 'group' | 'owner'
     * @param bool $includeScreenshot Adjuntar captura de pantalla HD generada vía Playwright
     * @return array ['success' => bool, 'message' => string, 'dispatch' => TelegramDispatch|null]
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
                'message' => 'Ficha incompleta. Debe registrar su Cédula, N° de Personal y Teléfono en su perfil antes de enviar reportes oficiales.',
                'dispatch' => null,
            ];
        }

        try {
            // Aumentar límite de tiempo para generación de captura gráfica Playwright
            @set_time_limit(75);

            // 2. Compilar el texto del reporte según el tipo y anexar observaciones si existen
            $cleanObservations = trim($observations);
            $reportText = '';
            if ($mode === 'live') {
                $reportText = $this->compileLiveReport($reportType, $user, $cleanObservations);
            } else {
                $reportText = $this->compileSnapshotReport($reportType, $user, $cleanObservations);
            }

            if (empty($reportText)) {
                return [
                    'success' => false,
                    'message' => 'No se pudo compilar el reporte. No se encontraron datos de monitoreo disponibles.',
                    'dispatch' => null,
                ];
            }

            // 3. Generar captura gráfica en HD vía Playwright si fue solicitada
            $screenshotPath = null;
            if ($includeScreenshot) {
                $screenshotPath = $this->generateScreenshot($reportType);
                if (!$screenshotPath) {
                    Log::warning("No se pudo generar la captura web para el reporte ({$reportType}). Se enviará solo texto.");
                }
            }

            // 4. Cargar credenciales y configuración del bot
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

            // 5. Determinar destinatarios según la opción seleccionada
            $targetChats = $this->resolveTargetChatIds($destination, $config);

            // DIRECTRIZ DE SEGURIDAD ESTRICTA (REGLA DE ORO #1):
            // Bajo ninguna circunstancia las pruebas automatizadas (Playwright, curl de prueba)
            // deben emitir mensajes al grupo corporativo (-1001383163558).
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

            // 6. Transmitir el reporte a los canales seleccionados
            $reportLabel = ($reportType === 'sedes') ? 'Sedes y Enlaces Regionales' : 'Servicios Corporativos';
            $successCount = 0;
            $failedChats = [];

            foreach ($targetChats as $chatId) {
                $msgSent = false;
                $photoSent = false;

                // A. Enviar primero el reporte textual completo en HTML
                $msgSent = $this->sendToTelegram($botToken, 'sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $reportText,
                    'parse_mode' => 'HTML',
                ], $proxies);

                // B. Si existe la captura de pantalla HD, transmitirla con pie de foto institucional
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

            $responseMsg = $overallSuccess
                ? "Reporte enviado exitosamente a {$destLabel}." . ($screenshotPath ? " Con captura HD adjunta." : "") . (!empty($cleanObservations) ? " Con observaciones." : "")
                : "Fallo en la entrega a Telegram (" . implode(', ', $failedChats) . "). Verifique conectividad y proxies.";

            // 7. Registrar en la tabla de auditoría institucional
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
                'message' => "Reporte oficial transmitido exitosamente a {$destLabel}.",
                'dispatch' => $dispatch,
                'has_screenshot' => !empty($screenshotPath),
                'has_observations' => !empty($cleanObservations),
                'destination_label' => $destLabel,
            ];
        } catch (\Throwable $e) {
            Log::error("Error en TelegramReportDispatchService: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [
                'success' => false,
                'message' => 'Excepción durante el envío: ' . $e->getMessage(),
                'dispatch' => null,
            ];
        }
    }

    /**
     * Carga configuración de tokens, proxies e identificadores desde config.json y bot.conf.
     */
    protected function loadBotConfig(): array
    {
        $jsonPath = '/scripts/telegram-admin-bot/config/config.json';
        $botConfPath = '/scripts/telegram-admin-bot/config/bot.conf';

        $token = null;
        $ownerId = '38914901';
        $groupId = '-1001383163558';
        $proxies = [];

        if (file_exists($jsonPath)) {
            $json = json_decode(@file_get_contents($jsonPath), true);
            if (is_array($json)) {
                $token = $json['bot_token'] ?? null;
                if (!empty($json['owner_id'])) {
                    $ownerId = (string)$json['owner_id'];
                }
                if (!empty($json['allowed_group_ids'][0])) {
                    $groupId = (string)$json['allowed_group_ids'][0];
                }
            }
        }

        if (file_exists($botConfPath)) {
            $confContent = @file_get_contents($botConfPath);
            if ($confContent) {
                $lines = explode("\n", $confContent);
                $conf = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && !str_starts_with($line, '#') && str_contains($line, '=')) {
                        [$k, $v] = explode('=', $line, 2);
                        $conf[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
                    }
                }

                if (empty($token) && !empty($conf['TOKENA'])) {
                    $token = $conf['TOKENA'];
                }
                if (!empty($conf['IDC'])) {
                    $ownerId = (string)$conf['IDC'];
                }
                if (!empty($conf['IDA']) && str_starts_with($conf['IDA'], '-')) {
                    $groupId = (string)$conf['IDA'];
                }

                foreach (['A', 'B', 'C', 'D'] as $letter) {
                    $ip = $conf["IPADDRPORTPROXY{$letter}"] ?? null;
                    $auth = $conf["USERPASSWDPROXY{$letter}"] ?? null;
                    if ($ip) {
                        if ($auth && str_contains($auth, ':')) {
                            [$u, $p] = explode(':', $auth, 2);
                            $proxies[] = 'http://' . urlencode($u) . ':' . urlencode($p) . '@' . $ip;
                        } else {
                            $proxies[] = 'http://' . $ip;
                        }
                    }
                }
            }
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
    protected function resolveTargetChatIds(string $destination, array $config): array
    {
        $ownerId = (string)($config['owner_id'] ?? '38914901');
        $groupId = (string)($config['group_id'] ?? '-1001383163558');

        return match ($destination) {
            'owner' => [$ownerId],
            'group' => [$groupId],
            'both' => array_values(array_unique([$groupId, $ownerId])),
            default => array_values(array_unique([$groupId, $ownerId])),
        };
    }

    /**
     * Genera una captura de pantalla en alta definición del dashboard mediante Playwright.
     */
    protected function generateScreenshot(string $reportType): ?string
    {
        $pythonExec = '/scripts/telegram-admin-bot/venv/bin/python';
        $script = '/scripts/telegram-admin-bot/monitor/web_screenshot.py';

        if (!file_exists($pythonExec) || !file_exists($script)) {
            return null;
        }

        $captureMode = ($reportType === 'sedes') ? 'sedes' : 'servicios';
        $cmd = 'export PYTHONPATH=/scripts/telegram-admin-bot && ' .
               escapeshellcmd($pythonExec) . ' -c ' . escapeshellarg(
            "import asyncio; from monitor.web_screenshot import capture_web_dashboard; " .
            "p = asyncio.run(capture_web_dashboard('{$captureMode}')); print(str(p) if p else '')"
        ) . ' 2>/dev/null';

        $output = trim(@shell_exec($cmd) ?? '');
        if (!empty($output) && file_exists($output) && filesize($output) > 1024) {
            return $output;
        }

        return null;
    }

    /**
     * Envío a la API de Telegram con soporte de multipart/form-data y failover a proxies.
     */
    protected function sendToTelegram(
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
            $req = Http::timeout(20);
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
                $req = Http::withOptions(['proxy' => $proxyUrl])->timeout(20);
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
     * Compila el reporte en formato oficial a partir del último snapshot en MariaDB.
     */
    protected function compileSnapshotReport(string $reportType, User $user, string $observations = ''): string
    {
        $snapshot = MonitoringSnapshot::latest()->first();
        if (!$snapshot || empty($snapshot->payload_json)) {
            return '';
        }

        $data = $snapshot->payload_json;
        $now = Carbon::now('America/Caracas');

        // Formato oficial de fecha y hora
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $diaSemana = $dias[$now->dayOfWeek];
        $mesNombre = $meses[$now->month];
        $fechaOficial = "{$diaSemana}, {$now->day} de {$mesNombre} de {$now->year}.";
        $horaOficial = $now->format('H:i');

        $firmaOperador = $this->formatOperatorSignature($user);

        if ($reportType === 'sedes') {
            return $this->buildSedesReportText($data, $fechaOficial, $horaOficial, $firmaOperador, $observations);
        } else {
            return $this->buildServicesReportText($data, $fechaOficial, $horaOficial, $firmaOperador, $observations);
        }
    }

    /**
     * Construye el texto del reporte de Servicios Corporativos
     */
    protected function buildServicesReportText(
        array $data,
        string $fecha,
        string $hora,
        string $firma,
        string $observations = ''
    ): string {
        $services = $data['services'] ?? [];
        $serviciosCorporativos = [];
        $serviciosRegionales = [];

        foreach ($services as $s) {
            $letter = strtoupper($s['letter'] ?? '');
            $name = $s['name'] ?? 'Servicio';
            $isUp = !empty($s['is_up']);
            $icon = $isUp ? '✅' : '❌';
            $line = "{$icon} - {$name}";

            $scope = strtolower($s['scope'] ?? '');
            $isRegional = ($scope === 'regional') || in_array($letter, ['L', 'M', 'N', 'O', 'Q', 'R', 'S', 'T']);
            if ($isRegional) {
                $serviciosRegionales[] = $line;
            } else {
                $serviciosCorporativos[] = $line;
            }
        }

        $linesCorp = implode("\n", $serviciosCorporativos);
        $linesReg = implode("\n", $serviciosRegionales);

        $tmpl = BotMessageTemplate::getByKey('servicios');

        $header = $tmpl && !empty($tmpl->header_text)
            ? $tmpl->header_text
            : "<b>GERENCIA DE ATIT REGIÓN CENTRAL</b>\n<b>DIVISIÓN DE ATIT CARABOBO</b>\n<b>DEPARTAMENTO DE  INFRAESTRUCTURA TECNOLÓGICA - SERVIDORES.</b>\n<b>Lugar:</b> Puerto Cabello - (Valle Seco)\n<b>Coordinación:</b> Infraestructura Tecnológica - Servidores.";

        $subHeader = $tmpl && !empty($tmpl->sub_header)
            ? $tmpl->sub_header
            : "<b>ESTATUS DE SERVICIOS CORPORATIVOS (CARABOBO - VALLE SECO)</b>";

        $legend = $tmpl && !empty($tmpl->legend_text)
            ? $tmpl->legend_text
            : "<b>Leyenda:</b>\n✅ Operativo.\n⚠️ Advertencia.\n❌ Fallas.";

        $impact = $tmpl && !empty($tmpl->impact_statement)
            ? $tmpl->impact_statement
            : "Impacto al SEN: Monitorear los servidores de la Región Central, permite detectar fallas a tiempo  que puedan ocasionar la imposibilidad de los servicios corporativos que son parte del SEN.";

        $slogan = $tmpl && !empty($tmpl->slogan)
            ? $tmpl->slogan
            : "<b>⚡️ATIT Somos la Voz, Comando y Control de SEN, Nadie se Cansa ⚡️</b>";

        // Bloque de observaciones antes de la firma institucional
        $obsBlock = !empty($observations)
            ? "\n\n━━━━━━━━━━━━\n📝 <b>OBSERVACIONES:</b>\n" . htmlspecialchars($observations) . "\n━━━━━━━━━━━━"
            : "";

        return "{$header}\n" .
               "<b>Fecha:</b> {$fecha}\n" .
               "<b>Hora:</b> {$hora}.\n\n" .
               "{$subHeader}\n\n" .
               "{$legend}\n\n" .
               "━━━━━━━━━━━━\n" .
               "<b>Servicios Corporativos Verificados:</b>\n" .
               "━━━━━━━━━━━━\n" .
               "{$linesCorp}\n\n" .
               "━━━━━━━━━━━━\n" .
               "<b>Servicios Regionales – Carabobo Verificados:</b>\n" .
               "━━━━━━━━━━━━\n" .
               "{$linesReg}\n\n" .
               "{$impact}" .
               "{$obsBlock}\n\n" .
               "{$firma}\n\n" .
               "{$slogan}";
    }

    /**
     * Construye el texto del reporte de Sedes y Enlaces
     */
    protected function buildSedesReportText(
        array $data,
        string $fecha,
        string $hora,
        string $firma,
        string $observations = ''
    ): string {
        $sites = $data['sites'] ?? [];
        $sitesBlocks = [];

        foreach ($sites as $site) {
            $siteName = $site['name'] ?? 'Sede';
            $isUp = !empty($site['is_up']);
            $icon = $isUp ? '✅' : '❌';
            $block = "━━━━━━━━━━━━\n<b>{$siteName}</b>\n━━━━━━━━━━━━\n{$icon} - {$siteName}";

            if (!empty($site['devices']) && is_array($site['devices'])) {
                foreach ($site['devices'] as $dev) {
                    $devName = $dev['name'] ?? 'Dispositivo';
                    $devUp = !empty($dev['is_up']);
                    $devIcon = $devUp ? '✅' : '❌';
                    $block .= "\n{$devIcon} - {$devName}";
                }
            }
            $sitesBlocks[] = $block;
        }

        $allSitesText = implode("\n\n", $sitesBlocks);

        $tmpl = BotMessageTemplate::getByKey('sedes');

        $header = $tmpl && !empty($tmpl->header_text)
            ? $tmpl->header_text
            : "<b>GERENCIA DE ATIT REGIÓN CENTRAL</b>\n<b>DIVISIÓN DE ATIT CARABOBO</b>\n<b>DEPARTAMENTO DE  INFRAESTRUCTURA TECNOLÓGICA</b>\n<b>Lugar:</b> Puerto Cabello - (Valle Seco)\n<b>Coordinación:</b> Infraestructura Tecnológica - Servidores.";

        $subHeader = $tmpl && !empty($tmpl->sub_header)
            ? $tmpl->sub_header
            : "<b>ESTATUS DE CIAU EJE COSTERO</b>";

        $legend = $tmpl && !empty($tmpl->legend_text)
            ? $tmpl->legend_text
            : "<b>Leyenda:</b>\n✅ Operativo.\n⚠️ Advertencia.\n❌ Fallas.";

        $impact = $tmpl && !empty($tmpl->impact_statement)
            ? $tmpl->impact_statement
            : "Impacto al SEN: Monitorear los servidores de la Región Central, permite detectar fallas a tiempo  que puedan ocasionar la imposibilidad de los servicios corporativos que son parte del SEN.";

        $slogan = $tmpl && !empty($tmpl->slogan)
            ? $tmpl->slogan
            : "<b>⚡️ATIT Somos la Voz, Comando y Control de SEN, Nadie se Cansa ⚡️</b>";

        // Bloque de observaciones antes de la firma institucional
        $obsBlock = !empty($observations)
            ? "\n\n━━━━━━━━━━━━\n📝 <b>OBSERVACIONES:</b>\n" . htmlspecialchars($observations) . "\n━━━━━━━━━━━━"
            : "";

        return "{$header}\n" .
               "<b>Fecha:</b> {$fecha}\n" .
               "<b>Hora:</b> {$hora}.\n\n" .
               "{$subHeader}\n\n" .
               "{$legend}\n\n" .
               "{$allSitesText}\n\n" .
               "{$impact}" .
               "{$obsBlock}\n\n" .
               "{$firma}\n\n" .
               "{$slogan}";
    }

    /**
     * Compila un escaneo físico en caliente mediante el script Python
     */
    protected function compileLiveReport(string $reportType, User $user, string $observations = ''): string
    {
        $scriptPath = '/scripts/telegram-admin-bot/monitor/monitor_engine.py';
        $pythonExec = '/scripts/telegram-admin-bot/venv/bin/python';

        if (!file_exists($scriptPath) || !file_exists($pythonExec)) {
            return $this->compileSnapshotReport($reportType, $user, $observations);
        }

        // Ejecutar monitor_engine.py en modo no-send y extraer salida
        $cmd = escapeshellcmd($pythonExec) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($reportType) . ' --no-send';
        $output = @shell_exec($cmd);

        if (empty($output)) {
            return $this->compileSnapshotReport($reportType, $user, $observations);
        }

        // Reemplazar la firma por defecto con la firma del usuario
        $firmaOperador = $this->formatOperatorSignature($user);
        $obsBlock = !empty($observations)
            ? "━━━━━━━━━━━━\n📝 <b>OBSERVACIONES:</b>\n" . htmlspecialchars($observations) . "\n━━━━━━━━━━━━\n\n"
            : "";

        $pattern = '/\*\*Personal de\s+ATIT:\*\*.*?(?=\*\*⚡️ATIT)/s';
        if (preg_match($pattern, $output)) {
            $output = preg_replace($pattern, "{$obsBlock}{$firmaOperador}\n\n", $output);
        } else {
            // Intentar con etiquetas HTML
            $htmlPattern = '/<b>Personal de\s+ATIT:<\/b>.*?(?=<b>⚡️ATIT)/s';
            if (preg_match($htmlPattern, $output)) {
                $output = preg_replace($htmlPattern, "{$obsBlock}{$firmaOperador}\n\n", $output);
            }
        }

        return $output;
    }

    /**
     * Formatea la firma institucional del operador con salto de línea en 📱Tlf
     */
    protected function formatOperatorSignature(User $user): string
    {
        $name = $user->full_title_name;
        $ci = $user->cedula ?? '';
        $personal = $user->personal_number ?? '';
        $phone = $user->phone ?? '';

        return "👨‍💻 <b>Personal de  ATIT:</b>\n" .
               htmlspecialchars($name) . "\n" .
               "C.I: " . htmlspecialchars($ci) . "\n" .
               "N° Personal: " . htmlspecialchars($personal) . "\n" .
               "📱Tlf: " . htmlspecialchars($phone);
    }
}
