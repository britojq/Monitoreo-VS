<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InventoryAsset;
use App\Models\InventoryPeripheral;
use App\Models\UpsDevice;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CertifySystemIntegrityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:certify
                            {--route= : Probar únicamente una ruta específica}
                            {--skip-views : Omitir compilación masiva de vistas}
                            {--json : Salida en formato JSON estructurado}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Barrera de Calidad Integral (Quality Gate): Compila vistas Blade, linta PHP, renderiza en frío rutas administrativas, audita el DOM contra Reglas de Oro (#2, #6, #7, #9, #11) y certifica integridad de MariaDB';

    /**
     * Rutas críticas a auditar mediante renderizado HTTP real en frío.
     */
    protected array $criticalRoutes = [
        ['name' => 'Home Público', 'uri' => '/', 'auth' => false],
        ['name' => 'API Estatus Público', 'uri' => '/api/status', 'auth' => false],
        ['name' => 'Pantalla Login', 'uri' => '/login', 'auth' => false],
        ['name' => 'Dashboard NOC', 'uri' => '/admin', 'auth' => true],
        ['name' => 'Equipos & Activos ITAM', 'uri' => '/admin/devices', 'auth' => true],
        ['name' => 'Supervisión Energía & UPS', 'uri' => '/admin/ups', 'auth' => true],
        ['name' => 'Sedes & Enlaces WAN', 'uri' => '/admin/sites', 'auth' => true],
        ['name' => 'Servicios Corporativos', 'uri' => '/admin/services', 'auth' => true],
        ['name' => 'Periféricos & Repuestos', 'uri' => '/admin/inventory/peripherals', 'auth' => true],
        ['name' => 'Plan de IP (IPAM)', 'uri' => '/admin/ipam', 'auth' => true],
        ['name' => 'Discovery & Telemetría SNMP', 'uri' => '/admin/discovery', 'auth' => true],
        ['name' => 'Radar de Red (NetRadar)', 'uri' => '/admin/netradar', 'auth' => true],
        ['name' => 'Actas de Asignación ITAM', 'uri' => '/admin/inventory/assignments', 'auth' => true],
        ['name' => 'Mantenimiento Técnico ITAM', 'uri' => '/admin/inventory/maintenance', 'auth' => true],
        ['name' => 'Auditoría Forense ITAM', 'uri' => '/admin/inventory/audit', 'auth' => true],
        ['name' => 'Centro de Reportes', 'uri' => '/admin/reports', 'auth' => true],
        ['name' => 'Topología de Red', 'uri' => '/admin/topology', 'auth' => true],
        ['name' => 'Alertas & Escalación', 'uri' => '/admin/alerts', 'auth' => true],
        ['name' => 'Manual de Ayuda', 'uri' => '/admin/ayuda', 'auth' => true],
    ];

    /**
     * Lista de violaciones detectadas durante la certificación.
     */
    protected array $violations = [];

    /**
     * Ejecución del comando de certificación.
     */
    public function handle(): int
    {
        $this->outputHeader();

        $allPassed = true;

        // FASE 1: Compilación masiva de vistas Blade y Linting de PHP
        if (!$this->option('skip-views')) {
            $phase1Passed = $this->certifyBladeAndPhpSyntax();
            $allPassed = $allPassed && $phase1Passed;
        }

        // FASE 2: Renderizado HTTP Real en Frío y Auditoría DOM
        $phase2Passed = $this->certifyHttpRenderAndDomRules();
        $allPassed = $allPassed && $phase2Passed;

        // FASE 3: Auditoría Forense de Base de Datos (MariaDB SSOT)
        $phase3Passed = $this->certifyDatabaseSsotIntegrity();
        $allPassed = $allPassed && $phase3Passed;

        // FASE 4: Certificación de Versión del Sistema (Regla de Oro #12)
        $phase4Passed = $this->certifySystemVersionParity();
        $allPassed = $allPassed && $phase4Passed;

        $this->outputSummary($allPassed);

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * FASE 1: Compilación de Blade y Linting PHP
     */
    protected function certifyBladeAndPhpSyntax(): bool
    {
        $this->line("\n<fg=cyan;options=bold>▶ FASE 1: Compilación de Plantillas Blade y Linting PHP...</>");

        // 1. Limpiar y pre-compilar vistas Blade
        $this->info("   • Purgando y pre-compilando vistas con 'view:cache'...");
        Artisan::call('view:clear');
        $exitCache = Artisan::call('view:cache');

        if ($exitCache !== 0) {
            $err = trim(Artisan::output());
            $this->recordViolation('BLADE_CACHE_FAIL', "Fallo fatal durante 'view:cache': {$err}");
            $this->error("   ❌ Falló 'view:cache'. Error de sintaxis en plantillas Blade.");
            return false;
        }

        // 2. Linting sintáctico de todas las vistas compiladas (Previene Error #21)
        $viewsPath = storage_path('framework/views');
        $compiledFiles = glob("{$viewsPath}/*.php") ?: [];
        $viewSyntaxErrors = 0;

        foreach ($compiledFiles as $file) {
            $cmd = sprintf('php -l %s 2>&1', escapeshellarg($file));
            exec($cmd, $out, $ret);
            if ($ret !== 0) {
                $viewSyntaxErrors++;
                $msg = implode("\n", $out);
                $this->recordViolation('BLADE_SYNTAX_ERROR', "Error de sintaxis en vista compilada {$file}: {$msg}");
                $this->error("   ❌ Error de sintaxis PHP en vista compilada: " . basename($file));
            }
            unset($out);
        }

        if ($viewSyntaxErrors > 0) {
            $this->error("   ❌ Se encontraron {$viewSyntaxErrors} vistas compiladas con errores de sintaxis (posible '@endif' o tag no cerrado).");
            return false;
        }

        $this->line("   <fg=green>✓</> " . count($compiledFiles) . " vistas Blade compiladas y certificadas con 'php -l' sin errores.");
        return true;
    }

    /**
     * FASE 2: Renderizado HTTP Real en Frío y Auditoría DOM
     */
    protected function certifyHttpRenderAndDomRules(): bool
    {
        $this->line("\n<fg=cyan;options=bold>▶ FASE 2: Renderizado HTTP en Frío y Auditoría de Reglas de Oro Visuales...</>");

        $adminUser = User::where('role', 'admin')->first();
        if (!$adminUser) {
            $this->recordViolation('NO_ADMIN_USER', 'No se encontró un usuario administrador en MariaDB para simular peticiones autenticadas.');
            $this->error("   ❌ No existe usuario con rol 'admin' en la base de datos.");
            return false;
        }

        $routesToTest = $this->criticalRoutes;
        if ($this->option('route')) {
            $filter = (string) $this->option('route');
            $routesToTest = array_filter($routesToTest, fn($r) => str_contains($r['uri'], $filter));
        }

        $allRoutesPassed = true;

        foreach ($routesToTest as $r) {
            $uri = $r['uri'];
            $name = $r['name'];
            $requiresAuth = $r['auth'];

            if ($requiresAuth) {
                Auth::setUser($adminUser);
            } else {
                Auth::logout();
            }

            try {
                $req = Request::create($uri, 'GET');
                if ($requiresAuth) {
                    $req->setUserResolver(fn() => $adminUser);
                }

                $res = app()->handle($req);
                $statusCode = $res->getStatusCode();

                if ($statusCode !== Response::HTTP_OK) {
                    $allRoutesPassed = false;
                    $this->recordViolation('HTTP_STATUS_NOT_200', "Ruta '{$uri}' ({$name}) retornó HTTP {$statusCode} en lugar de 200 OK.");
                    $this->error("   ❌ [HTTP {$statusCode}] {$name} ({$uri})");
                    continue;
                }

                $html = (string) $res->getContent();

                // Auditoría de Reglas sobre el HTML renderizado
                $domErrors = $this->auditDomContent($uri, $name, $html);
                if (!empty($domErrors)) {
                    $allRoutesPassed = false;
                    foreach ($domErrors as $err) {
                        $this->recordViolation($err['rule'], "Ruta '{$uri}' ({$name}): {$err['message']}");
                        $this->error("   ❌ [{$err['rule']}] {$name}: {$err['message']}");
                    }
                } else {
                    $this->line("   <fg=green>✓</> [200 OK] {$name} ({$uri}) - HTML auditado y conforme.");
                }

            } catch (\Throwable $e) {
                $allRoutesPassed = false;
                $this->recordViolation('RENDER_EXCEPTION', "Excepción fatal al renderizar '{$uri}' ({$name}): " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
                $this->error("   ❌ [EXCEPCIÓN] {$name} ({$uri}): " . $e->getMessage());
            }
        }

        return $allRoutesPassed;
    }

    /**
     * Audita el contenido HTML renderizado contra Reglas de Oro (#2, #6, #7, #9, #11).
     */
    protected function auditDomContent(string $uri, string $name, string $html): array
    {
        $errors = [];

        // 1. REGLA DE ORO #2: Prohibición de mencionar la tecnología de IA subyacente
        if (preg_match('/\b(ol+ama|ollama)\b/i', $html)) {
            $errors[] = [
                'rule' => 'REGLA_ORO_2_AI_VETADA',
                'message' => 'Se detectó mención explícita a la tecnología subyacente de IA en el HTML renderizado.',
            ];
        }

        // 2. REGLA DE ORO #9: Términos prohibidos ("360°" / "360", "custodio")
        if (preg_match('/\b(360°|360\s*grados)\b/i', $html) || str_contains($html, '360°')) {
            $errors[] = [
                'rule' => 'REGLA_ORO_9_TERMINO_VETADO',
                'message' => "Se detectó el término vetado '360°' o variante en la vista.",
            ];
        }
        if (preg_match('/\bcustodio\b/i', $html)) {
            $errors[] = [
                'rule' => 'REGLA_ORO_9_CUSTODIO_VETADO',
                'message' => "Se detectó el término vetado 'custodio' en la vista (debe usarse 'Responsable' o 'Funcionario').",
            ];
        }

        // 3. REGLA DE ORO #6 & #7: Detección de caracteres de relleno / corruptos en pantalla (ej: 222222...)
        if (preg_match('/2{6,}/', $html)) {
            $errors[] = [
                'rule' => 'REGLA_ORO_6_CORRUPT_PADDING',
                'message' => 'Se detectó cadena de relleno ROM corrupta (repetición de 222222...) en el HTML visible.',
            ];
        }
        if (preg_match('/\b(TEST-DUAL|10\.20\.99\.\d+)\b/i', $html)) {
            $errors[] = [
                'rule' => 'REGLA_ORO_6_DUMMY_VISIBLE',
                'message' => 'Se detectaron cadenas ficticias de prueba (TEST-DUAL o IP 10.20.99.X) visibles en la vista.',
            ];
        }

        // 4. REGLA DE ORO #11: Prohibición de badges con texto visible en cabeceras <h1> y <h2>
        // Detecta rectángulos con texto tipo <span class="...bg-... text-...">Texto</span> dentro de <h1>
        if (preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            foreach ($matches[1] as $h1Inner) {
                // Si contiene un span con texto visible de más de 3 letras que no sea sr-only ni hidden
                if (preg_match('/<span[^>]*class="(?![^"]*\b(sr-only|hidden)\b)[^"]*(?:bg-|border-|rounded)[^"]*"[^>]*>\s*([a-zA-Z0-9\s]{3,})\s*<\/span>/i', $h1Inner, $pillMatch)) {
                    $errors[] = [
                        'rule' => 'REGLA_ORO_11_BADGE_EN_TITULO',
                        'message' => "El encabezado h1 contiene una píldora con texto visible ('{$pillMatch[2]}'). Solo se permiten iconos compactos con tooltip title.",
                    ];
                }
            }
        }

        return $errors;
    }

    /**
     * FASE 3: Auditoría Forense de Base de Datos (MariaDB SSOT)
     */
    protected function certifyDatabaseSsotIntegrity(): bool
    {
        $this->line("\n<fg=cyan;options=bold>▶ FASE 3: Auditoría Forense de Datos en MariaDB (SSOT)...</>");

        $dbPassed = true;

        // 1. Invocar auditoría de cero datos ficticios
        $exitZeroDummy = Artisan::call('audit:verify-zero-dummy-data');
        if ($exitZeroDummy !== 0) {
            $dbPassed = false;
            $this->recordViolation('MARIADB_DUMMY_DATA', 'audit:verify-zero-dummy-data detectó registros ficticios en MariaDB.');
            $this->error("   ❌ Se detectaron registros ficticios o de depuración en MariaDB.");
        } else {
            $this->line("   <fg=green>✓</> Cero datos dummy o ficticios certificados en MariaDB (Regla de Oro #6).");
        }

        // 2. Verificar integridad del UPS ZTG LV6KL
        $ups = UpsDevice::first();
        if ($ups) {
            $upsName = (string) $ups->name;
            $upsModel = (string) $ups->model;
            $upsFw = (string) $ups->firmware_version;

            if (str_contains($upsName, '222222') || str_contains($upsModel, '222222') || $upsFw === '(') {
                $dbPassed = false;
                $this->recordViolation('UPS_IDENTITY_CORRUPT', "Registro de UPS contiene cadenas corruptas o firmware malformado: Name='{$upsName}', Model='{$upsModel}', FW='{$upsFw}'");
                $this->error("   ❌ Identidad de UPS en MariaDB corrupta con bytes de relleno ROM o firmware '('");
            } else {
                $this->line("   <fg=green>✓</> Identidad de UPS certificada: '{$upsName}' ({$upsModel}, FW: {$upsFw}).");
            }
        }

        // 3. Verificar que los periféricos ITAM tipo UPS no contengan padding
        $corruptPeripherals = InventoryPeripheral::where('type', 'ups')
            ->where(function ($q) {
                $q->where('model', 'LIKE', '%222222%')
                  ->orWhere('brand', 'LIKE', '%222222%')
                  ->orWhere('brand', 'Genérico');
            })
            ->count();

        if ($corruptPeripherals > 0) {
            $dbPassed = false;
            $this->recordViolation('ITAM_PERIPHERAL_CORRUPT', "Se encontraron {$corruptPeripherals} periféricos UPS en ITAM con modelos corruptos o 'Genérico'.");
            $this->error("   ❌ Periféricos ITAM contienen registros corruptos.");
        } else {
            $this->line("   <fg=green>✓</> Periféricos UPS en inventario ITAM conciliados y certificados.");
        }

        return $dbPassed;
    }

    /**
     * FASE 4: Certificación de Versión del Sistema (Regla de Oro #12)
     */
    protected function certifySystemVersionParity(): bool
    {
        $this->line("\n<fg=cyan;options=bold>▶ FASE 4: Certificación de Paridad de Versión del Sistema (Regla de Oro #12)...</>");

        $adminBlade = resource_path('views/layouts/admin.blade.php');
        $inspectorFile = app_path('Services/Cluster/RuntimeParityInspector.php');
        $helpCtrl = app_path('Http/Controllers/Admin/HelpController.php');

        $vBlade = null;
        if (file_exists($adminBlade)) {
            if (preg_match('/v(\d+\.\d+\.\d+)/', file_get_contents($adminBlade), $m)) {
                $vBlade = $m[1];
            }
        }

        $vInspector = null;
        if (file_exists($inspectorFile)) {
            if (preg_match("/'system_version'\s*=>\s*'v?(\d+\.\d+\.\d+)'/", file_get_contents($inspectorFile), $m)) {
                $vInspector = $m[1];
            }
        }

        $vHelp = null;
        if (file_exists($helpCtrl)) {
            if (preg_match("/\\\$systemVersion\s*=\s*'v?(\d+\.\d+\.\d+)'/", file_get_contents($helpCtrl), $m)) {
                $vHelp = $m[1];
            }
        }

        if (!$vBlade || !$vInspector || !$vHelp) {
            $this->recordViolation('VERSION_STRING_NOT_FOUND', "No se pudo extraer la versión de uno o más archivos canónicos (Blade: {$vBlade}, Inspector: {$vInspector}, Help: {$vHelp}).");
            $this->error("   ❌ No se pudo extraer la versión de los archivos del sistema.");
            return false;
        }

        if ($vBlade !== $vInspector || $vBlade !== $vHelp) {
            $this->recordViolation('VERSION_DISPARITY', "Disparidad de versiones detectada: admin.blade.php (v{$vBlade}), RuntimeParityInspector (v{$vInspector}), HelpController (v{$vHelp}).");
            $this->error("   ❌ Disparidad de versiones entre archivos: Blade=v{$vBlade}, Inspector=v{$vInspector}, Help=v{$vHelp}");
            return false;
        }

        $this->line("   <fg=green>✓</> Versión del sistema certificada y en paridad atómica: <fg=cyan;options=bold>v{$vBlade}</>");
        return true;
    }

    /**
     * Registra una violación para el reporte consolidado.
     */
    protected function recordViolation(string $code, string $message): void
    {
        $this->violations[] = [
            'code' => $code,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    protected function outputHeader(): void
    {
        $this->line("<fg=black;bg=cyan;options=bold> ========================================================================= </>");
        $this->line("<fg=black;bg=cyan;options=bold>   🛡️ BARRERA DE CALIDAD & CERTIFICACIÓN INTEGRAL DE SISTEMA (ZERO FAIL)  </>");
        $this->line("<fg=black;bg=cyan;options=bold> ========================================================================= </>");
    }

    protected function outputSummary(bool $passed): void
    {
        $this->line("\n<fg=black;bg=cyan;options=bold> ========================================================================= </>");
        if ($passed) {
            $this->line("<fg=green;options=bold> 🎉 CERTIFICACIÓN 100% EXITOSA: CERO ERRORES DE SINTAXIS, RENDER O DATOS. </>");
            $this->line(" <fg=white>El sistema cumple estrictamente todas las Reglas de Oro y estándares de calidad.</>");
        } else {
            $this->line("<fg=red;options=bold> ⛔ CERTIFICACIÓN FALLIDA: SE DETECTARON " . count($this->violations) . " VIOLACIONES CRÍTICAS. </>");
            $this->line(" <fg=yellow>Corrija los fallos listados antes de dar cualquier tarea por terminada o desplegar.</>");
            $this->line(" ------------------------------------------------------------------------- ");
            foreach ($this->violations as $idx => $v) {
                $num = $idx + 1;
                $this->line(" <fg=red>{$num}. [{$v['code']}]</> {$v['message']}");
            }
        }
        $this->line("<fg=black;bg=cyan;options=bold> ========================================================================= </>\n");
    }
}
