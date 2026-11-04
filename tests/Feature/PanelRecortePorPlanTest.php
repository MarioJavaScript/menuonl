<?php

namespace Tests\Feature;

use App\Filament\Pages\AjustesDeReservasYPedidos;
use App\Filament\Pages\Apariencia;
use App\Filament\Pages\FotosDelRestaurante;
use App\Filament\Pages\Recomendados;
use App\Filament\Pages\Reservas;
use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\CodigosQr\CodigoQrResource;
use App\Filament\Resources\Contactos\Pages\ListContactos;
use App\Filament\Resources\Horarios\HorarioResource;
use App\Filament\Resources\Invitaciones\InvitacionResource;
use App\Filament\Resources\Productos\ProductoResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\ResumenRestauranteWidget;
use App\Models\Categoria;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\EscaneoDiario;
use App\Models\GaleriaImagen;
use App\Models\HistorialCambio;
use App\Models\Horario;
use App\Models\Invitacion;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\ProgresoDeConfiguracion;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Lo que cada plan deja en el panel: Aura (sitio_menu) incluye todo; Lite (solo_menu) incluye el panel completo del menú
 * (apariencia, menú, datos, contactos, horario, QR, equipo y métricas) pero no el sitio, las reservas ni las fotos del
 * sitio. El techo se aplica en el servidor (políticas, páginas y guardado), no solo ocultando. El superadmin edita por
 * encima del techo, pero un plan sin reservas no tiene sección de reservas para nadie.
 */
class PanelRecortePorPlanTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    /**
     * @return array{0: Restaurante, 1: User}
     */
    private function entrar(string $plan, string $rol = 'propietario'): array
    {
        $restaurante = $plan === 'solo_menu' ? Restaurante::factory()->soloMenu()->create() : Restaurante::factory()->sitioMenu()->create();
        $usuario = $this->crearUsuario($rol, $restaurante);
        $this->entrarAlPanel($usuario, $restaurante);

        return [$restaurante, $usuario];
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function recursosDelMenu(): array
    {
        return [
            'platillos' => [ProductoResource::class],
            'categorías' => [CategoriaResource::class],
            'códigos QR' => [CodigoQrResource::class],
            'equipo' => [UserResource::class],
            'invitaciones' => [InvitacionResource::class],
            'horario' => [HorarioResource::class],
        ];
    }

    /**
     * @param  class-string  $recurso
     */
    #[DataProvider('recursosDelMenu')]
    public function test_el_propietario_abre_las_pantallas_del_menu_en_aura_y_en_lite(string $recurso): void
    {
        foreach (['sitio_menu', 'solo_menu'] as $plan) {
            $this->entrar($plan);
            $this->assertTrue($recurso::canViewAny(), "propietario en {$plan}");

            $this->entrar($plan, 'superadmin');
            $this->assertTrue($recurso::canViewAny(), "superadmin en {$plan}");
        }
    }

    public function test_el_propietario_de_lite_abre_apariencia_datos_recomendados_y_ajustes_de_pedidos(): void
    {
        [$restaurante] = $this->entrar('solo_menu');

        $this->assertTrue(Apariencia::canAccess());
        $this->assertTrue(Recomendados::canAccess());
        $this->assertTrue(EditRestauranteProfile::canView($restaurante));
        $this->assertTrue(AjustesDeReservasYPedidos::canAccess());
    }

    public function test_el_propietario_de_lite_no_abre_reservas_ni_fotos_del_sitio(): void
    {
        $this->entrar('solo_menu');

        $this->assertFalse(Reservas::canAccess());
        $this->assertFalse(FotosDelRestaurante::canAccess());

        foreach ([Reservas::getUrl(), FotosDelRestaurante::getUrl()] as $url) {
            $this->assertContains($this->get($url)->getStatusCode(), [403, 404], $url);
        }
    }

    public function test_el_propietario_de_aura_abre_todo_incluidas_reservas_y_fotos(): void
    {
        [$restaurante] = $this->entrar('sitio_menu');

        $this->assertTrue(Reservas::canAccess());
        $this->assertTrue(FotosDelRestaurante::canAccess());
        $this->assertTrue(Apariencia::canAccess());
        $this->assertTrue(Recomendados::canAccess());
        $this->assertTrue(EditRestauranteProfile::canView($restaurante));
        $this->assertTrue(AjustesDeReservasYPedidos::canAccess());
    }

    public function test_el_superadmin_abre_fotos_en_lite_con_el_aviso_de_que_no_se_muestran_pero_no_reservas(): void
    {
        $this->entrar('solo_menu', 'superadmin');

        $this->assertTrue(FotosDelRestaurante::canAccess());
        $this->assertFalse(Reservas::canAccess());

        Livewire::test(FotosDelRestaurante::class)
            ->assertSee(__('panel.fotos.sin_sitio.titulo'))
            ->assertSee(__('panel.fotos.sin_sitio.descripcion', ['plan' => 'Lite']))
            ->assertDontSee(__('panel.fotos.sin_galeria.titulo'));
    }

    public function test_las_politicas_de_lite_dejan_todo_lo_del_menu_y_cierran_reservas_y_galeria(): void
    {
        [$restaurante, $propietario] = $this->entrar('solo_menu');
        $superadmin = User::factory()->superadmin()->create();

        foreach ([Producto::class, Categoria::class, CodigoQr::class, Invitacion::class, User::class, HistorialCambio::class, Horario::class, Contacto::class, EscaneoDiario::class] as $modelo) {
            $this->assertTrue($propietario->can('viewAny', $modelo), "propietario: {$modelo}");
        }

        foreach ([Reserva::class, GaleriaImagen::class] as $modelo) {
            $this->assertFalse($propietario->can('viewAny', $modelo), "propietario: {$modelo}");
            $this->assertTrue($superadmin->can('viewAny', $modelo), "superadmin: {$modelo}");
        }

        $this->assertTrue($propietario->can('update', $restaurante));
    }

    public function test_un_editor_de_lite_tiene_lo_de_su_rol_dentro_del_plan(): void
    {
        $this->entrar('solo_menu', 'editor');

        $this->assertTrue(ProductoResource::canViewAny());
        $this->assertTrue(CodigoQrResource::canViewAny());
        $this->assertFalse(Reservas::canAccess());
        $this->assertFalse(UserResource::canViewAny());
    }

    public function test_el_menu_lateral_del_propietario_de_lite(): void
    {
        $this->entrar('solo_menu');

        $etiquetas = $this->etiquetasDelMenu();

        foreach ([__('panel.pedidos.nav'), __('panel.apariencia.nav'), __('panel.datos.nav'), __('panel.recomendados.nav'), 'Platillos', 'Categorías', 'Horario', 'Contactos'] as $esperada) {
            $this->assertContains($esperada, $etiquetas);
        }

        foreach ([__('panel.reservas.nav'), __('panel.fotos.nav')] as $oculta) {
            $this->assertNotContains($oculta, $etiquetas);
        }
    }

    public function test_el_menu_lateral_del_propietario_de_aura_incluye_reservas_y_fotos(): void
    {
        $this->entrar('sitio_menu');

        $etiquetas = $this->etiquetasDelMenu();

        foreach ([__('panel.reservas.nav'), __('panel.fotos.nav'), __('panel.pedidos.nav'), __('panel.apariencia.nav'), 'Platillos'] as $esperada) {
            $this->assertContains($esperada, $etiquetas);
        }
    }

    /**
     * @return list<string>
     */
    private function etiquetasDelMenu(): array
    {
        return collect(Filament::getNavigation())
            ->flatMap(fn ($grupo) => $grupo->getItems())
            ->map(fn ($item): string => (string) $item->getLabel())
            ->values()
            ->all();
    }

    public function test_lite_configura_sus_pedidos_por_whatsapp_completos(): void
    {
        [$restaurante] = $this->entrar('solo_menu');
        $restaurante->update(['pedidos_activos' => false, 'whatsapp_pedidos' => null]);

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->assertFormFieldVisible('pedidos_activos')
            ->assertFormFieldVisible('whatsapp_pedidos')
            ->assertFormFieldVisible('pedidos_domicilio')
            ->assertFormFieldVisible('pedidos_recoger')
            ->assertFormFieldVisible('pedidos_mensaje')
            ->assertFormFieldHidden('reservas_activas')
            ->assertSee(__('panel.pedidos.subtitulo_sin_reservas'))
            ->fillForm([
                'pedidos_activos' => true,
                'whatsapp_pedidos' => '5215512345678',
                'pedidos_domicilio' => false,
                'pedidos_recoger' => true,
                'pedidos_mensaje' => '  Te avisamos cuando esté listo.  ',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $restaurante->refresh();
        $this->assertTrue($restaurante->pedidos_activos);
        $this->assertSame('5215512345678', $restaurante->whatsapp_pedidos);
        $this->assertFalse($restaurante->pedidos_domicilio);
        $this->assertTrue($restaurante->pedidos_recoger);
        $this->assertSame('Te avisamos cuando esté listo.', $restaurante->pedidos_mensaje);
        $this->assertSame(['recoger'], $restaurante->modalidadesDePedido());
    }

    public function test_lite_descarta_los_campos_de_reservas_aunque_lleguen_en_la_peticion(): void
    {
        [$restaurante] = $this->entrar('solo_menu');
        $restaurante->update(['reservas_activas' => false, 'reservas_cupo' => 10, 'reservas_franja_min' => 60, 'sitio_plantilla' => 'b', 'nombre' => 'Fonda Lite']);

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->set('data.reservas_activas', true)
            ->set('data.reservas_cupo', 400)
            ->set('data.reservas_franja_min', 120)
            ->set('data.sitio_plantilla', 'e')
            ->set('data.nombre', 'Otro nombre')
            ->call('save')
            ->assertHasNoErrors();

        $restaurante->refresh();
        $this->assertFalse($restaurante->reservas_activas);
        $this->assertSame(10, $restaurante->reservas_cupo);
        $this->assertSame(60, $restaurante->reservas_franja_min);
        $this->assertSame('b', $restaurante->sitio_plantilla);
        $this->assertSame('Fonda Lite', $restaurante->nombre);
    }

    public function test_con_pedidos_activos_hay_que_dejar_al_menos_una_modalidad(): void
    {
        [$restaurante] = $this->entrar('solo_menu');
        $restaurante->update(['pedidos_activos' => true, 'whatsapp_pedidos' => '5215500000000', 'pedidos_domicilio' => true, 'pedidos_recoger' => true]);

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->fillForm(['pedidos_domicilio' => false, 'pedidos_recoger' => false])
            ->call('save')
            ->assertHasErrors(['data.pedidos_recoger']);

        $this->assertTrue($restaurante->fresh()->pedidos_domicilio);

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->fillForm(['pedidos_activos' => false, 'pedidos_domicilio' => false, 'pedidos_recoger' => false])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($restaurante->fresh()->pedidos_activos);
    }

    public function test_el_numero_es_obligatorio_si_se_activan_los_pedidos_y_el_mensaje_tiene_largo_maximo(): void
    {
        $this->entrar('solo_menu');

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->fillForm(['pedidos_activos' => true, 'whatsapp_pedidos' => ''])
            ->call('save')
            ->assertHasErrors(['data.whatsapp_pedidos' => 'required']);

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->fillForm(['pedidos_activos' => false, 'pedidos_mensaje' => str_repeat('x', 201)])
            ->call('save')
            ->assertHasErrors(['data.pedidos_mensaje' => 'max']);
    }

    public function test_aura_configura_pedidos_y_reservas(): void
    {
        [$restaurante] = $this->entrar('sitio_menu');

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->assertFormFieldVisible('pedidos_domicilio')
            ->assertFormFieldVisible('reservas_activas')
            ->assertSee(__('panel.pedidos.subtitulo'))
            ->fillForm([
                'pedidos_activos' => true,
                'whatsapp_pedidos' => '5215512345678',
                'pedidos_domicilio' => true,
                'pedidos_recoger' => false,
                'reservas_activas' => true,
                'reservas_cupo' => 22,
                'reservas_franja_min' => 90,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $restaurante->refresh();
        $this->assertTrue($restaurante->pedidos_activos);
        $this->assertSame(['domicilio'], $restaurante->modalidadesDePedido());
        $this->assertTrue($restaurante->reservas_activas);
        $this->assertSame(22, $restaurante->reservas_cupo);
        $this->assertSame(90, $restaurante->reservas_franja_min);
    }

    public function test_las_reservas_no_se_configuran_en_lite_ni_para_el_superadmin(): void
    {
        $this->entrar('solo_menu', 'superadmin');

        Livewire::test(AjustesDeReservasYPedidos::class)
            ->assertFormFieldVisible('pedidos_activos')
            ->assertFormFieldVisible('pedidos_domicilio')
            ->assertFormFieldHidden('reservas_activas');
    }

    public function test_contactos_en_lite_lista_y_deja_cambiar_todos_los_tipos(): void
    {
        [$restaurante, $usuario] = $this->entrar('solo_menu');
        $telefono = Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'telefono']);
        $correo = Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'email', 'valor' => 'hola@ejemplo.com']);
        $instagram = Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'instagram', 'valor' => 'https://instagram.com/ejemplo']);

        Livewire::test(ListContactos::class)->assertCanSeeTableRecords([$telefono, $correo, $instagram]);

        $this->assertTrue($usuario->can('update', $correo));
        $this->assertTrue($usuario->can('delete', $instagram));
    }

    public function test_ver_mi_sitio_no_aparece_en_lite_y_ver_mi_menu_si(): void
    {
        foreach ([['solo_menu', false], ['sitio_menu', true]] as [$plan, $verSitio]) {
            [$restaurante] = $this->entrar($plan);

            $datos = (fn (): array => $this->getViewData())->call(new ResumenRestauranteWidget);

            $this->assertSame($verSitio, $datos['sitioUrl'] !== null, $plan);
            $this->assertSame(route('menu.publico', ['slug' => $restaurante->slug]), $datos['menuUrl']);

            $html = view('filament.hooks.ver-menu-publico', [
                'url' => $datos['menuUrl'],
                'sitioUrl' => $datos['sitioUrl'],
            ])->render();

            $this->assertStringContainsString(__('panel.enlaces.ver_menu'), $html);
            $this->assertSame($verSitio, str_contains($html, __('panel.enlaces.ver_sitio')), $plan);
        }
    }

    public function test_apariencia_de_lite_explica_que_el_diseno_cambia_el_menu(): void
    {
        $this->entrar('solo_menu');
        Livewire::test(Apariencia::class)->assertSee(__('panel.apariencia.diseno.sin_sitio'));

        $this->entrar('sitio_menu');
        Livewire::test(Apariencia::class)->assertDontSee(__('panel.apariencia.diseno.sin_sitio'));
    }

    public function test_el_progreso_de_lite_pide_lo_del_menu_pero_no_el_mapa_del_sitio(): void
    {
        [$restaurante, $propietario] = $this->entrar('solo_menu');
        $restaurante->update(['pedidos_activos' => true]);

        $claves = collect(app(ProgresoDeConfiguracion::class)->para($restaurante, $propietario)['pasos'])->pluck('clave')->all();

        $this->assertEqualsCanonicalizing(['categoria', 'platillo', 'horario', 'contacto', 'descripcion', 'logotipo', 'portada', 'whatsapp'], $claves);
    }

    public function test_el_progreso_de_aura_conserva_todos_los_pasos_y_el_superadmin_ve_los_mismos(): void
    {
        [$restaurante, $propietario] = $this->entrar('sitio_menu');
        $restaurante->update(['pedidos_activos' => true]);
        $todos = collect(app(ProgresoDeConfiguracion::class)->para($restaurante, $propietario)['pasos'])->pluck('clave')->all();

        $this->assertEqualsCanonicalizing(['categoria', 'platillo', 'horario', 'contacto', 'descripcion', 'mapa', 'logotipo', 'portada', 'whatsapp'], $todos);

        [$restaurante, $superadmin] = $this->entrar('sitio_menu', 'superadmin');
        $restaurante->update(['pedidos_activos' => true]);
        $delSuperadmin = collect(app(ProgresoDeConfiguracion::class)->para($restaurante, $superadmin)['pasos'])->pluck('clave')->all();

        $this->assertEqualsCanonicalizing($todos, $delSuperadmin);
    }

    public function test_el_aviso_de_reservas_sin_horario_respeta_el_plan(): void
    {
        foreach ([['sitio_menu', 1], ['solo_menu', 0]] as [$plan, $avisos]) {
            [$restaurante, $usuario] = $this->entrar($plan);
            $restaurante->update(['reservas_activas' => true]);

            $this->assertCount($avisos, app(ProgresoDeConfiguracion::class)->para($restaurante, $usuario)['avisos'], $plan);
        }
    }
}
