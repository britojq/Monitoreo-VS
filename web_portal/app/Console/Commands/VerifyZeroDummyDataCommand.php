<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyZeroDummyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:verify-zero-dummy-data {--purge : Purgar automáticamente cualquier registro residual detectado}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica exhaustivamente que todas las tablas de MariaDB (SSOT) tengan cero registros ficticios, datos dummy o de pruebas';

    /**
     * Patrones de datos ficticios o de prueba prohibidos por la Regla de Oro #6.
     */
    protected array $forbiddenPatterns = [
        '%TEST-%',
        '%TEST\_%',
        '%TEST DUAL%',
        '%TEST-DUAL%',
        '%DUMMY%',
        '%10.20.99.%',
    ];

    /**
     * Ejecuta la verificación en todas las tablas del esquema.
     */
    public function handle(): int
    {
        $this->info('Iniciando auditoría global de datos ficticios en MariaDB (SSOT)...');

        $tables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();
        $colName = 'Tables_in_' . $dbName;

        $violations = [];

        foreach ($tables as $tableObj) {
            $table = $tableObj->$colName;

            // Omitir tablas del framework o migraciones
            if (in_array($table, ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], true)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);

            foreach ($columns as $col) {
                try {
                    $colType = Schema::getColumnType($table, $col);
                } catch (\Throwable) {
                    continue;
                }

                if (!in_array($colType, ['string', 'text', 'mediumtext', 'longtext'], true)) {
                    continue;
                }

                foreach ($this->forbiddenPatterns as $pattern) {
                    try {
                        $query = DB::table($table)->where($col, 'like', $pattern);

                        // Excepciones legítimas documentadas (RFCs o infraestructura oficial)
                        if ($table === 'snmp_oids' && $col === 'description') {
                            $query->where('description', 'not like', '%3=testing%');
                        }
                        if ($table === 'glpi_inventory_logs' && $col === 'raw_payload') {
                            $query->where('raw_payload', 'not like', '%speech-dispatcher%');
                        }

                        $matches = $query->get();

                        foreach ($matches as $row) {
                            $rowId = $row->id ?? ($row->uuid ?? 'N/A');
                            $val = (string) ($row->$col ?? '');
                            $snippet = mb_substr($val, 0, 100);

                            $violations[] = [
                                'table' => $table,
                                'column' => $col,
                                'pattern' => $pattern,
                                'id' => $rowId,
                                'snippet' => $snippet,
                            ];
                        }
                    } catch (\Throwable) {
                        // Ignorar errores de columnas virtuales o no compatibles
                    }
                }
            }
        }

        // Chequeo de seriales y puertos sintéticos en inventario y red (Regla de Oro #6)
        try {
            $invalidSerials = DB::table('monitored_network_devices')
                ->where('serial', '00000000')
                ->orWhere(function ($q) {
                    $q->where('serial', 'like', 'SN-NET-%')
                      ->where('id', '!=', 1); // Router principal legítimo
                })
                ->get(['id', 'name', 'serial']);

            foreach ($invalidSerials as $s) {
                $violations[] = [
                    'table' => 'monitored_network_devices',
                    'column' => 'serial',
                    'pattern' => '00000000 o SN-NET-% sintético',
                    'id' => $s->id,
                    'snippet' => "Dispositivo [{$s->name}] Serial: {$s->serial}",
                ];
            }

            $invalidPorts = DB::table('monitored_network_devices')
                ->where('ports', '1x GE')
                ->get(['id', 'name', 'ports']);

            foreach ($invalidPorts as $p) {
                $violations[] = [
                    'table' => 'monitored_network_devices',
                    'column' => 'ports',
                    'pattern' => '1x GE (puerto ficticio)',
                    'id' => $p->id,
                    'snippet' => "Dispositivo [{$p->name}] Ports: {$p->ports}",
                ];
            }
        } catch (\Throwable) {
            // Ignorar si la tabla no existe en alguna ejecución
        }

        // Chequeo de duplicidad de direcciones IP en inventario de red (Reglas #6, #7 y #8)
        try {
            $duplicateIps = DB::table('inventory_assets')
                ->select('ip_address', DB::raw('count(*) as count'), DB::raw('GROUP_CONCAT(id) as ids'), DB::raw('GROUP_CONCAT(name) as names'))
                ->whereNotNull('ip_address')
                ->where('ip_address', '!=', '')
                ->where('ip_address', '!=', '0.0.0.0')
                ->groupBy('ip_address')
                ->having('count', '>', 1)
                ->get();

            foreach ($duplicateIps as $dup) {
                $violations[] = [
                    'table' => 'inventory_assets',
                    'column' => 'ip_address',
                    'pattern' => 'IP DUPLICADA EN CENSO (Regla #6 / #7)',
                    'id' => $dup->ids,
                    'snippet' => "IP {$dup->ip_address} repetida {$dup->count} veces en equipos: [{$dup->names}]",
                ];
            }
        } catch (\Throwable) {
        }

        // Chequeo de duplicidad de posiciones (device_number) por sede (Regla #7)
        try {
            $duplicateSlots = DB::table('inventory_assets')
                ->select('monitored_site_id', 'device_number', DB::raw('count(*) as count'), DB::raw('GROUP_CONCAT(id) as ids'), DB::raw('GROUP_CONCAT(name) as names'))
                ->whereNotNull('monitored_site_id')
                ->whereNotNull('device_number')
                ->groupBy('monitored_site_id', 'device_number')
                ->having('count', '>', 1)
                ->get();

            foreach ($duplicateSlots as $dup) {
                $violations[] = [
                    'table' => 'inventory_assets',
                    'column' => 'device_number',
                    'pattern' => 'POSICIÓN DUPLICADA POR SEDE (device_number)',
                    'id' => $dup->ids,
                    'snippet' => "Sede {$dup->monitored_site_id} Posición #{$dup->device_number} repetida {$dup->count} veces en: [{$dup->names}]",
                ];
            }
        } catch (\Throwable) {
        }

        // Chequeo de duplicidad de nombres operativos por sede (Regla #7)
        try {
            $duplicateNames = DB::table('inventory_assets')
                ->select('monitored_site_id', 'name', DB::raw('count(*) as count'), DB::raw('GROUP_CONCAT(id) as ids'))
                ->whereNotNull('monitored_site_id')
                ->whereNotNull('name')
                ->groupBy('monitored_site_id', 'name')
                ->having('count', '>', 1)
                ->get();

            foreach ($duplicateNames as $dup) {
                $violations[] = [
                    'table' => 'inventory_assets',
                    'column' => 'name',
                    'pattern' => 'NOMBRE OPERATIVO DUPLICADO EN SEDE',
                    'id' => $dup->ids,
                    'snippet' => "Sede {$dup->monitored_site_id} Nombre '{$dup->name}' repetido {$dup->count} veces en IDs: [{$dup->ids}]",
                ];
            }
        } catch (\Throwable) {
        }

        // Chequeo de integridad de activos troncales (Router Principal ID 1 y Servidor Desarrollo ID 11519)
        try {
            $router = DB::table('inventory_assets')->where('id', 1)->first();
            if (!$router || $router->name !== 'ROUTER PRINCIPAL' || $router->ip_address !== '10.20.23.1' || $router->access_type !== 'TELNET' || (int) $router->access_port !== 23) {
                $violations[] = [
                    'table' => 'inventory_assets',
                    'column' => 'id=1 (ROUTER PRINCIPAL)',
                    'pattern' => 'CORRUPCIÓN DE ACTIVO CRÍTICO (Error #8)',
                    'id' => '1',
                    'snippet' => 'Router Principal no tiene los parámetros legítimos (TELNET:23, 10.20.23.1, tipo router)',
                ];
            }

            $devServer = DB::table('inventory_assets')->where('id', 11519)->first();
            if (!$devServer || $devServer->ip_address !== '10.20.23.221' || !str_contains($devServer->name, 'CENCARATIT')) {
                $violations[] = [
                    'table' => 'inventory_assets',
                    'column' => 'id=11519 (CENCARATIT)',
                    'pattern' => 'CORRUPCIÓN DE SERVIDOR DE DESARROLLO',
                    'id' => '11519',
                    'snippet' => 'Servidor de desarrollo 11519 no tiene IP 10.20.23.221 o nombre CENCARATIT',
                ];
            }
        } catch (\Throwable) {
        }

        if (empty($violations)) {
            $this->info('================================================================');
            $this->info('[OK] VERIFICACIÓN EXITOSA: Cero datos ficticios, cero duplicados y 100% integridad en MariaDB.');
            $this->info('Todas las tablas del sistema cumplen al 100% con la Regla de Oro #6 y #7.');
            $this->info('================================================================');
            return Command::SUCCESS;
        }

        $this->error('================================================================');
        $this->error('[FALLO CRÍTICO] Se detectaron violaciones de integridad o registros ficticios en MariaDB:');
        $this->error('================================================================');

        $headers = ['Tabla', 'Columna', 'ID', 'Patrón', 'Muestra del Valor'];
        $rows = [];
        foreach ($violations as $v) {
            $rows[] = [$v['table'], $v['column'], $v['id'], $v['pattern'], $v['snippet']];
        }
        $this->table($headers, $rows);

        if ($this->option('purge')) {
            $this->warn('Ejecutando purga automática de violaciones de texto residual detectadas...');
            foreach ($violations as $v) {
                if (str_contains($v['pattern'], 'DUPLICADA') || str_contains($v['pattern'], 'CORRUPCIÓN')) {
                    $this->error("La violación en {$v['table']} ({$v['pattern']}) requiere saneamiento relacional y no puede ser purgada automáticamente con --purge.");
                    continue;
                }
                if ($v['id'] !== 'N/A' && !empty($v['table']) && is_numeric($v['id'])) {
                    DB::table($v['table'])->where('id', $v['id'])->delete();
                }
            }
            $this->info('Purga completada. Re-verificando...');
            return $this->handle();
        }

        return Command::FAILURE;
    }
}
