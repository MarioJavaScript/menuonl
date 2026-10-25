# Despliegue: procesos en segundo plano

Esta carpeta trae la configuración lista para producción del **worker de colas**. Sin él, los trabajos en cola nunca corren:
la purga de Cloudflare al cambiar un menú o un plan (`PurgarCacheRestauranteJob`) y el correo de las invitaciones
(`EnviarInvitacionJob`). El resto del despliegue está en `docs/despliegue.md`.

| Archivo | Para qué sirve |
|---|---|
| `supervisor/menuonl-worker.conf` | Worker con Supervisor (2 procesos, reinicio automático). |
| `systemd/menuonl-worker@.service` | El mismo worker con systemd (plantilla: una instancia por proceso). |
| `desplegar.sh` | Ejemplo de la secuencia de despliegue, con `queue:restart` y la verificación final. |

Usa **uno** de los dos (Supervisor o systemd), no ambos.

## Requisitos

- `QUEUE_CONNECTION=database` (o `redis`). Las tablas `jobs`, `job_batches` y `failed_jobs` las crea la migración
  `0001_01_01_000002_create_jobs_table.php`; `php artisan migrate --force` las incluye.
- `CACHE_STORE` compartido entre procesos (`database`, `file` o `redis`), **no** `array`: `queue:restart` y la comprobación de
  `menuonl:verificar-produccion` usan la caché para hablar con los workers.
- `--timeout` (60 s) menor que `DB_QUEUE_RETRY_AFTER` (90 s). Si un trabajo tarda más que `retry_after`, otro worker lo toma y
  se ejecuta dos veces.
- Cron del planificador, aparte del worker: `* * * * * cd /var/www/menuonl && php artisan schedule:run >> /dev/null 2>&1`
  (sin él no corre el respaldo).

## Instalar con Supervisor

```bash
sudo cp deploy/supervisor/menuonl-worker.conf /etc/supervisor/conf.d/menuonl-worker.conf
# Ajusta rutas (/var/www/menuonl), usuario (www-data) y numprocs.
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start menuonl-worker:*
```

## Instalar con systemd

```bash
sudo cp deploy/systemd/menuonl-worker@.service /etc/systemd/system/
# Ajusta rutas, usuario y el binario de php en ExecStart.
sudo systemctl daemon-reload
sudo systemctl enable --now menuonl-worker@1 menuonl-worker@2
```

## En cada despliegue

El worker es un proceso de larga vida: **sigue ejecutando el código viejo hasta que se reinicia**. Después de actualizar el
código y migrar, corre siempre:

```bash
php artisan queue:restart
```

Cada worker termina después de su trabajo actual y Supervisor o systemd lo levantan de nuevo con el código nuevo. `desplegar.sh`
ya lo incluye. Los workers también se reinician solos cada hora (`--max-time=3600`).

## Comprobar que el worker está vivo

1. **El comando de verificación** (la prueba más fiable: encola un trabajo de prueba y espera a que alguien lo procese):

   ```bash
   php artisan menuonl:verificar-produccion
   ```

   La línea `Worker de colas` debe salir `[OK]`. Si sale `[FALLA]`, no hay un worker procesando.
2. **El proceso:**
   - Supervisor: `sudo supervisorctl status` (deben estar `RUNNING`).
   - systemd: `systemctl status menuonl-worker@1` y `journalctl -u menuonl-worker@1 -n 50`.
3. **La cola:** si crece y no baja, el worker no está procesando.

   ```sql
   SELECT COUNT(*) AS pendientes, MIN(FROM_UNIXTIME(created_at)) AS el_mas_viejo FROM jobs;
   ```

   Un trabajo pendiente de más de unos minutos es mala señal.
4. **El registro:** `storage/logs/worker.log` (y `storage/logs/laravel.log` para los errores de los trabajos).

## Trabajos fallidos

Un trabajo que agota sus reintentos queda en la tabla `failed_jobs`. `menuonl:verificar-produccion` avisa (`[AVISO]`) cuando
hay alguno.

```bash
php artisan queue:failed                 # lista los fallidos (id, cola, fecha)
php artisan queue:retry <id>             # reintenta uno
php artisan queue:retry all              # reintenta todos
php artisan queue:forget <id>            # descarta uno
php artisan queue:prune-failed --hours=168   # borra los fallidos de más de 7 días
```

Antes de reintentar, mira la causa en `storage/logs/laravel.log` o en la columna `exception`; si la causa sigue ahí, volverá a
fallar.

**Invitaciones cuyo correo no salió:** no hace falta `queue:retry`. La invitación ya está guardada y marcada: en
**Plataforma → Restaurantes** aparece "Correo no enviado" y la acción **Reenviar invitación** genera un enlace nuevo (el anterior
deja de funcionar) y lo vuelve a encolar. Antes revisa `MAIL_*` en el `.env` y que el proveedor de correo responda. El trabajo
va cifrado (lleva el enlace de acceso) y los mensajes de error que se guardan nunca incluyen el enlace.

## Si el worker se cae o hay que apagarlo

- Los trabajos pendientes **no se pierden**: siguen en `jobs` y se procesan cuando el worker vuelva.
- La purga de Cloudflare atrasada solo significa que el menú viejo se sirve unos minutos más desde el borde (el `Cache-Control`
  del menú es corto).
- Para pausar sin perder nada: `sudo supervisorctl stop menuonl-worker:*` (o `systemctl stop menuonl-worker@1 ...`).
