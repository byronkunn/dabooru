#!/bin/sh
# Runs once per deploy, before the new container takes traffic.
set -e

echo "Running migrations..."
php artisan migrate --force

echo "Linking storage..."
php artisan storage:link || true

echo "Clearing caches so the new config is picked up..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "Release complete."
