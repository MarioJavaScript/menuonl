# Inventario de datos personales

Borrador para revisión legal, no es asesoría legal. Sale de leer el código de la rama `legal-privacidad-terminos` (migraciones, modelos, formularios, controladores, API pública, panel, registros, respaldos, cabeceras, cookies, subida de imágenes, correo y configuración de Cloudflare) el 2026-11-06.

Marcas usadas:

- **POR CONFIRMAR**: no se puede determinar por el código. Lo confirma el responsable. Están reunidas en la sección 5.
- **Por defecto**: si el dato se guarda sin que nadie active nada. "Sí" significa que se guarda en cuanto se usa la función.

Lo que `/privacidad` y `/terminos` dicen sobre estos datos sale de este documento. Si cambia lo que el sistema guarda, hay que actualizar los dos.

## 1. Dueños de restaurante y su equipo (Menubly es responsable)

| Dato | De quién | Para qué se usa | Dónde se guarda | Cuánto tiempo | Quién lo recibe | Por defecto |
|---|---|---|---|---|---|---|
| Nombre | Dueño y equipo | Identificar la cuenta en el panel | `users.name` | Mientras exista la cuenta. No hay borrado: `UserPolicy::delete` devuelve `false` y revocar acceso conserva la cuenta | Hosting, respaldos | Sí |
| Correo electrónico | Dueño y equipo | Iniciar sesión, invitaciones, recuperar contraseña | `users.email` | Igual que el nombre | Hosting, respaldos, proveedor de correo | Sí |
| Contraseña (solo su hash Argon2id) | Dueño y equipo | Iniciar sesión | `users.password` | Igual que el nombre | Hosting, respaldos | Sí |
| Secreto de verificación en dos pasos y códigos de recuperación (cifrados) | Propietario y superadmin (obligatorio); otros roles (opcional) | Segundo paso al entrar | `users.app_authentication_secret`, `users.app_authentication_recovery_codes` | Igual que el nombre, o hasta que la persona lo cambie | Hosting, respaldos | Sí, para propietarios con `REQUIRE_2FA` encendido |
| Rol y restaurante al que pertenece | Dueño y equipo | Permisos del panel | `restaurante_usuario` | Mientras exista la relación. Revocar acceso la desactiva | Hosting, respaldos | Sí |
| Último acceso | Dueño y equipo | Dato de la cuenta | `users.ultimo_acceso` | Igual que el nombre | Hosting, respaldos | Sí |
| IP y navegador de la sesión | Dueño y equipo | Mantener la sesión del panel | `sessions.ip_address`, `sessions.user_agent`, `sessions.payload` (`SESSION_DRIVER=database`) | Vigencia de `SESSION_LIFETIME` (120 min en el ejemplo); la fila se borra cuando pasa la limpieza automática de Laravel | Hosting, respaldos | Sí, con ese controlador de sesión |
| Hash de correo e IP en intentos de entrada | Quien intenta entrar | Límite de intentos | Caché (`Login::clavesDeIntentos`, `login:cuenta:*`, `login:ip:*`) | `LOGIN_BLOQUEO_SEGUNDOS` (900 por defecto) | Hosting | Sí |
| Correo de la persona invitada, rol, quién invitó, fechas y error de envío | Persona invitada | Enviar y aceptar la invitación | `invitaciones` (el token solo como hash SHA-256) | El enlace vence a los 7 días (`InvitacionService::DIAS_VIGENCIA`). La fila no tiene borrado automático | Hosting, respaldos, proveedor de correo | Sí |
| Correo y token de recuperación de contraseña | Dueño y equipo | Recuperar la contraseña | `password_reset_tokens` | El enlace vence a los 60 minutos (`config/auth.php`). No hay limpieza programada de la fila | Hosting, respaldos, proveedor de correo | Solo si se pide |
| Cambios hechos en el panel, con el usuario que los hizo | Dueño y equipo | Historial de cambios del restaurante | `historial_cambios` (contactos, datos del restaurante, platillos, categorías, horarios, QR) | Sin borrado automático mientras exista el restaurante | Hosting, respaldos; lo ven propietario y superadmin | Sí |
| Quién confirmó, canceló o movió una reserva | Equipo | Historial de la reserva | `reserva_eventos.usuario_id` | `RESERVAS_EVENTOS_RETENCION_MESES` (12 por defecto), purgado por `menuonl:purgar-reserva-eventos` | Hosting, respaldos | Sí |
| Datos de contacto publicados: teléfono, WhatsApp, correo, dirección, redes, número de pedidos | Restaurante. Pueden ser datos de una persona física | Mostrarlos en el menú y en el sitio, recibir pedidos | `contactos`, `restaurantes.whatsapp_pedidos`; también en `historial_cambios` y en la caché del menú | Mientras el restaurante los mantenga. Son públicos y salen por la API `/api/v1/menu/{slug}` | Hosting, respaldos, Cloudflare (caché), cualquier visitante | Solo si el restaurante los captura |
| Logo, portada, fotos de platillos y galería | Restaurante. Una foto puede mostrar personas | Mostrarlas en el menú y en el sitio | `storage/app/public/restaurantes/` (públicas). Se convierten a WebP sin metadatos EXIF (`WebpImageService`) | Mientras el restaurante las mantenga | Hosting, respaldos, Cloudflare, cualquier visitante | Solo si se suben |
| Plan contratado | Restaurante | Decidir qué funciones tiene | `restaurantes.plan` | Mientras exista el restaurante | Hosting, respaldos | Sí |

