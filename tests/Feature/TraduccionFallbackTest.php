<?php

namespace Tests\Feature;

use App\Models\CategoriaTraduccion;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\VarianteTraduccion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TraduccionFallbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function modelosTraducibles(): array
    {
        return [
            'categoria' => ['categoria'],
            'producto' => ['producto'],
            'variante' => ['variante'],
        ];
    }

    #[DataProvider('modelosTraducibles')]
    public function test_devuelve_la_traduccion_del_idioma_solicitado(string $tipo): void
    {
        $traducible = $this->crearTraducible($tipo, idiomaDefault: 'en', idiomas: ['en', 'es']);

        $this->assertSame('es', $traducible->traduccion('es')->idioma);
    }

    #[DataProvider('modelosTraducibles')]
    public function test_usa_el_idioma_default_si_falta_el_solicitado(string $tipo): void
    {
        $traducible = $this->crearTraducible($tipo, idiomaDefault: 'en', idiomas: ['en', 'es']);

        $this->assertSame('en', $traducible->traduccion('fr')->idioma);
    }

    #[DataProvider('modelosTraducibles')]
    public function test_sin_idioma_usa_el_idioma_default(string $tipo): void
    {
        $traducible = $this->crearTraducible($tipo, idiomaDefault: 'en', idiomas: ['es', 'en']);

        $this->assertSame('en', $traducible->traduccion()->idioma);
    }

    #[DataProvider('modelosTraducibles')]
    public function test_devuelve_null_si_no_existe_ni_el_solicitado_ni_el_default(string $tipo): void
    {
        $traducible = $this->crearTraducible($tipo, idiomaDefault: 'en', idiomas: ['es']);

        $this->assertNull($traducible->traduccion('fr'));
    }

    /**
     * Create a translatable model of the given type with one translation per language.
     *
     * @param  list<string>  $idiomas
     */
    private function crearTraducible(string $tipo, string $idiomaDefault, array $idiomas): Model
    {
        $restaurante = Restaurante::factory()->create(['idioma_default' => $idiomaDefault]);
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id]);

        [$traducible, $traduccionFactory, $llave] = match ($tipo) {
            'categoria' => [$producto->categoria, CategoriaTraduccion::factory(), 'categoria_id'],
            'producto' => [$producto, ProductoTraduccion::factory(), 'producto_id'],
            'variante' => [ProductoVariante::factory()->create(['producto_id' => $producto->id]), VarianteTraduccion::factory(), 'variante_id'],
        };

        foreach ($idiomas as $idioma) {
            $traduccionFactory->create([$llave => $traducible->id, 'idioma' => $idioma]);
        }

        return $traducible->fresh();
    }
}
