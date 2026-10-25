<?php

namespace App\Policies;

use App\Enums\Capacidad;
use App\Models\HistorialCambio;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Policies\Concerns\ConsultaElPlanDelRestaurante;

class HistorialCambioPolicy
{
    use ConsultaElPlanDelRestaurante;

    /**
     * Determine whether the user can view audit log records.
     */
    public function viewAny(User $user): bool
    {
        if (! $user->activo) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        $restauranteId = RestauranteScope::resolveRestauranteId();
        if ($restauranteId !== null) {
            return $this->planIncluye((int) $restauranteId, Capacidad::Equipo) && $user->restaurantes()
                ->where('restaurantes.id', $restauranteId)
                ->where('restaurante_usuario.rol', 'propietario')
                ->exists();
        }

        return $user->restaurantes()
            ->where('restaurante_usuario.rol', 'propietario')
            ->exists();
    }

    /**
     * Determine whether the user can view the specific audit log record.
     */
    public function view(User $user, HistorialCambio $historialCambio): bool
    {
        if (! $user->activo) {
            return false;
        }

        $restauranteId = RestauranteScope::getRestauranteId();
        if ($restauranteId !== null && (int) $historialCambio->restaurante_id !== (int) $restauranteId) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->planIncluye((int) $historialCambio->restaurante_id, Capacidad::Equipo) && $user->restaurantes()
            ->where('restaurantes.id', $historialCambio->restaurante_id)
            ->where('restaurante_usuario.rol', 'propietario')
            ->exists();
    }

    /**
     * Determine whether the user can create audit records (immutable: false).
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update audit records (immutable: false).
     */
    public function update(User $user, HistorialCambio $historialCambio): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete audit records (tamper-proof: false).
     */
    public function delete(User $user, HistorialCambio $historialCambio): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore audit records (false).
     */
    public function restore(User $user, HistorialCambio $historialCambio): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete audit records (false).
     */
    public function forceDelete(User $user, HistorialCambio $historialCambio): bool
    {
        return false;
    }
}
