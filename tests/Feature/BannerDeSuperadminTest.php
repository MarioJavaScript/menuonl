<?php

namespace Tests\Feature;

use App\Filament\Pages\Inicio;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Restaurantes\RestauranteResource;
use App\Models\Restaurante;
use App\Services\BannerDeSuperadmin;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Banner de superadmin (docs/integracion-dashboard.md, PR 4): solo lo ve un superadmin dentro de un restaurante ajeno,
 * nunca en el HTML de los demás roles, y se oculta en cualquier pantalla del grupo de navegación "Plataforma".
 */
class BannerDeSuperadminTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const string MARCA = 'mb-banner--fijo';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    private function banner(): string
    {
        return (string) FilamentView::renderHook(PanelsRenderHook::TOPBAR_BEFORE);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function planes(): array
    {
        return [
            'completo' => ['completo'],
            'sitio y menú' => ['sitio_menu'],
            'solo menú' => ['solo_menu'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesDelRestaurante(): array
    {
        return [
            'propietario' => ['propietario'],
            'editor' => ['editor'],
            'gerente' => ['gerente'],
            'recepción' => ['recepcion'],
            'cocina' => ['cocina'],
        ];
    }

    private function restauranteConPlan(string $plan): Restaurante
    {
        return match ($plan) {
            'sitio_menu' => Restaurante::factory()->sitioMenu()->create(['slug' => 'a']),
            'solo_menu' => Restaurante::factory()->soloMenu()->create(['slug' => 'a']),
            default => Restaurante::factory()->create(['slug' => 'a']),
        };
    }

    #[DataProvider('planes')]
    public function test_el_superadmin_ve_el_banner_dentro_de_un_restaurante_en_cualquier_plan(string $plan): void
    {
        $restaurante = $this->restauranteConPlan($plan);
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $restaurante);

        $html = $this->banner();

        $this->assertStringContainsString(self::MARCA, $html);
        $this->assertStringContainsString('role="region"', $html);
        $this->assertStringContainsString('aria-label="'.e(__('panel.banner_superadmin.etiqueta', ['nombre' => $restaurante->nombre])).'"', $html);
        $this->assertStringContainsString('title="'.e($restaurante->nombre).'"', $html);
        $this->assertStringContainsString('data-nivel="'.$restaurante->plan->nivel().'"', $html);
        $this->assertStringContainsString('href="'.RestauranteResource::getUrl('index').'"', $html);
        $this->assertStringContainsString('aria-label="'.__('panel.banner_superadmin.volver').'"', $html);
        $this->assertSame(1, substr_count($html, self::MARCA));
    }

    #[DataProvider('rolesDelRestaurante')]
    public function test_los_demas_roles_no_reciben_ni_el_html_del_banner(string $rol): void
    {
        $restaurante = $this->restauranteConPlan('completo');
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        $this->assertSame('', trim($this->banner()));
    }

    public function test_sin_restaurante_activo_no_hay_banner(): void
    {
        $this->actingAs($this->crearUsuario('superadmin'));
        Filament::setCurrentPanel('admin');
        Filament::setTenant(null);

        $this->assertSame('', trim($this->banner()));
    }

    public function test_el_superadmin_que_es_miembro_activo_del_restaurante_no_ve_el_banner(): void
    {
        $restaurante = $this->restauranteConPlan('completo');
        $superadmin = $this->crearUsuario('superadmin');
        $superadmin->restaurantes()->attach($restaurante->id, ['rol' => 'propietario']);
        $this->entrarAlPanel($superadmin, $restaurante);

        $this->assertSame('', trim($this->banner()));
    }

    public function test_el_nombre_largo_o_con_marcas_va_completo_y_escapado(): void
    {
        $nombre = 'Restaurante "El gran <b>sabor</b>" '.str_repeat('de la esquina ', 12);
        $restaurante = Restaurante::factory()->create(['slug' => 'a', 'nombre' => $nombre]);
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $restaurante);

        $html = $this->banner();

        $this->assertStringContainsString('title="'.e($nombre).'"', $html);
        $this->assertStringContainsString('aria-label="'.e(__('panel.banner_superadmin.etiqueta', ['nombre' => $nombre])).'"', $html);
        $this->assertStringNotContainsString('<b>sabor</b>', $html);
    }

    public function test_el_banner_no_lleva_scripts_ni_atributos_de_evento(): void
    {
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $this->restauranteConPlan('completo'));

        $html = $this->banner();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_los_textos_son_fijos_en_el_idioma_sin_emojis_ni_la_palabra_carta(): void
    {
        foreach (__('panel.banner_superadmin') as $clave => $texto) {
            $this->assertIsString($texto, $clave);
            $this->assertDoesNotMatchRegularExpression('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', $texto, $clave);
            $this->assertStringNotContainsStringIgnoringCase('carta', $texto, $clave);
        }
    }

    /**
     * Cada recurso o página registrados con el grupo "Plataforma" tiene que ocultar el banner, sea cual sea su ruta.
     * Si alguien añade una pantalla nueva a ese grupo y el banner aparece en ella, esta prueba falla.
     */
    public function test_toda_pantalla_del_grupo_plataforma_oculta_el_banner(): void
    {
        $restaurante = $this->restauranteConPlan('completo');
        $superadmin = $this->crearUsuario('superadmin');
        $this->entrarAlPanel($superadmin, $restaurante);

        $panel = Filament::getPanel('admin');
        $urls = [];

        foreach ($panel->getResources() as $recurso) {
            if (BannerDeSuperadmin::esDePlataforma($recurso)) {
                $urls[$recurso] = array_key_exists('index', $recurso::getPages()) ? $recurso::getUrl('index') : null;
            }
        }

        foreach ($panel->getPages() as $pagina) {
            if (BannerDeSuperadmin::esDePlataforma($pagina)) {
                $urls[$pagina] = $pagina::getUrl();
            }
        }

        $this->assertArrayHasKey(RestauranteResource::class, $urls, 'Restaurantes es la pantalla de plataforma de referencia.');

        foreach ($urls as $clase => $url) {
            $this->assertNotNull($url, "{$clase} es de plataforma pero no tiene página de lista para comprobar.");

            $respuesta = $this->actingAs($superadmin)->get($url);

            $respuesta->assertOk();
            $this->assertStringNotContainsString(self::MARCA, $respuesta->getContent(), "{$clase} ({$url}) muestra el banner dentro de Plataforma.");
        }
    }

    public function test_el_grupo_decide_aunque_la_pantalla_cambie_de_grupo_segun_la_persona(): void
    {
        $restaurante = $this->restauranteConPlan('completo');
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $restaurante);

        $this->assertTrue(BannerDeSuperadmin::esDePlataforma(RestauranteResource::class));
        $this->assertFalse(BannerDeSuperadmin::esDePlataforma(CategoriaResource::class));
        $this->assertFalse(BannerDeSuperadmin::esDePlataforma(Inicio::class));
        $this->assertFalse(BannerDeSuperadmin::esDePlataforma(null));
        $this->assertFalse(BannerDeSuperadmin::esDePlataforma('Clase\\Que\\No\\Existe'));
    }

    public function test_las_paginas_del_restaurante_si_muestran_el_banner_al_superadmin_y_no_al_propietario(): void
    {
        $restaurante = $this->restauranteConPlan('completo');
        $superadmin = $this->crearUsuario('superadmin');
        $propietario = $this->crearUsuario('propietario', $restaurante);

        $conBanner = $this->actingAs($superadmin)->get('/admin/a/categorias')->assertOk()->getContent();
        $sinBanner = $this->actingAs($propietario)->get('/admin/a/categorias')->assertOk()->getContent();

        $this->assertSame(1, substr_count($conBanner, self::MARCA));
        $this->assertStringNotContainsString('mb-banner', $sinBanner);
        $this->assertStringNotContainsString(__('panel.banner_superadmin.volver'), $sinBanner);
    }

    public function test_el_banner_es_lo_primero_tras_saltar_al_contenido_y_va_antes_de_la_barra_superior(): void
    {
        $this->restauranteConPlan('completo');
        $html = $this->actingAs($this->crearUsuario('superadmin'))->get('/admin/a/categorias')->assertOk()->getContent();

        $salto = strpos($html, 'fi-skip-link');
        $banner = strpos($html, self::MARCA);
        $barra = strpos($html, 'fi-topbar');

        $this->assertNotFalse($salto);
        $this->assertNotFalse($banner);
        $this->assertNotFalse($barra);
        $this->assertLessThan($banner, $salto);
        $this->assertLessThan($barra, $banner);
    }

    public function test_la_pagina_con_el_banner_no_es_cacheable(): void
    {
        $this->restauranteConPlan('completo');

        $respuesta = $this->actingAs($this->crearUsuario('superadmin'))->get('/admin/a/categorias')->assertOk();

        $this->assertTrue($respuesta->headers->hasCacheControlDirective('private'), 'Cache-Control: '.$respuesta->headers->get('Cache-Control'));
        $this->assertFalse($respuesta->headers->hasCacheControlDirective('public'));
        $this->assertFalse($respuesta->headers->hasCacheControlDirective('max-age') && (int) $respuesta->headers->getCacheControlDirective('max-age') > 0);
        $this->assertFalse($respuesta->headers->hasCacheControlDirective('s-maxage'));
    }

    public function test_el_banner_no_cambia_la_politica_de_seguridad_del_panel(): void
    {
        $restaurante = $this->restauranteConPlan('completo');

        $deSuperadmin = $this->actingAs($this->crearUsuario('superadmin'))->get('/admin/a/categorias')->assertOk();
        $dePropietario = $this->actingAs($this->crearUsuario('propietario', $restaurante))->get('/admin/a/categorias')->assertOk();

        $this->assertNotNull($deSuperadmin->headers->get('Content-Security-Policy'));
        $this->assertSame(
            preg_replace("/'nonce-[^']+'/", "'nonce-x'", $dePropietario->headers->get('Content-Security-Policy')),
            preg_replace("/'nonce-[^']+'/", "'nonce-x'", $deSuperadmin->headers->get('Content-Security-Policy')),
        );
    }

    public function test_el_destino_de_vuelta_lo_protege_la_politica_en_el_servidor(): void
    {
        $restaurante = $this->restauranteConPlan('completo');
        $destino = RestauranteResource::getUrl('index', panel: 'admin', tenant: $restaurante);

        $this->actingAs($this->crearUsuario('superadmin'))->get($destino)->assertOk();

        foreach (['propietario', 'editor', 'gerente', 'recepcion', 'cocina'] as $rol) {
            $this->actingAs($this->crearUsuario($rol, $restaurante))->get($destino)->assertForbidden();
        }
    }

    public function test_el_css_solo_compensa_cuando_el_banner_esta_renderizado(): void
    {
        $css = (string) file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertStringContainsString('--mb-banner-alto: 3.25rem;', $css);

        preg_match_all('/([^{}]+)\{([^{}]*--mb-banner-activo[^{}]*)\}/', $css, $reglas, PREG_SET_ORDER);

        $this->assertNotEmpty($reglas);

        foreach ($reglas as $regla) {
            $this->assertStringContainsString(':has(.mb-banner--fijo)', $regla[1], 'Regla de compensación sin condición: '.trim($regla[1]));
        }

        preg_match_all('/([^{}]+)\{[^{}]*(?:\bsticky\b|--mb-banner-alto)[^{}]*\}/', $css, $otras, PREG_SET_ORDER);

        foreach ($otras as $regla) {
            $selector = trim($regla[1]);
            $this->assertTrue(
                str_contains($selector, 'mb-banner--fijo') || $selector === ':root',
                "La regla '{$selector}' usa el alto del banner o lo fija sin pertenecer a él.",
            );
        }
    }
}
