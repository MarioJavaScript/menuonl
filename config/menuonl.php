<?php

$requerir2fa = env('REQUIRE_2FA');

return [

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication requirement
    |--------------------------------------------------------------------------
    |
    | When true, superadmins and anyone who is propietario of a restaurant must set up
    | app-based two-factor authentication before using the panel (docs/02-seguridad.md §5).
    | It is ON unless REQUIRE_2FA is explicitly set to false/0/off/no, so a missing, empty or
    | mistyped variable (or APP_ENV) can never switch it off. Turn it off only in local
    | development and tests.
    |
    */

    'requerir_2fa' => ! ($requerir2fa === false
        || (is_string($requerir2fa) && in_array(strtolower(trim($requerir2fa)), ['0', 'false', 'off', 'no'], true))),

];
