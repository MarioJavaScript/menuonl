<?php

namespace App\Policies;

/**
 * Contenido del sitio del restaurante: lo gestionan el propietario y el superadmin. El editor no tiene acceso.
 */
class SitioConfiguracionPolicy extends MatrizPolicy
{
    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'reorder'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'reorder'],
    ];
}
