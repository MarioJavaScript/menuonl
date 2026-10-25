<?php

namespace Tests\Feature;

use App\Filament\Resources\Restaurantes\Pages\CreateRestaurante;
use App\Filament\Resources\Restaurantes\Pages\ListRestaurantes;
use App\Jobs\EnviarInvitacionJob;
use App\Mail\InvitacionMail;
use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\InvitacionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * El correo de la invitación sale por la cola: un fallo de correo no rompe el alta, la invitación nunca se pierde ni
 * se duplica y el superadmin puede reenviarla (docs/despliegue.md, H5).
 */
class EnvioInvitacionEnColaTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private Restaurante $restaurante;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();

        $this->restaurante = $this->crearRestaurante('envio-en-cola');
        $this->propietario = $this->crearUsuario('propietario', $this->restaurante);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function mensajesDeError(): array
    {
        return [
            'smtp caído' => ['Connection could not be established with host smtp.invalido:587'],
            'con saltos de línea' => ["Expected response code 250\nbut got code 550"],
            'mensaje larguísimo' => [str_repeat('error-del-servidor ', 60)],
        ];
    }

    /**
     * Mail goes through a real mailer whose transport always fails, as an SMTP server that is down would.
     */
    private function hacerQueElCorreoFalle(string $mensaje = 'Connection could not be established with host smtp.invalido:587'): void
    {
        // Si antes hubo Mail::fake(), se vuelve al gestor real para que use el mailer que falla.
        Mail::clearResolvedInstances();
        $this->app->forgetInstance('mail.manager');

        Mail::extend('falla-de-prueba', fn (): AbstractTransport => new class($mensaje) extends AbstractTransport
        {
            public function __construct(private string $mensaje)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                throw new RuntimeException($this->mensaje);
            }

            public function __toString(): string
            {
                return 'falla-de-prueba';
            }
        });

        config(['mail.default' => 'falla', 'mail.mailers.falla' => ['transport' => 'falla-de-prueba']]);
    }

    private function invitar(string $email = 'dueno@ejemplo.com'): Invitacion
    {
        return app(InvitacionService::class)->invitar($this->restaurante, $email, 'propietario', $this->propietario);
    }

    private function tokenEnviado(Invitacion $invitacion): string
    {
        $token = null;

        Mail::assertSent(InvitacionMail::class, function (InvitacionMail $mail) use ($invitacion, &$token): bool {
            if ($mail->invitacion->is($invitacion)) {
                $token = $mail->token;
            }

            return $token !== null;
        });

        return $token;
    }

    public function test_invitar_encola_el_correo_y_no_lo_envia_en_la_peticion(): void
    {
        Queue::fake();
        Mail::fake();

        $invitacion = $this->invitar();

        Queue::assertPushed(EnviarInvitacionJob::class, fn (EnviarInvitacionJob $job): bool => $job->invitacionId === $invitacion->id);
        Mail::assertNothingSent();
        $this->assertTrue($invitacion->fresh()->envioEnCola());
    }

    public function test_el_trabajo_va_cifrado_porque_lleva_el_token_en_claro(): void
    {
        $this->assertInstanceOf(ShouldBeEncrypted::class, new EnviarInvitacionJob(1, 'x'));
    }

    public function test_con_cola_real_el_token_no_aparece_en_claro_en_la_tabla_jobs_y_el_worker_envia_el_correo(): void
    {
        config(['queue.default' => 'database']);
        Mail::fake();

        $invitacion = $this->invitar();

        $this->assertSame(1, DB::table('jobs')->count());
        Mail::assertNothingSent();

        $crudo = (string) DB::table('jobs')->value('payload');
        $datos = json_decode($crudo, true);
        $token = unserialize(Crypt::decrypt($datos['data']['command']))->token;

        $this->assertNotSame('', $token);
        $this->assertStringNotContainsString($token, $crudo);

        $this->artisan('queue:work', ['--once' => true, '--tries' => 1, '--quiet' => true])->assertExitCode(0);

        $this->assertSame($token, $this->tokenEnviado($invitacion));
        $this->assertNotNull($invitacion->fresh()->enviada_en);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_con_cola_sincrona_el_correo_sale_y_la_invitacion_queda_enviada(): void
    {
        Mail::fake();

        $invitacion = $this->invitar();

        $this->tokenEnviado($invitacion);
        $this->assertNotNull($invitacion->fresh()->enviada_en);
        $this->assertFalse($invitacion->fresh()->envioFallido());
    }

    public function test_un_fallo_de_correo_no_rompe_la_creacion_y_la_invitacion_queda_marcada(): void
    {
        $this->hacerQueElCorreoFalle();

        $invitacion = $this->invitar();

        $guardada = Invitacion::withoutGlobalScopes()->findOrFail($invitacion->id);
        $this->assertTrue($guardada->envioFallido());
        $this->assertNull($guardada->enviada_en);
        $this->assertStringContainsString('RuntimeException', $guardada->envio_error);
        $this->assertSame(1, Invitacion::withoutGlobalScopes()->where('email', 'dueno@ejemplo.com')->count());
    }

    #[DataProvider('mensajesDeError')]
    public function test_el_error_guardado_es_corto_de_una_linea_y_cabe_en_su_columna(string $mensaje): void
    {
        $this->hacerQueElCorreoFalle($mensaje);

        $guardada = Invitacion::withoutGlobalScopes()->findOrFail($this->invitar()->id);

        $this->assertLessThanOrEqual(255, mb_strlen($guardada->envio_error));
        $this->assertStringNotContainsString("\n", $guardada->envio_error);
    }

    public function test_el_error_guardado_nunca_contiene_el_token(): void
    {
        $token = str_repeat('Ab3', 16);
        $this->assertStringNotContainsString($token, EnviarInvitacionJob::resumirError(new RuntimeException("falló con $token adentro"), $token));
    }

    public function test_con_cola_real_el_fallo_definitivo_marca_la_invitacion_y_no_filtra_el_token(): void
    {
        config(['queue.default' => 'database']);
        Mail::fake();
        $invitacion = $this->invitar();

        $token = unserialize(Crypt::decrypt(json_decode((string) DB::table('jobs')->value('payload'), true)['data']['command']))->token;

        $this->hacerQueElCorreoFalle();
        $this->artisan('queue:work', ['--once' => true, '--quiet' => true]);

        // Los primeros intentos fallidos se reintentan: la invitación sigue "en cola" y el trabajo vuelve a la tabla.
        $this->assertTrue($invitacion->fresh()->envioEnCola());
        $this->assertSame(1, DB::table('jobs')->count());

        for ($intento = 1; $intento < (new EnviarInvitacionJob(1, 'x'))->tries; $intento++) {
            DB::table('jobs')->update(['available_at' => 0]);
            $this->artisan('queue:work', ['--once' => true, '--quiet' => true]);
        }

        $this->assertTrue($invitacion->fresh()->envioFallido());
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertStringNotContainsString($token, (string) DB::table('failed_jobs')->value('payload'));
        $this->assertStringNotContainsString($token, (string) DB::table('failed_jobs')->value('exception'));
    }

    public function test_si_ni_siquiera_se_puede_encolar_la_invitacion_queda_creada_y_marcada(): void
    {
        Queue::shouldReceive('push')->andThrow(new RuntimeException('Redis no responde'));
        Mail::fake();

        $invitacion = $this->invitar();

        $this->assertTrue(Invitacion::withoutGlobalScopes()->findOrFail($invitacion->id)->envioFallido());
    }

    public function test_reenviar_genera_otro_enlace_en_la_misma_fila_y_el_anterior_deja_de_servir(): void
    {
        Mail::fake();
        $original = $this->invitar();
        $tokenViejo = $this->tokenEnviado($original);

        Mail::fake();
        $reenviada = app(InvitacionService::class)->reenviar($original, $this->propietario);

        $tokenNuevo = $this->tokenEnviado($reenviada);

        $this->assertSame($original->id, $reenviada->id);
        $this->assertNotSame($tokenViejo, $tokenNuevo);
        $this->assertSame(1, Invitacion::withoutGlobalScopes()->where('email', 'dueno@ejemplo.com')->count());
        $this->get("/invitaciones/{$tokenViejo}")->assertNotFound();
        $this->get("/invitaciones/{$tokenNuevo}")->assertOk();
    }

    public function test_reenviar_despues_de_un_fallo_limpia_el_aviso_y_envia(): void
    {
        $this->hacerQueElCorreoFalle();
        $invitacion = $this->invitar();
        $this->assertTrue($invitacion->fresh()->envioFallido());

        Mail::fake();
        $reenviada = app(InvitacionService::class)->reenviar($invitacion, $this->propietario);

        $this->tokenEnviado($reenviada);
        $this->assertFalse($reenviada->fresh()->envioFallido());
        $this->assertNotNull($reenviada->fresh()->enviada_en);
        $this->assertNull($reenviada->fresh()->envio_error);
    }

    public function test_un_trabajo_con_el_token_anterior_no_envia_nada_tras_reenviar(): void
    {
        Queue::fake();
        Mail::fake();
        $invitacion = $this->invitar();
        $tokenViejo = 'viejo'.str_repeat('a', 43);

        app(InvitacionService::class)->reenviar($invitacion, $this->propietario);
        (new EnviarInvitacionJob($invitacion->id, $tokenViejo))->handle();

        Mail::assertNothingSent();
    }

    public function test_el_mismo_trabajo_ejecutado_dos_veces_envia_una_sola_vez(): void
    {
        Queue::fake();
        Mail::fake();
        $invitacion = $this->invitar();
        $token = 'unico'.str_repeat('b', 43);
        Invitacion::withoutGlobalScopes()->whereKey($invitacion->id)->update(['token' => InvitacionService::hashear($token)]);

        $trabajo = new EnviarInvitacionJob($invitacion->id, $token);
        $trabajo->handle();
        $trabajo->handle();

        Mail::assertSent(InvitacionMail::class, 1);
    }

    public function test_una_invitacion_revocada_o_aceptada_no_se_envia_ni_se_marca(): void
    {
        Queue::fake();
        Mail::fake();
        $revocada = $this->invitar('revocada@ejemplo.com');
        $aceptada = $this->invitar('aceptada@ejemplo.com');
        $tokenRevocada = 'revoc'.str_repeat('c', 43);
        $tokenAceptada = 'acept'.str_repeat('d', 43);
        Invitacion::withoutGlobalScopes()->whereKey($revocada->id)->update(['token' => InvitacionService::hashear($tokenRevocada)]);
        Invitacion::withoutGlobalScopes()->whereKey($aceptada->id)->update(['token' => InvitacionService::hashear($tokenAceptada), 'aceptada_en' => now()]);
        Invitacion::withoutGlobalScopes()->whereKey($revocada->id)->delete();

        (new EnviarInvitacionJob($revocada->id, $tokenRevocada))->handle();
        (new EnviarInvitacionJob($aceptada->id, $tokenAceptada))->handle();
        (new EnviarInvitacionJob($revocada->id, $tokenRevocada))->failed(new RuntimeException('x'));

        Mail::assertNothingSent();
        $this->assertNull($aceptada->fresh()->enviada_en);
    }

    public function test_no_se_puede_reenviar_una_invitacion_aceptada(): void
    {
        Mail::fake();
        $invitacion = $this->invitar();
        Invitacion::withoutGlobalScopes()->whereKey($invitacion->id)->update(['aceptada_en' => now()]);

        $this->expectException(LogicException::class);

        app(InvitacionService::class)->reenviar($invitacion->fresh(), $this->propietario);
    }

    public function test_un_editor_no_puede_reenviar_invitaciones(): void
    {
        Mail::fake();
        $invitacion = $this->invitar();
        $editor = $this->crearUsuario('editor', $this->restaurante);

        $this->expectException(AuthorizationException::class);

        app(InvitacionService::class)->reenviar($invitacion, $editor);
    }

    public function test_crear_un_restaurante_con_el_correo_caido_no_lo_deshace_y_avisa_al_superadmin(): void
    {
        $this->hacerQueElCorreoFalle();
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $this->restaurante);

        Livewire::test(CreateRestaurante::class)
            ->fillForm([
                'nombre' => 'Taquería Con Correo Caído',
                'slug' => 'correo-caido',
                'moneda' => 'MXN',
                'idioma_default' => 'es',
                'zona_horaria' => 'America/Mexico_City',
                'estado' => 'activo',
                'email_propietario' => 'dueno@ejemplo.com',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('El restaurante se creó, pero el correo de invitación no salió');

        $nuevo = Restaurante::where('slug', 'correo-caido')->firstOrFail();
        $invitacion = Invitacion::withoutGlobalScopes()->where('restaurante_id', $nuevo->id)->firstOrFail();

        $this->assertDatabaseHas('restaurante_idiomas', ['restaurante_id' => $nuevo->id, 'idioma' => 'es']);
        $this->assertTrue($invitacion->envioFallido());
    }

    public function test_la_lista_de_restaurantes_marca_el_correo_no_enviado_y_permite_reenviarlo(): void
    {
        $this->hacerQueElCorreoFalle();
        $invitacion = $this->invitar();
        $superadmin = $this->crearUsuario('superadmin');
        $this->entrarAlPanel($superadmin, $this->restaurante);

        Livewire::test(ListRestaurantes::class)
            ->assertSee('Correo no enviado')
            ->assertTableActionVisible('reenviarInvitacion', $this->restaurante);

        Mail::fake();

        Livewire::test(ListRestaurantes::class)
            ->callTableAction('reenviarInvitacion', $this->restaurante)
            ->assertNotified('Invitación reenviada');

        $this->assertFalse($invitacion->fresh()->envioFallido());
        $this->assertNotNull($invitacion->fresh()->enviada_en);

        Livewire::test(ListRestaurantes::class)
            ->assertDontSee('Correo no enviado')
            ->assertTableActionHidden('reenviarInvitacion', $this->restaurante);
    }

    public function test_la_lista_de_invitaciones_muestra_el_estado_del_correo(): void
    {
        $this->hacerQueElCorreoFalle();
        $this->invitar('fallida@ejemplo.com');

        $this->actingAs($this->propietario)
            ->get("/admin/{$this->restaurante->slug}/invitaciones")
            ->assertOk()
            ->assertSee('No se envió')
            ->assertSee('Reenviar');
    }

    public function test_la_migracion_de_trabajos_y_fallidos_existe(): void
    {
        foreach (['jobs', 'failed_jobs', 'job_batches'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}");
        }
    }
}
