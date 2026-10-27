<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Datos de un rol del equipo que se retira, antes de quitarlo de los enums de `restaurante_usuario.rol` e
 * `invitaciones.rol` (las migraciones llaman a esta clase con "cocina" y "recepcion").
 *
 * - Las membresías pasan al rol de destino y se desactivan: conservan su historial, nadie gana acceso, y el propietario
 *   decide si las reactiva o las quita.
 * - Las invitaciones sin aceptar se borran (ya no hay rol que ofrecer); las aceptadas pasan al rol de destino.
 *
 * Solo usa el generador de consultas, sin SQL propio de un motor, para correr igual en SQLite y MariaDB. Se separó de la
 * migración para probar la lógica con filas sembradas sin revertir migraciones.
 */
class ReasignaRolRetirado
{
    /**
     * @return array{membresias: int, invitaciones_aceptadas: int, invitaciones_borradas: int}
     */
    public function __invoke(string $rolRetirado, string $rolDestino): array
    {
        return DB::transaction(fn (): array => [
            'membresias' => DB::table('restaurante_usuario')
                ->where('rol', $rolRetirado)
                ->update(['rol' => $rolDestino, 'activo' => false]),
            'invitaciones_aceptadas' => DB::table('invitaciones')
                ->where('rol', $rolRetirado)
                ->whereNotNull('aceptada_en')
                ->update(['rol' => $rolDestino]),
            'invitaciones_borradas' => DB::table('invitaciones')
                ->where('rol', $rolRetirado)
                ->whereNull('aceptada_en')
                ->delete(),
        ]);
    }
}
