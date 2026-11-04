<?php

namespace Tests\Feature;

use App\Enums\PlanRestaurante;
use App\Filament\Pages\Apariencia;
use App\Filament\Pages\FotosDelRestaurante;
use App\Filament\Pages\Reservas;
use App\Models\CodigoQr;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Services\PermisosPanel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El plan es un techo sobre el rol (docs/planes.md): los módulos de PermisosPanel y las pantallas de /admin se recortan en
 * el servidor. Aura (`sitio_menu`) no recorta nada; Lite (`solo_menu`) tiene panel pero quita las reservas y el sitio.
 */
class PanelPlanAccesoTest extends TestCase
{
    use RefreshDatabase;

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

    private function cambiarDePlan(Restaurante $restaurante, PlanRestaurante $plan): void
    {
        $restaurante->forceFill(['plan' => $plan])->save();
    }

    /**
     * @return array<string, array{0: string, 1: list<string>, 2: list<string>}>
     */
    public static function modulosPorRolYPlan(): array
    {
        return [
            'propietario' => ['propietario', PermisosPanel::MODULOS, ['inicio', 'menu', 'ajustes']],
            'gerente' => ['gerente', PermisosPanel::MODULOS, ['inicio', 'menu', 'ajustes']],
            'editor' => ['editor', ['inicio', 'reservas', 'menu'], ['inicio', 'menu']],
            'recepcion' => ['recepcion', ['inicio', 'reservas'], ['inicio']],
        ];
    }

    /**
     * @param  list<string>  $enAura
     * @param  list<string>  $enLite
     */
    #[DataProvider('modulosPorRolYPlan')]
    public function test_los_modulos_de_cada_rol_se_recortan_con_el_techo_del_plan(string $rol, array $enAura, array $enLite): void
    {
        foreach ([[Restaurante::factory()->sitioMenu(), $enAura], [Restaurante::factory()->soloMenu(), $enLite]] as [$fabrica, $esperados]) {
            $restaurante = $fabrica->create();
            $usuario = $this->miembro($restaurante, $rol);

            $this->assertSame($esperados, PermisosPanel::modulosDe($usuario, $restaurante->id), $rol.' en '.$restaurante->plan->value);
        }
    }

    public function test_el_superadmin_conserva_todo_salvo_las_reservas_en_lite(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->assertSame(PermisosPanel::MODULOS, PermisosPanel::modulosDe($superadmin, Restaurante::factory()->sitioMenu()->create()->id));
        $this->assertSame(['inicio', 'menu', 'ajustes'], PermisosPanel::modulosDe($superadmin, Restaurante::factory()->soloMenu()->create()->id));
    }

    public function test_el_propietario_entra_a_admin_en_los_dos_planes_y_solo_a_sus_restaurantes(): void
    {
        $lite = Restaurante::factory()->soloMenu()->create();
        $aura = Restaurante::factory()->sitioMenu()->create();
        $propietarioLite = $this->miembro($lite);
        $propietarioAura = $this->miembro($aura);
        $panel = Filament::getPanel('admin');

        $this->assertTrue($propietarioLite->canAccessPanel($panel));
        $this->assertTrue($propietarioLite->canAccessTenant($lite));
        $this->assertSame([$lite->id], $propietarioLite->getTenants($panel)->pluck('id')->all());
        $this->assertFalse($propietarioLite->canAccessTenant($aura));
        $this->actingAs($propietarioLite)->get('/admin')->assertRedirect(url('/admin/'.$lite->slug));
        $this->actingAs($propietarioLite)->get('/admin/'.$lite->slug)->assertOk();
        $this->actingAs($propietarioLite)->get('/admin/'.$aura->slug)->assertNotFound();

        $this->assertTrue($propietarioAura->canAccessPanel($panel));
        $this->assertTrue($propietarioAura->canAccessTenant($aura));
        $this->assertSame([$aura->id], $propietarioAura->getTenants($panel)->pluck('id')->all());
        $this->assertFalse($propietarioAura->canAccessTenant($lite));
    }

