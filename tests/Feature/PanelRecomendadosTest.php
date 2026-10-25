<?php

namespace Tests\Feature;

use App\Filament\Pages\Recomendados;
use App\Models\Etiqueta;
use App\Services\RecomendadosDelSitio;
use App\Services\SitioPublicoDatos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Recomendados: el dueño marca o desmarca platillos y ve cuántos muestra el sitio y cuál sale como principal.
 */
class PanelRecomendadosTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    public function test_el_propietario_marca_y_desmarca_recomendados(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $categoria = $this->crearCategoria($restaurante);
        $taco = $this->crearPlatillo($restaurante, $categoria, 'Taco al pastor');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Recomendados::class)
            ->assertSee('Platillos recomendados')
            ->assertSee('Taco al pastor')
            ->call('updateTableColumnState', 'destacado', (string) $taco->getKey(), true);

        $this->assertTrue($taco->fresh()->destacado);

        Livewire::test(Recomendados::class)
            ->assertSee('Tienes 1 platillo recomendado')
            ->call('updateTableColumnState', 'destacado', (string) $taco->getKey(), false);

        $this->assertFalse($taco->fresh()->destacado);
    }

    public function test_el_contador_dice_cuantos_muestra_el_sitio(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $categoria = $this->crearCategoria($restaurante);
        $maximo = SitioPublicoDatos::MAX_DESTACADOS;

        foreach (range(1, $maximo + 2) as $n) {
            $this->crearPlatillo($restaurante, $categoria, "Platillo {$n}", ['destacado' => true, 'orden' => $n]);
        }

        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Recomendados::class)
            ->assertSee('Tienes '.($maximo + 2).' platillos recomendados')
            ->assertSee("Tu sitio muestra hasta {$maximo} recomendados")
            ->assertSee('Marcaste 2 más de los que cabe en tu sitio');
    }

    public function test_sin_recomendados_explica_que_el_sitio_muestra_los_primeros(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->crearPlatillo($restaurante, $this->crearCategoria($restaurante), 'Sopa');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Recomendados::class)
            ->assertSee('Aún no tienes recomendados')
            ->assertSee('Mientras no marques ninguno');
    }

    public function test_el_servicio_solo_cuenta_lo_que_el_sitio_realmente_muestra(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $categoria = $this->crearCategoria($restaurante);
        $visible = $this->crearPlatillo($restaurante, $categoria, 'Visible', ['destacado' => true, 'orden' => 1]);
        $this->crearPlatillo($restaurante, $categoria, 'Oculto', ['destacado' => true, 'visible' => false, 'orden' => 2]);
        $agotado = $this->crearPlatillo($restaurante, $categoria, 'Agotado', ['destacado' => true, 'disponible' => false, 'orden' => 3]);
        $this->crearPlatillo($restaurante, $categoria, 'No marcado', ['orden' => 4]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $resumen = app(RecomendadosDelSitio::class)->para($restaurante);
        $this->assertSame(3, $resumen['marcados']);
        $this->assertSame([$visible->id, $agotado->id], $resumen['en_sitio']->pluck('id')->all());

        $restaurante->update(['mostrar_agotados' => false]);
        $resumen = app(RecomendadosDelSitio::class)->para($restaurante->fresh());
        $this->assertSame([$visible->id], $resumen['en_sitio']->pluck('id')->all());
    }

    public function test_el_principal_de_las_plantillas_e_f_g_es_el_nuevo_y_luego_el_primero_con_foto(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $restaurante->update(['sitio_plantilla' => 'f']);
        $categoria = $this->crearCategoria($restaurante);
        $this->crearPlatillo($restaurante, $categoria, 'Sin foto', ['destacado' => true, 'orden' => 1, 'imagen' => null]);
        $conFoto = $this->crearPlatillo($restaurante, $categoria, 'Con foto', ['destacado' => true, 'orden' => 2, 'imagen' => "restaurantes/{$restaurante->id}/platillos/x.webp"]);
        $nuevo = $this->crearPlatillo($restaurante, $categoria, 'Novedad', ['destacado' => true, 'orden' => 3, 'imagen' => null]);
        $nuevo->etiquetas()->attach(Etiqueta::factory()->create(['codigo' => 'nuevo'])->id);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $servicio = app(RecomendadosDelSitio::class);
        $this->assertSame($nuevo->id, $servicio->para($restaurante)['principal']?->id);

        $nuevo->etiquetas()->detach();
        $this->assertSame($conFoto->id, $servicio->para($restaurante->fresh())['principal']?->id);

        $restaurante->update(['sitio_plantilla' => 'h']);
        $this->assertSame($conFoto->id, $servicio->para($restaurante->fresh())['principal']?->id);

        Livewire::test(Recomendados::class)->assertSee('Platillo principal')->assertSee('Con foto');
    }

    public function test_las_plantillas_a_a_d_no_tienen_principal(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->crearPlatillo($restaurante, $this->crearCategoria($restaurante), 'Sopa', ['destacado' => true]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $resumen = app(RecomendadosDelSitio::class)->para($restaurante);
        $this->assertNull($resumen['principal']);
        $this->assertFalse($resumen['plantilla_con_principal']);

        Livewire::test(Recomendados::class)->assertSee('sin uno principal')->assertDontSee('Platillo principal');
    }

    public function test_solo_se_ven_y_cambian_los_platillos_del_restaurante_activo(): void
    {
        $a = $this->crearRestaurante('restaurante-a');
        $b = $this->crearRestaurante('restaurante-b');
        $this->crearPlatillo($a, $this->crearCategoria($a), 'Plato de A');
        $ajeno = $this->crearPlatillo($b, $this->crearCategoria($b), 'Plato secreto de B');
        $this->entrarAlPanel($this->crearUsuario('propietario', $a), $a);

        Livewire::test(Recomendados::class)
            ->assertSee('Plato de A')
            ->assertDontSee('Plato secreto de B')
            ->call('updateTableColumnState', 'destacado', (string) $ajeno->getKey(), true);

        $this->assertFalse($ajeno->fresh()->destacado);
    }

    #[DataProvider('rolesConAcceso')]
    public function test_quien_edita_platillos_hoy_tambien_marca_recomendados(string $rol): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $taco = $this->crearPlatillo($restaurante, $this->crearCategoria($restaurante), 'Taco');
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        $this->assertTrue(Recomendados::canAccess());
        Livewire::test(Recomendados::class)->call('updateTableColumnState', 'destacado', (string) $taco->getKey(), true);
        $this->assertTrue($taco->fresh()->destacado);
    }

    #[DataProvider('rolesSinAcceso')]
    public function test_recepcion_y_cocina_no_ven_recomendados(string $rol): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        $this->assertFalse(Recomendados::canAccess());
        Livewire::test(Recomendados::class)->assertForbidden();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesConAcceso(): array
    {
        return ['propietario' => ['propietario'], 'editor' => ['editor'], 'gerente' => ['gerente']];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesSinAcceso(): array
    {
        return ['recepcion' => ['recepcion'], 'cocina' => ['cocina']];
    }
}
