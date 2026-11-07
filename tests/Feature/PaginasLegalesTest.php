<?php

namespace Tests\Feature;

use App\Enums\PlanRestaurante;
use App\Services\DatosLegales;
use App\Services\MarcadoresPendientes;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * /privacidad y /terminos: datos del responsable y precios desde la configuración, sin marcadores para el visitante y sin el JS de la portada.
 */
class PaginasLegalesTest extends TestCase
{
    private const array VALORES = [
        'responsable' => 'Servicios de Prueba SA de CV',
        'domicilio' => 'Avenida de Prueba 742, Colonia Centro',
        'correo_privacidad' => 'privacidad@ejemplo.test',
        'fecha_actualizacion' => '2026-03-09',
        'jurisdiccion' => 'Estado de Prueba',
    ];

    /**
     * Livewire recuerda en una propiedad estática que otra prueba del mismo proceso ya pintó un componente, y entonces inyecta sus
     * estilos en cualquier respuesta. En producción cada petición empieza limpia, así que aquí se apaga para ver solo el HTML de la página.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'menuonl.legal' => self::VALORES,
            'menuonl.soporte_whatsapp' => null,
            'livewire.inject_assets' => false,
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function paginas(): array
    {
        return ['privacidad' => ['/privacidad'], 'términos' => ['/terminos']];
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function valoresFaltantes(): array
    {
        return ['nulo' => [null], 'vacío' => [''], 'espacios' => ['   '], 'corchetes' => ['[POR COMPLETAR]'], 'sin corchetes' => ['por completar']];
    }

    #[DataProvider('paginas')]
    public function test_responde_200_con_titulo_descripcion_y_enlace_canonico(string $ruta): void
    {
        $html = (string) $this->get($ruta)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<title>[^<]+\| '.preg_quote((string) config('app.name'), '#').'</title>#', $html);
        $this->assertMatchesRegularExpression('#<meta name="description" content="[^"]{50,}">#', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url($ruta).'">', $html);
    }

    public function test_los_terminos_usan_los_nombres_y_precios_de_la_configuracion(): void
    {
        config(['menuonl.precios' => [
            'lite' => 251,
            'aura' => 1503,
            'nota' => 'Nota de precios de prueba.',
            'nombres' => ['lite' => 'Plan Chico', 'aura' => 'Plan Grande'],
        ]]);

        $this->get('/terminos')
            ->assertOk()
            ->assertSeeInOrder(['Plan Chico, $251 al mes', 'Plan Grande, $1,503 al mes', 'Nota de precios de prueba.'])
            ->assertDontSee('Lite')
            ->assertDontSee('Aura')
            ->assertDontSee('$199')
            ->assertDontSee('$399');
    }

    public function test_sin_nota_de_precios_no_queda_un_parrafo_vacio(): void
    {
        config(['menuonl.precios.nota' => null]);

        $this->get('/terminos')->assertOk()->assertDontSee('<p></p>', false);
    }

    public function test_los_nombres_de_la_configuracion_coinciden_con_los_del_plan(): void
    {
        $this->assertSame(PlanRestaurante::SoloMenu->getLabel(), config('menuonl.precios.nombres.lite'));
        $this->assertSame(PlanRestaurante::SitioMenu->getLabel(), config('menuonl.precios.nombres.aura'));
    }

    public function test_el_aviso_toma_de_la_configuracion_los_datos_del_responsable(): void
    {
        $this->get('/privacidad')
            ->assertOk()
            ->assertSee(self::VALORES['responsable'])
            ->assertSee(self::VALORES['domicilio'])
            ->assertSee('href="mailto:'.self::VALORES['correo_privacidad'].'"', false)
            ->assertSee('Última actualización: 9 de marzo de 2026.');
    }

    public function test_los_terminos_toman_de_la_configuracion_los_datos_del_responsable(): void
    {
        $this->get('/terminos')
            ->assertOk()
            ->assertSee(self::VALORES['responsable'])
            ->assertSee('tribunales competentes de '.self::VALORES['jurisdiccion'])
            ->assertSee('href="mailto:'.self::VALORES['correo_privacidad'].'"', false)
            ->assertSee('Última actualización: 9 de marzo de 2026.');
    }

    public function test_los_plazos_del_aviso_salen_de_la_configuracion(): void
    {
        config(['menuonl.reservas.eventos_retencion_meses' => 7, 'menuonl.respaldos.retencion_dias' => 23]);

        $this->get('/privacidad')->assertOk()->assertSee('7 meses')->assertSee('23 días');
        $this->get('/terminos')->assertOk()->assertSee('23 días');
    }

    public function test_el_aviso_distingue_los_dos_roles_y_explica_whatsapp_y_lo_que_avisan_los_restaurantes(): void
    {
        $this->get('/privacidad')
            ->assertOk()
            ->assertSee('id="duenos"', false)
            ->assertSee('id="clientes"', false)
            ->assertSee('id="restaurantes"', false)
            ->assertSee('actúa como encargado')
            ->assertSee('eres tú quien lo envía desde tu teléfono', false);
    }

    #[DataProvider('paginas')]
    public function test_con_los_valores_puestos_no_hay_marcadores_corchetes_ni_emojis(string $ruta): void
    {
        $html = (string) $this->get($ruta)->assertOk()->getContent();

        $this->assertStringNotContainsString('[', $html);
        $this->assertStringNotContainsString(']', $html);
        $this->assertStringNotContainsString('POR ESCRIBIR', $html);
        $this->assertStringNotContainsString('PENDIENTE', $html);
        $this->assertStringNotContainsString('class="mark"', $html);
        $this->assertSame(0, preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html), 'Sin emojis.');
    }

    public function test_el_aviso_ya_no_cuenta_como_pendiente_y_los_formularios_pueden_enlazarlo(): void
    {
        $marcadores = new MarcadoresPendientes;

        $this->assertFalse($marcadores->privacidadPendiente());
        $this->assertSame([], $marcadores->enPaginasPublicas());
    }

    #[DataProvider('valoresFaltantes')]
    public function test_sin_datos_del_responsable_las_paginas_responden_sin_marcadores_para_el_visitante(mixed $valor): void
    {
        config(['menuonl.legal' => array_fill_keys(array_keys(self::VALORES), $valor)]);

        foreach (array_keys(self::paginas()) as $nombre) {
            $html = (string) $this->get(self::paginas()[$nombre][0])->assertOk()->getContent();

            $this->assertStringNotContainsString('[', $html);
            $this->assertStringNotContainsString('POR COMPLETAR', mb_strtoupper($html));
            $this->assertStringNotContainsString('Última actualización', $html);
            $this->assertStringNotContainsString('mailto:', $html);
            $this->assertStringContainsString('el contacto de soporte de '.config('app.name'), $html);
        }

        $this->assertSame(array_values(DatosLegales::VARIABLES), (new DatosLegales)->faltantes());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function valoresInvalidos(): array
    {
        return [
            'correo sin arroba' => ['correo_privacidad', 'privacidad.ejemplo.test', 'LEGAL_CORREO_PRIVACIDAD'],
            'fecha con otro formato' => ['fecha_actualizacion', '09/03/2026', 'LEGAL_FECHA_ACTUALIZACION'],
            'fecha que no existe' => ['fecha_actualizacion', '2026-02-31', 'LEGAL_FECHA_ACTUALIZACION'],
            'fecha con hora' => ['fecha_actualizacion', '2026-03-09 10:00', 'LEGAL_FECHA_ACTUALIZACION'],
            'domicilio con marcador' => ['domicilio', 'Calle [CONFIRMAR]', 'LEGAL_DOMICILIO'],
        ];
    }

    #[DataProvider('valoresInvalidos')]
    public function test_un_valor_invalido_cuenta_como_faltante_y_no_se_muestra(string $clave, string $valor, string $variable): void
    {
        config(['menuonl.legal.'.$clave => $valor]);

        $this->assertSame([$variable], (new DatosLegales)->faltantes());

        foreach (self::paginas() as [$ruta]) {
            $this->get($ruta)->assertOk()->assertDontSee($valor, false);
        }
    }

    public function test_con_todos_los_valores_validos_no_falta_nada(): void
    {
        $this->assertSame([], (new DatosLegales)->faltantes());
    }

    public function test_los_datos_del_responsable_se_escapan(): void
    {
        config(['menuonl.legal.responsable' => '<script>alert(1)</script> & Cía']);

        $this->get('/privacidad')
            ->assertOk()
            ->assertDontSee('<script>alert(1)', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; &amp; Cía', false);
    }

    #[DataProvider('paginas')]
    public function test_no_cargan_el_js_de_la_portada(string $ruta): void
    {
        $this->get($ruta)->assertOk()->assertDontSee('landing-reveal', false)->assertDontSee('data-reveal', false);
    }

    public function test_la_portada_sigue_cargando_su_js(): void
    {
        $this->get('/')->assertOk()->assertSee('landing-reveal', false);
    }

    public function test_las_paginas_se_enlazan_entre_si_y_con_la_portada(): void
    {
        $this->get('/privacidad')
            ->assertOk()
            ->assertSee('<a href="'.route('terminos').'">Términos y condiciones</a>', false)
            ->assertSee('<a class="btn btn--secondary" href="'.route('portada').'">', false);

        $this->get('/terminos')
            ->assertOk()
            ->assertSee('<a href="'.route('privacidad').'">Aviso de privacidad</a>', false)
            ->assertSee('<a class="btn btn--secondary" href="'.route('portada').'">', false);
    }

    #[DataProvider('paginas')]
    public function test_los_identificadores_no_se_repiten_y_cada_apartado_esta_nombrado(string $ruta): void
    {
        $html = (string) $this->get($ruta)->assertOk()->getContent();

        preg_match_all('/\sid="([^"]+)"/', $html, $ids);
        $this->assertSame([], array_keys(array_filter(array_count_values($ids[1]), fn (int $veces): bool => $veces > 1)));

        preg_match_all('/aria-labelledby="([^"]+)"/', $html, $referencias);
        $this->assertNotEmpty($referencias[1]);
        $this->assertSame([], array_values(array_diff($referencias[1], $ids[1])));
    }
}
