<?php

namespace Tests\Feature;

use App\Services\PaletaDelPanel;
use App\Services\PoliticaDeContenido;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\View\Components\ButtonComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Tema del admin (docs/integracion-dashboard.md, PR 1): tokens --mb-*, paleta de Filament, foco, fuentes del mismo origen y
 * contrastes WCAG AA calculados desde los valores declarados (no copiados de ningún documento).
 */
class TemaDelAdminTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const array TOKENS_MB = [
        'lienzo', 'superficie', 'superficie-2', 'tinta', 'tinta-2', 'tinta-3', 'accion', 'accion-hover', 'sobre-accion',
        'foco', 'linea', 'borde-control', 'fuente-titulo', 'fuente-texto', 'sombra-1', 'sombra-2', 'sombra-3',
    ];

    /**
     * Los --mo-* existentes (los comparte el menú público y la barra inferior): no deben cambiar.
     */
    private const array MO_CLARO = [
        'fuente' => "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
        'fondo' => '#f5f5f7', 'superficie' => '#ffffff', 'superficie-2' => '#ececf0', 'borde' => '#e3e3e8',
        'borde-control' => '#86868b', 'texto' => '#1d1d1f', 'texto-fuerte' => '#000000', 'texto-medio' => '#424245',
        'texto-suave' => '#636366', 'foco' => '#0066cc', 'tactil' => '44px',
        'sombra-flotante' => '0 12px 32px -8px rgba(0, 0, 0, 0.3)',
    ];

    private const array MO_OSCURO = [
        'fondo' => '#000000', 'superficie' => '#1c1c1e', 'superficie-2' => '#2c2c2e', 'borde' => '#38383a',
        'borde-control' => '#8e8e93', 'texto' => '#f2f2f7', 'texto-fuerte' => '#ffffff', 'texto-medio' => '#d1d1d6',
        'texto-suave' => '#a1a1a6', 'foco' => '#4da3ff',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    private function tema(): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(resource_path('css/filament/admin/theme.css')));
    }

    /**
     * Declaraciones --nombre: valor; de un bloque que empieza en $selector (hasta su primera llave de cierre).
     *
     * @return array<string, string>
     */
    private function bloque(string $css, string $selector): array
    {
        $inicio = strpos($css, "\n".$selector.' {');
        $this->assertNotFalse($inicio, "No existe el bloque {$selector}.");
        $cuerpo = substr($css, $inicio + strlen($selector) + 3, strpos($css, "\n}", $inicio) - $inicio - strlen($selector) - 3);

        preg_match_all('/^\s*--([a-z0-9-]+):\s*(.+?);\s*$/m', $cuerpo, $coincidencias, PREG_SET_ORDER);

        return array_column($coincidencias, 2, 1);
    }

    private static function luminancia(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $canales = array_map(function (string $par): float {
            $c = hexdec($par) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));

        return 0.2126 * $canales[0] + 0.7152 * $canales[1] + 0.0722 * $canales[2];
    }

    private static function contraste(string $a, string $b): float
    {
        [$mayor, $menor] = [max(self::luminancia($a), self::luminancia($b)), min(self::luminancia($a), self::luminancia($b))];

        return ($mayor + 0.05) / ($menor + 0.05);
    }

    public function test_los_tokens_mb_existen_en_claro_y_en_oscuro(): void
    {
        $claro = $this->bloque($this->tema(), ':root');
        $oscuro = $this->bloque($this->tema(), ':root.dark');

        foreach (self::TOKENS_MB as $token) {
            $this->assertArrayHasKey('mb-'.$token, $claro, "Falta --mb-{$token} en :root.");
        }

        foreach (array_diff(self::TOKENS_MB, ['fuente-titulo', 'fuente-texto']) as $token) {
            $this->assertArrayHasKey('mb-'.$token, $oscuro, "Falta --mb-{$token} en :root.dark.");
        }

        $this->assertSame('2.75rem', $claro['mb-objetivo']);
        $this->assertSame('3px', $claro['mb-foco-grosor']);
    }

    public function test_los_tokens_mo_existentes_no_cambiaron(): void
    {
        $claro = $this->bloque($this->tema(), ':root');
        $oscuro = $this->bloque($this->tema(), ':root.dark');

        foreach (self::MO_CLARO as $token => $valor) {
            $this->assertSame($valor, $claro['mo-'.$token], "--mo-{$token} (claro) cambió.");
        }

        foreach (self::MO_OSCURO as $token => $valor) {
            $this->assertSame($valor, $oscuro['mo-'.$token], "--mo-{$token} (oscuro) cambió.");
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: float}>
     */
    public static function paresDeContraste(): array
    {
        return [
            'claro: texto sobre superficie' => ['claro', 'mb-tinta', 'mb-superficie', 4.5],
            'claro: texto sobre lienzo' => ['claro', 'mb-tinta', 'mb-lienzo', 4.5],
            'claro: texto secundario sobre superficie' => ['claro', 'mb-tinta-2', 'mb-superficie', 4.5],
            'claro: texto secundario sobre lienzo' => ['claro', 'mb-tinta-2', 'mb-lienzo', 4.5],
            'claro: texto de ayuda sobre superficie' => ['claro', 'mb-tinta-3', 'mb-superficie', 4.5],
            'claro: texto de ayuda sobre lienzo' => ['claro', 'mb-tinta-3', 'mb-lienzo', 4.5],
            'claro: enlace sobre superficie' => ['claro', 'mb-accion', 'mb-superficie', 4.5],
            'claro: enlace sobre lienzo' => ['claro', 'mb-accion', 'mb-lienzo', 4.5],
            'claro: texto del boton sobre accion' => ['claro', 'mb-sobre-accion', 'mb-accion', 4.5],
            'claro: texto del boton sobre hover' => ['claro', 'mb-sobre-accion', 'mb-accion-hover', 4.5],
            'claro: foco sobre superficie' => ['claro', 'mb-foco', 'mb-superficie', 3.0],
            'claro: foco sobre lienzo' => ['claro', 'mb-foco', 'mb-lienzo', 3.0],
            'claro: borde de campo sobre superficie' => ['claro', 'mb-borde-control', 'mb-superficie', 3.0],
            'claro: borde de campo sobre lienzo' => ['claro', 'mb-borde-control', 'mb-lienzo', 3.0],
            'oscuro: texto sobre lienzo' => ['oscuro', 'mb-tinta', 'mb-lienzo', 4.5],
            'oscuro: texto sobre superficie' => ['oscuro', 'mb-tinta', 'mb-superficie', 4.5],
            'oscuro: texto secundario sobre superficie' => ['oscuro', 'mb-tinta-2', 'mb-superficie', 4.5],
            'oscuro: texto de ayuda sobre superficie' => ['oscuro', 'mb-tinta-3', 'mb-superficie', 4.5],
            'oscuro: enlace sobre lienzo' => ['oscuro', 'mb-accion', 'mb-lienzo', 4.5],
            'oscuro: enlace sobre superficie' => ['oscuro', 'mb-accion', 'mb-superficie', 4.5],
            'oscuro: texto del boton sobre accion' => ['oscuro', 'mb-sobre-accion', 'mb-accion', 4.5],
            'oscuro: texto del boton sobre hover' => ['oscuro', 'mb-sobre-accion', 'mb-accion-hover', 4.5],
            'oscuro: limite del boton sobre superficie' => ['oscuro', 'mb-accion', 'mb-superficie', 3.0],
            'oscuro: foco sobre superficie' => ['oscuro', 'mb-foco', 'mb-superficie', 3.0],
            'oscuro: foco sobre lienzo' => ['oscuro', 'mb-foco', 'mb-lienzo', 3.0],
            'oscuro: borde de campo sobre superficie' => ['oscuro', 'mb-borde-control', 'mb-superficie', 3.0],
            'oscuro: borde de campo sobre lienzo' => ['oscuro', 'mb-borde-control', 'mb-lienzo', 3.0],
            'claro: menu activo (texto) sobre su fondo' => ['claro', 'mb-accion-activo', 'mb-superficie-2', 4.5],
            'claro: barra inferior activa sobre superficie' => ['claro', 'mb-accion-activo', 'mb-superficie', 4.5],
            'claro: indicador del menu activo sobre su fondo' => ['claro', 'mb-accion', 'mb-superficie-2', 3.0],
            'claro: texto del menu sobre su fondo activo' => ['claro', 'mb-tinta-2', 'mb-superficie-2', 4.5],
            'claro: grupo del menu sobre lienzo' => ['claro', 'mb-tinta-2', 'mb-lienzo', 4.5],
            'oscuro: menu activo (texto) sobre su fondo' => ['oscuro', 'mb-accion-activo', 'mb-superficie-2', 4.5],
            'oscuro: barra inferior activa sobre superficie' => ['oscuro', 'mb-accion-activo', 'mb-superficie', 4.5],
            'oscuro: indicador del menu activo sobre su fondo' => ['oscuro', 'mb-accion', 'mb-superficie-2', 3.0],
            'oscuro: texto del menu sobre su fondo activo' => ['oscuro', 'mb-tinta-2', 'mb-superficie-2', 4.5],
            'oscuro: grupo del menu sobre lienzo' => ['oscuro', 'mb-tinta-2', 'mb-lienzo', 4.5],
        ];
    }

    #[DataProvider('paresDeContraste')]
    public function test_los_pares_de_color_cumplen_wcag_aa(string $modo, string $primero, string $segundo, float $minimo): void
    {
        $tokens = $this->bloque($this->tema(), $modo === 'claro' ? ':root' : ':root.dark');
        $ratio = self::contraste($tokens[$primero], $tokens[$segundo]);

        $this->assertGreaterThanOrEqual($minimo, $ratio, "--{$primero} sobre --{$segundo} ({$modo}) da ".round($ratio, 2).':1.');
    }

    public function test_la_paleta_del_panel_tiene_los_once_tonos_y_el_botones_cumplen_aa(): void
    {
        $tonos = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

        foreach ([PaletaDelPanel::PRIMARIO, PaletaDelPanel::GRIS] as $escala) {
            $this->assertSame($tonos, array_keys($escala));

            foreach ($escala as $hex) {
                $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $hex);
            }
        }

        $primario = PaletaDelPanel::PRIMARIO;
        $this->assertSame('#1B62B5', $primario[600]);
        $this->assertSame('#7CC4F8', $primario[400]);

        // Claro: texto 50 sobre el fondo 600, y sobre el hover 700 que fija theme.css.
        $this->assertGreaterThanOrEqual(4.5, self::contraste($primario[50], $primario[600]));
        $this->assertGreaterThanOrEqual(4.5, self::contraste($primario[50], $primario[700]));
        // Oscuro: texto marino (950) sobre el azul bebé (400) y sobre su hover (300).
        $this->assertGreaterThanOrEqual(4.5, self::contraste($primario[950], $primario[400]));
        $this->assertGreaterThanOrEqual(4.5, self::contraste($primario[950], $primario[300]));
        // Enlaces: 600 en claro y 400 en oscuro.
        $this->assertGreaterThanOrEqual(4.5, self::contraste($primario[600], PaletaDelPanel::GRIS[50]));
        $this->assertGreaterThanOrEqual(4.5, self::contraste($primario[400], PaletaDelPanel::GRIS[900]));
        // El gris 950 y el 50 son el fondo y el lienzo de los tokens.
        $claro = $this->bloque($this->tema(), ':root');
        $oscuro = $this->bloque($this->tema(), ':root.dark');
        $this->assertSame(strtolower(PaletaDelPanel::GRIS[50]), $claro['mb-lienzo']);
        $this->assertSame(strtolower(PaletaDelPanel::GRIS[950]), $oscuro['mb-lienzo']);
        $this->assertSame(strtolower(PaletaDelPanel::GRIS[900]), $oscuro['mb-superficie']);
        $this->assertSame(strtolower($primario[600]), $claro['mb-accion']);
        $this->assertSame(strtolower($primario[400]), $oscuro['mb-accion']);
    }

    public function test_el_panel_registra_la_paleta_y_filament_elige_el_tono_600_con_texto_50(): void
    {
        $this->get('/admin/login')->assertOk();

        $this->assertSame(Color::convertToOklch(PaletaDelPanel::PRIMARIO[600]), FilamentColor::getColor('primary')[600]);
        $this->assertSame(Color::convertToOklch(PaletaDelPanel::GRIS[950]), FilamentColor::getColor('gray')[950]);

        $mapa = ButtonComponent::make()->getColorMap(FilamentColor::getColor('primary'));
        $this->assertSame(600, $mapa['bg']);
        $this->assertSame(50, $mapa['text']);
    }

    public function test_el_inicio_de_sesion_publica_la_paleta_y_no_carga_fuentes_externas(): void
    {
        $html = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('--primary-600:'.Color::convertToOklch(PaletaDelPanel::PRIMARIO[600]), $html);
        $this->assertDoesNotMatchRegularExpression('/rel="preload"[^>]*inter-latin/i', $html, 'Filament no debe precargar Inter, que el tema no usa.');
        $this->assertMatchesRegularExpression('/--font-family:\s*.Figtree Variable./', $html);
        $this->assertDoesNotMatchRegularExpression('#fonts\.bunny\.net|fonts\.googleapis|fonts\.gstatic|cdn\.jsdelivr|unpkg\.com|cdnjs#i', $html);
    }

    public function test_el_hover_claro_usa_el_tono_700_y_el_oscuro_el_azul_bebe_con_texto_marino(): void
    {
        $css = $this->tema();

        $this->assertStringContainsString(".fi-btn.fi-color-primary:not(.fi-outlined) {\n    --hover-bg: var(--color-700);", $css);
        $this->assertStringContainsString(".dark .fi-btn.fi-color-primary:not(.fi-outlined) {\n    --dark-bg: var(--color-400);\n    --dark-text: var(--color-950);\n    --dark-hover-bg: var(--color-300);", $css);
    }

    public function test_el_foco_sustituye_al_anterior_y_no_se_suma(): void
    {
        $css = $this->tema();

        $this->assertSame(1, substr_count($css, 'outline: var(--mb-foco-grosor) solid var(--mb-foco);'));
        $this->assertSame(1, preg_match_all('/^\s*outline:/m', $css), 'Debe haber una sola declaración de contorno de foco.');
        $this->assertStringNotContainsString('outline: 2px solid var(--mo-foco)', $css);
        $this->assertStringNotContainsString('var(--mo-foco)', $css);
        $this->assertStringContainsString('.fi-body :is(.fi-btn, .fi-icon-btn):focus-visible {', $css);
        $this->assertStringContainsString('--tw-ring-shadow: 0 0 #0000;', $css);
    }

    public function test_los_objetivos_tactiles_siguen_en_44_px(): void
    {
        $css = $this->tema();

        $this->assertSame(1, substr_count($css, "    .fi-btn,\n    .fi-icon-btn {\n        min-height: var(--mb-objetivo);"));
        $this->assertStringContainsString('min-width: var(--mb-objetivo);', $css);
        $this->assertSame('2.75rem', $this->bloque($css, ':root')['mb-objetivo']);
    }

    public function test_los_titulos_usan_space_grotesk_y_el_resto_figtree(): void
    {
        $css = $this->tema();

        $this->assertStringContainsString("@import '@fontsource-variable/space-grotesk';", $css);
        $this->assertStringContainsString("@import '@fontsource-variable/figtree';", $css);
        $this->assertStringContainsString('--font-family: var(--mb-fuente-texto);', $css);
        $this->assertStringContainsString("    .fi-header-heading,\n    .fi-section-header-heading,\n    .fi-modal-heading {\n        font-family: var(--mb-fuente-titulo);", $css);
        $this->assertStringContainsString("'Space Grotesk Variable'", $this->bloque($css, ':root')['mb-fuente-titulo']);
        $this->assertStringContainsString("'Figtree Variable'", $this->bloque($css, ':root')['mb-fuente-texto']);

        $paquetes = json_decode((string) file_get_contents(base_path('package.json')), true)['dependencies'];
        $this->assertArrayHasKey('@fontsource-variable/space-grotesk', $paquetes);
        $this->assertArrayHasKey('@fontsource-variable/figtree', $paquetes);
    }

    public function test_el_tema_no_tiene_urls_externas_y_la_csp_del_admin_no_cambio(): void
    {
        $css = $this->tema();

        $this->assertDoesNotMatchRegularExpression('#https?://#', $css);
        $this->assertStringNotContainsString('@import url(', $css);

        $csp = (new PoliticaDeContenido)->admin('nonce-fijo');
        $this->assertStringContainsString("font-src 'self' data: https://fonts.bunny.net", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net", $csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-nonce-fijo' 'unsafe-eval'", $csp);
        preg_match_all('#https?://[a-z0-9.-]+#i', $csp, $hosts);
        $this->assertSame(['https://fonts.bunny.net'], array_values(array_unique($hosts[0])));
    }

    public function test_el_menu_publico_y_el_panel_de_servicio_no_conocen_los_tokens_del_admin(): void
    {
        foreach (['public/css/menuonl.css', 'resources/css/panel.css', 'resources/css/app.css'] as $archivo) {
            $contenido = (string) file_get_contents(base_path($archivo));

            $this->assertStringNotContainsString('--mb-', $contenido, "{$archivo} no debe usar los tokens del admin.");
            $this->assertStringNotContainsString('fontsource', $contenido, "{$archivo} no debe cargar las fuentes del admin.");
        }
    }

    public function test_el_css_compilado_del_tema_no_carga_nada_externo_y_las_fuentes_salen_de_build(): void
    {
        $manifiesto = public_path('build/manifest.json');

        if (! is_file($manifiesto)) {
            $this->markTestSkipped('No hay build de Vite: ejecuta npm run build para comprobar el CSS compilado.');
        }

        $entrada = json_decode((string) file_get_contents($manifiesto), true)['resources/css/filament/admin/theme.css'] ?? null;
        $this->assertNotNull($entrada, 'El manifiesto no incluye el tema del admin.');

        $css = (string) file_get_contents(public_path('build/'.$entrada['file']));

        preg_match_all('/url\(\s*["\']?([^)"\']+)/', $css, $urls);
        foreach (array_unique($urls[1]) as $url) {
            $this->assertTrue(str_starts_with($url, 'data:') || str_starts_with($url, '/build/assets/'), 'URL fuera de /build/assets: '.substr($url, 0, 80));
        }

        preg_match_all('#url\((/build/assets/[^)]+\.woff2)\)#', $css, $fuentes);
        $this->assertNotEmpty($fuentes[1]);
        $this->assertNotEmpty(array_filter($fuentes[1], fn (string $f): bool => str_contains($f, 'figtree')));
        $this->assertNotEmpty(array_filter($fuentes[1], fn (string $f): bool => str_contains($f, 'space-grotesk')));

        foreach (array_unique($fuentes[1]) as $fuente) {
            $this->assertFileExists(public_path(ltrim($fuente, '/')));
        }

        $this->assertDoesNotMatchRegularExpression('#@import\s+(url\()?["\']?https?:#', $css);
    }

    private function barraInferior(string $css): string
    {
        $inicio = strpos($css, '/* ---- Barra inferior del panel en móvil');
        $this->assertNotFalse($inicio, 'No existe el bloque de la barra inferior.');

        return substr($css, $inicio, strpos($css, '/*
 * Acciones de formulario pegajosas') - $inicio);
    }

    public function test_el_menu_lateral_y_la_barra_inferior_usan_los_tokens_mb_y_conservan_la_clase(): void
    {
        $css = $this->tema();
        $barra = $this->barraInferior($css);

        $this->assertStringContainsString('.mo-barra-inferior {', $barra);
        $this->assertStringNotContainsString('var(--mo-', $barra, 'La barra inferior ya no usa los tokens --mo-*.');
        $this->assertStringNotContainsString('var(--color-primary', $barra);
        $this->assertStringContainsString('min-height: 3.5rem;', $barra);
        $this->assertStringContainsString('padding-bottom: env(safe-area-inset-bottom);', $barra);
        $this->assertStringContainsString('.fi-sidebar-item.fi-active > .fi-sidebar-item-btn {', $css);
        $this->assertStringContainsString('min-height: var(--mb-objetivo);', $css);
    }

    public function test_la_barra_no_tapa_el_contenido_ni_las_acciones_pegajosas(): void
    {
        $css = $this->tema();

        $this->assertSame('calc(3.5rem + env(safe-area-inset-bottom))', $this->bloque($css, ':root')['mb-barra-inferior']);
        $this->assertStringContainsString('html:has(.mo-barra-inferior) {
            scroll-padding-bottom: calc(var(--mb-barra-inferior) + 1rem);', $css);
        $this->assertStringContainsString('            padding-bottom: calc(var(--mb-barra-inferior) + 1rem);', $css);
        $this->assertStringContainsString('.fi-body:has(.mo-barra-inferior) .fi-sc-actions.fi-sticky .fi-ac {
        bottom: calc(var(--mb-barra-inferior) + 0.5rem);', $css);

        // Las acciones pegajosas van fuera de @layer: la utilidad de Filament vive en @layer utilities y le ganaría.
        $this->assertGreaterThan(strpos($css, '/*
 * Acciones de formulario pegajosas'), strpos($css, '.fi-sc-actions.fi-sticky'));
        $this->assertMatchesRegularExpression('/@media \(max-width: 63\.99rem\) \{\s*\.fi-body:has\(\.mo-barra-inferior\) \.fi-sc-actions\.fi-sticky/', $css);
    }

    public function test_el_movimiento_se_apaga_con_prefers_reduced_motion(): void
    {
        $barra = $this->barraInferior($this->tema());

        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce) {', $barra);
        $this->assertMatchesRegularExpression('/prefers-reduced-motion: reduce\) \{[^}]*\.fi-sidebar,[^}]*\.mo-barra-inferior \*[^}]*transition: none !important;[^}]*animation: none !important;/s', $barra);
    }

    public function test_el_menu_y_la_barra_no_agregan_otro_contorno_ni_anillo_de_foco(): void
    {
        $css = $this->tema();
        $barra = $this->barraInferior($css);

        $this->assertDoesNotMatchRegularExpression('/^\s*outline/m', $barra);
        $this->assertStringNotContainsString('--tw-ring', $barra);
        $this->assertStringNotContainsString(':focus', $barra);
        $this->assertSame(1, preg_match_all('/^\s*outline:/m', $css));
    }

    public function test_el_viewport_de_filament_no_se_toca(): void
    {
        $this->assertStringNotContainsString('viewport-fit', (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php')));
        $this->assertStringNotContainsString('viewport-fit', $this->tema());
    }
}
