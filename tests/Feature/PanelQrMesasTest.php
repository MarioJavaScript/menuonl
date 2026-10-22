<?php

namespace Tests\Feature;

use App\Filament\Resources\CodigosQr\Pages\ListCodigosQr;
use App\Models\CodigoQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * QR y mesas: "Crear QR de mesas" por cantidad, sin tocar las mesas que ya tienen su código.
 */
class PanelQrMesasTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    public function test_el_propietario_crea_los_qr_de_sus_mesas_indicando_la_cantidad(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(ListCodigosQr::class)
            ->assertSee('Crear QR de mesas')
            ->callAction('crearQrMesas', ['cantidad' => 5])
            ->assertHasNoActionErrors()
            ->assertNotified('Creamos 5 QR de mesas.');

        $codigos = CodigoQr::query()->orderBy('id')->get();
        $this->assertSame(['Mesa 1', 'Mesa 2', 'Mesa 3', 'Mesa 4', 'Mesa 5'], $codigos->pluck('nombre')->all());
        $this->assertSame(['1', '2', '3', '4', '5'], $codigos->pluck('mesa')->all());
        $this->assertSame(5, $codigos->pluck('codigo')->unique()->count());
        $this->assertTrue($codigos->every(fn (CodigoQr $codigo): bool => $codigo->activo && $codigo->restaurante_id === $restaurante->id));
    }

    public function test_las_mesas_que_ya_tienen_qr_no_se_tocan(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $existente = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id, 'mesa' => '2', 'nombre' => 'Mesa 2 de la terraza', 'codigo' => 'terraza2']);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(ListCodigosQr::class)
            ->callAction('crearQrMesas', ['cantidad' => 3])
            ->assertNotified('Creamos 2 QR de mesas.');

        $this->assertSame(3, CodigoQr::query()->count());
        $this->assertSame('Mesa 2 de la terraza', $existente->fresh()->nombre);
        $this->assertSame('terraza2', $existente->fresh()->codigo);

        Livewire::test(ListCodigosQr::class)
            ->callAction('crearQrMesas', ['cantidad' => 3])
            ->assertNotified('No se creó ningún QR: todas esas mesas ya tenían uno.');
        $this->assertSame(3, CodigoQr::query()->count());
    }

    public function test_la_cantidad_debe_estar_entre_1_y_100(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        foreach ([0, 101, -3, 2.5] as $cantidad) {
            Livewire::test(ListCodigosQr::class)
                ->callAction('crearQrMesas', ['cantidad' => $cantidad])
                ->assertHasActionErrors(['cantidad']);
        }

        $this->assertSame(0, CodigoQr::query()->count());
    }

    public function test_los_qr_nuevos_son_solo_del_restaurante_activo(): void
    {
        $a = $this->crearRestaurante('restaurante-a');
        $b = $this->crearRestaurante('restaurante-b');
        $this->entrarAlPanel($this->crearUsuario('propietario', $a), $a);

        Livewire::test(ListCodigosQr::class)->callAction('crearQrMesas', ['cantidad' => 2]);

        $this->assertSame(2, CodigoQr::withoutGlobalScopes()->where('restaurante_id', $a->id)->count());
        $this->assertSame(0, CodigoQr::withoutGlobalScopes()->where('restaurante_id', $b->id)->count());
    }

    public function test_el_gerente_sigue_sin_ver_los_qr(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('gerente', $restaurante), $restaurante);

        Livewire::test(ListCodigosQr::class)->assertForbidden();
        $this->assertSame(0, CodigoQr::query()->count());
    }
}
