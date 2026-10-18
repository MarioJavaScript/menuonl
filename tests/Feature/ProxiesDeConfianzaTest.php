<?php

namespace Tests\Feature;

use App\Models\CodigoQr;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProxiesDeConfianzaTest extends TestCase
{
    use RefreshDatabase;

    private const IP_DEL_PROXY = '173.245.48.10';

    private const IP_FALSA = '6.6.6.6';

    private const IP_REAL_DEL_CLIENTE = '203.0.113.7';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_prueba-ip', fn (Request $request): array => [
            'ip' => $request->ip(),
            'login' => request()->ip(),
            'menu_publico' => RateLimiter::limiter('menu-publico')($request)->key,
            'invitaciones' => RateLimiter::limiter('invitaciones')($request)->key,
        ]);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function pedirDesde(string $remoto, string $forwardedFor): array
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $remoto])
            ->withHeaders(['X-Forwarded-For' => $forwardedFor])
            ->getJson('/_prueba-ip')
            ->assertOk()
            ->json();
    }

    public function test_sin_proxies_configurados_un_x_forwarded_for_falso_no_cambia_la_ip(): void
    {
        config(['menuonl.trusted_proxies' => []]);

        $respuesta = $this->pedirDesde(self::IP_DEL_PROXY, self::IP_FALSA);

        $this->assertSame(self::IP_DEL_PROXY, $respuesta['ip']);
    }

    public function test_con_un_remitente_no_confiable_un_x_forwarded_for_falso_no_cambia_la_ip(): void
    {
        config(['menuonl.trusted_proxies' => ['173.245.48.0/20']]);

        $respuesta = $this->pedirDesde('198.51.100.9', self::IP_FALSA);

        $this->assertSame('198.51.100.9', $respuesta['ip']);
    }

    public function test_con_un_proxy_confiable_se_usa_la_ip_del_cliente(): void
    {
        config(['menuonl.trusted_proxies' => ['173.245.48.0/20']]);

        $respuesta = $this->pedirDesde(self::IP_DEL_PROXY, self::IP_REAL_DEL_CLIENTE);

        $this->assertSame(self::IP_REAL_DEL_CLIENTE, $respuesta['ip']);
    }

    public function test_un_valor_falso_a_la_izquierda_de_la_cadena_no_gana_con_un_proxy_confiable(): void
    {
        config(['menuonl.trusted_proxies' => ['173.245.48.0/20']]);

        $respuesta = $this->pedirDesde(self::IP_DEL_PROXY, self::IP_FALSA.', '.self::IP_REAL_DEL_CLIENTE);

        $this->assertSame(self::IP_REAL_DEL_CLIENTE, $respuesta['ip']);
    }

    public function test_el_login_y_los_limiters_usan_la_ip_resuelta(): void
    {
        config(['menuonl.trusted_proxies' => ['173.245.48.0/20']]);

        $confiable = $this->pedirDesde(self::IP_DEL_PROXY, self::IP_REAL_DEL_CLIENTE);

        $this->assertSame(self::IP_REAL_DEL_CLIENTE, $confiable['login']);
        $this->assertSame(self::IP_REAL_DEL_CLIENTE, $confiable['menu_publico']);
        $this->assertSame(self::IP_REAL_DEL_CLIENTE, $confiable['invitaciones']);

        config(['menuonl.trusted_proxies' => []]);

        $noConfiable = $this->pedirDesde(self::IP_DEL_PROXY, self::IP_FALSA);

        $this->assertSame(self::IP_DEL_PROXY, $noConfiable['login']);
        $this->assertSame(self::IP_DEL_PROXY, $noConfiable['menu_publico']);
    }

    public function test_el_comodin_se_descarta_de_la_variable_de_entorno(): void
    {
        $leer = function (string $valor): array {
            putenv('TRUSTED_PROXIES='.$valor);

            return (require config_path('menuonl.php'))['trusted_proxies'];
        };

        try {
            $this->assertSame([], $leer(''));
            $this->assertSame([], $leer('*'));
            $this->assertSame(['10.0.0.0/8', '2400:cb00::/32'], $leer(' 10.0.0.0/8 , **, ,2400:cb00::/32 '));
        } finally {
            putenv('TRUSTED_PROXIES');
        }
    }

    public function test_evasion_de_rate_limiter_con_x_forwarded_for_falsificado_en_menu_publico_es_bloqueada(): void
    {
        config(['menuonl.trusted_proxies' => ['173.245.48.0/20']]);

        $restaurante = Restaurante::factory()->create(['slug' => 'resto-rate-limit-m']);
        $remotoNoConfiable = '198.51.100.42';

        for ($i = 1; $i <= 300; $i++) {
            $ipRotativa = '10.0.'.intdiv($i, 256).'.'.($i % 256);
            $res = $this->withServerVariables(['REMOTE_ADDR' => $remotoNoConfiable])
                ->withHeaders(['X-Forwarded-For' => $ipRotativa])
                ->get('/m/'.$restaurante->slug);

            if ($i === 1 || $i === 300) {
                $res->assertOk();
            }
        }

        // Petición 301 con una nueva IP rotativa debe recibir HTTP 429
        $res301 = $this->withServerVariables(['REMOTE_ADDR' => $remotoNoConfiable])
            ->withHeaders(['X-Forwarded-For' => '10.0.1.45'])
            ->get('/m/'.$restaurante->slug);

        $res301->assertStatus(429);
    }

    public function test_evasion_de_rate_limiter_con_x_forwarded_for_falsificado_en_redireccion_qr_es_bloqueada(): void
    {
        config(['menuonl.trusted_proxies' => ['173.245.48.0/20']]);

        $restaurante = Restaurante::factory()->create(['slug' => 'resto-rate-limit-q']);
        $qr = CodigoQr::factory()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'ratelimitqr01',
            'activo' => true,
        ]);
        $remotoNoConfiable = '198.51.100.43';

        for ($i = 1; $i <= 300; $i++) {
            $ipRotativa = '10.1.'.intdiv($i, 256).'.'.($i % 256);
            $res = $this->withServerVariables(['REMOTE_ADDR' => $remotoNoConfiable])
                ->withHeaders(['X-Forwarded-For' => $ipRotativa])
                ->get('/q/'.$qr->codigo);

            if ($i === 1 || $i === 300) {
                $res->assertStatus(302);
            }
        }

        // Petición 301 con nueva IP rotativa debe ser bloqueada con 429
        $res301 = $this->withServerVariables(['REMOTE_ADDR' => $remotoNoConfiable])
            ->withHeaders(['X-Forwarded-For' => '10.1.1.45'])
            ->get('/q/'.$qr->codigo);

        $res301->assertStatus(429);
    }

    public function test_proxy_confiable_permite_buckets_independientes_por_cada_ip_de_cliente_real(): void
    {
        config(['menuonl.trusted_proxies' => ['173.245.48.0/20']]);

        $restaurante = Restaurante::factory()->create(['slug' => 'resto-proxy-buckets']);
        $proxyConfiable = self::IP_DEL_PROXY;
        $clienteA = '203.0.113.1';
        $clienteB = '203.0.113.2';

        // Cliente A agota su cuota de 300 peticiones
        for ($i = 1; $i <= 300; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $proxyConfiable])
                ->withHeaders(['X-Forwarded-For' => $clienteA])
                ->get('/m/'.$restaurante->slug);
        }

        // Cliente A es bloqueado en la petición 301
        $this->withServerVariables(['REMOTE_ADDR' => $proxyConfiable])
            ->withHeaders(['X-Forwarded-For' => $clienteA])
            ->get('/m/'.$restaurante->slug)
            ->assertStatus(429);

        // Cliente B a través del MISMO proxy confiable debe tener su propio bucket intacto
        $this->withServerVariables(['REMOTE_ADDR' => $proxyConfiable])
            ->withHeaders(['X-Forwarded-For' => $clienteB])
            ->get('/m/'.$restaurante->slug)
            ->assertOk();
    }

    public function test_configuracion_de_proxies_malformados_no_causa_error_fatal(): void
    {
        config(['menuonl.trusted_proxies' => ['not-an-ip', '999.999.999.999', '10.0.0.0/99', '']]);

        $restaurante = Restaurante::factory()->create(['slug' => 'resto-malformed-proxy']);

        $respuesta = $this->pedirDesde('198.51.100.99', '203.0.113.88');
        $this->assertSame('198.51.100.99', $respuesta['ip']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.88'])
            ->get('/m/'.$restaurante->slug)
            ->assertOk();
    }
}
