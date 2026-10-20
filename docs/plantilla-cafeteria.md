# Plantilla E "Cafetería": análisis

Prototipo aprobado: `C:\proyectos\prueba` (Mezquite). Este documento resume el análisis previo a construir la plantilla `e`.

## Cómo está hoy

- **Sitio** (`/{slug}`): `SitioPublicoController` pide los datos a `SitioPublicoDatos::para()` y renderiza `sitio.{plantilla}`. La clave sale de `restaurantes.sitio_plantilla`; si no existe en `Catalogos::plantillasSitio()` cae a `a`. Cada vista usa `<x-sitio.layout>`, que carga solo `resources/css/sitio/{plantilla}.css` y `resources/js/sitio.js`.
- **Menú** (`/{slug}/menu`): `MenuPublicoController` arma `categorias`, `destacados`, `contactos` y `horarios` (con caché) y renderiza siempre `menu.publico`, que usa `<x-layout.publico>` (Tailwind `app.css` + `public/css/menuonl.css`, tema por `data-tema`). Hoy el menú tiene un solo diseño y obedece al estilo (`tema`) del restaurante, no a la plantilla del sitio.
- **Panel**: el selector de plantillas se dibuja en `resources/js/panel.js` con la lista que devuelve `AjustesController` desde `Catalogos::plantillasSitio()`. Registrar `e` ahí basta para que sea seleccionable.
- **Acento**: `Restaurante::colorAcentoSeguro()` (hex de 6 dígitos o `null`) y `colorAcentoTexto()` (blanco u oscuro legible sobre ese acento). La plantilla D ya los usa.

## Menú por plantilla: la forma más simple

En `MenuPublicoController`, al final, elegir la vista así:

```
$vista = view()->exists('menu.plantillas.'.$plantilla) ? 'menu.plantillas.'.$plantilla : 'menu.publico';
```

donde `$plantilla` es `sitio_plantilla` validada contra `Catalogos::plantillasSitio()`. Solo existirá `menu/plantillas/e.blade.php`; para `a`, `b`, `c` y `d` la vista no existe y todo sigue pasando por `menu.publico` sin cambio alguno. Una plantilla futura que quiera su menú solo agrega su archivo. El controlador, la caché, el idioma y los encabezados no cambian.

La vista `e` usa un layout propio (`components/sitio/cafeteria`) con su hoja y su script; no carga `menuonl.css` ni Tailwind, así que no depende del estilo del restaurante.

## Datos: de dónde sale cada bloque

| Bloque del prototipo | Campo / relación de Menuonl | Estado |
|---|---|---|
| Nombre (cabecera, pie) | `restaurantes.nombre` | Listo |
| Slogan | `restaurantes.eslogan` | Listo; se omite si está vacío |
| Descriptor del pie ("Café y bebidas frías en Hermosillo") | `restaurantes.descripcion` (recortada) | Parcial: es un texto largo; se usa solo si es corto, si no se omite |
| Franja de aviso superior | No existe | Falta; se omite |
| Foto de portada (bebida en arco) | `restaurantes.portada` | Listo; sin foto, recuadro neutro |
| Textura de piedra de la portada | Decoración del prototipo | No es dato; se reemplaza por color plano de la hoja (no se copian las fotos del prototipo) |
| Dos palabras gigantes + palabra en cursiva | Derivadas de nombre y eslogan (ver abajo) | Sin campo nuevo |
| Botón "Ver el menú" | `route('menu.publico')` | Listo |
| Categorías (filtros) | `categorias` visibles con su traducción | Listo; "todas" se agrega por código |
| Platillos: nombre, descripción | `producto_traducciones` (idioma, con respaldo) | Listo |
| Platillos: foto | `productos.imagen` | Listo; sin foto, recuadro neutro |
| Platillos: precio | `Producto::precioVigente()` con `FormatoMoneda` | Listo |
| Precio anterior tachado | `productos.precio` cuando hay `precio_oferta` menor | Listo |
| Variantes con precio | `producto_variantes` | Existe en Menuonl, no en el prototipo; se muestran como lista bajo el platillo |
| Etiquetas dietéticas / agotado | `etiquetas`, `disponible` | Existe en Menuonl, no en el prototipo; se muestran como marcas |
| Banda "Nueva bebida" (platillo destacado) | Primer producto `destacado` con foto y, si lo hay, con etiqueta `nuevo` | Parcial |
| Datos de la banda (Jarabe, Base, Tamaño) | No existe | Falta; se omite. Se muestran las etiquetas del platillo |
| Textos "Nueva", "de temporada", "bebida" | Fijos del prototipo | Se reemplazan por "Nuevo" (si tiene etiqueta `nuevo`) o "Recomendado"; nada inventado |
| Pedido simulado en memoria | El menú real pide por WhatsApp con un carrito propio de `menu.publico` | Falta: el carrito está acoplado a esa vista y no se reutiliza |
| Dirección | `contactos` tipo `direccion` | Listo |
| Horario | `horarios` | Listo; se omite si todos los días están cerrados |
| Teléfono | `contactos` tipo `telefono` | Listo |
| WhatsApp | `whatsapp_pedidos` o `contactos` tipo `whatsapp` | Listo |
| Instagram | `contactos` tipo `instagram` | Listo |
| Facebook / mapa | `contactos` tipo `facebook`, coordenadas | Existe; se agrega como enlace |
| Pie con nota de ejemplo | "Sitio creado con Menuonl" como las otras plantillas | Listo |
| Reservaciones | `reservas_activas` | El prototipo no las tiene; la plantilla `e` no incluye formulario |
| Idiomas del menú | `restaurante_idiomas` | El prototipo no los tiene; se agrega un selector mínimo si hay más de uno |

## Las dos palabras gigantes

Opciones:

1. **Derivarlas del nombre y el eslogan, sin campos nuevos (recomendada).** Nombre de varias palabras: la última palabra va a la derecha y el resto a la izquierda (igual que la plantilla D). Nombre de una sola palabra: va sola en un lado y la escena se centra. El eslogan, si es corto, va como palabra en cursiva; el eslogan completo va debajo con el botón. Sin textos inventados y funciona hoy para cualquier restaurante.
2. **Campos nuevos** (`portada_palabra_izquierda`, `portada_palabra_derecha`, `portada_acento`). Más fiel al prototipo, pero requiere migración, formulario en el panel y datos nuevos que llenar. No se hace sin tu aprobación.

Recomiendo la 1. Si luego quieres control editorial, la 2 se puede sumar sin romper la 1.

## Decisiones tomadas

- El acento del restaurante solo cambia el fondo de botones y marcas, con el texto de `colorAcentoTexto()`. Los títulos y textos sobre superficies claras usan siempre el arcilla de la plantilla, así el contraste de 4.5:1 no depende de un color ajeno.
- La plantilla no incluye pedido ni carrito. Se ofrece un botón "Pedir por WhatsApp" cuando el restaurante tiene número, sin tocar `menu.publico`.
- Sin migraciones, sin datos nuevos y sin cambiar el comportamiento de las plantillas `a`, `b`, `c` y `d`.

## Cambios fuera de la plantilla E (estrictamente necesarios)

1. `Catalogos::plantillasSitio()`: registrar `e`.
2. `vite.config.js`: registrar la hoja y el script de `e`.
3. `MenuPublicoController`: elegir `menu.plantillas.{plantilla}` si existe (para `a` a `d` no existe, sin efecto).
4. `tests/Feature/PanelAjustesTest.php`: el conteo de plantillas pasa de 4 a 5.
