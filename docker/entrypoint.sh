#!/bin/sh
set -e
cd /var/www/html

# storage/ is a Railway volume, so it starts empty on first boot and is owned
# by root. Recreate the layout Laravel expects and hand it to Apache's user.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

# Railway routes traffic to $PORT, not 80.
PORT="${PORT:-8080}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan migrate --force
php artisan config:cache
php artisan view:cache

# The queue worker, restarted whenever it exits (crash or its --max-time
# recycle), running as www-data so the files it writes stay readable to Apache.
(
    while true; do
        su -s /bin/sh www-data -c "php artisan queue:work --sleep=3 --tries=3 --max-time=3600" || true
        sleep 5
    done
) &

exec apache2-foreground
