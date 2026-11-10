# Deploying Dabooru on the Hostinger VPS

The production site runs from `/var/www/dabooru` on Ubuntu, with Caddy, PHP-FPM,
PostgreSQL, a systemd queue worker, and a systemd scheduler timer. The Render and
Railway files in this repository are optional examples; they do not describe the
live Hostinger server.

## Before each release

1. Confirm a recent database backup exists and the media bucket is healthy.
2. Review the commit and migration changes. Do not use `migrate:fresh` on production.
3. Deploy from the VPS console or an approved SSH session:

```sh
cd /var/www/dabooru
git status --short
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=AdultTagsSeeder --force
php artisan optimize
php artisan queue:restart
sudo systemctl restart dabooru-queue.service
```

`npm ci` needs the checked-in lockfile and should run only when frontend
dependencies/build inputs have changed. If it is skipped, `npm run build` uses the
already installed dependencies. Keep the previous commit ID available for rollback;
take a database backup before any migration that cannot be rolled back safely.

After deployment, check:

```sh
curl -fsS https://booruhub.tech/up
systemctl is-active caddy php8.5-fpm postgresql@18-main dabooru-queue.service dabooru-scheduler.timer
systemctl list-timers dabooru-scheduler.timer --no-pager
```

Then open the site in a private window and confirm the age gate, login, a gallery
page, messaging, and the admin report queue. Check `journalctl -u dabooru-queue`
and `journalctl -u dabooru-scheduler.service` for recent errors. Never paste `.env`
contents or credentials into a ticket or chat.

## Production services

- Caddy serves the web app; PHP-FPM runs PHP 8.5.
- PostgreSQL binds to `127.0.0.1` and `::1` only.
- `dabooru-queue.service` runs as `www-data` and is configured to restart.
- `dabooru-scheduler.timer` runs `php artisan schedule:run` each minute as `www-data`.
- UFW defaults to deny incoming traffic. HTTP/HTTPS are allowed from Cloudflare IP
  ranges; SSH remains reachable on port 22. Recheck this after firewall changes and
  keep the Cloudflare address ranges current.
- Set `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`,
  `LOG_CHANNEL=daily`, and a suitable `LOG_LEVEL` in the production `.env`.
- Public uploads use the configured S3-compatible disk. Direct messages use the
  private local disk and must not be moved to the public media bucket.

## Recovery and account operations

Hostinger currently provides weekly VPS backups/snapshots. Those are not a substitute
for an independent database and media backup. Configure an encrypted offsite copy,
monitor its success, and perform a restore drill before opening registration widely.
Keep its encryption key outside the VPS and its backup destination private.

Keep the Hostinger plan renewed. Maintain an uptime/error alert, review failed queue
jobs and urgent reports daily, and confirm the transactional mail provider accepts
adult platforms. Publish SPF, DKIM, and DMARC records for the sending domain.

Before broad public launch, have qualified counsel review the age-assurance approach,
terms, privacy notice, content policy, DMCA process, reporting/retention obligations,
and the jurisdictions where the site will be available. Assign a named person to
review urgent safety reports every day and provide a documented escalation path.
