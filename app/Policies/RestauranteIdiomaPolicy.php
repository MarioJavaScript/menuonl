<?php

namespace App\Policies;

/**
 * Active languages are part of the restaurant settings (RF-19): the editor can only read them.
 */
class RestauranteIdiomaPolicy extends MatrizPolicy
{
    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'reorder'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'reorder'],
        'editor' => ['viewAny', 'view'],
    ];
}
