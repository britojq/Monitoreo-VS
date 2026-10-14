<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryPeripheral;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncInventoryFromExcelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:sync-from-excel
                            {--dry-run : Simular la actualización sin persistir cambios en MariaDB}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza y enriquece los equipos existentes en MariaDB con los datos y periféricos recuperados del formato de inventario';

    /**
     * Conjunto de estaciones de trabajo operativas detectadas por número de serie exacto.
     */
    protected array $stationsData = [
        'MXL5242234' => [
            'custodian_name' => 'YUSNEIDY BARELA',
            'custodian_id_number' => 'V-14243365',
            'custodian_department' => 'PROCURA',
            'phone_extension' => '0412-5321300',
            'operating_system' => 'Xubuntu 22.04.1 LTS',
            'peripherals' => [
                [
                    'type' => 'monitor',
                    'brand' => 'Hewlett Packard',
                    'model' => 'HP V193b',
                    'serial_number' => '3CQ51308WG',
                    'notes' => 'Monitor asociado a puesto de trabajo',
                ],
                [
                    'type' => 'keyboard',
                    'brand' => 'HP',
                    'model' => 'KB57P',
                    'serial_number' => 'BDMEP0CDP7B0CS',
                    'notes' => 'Teclado USB estándar HP',
                ],
                [
                    'type' => 'mouse',
                    'brand' => 'HP',
                    'model' => 'M-SBJ96',
                    'serial_number' => 'FCMHF0BAU7BRM0',
                    'notes' => 'Mouse óptico USB HP',
                ],
                [
                    'type' => 'surge_protector',
                    'brand' => 'CDP',
                    'model' => 'AVR',
                    'serial_number' => '150407-0000102',
                    'notes' => 'Regulador de voltaje CDP',
                ],
                [
                    'type' => 'other',
                    'brand' => 'Panasonic',
                    'model' => 'Estándar',
                    'serial_number' => '6ABAC435675',
                    'notes' => 'Teléfono corporativo',
                ],
            ],
        ],
        'MXL524368J' => [
            'custodian_name' => 'FATIMA MUDINE',
            'custodian_id_number' => 'V-16568770',
            'custodian_department' => 'PROCURA',
            'phone_extension' => '0412-4507291',
            'operating_system' => 'Xubuntu 22.04.1 LTS',
            'peripherals' => [
                [
                    'type' => 'monitor',
                    'brand' => 'Hewlett Packard',
                    'model' => 'HP V193b',
                    'serial_number' => '3CQ51308WW',
                    'notes' => 'Monitor asociado a puesto de trabajo',
                ],
                [
                    'type' => 'keyboard',
                    'brand' => 'HP',
                    'model' => 'KB57P',
                    'serial_number' => 'BDMEP0AHH8NRB',
                    'notes' => 'Teclado USB estándar HP',
                ],
                [
                    'type' => 'mouse',
                    'brand' => 'HP',
                    'model' => 'M-SBJ96',
                    'serial_number' => 'FCMHF68AU7BQCM',
                    'notes' => 'Mouse óptico USB HP',
                ],
                [
                    'type' => 'surge_protector',
                    'brand' => 'CDP',
                    'model' => 'AVR',
                    'serial_number' => '150407-0000064',
                    'notes' => 'Regulador de voltaje CDP',
                ],
                [
                    'type' => 'other',
                    'brand' => 'Panasonic',
                    'model' => 'Estándar',
                    'serial_number' => '6ABAC435626',
                    'notes' => 'Teléfono corporativo',
                ],
            ],
        ],
        'MXL5243544' => [
            'custodian_name' => 'JOSE L MUÑOZ',
            'custodian_id_number' => 'V-12742830',
            'custodian_department' => 'COMERCIALES / LECTURA',
            'phone_extension' => '0412-4166966',
            'operating_system' => 'Microsoft Windows 7 Ultimate',
            'peripherals' => [
                [
                    'type' => 'monitor',
                    'brand' => 'Hewlett Packard',
                    'model' => 'HP V193b',
                    'serial_number' => '3CQ51307SP',
                    'notes' => 'Monitor asociado a puesto de trabajo',
                ],
                [
                    'type' => 'keyboard',
                    'brand' => 'HP',
                    'model' => 'KB57P',
                    'serial_number' => 'BDMEP0AHH8N0QX',
                    'notes' => 'Teclado USB estándar HP',
                ],
                [
                    'type' => 'mouse',
                    'brand' => 'HP',
                    'model' => 'MOFYKO',
                    'serial_number' => 'FCMHF0BAU7BE94',
                    'notes' => 'Mouse óptico USB HP',
                ],
            ],
        ],
        'MXL52437GL' => [
            'custodian_name' => 'CRISPIN GARCIA',
            'custodian_id_number' => 'V-7299820',
            'custodian_department' => 'COMERCIALES / GESTION ENERGIA',
            'phone_extension' => '0412-3140425',
            'operating_system' => 'Ubuntu 22.04.2 LTS',
            'peripherals' => [
                [
                    'type' => 'monitor',
                    'brand' => 'Hewlett Packard',
                    'model' => 'HP V193b',
                    'serial_number' => '3CQ51323JG',
                    'notes' => 'Monitor asociado a puesto de trabajo',
                ],
                [
                    'type' => 'keyboard',
                    'brand' => 'HP',
                    'model' => 'KB57P',
                    'serial_number' => 'BDMEP0AHH8Q23E',
                    'notes' => 'Teclado USB estándar HP',
                ],
                [
                    'type' => 'mouse',
                    'brand' => 'HP',
                    'model' => 'M-SBJ96',
                    'serial_number' => 'FCMHF0AHD8BL6S',
                    'notes' => 'Mouse óptico USB HP',
                ],
            ],
        ],
        'MXL52434R7' => [
            'custodian_name' => 'ALEIDA MESA',
            'custodian_id_number' => 'V-14702507',
            'custodian_department' => 'COMERCIALES / GESTION ENERGIA',
            'phone_extension' => '0412-9679477',
            'operating_system' => 'Ubuntu 22.04.5 LTS',
            'peripherals' => [
                [
                    'type' => 'monitor',
                    'brand' => 'Hewlett Packard',
                    'model' => 'HP V193b',
                    'serial_number' => '3CQ51323GQ',
                    'notes' => 'Monitor asociado a puesto de trabajo',
                ],
                [
                    'type' => 'keyboard',
                    'brand' => 'HP',
                    'model' => 'KB57P',
                    'serial_number' => 'BDMEP0CDP7B8AF',
                    'notes' => 'Teclado USB estándar HP',
                ],
                [
                    'type' => 'mouse',
                    'brand' => 'HP',
                    'model' => 'M-SBJ96',
                    'serial_number' => '23-049028',
                    'notes' => 'Mouse óptico USB HP',
                ],
                [
                    'type' => 'surge_protector',
                    'brand' => 'CDP',
                    'model' => 'R2C-AVR1008',
                    'serial_number' => '150407-0000538',
                    'notes' => 'Regulador de voltaje CDP',
                ],
            ],
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $this->info('========================================================================');
        $this->info('Sincronización y Enriquecimiento de Puestos de Trabajo desde Archivo');
        $this->info('========================================================================');
        if ($isDryRun) {
            $this->warn('MODO SIMULACIÓN (DRY-RUN): No se aplicarán cambios a MariaDB.');
        }

        $processedCount = 0;
        $peripheralsAdded = 0;
        $peripheralsUpdated = 0;

        DB::beginTransaction();

        try {
            foreach ($this->stationsData as $serial => $data) {
                /** @var InventoryAsset|null $asset */
                $asset = InventoryAsset::where('serial_number', $serial)->first();

                if (!$asset) {
                    $this->warn("No se encontró activo con serial [{$serial}] en MariaDB. Omitiendo.");
                    continue;
                }

                $this->line("Procesando equipo: [ID: {$asset->id}] {$asset->name} (Serial: {$serial})");
                $this->line("  -> Responsable: {$data['custodian_name']} | C.I.: {$data['custodian_id_number']} | Unidad: {$data['custodian_department']}");

                $oldCustodian = $asset->custodian_name;
                $oldCI = $asset->custodian_id_number;
                $oldDept = $asset->custodian_department;

                if (!$isDryRun) {
                    // 1. Actualizar datos en InventoryAsset (manteniendo intacto el Nombre Etiqueta)
                    $asset->custodian_name = $data['custodian_name'];
                    $asset->custodian_id_number = $data['custodian_id_number'];
                    $asset->custodian_department = $data['custodian_department'];
                    $asset->save();

                    // 2. Gestionar Asignación Activa en InventoryAssignment
                    $activeAssignment = InventoryAssignment::where('inventory_asset_id', $asset->id)
                        ->where('status', 'active')
                        ->first();

                    if ($activeAssignment) {
                        $activeAssignment->update([
                            'custodian_name' => $data['custodian_name'],
                            'custodian_id_number' => $data['custodian_id_number'],
                            'custodian_department' => $data['custodian_department'],
                            'phone_extension' => $data['phone_extension'],
                        ]);
                    } else {
                        $year = Carbon::now()->format('Y');
                        $countYear = InventoryAssignment::whereYear('created_at', (int) $year)->count() + 1;
                        $code = sprintf("UINF-4.05-%s-%04d", $year, $countYear);

                        $activeAssignment = InventoryAssignment::create([
                            'assignment_code' => $code,
                            'inventory_asset_id' => $asset->id,
                            'custodian_name' => $data['custodian_name'],
                            'custodian_id_number' => $data['custodian_id_number'],
                            'custodian_department' => $data['custodian_department'],
                            'phone_extension' => $data['phone_extension'],
                            'monitored_site_id' => $asset->monitored_site_id ?? 1,
                            'movement_type' => 'assignment',
                            'provenance' => 'stock',
                            'status' => 'active',
                            'assigned_at' => Carbon::now(),
                            'observations' => 'Asignación actualizada con censo de equipos de la sede',
                        ]);
                    }
                }

                // 3. Procesar Periféricos
                foreach ($data['peripherals'] as $p) {
                    $pType = $p['type'];
                    $pBrand = $p['brand'];
                    $pModel = $p['model'];
                    $pSerial = $p['serial_number'];
                    $pNotes = $p['notes'];

                    if ($pType === 'monitor') {
                        $existingMonitor = InventoryPeripheral::where('current_asset_id', $asset->id)
                            ->where('type', 'monitor')
                            ->first();

                        if ($existingMonitor) {
                            $this->line("  -> Monitor existente ID {$existingMonitor->id}: serial actualizado a {$pSerial}");
                            if (!$isDryRun) {
                                $existingMonitor->serial_number = $pSerial;
                                $existingMonitor->brand = $pBrand;
                                $existingMonitor->model = $pModel;
                                $existingMonitor->status = 'assigned';
                                $existingMonitor->save();
                            }
                            $peripheralsUpdated++;
                        } else {
                            $this->line("  -> Creando nuevo Monitor: {$pBrand} {$pModel} ({$pSerial})");
                            if (!$isDryRun) {
                                $this->createPeripheral($asset, $pType, $pBrand, $pModel, $pSerial, $pNotes);
                            }
                            $peripheralsAdded++;
                        }
                    } else {
                        // Teclado, Mouse, Regulador, Teléfono
                        $existingPer = InventoryPeripheral::where('serial_number', $pSerial)->first();

                        if ($existingPer) {
                            $this->line("  -> Periférico existente [{$pType}] Serial {$pSerial} asociado a activo {$asset->id}");
                            if (!$isDryRun) {
                                $existingPer->current_asset_id = $asset->id;
                                $existingPer->status = 'assigned';
                                $existingPer->save();
                            }
                            $peripheralsUpdated++;
                        } else {
                            $this->line("  -> Creando y vinculando Periférico [{$pType}]: {$pBrand} {$pModel} ({$pSerial})");
                            if (!$isDryRun) {
                                $this->createPeripheral($asset, $pType, $pBrand, $pModel, $pSerial, $pNotes);
                            }
                            $peripheralsAdded++;
                        }
                    }
                }

                if (!$isDryRun) {
                    // Actualizar peripherals_json en el acta/asignación activa
                    $attached = InventoryPeripheral::where('current_asset_id', $asset->id)->get();
                    $peripheralsJson = $attached->map(fn($item) => [
                        'type' => strtoupper($item->type_label),
                        'brand' => $item->brand,
                        'model' => $item->model,
                        'serial_number' => $item->serial_number,
                        'observations' => $item->notes ?? '',
                    ])->values()->all();

                    /** @var InventoryAssignment|null $activeAssignment */
                    $activeAssignment = InventoryAssignment::where('inventory_asset_id', $asset->id)
                        ->where('status', 'active')
                        ->first();

                    if ($activeAssignment) {
                        $activeAssignment->update([
                            'peripherals_json' => $peripheralsJson,
                        ]);
                    }

                    // 4. Registro de Auditoría
                    AuditLog::create([
                        'user_id' => 1,
                        'user_name' => 'Sistema (Sincronización Archivo)',
                        'user_role' => 'admin',
                        'event' => 'updated',
                        'module' => 'inventory',
                        'auditable_type' => InventoryAsset::class,
                        'auditable_id' => $asset->id,
                        'entity_name' => $asset->name,
                        'entity_label' => $asset->display_identity ?? $asset->name,
                        'description' => "Enriquecimiento de datos y periféricos para el puesto {$asset->name} desde formato de inventario",
                        'old_values' => [
                            'custodian_name' => $oldCustodian,
                            'custodian_id_number' => $oldCI,
                            'custodian_department' => $oldDept,
                        ],
                        'new_values' => [
                            'custodian_name' => $data['custodian_name'],
                            'custodian_id_number' => $data['custodian_id_number'],
                            'custodian_department' => $data['custodian_department'],
                            'phone_extension' => $data['phone_extension'],
                            'peripherals_count' => $attached->count(),
                        ],
                    ]);
                }

                $processedCount++;
            }

            if ($isDryRun) {
                DB::rollBack();
                $this->info('========================================================================');
                $this->info("SIMULACIÓN COMPLETADA: {$processedCount} puestos evaluados exitosamente.");
                $this->info("Periféricos a crear: {$peripheralsAdded} | Periféricos a actualizar: {$peripheralsUpdated}");
                $this->info('========================================================================');
            } else {
                DB::commit();
                $this->info('========================================================================');
                $this->info("ACTUALIZACIÓN EXITOSA: {$processedCount} puestos enriquecidos en MariaDB.");
                $this->info("Periféricos creados: {$peripheralsAdded} | Periféricos actualizados: {$peripheralsUpdated}");
                $this->info('========================================================================');
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error durante la sincronización: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Crea un periférico con código de etiqueta unificado.
     */
    protected function createPeripheral(
        InventoryAsset $asset,
        string $type,
        string $brand,
        string $model,
        string $serial,
        string $notes
    ): InventoryPeripheral {
        $prefix = match ($type) {
            'monitor' => 'MON',
            'keyboard' => 'KBD',
            'mouse' => 'MSE',
            'surge_protector' => 'PWR',
            'other' => 'TEL',
            default => 'PER',
        };

        $assetTag = $prefix . '-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 6));

        return InventoryPeripheral::create([
            'asset_tag' => $assetTag,
            'type' => $type,
            'brand' => $brand,
            'model' => $model,
            'serial_number' => $serial,
            'status' => 'assigned',
            'monitored_site_id' => $asset->monitored_site_id ?? 1,
            'current_asset_id' => $asset->id,
            'notes' => $notes,
        ]);
    }
}
