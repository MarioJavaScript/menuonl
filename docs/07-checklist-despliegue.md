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