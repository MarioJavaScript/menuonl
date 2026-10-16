<?php

namespace Tests\Feature;

use App\Models\Restaurante;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class PanelTenancyTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    public function test_usuario_sin_restaurantes_no_puede_entrar_al_panel(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/admin')->assertForbidden();
    }

    public function test_usuario_inactivo_no_puede_entrar_al_panel(): void
    {
        $restaurante = $this->crearRestaurante('a');
        $usuario = $this->crearUsuario('propietario', $restaurante);
        $usuario->update(['activo' => false]);

        $this->actingAs($usuario)->get('/admin/a/categorias')->assertForbidden();
    }

    public function test_superadmin_sin_restaurantes_si_puede_entrar(): void
    {
        $this->crearRestaurante('a');
        $superadmin = $this->crearUsuario('superadmin');

        $this->actingAs($superadmin)->get('/admin')->assertRedirect();
    }

    public function test_miembro_entra_a_su_restaurante_por_la_url(): void
    {
        $restaurante = $this->crearRestaurante('a');
        $editor = $this->crearUsuario('editor', $restaurante);

        $this->actingAs($editor)->get('/admin/a/categorias')->assertOk();
    }

    public function test_url_manipulada_hacia_un_tenant_ajeno_es_rechazada(): void
    {
        $this->crearRestaurante('a');
        $this->crearRestaurante('b');
        $propietarioA = $this->crearUsuario('propietario', Restaurante::where('slug', 'a')->first());

        foreach (['categorias', 'productos', 'contactos', 'horarios', 'codigos-qr', 'invitaciones', 'usuarios', 'profile'] as $ruta) {
            $this->actingAs($propietarioA)->get("/admin/b/{$ruta}")->assertNotFound();
        }
    }

    public function test_el_selector_ofrece_solo_los_restaurantes_del_usuario(): void
    {
        $a = $this->crearRestaurante('a');
        $b = $this->crearRestaurante('b');
        $this->crearRestaurante('c');
        $usuario = $this->crearUsuario('editor', $a);
        $usuario->restaurantes()->attach($b->id, ['rol' => 'propietario']);

        $this->actingAs($usuario);

        $tenants = $usuario->getTenants(Filament::getPanel('admin'));

        $this->assertEqualsCanonicalizing(['a', 'b'], $tenants->pluck('slug')->all());
    }

    public function test_superadmin_ve_todos_los_restaurantes_como_tenants(): void
    {
        $this->crearRestaurante('a');
        $this->crearRestaurante('b');
        $superadmin = $this->crearUsuario('superadmin');

        $tenants = $superadmin->getTenants(Filament::getPanel('admin'));

        $this->assertCount(2, $tenants);
    }

    public function test_el_restaurante_activo_llega_al_scope_durante_una_peticion_del_panel(): void
    {
        $a = $this->crearRestaurante('a');
        $b = $this->crearRestaurante('b');
        $this->crearCategoria($a, 'Solo A');
        $this->crearCategoria($b, 'Solo B');
        $editor = $this->crearUsuario('editor', $a);

        $this->actingAs($editor)->get('/admin/a/categorias')
            ->assertOk()
            ->assertSee('Solo A')
            ->assertDontSee('Solo B');
    }
}
