FROM richarvey/nginx-php-fpm:3.1.6

COPY . .

# Image config — see https://github.com/richarvey/nginx-php-fpm for all options
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

# Belt-and-braces alongside conf/nginx/nginx-site.conf: rewrites the base
# image's default try_files fallback to hit index.php instead of a bare
# 404, in case the custom config for any reason isn't picked up.
ENV PHP_CATCHALL=1

# NOTE: deliberately NOT setting SKIP_CHMOD — the image needs to chmod
# scripts/ itself (750) so scripts/00-laravel-deploy.sh is executable
# regardless of what file mode git preserved it with.

RUN composer install --no-dev --optimize-autoloader --no-interaction

CMD ["/start.sh"]
