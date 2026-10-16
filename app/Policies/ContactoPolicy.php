<?php

namespace App\Policies;

use App\Models\Contacto;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;

class ContactoPolicy
{
    /**
     * Determine whether the user can view any contacts.
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
     * Determine whether the user can view the contact.
     */
    public function view(User $user, Contacto $contacto): bool
    {
        return $this->canAccessItem($user, $contacto->restaurante_id);
    }

    /**
     * Determine whether the user can create contacts.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the contact.
     */
    public function update(User $user, Contacto $contacto): bool
    {
        return $this->canAccessItem($user, $contacto->restaurante_id);
    }

    /**
     * Determine whether the user can delete the contact.
     */
    public function delete(User $user, Contacto $contacto): bool
    {
        return $this->canAccessItem($user, $contacto->restaurante_id);
    }

    /**
     * Determine whether the user can restore the contact.
     */
    public function restore(User $user, Contacto $contacto): bool
    {
        return $this->canAccessItem($user, $contacto->restaurante_id);
    }

    /**
     * Determine whether the user can permanently delete the contact.
     */
    public function forceDelete(User $user, Contacto $contacto): bool
    {
        return $this->canAccessItem($user, $contacto->restaurante_id);
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
