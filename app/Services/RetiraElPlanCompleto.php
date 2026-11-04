<?php

namespace App\Services;

use App\Enums\PlanRestaurante;
use Illuminate\Support\Facades\DB;

/**
 * Datos del plan retirado "Completo", antes de cambiar el valor por defecto de `restaurantes.plan` (la migración
 * 2026_11_04_201124_planes_aura_y_lite llama a esta clase).
 *
 * Cada restaurante en `completo` pasa a `sitio_menu` (Aura, que ahora incluye todo lo que incluía Completo) y deja una
 * fila en `historial_cambios` sin usuario (el historial la muestra como "Sistema") con el plan de antes y el de después.
 * Los restaurantes borrados con soft delete también se mueven, para que no vuelvan con un plan que ya no existe.
 *
 * Solo usa el generador de consultas, sin SQL propio de un motor, para correr igual en SQLite y MariaDB. Se separó de la
 * migración para probar la lógica con filas sembradas sin revertir migraciones.
 */
class RetiraElPlanCompleto
{
    /**
     * @return int Restaurantes movidos
     */
    public function __invoke(): int
    {
        $retirado = PlanRestaurante::VALOR_RETIRADO_COMPLETO;
        $destino = PlanRestaurante::SitioMenu->value;

        return DB::transaction(function () use ($retirado, $destino): int {
            $ids = DB::table('restaurantes')
                ->where('plan', $retirado)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            $ahora = now();

            DB::table('historial_cambios')->insert($ids->map(fn (int|string $id): array => [
                'restaurante_id' => $id,
                'usuario_id' => null,
                'entidad' => 'restaurante',
                'entidad_id' => $id,
                'accion' => 'actualizar',
                'cambios' => json_encode(['plan' => ['antes' => $retirado, 'despues' => $destino]]),
                'created_at' => $ahora,
            ])->all());

            return DB::table('restaurantes')->whereIn('id', $ids)->update(['plan' => $destino]);
        });
    }
}
