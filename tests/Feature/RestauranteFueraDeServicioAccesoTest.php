<?php

namespace Tests\Feature;

use App\Enums\MotivoDePausa;
use App\Models\CodigoQr;
use App\Models\Restaurante;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Regla única de acceso al panel: un restaurante fuera de servicio (estado "suspendido") deja al equipo fuera,
 * por el panel y por la descarga de QR. El superadmin conserva el acceso.
 */
class RestauranteFueraDeServicioAccesoTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesDelEquipo(): array
    {
        return [
            'propietario' => ['propietario'],
            'gerente' => ['gerente'],
            'editor' => ['editor'],
            'recepcion' => ['recepcion'],
        ];
    }

    private function suspendido(): Restaurante
    {
        return Restaurante::factory()->suspendido()->create();
    }

    public function test_el_estado_suspendido_se_expresa_como_motivo_fuera_de_servicio(): void
    {
        $activo = Restaurante::factory()->create();
        $suspendido = $this->suspendido();

        $this->assertNull($activo->motivoDePausa());
        $this->assertFalse($activo->estaPausado());
        $this->assertTrue($activo->permiteElPanelAlEquipo());

        $this->assertSame(MotivoDePausa::FueraDeServicio, $suspendido->motivoDePausa());
        $this->assertTrue($suspendido->estaPausado());
        $this->assertFalse($suspendido->permiteElPanelAlEquipo());
    }

    public function test_reactivar_el_restaurante_devuelve_el_acceso(): void
    {
        $restaurante = $this->suspendido();
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->assertFalse($usuario->canAccessTenant($restaurante));

        $restaurante->update(['estado' => 'activo']);

        $this->assertTrue($usuario->fresh()->canAccessTenant($restaurante->fresh()));
    }

    #[DataProvider('rolesDelEquipo')]
    public function test_el_equipo_de_un_restaurante_suspendido_no_entra_al_panel_ni_por_url_directa(string $rol): void
    {
        $restaurante = $this->suspendido();
        $usuario = $this->crearUsuario($rol, $restaurante);

        $this->assertFalse($usuario->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($usuario->canAccessTenant($restaurante));
        $this->assertTrue($usuario->getTenants(Filament::getPanel('admin'))->isEmpty());

        $this->actingAs($usuario)->get('/admin/'.$restaurante->slug.'/categorias')->assertStatus(403);
        $this->actingAs($usuario)->get('/admin')->assertForbidden();
    }

    public function test_con_dos_restaurantes_entra_al_activo_y_el_suspendido_sale_del_selector(): void
    {
        $activo = Restaurante::factory()->create();
        $suspendido = $this->suspendido();
        $usuario = User::factory()->create();
        $usuario->restaurantes()->attach($activo->id, ['rol' => 'propietario']);
        $usuario->restaurantes()->attach($suspendido->id, ['rol' => 'propietario']);

        $tenants = $usuario->getTenants(Filament::getPanel('admin'));

        $this->assertSame([$activo->id], $tenants->pluck('id')->all());
        $this->assertTrue($usuario->canAccessTenant($activo));
        $this->assertFalse($usuario->canAccessTenant($suspendido));

        $this->actingAs($usuario)->get('/admin/'.$activo->slug.'/categorias')->assertOk();
        $this->actingAs($usuario)->get('/admin/'.$suspendido->slug.'/categorias')->assertNotFound();
    }

    public function test_el_superadmin_sigue_entrando_a_un_restaurante_suspendido(): void
    {
        $restaurante = $this->suspendido();
        $superadmin = $this->crearUsuario('superadmin');

        $this->assertTrue($superadmin->canAccessTenant($restaurante));
        $this->assertTrue($superadmin->getTenants(Filament::getPanel('admin'))->contains('id', $restaurante->id));

        $this->actingAs($superadmin)->get('/admin/'.$restaurante->slug.'/categorias')->assertOk();
    }

    public function test_un_restaurante_suspendido_no_cuenta_como_plan_sin_panel(): void
    {
        $restaurante = $this->suspendido();
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->assertTrue($usuario->restaurantesSinPanelPorPlan()->isEmpty());
        $this->assertFalse($usuario->debeVerElAvisoDePlanSinPanel());
    }

    #[DataProvider('rolesDelEquipo')]
    public function test_el_equipo_no_descarga_qr_de_un_restaurante_suspendido(string $rol): void
    {
        $restaurante = $this->suspendido();
        $usuario = $this->crearUsuario($rol, $restaurante);
        $qr = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id, 'activo' => true]);

        foreach (['svg', 'png', 'pdf'] as $formato) {
            $this->actingAs($usuario)
                ->get(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => $formato]))
                ->assertForbidden();
        }
    }

    public function test_el_equipo_si_descarga_qr_cuando_el_restaurante_esta_activo(): void
    {
        $restaurante = Restaurante::factory()->create();
        $usuario = $this->crearUsuario('propietario', $restaurante);
        $qr = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id, 'activo' => true]);

        $this->actingAs($usuario)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'svg']))
            ->assertOk();
    }

    public function test_el_superadmin_descarga_qr_de_un_restaurante_suspendido(): void
    {
        $restaurante = $this->suspendido();
        $superadmin = $this->crearUsuario('superadmin');
        $qr = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id, 'activo' => true]);

        $this->actingAs($superadmin)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'svg']))
            ->assertOk();
    }
}
