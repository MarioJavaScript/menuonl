<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProxiesDeConfianzaTest extends TestCase
{
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
}
