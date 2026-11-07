#!/usr/bin/env bash
# Ejemplo de despliegue de Menuonl. Ajusta la ruta y revisa cada paso antes de usarlo en producción.
# Nunca corre db:seed con datos de prueba: el seeder de demostración se niega fuera de local y testing.
set -euo pipefail

cd /var/www/menuonl

php artisan down --retry=60

git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci && npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Los workers terminan después de su trabajo actual y Supervisor/systemd los levantan con el código nuevo.
# Sin este paso siguen ejecutando el código viejo.
php artisan queue:restart

# Revisa la configuración antes de abrir el sitio. Si falla, set -e detiene el script y el sitio sigue en mantenimiento:
# corrige el .env, corre config:cache, repite este comando y, si termina en cero, corre php artisan up (docs/despliegue.md, punto 3).
# Va con --sin-worker porque durante el mantenimiento los workers no procesan trabajos y la sonda fallaría siempre.
php artisan menuonl:verificar-produccion --sin-worker

php artisan up

# Revisión completa, ahora sí con el worker. Si falla aquí, el sitio ya está arriba: solo el worker quedó sin confirmar.
php artisan menuonl:verificar-produccion
