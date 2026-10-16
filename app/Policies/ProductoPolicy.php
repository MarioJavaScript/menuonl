<?php

namespace App\Policies;

/**
 * Dishes: every restaurant role manages them (RF-14, RF-15); nobody force-deletes (RNF-07).
 */
class ProductoPolicy extends MatrizPolicy
{
    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'editor' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
    ];
}
