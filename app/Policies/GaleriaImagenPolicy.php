<?php

namespace App\Policies;

/**
 * Gallery photos belong to the public site: propietario and gerente manage them (superadmin too).
 */
class GaleriaImagenPolicy extends MatrizPolicy
{
    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'gerente' => ['viewAny', 'view', 'create', 'update', 'delete'],
    ];
}
