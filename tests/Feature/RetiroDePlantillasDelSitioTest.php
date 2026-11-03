<?php

namespace Tests\Feature;

use App\Filament\Resources\HistorialCambioResource\Pages\ListHistorialCambios;
use App\Models\HistorialCambio;
use App\Models\Restaurante;
use App\Services\AparienciaSitio;
use App\Services\RetiraPlantillasDelSitio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Retiro de las plantillas del sitio "a" y "c": los restaurantes que las usaban pasan a "b", cada cambio queda en el
 * historial como hecho por el sistema, y la columna nueva sale en "b". La lógica de datos se prueba con filas sembradas,
 * sin revertir la migración (ver App\Services\RetiraPlantillasDelSitio).
 */
class RetiroDePlantillasDelSitioTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private function conPlantilla(string $plantilla): Restaurante
    {
        $restaurante = Restaurante::factory()->create();
        DB::table('restaurantes')->where('id', $restaurante->id)->update(['sitio_plantilla' => $plantilla]);

        return $restaurante;
    }

    public function test_los_restaurantes_con_a_o_c_pasan_a_b_y_los_demas_no_cambian(): void
    {
        $conA = $this->conPlantilla('a');
        $conC = $this->conPlantilla('c');
        $conD = $this->conPlantilla('d');
        $conF = $this->conPlantilla('f');

        $movidos = app(RetiraPlantillasDelSitio::class)(['a', 'c'], 'b');

        $this->assertSame(2, $movidos);
        $this->assertSame('b', DB::table('restaurantes')->where('id', $conA->id)->value('sitio_plantilla'));
        $this->assertSame('b', DB::table('restaurantes')->where('id', $conC->id)->value('sitio_plantilla'));
        $this->assertSame('d', DB::table('restaurantes')->where('id', $conD->id)->value('sitio_plantilla'));
        $this->assertSame('f', DB::table('restaurantes')->where('id', $conF->id)->value('sitio_plantilla'));
    }

    public function test_cada_restaurante_movido_deja_una_fila_del_sistema_con_la_plantilla_de_antes_y_la_de_despues(): void
    {
        $conA = $this->conPlantilla('a');
        $conC = $this->conPlantilla('c');
        $conD = $this->conPlantilla('d');

        app(RetiraPlantillasDelSitio::class)(['a', 'c'], 'b');

        $filas = HistorialCambio::withoutGlobalScopes()->orderBy('restaurante_id')->get();

        $this->assertCount(2, $filas);
        $this->assertSame([$conA->id, $conC->id], $filas->pluck('restaurante_id')->all());
        $this->assertFalse(HistorialCambio::withoutGlobalScopes()->where('restaurante_id', $conD->id)->exists());

        foreach ([[$filas[0], 'a'], [$filas[1], 'c']] as [$fila, $antes]) {
            $this->assertNull($fila->usuario_id);
            $this->assertSame('restaurante', $fila->entidad);
            $this->assertSame($fila->restaurante_id, $fila->entidad_id);
            $this->assertSame('actualizar', $fila->accion);
            $this->assertSame(['sitio_plantilla' => ['antes' => $antes, 'despues' => 'b']], $fila->cambios);
            $this->assertNotNull($fila->created_at);
        }
    }

    public function test_tambien_mueve_los_restaurantes_borrados_para_que_no_vuelvan_con_una_plantilla_que_no_existe(): void
    {
        $borrado = $this->conPlantilla('c');
        $borrado->delete();

        $this->assertSame(1, app(RetiraPlantillasDelSitio::class)(['a', 'c'], 'b'));
        $this->assertSame('b', DB::table('restaurantes')->where('id', $borrado->id)->value('sitio_plantilla'));
    }

    public function test_sin_restaurantes_con_plantillas_retiradas_no_hace_nada(): void
    {
        $this->conPlantilla('b');

        $this->assertSame(0, app(RetiraPlantillasDelSitio::class)(['a', 'c'], 'b'));
        $this->assertSame(0, HistorialCambio::withoutGlobalScopes()->count());
    }

    public function test_correrla_dos_veces_no_duplica_el_historial(): void
    {
        $this->conPlantilla('a');

        app(RetiraPlantillasDelSitio::class)(['a', 'c'], 'b');
        app(RetiraPlantillasDelSitio::class)(['a', 'c'], 'b');

        $this->assertSame(1, HistorialCambio::withoutGlobalScopes()->count());
    }

    public function test_la_columna_sale_en_la_plantilla_por_defecto_nueva(): void
    {
        $columna = collect(Schema::getColumns('restaurantes'))->firstWhere('name', 'sitio_plantilla');

        $this->assertNotNull($columna);
        $this->assertFalse($columna['nullable']);
        $this->assertStringContainsString(AparienciaSitio::PLANTILLA_POR_DEFECTO, (string) $columna['default']);

        $slug = 'sin-plantilla-'.fake()->unique()->lexify('??????');
        DB::table('restaurantes')->insert(['nombre' => 'Sin plantilla', 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('b', DB::table('restaurantes')->where('slug', $slug)->value('sitio_plantilla'));
        $this->assertSame('b', Restaurante::factory()->create()->fresh()->sitio_plantilla);
    }

    public function test_el_historial_muestra_el_cambio_como_hecho_por_el_sistema(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        DB::table('restaurantes')->where('id', $restaurante->id)->update(['sitio_plantilla' => 'a']);
        app(RetiraPlantillasDelSitio::class)(['a', 'c'], 'b');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $fila = HistorialCambio::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->sole();

        Livewire::test(ListHistorialCambios::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$fila])
            ->assertTableColumnStateSet('usuario.name', 'Sistema', $fila)
            ->assertSee('Sistema');
    }
}
