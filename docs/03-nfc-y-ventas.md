# NFC y estrategia de venta

## NFC

Una etiqueta NFC (NTAG213/215) guarda la misma URL corta `/q/{codigo}` que el QR, así que casi no cambia el sistema.

- Renombrar `codigos_qr` a `codigos_acceso` y agregar un campo `tipo` (`qr` / `nfc`).
- Android lo lee directo; iPhone desde el XS en adelante, sin apps.
- Bloquear las etiquetas en modo solo lectura y pegar siempre el QR junto al NFC como respaldo.
- Riesgo principal: que alguien pegue una etiqueta falsa encima. Usar soportes difíciles de manipular y, como opción premium, etiquetas NTAG 424 DNA con URL firmada.
- Agregar `?src=nfc` o `?src=qr` para estadísticas futuras.

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
