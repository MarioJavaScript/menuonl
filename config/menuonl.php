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

    'reservas' => [
        'max_personas' => (int) env('RESERVAS_MAX_PERSONAS', 12),
        'max_dias' => (int) env('RESERVAS_MAX_DIAS', 60),
        'anticipacion_min' => (int) env('RESERVAS_ANTICIPACION_MIN', 30),
        'espera_min_segundos' => (int) env('RESERVAS_ESPERA_MIN_SEGUNDOS', 2),
    ],

    'imagenes' => [
        'max_bytes' => (int) env('IMAGENES_MAX_BYTES', 2 * 1024 * 1024),
        'carpetas' => ['logos', 'portadas', 'productos', 'galeria'],
        'max_galeria' => (int) env('IMAGENES_MAX_GALERIA', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | IPs/CIDR (TRUSTED_PROXIES, separadas por comas) cuyos X-Forwarded-* se aceptan; de ellos
    | sale $request->ip(). Vacío = no se confía en ningún proxy. Un comodín ('*', '**') se
    | descarta siempre: permitiría falsificar la IP y evadir los límites de intentos
    | (docs/02-seguridad.md §6). Ver los rangos de Cloudflare en .env.example.
    |
    */

    'trusted_proxies' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))),
        function (string $proxy): bool {
            if ($proxy === '' || in_array($proxy, ['*', '**', '0.0.0.0/0', '::/0', '0/0'], true)) {
                return false;
            }

            if (str_contains($proxy, '/')) {
                [$subnet, $mask] = explode('/', $proxy, 2);
                if (! filter_var($subnet, FILTER_VALIDATE_IP) || ! ctype_digit($mask)) {
                    return false;
                }
                $maskNum = (int) $mask;
                $isIpv6 = str_contains($subnet, ':');

                return $isIpv6 ? ($maskNum > 0 && $maskNum <= 128) : ($maskNum > 0 && $maskNum <= 32);
            }

            return (bool) filter_var($proxy, FILTER_VALIDATE_IP);
        },
    )),

    /*
    |--------------------------------------------------------------------------
    | Respaldos (RNF-06)
    |--------------------------------------------------------------------------
    |
    | `menuonl:backup` genera un .zip cifrado (AES-256) con el volcado de la base y
    | storage/app/public/restaurantes, lo sube al disco `backups` y borra los de más de
    | `retencion_dias`. Nunca incluye .env ni secretos. Ver App\Services\RespaldoService.
    |
    */

    'respaldos' => [
        'disco' => env('BACKUP_DISK', 'backups'),
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'prefijo' => env('BACKUP_PREFIX', 'menuonl'),
        'retencion_dias' => (int) env('BACKUP_RETENTION_DAYS', 14),
        'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'timeout_segundos' => (int) env('BACKUP_TIMEOUT_SECONDS', 1800),
    ],

];
