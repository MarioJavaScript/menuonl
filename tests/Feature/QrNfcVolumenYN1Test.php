<?php

namespace Tests\Feature;

use App\Enums\TipoCodigoAcceso;
use App\Filament\Resources\CodigosQr\Pages\ListCodigosQr;
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
use App\Models\Scopes\RestauranteScope;
use App\Models\VarianteTraduccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class QrNfcVolumenYN1Test extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);

        parent::tearDown();
    }

    public function test_lote_de_25_mesas_ejecuta_consulta_unica_where_in_sin_n_mas_1_por_mesa(): void
    {
        $restaurante = $this->crearRestaurante('volumen-n1-bistro');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        foreach ([3, 7, 15, 22] as $mesaExistente) {
            CodigoQr::factory()->deMesa((string) $mesaExistente)->create([
                'restaurante_id' => $restaurante->id,
                'tipo' => TipoCodigoAcceso::Qr,
            ]);
        }

        $this->entrarAlPanel($usuario, $restaurante);

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 25,
                'tipo' => TipoCodigoAcceso::Nfc->value,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $consultasWhereIn = array_filter($queries, function (array $q): bool {
            $sql = strtolower($q['query']);

            return str_contains($sql, 'select')
                && str_contains($sql, '"mesa"')
                && str_contains($sql, 'in (');
        });

        $consultasIndividualesMesa = array_filter($queries, function (array $q): bool {
            $sql = strtolower($q['query']);

            return str_contains($sql, 'select')
                && str_contains($sql, 'codigos_qr')
                && (str_contains($sql, '"mesa" =') || str_contains($sql, 'mesa ='));
        });

        $this->assertCount(1, $consultasWhereIn, 'Debe ejecutarse exactamente 1 consulta por lote whereIn para verificar mesas existentes.');
        $this->assertCount(0, $consultasIndividualesMesa, 'No deben ejecutarse consultas individuales N+1 por mesa.');
        $this->assertSame(25, CodigoQr::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->count());
    }

    public function test_generacion_masiva_de_500_codigos_qr_y_nfc_mantiene_cero_colisiones_y_formato_valido(): void
    {
        $restaurante = $this->crearRestaurante('volumen-500-mesas');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->entrarAlPanel($usuario, $restaurante);

        // Lote 1: Mesas 1..250 como QR
        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 250,
                'tipo' => TipoCodigoAcceso::Qr->value,
            ])
            ->assertHasNoActionErrors();

        // Lote 2: Mesas 251..500 como NFC
        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 251,
                'hasta_mesa' => 500,
                'tipo' => TipoCodigoAcceso::Nfc->value,
            ])
            ->assertHasNoActionErrors();

        $codigos = CodigoQr::withoutGlobalScopes()
            ->where('restaurante_id', $restaurante->id)
            ->pluck('codigo')
            ->all();

        $this->assertCount(500, $codigos);
        $this->assertCount(500, array_unique($codigos), 'Los 500 tokens generados deben ser 100% únicos.');

        foreach ($codigos as $token) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $token);
        }
    }

    public function test_menu_publico_con_volumen_alto_no_presenta_n_mas_1_en_frio_y_ejecuta_3_o_menos_consultas_en_caliente(): void
    {
        Cache::flush();

        $restaurante = Restaurante::factory()->create([
            'slug' => 'gran-menu-volumen',
            'estado' => 'activo',
        ]);

        Horario::factory()->create(['restaurante_id' => $restaurante->id, 'dia_semana' => 1]);
        Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true]);
        $etiqueta = Etiqueta::factory()->create();

        // Crear 6 categorías x 10 productos = 60 productos con traducciones, etiquetas y variantes
        for ($c = 1; $c <= 6; $c++) {
            $categoria = Categoria::factory()->create([
                'restaurante_id' => $restaurante->id,
                'visible' => true,
                'orden' => $c,
            ]);
            CategoriaTraduccion::factory()->create([
                'categoria_id' => $categoria->id,
                'idioma' => 'es',
                'nombre' => "Categoría Volumen {$c}",
            ]);

            for ($p = 1; $p <= 10; $p++) {
                $producto = Producto::factory()->create([
                    'restaurante_id' => $restaurante->id,
                    'categoria_id' => $categoria->id,
                    'visible' => true,
                    'disponible' => true,
                    'orden' => $p,
                ]);
                ProductoTraduccion::factory()->create([
                    'producto_id' => $producto->id,
                    'idioma' => 'es',
                    'nombre' => "Platillo {$c}-{$p}",
                ]);
                $producto->etiquetas()->attach($etiqueta->id);

                $variante = ProductoVariante::factory()->create([
                    'producto_id' => $producto->id,
                ]);
                VarianteTraduccion::factory()->create([
                    'variante_id' => $variante->id,
                    'idioma' => 'es',
                    'nombre' => "Variante {$c}-{$p}",
                ]);
            }
        }

        $url = route('menu.publico', ['slug' => $restaurante->slug]);

        // 1. Petición en frío (sin caché): número de consultas acotado por eager loading (<= 15, lejos de 60+)
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get($url)
            ->assertOk()
            ->assertSee('Platillo 1-1')
            ->assertSee('Platillo 6-10');

        $consultasEnFrio = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            15,
            $consultasEnFrio,
            "El menú público en frío con 60 productos ejecutó {$consultasEnFrio} consultas; debe estar acotado por eager loading (<= 15)."
        );

        // 2. Petición en caliente (con caché L2 poblada): debe ejecutar <= 3 consultas
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get($url)
            ->assertOk()
            ->assertSee('Platillo 1-1')
            ->assertSee('Platillo 6-10');

        $consultasEnCaliente = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            3,
            $consultasEnCaliente,
            "El menú público con caché caliente ejecutó {$consultasEnCaliente} consultas; debe ser <= 3."
        );
    }
}
