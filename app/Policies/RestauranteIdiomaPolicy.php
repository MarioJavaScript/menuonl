<?php

namespace App\Policies;

use App\Models\RestauranteIdioma;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;

class RestauranteIdiomaPolicy
{
    /**
     * Determine whether the user can view any configured languages.
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
     * Determine whether the user can view the configured language.
     */
    public function view(User $user, RestauranteIdioma $restauranteIdioma): bool
    {
        return $this->canAccessItem($user, $restauranteIdioma->restaurante_id);
    }

    /**
     * Determine whether the user can configure languages (Propietario / Superadmin).
     */
    public function create(User $user): bool
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
                ->where('restaurante_usuario.rol', 'propietario')
                ->exists();
        }

        return $user->restaurantes()
            ->where('restaurante_usuario.rol', 'propietario')
            ->exists();
    }

    /**
     * Determine whether the user can update the configured language.
     */
    public function update(User $user, RestauranteIdioma $restauranteIdioma): bool
    {
        return $this->canManageLanguage($user, $restauranteIdioma);
    }

    /**
     * Determine whether the user can delete the configured language.
     */
    public function delete(User $user, RestauranteIdioma $restauranteIdioma): bool
    {
        return $this->canManageLanguage($user, $restauranteIdioma);
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

    protected function canManageLanguage(User $user, RestauranteIdioma $idioma): bool
    {
        if (! $user->activo) {
            return false;
        }

        $restauranteId = RestauranteScope::getRestauranteId();
        if ($restauranteId !== null && (int) $idioma->restaurante_id !== (int) $restauranteId) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->restaurantes()
            ->where('restaurantes.id', $idioma->restaurante_id)
            ->where('restaurante_usuario.rol', 'propietario')
            ->exists();
    }
}
