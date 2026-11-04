<?php

namespace Tests\Feature;

use App\Enums\Capacidad;
use App\Enums\PlanRestaurante;
use App\Filament\Resources\HistorialCambioResource\Pages\ListHistorialCambios;
use App\Models\HistorialCambio;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\RetiraElPlanCompleto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Columna `plan` y retiro del plan "Completo": los restaurantes en `completo` pasan a Aura (`sitio_menu`), cada cambio
 * queda en el historial como hecho por el sistema, la columna sale en `sitio_menu`, y una fila vieja que aún diga
 * `completo` se lee como Aura (no pierde su sitio durante el despliegue). La lógica de datos se prueba con filas
 * sembradas, sin revertir la migración (ver App\Services\RetiraElPlanCompleto).
 */
class PlanRestauranteMigracionTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private function conPlan(string $plan): Restaurante
    {
        $restaurante = Restaurante::factory()->create();
        DB::table('restaurantes')->where('id', $restaurante->id)->update(['plan' => $plan]);

        return $restaurante;
    }

    public function test_un_restaurante_nuevo_queda_en_aura_por_defecto(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->assertSame(PlanRestaurante::SitioMenu, $restaurante->plan);
        $this->assertSame(PlanRestaurante::SitioMenu, $restaurante->fresh()->plan);
        $this->assertSame('sitio_menu', DB::table('restaurantes')->where('id', $restaurante->id)->value('plan'));
    }

    public function test_el_valor_por_defecto_de_la_columna_es_sitio_menu(): void
    {
        $columna = collect(Schema::getColumns('restaurantes'))->firstWhere('name', 'plan');

        $this->assertNotNull($columna);
        $this->assertFalse($columna['nullable']);
        $this->assertStringContainsString('sitio_menu', (string) $columna['default']);
        $this->assertStringNotContainsString('completo', (string) $columna['default']);

        $slug = 'sin-plan-'.fake()->unique()->lexify('??????');

        DB::table('restaurantes')->insert([
            'nombre' => 'Restaurante sin plan',
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('sitio_menu', DB::table('restaurantes')->where('slug', $slug)->value('plan'));
    }

    public function test_los_restaurantes_en_completo_pasan_a_aura_y_los_demas_no_cambian(): void
    {
        $completoUno = $this->conPlan('completo');
        $completoDos = $this->conPlan('completo');
        $aura = $this->conPlan('sitio_menu');
        $lite = $this->conPlan('solo_menu');

        $this->assertSame(2, app(RetiraElPlanCompleto::class)());

        $this->assertSame('sitio_menu', DB::table('restaurantes')->where('id', $completoUno->id)->value('plan'));
        $this->assertSame('sitio_menu', DB::table('restaurantes')->where('id', $completoDos->id)->value('plan'));
        $this->assertSame('sitio_menu', DB::table('restaurantes')->where('id', $aura->id)->value('plan'));
        $this->assertSame('solo_menu', DB::table('restaurantes')->where('id', $lite->id)->value('plan'));
        $this->assertSame(0, DB::table('restaurantes')->where('plan', 'completo')->count());
    }

    public function test_cada_restaurante_movido_deja_una_fila_del_sistema_con_el_plan_de_antes_y_el_de_despues(): void
    {
        $uno = $this->conPlan('completo');
        $dos = $this->conPlan('completo');
        $lite = $this->conPlan('solo_menu');

        app(RetiraElPlanCompleto::class)();

        $filas = HistorialCambio::withoutGlobalScopes()->orderBy('restaurante_id')->get();

        $this->assertCount(2, $filas);
        $this->assertSame([$uno->id, $dos->id], $filas->pluck('restaurante_id')->all());
        $this->assertFalse(HistorialCambio::withoutGlobalScopes()->where('restaurante_id', $lite->id)->exists());

        foreach ($filas as $fila) {
            $this->assertNull($fila->usuario_id);
            $this->assertSame('restaurante', $fila->entidad);
            $this->assertSame($fila->restaurante_id, $fila->entidad_id);
            $this->assertSame('actualizar', $fila->accion);
            $this->assertSame(['plan' => ['antes' => 'completo', 'despues' => 'sitio_menu']], $fila->cambios);
            $this->assertNotNull($fila->created_at);
        }
    }

    public function test_tambien_mueve_los_restaurantes_borrados(): void
    {
        $borrado = $this->conPlan('completo');
        $borrado->delete();

        $this->assertSame(1, app(RetiraElPlanCompleto::class)());
        $this->assertSame('sitio_menu', DB::table('restaurantes')->where('id', $borrado->id)->value('plan'));
    }

    public function test_sin_restaurantes_en_completo_no_hace_nada_y_correrla_dos_veces_no_duplica(): void
    {
        $this->conPlan('solo_menu');

        $this->assertSame(0, app(RetiraElPlanCompleto::class)());
        $this->assertSame(0, HistorialCambio::withoutGlobalScopes()->count());

        $this->conPlan('completo');
        app(RetiraElPlanCompleto::class)();
        app(RetiraElPlanCompleto::class)();

        $this->assertSame(1, HistorialCambio::withoutGlobalScopes()->count());
    }

    public function test_el_historial_muestra_el_cambio_como_hecho_por_el_sistema(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        DB::table('restaurantes')->where('id', $restaurante->id)->update(['plan' => 'completo']);
        app(RetiraElPlanCompleto::class)();
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $fila = HistorialCambio::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->sole();

        Livewire::test(ListHistorialCambios::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$fila])
            ->assertTableColumnStateSet('usuario.name', 'Sistema', $fila);
    }

    public function test_una_fila_que_aun_dice_completo_se_lee_como_aura_y_conserva_sitio_reservas_y_panel(): void
    {
        $restaurante = $this->conPlan('completo');
        $leido = $restaurante->fresh();

        $this->assertSame(PlanRestaurante::SitioMenu, $leido->plan);
        $this->assertTrue($leido->tieneSitio());
        $this->assertTrue($leido->reservasDisponibles());
        $this->assertTrue($leido->permiteElPanelAlEquipo());

        foreach (Capacidad::cases() as $capacidad) {
            $this->assertTrue($leido->incluye($capacidad), $capacidad->value);
        }
    }

    public function test_guardar_el_valor_retirado_completo_lo_deja_como_aura(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();

        $restaurante->forceFill(['plan' => 'completo'])->save();

        $this->assertSame('sitio_menu', DB::table('restaurantes')->where('id', $restaurante->id)->value('plan'));
    }

    public function test_no_se_puede_guardar_un_plan_fuera_de_la_lista(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->expectException(\ValueError::class);

        $restaurante->forceFill(['plan' => 'premium']);
    }

    public function test_un_valor_raro_en_la_base_se_lee_como_lite(): void
    {
        $restaurante = $this->conPlan('premium');

        $leido = $restaurante->fresh();
        $this->assertSame(PlanRestaurante::SoloMenu, $leido->plan);
        $this->assertFalse($leido->tieneSitio());
    }

    public function test_el_plan_se_cambia_solo_con_force_fill_y_no_por_asignacion_masiva(): void
    {
        $restaurante = Restaurante::factory()->create();

        $restaurante->fill(['plan' => 'solo_menu']);

        $this->assertSame(PlanRestaurante::SitioMenu, $restaurante->plan);

        $restaurante->forceFill(['plan' => PlanRestaurante::SoloMenu])->save();

        $this->assertSame(PlanRestaurante::SoloMenu, $restaurante->fresh()->plan);
        $this->assertSame('solo_menu', DB::table('restaurantes')->where('id', $restaurante->id)->value('plan'));
    }

    public function test_los_estados_de_la_fabrica_ponen_cada_plan(): void
    {
        $this->assertSame(PlanRestaurante::SitioMenu, Restaurante::factory()->sitioMenu()->create()->fresh()->plan);
        $this->assertSame(PlanRestaurante::SoloMenu, Restaurante::factory()->soloMenu()->create()->fresh()->plan);
    }

    public function test_atajos_del_modelo_y_excepcion_del_superadmin(): void
    {
        $aura = Restaurante::factory()->sitioMenu()->create();
        $lite = Restaurante::factory()->soloMenu()->create();
        $superadmin = User::factory()->create(['es_superadmin' => true]);
        $usuario = User::factory()->create();

        $this->assertTrue($aura->tieneSitio());
        $this->assertFalse($lite->tieneSitio());

        $this->assertTrue($aura->reservasDisponibles());
        $this->assertFalse($lite->reservasDisponibles());

        $aura->reservas_activas = false;
        $this->assertFalse($aura->reservasDisponibles());

        $this->assertTrue($lite->permiteA($usuario, Capacidad::EditarMenu));
        $this->assertTrue($lite->permiteA($usuario, Capacidad::Panel));
        $this->assertFalse($lite->permiteA($usuario, Capacidad::Reservas));
        $this->assertFalse($lite->permiteA($usuario, Capacidad::Sitio));
        $this->assertTrue($lite->permiteA($superadmin, Capacidad::Sitio));
    }

    public function test_los_nombres_de_cada_plan_son_aura_y_lite(): void
    {
        $this->assertSame(
            ['Aura', 'Lite'],
            array_map(fn (PlanRestaurante $plan): string => $plan->getLabel(), PlanRestaurante::cases()),
        );

        foreach (PlanRestaurante::cases() as $plan) {
            $this->assertNotSame('panel.planes.'.$plan->value.'.descripcion', $plan->descripcion());
            $this->assertStringNotContainsString('Completo', $plan->descripcion());
        }

        $this->assertFalse(trans()->has('panel.planes.completo'));
    }
}
