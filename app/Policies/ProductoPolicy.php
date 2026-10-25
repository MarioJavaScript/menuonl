<?php

namespace App\Policies;

use App\Enums\Capacidad;

/**
 * Dishes: every restaurant role manages them (RF-14, RF-15); nobody force-deletes (RNF-07).
 */
class ProductoPolicy extends MatrizPolicy
{
    protected const ?Capacidad CAPACIDAD = Capacidad::EditarMenu;

    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'editor' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'gerente' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
    ];
}
