<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelApiMenuYResumenTest extends TestCase
{
    use RefreshDatabase;

    /** Fixed mid-day instant so "today" is the same date in UTC and in the restaurant's time zone. */
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 18:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function miembro(Restaurante $restaurante): User
    {
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario, ['rol' => 'editor']);

        return $usuario;
    }

    private function url(Restaurante $restaurante, string $ruta): string
    {
        return "/api/v1/panel/{$restaurante->slug}/".$ruta;
    }

    /**
     * @return array{0: Restaurante, 1: Categoria, 2: Producto}
     */
    private function menuBase(string $nombre = 'Risotto'): array
    {
        $restaurante = Restaurante::factory()->create(['idioma_default' => 'es']);
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Fuertes']);
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'precio' => 280]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => $nombre]);

        return [$restaurante, $categoria, $producto];
    }

    public function test_menu_requires_login_and_lists_names_with_hidden_and_soldout_dishes(): void
    {
        [$restaurante, $categoria] = $this->menuBase();
        $oculto = Producto::factory()->oculto()->agotado()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $oculto->id, 'idioma' => 'es', 'nombre' => 'Secreto']);

        $this->getJson($this->url($restaurante, 'menu'))->assertUnauthorized();

        $r = $this->actingAs($this->miembro($restaurante))->getJson($this->url($restaurante, 'menu'))->assertOk();
        $r->assertJsonPath('data.0.nombre', 'Fuertes')->assertJsonCount(2, 'data.0.productos');
        $this->assertContains('Secreto', array_column($r->json('data.0.productos'), 'nombre'));
    }

    public function test_updates_availability_price_visibility_and_name(): void
    {
        [$restaurante, , $producto] = $this->menuBase();

        $this->actingAs($this->miembro($restaurante))
            ->patchJson($this->url($restaurante, "productos/{$producto->id}"), ['disponible' => false, 'visible' => false, 'precio' => 295.5, 'nombre' => 'Risotto de hongos'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Risotto de hongos')
            ->assertJsonPath('data.precio', 295.5)
            ->assertJsonPath('data.disponible', false);

        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'disponible' => 0, 'visible' => 0]);
        $this->assertDatabaseHas('producto_traducciones', ['producto_id' => $producto->id, 'nombre' => 'Risotto de hongos']);
    }

    public function test_rejects_negative_or_huge_prices_and_blank_names(): void
    {
        [$restaurante, , $producto] = $this->menuBase();
        $usuario = $this->miembro($restaurante);
        $url = $this->url($restaurante, "productos/{$producto->id}");

        $this->actingAs($usuario)->patchJson($url, ['precio' => -1])->assertJsonValidationErrors('precio');
        $this->actingAs($usuario)->patchJson($url, ['precio' => 100001])->assertJsonValidationErrors('precio');
        $this->actingAs($usuario)->patchJson($url, ['nombre' => ''])->assertJsonValidationErrors('nombre');
        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'precio' => 280]);
    }

    public function test_duplicates_dish_with_copy_suffix_and_without_image(): void
    {
        [$restaurante, , $producto] = $this->menuBase();
        $producto->update(['imagen' => null]);

        $this->actingAs($this->miembro($restaurante))
            ->postJson($this->url($restaurante, "productos/{$producto->id}/duplicar"))
            ->assertCreated()->assertJsonPath('data.nombre', 'Risotto (copia)')->assertJsonPath('data.precio', 280);

        $this->assertSame(2, Producto::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->count());
    }

    public function test_creates_dish_in_category_and_deletes_it(): void
    {
        [$restaurante, $categoria] = $this->menuBase();
        $usuario = $this->miembro($restaurante);

        $id = $this->actingAs($usuario)
            ->postJson($this->url($restaurante, "categorias/{$categoria->id}/productos"), ['nombre' => 'Tiramisú', 'precio' => 125])
            ->assertCreated()->assertJsonPath('data.nombre', 'Tiramisú')->json('data.id');

        $this->actingAs($usuario)->deleteJson($this->url($restaurante, "productos/{$id}"))->assertNoContent();
        $this->assertSoftDeleted('productos', ['id' => $id]);
    }

    public function test_cannot_edit_or_delete_dish_of_another_restaurant(): void
    {
        [, , $ajeno] = $this->menuBase('Ajeno');
        $propio = Restaurante::factory()->create();
        $usuario = $this->miembro($propio);

        $this->actingAs($usuario)->patchJson($this->url($propio, "productos/{$ajeno->id}"), ['precio' => 1])->assertNotFound();
        $this->actingAs($usuario)->deleteJson($this->url($propio, "productos/{$ajeno->id}"))->assertNotFound();
        $this->actingAs($usuario)->postJson($this->url($propio, "productos/{$ajeno->id}/duplicar"))->assertNotFound();
        $this->assertDatabaseHas('productos', ['id' => $ajeno->id, 'precio' => 280]);
    }

    public function test_summary_counts_only_today_and_own_restaurant(): void
    {
        $restaurante = Restaurante::factory()->create();
        $otro = Restaurante::factory()->create();
        Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'personas' => 4]);
        Reserva::factory()->confirmada()->create(['restaurante_id' => $restaurante->id, 'personas' => 2]);
        Reserva::factory()->cancelada()->create(['restaurante_id' => $restaurante->id, 'personas' => 9]);

        $gerente = User::factory()->create();
        $restaurante->usuarios()->attach($gerente, ['rol' => 'gerente']);

        $datos = $this->actingAs($gerente)->getJson($this->url($restaurante, 'resumen'))
            ->assertOk()
            ->assertJsonPath('data.comensales_esperados', 6)
            ->assertJsonPath('data.reservas_por_confirmar', 1)
            ->json('data');

        $this->assertArrayNotHasKey('ventas_hoy', $datos);
        $this->assertArrayNotHasKey('pedidos_activos', $datos);
    }
}
