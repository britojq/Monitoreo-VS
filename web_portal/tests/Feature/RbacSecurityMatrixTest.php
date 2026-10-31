<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RbacSecurityMatrixTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $operatorUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Usuario Administrador para pruebas
        $this->adminUser = User::create([
            'name' => 'Admin RBAC Test',
            'email' => 'admin_rbac_' . uniqid() . '@empresa.gob.ve',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Usuario Operador con permisos por defecto
        $this->operatorUser = User::create([
            'name' => 'Operador RBAC Test',
            'email' => 'operator_rbac_' . uniqid() . '@empresa.gob.ve',
            'password' => bcrypt('password123'),
            'role' => 'operator',
            'permissions' => null, // Carga permisos por defecto
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: El Administrador tiene acceso irrestricto a pantallas de gobernanza y configuración.
     */
    public function test_admin_has_full_access_to_governance_and_management_routes(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.users.index'));
        $response->assertStatus(200);

        $responseBans = $this->actingAs($this->adminUser)->get(route('admin.bans.index'));
        $responseBans->assertStatus(200);

        $responseSettings = $this->actingAs($this->adminUser)->get(route('admin.settings.advanced'));
        $responseSettings->assertStatus(200);
    }

    /**
     * Test 2: El Operador estándar NO puede acceder a rutas de administración y recibe 403 Forbidden sin ser baneado.
     */
    public function test_operator_is_denied_from_admin_governance_routes_with_403_and_not_banned(): void
    {
        $response = $this->actingAs($this->operatorUser)->get(route('admin.users.index'));
        $response->assertStatus(403);

        $responseBans = $this->actingAs($this->operatorUser)->get(route('admin.bans.index'));
        $responseBans->assertStatus(403);

        $responseSettings = $this->actingAs($this->operatorUser)->get(route('admin.settings.advanced'));
        $responseSettings->assertStatus(403);

        // Certificar que el usuario NO fue suspendido/baneado
        $this->operatorUser->refresh();
        $this->assertTrue((bool) $this->operatorUser->is_active);
        $this->assertNull($this->operatorUser->banned_at);
    }

    /**
     * Test 3: El Operador por defecto puede consultar pantallas operativas permitidas.
     */
    public function test_operator_can_view_authorized_monitoring_screens(): void
    {
        $responseServices = $this->actingAs($this->operatorUser)->get(route('admin.services.index'));
        $responseServices->assertStatus(200);

        $responseUps = $this->actingAs($this->operatorUser)->get(route('admin.ups.index'));
        $responseUps->assertStatus(200);

        $responseAssets = $this->actingAs($this->operatorUser)->get(route('admin.inventory.assets.index'));
        $responseAssets->assertStatus(200);

        $responseDeploy = $this->actingAs($this->operatorUser)->get(route('admin.inventory.deploy'));
        $responseDeploy->assertStatus(200);
    }

    /**
     * Test 4: El Operador sin permiso inventory.deploy NO puede ejecutar despliegue remoto ni escaneo de subred.
     */
    public function test_operator_without_inventory_deploy_permission_cannot_execute_deployment_or_subnet_scan(): void
    {
        // Intento de desplegar agente vía POST
        $responseDeploy = $this->actingAs($this->operatorUser)->postJson(route('admin.inventory.deploy.execute'), [
            'host' => '192.168.1.200',
            'os' => 'windows',
            'user' => 'Administrator',
            'password' => 'secret',
            'target' => 'dev',
            'action' => 'install',
        ]);
        $responseDeploy->assertStatus(403);

        // Intento de escanear subred vía POST
        $responseScan = $this->actingAs($this->operatorUser)->postJson(route('admin.inventory.deploy.scan-subnet'), [
            'subnet' => '192.168.1.0/24',
        ]);
        $responseScan->assertStatus(403);
    }

    /**
     * Test 5: Un Operador con permiso inventory.deploy explícito puede ejecutar escaneos de subred.
     */
    public function test_operator_with_granted_inventory_deploy_can_scan_subnet(): void
    {
        $this->operatorUser->update([
            'permissions' => ['inventory.view', 'inventory.deploy'],
        ]);

        $this->assertTrue($this->operatorUser->hasPermission('inventory.deploy'));

        $responseScan = $this->actingAs($this->operatorUser)->postJson(route('admin.inventory.deploy.scan-subnet'), [
            'subnet' => '192.168.1.0/24',
        ]);
        // Debe procesarse o responder exitoso (200)
        $responseScan->assertStatus(200);
        $this->assertTrue($responseScan->json('success'));
    }

    /**
     * Test 6: Revocar un permiso de visualización (ej. ups.view) bloquea efectivamente la pantalla con 403.
     */
    public function test_revoking_view_permission_blocks_screen_with_403(): void
    {
        // Operador con permisos donde se excluye deliberadamente ups.view
        $this->operatorUser->update([
            'permissions' => ['infra.view', 'inventory.view'],
        ]);

        $this->assertFalse($this->operatorUser->hasPermission('ups.view'));

        $responseUps = $this->actingAs($this->operatorUser)->get(route('admin.ups.index'));
        $responseUps->assertStatus(403);
    }

    /**
     * Test 7: Ocultamiento de interfaz: Operador sin permisos NO ve botones de acción en la vista de Despliegue.
     */
    public function test_frontend_hides_action_buttons_from_unauthorized_operator(): void
    {
        $response = $this->actingAs($this->operatorUser)->get(route('admin.inventory.deploy'));
        $response->assertStatus(200);

        // El operador NO debe ver el botón de Escanear Segmento ni Desplegar Agente
        $response->assertDontSee('Escanear Segmento');
        $response->assertDontSee('Instalar Agente GLPI');
        $response->assertDontSee('Catalogar en Inventario');

        // El Administrador SÍ debe ver dichos botones
        $adminResponse = $this->actingAs($this->adminUser)->get(route('admin.inventory.deploy'));
        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('Escanear Segmento');
        $adminResponse->assertSee('Guía de Instalación GLPI');
    }

    /**
     * Test 8: Cumplimiento de Reglas de Oro en vistas Blade (cero términos vetados).
     */
    public function test_views_comply_with_banned_terms_rules(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.inventory.deploy'));
        $response->assertDontSee('360°');
        $response->assertDontSee('360');
        $response->assertDontSee('custodio');
        $response->assertDontSee('Custodio');

        $responseAssignments = $this->actingAs($this->adminUser)->get(route('admin.inventory.assignments.index'));
        $responseAssignments->assertDontSee('custodio');
        $responseAssignments->assertDontSee('Custodio');

        $responseMaintenance = $this->actingAs($this->adminUser)->get(route('admin.inventory.maintenance.index'));
        $responseMaintenance->assertDontSee('custodio');
        $responseMaintenance->assertDontSee('Custodio');
    }

    /**
     * Test 9: Revalidación de nuevas funciones: Operador puede consultar IPAM y Centro de Reportes.
     */
    public function test_operator_can_access_new_features_ipam_and_reports(): void
    {
        // 1. IPAM Grid
        $responseIpam = $this->actingAs($this->operatorUser)->get(route('admin.ipam.index'));
        $responseIpam->assertStatus(200);

        $responseIpamGrid = $this->actingAs($this->operatorUser)->getJson(route('admin.ipam.grid'));
        $responseIpamGrid->assertStatus(200);

        // 2. Centro de Reportes
        $responseReports = $this->actingAs($this->operatorUser)->get(route('admin.reports.index'));
        $responseReports->assertStatus(200);

        $responseReportExport = $this->actingAs($this->operatorUser)->get(route('admin.reports.export', ['type' => 'census', 'format' => 'csv']));
        $responseReportExport->assertStatus(200);
    }

    /**
     * Test 10: Permisos de Asignación e ITAM: Creación de actas UINF-4.05 requiere inventory.assign o inventory.manage.
     */
    public function test_assignment_actas_require_assign_or_manage_permission(): void
    {
        // Sin permiso inventory.assign ni inventory.manage -> 403 en create
        $this->operatorUser->update([
            'permissions' => ['inventory.view'],
        ]);
        $responseDenied = $this->actingAs($this->operatorUser->fresh())->get(route('admin.inventory.assignments.create'));

        // Con permiso inventory.assign -> 200 en create
        $this->operatorUser->update([
            'permissions' => ['inventory.view', 'inventory.assign'],
        ]);
        $responseAllowedAssign = $this->actingAs($this->operatorUser->fresh())->get(route('admin.inventory.assignments.create'));

        // Con permiso inventory.manage -> 200 en create
        $this->operatorUser->update([
            'permissions' => ['inventory.view', 'inventory.manage'],
        ]);
        $responseAllowedManage = $this->actingAs($this->operatorUser->fresh())->get(route('admin.inventory.assignments.create'));

        $responseDenied->assertStatus(403);
        $responseAllowedAssign->assertStatus(200);
        $responseAllowedManage->assertStatus(200);
    }

    /**
     * Test 11: Auditoría y consistencia del censo de usuarios reales en MariaDB.
     */
    public function test_real_users_in_database_have_coherent_roles_and_permissions(): void
    {
        $users = User::all();
        $this->assertGreaterThanOrEqual(4, $users->count());

        $defaultOpKeys = PermissionService::getDefaultOperatorPermissions();
        $allKeys = PermissionService::getAllPermissionKeys();

        foreach ($users as $user) {
            $this->assertTrue((bool)$user->is_active, "Usuario [{$user->name}] debe estar activo.");
            $this->assertContains($user->role, ['admin', 'operator', 'SuperAdmin']);

            $effective = $user->getEffectivePermissions();

            if ($user->isAdmin()) {
                // Administradores deben tener el 100% de los permisos (63)
                $this->assertCount(count($allKeys), $effective);
            } else {
                // Operadores deben contar con al menos los permisos por defecto
                foreach ($defaultOpKeys as $defKey) {
                    $this->assertTrue(
                        in_array($defKey, $effective, true),
                        "Operador [{$user->name}] carece del permiso por defecto [{$defKey}]."
                    );
                }
            }
        }
    }
}
