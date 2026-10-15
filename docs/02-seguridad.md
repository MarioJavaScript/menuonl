# Seguridad

Recomendaciones que **se suman** a lo ya definido en la especificación (RNF-03, RNF-04). Lo que está marcado con ➕ no estaba en la v1.0 del documento original.

## 1. Inyección SQL

- Usar solo Eloquent o el query builder (consultas parametrizadas). Nunca concatenar entradas en `DB::raw`, `whereRaw` u `orderByRaw` sin bindings.
- Validar todo con Form Requests: `slug` y `codigo` con regex, `mesa` con máximo 20 caracteres alfanuméricos, y listas blancas para columnas de ordenamiento.
- El usuario de MariaDB de la app solo debe tener `SELECT/INSERT/UPDATE/DELETE`, sin `FILE`, `DROP` ni `GRANT`. MariaDB escuchando solo en localhost.
- Probar con sqlmap y OWASP ZAP en un entorno de staging.

## 2. Imágenes

- Verificar el MIME real del contenido (no la extensión) y limitar peso y dimensiones para evitar bombas de descompresión.
- Reprocesar siempre a WebP (Imagick/GD): destruye payloads ocultos y polyglots, y elimina el EXIF.
- Renombrar con nombres aleatorios. No aceptar SVG de usuarios (permite XSS).
- Nginx, en la carpeta de imágenes: `location ~ \.php$ { deny all; }` y header `X-Content-Type-Options: nosniff`.

## 3. Aislamiento entre restaurantes

- Global Scope por `restaurante_id` y Policies en Filament.
- Tests automáticos que intenten leer/modificar datos de otro restaurante.

## 4. XSS

- Solo `{{ }}` en Blade; nunca `{!! !!}`.
- En `contactos.valor` aceptar solo esquemas `http`, `https`, `tel` y `mailto` (bloquear `javascript:`).
- Aplicar `urlencode` al mensaje de WhatsApp.

## 5. Cuentas y sesiones

- argon2 para contraseñas.
- ➕ 2FA obligatorio para Propietario y Superadmin.
- Cookies `Secure`, `HttpOnly`, `SameSite`.
- Límite de intentos de inicio de sesión.
- ➕ Tokens de invitación guardados con hash (la v1.0 solo dice "token único").

## 6. Cloudflare y red

- WAF y rate limiting activos.
- ➕ Firewall del VPS que acepte solo IPs de Cloudflare (si no, se puede saltar la protección).
- Configurar *trusted proxies* en Laravel.
- Token de API de Cloudflare con permisos mínimos, solo para purgar caché.
- Decisión pendiente: que `/admin` pase por el proxy de Cloudflare con regla *Bypass cache* (recomendado) en lugar de ir directo a la IP del VPS. Ver [05-pendientes.md](05-pendientes.md).

## 7. Servidor

- SSH solo con llaves, fail2ban.
- Redis con contraseña y en localhost.
- Secretos solo en `.env`, fuera del repositorio.
- `composer audit` periódico.
- Respaldos cifrados.

## 8. Headers

- `Content-Security-Policy`, `Strict-Transport-Security` y `frame-ancestors`.
