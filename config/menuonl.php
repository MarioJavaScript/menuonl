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

    /*
    |--------------------------------------------------------------------------
    | Panel login attempt limit
    |--------------------------------------------------------------------------
    |
    | Failed attempts per email + IP and per IP; reaching either blocks the login
    | for `bloqueo_segundos` (see App\Filament\Pages\Auth\Login).
    |
    */

    'login' => [
        'max_intentos_cuenta' => (int) env('LOGIN_MAX_INTENTOS_CUENTA', 5),
        'max_intentos_ip' => (int) env('LOGIN_MAX_INTENTOS_IP', 20),
        'bloqueo_segundos' => (int) env('LOGIN_BLOQUEO_SEGUNDOS', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Restaurant images (logo, cover, product photo)
    |--------------------------------------------------------------------------
    |
    | `max_bytes` is checked on the uploaded file BEFORE it is decoded, so a huge file never
    | reaches GD. Dimensions are capped separately (4096 px) by WebpImageService.
    |
    */

    'imagenes' => [
        'max_bytes' => (int) env('IMAGENES_MAX_BYTES', 2 * 1024 * 1024),
        'carpetas' => ['logos', 'portadas', 'productos'],
    ],

];
