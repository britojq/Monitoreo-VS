<?php

namespace Tests\Feature;

use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminSitesAccordionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Valida que la vista /admin/sites renderice correctamente con soporte
     * de acordeón desplegable, botón unificado de toggle y drag & drop.
     */
    public function test_admin_sites_renders_accordion_and_toggle_controls(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user, 'Debe existir un usuario administrador legítimo en la base de datos');

        $response = $this->actingAs($user)->get(route('admin.sites.index'));

        $response->assertStatus(200);
        $response->assertSee('toggleAdminSiteDevices', false);
        $response->assertSee('toggleAllAdminSites', false);
        $response->assertSee('btn-toggle-all-sites', false);
        $response->assertSee('Desplegar Todo', false);
        $response->assertSee('chevron-icon', false);
        $response->assertSee('admin-site-devices-', false);
        $response->assertSee('sites-sortable-list', false);
        $response->assertSee('drag_indicator', false);
        $response->assertSee('sites-toast', false);
    }

    /**
     * Valida que el endpoint /admin/sites/reorder actualice el sort_order
     * de las sedes de forma persistente en MariaDB como fuente centralizada.
     */
    public function test_admin_sites_reorder_endpoint_persists_sort_order(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user, 'Debe existir un usuario administrador legítimo en la base de datos');

        $sites = MonitoredSite::orderBy('sort_order')->take(3)->get();
        if ($sites->count() < 2) {
            $this->markTestSkipped('Se requieren al menos 2 sedes registradas para probar reordenamiento');
        }

        // Invertir el orden de las primeras dos sedes
        $reversedIds = $sites->pluck('id')->reverse()->values()->toArray();

        $response = $this->actingAs($user)->postJson(route('admin.sites.reorder'), [
            'order' => $reversedIds,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Verificar persistencia en MariaDB
        $firstId = $reversedIds[0];
        $secondId = $reversedIds[1];

        $firstSite = MonitoredSite::find($firstId);
        $secondSite = MonitoredSite::find($secondId);

        $this->assertEquals(1, $firstSite->sort_order);
        $this->assertEquals(2, $secondSite->sort_order);
    }
}
