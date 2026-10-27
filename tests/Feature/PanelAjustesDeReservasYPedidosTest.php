<?php

namespace Tests\Feature;

use App\Filament\Pages\AjustesDeReservasYPedidos;
use App\Filament\Pages\Reservas;
use App\Models\Horario;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Pedidos por WhatsApp y reservas, desde el panel del propietario (antes las reservas solo estaban en /panel).
 */
class PanelAjustesDeReservasYPedidosTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    public function test_el_propietario_ve_los_ajustes_de_reservas_y_pedidos_con_la_ayuda_de_cada_ajuste(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $this->get(AjustesDeReservasYPedidos::getUrl())
            ->assertOk()
            ->assertSee('Recibir pedidos por WhatsApp')
            ->assertSee('el menú deja elegir platillos y enviar el pedido')
            ->assertSee('Aceptar reservas desde mi sitio')
            ->assertSee('el formulario de reservas desaparece de tu sitio')
            ->assertSee('Personas que puedes recibir al mismo tiempo')
            ->assertSee('Duración de cada reserva');
    }

    public function test_guarda_los_pedidos_y_las_reservas(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->fillForm([
                'pedidos_activos' => true,
                'whatsapp_pedidos' => '5215512345678',
                'reservas_activas' => false,
                'reservas_cupo' => 18,
                'reservas_franja_min' => 90,
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Cambios guardados');

        $restaurante->refresh();
        $this->assertTrue($restaurante->pedidos_activos);
        $this->assertSame('5215512345678', $restaurante->whatsapp_pedidos);
        $this->assertFalse($restaurante->reservas_activas);
        $this->assertSame(18, $restaurante->reservas_cupo);
        $this->assertSame(90, $restaurante->reservas_franja_min);
    }

    public function test_activar_los_pedidos_exige_el_numero_de_whatsapp(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $restaurante->update(['whatsapp_pedidos' => null]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->fillForm(['pedidos_activos' => true, 'whatsapp_pedidos' => ''])
            ->call('save')
            ->assertHasErrors(['data.whatsapp_pedidos' => 'required'])
            ->assertSee('Escribe el número de WhatsApp para poder recibir pedidos.');

        $this->assertFalse($restaurante->fresh()->pedidos_activos);
    }

    public function test_con_los_pedidos_apagados_el_numero_puede_quedar_vacio(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->fillForm(['pedidos_activos' => false, 'whatsapp_pedidos' => ''])
            ->call('save')
            ->assertHasNoErrors();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function valoresInvalidos(): array
    {
        return [
            'numero con signos' => [['pedidos_activos' => true, 'whatsapp_pedidos' => '+52 55 1234'], 'data.whatsapp_pedidos'],
            'numero muy corto' => [['pedidos_activos' => true, 'whatsapp_pedidos' => '12345'], 'data.whatsapp_pedidos'],
            'cupo en cero' => [['reservas_cupo' => 0], 'data.reservas_cupo'],
            'cupo enorme' => [['reservas_cupo' => 501], 'data.reservas_cupo'],
            'cupo con decimales' => [['reservas_cupo' => 10.5], 'data.reservas_cupo'],
            'duracion fuera de lista' => [['reservas_franja_min' => 45], 'data.reservas_franja_min'],
        ];
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    #[DataProvider('valoresInvalidos')]
    public function test_rechaza_valores_invalidos(array $valores, string $campo): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->fillForm($valores)
            ->call('save')
            ->assertHasErrors([$campo]);

        $this->assertSame(30, $restaurante->fresh()->reservas_cupo);
    }

    public function test_avisa_si_las_reservas_estan_activas_y_no_hay_horario(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(AjustesDeReservasYPedidos::class)->assertSee('Aún no tienes horario');

        Horario::factory()->create(['restaurante_id' => $restaurante->id, 'cerrado' => false, 'dia_semana' => 1, 'abre' => '09:00', 'cierra' => '18:00']);

        Livewire::test(AjustesDeReservasYPedidos::class)->assertDontSee('Aún no tienes horario');
    }

    public function test_solo_cambia_el_restaurante_activo(): void
    {
        $a = $this->crearRestaurante('restaurante-a');
        $b = $this->crearRestaurante('restaurante-b');
        $this->entrarAlPanel($this->crearUsuario('propietario', $a), $a);

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->fillForm(['reservas_cupo' => 7])
            ->call('save');

        $this->assertSame(7, $a->fresh()->reservas_cupo);
        $this->assertSame(30, $b->fresh()->reservas_cupo);
    }

    #[DataProvider('rolesSinAcceso')]
    public function test_los_demas_roles_no_ven_los_ajustes_de_reservas_y_pedidos(string $rol): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        $this->assertFalse(AjustesDeReservasYPedidos::canAccess());
        $this->assertNotContains(AjustesDeReservasYPedidos::getUrl(), collect(Filament::getNavigation())->flatMap(fn ($grupo) => $grupo->getItems())->map(fn ($item): string => $item->getUrl())->all());
        Livewire::test(AjustesDeReservasYPedidos::class)->assertForbidden();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesSinAcceso(): array
    {
        return ['editor' => ['editor'], 'gerente' => ['gerente'], 'recepcion' => ['recepcion'], 'cocina' => ['cocina']];
    }

    public function test_el_propietario_ve_reservas_y_los_ajustes_en_el_grupo_de_operacion_sin_la_segunda_ventana_de_servicio(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $urls = collect(Filament::getNavigation())->flatMap(fn ($grupo) => $grupo->getItems())->map(fn ($item): string => $item->getUrl())->all();

        $this->assertContains(Reservas::getUrl(), $urls);
        $this->assertContains(AjustesDeReservasYPedidos::getUrl(), $urls);
        $this->assertSame('ajustes-de-reservas-y-pedidos', AjustesDeReservasYPedidos::getSlug());
        $this->assertSame('Ajustes de reservas y pedidos', AjustesDeReservasYPedidos::getNavigationLabel());
        $this->assertSame('Pedidos y reservas', AjustesDeReservasYPedidos::getNavigationGroup());
    }
}
