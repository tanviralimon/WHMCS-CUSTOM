#!/bin/bash
#
# CloudPanel Laravel Deployment Script
# Run this on your server after git clone / git pull
#
# Usage: bash deploy.sh
#

set -e

echo "🚀 Deploying WHMCS Portal..."

# ─── Step 1: Install PHP dependencies ─────────────────────
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# ─── Step 2: Environment setup (first deploy only) ────────
if [ ! -f .env ]; then
    echo "📝 Creating .env from .env.production..."
    cp .env.production .env
    php artisan key:generate --force
    echo ""
    echo "⚠️  IMPORTANT: Edit .env and set your database credentials and WHMCS API keys!"
    echo "   nano .env"
    echo ""
fi

# ─── Step 3: Run migrations ───────────────────────────────
echo "🗄️  Running database migrations..."
php artisan migrate --force

# ─── Step 4: Clear and optimize caches ────────────────────
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ─── Step 5: Set permissions ──────────────────────────────
echo "🔒 Setting permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# ─── Step 6: Restart PHP-FPM ─────────────────────────────
echo "🔄 Restarting PHP-FPM..."
# CloudPanel uses PHP-FPM — detect the version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
sudo systemctl restart php${PHP_VERSION}-fpm 2>/dev/null || echo "  ℹ️  Restart PHP-FPM manually if needed"

echo ""
echo "✅ Deployment complete!"
echo ""
