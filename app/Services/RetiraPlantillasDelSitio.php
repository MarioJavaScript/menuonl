<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Datos de las plantillas del sitio que se retiran, antes de cambiar el valor por defecto de `restaurantes.sitio_plantilla`
 * (la migración llama a esta clase con "a" y "c" hacia "b").
 *
 * Cada restaurante que usaba una plantilla retirada pasa a la de destino y deja una fila en `historial_cambios` sin
 * usuario (el historial la muestra como "Sistema") con la plantilla de antes y la de después. Los restaurantes borrados
 * con soft delete también se mueven, para que no vuelvan con una plantilla que ya no existe.
 *
 * Solo usa el generador de consultas, sin SQL propio de un motor, para correr igual en SQLite y MariaDB. Se separó de la
 * migración para probar la lógica con filas sembradas sin revertir migraciones.
 */
class RetiraPlantillasDelSitio
{
    /**
     * @param  list<string>  $retiradas
     * @return int Restaurantes movidos
     */
    public function __invoke(array $retiradas, string $destino): int
    {
        return DB::transaction(function () use ($retiradas, $destino): int {
            $restaurantes = DB::table('restaurantes')
                ->whereIn('sitio_plantilla', $retiradas)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'sitio_plantilla']);

            if ($restaurantes->isEmpty()) {
                return 0;
            }

            $ahora = now();

            DB::table('historial_cambios')->insert($restaurantes->map(fn (object $restaurante): array => [
                'restaurante_id' => $restaurante->id,
                'usuario_id' => null,
                'entidad' => 'restaurante',
                'entidad_id' => $restaurante->id,
                'accion' => 'actualizar',
                'cambios' => json_encode(['sitio_plantilla' => ['antes' => $restaurante->sitio_plantilla, 'despues' => $destino]]),
                'created_at' => $ahora,
            ])->all());

            return DB::table('restaurantes')->whereIn('id', $restaurantes->pluck('id'))->update(['sitio_plantilla' => $destino]);
        });
    }
}
