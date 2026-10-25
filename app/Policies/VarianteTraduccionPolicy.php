<?php

namespace App\Policies;

use App\Enums\Capacidad;
use Illuminate\Database\Eloquent\Model;

/**
 * Variant translations have no restaurante_id; they are authorized through variant and product.
 */
class VarianteTraduccionPolicy extends MatrizPolicy
{
    protected const ?Capacidad CAPACIDAD = Capacidad::EditarMenu;

    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'editor' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
    ];

    /**
     * Authorized through the parent: the restaurant is the one of the variante.
     */
    protected function restauranteDe(Model $modelo): ?int
    {
        return $modelo->variante()->first()?->producto()->withTrashed()->first()?->restaurante_id;
    }
}
