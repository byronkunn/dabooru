# BooruHub

**BooruHub** is a gallery-first social site for artists who post mature work: images, GIFs,
videos, albums and comics in one place instead of split across two accounts. Every work
carries an explicit **SFW / Suggestive / Explicit** rating, the site sits behind an 18+ age
gate, and a viewer who hasn't opted in never sees explicit content.

Around the media it has the social basics — a following feed, likes, star ratings, saves,
threaded comments, follows, direct messages with attachments and stickers, notifications,
and a series/reader view for comics — plus real file uploads for images, GIFs, videos,
albums and comic pages.

Also included: a Creator Studio with analytics, traffic sources and bulk editing;
account-level tag mutes applied to every feed and to search; a skippable post-registration
setup that gives a new reader a working feed immediately; a private report-status page; a
bug report form; a tip page with crypto wallet addresses; and an admin panel with a reports
queue, staff roles, moderation log, DMCA queue, announcements and site logs.

It is a **Laravel 12 + Inertia + React** app with no client-side routing: every page is a
real HTTP request backed by a controller and Eloquent queries, so the server is the source
of truth for what a viewer is allowed to see.

---

## This repository contains no media

There is **no artwork, no seeded posts and no database** in git. The database ships empty
and `sfw/` ships empty.

That is deliberate — the media is hundreds of megabytes of binaries, which does not belong
in a git repository. A fresh clone gives you a fully working application with nothing in
it: you sign in, and the gallery says *"Nothing in this filter yet."*

To get a populated site, either upload your own work through **Upload**, or drop files into
`sfw/` and run the seeders (see [Populating the site](#populating-the-site)).

## Requirements

- **PHP 8.2+** (developed on PHP 8.4) with `pdo_sqlite`, `fileinfo`, `mbstring`, `openssl`
- **Composer** 2
- **Node.js** 18+ and npm (built with Vite 7)
- No database server needed — SQLite by default at `database/database.sqlite`
- **ffmpeg** (optional) — used for video poster frames when present

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

npm install
npm run build            # or `npm run dev` for hot reload

php artisan migrate
php artisan storage:link
php artisan serve --port=8001
```

Open **http://127.0.0.1:8001**.

`php artisan serve` defaults to port 8000; pass `--port=8001` if that port is taken (it was,
during development).

### Getting an admin account

The database starts with no accounts at all. Register through the site, then promote
yourself:

```bash
php artisan tinker
>>> App\Models\User::where('email', 'you@example.com')->first()->forceFill(['is_admin' => true])->save();
```

Then set your ID in `.env` so you also hold owner rights (tip wallets, staff management):

```
ADMIN_OWNER_ID=1
```

`php artisan tinker --execute="echo App\Models\User::where('email','you@example.com')->value('id');"`
prints the ID to use.

**`ADMIN_OWNER_ID` must be updated after every `migrate:fresh`.** IDs are assigned in
insertion order, so wiping the database renumbers everyone and silently points the owner
config at the wrong account (or at nobody). `php artisan config:clear` afterwards.

## Populating the site

Two ways, and they are independent.

**Upload through the UI.** Sign in, open **Upload**, and post images, GIFs, video, albums or
comic pages. This is the real path and needs nothing else.

**Seed from a folder.** If you have a local library, put files in `sfw/` and run the
seeders. Expected layout:

```
sfw/image/     photos, illustrations, GIFs
sfw/video/     mp4/webm
sfw/thumbs/    optional poster frames for the videos
```

Then regenerate the dimensions manifest and seed:

```bash
php artisan migrate:fresh --seed
```

The seeders read `database/data/sfw/manifest.json` to know each file's dimensions, so that
manifest has to match what is on disk, and it **is** tracked in git. It is generated with
`python3` + Pillow + `ffprobe`. The schema is a JSON object with four keys — `image`,
`video`, `avatar` and `banner-image` — each an array of entries holding `file`, `w` and `h`
(plus `duration` for video).

Files are credited from their name: `jane-doe-dragon-study.jpg` becomes **Jane Doe**,
*"Jane Doe Dragon Study"*. Files with hashed names are posted as **Unsorted** rather than
assigned a fake artist.

### About the seeders

`DatabaseSeeder` runs, in order: `AdminSeeder`, `KnowledgeSeeder` (the works built from
`sfw/`), `SampleComicsSeeder`, `MediaTagsSeeder` (tag suggestions), `SampleLikesSeeder`
and `TagCategoriesSeeder`.

Two further seeders exist but are **not** in that chain, because they are only useful when
testing:

| Seeder | Adds | Run with |
| --- | --- | --- |
| `QaAccountsSeeder` | `@qa.test` accounts, password `password` | `db:seed --class=QaAccountsSeeder` |
| `MediaCommentsSeeder` | sample comments on media posts | `db:seed --class=MediaCommentsSeeder` |

**Do not run either in production** — they create accounts with a known password.

**Like counts are derived, never invented.** `likes_count` is a cache recalculated from the
`likes` table on every like and unlike, so it cannot drift or go negative. Repair old data
with:

```bash
php artisan posts:reconcile-counters --dry-run
```

## Media storage

Uploads are stored on the `public` disk (`storage/app/public`) and served through
`public/storage`. Direct-message attachments are stored on the **private** disk and served
through an authenticated route that checks conversation membership — they are never
publicly reachable.

Images are always re-encoded on upload to strip EXIF/GPS, including when a sender asks to
keep the "original". PNG transparency and GIF animation are preserved.

`sfw/` is deliberately gitignored. So are `public/build`, `vendor`, `node_modules`,
`.env` and the SQLite database.

## Maintenance commands

```bash
php artisan posts:reconcile-counters [--dry-run]   # repair like/comment counters
php artisan tags:prune-weak [--dry-run] [--all]    # drop auto-generated one-off tags
php artisan messages:privatize-attachments         # move DM files to private storage
```

## Testing

```bash
php artisan test          # 382 feature tests
npm test                  # JS unit tests
```

The suite builds its own fixtures, so it runs on a completely empty database and does not
need `sfw/` to be populated.

## Production notes

Before running this for real:

- `APP_ENV=production`, `APP_DEBUG=false` — debug mode leaks environment variables in error pages
- Real mail credentials — password resets and urgent-report alerts are written to the log otherwise
- A queue worker (`php artisan queue:work`) and cron (`php artisan schedule:run`) — scheduled
  posts, scheduled messages and expiring messages all depend on them and fail silently without
- `SESSION_SECURE_COOKIE=true` behind HTTPS, plus trusted proxies if you run behind a load balancer
- Daily backups of `database/database.sqlite` and `storage/app/`
- Do not run `QaAccountsSeeder`; rotate or delete the seeder admin account

## Licence

No licence file is included. Add one before distributing; the code is unlicensed as-is,
which means all rights are reserved by default.
