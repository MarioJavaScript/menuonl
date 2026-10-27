# Checklist de despliegue

Pasos que no se pueden comprobar en desarrollo y deben hacerse en el VPS antes de dar por cumplidos la seguridad de red y RNF-06.

## Proxies de confianza

- [ ] `TRUSTED_PROXIES` con los rangos de Cloudflare de `.env.example` si el sitio va detrás de Cloudflare. Revisarlos contra `cloudflare.com/ips-v4` e `ips-v6` (la última verificación fue el 2026-10-16).
- [ ] Si el VPS **no** está detrás de Cloudflare, dejar `TRUSTED_PROXIES` vacía. Nunca `*`: se descarta, y con la lista vacía no se confía en ningún proxy.
- [ ] Comprobar que `$request->ip()` es la IP real del visitante (por ejemplo, desde el panel o con un log temporal). Si todos aparecen con la IP del proxy, el login y `menu-publico` bloquearían a clientes distintos entre sí.
- [ ] Firewall del VPS que acepte solo IPs de Cloudflare (ver `02-seguridad.md`, sección 6).

## Respaldos (RNF-06)

- [ ] Crear un bucket **privado** en Cloudflare R2 o Backblaze B2 y credenciales limitadas a ese bucket. Completar `BACKUP_ACCESS_KEY_ID`, `BACKUP_SECRET_ACCESS_KEY`, `BACKUP_BUCKET`, `BACKUP_ENDPOINT` y `BACKUP_REGION`.
- [ ] Definir `BACKUP_ARCHIVE_PASSWORD` y guardarla **fuera del VPS** (gestor de contraseñas). Sin ella no se puede restaurar ningún respaldo, y el comando se niega a respaldar si está vacía.
- [ ] Ajustar `BACKUP_MYSQLDUMP_PATH`: en MariaDB reciente el binario puede llamarse `mariadb-dump`. Comprobarlo con `which mysqldump mariadb-dump`.
- [ ] Probar `mysqldump` real: en desarrollo la prueba lo simula, nunca se ejecutó contra MariaDB. Correr `php artisan menuonl:backup` en el VPS y confirmar código de salida 0 y el archivo en el bucket.
- [ ] Probar la restauración real: `php artisan menuonl:backup-verify`, y además descargar un respaldo, abrirlo con 7-Zip con la contraseña e importar `database.sql` en una base de pruebas.
- [ ] Confirmar que el cron del scheduler (`php artisan schedule:run` cada minuto) está activo y que el respaldo de las 03:00 aparece cada día.
- [ ] Revisar que un fallo se vea en `storage/logs` (`menuonl:backup - ...`). Pendiente decidir si hace falta una alerta externa.
- [ ] Verificar la retención: tras 15 días no deben quedar respaldos con más de 14 días.

RNF-06 se da por cumplido solo cuando los tres puntos de prueba (`menuonl:backup`, `menuonl:backup-verify` y la restauración manual) pasan en el VPS.
## Dominio y claves

- [ ] `APP_URL` debe ser el dominio real con `https://` (por ejemplo `https://menu.midominio.com`). Los QR descargables e impresos codifican esa URL, no el `Host` de la petición: si `APP_URL` está mal, todos los QR impresos apuntarán al dominio equivocado y habrá que reimprimirlos.
- [ ] Guardar `APP_KEY` en el gestor de contraseñas, fuera del VPS. Con ella se cifran sesiones y datos; si se pierde o cambia, se invalidan las sesiones y lo cifrado con ella deja de poder leerse.

## Respaldos: puntos adicionales

- [ ] El respaldo arma el archivo en la carpeta temporal del sistema. Confirmar que ahí hay espacio suficiente para la base de datos y los archivos subidos, y que el usuario que ejecuta el cron puede escribir en ella.
- [ ] Probar un respaldo con miles de archivos (imágenes de muchos restaurantes) y revisar tiempo, memoria y espacio temporal usados. Las pruebas de desarrollo solo usan unos pocos archivos.
- [ ] Si la subida a R2/B2 falla por checksum (por ejemplo errores de `x-amz-checksum` o `Content-MD5`), revisar la compatibilidad de la versión del SDK de AWS con el proveedor: las versiones recientes envían checksums que R2/B2 pueden rechazar.

## Interfaz (CSS y marca)

- [ ] Correr `npm ci && npm run build` en cada despliegue. Sin `public/build` las utilidades de Tailwind no existen y las páginas públicas se ven sin estilos (`public/css/menuonl.css` solo cubre los tokens, el modo oscuro y los componentes propios).
- [ ] Definir `APP_NAME=Menubly` en el `.env` de producción: lo usan el título del navegador y el pie de los correos.
- [ ] Servir `public/css/menuonl.css` y `public/favicon.svg` con caché larga en el CDN, y purgar el CDN si cambian.


## Direcciones públicas (sitio y menú)

- [ ] El sitio del restaurante vive en `/{slug}` y el menú en `/{slug}/menu`. Las direcciones antiguas `/s/{slug}` y `/m/{slug}` solo redirigen (301) conservando `?mesa=` y `?lang=`. Los QR impresos usan `/q/{codigo}`, que no cambia.
- [ ] Si hay reglas de caché en Cloudflare para `/m/*` (o `/s/*`), cambiarlas por las nuevas: una para `/*/menu` y otra para las páginas de sitio `/{slug}`, excluyendo `/admin*`, `/panel*`, `/api/*`, `/q/*`, `/invitaciones/*`, `/livewire*`, `/storage/*` y `/build/*`. La purga automática (`PurgarCacheRestauranteJob`) borra `https://{dominio}/{slug}/menu` y `https://{dominio}/{slug}`.
- [ ] Antes de añadir una ruta nueva en el primer nivel (`/algo`), agregar `algo` a `Catalogos::slugsReservados()`; una prueba lo exige.

## Retiro de los pedidos manuales (migraciones `2026_10_28_*`)

- [ ] Antes del primer deploy con estas migraciones, contar con consultas de **solo lectura** lo que se va a perder o reasignar: `SELECT COUNT(*) FROM pedidos;`, `SELECT COUNT(*) FROM restaurante_usuario WHERE rol = 'cocina';` y `SELECT COUNT(*) FROM invitaciones WHERE rol = 'cocina';`. Anotar los números en el registro del deploy. Si hay filas en `pedidos`, respaldar antes (`php artisan menuonl:backup`): la tabla se borra y su `down()` la recrea vacía.
- [ ] Las membresías con rol `cocina` pasan a `recepcion` **desactivadas** (el propietario decide si las reactiva); las invitaciones `cocina` sin aceptar se borran y las aceptadas pasan a `recepcion`. Avisar a esas personas: al entrar a `/panel` verán "Tu acceso está desactivado".
- [ ] Tras **restaurar un respaldo anterior** a este cambio, correr `php artisan migrate`: `menuonl:restaurar-respaldo` devuelve la base tal cual estaba (incluida la tabla `migrations`), así que la tabla `pedidos` y el rol `cocina` reaparecerían hasta migrar.
