<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryPeripheral;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SyncInventoryFromExcelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dry_run_executes_successfully(): void
    {
        $this->artisan('inventory:sync-from-excel', ['--dry-run' => true])
            ->expectsOutputToContain('MODO SIMULACIÓN (DRY-RUN)')
            ->expectsOutputToContain('SIMULACIÓN COMPLETADA')
            ->assertExitCode(0);
    }

    public function test_sync_command_updates_asset_custodians_and_preserves_label_names(): void
    {
        $this->artisan('inventory:sync-from-excel')
            ->expectsOutputToContain('ACTUALIZACIÓN EXITOSA')
            ->assertExitCode(0);

        // Verificar activo CENCARPROC-2001
        $asset = InventoryAsset::where('serial_number', 'MXL5242234')->first();
        $this->assertNotNull($asset);
        $this->assertSame('CENCARPROC-2001', $asset->name); // Nombre Etiqueta intacto
        $this->assertSame('YUSNEIDY BARELA', $asset->custodian_name);
        $this->assertSame('V-14243365', $asset->custodian_id_number);
        $this->assertSame('PROCURA', $asset->custodian_department);

        // Verificar asignación activa
        $assignment = InventoryAssignment::where('inventory_asset_id', $asset->id)
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($assignment);
        $this->assertSame('YUSNEIDY BARELA', $assignment->custodian_name);
        $this->assertSame('0412-5321300', $assignment->phone_extension);

        // Verificar periféricos asociados
        $peripherals = InventoryPeripheral::where('current_asset_id', $asset->id)->get();
        $this->assertGreaterThanOrEqual(4, $peripherals->count());

        $hasKeyboard = $peripherals->contains(fn($p) => $p->type === 'keyboard' && $p->serial_number === 'BDMEP0CDP7B0CS');
        $hasMouse = $peripherals->contains(fn($p) => $p->type === 'mouse' && $p->serial_number === 'FCMHF0BAU7BRM0');
        $hasRegulator = $peripherals->contains(fn($p) => $p->type === 'surge_protector' && $p->serial_number === '150407-0000102');

        $this->assertTrue($hasKeyboard, 'Debe contener el teclado asociado del formato');
        $this->assertTrue($hasMouse, 'Debe contener el ratón asociado del formato');
        $this->assertTrue($hasRegulator, 'Debe contener el regulador asociado del formato');
    }

    public function test_sync_command_handles_all_five_workstations(): void
    {
        $this->artisan('inventory:sync-from-excel')
            ->assertExitCode(0);

        $expectedWorkstations = [
            'MXL5242234' => 'YUSNEIDY BARELA',
            'MXL524368J' => 'FATIMA MUDINE',
            'MXL5243544' => 'JOSE L MUÑOZ',
            'MXL52437GL' => 'CRISPIN GARCIA',
            'MXL52434R7' => 'ALEIDA MESA',
        ];

        foreach ($expectedWorkstations as $serial => $expectedCustodian) {
            $asset = InventoryAsset::where('serial_number', $serial)->first();
            $this->assertNotNull($asset, "El activo con serial {$serial} debe existir");
            $this->assertSame($expectedCustodian, $asset->custodian_name);

            $assignment = InventoryAssignment::where('inventory_asset_id', $asset->id)
                ->where('status', 'active')
                ->first();
            $this->assertNotNull($assignment, "Debe tener asignación activa para {$expectedCustodian}");
            $this->assertSame($expectedCustodian, $assignment->custodian_name);
        }
    }
}
