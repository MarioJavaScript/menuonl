<?php

namespace App\Policies;

use App\Models\Invitacion;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;

class InvitacionPolicy
{
    /**
     * Determine whether the user can view any invitations.
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
                ->where('restaurante_usuario.rol', 'propietario')
                ->exists();
        }

        return $user->restaurantes()
            ->where('restaurante_usuario.rol', 'propietario')
            ->exists();
    }

    /**
     * Determine whether the user can view the invitation.
     */
    public function view(User $user, Invitacion $invitacion): bool
    {
        return $this->canManageInvitation($user, $invitacion);
    }

    /**
     * Determine whether the user can create invitations.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the invitation.
     */
    public function update(User $user, Invitacion $invitacion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the invitation (revoke).
     */
    public function delete(User $user, Invitacion $invitacion): bool
    {
        return $this->canManageInvitation($user, $invitacion);
    }

    /**
     * Determine whether the user can restore the invitation.
     */
    public function restore(User $user, Invitacion $invitacion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the invitation.
     */
    public function forceDelete(User $user, Invitacion $invitacion): bool
    {
        return $this->canManageInvitation($user, $invitacion);
    }

    /**
     * Check if user is propietario of the restaurant issuing the invitation.
     */
    protected function canManageInvitation(User $user, Invitacion $invitacion): bool
    {
        if (! $user->activo) {
            return false;
        }

        $restauranteId = RestauranteScope::getRestauranteId();
        if ($restauranteId !== null && (int) $invitacion->restaurante_id !== (int) $restauranteId) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->restaurantes()
            ->where('restaurantes.id', $invitacion->restaurante_id)
            ->where('restaurante_usuario.rol', 'propietario')
            ->exists();
    }
}
