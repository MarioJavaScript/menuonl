<?php

namespace App\Policies;

/**
 * Reservations are handled at the host desk: every restaurant role manages them; nobody force-deletes.
 */
class ReservaPolicy extends MatrizPolicy
{
    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'editor' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'gerente' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'recepcion' => ['viewAny', 'view', 'create', 'update', 'delete'],
    ];
}
