# Despliegue a producción: análisis y checklist (Fase 1)

Estado: **solo análisis y documentación. No se cambió código de la aplicación.** Este documento no sustituye a [07-checklist-despliegue.md](07-checklist-despliegue.md) (proxies, respaldos, dominio, CSS): lo ordena, lo completa y lo contrasta con el código real. Donde ambos coinciden, vale lo que diga el código.

Lo que se verificó leyendo el código y ejecutando comandos de solo lectura en el equipo de desarrollo está marcado como **Verificado**. Lo que solo se puede comprobar en el servidor real está en la sección "Qué no se pudo verificar" al final.

Convenciones de las tablas de cada punto:

- **Ya está**: lo que existe en el código, con archivo.
- **Falta**: lo que no existe o no está probado.
- **Riesgo si no se hace**: qué pasa con clientes reales.

Prioridad: **P0** bloquea publicar con clientes reales, **P1** antes del primer cliente de pago, **P2** primeras semanas.

---

## 0. Hallazgos que cambian el plan (léelos primero)

| # | Hallazgo | Prioridad |
|---|---|---|
| H1 | **RESUELTO.** `/{slug}`, `/{slug}/menu`, `/q/{codigo}`, `/m/{slug}` y `/s/{slug}` ya no pasan por `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `ShareErrorsFromSession` ni `PreventRequestForgery` (`routes/web.php`, variable `$middlewareDeSesion`): sin `Set-Cookie`, sin fila en `sessions`, sin token CSRF. Conservan el límite `throttle:menu-publico` y sus `Cache-Control`. Pruebas: `tests/Feature/PaginasPublicasSinSesionTest.php`. Quedan igual con sesión: `/admin`, `/panel`, `/api/v1/panel/*`, `/invitaciones/*` y `/` (portada). Descripción original: **Las páginas públicas mandaban cookies de sesión junto con `Cache-Control: public`.** Medido en local contra `/{slug}/menu` y `/{slug}`: ambas responden con `Set-Cookie: laravel-session` y `XSRF-TOKEN` y `Cache-Control: max-age=30, public`. `/q/{codigo}` también abre sesión (medido con un código inexistente, que da 404; falta medir uno válido). Cloudflare no guarda en caché respuestas con `Set-Cookie` por defecto, así que **hoy el menú no se serviría desde el borde** (RNF-02 y RNF-09 no se cumplirían). Peor: si alguien activa "Cache Everything" para forzarlo, una cookie de sesión de un visitante podría servirse a otros. Además, con `SESSION_DRIVER=database`, cada visita anónima (y cada bot) escribe una fila en `sessions`. | P0 |
| H2 | **RESUELTO.** `DemoRestauranteSeeder` ahora se niega a correr fuera de `local` y `testing` (lanza un error claro y termina con código distinto de cero; prueba en `tests/Feature/GuardaSeederDemoTest.php`). Descripción original: `DemoRestauranteSeeder` crea `superadmin@menuonl.test` con contraseña `password` y no tiene ninguna guarda de entorno.** La guarda (`local`/`testing`) está solo en `DatabaseSeeder`. Un `php artisan db:seed --class=DemoRestauranteSeeder` en producción crea un superadmin con contraseña pública. | P0 |
| H3 | **RESUELTO.** Existe `php artisan menuonl:crear-superadmin` (ver punto 7; pruebas en `tests/Feature/CrearSuperadminTest.php`). Descripción original: **No había forma segura de crear el primer superadmin.** No existe comando; hoy habría que usar `tinker` con `forceFill(['es_superadmin' => true])` (el campo no es asignable en masa). Sin superadmin no se puede crear ningún restaurante ni invitar a nadie. | P0 |
| H4 | **Los trabajos en cola necesitan un proceso `queue:work`** y el repositorio no trae su configuración. `PurgarCacheRestauranteJob` (`app/Jobs/PurgarCacheRestauranteJob.php`) implementa `ShouldQueue` y `QUEUE_CONNECTION=database`: sin worker, la purga de Cloudflare al cambiar un menú o un plan **nunca se ejecuta**. | P0 |
| H5 | **Correo:** `MAIL_MAILER=log` por defecto (`.env.example`, `config/mail.php`). Las invitaciones (`app/Services/InvitacionService.php`) se mandan **sincrónicas** a propósito (el token no debe pasar por la tabla `jobs`). Con el mailer `log` el propietario nunca recibe su invitación; con un SMTP caído, crear un restaurante falla *después* de haberlo guardado. | P0 |
| H6 | **La política de contenido (CSP) no protege de XSS en la práctica y solo existe en las páginas públicas.** Es `default-src 'self' 'unsafe-inline'`, que permite scripts en línea. `/admin/login` no manda CSP, `X-Frame-Options` ni `X-Content-Type-Options` (medido), y en el código solo tres controladores públicos (sitio, menú y `/q`) añaden esas cabeceras, así que `/panel` e `/invitaciones/*` tampoco las tendrían (deducido, no medido). Nadie manda HSTS. | P1 |
| H7 | **El respaldo usa las credenciales de base de datos de la aplicación**, que según [02-seguridad.md](02-seguridad.md) §1 deberían ser solo `SELECT/INSERT/UPDATE/DELETE`. `mysqldump --single-transaction --routines --triggers` necesita más permisos, y las migraciones necesitan DDL. Hay que decidir usuarios de base de datos separados. | P1 |
| H8 | **No hay comando de restauración.** Existe `menuonl:backup-verify` (descifra y revisa), pero restaurar es un procedimiento manual que nunca se ha ensayado. | P1 |
| H9 | **El `.env.example` de la rama actual trae un número de WhatsApp que parece real** (`SOPORTE_WHATSAPP=526333345729`, cambio sin commit). El ejemplo debe ir vacío; el número real solo en el `.env` del servidor. | P1 |
| H10 | **El respaldo diario corre a las 03:00 UTC** (`routes/console.php`, `config/app.php` tiene `timezone => UTC`): son las 21:00 en Ciudad de México, en pleno servicio de cena. | P2 |

---

## 1. Requisitos del servidor

### Checklist

- [ ] **PHP 8.4** (CI y desarrollo usan 8.4: `.github/workflows/ci.yml`, y el PHP local es 8.4.26). `composer.json` acepta `^8.3` y fija `config.platform.php = 8.3.33`; instalar con 8.4 funciona, pero conviene mantener producción y CI en la misma versión menor.
- [ ] Extensiones PHP: `gd` (con soporte WebP: `imagewebp`/`imagecreatefromwebp`, usado por `WebpImageService` y por el PNG de los QR), `intl` (Filament y `Number::format`), `zip` (respaldos cifrados AES-256: `ZipArchive::EM_AES_256`, requiere libzip reciente), `pdo_mysql`, `mbstring`, `curl`, `fileinfo`, `exif` (orientación de fotos de celular), `bcmath`, `openssl`, `redis` si se usa Redis. **Verificado en desarrollo:** todas presentes, y `PASSWORD_ARGON2ID` disponible (`HASH_DRIVER=argon2id`). Hay que repetir esta comprobación en el servidor (`php -m`, `php -r 'var_dump(defined("PASSWORD_ARGON2ID"));'`).
- [ ] Límites de PHP (`php.ini` de FPM y de CLI para los trabajos programados):
  - `upload_max_filesize = 12M`
  - `post_max_size = 14M`
  - `memory_limit = 256M` (el procesador de imágenes calcula 4 bytes por píxel y rechaza lo que no cabe; ver `WebpImageService`)
  - `max_execution_time` razonable en FPM (60) y sin límite en CLI para `menuonl:backup`.
  - `upload_tmp_dir` en un disco con espacio y escribible por el usuario de PHP (ver punto 6.5).
- [ ] Nginx: `client_max_body_size 12M;` (igual que `upload_max_filesize`, o el límite de Nginx corta antes y responde 413 sin el mensaje amable de la app).
- [ ] Coherencia de límites: la app acepta imágenes de hasta **10 MB** (`IMAGENES_MAX_BYTES`, `config/menuonl.php`), Livewire acepta hasta **12 MB** en subidas temporales (`max:12288` por defecto, `vendor/livewire/.../FileUploadConfiguration.php`), PHP 12M y Nginx 12M. Orden correcto: app (10) < Livewire/PHP/Nginx (12) < `post_max_size` (14).
- [ ] **MariaDB** 10.6+ (la especificación) con InnoDB y `utf8mb4`. CI ya corre migraciones y pruebas contra MariaDB 11.4 (`tests-mariadb` en `ci.yml`); usar esa versión o una compatible.
- [ ] Binario de volcado: `mysqldump` o `mariadb-dump` (`BACKUP_MYSQLDUMP_PATH`), instalado en el servidor.
- [ ] Node **22** solo para compilar los recursos (no hace falta en ejecución si se compila en CI y se sube `public/build`).
- [ ] Redis 6+ (la especificación lo menciona para caché y sesiones; el código funciona con `database`, ver punto 2).
- [ ] Supervisor (o systemd) para `queue:work`, y cron para `schedule:run`.
- [ ] Nginx: bloquear PHP dentro de `storage` (`location ~* ^/storage/.*\.php$ { deny all; }`), `X-Content-Type-Options: nosniff` en esa carpeta, ocultar versión (`server_tokens off`), y servir `public/` como raíz (nunca la raíz del proyecto).
- [ ] Firewall: SSH con llaves, fail2ban, MariaDB y Redis solo en `127.0.0.1`, y HTTP/HTTPS aceptando solo rangos de Cloudflare ([02-seguridad.md](02-seguridad.md) §6–7).

### Estado en el código

| Ya está | Falta | Riesgo si no se hace |
|---|---|---|
| CI con PHP 8.4, Node 22, extensiones `gd intl zip`, y MariaDB 11.4 (`.github/workflows/ci.yml`). Límite de 10 MB y mensajes de "demasiado grande" (`config/menuonl.php`, `app/Services/WebpImageService.php`, `app/Filament/Components/ImagenRestauranteUpload.php`). `HASH_DRIVER=argon2id` en `.env.example`. | Ninguna guía de servidor en el repositorio (ejemplo de Nginx, `php.ini`, Supervisor, cron). No hay comprobación automática de extensiones al arrancar. | Sin `gd` con WebP o sin `zip` AES, las subidas o los respaldos fallan en silencio o con errores crípticos. Un `client_max_body_size` menor da 413 sin mensaje para el cliente. |

---

## 2. Variables del `.env` de producción

Se parte de `.env.example`. La columna "Producción" es el valor esperado; las marcadas **NO** son valores de desarrollo que no deben llegar al servidor.

| Variable | Producción | Notas |
|---|---|---|
| `APP_NAME` | `MENUONL` | Título del navegador y pie de los correos. La marca comercial (Menubly/Menuonl) debe decidirse **antes** del primer QR impreso. |
| `APP_ENV` | `production` | **NO** `local`. Con `local` se registra la ruta de desarrollo `/diseno`, el seeder de demostración corre con `db:seed` y otras herramientas de desarrollo (por ejemplo las rutas de Boost) se comportan distinto; tras desplegar, revisar `php artisan route:list` para confirmar que no quedan rutas de desarrollo. `session.secure` también depende de esto. |
| `APP_DEBUG` | `false` | **NO** `true`: expone trazas, variables y rutas. |
| `APP_KEY` | generar con `php artisan key:generate --force` | Guardarla fuera del servidor. Si se pierde o cambia, se invalidan sesiones y lo cifrado. |
| `APP_URL` | `https://dominio-real` | Los QR descargables e impresos codifican esta URL, no el `Host` de la petición. Si está mal, hay que reimprimir todos los QR. |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `es` / `es` | |
| `LOG_CHANNEL` / `LOG_STACK` | `stack` / `daily` | **NO** `single`: un solo archivo sin rotación crece sin límite. `LOG_DAILY_DAYS` (por defecto 14) controla cuántos se guardan. |
| `LOG_LEVEL` | `warning` (o `error`) | **NO** `debug`. |
| `DB_CONNECTION` | `mariadb` | **NO** `sqlite` (es el valor del ejemplo). Más `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (descomentar). |
| `SESSION_DRIVER` | `database` o `redis` | Las páginas públicas ya no abren sesión (H1 resuelto); solo la usan el panel, las invitaciones y la portada. |
| `SESSION_SECURE_COOKIE` / `SESSION_SAME_SITE` | `true` / `lax` | Ya vienen así en el ejemplo. `http_only` es `true` por defecto (`config/session.php`). |
| `SESSION_LIFETIME` | 120 (min) o menos para el panel | |
| `CACHE_STORE` | `redis` (o `database`) | Es el nivel L1 del menú (`MenuCacheService`, TTL 24 h). Con `database` funciona, solo es más lento. |
| `QUEUE_CONNECTION` | `database` o `redis` | Necesita `queue:work` activo (H4). **NO** `sync` en producción si se quiere que la purga no retrase la respuesta; sí es válido como parche temporal. |
| `MAIL_MAILER` y datos SMTP | proveedor real (SMTP/SES/Postmark) | **NO** `log` (H5). `MAIL_FROM_ADDRESS` y `MAIL_FROM_NAME` reales, **NO** `hello@example.com`. Probar un envío real de invitación antes del primer cliente. |
| `FILESYSTEM_DISK` | `local` | Las imágenes usan el disco `public` (`storage/app/public/restaurantes/...`). |
| `REQUIRE_2FA` | `true` (o no definirla) | Está **encendida por defecto** salvo que se ponga explícitamente `false/0/off/no` (`config/menuonl.php`). **NO** apagarla en producción. |
| `HASH_DRIVER` | `argon2id` | |
| `BCRYPT_ROUNDS` | 12 | Solo aplica si se vuelve a bcrypt. |
| `TRUSTED_PROXIES` | rangos de Cloudflare (vienen en el ejemplo) | Vacía si **no** hay Cloudflare delante. Nunca `*` (se descarta). Revisar contra `cloudflare.com/ips-v4` e `ips-v6`. |
| `CLOUDFLARE_ZONE_ID`, `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_DOMAIN` | reales | Token con permiso solo de "Purgar caché" en esa zona. Si faltan, la purga se omite con un `Log::info` (no falla). `CLOUDFLARE_DOMAIN` por defecto es `menuonl.com`. |
| `SOPORTE_WHATSAPP` | solo dígitos con lada, p. ej. `5215512345678` | Vacía = la pantalla de "Tu plan no incluye el panel" no muestra el botón. El número real va solo en el `.env` del servidor (H9). |
| `BACKUP_DISK`, `BACKUP_ACCESS_KEY_ID`, `BACKUP_SECRET_ACCESS_KEY`, `BACKUP_BUCKET`, `BACKUP_ENDPOINT`, `BACKUP_REGION`, `BACKUP_USE_PATH_STYLE_ENDPOINT` | bucket **privado** R2/B2 | Credenciales limitadas a ese bucket. |
| `BACKUP_ARCHIVE_PASSWORD` | contraseña larga y aleatoria | Guardada **fuera** del servidor. Vacía = el respaldo se niega a correr. |
| `BACKUP_RETENTION_DAYS`, `BACKUP_PREFIX`, `BACKUP_MYSQLDUMP_PATH`, `BACKUP_TIMEOUT_SECONDS` | 14 / `menuonl` / `mariadb-dump` si aplica / 1800 | |
| `IMAGENES_MAX_BYTES`, `IMAGENES_MAX_GALERIA` | 10 MB / 12 | Deben respetar los límites del punto 1. |
| `AWS_*`, `REDIS_*`, `MEMCACHED_HOST` | solo lo que se use | **NO** dejar valores de ejemplo. `REDIS_PASSWORD` real si hay Redis. |
| `FRONTEND_URL` | no definir | El frontend Next.js (`frontend/`) es experimental y no forma parte de este despliegue. `config/cors.php` trae orígenes `localhost` fijos; inofensivos sin frontend, pero conviene quitarlos si no se usa. |
| `VITE_APP_NAME` | `${APP_NAME}` | Solo en compilación. |

Reglas generales: el `.env` no se versiona (`.gitignore` ya lo cubre) y su permiso es `600`. Tras cambiar el `.env`, correr `php artisan config:cache` y `php artisan queue:restart`.

| Ya está | Falta | Riesgo si no se hace |
|---|---|---|
| `.env.example` documentado (proxies, respaldos, soporte). `session.secure` toma `APP_ENV=production` por defecto. Todo uso de `env()` está dentro de `config/` (compatible con `config:cache`). 2FA activa por defecto. | Un `.env.production.example` (o una sección en este documento) con los valores de producción: el ejemplo actual es de desarrollo (`APP_ENV=local`, `APP_DEBUG=true`, `DB_CONNECTION=sqlite`, `MAIL_MAILER=log`, `LOG_STACK=single`, `LOG_LEVEL=debug`). No hay una orden que revise el `.env` antes de arrancar. | Pasar a producción con `APP_DEBUG=true` o `APP_ENV=local` expone secretos y rutas de desarrollo. Con `MAIL_MAILER=log` nadie recibe invitaciones. |

---

## 3. Pasos de despliegue

Orden recomendado para un despliegue (la primera vez y las siguientes). Idealmente se compila en CI y se sube el resultado; si se compila en el servidor, hace falta Node 22.

### Primera vez

1. [ ] Crear usuario del sistema sin privilegios, carpeta de la aplicación y el vhost de Nginx apuntando a `public/`.
2. [ ] Crear la base `menuonl` (`utf8mb4`) y los usuarios de base de datos (ver H7): uno de aplicación (CRUD) y, si se decide, otro de migraciones/respaldo.
3. [ ] Clonar el repositorio en la rama/etiqueta a publicar. No se despliega `frontend/`, `node_modules/` ni `graphify-out/`.
4. [ ] Crear `.env` desde las secciones del punto 2 (`chmod 600`).
5. [ ] `composer install --no-dev --optimize-autoloader --no-interaction`. Los scripts de Composer ejecutan `package:discover` y `filament:upgrade`; el usuario debe poder escribir `bootstrap/cache` y `storage`.
6. [ ] `php artisan key:generate --force` (solo si el `.env` no trae `APP_KEY`).
7. [ ] Recursos: `npm ci && npm run build` (Node 22). `public/build` está en `.gitignore`: **sin este paso las páginas se ven sin estilos y el panel falla** ("Unable to locate file in Vite manifest").
8. [ ] `php artisan migrate --force`.
9. [ ] Datos base: `php artisan db:seed --force`. En producción `DatabaseSeeder` solo carga `EtiquetaSeeder` (idempotente: `updateOrCreate`). **No** ejecutar `DemoRestauranteSeeder` (H2).
10. [ ] `php artisan storage:link` (crea `public/storage` → `storage/app/public`; es un enlace simbólico local, no se versiona). Comprobar que `https://dominio/storage/restaurantes/...` sirve una imagen subida.
11. [ ] Permisos: el usuario de PHP-FPM escribe en `storage/` (incluyendo `storage/app/private/livewire-tmp`, donde Livewire guarda subidas temporales) y `bootstrap/cache/`. El código y `vendor/` no deben ser escribibles por PHP.
12. [ ] Cachés de producción: `php artisan optimize` (config, rutas, vistas, eventos) y `php artisan filament:optimize` (componentes e íconos de Filament). **Verificado:** `php artisan route:cache` funciona con las rutas actuales (la ruta `/panel` usa un closure y se serializa sin problema); se limpió después.
13. [ ] Procesos: Supervisor con `php artisan queue:work --tries=3 --max-time=3600` y cron `* * * * * cd /ruta && php artisan schedule:run >> /dev/null 2>&1` (H4; sin el cron no corre el respaldo).
14. [ ] Crear el primer superadmin y los primeros restaurantes (punto 7).
15. [ ] Prueba de humo (punto "Verificación final" más abajo).

### Cada despliegue siguiente

1. [ ] `php artisan down --retry=60` (opcional si el cambio no migra la base).
2. [ ] Traer código, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`.
3. [ ] `php artisan migrate --force`.
4. [ ] `php artisan optimize:clear` seguido de `php artisan optimize` y `php artisan filament:optimize`.
5. [ ] `php artisan queue:restart` (para que los workers carguen el código nuevo).
6. [ ] `php artisan up`.
7. [ ] Recargar PHP-FPM si OPcache está con `validate_timestamps=0`.
8. [ ] Humo: `/up` responde 200, `/admin/login` carga con estilos, un menú público abre, y el log no muestra errores nuevos.

### Verificación final antes de abrir a clientes

- [ ] `php artisan about` muestra `Environment: production`, `Debug Mode: OFF`, `Cache: config/routes/views cached`.
- [ ] `php artisan schedule:list` lista `menuonl:backup` a las 03:00 y el cron del servidor está activo.
- [ ] `php artisan queue:work --once` procesa un trabajo de prueba; `php artisan queue:failed` está vacío.
- [ ] Una invitación real llega por correo y su enlace abre con `https`.
- [ ] Subir una foto de 9 MB desde el celular funciona; una de 13 MB muestra el mensaje amable.
- [ ] `$request->ip()` es la IP real del visitante (no la del proxy), ver punto 5.

| Ya está | Falta | Riesgo si no se hace |
|---|---|---|
| `composer.json` con `setup`, scripts post-instalación y `optimize-autoloader`. Migraciones de `jobs`, `failed_jobs`, `cache` y `sessions` incluidas. `/up` (`bootstrap/app.php`) como comprobación. Rutas y config cacheables (verificado). | Ningún script ni documento con la secuencia de despliegue; ni archivo de Supervisor ni de cron; ni orden de "humo" posterior. | Un paso olvidado (compilar, `storage:link`, worker, cron) deja el sitio sin estilos, sin imágenes, sin purga de caché o sin respaldos, sin que nada avise. |

---

## 4. Respaldos

### Qué hace hoy (`app/Services/RespaldoService.php`, `app/Console/Commands/RespaldarMenuonl.php`)

- **Qué respalda:** el volcado de la base (`database.sql` con `mysqldump --single-transaction --routines --triggers`, o `VACUUM INTO` para SQLite), **todas** las imágenes en `storage/app/public/restaurantes/` y un `manifest.json` (fecha, nombre del volcado, número de imágenes). **No** respalda `.env`, `APP_KEY`, configuración de Nginx/PHP, ni `storage/app/private` (las subidas temporales no importan).
- **Cifrado:** un `.zip` con AES-256 (`BACKUP_ARCHIVE_PASSWORD`). Los nombres de archivo dentro del zip **no** van cifrados. Sin contraseña, el comando se niega a respaldar.
- **Cuándo:** `routes/console.php` programa `menuonl:backup` todos los días a las 03:00 (zona de la aplicación, hoy UTC → 21:00 en Ciudad de México), con `withoutOverlapping()` y un `Log::error` si falla. Requiere el cron de `schedule:run`.
- **Dónde:** disco `backups` de `config/filesystems.php` (S3 compatible: Cloudflare R2 o Backblaze B2), `visibility: private`. Nombre: `{BACKUP_PREFIX}-AAAAMMDD-HHMMSS.zip`.
- **Retención:** `aplicarRetencion()` borra los que tengan más de `BACKUP_RETENTION_DAYS` (14) según la fecha del **nombre**, y solo toca archivos con el patrón del comando.
- **Comprobaciones:** al subir compara el tamaño remoto con el local. `menuonl:backup-verify [archivo]` descarga el más reciente (o el indicado), revisa Zip Slip y tamaño (tope de 2 GB descomprimido), lo descifra en una carpeta temporal y comprueba `manifest.json`, que el volcado exista y contenga `CREATE TABLE` (solo mira los primeros 5 MB) y que el número de imágenes coincida. **No importa la base.**
- **Frecuencia efectiva (RPO):** hasta 24 horas de datos perdidos en el peor caso.

### Cómo se restaura (no existe comando; procedimiento manual)

1. Descargar el `.zip` más reciente del bucket (panel de R2/B2 o `aws s3 cp --endpoint-url ...`).
2. Obtener `BACKUP_ARCHIVE_PASSWORD` del gestor de contraseñas.
3. Extraer con 7-Zip o `7z x -p archivo.zip -o/ruta/restauracion` (el `unzip` estándar no abre AES; `7z` sí).
4. Crear una base vacía e importar: `mariadb -u usuario -p base < database.sql`.
5. Copiar `restaurantes/` a `storage/app/public/restaurantes/` y ajustar el dueño al usuario de PHP.
6. Restablecer el `.env` desde su copia segura (con el **mismo** `APP_KEY`) y correr `php artisan migrate --force` si el esquema del respaldo es anterior al código.
7. `php artisan optimize:clear`, `php artisan queue:restart`, y comprobar login, un menú y una imagen.

### Plan de prueba de restauración (hacerlo antes del primer cliente, y luego cada trimestre)

- [ ] Servidor o base de pruebas **distinta** de producción.
- [ ] `php artisan menuonl:backup` en el servidor: salida 0, el archivo aparece en el bucket con tamaño razonable.
- [ ] `php artisan menuonl:backup-verify`: salida 0.
- [ ] Restauración manual completa (pasos 1–7 de arriba) en la base de pruebas; cronometrar el tiempo.
- [ ] Verificar: cuenta de superadmin entra con 2FA, `SELECT COUNT(*)` coincide en `restaurantes`, `productos`, `reservas`, un menú público abre con sus imágenes.
- [ ] Probar un respaldo con datos reales de volumen (miles de imágenes): tiempo, memoria y espacio temporal (el zip se arma en `sys_get_temp_dir()`).
- [ ] Probar la retención: tras 15 días no hay respaldos con más de 14.
- [ ] Probar un fallo a propósito (contraseña de bucket mala) y comprobar que se ve en el log y, cuando exista, en la alerta (punto 8).
- [ ] Anotar el tiempo total de restauración (RTO) en este documento.

| Ya está | Falta | Riesgo si no se hace |
|---|---|---|
| Respaldo cifrado diario con retención y verificación (`RespaldoService`, comandos `menuonl:backup` y `menuonl:backup-verify`, programación en `routes/console.php`, disco `backups`). Pruebas: `tests/Feature/RespaldoMenuonlTest.php` y relacionadas. | **(1)** Nunca se ejecutó contra MariaDB, R2/B2 ni un volumen real (lo reconoce `07-checklist-despliegue.md`). **(2)** Sin comando de restauración ni ensayo. **(3)** Sin alerta cuando falla (solo `Log::error`). **(4)** Usa las credenciales de la aplicación (H7). **(5)** Hora en UTC (H10). **(6)** `backup-verify` no importa el SQL. **(7)** Un fallo de retención deja el respaldo subido pero devuelve error, y no hay aviso si no se sube nada durante días. | Un respaldo que nunca se probó es una esperanza, no un respaldo. Perder la contraseña del zip o el `APP_KEY` vuelve irrecuperable el respaldo. Un fallo silencioso se descubre el día del desastre. |

---

## 5. Cloudflare

### Checklist

- [ ] Dominio en Cloudflare con proxy naranja y SSL/TLS **Full (strict)** con certificado de origen. Activar HSTS desde Cloudflare (la aplicación no manda `Strict-Transport-Security`).
- [x] H1 resuelto (cookies en páginas públicas). Falta confirmar en producción con `curl -sI https://dominio/{slug}/menu` que no hay `Set-Cookie`, antes de cualquier regla de caché.
- [ ] Caché del sitio y del menú: el origen ya manda `Cache-Control: public, max-age=30` en el sitio (`stale-while-revalidate=300`) y `max-age=30, stale-while-revalidate=86400` en el menú (`app/Http/Controllers/SitioPublicoController.php`, `MenuPublicoController.php`). Regla de caché: **"Eligible for cache" + "Respect origin TTL"** (no "Cache Everything" con TTL fijo) solo para `/{slug}` y `/{slug}/menu`.
- [ ] Reglas de **no caché / bypass**: `/admin*`, `/panel*`, `/api/*`, `/livewire*`, `/q/*`, `/invitaciones/*`, `/storage/*` (las imágenes tienen nombres aleatorios, se pueden cachear aparte con TTL largo), `/build/*` (archivos con hash, TTL largo), `/up`.
- [ ] Purga automática: `PurgarCacheRestauranteJob` borra `https://{dominio}/{slug}/menu` y `https://{dominio}/{slug}` (solo esas dos URL exactas). Se dispara con `MenuCacheService::invalidateRestaurante()` al cambiar datos del restaurante, y **al cambiar el plan** (`Restaurante::cambiarPlan()` llama `triggerMenuCachePurge()`). Requiere worker (H4) y token válido.
- [ ] Limitación conocida: las variantes con parámetros (`?lang=en`, `?mesa=5`) son claves de caché distintas y **no** se purgan por URL exacta. Su vida máxima la acota `max-age=30`, pero el menú tiene `stale-while-revalidate=86400`: puede mostrarse contenido viejo hasta que el borde revalide. Valorar purga por prefijo (plan Enterprise) o por etiquetas, o bajar `stale-while-revalidate`.
- [ ] Rangos de proxies de confianza: `TRUSTED_PROXIES` con los de Cloudflare. Comprobar en producción que `$request->ip()` coincide con la IP del visitante (cabecera `CF-Connecting-IP`). Si todos aparecen con la IP del proxy, el límite de login y `menu-publico` bloquearía a clientes distintos entre sí.
- [ ] Firewall del servidor aceptando solo IPs de Cloudflare en 80/443, para que no se pueda saltar el WAF entrando por la IP directa.
- [ ] Reglas recomendadas:
  - WAF administrado y OWASP en modo bloqueo; "Bot Fight Mode" activado.
  - Rate limiting: `POST /admin/login` y `POST /livewire*` (p. ej. 10 por minuto por IP), `GET /q/*` (la app limita a 300/min por IP; en Cloudflare, p. ej. 120/min por IP), `POST /api/v1/sitio/*/reservas` (la app limita a 3/min y 10/hora), `/invitaciones/*` (la app limita a 10/min).
  - Regla de "Challenge" administrado para países sin clientes si procede.
  - Ocultar el panel: restringir `/admin*` y `/panel*` por país o por Cloudflare Access si hay pocas personas administrando.
  - Token de API con el permiso mínimo "Zone → Cache Purge" sobre una sola zona.
- [ ] Decisión pendiente ([05-pendientes.md](05-pendientes.md)): pasar `/admin` por el proxy con regla de bypass de caché (recomendado) en lugar de conexión directa.

| Ya está | Falta | Riesgo si no se hace |
|---|---|---|
| `ConfiarEnProxiesConfigurados` y `TRUSTED_PROXIES` con rangos (verificados el 2026-10-16) (`app/Http/Middleware/ConfiarEnProxiesConfigurados.php`, `config/menuonl.php`, `tests/Feature/ProxiesDeConfianzaTest.php`). Cabeceras de caché del sitio/menú. `PurgarCacheRestauranteJob` y su disparo al cambiar menú o plan (`app/Services/MenuCacheService.php`, `app/Models/Restaurante.php`). Límites propios: `menu-publico` (300/min por IP), `reservas-publicas`, `invitaciones`, `panel-api` (`app/Providers/AppServiceProvider.php`). | **H1** (cookies en páginas públicas, el borde no cacheará). Worker de colas (H4). Las reglas de Cloudflare no están escritas ni probadas. Purga de variantes con `?lang`/`?mesa`. Ningún aviso si la purga falla (solo `Log::error`, sin reintentos configurados en el trabajo). `/q/*` comparte el límite `menu-publico` con el menú: una red con IP compartida (p. ej. el WiFi de un restaurante con muchos clientes) suma todo en una cubeta. | Sin caché de borde, un pico de escaneos de QR llega entero al VPS (RNF-09 deja de cumplirse). Sin purga, un cambio de precio o de plan tarda en verse. Una regla de "cachear todo" mal puesta podría servir cookies de sesión ajenas. |

---

## 6. Seguridad pendiente

### 6.1 Política de contenido (CSP) y cabeceras

Medido en local con el kernel HTTP de Laravel:

| Ruta | CSP | X-Frame-Options | nosniff | HSTS |
|---|---|---|---|---|
| `/{slug}/menu`, `/{slug}`, `/q/*` | sí (`default-src 'self' 'unsafe-inline' ...`) | `SAMEORIGIN` | sí | no |
| `/admin/login` (y todo `/admin`) | **no** | **no** | **no** | no |
| `/panel`, `/invitaciones/*` | no medido; el código no las añade | no medido | no medido | no |

- **Scripts y estilos en línea:** hay 2 bloques `<script>` en línea en las vistas (uno en `resources/views/components/layout/publico.blade.php`, usado por el sitio y el menú; el otro en la página de diseño, solo local) y unos 43 usos de `style=`/`onclick=` en todas las vistas. Filament/Livewire/Alpine también emiten scripts en línea y usan `eval` en su build estándar. Por eso la CSP actual necesita `'unsafe-inline'` y, para `/admin`, una CSP estricta no es viable sin trabajo (el repositorio ya depende de `@alpinejs/csp`, lo que sugiere esa intención, pero el panel de Filament no lo usa).
- **Plan de endurecimiento recomendado:** (1) un middleware global de cabeceras de seguridad (`X-Content-Type-Options`, `X-Frame-Options`/`frame-ancestors`, `Referrer-Policy`, `Permissions-Policy`) que cubra `/admin`, `/panel` e `/invitaciones`; (2) HSTS desde Cloudflare; (3) CSP en modo **Report-Only** primero, con nonce para scripts propios en las páginas públicas y de `/panel` (que usa Vite, sin Filament), quitando `'unsafe-inline'` de `script-src` allí; (4) dejar `/admin` con una CSP más laxa (con las excepciones que Filament necesita) y documentarla.

### 6.2 Límite de intentos de login

- **Ya está:** `app/Filament/Pages/Auth/Login.php`: intentos fallidos por correo+IP (5) y por IP (20), bloqueo de 900 s (`config/menuonl.php`, `login.*`), mensaje genérico; sobre el límite propio de Filament (`rateLimit(5)`). El bloqueo usa la IP de la petición, por eso depende de `TRUSTED_PROXIES` (punto 5). Pruebas en `tests/Feature/LimiteLoginTest.php` y `PlanSinPanelDosFactoresTest.php`.
- **Falta:** regla equivalente en Cloudflare (punto 5); el contador vive en la caché: si la caché es `database` y se limpia, se reinician los intentos; alerta ante picos de fallos.

### 6.3 2FA obligatoria para superadmin

- **Ya está:** `User::requiereDosFactores()` (superadmin y propietarios) + `ExigeDosFactores` (middleware persistente, también en actualizaciones de Livewire) + `menuonl.requerir_2fa` (encendida por defecto aunque falte la variable). Códigos de recuperación activados. Pruebas: `tests/Feature/DosFactoresTest.php`, `PlanSinPanelDosFactoresTest.php`.
- **Falta (operativo):** documentar qué hacer si el superadmin pierde el segundo factor y los códigos de recuperación (hoy: restablecerlo por `tinker`, con otro superadmin como respaldo); tener **dos** superadmins; no apagar `REQUIRE_2FA`. Un propietario de "Solo menú" no necesita 2FA (no tiene panel): comportamiento decidido y con prueba.

### 6.4 Cookies, sesiones y CSRF

- **Ya está:** `session.secure` por entorno, `http_only` verdadero, `same_site=lax`, token CSRF en Filament y Livewire.
- **Hecho:** H1 (páginas públicas sin sesión ni cookies). **Falta:** decidir Redis para sesiones si el tráfico crece.

### 6.5 Mensaje amable cuando falla el archivo temporal de la subida de imágenes

- **Ya está:** mensajes claros para "demasiado grande" e "ilegible" tanto en Filament (`ImagenRestauranteUpload`) como en la API de `/panel` (`ImagenController::archivo()`), con el mismo texto (`WebpImageService::mensajeDemasiadoGrande()`).
- **Falta:** cuando PHP no puede guardar el archivo temporal (`UPLOAD_ERR_NO_TMP_DIR`, `UPLOAD_ERR_CANT_WRITE`, disco lleno, `storage/app/private/livewire-tmp` sin permisos), `ImagenController::archivo()` responde igual "demasiado grande" a **cualquier** archivo inválido (`imagen.file`/`imagen.uploaded` usan el mismo mensaje), lo cual es engañoso; y en Filament/Livewire el fallo de la subida temporal no tiene un mensaje propio (el usuario ve el error genérico del componente). Hay que distinguir el código de error de `UploadedFile::getError()`, mostrar algo como "No pudimos recibir tu foto en este momento. Intenta de nuevo en unos minutos" y registrar el detalle con `Log::error`.
- **Riesgo:** el primer cliente con un servidor mal permisionado verá "tu foto es demasiado grande" con una foto pequeña, y no sabrá a quién escribirle.

### 6.6 Otros puntos de seguridad

- **Seeders de prueba en producción (H2, resuelto)** y número de WhatsApp real en el ejemplo (H9).
- **Usuarios de base de datos** con permisos mínimos y MariaDB solo en `localhost` ([02-seguridad.md](02-seguridad.md) §1), conciliado con las necesidades de migración y respaldo (H7).
- **Imágenes:** ya se reprocesan siempre a WebP con nombre aleatorio (`WebpImageService`, `tests/Feature/ImagenesSeguridadTest.php`); falta la regla de Nginx que impida ejecutar PHP en `storage/`.
- **`composer audit`** ya corre en CI; correrlo también periódicamente sobre el servidor (o con Dependabot).
- **Datos de contacto de soporte:** `ContactoSoporte` (`app/Services/ContactoSoporte.php`) no tiene número escrito en el código.

| Ya está | Falta | Riesgo si no se hace |
|---|---|---|
| Login con límite propio, 2FA obligatoria con recordatorio por middleware, CSP y cabeceras en las páginas públicas, imágenes reprocesadas, aislamiento por restaurante, tokens de invitación con hash. | Cabeceras de seguridad en `/admin`, `/panel` e `/invitaciones`; CSP útil (sin `unsafe-inline`) al menos en públicas y `/panel`; HSTS; mensaje correcto para fallos de archivo temporal; procedimiento de recuperación de 2FA. | Clickjacking y XSS con menos barreras en el panel; mensajes engañosos al cliente; bloqueo permanente del superadmin si pierde su 2FA. |

---

## 7. Primeros usuarios y restaurantes reales (sin seeders de prueba)

### Flujo recomendado

1. **Base limpia:** `php artisan migrate --force` y `php artisan db:seed --force` (solo `EtiquetaSeeder` en producción; confirmado en `database/seeders/DatabaseSeeder.php`). **Nunca** `--class=DemoRestauranteSeeder`.
2. **Primer superadmin:** `php artisan menuonl:crear-superadmin` (por SSH, en una terminal interactiva).
   - Pide, en este orden: correo, nombre completo, contraseña y confirmación. La contraseña se escribe oculta y **nunca** se acepta como argumento, opción ni variable de entorno (no queda en el historial de la terminal), no se imprime y no se registra.
   - Exige contraseña de al menos 12 caracteres, correo con formato válido y único (sin importar mayúsculas), y se niega a correr con `--no-interaction`.
   - Si ya existe un superadmin, se niega; para crear otro (por ejemplo el de respaldo) hay que pasar `--confirmo-otro-superadmin`.
   - Guarda la contraseña con el mismo hash que el resto de la aplicación (Argon2id si `HASH_DRIVER=argon2id`, como exige el punto 2), activa la cuenta y marca el correo como verificado. No usa `tinker` ni depende de `$fillable`.
   - **Historial:** no se escribe ahí. Las filas de `historial_cambios` pertenecen a un restaurante (`restaurante_id` obligatorio) y un superadmin no tiene ninguno. Queda una línea en el registro de la aplicación (`storage/logs`): `menuonl:crear-superadmin - Superadmin creado.` con el id y el correo, sin contraseña.
   - Entrar a `/admin/login`: el sistema exige configurar la app de autenticación (2FA) antes de cualquier otra pantalla; guardar los códigos de recuperación.
3. **Segundo superadmin** (respaldo) con el mismo comando y `--confirmo-otro-superadmin`.
4. **Primer restaurante real:** Plataforma → Restaurantes → Crear: nombre, dirección corta (no reservada: `Catalogos::slugsReservados()`), moneda, idioma, zona horaria, **plan** (por defecto Completo), estado y correo del propietario. La aplicación crea el restaurante, siembra su idioma y envía una **invitación por correo** (`CreateRestaurante::afterCreate()`, `InvitacionService`), válida 7 días, de un solo uso. Requiere correo real configurado (H5).
5. **El propietario** abre el enlace, define su contraseña, configura 2FA en su primer ingreso, y empieza a cargar su menú.
6. **QR:** generarlos solo con `APP_URL` definitivo (los QR impresos codifican la URL de `APP_URL`).
7. **Cambios de plan:** solo el superadmin, con ventana de confirmación y registro en el historial (`Restaurante::cambiarPlan()`).

### Limpieza previa

- [ ] Confirmar que en producción **no** existen `superadmin@menuonl.test`, `propietario@menuonl.test` ni `editor@menuonl.test` (`SELECT email FROM users WHERE email LIKE '%@menuonl.test'`).
- [ ] Confirmar que no hay restaurantes de demostración (`la-esquina`, etc.) ni carpetas de pruebas dentro de `storage/app/public` (en el equipo de desarrollo existe `challenger_verify_alpha`, ignorado por Git; no copiarlo al servidor).

| Ya está | Falta | Riesgo si no se hace |
|---|---|---|
| Alta de restaurante por Plataforma con selector de plan e invitación por correo (`app/Filament/Resources/Restaurantes/Pages/CreateRestaurante.php`); invitaciones con hash, caducidad y reemplazo (`InvitacionService`); `DatabaseSeeder` que solo carga demostración en `local`/`testing`; guarda de entorno también dentro de `DemoRestauranteSeeder` (H2); comando `menuonl:crear-superadmin` (H3). | Una verificación de "no hay cuentas `@menuonl.test`" al desplegar. | Sin superadmin no se arranca; con el seeder de demostración, queda una cuenta con contraseña conocida. |

---

## 8. Monitoreo y registros

### Qué hay hoy

- **Registros:** canal `stack`/`single` por defecto (`config/logging.php`, `.env.example`), `LOG_LEVEL=debug`. Eventos con prefijo estable: `menuonl:backup - ...` (respaldo y su verificación), `PurgarCacheRestauranteJob: ...` (purga de Cloudflare), y el historial de cambios de negocio en la tabla `historial_cambios` (quién, cuándo y qué, incluido el cambio de plan).
- **Salud:** `GET /up` (solo comprueba que la aplicación arranca, no la base de datos ni las colas).
- **Colas:** tabla `failed_jobs` (comando `queue:failed`); sin alerta.

### Checklist

- [ ] `LOG_STACK=daily`, `LOG_LEVEL=warning`, rotación por `LOG_DAILY_DAYS=14` y `logrotate` del sistema para Nginx y PHP-FPM.
- [ ] Monitor externo de disponibilidad (UptimeRobot, Better Stack o similar) sobre `/up` y sobre el menú de un restaurante de prueba, con aviso por correo o WhatsApp.
- [ ] Alerta de respaldo: que falle de forma visible (correo/WhatsApp) cuando `menuonl:backup` termina con error o cuando pasan más de 26 horas sin un respaldo nuevo en el bucket.
- [ ] Alerta de colas: `failed_jobs` con filas, o trabajos en espera más de unos minutos.
- [ ] Seguimiento de errores de la aplicación (Sentry, Flare o equivalente) para excepciones no controladas.
- [ ] Vigilar disco (`storage/`, `/tmp` durante el respaldo, el bucket), memoria de PHP-FPM y conexiones de MariaDB.
- [ ] Revisar semanalmente: intentos fallidos de login (picos), 429 en `/q` y menús, errores 5xx en Nginx y el panel de Cloudflare (analíticas y eventos del WAF).
- [ ] Documentar quién recibe las alertas y cuál es el procedimiento de respuesta (restaurar respaldo, apagar un restaurante con `estado=suspendido`, rotar `CLOUDFLARE_API_TOKEN`).

| Ya está | Falta | Riesgo si no se hace |
|---|---|---|
| Registros con prefijos consistentes; historial de cambios por restaurante; `/up`; tabla de trabajos fallidos. | Rotación de logs (`single` crece sin límite); monitoreo externo; alertas de respaldo y de colas; seguimiento de errores; un `/up` que revise base de datos. | Un respaldo roto, una cola detenida o un sitio caído se descubren cuando lo avisa un cliente. |

---

## 9. Lista priorizada de PR pequeños

Cada PR es independiente y revisable por separado. Ninguno cambia la lógica de negocio de los planes.

| # | PR | Resuelve | Prioridad |
|---|---|---|---|
| 1 | ~~**Páginas públicas sin sesión ni cookies**~~ **Hecho** (las rutas web públicas excluyen los middleware de sesión; el menú, el sitio y los QR no necesitan sesión; la API pública de reservas ya estaba en el grupo `api`). Planteamiento original: que `/{slug}`, `/{slug}/menu`, `/q/{codigo}` y `/api/v1/menu/*` no usen el grupo `web` completo (sin `StartSession`, `EncryptCookies`, `PreventRequestForgery`); prueba que la respuesta no trae `Set-Cookie`. Verificar que el formulario de reservas y el carrito (`localStorage`) no dependen de la sesión. | H1, RNF-02, RNF-09 | P0 |
| 2 | ~~**Guarda de entorno en `DemoRestauranteSeeder`**~~ **Hecho:** solo corre en `local` y `testing`, con prueba. | H2 | P0 |
| 3 | ~~**Comando `menuonl:crear-superadmin`**~~ **Hecho** (interactivo, contraseña oculta y confirmada, mínimo 12 caracteres, `--confirmo-otro-superadmin`; sin Historial por no tener restaurante). Planteamiento original: pide nombre, correo y contraseña sin eco (o genera una), valida longitud, asigna `es_superadmin` con `forceFill`, nunca imprime la contraseña, y se niega si ya existe el correo; prueba. | H3 | P0 |
| 4 | **Documentación de procesos (sin código de app):** `docs/` con ejemplo de Nginx (límites, `storage`, cabeceras), `php.ini`, Supervisor para `queue:work` y cron de `schedule:run`; y `.env.production.example` con los valores del punto 2. | H4, punto 1 y 2 | P0 |
| 5 | **Correo de producción:** comando o prueba `menuonl:probar-correo {destino}` para validar SMTP antes del primer cliente; y que un fallo de envío de invitación no deje el restaurante a medias (reportar el error y permitir "Reenviar invitación"). | H5 | P0 |
| 6 | **Quitar el número real de `.env.example`:** dejar `SOPORTE_WHATSAPP=` vacía y revisar que ningún dato personal esté en el repositorio. | H9 | P1 |
| 7 | **Cabeceras de seguridad globales:** middleware con `X-Content-Type-Options`, `X-Frame-Options`/`frame-ancestors`, `Referrer-Policy` y `Permissions-Policy` para `/admin`, `/panel`, `/invitaciones` y la API; pruebas por ruta. HSTS se configura en Cloudflare. | H6 | P1 |
| 8 | **Mensaje amable para fallos del archivo temporal de subida:** distinguir `UPLOAD_ERR_*` en `ImagenController::archivo()` y en `ImagenRestauranteUpload`, con texto propio y `Log::error`; pruebas con un archivo inválido simulado. | punto 6.5 | P1 |
| 9 | **Usuarios de base de datos y respaldo:** decidir y documentar permisos (migraciones, aplicación, respaldo) y permitir una conexión de respaldo propia (`BACKUP_DB_*`) en `RespaldoService`; prueba del armado del comando. | H7 | P1 |
| 10 | **Comando de restauración asistida `menuonl:backup-restore`** (descarga, descifra, importa a una base indicada y copia imágenes a una carpeta), más un ensayo documentado; y que `backup-verify` importe el SQL a SQLite/MariaDB temporal en CI. | H8, punto 4 | P1 |
| 11 | **Alertas de respaldo y colas:** notificación por correo (o webhook) cuando `menuonl:backup` falla o no hay respaldo reciente (comando programado de vigilancia), y cuando `failed_jobs` crece. | puntos 4 y 8 | P1 |
| 12 | **Reglas de Cloudflare como documento + prueba de humo:** `docs/` con las reglas exactas (caché, bypass, rate limiting) y un script de comprobación con `curl` (cabeceras de caché, ausencia de `Set-Cookie`, `CF-Cache-Status`). Depende del PR 1. | punto 5 | P1 |
| 13 | **Zona horaria del respaldo:** programar `menuonl:backup` en `America/Mexico_City` (o en una hora de baja carga) y documentarlo. | H10 | P2 |
| 14 | **CSP en modo Report-Only** para páginas públicas y `/panel`, con nonce para los scripts propios; revisar violaciones y luego aplicar sin `unsafe-inline` en `script-src`. | H6 | P2 |
| 15 | **Mejorar `/up`:** comprobar base de datos (y caché) con una ruta de salud dedicada, sin exponer detalles. | punto 8 | P2 |
| 16 | **Purga de variantes con parámetros** (`?lang`, `?mesa`) y reintentos del trabajo `PurgarCacheRestauranteJob` (`tries`/`backoff`) con aviso al agotar. | punto 5 | P2 |
| 17 | **Rotación de logs y configuración de monitoreo externo** (documento + variables), y Sentry/Flare opcional. | punto 8 | P2 |
| 18 | **Procedimiento de recuperación de 2FA del superadmin** como comando seguro (`menuonl:restablecer-2fa {correo}`) y documento. | 6.3 | P2 |

Orden sugerido: 1 → 2 → 3 → 4 → 5 (quitan lo que hoy bloquea), luego 6–12 (antes del primer cliente de pago) y 13–18 en las primeras semanas.

---

## Qué no se pudo verificar

- **Nada se probó en un servidor real.** Los versiones de PHP/MariaDB, `php.ini`, Nginx, Supervisor y cron son lo que el código requiere según lo leído; falta comprobarlo en el VPS. Las extensiones se verificaron solo en el equipo de desarrollo (PHP 8.4.26 con todas presentes).
- **Cabeceras de la tabla del punto 6.1** se midieron en local (kernel HTTP de Laravel, con `APP_ENV=local`) y con la base de desarrollo; en producción puede variar si Nginx o Cloudflare añaden cabeceras. La presencia de `Set-Cookie` en las páginas públicas (H1) es del propio código, no depende del entorno, pero no se probó contra Cloudflare.
- **Cómo se comporta Cloudflare** con `stale-while-revalidate`, con cookies y con las reglas propuestas: no se probó; son recomendaciones a partir de su documentación general.
- **Respaldos:** `mysqldump` real contra MariaDB, subida a R2/B2 y restauración completa nunca se ejecutaron (las pruebas de desarrollo simulan el volcado). Los permisos de base de datos que necesita el volcado (H7) son una estimación, no una prueba.
- **Correo:** no se probó ningún proveedor SMTP.
- **Rendimiento y límites:** no se midieron subidas de 10–12 MB con el `php.ini` y Nginx propuestos, ni un respaldo con miles de imágenes.
- **`route:cache` y `config:cache`:** `route:cache` se probó en desarrollo y funciona (se limpió después); `config:cache` se dedujo de que todo `env()` está dentro de `config/`.
- **Versión de producción de librerías** (libzip para AES-256, Livewire, Filament): se asume la del `composer.lock`.
