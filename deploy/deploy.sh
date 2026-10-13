#!/usr/bin/env bash
# Run on the server from the project folder after uploading / pulling new code:   bash deploy/deploy.sh

set -euo pipefail
cd "$(dirname "$0")/.."

echo "1/7 PHP packages"
composer install --no-dev --optimize-autoloader --no-interaction

echo "2/7 Front end"
if command -v npm >/dev/null 2>&1; then
  npm ci && npm run build
else
  echo "   npm not found: upload the built public/build folder from your computer instead."
fi

echo "3/7 Folders"
mkdir -p public/images/uploads storage/logs storage/framework/{cache,sessions,views} bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache public/images/uploads || true

echo "4/7 Database"
php artisan migrate --force

echo "5/7 Clear old caches, then cache config/routes/views"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "6/7 Restart the queue worker (if one is running)"
php artisan queue:restart || true

echo "7/7 Check"
php artisan shop:check "$@"
