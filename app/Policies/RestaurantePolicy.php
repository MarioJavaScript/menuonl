<?php

namespace App\Policies;

use App\Models\Restaurante;
use App\Models\User;

class RestaurantePolicy
{
    /**
     * Determine whether the user can view any restaurants.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can view the restaurant.
     */
    public function view(User $user, Restaurante $restaurante): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->restaurantes()
            ->where('restaurantes.id', $restaurante->id)
            ->where('restaurantes.estado', 'activo')
            ->exists();
    }

    /**
     * Determine whether the user can create restaurants.
     */
    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can update the restaurant settings.
     */
    public function update(User $user, Restaurante $restaurante): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->esPropietarioDe($restaurante);
    }

    /**
     * Determine whether the user can delete the restaurant.
     */
    public function delete(User $user, Restaurante $restaurante): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can restore the restaurant.
     */
    public function restore(User $user, Restaurante $restaurante): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can permanently delete the restaurant.
     */
    public function forceDelete(User $user, Restaurante $restaurante): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can suspend the restaurant.
     */
    public function suspend(User $user, Restaurante $restaurante): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can reactivate the restaurant.
     */
    public function reactivate(User $user, Restaurante $restaurante): bool
    {
        return $user->isSuperadmin();
    }
}
