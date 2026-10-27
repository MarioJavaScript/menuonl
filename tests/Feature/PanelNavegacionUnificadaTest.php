<?php

namespace Tests\Feature;

use App\Filament\Pages\AjustesDeReservasYPedidos;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\Reservas;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Services\ProgresoDeConfiguracion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Navegación unificada de /admin (docs/integracion-dashboard.md, PR 3): lee el HTML pintado del menú, no solo la
 * lista de Filament. Reservas y Ajustes de reservas y pedidos son las dos pantallas del grupo; el único enlace a /panel
 * es el ítem TEMPORAL de los pedidos manuales, solo con el módulo pedidos. Se comprueba por URL y por clase, no por
 * etiquetas que se repiten.
 */
class PanelNavegacionUnificadaTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const string MARCA_BANNER = 'mb-banner--fijo';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    private function restauranteDePlan(string $plan, string $slug = 'la-esquina'): Restaurante
    {
        return match ($plan) {
            'sitio_menu' => Restaurante::factory()->sitioMenu()->create(['slug' => $slug]),
            'solo_menu' => Restaurante::factory()->soloMenu()->create(['slug' => $slug]),
            default => Restaurante::factory()->create(['slug' => $slug]),
        };
    }

    private function enlace(string $url): string
    {
        return 'href="'.$url.'"';
    }

    /**
     * Plan, rol, ve Reservas, ve Ajustes de reservas y pedidos, ve el ítem temporal hacia /panel.
     *
     * @return array<string, array{0: string, 1: string, 2: bool, 3: bool, 4: bool}>
     */
    public static function menuPorPlanYRol(): array
    {
        return [
            'propietario completo' => ['completo', 'propietario', true, true, true],
            'superadmin completo' => ['completo', 'superadmin', true, true, true],
            'propietario sitio y menú' => ['sitio_menu', 'propietario', true, true, false],
            'superadmin sitio y menú' => ['sitio_menu', 'superadmin', true, true, true],
            'superadmin solo menú' => ['solo_menu', 'superadmin', false, true, true],
            'gerente completo' => ['completo', 'gerente', true, false, true],
            'recepción completo' => ['completo', 'recepcion', true, false, false],
            'cocina completo' => ['completo', 'cocina', false, false, true],
        ];
    }

    #[DataProvider('menuPorPlanYRol')]
    public function test_el_html_del_menu_ofrece_cada_pantalla_solo_a_quien_le_corresponde(string $plan, string $rol, bool $reservas, bool $ajustes, bool $temporal): void
    {
        $restaurante = $this->restauranteDePlan($plan);
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        $html = $this->get(Inicio::getUrl())->assertOk()->getContent();

        $this->assertSame($reservas, str_contains($html, $this->enlace(Reservas::getUrl())), 'Reservas');
        $this->assertSame($ajustes, str_contains($html, $this->enlace(AjustesDeReservasYPedidos::getUrl())), 'Ajustes de reservas y pedidos');
        $this->assertSame($temporal, str_contains($html, $this->enlace(route('panel'))), 'Ítem temporal hacia /panel');
        $this->assertStringNotContainsString('Pedidos y reservas de hoy', $html);
        $this->assertStringNotContainsString('/pedidos-y-reservas', $html, 'Ninguna dirección vieja en el menú.');
    }

    public function test_el_html_del_propietario_trae_los_nombres_nuevos_y_marca_el_item_temporal(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $this->get(Inicio::getUrl())
            ->assertSee('Ajustes de reservas y pedidos')
            ->assertSee('Pedidos (panel anterior)')
            ->assertDontSee('Pedidos y reservas de hoy');
    }

    public function test_los_ajustes_dicen_su_nombre_y_remiten_a_reservas(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $this->get(AjustesDeReservasYPedidos::getUrl())
            ->assertOk()
            ->assertSee('Ajustes de reservas y pedidos')
            ->assertSee('Para atender las reservas, ve a Reservas.');
    }

    public function test_la_url_vieja_de_los_ajustes_redirige_a_la_nueva_con_la_sesion_correcta(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $this->get('/admin/la-esquina/pedidos-y-reservas')->assertRedirect(AjustesDeReservasYPedidos::getUrl());
        $this->assertStringEndsWith('/admin/la-esquina/ajustes-de-reservas-y-pedidos', AjustesDeReservasYPedidos::getUrl());
    }

    public function test_la_url_vieja_redirige_tambien_para_el_superadmin_de_sitio_y_menu(): void
    {
        $restaurante = $this->restauranteDePlan('sitio_menu');
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $restaurante);

        $this->get('/admin/la-esquina/pedidos-y-reservas')->assertRedirect(AjustesDeReservasYPedidos::getUrl());
    }

    public function test_sin_sesion_la_url_vieja_lleva_al_inicio_de_sesion(): void
    {
        $this->restauranteDePlan('completo');

        $this->get('/admin/la-esquina/pedidos-y-reservas')->assertRedirect('/admin/login');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesSinLosAjustes(): array
    {
        return ['editor' => ['editor'], 'gerente' => ['gerente'], 'recepción' => ['recepcion'], 'cocina' => ['cocina']];
    }

    #[DataProvider('rolesSinLosAjustes')]
    public function test_quien_no_puede_ver_los_ajustes_no_los_obtiene_con_la_url_vieja(string $rol): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        $respuesta = $this->get('/admin/la-esquina/pedidos-y-reservas');

        $respuesta->assertForbidden();
        $this->assertNull($respuesta->headers->get('Location'));
    }

    public function test_el_superadmin_de_solo_menu_sigue_llegando_a_los_ajustes_por_la_url_vieja(): void
    {
        $restaurante = $this->restauranteDePlan('solo_menu');
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $restaurante);

        $this->get('/admin/la-esquina/pedidos-y-reservas')->assertRedirect(AjustesDeReservasYPedidos::getUrl());
        $this->assertContains($this->get(Reservas::getUrl())->getStatusCode(), [403, 404]);
    }

    public function test_con_la_url_vieja_de_otro_restaurante_no_se_obtiene_nada(): void
    {
        $propio = $this->restauranteDePlan('completo', 'la-esquina');
        $this->restauranteDePlan('completo', 'la-otra');
        $this->entrarAlPanel($this->crearUsuario('propietario', $propio), $propio);

        $vieja = $this->get('/admin/la-otra/pedidos-y-reservas');
        $nueva = $this->get('/admin/la-otra/ajustes-de-reservas-y-pedidos');

        $this->assertContains($vieja->getStatusCode(), [403, 404]);
        $this->assertNull($vieja->headers->get('Location'));
        $this->assertSame($nueva->getStatusCode(), $vieja->getStatusCode(), 'La dirección vieja no abre más que la nueva.');
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function paginasConBanner(): array
    {
        return ['Reservas' => [Reservas::class], 'Ajustes de reservas y pedidos' => [AjustesDeReservasYPedidos::class]];
    }

    /**
     * @param  class-string<Reservas|AjustesDeReservasYPedidos>  $pagina
     */
    #[DataProvider('paginasConBanner')]
    public function test_el_banner_de_superadmin_sale_en_reservas_y_en_los_ajustes_y_no_para_el_propietario(string $pagina): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $restaurante);

        $this->get($pagina::getUrl())->assertOk()->assertSee(self::MARCA_BANNER, escape: false);

        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $this->get($pagina::getUrl())->assertOk()->assertDontSee(self::MARCA_BANNER, escape: false);
    }

    public function test_el_resumen_del_dia_de_inicio_enlaza_a_reservas_y_no_a_panel(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 18:00:00', 'UTC'));
        $restaurante = $this->restauranteDePlan('completo');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);
        Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'fecha' => '2026-10-21', 'hora' => '20:00:00', 'estado' => 'pendiente']);

        $dia = app(ProgresoDeConfiguracion::class)->para($restaurante, $propietario)['dia'];
        $this->assertSame(Reservas::getUrl(), $dia['url']);

        $html = $this->get(Inicio::getUrl())->assertOk()->getContent();
        $this->assertStringContainsString('class="fi-btn', substr($html, (int) strpos($html, $this->enlace(Reservas::getUrl()))));
        $this->assertStringContainsString(__('panel.inicio.dia.ver'), $html);
    }
}
