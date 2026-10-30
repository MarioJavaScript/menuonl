<?php

namespace Tests\Feature;

use App\Mail\RespaldoVencidoMail;
use App\Services\RespaldoService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Aviso por correo cuando el respaldo falta o está vencido (menuonl:backup-alerta): una vez al día, sin rutas ni
 * excepciones en el mensaje, y sin depender de la hora real ni del php.ini.
 */
class AlertaDeRespaldoTest extends TestCase
{
    private const CORREO = 'equipo@ejemplo.com';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-21 12:00:00');
        CarbonImmutable::setTestNow('2026-10-21 12:00:00');

        Storage::fake('backups');
        Mail::fake();
        config([
            'menuonl.respaldos.disco' => 'backups',
            'menuonl.respaldos.horas_maximas_sin_respaldo' => 36,
            'menuonl.respaldos.alerta_correo' => self::CORREO,
            'mail.default' => 'smtp',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function subirRespaldoDeHaceHoras(int $horas): void
    {
        $fecha = CarbonImmutable::now('UTC')->subHours($horas);
        Storage::disk('backups')->put('menuonl-'.$fecha->format('Ymd-His').'.zip', 'contenido');
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function ejecutar(): array
    {
        $codigo = Artisan::call('menuonl:backup-alerta');

        return [$codigo, Artisan::output()];
    }

    public function test_con_un_respaldo_reciente_no_avisa_y_termina_bien(): void
    {
        $this->subirRespaldoDeHaceHoras(2);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('Respaldo al día', $salida);
        Mail::assertNothingSent();
    }

    public function test_justo_en_el_limite_sigue_al_dia_y_un_poco_mas_ya_avisa(): void
    {
        $this->subirRespaldoDeHaceHoras(36);
        $this->assertNull(app(RespaldoService::class)->diagnosticarAntiguedad()['problema']);

        Storage::fake('backups');
        $this->subirRespaldoDeHaceHoras(37);

        $this->assertSame('vencido', app(RespaldoService::class)->diagnosticarAntiguedad()['problema']);
    }

    public function test_un_respaldo_vencido_avisa_por_correo_y_sale_con_error(): void
    {
        $this->subirRespaldoDeHaceHoras(40);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('Aviso enviado por correo', $salida);
        Mail::assertSent(RespaldoVencidoMail::class, fn (RespaldoVencidoMail $correo): bool => $correo->hasTo(self::CORREO)
            && $correo->problema === 'vencido'
            && $correo->horas === 40
            && $correo->maximo === 36);
    }

    public function test_sin_ningun_respaldo_avisa(): void
    {
        [$codigo] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        Mail::assertSent(RespaldoVencidoMail::class, fn (RespaldoVencidoMail $correo): bool => $correo->problema === 'sin_respaldos' && $correo->horas === null);
    }

    public function test_si_el_disco_no_se_puede_leer_avisa_sin_revelar_el_motivo(): void
    {
        config(['menuonl.respaldos.disco' => 'disco-que-no-existe']);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertStringNotContainsString('disco-que-no-existe', $salida);
        Mail::assertSent(RespaldoVencidoMail::class, function (RespaldoVencidoMail $correo): bool {
            $html = $correo->render();

            return $correo->problema === 'disco_ilegible'
                && ! str_contains($html, 'disco-que-no-existe')
                && ! str_contains($html, 'InvalidArgumentException');
        });
    }

    public function test_solo_se_avisa_una_vez_al_dia_aunque_el_problema_siga(): void
    {
        $this->subirRespaldoDeHaceHoras(40);

        [, $primera] = $this->ejecutar();
        [$codigo, $segunda] = $this->ejecutar();

        $this->assertStringContainsString('Aviso enviado', $primera);
        $this->assertStringContainsString('Ya se avisó hoy', $segunda);
        $this->assertSame(1, $codigo);
        Mail::assertSentCount(1);

        Carbon::setTestNow('2026-10-22 12:00:00');
        CarbonImmutable::setTestNow('2026-10-22 12:00:00');
        $this->ejecutar();

        Mail::assertSentCount(2);
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function destinatariosInvalidos(): array
    {
        return [
            'sin definir' => [null],
            'vacío' => [''],
            'solo espacios' => ['   '],
            'sin arroba' => ['equipo-ejemplo.com'],
        ];
    }

    #[DataProvider('destinatariosInvalidos')]
    public function test_sin_destinatario_valido_no_envia_nada_pero_sigue_fallando(?string $correo): void
    {
        config(['menuonl.respaldos.alerta_correo' => $correo]);
        $this->subirRespaldoDeHaceHoras(40);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('BACKUP_ALERT_EMAIL', $salida);
        Mail::assertNothingSent();
    }

    public function test_con_un_mailer_que_no_envia_correos_reales_no_envia_ni_gasta_el_aviso_del_dia(): void
    {
        config(['mail.default' => 'log']);
        $this->subirRespaldoDeHaceHoras(40);

        [$codigo, $salida] = $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('MAIL_MAILER', $salida);
        Mail::assertNothingSent();

        config(['mail.default' => 'smtp']);
        $this->ejecutar();

        Mail::assertSentCount(1);
    }

    public function test_si_el_correo_falla_no_revela_el_error_y_se_reintenta_en_la_siguiente_revision(): void
    {
        Log::spy();
        Mail::shouldReceive('to')->twice()->andThrow(new RuntimeException('smtp://usuario:clave-secreta@servidor.interno'));
        $this->subirRespaldoDeHaceHoras(40);

        [$codigo, $salida] = $this->ejecutar();
        $this->ejecutar();

        $this->assertSame(1, $codigo);
        $this->assertStringNotContainsString('clave-secreta', $salida);
        $this->assertStringNotContainsString('servidor.interno', $salida);
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $mensaje, array $contexto = []): bool => str_contains($mensaje, 'No se pudo enviar') && $contexto === ['excepcion' => RuntimeException::class])
            ->twice();
    }

    /**
     * @return array<string, array{0: string, 1: int|null}>
     */
    public static function problemas(): array
    {
        return [
            'vencido' => ['vencido', 41],
            'sin respaldos' => ['sin_respaldos', null],
            'disco ilegible' => ['disco_ilegible', null],
        ];
    }

    #[DataProvider('problemas')]
    public function test_el_correo_dice_el_problema_sin_rutas_ni_excepciones(string $problema, ?int $horas): void
    {
        $html = (new RespaldoVencidoMail($problema, $horas, 36))->render();

        $this->assertStringContainsString(__('panel.respaldo_alerta.titulo'), $html);
        $this->assertStringContainsString('menuonl:backup', $html);
        $this->assertStringContainsString(__('panel.respaldo_alerta.problemas.'.$problema, ['horas' => $horas, 'maximo' => 36]), $html);

        foreach (['storage/', 'laravel.log', '.zip', 'Exception', 'Stack trace', 'vendor/', 'BACKUP_ARCHIVE_PASSWORD', 'password'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $html, $prohibido);
        }
    }

    public function test_el_asunto_lleva_el_nombre_de_la_app(): void
    {
        config(['app.name' => 'Menubly de Prueba']);

        $this->assertSame('El respaldo de Menubly de Prueba no se completó', (new RespaldoVencidoMail('vencido', 40, 36))->envelope()->subject);
    }

    /**
     * @return array<string, array{0: string|null, 1: string}>
     */
    public static function horasDeRevision(): array
    {
        return [
            'por defecto' => ['06:00', '06:00'],
            'otra hora válida' => ['07:30', '07:30'],
            'mal escrita' => ['6am', '06:00'],
            'fuera de rango' => ['25:00', '06:00'],
            'vacía' => ['', '06:00'],
            'sin definir' => [null, '06:00'],
        ];
    }

    #[DataProvider('horasDeRevision')]
    public function test_la_hora_de_revision_cae_a_las_6_si_esta_mal_escrita(?string $configurada, string $esperada): void
    {
        config(['menuonl.respaldos.alerta_hora' => $configurada]);

        $this->assertSame($esperada, RespaldoService::horaDeAlerta());
    }

    public function test_la_revision_esta_programada_a_las_6_hora_de_ciudad_de_mexico_despues_del_respaldo(): void
    {
        $eventos = collect($this->app->make(Schedule::class)->events())
            ->filter(fn (Event $evento): bool => str_contains((string) $evento->command, 'menuonl:backup-alerta'));

        $this->assertCount(1, $eventos);

        $evento = $eventos->first();

        $this->assertSame('0 6 * * *', $evento->expression);
        $this->assertSame('America/Mexico_City', (string) $evento->timezone);
        $this->assertTrue($evento->withoutOverlapping);
    }

    public function test_verificar_produccion_avisa_si_nadie_recibiria_la_alerta_sin_imprimir_el_correo(): void
    {
        Artisan::call('menuonl:verificar-produccion');
        $conCorreo = Artisan::output();

        config(['menuonl.respaldos.alerta_correo' => null]);
        Artisan::call('menuonl:verificar-produccion');
        $sinCorreo = Artisan::output();

        $this->assertMatchesRegularExpression('/\[OK\]\s+BACKUP_ALERT_EMAIL está definida/', $conCorreo);
        $this->assertStringNotContainsString(self::CORREO, $conCorreo);
        $this->assertMatchesRegularExpression('/\[AVISO\]\s+BACKUP_ALERT_EMAIL falta/', $sinCorreo);
    }
}
