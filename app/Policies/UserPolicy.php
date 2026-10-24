<?php

namespace App\Policies;

use App\Enums\Capacidad;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Policies\Concerns\ConsultaElPlanDelRestaurante;
use Illuminate\Support\Facades\DB;

/**
 * Users of the active restaurant: propietario and superadmin list them and revoke access (RF-13).
 * Accounts are created through invitations, never from the panel, and are not edited here.
 */
class UserPolicy
{
    use ConsultaElPlanDelRestaurante;

    public function viewAny(User $actor): bool
    {
        if ($actor->isSuperadmin()) {
            return true;
        }

        $restauranteId = RestauranteScope::resolveRestauranteId();

        return $actor->esPropietarioDe($restauranteId) && $this->planIncluye($restauranteId, Capacidad::Equipo);
    }

    public function view(User $actor, User $objetivo): bool
    {
        return $this->viewAny($actor) && $this->esMiembroDelRestauranteActivo($objetivo);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, User $objetivo): bool
    {
        return false;
    }

    public function delete(User $actor, User $objetivo): bool
    {
        return false;
    }

    /**
     * Remove the target's access to the active restaurant (the account itself is kept).
     *
     * You cannot revoke yourself, and the last propietario can never be removed.
     */
    public function revocarAcceso(User $actor, User $objetivo): bool
    {
        $restauranteId = RestauranteScope::resolveRestauranteId();

        if ($restauranteId === null || $actor->is($objetivo) || ! $this->viewAny($actor)) {
            return false;
        }

        if ($objetivo->rolEn($restauranteId) === null) {
            return false;
        }

        if ($objetivo->esPropietarioDe($restauranteId)) {
            return DB::table('restaurante_usuario')
                ->where('restaurante_id', $restauranteId)
                ->where('rol', 'propietario')
                ->count() > 1;
        }

        return true;
    }

    private function esMiembroDelRestauranteActivo(User $objetivo): bool
    {
        return $objetivo->rolEn(RestauranteScope::resolveRestauranteId()) !== null;
    }
}
