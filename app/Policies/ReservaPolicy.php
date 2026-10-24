<?php

namespace App\Policies;

use App\Enums\Capacidad;

/**
 * Reservations are handled at the host desk: every restaurant role manages them; nobody force-deletes.
 */
class ReservaPolicy extends MatrizPolicy
{
    protected const ?Capacidad CAPACIDAD = Capacidad::Reservas;

    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'editor' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'gerente' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'recepcion' => ['viewAny', 'view', 'create', 'update', 'delete'],
    ];
}
