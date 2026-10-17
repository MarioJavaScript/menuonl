# NFC y estrategia de venta

## NFC

Una etiqueta NFC (NTAG213/215) guarda la misma URL corta `/q/{codigo}` que el QR, así que casi no cambia el sistema.

- Se agregó un campo `tipo` (`qr` / `nfc`) a `codigos_qr`. **Decisión:** no se renombra la tabla a `codigos_acceso` y no se agrega `?src=` al redirect. La etiqueta y el QR comparten la misma URL `/q/{codigo}` y se tratan igual; renombrar solo costaría una migración riesgosa sin cambiar el comportamiento. El campo `tipo` basta para distinguirlos en el panel.
- Las estadísticas por origen (`?src=nfc` / `?src=qr`) quedan fuera por ahora: exigirían que cada etiqueta lleve una URL distinta de su QR y se reconsideran cuando existan estadísticas. Si hace falta saber por dónde entran, se crea un código NFC y un código QR separados para la misma mesa.
- Android lo lee directo; iPhone desde el XS en adelante, sin apps.
- Bloquear las etiquetas en modo solo lectura y pegar siempre el QR junto al NFC como respaldo.
- Riesgo principal: que alguien pegue una etiqueta falsa encima. Usar soportes difíciles de manipular y, como opción premium, etiquetas NTAG 424 DNA con URL firmada.

### Cómo programar una etiqueta

1. En el panel, crea un código con tipo **Etiqueta NFC** (o cambia el tipo de uno existente) y copia su **URL corta** desde la tabla de códigos; es la URL completa con el dominio real, por ejemplo `https://menu.midominio.com/q/m1-barra`.
2. Compra etiquetas **NTAG213** (suficientes para una URL corta).
3. En tu celular instala **NFC Tools** (o una app similar), elige *Escribir* → *Añadir un registro* → *URL/URI*, pega la URL copiada y acerca la etiqueta.
4. Lee la etiqueta de vuelta para comprobar que abre el menú y, ya probada, **bloquéala en solo lectura** (*Otros* → *Bloquear etiqueta*). Es irreversible.
5. Pega **siempre el QR al lado** como respaldo: no todos los celulares leen NFC.
**Antes de bloquear una etiqueta, confirma el dominio definitivo.** La URL grabada no se puede cambiar una vez bloqueada, y si el dominio cambia hay que reemplazar todas las etiquetas. Esto importa porque el dominio de la plataforma aún está por definir. 
6. Si alguien pega una etiqueta falsa encima, **desactiva ese código en el panel** (el escaneo mostrará un aviso de código no disponible), crea un código nuevo, programa una etiqueta nueva y reemplázala.


## Estrategia de venta

1. **Piloto gratis:** 30 días con 3 a 5 restaurantes locales (fondas, cafés, taquerías). Se carga el menú de cada uno: la demo con su propio menú vende sola.
2. **Dolor a resolver:** no reimprimir menús al cambiar un precio, aparecer como abierto/cerrado y recibir pedidos por WhatsApp.
3. **Planes:** Básico (menú + QR), Pro (pedidos, idiomas, NFC) y pago único por el kit físico (soportes NFC/QR). Fuera de la v1; al inicio se cobra manualmente.
4. **Canales:** imprentas, agencias de diseño y repartidores de WhatsApp como revendedores con comisión.
5. **Aviso de privacidad:** como no se guardan datos del comensal, basta uno simple.

## Riesgos de negocio

- Mercado saturado de menús QR, varios gratuitos.
- Falta de diferenciación; el NFC por sí solo no basta.
- El trabajo real es vender y dar soporte, no programar.
- La v1 es ambiciosa para un restaurante pequeño: validar con clientes reales antes de construir todo.
