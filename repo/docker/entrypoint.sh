#!/bin/bash
set -e

echo "==> Waiting for MySQL to be ready..."
until php -r "
    try {
        \$options = [];
        if (defined('PDO::MYSQL_ATTR_SSL_CA') && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            // Keep local Docker DB checks simple and avoid cert verification failures.
            \$options = [PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false];
        }
        new PDO(
            'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306'),
            getenv('DB_USERNAME') ?: 'meridian',
            getenv('DB_PASSWORD') ?: 'secret',
            \$options
        );
        echo 'ok';
    } catch (Throwable \$e) {
        exit(1);
    }
" 2>/dev/null; do
    echo "MySQL is unavailable - sleeping 2s"
    sleep 2
done
echo "==> MySQL is ready."

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "==> Generating APP_KEY..."
    APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
    export APP_KEY
    echo "==> APP_KEY generated."
fi

echo "==> Running migrations..."
php artisan migrate --force --no-interaction

echo "==> Running seeders..."
php artisan db:seed --force --no-interaction || echo "Seeder already ran or skipped."

echo "==> Caching configuration..."
php artisan config:cache
php artisan route:cache

echo "==> Ensuring backups directory exists..."
mkdir -p /backups

echo "==> Starting schedule worker in background..."
php artisan schedule:work &

echo "==> Starting PHP-FPM..."
exec "$@"
