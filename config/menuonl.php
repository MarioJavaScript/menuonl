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
    | reaches GD. Dimensions (4096 px per side, 16 MP total) are capped separately by WebpImageService.
    | The server limits upload_max_filesize and post_max_size must be at least this value (see docs).
    |
    */

    'reservas' => [
        'max_personas' => (int) env('RESERVAS_MAX_PERSONAS', 12),
        'max_dias' => (int) env('RESERVAS_MAX_DIAS', 60),
        'anticipacion_min' => (int) env('RESERVAS_ANTICIPACION_MIN', 30),
        'espera_min_segundos' => (int) env('RESERVAS_ESPERA_MIN_SEGUNDOS', 2),
        // Historial de quién confirmó, canceló o movió una reserva; `menuonl:purgar-reserva-eventos` borra lo más viejo.
        'eventos_retencion_meses' => max(1, (int) env('RESERVAS_EVENTOS_RETENCION_MESES', 12)),
    ],

    'imagenes' => [
        'max_bytes' => (int) env('IMAGENES_MAX_BYTES', 10 * 1024 * 1024),
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
    | Contacto de soporte
    |--------------------------------------------------------------------------
    |
    | Número de WhatsApp (solo dígitos, con lada, formato wa.me) que se ofrece en el mensaje de
    | "Solo menú" (docs/planes.md, D10). Vacío = no se muestra el botón de WhatsApp.
    | Ver App\Services\ContactoSoporte.
    |
    */

    'soporte_whatsapp' => env('SOPORTE_WHATSAPP'),

    /*
    |--------------------------------------------------------------------------
    | Cabeceras de seguridad
    |--------------------------------------------------------------------------
    |
    | `hsts_segundos`: duración de Strict-Transport-Security. Solo se envía con APP_ENV=production y una petición https,
    | y sin includeSubDomains ni preload. 0 lo desactiva. Valor inicial prudente: 7 días (ver docs/despliegue.md, 6.1).
    |
    */

    'seguridad' => [
        'hsts_segundos' => (int) env('HSTS_MAX_AGE', 604800),
    ],

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
        // Hora del respaldo diario en la zona indicada (poco tráfico en México: ~04:00 hora de Ciudad de México).
        'hora' => env('BACKUP_TIME', '04:00'),
        'zona_horaria' => env('BACKUP_TIMEZONE', 'America/Mexico_City'),
        // Usuario de base de datos propio y de solo lectura para el respaldo (opcional; si falta se usa el de la aplicación).
        'db_usuario' => env('BACKUP_DB_USERNAME'),
        'db_password' => env('BACKUP_DB_PASSWORD'),
        // Cliente y credenciales para restaurar (opcionales; si faltan se usa la conexión de la aplicación).
        'mysql_path' => env('BACKUP_MYSQL_PATH', 'mysql'),
        'restauracion' => [
            'usuario' => env('RESTORE_DB_USERNAME'),
            'password' => env('RESTORE_DB_PASSWORD'),
        ],
        // verificar-produccion avisa cuando el último respaldo es más viejo que esto.
        'horas_maximas_sin_respaldo' => (int) env('BACKUP_MAX_AGE_HOURS', 36),
    ],

    /*
    |--------------------------------------------------------------------------
    | Métricas de escaneos QR/NFC
    |--------------------------------------------------------------------------
    |
    | Agregado diario por código (sin IP, user-agent ni cookies). `menuonl:purgar-escaneos` borra lo más viejo que la
    | retención; 400 días (13 meses) permiten comparar un año completo con el anterior. Nunca menos de 31.
    |
    */

    'metricas' => [
        'retencion_dias' => max(31, (int) env('METRICAS_RETENCION_DIAS', 400)),
    ],
];
