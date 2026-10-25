<?php

namespace Tests\Feature;

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
 * El inicio de sesión del propietario de un restaurante sin panel por su plan, con la exigencia de dos factores
 * encendida (REQUIRE_2FA=1): la pantalla "Tu plan no incluye el panel" llega solo después del segundo factor, y los
 * fallos siguen siendo el mismo mensaje genérico que no revela si la cuenta existe.
 */
class PlanSinPanelDosFactoresTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRETO = 'JBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml apaga el requisito para no estorbar a otras pruebas; aquí se enciende, como en producción.
        config(['menuonl.requerir_2fa' => true, 'menuonl.soporte_whatsapp' => '5215512345678']);
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
            'cuenta sin restaurantes' => ['sin_restaurantes'],
            'membresía inactiva' => ['membresia_inactiva'],
            'correo inexistente' => ['correo_inexistente'],
        ];
    }

    private function propietarioDeSoloMenu(bool $conDosFactores): User
    {
        $restaurante = Restaurante::factory()->soloMenu()->create(['nombre' => 'Fonda Sin Panel']);
        $propietario = User::factory()->create();
        $restaurante->usuarios()->attach($propietario, ['rol' => 'propietario']);

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

    public function test_con_2fa_configurado_la_pantalla_no_aparece_hasta_completar_el_segundo_factor(): void
    {
        $propietario = $this->propietarioDeSoloMenu(conDosFactores: true);

        $login = $this->intentarEntrar($propietario->email, 'password');

        $this->assertNotEmpty($login->get('userUndertakingMultiFactorAuthentication'));
        $this->assertGuest();
        $login->assertDontSee(__('panel.plan_sin_panel.titulo'))->assertDontSee('Fonda Sin Panel');

        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/panel')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_un_codigo_incorrecto_no_inicia_sesion_ni_muestra_la_pantalla(): void
    {
        $propietario = $this->propietarioDeSoloMenu(conDosFactores: true);
        $codigoMalo = $this->codigoActual() === '000000' ? '111111' : '000000';

        $login = $this->intentarEntrar($propietario->email, 'password')
            ->set('data.multiFactor.app.code', $codigoMalo)
            ->call('authenticate')
            ->assertHasErrors(['data.multiFactor.app.code'])
            ->assertDontSee(__('panel.plan_sin_panel.titulo'));

        $this->assertSame(
            [__('filament-panels::auth/multi-factor/app/provider.login_form.code.messages.invalid')],
            $login->errors()->get('data.multiFactor.app.code'),
        );

        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_con_el_codigo_correcto_entra_y_solo_entonces_ve_la_pantalla(): void
    {
        $propietario = $this->propietarioDeSoloMenu(conDosFactores: true);

        $this->intentarEntrar($propietario->email, 'password')
            ->set('data.multiFactor.app.code', $this->codigoActual())
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($propietario);

        foreach (['/admin', '/panel'] as $ruta) {
            $this->get($ruta)
                ->assertForbidden()
                ->assertSee(__('panel.plan_sin_panel.titulo'))
                ->assertSee('Fonda Sin Panel');
        }
    }

    public function test_sin_2fa_configurado_no_se_le_pide_configurarlo_para_ver_un_aviso_sin_nada_que_proteger(): void
    {
        $propietario = $this->propietarioDeSoloMenu(conDosFactores: false);

        $this->intentarEntrar($propietario->email, 'password')->assertHasNoErrors();

        $this->assertAuthenticatedAs($propietario);
        $this->get('/admin')->assertForbidden()->assertSee(__('panel.plan_sin_panel.titulo'));
        $this->get(route('filament.admin.auth.profile'))->assertForbidden()->assertSee(__('panel.plan_sin_panel.titulo'));
        $this->post(route('filament.admin.auth.logout'))->assertRedirect();
        $this->assertGuest();
    }

    #[DataProvider('cuentasQueNoPuedenEntrar')]
    public function test_los_fallos_dan_el_mismo_mensaje_generico_y_nunca_inician_el_segundo_factor(string $caso): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $usuario = User::factory()->create();

        switch ($caso) {
            case 'contraseña_incorrecta':
            case 'contrasena_incorrecta':
                $restaurante->usuarios()->attach($usuario, ['rol' => 'propietario']);
                $clave = 'clave-equivocada';
                break;
            case 'cuenta_inactiva':
                $usuario->forceFill(['activo' => false])->save();
                $restaurante->usuarios()->attach($usuario, ['rol' => 'propietario']);
                $clave = 'password';
                break;
            case 'membresia_inactiva':
                $restaurante->usuarios()->attach($usuario, ['rol' => 'propietario', 'activo' => false]);
                $clave = 'password';
                break;
            case 'correo_inexistente':
                $usuario->delete();
                $clave = 'password';
                break;
            default:
                $clave = 'password';
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

    public function test_una_cuenta_con_acceso_normal_sigue_pasando_por_el_segundo_factor(): void
    {
        $completo = Restaurante::factory()->create();
        $propietario = User::factory()->create();
        $completo->usuarios()->attach($propietario, ['rol' => 'propietario']);
        $propietario->saveAppAuthenticationSecret(self::SECRETO);

        $login = $this->intentarEntrar($propietario->email, 'password');

        $this->assertNotEmpty($login->get('userUndertakingMultiFactorAuthentication'));
        $this->assertGuest();

        $login->set('data.multiFactor.app.code', $this->codigoActual())
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($propietario);
        $this->get('/panel')->assertOk()->assertDontSee(__('panel.plan_sin_panel.titulo'));
    }
}