    public function test_quien_tiene_un_restaurante_aura_y_otro_lite_ve_los_dos_y_reservas_solo_en_aura(): void
    {
        $aura = Restaurante::factory()->sitioMenu()->create(['nombre' => 'Aaa Aura']);
        $lite = Restaurante::factory()->soloMenu()->create(['nombre' => 'Bbb Lite']);
        $usuario = User::factory()->create();
        $aura->usuarios()->attach($usuario, ['rol' => 'propietario']);
        $lite->usuarios()->attach($usuario, ['rol' => 'propietario']);
        $panel = Filament::getPanel('admin');

        $this->assertTrue($usuario->canAccessPanel($panel));
        $this->assertEqualsCanonicalizing([$aura->id, $lite->id], $usuario->getTenants($panel)->pluck('id')->all());
        $this->actingAs($usuario)->get(Reservas::getUrl(tenant: $aura))->assertOk();
        $this->actingAs($usuario)->get(Reservas::getUrl(tenant: $lite))->assertNotFound();
    }

    public function test_el_superadmin_entra_a_los_dos_planes(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $panel = Filament::getPanel('admin');

        $this->assertTrue($superadmin->canAccessPanel($panel));

        foreach ([Restaurante::factory()->sitioMenu(), Restaurante::factory()->soloMenu()] as $fabrica) {
            $restaurante = $fabrica->create();

            $this->assertTrue($superadmin->canAccessTenant($restaurante), $restaurante->plan->value);
            $this->assertTrue($superadmin->getTenants($panel)->contains('id', $restaurante->id));
            $this->actingAs($superadmin)->get(Apariencia::getUrl(tenant: $restaurante))->assertOk();
        }

        $lite = Restaurante::factory()->soloMenu()->create();
        $this->actingAs($superadmin)->get(Reservas::getUrl(tenant: $lite))->assertNotFound();
    }

    public function test_una_sesion_abierta_pierde_reservas_y_fotos_en_cuanto_pasa_a_lite_y_las_recupera_al_volver(): void
    {
        $restaurante = Restaurante::factory()->sitioMenu()->create();
        $propietario = $this->miembro($restaurante);
        $apariencia = Apariencia::getUrl(tenant: $restaurante);
        $fotos = FotosDelRestaurante::getUrl(tenant: $restaurante);
        $reservas = Reservas::getUrl(tenant: $restaurante);

        $this->actingAs($propietario)->get($apariencia)->assertOk();
        $this->actingAs($propietario)->get($fotos)->assertOk();
        $this->actingAs($propietario)->get($reservas)->assertOk();

        $this->cambiarDePlan($restaurante, PlanRestaurante::SoloMenu);
        $this->actingAs($propietario)->get($apariencia)->assertOk();
        $this->actingAs($propietario)->get($fotos)->assertForbidden();
        $this->actingAs($propietario)->get($reservas)->assertNotFound();

        $this->cambiarDePlan($restaurante, PlanRestaurante::SitioMenu);
        $this->actingAs($propietario)->get($fotos)->assertOk();
        $this->actingAs($propietario)->get($reservas)->assertOk();
    }

    public function test_un_plan_raro_en_la_base_se_trata_como_lite_el_plan_mas_restrictivo(): void
    {
        $restaurante = Restaurante::factory()->sitioMenu()->create();
        $propietario = $this->miembro($restaurante);

        Restaurante::whereKey($restaurante->id)->toBase()->update(['plan' => 'premium']);

        $this->assertSame(['inicio', 'menu', 'ajustes'], PermisosPanel::modulosDe($propietario, $restaurante->id));
        $this->assertFalse($restaurante->fresh()->tieneSitio());
        $this->actingAs($propietario)->get(Reservas::getUrl(tenant: $restaurante))->assertNotFound();
    }

    public function test_un_plan_completo_que_quedo_en_la_base_se_trata_como_aura(): void
    {
        $restaurante = Restaurante::factory()->sitioMenu()->create();
        $propietario = $this->miembro($restaurante);

        Restaurante::whereKey($restaurante->id)->toBase()->update(['plan' => 'completo']);

        $this->assertSame(PermisosPanel::MODULOS, PermisosPanel::modulosDe($propietario, $restaurante->id));
        $this->actingAs($propietario)->get(Reservas::getUrl(tenant: $restaurante))->assertOk();
        $this->actingAs($propietario)->get(FotosDelRestaurante::getUrl(tenant: $restaurante))->assertOk();
    }

    public function test_el_propietario_de_lite_descarga_sus_qr(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $propietario = $this->miembro($restaurante);
        $qr = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id]);

        $this->actingAs($propietario)->get(route('codigos-qr.descargar', ['codigoQr' => $qr->id, 'formato' => 'svg']))->assertOk();
    }
}
