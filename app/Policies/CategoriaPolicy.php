<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;

class CategoriaPolicy
{
    /**
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        if (! $user->activo) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        $restauranteId = RestauranteScope::getRestauranteId();
        if ($restauranteId !== null) {
            return $user->restaurantes()
                ->where('restaurantes.id', $restauranteId)
                ->whereIn('restaurante_usuario.rol', ['propietario', 'editor'])
                ->exists();
        }

        return $user->restaurantes()
            ->whereIn('restaurante_usuario.rol', ['propietario', 'editor'])
            ->exists();
    }

    /**
     * Determine whether the user can view the category.
     */
    public function view(User $user, Categoria $categoria): bool
    {
        return $this->canAccessItem($user, $categoria->restaurante_id);
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, Categoria $categoria): bool
    {
        return $this->canAccessItem($user, $categoria->restaurante_id);
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, Categoria $categoria): bool
    {
        return $this->canAccessItem($user, $categoria->restaurante_id);
    }

    /**
     * Determine whether the user can restore the category.
     */
    public function restore(User $user, Categoria $categoria): bool
    {
        return $this->canAccessItem($user, $categoria->restaurante_id);
    }

    /**
     * Determine whether the user can permanently delete the category.
     */
    public function forceDelete(User $user, Categoria $categoria): bool
    {
        if (! $user->activo) {
            return false;
        }

        $restauranteId = RestauranteScope::getRestauranteId();
        if ($restauranteId !== null && (int) $categoria->restaurante_id !== (int) $restauranteId) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->restaurantes()
            ->where('restaurantes.id', $categoria->restaurante_id)
            ->where('restaurante_usuario.rol', 'propietario')
            ->exists();
    }

    /**
     * Check if user has operational access to items for this restaurant.
     */
    protected function canAccessItem(User $user, ?int $itemRestauranteId): bool
    {
        if (! $user->activo) {
            return false;
        }

        $restauranteId = RestauranteScope::getRestauranteId();
        if ($restauranteId !== null && (int) $itemRestauranteId !== (int) $restauranteId) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        if (! $itemRestauranteId) {
            return false;
        }

        return $user->restaurantes()
            ->where('restaurantes.id', $itemRestauranteId)
            ->whereIn('restaurante_usuario.rol', ['propietario', 'editor'])
            ->exists();
    }
}
