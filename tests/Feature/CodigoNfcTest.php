<?php

namespace Tests\Feature;

use App\Enums\TipoCodigoAcceso;
use App\Filament\Resources\CodigosQr\Pages\CreateCodigoQr;
use App\Filament\Resources\CodigosQr\Pages\EditCodigoQr;
use App\Filament\Resources\CodigosQr\Pages\ListCodigosQr;
use App\Models\CodigoQr;
use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\Concerns\Normaliza404;
use Tests\TestCase;
use ValueError;

class CodigoNfcTest extends TestCase
{
    use ArmaEscenarioDePanel, Normaliza404, RefreshDatabase;

    private Restaurante $a;

    private Restaurante $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();

        $this->a = $this->crearRestaurante('resto-a');
        $this->b = $this->crearRestaurante('resto-b');
    }

    public function test_el_tipo_por_defecto_es_qr(): void
    {
        $codigo = CodigoQr::factory()->create(['restaurante_id' => $this->a->id]);

        $this->assertDatabaseHas('codigos_qr', ['id' => $codigo->id, 'tipo' => 'qr']);
        $this->assertSame(TipoCodigoAcceso::Qr, $codigo->fresh()->tipo);
        $this->assertFalse($codigo->fresh()->esNfc());
    }

    public function test_la_factory_crea_codigos_nfc(): void
    {
        $codigo = CodigoQr::factory()->nfc()->create(['restaurante_id' => $this->a->id]);

        $this->assertDatabaseHas('codigos_qr', ['id' => $codigo->id, 'tipo' => 'nfc']);
        $this->assertTrue($codigo->fresh()->esNfc());
    }

    public function test_un_tipo_invalido_en_el_modelo_se_rechaza(): void
    {
        $this->expectException(ValueError::class);

        $codigo = CodigoQr::factory()->create(['restaurante_id' => $this->a->id]);
        $codigo->tipo = 'rfid';
    }

    public function test_el_formulario_crea_un_codigo_nfc(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(CreateCodigoQr::class)
            ->fillForm(['nombre' => 'Etiqueta barra', 'codigo' => 'nfcbarra1', 'tipo' => 'nfc'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('codigos_qr', [
            'restaurante_id' => $this->a->id,
            'codigo' => 'nfcbarra1',
            'tipo' => 'nfc',
        ]);
    }

    public function test_el_formulario_usa_qr_como_tipo_por_defecto(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(CreateCodigoQr::class)
            ->assertFormSet(['tipo' => TipoCodigoAcceso::Qr])
            ->fillForm(['nombre' => 'Mesa 1', 'codigo' => 'mesauno1'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('codigos_qr', ['codigo' => 'mesauno1', 'tipo' => 'qr']);
    }

    public function test_el_formulario_rechaza_un_tipo_invalido_o_vacio(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(CreateCodigoQr::class)
            ->fillForm(['nombre' => 'Rara', 'codigo' => 'rara0001', 'tipo' => 'rfid'])
            ->call('create')
            ->assertHasFormErrors(['tipo']);

        Livewire::test(CreateCodigoQr::class)
            ->fillForm(['nombre' => 'Vacia', 'codigo' => 'vacia001', 'tipo' => null])
            ->call('create')
            ->assertHasFormErrors(['tipo' => 'required']);

        $this->assertDatabaseCount('codigos_qr', 0);
    }

    public function test_el_redirect_de_un_codigo_nfc_activo_es_igual_al_de_qr(): void
    {
        $nfc = CodigoQr::factory()->nfc()->deMesa('5')->create(['restaurante_id' => $this->a->id, 'codigo' => 'nfcmesa5']);
        $qr = CodigoQr::factory()->deMesa('5')->create(['restaurante_id' => $this->a->id, 'codigo' => 'qrmesa05']);

        $destino = route('menu.publico', ['slug' => 'resto-a', 'mesa' => '5']);

        $this->get(route('qr.redirect', ['codigo' => $nfc->codigo]))->assertStatus(302)->assertRedirect($destino);
        $this->get(route('qr.redirect', ['codigo' => $qr->codigo]))->assertStatus(302)->assertRedirect($destino);
    }

    public function test_un_codigo_nfc_inactivo_da_el_mismo_404_que_un_qr_inactivo(): void
    {
        $nfc = CodigoQr::factory()->nfc()->inactivo()->create(['restaurante_id' => $this->a->id, 'codigo' => 'nfcapaga1']);
        $qr = CodigoQr::factory()->inactivo()->create(['restaurante_id' => $this->a->id, 'codigo' => 'qrapagado']);

        $rutaNfc = route('qr.redirect', ['codigo' => $nfc->codigo], false);
        $rutaQr = route('qr.redirect', ['codigo' => $qr->codigo], false);

        $respuestaNfc = $this->get($rutaNfc);
        $respuestaQr = $this->get($rutaQr);

        $respuestaNfc->assertNotFound()->assertViewIs('menu.no-disponible');
        $respuestaQr->assertNotFound()->assertViewIs('menu.no-disponible');
        $this->assertMismo404SalvoLaUrlPedida([$rutaNfc => $respuestaNfc, $rutaQr => $respuestaQr]);
    }

    public function test_el_lote_de_mesas_respeta_el_tipo_elegido(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', ['desde_mesa' => 1, 'hasta_mesa' => 2, 'tipo' => 'nfc'])
            ->assertHasNoActionErrors();

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', ['desde_mesa' => 3, 'hasta_mesa' => 4, 'tipo' => 'qr'])
            ->assertHasNoActionErrors();

        $this->assertSame(2, CodigoQr::where('tipo', 'nfc')->whereIn('mesa', ['1', '2'])->count());
        $this->assertSame(2, CodigoQr::where('tipo', 'qr')->whereIn('mesa', ['3', '4'])->count());
    }

    public function test_el_lote_de_mesas_usa_qr_por_defecto_y_rechaza_un_tipo_invalido(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', ['desde_mesa' => 1, 'hasta_mesa' => 1])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('codigos_qr', ['mesa' => '1', 'tipo' => 'qr']);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', ['desde_mesa' => 5, 'hasta_mesa' => 6, 'tipo' => 'rfid'])
            ->assertHasActionErrors(['tipo']);
        $this->assertDatabaseMissing('codigos_qr', ['mesa' => '5']);
    }

    public function test_la_url_copiable_usa_app_url_y_no_el_host_de_la_peticion(): void
    {
        config(['app.url' => 'https://menu.ejemplo.test']);
        $nfc = CodigoQr::factory()->nfc()->create(['restaurante_id' => $this->a->id, 'codigo' => 'nfcurl001']);
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        $this->withServerVariables(['HTTP_HOST' => 'evil.test']);

        Livewire::test(ListCodigosQr::class)
            ->assertTableColumnStateSet('url_corta', 'https://menu.ejemplo.test/q/nfcurl001', $nfc)
            ->assertSee('https://menu.ejemplo.test/q/nfcurl001')
            ->assertDontSee('evil.test');
    }

    public function test_la_tabla_muestra_el_tipo_y_filtra_por_tipo(): void
    {
        $nfc = CodigoQr::factory()->nfc()->create(['restaurante_id' => $this->a->id]);
        $qr = CodigoQr::factory()->create(['restaurante_id' => $this->a->id]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(ListCodigosQr::class)
            ->assertTableColumnExists('tipo')
            ->assertTableColumnStateSet('tipo', TipoCodigoAcceso::Nfc, $nfc)
            ->filterTable('tipo', 'nfc')
            ->assertCanSeeTableRecords([$nfc])
            ->assertCanNotSeeTableRecords([$qr]);
    }

    public function test_un_usuario_de_otro_restaurante_no_ve_ni_edita_codigos_nfc_ajenos(): void
    {
        $propio = CodigoQr::factory()->nfc()->create(['restaurante_id' => $this->a->id]);
        $ajeno = CodigoQr::factory()->nfc()->create(['restaurante_id' => $this->b->id, 'nombre' => 'Ajeno']);
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(ListCodigosQr::class)
            ->assertCanSeeTableRecords([$propio])
            ->assertCanNotSeeTableRecords([$ajeno]);

        Livewire::test(EditCodigoQr::class, ['record' => $ajeno->getRouteKey()])->assertNotFound();

        $this->actingAs($this->crearUsuario('propietario', $this->a))
            ->get("/admin/resto-b/codigos-qr/{$ajeno->id}/edit")
            ->assertNotFound();

        $this->assertDatabaseHas('codigos_qr', ['id' => $ajeno->id, 'nombre' => 'Ajeno', 'tipo' => 'nfc']);
    }
}
