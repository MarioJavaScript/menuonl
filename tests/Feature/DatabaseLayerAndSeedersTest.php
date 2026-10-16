<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\HistorialCambio;
use App\Models\Horario;
use App\Models\Invitacion;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\Scopes\RestauranteScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseLayerAndSeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    public function test_tenant_isolation_is_enforced_across_all_restaurant_scoped_models(): void
    {
        $restauranteA = Restaurante::create(['nombre' => 'Restaurante A', 'slug' => 'rest-a']);
        $restauranteB = Restaurante::create(['nombre' => 'Restaurante B', 'slug' => 'rest-b']);

        // Set context to Restaurant A
        RestauranteScope::setRestauranteId($restauranteA->id);

        $catA = Categoria::create(['orden' => 1, 'visible' => true]);
        $prodA = Producto::create(['categoria_id' => $catA->id, 'precio' => 100.00]);
        $contactoA = Contacto::create(['tipo' => 'telefono', 'valor' => '+525500000001']);
        $horarioA = Horario::create(['dia_semana' => 1, 'abre' => '10:00:00', 'cierra' => '20:00:00']);
        $qrA = CodigoQr::create(['nombre' => 'General', 'codigo' => 'qr-a-01']);
        $invA = Invitacion::create(['email' => 'a@example.com', 'rol' => 'editor', 'token' => str_repeat('a', 64), 'expira_en' => now()->addDays(7)]);
        $histA = HistorialCambio::create(['entidad' => 'test', 'entidad_id' => 1, 'accion' => 'crear']);
        $idiomaA = RestauranteIdioma::create(['idioma' => 'es']);

        // Assert auto-assignment of restaurante_id
        $this->assertEquals($restauranteA->id, $catA->restaurante_id);
        $this->assertEquals($restauranteA->id, $prodA->restaurante_id);
        $this->assertEquals($restauranteA->id, $contactoA->restaurante_id);
        $this->assertEquals($restauranteA->id, $horarioA->restaurante_id);
        $this->assertEquals($restauranteA->id, $qrA->restaurante_id);
        $this->assertEquals($restauranteA->id, $invA->restaurante_id);
        $this->assertEquals($restauranteA->id, $histA->restaurante_id);
        $this->assertEquals($restauranteA->id, $idiomaA->restaurante_id);

        // Switch context to Restaurant B
        RestauranteScope::setRestauranteId($restauranteB->id);

        $this->assertCount(0, Categoria::all());
        $this->assertCount(0, Producto::all());
        $this->assertCount(0, Contacto::all());
        $this->assertCount(0, Horario::all());
        $this->assertCount(0, CodigoQr::all());
        $this->assertCount(0, Invitacion::all());
        $this->assertCount(0, HistorialCambio::all());
        $this->assertCount(0, RestauranteIdioma::all());

        // Create entity in Restaurant B
        $catB = Categoria::create(['orden' => 1, 'visible' => true]);
        $this->assertEquals($restauranteB->id, $catB->restaurante_id);
        $this->assertCount(1, Categoria::all());
        $this->assertEquals($catB->id, Categoria::first()->id);

        // Switch back to Restaurant A
        RestauranteScope::setRestauranteId($restauranteA->id);
        $this->assertCount(1, Categoria::all());
        $this->assertEquals($catA->id, Categoria::first()->id);
    }

    public function test_soft_deletes_on_categoria_and_producto(): void
    {
        $restaurante = Restaurante::create(['nombre' => 'Test Soft', 'slug' => 'test-soft']);
        RestauranteScope::setRestauranteId($restaurante->id);

        $categoria = Categoria::create(['orden' => 1, 'visible' => true]);
        $producto = Producto::create(['categoria_id' => $categoria->id, 'precio' => 120.00]);

        // Soft delete category
        $categoria->delete();
        $this->assertSoftDeleted('categorias', ['id' => $categoria->id]);
        $this->assertCount(0, Categoria::all());
        $this->assertCount(1, Categoria::withTrashed()->get());

        // Soft delete product
        $producto->delete();
        $this->assertSoftDeleted('productos', ['id' => $producto->id]);
        $this->assertCount(0, Producto::all());
        $this->assertCount(1, Producto::withTrashed()->get());

        // Restore
        $producto->restore();
        $this->assertCount(1, Producto::all());
    }
}
