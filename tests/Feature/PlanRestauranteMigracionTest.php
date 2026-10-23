<?php

namespace Tests\Feature;

use App\Enums\Capacidad;
use App\Enums\PlanRestaurante;
use App\Models\Restaurante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlanRestauranteMigracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_restaurante_nuevo_queda_en_completo_por_defecto(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->assertSame(PlanRestaurante::Completo, $restaurante->plan);
        $this->assertSame(PlanRestaurante::Completo, $restaurante->fresh()->plan);
        $this->assertSame('completo', DB::table('restaurantes')->where('id', $restaurante->id)->value('plan'));
    }

    public function test_el_valor_por_defecto_de_la_columna_llena_las_filas_sin_plan(): void
    {
        $columna = collect(Schema::getColumns('restaurantes'))->firstWhere('name', 'plan');

        $this->assertNotNull($columna);
        $this->assertFalse($columna['nullable']);
        $this->assertStringContainsString('completo', (string) $columna['default']);

        $slug = 'sin-plan-'.fake()->unique()->lexify('??????');

        DB::table('restaurantes')->insert([
            'nombre' => 'Restaurante sin plan',
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('completo', DB::table('restaurantes')->where('slug', $slug)->value('plan'));
    }

    public function test_no_se_puede_guardar_un_plan_fuera_de_la_lista(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->expectException(\ValueError::class);

        $restaurante->forceFill(['plan' => 'premium']);
    }

    public function test_un_valor_raro_en_la_base_se_lee_como_solo_menu(): void
    {
        $restaurante = Restaurante::factory()->create();

        DB::table('restaurantes')->where('id', $restaurante->id)->update(['plan' => 'premium']);

        $leido = $restaurante->fresh();
        $this->assertSame(PlanRestaurante::SoloMenu, $leido->plan);
        $this->assertFalse($leido->tieneSitio());
    }

    public function test_el_plan_se_cambia_solo_con_force_fill_y_no_por_asignacion_masiva(): void
    {
        $restaurante = Restaurante::factory()->create();

        $restaurante->fill(['plan' => 'solo_menu']);

        $this->assertSame(PlanRestaurante::Completo, $restaurante->plan);

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
        $completo = Restaurante::factory()->create();
        $sitioMenu = Restaurante::factory()->sitioMenu()->create();
        $soloMenu = Restaurante::factory()->soloMenu()->create();
        $superadmin = User::factory()->create(['es_superadmin' => true]);
        $usuario = User::factory()->create();

        $this->assertTrue($completo->tieneSitio());
        $this->assertTrue($sitioMenu->tieneSitio());
        $this->assertFalse($soloMenu->tieneSitio());

        $this->assertTrue($completo->reservasDisponibles());
        $this->assertTrue($sitioMenu->reservasDisponibles());
        $this->assertFalse($soloMenu->reservasDisponibles());

        $sitioMenu->reservas_activas = false;
        $this->assertFalse($sitioMenu->reservasDisponibles());

        $this->assertFalse($sitioMenu->permiteA($usuario, Capacidad::EditarMenu));
        $this->assertTrue($sitioMenu->permiteA($usuario, Capacidad::EditarHorario));
        $this->assertTrue($soloMenu->permiteA($superadmin, Capacidad::EditarMenu));
        $this->assertFalse($soloMenu->permiteA($usuario, Capacidad::Panel));
    }

    public function test_los_textos_de_cada_plan_existen(): void
    {
        $this->assertSame(
            ['Completo', 'Sitio y menú', 'Solo menú'],
            array_map(fn (PlanRestaurante $plan): string => $plan->getLabel(), PlanRestaurante::cases()),
        );

        foreach (PlanRestaurante::cases() as $plan) {
            $this->assertNotSame('panel.planes.'.$plan->value.'.descripcion', $plan->descripcion());
        }
    }
}