El sistema no guarda datos de pago, tarjetas ni datos fiscales (RFC, razón social): no existen esas columnas ni integración con una pasarela.

## 2. Clientes de cada restaurante (cada restaurante es responsable y Menubly es encargado)

| Dato | De quién | Para qué se usa | Dónde se guarda | Cuánto tiempo | Quién lo recibe | Por defecto |
|---|---|---|---|---|---|---|
| Nombre | Cliente que reserva | Registrar la reserva y que el restaurante la identifique | `reservas.nombre` | **Sin plazo.** No hay purga, ni acción de borrado en el panel (`GestionDeReservas::eliminar` existe, pero la página Reservas no la ofrece). Si se borra el restaurante es borrado lógico, y la reserva no se elimina | Restaurante, hosting, respaldos. Pasa por Cloudflare en tránsito | Sí |
| Teléfono | Cliente que reserva | Confirmar la reserva por teléfono o WhatsApp | `reservas.telefono` (obligatorio desde el sitio, opcional si la captura el equipo) | Igual que el nombre | Igual que el nombre | Sí |
| Personas, fecha y hora | Cliente que reserva | Calcular el cupo y organizar el servicio | `reservas.personas`, `fecha`, `hora` | Igual que el nombre | Igual que el nombre | Sí |
| Nota libre (hasta 255 caracteres) | Cliente que reserva | Avisar algo al restaurante. **Puede traer datos sensibles** si la persona escribe, por ejemplo, una condición de salud | `reservas.nota` | Igual que el nombre | Igual que el nombre | Solo si se escribe |
| Estado y origen de la reserva | Cliente que reserva | Seguimiento de la reserva | `reservas.estado`, `reservas.origen`, `reserva_eventos` (sin nombre ni teléfono) | Reserva: sin plazo. Eventos: 12 meses por defecto | Restaurante, hosting, respaldos | Sí |
| IP (solo para el límite de envíos) | Cliente que reserva | Frenar abusos: 3 por minuto, 10 por hora por restaurante | Caché del limitador. **No se guarda con la reserva** | Minutos a una hora, lo que dure la ventana del límite | Hosting | Sí |

El formulario tiene un campo trampa (`sitio_web`). Si llega lleno, la respuesta es de éxito y no se guarda nada.

### Pedidos por WhatsApp

El servidor **no recibe ni guarda el pedido**. La tabla `pedidos` se eliminó (`2026_10_28_000002_eliminar_tabla_pedidos`).

