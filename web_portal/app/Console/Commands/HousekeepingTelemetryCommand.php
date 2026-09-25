<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SnmpInterfaceHourlyRollup;
use App\Models\SnmpMetricHourlyRollup;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HousekeepingTelemetryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telemetry:housekeeping 
                            {--dry-run : Simular la operación y mostrar conteos sin eliminar registros}
                            {--force : Ejecutar sin solicitar confirmación interactiva}
                            {--aggregate-only : Solo calcular los rollups horarios sin purgar datos antiguos}
                            {--purge-only : Solo purgar datos antiguos sin calcular rollups previos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fase 8: Mantenimiento, agregación horaria y purga de series temporales y telemetría de monitoreo';

    /**
     * Políticas de retención (en días) según especificación técnica Fase 8.
     */
    protected array $retentionPolicies = [
        'netflow_records' => [
            'days' => 7,
            'date_col' => 'window_start',
            'desc' => 'Flujos de red NetFlow v5 (alto volumen)',
        ],
        'snmp_metrics_history' => [
            'days' => 30,
            'date_col' => 'collected_at',
            'desc' => 'Métricas de dispositivos SNMP (CPU, RAM, temp)',
        ],
        'snmp_interface_metrics' => [
            'days' => 30,
            'date_col' => 'collected_at',
            'desc' => 'Métricas de interfaces SNMP (tráfico, octetos)',
        ],
        'syslog_events' => [
            'days' => 30,
            'date_col' => 'received_at',
            'desc' => 'Bitácora centralizada de red Syslog RFC 3164/5424',
        ],
        'snmp_traps_received' => [
            'days' => 90,
            'date_col' => 'received_at',
            'desc' => 'Trampas SNMP recibidas por eventos de red',
        ],
        'service_check_histories' => [
            'days' => 60,
            'date_col' => 'checked_at',
            'desc' => 'Historial de comprobación de servicios HTTP/DNS/LDAP',
        ],
        'site_check_histories' => [
            'days' => 60,
            'date_col' => 'checked_at',
            'desc' => 'Historial de telemetría de sedes (jitter, pérdida)',
        ],
        'proxy_check_histories' => [
            'days' => 60,
            'date_col' => 'checked_at',
            'desc' => 'Historial de conectividad de proxies corporativos',
        ],
        'network_device_check_histories' => [
            'days' => 60,
            'date_col' => 'checked_at',
            'desc' => 'Historial de accesibilidad de conmutadores y routers',
        ],
        'discovery_scans' => [
            'days' => 30,
            'date_col' => 'started_at',
            'desc' => 'Historial de escaneos de subred y anti-rogue',
        ],
        'discovered_device_history' => [
            'days' => 60,
            'date_col' => 'occurred_at',
            'desc' => 'Historial de cambios de estado en dispositivos descubiertos',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $start = microtime(true);
        $isDryRun = $this->option('dry-run');
        $aggregateOnly = $this->option('aggregate-only');
        $purgeOnly = $this->option('purge-only');

        $this->info("========================================================================");
        $this->info("🧹 MANTENIMIENTO Y PURGA DE TELEMETRÍA (GITOPS FASE 8)");
        $this->info("========================================================================");
        $this->comment("Hora de Inicio : " . date('Y-m-d H:i:s'));
        $this->comment("Modo           : " . ($isDryRun ? "🔍 SIMULACIÓN (DRY-RUN)" : "⚡ EJECUCIÓN REAL"));

        // PASO 1: Agregación de Rollups Horarios
        $rollupsGenerated = 0;
        if (!$purgeOnly) {
            $this->newLine();
            $this->info("📊 1. Generando agregaciones horarias (Rollups a largo plazo)...");
            $rollupsGenerated = $this->generateHourlyRollups($isDryRun);
        }

        // PASO 2: Purga Segura por Lotes
        $results = [];
        $totalPurged = 0;

        if (!$aggregateOnly) {
            $this->newLine();
            $this->info("🗑️  2. Evaluando y purgando series temporales según políticas...");

            foreach ($this->retentionPolicies as $table => $config) {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                $days = $config['days'];
                $col = $config['date_col'];
                $desc = $config['desc'];
                $cutoff = Carbon::now()->subDays($days);

                // Contar registros candidatos
                $count = DB::table($table)->where($col, '<', $cutoff)->count();

                if ($isDryRun) {
                    $results[] = [
                        'table' => $table,
                        'retention' => "{$days} días",
                        'candidates' => $count,
                        'status' => $count > 0 ? 'Por purgar' : 'Al día',
                    ];
                    $totalPurged += $count;
                } else {
                    $purgedInTable = 0;
                    if ($count > 0) {
                        // Eliminación segura por lotes para evitar bloqueos de tabla en MariaDB
                        $batchSize = 2500;
                        do {
                            $deleted = DB::table($table)
                                ->where($col, '<', $cutoff)
                                ->limit($batchSize)
                                ->delete();
                            $purgedInTable += $deleted;
                        } while ($deleted >= $batchSize);
                    }

                    $results[] = [
                        'table' => $table,
                        'retention' => "{$days} días",
                        'candidates' => $purgedInTable,
                        'status' => '✅ Purgado',
                    ];
                    $totalPurged += $purgedInTable;
                }
            }

            // Mostrar resumen en tabla CLI
            $this->table(
                ['Tabla', 'Retención', 'Registros', 'Estado'],
                $results
            );
        }

        $elapsed = round(microtime(true) - $start, 2);
        $this->newLine();
        $this->info("------------------------------------------------------------------------");
        $this->info("✨ RESUMEN DE EJECUCIÓN:");
        $this->line("   • Rollups Generados   : {$rollupsGenerated}");
        $this->line("   • Registros Purgados  : {$totalPurged}");
        $this->line("   • Tiempo de Ejecución : {$elapsed}s");
        $this->info("========================================================================");

        // Registrar en bitácora de auditoría si fue una ejecución real
        if (!$isDryRun && ($totalPurged > 0 || $rollupsGenerated > 0)) {
            try {
                AuditLog::create([
                    'user_id' => null,
                    'action' => 'telemetry_housekeeping',
                    'entity_type' => 'system',
                    'entity_id' => 0,
                    'details' => json_encode([
                        'purged_records' => $totalPurged,
                        'rollups_generated' => $rollupsGenerated,
                        'duration_seconds' => $elapsed,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ]),
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Console/Artisan',
                ]);
            } catch (\Exception $e) {
                Log::warning("No se pudo registrar entrada de auditoría para housekeeping: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Genera resúmenes horarios consolidados para métricas que estén próximas a purgarse
     * o que aún no hayan sido agregadas.
     */
    protected function generateHourlyRollups(bool $isDryRun): int
    {
        $totalCreated = 0;
        $now = now();

        // 1. Rollup de Métricas SNMP de Dispositivos (CPU, Memoria, etc.)
        if (DB::getSchemaBuilder()->hasTable('snmp_metrics_history') && DB::getSchemaBuilder()->hasTable('snmp_metric_hourly_rollups')) {
            $latestMetricRollup = SnmpMetricHourlyRollup::max('hour_timestamp');
            $metricsSince = $latestMetricRollup ? Carbon::parse($latestMetricRollup)->subHours(1) : Carbon::now()->subDays(30);

            $metricsHours = DB::table('snmp_metrics_history')
                ->select(
                    'snmp_device_id',
                    'snmp_oid_id',
                    DB::raw("DATE_FORMAT(collected_at, '%Y-%m-%d %H:00:00') as hour_stamp"),
                    DB::raw('AVG(metric_value) as avg_val'),
                    DB::raw('MIN(metric_value) as min_val'),
                    DB::raw('MAX(metric_value) as max_val'),
                    DB::raw('COUNT(*) as sample_cnt')
                )
                ->where('collected_at', '<', Carbon::now()->startOfHour())
                ->where('collected_at', '>=', $metricsSince)
                ->groupBy('snmp_device_id', 'snmp_oid_id', DB::raw("DATE_FORMAT(collected_at, '%Y-%m-%d %H:00:00')"))
                ->get();

            if (!$isDryRun && $metricsHours->isNotEmpty()) {
                $metricBatch = [];
                foreach ($metricsHours as $mh) {
                    $metricBatch[] = [
                        'snmp_device_id' => $mh->snmp_device_id,
                        'snmp_oid_id' => $mh->snmp_oid_id,
                        'hour_timestamp' => $mh->hour_stamp,
                        'avg_value' => round((float)$mh->avg_val, 4),
                        'min_value' => round((float)$mh->min_val, 4),
                        'max_value' => round((float)$mh->max_val, 4),
                        'samples_count' => (int)$mh->sample_cnt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($metricBatch) >= 500) {
                        DB::table('snmp_metric_hourly_rollups')->upsert(
                            $metricBatch,
                            ['snmp_device_id', 'snmp_oid_id', 'hour_timestamp'],
                            ['avg_value', 'min_value', 'max_value', 'samples_count', 'updated_at']
                        );
                        $metricBatch = [];
                    }
                }
                if (!empty($metricBatch)) {
                    DB::table('snmp_metric_hourly_rollups')->upsert(
                        $metricBatch,
                        ['snmp_device_id', 'snmp_oid_id', 'hour_timestamp'],
                        ['avg_value', 'min_value', 'max_value', 'samples_count', 'updated_at']
                    );
                }
            }
            $totalCreated += $metricsHours->count();
        }

        // 2. Rollup de Métricas de Interfaces SNMP (Tráfico in/out, utilización)
        if (DB::getSchemaBuilder()->hasTable('snmp_interface_metrics') && DB::getSchemaBuilder()->hasTable('snmp_interface_hourly_rollups')) {
            $latestIfaceRollup = SnmpInterfaceHourlyRollup::max('hour_timestamp');
            $ifaceSince = $latestIfaceRollup ? Carbon::parse($latestIfaceRollup)->subHours(1) : Carbon::now()->subDays(30);

            $interfaceHours = DB::table('snmp_interface_metrics')
                ->select(
                    'snmp_interface_id',
                    DB::raw("DATE_FORMAT(collected_at, '%Y-%m-%d %H:00:00') as hour_stamp"),
                    DB::raw('AVG(in_bps) as avg_in'),
                    DB::raw('MAX(in_bps) as max_in'),
                    DB::raw('AVG(out_bps) as avg_out'),
                    DB::raw('MAX(out_bps) as max_out'),
                    DB::raw('AVG(in_utilization_pct) as avg_in_u'),
                    DB::raw('MAX(in_utilization_pct) as max_in_u'),
                    DB::raw('AVG(out_utilization_pct) as avg_out_u'),
                    DB::raw('MAX(out_utilization_pct) as max_out_u'),
                    DB::raw('COUNT(*) as sample_cnt')
                )
                ->where('collected_at', '<', Carbon::now()->startOfHour())
                ->where('collected_at', '>=', $ifaceSince)
                ->groupBy('snmp_interface_id', DB::raw("DATE_FORMAT(collected_at, '%Y-%m-%d %H:00:00')"))
                ->get();

            if (!$isDryRun && $interfaceHours->isNotEmpty()) {
                $ifaceBatch = [];
                foreach ($interfaceHours as $ih) {
                    $ifaceBatch[] = [
                        'snmp_interface_id' => $ih->snmp_interface_id,
                        'hour_timestamp' => $ih->hour_stamp,
                        'avg_in_bps' => round((float)$ih->avg_in, 2),
                        'max_in_bps' => round((float)$ih->max_in, 2),
                        'avg_out_bps' => round((float)$ih->avg_out, 2),
                        'max_out_bps' => round((float)$ih->max_out, 2),
                        'avg_in_util_pct' => round((float)$ih->avg_in_u, 2),
                        'max_in_util_pct' => round((float)$ih->max_in_u, 2),
                        'avg_out_util_pct' => round((float)$ih->avg_out_u, 2),
                        'max_out_util_pct' => round((float)$ih->max_out_u, 2),
                        'samples_count' => (int)$ih->sample_cnt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($ifaceBatch) >= 500) {
                        DB::table('snmp_interface_hourly_rollups')->upsert(
                            $ifaceBatch,
                            ['snmp_interface_id', 'hour_timestamp'],
                            ['avg_in_bps', 'max_in_bps', 'avg_out_bps', 'max_out_bps', 'avg_in_util_pct', 'max_in_util_pct', 'avg_out_util_pct', 'max_out_util_pct', 'samples_count', 'updated_at']
                        );
                        $ifaceBatch = [];
                    }
                }
                if (!empty($ifaceBatch)) {
                    DB::table('snmp_interface_hourly_rollups')->upsert(
                        $ifaceBatch,
                        ['snmp_interface_id', 'hour_timestamp'],
                        ['avg_in_bps', 'max_in_bps', 'avg_out_bps', 'max_out_bps', 'avg_in_util_pct', 'max_in_util_pct', 'avg_out_util_pct', 'max_out_util_pct', 'samples_count', 'updated_at']
                    );
                }
            }
            $totalCreated += $interfaceHours->count();
        }

        $this->line("   ✓ {$totalCreated} resúmenes horarios procesados.");
        return $totalCreated;
    }
}
