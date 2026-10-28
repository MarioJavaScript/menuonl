<?php

namespace Tests\Feature;

use App\Filament\Pages\Reservas;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Lo que ve la recepción heredada en /admin ahora que el /panel viejo ya no existe: Inicio útil y sin enlaces a pantallas
 * que no puede abrir, y perfil y cierre de sesión siempre al alcance, sin exigirle el segundo factor.
 */
class RecepcionEnAdminTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private Restaurante $restaurante;

    private User $recepcion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
        $this->restaurante = $this->crearRestaurante('la-esquina');
        $this->recepcion = $this->crearUsuario('recepcion', $this->restaurante);
    }

    private function inicioDe(User $usuario): string
    {
        $url = '/admin/'.$this->restaurante->slug;

        return $this->actingAs($usuario)->get($url)->assertOk()->getContent();
    }

    public function test_la_recepcion_ve_el_bloque_de_hoy_con_enlace_a_reservas(): void
    {
        Reserva::factory()->create(['restaurante_id' => $this->restaurante->id, 'estado' => 'pendiente', 'nombre' => 'Lucía Márquez']);

        $html = $this->inicioDe($this->recepcion);

        $this->assertStringContainsString('href="'.Reservas::getUrl(tenant: $this->restaurante).'"', $html);
    }

    public function test_la_recepcion_no_ve_botones_ni_listas_de_configuracion_que_no_puede_abrir(): void
    {
        $html = $this->inicioDe($this->recepcion);

        $this->assertStringNotContainsString('Agregar platillo', $html);
        $this->assertStringNotContainsString(__('panel.inicio.falta.titulo'), $html);
        $this->assertStringNotContainsString(__('panel.inicio.atencion.titulo'), $html);
        $this->assertStringNotContainsString(__('panel.inicio.accesos.descargar_qr'), $html);
        $this->assertStringNotContainsString(ProductoResource::getUrl('create', tenant: $this->restaurante), $html);
        $this->assertStringNotContainsString(CategoriaResource::getUrl('index', tenant: $this->restaurante), $html);
    }

    public function test_el_propietario_si_ve_el_boton_y_la_lista_de_pendientes(): void
    {
        $html = $this->inicioDe($this->crearUsuario('propietario', $this->restaurante));

        $this->assertStringContainsString('Agregar platillo', $html);
        $this->assertStringContainsString(__('panel.inicio.falta.titulo'), $html);
    }

    public function test_la_recepcion_tiene_a_mano_el_perfil_y_el_cierre_de_sesion(): void
    {
        $html = $this->inicioDe($this->recepcion);

        $this->assertStringContainsString(Filament::getPanel('admin')->getProfileUrl(), $html);
        $this->assertStringContainsString(Filament::getPanel('admin')->getLogoutUrl(), $html);
    }

    public function test_la_recepcion_no_tiene_que_configurar_el_segundo_factor_para_trabajar(): void
    {
        config(['menuonl.requerir_2fa' => true]);

        $this->assertFalse($this->recepcion->requiereDosFactores());
        $this->actingAs($this->recepcion)->get(Reservas::getUrl(tenant: $this->restaurante))->assertOk();
        $this->actingAs($this->recepcion)->get('/admin/'.$this->restaurante->slug)->assertOk();
    }

    public function test_el_perfil_de_la_recepcion_se_abre_sin_bucle(): void
    {
        config(['menuonl.requerir_2fa' => true]);

        $this->actingAs($this->recepcion)->get(Filament::getPanel('admin')->getProfileUrl())->assertOk();
    }

    public function test_quien_es_recepcion_aqui_y_propietario_en_otro_restaurante_si_debe_configurar_el_segundo_factor(): void
    {
        config(['menuonl.requerir_2fa' => true]);
        $otro = $this->crearRestaurante('otro-local');
        $otro->usuarios()->attach($this->recepcion, ['rol' => 'propietario']);

        $this->actingAs($this->recepcion->fresh())
            ->get(Reservas::getUrl(tenant: $this->restaurante))
            ->assertRedirect(Filament::getPanel('admin')->getProfileUrl());

        $this->actingAs($this->recepcion->fresh())->get(Filament::getPanel('admin')->getProfileUrl())->assertOk();
    }
}
