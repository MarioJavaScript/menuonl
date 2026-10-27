<?php

namespace Tests\Feature;

use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Services\EnlaceGoogleMaps;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Seguir un enlace corto de Google Maps es la única petición saliente que dispara el dueño: solo https y dominios fijos
 * en cada salto, sin redirecciones automáticas, tiempos y tamaño acotados, IP pública y con límite de peticiones.
 */
class EnlaceGoogleMapsSeguridadTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const CORTO = 'https://maps.app.goo.gl/AbCdEf123';

    private const LARGO = 'https://www.google.com/maps/place/Lupe/@19.432608,-99.133209,17z';

    protected function setUp(): void
    {
        parent::setUp();

        EnlaceGoogleMaps::usarResolutor(fn (string $host): array => ['142.250.80.46']);
    }

    protected function tearDown(): void
    {
        EnlaceGoogleMaps::usarResolutor(null);

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function enlacesIniciales(): array
    {
        return [
            'largo valido' => [self::LARGO, true],
            'corto valido' => [self::CORTO, true],
            'maps.google.com' => ['https://maps.google.com/?q=19.4,-99.1', true],
            'http' => ['http://www.google.com/maps/@1,1,1z', false],
            'sin esquema' => ['www.google.com/maps/@1,1,1z', false],
            'esquema distinto' => ['ftp://www.google.com/maps/@1,1,1z', false],
            'javascript' => ['javascript:alert(1)', false],
            'otro dominio' => ['https://evil.example/maps/@1,1,1z', false],
            'subdominio no listado' => ['https://evil.google.com/maps/@1,1,1z', false],
            'sufijo engañoso' => ['https://www.google.com.evil.example/maps/@1,1,1z', false],
            'prefijo engañoso' => ['https://evilgoogle.com/maps/@1,1,1z', false],
            'arroba en la direccion' => ['https://www.google.com@evil.example/maps/@1,1,1z', false],
            'ip numerica' => ['https://169.254.169.254/maps/@1,1,1z', false],
            'localhost' => ['https://localhost/maps/@1,1,1z', false],
            'ip interna' => ['https://10.0.0.5/maps/@1,1,1z', false],
            'goo.gl fuera de maps' => ['https://goo.gl/otra-cosa', false],
            'corto con puerto' => ['https://maps.app.goo.gl:8080/AbC', false],
            'google fuera de /maps' => ['https://www.google.com/url?q=https://evil.example', false],
        ];
    }

    #[DataProvider('enlacesIniciales')]
    public function test_solo_https_y_dominios_fijos_en_el_enlace_inicial(string $url, bool $valido): void
    {
        $this->assertSame($valido, EnlaceGoogleMaps::esValido($url));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function destinosHostiles(): array
    {
        return [
            'metadatos de la nube' => ['http://169.254.169.254/latest/meta-data/'],
            'metadatos por https' => ['https://169.254.169.254/maps/@1.5,2.5,1z'],
            'localhost' => ['http://localhost/maps/@1.5,2.5,1z'],
            'localhost por https' => ['https://localhost:8000/maps/@1.5,2.5,1z'],
            'loopback' => ['https://127.0.0.1/maps/@1.5,2.5,1z'],
            'ip interna' => ['https://192.168.1.10/maps/@1.5,2.5,1z'],
            'ip interna 10' => ['https://10.0.0.5/maps/@1.5,2.5,1z'],
            'ipv6 loopback' => ['https://[::1]/maps/@1.5,2.5,1z'],
            'otro dominio' => ['https://evil.example/maps/@1.5,2.5,1z'],
            'http aunque sea google' => ['http://www.google.com/maps/@1.5,2.5,1z'],
            'esquema ftp' => ['ftp://www.google.com/maps/@1.5,2.5,1z'],
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,hola'],
            'file' => ['file:///etc/passwd'],
            'ruta relativa' => ['/maps/@1.5,2.5,1z'],
            'vacio' => [''],
        ];
    }

    #[DataProvider('destinosHostiles')]
    public function test_cada_salto_se_valida_y_un_destino_hostil_no_se_visita(string $destino): void
    {
        Http::fake([self::CORTO => Http::response('', 302, ['Location' => $destino])]);

        $this->assertNull(EnlaceGoogleMaps::coordenadas(self::CORTO));

        Http::assertSentCount(1);
        Http::assertSent(fn ($peticion): bool => $peticion->url() === self::CORTO);
    }

    public function test_un_salto_intermedio_hostil_corta_la_cadena(): void
    {
        Http::fake([
            self::CORTO => Http::response('', 302, ['Location' => 'https://maps.app.goo.gl/segundo']),
            'maps.app.goo.gl/segundo' => Http::response('', 302, ['Location' => 'http://169.254.169.254/']),
        ]);

        $this->assertNull(EnlaceGoogleMaps::coordenadas(self::CORTO));
        Http::assertSentCount(2);
    }

    public function test_una_cadena_valida_de_dos_saltos_si_llega_a_las_coordenadas(): void
    {
        Http::fake([
            self::CORTO => Http::response('', 302, ['Location' => 'https://maps.app.goo.gl/segundo']),
            'maps.app.goo.gl/segundo' => Http::response('', 302, ['Location' => self::LARGO]),
        ]);

        $this->assertSame(['latitud' => 19.432608, 'longitud' => -99.133209], EnlaceGoogleMaps::coordenadas(self::CORTO));
    }

    public function test_un_bucle_de_redirecciones_se_corta_con_pocas_peticiones(): void
    {
        Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => self::CORTO])]);

        $this->assertNull(EnlaceGoogleMaps::coordenadas(self::CORTO));

        Http::assertSentCount(3);
    }

    public function test_una_cadena_larga_no_se_sigue_mas_alla_del_maximo(): void
    {
        $secuencia = Http::sequence();
        foreach (range(1, 10) as $n) {
            $secuencia->push('', 302, ['Location' => "https://maps.app.goo.gl/salto{$n}"]);
        }
        Http::fake(['maps.app.goo.gl/*' => $secuencia]);

        $this->assertNull(EnlaceGoogleMaps::coordenadas(self::CORTO));

        Http::assertSentCount(3);
    }

    public function test_el_cliente_no_sigue_redirecciones_y_tiene_tiempos_y_tamano_acotados(): void
    {
        $opciones = EnlaceGoogleMaps::cliente('maps.app.goo.gl', '142.250.80.46')->getOptions();

        $this->assertFalse($opciones['allow_redirects']);
        $this->assertSame(4, $opciones['timeout']);
        $this->assertSame(2, $opciones['connect_timeout']);
        $this->assertTrue($opciones['stream']);
        $this->assertSame(['maps.app.goo.gl:443:142.250.80.46'], $opciones['curl'][CURLOPT_RESOLVE]);
        $this->assertIsCallable($opciones['on_headers']);
    }

    public function test_una_respuesta_enorme_se_descarta(): void
    {
        Http::fake([self::CORTO => Http::response(str_repeat('x', 100), 302, ['Location' => self::LARGO, 'Content-Length' => '5000000'])]);

        $this->assertNull(EnlaceGoogleMaps::coordenadas(self::CORTO));
    }

    public function test_la_cabecera_de_tamano_se_comprueba_antes_de_descargar(): void
    {
        $opciones = EnlaceGoogleMaps::cliente('maps.app.goo.gl', '142.250.80.46')->getOptions();
        $respuesta = new Response(302, ['Content-Length' => '5000000']);

        $this->expectException(\RuntimeException::class);
        $opciones['on_headers']($respuesta);
    }

    public function test_una_espera_infinita_o_una_conexion_caida_termina_sin_ubicacion(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->assertNull(EnlaceGoogleMaps::coordenadas(self::CORTO));
    }

    /**
     * @return array<string, array{0: list<string>}>
     */
    public static function resolucionesNoPublicas(): array
    {
        return [
            'privada 10' => [['10.0.0.5']],
            'privada 192' => [['192.168.1.10']],
            'privada 172' => [['172.16.0.9']],
            'loopback' => [['127.0.0.1']],
            'enlace local y metadatos' => [['169.254.169.254']],
            'cero' => [['0.0.0.0']],
            'ipv6 loopback' => [['::1']],
            'ipv6 enlace local' => [['fe80::1']],
            'ipv6 privada' => [['fd00::1']],
            'ipv4 dentro de ipv6' => [['::ffff:127.0.0.1']],
            'una publica y una privada' => [['142.250.80.46', '10.0.0.5']],
            'no resuelve' => [[]],
        ];
    }

    /**
     * @param  list<string>  $ips
     */
    #[DataProvider('resolucionesNoPublicas')]
    public function test_si_el_dominio_resuelve_a_una_ip_no_publica_no_se_hace_la_peticion(array $ips): void
    {
        EnlaceGoogleMaps::usarResolutor(fn (string $host): array => $ips);
        Http::fake();

        $this->assertNull(EnlaceGoogleMaps::coordenadas(self::CORTO));
        Http::assertNothingSent();
    }

    public function test_cada_salto_resuelve_su_propio_dominio(): void
    {
        $resueltos = [];
        EnlaceGoogleMaps::usarResolutor(function (string $host) use (&$resueltos): array {
            $resueltos[] = $host;

            return ['142.250.80.46'];
        });
        Http::fake([
            self::CORTO => Http::response('', 302, ['Location' => 'https://goo.gl/maps/otro']),
            'goo.gl/maps/otro' => Http::response('', 302, ['Location' => self::LARGO]),
        ]);

        $this->assertNotNull(EnlaceGoogleMaps::coordenadas(self::CORTO));
        $this->assertSame(['maps.app.goo.gl', 'goo.gl'], $resueltos);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function direcciones(): array
    {
        return [
            'publica' => ['142.250.80.46', true],
            'publica ipv6' => ['2607:f8b0:4005:80a::200e', true],
            'privada' => ['10.1.2.3', false],
            'loopback' => ['127.0.0.2', false],
            'enlace local' => ['169.254.1.1', false],
            'reservada' => ['240.0.0.1', false],
            'ipv6 unica local' => ['fc00::1', false],
            'no es una ip' => ['no-soy-ip', false],
        ];
    }

    #[DataProvider('direcciones')]
    public function test_clasificacion_de_direcciones_ip(string $ip, bool $publica): void
    {
        $this->assertSame($publica, EnlaceGoogleMaps::esIpPublica($ip));
    }

    public function test_enlace_largo_no_hace_ninguna_peticion(): void
    {
        Http::fake();

        $this->assertNotNull(EnlaceGoogleMaps::coordenadas(self::LARGO));
        Http::assertNothingSent();
    }

    public function test_la_pantalla_limita_los_enlaces_cortos_por_persona(): void
    {
        Http::fake([self::CORTO => Http::response('', 302, ['Location' => self::LARGO])]);
        $restaurante = $this->crearRestaurante('la-esquina');
        $usuario = $this->crearUsuario('propietario', $restaurante);
        RateLimiter::clear('enlace-mapa:'.$usuario->id);
        $this->entrarAlPanel($usuario, $restaurante);

        foreach (range(1, 10) as $intento) {
            Livewire::test(EditRestauranteProfile::class)
                ->fillForm(['enlace_mapa' => self::CORTO])
                ->call('save')
                ->assertHasNoErrors();
        }

        Livewire::test(EditRestauranteProfile::class)
            ->fillForm(['enlace_mapa' => self::CORTO])
            ->call('save')
            ->assertHasErrors(['data.enlace_mapa'])
            ->assertSee('Espera un minuto');

        Http::assertSentCount(10);
    }

    public function test_los_enlaces_largos_no_gastan_el_limite(): void
    {
        Http::fake();
        $restaurante = $this->crearRestaurante('la-esquina');
        $usuario = $this->crearUsuario('propietario', $restaurante);
        RateLimiter::clear('enlace-mapa:'.$usuario->id);
        $this->entrarAlPanel($usuario, $restaurante);

        foreach (range(1, 15) as $intento) {
            Livewire::test(EditRestauranteProfile::class)
                ->fillForm(['enlace_mapa' => self::LARGO])
                ->call('save')
                ->assertHasNoErrors();
        }
    }

    public function test_el_limite_es_por_persona(): void
    {
        Http::fake([self::CORTO => Http::response('', 302, ['Location' => self::LARGO])]);
        $restaurante = $this->crearRestaurante('la-esquina');
        $uno = $this->crearUsuario('propietario', $restaurante);
        $dos = $this->crearUsuario('propietario', $restaurante);
        RateLimiter::clear('enlace-mapa:'.$uno->id);
        RateLimiter::clear('enlace-mapa:'.$dos->id);
        $this->entrarAlPanel($uno, $restaurante);

        foreach (range(1, 11) as $intento) {
            Livewire::test(EditRestauranteProfile::class)->fillForm(['enlace_mapa' => self::CORTO])->call('save');
        }

        $this->entrarAlPanel($dos, $restaurante);
        Livewire::test(EditRestauranteProfile::class)
            ->fillForm(['enlace_mapa' => self::CORTO])
            ->call('save')
            ->assertHasNoErrors();
    }

    #[DataProvider('rolesSinAcceso')]
    public function test_solo_quien_puede_editar_el_restaurante_llega_a_seguir_enlaces(string $rol): void
    {
        Http::fake();
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        Livewire::test(EditRestauranteProfile::class)->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_un_usuario_sin_sesion_no_llega_a_la_pantalla(): void
    {
        $this->crearRestaurante('la-esquina');

        $this->get('/admin/la-esquina/profile')->assertRedirect();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesSinAcceso(): array
    {
        return ['editor' => ['editor'], 'gerente' => ['gerente'], 'recepcion' => ['recepcion']];
    }
}
