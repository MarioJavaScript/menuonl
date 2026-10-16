<?php

namespace App\Policies;

use Illuminate\Database\Eloquent\Model;

/**
 * Product translations have no restaurante_id; they are authorized through their product.
 */
class ProductoTraduccionPolicy extends MatrizPolicy
{
    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'editor' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
    ];

    /**
     * Authorized through the parent: the restaurant is the one of the producto.
     */
    protected function restauranteDe(Model $modelo): ?int
    {
        return $modelo->producto()->withTrashed()->first()?->restaurante_id;
    }
}
