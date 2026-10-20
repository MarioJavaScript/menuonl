<?php

namespace Tests\Feature;

use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Services\Catalogos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * "Mi restaurante" (Filament) también deja elegir la plantilla del sitio, con la misma lista que usa el panel de servicio.
 */
class PerfilPlantillaSitioTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    public function test_la_pantalla_ofrece_todas_las_plantillas_incluida_cafeteria(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(EditRestauranteProfile::class)
            ->assertSee('Plantilla del sitio')
            ->assertSee(Catalogos::plantillasSitio()['e']['nombre'])
            ->assertSee(Catalogos::plantillasSitio()['a']['nombre']);
    }

    public function test_cafeteria_no_es_el_valor_por_defecto_y_se_puede_elegir_y_guardar(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);
        $this->assertSame('a', $restaurante->fresh()->sitio_plantilla);

        Livewire::test(EditRestauranteProfile::class)
            ->assertSet('data.sitio_plantilla', 'a')
            ->set('data.sitio_plantilla', 'e')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('e', $restaurante->fresh()->sitio_plantilla);
    }

    public function test_un_valor_de_plantilla_desconocido_se_rechaza(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(EditRestauranteProfile::class)
            ->set('data.sitio_plantilla', 'z')
            ->call('save')
            ->assertHasErrors(['data.sitio_plantilla']);

        $this->assertSame('a', $restaurante->fresh()->sitio_plantilla);
    }
}
