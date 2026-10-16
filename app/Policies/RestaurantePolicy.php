<?php

namespace App\Policies;

use App\Models\Restaurante;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Restaurants: the superadmin lists, creates and suspends them (RF-30, RF-31). Members only see
 * their own restaurant, and only the propietario edits its settings (RF-19). Nobody deletes.
 */
class RestaurantePolicy extends MatrizPolicy
{
    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update'],
        'propietario' => ['view', 'update'],
        'editor' => ['view'],
    ];

    /**
     * A restaurant is its own owner.
     */
    protected function restauranteDe(Model $modelo): ?int
    {
        return $modelo instanceof Restaurante ? (int) $modelo->getKey() : null;
    }

    /**
     * The platform-wide list is only for the superadmin; members never list other restaurants.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Only the superadmin registers new restaurants.
     */
    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }
}
