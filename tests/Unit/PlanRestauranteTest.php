<?php

namespace Tests\Unit;

use App\Enums\Capacidad;
use App\Enums\PlanRestaurante;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlanRestauranteTest extends TestCase
{
    /**
     * Una fila por capacidad: la tabla de docs/planes.md (2.2) con D2, D4 y D5 resueltas.
     *
     * @return array<string, array{0: Capacidad, 1: bool, 2: bool, 3: bool}>
     */
    public static function tablaDeCapacidades(): array
    {
        return [
            'sitio' => [Capacidad::Sitio, true, true, false],
            'reservas' => [Capacidad::Reservas, true, true, false],
            'panel' => [Capacidad::Panel, true, true, false],
            'editar_diseno' => [Capacidad::EditarDiseno, true, false, false],
            'editar_menu' => [Capacidad::EditarMenu, true, false, false],
            'editar_datos' => [Capacidad::EditarDatos, true, false, false],
            'editar_telefonos' => [Capacidad::EditarTelefonos, true, true, false],
            'editar_horario' => [Capacidad::EditarHorario, true, true, false],
            'qr' => [Capacidad::Qr, true, false, false],
            'equipo' => [Capacidad::Equipo, true, false, false],
            'servicio' => [Capacidad::Servicio, true, true, false],
            'metricas' => [Capacidad::Metricas, true, false, false],
        ];
    }

    #[DataProvider('tablaDeCapacidades')]
    public function test_cada_plan_incluye_solo_lo_que_dice_la_tabla(Capacidad $capacidad, bool $completo, bool $sitioMenu, bool $soloMenu): void
    {
        $this->assertSame($completo, PlanRestaurante::Completo->incluye($capacidad));
        $this->assertSame($sitioMenu, PlanRestaurante::SitioMenu->incluye($capacidad));
        $this->assertSame($soloMenu, PlanRestaurante::SoloMenu->incluye($capacidad));
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
        $this->assertSame(['completo', 'sitio_menu', 'solo_menu'], array_map(fn (PlanRestaurante $p): string => $p->value, PlanRestaurante::cases()));

        foreach (PlanRestaurante::cases() as $plan) {
            $this->assertLessThanOrEqual(20, strlen($plan->value));
        }
    }

    public function test_un_valor_desconocido_o_vacio_cae_al_plan_mas_restrictivo(): void
    {
        $this->assertSame(PlanRestaurante::SoloMenu, PlanRestaurante::desde('premium'));
        $this->assertSame(PlanRestaurante::SoloMenu, PlanRestaurante::desde(''));
        $this->assertSame(PlanRestaurante::SoloMenu, PlanRestaurante::desde(null));
        $this->assertSame(PlanRestaurante::Completo, PlanRestaurante::desde('completo'));
    }
}
