<?php

namespace App\Policies;

/**
 * Contacts: the editor manages them too (RF-18).
 */
class ContactoPolicy extends MatrizPolicy
{
    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'editor' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
    ];
}
