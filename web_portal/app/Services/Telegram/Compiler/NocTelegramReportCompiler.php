<?php

declare(strict_types=1);

namespace App\Services\Telegram\Compiler;

use App\Models\BotMessageTemplate;
use App\Models\MonitoringSnapshot;
use App\Models\User;
use App\Services\System\SonarPathService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Process;

/**
 * Compilador de textos y plantillas de reportes oficiales de Telegram para el NOC.
 */
class NocTelegramReportCompiler
{
    /**
     * Compila y retorna el texto íntegro del reporte oficial para previsualización o despacho.
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
     * Compila el reporte en formato oficial a partir del último snapshot en MariaDB.
     */
    public function compileSnapshotReport(string $reportType, User $user, string $observations = ''): string
    {
        $snapshot = MonitoringSnapshot::latest()->first();
        if (!$snapshot || empty($snapshot->payload_json)) {
            return '';
        }

        $data = $snapshot->payload_json;
        $now = Carbon::now('America/Caracas');

        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $diaSemana = $dias[$now->dayOfWeek];
        $mesNombre = $meses[$now->month];
        $fechaOficial = "{$diaSemana}, {$now->day} de {$mesNombre} de {$now->year}.";
        $horaOficial = $now->format('H:i');

        $firmaOperador = $this->formatOperatorSignature($user);

        if ($reportType === 'sedes') {
            return $this->buildSedesReportText($data, $fechaOficial, $horaOficial, $firmaOperador, $observations);
        }

        return $this->buildServicesReportText($data, $fechaOficial, $horaOficial, $firmaOperador, $observations);
    }

    /**
     * Construye el texto del reporte de Servicios Corporativos.
     */
    public function buildServicesReportText(
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
     * Construye el texto del reporte de Sedes y Enlaces.
     */
    public function buildSedesReportText(
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
     * Compila un escaneo físico en caliente mediante el motor de monitoreo.
     */
    public function compileLiveReport(string $reportType, User $user, string $observations = ''): string
    {
        $scriptPath = SonarPathService::monitorScript('monitor_engine.py');
        $pythonExec = SonarPathService::pythonBinary();

        if (!file_exists($scriptPath) || !file_exists($pythonExec)) {
            return $this->compileSnapshotReport($reportType, $user, $observations);
        }

        // ARC-09: Erradicación de @shell_exec adoptando Process con timeout controlado
        $process = Process::timeout(30)->run([$pythonExec, $scriptPath, $reportType, '--no-send']);
        $output = $process->successful() ? trim($process->output()) : null;

        if (empty($output)) {
            return $this->compileSnapshotReport($reportType, $user, $observations);
        }

        $firmaOperador = $this->formatOperatorSignature($user);
        $obsBlock = !empty($observations)
            ? "━━━━━━━━━━━━\n📝 <b>OBSERVACIONES:</b>\n" . htmlspecialchars($observations) . "\n━━━━━━━━━━━━\n\n"
            : "";

        $pattern = '/\*\*Personal de\s+ATIT:\*\*.*?(?=\*\*⚡️ATIT)/s';
        if (preg_match($pattern, $output)) {
            $output = preg_replace($pattern, "{$obsBlock}{$firmaOperador}\n\n", $output);
        } else {
            $htmlPattern = '/<b>Personal de\s+ATIT:<\/b>.*?(?=<b>⚡️ATIT)/s';
            if (preg_match($htmlPattern, $output)) {
                $output = preg_replace($htmlPattern, "{$obsBlock}{$firmaOperador}\n\n", $output);
            }
        }

        return $output;
    }

    /**
     * Formatea la firma corporativa del operador con formato unificado.
     */
    public function formatOperatorSignature(User $user): string
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
