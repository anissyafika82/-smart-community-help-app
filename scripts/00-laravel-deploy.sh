#!/usr/bin/env bash
set -e
echo "Running deployment script..."

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Deployment script finished."
