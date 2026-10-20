<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class SeguridadCuentasTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    public function test_las_contrasenas_se_guardan_con_argon2id(): void
    {
        $this->assertSame('argon2id', config('hashing.driver'));

        $usuario = User::factory()->create(['password' => 'Contrasena-Segura-123']);

        $this->assertStringStartsWith('$argon2id$', $usuario->password);
        $this->assertTrue(Hash::check('Contrasena-Segura-123', $usuario->password));
    }

    public function test_la_cookie_de_sesion_es_secure_en_produccion_y_siempre_httponly_y_samesite(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);

        $anterior = [$_ENV['APP_ENV'] ?? null, $_SERVER['APP_ENV'] ?? null];
        $cookieAnterior = [$_ENV['SESSION_SECURE_COOKIE'] ?? null, $_SERVER['SESSION_SECURE_COOKIE'] ?? null, getenv('SESSION_SECURE_COOKIE')];
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
        unset($_ENV['SESSION_SECURE_COOKIE'], $_SERVER['SESSION_SECURE_COOKIE']);
        putenv('SESSION_SECURE_COOKIE');

        try {
            $configuracion = require config_path('session.php');
        } finally {
            [$_ENV['APP_ENV'], $_SERVER['APP_ENV']] = $anterior;
            [$_ENV['SESSION_SECURE_COOKIE'], $_SERVER['SESSION_SECURE_COOKIE']] = $cookieAnterior;
            if ($cookieAnterior[2] !== false) {
                putenv('SESSION_SECURE_COOKIE='.$cookieAnterior[2]);
            }
        }

        $this->assertTrue($configuracion['secure']);
        $this->assertTrue($configuracion['http_only']);
        $this->assertSame('lax', $configuracion['same_site']);
    }

    public function test_el_inicio_de_sesion_del_panel_limita_los_intentos(): void
    {
        $restaurante = $this->crearRestaurante('a');
        $usuario = $this->crearUsuario('propietario', $restaurante);
        $usuario->update(['password' => 'Contrasena-Segura-123']);
        Filament::setCurrentPanel('admin');

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)
                ->fillForm(['email' => $usuario->email, 'password' => 'incorrecta'])
                ->call('authenticate');
        }

        // The sixth attempt is blocked even with the right password.
        Livewire::test(Login::class)
            ->fillForm(['email' => $usuario->email, 'password' => 'Contrasena-Segura-123'])
            ->call('authenticate')
            ->assertNotified();

        $this->assertGuest();
    }

    public function test_con_la_contrasena_correcta_el_miembro_inicia_sesion(): void
    {
        $restaurante = $this->crearRestaurante('a');
        $usuario = $this->crearUsuario('editor', $restaurante);
        $usuario->update(['password' => 'Contrasena-Segura-123']);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => $usuario->email, 'password' => 'Contrasena-Segura-123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_un_usuario_sin_restaurantes_no_puede_iniciar_sesion_en_el_panel(): void
    {
        $usuario = User::factory()->create(['password' => 'Contrasena-Segura-123']);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => $usuario->email, 'password' => 'Contrasena-Segura-123'])
            ->call('authenticate');

        $this->assertGuest();
    }

    public function test_la_recuperacion_de_contrasena_esta_disponible(): void
    {
        $this->get('/admin/password-reset/request')->assertOk();
    }
}