| Qué pasa | Datos | Dónde queda |
|---|---|---|
| Armar el carrito | Identificador de platillo y variante, cantidad, nota por platillo | `localStorage` del teléfono del cliente (`menuonl_cart_*`). No sale de ahí hasta enviarlo |
| Enlace a WhatsApp (`resources/js/pedido/mensaje.js`) | Nombre del restaurante, modalidad (domicilio o para recoger), platillos, variantes, importes, notas por platillo, nota para la cocina, total y el mensaje opcional del restaurante. No pide nombre, teléfono ni dirección | Se arma en el navegador como un enlace `wa.me` hacia el número de pedidos del restaurante |
| Envío | **El texto lo envía el propio cliente desde su teléfono**: el enlace abre WhatsApp con el mensaje escrito y es la persona quien pulsa enviar. Puede editarlo o no enviarlo. Su número de WhatsApp lo recibe el restaurante por la propia aplicación, igual que la dirección si la da en el chat | WhatsApp y el teléfono del restaurante, fuera del sistema |

Después de reservar, el sitio puede ofrecer un botón a WhatsApp con otro mensaje: restaurante, nombre, personas, fecha y hora (`ReservaPublicaController::store`). También lo envía el cliente, y solo si quiere.

## 3. Visitantes del sitio, del menú y de la portada

| Dato | De quién | Para qué se usa | Dónde se guarda | Cuánto tiempo | Quién lo recibe | Por defecto |
|---|---|---|---|---|---|---|
| IP, navegador, dirección visitada | Visitante | Entregar la página, seguridad y límites (`menu-publico`, 300 por minuto) | No se guarda en tablas. Límite en caché | Minutos en la caché. Registros de Nginx y de Cloudflare: **POR CONFIRMAR** | Cloudflare, hosting | Sí, en tránsito |
| Escaneos de QR y NFC | Nadie identificado: es un contador por código, día y tipo | Métricas del restaurante | `escaneos_diarios` (sin IP, navegador, cookies ni hora; el navegador solo se lee en memoria para descartar bots) | `METRICAS_RETENCION_DIAS` (400 por defecto) | Restaurante, hosting, respaldos | Sí, pero no es dato personal |
| Registros de errores | Quien provoque un error. Laravel añade el id del usuario con sesión, y un error de base de datos podría incluir los valores de la consulta (por ejemplo, nombre o teléfono de una reserva) | Diagnóstico | `storage/logs/` | `LOG_DAILY_DAYS` (14) si en producción se usa `LOG_STACK=daily`, como pide `docs/despliegue.md`. Con `single`, no rota | Hosting | Solo si hay error |

No hay analítica, píxeles, publicidad, mapas embebidos ni fuentes externas en las páginas públicas (revisado con búsqueda en `resources/` y en `public/js/`, y por la CSP de `app/Services/PoliticaDeContenido.php`).

## 4. Cookies, almacenamiento y terceros

### Cookies y almacenamiento en el navegador

| Nombre | Dónde | Para qué | Duración |
|---|---|---|---|
| Sesión (`SESSION_COOKIE`, por defecto a partir de `APP_NAME`) | `/admin`, `/invitaciones` | Mantener la sesión | `SESSION_LIFETIME` |
| `XSRF-TOKEN` | `/admin`, `/invitaciones` | Proteger formularios | `SESSION_LIFETIME` |
| `remember_web_*` | `/admin`, solo si la persona marca "recordarme" | Recordar la sesión | 400 días (`SessionGuard::$rememberDuration`) |
| `localStorage` `menuonl_cart_*` | Menú y sitio con pedidos | Carrito en el dispositivo | Hasta vaciarlo |
| `localStorage` de apariencia (`public/js/apariencia.js`) y preferencias de Filament | Menú y panel | Modo claro u oscuro, interfaz | Hasta borrarlo en el navegador |
| Cookie técnica de Cloudflare (`__cf_bm`, si aplica) | Todo el dominio | Protección contra bots. `docs/despliegue.md` recomienda "Bot Fight Mode" | **POR CONFIRMAR** en el panel de Cloudflare |

La portada, `/privacidad`, `/terminos`, el menú, el sitio y `/q/*` no mandan cookies desde el origen (`tests/Feature/PaginasPublicasSinSesionTest.php`, `tests/Feature/PortadaTest.php`).

### Encargados y terceros

