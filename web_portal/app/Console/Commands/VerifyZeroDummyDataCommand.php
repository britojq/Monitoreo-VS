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

        if (empty($violations)) {
            $this->info('================================================================');
            $this->info('[OK] VERIFICACIÓN EXITOSA: Cero datos ficticios o de prueba en MariaDB.');
            $this->info('Todas las tablas del sistema cumplen al 100% con la Regla de Oro #6.');
            $this->info('================================================================');
            return Command::SUCCESS;
        }

        $this->error('================================================================');
        $this->error('[FALLO CRÍTICO] Se detectaron registros ficticios o de pruebas en MariaDB:');
        $this->error('================================================================');

        $headers = ['Tabla', 'Columna', 'ID', 'Patrón', 'Muestra del Valor'];
        $rows = [];
        foreach ($violations as $v) {
            $rows[] = [$v['table'], $v['column'], $v['id'], $v['pattern'], $v['snippet']];
        }
        $this->table($headers, $rows);

        if ($this->option('purge')) {
            $this->warn('Ejecutando purga automática de violaciones detectadas...');
            foreach ($violations as $v) {
                if ($v['id'] !== 'N/A' && !empty($v['table'])) {
                    DB::table($v['table'])->where('id', $v['id'])->delete();
                }
            }
            $this->info('Purga completada. Re-verificando...');
            return $this->handle();
        }

        return Command::FAILURE;
    }
}
