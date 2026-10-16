<?php

namespace App\Policies;

use App\Models\Etiqueta;
use App\Models\User;

class EtiquetaPolicy
{
    /**
     * Determine whether the user can view any global dietary tags.
     */
    public function viewAny(User $user): bool
    {
        return (bool) $user->activo;
    }

    /**
     * Determine whether the user can view the global dietary tag.
     */
    public function view(User $user, Etiqueta $etiqueta): bool
    {
        return (bool) $user->activo;
    }

    /**
     * Determine whether the user can create global dietary tags.
     */
    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can update the global dietary tag.
     */
    public function update(User $user, Etiqueta $etiqueta): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can delete the global dietary tag.
     */
    public function delete(User $user, Etiqueta $etiqueta): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can restore the global dietary tag.
     */
    public function restore(User $user, Etiqueta $etiqueta): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can permanently delete the global dietary tag.
     */
    public function forceDelete(User $user, Etiqueta $etiqueta): bool
    {
        return $user->isSuperadmin();
    }
}
