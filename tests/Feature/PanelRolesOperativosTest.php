<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Reservas;
use App\Http\Responses\LoginRespuesta;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Services\Catalogos;
use App\Services\PermisosPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Roles heredados (gerente, editor, recepción) sin el /panel viejo: sus módulos siguen saliendo de PermisosPanel y la
 * recepción entra por /admin directo a Reservas.
 */
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
     * El destino que Filament da tras iniciar sesión, sin pasar por la pantalla de login.
     */
    private function destinoTrasIniciarSesion(User $usuario): string
    {
        $peticion = Request::create('/admin/login');
        $peticion->setUserResolver(fn () => $usuario);
        $this->actingAs($usuario);

        return (new LoginRespuesta)->toResponse($peticion)->getTargetUrl();
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
     */
    #[DataProvider('modulosPorRol')]
    public function test_cada_rol_tiene_sus_modulos(string $rol, array $esperados): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->assertSame($esperados, PermisosPanel::modulosDe($this->conRol($restaurante, $rol), $restaurante));
    }

    public function test_el_rol_se_resuelve_por_restaurante(): void
    {
        $a = Restaurante::factory()->create();
        $b = Restaurante::factory()->create();
        $usuario = $this->conRol($a, 'gerente');
        $b->usuarios()->attach($usuario, ['rol' => 'recepcion']);

        $this->assertTrue(PermisosPanel::permite($usuario, $a, 'ajustes'));
        $this->assertFalse(PermisosPanel::permite($usuario, $b, 'ajustes'));
        $this->assertTrue(PermisosPanel::permite($usuario, $b, 'reservas'));
    }

    public function test_un_miembro_desactivado_pierde_todo_el_acceso(): void
    {
        $restaurante = Restaurante::factory()->create();
        $usuario = $this->conRol($restaurante, 'recepcion');
        $restaurante->usuarios()->updateExistingPivot($usuario->id, ['activo' => false]);
        $usuario = $usuario->fresh();

        $this->assertFalse($usuario->canAccessTenant($restaurante));
        $this->assertSame([], PermisosPanel::modulosDe($usuario, $restaurante->id));
        $this->assertFalse($usuario->esSoloOperativo(), 'Sin membresías activas no es "solo recepción".');
        $this->actingAs($usuario)->get(Reservas::getUrl(tenant: $restaurante))->assertStatus(403);
        $this->actingAs($usuario)->get('/admin')->assertStatus(403);
    }

    public function test_solo_la_recepcion_cuenta_como_solo_operativa(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->assertTrue($this->conRol($restaurante, 'recepcion')->esSoloOperativo());
        $this->assertFalse($this->conRol($restaurante, 'gerente')->esSoloOperativo());
        $this->assertFalse($this->conRol($restaurante, 'propietario')->esSoloOperativo());
        $this->assertFalse(User::factory()->superadmin()->create()->esSoloOperativo());
    }

    public function test_la_recepcion_va_a_reservas_de_su_restaurante_y_no_al_panel_viejo(): void
    {
        $restaurante = Restaurante::factory()->create(['slug' => 'la-esquina']);
        $recepcion = $this->conRol($restaurante, 'recepcion');

        $destino = $this->destinoTrasIniciarSesion($recepcion);

        $this->assertSame(Reservas::getUrl(tenant: $restaurante), $destino);
        $this->assertStringNotContainsString('/panel', parse_url($destino, PHP_URL_PATH));
        $this->actingAs($recepcion)->get($destino)->assertOk();
    }

    public function test_con_varios_restaurantes_va_al_primero_por_nombre_donde_tiene_reservas(): void
    {
        $zeta = Restaurante::factory()->create(['nombre' => 'Zeta', 'slug' => 'zeta']);
        $alfa = Restaurante::factory()->create(['nombre' => 'Alfa', 'slug' => 'alfa']);
        $solo = Restaurante::factory()->soloMenu()->create(['nombre' => 'Aaa Solo Menú', 'slug' => 'aaa-solo-menu']);
        $usuario = $this->conRol($zeta, 'recepcion');
        $alfa->usuarios()->attach($usuario, ['rol' => 'recepcion']);
        $solo->usuarios()->attach($usuario, ['rol' => 'recepcion']);

        $this->assertSame(Reservas::getUrl(tenant: $alfa), $this->destinoTrasIniciarSesion($usuario));
    }

    public function test_si_no_tiene_ningun_restaurante_con_panel_va_a_admin_sin_bucles(): void
    {
        $solo = Restaurante::factory()->soloMenu()->create(['slug' => 'solo-menu']);
        $usuario = $this->conRol($solo, 'recepcion');

        $destino = $this->destinoTrasIniciarSesion($usuario);

        $this->assertSame(url('/admin'), $destino);
        $this->actingAs($usuario)->get($destino)->assertForbidden();
    }

    public function test_quien_tambien_tiene_otro_rol_sigue_el_destino_normal_de_filament(): void
    {
        $restaurante = Restaurante::factory()->create(['slug' => 'la-esquina']);
        $usuario = $this->conRol($restaurante, 'recepcion');
        Restaurante::factory()->create(['slug' => 'otra'])->usuarios()->attach($usuario, ['rol' => 'gerente']);

        $this->assertNotSame(Reservas::getUrl(tenant: $restaurante), $this->destinoTrasIniciarSesion($usuario));
    }

    public function test_al_iniciar_sesion_la_recepcion_llega_a_reservas_por_la_pantalla_de_login(): void
    {
        $restaurante = Restaurante::factory()->create(['slug' => 'la-esquina']);
        $recepcion = User::factory()->create(['email' => 'recepcion@ejemplo.com']);
        $restaurante->usuarios()->attach($recepcion, ['rol' => 'recepcion']);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'recepcion@ejemplo.com', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(Reservas::getUrl(tenant: $restaurante));

        $this->assertAuthenticatedAs($recepcion);
        $this->get(Reservas::getUrl(tenant: $restaurante))->assertOk();
    }

    public function test_el_rol_cocina_y_el_modulo_pedidos_ya_no_existen_en_el_catalogo(): void
    {
        $this->assertNotContains('pedidos', PermisosPanel::MODULOS);
        $this->assertArrayNotHasKey('cocina', Catalogos::roles());
        $this->assertFalse(Schema::hasTable('pedidos'));
    }
}
