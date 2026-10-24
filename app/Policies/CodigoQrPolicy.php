<?php

namespace App\Policies;

use App\Enums\Capacidad;

/**
 * QR codes: the editor creates and edits them (diagram 6.4), but only propietario and
 * superadmin delete them, since the specification does not give the editor that power.
 */
class CodigoQrPolicy extends MatrizPolicy
{
    protected const ?Capacidad CAPACIDAD = Capacidad::Qr;

    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore'],
        'editor' => ['viewAny', 'view', 'create', 'update'],
    ];
}
