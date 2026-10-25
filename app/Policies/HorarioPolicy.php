<?php

namespace App\Policies;

use App\Enums\Capacidad;

/**
 * Opening hours: the editor manages them too (RF-18).
 */
class HorarioPolicy extends MatrizPolicy
{
    protected const ?Capacidad CAPACIDAD = Capacidad::EditarHorario;

    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'editor' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
    ];
}
