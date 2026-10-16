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
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AislamientoModelosTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);

        parent::tearDown();
    }

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function modelosConRestaurante(): array
    {
        return [
            'categoria' => [Categoria::class],
            'producto' => [Producto::class],
            'contacto' => [Contacto::class],
            'horario' => [Horario::class],
            'codigo qr' => [CodigoQr::class],
            'idioma' => [RestauranteIdioma::class],
            'invitacion' => [Invitacion::class],
            'historial' => [HistorialCambio::class],
        ];
    }

    /**
     * @param  class-string<Model>  $modelo
     */
    #[DataProvider('modelosConRestaurante')]
    public function test_contexto_fijado_solo_devuelve_registros_del_restaurante(string $modelo): void
    {
        [$propio, $ajeno] = Restaurante::factory()->count(2)->create();
        $registroPropio = $modelo::factory()->create(['restaurante_id' => $propio->id]);
        $modelo::factory()->create(['restaurante_id' => $ajeno->id]);

        RestauranteScope::setRestauranteId($propio->id);

        $this->assertEquals([$registroPropio->id], $modelo::query()->pluck('id')->all());
        $this->assertNull($modelo::query()->where('restaurante_id', $ajeno->id)->first());
    }

    /**
     * @param  class-string<Model>  $modelo
     */
    #[DataProvider('modelosConRestaurante')]
    public function test_panel_sin_restaurante_activo_no_devuelve_registros(string $modelo): void
    {
        $modelo::factory()->create();

        Filament::setServingStatus();

        $this->assertSame(0, $modelo::query()->count());
    }

    /**
     * @param  class-string<Model>  $modelo
     */
    #[DataProvider('modelosConRestaurante')]
    public function test_panel_con_tenant_solo_devuelve_registros_del_tenant(string $modelo): void
    {
        [$propio, $ajeno] = Restaurante::factory()->count(2)->create();
        $registroPropio = $modelo::factory()->create(['restaurante_id' => $propio->id]);
        $modelo::factory()->create(['restaurante_id' => $ajeno->id]);

        Filament::setServingStatus();
        Filament::setTenant($propio, isQuiet: true);

        $this->assertEquals([$registroPropio->id], $modelo::query()->pluck('id')->all());
    }

    /**
     * @param  class-string<Model>  $modelo
     */
    #[DataProvider('modelosConRestaurante')]
    public function test_ruta_publica_sin_contexto_no_filtra(string $modelo): void
    {
        $modelo::factory()->count(2)->create();

        $this->assertSame(2, $modelo::query()->count());
    }

    /**
     * @param  class-string<Model>  $modelo
     */
    #[DataProvider('modelosConRestaurante')]
    public function test_crear_en_contexto_asigna_el_restaurante_activo(string $modelo): void
    {
        $restaurante = Restaurante::factory()->create();
        RestauranteScope::setRestauranteId($restaurante->id);

        $registro = $modelo::factory()->make(['restaurante_id' => null]);
        $registro->save();

        $this->assertSame($restaurante->id, $registro->restaurante_id);
    }

    /**
     * @param  class-string<Model>  $modelo
     */
    #[DataProvider('modelosConRestaurante')]
    public function test_crear_para_otro_restaurante_en_contexto_lanza_excepcion(string $modelo): void
    {
        [$propio, $ajeno] = Restaurante::factory()->count(2)->create();
        RestauranteScope::setRestauranteId($propio->id);

        $this->expectException(AuthorizationException::class);

        $modelo::factory()->create(['restaurante_id' => $ajeno->id]);
    }

    public function test_crear_en_panel_sin_restaurante_activo_lanza_excepcion(): void
    {
        $contacto = Contacto::factory()->make(['restaurante_id' => null]);
        Filament::setServingStatus();

        $this->expectException(AuthorizationException::class);

        $contacto->save();
    }

    public function test_mover_registro_a_otro_restaurante_en_contexto_lanza_excepcion(): void
    {
        [$propio, $ajeno] = Restaurante::factory()->count(2)->create();
        $contacto = Contacto::factory()->create(['restaurante_id' => $propio->id]);
        RestauranteScope::setRestauranteId($propio->id);

        try {
            $contacto->update(['restaurante_id' => $ajeno->id]);
            $this->fail('Se esperaba AuthorizationException.');
        } catch (AuthorizationException) {
            $this->assertSame($propio->id, Contacto::withoutGlobalScopes()->find($contacto->id)->restaurante_id);
        }
    }
}
