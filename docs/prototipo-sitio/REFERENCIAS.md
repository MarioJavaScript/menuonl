# Referencias de diseño

Patrones observados en sitios reales de restaurantes. Son inspiración sobre estructura y jerarquía: no se copió
texto, imágenes, logotipos ni código de ninguno.

## Alcance y limitaciones de la investigación

- Se consultó el texto de las páginas de inicio mediante herramientas de lectura web. Esas herramientas devuelven
  solo texto: no permiten ver colores, tipografías exactas ni el comportamiento real en un teléfono. Lo que se dice
  de paleta y de móvil proviene de la estructura del contenido y de la descripción textual, y debe tomarse como
  aproximado.
- Un quinto sitio de alta cocina (Pujol, Ciudad de México) respondió con error 429 y no se pudo analizar.

## Sitios revisados

### Noma (Copenhague) - noma.dk
- Cabecera mínima: nombre del restaurante, selector de idioma y un único control "Menu".
- Portada con carrusel de fotografías a todo el ancho, título de página y botón "Book a table".
- La reservación se repite: primer elemento del menú, sección propia y pie de página.
- Bloques editoriales; el pie repite contacto y horarios; en móvil todo se apila en una columna.

### Eleven Madison Park (Nueva York) - elevenmadisonpark.com
- Cabecera de una sola fila con logotipo y pocas etiquetas en minúsculas con inicial mayúscula.
- Enlace "Reservations" en la cabecera y botón de reserva justo tras la portada.
- Bloque de contacto con dirección, teléfono y red social; pie denso con boletín.
- Imágenes por categoría: equipo, platos, bebidas, sala; pies de foto con nombre del plato e ingredientes.
- En móvil, navegación por niveles.

### Nobu - noburestaurants.com
- Barra superior persistente con selector de ubicación y acceso "Reserve & Contact".
- Tarjetas que alternan imagen y texto; títulos de sección en mayúsculas con un filete corto debajo.
- Párrafos introductorios en negrita y llamadas a la acción cortas en mayúsculas bajo el texto.

### Osteria Francescana (Módena) - osteriafrancescana.it
- Portada de fotografías a todo el ancho sin texto encima.
- Bloque de historia con sobretítulo en mayúsculas y titular con dos puntos.
- Bandas de imagen a sangre que se alternan con textos breves; jerarquía muy contenida.
- Enlace de texto "Book" que se repite en la navegación.

## Patrones aplicados en el prototipo

| Patrón | Dónde se aplica |
| --- | --- |
| Reserva visible desde el inicio y repetida (cabecera, portada, barra fija en móvil, sección propia) | Botones "Reservar" y sección `#reservar` |
| Cabecera simple: nombre y pocas anclas | Cabecera con 4 anclas y menú `<details>` en móvil |
| Portada a pantalla casi completa con foto, titular grande y estado | Sección `#inicio` con "Abierto ahora" y horario de hoy |
| Sobretítulo en mayúsculas con espaciado + titular serif grande | Todas las secciones |
| Alternancia de bloques claros y oscuros para marcar ritmo | Crema, papel, noche (reservas y pie) |
| Pies de foto con nombre del plato | Galería |
| Contacto, horarios y dirección repetidos al final | Sección `#contacto` y pie |
| Una columna en móvil, cuadrícula en escritorio | Todo el sitio |

## Decisiones propias

- Pareja tipográfica: Fraunces (serif de carácter cálido) para titulares e Inter para texto, ambas con licencia OFL
  y autoalojadas.
- Paleta cálida de crema, tinta casi negra y terracota para el acento; el texto cumple contraste de 4.5:1.
- El menú se presenta con líneas punteadas entre nombre y precio, como una carta impresa.
