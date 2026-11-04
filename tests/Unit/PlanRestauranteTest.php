<?php

namespace Tests\Unit;

use App\Enums\Capacidad;
use App\Enums\PlanRestaurante;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlanRestauranteTest extends TestCase
{
    /**
     * Una fila por capacidad: Aura (sitio_menu) incluye todo; Lite (solo_menu) todo menos el sitio y las reservas.
     *
     * @return array<string, array{0: Capacidad, 1: bool, 2: bool}>
     */
    public static function tablaDeCapacidades(): array
    {
        return [
            'sitio' => [Capacidad::Sitio, true, false],
            'reservas' => [Capacidad::Reservas, true, false],
            'panel' => [Capacidad::Panel, true, true],
            'editar_diseno' => [Capacidad::EditarDiseno, true, true],
            'editar_menu' => [Capacidad::EditarMenu, true, true],
            'editar_datos' => [Capacidad::EditarDatos, true, true],
            'editar_telefonos' => [Capacidad::EditarTelefonos, true, true],
            'editar_horario' => [Capacidad::EditarHorario, true, true],
            'qr' => [Capacidad::Qr, true, true],
            'equipo' => [Capacidad::Equipo, true, true],
            'servicio' => [Capacidad::Servicio, true, true],
            'metricas' => [Capacidad::Metricas, true, true],
        ];
    }

    #[DataProvider('tablaDeCapacidades')]
    public function test_cada_plan_incluye_solo_lo_que_dice_la_tabla(Capacidad $capacidad, bool $aura, bool $lite): void
    {
        $this->assertSame($aura, PlanRestaurante::SitioMenu->incluye($capacidad));
        $this->assertSame($lite, PlanRestaurante::SoloMenu->incluye($capacidad));
    }

    public function test_la_tabla_cubre_todas_las_capacidades(): void
    {
        $this->assertEqualsCanonicalizing(
            array_map(fn (Capacidad $c): string => $c->name, Capacidad::cases()),
            array_values(array_map(fn (array $fila): string => $fila[0]->name, self::tablaDeCapacidades())),
        );
    }

    public function test_los_valores_guardados_caben_en_la_columna(): void
    {
        $this->assertSame(['sitio_menu', 'solo_menu'], array_map(fn (PlanRestaurante $p): string => $p->value, PlanRestaurante::cases()));

        foreach (PlanRestaurante::cases() as $plan) {
            $this->assertLessThanOrEqual(20, strlen($plan->value));
        }
    }

    public function test_un_valor_desconocido_o_vacio_cae_al_plan_mas_restrictivo(): void
    {
        $this->assertSame(PlanRestaurante::SoloMenu, PlanRestaurante::desde('premium'));
        $this->assertSame(PlanRestaurante::SoloMenu, PlanRestaurante::desde(''));
        $this->assertSame(PlanRestaurante::SoloMenu, PlanRestaurante::desde(null));
        $this->assertSame(PlanRestaurante::SitioMenu, PlanRestaurante::desde('sitio_menu'));
        $this->assertSame(PlanRestaurante::SoloMenu, PlanRestaurante::desde('solo_menu'));
    }

    public function test_el_plan_retirado_completo_se_lee_como_aura(): void
    {
        $this->assertSame('completo', PlanRestaurante::VALOR_RETIRADO_COMPLETO);
        $this->assertNull(PlanRestaurante::tryFrom('completo'));
        $this->assertSame(PlanRestaurante::SitioMenu, PlanRestaurante::desde('completo'));
    }

    public function test_un_formulario_no_puede_elegir_el_plan_retirado(): void
    {
        $this->assertNull(PlanRestaurante::deFormulario('completo'));
        $this->assertSame(PlanRestaurante::SitioMenu, PlanRestaurante::deFormulario('sitio_menu'));
        $this->assertSame(PlanRestaurante::SoloMenu, PlanRestaurante::deFormulario(PlanRestaurante::SoloMenu));
    }
}
