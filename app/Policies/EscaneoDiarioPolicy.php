<?php

namespace App\Policies;

use App\Enums\Capacidad;

/**
 * Métricas de escaneos: las ve el propietario (si su plan incluye métricas) y el superadmin; nadie las edita desde la
 * aplicación, porque se escriben solas al escanear. El conteo corre para todos los planes; el plan solo decide quién lo ve.
 */
class EscaneoDiarioPolicy extends MatrizPolicy
{
    protected const ?Capacidad CAPACIDAD = Capacidad::Metricas;

    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view'],
        'propietario' => ['viewAny', 'view'],
    ];
}
