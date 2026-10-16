<?php

namespace Tests\Feature;

use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Categorias\Pages\ListCategorias;
use App\Filament\Resources\CodigosQr\Pages\ListCodigosQr;
use App\Filament\Resources\Contactos\Pages\ListContactos;
use App\Filament\Resources\Productos\Pages\CreateProducto;
use App\Filament\Resources\Restaurantes\Pages\ListRestaurantes;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Categoria;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class AislamientoPanelTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private Restaurante $a;

    private Restaurante $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();

        $this->a = $this->crearRestaurante('a');
        $this->b = $this->crearRestaurante('b');
    }

    public function test_url_directa_a_un_registro_de_otro_restaurante_no_se_abre(): void
    {
        $categoriaB = $this->crearCategoria($this->b, 'Secreta de B');
        $propietarioA = $this->crearUsuario('propietario', $this->a);

        // Under its own tenant URL the record does not exist for A...
        $this->actingAs($propietarioA)->get("/admin/a/categorias/{$categoriaB->id}/edit")->assertNotFound();
        // ...and the tenant URL of B is closed to A.
        $this->actingAs($propietarioA)->get("/admin/b/categorias/{$categoriaB->id}/edit")->assertNotFound();
    }

    public function test_editar_un_registro_ajeno_desde_livewire_falla(): void
    {
        $categoriaB = $this->crearCategoria($this->b);
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(EditCategoria::class, ['record' => $categoriaB->getRouteKey()])->assertNotFound();
    }

    public function test_la_tabla_solo_lista_registros_del_restaurante_activo(): void
    {
        $propia = $this->crearCategoria($this->a, 'Propia');
        $ajena = $this->crearCategoria($this->b, 'Ajena');
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(ListCategorias::class)
            ->assertCanSeeTableRecords([$propia])
            ->assertCanNotSeeTableRecords([$ajena]);
    }

    public function test_no_se_puede_borrar_un_registro_ajeno_desde_la_tabla(): void
    {
        $contactoB = Contacto::factory()->create(['restaurante_id' => $this->b->id]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        try {
            Livewire::test(ListContactos::class)->callAction(TestAction::make('delete')->table($contactoB));
        } catch (\Throwable) {
            // The record is not part of the table query, so the action cannot run.
        }

        $this->assertDatabaseHas('contactos', ['id' => $contactoB->id]);
    }

    public function test_el_editor_borra_contactos_de_su_restaurante_pero_no_codigos_qr(): void
    {
        $contacto = Contacto::factory()->create(['restaurante_id' => $this->a->id]);
        $qr = CodigoQr::factory()->create(['restaurante_id' => $this->a->id]);
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(ListContactos::class)
            ->callAction(TestAction::make('delete')->table($contacto))
            ->assertNotified();
        $this->assertDatabaseMissing('contactos', ['id' => $contacto->id]);

        Livewire::test(ListCodigosQr::class)
            ->assertActionHidden(TestAction::make('delete')->table($qr));
        $this->assertDatabaseHas('codigos_qr', ['id' => $qr->id]);
    }

    public function test_el_editor_borra_categorias_con_borrado_logico(): void
    {
        $categoria = $this->crearCategoria($this->a);
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(ListCategorias::class)->callAction(TestAction::make('delete')->table($categoria));

        $this->assertSoftDeleted('categorias', ['id' => $categoria->id]);
    }

    public function test_no_se_puede_crear_un_producto_en_una_categoria_de_otro_restaurante(): void
    {
        $categoriaB = $this->crearCategoria($this->b);
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoriaB->id,
                'precio' => 50,
                'orden' => 0,
                'traducciones' => [['idioma' => 'es', 'nombre' => 'Intruso']],
            ])
            ->call('create')
            ->assertHasFormErrors(['categoria_id']);

        $this->assertSame(0, Producto::withoutGlobalScopes()->count());
    }

    public function test_los_productos_creados_pertenecen_al_restaurante_activo(): void
    {
        $categoriaA = $this->crearCategoria($this->a);
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoriaA->id,
                'precio' => 85.5,
                'orden' => 1,
                'traducciones' => [['idioma' => 'es', 'nombre' => 'Guacamole']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($this->a->id, Producto::withoutGlobalScopes()->firstOrFail()->restaurante_id);
    }

    public function test_el_superadmin_ve_todos_los_restaurantes_y_los_demas_no_acceden_al_recurso(): void
    {
        $superadmin = $this->crearUsuario('superadmin');

        $this->entrarAlPanel($superadmin, $this->a);
        Livewire::test(ListRestaurantes::class)
            ->assertCanSeeTableRecords([$this->a, $this->b]);

        foreach (['propietario', 'editor'] as $rol) {
            $this->actingAs($this->crearUsuario($rol, $this->a))->get('/admin/a/restaurantes')->assertForbidden();
        }
    }

    public function test_propietario_y_editor_no_ven_restaurantes_ajenos_en_ninguna_lista(): void
    {
        $propietario = $this->crearUsuario('propietario', $this->a);

        $this->actingAs($propietario)->get('/admin/a/restaurantes')->assertForbidden();
        $this->assertSame([$this->a->id], $propietario->getTenants(Filament::getPanel('admin'))->pluck('id')->all());
    }

    public function test_la_lista_de_usuarios_solo_muestra_miembros_del_restaurante_activo(): void
    {
        $propietario = $this->crearUsuario('propietario', $this->a);
        $miembro = $this->crearUsuario('editor', $this->a);
        $ajeno = $this->crearUsuario('editor', $this->b);
        $this->entrarAlPanel($propietario, $this->a);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$propietario, $miembro])
            ->assertCanNotSeeTableRecords([$ajeno]);
    }

    public function test_el_propietario_revoca_el_acceso_de_un_editor(): void
    {
        $propietario = $this->crearUsuario('propietario', $this->a);
        $editor = $this->crearUsuario('editor', $this->a);
        $this->entrarAlPanel($propietario, $this->a);

        Livewire::test(ListUsers::class)->callAction(TestAction::make('revocarAcceso')->table($editor));

        $this->assertDatabaseMissing('restaurante_usuario', ['user_id' => $editor->id, 'restaurante_id' => $this->a->id]);
        $this->assertDatabaseHas('users', ['id' => $editor->id]);
    }

    public function test_el_editor_no_entra_a_usuarios_ni_invitaciones_ni_configuracion(): void
    {
        $editor = $this->crearUsuario('editor', $this->a);

        foreach (['usuarios', 'invitaciones', 'invitaciones/create'] as $ruta) {
            $this->actingAs($editor)->get("/admin/a/{$ruta}")->assertForbidden();
        }

        // The settings page hides itself with a 404 when the policy denies `update`.
        $this->actingAs($editor)->get('/admin/a/profile')->assertNotFound();
    }

    public function test_un_usuario_de_dos_restaurantes_solo_ve_los_datos_del_tenant_elegido(): void
    {
        $usuario = $this->crearUsuario('propietario', $this->a);
        $usuario->restaurantes()->attach($this->b->id, ['rol' => 'editor']);
        $this->crearCategoria($this->a, 'Categoria de A');
        $this->crearCategoria($this->b, 'Categoria de B');

        $this->actingAs($usuario)->get('/admin/a/categorias')->assertSee('Categoria de A')->assertDontSee('Categoria de B');
        $this->actingAs($usuario)->get('/admin/b/categorias')->assertSee('Categoria de B')->assertDontSee('Categoria de A');
    }

    public function test_el_scope_filtra_las_consultas_directas_durante_una_peticion_del_panel(): void
    {
        $this->crearCategoria($this->a);
        $this->crearCategoria($this->b);
        $editor = $this->crearUsuario('editor', $this->a);

        $this->actingAs($editor)->get('/admin/a/categorias')->assertOk();

        // Same process, panel still marked as serving with tenant A: model queries stay inside A.
        $this->assertSame(1, Categoria::query()->count());
        $this->assertSame([$this->a->id], Categoria::query()->pluck('restaurante_id')->all());
        $this->assertInstanceOf(User::class, $editor);
    }
}
