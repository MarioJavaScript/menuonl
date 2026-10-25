<?php

namespace Tests\Feature;

use App\Filament\Pages\Apariencia;
use App\Filament\Pages\FotosDelRestaurante;
use App\Filament\Pages\PedidosYReservas;
use App\Filament\Pages\Recomendados;
use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\CodigosQr\CodigoQrResource;
use App\Filament\Resources\Contactos\Pages\CreateContacto;
use App\Filament\Resources\Contactos\Pages\EditContacto;
use App\Filament\Resources\Contactos\Pages\ListContactos;
use App\Filament\Resources\Horarios\HorarioResource;
use App\Filament\Resources\Invitaciones\InvitacionResource;
use App\Filament\Resources\Productos\ProductoResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\ResumenRestauranteWidget;
use App\Models\Categoria;
use App\Models\CodigoQr;
use App\Models\Contacto;
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
 * Paso 4 de docs/planes.md: en Sitio y menú el propietario solo toca el WhatsApp de pedidos, los teléfonos de contacto
 * y el horario; lo demás está cerrado en el servidor. El superadmin conserva todo en cualquier plan.
 */
class PanelRecortePorPlanTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    /**
     * @return array{0: Restaurante, 1: User}
     */
    private function entrar(string $plan, string $rol = 'propietario'): array
    {
        $restaurante = match ($plan) {
            'sitio_menu' => Restaurante::factory()->sitioMenu()->create(),
            'solo_menu' => Restaurante::factory()->soloMenu()->create(),
            default => Restaurante::factory()->create(),
        };
        $usuario = $this->crearUsuario($rol, $restaurante);
        $this->entrarAlPanel($usuario, $restaurante);

        return [$restaurante, $usuario];
    }

    /**
     * @return array<string, array{0: class-string, 1: bool}>
     */
    public static function pantallasEnSitioMenu(): array
    {
        return [
            'platillos' => [ProductoResource::class, false],
            'categorías' => [CategoriaResource::class, false],
            'códigos QR' => [CodigoQrResource::class, false],
            'equipo' => [UserResource::class, false],
            'invitaciones' => [InvitacionResource::class, false],
            'horario' => [HorarioResource::class, true],
        ];
    }

    /**
     * @param  class-string  $recurso
     */
    #[DataProvider('pantallasEnSitioMenu')]
    public function test_el_propietario_de_sitio_y_menu_solo_abre_las_pantallas_que_el_plan_deja(string $recurso, bool $abierta): void
    {
        $this->entrar('sitio_menu');

        $this->assertSame($abierta, $recurso::canViewAny());
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function pantallas(): array
    {
        return array_map(fn (array $caso): array => [$caso[0]], self::pantallasEnSitioMenu());
    }

    /**
     * @param  class-string  $recurso
     */
    #[DataProvider('pantallas')]
    public function test_el_propietario_de_plan_completo_y_el_superadmin_abren_todo(string $recurso): void
    {
        $this->entrar('completo');
        $this->assertTrue($recurso::canViewAny());

        foreach (['sitio_menu', 'solo_menu'] as $plan) {
            $this->entrar($plan, 'superadmin');
            $this->assertTrue($recurso::canViewAny(), "superadmin en {$plan}");
        }
    }

    public function test_las_paginas_de_diseno_y_datos_estan_cerradas_para_el_propietario_de_sitio_y_menu(): void
    {
        [$restaurante] = $this->entrar('sitio_menu');

        $this->assertFalse(Apariencia::canAccess());
        $this->assertFalse(FotosDelRestaurante::canAccess());
        $this->assertFalse(Recomendados::canAccess());
        $this->assertFalse(EditRestauranteProfile::canView($restaurante));
        $this->assertTrue(PedidosYReservas::canAccess());
    }

    public function test_el_superadmin_y_el_propietario_completo_abren_las_paginas_de_diseno_y_datos(): void
    {
        [$restaurante] = $this->entrar('completo');
        $this->assertTrue(Apariencia::canAccess());
        $this->assertTrue(FotosDelRestaurante::canAccess());
        $this->assertTrue(Recomendados::canAccess());
        $this->assertTrue(EditRestauranteProfile::canView($restaurante));

        foreach (['sitio_menu', 'solo_menu'] as $plan) {
            [$restaurante] = $this->entrar($plan, 'superadmin');
            $this->assertTrue(Apariencia::canAccess(), $plan);
            $this->assertTrue(FotosDelRestaurante::canAccess(), $plan);
            $this->assertTrue(EditRestauranteProfile::canView($restaurante), $plan);
            $this->assertTrue(PedidosYReservas::canAccess(), $plan);
        }
    }

    public function test_las_direcciones_de_las_pantallas_cerradas_responden_con_error_desde_el_servidor(): void
    {
        [$restaurante] = $this->entrar('sitio_menu');

        foreach ([Apariencia::getUrl(), FotosDelRestaurante::getUrl(), Recomendados::getUrl(), EditRestauranteProfile::getUrl(), ProductoResource::getUrl('index'), CategoriaResource::getUrl('index'), CodigoQrResource::getUrl('index'), UserResource::getUrl('index')] as $url) {
            $this->assertContains($this->get($url)->getStatusCode(), [403, 404], $url);
        }

        $codigo = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id]);
        $this->get(route('codigos-qr.descargar', ['codigoQr' => $codigo, 'formato' => 'png']))->assertForbidden();
    }

    public function test_un_editor_no_gana_acceso_por_el_rol_si_el_plan_no_lo_incluye(): void
    {
        $this->entrar('sitio_menu', 'editor');

        $this->assertFalse(ProductoResource::canViewAny());
        $this->assertFalse(CodigoQrResource::canViewAny());
        $this->assertTrue(HorarioResource::canViewAny());
    }

    public function test_las_politicas_del_plan_solo_recortan_a_quien_no_es_superadmin(): void
    {
        [$restaurante, $propietario] = $this->entrar('sitio_menu');
        $superadmin = User::factory()->superadmin()->create();

        foreach ([Producto::class, Categoria::class, GaleriaImagen::class, CodigoQr::class, Invitacion::class, User::class, HistorialCambio::class] as $modelo) {
            $this->assertFalse($propietario->can('viewAny', $modelo), "propietario: {$modelo}");
            $this->assertTrue($superadmin->can('viewAny', $modelo), "superadmin: {$modelo}");
        }

        foreach ([Horario::class, Contacto::class, Reserva::class] as $modelo) {
            $this->assertTrue($propietario->can('viewAny', $modelo), "propietario: {$modelo}");
        }

        $this->assertTrue($propietario->can('update', $restaurante));
    }

    public function test_en_solo_menu_ni_las_reservas_ni_el_horario_ni_los_contactos_quedan_para_el_equipo(): void
    {
        [, $propietario] = $this->entrar('solo_menu');

        foreach ([Reserva::class, Horario::class, Contacto::class, Producto::class] as $modelo) {
            $this->assertFalse($propietario->can('viewAny', $modelo), $modelo);
        }
    }

    public function test_el_menu_lateral_del_propietario_de_sitio_y_menu_solo_lista_lo_permitido(): void
    {
        $this->entrar('sitio_menu');

        $etiquetas = $this->etiquetasDelMenu();

        foreach ([__('panel.pedidos.nav'), 'Horario', 'Contactos', __('panel.enlaces.servicio')] as $esperada) {
            $this->assertContains($esperada, $etiquetas);
        }

        foreach ([__('panel.apariencia.nav'), __('panel.datos.nav'), __('panel.fotos.nav'), __('panel.recomendados.nav'), 'Platillos', 'Categorías'] as $oculta) {
            $this->assertNotContains($oculta, $etiquetas);
        }
    }

    public function test_el_superadmin_ve_todo_el_menu_lateral_en_sitio_y_menu(): void
    {
        $this->entrar('sitio_menu', 'superadmin');

        $etiquetas = $this->etiquetasDelMenu();

        foreach ([__('panel.apariencia.nav'), __('panel.datos.nav'), __('panel.fotos.nav'), __('panel.recomendados.nav'), 'Platillos', 'Categorías'] as $esperada) {
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

    public function test_pedidos_y_reservas_en_sitio_y_menu_solo_cambia_el_numero_de_whatsapp(): void
    {
        [$restaurante] = $this->entrar('sitio_menu');
        $restaurante->update(['pedidos_activos' => false, 'whatsapp_pedidos' => '5215500000000', 'reservas_activas' => false, 'reservas_cupo' => 10, 'reservas_franja_min' => 60]);

        Livewire::test(PedidosYReservas::class)
            ->assertFormFieldExists('whatsapp_pedidos')
            ->assertFormFieldHidden('pedidos_activos')
            ->assertFormFieldHidden('reservas_activas')
            ->assertFormFieldHidden('reservas_cupo')
            ->set('data.whatsapp_pedidos', '5215512345678')
            ->set('data.pedidos_activos', true)
            ->set('data.reservas_activas', true)
            ->set('data.reservas_cupo', 400)
            ->set('data.reservas_franja_min', 120)
            ->call('save')
            ->assertHasNoErrors();

        $restaurante->refresh();
        $this->assertSame('5215512345678', $restaurante->whatsapp_pedidos);
        $this->assertFalse($restaurante->pedidos_activos);
        $this->assertFalse($restaurante->reservas_activas);
        $this->assertSame(10, $restaurante->reservas_cupo);
        $this->assertSame(60, $restaurante->reservas_franja_min);
    }

    public function test_el_numero_sigue_siendo_obligatorio_si_el_superadmin_dejo_los_pedidos_activos(): void
    {
        [$restaurante] = $this->entrar('sitio_menu');
        $restaurante->update(['pedidos_activos' => true, 'whatsapp_pedidos' => '5215500000000']);

        Livewire::test(PedidosYReservas::class)
            ->set('data.whatsapp_pedidos', '')
            ->call('save')
            ->assertHasErrors(['data.whatsapp_pedidos' => 'required']);

        $this->assertSame('5215500000000', $restaurante->fresh()->whatsapp_pedidos);
    }

    public function test_el_superadmin_cambia_todo_en_pedidos_y_reservas_aunque_el_plan_sea_sitio_y_menu(): void
    {
        [$restaurante] = $this->entrar('sitio_menu', 'superadmin');

        Livewire::test(PedidosYReservas::class)
            ->assertFormFieldVisible('pedidos_activos')
            ->assertFormFieldVisible('reservas_activas')
            ->fillForm([
                'pedidos_activos' => true,
                'whatsapp_pedidos' => '5215512345678',
                'reservas_activas' => true,
                'reservas_cupo' => 22,
                'reservas_franja_min' => 90,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $restaurante->refresh();
        $this->assertTrue($restaurante->pedidos_activos);
        $this->assertTrue($restaurante->reservas_activas);
        $this->assertSame(22, $restaurante->reservas_cupo);
    }

    public function test_las_reservas_no_se_configuran_en_solo_menu_ni_para_el_superadmin(): void
    {
        $this->entrar('solo_menu', 'superadmin');

        Livewire::test(PedidosYReservas::class)
            ->assertFormFieldVisible('pedidos_activos')
            ->assertFormFieldHidden('reservas_activas');
    }

    public function test_contactos_en_sitio_y_menu_solo_lista_y_deja_cambiar_telefonos(): void
    {
        [$restaurante, $usuario] = $this->entrar('sitio_menu');
        $telefono = Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'telefono']);
        $whatsapp = Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'whatsapp', 'valor' => '+52 55 1111 2222']);
        $correo = Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'email', 'valor' => 'hola@ejemplo.com']);
        $instagram = Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'instagram', 'valor' => 'https://instagram.com/ejemplo']);

        Livewire::test(ListContactos::class)
            ->assertCanSeeTableRecords([$telefono, $whatsapp])
            ->assertCanNotSeeTableRecords([$correo, $instagram]);

        $this->assertTrue($usuario->can('update', $telefono));
        $this->assertTrue($usuario->can('delete', $whatsapp));
        $this->assertFalse($usuario->can('update', $correo));
        $this->assertFalse($usuario->can('delete', $instagram));
    }

    public function test_no_se_puede_abrir_un_contacto_de_otro_tipo_por_su_direccion(): void
    {
        [$restaurante] = $this->entrar('sitio_menu');
        $correo = Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'email', 'valor' => 'hola@ejemplo.com']);

        $this->get(EditContacto::getUrl(['record' => $correo]))->assertNotFound();
    }

    public function test_crear_un_contacto_en_sitio_y_menu_acepta_telefonos_y_rechaza_otros_tipos(): void
    {
        [$restaurante] = $this->entrar('sitio_menu');

        Livewire::test(CreateContacto::class)
            ->fillForm(['tipo' => 'email', 'valor' => 'hola@ejemplo.com', 'orden' => 0])
            ->call('create')
            ->assertHasFormErrors(['tipo']);

        Livewire::test(CreateContacto::class)
            ->fillForm(['tipo' => 'whatsapp', 'valor' => '+52 55 1234 5678', 'orden' => 0])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(0, Contacto::query()->where('restaurante_id', $restaurante->id)->where('tipo', 'email')->count());
        $this->assertSame(1, Contacto::query()->where('restaurante_id', $restaurante->id)->where('tipo', 'whatsapp')->count());
    }

    public function test_el_superadmin_y_el_plan_completo_ven_todos_los_tipos_de_contacto(): void
    {
        foreach ([['completo', 'propietario'], ['sitio_menu', 'superadmin'], ['solo_menu', 'superadmin']] as [$plan, $rol]) {
            [$restaurante, $usuario] = $this->entrar($plan, $rol);
            $correo = Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'email', 'valor' => 'hola@ejemplo.com']);

            Livewire::test(ListContactos::class)->assertCanSeeTableRecords([$correo]);
            $this->assertTrue($usuario->can('update', $correo), $plan);
        }
    }

    public function test_un_editor_de_sitio_y_menu_tampoco_cambia_contactos_de_otro_tipo(): void
    {
        [$restaurante, $editor] = $this->entrar('sitio_menu', 'editor');
        $correo = Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'email', 'valor' => 'hola@ejemplo.com']);

        $this->assertFalse($editor->can('update', $correo));
    }

    public function test_ver_mi_sitio_no_aparece_en_solo_menu_y_ver_mi_menu_si(): void
    {
        foreach ([['solo_menu', false], ['sitio_menu', true], ['completo', true]] as [$plan, $verSitio]) {
            [$restaurante] = $this->entrar($plan, 'superadmin');

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

    public function test_el_progreso_de_sitio_y_menu_solo_pide_lo_que_el_propietario_puede_hacer(): void
    {
        [$restaurante, $propietario] = $this->entrar('sitio_menu');
        $restaurante->update(['pedidos_activos' => true, 'whatsapp_pedidos' => null]);

        $claves = collect(app(ProgresoDeConfiguracion::class)->para($restaurante, $propietario)['pasos'])->pluck('clave')->all();

        $this->assertEqualsCanonicalizing(['horario', 'contacto', 'whatsapp'], $claves);
    }

    public function test_el_progreso_completo_conserva_todos_los_pasos_y_el_superadmin_los_ve_en_cualquier_plan(): void
    {
        [$restaurante, $propietario] = $this->entrar('completo');
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
        foreach ([['sitio_menu', 1], ['completo', 1], ['solo_menu', 0]] as [$plan, $avisos]) {
            [$restaurante, $usuario] = $this->entrar($plan, 'superadmin');
            $restaurante->update(['reservas_activas' => true]);

            $this->assertCount($avisos, app(ProgresoDeConfiguracion::class)->para($restaurante, $usuario)['avisos'], $plan);
        }
    }
}
