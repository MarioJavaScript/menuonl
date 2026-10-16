<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any platform users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can view a specific platform user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can delete the user.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can restore the user.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Determine whether the user can permanently delete the user.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isSuperadmin();
    }
}
