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

php artisan up

# Falla (código distinto de cero) si la configuración de producción o el worker están mal.
php artisan menuonl:verificar-produccion
