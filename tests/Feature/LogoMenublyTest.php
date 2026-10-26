<?php

namespace Tests\Feature;

use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logo oficial de Menubly (public/brand): archivos, SVG sin código activo, componente x-mb.logo, favicons y pantallas
 * donde aparece. Todo propio: ninguna imagen ni icono sale de otro origen.
 */
class LogoMenublyTest extends TestCase
{
    use RefreshDatabase;

    private const ARCHIVOS = [
        'menubly-horizontal-color.svg',
        'menubly-horizontal-negativo.svg',
        'menubly-simbolo-color.svg',
        'menubly-wordmark-color.svg',
        'menubly-wordmark-negativo.svg',
        'favicon.svg',
        'favicon.ico',
        'apple-touch-icon.png',
    ];

    private const URLS_PUBLICAS = ['/', '/admin/login', '/invitaciones/token-que-no-existe'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.name' => 'Marca Fija de Prueba']);
    }

    /**
     * @return list<string>
     */
    private function svgs(): array
    {
        return [...glob(public_path('brand/*.svg')), public_path('favicon.svg')];
    }

    private function menuActivo(): Restaurante
    {
        $restaurante = Restaurante::factory()->create(['slug' => 'logo-fijo', 'estado' => 'activo']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'es']);

        return $restaurante;
    }

    /**
     * @return list<string>
     */
    private function urlsConMenu(): array
    {
        return [...self::URLS_PUBLICAS, route('menu.publico', ['slug' => $this->menuActivo()->slug])];
    }

    public function test_los_archivos_de_marca_existen_y_la_raiz_tiene_favicon_svg_e_ico(): void
    {
        foreach (self::ARCHIVOS as $archivo) {
            $this->assertFileExists(public_path('brand/'.$archivo));
            $this->assertGreaterThan(0, filesize(public_path('brand/'.$archivo)), $archivo);
        }

        $this->assertFileEquals(public_path('brand/favicon.svg'), public_path('favicon.svg'));
        $this->assertFileEquals(public_path('brand/favicon.ico'), public_path('favicon.ico'));
    }

    public function test_ningun_svg_tiene_codigo_activo_ni_referencias_externas(): void
    {
        $prohibidos = ['<script', 'foreignobject', 'xlink:href', 'href=', '@import', 'url(', '<image', 'data:', '<style', '<use', '<!doctype', '<!entity'];

        foreach ($this->svgs() as $ruta) {
            $svg = strtolower((string) file_get_contents($ruta));
            $this->assertStringStartsWith('<svg', $svg, basename($ruta));

            foreach ($prohibidos as $texto) {
                $this->assertStringNotContainsString($texto, $svg, basename($ruta).' contiene '.$texto);
            }

            $this->assertSame(0, preg_match('/\son[a-z]+\s*=/', $svg), basename($ruta).' tiene un atributo on*');
            $this->assertLessThan(10 * 1024, filesize($ruta), basename($ruta).' pesa demasiado');
        }
    }

    public function test_el_componente_pone_dimensiones_explicitas_y_alt_segun_el_uso(): void
    {
        $html = view('components.mb.logo', ['variante' => 'horizontal', 'alto' => 32])->render();

        $this->assertSame(2, substr_count($html, '<img'));
        $this->assertStringContainsString('width="153" height="32"', $html);
        $this->assertStringNotContainsString('2440', $html);
        $this->assertSame(2, substr_count($html, 'alt="Marca Fija de Prueba"'));
        $this->assertStringContainsString('menubly-horizontal-color.svg?v=', $html);
        $this->assertStringContainsString('menubly-horizontal-negativo.svg?v=', $html);
        $this->assertSame(1, substr_count($html, ' hidden'));

        $decorativo = view('components.mb.logo', ['variante' => 'wordmark', 'alto' => 16, 'decorativo' => true])->render();
        $this->assertSame(2, substr_count($decorativo, 'alt=""'));
        $this->assertStringContainsString('width="71" height="16"', $decorativo);

        $simbolo = view('components.mb.logo', ['variante' => 'simbolo', 'alto' => 48])->render();
        $this->assertSame(1, substr_count($simbolo, '<img'));
        $this->assertStringContainsString('width="48" height="48"', $simbolo);
    }

    public function test_las_reglas_de_claro_y_oscuro_estan_en_su_hoja_sin_javascript(): void
    {
        $publico = (string) file_get_contents(public_path('css/menuonl.css'));
        $this->assertStringContainsString(':root[data-apariencia="dark"] .mb-logo--claro { display: none; }', $publico);
        $this->assertStringContainsString(':root[data-apariencia="dark"] .mb-logo--oscuro { display: inline; }', $publico);
        $this->assertMatchesRegularExpression('/@media \(prefers-color-scheme: dark\) \{\s*:root\[data-apariencia="system"\] \.mb-logo--claro/', $publico);

        $admin = (string) file_get_contents(resource_path('css/filament/admin/theme.css'));
        $this->assertStringContainsString(':root.dark .mb-logo--claro { display: none; }', $admin);
        $this->assertStringContainsString(':root.dark .mb-logo--oscuro { display: inline; }', $admin);
    }

    public function test_el_logo_aparece_en_login_portada_invitaciones_y_pie_del_menu(): void
    {
        $restaurante = $this->menuActivo();

        $this->get('/admin/login')->assertOk()->assertSee('brand/menubly-horizontal-color.svg', false)->assertSee('alt="Marca Fija de Prueba"', false);
        $this->get('/')->assertOk()->assertSee('brand/menubly-horizontal-color.svg', false)->assertDontSee('>M</span>', false);
        $this->get('/invitaciones/token-que-no-existe')->assertSee('brand/menubly-horizontal-color.svg', false)->assertDontSee('>M</span>', false);
        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk()->assertSee('brand/menubly-wordmark-color.svg', false);
    }

    public function test_la_pantalla_sin_panel_lleva_el_logo_y_sus_reglas_de_oscuro(): void
    {
        $html = view('plan.sin-panel', ['restaurantes' => ['Fonda Uno'], 'enlaceWhatsapp' => null, 'urlCerrarSesion' => '/salir'])->render();

        $this->assertStringContainsString('brand/menubly-horizontal-color.svg', $html);
        $this->assertStringContainsString('brand/menubly-horizontal-negativo.svg', $html);
        $this->assertStringContainsString(':root[data-apariencia="dark"] .mb-logo--claro { display: none; }', $html);
    }

    public function test_los_favicons_se_declaran_con_version_en_todas_las_pestanas(): void
    {
        foreach ($this->urlsConMenu() as $url) {
            $respuesta = $this->get($url);

            $respuesta->assertSee('favicon.svg?v=', false);
            $respuesta->assertSee('favicon.ico?v=', false);
            $respuesta->assertSee('brand/apple-touch-icon.png?v=', false);
        }

        $plantillas = glob(resource_path('views/components/sitio/{,*/}layout.blade.php'), GLOB_BRACE);
        $this->assertNotEmpty($plantillas);
        foreach ($plantillas as $plantilla) {
            $this->assertStringContainsString('<x-mb.favicons />', (string) file_get_contents($plantilla), $plantilla);
        }
    }

    public function test_ninguna_pagina_carga_imagenes_ni_iconos_de_otro_origen(): void
    {
        $propio = parse_url(url('/'), PHP_URL_HOST);

        foreach ($this->urlsConMenu() as $url) {
            $html = $this->get($url)->getContent();

            preg_match_all('/<img\b[^>]*\ssrc="([^"]+)"|<link\b[^>]*\srel="(?:icon|apple-touch-icon)"[^>]*\shref="([^"]+)"/', $html, $coincidencias, PREG_SET_ORDER);
            $this->assertNotEmpty($coincidencias, $url);

            foreach ($coincidencias as $coincidencia) {
                $ruta = $coincidencia[2] ?? $coincidencia[1];
                $host = parse_url(html_entity_decode($ruta), PHP_URL_HOST);
                $this->assertTrue($host === null || $host === $propio, "$url carga $ruta de otro origen");
            }
        }
    }
}
