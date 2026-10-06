#!/bin/sh

set -e

cd /var/www/html

echo "Starting DeliveryHub container..."

if [ ! -f vendor/autoload.php ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
    echo "Creating .env..."
    cp .env.example .env
fi

if ! grep -q "^APP_KEY=.\+" .env 2>/dev/null; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

exec "$@"
