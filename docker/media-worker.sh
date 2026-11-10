#!/bin/sh
set -e

php artisan config:cache
exec php artisan queue:work --queue=media --tries=1 --max-time=7200 --sleep=2 --timeout=5400
