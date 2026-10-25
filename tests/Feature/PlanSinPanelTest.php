<?php

namespace Tests\Feature;

use App\Enums\PlanRestaurante;
use App\Filament\Pages\Auth\Login;
use App\Models\Restaurante;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pantalla para quien solo pertenece a restaurantes cuyo plan no incluye el panel (docs/planes.md, paso 6, D10).
 */
class PlanSinPanelTest extends TestCase
{
    use RefreshDatabase;

    private const NUMERO = '5215512345678';

    protected function setUp(): void
    {
        parent::setUp();
        config(['menuonl.soporte_whatsapp' => self::NUMERO]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesDelEquipo(): array
    {
        return [
            'propietario' => ['propietario'],
            'gerente' => ['gerente'],
            'editor' => ['editor'],
            'recepcion' => ['recepcion'],
            'cocina' => ['cocina'],
        ];
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function numerosSinSoporte(): array
    {
        return [
            'sin definir' => [null],
            'vacío' => [''],
            'solo espacios' => ['   '],
            'sin dígitos' => ['no-es-un-numero'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rutasDeEntrada(): array
    {
        return [
            'admin' => ['/admin'],
            'panel de servicio' => ['/panel'],
        ];
    }

    private function miembro(Restaurante $restaurante, string $rol = 'propietario'): User
    {
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario, ['rol' => $rol]);

        return $usuario;
    }

    private function enlaceEsperado(): string
    {
        return 'https://wa.me/'.self::NUMERO.'?text='.rawurlencode('Hola, quiero cambiar algo de mi menú');
    }

    #[DataProvider('rutasDeEntrada')]
    public function test_el_propietario_de_solo_menu_ve_la_pantalla_amable_y_no_un_403_a_secas(string $ruta): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create(['nombre' => 'Taquería La Esquina']);
        $propietario = $this->miembro($restaurante);

        $respuesta = $this->actingAs($propietario)->get($ruta);

        $respuesta->assertForbidden()
            ->assertSee(__('panel.plan_sin_panel.titulo'))
            ->assertSee('Taquería La Esquina')
            ->assertSee(__('panel.plan_sin_panel.proveedor'))
            ->assertSee(__('panel.plan_sin_panel.boton_whatsapp'))
            ->assertSee(e($this->enlaceEsperado()), false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    #[DataProvider('rolesDelEquipo')]
    public function test_cada_rol_del_equipo_de_un_restaurante_solo_menu_ve_la_pantalla_en_admin_y_panel(string $rol): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $usuario = $this->miembro($restaurante, $rol);

        foreach (['/admin', '/panel', '/admin/'.$restaurante->slug] as $ruta) {
            $this->actingAs($usuario)->get($ruta)
                ->assertForbidden()
                ->assertSee(__('panel.plan_sin_panel.titulo'));
        }
    }

    public function test_la_pantalla_solo_muestra_el_nombre_del_restaurante(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create([
            'nombre' => 'Cocina de Doña Lupe',
            'slug' => 'dona-lupe-secreta',
            'whatsapp_pedidos' => '5215599990000',
        ]);
        $propietario = $this->miembro($restaurante);

        foreach (['/admin', '/panel'] as $ruta) {
            $html = $this->actingAs($propietario)->get($ruta)->getContent();

            $this->assertStringContainsString('Cocina de Doña Lupe', $html);

            foreach (['dona-lupe-secreta', '5215599990000', 'solo_menu', 'sitio_menu', 'completo', (string) $propietario->email, 'MXN', 'America/Mexico_City'] as $dato) {
                $this->assertStringNotContainsString($dato, $html, $dato);
            }

            foreach (PlanRestaurante::cases() as $plan) {
                $this->assertStringNotContainsString($plan->getLabel(), $html, $plan->value);
            }
        }
    }

    public function test_el_nombre_del_restaurante_sale_escapado(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create(['nombre' => '<script>alert(1)</script> Grill']);

        $html = $this->actingAs($this->miembro($restaurante))->get('/admin')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString(e('<script>alert(1)</script> Grill'), $html);
    }

    #[DataProvider('numerosSinSoporte')]
    public function test_sin_numero_de_soporte_no_hay_boton_y_el_texto_manda_al_proveedor(?string $numero): void
    {
        config(['menuonl.soporte_whatsapp' => $numero]);
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $propietario = $this->miembro($restaurante);

        foreach (['/admin', '/panel'] as $ruta) {
            $this->actingAs($propietario)->get($ruta)
                ->assertForbidden()
                ->assertSee(__('panel.plan_sin_panel.sin_whatsapp'))
                ->assertDontSee(__('panel.plan_sin_panel.boton_whatsapp'))
                ->assertDontSee('wa.me');
        }
    }

    public function test_con_numero_de_soporte_el_boton_abre_whatsapp_en_otra_pestana_de_forma_segura(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();

        $this->actingAs($this->miembro($restaurante))->get('/admin')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertDontSee(__('panel.plan_sin_panel.sin_whatsapp'));
    }

    public function test_varios_restaurantes_solo_menu_se_listan_por_nombre(): void
    {
        $uno = Restaurante::factory()->soloMenu()->create(['nombre' => 'Zeta Tacos']);
        $dos = Restaurante::factory()->soloMenu()->create(['nombre' => 'Alfa Cafe']);
        $usuario = $this->miembro($uno);
        $dos->usuarios()->attach($usuario, ['rol' => 'propietario']);

        $this->actingAs($usuario)->get('/admin')
            ->assertForbidden()
            ->assertSeeInOrder(['Alfa Cafe', 'Zeta Tacos'])
            ->assertSee(__('panel.plan_sin_panel.varios'));
    }

    public function test_una_membresia_inactiva_no_cuenta_para_la_pantalla(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create(['nombre' => 'Restaurante Dado de Baja']);
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario, ['rol' => 'propietario', 'activo' => false]);

        $this->actingAs($usuario)->get('/admin')
            ->assertForbidden()
            ->assertDontSee(__('panel.plan_sin_panel.titulo'))
            ->assertDontSee('Restaurante Dado de Baja');
    }

    public function test_quien_tiene_un_restaurante_con_acceso_y_otro_solo_menu_entra_sin_toparse_con_la_pantalla(): void
    {
        $completo = Restaurante::factory()->create(['nombre' => 'Restaurante Con Acceso']);
        $soloMenu = Restaurante::factory()->soloMenu()->create(['nombre' => 'Restaurante Sin Panel']);
        $usuario = $this->miembro($completo);
        $soloMenu->usuarios()->attach($usuario, ['rol' => 'propietario']);

        $this->assertFalse($usuario->debeVerElAvisoDePlanSinPanel());

        $admin = $this->actingAs($usuario)->get('/admin');
        $admin->assertRedirect(Filament::getPanel('admin')->getUrl($completo));
        $this->assertStringNotContainsString(__('panel.plan_sin_panel.titulo'), (string) $admin->getContent());

        $this->actingAs($usuario)->get('/admin/'.$completo->slug)
            ->assertOk()
            ->assertDontSee(__('panel.plan_sin_panel.titulo'));

        $this->actingAs($usuario)->get('/panel')
            ->assertOk()
            ->assertDontSee(__('panel.plan_sin_panel.titulo'));
    }

    public function test_el_equipo_de_sitio_y_menu_y_de_completo_no_ve_la_pantalla(): void
    {
        foreach ([Restaurante::factory()->sitioMenu()->create(), Restaurante::factory()->create()] as $restaurante) {
            $usuario = $this->miembro($restaurante);

            $this->assertFalse($usuario->debeVerElAvisoDePlanSinPanel());
            $this->actingAs($usuario)->get('/panel')->assertOk()->assertDontSee(__('panel.plan_sin_panel.titulo'));
            $this->actingAs($usuario)->get('/admin/'.$restaurante->slug)->assertDontSee(__('panel.plan_sin_panel.titulo'), false);
        }
    }

    #[DataProvider('rutasDeEntrada')]
    public function test_el_superadmin_nunca_ve_la_pantalla(string $ruta): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $superadmin = User::factory()->superadmin()->create();
        $restaurante->usuarios()->attach($superadmin, ['rol' => 'propietario']);

        $this->assertFalse($superadmin->debeVerElAvisoDePlanSinPanel());

        $respuesta = $this->actingAs($superadmin)->get($ruta);

        $this->assertNotSame(403, $respuesta->getStatusCode());
        $this->assertStringNotContainsString(__('panel.plan_sin_panel.titulo'), (string) $respuesta->getContent());
    }

    public function test_cuentas_inactivas_y_sin_restaurantes_siguen_con_el_403_de_siempre(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $inactivo = User::factory()->create(['activo' => false]);
        $restaurante->usuarios()->attach($inactivo, ['rol' => 'propietario']);
        $huerfano = User::factory()->create();

        foreach ([$inactivo, $huerfano] as $usuario) {
            $this->assertFalse($usuario->debeVerElAvisoDePlanSinPanel());

            foreach (['/admin', '/panel'] as $ruta) {
                $respuesta = $this->actingAs($usuario)->get($ruta);

                if ($ruta === '/admin') {
                    $respuesta->assertForbidden();
                }
                $this->assertStringNotContainsString(__('panel.plan_sin_panel.titulo'), (string) $respuesta->getContent());
            }
        }
    }

    public function test_un_invitado_sigue_yendo_al_inicio_de_sesion(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/panel')->assertRedirect('/admin/login');
    }

    public function test_desde_la_pantalla_se_puede_cerrar_sesion(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $propietario = $this->miembro($restaurante);

        $html = $this->actingAs($propietario)->get('/admin')->getContent();
        $this->assertStringContainsString('action="'.Filament::getPanel('admin')->getLogoutUrl().'"', $html);

        $this->actingAs($propietario)->post(Filament::getPanel('admin')->getLogoutUrl())->assertRedirect();

        $this->assertGuest();
    }

    public function test_el_propietario_de_solo_menu_puede_iniciar_sesion_y_llega_a_la_pantalla(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $propietario = $this->miembro($restaurante);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => $propietario->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($propietario);
        $this->get('/admin')->assertForbidden()->assertSee(__('panel.plan_sin_panel.titulo'));
    }

    public function test_la_clave_incorrecta_sigue_sin_dejar_entrar_a_nadie(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $propietario = $this->miembro($restaurante);
        $sinRestaurantes = User::factory()->create();
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => $propietario->email, 'password' => 'incorrecta'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        Livewire::test(Login::class)
            ->fillForm(['email' => $sinRestaurantes->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_al_bajar_el_plan_la_pantalla_aparece_en_la_siguiente_peticion_y_al_subirlo_desaparece(): void
    {
        $restaurante = Restaurante::factory()->create();
        $propietario = $this->miembro($restaurante);
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($propietario)->get('/panel')->assertOk();

        $restaurante->cambiarPlan(PlanRestaurante::SoloMenu, $superadmin);
        $this->actingAs($propietario->fresh())->get('/panel')->assertForbidden()->assertSee(__('panel.plan_sin_panel.titulo'));

        $restaurante->cambiarPlan(PlanRestaurante::Completo, $superadmin);
        $this->actingAs($propietario->fresh())->get('/panel')->assertOk()->assertDontSee(__('panel.plan_sin_panel.titulo'));
    }

    public function test_la_pantalla_esta_pensada_para_celular_y_sin_emojis(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();

        $html = $this->actingAs($this->miembro($restaurante))->get('/admin')->getContent();

        $this->assertStringContainsString('<html lang="es-MX">', $html);
        $this->assertStringContainsString('name="viewport" content="width=device-width,initial-scale=1"', $html);
        $this->assertStringContainsString('min-height: 48px', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
        $this->assertSame(0, preg_match('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE0F}]/u', $html));
    }
}
