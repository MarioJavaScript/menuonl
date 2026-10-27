<?php

namespace Tests\Feature;

use App\Http\Responses\LoginRespuesta;
use App\Models\Categoria;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Services\Catalogos;
use App\Services\PermisosPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelRolesOperativosTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function conRol(Restaurante $restaurante, string $rol): User
    {
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario, ['rol' => $rol]);

        return $usuario;
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function modulosPorRol(): array
    {
        return [
            'propietario' => ['propietario', ['inicio', 'reservas', 'menu', 'ajustes']],
            'gerente' => ['gerente', ['inicio', 'reservas', 'menu', 'ajustes']],
            'editor' => ['editor', ['inicio', 'reservas', 'menu']],
            'recepcion' => ['recepcion', ['inicio', 'reservas']],
        ];
    }

    /**
     * @param  list<string>  $esperados
     *
     * @dataProvider modulosPorRol
     */
    #[DataProvider('modulosPorRol')]
    public function test_panel_lists_modules_for_each_role(string $rol, array $esperados): void
    {
        $restaurante = Restaurante::factory()->create();
        $usuario = $this->conRol($restaurante, $rol);

        $this->actingAs($usuario)->getJson('/api/v1/panel')
            ->assertOk()->assertJsonPath('data.0.modulos', $esperados);
    }

    public function test_every_endpoint_answers_403_for_modules_outside_the_role(): void
    {
        $restaurante = Restaurante::factory()->create();
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id]);
        $base = "/api/v1/panel/{$restaurante->slug}";

        $matriz = [
            'recepcion' => [
                ['get', '/menu'], ['post', "/categorias/{$categoria->id}/productos"], ['get', '/equipo'],
            ],
            'editor' => [
                ['get', '/equipo'], ['post', '/equipo/invitaciones'],
            ],
        ];

        foreach ($matriz as $rol => $llamadas) {
            $usuario = $this->conRol($restaurante, $rol);

            foreach ($llamadas as [$metodo, $ruta]) {
                $this->actingAs($usuario)->json($metodo, $base.$ruta, [])->assertForbidden();
            }
        }

        $this->assertDatabaseHas('reservas', ['id' => $reserva->id, 'estado' => 'pendiente']);
    }

    public function test_each_role_can_use_its_own_modules(): void
    {
        $restaurante = Restaurante::factory()->create();
        $base = "/api/v1/panel/{$restaurante->slug}";

        $this->actingAs($this->conRol($restaurante, 'recepcion'))->getJson($base.'/reservas')->assertOk();
        $this->actingAs($this->conRol($restaurante, 'gerente'))->getJson($base.'/equipo')->assertOk();
        $this->actingAs($this->conRol($restaurante, 'gerente'))->getJson($base.'/menu')->assertOk();
    }

    public function test_summary_only_exposes_figures_of_the_roles_modules(): void
    {
        $restaurante = Restaurante::factory()->create();
        $base = "/api/v1/panel/{$restaurante->slug}/resumen";

        $recepcion = $this->actingAs($this->conRol($restaurante, 'recepcion'))->getJson($base)->assertOk()->json('data');
        $this->assertArrayHasKey('comensales_esperados', $recepcion);

        foreach (['recepcion' => $recepcion, 'editor' => $this->actingAs($this->conRol($restaurante, 'editor'))->getJson($base)->json('data'), 'gerente' => $this->actingAs($this->conRol($restaurante, 'gerente'))->getJson($base)->json('data')] as $rol => $datos) {
            $this->assertArrayNotHasKey('ventas_hoy', $datos, $rol);
            $this->assertArrayNotHasKey('pedidos_activos', $datos, $rol);
        }
    }

    public function test_deactivated_member_loses_all_access(): void
    {
        $restaurante = Restaurante::factory()->create();
        $usuario = $this->conRol($restaurante, 'recepcion');
        $restaurante->usuarios()->updateExistingPivot($usuario->id, ['activo' => false]);
        $usuario = $usuario->fresh();

        $this->actingAs($usuario)->getJson("/api/v1/panel/{$restaurante->slug}/reservas")->assertNotFound();
        $this->actingAs($usuario)->getJson('/api/v1/panel')->assertJsonCount(0, 'data');
        $this->assertFalse($usuario->canAccessTenant($restaurante));
        $this->assertSame([], PermisosPanel::modulosDe($usuario, $restaurante->id));
    }

    public function test_roles_are_per_restaurant(): void
    {
        $a = Restaurante::factory()->create();
        $b = Restaurante::factory()->create();
        $usuario = $this->conRol($a, 'gerente');
        $b->usuarios()->attach($usuario, ['rol' => 'recepcion']);

        $this->actingAs($usuario)->getJson("/api/v1/panel/{$a->slug}/equipo")->assertOk();
        $this->actingAs($usuario)->getJson("/api/v1/panel/{$b->slug}/equipo")->assertForbidden();
        $this->actingAs($usuario)->getJson("/api/v1/panel/{$b->slug}/reservas")->assertOk();
    }

    public function test_operational_only_users_land_on_the_panel_after_login(): void
    {
        $restaurante = Restaurante::factory()->create();
        $recepcion = $this->conRol($restaurante, 'recepcion');
        $gerente = $this->conRol($restaurante, 'gerente');

        $this->assertTrue($recepcion->esSoloOperativo());
        $this->assertFalse($gerente->esSoloOperativo());
        $this->assertFalse(User::factory()->superadmin()->create()->esSoloOperativo());

        $peticion = Request::create('/admin/login');
        $peticion->setUserResolver(fn () => $recepcion);
        $this->assertStringEndsWith('/panel', (new LoginRespuesta)->toResponse($peticion)->getTargetUrl());
    }

    public function test_las_rutas_de_pedidos_manuales_ya_no_existen_para_nadie(): void
    {
        $restaurante = Restaurante::factory()->create();
        $base = "/api/v1/panel/{$restaurante->slug}";

        foreach (['propietario', 'gerente', 'editor', 'recepcion'] as $rol) {
            $usuario = $this->conRol($restaurante, $rol);

            $this->actingAs($usuario)->getJson($base.'/pedidos')->assertNotFound();
            $this->actingAs($usuario)->postJson($base.'/pedidos', [])->assertNotFound();
            $this->actingAs($usuario)->postJson($base.'/pedidos/1/avanzar')->assertNotFound();
        }
    }

    public function test_el_rol_cocina_y_el_modulo_pedidos_ya_no_existen_en_el_catalogo(): void
    {
        $this->assertNotContains('pedidos', PermisosPanel::MODULOS);
        $this->assertArrayNotHasKey('cocina', Catalogos::roles());
        $this->assertFalse(Schema::hasTable('pedidos'));
    }

    public function test_quien_se_queda_sin_membresias_activas_recibe_el_aviso_en_vez_de_una_pantalla_en_blanco(): void
    {
        $restaurante = Restaurante::factory()->create();
        $usuario = $this->conRol($restaurante, 'recepcion');
        $restaurante->usuarios()->updateExistingPivot($usuario->id, ['activo' => false]);

        $this->actingAs($usuario->fresh())->getJson('/api/v1/panel')->assertOk()->assertExactJson(['data' => []]);

        $js = (string) file_get_contents(resource_path('js/panel.js'));
        $this->assertStringContainsString('Tu acceso está desactivado. Pídele al propietario del restaurante que lo revise.', $js);
        $this->assertStringContainsString('if(!S.restaurantes.length)', $js, 'El aviso debe salir antes de leer el primer restaurante.');
        $this->assertStringNotContainsString('/pedidos', $js);
        $this->assertStringNotContainsString('cocina', $js);
    }
}
