<?php

namespace Tests\Feature;

use App\Models\Restaurante;
use App\Models\User;
use App\Services\PoliticaDeContenido;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La puerta del panel viejo: /panel* manda a /admin con 302 y /api/v1/panel* responde 410, sin sesión, cookies, CSRF ni límites.
 */
class PuertaDelPanelTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE = 'Esta versión del panel ya no existe. Recarga la página.';

    /**
     * @return array<string, array{0: string}>
     */
    public static function metodos(): array
    {
        return [
            'GET' => ['GET'],
            'POST' => ['POST'],
            'PUT' => ['PUT'],
            'PATCH' => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rutasDelPanel(): array
    {
        return [
            'raíz' => ['/panel'],
            'con diagonal final' => ['/panel/'],
            'sección' => ['/panel/reservas'],
            'restaurante y sección' => ['/panel/la-esquina/menu'],
            'anidada' => ['/panel/a/b/c/d'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rutasDeLaApi(): array
    {
        return [
            'raíz' => ['/api/v1/panel'],
            'resumen' => ['/api/v1/panel/la-esquina/resumen'],
            'reservas' => ['/api/v1/panel/la-esquina/reservas/12'],
            'ajustes' => ['/api/v1/panel/la-esquina/ajustes'],
            'imagenes' => ['/api/v1/panel/la-esquina/imagenes/portada'],
        ];
    }

    #[DataProvider('rutasDelPanel')]
    public function test_el_panel_viejo_manda_a_admin_con_302(string $ruta): void
    {
        $this->get($ruta)->assertStatus(302)->assertRedirect('/admin');
    }

    #[DataProvider('metodos')]
    public function test_el_302_vale_para_cualquier_metodo_sin_419(string $metodo): void
    {
        $respuesta = $this->call($metodo, '/panel/reservas');

        $respuesta->assertStatus(302)->assertRedirect('/admin');
        $this->assertSame([], $respuesta->headers->getCookies());
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
    }

    /**
     * La rama is('panel') de CabecerasDeSeguridad sigue viva hasta el PR que borra el código muerto: en la ruta exacta /panel la
     * redirección aún lleva esa política (sin efecto en un 302 sin documento); en las demás rutas del panel no lleva ninguna.
     */
    public function test_las_rutas_internas_del_panel_viejo_no_llevan_la_politica_del_panel(): void
    {
        $respuesta = $this->get('/panel/reservas');

        $this->assertNotSame(app(PoliticaDeContenido::class)->panel(), $respuesta->headers->get('Content-Security-Policy'));
        $this->assertNull($respuesta->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_con_sesion_iniciada_tambien_va_a_admin_sin_pasar_por_el_inicio_de_sesion(): void
    {
        $usuario = User::factory()->create();
        Restaurante::factory()->create()->usuarios()->attach($usuario, ['rol' => 'propietario']);

        $this->actingAs($usuario)->get('/panel')->assertRedirect('/admin');
        $this->actingAs($usuario)->post('/panel/la-esquina/menu')->assertRedirect('/admin');
    }

    public function test_un_invitado_llega_a_admin_y_es_admin_quien_pide_la_sesion(): void
    {
        $this->get('/panel')->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    #[DataProvider('rutasDeLaApi')]
    #[DataProvider('metodos')]
    public function test_la_api_vieja_responde_410_con_el_mensaje(string $rutaOMetodo): void
    {
        $esMetodo = in_array($rutaOMetodo, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true);
        $respuesta = $esMetodo
            ? $this->json($rutaOMetodo, '/api/v1/panel/la-esquina/reservas')
            : $this->getJson($rutaOMetodo);

        $respuesta->assertStatus(410)->assertExactJson(['message' => self::MENSAJE]);
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertSame([], $respuesta->headers->getCookies());
    }

    public function test_la_api_vieja_responde_410_con_y_sin_sesion_sin_401_ni_403_ni_404(): void
    {
        $usuario = User::factory()->create();
        $restaurante = Restaurante::factory()->create(['slug' => 'la-esquina']);
        $restaurante->usuarios()->attach($usuario, ['rol' => 'propietario']);

        foreach (['get', 'post', 'put', 'delete'] as $metodo) {
            $this->{$metodo.'Json'}('/api/v1/panel/la-esquina/ajustes')->assertStatus(410);
            $this->actingAs($usuario)->{$metodo.'Json'}('/api/v1/panel/la-esquina/ajustes')->assertStatus(410);
            $this->actingAs($usuario)->{$metodo.'Json'}('/api/v1/panel/otro-restaurante/ajustes')->assertStatus(410);
        }
    }

    public function test_la_api_vieja_no_se_limita_por_frecuencia(): void
    {
        for ($i = 0; $i < 80; $i++) {
            $this->getJson('/api/v1/panel/la-esquina/reservas')->assertStatus(410);
        }
    }

    /**
     * No depende del php.ini: se le entrega al manejador la excepción que lanzaría ValidatePostSize.
     */
    public function test_el_manejador_responde_410_a_un_cuerpo_demasiado_grande_en_la_api_vieja(): void
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $metodo) {
            foreach (['/api/v1/panel', '/api/v1/panel/la-esquina/imagenes/portada'] as $ruta) {
                $peticion = Request::create($ruta, $metodo);
                $respuesta = app(ExceptionHandler::class)->render($peticion, new PostTooLargeException);

                $this->assertSame(410, $respuesta->getStatusCode(), "{$metodo} {$ruta}");
                $this->assertSame(['message' => self::MENSAJE], json_decode((string) $respuesta->getContent(), true));
            }
        }
    }

    public function test_el_manejador_no_cambia_el_413_fuera_de_la_api_vieja(): void
    {
        $respuesta = app(ExceptionHandler::class)->render(Request::create('/admin/algo', 'POST'), new PostTooLargeException);

        $this->assertSame(413, $respuesta->getStatusCode());

        $subida = Request::create('/livewire/upload-file', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertSame(422, app(ExceptionHandler::class)->render($subida, new PostTooLargeException)->getStatusCode());
    }

    public function test_un_cuerpo_demasiado_grande_recibe_410_por_http_con_cualquier_php_ini(): void
    {
        $this->call('POST', '/api/v1/panel/la-esquina/imagenes/portada', [], [], [], [
            'CONTENT_LENGTH' => 4 * 1024 * 1024 * 1024,
            'HTTP_ACCEPT' => 'application/json',
        ])->assertStatus(410)->assertExactJson(['message' => self::MENSAJE]);
    }

    public function test_las_rutas_publicas_vecinas_siguen_funcionando(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]))->assertOk();
        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk();
    }
}
