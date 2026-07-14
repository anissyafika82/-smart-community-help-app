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

# Render provides $PORT at runtime; nginx-php-fpm reads $PORT if set
ENV SKIP_CHMOD=1

RUN composer install --no-dev --optimize-autoloader --no-interaction

CMD ["/start.sh"]
