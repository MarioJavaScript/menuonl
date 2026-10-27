<?php

namespace Tests\Feature;

use App\Filament\Pages\AjustesDeReservasYPedidos;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\Reservas;
use App\Models\Restaurante;
use App\Services\BarraInferiorDelPanel;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Barra inferior del panel en el celular (docs/integracion-dashboard.md, PR 2): hasta 4 accesos según lo que cada rol y plan
 * puede abrir, "Más" para el menú completo, y nunca un acceso que la navegación del panel no ofrezca a esa persona.
 */
class BarraInferiorDelPanelTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    private function entrar(string $plan, string $rol): Restaurante
    {
        $restaurante = match ($plan) {
            'sitio_menu' => Restaurante::factory()->sitioMenu()->create(),
            default => Restaurante::factory()->create(),
        };
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        return $restaurante;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function accesosPorRolYPlan(): array
    {
        return [
            'propietario completo' => ['completo', 'propietario', ['inicio', 'platillos', 'reservas', 'apariencia']],
            'superadmin completo' => ['completo', 'superadmin', ['inicio', 'platillos', 'reservas', 'apariencia']],
            'propietario sitio y menú' => ['sitio_menu', 'propietario', ['inicio', 'reservas', 'horario', 'contactos']],
            'superadmin sitio y menú' => ['sitio_menu', 'superadmin', ['inicio', 'platillos', 'reservas', 'apariencia']],
            'editor' => ['completo', 'editor', ['inicio', 'platillos', 'reservas', 'categorias']],
            'gerente' => ['completo', 'gerente', ['inicio', 'platillos', 'reservas']],
            'recepción' => ['completo', 'recepcion', ['inicio', 'reservas']],
            'cocina' => ['completo', 'cocina', ['inicio', 'pedidos']],
        ];
    }

    /**
     * @param  list<string>  $esperados
     */
    #[DataProvider('accesosPorRolYPlan')]
    public function test_cada_rol_y_plan_ve_los_accesos_que_le_corresponden(string $plan, string $rol, array $esperados): void
    {
        $this->entrar($plan, $rol);

        $claves = array_column(app(BarraInferiorDelPanel::class)->accesos(), 'clave');

        $this->assertSame($esperados, $claves);
        $this->assertLessThanOrEqual(BarraInferiorDelPanel::MAXIMO, count($claves));
    }

    /**
     * @param  list<string>  $esperados
     */
    #[DataProvider('accesosPorRolYPlan')]
    public function test_los_accesos_son_destinos_que_el_menu_lateral_tambien_ofrece(string $plan, string $rol, array $esperados): void
    {
        $this->entrar($plan, $rol);

        $enElMenu = collect(Filament::getNavigation())->flatMap(fn ($grupo) => $grupo->getItems())->map(fn ($item): string => $item->getUrl())->push(Inicio::getUrl())->all();

        foreach (app(BarraInferiorDelPanel::class)->accesos() as $acceso) {
            $this->assertContains($acceso['url'], $enElMenu, "{$rol} ve {$acceso['clave']} en la barra pero no en el menú lateral.");
        }
        $this->assertNotEmpty($esperados);
    }

    public function test_la_barra_se_dibuja_con_su_nombre_los_accesos_y_mas(): void
    {
        $this->entrar('completo', 'propietario');

        $html = (string) FilamentView::renderHook(PanelsRenderHook::BODY_END);

        $this->assertStringContainsString('class="mo-barra-inferior"', $html);
        $this->assertStringContainsString('aria-label="'.__('panel.barra.nombre').'"', $html);
        $this->assertStringNotContainsString('Accesos rápidos', $html);
        $this->assertSame(4 + 1, substr_count($html, '<span>'), 'Cuatro accesos más "Más".');
        $this->assertStringContainsString('aria-controls="fi-main-sidebar"', $html);
        $this->assertStringContainsString('aria-label="'.__('panel.barra.mas_opciones').'"', $html);
        $this->assertStringContainsString('grid-template-columns: repeat(5, minmax(0, 1fr))', $html);
    }

    public function test_reservas_apunta_a_la_pagina_de_reservas_y_no_a_los_ajustes_ni_a_panel(): void
    {
        $this->entrar('completo', 'propietario');

        $html = (string) FilamentView::renderHook(PanelsRenderHook::BODY_END);

        $this->assertStringContainsString('href="'.Reservas::getUrl().'"', $html);
        $this->assertStringContainsString('<span>'.__('panel.barra.reservas').'</span>', $html);
        $this->assertStringNotContainsString('href="'.AjustesDeReservasYPedidos::getUrl().'"', $html, 'Los ajustes quedan en "Más", no en la barra.');
        $this->assertStringNotContainsString('href="'.route('panel').'"', $html);
        $this->assertStringNotContainsString('aria-label="'.__('panel.enlaces.servicio').'"', $html);
        $this->assertStringNotContainsString('mo-barra-insignia', $html, 'La barra no lleva insignia en este PR.');
    }

    public function test_la_pagina_de_reservas_es_la_activa_solo_estando_en_ella(): void
    {
        $this->entrar('completo', 'propietario');
        $acceso = collect(app(BarraInferiorDelPanel::class)->accesos())->firstWhere('clave', 'reservas');

        $this->assertFalse($acceso['activa']);
        $this->assertSame(Reservas::getUrl(), $acceso['url']);
        $this->assertSame($acceso['etiqueta'], $acceso['nombre'], 'El nombre accesible coincide con el texto visible.');
    }

    public function test_cocina_solo_tiene_los_pedidos_del_panel_anterior_con_su_nombre_completo(): void
    {
        $this->entrar('completo', 'cocina');

        $html = (string) FilamentView::renderHook(PanelsRenderHook::BODY_END);

        $this->assertStringContainsString('<span>'.__('panel.barra.pedidos').'</span>', $html);
        $this->assertStringContainsString('aria-label="'.__('panel.enlaces.servicio').'"', $html);
        $this->assertStringContainsString('href="'.route('panel').'"', $html);
        $this->assertStringNotContainsString('href="'.Reservas::getUrl().'"', $html);
        $this->assertSame(2 + 1, substr_count($html, '<span>'));
        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr))', $html);
        $this->assertStringContainsString(__('panel.barra.pedidos'), __('panel.enlaces.servicio'), 'El nombre accesible debe contener el texto visible.');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rolesConReservas(): array
    {
        return ['propietario' => ['completo', 'propietario'], 'superadmin' => ['completo', 'superadmin'], 'gerente' => ['completo', 'gerente'], 'recepción' => ['completo', 'recepcion'], 'propietario sitio y menú' => ['sitio_menu', 'propietario']];
    }

    #[DataProvider('rolesConReservas')]
    public function test_quien_ve_reservas_no_recibe_ningun_enlace_a_panel_en_la_barra(string $plan, string $rol): void
    {
        $this->entrar($plan, $rol);

        $html = (string) FilamentView::renderHook(PanelsRenderHook::BODY_END);

        $this->assertStringContainsString('href="'.Reservas::getUrl().'"', $html);
        $this->assertStringNotContainsString('href="'.route('panel').'"', $html);
    }

    public function test_sin_restaurante_activo_no_hay_barra(): void
    {
        $this->entrar('completo', 'propietario');
        Filament::setTenant(null);

        $this->assertSame('', trim((string) FilamentView::renderHook(PanelsRenderHook::BODY_END)));
    }

    public function test_solo_el_acceso_de_la_pantalla_abierta_lleva_aria_current(): void
    {
        $this->entrar('completo', 'propietario');

        $html = $this->get(Inicio::getUrl())->assertOk()->getContent();
        preg_match('/<nav class="mo-barra-inferior".*?<\/nav>/s', (string) $html, $barra);

        $this->assertNotEmpty($barra, 'La barra no está en la página de Inicio.');
        $this->assertSame(1, substr_count($barra[0], 'aria-current="page"'));
        $this->assertMatchesRegularExpression('/<a\s+href="'.preg_quote(Inicio::getUrl(), '/').'"\s+aria-current="page"/', $barra[0]);
    }

    public function test_los_enlaces_publicos_estan_tambien_en_el_menu_lateral_y_el_sitio_se_oculta_en_la_barra_superior_del_celular(): void
    {
        $restaurante = $this->entrar('completo', 'propietario');

        $lateral = (string) FilamentView::renderHook(PanelsRenderHook::SIDEBAR_FOOTER);
        $superior = (string) FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE);
        $sitio = route('sitio.publico', ['slug' => $restaurante->slug]);
        $menu = route('menu.publico', ['slug' => $restaurante->slug]);

        $this->assertStringContainsString('href="'.$sitio.'"', $lateral);
        $this->assertStringContainsString('href="'.$menu.'"', $lateral);
        $this->assertStringContainsString('lg:hidden', $lateral);
        $this->assertStringContainsString('href="'.$menu.'"', $superior);
        $this->assertStringContainsString('max-lg:hidden', $superior);
    }
}
