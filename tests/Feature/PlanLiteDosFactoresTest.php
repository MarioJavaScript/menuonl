<?php

namespace Tests\Feature;

use App\Filament\Pages\Apariencia;
use App\Filament\Pages\Auth\Login;
use App\Models\Restaurante;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

/**
 * El propietario de un restaurante Lite (`solo_menu`) entra al panel con la exigencia de dos factores encendida
 * (REQUIRE_2FA=1), igual que el de Aura: no llega al panel antes del segundo factor, si aún no lo configuró se le
 * manda a configurarlo, y los fallos siguen siendo el mismo mensaje genérico que no revela si la cuenta existe.
 */
class PlanLiteDosFactoresTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRETO = 'JBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml apaga el requisito para no estorbar a otras pruebas; aquí se enciende, como en producción.
        config(['menuonl.requerir_2fa' => true]);
        Filament::setCurrentPanel('admin');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function cuentasQueNoPuedenEntrar(): array
    {
        return [
            'contraseña incorrecta' => ['contrasena_incorrecta'],
            'cuenta inactiva' => ['cuenta_inactiva'],
            'membresía inactiva' => ['membresia_inactiva'],
            'correo inexistente' => ['correo_inexistente'],
        ];
    }

    private function restauranteLite(): Restaurante
    {
        return Restaurante::factory()->soloMenu()->create(['nombre' => 'Fonda Lite', 'slug' => 'fonda-lite']);
    }

    private function propietarioDeLite(bool $conDosFactores): User
    {
        $propietario = User::factory()->create();
        $this->restauranteLite()->usuarios()->attach($propietario, ['rol' => 'propietario']);

        if ($conDosFactores) {
            $propietario->saveAppAuthenticationSecret(self::SECRETO);
        }

        return $propietario;
    }

    private function intentarEntrar(string $correo, string $clave): Testable
    {
        return Livewire::test(Login::class)
            ->fillForm(['email' => $correo, 'password' => $clave])
            ->call('authenticate');
    }

    private function codigoActual(): string
    {
        return app(Google2FA::class)->getCurrentOtp(self::SECRETO);
    }

    private function perfil(): string
    {
        return route('filament.admin.auth.profile');
    }

    public function test_el_propietario_de_lite_debe_tener_dos_factores(): void
    {
        $this->assertTrue($this->propietarioDeLite(conDosFactores: false)->requiereDosFactores());
    }

    public function test_con_2fa_configurado_no_entra_al_panel_hasta_completar_el_segundo_factor(): void
    {
        $propietario = $this->propietarioDeLite(conDosFactores: true);

        $login = $this->intentarEntrar($propietario->email, 'password');

        $this->assertNotEmpty($login->get('userUndertakingMultiFactorAuthentication'));
        $this->assertGuest();
        $login->assertDontSee('Fonda Lite');

        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/fonda-lite')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_un_codigo_incorrecto_no_inicia_sesion(): void
    {
        $propietario = $this->propietarioDeLite(conDosFactores: true);
        $codigoMalo = $this->codigoActual() === '000000' ? '111111' : '000000';

        $login = $this->intentarEntrar($propietario->email, 'password')
            ->set('data.multiFactor.app.code', $codigoMalo)
            ->call('authenticate')
            ->assertHasErrors(['data.multiFactor.app.code']);

        $this->assertSame(
            [__('filament-panels::auth/multi-factor/app/provider.login_form.code.messages.invalid')],
            $login->errors()->get('data.multiFactor.app.code'),
        );

        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_con_el_codigo_correcto_entra_al_panel_de_su_restaurante_lite(): void
    {
        $propietario = $this->propietarioDeLite(conDosFactores: true);
        $restaurante = Restaurante::where('slug', 'fonda-lite')->sole();

        $this->intentarEntrar($propietario->email, 'password')
            ->set('data.multiFactor.app.code', $this->codigoActual())
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($propietario);
        $this->get('/admin')->assertRedirect(url('/admin/fonda-lite'));
        $this->get('/admin/fonda-lite')->assertOk()->assertSee('Fonda Lite');
        $this->get(Apariencia::getUrl(tenant: $restaurante))->assertOk();
    }

    public function test_sin_2fa_configurado_se_le_manda_a_configurarlo_antes_de_usar_el_panel(): void
    {
        $propietario = $this->propietarioDeLite(conDosFactores: false);
        $restaurante = Restaurante::where('slug', 'fonda-lite')->sole();

        $this->intentarEntrar($propietario->email, 'password')->assertHasNoErrors();
        $this->assertAuthenticatedAs($propietario);

        foreach (['/admin', '/admin/fonda-lite', Apariencia::getUrl(tenant: $restaurante)] as $ruta) {
            $this->get($ruta)->assertRedirect($this->perfil());
        }
        $this->get($this->perfil())->assertOk();

        $propietario->saveAppAuthenticationSecret(self::SECRETO);
        $this->actingAs($propietario->fresh())->get('/admin/fonda-lite')->assertOk();
    }

    #[DataProvider('cuentasQueNoPuedenEntrar')]
    public function test_los_fallos_dan_el_mismo_mensaje_generico_y_nunca_inician_el_segundo_factor(string $caso): void
    {
        $restaurante = $this->restauranteLite();
        $usuario = User::factory()->create();
        $clave = 'password';

        switch ($caso) {
            case 'contrasena_incorrecta':
                $restaurante->usuarios()->attach($usuario, ['rol' => 'propietario']);
                $clave = 'clave-equivocada';
                break;
            case 'cuenta_inactiva':
                $usuario->forceFill(['activo' => false])->save();
                $restaurante->usuarios()->attach($usuario, ['rol' => 'propietario']);
                break;
            case 'membresia_inactiva':
                $restaurante->usuarios()->attach($usuario, ['rol' => 'propietario', 'activo' => false]);
                break;
            case 'correo_inexistente':
                $usuario->delete();
                break;
        }

        // Con 2FA configurado: si el servidor lo pidiera, delataría que la contraseña era válida.
        if ($caso !== 'correo_inexistente') {
            $usuario->saveAppAuthenticationSecret(self::SECRETO);
        }

        $login = $this->intentarEntrar($usuario->email, $clave);

        $this->assertSame(
            [__('filament-panels::auth/pages/login.messages.failed')],
            $login->errors()->get('data.email'),
            $caso,
        );
        $this->assertEmpty($login->get('userUndertakingMultiFactorAuthentication'), $caso);
        $this->assertGuest();
    }

    public function test_el_propietario_de_aura_pasa_por_el_mismo_segundo_factor(): void
    {
        $aura = Restaurante::factory()->sitioMenu()->create();
        $propietario = User::factory()->create();
        $aura->usuarios()->attach($propietario, ['rol' => 'propietario']);
        $propietario->saveAppAuthenticationSecret(self::SECRETO);

        $login = $this->intentarEntrar($propietario->email, 'password');

        $this->assertNotEmpty($login->get('userUndertakingMultiFactorAuthentication'));
        $this->assertGuest();

        $login->set('data.multiFactor.app.code', $this->codigoActual())
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($propietario);
        $this->get('/admin/'.$aura->slug)->assertOk();
    }
}
