#!/bin/bash
#
# Auto-deploy webhook script for CloudPanel
# Called by GitHub webhook on every push
#
# Setup:
# 1. Place this at: /home/USER/webhooks/deploy.sh
# 2. Make executable: chmod +x deploy.sh
# 3. Add a cron or use a tiny PHP webhook receiver
#

set -e

DEPLOY_PATH="/home/YOUR_USER/htdocs/orcus.one"
LOG_FILE="/home/YOUR_USER/logs/deploy.log"
BRANCH="main"

echo "$(date) - Deploy started" >> "$LOG_FILE"

cd "$DEPLOY_PATH"

# Pull latest code
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

# Install PHP dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Fix permissions
chmod -R 775 storage bootstrap/cache

# Restart PHP-FPM
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
sudo systemctl restart "php${PHP_VERSION}-fpm"

echo "$(date) - Deploy completed" >> "$LOG_FILE"
