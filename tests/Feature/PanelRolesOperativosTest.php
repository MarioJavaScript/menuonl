<?php

namespace Tests\Feature;

use App\Http\Responses\LoginRespuesta;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Services\PermisosPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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
            'propietario' => ['propietario', ['inicio', 'reservas', 'menu', 'pedidos', 'ajustes']],
            'gerente' => ['gerente', ['inicio', 'reservas', 'menu', 'pedidos', 'ajustes']],
            'editor' => ['editor', ['inicio', 'reservas', 'menu', 'pedidos']],
            'recepcion' => ['recepcion', ['inicio', 'reservas']],
            'cocina' => ['cocina', ['pedidos']],
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
        $pedido = Pedido::factory()->create(['restaurante_id' => $restaurante->id]);
        $base = "/api/v1/panel/{$restaurante->slug}";

        $matriz = [
            'recepcion' => [
                ['get', '/pedidos'], ['post', "/pedidos/{$pedido->id}/avanzar"], ['get', '/menu'], ['post', "/categorias/{$categoria->id}/productos"], ['get', '/equipo'],
            ],
            'cocina' => [
                ['get', '/reservas'], ['patch', "/reservas/{$reserva->id}"], ['get', '/menu'], ['get', '/resumen'], ['get', '/equipo'],
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

        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'estado' => 'nuevo']);
        $this->assertDatabaseHas('reservas', ['id' => $reserva->id, 'estado' => 'pendiente']);
    }

    public function test_each_role_can_use_its_own_modules(): void
    {
        $restaurante = Restaurante::factory()->create();
        $base = "/api/v1/panel/{$restaurante->slug}";

        $this->actingAs($this->conRol($restaurante, 'recepcion'))->getJson($base.'/reservas')->assertOk();
        $this->actingAs($this->conRol($restaurante, 'cocina'))->getJson($base.'/pedidos')->assertOk();
        $this->actingAs($this->conRol($restaurante, 'gerente'))->getJson($base.'/equipo')->assertOk();
        $this->actingAs($this->conRol($restaurante, 'gerente'))->getJson($base.'/menu')->assertOk();
    }

    public function test_summary_only_exposes_figures_of_the_roles_modules(): void
    {
        $restaurante = Restaurante::factory()->create();
        $base = "/api/v1/panel/{$restaurante->slug}/resumen";

        $recepcion = $this->actingAs($this->conRol($restaurante, 'recepcion'))->getJson($base)->assertOk()->json('data');
        $this->assertArrayHasKey('comensales_esperados', $recepcion);
        $this->assertArrayNotHasKey('ventas_hoy', $recepcion);
        $this->assertArrayNotHasKey('pedidos_activos', $recepcion);

        $editor = $this->actingAs($this->conRol($restaurante, 'editor'))->getJson($base)->json('data');
        $this->assertArrayHasKey('pedidos_activos', $editor);
        $this->assertArrayNotHasKey('ventas_hoy', $editor);

        $gerente = $this->actingAs($this->conRol($restaurante, 'gerente'))->getJson($base)->json('data');
        $this->assertArrayHasKey('ventas_hoy', $gerente);
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
        $b->usuarios()->attach($usuario, ['rol' => 'cocina']);

        $this->actingAs($usuario)->getJson("/api/v1/panel/{$a->slug}/equipo")->assertOk();
        $this->actingAs($usuario)->getJson("/api/v1/panel/{$b->slug}/equipo")->assertForbidden();
        $this->actingAs($usuario)->getJson("/api/v1/panel/{$b->slug}/pedidos")->assertOk();
    }

    public function test_operational_only_users_land_on_the_panel_after_login(): void
    {
        $restaurante = Restaurante::factory()->create();
        $cocina = $this->conRol($restaurante, 'cocina');
        $gerente = $this->conRol($restaurante, 'gerente');

        $this->assertTrue($cocina->esSoloOperativo());
        $this->assertFalse($gerente->esSoloOperativo());
        $this->assertFalse(User::factory()->superadmin()->create()->esSoloOperativo());

        $peticion = Request::create('/admin/login');
        $peticion->setUserResolver(fn () => $cocina);
        $this->assertStringEndsWith('/panel', (new LoginRespuesta)->toResponse($peticion)->getTargetUrl());
    }
}
