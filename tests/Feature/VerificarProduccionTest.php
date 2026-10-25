<?php

namespace Tests\Feature;

use App\Jobs\LatidoColasJob;
use App\Services\SondaDeCola;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * menuonl:verificar-produccion: dice qué está mal en la configuración de producción sin imprimir secretos.
 */
class VerificarProduccionTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const array SECRETOS = [
        'app.key' => 'base64:clave-de-aplicacion-secreta',
        'mail.mailers.smtp.password' => 'contrasena-smtp-secreta',
        'mail.mailers.smtp.username' => 'usuario-smtp-secreto',
        'database.connections.mysql.password' => 'contrasena-bd-secreta',
        'services.cloudflare.api_token' => 'token-cloudflare-secreto',
    ];

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();

        Storage::fake('backups');
        config(['menuonl.respaldos.disco' => 'backups']);

        $this->configurarProduccionCorrecta();
        $this->sondaQueResponde(true);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function configurarProduccionCorrecta(array $cambios = []): void
    {
        config($cambios + [
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://menu-secreto.ejemplo.com',
            'app.locale' => 'es',
            'mail.default' => 'smtp',
            'queue.default' => 'database',
            'cache.default' => 'database',
            'menuonl.soporte_whatsapp' => '525512349988',
            ...self::SECRETOS,
        ]);
    }

    private function sondaQueResponde(bool $responde): void
    {
        $this->app->instance(SondaDeCola::class, new class($responde) extends SondaDeCola
        {
            public function __construct(private bool $responde) {}

            public function sondear(int $segundos): bool
            {
                return $this->responde;
            }
        });
    }

    /**
     * @return array<string, array{0: string, 1: mixed, 2: string}>
     */
    public static function configuracionesIncorrectas(): array
    {
        return [
            'entorno local' => ['app.env', 'local', 'APP_ENV'],
            'entorno staging' => ['app.env', 'staging', 'APP_ENV'],
            'debug encendido' => ['app.debug', true, 'APP_DEBUG'],
            'url con http' => ['app.url', 'http://menu.ejemplo.com', 'APP_URL'],
            'url sin esquema' => ['app.url', 'menu.ejemplo.com', 'APP_URL'],
            'correo en log' => ['mail.default', 'log', 'MAIL_MAILER'],
            'correo en array' => ['mail.default', 'array', 'MAIL_MAILER'],
            'cola síncrona' => ['queue.default', 'sync', 'QUEUE_CONNECTION'],
            'cola nula' => ['queue.default', 'null', 'QUEUE_CONNECTION'],
            'whatsapp ausente' => ['menuonl.soporte_whatsapp', null, 'SOPORTE_WHATSAPP'],
            'whatsapp vacío' => ['menuonl.soporte_whatsapp', '', 'SOPORTE_WHATSAPP'],
            'whatsapp sin dígitos' => ['menuonl.soporte_whatsapp', 'sin-numero', 'SOPORTE_WHATSAPP'],
            'idioma en inglés' => ['app.locale', 'en', 'APP_LOCALE'],
        ];
    }

    private function ejecutar(array $opciones = []): array
    {
        $codigo = Artisan::call('menuonl:verificar-produccion', $opciones);

        return [$codigo, Artisan::output()];
    }

    public function test_con_todo_bien_termina_en_cero(): void
    {
        $this->artisan('menuonl:verificar-produccion')
            ->expectsOutputToContain('Todo en orden')
            ->doesntExpectOutputToContain('[FALLA]')
            ->assertExitCode(0);
    }

    #[DataProvider('configuracionesIncorrectas')]
    public function test_cada_ajuste_incorrecto_se_nombra_y_hace_fallar_el_comando(string $clave, mixed $valor, string $nombre): void
    {
        $this->configurarProduccionCorrecta([$clave => $valor]);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertMatchesRegularExpression('/\[FALLA\]\s+'.preg_quote($nombre, '/').'/', $salida);
        $this->assertSame(1, preg_match_all('/\[FALLA\]/', $salida), 'Solo debe fallar el ajuste que se rompió.');
    }

    public function test_varios_problemas_se_listan_todos_y_se_cuentan(): void
    {
        $this->configurarProduccionCorrecta(['app.env' => 'local', 'app.debug' => true, 'mail.default' => 'log']);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('3 problema(s) grave(s)', $salida);
    }

    public function test_si_el_worker_no_procesa_el_trabajo_de_prueba_falla(): void
    {
        $this->sondaQueResponde(false);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertMatchesRegularExpression('/\[FALLA\]\s+Worker de colas/', $salida);
        $this->assertStringContainsString('deploy/README.md', $salida);
    }

    public function test_con_cache_que_no_se_comparte_no_se_puede_comprobar_el_worker_y_falla(): void
    {
        $this->configurarProduccionCorrecta(['cache.default' => 'array']);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('no se puede comprobar con CACHE_STORE="array"', $salida);
    }

    public function test_con_cola_sincrona_falla_por_la_cola_y_omite_la_comprobacion_del_worker(): void
    {
        $this->configurarProduccionCorrecta(['queue.default' => 'sync']);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertSame(1, preg_match_all('/\[FALLA\]/', $salida));
        $this->assertStringContainsString('[OMITIDO]', $salida);
    }

    public function test_sin_worker_omite_solo_esa_comprobacion(): void
    {
        $this->sondaQueResponde(false);

        [$codigo, $salida] = $this->ejecutar(['--sin-worker' => true]);

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('comprobación omitida', $salida);
    }

    public function test_si_no_se_puede_encolar_la_prueba_falla_con_un_mensaje_claro(): void
    {
        $this->app->instance(SondaDeCola::class, new class extends SondaDeCola
        {
            public function sondear(int $segundos): bool
            {
                throw new \RuntimeException('Connection refused: usuario-smtp-secreto@bd');
            }
        });

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('no se pudo encolar el trabajo de prueba (RuntimeException)', $salida);
        $this->assertStringNotContainsString('usuario-smtp-secreto', $salida);
    }

    public function test_los_trabajos_fallidos_se_avisan_sin_cambiar_el_codigo_de_salida(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'x',
        ]);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('[AVISO]', $salida);
        $this->assertStringContainsString('1 trabajo(s) fallido(s)', $salida);
    }

    public function test_ninguna_salida_imprime_secretos_ni_valores_privados(): void
    {
        $prohibidos = [...array_values(self::SECRETOS), '525512349988', 'menu-secreto.ejemplo.com'];

        foreach ([[], ['app.env' => 'local', 'app.debug' => true, 'mail.default' => 'log', 'queue.default' => 'sync', 'menuonl.soporte_whatsapp' => null]] as $cambios) {
            $this->configurarProduccionCorrecta($cambios);
            [, $salida] = $this->ejecutar();

            foreach ($prohibidos as $secreto) {
                $this->assertStringNotContainsString($secreto, $salida);
            }
        }
    }

    public function test_la_sonda_real_confirma_solo_cuando_un_worker_procesa_su_trabajo(): void
    {
        $this->app->forgetInstance(SondaDeCola::class);
        $this->app->bind(SondaDeCola::class, SondaDeCola::class);
        $sonda = new SondaDeCola;

        $marca = $sonda->iniciar();

        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertFalse($sonda->confirmado($marca));
        $this->assertFalse($sonda->confirmado('otra-marca'));

        $this->artisan('queue:work', ['--once' => true, '--quiet' => true])->assertExitCode(0);

        $this->assertTrue($sonda->confirmado($marca));
        $this->assertFalse($sonda->confirmado('otra-marca'));
    }

    public function test_la_sonda_real_sin_worker_no_confirma(): void
    {
        $this->assertFalse((new SondaDeCola)->sondear(0));
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_el_trabajo_de_prueba_se_encola_con_su_marca(): void
    {
        Queue::fake();

        $marca = (new SondaDeCola)->iniciar();

        Queue::assertPushed(LatidoColasJob::class, fn (LatidoColasJob $job): bool => $job->marca === $marca);
    }

    private function subirRespaldoDeHaceHoras(int $horas): void
    {
        $fecha = CarbonImmutable::now('UTC')->subHours($horas);
        Storage::disk('backups')->put('menuonl-'.$fecha->format('Ymd-His').'.zip', 'contenido');
    }

    /**
     * @return array<string, array{0: int, 1: bool}>
     */
    public static function antiguedadesDeRespaldo(): array
    {
        return [
            'recién hecho' => [1, false],
            'justo en el límite' => [36, false],
            'poco más viejo que el límite' => [37, true],
            'de hace días' => [96, true],
        ];
    }

    #[DataProvider('antiguedadesDeRespaldo')]
    public function test_avisa_sin_fallar_solo_si_el_ultimo_respaldo_pasa_de_36_horas(int $horas, bool $avisa): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC'));
        $this->subirRespaldoDeHaceHoras($horas);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(0, $codigo, 'El aviso de respaldo no cambia el código de salida.');
        $this->assertSame($avisa, str_contains($salida, "El último respaldo exitoso tiene {$horas} horas"));
        $this->assertSame(! $avisa, str_contains($salida, "Último respaldo hace {$horas} horas"));
        $this->assertStringContainsString('Todo en orden', $salida);
    }

    public function test_usa_el_respaldo_mas_reciente_y_no_el_primero_que_encuentra(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC'));
        $this->subirRespaldoDeHaceHoras(200);
        $this->subirRespaldoDeHaceHoras(5);

        [, $salida] = $this->ejecutar();

        $this->assertStringContainsString('Último respaldo hace 5 horas', $salida);
        $this->assertStringNotContainsString('[AVISO]  El último respaldo', $salida);
    }

    public function test_sin_ningun_respaldo_avisa_sin_fallar(): void
    {
        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('No hay ningún respaldo', $salida);
    }

    public function test_el_limite_de_horas_se_puede_configurar(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC'));
        config(['menuonl.respaldos.horas_maximas_sin_respaldo' => 12]);
        $this->subirRespaldoDeHaceHoras(13);

        [, $salida] = $this->ejecutar();

        $this->assertStringContainsString('tiene 13 horas (máximo 12)', $salida);
    }

    public function test_si_no_se_puede_leer_el_disco_de_respaldos_avisa_sin_fallar_ni_imprimir_el_error(): void
    {
        config(['menuonl.respaldos.disco' => 'disco-que-no-existe']);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('No se pudo consultar el disco de respaldos', $salida);
        $this->assertStringContainsString('Todo en orden', $salida);
    }
}
