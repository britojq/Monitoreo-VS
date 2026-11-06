<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. TABLA MAESTRA DE ACTIVOS DE TI
        Schema::create('inventory_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 64)->unique()->nullable()->comment('Código patrimonial o etiqueta de inventario');
            $table->string('name', 128)->comment('Nombre del host o activo');
            $table->string('type', 32)->default('desktop')->comment('desktop, laptop, server, switch, router, firewall, access_point, printer, ip_phone, ups, other');
            $table->string('status', 32)->default('in_stock')->comment('in_stock, assigned, in_maintenance, in_transit, decommissioned, damaged, unauthorized');
            $table->string('brand', 64)->nullable();
            $table->string('model', 96)->nullable();
            $table->string('serial_number', 128)->nullable()->index();
            $table->string('mac_address', 48)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            
            // Relaciones físicas y lógicas
            $table->foreignId('monitored_site_id')->nullable()->constrained('monitored_sites')->nullOnDelete();
            $table->string('location_detail', 191)->nullable()->comment('Piso, departamento u oficina específica');
            $table->foreignId('monitored_network_device_id')->nullable()->constrained('monitored_network_devices')->nullOnDelete();
            $table->foreignId('current_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Custodio (Datos de LDAP o trabajador físico)
            $table->string('custodian_name', 128)->nullable();
            $table->string('custodian_id_number', 32)->nullable();
            $table->string('custodian_department', 128)->nullable();
            
            // Sistema Operativo y Datos Técnicos
            $table->string('operating_system', 128)->nullable();
            $table->string('os_kernel', 64)->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_expiration')->nullable();
            $table->timestamp('last_glpi_sync_at')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. COMPONENTES INTERNOS DE HARDWARE
        Schema::create('inventory_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_asset_id')->constrained('inventory_assets')->cascadeOnDelete();
            $table->string('type', 32)->comment('cpu, ram, disk, gpu, network_card, motherboard, power_supply, other');
            $table->string('name', 128)->comment('Ej: Kingston NV2 1TB, Intel Core i7');
            $table->string('brand', 64)->nullable();
            $table->string('model', 96)->nullable();
            $table->string('capacity', 64)->nullable()->comment('Ej: 16 GB, 1 TB NVMe, 8 Cores');
            $table->string('serial_number', 128)->nullable();
            $table->string('slot_or_bus', 64)->nullable()->comment('Ej: DIMM_1, SATA-0, PCIe-1');
            $table->string('status', 32)->default('active')->comment('active, defective, replaced');
            $table->timestamps();
        });

        // 3. HISTORIAL DE CAMBIOS EN COMPONENTES (AUDITORÍA FORENSE DE HARDWARE)
        Schema::create('inventory_components_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_asset_id')->constrained('inventory_assets')->cascadeOnDelete();
            $table->string('action', 32)->comment('added, removed, modified');
            $table->string('component_type', 32);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('reason', 191)->nullable();
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
        });

        // 4. PERIFÉRICOS INDEPENDIENTES (MONITORES, TECLADOS, MOUSE, PROTECTORES)
        Schema::create('inventory_peripherals', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 64)->unique()->nullable();
            $table->string('type', 32)->comment('monitor, keyboard, mouse, headset, ups, surge_protector, scanner, other');
            $table->string('brand', 64)->nullable();
            $table->string('model', 96)->nullable();
            $table->string('serial_number', 128)->nullable()->index();
            $table->string('status', 32)->default('in_stock')->comment('in_stock, assigned, in_maintenance, damaged, decommissioned');
            
            $table->foreignId('monitored_site_id')->nullable()->constrained('monitored_sites')->nullOnDelete();
            $table->foreignId('current_asset_id')->nullable()->constrained('inventory_assets')->nullOnDelete();
            $table->foreignId('current_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. ASIGNACIONES, TRASLADOS Y ACTAS DE CUSTODIA
        Schema::create('inventory_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('assignment_code', 64)->unique()->comment('Ej: ACTA-ASG-2026-0001');
            $table->foreignId('inventory_asset_id')->constrained('inventory_assets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Datos del Custodio
            $table->string('custodian_name', 128);
            $table->string('custodian_id_number', 32)->nullable();
            $table->string('custodian_department', 128)->nullable();
            $table->string('custodian_role', 96)->nullable();
            
            $table->foreignId('monitored_site_id')->nullable()->constrained('monitored_sites')->nullOnDelete();
            $table->string('movement_type', 32)->default('assignment')->comment('assignment, transfer, return, loan');
            $table->json('peripherals_json')->nullable()->comment('Listado de periféricos entregados en el kit');
            
            $table->timestamp('assigned_at');
            $table->timestamp('returned_at')->nullable();
            $table->string('document_pdf_path', 255)->nullable();
            $table->string('status', 32)->default('active')->comment('active, completed, canceled');
            
            $table->foreignId('delivered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->timestamps();
        });

        // 6. TICKETS DE MANTENIMIENTO PREVENTIVO Y CORRECTIVO
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_code', 64)->unique()->comment('Ej: MNT-2026-0001');
            $table->foreignId('inventory_asset_id')->constrained('inventory_assets')->cascadeOnDelete();
            $table->string('type', 32)->default('preventive')->comment('preventive, corrective');
            $table->string('status', 32)->default('scheduled')->comment('scheduled, in_progress, completed, canceled');
            $table->string('priority', 32)->default('medium')->comment('low, medium, high, critical');
            $table->string('title', 191);
            $table->text('description')->nullable();
            
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('technician_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->decimal('cost', 10, 2)->default(0.00);
            $table->timestamps();
        });

        // 7. BITÁCORA FORENSE DE INGESTIÓN DEL AGENTE GLPI
        Schema::create('glpi_inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('user_agent', 191)->nullable();
            $table->string('device_name', 128)->nullable();
            $table->string('serial_number', 128)->nullable();
            $table->string('mac_address', 48)->nullable();
            $table->string('payload_format', 16)->default('json')->comment('json, xml');
            $table->longText('raw_payload');
            $table->boolean('processed')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('glpi_inventory_logs');
        Schema::dropIfExists('maintenance_tickets');
        Schema::dropIfExists('inventory_assignments');
        Schema::dropIfExists('inventory_peripherals');
        Schema::dropIfExists('inventory_components_histories');
        Schema::dropIfExists('inventory_components');
        Schema::dropIfExists('inventory_assets');
    }
};
