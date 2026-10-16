<?php

namespace App\Policies;

use App\Models\Producto;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;

class ProductoPolicy
{
    /**
     * Determine whether the user can view any products.
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
     * Determine whether the user can view the product.
     */
    public function view(User $user, Producto $producto): bool
    {
        return $this->canAccessItem($user, $producto->restaurante_id);
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Producto $producto): bool
    {
        return $this->canAccessItem($user, $producto->restaurante_id);
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user, Producto $producto): bool
    {
        return $this->canAccessItem($user, $producto->restaurante_id);
    }

    /**
     * Determine whether the user can restore the product.
     */
    public function restore(User $user, Producto $producto): bool
    {
        return $this->canAccessItem($user, $producto->restaurante_id);
    }

    /**
     * Determine whether the user can permanently delete the product.
     */
    public function forceDelete(User $user, Producto $producto): bool
    {
        if (! $user->activo) {
            return false;
        }

        $restauranteId = RestauranteScope::getRestauranteId();
        if ($restauranteId !== null && (int) $producto->restaurante_id !== (int) $restauranteId) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->restaurantes()
            ->where('restaurantes.id', $producto->restaurante_id)
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
