<?php

namespace App\Policies;

use App\Enums\Capacidad;

/**
 * Gallery photos belong to the public site: propietario and gerente manage them (superadmin too). They only show on
 * the site, so the ceiling is the site itself: Lite (no site) leaves them to the superadmin.
 */
class GaleriaImagenPolicy extends MatrizPolicy
{
    protected const ?Capacidad CAPACIDAD = Capacidad::Sitio;

    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'gerente' => ['viewAny', 'view', 'create', 'update', 'delete'],
    ];
}
