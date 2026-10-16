<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\Etiqueta;
use App\Models\HistorialCambio;
use App\Models\Horario;
use App\Models\Invitacion;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EtiquetaSeeder;
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

    public function test_etiqueta_seeder_populates_the_seven_canonical_tags(): void
    {
        $this->seed(EtiquetaSeeder::class);

        $expectedTags = [
            'vegano' => ['icono' => 'leaf', 'color' => '#16a34a'],
            'vegetariano' => ['icono' => 'carrot', 'color' => '#65a30d'],
            'picante' => ['icono' => 'flame', 'color' => '#dc2626'],
            'sin_gluten' => ['icono' => 'wheat-off', 'color' => '#d97706'],
            'sin_lactosa' => ['icono' => 'milk-off', 'color' => '#0284c7'],
            'nuevo' => ['icono' => 'sparkles', 'color' => '#8b5cf6'],
            'recomendado' => ['icono' => 'star', 'color' => '#eab308'],
        ];

        $this->assertCount(7, Etiqueta::all());

        foreach ($expectedTags as $slug => $data) {
            $tag = Etiqueta::where('slug', $slug)->first();
            $this->assertNotNull($tag, "Tag [{$slug}] must exist.");
            $this->assertEquals($data['icono'], $tag->icono);
            $this->assertEquals($data['color'], $tag->color);
            $this->assertEquals($slug, $tag->codigo);
            $this->assertTrue($tag->activo);
        }
    }

    public function test_database_seeder_executes_cleanly_and_creates_demo_restaurant_graph(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Verify Demo Restaurant exists
        $restaurante = Restaurante::where('slug', 'la-trattoria-demo')->first();
        $this->assertNotNull($restaurante);
        $this->assertEquals('La Trattoria Demo', $restaurante->nombre);
        $this->assertEquals('MXN', $restaurante->moneda);
        $this->assertEquals('es', $restaurante->idioma_default);
        $this->assertEquals('America/Mexico_City', $restaurante->zona_horaria);
        $this->assertTrue($restaurante->isActivo());

        // Verify users attached with roles
        $this->assertDatabaseHas('users', ['email' => 'admin@menuonl.com', 'es_superadmin' => true]);
        $this->assertDatabaseHas('users', ['email' => 'propietario@demo.com', 'es_superadmin' => false]);
        $this->assertDatabaseHas('users', ['email' => 'editor@demo.com', 'es_superadmin' => false]);

        $propietario = User::where('email', 'propietario@demo.com')->first();
        $this->assertTrue($restaurante->usuarios()->where('user_id', $propietario->id)->wherePivot('rol', 'propietario')->exists());

        $editor = User::where('email', 'editor@demo.com')->first();
        $this->assertTrue($restaurante->usuarios()->where('user_id', $editor->id)->wherePivot('rol', 'editor')->exists());

        // Verify languages
        $this->assertCount(2, $restaurante->idiomas);

        // Verify categories and products
        $this->assertGreaterThanOrEqual(3, $restaurante->categorias()->count());
        $this->assertGreaterThanOrEqual(6, $restaurante->productos()->count());

        // Verify product with variants (Pizza Margherita)
        $pizza = $restaurante->productos()->whereHas('variantes')->first();
        $this->assertNotNull($pizza);
        $this->assertCount(2, $pizza->variantes);

        // Verify contacts and schedules
        $this->assertGreaterThanOrEqual(4, $restaurante->contactos()->count());
        $this->assertGreaterThanOrEqual(10, $restaurante->horarios()->count());

        // Verify QR codes
        $this->assertGreaterThanOrEqual(3, $restaurante->codigosQr()->count());
        $this->assertDatabaseHas('codigos_qr', ['restaurante_id' => $restaurante->id, 'codigo' => 'demo-gral']);
        $this->assertDatabaseHas('codigos_qr', ['restaurante_id' => $restaurante->id, 'codigo' => 'demo-m01', 'mesa' => '1']);

        // Verify invitations
        $this->assertDatabaseHas('invitaciones', [
            'restaurante_id' => $restaurante->id,
            'email' => 'nuevo_editor@demo.com',
            'rol' => 'editor',
        ]);

        // Verify audit log entry
        $this->assertDatabaseHas('historial_cambios', [
            'restaurante_id' => $restaurante->id,
            'entidad' => 'restaurante',
            'accion' => 'crear',
        ]);
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
        $qrA = CodigoQr::create(['codigo' => 'qr-a-01', 'tipo' => 'general']);
        $invA = Invitacion::create(['email' => 'a@example.com', 'rol' => 'editor', 'token' => str_repeat('a', 64), 'expira_en' => now()->addDays(7)]);
        $histA = HistorialCambio::create(['entidad' => 'test', 'entidad_id' => 1, 'accion' => 'crear']);
        $idiomaA = RestauranteIdioma::create(['codigo' => 'es']);

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

    public function test_all_factories_generate_valid_models(): void
    {
        $restaurante = Restaurante::factory()->create();
        $categoria = Categoria::factory()->withTranslation('Tapas')->create(['restaurante_id' => $restaurante->id]);
        $producto = Producto::factory()->withTranslation('Jamon Iberico')->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
        ]);
        $variante = ProductoVariante::factory()->withTranslation('Ración 100g')->create(['producto_id' => $producto->id]);
        $contacto = Contacto::factory()->create(['restaurante_id' => $restaurante->id]);
        $horario = Horario::factory()->nocturno()->create(['restaurante_id' => $restaurante->id]);
        $qr = CodigoQr::factory()->paraMesa('5')->create(['restaurante_id' => $restaurante->id]);
        $invitacion = Invitacion::factory()->create(['restaurante_id' => $restaurante->id]);
        $etiqueta = Etiqueta::factory()->create();

        $this->assertDatabaseHas('restaurantes', ['id' => $restaurante->id]);
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
        $this->assertDatabaseHas('productos', ['id' => $producto->id]);
        $this->assertDatabaseHas('producto_variantes', ['id' => $variante->id]);
        $this->assertDatabaseHas('contactos', ['id' => $contacto->id]);
        $this->assertDatabaseHas('horarios', ['id' => $horario->id]);
        $this->assertDatabaseHas('codigos_qr', ['id' => $qr->id]);
        $this->assertDatabaseHas('invitaciones', ['id' => $invitacion->id]);
        $this->assertDatabaseHas('etiquetas', ['id' => $etiqueta->id]);

        $this->assertEquals('Tapas', $categoria->getNombre());
        $this->assertEquals('Jamon Iberico', $producto->getNombre());
        $this->assertEquals('Ración 100g', $variante->getNombre());
        $this->assertTrue($horario->esNocturno());
        $this->assertTrue($qr->esMesa());
        $this->assertEquals('Mesa 5', $qr->nombre);
    }
}
