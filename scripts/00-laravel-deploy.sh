#!/usr/bin/env bash
set -e
echo "Running deployment script..."

# The base image's own nginx config generation doesn't fall back to
# index.php for paths that aren't literal files (only "/" itself works,
# via the `index` directive) — every Laravel route under /api/* 404s at
# the nginx layer before ever reaching PHP. Overwrite it with our own
# config (docker/nginx/site.conf) here, right before nginx actually
# starts, so ours wins regardless of what the image generated.
for target in /etc/nginx/sites-available/default.conf /etc/nginx/sites-enabled/default.conf /etc/nginx/conf.d/default.conf; do
    if [ -f "$target" ] || [ -d "$(dirname "$target")" ]; then
        cp /var/www/html/docker/nginx/site.conf "$target"
        echo "Installed custom nginx config at $target"
    fi
done

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Deployment script finished."
