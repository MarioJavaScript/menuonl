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
| H1 | **RESUELTO.** `/{slug}`, `/{slug}/menu`, `/q/{codigo}`, `/m/{slug}` y `/s/{slug}` ya no pasan por `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `ShareErrorsFromSession` ni `PreventRequestForgery` (`routes/web.php`, variable `$middlewareDeSesion`): sin `Set-Cookie`, sin fila en `sessions`, sin token CSRF. Conservan el límite `throttle:menu-publico` y sus `Cache-Control`. Pruebas: `tests/Feature/PaginasPublicasSinSesionTest.php`. Quedan igual con sesión: `/admin`, `/panel`, `/api/v1/panel/*`, y `/invitaciones/*`. La portada (`/`) y las páginas legales (`/privacidad`, `/terminos`) también son sin sesión desde la landing (punto 5.1). Descripción original: **Las páginas públicas mandaban cookies de sesión junto con `Cache-Control: public`.** Medido en local contra `/{slug}/menu` y `/{slug}`: ambas responden con `Set-Cookie: laravel-session` y `XSRF-TOKEN` y `Cache-Control: max-age=30, public`. `/q/{codigo}` también abre sesión (medido con un código inexistente, que da 404; falta medir uno válido). Cloudflare no guarda en caché respuestas con `Set-Cookie` por defecto, así que **hoy el menú no se serviría desde el borde** (RNF-02 y RNF-09 no se cumplirían). Peor: si alguien activa "Cache Everything" para forzarlo, una cookie de sesión de un visitante podría servirse a otros. Además, con `SESSION_DRIVER=database`, cada visita anónima (y cada bot) escribe una fila en `sessions`. | P0 |
| H2 | **RESUELTO.** `DemoRestauranteSeeder` ahora se niega a correr fuera de `local` y `testing` (lanza un error claro y termina con código distinto de cero; prueba en `tests/Feature/GuardaSeederDemoTest.php`). Descripción original: `DemoRestauranteSeeder` crea `superadmin@menuonl.test` con contraseña `password` y no tiene ninguna guarda de entorno.** La guarda (`local`/`testing`) está solo en `DatabaseSeeder`. Un `php artisan db:seed --class=DemoRestauranteSeeder` en producción crea un superadmin con contraseña pública. | P0 |
| H3 | **RESUELTO.** Existe `php artisan menuonl:crear-superadmin` (ver punto 7; pruebas en `tests/Feature/CrearSuperadminTest.php`). Descripción original: **No había forma segura de crear el primer superadmin.** No existe comando; hoy habría que usar `tinker` con `forceFill(['es_superadmin' => true])` (el campo no es asignable en masa). Sin superadmin no se puede crear ningún restaurante ni invitar a nadie. | P0 |
| H4 | **RESUELTO.** Hay configuración lista en `deploy/` (Supervisor, systemd y un `desplegar.sh` con `queue:restart`), documentada en `deploy/README.md` (cómo comprobar que el worker vive y qué hacer con trabajos fallidos), y el comando `menuonl:verificar-produccion` lo comprueba con un trabajo de prueba. Las tablas `jobs` y `failed_jobs` ya tienen migración. Descripción original: **Los trabajos en cola necesitan un proceso `queue:work`** y el repositorio no trae su configuración. `PurgarCacheRestauranteJob` (`app/Jobs/PurgarCacheRestauranteJob.php`) implementa `ShouldQueue` y `QUEUE_CONNECTION=database`: sin worker, la purga de Cloudflare al cambiar un menú o un plan **nunca se ejecuta**. | P0 |
| H5 | **RESUELTO (invitaciones).** El correo de la invitación sale por la cola (`EnviarInvitacionJob`, cifrado porque lleva el enlace): un fallo de SMTP ya no rompe el alta, la invitación queda guardada y marcada, el superadmin ve "Correo no enviado" en Restaurantes y puede reenviarla (enlace nuevo, misma fila, sin duplicados). `menuonl:verificar-produccion` falla si `MAIL_MAILER` es `log` o `array`. Falta probar un envío real con el SMTP definitivo. Descripción original: **Correo:** `MAIL_MAILER=log` por defecto (`.env.example`, `config/mail.php`). Las invitaciones (`app/Services/InvitacionService.php`) se mandan **sincrónicas** a propósito (el token no debe pasar por la tabla `jobs`). Con el mailer `log` el propietario nunca recibe su invitación; con un SMTP caído, crear un restaurante falla *después* de haberlo guardado. | P0 |
| H6 | **RESUELTO en lo que se pudo hacer con seguridad; queda una parte documentada (punto 6.1).** Todas las respuestas llevan `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` y `Permissions-Policy`; `/admin` tiene una CSP con nonce, `/panel` e `/invitaciones` una estricta, las páginas públicas ya no permiten scripts en línea y HSTS sale solo en producción por https. **Falta:** quitar `'unsafe-eval'` del admin y `'unsafe-inline'` de los estilos. Descripción original: **La política de contenido (CSP) no protege de XSS en la práctica y solo existe en las páginas públicas.** Es `default-src 'self' 'unsafe-inline'`, que permite scripts en línea. `/admin/login` no manda CSP, `X-Frame-Options` ni `X-Content-Type-Options` (medido), y en el código solo tres controladores públicos (sitio, menú y `/q`) añaden esas cabeceras, así que `/panel` e `/invitaciones/*` tampoco las tendrían (deducido, no medido). Nadie manda HSTS. | P1 |
| H7 | **RESUELTO.** El respaldo puede usar un usuario propio de solo lectura (`BACKUP_DB_USERNAME`/`BACKUP_DB_PASSWORD`; si faltan, cae al de la aplicación). Permisos exactos y cómo crearlo: punto 4. Descripción original: **El respaldo usa las credenciales de base de datos de la aplicación**, que según [02-seguridad.md](02-seguridad.md) §1 deberían ser solo `SELECT/INSERT/UPDATE/DELETE`. `mysqldump --single-transaction --routines --triggers` necesita más permisos, y las migraciones necesitan DDL. Hay que decidir usuarios de base de datos separados. | P1 |
| H8 | **RESUELTO.** Existe `php artisan menuonl:restaurar-respaldo` y un procedimiento de ensayo paso a paso (punto 4). **Falta ejecutarlo contra MariaDB real.** Descripción original: **No hay comando de restauración.** Existe `menuonl:backup-verify` (descifra y revisa), pero restaurar es un procedimiento manual que nunca se ha ensayado. | P1 |
| H9 | **El `.env.example` de la rama actual trae un número de WhatsApp que parece real** (un número completo en `SOPORTE_WHATSAPP`, cambio sin commit; ya no se repite aquí). El ejemplo debe ir vacío; el número real solo en el `.env` del servidor. | P1 |
| H10 | **RESUELTO.** Corre a las 04:00 hora de Ciudad de México (`BACKUP_TIME`, `BACKUP_TIMEZONE`; zona explícita en el programador). Descripción original: **El respaldo diario corre a las 03:00 UTC** (`routes/console.php`, `config/app.php` tiene `timezone => UTC`): son las 21:00 en Ciudad de México, en pleno servicio de cena. | P2 |

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
| `APP_NAME` | `Menubly` | Nombre visible de la plataforma: es el único valor de marca (`config('app.name')`); lo usan el título del navegador, el encabezado del panel, el pie de menús y sitios, las pantallas de invitación y el pie de los correos. **Hay que cambiarlo también en el `.env` de producción**: si queda `MENUONL`, eso es lo que se verá. Los identificadores internos (`menuonl:*`, `config/menuonl.php`, `BACKUP_PREFIX`, claves de `localStorage`) conservan `menuonl`. |
| `APP_ENV` | `production` | **NO** `local`. Con `local` se registra la ruta de desarrollo `/diseno`, el seeder de demostración corre con `db:seed` y otras herramientas de desarrollo (por ejemplo las rutas de Boost) se comportan distinto; tras desplegar, revisar `php artisan route:list` para confirmar que no quedan rutas de desarrollo. `session.secure` también depende de esto. |
| `APP_DEBUG` | `false` | **NO** `true`: expone trazas, variables y rutas. |
| `APP_KEY` | generar con `php artisan key:generate --force` | Guardarla fuera del servidor. Si se pierde o cambia, se invalidan sesiones y lo cifrado. |
| `APP_URL` | `https://dominio-real` | Los QR descargables e impresos codifican esta URL, no el `Host` de la petición. Si está mal, hay que reimprimir todos los QR. |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `es` / `es` | |
| `LOG_CHANNEL` / `LOG_STACK` | `stack` / `daily` | **NO** `single`: un solo archivo sin rotación crece sin límite. `LOG_DAILY_DAYS` (por defecto 14) controla cuántos se guardan. |
| `LOG_LEVEL` | `warning` (o `error`) | **NO** `debug`. |
| `DB_CONNECTION` | `mariadb` | **NO** `sqlite` (es el valor del ejemplo). Más `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (descomentar). |
| `SESSION_DRIVER` | `database` o `redis` | Las páginas públicas ya no abren sesión (H1 resuelto); solo la usan el panel y las invitaciones. |
| `SESSION_SECURE_COOKIE` / `SESSION_SAME_SITE` | `true` / `lax` | Ya vienen así en el ejemplo. `http_only` es `true` por defecto (`config/session.php`). |
| `SESSION_LIFETIME` | 120 (min) o menos para el panel | |
| `CACHE_STORE` | `redis` (o `database`) | Es el nivel L1 del menú (`MenuCacheService`, TTL 24 h). Con `database` funciona, solo es más lento. |
| `QUEUE_CONNECTION` | `database` o `redis` | Necesita el worker de `deploy/` activo (H4 resuelto; `menuonl:verificar-produccion` lo comprueba). **NO** `sync` en producción si se quiere que la purga no retrase la respuesta; sí es válido como parche temporal. |
| `MAIL_MAILER` y datos SMTP | proveedor real (SMTP/SES/Postmark) | **NO** `log` ni `array` (H5; `menuonl:verificar-produccion` falla con ellos). `MAIL_FROM_ADDRESS` y `MAIL_FROM_NAME` reales, **NO** `hello@example.com`. Probar un envío real de invitación antes del primer cliente. |
| `FILESYSTEM_DISK` | `local` | Las imágenes usan el disco `public` (`storage/app/public/restaurantes/...`). |
| `REQUIRE_2FA` | `true` (o no definirla) | Está **encendida por defecto** salvo que se ponga explícitamente `false/0/off/no` (`config/menuonl.php`). **NO** apagarla en producción. |
| `HASH_DRIVER` | `argon2id` | |
| `BCRYPT_ROUNDS` | 12 | Solo aplica si se vuelve a bcrypt. |
| `TRUSTED_PROXIES` | rangos de Cloudflare (vienen en el ejemplo) | Vacía si **no** hay Cloudflare delante. Nunca `*` (se descarta). Revisar contra `cloudflare.com/ips-v4` e `ips-v6`. |
| `CLOUDFLARE_ZONE_ID`, `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_DOMAIN` | reales | Token con permiso solo de "Purgar caché" en esa zona. Si faltan, la purga se omite con un `Log::info` (no falla). `CLOUDFLARE_DOMAIN` por defecto es `menuonl.com`. |
| `SOPORTE_WHATSAPP` | solo dígitos con lada, p. ej. `5215512345678` | **Obligatoria en producción.** Entre 10 y 15 dígitos (con lada). Vacía o inválida = la portada no pinta sus botones de WhatsApp (ni el principal) y la pantalla de "Tu plan no incluye el panel" no muestra el botón de soporte; `menuonl:verificar-produccion` falla. El número real va solo en el `.env` del servidor (H9), nunca en el repositorio. |
| `MENU_DEMO_URL` | ruta relativa (`/la-esquina/menu`) o URL de tu propio dominio | Menú que enseña el botón "Ver un menú de ejemplo" de la portada. Cualquier otra cosa (otro dominio, usuario en la URL, espacios) se ignora. Vacía = el primer restaurante activo (el de muestra `la-esquina` si existe); sin ninguno, el botón no sale. |
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
12. [ ] Cachés de producción: `php artisan optimize` (config, rutas, vistas, eventos) y `php artisan filament:optimize` (componentes e íconos de Filament). **Verificado:** `php artisan config:cache`, `route:cache`, `view:cache`, `event:cache` y `optimize` terminan bien (la ruta `/panel` usa un closure y se serializa sin problema); se limpió después con `optimize:clear`. `optimize` falló mientras la regla de subida de Livewire iba como objeto en la configuración; ya está corregido y lo vigila `ConfiguracionCacheableTest`.
13. [ ] Procesos: worker de colas con Supervisor o systemd (`deploy/supervisor/menuonl-worker.conf` o `deploy/systemd/menuonl-worker@.service`, ver `deploy/README.md`) y cron `* * * * * cd /ruta && php artisan schedule:run >> /dev/null 2>&1` (sin el cron no corre el respaldo).
14. [ ] Crear el primer superadmin y los primeros restaurantes (punto 7).
15. [ ] Prueba de humo (punto "Verificación final" más abajo).

### Cada despliegue siguiente

1. [ ] `php artisan down --retry=60` (opcional si el cambio no migra la base).
2. [ ] Traer código, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`.
3. [ ] `php artisan migrate --force`.
4. [ ] `php artisan optimize:clear` seguido de `php artisan optimize` y `php artisan filament:optimize`.
5. [ ] `php artisan queue:restart` (para que los workers carguen el código nuevo; `deploy/desplegar.sh` lo incluye).
6. [ ] `php artisan up`.
7. [ ] Recargar PHP-FPM si OPcache está con `validate_timestamps=0`.
8. [ ] Humo: `/up` responde 200, `/admin/login` carga con estilos, un menú público abre, y el log no muestra errores nuevos.
9. [ ] Si el despliegue cambió el logo o los favicons (`public/brand/*`, `public/favicon.*`): purgar en Cloudflare la caché de `/brand/*` y `/favicon*` (Caching, Purge by URL o por prefijo). Las páginas piden los iconos con `?v=` (fecha del archivo) para romper la caché del navegador, pero la ruta de la raíz (`/favicon.ico`, que algunos navegadores piden solos, sin `?v=`) solo se actualiza si se purga.

### Verificación final antes de abrir a clientes

- [ ] `php artisan about` muestra `Environment: production`, `Debug Mode: OFF`, `Cache: config/routes/views cached`.
- [ ] `php artisan schedule:list` lista `menuonl:backup` a las 04:00 (hora de Ciudad de México) y el cron del servidor está activo.
- [ ] `php artisan menuonl:verificar-produccion` termina en cero (ver abajo); `php artisan queue:failed` está vacío.
- [ ] Una invitación real llega por correo y su enlace abre con `https`.
- [ ] Subir una foto de 9 MB desde el celular funciona; una de 13 MB muestra el mensaje amable.
- [ ] `$request->ip()` es la IP real del visitante (no la del proxy), ver punto 5.

#### `php artisan menuonl:verificar-produccion`

Revisa la configuración de producción y dice con claridad qué está mal. Termina con código distinto de cero si algo grave falla (úsalo al final de cada despliegue). **Nunca imprime valores secretos**: solo nombres de entorno, de mailer y de conexión, y si cada ajuste cumple.

| Revisa | Falla si |
|---|---|
| `APP_ENV` | no es `production` |
| `APP_DEBUG` | no es `false` |
| `APP_URL` | no empieza con `https://` |
| `MAIL_MAILER` | es `log` o `array` |
| `QUEUE_CONNECTION` | es `sync` o `null` |
| `SOPORTE_WHATSAPP` | falta o no tiene entre 10 y 15 dígitos (no imprime el número) |
| `APP_LOCALE` | no es `es` |
| Worker de colas | encola un trabajo de prueba (`LatidoColasJob`) y ningún worker lo procesa en `--espera` segundos (15 por defecto); también falla si `CACHE_STORE` es `array` o `null`, porque entonces no se puede comprobar |

Opciones: `--espera=30` (segundos de espera al worker) y `--sin-worker` (omite esa comprobación; no recomendado). Además avisa (`[AVISO]`, sin afectar el código de salida) si hay trabajos en `failed_jobs` y si el **último respaldo tiene más de 36 horas** (`BACKUP_MAX_AGE_HOURS`), no hay ninguno o no se puede leer el disco de respaldos. Es un aviso y no una falla porque en un servidor recién instalado todavía no hay respaldo; revísalo igual antes del primer cliente. Pruebas: `tests/Feature/VerificarProduccionTest.php`.

También avisa (`[AVISO]`, sin fallar) si `/`, `/privacidad` o `/terminos` aún muestran texto pendiente: `[PRECIO]`, `[CONFIRMAR]`, `[CAPTURA DEL MENÚ]`, `[CAPTURA DEL PANEL]`, `[TESTIMONIO]`, `[NOMBRE Y RESTAURANTE]` o `[PÁGINA POR ESCRIBIR]` (lista en `App\Services\MarcadoresPendientes`). No publiques la landing mientras el comando los siga listando.

El comando lee la configuración **tal como la ve la aplicación**: si usas `config:cache`, corre `php artisan optimize` antes de verificar.

| Ya está | Falta | Riesgo si no se hace |
|---|---|---|
| `composer.json` con `setup`, scripts post-instalación y `optimize-autoloader`. Migraciones de `jobs`, `failed_jobs`, `cache` y `sessions` incluidas. `/up` (`bootstrap/app.php`) como comprobación. Rutas y config cacheables (verificado). | Ningún script ni documento con la secuencia de despliegue; ni archivo de Supervisor ni de cron; ni orden de "humo" posterior. | Un paso olvidado (compilar, `storage:link`, worker, cron) deja el sitio sin estilos, sin imágenes, sin purga de caché o sin respaldos, sin que nada avise. |

---

## 4. Respaldos

### Qué hace hoy (`app/Services/RespaldoService.php`, `app/Console/Commands/RespaldarMenuonl.php`)

- **Qué respalda:** el volcado de la base (`database.sql` con `mysqldump --single-transaction --routines --triggers`, o `VACUUM INTO` para SQLite), **todas** las imágenes en `storage/app/public/restaurantes/` y un `manifest.json` (fecha, nombre del volcado, número de imágenes). **No** respalda `.env`, `APP_KEY`, configuración de Nginx/PHP, ni `storage/app/private` (las subidas temporales no importan).
- **Cifrado:** un `.zip` con AES-256 (`BACKUP_ARCHIVE_PASSWORD`). Los nombres de archivo dentro del zip **no** van cifrados. Sin contraseña, el comando se niega a respaldar.
- **Cuándo:** `routes/console.php` programa `menuonl:backup` todos los días a las **04:00 hora de Ciudad de México** (`BACKUP_TIME=04:00`, `BACKUP_TIMEZONE=America/Mexico_City`; la zona va explícita en el programador y no depende de `APP_TIMEZONE`, que sigue en UTC). Un valor mal escrito en el `.env` cae a 04:00 / Ciudad de México. Con `withoutOverlapping()` y un `Log::error` si falla (`menuonl:backup - ...`, tanto del comando como del programador). Requiere el cron de `schedule:run`. Hoy no existe un mecanismo de aviso al superadmin (correo/webhook), así que el aviso es el log más el `[AVISO]` de `menuonl:verificar-produccion` cuando el último respaldo supera las 36 horas; la alerta externa sigue pendiente (PR 11).
- **Integridad:** el `manifest.json` lleva el SHA-256 del volcado (`sha256_base`); `menuonl:backup-verify` y la restauración lo comprueban antes de tocar nada. Los respaldos anteriores a este campo se siguen aceptando y solo se validan por contenido.
- **Usuario de base de datos del respaldo:** ver "Usuario de solo lectura" más abajo.
- **Dónde:** disco `backups` de `config/filesystems.php` (S3 compatible: Cloudflare R2 o Backblaze B2), `visibility: private`. Nombre: `{BACKUP_PREFIX}-AAAAMMDD-HHMMSS.zip`.
- **Retención:** `aplicarRetencion()` borra los que tengan más de `BACKUP_RETENTION_DAYS` (14) según la fecha del **nombre**, y solo toca archivos con el patrón del comando.
- **Comprobaciones:** al subir compara el tamaño remoto con el local. `menuonl:backup-verify [archivo]` descarga el más reciente (o el indicado), revisa Zip Slip y tamaño (tope de 2 GB descomprimido), lo descifra en una carpeta temporal y comprueba `manifest.json`, que el volcado exista y contenga `CREATE TABLE` (solo mira los primeros 5 MB) y que el número de imágenes coincida. **No importa la base** (la restauración sí lo hace, ver abajo).
- **Frecuencia efectiva (RPO):** hasta 24 horas de datos perdidos en el peor caso.

### Usuario de solo lectura para el respaldo (H7)

Variables opcionales: `BACKUP_DB_USERNAME` y `BACKUP_DB_PASSWORD`. Si `BACKUP_DB_USERNAME` está vacío se usa el usuario de la aplicación (comportamiento anterior). La contraseña viaja a `mysqldump` por la variable de entorno `MYSQL_PWD`, nunca en la línea de comandos.

El volcado usa `--single-transaction --routines --triggers --no-tablespaces`. Permisos mínimos **estimados** para MariaDB (no verificados contra un servidor real; ajústalos con el primer respaldo de prueba):

```sql
CREATE USER 'menuonl_respaldo'@'localhost' IDENTIFIED BY '<contraseña larga y única>';
GRANT SELECT, SHOW VIEW, TRIGGER, EVENT ON `menuonl`.* TO 'menuonl_respaldo'@'localhost';
-- --routines lee mysql.proc en MariaDB; si mysqldump se queja de "SHOW CREATE PROCEDURE", agrega:
-- GRANT SELECT ON `mysql`.`proc` TO 'menuonl_respaldo'@'localhost';
FLUSH PRIVILEGES;
```

- `SELECT`: leer las tablas. `SHOW VIEW`: volcar vistas. `TRIGGER`: volcar disparadores. `EVENT`: volcar eventos (si no usas eventos puedes quitarlo). `LOCK TABLES` **no** hace falta porque se usa `--single-transaction`; `RELOAD`/`PROCESS` tampoco por `--no-tablespaces`.
- Es de solo lectura: no puede modificar ni borrar datos aunque se filtre su contraseña. Usa un bucket y una contraseña distintos a los de la aplicación.
- Prueba: `mysqldump -u menuonl_respaldo -p --single-transaction --routines --triggers --no-tablespaces menuonl > /dev/null` y luego `php artisan menuonl:backup`.

### Restaurar: `php artisan menuonl:restaurar-respaldo` (H8)

```
php artisan menuonl:restaurar-respaldo [nombre] [--base-destino=] [--sobrescribir-base-activa]
    [--carpeta-imagenes=] [--sobrescribir-imagenes] [--sin-imagenes] [--confirmo] [--confirmo-base=]
```

- **Qué respaldo:** el `nombre` indicado (`menuonl-AAAAMMDD-HHMMSS.zip`) o, sin argumento, el más reciente.
- **Antes de tocar nada:** descarga el `.zip`, valida entradas (Zip Slip, tamaño), lo descifra con `BACKUP_ARCHIVE_PASSWORD` y comprueba `manifest.json`, el SHA-256 del volcado, que haya tablas y que las imágenes coincidan. Si algo falla termina con código 1, un mensaje claro y **sin escribir nada** (la carpeta temporal se borra).
- **Por defecto no toca lo activo:** restaura en una base **nueva** (MariaDB: `<base>_restaurada_<fecha>`; SQLite: `storage/app/restauraciones/<respaldo>.sqlite`) y las imágenes en `storage/app/restauraciones/<respaldo>/restaurantes`. No sobrescribe una base con tablas ni un archivo o carpeta existentes.
- **Confirmación:** muestra un plan y pide confirmar. Sin terminal interactiva exige `--confirmo`.
- **Reemplazar la base activa** exige `--sobrescribir-base-activa` y escribir el nombre exacto de la base (sin terminal: `--confirmo-base=<nombre>`). En MariaDB importa primero a una base temporal `<base>_rest_<hex>` y, si todo salió bien, hace un único `RENAME TABLE` (atómico) que aparta las tablas actuales en `<base>_previa_<fecha>` (se conservan para volver atrás; bórralas cuando confirmes). Se niega si la base tiene vistas, disparadores o rutinas (RENAME no las mueve con seguridad): en ese caso restaura en otra base y cambia `DB_DATABASE`. En SQLite copia a un archivo temporal, revisa `PRAGMA integrity_check`, guarda `<archivo>.previa-<fecha>` y hace `rename`.
- **Reemplazar las imágenes en uso** exige `--sobrescribir-imagenes`: se copian a una carpeta de preparación junto a la final y se publican con `rename`; la carpeta anterior queda como `restaurantes.previa-<fecha>`. `--sin-imagenes` las omite.
- **Si algo falla a mitad:** se borran las bases/carpetas auxiliares y la activa sigue como estaba; queda un `Log::error('menuonl:restaurar-respaldo - La restauración falló.')`.
- **Credenciales:** por defecto las de la conexión de la aplicación; para restaurar suele hacer falta un usuario que pueda crear bases: `RESTORE_DB_USERNAME`/`RESTORE_DB_PASSWORD` (opcionales). El cliente es `BACKUP_MYSQL_PATH` (`mysql` por defecto; en MariaDB reciente puede ser `mariadb`). Nada de esto se imprime; los errores del cliente ocultan la contraseña.
- No restaura entre motores distintos (respaldo SQLite hacia MariaDB o al revés).
- Pruebas: `tests/Feature/RestaurarRespaldoTest.php` (SQLite real) y `RestaurarRespaldoMariaDbTest.php` (cliente simulado).

### Procedimiento de ensayo de restauración (antes del primer cliente, y luego cada trimestre)

Hazlo en un servidor de pruebas o, como mínimo, **sin** `--sobrescribir-base-activa`.

1. `php artisan menuonl:backup` → salida 0; el archivo aparece en el bucket con tamaño razonable.
2. `php artisan menuonl:backup-verify` → salida 0.
3. Restaurar a una base nueva y cronometrar: `time php artisan menuonl:restaurar-respaldo --base-destino=menuonl_ensayo` (confirma con `s`; en un script añade `--confirmo`). Anota el tiempo: es tu RTO.
4. Comparar conteos en MariaDB: `SELECT COUNT(*) FROM menuonl.restaurantes` contra `menuonl_ensayo.restaurantes`; repite con `productos` y `reservas`. Pueden diferir en lo creado después del respaldo (hasta 24 h).
5. Apuntar una copia de la aplicación a la base restaurada (`DB_DATABASE=menuonl_ensayo` en un `.env` de pruebas con el **mismo** `APP_KEY`), correr `php artisan migrate --force` si el esquema del respaldo es anterior al código, y comprobar: el superadmin entra con 2FA, un menú público abre y sus imágenes cargan (copiadas de `storage/app/restauraciones/<respaldo>/restaurantes`).
6. Probar el camino de reemplazo en staging (no en producción): `php artisan menuonl:restaurar-respaldo --sobrescribir-base-activa --sobrescribir-imagenes`, escribir el nombre de la base, verificar la aplicación y que existen `<base>_previa_<fecha>` y `restaurantes.previa-<fecha>`; luego `php artisan optimize:clear` y `php artisan queue:restart`.
7. Probar un archivo dañado: sube a un bucket de pruebas un `.zip` truncado con nombre `menuonl-AAAAMMDD-HHMMSS.zip` y comprueba que el comando falla con código 1 y no crea ninguna base.
8. Probar un fallo del respaldo a propósito (contraseña de bucket mala): `menuonl:backup` falla, aparece `menuonl:backup - ...` en `storage/logs/laravel.log` y, pasadas 36 horas, `menuonl:verificar-produccion` muestra el `[AVISO]`.
9. Borrar lo del ensayo: `DROP DATABASE menuonl_ensayo;` y `storage/app/restauraciones/`.
10. Anotar fecha, tiempo total (RTO) y resultado en este documento.

Pendientes del ensayo: un respaldo con volumen real (miles de imágenes: tiempo, memoria y espacio temporal; el zip se arma en `sys_get_temp_dir()`), y la retención (tras 15 días no hay respaldos con más de 14).

| Ya está | Falta | Riesgo si no se hace |
|---|---|---|
| Respaldo cifrado diario con retención y verificación (`RespaldoService`, comandos `menuonl:backup` y `menuonl:backup-verify`, programación en `routes/console.php`, disco `backups`). Pruebas: `tests/Feature/RespaldoMenuonlTest.php` y relacionadas. | **(1)** Nunca se ejecutó contra MariaDB, R2/B2 ni un volumen real (lo reconoce `07-checklist-despliegue.md`). **(2)** ~~Sin comando de restauración~~ resuelto (`menuonl:restaurar-respaldo`); falta **ejecutar el ensayo** contra MariaDB real. **(3)** Sin alerta activa cuando falla (solo `Log::error` y el aviso de 36 h en `verificar-produccion`). **(4)** ~~Credenciales de la aplicación~~ resuelto con `BACKUP_DB_*` (permisos sin verificar). **(5)** ~~Hora en UTC~~ resuelto (04:00 Ciudad de México). **(6)** `backup-verify` no importa el SQL (sí comprueba el SHA-256). **(7)** Un fallo de retención deja el respaldo subido pero devuelve error, y no hay aviso si no se sube nada durante días. | Un respaldo que nunca se probó es una esperanza, no un respaldo. Perder la contraseña del zip o el `APP_KEY` vuelve irrecuperable el respaldo. Un fallo silencioso se descubre el día del desastre. |

---

## 5. Cloudflare

### 5.1 Landing (`/`, `/privacidad`, `/terminos`)

La portada es pública, sin sesión ni cookies, y manda `Cache-Control: public, max-age=300, stale-while-revalidate=600`. Sus recursos son del propio dominio: CSS compilado por Vite (`/build/*`, entrada `resources/css/landing.css`, con las fuentes latinas de `@fontsource`), `public/js/landing-reveal.js`, imágenes en `public/landing/img/` y `public/brand/`.

Reglas de Cloudflare (Caching → Cache Rules), en este orden:

| Acción | Rutas |
|---|---|
| **Cachear** (respetar el `Cache-Control` del origen) | `/`, `/privacidad`, `/terminos`, `/build/*`, `/brand/*`, `/js/*`, `/landing/*` |
| **Bypass de caché** | `/admin*`, `/q/*`, `/livewire*`, `/api/*`, `/invitaciones/*` |

No uses "Cache Everything" para todo el dominio. Tras cambiar el texto de la portada, purga `/`, `/privacidad` y `/terminos` (si no, tardan hasta 5 minutos más la ventana de revalidación en verse). Antes de abrir a clientes: `curl -sI https://dominio/` no debe mostrar `Set-Cookie`, y `menuonl:verificar-produccion` no debe avisar de marcadores pendientes.

### Checklist

- [ ] Dominio en Cloudflare con proxy naranja y SSL/TLS **Full (strict)** con certificado de origen. La aplicación manda `Strict-Transport-Security` sola (solo en producción y por https, 7 días al principio, ver 6.1); no actives también HSTS en Cloudflare con otra duración hasta decidir cuál manda, y sube la duración por etapas.
- [x] H1 resuelto (cookies en páginas públicas). Falta confirmar en producción con `curl -sI https://dominio/{slug}/menu` que no hay `Set-Cookie`, antes de cualquier regla de caché.
- [ ] Caché del sitio y del menú: el origen ya manda `Cache-Control: public, max-age=30` en el sitio (`stale-while-revalidate=300`) y `max-age=30, stale-while-revalidate=86400` en el menú (`app/Http/Controllers/SitioPublicoController.php`, `MenuPublicoController.php`). Regla de caché: **"Eligible for cache" + "Respect origin TTL"** (no "Cache Everything" con TTL fijo) solo para `/{slug}` y `/{slug}/menu`.
- [ ] **Conteo de escaneos de `/q/*` (PR 10), pendiente de verificar en el servidor real.** (1) Que `/q/*` no se cachea en Cloudflare: `curl -sI https://dominio/q/CODIGO` dos veces seguidas debe mostrar `Cache-Control` con `no-cache` y **ningún** `cf-cache-status: HIT`; si una respuesta saliera de la caché, el escaneo no llegaría al origen y no se contaría (y la redirección podría quedar vieja). (2) Que PHP-FPM libera al cliente antes de contar: el conteo corre después de enviar la respuesta (`defer()` en `terminate`), lo que depende de `fastcgi_finish_request` bajo PHP-FPM; medir con `curl -w '%{time_total}'` contra `/q/CODIGO` con la base lenta o bloqueada a propósito y confirmar que el tiempo de la redirección no crece. Nada de esto se pudo probar sin servidor real (ver `docs/integracion-dashboard.md`, sección 13).
- [ ] Reglas de **no caché / bypass**: `/admin*`, `/panel*` (302 a `/admin`), `/api/*` (incluye `/api/v1/panel*`, que responde 410), `/livewire*`, `/q/*`, `/invitaciones/*`, `/storage/*` (las imágenes tienen nombres aleatorios, se pueden cachear aparte con TTL largo), `/build/*` (archivos con hash, TTL largo), `/up`.
- [ ] Purga automática: `PurgarCacheRestauranteJob` borra `https://{dominio}/{slug}/menu` y `https://{dominio}/{slug}` (solo esas dos URL exactas). Se dispara con `MenuCacheService::invalidateRestaurante()` al cambiar datos del restaurante, y **al cambiar el plan** (`Restaurante::cambiarPlan()` llama `triggerMenuCachePurge()`). Requiere worker (H4) y token válido.
- [ ] Limitación conocida: las variantes con parámetros (`?lang=en`, `?mesa=5`) son claves de caché distintas y **no** se purgan por URL exacta. Su vida máxima la acota `max-age=30`, pero el menú tiene `stale-while-revalidate=86400`: puede mostrarse contenido viejo hasta que el borde revalide. Valorar purga por prefijo (plan Enterprise) o por etiquetas, o bajar `stale-while-revalidate`.
- [ ] Rangos de proxies de confianza: `TRUSTED_PROXIES` con los de Cloudflare. Comprobar en producción que `$request->ip()` coincide con la IP del visitante (cabecera `CF-Connecting-IP`). Si todos aparecen con la IP del proxy, el límite de login y `menu-publico` bloquearía a clientes distintos entre sí.
- [ ] Firewall del servidor aceptando solo IPs de Cloudflare en 80/443, para que no se pueda saltar el WAF entrando por la IP directa.
- [ ] Reglas recomendadas:
  - WAF administrado y OWASP en modo bloqueo; "Bot Fight Mode" activado.
  - Rate limiting: `POST /admin/login` y `POST /livewire*` (p. ej. 10 por minuto por IP), `GET /q/*` (la app limita a 300/min por IP; en Cloudflare, p. ej. 120/min por IP), `POST /api/v1/sitio/*/reservas` (la app limita a 3/min y 10/hora), `/invitaciones/*` (la app limita a 10/min).
  - Regla de "Challenge" administrado para países sin clientes si procede.
  - Ocultar el panel: restringir `/admin*` por país o por Cloudflare Access si hay pocas personas administrando. `/panel*` y `/api/v1/panel*` ya no sirven nada: solo redirigen o dan 410, no hace falta protegerlos.
  - **`/panel*` y `/api/v1/panel*` (puerta del panel viejo):** el origen manda `Cache-Control: no-store` en el 302 y en el 410. Aun así, crea una regla explícita de **bypass de caché** para ambos patrones y **no** uses "Redirect Rules" permanentes (301/308) ni "Cache Everything" sobre ellos: un 410 o un 302 guardado en el borde (o un 301 en el navegador) seguiría mandando a la gente a `/admin` aunque algún día se reutilice la ruta. Si ya cacheaste alguno, purga por prefijo. Comprobación: `curl -sI https://dominio/panel` debe dar `302` con `location: /admin` y `cache-control: no-store`; `curl -si https://dominio/api/v1/panel/x/ajustes` debe dar `410` con el mensaje y sin `Set-Cookie`.
  - Token de API con el permiso mínimo "Zone → Cache Purge" sobre una sola zona.
- [ ] Decisión pendiente ([05-pendientes.md](05-pendientes.md)): pasar `/admin` por el proxy con regla de bypass de caché (recomendado) en lugar de conexión directa.

| Ya está | Falta | Riesgo si no se hace |
|---|---|---|
| `ConfiarEnProxiesConfigurados` y `TRUSTED_PROXIES` con rangos (verificados el 2026-10-16) (`app/Http/Middleware/ConfiarEnProxiesConfigurados.php`, `config/menuonl.php`, `tests/Feature/ProxiesDeConfianzaTest.php`). Cabeceras de caché del sitio/menú. `PurgarCacheRestauranteJob` y su disparo al cambiar menú o plan (`app/Services/MenuCacheService.php`, `app/Models/Restaurante.php`). Límites propios: `menu-publico` (300/min por IP), `reservas-publicas`, `invitaciones`, `panel-api` (`app/Providers/AppServiceProvider.php`). | **H1** (cookies en páginas públicas, el borde no cacheará). Worker de colas (H4). Las reglas de Cloudflare no están escritas ni probadas. Purga de variantes con `?lang`/`?mesa`. Ningún aviso si la purga falla (solo `Log::error`, sin reintentos configurados en el trabajo). `/q/*` comparte el límite `menu-publico` con el menú: una red con IP compartida (p. ej. el WiFi de un restaurante con muchos clientes) suma todo en una cubeta. | Sin caché de borde, un pico de escaneos de QR llega entero al VPS (RNF-09 deja de cumplirse). Sin purga, un cambio de precio o de plan tarda en verse. Una regla de "cachear todo" mal puesta podría servir cookies de sesión ajenas. |

---

### 5.2 Restaurante suspendido (fuera de servicio): caché y worker

Lo activa y desactiva solo el equipo desde Plataforma (`estado = suspendido`). Al suspender, el equipo del restaurante (propietario, gerente, editor, recepción) pierde el panel y ve un aviso neutro de "fuera de servicio" con el contacto de soporte (`SOPORTE_WHATSAPP`); el superadmin entra a todo.

- **Origen:** el menú, el sitio, los QR, la API y las reservas del restaurante responden 404 en el mismo instante; no dependen de ninguna caché.
- **Cloudflare:** guardar el restaurante dispara `PurgarCacheRestauranteJob`, que borra `/{slug}/menu` y `/{slug}` en el borde. Hasta que ese trabajo corre, el borde puede seguir sirviendo la copia anterior: hasta `max-age=30` s más `stale-while-revalidate=86400` (24 h) si la purga no llega a ejecutarse.
- **Worker de colas:** la purga va por la cola (`QUEUE_CONNECTION=database`). Con el worker caído la suspensión no llega al borde y el menú público puede seguir visible hasta 24 h. Antes de suspender (o si hay urgencia), comprueba el worker con `php artisan menuonl:verificar-produccion` y purga a mano en Cloudflare si hace falta.
- **Sesión abierta:** un miembro con la sesión abierta recibe el 404 de Filament en su siguiente acción y el aviso en la siguiente carga de página.
- **Invitaciones:** Plataforma no crea ni reenvía invitaciones de un restaurante suspendido; hay que reactivarlo primero.

## 6. Seguridad pendiente

### 6.1 Política de contenido (CSP) y cabeceras (H6, resuelto)

El middleware `App\Http\Middleware\CabecerasDeSeguridad` (registrado como global en `bootstrap/app.php`) añade a **todas** las respuestas, sin pisar lo que ponga un controlador, y sin tocar sesión ni cookies (las páginas públicas siguen siendo cacheables):

| Cabecera | Valor |
|---|---|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` (y `frame-ancestors 'self'` en la CSP) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()` (nada de eso se usa; solo funciones que reconocen los navegadores actuales, sin `run-ad-auction`, `join-ad-interest-group` ni otras de publicidad, que generan avisos en consola. Si esos avisos aparecen en producción no vienen de la aplicación: revisa Cloudflare o el servidor web) |
| `Content-Security-Policy` | solo en respuestas HTML; según el grupo de rutas (abajo) |
| `Strict-Transport-Security` | `max-age=604800` (7 días) **solo** si `APP_ENV=production` y la petición llegó por https; sin `includeSubDomains` ni `preload` |

Políticas por grupo (`app/Services/PoliticaDeContenido.php`):

| Grupo | `script-src` | Notas |
|---|---|---|
| `/{slug}`, `/{slug}/menu`, `/q/*` (públicas) | `'self'` | **Sin `'unsafe-inline'` en scripts.** El único script en línea (apariencia clara/oscura) pasó a `public/js/apariencia.js`. Sin nonce a propósito: el HTML debe ser igual en cada petición para que Cloudflare lo cachee. `default-src` conserva `'unsafe-inline'` solo para estilos (hay unos 40 atributos `style=`). |
| `/`, `/privacidad`, `/terminos` (`PoliticaDeContenido::LANDING`) | `'self'` | Landing: `default-src 'none'` y solo `'self'` en scripts, estilos, imágenes y fuentes; sin `unsafe-inline`, sin nonce, `connect-src`, `frame-src` y `form-action` en `'none'`. El JSON-LD es un dato, no un script. Los botones de WhatsApp son enlaces (navegación), no los afecta. |
| `/invitaciones/*` (`PoliticaDeContenido::estricta()`) | `'self'` | Vite propio, sin scripts en línea. `connect-src 'self'`, `object-src 'none'`, `base-uri 'self'`. |
| `/admin` (Filament y Livewire) | `'self' 'nonce-…' 'unsafe-eval'` | Nonce nuevo por petición (`Vite::useCspNonce()`); Filament y Livewire lo ponen a sus scripts en línea. Estilos: `'unsafe-inline'` y `fonts.bunny.net` (la tipografía por defecto de Filament). |

Nota: `/panel` ya no tiene pantalla: es una redirección 302 a `/admin` y recibe la política pública (`docs/integracion-dashboard.md`, secciones 22 y 23). La política estricta quedó solo para `/invitaciones/*`. Las reglas de Cloudflare para `/panel*` y `/api/v1/panel*` siguen valiendo mientras exista la puerta.

- **Por qué `'unsafe-eval'` en el admin:** Alpine (versión estándar, la que usan Filament y Livewire) evalúa las expresiones de las plantillas con `new Function`. Livewire trae una versión compatible con CSP, pero las plantillas de Filament usan expresiones que esa versión no soporta (no se probó en un navegador; es la razón conocida de que Filament no la active), así que no se activó. Mientras Filament no lo soporte, `'unsafe-eval'` es lo que falta endurecer: un XSS en `/admin` aún podría ejecutar código vía `eval`, aunque ya no por scripts en línea ni atributos `on…=`.
- **Por qué `'unsafe-inline'` sigue en estilos:** Filament y las plantillas del sitio usan atributos `style=`. El riesgo de XSS por estilos es mucho menor que por scripts.
- **Desarrollo:** con `npm run dev` (archivo `public/hot`) y fuera de producción, la CSP admite el servidor de Vite (y su websocket) automáticamente.
- **HSTS, por etapas:** empezamos en 7 días (`HSTS_MAX_AGE=604800`; `0` lo apaga). Si tras una semana todo el tráfico va bien por https, súbelo a 30 días (`2592000`) y luego a 6 meses o 1 año. `includeSubDomains` y `preload` **no** se envían todavía: `preload` es casi irreversible y `includeSubDomains` obligaría a que cualquier subdominio tenga https. Detrás de Cloudflare el esquema real llega por `X-Forwarded-Proto`: depende de `TRUSTED_PROXIES` (punto 5); si `isSecure()` fuera falso, la cabecera simplemente no sale.
- **Estáticos:** las imágenes, CSS y JS que sirve Nginx directamente no pasan por Laravel; añade ahí `X-Content-Type-Options: nosniff` (pendiente en el ejemplo de Nginx, PR 4).
- **Pruebas:** `tests/Feature/CabecerasDeSeguridadTest.php` (cada cabecera en cada grupo, nonce y scripts en línea del admin, HTML público idéntico entre peticiones, HSTS solo en producción con https).

### 6.2 Límite de intentos de login

- **Ya está:** `app/Filament/Pages/Auth/Login.php`: intentos fallidos por correo+IP (5) y por IP (20), bloqueo de 900 s (`config/menuonl.php`, `login.*`), mensaje genérico; sobre el límite propio de Filament (`rateLimit(5)`). El bloqueo usa la IP de la petición, por eso depende de `TRUSTED_PROXIES` (punto 5). Pruebas en `tests/Feature/LimiteLoginTest.php` y `PlanSinPanelDosFactoresTest.php`.
- **Falta:** regla equivalente en Cloudflare (punto 5); el contador vive en la caché: si la caché es `database` y se limpia, se reinician los intentos; alerta ante picos de fallos.

### 6.3 2FA obligatoria para superadmin

- **Ya está:** `User::requiereDosFactores()` (superadmin y propietarios) + `ExigeDosFactores` (middleware persistente, también en actualizaciones de Livewire) + `menuonl.requerir_2fa` (encendida por defecto aunque falte la variable). Códigos de recuperación activados. Pruebas: `tests/Feature/DosFactoresTest.php`, `PlanSinPanelDosFactoresTest.php`.
- **Falta (operativo):** documentar qué hacer si el superadmin pierde el segundo factor y los códigos de recuperación (hoy: restablecerlo por `tinker`, con otro superadmin como respaldo); tener **dos** superadmins; no apagar `REQUIRE_2FA`. Un propietario de "Solo menú" no necesita 2FA (no tiene panel): comportamiento decidido y con prueba.

### 6.4 Cookies, sesiones y CSRF

- **Ya está:** `session.secure` por entorno, `http_only` verdadero, `same_site=lax`, token CSRF en Filament y Livewire.
- **Hecho:** H1 (páginas públicas sin sesión ni cookies). **Falta:** decidir Redis para sesiones si el tráfico crece.

### 6.5 Mensajes claros cuando falla la subida de una foto (resuelto)

Antes, cualquier archivo inválido en la API de `/panel` se reportaba como "demasiado grande". Ahora cada caso tiene su texto (`WebpImageService`), igual en `/panel` y en `/admin`:

| Caso | Mensaje | Dónde se detecta |
|---|---|---|
| Pesa más que nuestro límite (10 MB) | "La foto es demasiado grande. Prueba con una de menos de 10 MB." | `validateImage()`; regla `max` de Filament; regla de subida temporal |
| Tipo no permitido (PDF, SVG, GIF, texto…) | "Ese tipo de archivo no se puede subir. Elige una foto en JPG, PNG o WebP." | `validateImage()` (tipo real, no la extensión); reglas `image`/`mimetypes` de Filament; Livewire si no es un archivo |
| Límite del servidor (`upload_max_filesize`, `post_max_size`) | "El servidor no pudo recibir la foto porque supera el tamaño que acepta. Prueba con una más pequeña; si ya es pequeña, avisa a soporte." | `UPLOAD_ERR_INI_SIZE/FORM_SIZE`; cuerpo mayor que `post_max_size` (Laravel responde 413 y `bootstrap/app.php` lo traduce: 422 con el mismo formato de errores en la subida temporal de Livewire, y 410 en `/api/v1/panel*`) |
| Subida incompleta | "La foto no llegó completa. Revisa tu conexión e inténtalo de nuevo." | `UPLOAD_ERR_PARTIAL`; archivo ausente en la subida temporal |
| Falla el archivo temporal | "No pudimos guardar tu foto en este momento. Intenta de nuevo en unos minutos; si sigue pasando, avisa a soporte." | `UPLOAD_ERR_NO_TMP_DIR/CANT_WRITE/EXTENSION`; regla `uploaded` de Filament |
| Imagen ilegible o dañada | "No pudimos leer esa imagen. Prueba con otra en JPG, PNG o WebP." | sin cambios |

- Nunca se muestran rutas ni códigos de PHP: el código (`codigo_php`) se registra con `Log::error` (`Subida de imagen fallida antes de procesarla.` / `Subida temporal de imagen fallida.`).
- La subida temporal de Livewire/Filament usa `App\Rules\SubidaRecibida` (registrada con el nombre `subida_recibida` en `AppServiceProvider` y referenciada como texto en `config/livewire.php`, para que `config:cache` pueda serializarla) en lugar de `required|file|max:12288`: Laravel marca cualquier subida inválida con un "uploaded" genérico cuando el campo lleva reglas de archivo, y esa regla es la que sí distingue el motivo.
- Pruebas: `tests/Feature/ErroresDeSubidaDeImagenTest.php` (cada código de PHP, 413, tipo no permitido, límite propio, sin rutas en los mensajes) y `ImagenesSeguridadTest.php`.
- **Falta (operativo):** `upload_max_filesize`/`post_max_size` y `client_max_body_size` de Nginx deben ser ≥ 12 MB (punto 2); si Nginx corta antes que PHP responde su propia página 413 y la persona verá el error genérico del navegador.

### 6.6 Otros puntos de seguridad

- **Seeders de prueba en producción (H2, resuelto)** y número de WhatsApp real en el ejemplo (H9).
- **Usuarios de base de datos** con permisos mínimos y MariaDB solo en `localhost` ([02-seguridad.md](02-seguridad.md) §1), conciliado con las necesidades de migración y respaldo (H7).
- **Imágenes:** ya se reprocesan siempre a WebP con nombre aleatorio (`WebpImageService`, `tests/Feature/ImagenesSeguridadTest.php`); falta la regla de Nginx que impida ejecutar PHP en `storage/`.
- **`composer audit`** ya corre en CI; correrlo también periódicamente sobre el servidor (o con Dependabot).
- **Datos de contacto de soporte:** `ContactoSoporte` (`app/Services/ContactoSoporte.php`) no tiene número escrito en el código.
- **Pendiente menor, "Reintentar lectura" (`menu/no-disponible.blade.php`):** el `href` se arma con `request()->fullUrl()`, así que refleja el host y el query string de la petición (Blade lo escapa; no es un riesgo de XSS ni revela si el código o el slug existe, y las pruebas de 404 lo normalizan con `Tests\Concerns\Normaliza404`). Convendría un `href` relativo (por ejemplo, la ruta sin esquema ni host) para no depender del host recibido. Solo anotado; no se cambió código.

| Ya está | Falta | Riesgo si no se hace |
|---|---|---|
| Login con límite propio, 2FA obligatoria con recordatorio por middleware, CSP y cabeceras de seguridad en todas las respuestas (nonce en `/admin`, sin scripts en línea en lo demás), HSTS en producción, mensajes de subida de fotos por caso, imágenes reprocesadas, aislamiento por restaurante, tokens de invitación con hash. | Quitar `'unsafe-eval'` del admin (depende de Filament/Alpine); `'unsafe-inline'` en estilos; subir HSTS por etapas; cabeceras en los estáticos de Nginx; procedimiento de recuperación de 2FA. | Clickjacking y XSS con menos barreras en el panel; mensajes engañosos al cliente; bloqueo permanente del superadmin si pierde su 2FA. |

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
4. **Primer restaurante real:** Plataforma → Restaurantes → Crear: nombre, dirección corta (no reservada: `Catalogos::slugsReservados()`), moneda, idioma, zona horaria, **plan** (por defecto Completo), estado y correo del propietario. La aplicación crea el restaurante, siembra su idioma y encola una **invitación por correo** (`CreateRestaurante::afterCreate()`, `InvitacionService`), válida 7 días, de un solo uso. Requiere correo real configurado (H5). Si el correo falla, el restaurante queda creado, la invitación queda guardada y marcada, y en Restaurantes aparece "Correo no enviado" con la acción "Reenviar invitación" (genera un enlace nuevo; el anterior deja de funcionar).
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
| 4 | ~~**Documentación de procesos**~~ **Hecho en parte:** `deploy/` trae Supervisor, systemd, `desplegar.sh` y `deploy/README.md` (worker, `queue:restart`, comprobación y trabajos fallidos). **Falta:** ejemplo de Nginx (límites, `storage`, cabeceras), `php.ini` y `.env.production.example`. | H4, punto 1 y 2 | P0 |
| 5 | ~~**Correo de producción**~~ **Hecho en parte:** el envío va por la cola, un fallo no deja el restaurante a medias, se reenvía desde Restaurantes, y `menuonl:verificar-produccion` rechaza `log`/`array`. **Falta:** `menuonl:probar-correo {destino}` para validar el SMTP definitivo con un envío real. | H5 | P0 |
| 6 | **Quitar el número real de `.env.example`:** dejar `SOPORTE_WHATSAPP=` vacía y revisar que ningún dato personal esté en el repositorio. | H9 | P1 |
| 7 | ~~**Cabeceras de seguridad globales**~~ **Hecho** (punto 6.1: middleware global, CSP por grupo, HSTS en la aplicación; pruebas por ruta). Planteamiento original: middleware con `X-Content-Type-Options`, `X-Frame-Options`/`frame-ancestors`, `Referrer-Policy` y `Permissions-Policy` para `/admin`, `/panel`, `/invitaciones` y la API; pruebas por ruta. HSTS se configura en Cloudflare. | H6 | P1 |
| 8 | ~~**Mensaje amable para fallos del archivo temporal de subida**~~ **Hecho** (punto 6.5; un mensaje por caso). Planteamiento original: distinguir `UPLOAD_ERR_*` en `ImagenController::archivo()` y en `ImagenRestauranteUpload`, con texto propio y `Log::error`; pruebas con un archivo inválido simulado. | punto 6.5 | P1 |
| 9 | ~~**Usuarios de base de datos y respaldo**~~ **Hecho** (`BACKUP_DB_*` opcional, permisos documentados en el punto 4; falta confirmarlos con MariaDB real). Planteamiento original: decidir y documentar permisos (migraciones, aplicación, respaldo) y permitir una conexión de respaldo propia (`BACKUP_DB_*`) en `RespaldoService`; prueba del armado del comando. | H7 | P1 |
| 10 | ~~**Comando de restauración asistida**~~ **Hecho** como `menuonl:restaurar-respaldo` (punto 4), con ensayo documentado; **falta** que `backup-verify` importe el SQL a una base temporal en CI. Planteamiento original: `menuonl:backup-restore` (descarga, descifra, importa a una base indicada y copia imágenes a una carpeta), más un ensayo documentado; y que `backup-verify` importe el SQL a SQLite/MariaDB temporal en CI. | H8, punto 4 | P1 |
| 11 | **Alertas de respaldo y colas** (**en parte:** `Log::error` al fallar y `[AVISO]` de 36 h en `verificar-produccion`; **falta** la notificación activa): notificación por correo (o webhook) cuando `menuonl:backup` falla o no hay respaldo reciente (comando programado de vigilancia), y cuando `failed_jobs` crece. | puntos 4 y 8 | P1 |
| 12 | **Reglas de Cloudflare como documento + prueba de humo:** `docs/` con las reglas exactas (caché, bypass, rate limiting) y un script de comprobación con `curl` (cabeceras de caché, ausencia de `Set-Cookie`, `CF-Cache-Status`). Depende del PR 1. | punto 5 | P1 |
| 13 | ~~**Zona horaria del respaldo**~~ **Hecho** (04:00 `America/Mexico_City`, configurable). Planteamiento original: programar `menuonl:backup` en `America/Mexico_City` (o en una hora de baja carga) y documentarlo. | H10 | P2 |
| 14 | ~~**CSP sin `unsafe-inline` en scripts**~~ **Hecho** sin pasar por Report-Only (no hay scripts en línea fuera de Filament; el admin usa nonce). **Falta:** observar en un navegador real que `/admin` no registra violaciones (consola) y, a futuro, quitar `'unsafe-eval'`. Planteamiento original: CSP en modo Report-Only para páginas públicas y `/panel`, con nonce para los scripts propios; revisar violaciones y luego aplicar sin `unsafe-inline` en `script-src`. | H6 | P2 |
| 15 | **Mejorar `/up`:** comprobar base de datos (y caché) con una ruta de salud dedicada, sin exponer detalles. | punto 8 | P2 |
| 16 | **Purga de variantes con parámetros** (`?lang`, `?mesa`) y reintentos del trabajo `PurgarCacheRestauranteJob` (`tries`/`backoff`) con aviso al agotar. | punto 5 | P2 |
| 17 | **Rotación de logs y configuración de monitoreo externo** (documento + variables), y Sentry/Flare opcional. | punto 8 | P2 |
| 18 | **Procedimiento de recuperación de 2FA del superadmin** como comando seguro (`menuonl:restablecer-2fa {correo}`) y documento. | 6.3 | P2 |

Orden sugerido: 1 → 2 → 3 → 4 → 5 (quitan lo que hoy bloquea), luego 6–12 (antes del primer cliente de pago) y 13–18 en las primeras semanas.

---

## Qué no se pudo verificar

- **Nada se probó en un servidor real.** Los versiones de PHP/MariaDB, `php.ini`, Nginx, Supervisor y cron son lo que el código requiere según lo leído; falta comprobarlo en el VPS. Las extensiones se verificaron solo en el equipo de desarrollo (PHP 8.4.26 con todas presentes).
- **Cabeceras y CSP del punto 6.1** se comprobaron con pruebas automáticas (kernel HTTP de Laravel) y con `php artisan serve` leyendo las cabeceras de `/admin/login`; **ningún navegador real las ejecutó** (no había Chrome instalado en el equipo de desarrollo), así que no se ha visto si la consola registra violaciones de CSP en Filament (login, tablas, subida de archivos, selectores), en `/panel` o en las plantillas a–h; en producción puede variar si Nginx o Cloudflare añaden o cambian cabeceras (p. ej. otro HSTS). La presencia de `Set-Cookie` en las páginas públicas (H1) es del propio código, no depende del entorno, pero no se probó contra Cloudflare.
- **Cómo se comporta Cloudflare** con `stale-while-revalidate`, con cookies y con las reglas propuestas: no se probó; son recomendaciones a partir de su documentación general.
- **Respaldos:** `mysqldump` real contra MariaDB, subida a R2/B2 y restauración completa nunca se ejecutaron (las pruebas de desarrollo simulan el volcado). Los permisos de base de datos que necesita el volcado (H7) son una estimación, no una prueba. Tampoco se ejecutó contra MariaDB real el cliente `mysql` de la restauración, el `RENAME TABLE` entre bases ni los permisos del usuario de restauración (`CREATE`, `DROP`, `ALTER`, `RENAME` sobre las bases auxiliares); las pruebas simulan el cliente y verifican qué se le pide y en qué orden.
- **Correo:** no se probó ningún proveedor SMTP.
- **Subidas de fotos:** los mensajes de 413 y de errores de archivo temporal se probaron con códigos de error simulados; no se forzó un fallo real de PHP/Nginx ni se vio cómo los muestra FilePond en Filament.
- **Rendimiento y límites:** no se midieron subidas de 10–12 MB con el `php.ini` y Nginx propuestos, ni un respaldo con miles de imágenes.
- **`route:cache` y `config:cache`:** ambos se probaron en desarrollo y funcionan (se limpiaron después). `config:cache` solo falló por la regla de subida de Livewire guardada como objeto, ya corregido; que todo `env()` esté dentro de `config/` no bastaba.
- **Versión de producción de librerías** (libzip para AES-256, Livewire, Filament): se asume la del `composer.lock`.