| Quién | Función | Qué datos ve | Base en el repositorio | Por confirmar |
|---|---|---|---|---|
| Hosting (VPS) | Servidor, base de datos, imágenes y registros | Todos | `docs/01-especificacion.md` dice "VPS en Finlandia" | Proveedor, país definitivo y contrato |
| Cloudflare | CDN, caché, WAF y purga de caché | Tráfico en tránsito, incluidos los datos del formulario de reservas y el acceso al panel si pasa por el proxy; caché de páginas públicas | `config/services.php`, `TRUSTED_PROXIES`, `docs/despliegue.md` | Si ya está activo, plan, y si `/admin` pasa por el proxy (decisión abierta en `docs/05-pendientes.md`) |
| Proveedor de correo | Invitaciones, recuperar contraseña, alerta de respaldos | Correo de destino y contenido del mensaje | `MAIL_MAILER`, `config/services.php` trae Postmark, Resend y SES sin elegir | Proveedor |
| Almacén de respaldos | Copia diaria cifrada con AES-256 fuera del VPS. Incluye toda la base (también reservas, sesiones e invitaciones) y las imágenes | Solo el archivo cifrado; los nombres de archivo internos no van cifrados | `config/menuonl.php`, `.env.example`: R2 o Backblaze B2 | Proveedor y región |
| WhatsApp (Meta) | **No es encargado.** Es la aplicación que el cliente y el restaurante eligen usar | Lo que el cliente envía | `resources/js/pedido/mensaje.js` | Nada |

Retención de respaldos: `BACKUP_RETENTION_DAYS`, 14 por defecto. Un dato borrado de la base puede seguir en los respaldos hasta que venzan.

## 5. Lo que no se pudo determinar por el código

Para confirmar por el responsable:

1. Identidad legal y domicilio del responsable (`LEGAL_RESPONSABLE`, `LEGAL_DOMICILIO`), correo de privacidad y fecha.
2. Estado para la jurisdicción (`LEGAL_JURISDICCION`).
3. Proveedor y país del hosting, y si Finlandia sigue vigente.
4. Proveedor de correo y proveedor y región de respaldos.
5. Si Cloudflare ya está activo, su plan, si guarda registros, si deja `__cf_bm` y si `/admin` pasa por su proxy.
6. Cuánto tiempo guarda Nginx sus registros de acceso. No hay configuración de Nginx en `deploy/`. También falta confirmar que en producción se use `LOG_STACK=daily`.
7. Plazo de conservación de las reservas. Hoy **no existe** y se guardan indefinidamente. El aviso lo dice así.
8. Qué pasa con los datos cuando un restaurante se da de baja. Hoy no hay función de baja: solo suspender, que conserva todo.
9. Cómo se atiende una solicitud de cancelación (ARCO). Hoy **no hay función** para borrar una reserva ni una cuenta en el panel, así que habría que hacerlo a mano en la base. Si se quiere otra cosa, requiere desarrollo aparte.
10. Forma de pago, fechas de cobro, IVA, facturación, reembolsos e impago. Nada de esto existe en el código; `docs/03-nfc-y-ventas.md` solo dice "al inicio se cobra manualmente".
11. Si habrá finalidades secundarias, como boletines. Hoy el código no tiene ninguna, y el aviso lo declara así.
12. Si existe un contrato de encargo entre Menubly y cada restaurante. El sistema no lo registra ni pide aceptar los términos al crear la cuenta.
13. Vigencia del Reglamento: la LFPDPPP actual se publicó en el DOF el 20 de marzo de 2025. Falta confirmar qué reglamento aplica. El aviso nombra a la Secretaría Anticorrupción y Buen Gobierno como autoridad.

## 6. Notas para revisar

- `docs/03-nfc-y-ventas.md`, punto 5, dice que no se guardan datos del comensal y que basta un aviso simple. **Ya no es cierto**: las reservas guardan nombre y teléfono.
- El enlace a `/privacidad` en los formularios de reserva lleva al aviso general de Menubly. No hay campo para el aviso propio de cada restaurante, y agregarlo requiere migración.
- Los formularios de reserva no piden aceptación expresa: solo enlazan el aviso. Los términos tampoco se aceptan expresamente al aceptar la invitación.
- `historial_cambios` conserva valores anteriores de los contactos, así que un teléfono corregido sigue en el historial.
