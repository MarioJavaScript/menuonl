<?php

namespace Tests\Feature;

use App\Http\Middleware\CabecerasDeSeguridad;
use App\Models\Restaurante;
use App\Services\PoliticaDeContenido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Cabeceras de seguridad por grupo de rutas (docs/despliegue.md, 6.1): admin (Filament + Livewire), invitaciones,
 * páginas públicas y API. La CSP del admin lleva nonce y sus scripts en línea lo usan; lo demás no tiene scripts en línea.
 */
class CabecerasDeSeguridadTest extends TestCase
{
    private const FUNCIONES_RECONOCIDAS = ['accelerometer', 'camera', 'geolocation', 'gyroscope', 'magnetometer', 'microphone', 'payment', 'usb'];

    use ArmaEscenarioDePanel, RefreshDatabase;

    private const string SLUG = 'cabeceras-fijo';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    /**
     * Pide una ruta de cada grupo y devuelve la respuesta.
     */
    private function pedir(string $grupo, string $esquema = 'http'): TestResponse
    {
        $base = $esquema.'://localhost';

        return match ($grupo) {
            'admin' => $this->get($base.'/admin/login'),
            'invitaciones' => $this->get($base.'/invitaciones/token-que-no-existe'),
            'menu' => $this->get($base.'/'.Restaurante::factory()->create(['slug' => self::SLUG])->slug.'/menu'),
            'portada' => $this->get($base.'/'),
            'no-encontrada' => $this->get($base.'/ruta/que/no/existe/para/nada'),
            'api' => $this->getJson($base.'/api/v1/menu/'.Restaurante::factory()->create(['slug' => self::SLUG])->slug),
            'salud' => $this->get($base.'/up'),
        };
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function gruposDeRutas(): array
    {
        return [
            'admin (Filament)' => ['admin', true],
            'invitaciones' => ['invitaciones', true],
            'menú público' => ['menu', true],
            'portada' => ['portada', true],
            'ruta inexistente' => ['no-encontrada', true],
            'API JSON' => ['api', false],
            'salud' => ['salud', true],
        ];
    }

    #[DataProvider('gruposDeRutas')]
    public function test_cada_grupo_de_rutas_manda_las_cabeceras_basicas(string $grupo, bool $esHtml): void
    {
        $respuesta = $this->pedir($grupo);

        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
        $respuesta->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $respuesta->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $respuesta->assertHeader('Permissions-Policy', CabecerasDeSeguridad::PERMISSIONS_POLICY);
        $this->assertStringContainsString('camera=()', (string) $respuesta->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('geolocation=()', (string) $respuesta->headers->get('Permissions-Policy'));
        $this->assertSame(
            [],
            array_diff($this->funcionesDePermissionsPolicy((string) $respuesta->headers->get('Permissions-Policy')), self::FUNCIONES_RECONOCIDAS),
            'Permissions-Policy solo debe nombrar funciones que reconocen los navegadores actuales (si no, la consola avisa).',
        );
        $this->assertSame(
            $esHtml && str_contains(strtolower((string) $respuesta->headers->get('Content-Type')), 'text/html'),
            $respuesta->headers->has('Content-Security-Policy'),
            'La CSP solo se manda en respuestas HTML.',
        );
    }

    /**
     * @return list<string>
     */
    private function funcionesDePermissionsPolicy(string $valor): array
    {
        return array_map(fn (string $parte): string => trim(explode('=', $parte)[0]), explode(',', $valor));
    }

    public function test_permissions_policy_no_nombra_funciones_de_publicidad_ni_usadas_por_la_aplicacion(): void
    {
        $funciones = $this->funcionesDePermissionsPolicy(CabecerasDeSeguridad::PERMISSIONS_POLICY);

        $this->assertSame([], array_intersect($funciones, ['run-ad-auction', 'join-ad-interest-group', 'browsing-topics', 'interest-cohort']));
        $this->assertSame([], array_diff($funciones, self::FUNCIONES_RECONOCIDAS));
        $this->assertStringNotContainsString('(self)', CabecerasDeSeguridad::PERMISSIONS_POLICY, 'Ninguna función se concede: la aplicación no usa ninguna.');
    }

    /**
     * Valor de una directiva de la CSP (por ejemplo script-src).
     */
    private function directiva(string $csp, string $nombre): string
    {
        foreach (explode(';', $csp) as $parte) {
            $parte = trim($parte);

            if (str_starts_with($parte, $nombre.' ')) {
                return substr($parte, strlen($nombre) + 1);
            }
        }

        return '';
    }

    public function test_el_admin_usa_nonce_sin_unsafe_inline_en_scripts_y_sus_scripts_en_linea_lo_llevan(): void
    {
        $respuesta = $this->pedir('admin')->assertOk();
        $csp = (string) $respuesta->headers->get('Content-Security-Policy');
        $scripts = $this->directiva($csp, 'script-src');

        $this->assertSame(1, preg_match("/'nonce-([A-Za-z0-9]{40})'/", $scripts, $coincidencia), $csp);
        $nonce = $coincidencia[1];
        $this->assertStringNotContainsString("'unsafe-inline'", $scripts);
        $this->assertStringContainsString("'self'", $scripts);
        $this->assertStringContainsString("'unsafe-eval'", $scripts, 'Alpine estándar evalúa expresiones; ver docs/despliegue.md.');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);

        $html = $respuesta->getContent();
        preg_match_all('#<script\b([^>]*)>#i', $html, $etiquetas);
        $this->assertNotEmpty($etiquetas[1], 'El login de Filament carga scripts.');

        $enLinea = 0;
        foreach ($etiquetas[1] as $atributos) {
            if (! str_contains($atributos, 'src=')) {
                $enLinea++;
                $this->assertStringContainsString('nonce="'.$nonce.'"', $atributos, 'Un script en línea sin nonce lo bloquearía la CSP: <script '.$atributos.'>');

                continue;
            }

            preg_match('/src="([^"]+)"/', $atributos, $src);
            $esPropio = str_starts_with($src[1], '/') || str_starts_with($src[1], 'http://localhost');
            $this->assertTrue($esPropio || str_contains($atributos, 'nonce="'.$nonce.'"'), 'Script externo no permitido: '.$src[1]);
        }
        $this->assertGreaterThan(0, $enLinea, 'Filament/Livewire emiten scripts en línea: sin nonce la CSP rompería el panel.');
        $this->assertSame(0, preg_match('/\s(onclick|onload|onerror|onsubmit|onchange)=/i', $html), 'Sin manejadores en línea.');
    }

    public function test_cada_peticion_al_admin_recibe_un_nonce_distinto(): void
    {
        $primero = (string) $this->pedir('admin')->headers->get('Content-Security-Policy');
        $this->refreshApplication();
        $segundo = (string) $this->pedir('admin')->headers->get('Content-Security-Policy');

        $this->assertNotSame($primero, $segundo);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function gruposSinScriptsEnLinea(): array
    {
        return [
            'invitaciones' => ['invitaciones'],
            'menú público' => ['menu'],
            'portada' => ['portada'],
        ];
    }

    #[DataProvider('gruposSinScriptsEnLinea')]
    public function test_fuera_del_admin_los_scripts_son_solo_del_propio_sitio_y_no_hay_scripts_en_linea(string $grupo): void
    {
        $respuesta = $this->pedir($grupo);
        $csp = (string) $respuesta->headers->get('Content-Security-Policy');
        $scripts = $this->directiva($csp, 'script-src');

        $this->assertSame("'self'", $scripts, $csp);
        $this->assertStringNotContainsString('nonce-', $csp, 'Con nonce el HTML cambiaría en cada petición y no se podría cachear.');

        preg_match_all('#<script\b([^>]*)>#i', $respuesta->getContent(), $etiquetas);
        foreach ($etiquetas[1] as $atributos) {
            $this->assertStringContainsString('src=', $atributos, 'No debe haber scripts en línea: <script '.$atributos.'>');
        }
    }

    public function test_las_invitaciones_tienen_una_politica_propia_y_estricta(): void
    {
        $csp = (string) $this->pedir('invitaciones')->headers->get('Content-Security-Policy');

        $this->assertSame("'self'", $this->directiva($csp, 'default-src'));
        $this->assertSame("'self'", $this->directiva($csp, 'connect-src'));
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_las_paginas_publicas_conservan_su_politica_y_el_script_de_apariencia_es_un_archivo(): void
    {
        $respuesta = $this->pedir('menu')->assertOk();

        $this->assertSame(PoliticaDeContenido::MENU, $respuesta->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('js/apariencia.js', $respuesta->getContent());
        $this->assertFileExists(public_path('js/apariencia.js'));
        $this->assertStringContainsString('filament:theme:mode', (string) file_get_contents(public_path('js/apariencia.js')));
    }

    public function test_las_paginas_publicas_siguen_siendo_cacheables_e_iguales_en_cada_peticion(): void
    {
        $restaurante = Restaurante::factory()->create(['slug' => self::SLUG]);

        // Livewire guarda en una variable estática si ya pintó un componente; una petición previa la deja en su estado de reposo.
        $this->get('/'.$restaurante->slug.'/menu')->assertOk();

        $primera = $this->get('/'.$restaurante->slug.'/menu')->assertOk();
        $segunda = $this->get('/'.$restaurante->slug.'/menu')->assertOk();

        $this->assertSame($primera->getContent(), $segunda->getContent());
        $this->assertSame([], $primera->headers->getCookies(), 'Sin cookies: Cloudflare puede cachearla.');
        $this->assertStringContainsString('public', (string) $primera->headers->get('Cache-Control'));
        $this->assertSame($primera->headers->get('Content-Security-Policy'), $segunda->headers->get('Content-Security-Policy'));
    }

    public function test_la_cabecera_que_pone_el_controlador_no_se_pisa(): void
    {
        $restaurante = Restaurante::factory()->create(['slug' => self::SLUG]);

        $this->get('/'.$restaurante->slug.'/menu')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $csp = (string) $this->get('/slug-que-no-existe-para-nada/menu')->assertNotFound()->headers->get('Content-Security-Policy');

        $this->assertSame(PoliticaDeContenido::MENU, $csp);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int, 3: ?string}>
     */
    public static function casosDeHsts(): array
    {
        return [
            'producción con https' => ['production', 'https', 604800, 'max-age=604800'],
            'producción con https y otra duración' => ['production', 'https', 86400, 'max-age=86400'],
            'producción con http' => ['production', 'http', 604800, null],
            'local con https' => ['local', 'https', 604800, null],
            'pruebas con https' => ['testing', 'https', 604800, null],
            'producción con https y duración cero' => ['production', 'https', 0, null],
        ];
    }

    #[DataProvider('casosDeHsts')]
    public function test_hsts_solo_en_produccion_con_https(string $entorno, string $esquema, int $segundos, ?string $esperado): void
    {
        $this->app->instance('env', $entorno);
        config(['menuonl.seguridad.hsts_segundos' => $segundos]);

        $respuesta = $this->pedir('menu', $esquema);

        if ($esperado === null) {
            $respuesta->assertHeaderMissing('Strict-Transport-Security');

            return;
        }

        $respuesta->assertHeader('Strict-Transport-Security', $esperado);
        $this->assertStringNotContainsString('includeSubDomains', (string) $respuesta->headers->get('Strict-Transport-Security'));
        $this->assertStringNotContainsString('preload', (string) $respuesta->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_por_defecto_dura_siete_dias(): void
    {
        $this->assertSame(604800, config('menuonl.seguridad.hsts_segundos'));
    }

    public function test_hsts_tambien_llega_en_el_admin_y_la_api_por_https_en_produccion(): void
    {
        $this->app->instance('env', 'production');

        foreach (['admin', 'api'] as $grupo) {
            $this->pedir($grupo, 'https')->assertHeader('Strict-Transport-Security', 'max-age=604800');
        }
    }

    public function test_con_el_servidor_de_vite_en_desarrollo_la_politica_lo_admite_y_en_produccion_no(): void
    {
        $caliente = tempnam(sys_get_temp_dir(), 'hot');
        file_put_contents($caliente, 'http://localhost:5173');
        Vite::useHotFile($caliente);

        try {
            $politicas = app(PoliticaDeContenido::class);
            $desarrollo = $politicas->paraEntorno($politicas->panel());

            $this->assertStringContainsString("script-src 'self' http://localhost:5173", $desarrollo);
            $this->assertStringContainsString('ws://localhost:5173', $this->directiva($desarrollo, 'connect-src'));

            $this->app->instance('env', 'production');

            $this->assertSame($politicas->panel(), $politicas->paraEntorno($politicas->panel()));
        } finally {
            @unlink($caliente);
        }
    }
}
