<?php

namespace App\Policies;

use App\Enums\Capacidad;

/**
 * Menu categories: every restaurant role manages them (RF-14); nobody force-deletes (RNF-07).
 */
class CategoriaPolicy extends MatrizPolicy
{
    protected const ?Capacidad CAPACIDAD = Capacidad::EditarMenu;

    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'editor' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
    ];
}
