<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\Restaurante;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class LimiteLoginTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();

        config(['menuonl.login.max_intentos_cuenta' => 3, 'menuonl.login.max_intentos_ip' => 100]);
        RateLimiter::clear('login:cuenta:'.sha1('ana@example.com|127.0.0.1'));

        $restaurante = $this->crearRestaurante('resto-login');
        $this->usuario = User::factory()->create(['email' => 'ana@example.com', 'password' => 'correcta-123456']);
        $this->usuario->restaurantes()->attach($restaurante->id, ['rol' => 'editor']);

        Filament::setCurrentPanel('admin');
    }

    private function intentar(string $email, string $password): Testable
    {
        return Livewire::test(Login::class)
            ->fillForm(['email' => $email, 'password' => $password])
            ->call('authenticate');
    }

    public function test_bloquea_la_cuenta_tras_los_intentos_fallidos_aunque_luego_llegue_la_clave_correcta(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->intentar('ana@example.com', 'mala')->assertHasErrors();
        }

        $this->intentar('ana@example.com', 'correcta-123456')->assertHasErrors(['data.email']);

        $this->assertGuest();
    }

    public function test_la_clave_correcta_antes_del_limite_inicia_sesion_y_reinicia_el_contador(): void
    {
        $this->intentar('ana@example.com', 'mala');
        $this->intentar('ana@example.com', 'mala');
        $this->intentar('ana@example.com', 'correcta-123456')->assertHasNoErrors();

        $this->assertAuthenticatedAs($this->usuario);
        $this->assertSame(3, RateLimiter::remaining('login:cuenta:'.sha1('ana@example.com|127.0.0.1'), 3));
    }

    public function test_el_bloqueo_es_por_cuenta_y_otro_correo_no_queda_bloqueado(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->intentar('ana@example.com', 'mala');
        }

        $otra = User::factory()->create(['email' => 'luis@example.com', 'password' => 'otra-clave-123456']);
        $otra->restaurantes()->attach(Restaurante::first()->id, ['rol' => 'editor']);

        $this->intentar('luis@example.com', 'otra-clave-123456')->assertHasNoErrors();
        $this->assertAuthenticatedAs($otra);
    }

    public function test_un_correo_inexistente_se_bloquea_igual_y_con_el_mismo_mensaje(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->intentar('nadie@example.com', 'mala');
        }

        $bloqueado = $this->intentar('nadie@example.com', 'mala');
        $bloqueado->assertHasErrors(['data.email']);

        $this->assertStringContainsString('Demasiados intentos', collect($bloqueado->errors()->get('data.email'))->first());
    }

    public function test_el_limite_por_ip_bloquea_cualquier_correo(): void
    {
        config(['menuonl.login.max_intentos_cuenta' => 100, 'menuonl.login.max_intentos_ip' => 4]);
        RateLimiter::clear('login:ip:'.sha1('127.0.0.1'));

        for ($i = 0; $i < 4; $i++) {
            $this->intentar("probe{$i}@example.com", 'mala');
        }

        $this->intentar('ana@example.com', 'correcta-123456')->assertHasErrors(['data.email']);
        $this->assertGuest();
    }
}
