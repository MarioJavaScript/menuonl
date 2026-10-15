# Pendientes por confirmar

| Tema | Propuesta / estado |
| --- | --- |
| Lenguaje / framework | Laravel 11 + Filament 3. Con Cloudflare delante, la diferencia de velocidad entre lenguajes casi no se nota. |
| Dominio de la plataforma | Por definir. Debe administrarse en Cloudflare. |
| Moneda e idioma por defecto | Por definir según el país de los primeros clientes. |
| Ubicación de los clientes | Se asume Latinoamérica; confirmar. |
| `/admin` y Cloudflare | La spec dice "conexión directa" al VPS. Recomendado: pasar por el proxy de Cloudflare con *Bypass cache* para conservar WAF y ocultar la IP real. |
| `esquema.sql` | **Completado.** Incluido en [docs/esquema.sql](esquema.sql). DDL completo para MariaDB 10.6+ con 14 tablas, índices, llaves foráneas y datos iniciales. |
| Diagramas de flujo (sección 6) | **Completado.** Los 4 diagramas de flujo fueron redactados en Mermaid e integrados en [01-especificacion.md#6-diagramas-de-flujo](01-especificacion.md#6-diagramas-de-flujo). |
| Alcance de la v1 | Decidir qué se recorta para el MVP (ver [04-roadmap-mvp.md](04-roadmap-mvp.md)). |
