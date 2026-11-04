<?php

namespace Tests\Feature;

use App\Filament\Pages\Apariencia;
use App\Services\AparienciaSitio;
use App\Services\Catalogos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * "Apariencia" (Filament) deja elegir la plantilla del sitio, con la misma lista que usa el panel de servicio.
 */
class PerfilPlantillaSitioTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    public function test_la_pantalla_ofrece_todas_las_plantillas_incluida_cafeteria(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->assertSee('Diseño de tu sitio')
            ->assertSee('Cafetería')
            ->assertSee('Oscuro elegante')
            ->assertDontSee('Claro y sencillo')
            ->assertDontSee('Clásico y cálido');
    }

    public function test_cafeteria_no_es_el_valor_por_defecto_y_se_puede_elegir_y_guardar(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);
        $this->assertSame('b', $restaurante->fresh()->sitio_plantilla);

        Livewire::test(Apariencia::class)
            ->assertSet('data.sitio_plantilla', 'b')
            ->set('data.sitio_plantilla', 'e')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('e', $restaurante->fresh()->sitio_plantilla);
    }

    public function test_un_valor_de_plantilla_desconocido_se_rechaza(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->set('data.sitio_plantilla', 'z')
            ->call('save')
            ->assertHasErrors(['data.sitio_plantilla']);

        $this->assertSame('b', $restaurante->fresh()->sitio_plantilla);
    }

    #[DataProvider('plantillasRetiradas')]
    public function test_una_plantilla_retirada_ya_no_se_puede_guardar(string $retirada): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->set('data.sitio_plantilla', $retirada)
            ->call('save')
            ->assertHasErrors(['data.sitio_plantilla']);

        $this->assertSame('b', $restaurante->fresh()->sitio_plantilla);
    }

    #[DataProvider('plantillasRetiradas')]
    public function test_un_valor_guardado_que_ya_no_existe_se_muestra_y_se_guarda_como_la_plantilla_por_defecto(string $retirada): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $restaurante->forceFill(['sitio_plantilla' => $retirada])->saveQuietly();
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->assertSet('data.sitio_plantilla', AparienciaSitio::PLANTILLA_POR_DEFECTO)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(AparienciaSitio::PLANTILLA_POR_DEFECTO, $restaurante->fresh()->sitio_plantilla);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function plantillasRetiradas(): array
    {
        return ['a' => ['a'], 'c' => ['c'], 'desconocida' => ['z']];
    }

    public function test_cada_plantilla_del_catalogo_tiene_su_ficha_para_el_dueno(): void
    {
        $this->assertSame(
            array_keys(Catalogos::plantillasSitio()),
            array_keys(AparienciaSitio::fichas()),
        );
    }
}
