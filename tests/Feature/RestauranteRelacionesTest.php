<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\Etiqueta;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\VarianteTraduccion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RestauranteRelacionesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Relationships Mitad B relies on: relation name => related model.
     *
     * @return array<string, array{string, class-string<Model>}>
     */
    public static function relacionesDelContrato(): array
    {
        return [
            'idiomas' => ['idiomas', RestauranteIdioma::class],
            'categorias' => ['categorias', Categoria::class],
            'productos' => ['productos', Producto::class],
            'contactos' => ['contactos', Contacto::class],
            'horarios' => ['horarios', Horario::class],
            'codigos qr' => ['codigosQr', CodigoQr::class],
        ];
    }

    /**
     * @param  class-string<Model>  $modelo
     */
    #[DataProvider('relacionesDelContrato')]
    public function test_restaurante_expone_solo_sus_registros(string $relacion, string $modelo): void
    {
        [$restaurante, $otro] = Restaurante::factory()->count(2)->create();
        $propio = $modelo::factory()->create(['restaurante_id' => $restaurante->id]);
        $modelo::factory()->create(['restaurante_id' => $otro->id]);

        $this->assertEquals([$propio->id], $restaurante->{$relacion}->pluck('id')->all());
    }

    public function test_producto_expone_variantes_y_etiquetas(): void
    {
        $producto = Producto::factory()->create();
        $variantes = ProductoVariante::factory()->count(2)->create(['producto_id' => $producto->id]);
        $etiquetas = Etiqueta::factory()->count(2)->create();
        $producto->etiquetas()->attach($etiquetas);

        $this->assertEqualsCanonicalizing($variantes->pluck('id')->all(), $producto->variantes->pluck('id')->all());
        $this->assertEqualsCanonicalizing($etiquetas->pluck('id')->all(), $producto->etiquetas->pluck('id')->all());
    }

    public function test_categoria_agrupa_sus_productos(): void
    {
        $producto = Producto::factory()->create();
        Producto::factory()->create(['restaurante_id' => $producto->restaurante_id]);

        $this->assertEquals([$producto->id], $producto->categoria->productos->pluck('id')->all());
    }

    public function test_borrado_logico_conserva_categoria_y_producto(): void
    {
        $producto = Producto::factory()->create();
        $categoria = $producto->categoria;

        $categoria->delete();
        $producto->delete();

        $this->assertSoftDeleted($categoria);
        $this->assertSoftDeleted($producto);
    }

    public function test_borrado_definitivo_del_restaurante_elimina_en_cascada(): void
    {
        $producto = Producto::factory()->create();
        $restaurante = $producto->restaurante;
        CategoriaTraduccion::factory()->create(['categoria_id' => $producto->categoria_id]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id]);
        VarianteTraduccion::factory()->create([
            'variante_id' => ProductoVariante::factory()->create(['producto_id' => $producto->id])->id,
        ]);
        $producto->etiquetas()->attach(Etiqueta::factory()->create());

        $restaurante->forceDelete();

        foreach (['categorias', 'categoria_traducciones', 'productos', 'producto_traducciones', 'producto_variantes', 'variante_traducciones', 'producto_etiqueta'] as $tabla) {
            $this->assertDatabaseEmpty($tabla);
        }
        $this->assertDatabaseCount('etiquetas', 1);
    }
}
