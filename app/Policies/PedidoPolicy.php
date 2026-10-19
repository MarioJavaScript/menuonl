<?php

namespace App\Policies;

/**
 * Orders move through the kitchen flow: every restaurant role manages them; nobody force-deletes.
 */
class PedidoPolicy extends MatrizPolicy
{
    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'editor' => ['viewAny', 'view', 'create', 'update', 'delete'],
    ];
}
