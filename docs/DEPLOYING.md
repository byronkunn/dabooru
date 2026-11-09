# Deploying Dabooru

The app is a Laravel server, so it needs a PHP host — not GitHub Pages. This is the
shortest path to a URL you can open from anywhere.

## Render (recommended)

[Render](https://render.com) runs the `Dockerfile` in this repo and gives you Postgres,
a queue worker and a scheduler on one bill.

### 1. Create the blueprint

Push the repo, then in Render: **New → Blueprint**, pick `byronkunn/dabooru`. Render reads
`render.yaml` and provisions:

| Service | What it does |
| --- | --- |
| `dabooru` | Web app, `php artisan serve` behind Render's proxy |
| `dabooru-worker` | `queue:work` — scheduled messages, expiring messages, video posters |
| `dabooru-cron` | `schedule:run` every minute |
| `dabooru-db` | Postgres |

The worker and cron are not optional. Without them scheduled posts never publish,
scheduled DMs never send, and expiring messages never expire — silently, with no error.

### 2. Fill in the secrets

Render prompts for the values marked `sync: false`:

| Variable | Value |
| --- | --- |
| `APP_URL` | `https://<your-service>.onrender.com` (exact, including scheme) |
| `APP_KEY` | output of `php artisan key:generate --show` |
| `MAIL_*` | SMTP credentials (Mailgun, Postmark, Resend) |
| `ADMIN_OWNER_ID` | your user ID after registering — see below |
| `ADMIN_URGENT_REPORT_EMAIL` | where urgent report alerts go |

`APP_URL` must match the real URL exactly. If it is wrong, uploaded images 404 and
links point at the wrong host.

### 3. Register and become admin

The database is created empty — there is no admin and no content.

1. Open the site and **register**
2. Find your ID: Render → your Postgres → **PSQL**, then `select id, email from users;`
3. In Render → `dabooru` → Environment, set `ADMIN_OWNER_ID` to that ID
4. Promote yourself to admin:

```sql
update users set is_admin = true where email = 'you@example.com';
```

### 4. Add content

Either post through **Upload** in the UI, or seed from `sfw/`:

```bash
# from a Render shell on the web service
php artisan db:seed
```

`sfw/` is not in the repo, so seeding on Render only works if you put files there first.

## Persistent storage

Uploads would normally sit on the container's local disk, which Render discards on every
deploy — wiping every avatar, gallery upload and DM attachment.

`render.yaml` prevents that by attaching a **10 GB Render Disk** at `/var/data` and pointing
both storage roots at it:

```yaml
disk:
  name: dabooru-storage
  mountPath: /var/data
  sizeGB: 10
envVars:
  - key: PUBLIC_DISK_ROOT
    value: /var/data/public      # gallery uploads, avatars, thumbnails
  - key: PRIVATE_DISK_ROOT
    value: /var/data/private     # DM attachments
```

Disk is a paid add-on. On the free tier you have no persistent disk, so uploads will be
lost on redeploy — acceptable for trying the site, not for real users.

For real traffic, Cloudflare R2 is a good place for gallery uploads. The app's `s3` disk
uses the R2 S3 API for writes and an R2 custom domain for public reads. New production
uploads use R2; existing files stored on the Render disk continue to use their recorded
`public` disk until migrated.

### Cloudflare R2 media storage and caching

1. In Cloudflare, create an R2 bucket (for example, `dabooru-media`). Keep the bucket
   private at first, then attach a custom domain such as `media.example.com` under the
   bucket's **Settings → Public access → Custom Domains**. Cloudflare creates the DNS
   record and enables the custom domain's CDN path. Do not use the `r2.dev` development URL
   for production.
2. Create an R2 API token scoped to this bucket with **Object Read & Write**. Save its
   Access Key ID and Secret Access Key. Get the account ID from Cloudflare's R2 overview.
3. In Render → `dabooru` → **Environment**, fill the `AWS_*` entries from the blueprint:

   | Variable | Value |
   | --- | --- |
   | `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | R2 token credentials |
   | `AWS_DEFAULT_REGION` | `auto` |
   | `AWS_BUCKET` | Exact R2 bucket name |
   | `AWS_ENDPOINT` | `https://<account-id>.r2.cloudflarestorage.com` |
   | `AWS_URL` | `https://media.example.com` (your R2 custom domain, no trailing slash) |
   | `FILESYSTEM_UPLOAD_DISK` | `s3` (set by the blueprint) |

4. Add a Cloudflare Cache Rule for the media hostname so all object paths are eligible
   for edge caching; the app sets a one-year immutable Cache-Control header because upload
   object names are random and never overwritten. Consider Smart Tiered Cache for large
   libraries. Keep DM attachments on the app's private local disk; they are not uploaded to
   R2 or exposed through the public media domain.

The R2 custom domain is public: anyone who knows an object URL can request that object.
The application already treats gallery upload URLs as public media URLs. R2's `r2.dev`
endpoint does not provide the production caching and security controls of a custom domain.

## Railway (alternative)

Railway also builds the `Dockerfile` directly:

1. **New Project → Deploy from GitHub repo**
2. Add the **Postgres** plugin; Railway injects `DATABASE_URL`
3. Set the same environment variables as above
4. Add two more services from the same repo, overriding the start command to
   `sh docker/worker.sh` and `sh docker/scheduler.sh`, and set the scheduler to run on a
   cron schedule of `* * * * *`
5. In Settings → Deploy, set the deploy command to `sh docker/release.sh`

## Not suitable

- **GitHub Pages** — static only, cannot run PHP
- **Vercel / Netlify** — serverless Node; no persistent PHP process, no queue worker

## Before real traffic

Adult content gets accounts suspended on most platforms. Beyond that:

- Verify you are allowed to host this content under your host's AUP
- Set `APP_DEBUG=false` and `APP_ENV=production` (the blueprint already does)
- Configure mail, or password resets silently go to the log
- Add offsite database backups — Render's free tier does not include them
- Rotate or delete the `mod@dabooru.test` seeder account if you ran `AdminSeeder`
- Do not run `QaAccountsSeeder`; those accounts have a known password
