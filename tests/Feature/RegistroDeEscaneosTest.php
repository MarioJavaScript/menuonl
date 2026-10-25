<?php

namespace Tests\Feature;

use App\Enums\TipoCodigoAcceso;
use App\Http\Controllers\QrRedirectController;
use App\Models\CodigoQr;
use App\Models\EscaneoDiario;
use App\Models\Restaurante;
use App\Services\RegistroDeEscaneos;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Backend de métricas (docs/integracion-dashboard.md, PR 10): cada redirección válida de /q/{codigo} suma 1 a un agregado
 * por (código, día local del restaurante, tipo). Sin IP, user-agent, cookies ni hora; nunca afecta la redirección.
 */
class RegistroDeEscaneosTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const string AHORA = '2026-10-20 15:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
        Carbon::setTestNow(Carbon::parse(self::AHORA, 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function codigoDeMesa(string $mesa = '4', string $zona = 'America/Mexico_City', string $plan = 'completo'): CodigoQr
    {
        $restaurante = match ($plan) {
            'sitio_menu' => Restaurante::factory()->sitioMenu(),
            'solo_menu' => Restaurante::factory()->soloMenu(),
            default => Restaurante::factory(),
        };

        return CodigoQr::factory()->deMesa($mesa)->create([
            'restaurante_id' => $restaurante->create(['zona_horaria' => $zona])->id,
        ]);
    }

    private function escanear(CodigoQr $codigo, array $cabeceras = []): void
    {
        $this->get(route('qr.redirect', ['codigo' => $codigo->codigo], false), $cabeceras)->assertStatus(302);
    }

    public function test_un_escaneo_valido_crea_la_fila_del_dia_con_conteo_1(): void
    {
        $codigo = $this->codigoDeMesa();

        $this->escanear($codigo);

        $fila = EscaneoDiario::withoutGlobalScopes()->sole();
        $this->assertSame($codigo->id, $fila->codigo_qr_id);
        $this->assertSame($codigo->restaurante_id, $fila->restaurante_id);
        $this->assertSame('2026-10-20', $fila->fecha->toDateString());
        $this->assertSame(TipoCodigoAcceso::Qr, $fila->tipo);
        $this->assertSame(1, $fila->conteo);
    }

    public function test_los_escaneos_siguientes_incrementan_la_misma_fila(): void
    {
        $codigo = $this->codigoDeMesa();

        foreach (range(1, 5) as $ignorado) {
            $this->escanear($codigo);
        }

        $this->assertSame(1, EscaneoDiario::withoutGlobalScopes()->count());
        $this->assertSame(5, EscaneoDiario::withoutGlobalScopes()->sole()->conteo);
    }

    public function test_cada_escaneo_es_una_sola_sentencia_atomica_sin_leer_antes(): void
    {
        $codigo = $this->codigoDeMesa();
        $sentencias = [];
        DB::listen(function ($consulta) use (&$sentencias): void {
            if (str_contains($consulta->sql, 'escaneos_diarios')) {
                $sentencias[] = strtolower($consulta->sql);
            }
        });

        $this->escanear($codigo);
        $this->escanear($codigo);

        $this->assertCount(2, $sentencias, 'Una sentencia por escaneo y ninguna lectura previa de la tabla.');
        $conflicto = match (DB::connection()->getDriverName()) {
            'sqlite' => 'on conflict ("codigo_qr_id", "fecha", "tipo") do update set "conteo" = conteo + 1',
            'mysql', 'mariadb' => 'on duplicate key update `conteo` = conteo + 1',
            default => $this->fail('Driver sin sintaxis de upsert conocida en esta prueba: '.DB::connection()->getDriverName()),
        };
        foreach ($sentencias as $sql) {
            $this->assertStringStartsWith('insert into', $sql);
            $this->assertStringContainsString($conflicto, $sql);
        }
        $this->assertSame(2, EscaneoDiario::withoutGlobalScopes()->sole()->conteo);
    }

    public function test_el_conteo_ocurre_despues_de_responder(): void
    {
        $codigo = $this->codigoDeMesa();
        $kernel = $this->app->make(Kernel::class);
        $peticion = Request::create(route('qr.redirect', ['codigo' => $codigo->codigo], false), 'GET');

        $respuesta = $kernel->handle($peticion);

        $this->assertSame(302, $respuesta->getStatusCode());
        $this->assertSame(0, EscaneoDiario::withoutGlobalScopes()->count(), 'Todavía no se contó: la respuesta ya está lista.');

        $kernel->terminate($peticion, $respuesta);

        $this->assertSame(1, EscaneoDiario::withoutGlobalScopes()->sole()->conteo);
    }

    public function test_el_dia_es_el_de_la_zona_horaria_del_restaurante(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 03:30:00', 'UTC'));
        $mexico = $this->codigoDeMesa('1', 'America/Mexico_City');
        $tokio = $this->codigoDeMesa('1', 'Asia/Tokyo');

        $this->escanear($mexico);
        $this->escanear($tokio);

        $fechas = EscaneoDiario::withoutGlobalScopes()->get()->mapWithKeys(fn (EscaneoDiario $fila): array => [$fila->codigo_qr_id => $fila->fecha->toDateString()]);
        $this->assertSame('2026-10-24', $fechas[$mexico->id], '03:30 UTC es la noche del 24 en Ciudad de México.');
        $this->assertSame('2026-10-25', $fechas[$tokio->id], '03:30 UTC es el mediodía del 25 en Tokio.');
    }

    public function test_una_zona_invalida_cae_a_la_de_la_aplicacion(): void
    {
        $codigo = $this->codigoDeMesa('2', 'Zona/Inexistente');

        $this->escanear($codigo);

        $this->assertSame(config('app.timezone'), 'UTC');
        $this->assertSame('2026-10-20', EscaneoDiario::withoutGlobalScopes()->sole()->fecha->toDateString());
    }

    public function test_dias_y_codigos_distintos_dan_filas_distintas(): void
    {
        $uno = $this->codigoDeMesa('1');
        $otro = CodigoQr::factory()->deMesa('2')->create(['restaurante_id' => $uno->restaurante_id]);

        $this->escanear($uno);
        $this->escanear($otro);
        Carbon::setTestNow(Carbon::parse('2026-10-21 15:00:00', 'UTC'));
        $this->escanear($uno);

        $this->assertSame(3, EscaneoDiario::withoutGlobalScopes()->count());
        $this->assertSame(1, EscaneoDiario::withoutGlobalScopes()->where('codigo_qr_id', $otro->id)->sole()->conteo);
    }

    public function test_cambiar_el_tipo_a_mitad_del_dia_parte_el_conteo_en_dos_filas(): void
    {
        $codigo = $this->codigoDeMesa();

        $this->escanear($codigo);
        $codigo->update(['tipo' => TipoCodigoAcceso::Nfc]);
        $this->escanear($codigo);
        $this->escanear($codigo);

        $filas = EscaneoDiario::withoutGlobalScopes()->orderBy('tipo')->get();
        $this->assertSame(['nfc', 'qr'], $filas->map(fn (EscaneoDiario $fila): string => $fila->tipo->value)->all());
        $this->assertSame([2, 1], $filas->pluck('conteo')->all());
    }

    #[DataProvider('planes')]
    public function test_el_conteo_corre_para_todos_los_planes(string $plan): void
    {
        $codigo = $this->codigoDeMesa('3', 'America/Mexico_City', $plan);

        $this->escanear($codigo);

        $this->assertSame(1, EscaneoDiario::withoutGlobalScopes()->sole()->conteo);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function planes(): array
    {
        return ['completo' => ['completo'], 'sitio y menú' => ['sitio_menu'], 'solo menú' => ['solo_menu']];
    }

    public function test_los_codigos_nfc_se_cuentan_como_nfc(): void
    {
        $restaurante = Restaurante::factory()->create();
        $nfc = CodigoQr::factory()->nfc()->deMesa('9')->create(['restaurante_id' => $restaurante->id]);

        $this->escanear($nfc);

        $this->assertSame(TipoCodigoAcceso::Nfc, EscaneoDiario::withoutGlobalScopes()->sole()->tipo);
    }

    public function test_los_404_no_se_cuentan(): void
    {
        $inactivo = CodigoQr::factory()->inactivo()->create(['restaurante_id' => Restaurante::factory()->create()->id]);
        $suspendido = CodigoQr::factory()->create(['restaurante_id' => Restaurante::factory()->suspendido()->create()->id]);
        $borrado = CodigoQr::factory()->create(['restaurante_id' => ($restauranteBorrado = Restaurante::factory()->create())->id]);
        $restauranteBorrado->delete();

        foreach (['noexiste99', 'mal formado!', $inactivo->codigo, $suspendido->codigo, $borrado->codigo] as $codigo) {
            $this->get('/q/'.rawurlencode($codigo))->assertNotFound();
        }

        $this->assertSame(0, EscaneoDiario::withoutGlobalScopes()->count());
    }

    public function test_head_no_cuenta(): void
    {
        $codigo = $this->codigoDeMesa();

        $this->call('HEAD', route('qr.redirect', ['codigo' => $codigo->codigo], false));

        $this->assertSame(0, EscaneoDiario::withoutGlobalScopes()->count());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function cargasAnticipadas(): array
    {
        return [
            'Sec-Purpose prefetch' => ['Sec-Purpose', 'prefetch'],
            'Sec-Purpose prerender' => ['Sec-Purpose', 'prefetch;prerender'],
            'Purpose' => ['Purpose', 'prefetch'],
            'X-Moz' => ['X-Moz', 'prefetch'],
        ];
    }

    #[DataProvider('cargasAnticipadas')]
    public function test_las_cargas_anticipadas_no_cuentan(string $cabecera, string $valor): void
    {
        $this->escanear($this->codigoDeMesa(), [$cabecera => $valor]);

        $this->assertSame(0, EscaneoDiario::withoutGlobalScopes()->count());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function agentesNoHumanos(): array
    {
        return [
            'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            'palabra bot' => ['Mozilla/5.0 (compatible; bot)'],
            'crawler' => ['Mozilla/5.0 (compatible; Yahoo Crawler)'],
            'spider' => ['Mozilla/5.0 (compatible; Baiduspider/2.0)'],
            'vista previa' => ['Slack-LinkExpanding 1.0 (+https://api.slack.com/robots) preview'],
        ];
    }

    #[DataProvider('agentesNoHumanos')]
    public function test_los_bots_y_vistas_previas_declarados_no_cuentan(string $agente): void
    {
        $this->escanear($this->codigoDeMesa(), ['User-Agent' => $agente]);

        $this->assertSame(0, EscaneoDiario::withoutGlobalScopes()->count());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function agentesHumanos(): array
    {
        return [
            'Android con CUBOT' => ['Mozilla/5.0 (Linux; Android 10; CUBOT_X30) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'],
            'iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'],
            'sin user-agent' => [''],
        ];
    }

    #[DataProvider('agentesHumanos')]
    public function test_los_telefonos_reales_si_cuentan_aunque_el_nombre_lleve_bot_dentro(string $agente): void
    {
        $this->escanear($this->codigoDeMesa(), ['User-Agent' => $agente]);

        $this->assertSame(1, EscaneoDiario::withoutGlobalScopes()->count());
    }

    public function test_si_el_conteo_falla_la_redireccion_sigue_igual_y_se_registra_el_error(): void
    {
        $codigo = $this->codigoDeMesa('7');
        $destino = route('menu.publico', ['slug' => $codigo->restaurante->slug, 'mesa' => '7']);
        Exceptions::fake();
        DB::beforeExecuting(function (string $sql): void {
            if (str_contains($sql, 'escaneos_diarios')) {
                throw new RuntimeException('Fallo simulado del conteo.');
            }
        });

        $this->get(route('qr.redirect', ['codigo' => $codigo->codigo], false))
            ->assertStatus(302)
            ->assertRedirect($destino);

        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_la_respuesta_no_trae_cookies_ni_cabeceras_nuevas_por_contar(): void
    {
        $codigo = $this->codigoDeMesa();
        $contada = $this->get(route('qr.redirect', ['codigo' => $codigo->codigo], false));
        $noContada = $this->get(route('qr.redirect', ['codigo' => $codigo->codigo], false), ['User-Agent' => 'Mozilla/5.0 (compatible; bot)']);

        $this->assertSame(1, EscaneoDiario::withoutGlobalScopes()->sole()->conteo, 'Solo el escaneo humano contó.');
        $this->assertSame([], $contada->headers->getCookies());
        $nombres = fn ($respuesta): array => collect($respuesta->headers->keys())->reject(fn (string $n): bool => $n === 'date')->sort()->values()->all();
        $this->assertSame($nombres($noContada), $nombres($contada));
        $this->assertSame($noContada->headers->get('Location'), $contada->headers->get('Location'));
        $this->assertSame($noContada->headers->get('Cache-Control'), $contada->headers->get('Cache-Control'));
    }

    public function test_no_se_guarda_nada_que_identifique_a_la_persona(): void
    {
        $codigo = $this->codigoDeMesa();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->get(route('qr.redirect', ['codigo' => $codigo->codigo], false), ['User-Agent' => 'AgenteMuyEspecifico/9.9', 'Cookie' => 'rastro=abc123'])
            ->assertStatus(302);

        $this->assertSame(['id', 'restaurante_id', 'codigo_qr_id', 'fecha', 'tipo', 'conteo'], Schema::getColumnListing('escaneos_diarios'));
        $guardado = json_encode(DB::table('escaneos_diarios')->get());
        foreach (['203.0.113.77', 'AgenteMuyEspecifico', 'abc123'] as $rastro) {
            $this->assertStringNotContainsString($rastro, (string) $guardado);
        }
    }

    public function test_la_restriccion_unica_impide_dos_filas_del_mismo_codigo_dia_y_tipo(): void
    {
        $codigo = $this->codigoDeMesa();
        $fila = ['restaurante_id' => $codigo->restaurante_id, 'codigo_qr_id' => $codigo->id, 'fecha' => '2026-10-20', 'tipo' => 'qr', 'conteo' => 1];
        DB::table('escaneos_diarios')->insert($fila);

        DB::table('escaneos_diarios')->insert([...$fila, 'tipo' => 'nfc']);
        $this->assertSame(2, DB::table('escaneos_diarios')->count());

        $this->expectException(QueryException::class);
        DB::table('escaneos_diarios')->insert($fila);
    }

    public function test_borrar_un_codigo_o_un_restaurante_borra_sus_conteos(): void
    {
        $uno = $this->codigoDeMesa('1');
        $otro = $this->codigoDeMesa('2');
        $this->escanear($uno);
        $this->escanear($otro);

        $uno->delete();
        $this->assertSame([$otro->id], EscaneoDiario::withoutGlobalScopes()->pluck('codigo_qr_id')->all());

        $otro->restaurante->forceDelete();
        $this->assertSame(0, EscaneoDiario::withoutGlobalScopes()->count());
    }

    public function test_el_filtro_de_user_agent_usa_palabra_completa(): void
    {
        $this->assertSame(1, preg_match(RegistroDeEscaneos::PATRON_DE_USER_AGENT_NO_HUMANO, 'compatible; bot'));
        $this->assertSame(0, preg_match(RegistroDeEscaneos::PATRON_DE_USER_AGENT_NO_HUMANO, 'Android 10; CUBOT_X30'));
        $this->assertSame(0, preg_match(RegistroDeEscaneos::PATRON_DE_USER_AGENT_NO_HUMANO, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)'));
    }

    public function test_el_controlador_recibe_el_servicio_y_no_lee_la_ip(): void
    {
        $fuente = (string) file_get_contents((new \ReflectionClass(QrRedirectController::class))->getFileName());
        $servicio = (string) file_get_contents((new \ReflectionClass(RegistroDeEscaneos::class))->getFileName());

        $this->assertStringContainsString('RegistroDeEscaneos $escaneos', $fuente);
        foreach ([$fuente, $servicio] as $codigoFuente) {
            $this->assertStringNotContainsString('->ip()', $codigoFuente);
            $this->assertStringNotContainsString('cookie(', $codigoFuente);
            $this->assertStringNotContainsString('session(', $codigoFuente);
        }
    }

    public function test_la_herramienta_manual_de_concurrencia_no_corre_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('menuonl:probar-concurrencia-escaneos')->assertFailed();
    }
}
