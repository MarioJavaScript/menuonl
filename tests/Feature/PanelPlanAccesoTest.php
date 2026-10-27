<?php

namespace Tests\Feature;

use App\Enums\PlanRestaurante;
use App\Http\Middleware\ModuloPanel;
use App\Models\CodigoQr;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Services\PermisosPanel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RutaDeLaAplicacion;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El plan es un techo sobre el rol (docs/planes.md, paso 3): /panel y /admin se recortan en el servidor.
 */
class PanelPlanAccesoTest extends TestCase
{
    use RefreshDatabase;

    private const array ROLES = ['propietario', 'gerente', 'editor', 'recepcion'];

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function miembro(Restaurante $restaurante, string $rol = 'propietario'): User
    {
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario, ['rol' => $rol]);

        return $usuario;
    }

    private function bajarDePlan(Restaurante $restaurante, PlanRestaurante $plan): void
    {
        $restaurante->forceFill(['plan' => $plan])->save();
    }

    /**
     * @return array<string, array{0: string, 1: list<string>, 2: list<string>, 3: list<string>}>
     */
    public static function modulosPorRolYPlan(): array
    {
        $todos = PermisosPanel::MODULOS;

        return [
            'propietario' => ['propietario', $todos, ['reservas'], []],
            'gerente' => ['gerente', $todos, ['reservas'], []],
            'editor' => ['editor', ['inicio', 'reservas', 'menu'], ['reservas'], []],
            'recepcion' => ['recepcion', ['inicio', 'reservas'], ['reservas'], []],
        ];
    }

    /**
     * @param  list<string>  $enCompleto
     * @param  list<string>  $enSitioMenu
     * @param  list<string>  $enSoloMenu
     */
    #[DataProvider('modulosPorRolYPlan')]
    public function test_los_modulos_de_cada_rol_se_recortan_con_el_techo_del_plan(string $rol, array $enCompleto, array $enSitioMenu, array $enSoloMenu): void
    {
        foreach ([[Restaurante::factory(), $enCompleto], [Restaurante::factory()->sitioMenu(), $enSitioMenu], [Restaurante::factory()->soloMenu(), $enSoloMenu]] as [$fabrica, $esperados]) {
            $restaurante = $fabrica->create();
            $usuario = $this->miembro($restaurante, $rol);

            $this->assertSame($esperados, PermisosPanel::modulosDe($usuario, $restaurante->id), $rol.' en '.$restaurante->plan->value);
        }
    }

    public function test_el_superadmin_conserva_todo_salvo_las_reservas_donde_el_plan_no_las_tiene(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $sinReservas = array_values(array_diff(PermisosPanel::MODULOS, ['reservas']));

        $this->assertSame(PermisosPanel::MODULOS, PermisosPanel::modulosDe($superadmin, Restaurante::factory()->create()->id));
        $this->assertSame(PermisosPanel::MODULOS, PermisosPanel::modulosDe($superadmin, Restaurante::factory()->sitioMenu()->create()->id));
        $this->assertSame($sinReservas, PermisosPanel::modulosDe($superadmin, Restaurante::factory()->soloMenu()->create()->id));
    }

    public function test_sitio_y_menu_atiende_las_reservas_y_el_resto_responde_403(): void
    {
        $restaurante = Restaurante::factory()->sitioMenu()->create();
        $propietario = $this->miembro($restaurante);
        $base = "/api/v1/panel/{$restaurante->slug}";

        $this->actingAs($propietario)->getJson($base.'/reservas')->assertOk();

        foreach (['/resumen', '/menu', '/ajustes', '/equipo'] as $ruta) {
            $this->actingAs($propietario)->getJson($base.$ruta)->assertForbidden();
        }

        $this->actingAs($propietario)->patchJson($base.'/ajustes', ['nombre' => 'Otro nombre'])->assertForbidden();
        $this->assertNotSame('Otro nombre', $restaurante->fresh()->nombre);
    }

    public function test_la_lista_de_restaurantes_de_panel_ofrece_solo_lo_que_el_plan_deja(): void
    {
        $completo = Restaurante::factory()->create();
        $sitioMenu = Restaurante::factory()->sitioMenu()->create();
        $soloMenu = Restaurante::factory()->soloMenu()->create();
        $usuario = User::factory()->create();

        foreach ([$completo, $sitioMenu, $soloMenu] as $restaurante) {
            $restaurante->usuarios()->attach($usuario, ['rol' => 'propietario']);
        }

        $datos = collect($this->actingAs($usuario)->getJson('/api/v1/panel')->assertOk()->json('data'))->keyBy('slug');

        $this->assertEqualsCanonicalizing([$completo->slug, $sitioMenu->slug], $datos->keys()->all());
        $this->assertSame(['reservas'], $datos[$sitioMenu->slug]['modulos']);
        $this->assertSame(PermisosPanel::MODULOS, $datos[$completo->slug]['modulos']);
    }

    public function test_solo_menu_responde_404_en_toda_la_api_de_panel_como_un_restaurante_ajeno(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $propietario = $this->miembro($restaurante);

        foreach ($this->rutasDelPanel($restaurante) as [$metodo, $uri]) {
            $this->actingAs($propietario)->json($metodo, $uri)->assertNotFound();
        }
    }

    public function test_un_propietario_de_solo_menu_no_entra_a_admin_y_uno_de_sitio_y_menu_si_es_miembro(): void
    {
        $soloMenu = Restaurante::factory()->soloMenu()->create();
        $sitioMenu = Restaurante::factory()->sitioMenu()->create();
        $propietarioSolo = $this->miembro($soloMenu);
        $propietarioSitio = $this->miembro($sitioMenu);
        $panel = Filament::getPanel('admin');

        $this->assertFalse($propietarioSolo->canAccessPanel($panel));
        $this->assertFalse($propietarioSolo->canAccessTenant($soloMenu));
        $this->assertCount(0, $propietarioSolo->getTenants($panel));
        $this->actingAs($propietarioSolo)->get('/admin')->assertForbidden();
        $this->actingAs($propietarioSolo)->get('/admin/'.$soloMenu->slug)->assertForbidden();

        $this->assertTrue($propietarioSitio->canAccessPanel($panel));
        $this->assertTrue($propietarioSitio->canAccessTenant($sitioMenu));
        $this->assertSame([$sitioMenu->id], $propietarioSitio->getTenants($panel)->pluck('id')->all());
        $this->assertFalse($propietarioSitio->canAccessTenant($soloMenu));
    }

    public function test_quien_tiene_un_restaurante_completo_y_otro_solo_menu_ve_solo_el_primero(): void
    {
        $completo = Restaurante::factory()->create();
        $soloMenu = Restaurante::factory()->soloMenu()->create();
        $usuario = User::factory()->create();
        $completo->usuarios()->attach($usuario, ['rol' => 'propietario']);
        $soloMenu->usuarios()->attach($usuario, ['rol' => 'propietario']);
        $panel = Filament::getPanel('admin');

        $this->assertTrue($usuario->canAccessPanel($panel));
        $this->assertSame([$completo->id], $usuario->getTenants($panel)->pluck('id')->all());
        $this->assertTrue($usuario->canAccessTenant($completo));
        $this->assertFalse($usuario->canAccessTenant($soloMenu));
        $this->actingAs($usuario)->getJson("/api/v1/panel/{$soloMenu->slug}/reservas")->assertNotFound();
    }

    public function test_el_superadmin_entra_a_los_tres_planes(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $panel = Filament::getPanel('admin');

        $this->assertTrue($superadmin->canAccessPanel($panel));

        foreach ([Restaurante::factory(), Restaurante::factory()->sitioMenu(), Restaurante::factory()->soloMenu()] as $fabrica) {
            $restaurante = $fabrica->create();

            $this->assertTrue($superadmin->canAccessTenant($restaurante), $restaurante->plan->value);
            $this->assertTrue($superadmin->getTenants($panel)->contains('id', $restaurante->id));
            $this->actingAs($superadmin)->getJson("/api/v1/panel/{$restaurante->slug}/ajustes")->assertOk();
        }

        $soloMenu = Restaurante::factory()->soloMenu()->create();
        $this->actingAs($superadmin)->getJson("/api/v1/panel/{$soloMenu->slug}/reservas")->assertForbidden();
    }

    public function test_una_sesion_abierta_pierde_el_acceso_en_cuanto_se_baja_el_plan(): void
    {
        $restaurante = Restaurante::factory()->create();
        $propietario = $this->miembro($restaurante);
        $base = "/api/v1/panel/{$restaurante->slug}";

        $this->actingAs($propietario)->getJson($base.'/ajustes')->assertOk();

        $this->bajarDePlan($restaurante, PlanRestaurante::SitioMenu);
        $this->actingAs($propietario)->getJson($base.'/ajustes')->assertForbidden();
        $this->actingAs($propietario)->getJson($base.'/reservas')->assertOk();

        $this->bajarDePlan($restaurante, PlanRestaurante::SoloMenu);
        $this->actingAs($propietario)->getJson($base.'/reservas')->assertNotFound();

        $this->bajarDePlan($restaurante, PlanRestaurante::Completo);
        $this->actingAs($propietario)->getJson($base.'/ajustes')->assertOk();
    }

    public function test_un_plan_raro_en_la_base_cierra_el_acceso(): void
    {
        $restaurante = Restaurante::factory()->create();
        $propietario = $this->miembro($restaurante);

        Restaurante::whereKey($restaurante->id)->toBase()->update(['plan' => 'premium']);

        $this->assertSame([], PermisosPanel::modulosDe($propietario, $restaurante->id));
        $this->actingAs($propietario)->getJson("/api/v1/panel/{$restaurante->slug}/reservas")->assertNotFound();
    }

    public function test_toda_ruta_de_la_api_de_panel_pasa_por_un_modulo(): void
    {
        $rutas = $this->rutasDeLaApiDelPanel();

        $this->assertNotEmpty($rutas);

        foreach ($rutas as $ruta) {
            $conModulo = collect($ruta->gatherMiddleware())->contains(fn ($m): bool => is_string($m) && str_starts_with($m, ModuloPanel::class.':'));

            $this->assertTrue($conModulo, 'Sin ModuloPanel: '.$ruta->uri());
        }
    }

    public function test_sitio_y_menu_solo_alcanza_las_rutas_del_modulo_de_reservas(): void
    {
        $restaurante = Restaurante::factory()->sitioMenu()->create();
        $propietario = $this->miembro($restaurante);

        foreach ($this->rutasDelPanel($restaurante) as [$metodo, $uri, $modulo]) {
            $estado = $this->actingAs($propietario)->json($metodo, $uri)->getStatusCode();

            if ($modulo === 'reservas') {
                $this->assertNotSame(403, $estado, "{$metodo} {$uri}");
            } else {
                // Con un registro inexistente el enlace del modelo responde 404 antes que el módulo; nunca deja pasar.
                $this->assertContains($estado, str_contains($uri, '/1') ? [403, 404] : [403], "{$metodo} {$uri}");
            }
        }
    }

    public function test_la_descarga_de_qr_se_cierra_para_el_equipo_de_solo_menu(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $propietario = $this->miembro($restaurante);
        $qr = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id]);

        $this->actingAs($propietario)->get(route('codigos-qr.descargar', ['codigoQr' => $qr->id, 'formato' => 'svg']))->assertForbidden();
    }

    /**
     * @return list<RutaDeLaAplicacion>
     */
    private function rutasDeLaApiDelPanel(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RutaDeLaAplicacion $ruta): bool => str_starts_with((string) $ruta->getName(), 'api.v1.panel.') && $ruta->getName() !== 'api.v1.panel.restaurantes')
            ->values()
            ->all();
    }

    /**
     * Cada ruta de /api/v1/panel/{restaurante}/... con su método, su dirección real y su módulo.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function rutasDelPanel(Restaurante $restaurante): array
    {
        $resultado = [];

        foreach ($this->rutasDeLaApiDelPanel() as $ruta) {
            $modulo = collect($ruta->gatherMiddleware())
                ->first(fn ($m): bool => is_string($m) && str_starts_with($m, ModuloPanel::class.':'));
            $uri = preg_replace('/\{restaurante[^}]*\}/', $restaurante->slug, $ruta->uri());
            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', (string) $uri);

            foreach (array_diff($ruta->methods(), ['HEAD']) as $metodo) {
                $resultado[] = [$metodo, $uri, substr((string) $modulo, strlen(ModuloPanel::class) + 1)];
            }
        }

        return $resultado;
    }
}
