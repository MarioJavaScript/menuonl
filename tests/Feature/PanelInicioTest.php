<?php

namespace Tests\Feature;

use App\Filament\Pages\AjustesDeReservasYPedidos;
use App\Filament\Pages\Apariencia;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Contactos\ContactoResource;
use App\Filament\Resources\Horarios\HorarioResource;
use App\Filament\Resources\Productos\ProductoResource;
use App\Filament\Widgets\ResumenRestauranteWidget;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\Horario;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\ProgresoDeConfiguracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Inicio: "Qué falta configurar" con porcentaje, resumen del día y accesos rápidos, según lo que cada rol ya puede ver.
 */
class PanelInicioTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    /**
     * @return array<string, bool> clave del paso => cumplido
     */
    private function pasos(Restaurante $restaurante, User $usuario): array
    {
        $progreso = app(ProgresoDeConfiguracion::class)->para($restaurante->fresh(), $usuario);

        return collect($progreso['pasos'])->mapWithKeys(fn (array $paso): array => [$paso['clave'] => $paso['hecho']])->all();
    }

    private function propietario(string $slug = 'la-esquina'): array
    {
        $restaurante = $this->crearRestaurante($slug);
        $usuario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($usuario, $restaurante);

        return [$restaurante, $usuario];
    }

    /**
     * Cada paso: cómo dejarlo sin cumplir y cómo cumplirlo.
     *
     * @return array<string, array{0: string}>
     */
    public static function pasosDeLaLista(): array
    {
        return array_combine(
            ['categoria', 'platillo', 'horario', 'contacto', 'descripcion', 'mapa', 'logotipo', 'portada', 'whatsapp'],
            array_map(fn (string $clave): array => [$clave], ['categoria', 'platillo', 'horario', 'contacto', 'descripcion', 'mapa', 'logotipo', 'portada', 'whatsapp']),
        );
    }

    private function cumplir(string $clave, Restaurante $restaurante): void
    {
        match ($clave) {
            'categoria' => $this->crearCategoria($restaurante),
            'platillo' => $this->crearPlatillo($restaurante, $restaurante->categorias()->first() ?? $this->crearCategoria($restaurante), 'Sopa'),
            'horario' => Horario::factory()->create(['restaurante_id' => $restaurante->id, 'dia_semana' => 1]),
            'contacto' => Contacto::factory()->create(['restaurante_id' => $restaurante->id]),
            'descripcion' => $restaurante->update(['descripcion' => 'Cocina de barrio.']),
            'mapa' => $restaurante->update(['latitud' => 19.432608, 'longitud' => -99.133209]),
            'logotipo' => $restaurante->update(['logo' => "restaurantes/{$restaurante->id}/logos/a.webp"]),
            'portada' => $restaurante->update(['portada' => "restaurantes/{$restaurante->id}/portadas/a.webp"]),
            'whatsapp' => $restaurante->update(['whatsapp_pedidos' => '5215512345678']),
        };
    }

    private function dejarSinCumplir(string $clave, Restaurante $restaurante): void
    {
        match ($clave) {
            'descripcion' => $restaurante->update(['descripcion' => '   ']),
            'whatsapp' => $restaurante->update(['pedidos_activos' => true, 'whatsapp_pedidos' => null]),
            default => null,
        };
    }

    #[DataProvider('pasosDeLaLista')]
    public function test_cada_paso_aparece_pendiente_y_se_marca_solo_cuando_se_cumple(string $clave): void
    {
        [$restaurante, $usuario] = $this->propietario();
        $restaurante->update(['pedidos_activos' => true, 'whatsapp_pedidos' => null]);
        $this->dejarSinCumplir($clave, $restaurante);

        $this->assertFalse($this->pasos($restaurante, $usuario)[$clave], "{$clave} debe estar pendiente");
        Livewire::test(ResumenRestauranteWidget::class)
            ->assertSee(__("panel.inicio.pasos.{$clave}.pendiente"), escape: true)
            ->assertSee(__("panel.inicio.pasos.{$clave}.accion"));

        $this->cumplir($clave, $restaurante);

        $this->assertTrue($this->pasos($restaurante, $usuario)[$clave], "{$clave} debe estar cumplido");
        Livewire::test(ResumenRestauranteWidget::class)
            ->assertDontSee(__("panel.inicio.pasos.{$clave}.pendiente"), escape: true)
            ->assertSee(__("panel.inicio.pasos.{$clave}.hecho"));
    }

    public function test_un_contacto_oculto_no_cuenta_como_cumplido(): void
    {
        [$restaurante, $usuario] = $this->propietario();
        Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => false]);

        $this->assertFalse($this->pasos($restaurante, $usuario)['contacto']);
    }

    public function test_un_horario_de_dia_cerrado_no_cuenta_como_cumplido(): void
    {
        [$restaurante, $usuario] = $this->propietario();
        Horario::factory()->cerrado()->create(['restaurante_id' => $restaurante->id, 'dia_semana' => 2]);

        $this->assertFalse($this->pasos($restaurante, $usuario)['horario']);
    }

    public function test_el_paso_de_whatsapp_solo_existe_si_los_pedidos_estan_activos(): void
    {
        [$restaurante, $usuario] = $this->propietario();

        $this->assertArrayNotHasKey('whatsapp', $this->pasos($restaurante, $usuario));

        $restaurante->update(['pedidos_activos' => true, 'whatsapp_pedidos' => null]);
        $this->assertFalse($this->pasos($restaurante, $usuario)['whatsapp']);

        $restaurante->update(['whatsapp_pedidos' => '5215512345678']);
        $this->assertTrue($this->pasos($restaurante, $usuario)['whatsapp']);
    }

    public function test_el_porcentaje_sube_con_cada_paso_y_llega_a_cien(): void
    {
        [$restaurante, $usuario] = $this->propietario();
        $servicio = app(ProgresoDeConfiguracion::class);

        $inicial = $servicio->para($restaurante->fresh(), $usuario);
        $this->assertSame(8, $inicial['total']);
        $this->assertSame(1, $inicial['hechos']);
        $this->assertSame(13, $inicial['porcentaje']);

        Livewire::test(ResumenRestauranteWidget::class)
            ->assertSee('Tu restaurante está al 13 %.')
            ->assertSee('Te faltan 7 pasos.');

        $this->cumplir('categoria', $restaurante);
        $this->assertSame(25, $servicio->para($restaurante->fresh(), $usuario)['porcentaje']);

        foreach (['platillo', 'horario', 'contacto', 'mapa', 'logotipo', 'portada'] as $clave) {
            $this->cumplir($clave, $restaurante);
        }

        $final = $servicio->para($restaurante->fresh(), $usuario);
        $this->assertSame(100, $final['porcentaje']);

        Livewire::test(ResumenRestauranteWidget::class)
            ->assertSee('Tu restaurante está al 100 %.')
            ->assertSee('Todo en orden.')
            ->assertSee('Tu menú está completo.');
    }

    public function test_la_barra_de_progreso_es_accesible(): void
    {
        [$restaurante] = $this->propietario();

        $html = Livewire::test(ResumenRestauranteWidget::class)->html();

        $this->assertStringContainsString('role="progressbar"', $html);
        $this->assertStringContainsString('aria-valuenow="13"', $html);
        $this->assertStringContainsString('aria-valuemax="100"', $html);
        $this->assertStringContainsString('Pendiente: ', $html);
    }

    public function test_los_botones_llevan_a_la_pantalla_de_cada_paso(): void
    {
        [$restaurante, $usuario] = $this->propietario();
        $restaurante->update(['pedidos_activos' => true, 'whatsapp_pedidos' => null]);

        $urls = collect(app(ProgresoDeConfiguracion::class)->para($restaurante->fresh(), $usuario)['pasos'])->pluck('url', 'clave')->all();

        $this->assertSame(CategoriaResource::getUrl('create'), $urls['categoria']);
        $this->assertSame(ProductoResource::getUrl('create'), $urls['platillo']);
        $this->assertSame(HorarioResource::getUrl('index'), $urls['horario']);
        $this->assertSame(ContactoResource::getUrl('index'), $urls['contacto']);
        $this->assertSame(EditRestauranteProfile::getUrl(), $urls['descripcion']);
        $this->assertSame(EditRestauranteProfile::getUrl(), $urls['mapa']);
        $this->assertSame(Apariencia::getUrl(), $urls['logotipo']);
        $this->assertSame(Apariencia::getUrl(), $urls['portada']);
        $this->assertSame(AjustesDeReservasYPedidos::getUrl(), $urls['whatsapp']);

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_aviso_de_reservas_activas_sin_horario(): void
    {
        [$restaurante, $usuario] = $this->propietario();
        $servicio = app(ProgresoDeConfiguracion::class);

        $this->assertSame(['reservas_sin_horario'], array_column($servicio->para($restaurante->fresh(), $usuario)['avisos'], 'clave'));
        Livewire::test(ResumenRestauranteWidget::class)->assertSee('Tus clientes pueden reservar, pero aún no tienes horario');

        $this->cumplir('horario', $restaurante);
        $this->assertSame([], $servicio->para($restaurante->fresh(), $usuario)['avisos']);
        Livewire::test(ResumenRestauranteWidget::class)->assertDontSee('Tus clientes pueden reservar');

        $restaurante->horarios()->delete();
        $restaurante->update(['reservas_activas' => false]);
        $this->assertSame([], $servicio->para($restaurante->fresh(), $usuario)['avisos']);
    }

    public function test_accesos_rapidos_para_el_propietario(): void
    {
        [$restaurante] = $this->propietario();
        $this->get(Inicio::getUrl())
            ->assertSee('Accesos rápidos')
            ->assertSee(route('sitio.publico', ['slug' => $restaurante->slug]), escape: false)
            ->assertSee(route('menu.publico', ['slug' => $restaurante->slug]), escape: false)
            ->assertDontSee('Descargar mi QR');

        $qr = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id, 'mesa' => '4', 'nombre' => 'Mesa 4']);
        $general = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id, 'mesa' => null]);

        $this->get(Inicio::getUrl())
            ->assertSee('Descargar mi QR')
            ->assertSee(route('codigos-qr.descargar', ['codigoQr' => $general, 'formato' => 'png']), escape: false)
            ->assertDontSee(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'png']), escape: false);
    }

    public function test_el_enlace_de_descarga_del_qr_funciona(): void
    {
        [$restaurante] = $this->propietario();
        $general = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id, 'mesa' => null]);

        $this->get(route('codigos-qr.descargar', ['codigoQr' => $general, 'formato' => 'png']))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_un_qr_inactivo_no_se_ofrece_para_descargar(): void
    {
        [$restaurante] = $this->propietario();
        CodigoQr::factory()->create(['restaurante_id' => $restaurante->id, 'activo' => false]);

        Livewire::test(ResumenRestauranteWidget::class)->assertDontSee('Descargar mi QR');
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function pasosPorRol(): array
    {
        return [
            'propietario' => ['propietario', ['categoria', 'platillo', 'horario', 'contacto', 'descripcion', 'mapa', 'logotipo', 'portada']],
            'editor' => ['editor', ['categoria', 'platillo', 'horario', 'contacto']],
            'gerente' => ['gerente', ['platillo']],
            'recepcion' => ['recepcion', []],
            'cocina' => ['cocina', []],
        ];
    }

    /**
     * @param  list<string>  $esperados
     */
    #[DataProvider('pasosPorRol')]
    public function test_cada_rol_ve_solo_los_pasos_de_pantallas_que_ya_puede_abrir(string $rol, array $esperados): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $usuario = $this->crearUsuario($rol, $restaurante);
        $this->entrarAlPanel($usuario, $restaurante);

        $this->assertSame($esperados, array_keys($this->pasos($restaurante, $usuario)));
    }

    public function test_el_propietario_ve_el_paso_de_whatsapp_pero_el_gerente_y_el_editor_no(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $restaurante->update(['pedidos_activos' => true, 'whatsapp_pedidos' => null]);

        foreach (['propietario' => true, 'gerente' => false, 'editor' => false] as $rol => $debeVerlo) {
            $usuario = $this->crearUsuario($rol, $restaurante);
            $this->entrarAlPanel($usuario, $restaurante);

            $this->assertSame($debeVerlo, array_key_exists('whatsapp', $this->pasos($restaurante, $usuario)), $rol);
        }
    }

    public function test_el_gerente_no_ve_botones_de_pantallas_que_no_puede_abrir(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $restaurante->update(['pedidos_activos' => true, 'whatsapp_pedidos' => null]);
        $this->entrarAlPanel($this->crearUsuario('gerente', $restaurante), $restaurante);

        Livewire::test(ResumenRestauranteWidget::class)
            ->assertSee('Qué falta configurar')
            ->assertSee('Agregar platillo')
            ->assertDontSee('Crear categoría')
            ->assertDontSee('Agregar horario')
            ->assertDontSee('Subir logotipo')
            ->assertDontSee('Agregar WhatsApp')
            ->assertDontSee('Tus clientes pueden reservar')
            ->assertDontSee('Crear QR de mesas')
            ->assertDontSee('Descargar mi QR');
    }

    public function test_el_editor_ve_lo_del_menu_y_no_lo_del_restaurante(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('editor', $restaurante), $restaurante);

        Livewire::test(ResumenRestauranteWidget::class)
            ->assertSee('Crear categoría')
            ->assertSee('Agregar horario')
            ->assertSee('Agregar contacto')
            ->assertSee('Tus clientes pueden reservar')
            ->assertDontSee('Subir logotipo')
            ->assertDontSee('Subir portada')
            ->assertDontSee('Escribir descripción')
            ->assertDontSee('Agregar ubicación');
    }

    public function test_recepcion_y_cocina_no_ven_la_lista_ni_los_avisos(): void
    {
        $escenarios = [];
        foreach (['recepcion', 'cocina'] as $rol) {
            $restaurante = $this->crearRestaurante("local-{$rol}");
            $escenarios[] = [$this->crearUsuario($rol, $restaurante), $restaurante];
        }

        foreach ($escenarios as [$usuario, $restaurante]) {
            $this->entrarAlPanel($usuario, $restaurante);

            Livewire::test(ResumenRestauranteWidget::class)
                ->assertDontSee('Qué falta configurar')
                ->assertDontSee('Tus clientes pueden reservar')
                ->assertDontSee('Para revisar')
                ->assertDontSee('Tu restaurante está al');
        }
    }

    public function test_lo_que_hay_que_revisar_se_muestra_solo_a_quien_ve_platillos_o_qr(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->crearPlatillo($restaurante, $this->crearCategoria($restaurante), 'Sopa', ['disponible' => false, 'imagen' => null]);

        $this->entrarAlPanel($this->crearUsuario('gerente', $restaurante), $restaurante);
        Livewire::test(ResumenRestauranteWidget::class)
            ->assertSee('Tienes 1 platillo agotado.')
            ->assertSee('1 platillo no tiene foto')
            ->assertDontSee('Aún no creas los códigos QR');

        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);
        Livewire::test(ResumenRestauranteWidget::class)
            ->assertSee('Tienes 1 platillo agotado.')
            ->assertSee('Aún no creas los códigos QR de tus mesas.');
    }

    public function test_restaurante_sin_datos_muestra_la_lista_pero_ningun_bloque_vacio(): void
    {
        [$restaurante] = $this->propietario();

        Livewire::test(ResumenRestauranteWidget::class)
            ->assertSee('Qué falta configurar')
            ->assertSee('Crea tu primera categoría')
            ->assertDontSee('Para revisar')
            ->assertDontSee('Descargar mi QR')
            ->assertDontSee('platillos agotados')
            ->assertDontSee('Todo en orden.');
    }

    public function test_los_textos_no_usan_jerga_carta_ni_emojis(): void
    {
        [$restaurante] = $this->propietario();
        $restaurante->update(['pedidos_activos' => true, 'whatsapp_pedidos' => null]);
        Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'fecha' => now($restaurante->zona_horaria)->toDateString()]);

        $texto = strip_tags(Livewire::test(ResumenRestauranteWidget::class)->html());

        foreach (['slug', 'tema', 'plantilla_sitio', 'tenant', 'carta'] as $palabra) {
            $this->assertDoesNotMatchRegularExpression('/\b'.$palabra.'\b/iu', $texto, $palabra);
        }
        $this->assertDoesNotMatchRegularExpression('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', $texto);
    }

    public function test_inicio_se_abre_para_cada_rol_con_acceso_al_panel(): void
    {
        $escenarios = [];
        foreach (['propietario', 'gerente', 'editor', 'recepcion', 'cocina'] as $rol) {
            $restaurante = $this->crearRestaurante("inicio-{$rol}");
            $escenarios[] = [$this->crearUsuario($rol, $restaurante), $restaurante];
        }

        foreach ($escenarios as [$usuario, $restaurante]) {
            $this->entrarAlPanel($usuario, $restaurante);

            $this->get(Inicio::getUrl())->assertOk()->assertSee('Accesos rápidos');
        }
    }

    public function test_la_lista_es_legible_en_pantallas_angostas(): void
    {
        [$restaurante] = $this->propietario();

        $html = Livewire::test(ResumenRestauranteWidget::class)->html();

        $this->assertStringContainsString('flex-col gap-2 py-3 sm:flex-row', $html);
        $this->assertStringContainsString('flex flex-wrap gap-2', $html);
    }
}
