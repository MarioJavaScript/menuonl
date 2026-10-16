<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CodigoQr;
use App\Models\Etiqueta;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCrudAndBusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurante $restaurante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurante = Restaurante::create([
            'nombre' => 'Mesón Español',
            'slug' => 'meson-espanol',
            'idioma_default' => 'es',
            'zona_horaria' => 'America/Mexico_City',
        ]);
    }

    /**
     * Tier 1: Categoria CRUD and ordering.
     */
    public function test_categoria_crud_and_ordering(): void
    {
        $categoria = Categoria::create([
            'restaurante_id' => $this->restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);

        $this->assertDatabaseHas('categorias', [
            'id' => $categoria->id,
            'orden' => 1,
            'visible' => true,
        ]);

        // Update
        $categoria->update(['orden' => 3, 'visible' => false]);
        $this->assertEquals(3, $categoria->fresh()->orden);
        $this->assertFalse($categoria->fresh()->visible);
    }

    /**
     * Tier 1: Producto CRUD with variants and dietary tags.
     */
    public function test_producto_crud_with_variants_and_tags(): void
    {
        $categoria = Categoria::create([
            'restaurante_id' => $this->restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);

        $producto = Producto::create([
            'restaurante_id' => $this->restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => '120.00',
            'precio_oferta' => '99.00',
            'disponible' => true,
            'visible' => true,
            'destacado' => true,
            'orden' => 1,
        ]);

        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'precio' => '120.00',
            'precio_oferta' => '99.00',
        ]);

        // Add variants
        $varianteChica = ProductoVariante::create([
            'producto_id' => $producto->id,
            'precio' => '90.00',
            'disponible' => true,
            'orden' => 1,
        ]);

        $this->assertDatabaseHas('producto_variantes', [
            'id' => $varianteChica->id,
            'producto_id' => $producto->id,
            'precio' => '90.00',
        ]);

        // Attach dietary tag
        $tag = Etiqueta::create([
            'codigo' => 'sin_gluten',
            'icono' => 'wheat-off',
        ]);

        $producto->etiquetas()->attach($tag->id);

        $this->assertDatabaseHas('producto_etiqueta', [
            'producto_id' => $producto->id,
            'etiqueta_id' => $tag->id,
        ]);
        $this->assertCount(1, $producto->fresh()->etiquetas);
    }

    /**
     * Tier 1: Soft deletes mark deleted_at on Categoria and Producto without removing records.
     */
    public function test_soft_deletes_on_categoria_and_producto(): void
    {
        $categoria = Categoria::create([
            'restaurante_id' => $this->restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);

        $producto = Producto::create([
            'restaurante_id' => $this->restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => '80.00',
            'disponible' => true,
            'visible' => true,
        ]);

        $categoria->delete();
        $producto->delete();

        $this->assertSoftDeleted('categorias', ['id' => $categoria->id]);
        $this->assertSoftDeleted('productos', ['id' => $producto->id]);

        $this->assertNull(Categoria::find($categoria->id));
        $this->assertNotNull(Categoria::withTrashed()->find($categoria->id));

        $this->assertNull(Producto::find($producto->id));
        $this->assertNotNull(Producto::withTrashed()->find($producto->id));
    }

    /**
     * Tier 1: Quick-edit updates precio, precio_oferta, and toggles disponible.
     */
    public function test_quick_edit_updates_precio_oferta_and_disponibilidad(): void
    {
        $categoria = Categoria::create([
            'restaurante_id' => $this->restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);

        $producto = Producto::create([
            'restaurante_id' => $this->restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => '75.00',
            'disponible' => true,
            'visible' => true,
        ]);

        // Simulating quick-edit price change and mark as sold out (disponible = 0)
        $producto->update([
            'precio' => '85.50',
            'precio_oferta' => '70.00',
            'disponible' => false,
        ]);

        $refreshed = $producto->fresh();
        $this->assertEquals('85.50', $refreshed->precio);
        $this->assertEquals('70.00', $refreshed->precio_oferta);
        $this->assertFalse($refreshed->disponible);
    }

    /**
     * Tier 2: Multi-language translations and fallback to idioma_default.
     */
    public function test_multilanguage_translations_and_fallback_to_idioma_default(): void
    {
        $categoria = Categoria::create([
            'restaurante_id' => $this->restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);

        $producto = Producto::create([
            'restaurante_id' => $this->restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => '150.00',
            'disponible' => true,
            'visible' => true,
        ]);

        // Spanish translation (default)
        ProductoTraduccion::create([
            'producto_id' => $producto->id,
            'idioma' => 'es',
            'nombre' => 'Paella Valenciana',
            'descripcion' => 'Arroz tradicional con mariscos y azafrán.',
        ]);

        // English translation
        ProductoTraduccion::create([
            'producto_id' => $producto->id,
            'idioma' => 'en',
            'nombre' => 'Valencian Paella',
            'descripcion' => 'Traditional rice dish with seafood and saffron.',
        ]);

        // Exact match
        $this->assertEquals('Paella Valenciana', $producto->getNombre('es'));
        $this->assertEquals('Valencian Paella', $producto->getNombre('en'));

        // Fallback to default language ('es') when requesting an unconfigured language (e.g. 'fr')
        $this->assertEquals('Paella Valenciana', $producto->getNombre('fr'));
        $this->assertEquals('Arroz tradicional con mariscos y azafrán.', $producto->getDescripcion('fr'));
    }

    /**
     * Tier 2: Restaurant open/closed status calculation (estaAbiertoAhora) on regular day shift.
     */
    public function test_restaurant_schedules_regular_day_shift_calculation(): void
    {
        // Monday (1): 12:00 to 18:00
        Horario::create([
            'restaurante_id' => $this->restaurante->id,
            'dia_semana' => 1,
            'abre' => '12:00:00',
            'cierra' => '18:00:00',
            'cerrado' => false,
        ]);

        // Monday at 14:30 -> Open
        $mondayOpen = Carbon::parse('next monday 14:30', 'America/Mexico_City');
        $this->assertTrue($this->restaurante->estaAbiertoAhora($mondayOpen));

        // Monday at 19:30 -> Closed
        $mondayClosed = Carbon::parse('next monday 19:30', 'America/Mexico_City');
        $this->assertFalse($this->restaurante->estaAbiertoAhora($mondayClosed));
    }

    /**
     * Tier 2: Restaurant open/closed status calculation across midnight (cierra < abre).
     */
    public function test_restaurant_schedules_overnight_shift_calculation(): void
    {
        // Friday (5): 20:00 to 02:00 (next day Saturday morning)
        Horario::create([
            'restaurante_id' => $this->restaurante->id,
            'dia_semana' => 5,
            'abre' => '20:00:00',
            'cierra' => '02:00:00',
            'cerrado' => false,
        ]);

        // Friday at 23:30 -> Open
        $fridayNight = Carbon::parse('next friday 23:30', 'America/Mexico_City');
        $this->assertTrue($this->restaurante->estaAbiertoAhora($fridayNight));

        // Saturday at 01:30 (continuation of Friday overnight shift) -> Open
        $saturdayEarly = Carbon::parse('next friday 20:00', 'America/Mexico_City')->addHours(5)->addMinutes(30);
        $this->assertTrue($this->restaurante->estaAbiertoAhora($saturdayEarly));

        // Saturday at 03:00 -> Closed
        $saturdayLate = Carbon::parse('next friday 20:00', 'America/Mexico_City')->addHours(7);
        $this->assertFalse($this->restaurante->estaAbiertoAhora($saturdayLate));
    }

    /**
     * Tier 2: Day marked as cerrado is always closed.
     */
    public function test_restaurant_schedules_closed_day(): void
    {
        // Sunday (0): Closed
        Horario::create([
            'restaurante_id' => $this->restaurante->id,
            'dia_semana' => 0,
            'cerrado' => true,
        ]);

        $sundayNoon = Carbon::parse('next sunday 12:00', 'America/Mexico_City');
        $this->assertFalse($this->restaurante->estaAbiertoAhora($sundayNoon));
    }

    /**
     * Tier 1: QR Code single and batch range generation.
     */
    public function test_qr_code_single_and_batch_range_generation(): void
    {
        // Single General QR
        $generalQr = CodigoQr::create([
            'restaurante_id' => $this->restaurante->id,
            'nombre' => 'Menú General Barra',
            'codigo' => 'GEN-MESON-01',
            'mesa' => null,
            'activo' => true,
        ]);

        $this->assertDatabaseHas('codigos_qr', [
            'id' => $generalQr->id,
            'codigo' => 'GEN-MESON-01',
            'mesa' => null,
        ]);

        // Batch generation for tables 1 to 5
        for ($tableNum = 1; $tableNum <= 5; $tableNum++) {
            CodigoQr::create([
                'restaurante_id' => $this->restaurante->id,
                'nombre' => "Mesa {$tableNum}",
                'mesa' => (string) $tableNum,
                'codigo' => sprintf('M%02d-%s', $tableNum, substr(md5((string) $tableNum), 0, 8)),
                'activo' => true,
            ]);
        }

        $this->assertDatabaseCount('codigos_qr', 6);
        $this->assertDatabaseHas('codigos_qr', [
            'restaurante_id' => $this->restaurante->id,
            'mesa' => '3',
        ]);
    }
}
