<?php

namespace App\Services;

use App\Models\BotMessageTemplate;
use App\Models\MonitoringSnapshot;
use App\Models\TelegramDispatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TelegramReportDispatchService
{
    protected TelegramNotificationService $telegramService;

    public function __construct(TelegramNotificationService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Despacha un reporte oficial a Telegram firmado por el usuario en sesión.
     *
     * @param User $user Usuario autenticado firmante
     * @param string $reportType 'servicios' | 'sedes' | 'completo'
     * @param string $mode 'instant' (desde snapshot) | 'live' (escaneo físico)
     * @param string|null $ipAddress Dirección IP del operador
     * @return array ['success' => bool, 'message' => string, 'dispatch' => TelegramDispatch|null]
     */
    public function dispatch(User $user, string $reportType = 'servicios', string $mode = 'instant', ?string $ipAddress = null): array
    {
        // 1. Validar que el usuario tenga su ficha completa
        if (!$user->hasCompleteAtitProfile()) {
            return [
                'success' => false,
                'message' => 'Ficha incompleta. Debe registrar su Cédula, N° de Personal y Teléfono en su perfil antes de despachar reportes oficiales.',
                'dispatch' => null,
            ];
        }

        try {
            // 2. Compilar el texto del reporte según el tipo
            $reportText = '';
            if ($mode === 'live') {
                $reportText = $this->compileLiveReport($reportType, $user);
            } else {
                $reportText = $this->compileSnapshotReport($reportType, $user);
            }

            if (empty($reportText)) {
                return [
                    'success' => false,
                    'message' => 'No se pudo compilar el reporte. No se encontraron datos de monitoreo disponibles.',
                    'dispatch' => null,
                ];
            }

            // 3. Enviar a Telegram mediante el despachador protegido
            // DIRECTRIZ DE SEGURIDAD ESTRICTA (REGLA DE ORO #1):
            // Todo acceso proveniente de 127.0.0.1 o ::1 (pruebas locales/Playwright) debe
            // bloquear el despacho externo a Telegram y limitarse al registro local de auditoría.
            $isLoopback = in_array($ipAddress, ['127.0.0.1', '::1']);
            $sent = false;

            if ($isLoopback) {
                $sent = true;
                $responseMsg = 'Reporte auditado en entorno de desarrollo/local (127.0.0.1). Despacho externo a Telegram bloqueado por seguridad.';
            } else {
                $sent = $this->telegramService->sendMessage($reportText, 'HTML');
                $responseMsg = $sent ? 'Despachado exitosamente a Telegram.' : 'Error al despachar mensaje a través del API de Telegram.';
            }

            // 4. Registrar en la tabla de auditoría
            $dispatch = TelegramDispatch::create([
                'user_id' => $user->id,
                'report_type' => $reportType,
                'operator_name' => $user->full_title_name,
                'operator_ci' => $user->cedula,
                'operator_personal_number' => $user->personal_number,
                'operator_phone' => $user->phone,
                'status' => $sent ? 'success' : 'failed',
                'response_message' => $responseMsg,
                'ip_address' => $ipAddress,
            ]);

            if (!$sent) {
                return [
                    'success' => false,
                    'message' => 'No se pudo despachar el mensaje por Telegram. Verifique la conectividad y tokens del bot.',
                    'dispatch' => $dispatch,
                ];
            }

            return [
                'success' => true,
                'message' => 'Reporte oficial despachado exitosamente a Telegram.',
                'dispatch' => $dispatch,
            ];
        } catch (\Throwable $e) {
            Log::error("Error en TelegramReportDispatchService: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [
                'success' => false,
                'message' => 'Excepción durante el despacho: ' . $e->getMessage(),
                'dispatch' => null,
            ];
        }
    }

    /**
     * Compila el reporte en formato oficial a partir del último snapshot en MariaDB.
     */
    protected function compileSnapshotReport(string $reportType, User $user): string
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
            return $this->buildSedesReportText($data, $fechaOficial, $horaOficial, $firmaOperador);
        } else {
            return $this->buildServicesReportText($data, $fechaOficial, $horaOficial, $firmaOperador);
        }
    }

    /**
     * Construye el texto del reporte de Servicios Corporativos
     */
    protected function buildServicesReportText(array $data, string $fecha, string $hora, string $firma): string
    {
        $services = $data['services'] ?? [];
        $serviciosCorporativos = [];
        $serviciosRegionales = [];

        foreach ($services as $s) {
            $letter = strtoupper($s['letter'] ?? '');
            $name = $s['name'] ?? 'Servicio';
            $isUp = !empty($s['is_up']);
            $icon = $isUp ? '✅' : '❌';
            $line = "{$icon} - {$name}";

            // Usar scope (regional o corporativo) con fallback retrocompatible
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
               "{$impact}\n\n" .
               "{$firma}\n\n" .
               "{$slogan}";
    }

    /**
     * Construye el texto del reporte de Sedes y Enlaces
     */
    protected function buildSedesReportText(array $data, string $fecha, string $hora, string $firma): string
    {
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

        return "{$header}\n" .
               "<b>Fecha:</b> {$fecha}\n" .
               "<b>Hora:</b> {$hora}.\n\n" .
               "{$subHeader}\n\n" .
               "{$legend}\n\n" .
               "{$allSitesText}\n\n" .
               "{$impact}\n\n" .
               "{$firma}\n\n" .
               "{$slogan}";
    }

    /**
     * Compila un escaneo físico en caliente mediante el script Python
     */
    protected function compileLiveReport(string $reportType, User $user): string
    {
        $scriptPath = '/scripts/telegram-admin-bot/monitor/monitor_engine.py';
        $pythonExec = '/scripts/telegram-admin-bot/venv/bin/python';

        if (!file_exists($scriptPath) || !file_exists($pythonExec)) {
            return $this->compileSnapshotReport($reportType, $user);
        }

        // Ejecutar monitor_engine.py en modo no-send y extraer salida
        $cmd = escapeshellcmd($pythonExec) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($reportType) . ' --no-send';
        $output = @shell_exec($cmd);

        if (empty($output)) {
            return $this->compileSnapshotReport($reportType, $user);
        }

        // Reemplazar la firma por defecto con la firma del usuario
        $firmaOperador = $this->formatOperatorSignature($user);
        $pattern = '/\*\*Personal de\s+ATIT:\*\*.*?(?=\*\*⚡️ATIT)/s';
        if (preg_match($pattern, $output)) {
            $output = preg_replace($pattern, "{$firmaOperador}\n\n", $output);
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

        return "<b>Personal de  ATIT:</b>\n" .
               htmlspecialchars($name) . "\n" .
               "C.I: " . htmlspecialchars($ci) . "\n" .
               "N° Personal: " . htmlspecialchars($personal) . "\n" .
               "📱Tlf: " . htmlspecialchars($phone);
    }
}
