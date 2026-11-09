#!/bin/sh
# Scheduler tick. The platform calls this every minute.
#
# Drives messages:send-scheduled, messages:expire, studio:publish-scheduled and
# the site-event prune. Running it more often is harmless; running it never
# means scheduled work never happens.
set -e

php artisan schedule:run --no-interaction
