<?php

namespace Tests\Feature;

use App\Enums\Capacidad;
use App\Enums\PlanRestaurante;
use App\Services\PoliticaDeContenido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Portada con escena 3D: el HTML servido es la página completa, sin depender de JavaScript. Precios y contacto salen de la
 * configuración, y NFC solo aparece si `menuonl.nfc_disponible` está encendido.
 */
class LandingNuevaTest extends TestCase
{
    use RefreshDatabase;

    private const string NUMERO = '5210000000000';

    protected function setUp(): void
    {
        parent::setUp();

        config(['menuonl.soporte_whatsapp' => self::NUMERO, 'menuonl.nfc_disponible' => false, 'app.url' => 'https://menubly.test']);
    }

    private function portada(): string
    {
        return (string) $this->get('/')->assertOk()->getContent();
    }

    private function fuente(string $ruta): string
    {
        return (string) file_get_contents(base_path($ruta));
    }

    /**
     * @return list<string>
     */
    private function archivosDeLaEscena(): array
    {
        return ['resources/js/landing.js', 'resources/js/landing/escena.js', 'resources/js/landing/recorrido.js', 'resources/js/landing/rendimiento.js', 'public/js/landing-criterio.js'];
    }

    public function test_con_nfc_apagado_ninguna_mencion_aparece_en_html_seo_ni_en_el_codigo_de_la_escena(): void
    {
        $html = $this->portada();

        foreach (['nfc', 'etiqueta', 'acerca tu celular', 'tocan la mesa', 'tocar la mesa', 'data-anillos'] as $mencion) {
            $this->assertStringNotContainsStringIgnoringCase($mencion, $html, "La portada menciona: {$mencion}");
        }

        foreach ([...$this->archivosDeLaEscena(), 'resources/css/landing.css'] as $archivo) {
            $this->assertStringNotContainsStringIgnoringCase('nfc', $this->fuente($archivo), "{$archivo} menciona NFC.");
        }
    }

