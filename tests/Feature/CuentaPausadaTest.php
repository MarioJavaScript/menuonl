<?php

namespace Tests\Feature;

use App\Enums\MotivoDePausa;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\Restaurantes\Pages\CreateRestaurante;
use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\InvitacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Aviso "fuera de servicio" para el equipo de un restaurante suspendido: neutro, con el contacto de soporte,
 * sin datos internos; más los extras de Plataforma (sin invitaciones, banner de superadmin).
 */
class CuentaPausadaTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const NUMERO = '5215512345678';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
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
            'sin dígitos' => ['no-es-un-numero'],
            'muy corto' => ['12345'],
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

    #[DataProvider('rolesDelEquipo')]
    public function test_cada_rol_del_equipo_ve_el_aviso_en_lugar_de_un_403_a_secas(string $rol): void
    {
        $restaurante = Restaurante::factory()->suspendido()->create(['nombre' => 'Taquería La Pausa']);
        $usuario = $this->miembro($restaurante, $rol);

        foreach (['/admin', '/admin/'.$restaurante->slug, '/admin/'.$restaurante->slug.'/categorias'] as $ruta) {
            $this->actingAs($usuario)->get($ruta)
                ->assertForbidden()
                ->assertSee(__('panel.cuenta_pausada.titulo'))
                ->assertSee('Taquería La Pausa')
                ->assertSee(__('panel.cuenta_pausada.motivos.'.MotivoDePausa::FueraDeServicio->value))
                ->assertSee(__('panel.cuenta_pausada.boton_whatsapp'))
                ->assertSee(e($this->enlaceEsperado()), false)
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_el_aviso_es_neutro_y_no_expone_datos_internos(): void
    {
        $restaurante = Restaurante::factory()->suspendido()->create([
            'nombre' => 'Cocina de Doña Lupe',
            'slug' => 'dona-lupe-secreta',
            'whatsapp_pedidos' => '5215599990000',
        ]);
        $propietario = $this->miembro($restaurante);

        $html = $this->actingAs($propietario)->get('/admin')->getContent();

        $this->assertStringContainsString('Cocina de Doña Lupe', $html);

        foreach (['dona-lupe-secreta', '5215599990000', 'solo_menu', 'sitio_menu', 'suspendido', (string) $propietario->email, 'MXN', 'America/Mexico_City'] as $dato) {
            $this->assertStringNotContainsString($dato, $html, $dato);
        }

        $texto = mb_strtolower(strip_tags($html));

        foreach (['pago', 'adeudo', 'factura', 'suspendid', 'cobro', 'plan '] as $palabra) {
            $this->assertStringNotContainsString($palabra, $texto, $palabra);
        }
    }

    public function test_el_numero_de_soporte_solo_va_dentro_del_enlace_de_whatsapp(): void
    {
        $restaurante = Restaurante::factory()->suspendido()->create();
        $html = $this->actingAs($this->miembro($restaurante))->get('/admin')->getContent();

        $sinEnlace = str_replace(e($this->enlaceEsperado()), '', $html);

        $this->assertStringNotContainsString(self::NUMERO, $sinEnlace);
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $html);
    }

    #[DataProvider('numerosSinSoporte')]
    public function test_sin_numero_valido_no_hay_boton_y_el_texto_manda_al_proveedor(?string $numero): void
    {
        config(['menuonl.soporte_whatsapp' => $numero]);
        $restaurante = Restaurante::factory()->suspendido()->create();

        $this->actingAs($this->miembro($restaurante))->get('/admin')
            ->assertForbidden()
            ->assertSee(__('panel.cuenta_pausada.sin_whatsapp'))
            ->assertDontSee(__('panel.cuenta_pausada.boton_whatsapp'))
            ->assertDontSee('wa.me');
    }

    public function test_el_nombre_del_restaurante_sale_escapado(): void
    {
        $restaurante = Restaurante::factory()->suspendido()->create(['nombre' => '<script>alert(1)</script> Grill']);

        $html = $this->actingAs($this->miembro($restaurante))->get('/admin')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString(e('<script>alert(1)</script> Grill'), $html);
    }

    public function test_varios_restaurantes_suspendidos_se_listan_por_nombre(): void
    {
        $uno = Restaurante::factory()->suspendido()->create(['nombre' => 'Zeta Tacos']);
        $dos = Restaurante::factory()->suspendido()->create(['nombre' => 'Alfa Cafe']);
        $usuario = $this->miembro($uno);
        $dos->usuarios()->attach($usuario, ['rol' => 'propietario']);

        $this->actingAs($usuario)->get('/admin')
            ->assertForbidden()
            ->assertSeeInOrder(['Alfa Cafe', 'Zeta Tacos'])
            ->assertSee(__('panel.cuenta_pausada.varios'));
    }

    public function test_con_un_restaurante_suspendido_y_otro_sin_panel_por_plan_gana_el_aviso_de_pausa(): void
    {
        $pausado = Restaurante::factory()->suspendido()->create();
        $soloMenu = Restaurante::factory()->soloMenu()->create();
        $usuario = $this->miembro($pausado);
        $soloMenu->usuarios()->attach($usuario, ['rol' => 'propietario']);

        $this->assertTrue($usuario->debeVerElAvisoDeCuentaPausada());

        $this->actingAs($usuario)->get('/admin')
            ->assertForbidden()
            ->assertSee(__('panel.cuenta_pausada.titulo'))
            ->assertDontSee(__('panel.plan_sin_panel.titulo'));
    }

    public function test_quien_tiene_un_restaurante_activo_entra_sin_ver_el_aviso(): void
    {
        $activo = Restaurante::factory()->create();
        $pausado = Restaurante::factory()->suspendido()->create();
        $usuario = $this->miembro($activo);
        $pausado->usuarios()->attach($usuario, ['rol' => 'propietario']);

        $this->assertFalse($usuario->debeVerElAvisoDeCuentaPausada());

        $this->actingAs($usuario)->get('/admin/'.$activo->slug.'/categorias')
            ->assertOk()
            ->assertDontSee(__('panel.cuenta_pausada.titulo'));
    }

    public function test_el_superadmin_nunca_ve_el_aviso(): void
    {
        $restaurante = Restaurante::factory()->suspendido()->create();
        $superadmin = User::factory()->superadmin()->create();
        $restaurante->usuarios()->attach($superadmin, ['rol' => 'propietario']);

        $this->assertFalse($superadmin->debeVerElAvisoDeCuentaPausada());
        $this->actingAs($superadmin)->get('/admin/'.$restaurante->slug.'/categorias')->assertOk();
    }

    public function test_una_membresia_inactiva_o_una_cuenta_inactiva_no_ven_el_aviso(): void
    {
        $restaurante = Restaurante::factory()->suspendido()->create(['nombre' => 'Restaurante Dado de Baja']);
        $sinMembresia = User::factory()->create();
        $restaurante->usuarios()->attach($sinMembresia, ['rol' => 'propietario', 'activo' => false]);
        $inactivo = $this->miembro($restaurante);
        $inactivo->update(['activo' => false]);

        foreach ([$sinMembresia, $inactivo] as $usuario) {
            $this->actingAs($usuario)->get('/admin')
                ->assertForbidden()
                ->assertDontSee(__('panel.cuenta_pausada.titulo'))
                ->assertDontSee('Restaurante Dado de Baja');
        }
    }

    public function test_al_reactivar_el_restaurante_el_equipo_vuelve_a_entrar(): void
    {
        $restaurante = Restaurante::factory()->suspendido()->create();
        $usuario = $this->miembro($restaurante);

        $this->actingAs($usuario)->get('/admin/'.$restaurante->slug.'/categorias')->assertForbidden();

        $restaurante->update(['estado' => 'activo']);

        $this->actingAs($usuario->fresh())->get('/admin/'.$restaurante->slug.'/categorias')->assertOk();
    }

    public function test_el_equipo_de_un_restaurante_suspendido_inicia_sesion_y_llega_al_aviso(): void
    {
        $restaurante = Restaurante::factory()->suspendido()->create(['nombre' => 'Fonda En Pausa']);
        $usuario = $this->miembro($restaurante);

        Livewire::test(Login::class)
            ->fillForm(['email' => $usuario->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($usuario);
        $this->get('/admin')->assertForbidden()->assertSee(__('panel.cuenta_pausada.titulo'))->assertSee('Fonda En Pausa');
    }

    public function test_cerrar_sesion_sigue_funcionando_desde_el_aviso(): void
    {
        $restaurante = Restaurante::factory()->suspendido()->create();
        $usuario = $this->miembro($restaurante);

        $this->actingAs($usuario)->post(route('filament.admin.auth.logout'))->assertRedirect();

        $this->assertGuest();
    }

    public function test_el_aviso_de_plan_sin_panel_sigue_igual_para_un_restaurante_activo(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();

        $this->actingAs($this->miembro($restaurante))->get('/admin')
            ->assertForbidden()
            ->assertDontSee(__('panel.cuenta_pausada.titulo'))
            ->assertSee(__('panel.plan_sin_panel.titulo'));
    }

    public function test_plataforma_no_invita_ni_reenvia_en_un_restaurante_suspendido(): void
    {
        Mail::fake();
        $restaurante = Restaurante::factory()->create();
        $superadmin = User::factory()->superadmin()->create();
        $servicio = app(InvitacionService::class);
        $pendiente = $servicio->invitar($restaurante, 'dueno@ejemplo.com', 'propietario', $superadmin);

        $restaurante->update(['estado' => 'suspendido']);

        foreach ([
            fn () => $servicio->invitar($restaurante->fresh(), 'otro@ejemplo.com', 'propietario', $superadmin),
            fn () => $servicio->reenviar($pendiente, $superadmin),
        ] as $intento) {
            try {
                $intento();
                $this->fail('Debió rechazar la invitación.');
            } catch (ValidationException $e) {
                $this->assertSame([__('panel.cuenta_pausada.invitar_bloqueado')], $e->errors()['restaurante']);
            }
        }

        $this->assertSame(0, Invitacion::withoutGlobalScopes()->where('email', 'otro@ejemplo.com')->count());
    }

    public function test_plataforma_crea_un_restaurante_fuera_de_servicio_sin_enviar_invitacion(): void
    {
        Mail::fake();
        $this->entrarAlPanel(User::factory()->superadmin()->create(), Restaurante::factory()->create());

        Livewire::test(CreateRestaurante::class)
            ->fillForm([
                'nombre' => 'Taquería Pausada',
                'slug' => 'taqueria-pausada',
                'moneda' => 'MXN',
                'idioma_default' => 'es',
                'zona_horaria' => 'America/Mexico_City',
                'estado' => 'suspendido',
                'email_propietario' => 'dueno@ejemplo.com',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified(__('panel.cuenta_pausada.invitacion_omitida'));

        $nuevo = Restaurante::where('slug', 'taqueria-pausada')->firstOrFail();
        $this->assertSame(0, Invitacion::withoutGlobalScopes()->where('restaurante_id', $nuevo->id)->count());
    }

    public function test_el_banner_de_superadmin_avisa_que_el_restaurante_esta_fuera_de_servicio(): void
    {
        $pausado = Restaurante::factory()->suspendido()->create();
        $activo = Restaurante::factory()->create();
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->get('/admin/'.$pausado->slug.'/categorias')
            ->assertOk()
            ->assertSee('data-fuera-de-servicio', false)
            ->assertSee(__('panel.banner_superadmin.fuera_de_servicio'));

        $this->actingAs($superadmin)->get('/admin/'.$activo->slug.'/categorias')
            ->assertOk()
            ->assertDontSee('data-fuera-de-servicio', false);
    }
}
