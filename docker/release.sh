#!/bin/sh
# Runs once per deploy, before the new container takes traffic.
set -e

echo "Running migrations..."
php artisan migrate --force

echo "Seeding the adult tag taxonomy..."
php artisan db:seed --class=AdultTagsSeeder --force

# uploads live on the mounted volume when one is configured
mkdir -p "${PUBLIC_DISK_ROOT:-storage/app/public}" "${PRIVATE_DISK_ROOT:-storage/app/private}"

echo "Linking storage..."
rm -rf public/storage
php artisan storage:link || true

echo "Clearing caches so the new config is picked up..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "Release complete."
