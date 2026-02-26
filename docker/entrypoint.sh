#!/bin/sh

set -e

echo "🟡 Waiting for database to be ready..."
sleep 10



# Ensure storage and cache dirs are present
mkdir -p storage/logs \
         storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         bootstrap/cache

chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Composer install (if vendor is missing)
# if [ ! -d vendor ]; then
#   echo "📦 Running composer install..."
#   composer install --no-interaction --prefer-dist --optimize-autoloader
# fi

# Composer install
composer install --no-interaction --prefer-dist --optimize-autoloader

# Laravel setup
echo "🔑 Generating app key..."
php artisan key:generate || echo "App key already set"

echo "🔗 Linking storage..."
php artisan storage:link || echo "Storage already linked"


echo "🛠 Running migrations..."
php artisan migrate --force || echo "Migration failed (likely already run)"

echo "🌱 Running seeders..."
php artisan db:seed --force || echo "Seeding skipped or failed"

echo "📚 Generating Swagger docs..."
php artisan l5-swagger:generate || echo "Swagger skipped"

echo "📚 Generating Storage..."
# rm -f public/storage
# php artisan storage:link

chown -R www-data:www-data /var/www/html
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

php artisan optimize:clear


echo "Application ready!"

echo "🚀 Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
