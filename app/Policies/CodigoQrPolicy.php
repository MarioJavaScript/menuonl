<?php

namespace App\Policies;

use App\Models\CodigoQr;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;

class CodigoQrPolicy
{
    /**
     * Determine whether the user can view any QR codes.
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
     * Determine whether the user can view the QR code.
     */
    public function view(User $user, CodigoQr $codigoQr): bool
    {
        return $this->canAccessItem($user, $codigoQr->restaurante_id);
    }

    /**
     * Determine whether the user can create QR codes.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the QR code.
     */
    public function update(User $user, CodigoQr $codigoQr): bool
    {
        return $this->canAccessItem($user, $codigoQr->restaurante_id);
    }

    /**
     * Determine whether the user can delete the QR code.
     */
    public function delete(User $user, CodigoQr $codigoQr): bool
    {
        return $this->canAccessItem($user, $codigoQr->restaurante_id);
    }

    /**
     * Determine whether the user can restore the QR code.
     */
    public function restore(User $user, CodigoQr $codigoQr): bool
    {
        return $this->canAccessItem($user, $codigoQr->restaurante_id);
    }

    /**
     * Determine whether the user can permanently delete the QR code.
     */
    public function forceDelete(User $user, CodigoQr $codigoQr): bool
    {
        return $this->canAccessItem($user, $codigoQr->restaurante_id);
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
