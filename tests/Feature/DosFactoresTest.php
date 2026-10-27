<?php

namespace Tests\Feature;

use App\Filament\Resources\Categorias\Pages\ListCategorias;
use App\Mail\InvitacionMail;
use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\InvitacionService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class DosFactoresTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const string SECRETO = 'JBSWY3DPEHPK3PXP';

    private Restaurante $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();

        // phpunit.xml turns the requirement off so other tests are not disturbed; these tests force it on.
        config(['menuonl.requerir_2fa' => true]);
        $this->a = $this->crearRestaurante('a');
    }

    private function conDosFactores(User $usuario): User
    {
        $usuario->saveAppAuthenticationSecret(self::SECRETO);

        return $usuario;
    }

    private function perfil(): string
    {
        return route('filament.admin.auth.profile');
    }

    /**
     * @return list<string>
     */
    private function rutasDelPanel(): array
    {
        return ['/admin', '/admin/a', '/admin/a/categorias', '/admin/a/productos', '/admin/a/contactos', '/admin/a/invitaciones', '/admin/a/usuarios', '/admin/a/profile'];
    }

    /**
     * Raw signed snapshot of the Livewire component of the given class, taken from the page HTML.
     */
    private function snapshotDe(string $html, string $claseDelComponente): string
    {
        $nombre = app('livewire.factory')->resolveComponentName($claseDelComponente);

        preg_match_all('/wire:snapshot="([^"]*)"/', $html, $coincidencias);

        foreach ($coincidencias[1] as $codificado) {
            $snapshot = htmlspecialchars_decode($codificado, ENT_QUOTES | ENT_SUBSTITUTE);

            if ((json_decode($snapshot, true)['memo']['name'] ?? null) === $nombre) {
                return $snapshot;
            }
        }

        $this->fail("La página no contiene un snapshot de {$claseDelComponente}");
    }

    /**
     * Send a Livewire update (refresh) for the snapshot, as the browser would from an already loaded page.
     */
    private function actualizarLivewire(string $snapshot): TestResponse
    {
        return $this->withHeader('X-Livewire', 'true')->postJson(route('default-livewire.update'), [
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]]]],
        ]);
    }

    public function test_propietario_y_superadmin_sin_2fa_son_redirigidos_al_perfil_desde_cualquier_ruta(): void
    {
        foreach (['propietario' => $this->crearUsuario('propietario', $this->a), 'superadmin' => $this->crearUsuario('superadmin')] as $rol => $usuario) {
            foreach ($this->rutasDelPanel() as $ruta) {
                $this->actingAs($usuario)->get($ruta)->assertRedirect($this->perfil(), "{$rol} en {$ruta}");
            }
        }
    }

    public function test_el_perfil_y_el_cierre_de_sesion_siguen_disponibles(): void
    {
        $propietario = $this->crearUsuario('propietario', $this->a);

        $this->actingAs($propietario)->get($this->perfil())->assertOk();

        $respuesta = $this->actingAs($propietario)->post(route('filament.admin.auth.logout'));
        $this->assertNotSame($this->perfil(), $respuesta->headers->get('Location'));
        $this->assertGuest();
    }

    public function test_una_pagina_ya_cargada_no_puede_seguir_usando_acciones_de_livewire_sin_2fa(): void
    {
        $propietario = $this->conDosFactores($this->crearUsuario('propietario', $this->a));

        // The page was loaded while the user still had two-factor authentication...
        $html = $this->actingAs($propietario)->get('/admin/a/categorias')->assertOk()->getContent();
        $snapshot = $this->snapshotDe($html, ListCategorias::class);

        // ...and then loses it (or never had it, with the requirement switched on afterwards).
        $propietario->saveAppAuthenticationSecret(null);

        $this->actualizarLivewire($snapshot)->assertRedirect($this->perfil());
    }

    public function test_el_superadmin_sin_2fa_tampoco_puede_usar_livewire_desde_otra_pagina(): void
    {
        $superadmin = $this->conDosFactores($this->crearUsuario('superadmin'));
        $html = $this->actingAs($superadmin)->get('/admin/a/categorias')->assertOk()->getContent();
        $snapshot = $this->snapshotDe($html, ListCategorias::class);
        $superadmin->saveAppAuthenticationSecret(null);

        $this->actualizarLivewire($snapshot)->assertRedirect($this->perfil());
    }

    public function test_los_componentes_livewire_del_propio_perfil_si_funcionan_sin_2fa(): void
    {
        $propietario = $this->crearUsuario('propietario', $this->a);
        Filament::setCurrentPanel('admin');
        $clasePerfil = Filament::getCurrentOrDefaultPanel()->getProfilePage();

        $html = $this->actingAs($propietario)->get($this->perfil())->assertOk()->getContent();

        $this->actualizarLivewire($this->snapshotDe($html, $clasePerfil))->assertOk();
    }

    public function test_con_2fa_configurada_entran_normal(): void
    {
        $propietario = $this->conDosFactores($this->crearUsuario('propietario', $this->a));
        $superadmin = $this->conDosFactores($this->crearUsuario('superadmin'));

        $this->actingAs($propietario)->get('/admin/a/categorias')->assertOk();
        $this->actingAs($propietario)->get('/admin/a/profile')->assertOk();
        $this->actingAs($superadmin)->get('/admin/a/categorias')->assertOk();
        $this->actingAs($superadmin)->get('/admin/a/restaurantes')->assertOk();
    }

    public function test_el_editor_entra_sin_2fa_y_puede_activarla_desde_el_perfil(): void
    {
        $editor = $this->crearUsuario('editor', $this->a);

        $this->actingAs($editor)->get('/admin/a/categorias')->assertOk();
        $this->actingAs($editor)->get($this->perfil())->assertOk();
        $this->assertFalse($editor->requiereDosFactores());

        $html = $this->actingAs($editor)->get('/admin/a/categorias')->getContent();
        $this->actualizarLivewire($this->snapshotDe($html, ListCategorias::class))->assertOk();
    }

    public function test_ser_propietario_en_cualquier_restaurante_hace_obligatoria_la_2fa(): void
    {
        $usuario = $this->crearUsuario('editor', $this->a);
        $usuario->restaurantes()->attach($this->crearRestaurante('b')->id, ['rol' => 'propietario']);

        $this->actingAs($usuario)->get('/admin/a/categorias')->assertRedirect($this->perfil());
    }

    public function test_quien_acepta_una_invitacion_como_propietario_entra_pero_el_panel_le_exige_la_2fa(): void
    {
        Mail::fake();
        $creador = $this->crearUsuario('propietario', $this->a);
        app(InvitacionService::class)->invitar($this->a, 'socio@ejemplo.com', 'propietario', $creador);

        $token = null;
        Mail::assertSent(InvitacionMail::class, function (InvitacionMail $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });

        // The controller signs the new account in with Auth::login(), outside Filament's login page.
        $this->post("/invitaciones/{$token}", [
            'name' => 'Socio Nuevo',
            'password' => 'Contrasena-Segura-123',
            'password_confirmation' => 'Contrasena-Segura-123',
        ])->assertRedirect(route('filament.admin.pages.dashboard', ['tenant' => $this->a]));

        $socio = User::where('email', 'socio@ejemplo.com')->firstOrFail();
        $this->assertAuthenticatedAs($socio);

        // There was no factor to challenge yet, and the panel will not let them work until they set one up.
        $this->get('/admin/a/categorias')->assertRedirect($this->perfil());
        $this->get($this->perfil())->assertOk();
    }

    public function test_quien_acepta_una_invitacion_como_editor_entra_sin_2fa(): void
    {
        Mail::fake();
        $creador = $this->crearUsuario('propietario', $this->a);
        $invitacion = app(InvitacionService::class)->invitar($this->a, 'ayuda@ejemplo.com', 'propietario', $creador);
        // Invitación anterior a que solo se ofreciera "propietario": sigue valiendo como editor.
        Invitacion::withoutGlobalScopes()->whereKey($invitacion->getKey())->update(['rol' => 'editor']);

        $token = null;
        Mail::assertSent(InvitacionMail::class, function (InvitacionMail $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });

        $this->post("/invitaciones/{$token}", [
            'name' => 'Ayuda Nueva',
            'password' => 'Contrasena-Segura-123',
            'password_confirmation' => 'Contrasena-Segura-123',
        ])->assertRedirect();

        $this->get('/admin/a/categorias')->assertOk();
    }

    public function test_con_el_requisito_desactivado_por_config_no_se_exige(): void
    {
        config(['menuonl.requerir_2fa' => false]);

        $this->actingAs($this->crearUsuario('propietario', $this->a))->get('/admin/a/categorias')->assertOk();
    }

    public function test_el_secreto_y_los_codigos_de_recuperacion_se_guardan_cifrados(): void
    {
        $usuario = User::factory()->create();
        $codigos = ['abcd1234-efgh5678', 'ijkl9012-mnop3456'];

        $usuario->saveAppAuthenticationSecret(self::SECRETO);
        $usuario->saveAppAuthenticationRecoveryCodes($codigos);

        $fila = DB::table('users')->where('id', $usuario->id)->first();

        $this->assertStringNotContainsString(self::SECRETO, $fila->app_authentication_secret);
        $this->assertSame(self::SECRETO, Crypt::decryptString($fila->app_authentication_secret));
        $this->assertStringNotContainsString('abcd1234', $fila->app_authentication_recovery_codes);
        $this->assertStringNotContainsString('ijkl9012', $fila->app_authentication_recovery_codes);
        $this->assertSame($codigos, json_decode(Crypt::decryptString($fila->app_authentication_recovery_codes), true));

        $recargado = User::find($usuario->id);
        $this->assertSame(self::SECRETO, $recargado->getAppAuthenticationSecret());
        $this->assertSame($codigos, $recargado->getAppAuthenticationRecoveryCodes());
    }

    public function test_los_secretos_no_salen_al_serializar_el_usuario(): void
    {
        $usuario = $this->conDosFactores(User::factory()->create());

        $this->assertArrayNotHasKey('app_authentication_secret', $usuario->toArray());
        $this->assertArrayNotHasKey('app_authentication_recovery_codes', $usuario->toArray());
    }

    /**
     * Load config/menuonl.php as if the environment had the given variables (null = not defined).
     *
     * @param  array<string, string|null>  $entorno
     * @return array<string, mixed>
     */
    private function configuracionConEntorno(array $entorno): array
    {
        $respaldo = [];

        foreach ($entorno as $nombre => $valor) {
            $respaldo[$nombre] = [$_ENV[$nombre] ?? null, $_SERVER[$nombre] ?? null, getenv($nombre)];

            if ($valor === null) {
                unset($_ENV[$nombre], $_SERVER[$nombre]);
                putenv($nombre);
            } else {
                $_ENV[$nombre] = $_SERVER[$nombre] = $valor;
                putenv("{$nombre}={$valor}");
            }
        }

        try {
            return require config_path('menuonl.php');
        } finally {
            foreach ($respaldo as $nombre => [$env, $server, $proceso]) {
                unset($_ENV[$nombre], $_SERVER[$nombre]);

                if ($env !== null) {
                    $_ENV[$nombre] = $env;
                }

                if ($server !== null) {
                    $_SERVER[$nombre] = $server;
                }

                $proceso === false ? putenv($nombre) : putenv("{$nombre}={$proceso}");
            }
        }
    }

    public function test_sin_definir_require_2fa_y_en_produccion_el_requisito_esta_activo(): void
    {
        $this->assertTrue($this->configuracionConEntorno(['REQUIRE_2FA' => null, 'APP_ENV' => 'production'])['requerir_2fa']);
    }

    public function test_sin_definir_nada_ni_siquiera_app_env_el_requisito_esta_activo(): void
    {
        $this->assertTrue($this->configuracionConEntorno(['REQUIRE_2FA' => null, 'APP_ENV' => null])['requerir_2fa']);
    }

    public function test_solo_un_false_explicito_lo_desactiva_y_un_valor_raro_no(): void
    {
        foreach (['false', 'FALSE', '0', 'off', 'no'] as $apagado) {
            $this->assertFalse($this->configuracionConEntorno(['REQUIRE_2FA' => $apagado])['requerir_2fa'], $apagado);
        }

        foreach (['true', '1', 'quiza', '', ' '] as $activo) {
            $this->assertTrue($this->configuracionConEntorno(['REQUIRE_2FA' => $activo])['requerir_2fa'], "'{$activo}'");
        }
    }

    public function test_el_entorno_de_la_suite_queda_restaurado_tras_probar_la_configuracion(): void
    {
        $antes = [env('APP_ENV'), env('REQUIRE_2FA'), getenv('REQUIRE_2FA')];

        $this->configuracionConEntorno(['REQUIRE_2FA' => null, 'APP_ENV' => null]);
        $this->configuracionConEntorno(['REQUIRE_2FA' => 'true', 'APP_ENV' => 'production']);

        $this->assertSame($antes, [env('APP_ENV'), env('REQUIRE_2FA'), getenv('REQUIRE_2FA')]);
        $this->assertSame('testing', env('APP_ENV'));
    }
}
