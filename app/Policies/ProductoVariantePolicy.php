<?php

namespace App\Policies;

use App\Models\ProductoVariante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;

class ProductoVariantePolicy
{
    /**
     * Determine whether the user can view any variants.
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
     * Determine whether the user can view the variant.
     */
    public function view(User $user, ProductoVariante $variante): bool
    {
        $producto = $variante->producto;

        return $this->canAccessItem($user, $producto?->restaurante_id);
    }

    /**
     * Determine whether the user can create variants.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the variant.
     */
    public function update(User $user, ProductoVariante $variante): bool
    {
        $producto = $variante->producto;

        return $this->canAccessItem($user, $producto?->restaurante_id);
    }

    /**
     * Determine whether the user can delete the variant.
     */
    public function delete(User $user, ProductoVariante $variante): bool
    {
        $producto = $variante->producto;

        return $this->canAccessItem($user, $producto?->restaurante_id);
    }

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
