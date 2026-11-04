<?php

namespace Tests\Feature;

use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RevisaAtributosWire;
use Tests\TestCase;

/**
 * Logo del restaurante en la cabecera del sitio (plantillas b, d, e, f, g y h) y en el menú público.
 *
 * Con logo se ve el logo junto al nombre; sin logo, solo el nombre. En las cabeceras oscuras (b, d, e sobre su bloque
 * arcilla y f) el logo va dentro de un fondo claro redondeado (cada hoja con su propia clase). En el menú el logo usa contain, no
 * cover, y en modo oscuro su marco pasa a un claro fijo. Usa withoutVite() y una hora fija: no depende de public/build ni
 * del reloj.
 */
class LogoDelRestauranteTest extends TestCase
{
    use RefreshDatabase, RevisaAtributosWire;

    private const string HOST_DE_PRODUCCION = 'https://menubly.2a2gcompany.com';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withoutVite();
        $this->travelTo('2026-10-22 02:00:00');
    }

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    /**
     * Plantilla y clase del fondo claro del logo; null en las de cabecera clara, que no lo llevan.
     *
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function plantillas(): array
    {
        return [
            'b oscuro elegante' => ['b', 'logo-sobre-claro'],
            'd oscuro con detalles dorados' => ['d', 'logo-sobre-claro'],
            'e cafetería (cabecera sobre arcilla)' => ['e', 'cal-cabecera__logo'],
            'f bar' => ['f', 'bar-marca__fondo'],
            'g café pastel' => ['g', null],
            'h pizzería' => ['h', null],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function temasDelMenu(): array
    {
        return [
            'cálido' => ['calido'],
            'editorial oscuro' => ['editorial'],
            'rústico' => ['rustico'],
            'clásico' => ['clasico'],
        ];
    }

    private function restaurante(string $plantilla, ?string $logo, string $tema = 'calido'): Restaurante
    {
        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Casa Lumbre',
            'slug' => 'casa-lumbre',
            'sitio_plantilla' => $plantilla,
            'tema' => $tema,
        ]);

        if ($logo !== null) {
            $restaurante->forceFill(['logo' => "restaurantes/{$restaurante->id}/logos/{$logo}"])->saveQuietly();
        }

        return $restaurante;
    }

    private function xpathDe(string $html): DOMXPath
    {
        return new DOMXPath($this->documento($html));
    }

    /**
     * Imágenes dentro de la primera cabecera de la página (la del sitio o la del menú de la plantilla).
     *
     * @return list<\DOMElement>
     */
    private function imagenesDeLaCabecera(string $html): array
    {
        return iterator_to_array($this->xpathDe($html)->query('(//header)[1]//img'), false);
    }

    #[DataProvider('plantillas')]
    public function test_la_cabecera_muestra_el_logo_junto_al_nombre_cuando_existe(string $plantilla, ?string $claseDelFondo): void
    {
        $restaurante = $this->restaurante($plantilla, 'lumbre.webp');

        $respuesta = $this->get(route('sitio.publico', ['slug' => 'casa-lumbre']))->assertOk();
        $html = $respuesta->getContent();

        $imagenes = $this->imagenesDeLaCabecera($html);
        $this->assertCount(1, $imagenes, "La cabecera de la plantilla {$plantilla} debe traer el logo.");
        $this->assertStringEndsWith("restaurantes/{$restaurante->id}/logos/lumbre.webp", $imagenes[0]->getAttribute('src'));
        $this->assertSame('', $imagenes[0]->getAttribute('alt'), 'El nombre ya va en texto: el logo es decorativo.');
        $this->assertStringContainsString('Casa Lumbre', (string) $this->xpathDe($html)->query('(//header)[1]')->item(0)?->textContent);
        $this->assertAtributosDeEventosLimpios($html);
    }

    #[DataProvider('plantillas')]
    public function test_sin_logo_la_cabecera_muestra_solo_el_nombre(string $plantilla, ?string $claseDelFondo): void
    {
        $this->restaurante($plantilla, null);

        $html = $this->get(route('sitio.publico', ['slug' => 'casa-lumbre']))->assertOk()->getContent();

        $this->assertSame([], $this->imagenesDeLaCabecera($html), "Sin logo, la plantilla {$plantilla} no pinta imagen en la cabecera.");
        if ($claseDelFondo !== null) {
            $this->assertStringNotContainsString($claseDelFondo.'"', $html);
        }
        $this->assertStringContainsString('Casa Lumbre', (string) $this->xpathDe($html)->query('(//header)[1]')->item(0)?->textContent);
    }

    #[DataProvider('plantillas')]
    public function test_las_cabeceras_oscuras_ponen_el_logo_sobre_un_fondo_claro(string $plantilla, ?string $claseDelFondo): void
    {
        $this->restaurante($plantilla, 'lumbre.webp');

        $html = $this->get(route('sitio.publico', ['slug' => 'casa-lumbre']))->assertOk()->getContent();
        $hoja = (string) file_get_contents(resource_path("css/sitio/{$plantilla}.css"));

        if ($claseDelFondo === null) {
            $this->assertSame(0, $this->xpathDe($html)->query('(//header)[1]//span/img')->length, "La cabecera clara de {$plantilla} no envuelve el logo.");
            $this->assertStringNotContainsString('logo-sobre-claro', $hoja);

            return;
        }

        $logos = $this->xpathDe($html)->query("(//header)[1]//span[contains(concat(' ', normalize-space(@class), ' '), ' {$claseDelFondo} ')]/img");
        $this->assertSame(1, $logos->length, "El logo de {$plantilla} debe ir dentro de .{$claseDelFondo}.");

        $clase = preg_quote($claseDelFondo, '/');
        $this->assertSame(1, preg_match('/\.'.$clase.'\s*\{([^}]*)\}/', $hoja, $regla), "Falta la regla .{$claseDelFondo} en {$plantilla}.css.");
        $this->assertMatchesRegularExpression('/border-radius:\s*(?:0?\.[0-9]*[1-9]|[1-9])/', $regla[1], 'El fondo del logo va redondeado.');
        $this->assertSame(1, preg_match('/background:\s*var\((--[a-z-]+)\)/', $regla[1], $variable));
        $this->assertGreaterThan(0.8, $this->luminancia($this->colorDeLaVariable($hoja, $variable[1])), "El fondo del logo de {$plantilla} debe ser claro.");
        $this->assertMatchesRegularExpression('/\.'.$clase.'\s+\.[a-z_-]+\s*\{[^}]*height:\s*100%/', $hoja, 'El logo llena su fondo sin recortarse.');
    }

    /**
     * Sigue var(--a) -> var(--b) -> #hex dentro de la hoja hasta dar con el color.
     */
    private function colorDeLaVariable(string $hoja, string $variable): string
    {
        for ($saltos = 0; $saltos < 5; $saltos++) {
            $this->assertSame(1, preg_match('/'.preg_quote($variable, '/').':\s*([^;]+);/', $hoja, $valor), "No se encontró {$variable}.");
            $valor = trim($valor[1]);
            if (preg_match('/\Avar\((--[a-z-]+)\)\z/', $valor, $siguiente) !== 1) {
                return $valor;
            }
            $variable = $siguiente[1];
        }

        $this->fail("{$variable} no termina en un color.");
    }

    /**
     * Luminancia relativa (WCAG) de un #rrggbb.
     */
    private function luminancia(string $hex): float
    {
        $this->assertMatchesRegularExpression('/\A#[0-9a-fA-F]{6}\z/', $hex);
        [$rojo, $verde, $azul] = array_map(function (string $par): float {
            $canal = hexdec($par) / 255;

            return $canal <= 0.03928 ? $canal / 12.92 : (($canal + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        return 0.2126 * $rojo + 0.7152 * $verde + 0.0722 * $azul;
    }

    #[DataProvider('temasDelMenu')]
    public function test_el_menu_publico_muestra_el_logo_en_su_marco_con_cada_estilo(string $tema): void
    {
        $restaurante = $this->restaurante('b', 'lumbre.webp', $tema);

        $html = $this->get(route('menu.publico', ['slug' => 'casa-lumbre']))->assertOk()->getContent();
        $xpath = $this->xpathDe($html);

        $this->assertMatchesRegularExpression('/<body[^>]*\sdata-tema="'.$tema.'"/', $html);
        foreach (['hero-logo--imagen', 'barra-logo--imagen'] as $marco) {
            $imagenes = $xpath->query("//span[contains(concat(' ', normalize-space(@class), ' '), ' {$marco} ')]/img");
            $this->assertSame(1, $imagenes->length, "Falta el logo en .{$marco} con el estilo {$tema}.");
            $this->assertStringEndsWith("restaurantes/{$restaurante->id}/logos/lumbre.webp", $imagenes->item(0)->getAttribute('src'));
        }
        $this->assertStringNotContainsString('hero-monograma', $html);
    }

    public function test_sin_logo_el_menu_publico_muestra_la_inicial(): void
    {
        $this->restaurante('b', null, 'editorial');

        $html = $this->get(route('menu.publico', ['slug' => 'casa-lumbre']))->assertOk()->getContent();

        $this->assertStringContainsString('hero-monograma', $html);
        $this->assertStringContainsString('barra-monograma', $html);
        $this->assertStringNotContainsString('hero-logo--imagen', $html);
        $this->assertStringNotContainsString('barra-logo--imagen', $html);
    }

    public function test_el_logo_del_menu_usa_contain_y_no_cover(): void
    {
        $hoja = (string) file_get_contents(public_path('css/menuonl.css'));

        foreach (['.hero-logo', '.barra-logo'] as $selector) {
            $this->assertSame(1, preg_match('/'.preg_quote($selector, '/').'\s*\{([^}]*)\}/', $hoja, $bloque), "Falta la regla {$selector}.");
            $this->assertStringContainsString('object-fit: contain', $bloque[1]);
            $this->assertStringNotContainsString('cover', $bloque[1], "{$selector} no debe recortar el logo.");
        }
        foreach (['.hero-logo--imagen img', '.barra-logo--imagen img'] as $selector) {
            $this->assertMatchesRegularExpression('/'.preg_quote($selector, '/').'\s*\{[^}]*object-fit:\s*contain/', $hoja);
        }
    }

    public function test_en_modo_oscuro_el_marco_del_logo_del_menu_es_claro(): void
    {
        $hoja = (string) file_get_contents(public_path('css/menuonl.css'));
        $fondoClaro = '/\{\s*background-color:\s*#f4f1ea;\s*border-color:\s*#f4f1ea;\s*\}/';

        $this->assertMatchesRegularExpression('/:root\[data-apariencia="dark"\] \.hero-logo--imagen,\s*:root\[data-apariencia="dark"\] \.barra-logo--imagen\s*'.trim($fondoClaro, '/').'/', $hoja);
        $this->assertMatchesRegularExpression('/@media \(prefers-color-scheme: dark\) \{\s*:root\[data-apariencia="system"\] \.hero-logo--imagen,\s*:root\[data-apariencia="system"\] \.barra-logo--imagen\s*'.trim($fondoClaro, '/').'/', $hoja);
    }

    public function test_la_url_del_logo_sale_con_el_host_de_produccion_en_https(): void
    {
        $discoPublico = $this->configuracionDeDiscosCon(self::HOST_DE_PRODUCCION)['disks']['public'];
        $this->assertSame(self::HOST_DE_PRODUCCION.'/storage', $discoPublico['url']);
        Storage::fake('public', ['url' => $discoPublico['url']]);

        $restaurante = $this->restaurante('b', 'lumbre.webp');
        $esperada = self::HOST_DE_PRODUCCION."/storage/restaurantes/{$restaurante->id}/logos/lumbre.webp";

        $sitio = $this->get(route('sitio.publico', ['slug' => 'casa-lumbre']))->assertOk()->getContent();
        $this->assertSame($esperada, $this->imagenesDeLaCabecera($sitio)[0]->getAttribute('src'));

        $menu = $this->get(route('menu.publico', ['slug' => 'casa-lumbre']))->assertOk()->getContent();
        $fuentes = array_map(
            fn (\DOMElement $imagen): string => $imagen->getAttribute('src'),
            iterator_to_array($this->xpathDe($menu)->query('//span[contains(@class, "logo--imagen")]/img'), false),
        );
        $this->assertSame([$esperada, $esperada], $fuentes);

        foreach ([$sitio, $menu] as $html) {
            $this->assertStringNotContainsString('http://menubly.2a2gcompany.com', $html, 'Nada de contenido mixto.');
        }
    }

    /**
     * Vuelve a leer config/filesystems.php con un APP_URL dado, como lo haría producción, y deja el entorno como estaba.
     *
     * @return array{disks: array<string, array<string, mixed>>}
     */
    private function configuracionDeDiscosCon(string $appUrl): array
    {
        $servidorAntes = $_SERVER['APP_URL'] ?? null;
        $entornoAntes = $_ENV['APP_URL'] ?? null;
        $_SERVER['APP_URL'] = $appUrl;
        $_ENV['APP_URL'] = $appUrl;

        try {
            return require config_path('filesystems.php');
        } finally {
            if ($servidorAntes === null) {
                unset($_SERVER['APP_URL']);
            } else {
                $_SERVER['APP_URL'] = $servidorAntes;
            }
            if ($entornoAntes === null) {
                unset($_ENV['APP_URL']);
            } else {
                $_ENV['APP_URL'] = $entornoAntes;
            }
        }
    }
}
