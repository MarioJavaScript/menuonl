<?php

namespace App\Policies;

/**
 * Invitations: only propietario and superadmin invite and revoke (RF-13). They are never edited.
 */
class InvitacionPolicy extends MatrizPolicy
{
    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'delete'],
        'propietario' => ['viewAny', 'view', 'create', 'delete'],
        'editor' => [],
    ];
}