    public function test_con_nfc_encendido_los_textos_de_qr_y_nfc_llegan_a_html_seo_canvas_planes_y_preguntas(): void
    {
        config(['menuonl.nfc_disponible' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>'.config('app.name').' | Menú digital con QR y NFC para restaurantes</title>', false)
            ->assertSee('<meta name="description" content="Tus clientes escanean un QR o tocan la mesa', false)
            ->assertSee('data-texto-soporte="o acerca tu celular a la etiqueta"', false)
            ->assertSee('data-anillos', false)
            ->assertSee('QR + NFC en la mesa')
            ->assertSee('Menú digital con QR o NFC que tú editas')
            ->assertSee('Para tocar la mesa, el celular debe tener NFC.');
    }

    /**
     * @return array<string, array{0: ?string, 1: bool}>
     */
    public static function valoresDeNfc(): array
    {
        return [
            'sin definir' => [null, false],
            'vacío' => ['', false],
            'falso' => ['false', false],
            'texto raro' => ['quizas', false],
            'verdadero' => ['true', true],
            'uno' => ['1', true],
        ];
    }

    #[DataProvider('valoresDeNfc')]
    public function test_la_opcion_de_nfc_solo_se_enciende_con_un_valor_afirmativo(?string $valor, bool $esperado): void
    {
        $this->definirVariableDeNfc($valor);

        try {
            $configuracion = require config_path('menuonl.php');

            $this->assertSame($esperado, $configuracion['nfc_disponible']);
        } finally {
            $this->definirVariableDeNfc(null);
        }
    }

    private function definirVariableDeNfc(?string $valor): void
    {
        if ($valor === null) {
            unset($_SERVER['NFC_DISPONIBLE'], $_ENV['NFC_DISPONIBLE']);
            putenv('NFC_DISPONIBLE');

            return;
        }

        $_SERVER['NFC_DISPONIBLE'] = $_ENV['NFC_DISPONIBLE'] = $valor;
        putenv("NFC_DISPONIBLE={$valor}");
    }

    public function test_los_planes_muestran_los_precios_de_la_configuracion_y_nunca_los_del_prototipo(): void
    {
        config(['menuonl.precios' => ['lite' => 123, 'aura' => 4567, 'nota' => 'Nota de prueba.']]);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['id="plan-lite"', '$123 al mes', 'id="plan-aura"', '$4,567 al mes', 'Nota de prueba.'], false)
            ->assertDontSee('$499')
            ->assertDontSee('$399')
            ->assertDontSee('$199');
    }

    public function test_los_precios_de_la_configuracion_no_se_escriben_a_mano_en_la_vista(): void
    {
        $vista = $this->fuente('resources/views/landing/inicio.blade.php');

        $this->assertStringContainsString("config('menuonl.precios.lite')", $vista);
        $this->assertStringContainsString("config('menuonl.precios.aura')", $vista);
        $this->assertDoesNotMatchRegularExpression('/\$\s?\d{3}/', $vista);
    }

    public function test_el_texto_de_cada_plan_coincide_con_lo_que_el_plan_incluye(): void
    {
        $this->assertFalse(PlanRestaurante::SoloMenu->incluye(Capacidad::Sitio));
        $this->assertFalse(PlanRestaurante::SoloMenu->incluye(Capacidad::Reservas));
        $this->assertTrue(PlanRestaurante::SoloMenu->incluye(Capacidad::EditarMenu));
        $this->assertTrue(PlanRestaurante::SitioMenu->incluye(Capacidad::Sitio));
        $this->assertTrue(PlanRestaurante::SitioMenu->incluye(Capacidad::Reservas));

        $html = $this->portada();
        $inicioLite = strpos($html, 'id="plan-lite"');
        $inicioAura = strpos($html, 'id="plan-aura"');
        $finAura = strpos($html, '</article>', $inicioAura);
        $lite = substr($html, $inicioLite, $inicioAura - $inicioLite);
        $aura = substr($html, $inicioAura, $finAura - $inicioAura);

        foreach (['que tú editas', 'Foto de cada platillo', 'Domicilio o Para recoger', 'No incluye sitio web'] as $texto) {
            $this->assertStringContainsString($texto, $lite);
        }

        foreach (['Galería de fotos', 'Reservas en línea', 'Sitio web con plantilla'] as $texto) {
            $this->assertStringNotContainsString($texto, $lite);
        }

        foreach (['Todo lo de Lite', 'Sitio web con plantilla', 'Reservas en línea', 'sitio web del restaurante', 'Galería de fotos del restaurante'] as $texto) {
            $this->assertStringContainsString($texto, $aura);
        }
    }

    public function test_no_se_promete_un_sitio_propio_ni_dominio_propio(): void
    {
        $html = $this->portada();

        foreach (['sitio web propio', 'sitio propio', 'dominio'] as $texto) {
            $this->assertStringNotContainsStringIgnoringCase($texto, $html);
        }
    }

    public function test_los_llamados_y_el_menu_son_enlaces_reales_y_cada_ancla_tiene_su_destino(): void
    {
        $html = $this->portada();

        preg_match_all('/\sid="([^"]+)"/', $html, $ids);
        preg_match_all('/href="(?:'.preg_quote(route('portada'), '/').')?#([^"]+)"/', $html, $anclas);

        $this->assertNotEmpty($anclas[1]);

        foreach (array_unique($anclas[1]) as $ancla) {
            $this->assertContains($ancla, $ids[1], "El ancla #{$ancla} no tiene destino.");
        }

        foreach (['como-funciona', 'incluye', 'planes', 'preguntas', 'contacto'] as $seccion) {
            $this->assertContains($seccion, $anclas[1], "El menú no enlaza a #{$seccion}.");
        }

        $this->assertMatchesRegularExpression('#<a class="btn btn--primary" href="https://wa\.me/'.self::NUMERO.'[^"]*"[^>]*>.*?Quiero#s', $html);
        $this->assertStringContainsString('<a class="btn btn--secondary" href="#como-funciona">', $html);
        $this->assertStringNotContainsString('class="cta1"', $html);
    }

    public function test_cada_plan_lleva_su_enlace_de_whatsapp_con_su_nombre(): void
    {
        $html = $this->portada();

        foreach (['Lite', 'Aura'] as $plan) {
            $this->assertStringContainsString(rawurlencode("Hola, quiero informes del plan {$plan} de ".config('app.name').'.'), $html);
        }
    }

    public function test_sin_whatsapp_quiero_menubly_lleva_a_los_planes_y_no_queda_un_enlace_vacio(): void
    {
        config(['menuonl.soporte_whatsapp' => null]);

        $html = $this->portada();

        $this->assertStringContainsString('<a class="btn btn--primary" href="#planes"><span>Quiero '.config('app.name').'</span></a>', $html);
        $this->assertStringNotContainsString('wa.me', $html);
        $this->assertStringNotContainsString('href=""', $html);
    }

    public function test_sin_recursos_externos_ni_datos_incrustados(): void
    {
        $fuentes = array_map($this->fuente(...), [...$this->archivosDeLaEscena(), 'resources/css/landing.css']);

        foreach ([$this->portada(), ...$fuentes] as $contenido) {
            foreach (['unpkg', 'googleapis', 'gstatic', 'jsdelivr', 'cdnjs', 'data:image', 'importmap', '2a2gcompany'] as $prohibido) {
                $this->assertStringNotContainsString($prohibido, $contenido);
            }
        }
    }

    public function test_las_fuentes_y_las_imagenes_de_la_escena_son_locales_y_ligeras(): void
    {
        foreach (['plus-jakarta-sans-latin-var.woff2', 'space-grotesk-latin-var.woff2'] as $fuente) {
            $ruta = public_path("fonts/landing/{$fuente}");

            $this->assertFileExists($ruta);
            $this->assertLessThan(20 * 1024, filesize($ruta));
            $this->assertStringContainsString("url(\"/fonts/landing/{$fuente}\")", $this->fuente('resources/css/landing.css'));
        }

        $licencia = $this->fuente('public/fonts/landing/OFL.txt');
        $this->assertStringContainsString('The Plus Jakarta Sans Project Authors', $licencia);
        $this->assertStringContainsString('The Space Grotesk Project Authors', $licencia);
        $this->assertStringContainsString('SIL OPEN FONT LICENSE', $licencia);

        $html = $this->portada();
        foreach (['landing/img/menubly-negativo-306.webp', 'landing/img/menubly-simbolo-128.webp'] as $imagen) {
            $this->assertFileExists(public_path($imagen));
            $this->assertStringContainsString($imagen, $html);
        }
    }

    public function test_sin_javascript_el_html_ya_trae_todo_el_contenido_legible(): void
    {
        $html = $this->portada();

        foreach (['Tu menú,', 'Apunta la cámara.', 'Elige tu', 'id="plan-lite"', 'id="plan-aura"', 'Preguntas frecuentes', 'Platiquemos de tu restaurante', route('privacidad'), route('terminos'), url('/admin')] as $contenido) {
            $this->assertStringContainsString($contenido, $html);
        }

        $this->assertDoesNotMatchRegularExpression('/<html[^>]*landing-3d/', $html, 'El modo 3D solo lo activa el script.');
        $this->assertDoesNotMatchRegularExpression('/<(section|article|main)\b[^>]*\s(hidden|inert)\b/', $html);
        $this->assertStringNotContainsString('<noscript', $html);

        $css = $this->fuente('resources/css/landing.css');
        preg_match_all('/^([^{}\n]*\.tramo[^{}\n]*)\{[^}]*opacity:\s*0/m', $css, $reglasOcultas);

        foreach ($reglasOcultas[1] as $selector) {
            $this->assertStringContainsString('.landing-3d', $selector, 'Fuera del modo 3D nada puede quedar invisible.');
        }
    }

    public function test_la_escena_y_el_criterio_solo_se_cargan_en_la_portada(): void
    {
        $this->assertStringContainsString('js/landing-criterio.js', $this->portada());

        foreach (['/privacidad' => 'Aviso de privacidad', '/terminos' => 'Términos y condiciones'] as $ruta => $encabezado) {
            $html = (string) $this->get($ruta)
                ->assertOk()
                ->assertDontSee('landing-criterio', false)
                ->assertDontSee('<canvas', false)
                ->assertSee('<h1>'.$encabezado.'</h1>', false)
                ->getContent();

            $this->assertDoesNotMatchRegularExpression('#assets/(landing|escena)-[^"]*\.js#', $html);
        }
    }

    public function test_quedaron_fuera_los_atajos_de_desarrollo_y_los_bucles_de_css(): void
    {
        $this->assertStringNotContainsString('id="help"', $this->portada());

        foreach ($this->archivosDeLaEscena() as $archivo) {
            foreach (['keydown', 'BroadcastChannel', 'requestFullscreen', 'startAuto'] as $atajo) {
                $this->assertStringNotContainsString($atajo, $this->fuente($archivo), "{$archivo} conserva {$atajo}.");
            }
        }

        $css = $this->fuente('resources/css/landing.css');
        $this->assertStringNotContainsString('infinite', $css);
        $this->assertStringNotContainsString('scrollbar-width', $css);
    }

    public function test_la_escena_no_pide_nada_que_la_csp_de_la_portada_bloquee(): void
    {
        foreach (["'unsafe-eval'", "'unsafe-inline'"] as $relajada) {
            $this->assertStringNotContainsString($relajada, PoliticaDeContenido::LANDING);
        }

        $this->assertStringContainsString("script-src 'self'", PoliticaDeContenido::LANDING);
        $this->assertStringContainsString("connect-src 'none'", PoliticaDeContenido::LANDING);

        foreach (['fetch(', 'XMLHttpRequest', 'FileLoader', 'GLTFLoader', 'ImageBitmapLoader', 'new Function', 'eval(', 'cssText', "setAttribute('style'"] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $this->fuente('resources/js/landing/escena.js'));
            $this->assertStringNotContainsString($prohibido, $this->fuente('resources/js/landing.js'));
        }
    }

    public function test_three_va_exacto_y_solo_como_importacion_dinamica_del_archivo_compilado(): void
    {
        $paquetes = json_decode($this->fuente('package.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $paquetes['dependencies']['three']);
        $this->assertStringNotContainsString("from 'three", $this->fuente('resources/js/landing.js'));

        $manifiesto = public_path('build/manifest.json');
        if (! is_file($manifiesto)) {
            $this->markTestSkipped('Sin compilar: la prueba del archivo compilado corre en CI, que compila antes de probar.');
        }

        $entradas = json_decode((string) file_get_contents($manifiesto), true, flags: JSON_THROW_ON_ERROR);
        $entrada = $entradas['resources/js/landing.js'];

        $this->assertContains('resources/js/landing/escena.js', $entrada['dynamicImports']);
        $this->assertArrayNotHasKey('imports', $entrada);

        $compilado = (string) file_get_contents(public_path('build/'.$entradas['resources/js/landing/escena.js']['file']));
        $this->assertDoesNotMatchRegularExpression('/\beval\(|new Function/', $compilado);
        $this->assertStringNotContainsStringIgnoringCase('nfc', $compilado);
    }
}
