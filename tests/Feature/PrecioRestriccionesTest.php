<?php

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrecioRestriccionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_producto_rechaza_precio_negativo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Producto::factory()->create(['precio' => -0.01]);
    }

    public function test_producto_rechaza_precio_de_oferta_negativo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Producto::factory()->create(['precio' => 50, 'precio_oferta' => -1]);
    }

    public function test_variante_rechaza_precio_negativo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProductoVariante::factory()->create(['precio' => -5]);
    }

    public function test_acepta_precio_cero_y_lo_guarda_con_dos_decimales(): void
    {
        $gratis = Producto::factory()->create(['precio' => 0, 'precio_oferta' => 0]);
        $conCentavos = Producto::factory()->create(['precio' => 85.5]);

        $this->assertSame('0.00', $gratis->fresh()->precio);
        $this->assertSame('0.00', $gratis->fresh()->precio_oferta);
        $this->assertSame('85.50', $conCentavos->fresh()->precio);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function diasFueraDeRango(): array
    {
        return [
            'antes del domingo' => [-1],
            'despues del sabado' => [7],
        ];
    }

    #[DataProvider('diasFueraDeRango')]
    public function test_horario_rechaza_dia_fuera_de_rango(int $dia): void
    {
        $this->expectException(InvalidArgumentException::class);

        Horario::factory()->create(['dia_semana' => $dia]);
    }

    public function test_base_de_datos_rechaza_precio_negativo(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite no admite agregar restricciones CHECK; se cubre en el modelo.');
        }

        $producto = Producto::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('productos')->where('id', $producto->id)->update(['precio' => -1]);
    }
}
