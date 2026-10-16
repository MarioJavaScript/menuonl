<?php

namespace Tests\Feature;

use App\Filament\Resources\Invitaciones\Pages\CreateInvitacion;
use App\Filament\Resources\Restaurantes\Pages\CreateRestaurante;
use App\Mail\InvitacionMail;
use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\InvitacionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class InvitacionesTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private Restaurante $a;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();

        $this->a = $this->crearRestaurante('a');
        $this->propietario = $this->crearUsuario('propietario', $this->a);
        Mail::fake();
    }

    /**
     * Invite through the service and return the invitation with the plain token taken from the mail.
     *
     * @return array{0: Invitacion, 1: string}
     */
    private function invitar(string $email = 'nuevo@ejemplo.com', string $rol = 'editor'): array
    {
        $invitacion = app(InvitacionService::class)->invitar($this->a, $email, $rol, $this->propietario);

        $token = null;
        Mail::assertSent(InvitacionMail::class, function (InvitacionMail $mail) use ($invitacion, &$token): bool {
            if ($mail->invitacion->is($invitacion)) {
                $token = $mail->token;
            }

            return $token !== null;
        });

        return [$invitacion, $token];
    }

    /**
     * @return array<string, string>
     */
    private function datosNuevaCuenta(string $password = 'Contrasena-Segura-123'): array
    {
        return ['name' => 'Persona Nueva', 'password' => $password, 'password_confirmation' => $password];
    }

    public function test_el_token_se_guarda_como_hash_sha256_y_el_claro_solo_viaja_por_correo(): void
    {
        [$invitacion, $token] = $this->invitar();

        $this->assertGreaterThanOrEqual(40, strlen($token));
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9]+\z/', $token);

        $guardado = Invitacion::withoutGlobalScopes()->findOrFail($invitacion->id)->token;
        $this->assertSame(hash('sha256', $token), $guardado);
        $this->assertSame(64, strlen($guardado));
        $this->assertNotSame($token, $guardado);
        $this->assertDatabaseMissing('invitaciones', ['token' => $token]);
    }

    public function test_los_tokens_son_distintos_en_cada_invitacion(): void
    {
        [, $uno] = $this->invitar('uno@ejemplo.com');
        Mail::fake();
        [, $dos] = $this->invitar('dos@ejemplo.com');

        $this->assertNotSame($uno, $dos);
    }

    public function test_la_invitacion_caduca_a_los_7_dias(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$invitacion] = $this->invitar();

        $this->assertTrue($invitacion->expira_en->equalTo(now()->addDays(7)));
    }

    public function test_el_correo_lleva_el_enlace_con_el_token_y_el_rol(): void
    {
        [$invitacion, $token] = $this->invitar('nuevo@ejemplo.com', 'propietario');

        Mail::assertSent(InvitacionMail::class, function (InvitacionMail $mail) use ($token): bool {
            $html = $mail->render();

            return $mail->hasTo('nuevo@ejemplo.com')
                && str_contains($html, route('invitaciones.mostrar', ['token' => $token]))
                && str_contains($html, 'Propietario')
                && str_contains($html, $this->a->nombre);
        });
        $this->assertSame('propietario', $invitacion->rol);
    }

    public function test_el_correo_no_se_encola(): void
    {
        $this->invitar();

        Mail::assertNotQueued(InvitacionMail::class);
    }

    public function test_aceptar_crea_la_cuenta_la_vincula_con_el_rol_y_marca_la_invitacion(): void
    {
        [$invitacion, $token] = $this->invitar('nuevo@ejemplo.com', 'editor');

        $this->get("/invitaciones/{$token}")->assertOk()->assertSee('Aceptar invitación');

        $this->post("/invitaciones/{$token}", $this->datosNuevaCuenta())
            ->assertRedirect(route('filament.admin.pages.dashboard', ['tenant' => $this->a]));

        $usuario = User::where('email', 'nuevo@ejemplo.com')->firstOrFail();
        $this->assertTrue($usuario->activo);
        $this->assertNotNull($usuario->email_verified_at);
        $this->assertTrue(Hash::check('Contrasena-Segura-123', $usuario->password));
        $this->assertStringStartsWith('$argon2id$', $usuario->password);
        $this->assertSame('editor', $usuario->rolEn($this->a));
        $this->assertNotNull($invitacion->fresh()->aceptada_en);
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_un_propietario_invitado_recibe_el_rol_propietario(): void
    {
        [, $token] = $this->invitar('socio@ejemplo.com', 'propietario');

        $this->post("/invitaciones/{$token}", $this->datosNuevaCuenta())->assertRedirect();

        $this->assertTrue(User::where('email', 'socio@ejemplo.com')->firstOrFail()->esPropietarioDe($this->a));
    }

    public function test_la_invitacion_es_de_un_solo_uso(): void
    {
        [, $token] = $this->invitar();

        $this->post("/invitaciones/{$token}", $this->datosNuevaCuenta())->assertRedirect();
        auth()->logout();

        $this->get("/invitaciones/{$token}")->assertNotFound();
        $this->post("/invitaciones/{$token}", $this->datosNuevaCuenta('Otra-Contrasena-456'))->assertNotFound();
        $this->assertSame(1, User::where('email', 'nuevo@ejemplo.com')->count());
        $this->assertTrue(Hash::check('Contrasena-Segura-123', User::where('email', 'nuevo@ejemplo.com')->first()->password));
    }

    public function test_la_invitacion_caducada_se_rechaza_pero_un_segundo_antes_aun_sirve(): void
    {
        $this->travelTo(now()->startOfSecond());
        [, $token] = $this->invitar();

        $this->travel(7)->days();
        $this->travel(-1)->seconds();
        $this->get("/invitaciones/{$token}")->assertOk();

        $this->travel(2)->seconds();
        $this->get("/invitaciones/{$token}")->assertNotFound();
        $this->post("/invitaciones/{$token}", $this->datosNuevaCuenta())->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@ejemplo.com']);
    }

    public function test_un_token_alterado_se_rechaza(): void
    {
        [, $token] = $this->invitar();

        $ultimo = substr($token, -1);
        $alterado = substr($token, 0, -1).($ultimo === 'a' ? 'b' : 'a');

        foreach ([$alterado, substr($token, 0, -1), $token.'x', strtoupper($token) === $token ? strtolower($token) : strtoupper($token), str_repeat('a', 48)] as $malo) {
            $this->get("/invitaciones/{$malo}")->assertNotFound();
            $this->post("/invitaciones/{$malo}", $this->datosNuevaCuenta())->assertNotFound();
        }

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@ejemplo.com']);
    }

    public function test_el_hash_guardado_no_sirve_como_token(): void
    {
        [$invitacion] = $this->invitar();
        $hash = Invitacion::withoutGlobalScopes()->findOrFail($invitacion->id)->token;

        $this->get("/invitaciones/{$hash}")->assertNotFound();
        $this->post("/invitaciones/{$hash}", $this->datosNuevaCuenta())->assertNotFound();
    }

    public function test_todos_los_fallos_responden_exactamente_lo_mismo(): void
    {
        [, $vigente] = $this->invitar('uno@ejemplo.com');
        [, $usada] = $this->invitar('dos@ejemplo.com');
        [, $caducada] = $this->invitar('tres@ejemplo.com');

        $this->post("/invitaciones/{$usada}", $this->datosNuevaCuenta())->assertRedirect();
        auth()->logout();
        Invitacion::withoutGlobalScopes()->where('email', 'tres@ejemplo.com')->update(['expira_en' => now()->subMinute()]);

        $respuestas = collect([
            'desconocido' => str_repeat('z', 48),
            'formato invalido' => 'corto',
            'alterado' => substr($vigente, 0, -1).(substr($vigente, -1) === 'a' ? 'b' : 'a'),
            'usada' => $usada,
            'caducada' => $caducada,
        ])->map(fn (string $token) => $this->get("/invitaciones/{$token}"));

        $respuestas->each(fn ($respuesta) => $respuesta->assertNotFound());
        $this->assertCount(1, $respuestas->map(fn ($respuesta) => $respuesta->getContent())->unique(), 'el cuerpo revela la causa');
        $this->assertCount(1, $respuestas->map(fn ($respuesta) => $respuesta->getStatusCode())->unique());
    }

    public function test_una_invitacion_de_un_restaurante_suspendido_no_se_puede_aceptar(): void
    {
        [, $token] = $this->invitar();
        $this->a->update(['estado' => 'suspendido']);

        $this->get("/invitaciones/{$token}")->assertNotFound();
    }

    public function test_una_contrasena_debil_no_consume_la_invitacion(): void
    {
        [$invitacion, $token] = $this->invitar();

        $this->post("/invitaciones/{$token}", $this->datosNuevaCuenta('corta'))->assertSessionHasErrors('password');
        $this->post("/invitaciones/{$token}", ['name' => 'X', 'password' => 'Contrasena-Segura-123', 'password_confirmation' => 'distinta'])->assertSessionHasErrors('password');

        $this->assertNull($invitacion->fresh()->aceptada_en);
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@ejemplo.com']);
        $this->get("/invitaciones/{$token}")->assertOk();
    }

    public function test_una_cuenta_existente_debe_iniciar_sesion_para_aceptar(): void
    {
        $existente = User::factory()->create(['email' => 'ya@ejemplo.com']);
        [$invitacion, $token] = $this->invitar('ya@ejemplo.com');

        $this->get("/invitaciones/{$token}")->assertOk()->assertSee('Iniciar sesión');
        $this->post("/invitaciones/{$token}")->assertRedirect(route('invitaciones.mostrar', ['token' => $token]));
        $this->assertNull($existente->rolEn($this->a));
        $this->assertNull($invitacion->fresh()->aceptada_en);

        $intruso = User::factory()->create();
        $this->actingAs($intruso)->post("/invitaciones/{$token}")->assertRedirect(route('invitaciones.mostrar', ['token' => $token]));
        $this->assertNull($intruso->rolEn($this->a));
        $this->assertNull($invitacion->fresh()->aceptada_en);
    }

    public function test_una_cuenta_existente_con_sesion_se_vincula_con_el_rol_invitado(): void
    {
        $existente = User::factory()->create(['email' => 'ya@ejemplo.com']);
        [$invitacion, $token] = $this->invitar('ya@ejemplo.com', 'editor');

        $this->actingAs($existente)->post("/invitaciones/{$token}")->assertRedirect();

        $this->assertSame('editor', User::find($existente->id)->rolEn($this->a));
        $this->assertNotNull($invitacion->fresh()->aceptada_en);
    }

    public function test_reinvitar_al_mismo_correo_invalida_el_enlace_anterior(): void
    {
        [, $primero] = $this->invitar('nuevo@ejemplo.com');
        Mail::fake();
        [, $segundo] = $this->invitar('nuevo@ejemplo.com');

        $this->get("/invitaciones/{$primero}")->assertNotFound();
        $this->get("/invitaciones/{$segundo}")->assertOk();
    }

    public function test_la_ruta_tiene_limite_de_intentos(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get('/invitaciones/'.str_repeat('a', 48))->assertNotFound();
        }

        $this->get('/invitaciones/'.str_repeat('a', 48))->assertTooManyRequests();
    }

    public function test_el_editor_no_puede_invitar_ni_por_servicio_ni_por_panel(): void
    {
        $editor = $this->crearUsuario('editor', $this->a);

        $this->expectException(AuthorizationException::class);

        try {
            app(InvitacionService::class)->invitar($this->a, 'otro@ejemplo.com', 'editor', $editor);
        } finally {
            $this->actingAs($editor)->get('/admin/a/invitaciones/create')->assertForbidden();
            Mail::assertNothingSent();
        }
    }

    public function test_un_propietario_de_otro_restaurante_no_puede_invitar_a_este(): void
    {
        $ajeno = $this->crearUsuario('propietario', $this->crearRestaurante('b'));

        $this->expectException(AuthorizationException::class);

        app(InvitacionService::class)->invitar($this->a, 'otro@ejemplo.com', 'editor', $ajeno);
    }

    public function test_el_propietario_invita_desde_el_panel(): void
    {
        $this->entrarAlPanel($this->propietario, $this->a);

        Livewire::test(CreateInvitacion::class)
            ->fillForm(['email' => 'Nuevo@Ejemplo.com', 'rol' => 'editor'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('invitaciones', ['email' => 'nuevo@ejemplo.com', 'rol' => 'editor', 'restaurante_id' => $this->a->id]);
        Mail::assertSent(InvitacionMail::class, fn (InvitacionMail $mail) => $mail->hasTo('nuevo@ejemplo.com'));
    }

    public function test_no_se_puede_invitar_a_quien_ya_es_miembro_ni_con_un_rol_inventado(): void
    {
        $editor = $this->crearUsuario('editor', $this->a);
        $this->entrarAlPanel($this->propietario, $this->a);

        Livewire::test(CreateInvitacion::class)
            ->fillForm(['email' => $editor->email, 'rol' => 'editor'])
            ->call('create')
            ->assertHasFormErrors(['email']);

        Livewire::test(CreateInvitacion::class)
            ->fillForm(['email' => 'otro@ejemplo.com', 'rol' => 'superadmin'])
            ->call('create')
            ->assertHasFormErrors(['rol']);

        Mail::assertNothingSent();
    }

    public function test_el_superadmin_da_de_alta_un_restaurante_e_invita_a_su_propietario(): void
    {
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $this->a);

        Livewire::test(CreateRestaurante::class)
            ->fillForm([
                'nombre' => 'Taquería Nueva',
                'slug' => 'taqueria-nueva',
                'moneda' => 'MXN',
                'idioma_default' => 'es',
                'zona_horaria' => 'America/Mexico_City',
                'estado' => 'activo',
                'email_propietario' => 'dueno@ejemplo.com',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo = Restaurante::where('slug', 'taqueria-nueva')->firstOrFail();
        $this->assertDatabaseHas('restaurante_idiomas', ['restaurante_id' => $nuevo->id, 'idioma' => 'es']);
        $this->assertDatabaseHas('invitaciones', ['restaurante_id' => $nuevo->id, 'email' => 'dueno@ejemplo.com', 'rol' => 'propietario']);
        Mail::assertSent(InvitacionMail::class, fn (InvitacionMail $mail) => $mail->hasTo('dueno@ejemplo.com'));
    }
}
