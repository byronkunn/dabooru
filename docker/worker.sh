#!/bin/sh
# Worker process for the database queue.
#
# Scheduled messages, expiring messages, chapter publishing and the urgent-report
# mail all depend on this running. Without it those jobs sit in the table forever
# and the features silently do nothing.
set -e

php artisan config:cache
exec php artisan queue:work --tries=3 --max-time=3600 --sleep=2 --timeout=90
