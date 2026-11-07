# Inkwell

**Inkwell** is a gallery-first portfolio site for artists who post mature work: a 34-post demo gallery of images, GIFs, videos, albums and comic chapters, built for people who want their NSFW work in the same place as their SFW work instead of split across two accounts. Every post carries an explicit **SFW / Suggestive / Explicit** rating, the whole site sits behind an 18+ age gate, and a viewer who hasn't opted in never sees explicit content. Around the media it has the social basics — following feed, likes, saves, comments, follows, DMs, notifications, a series/reader view for comics — plus real file uploads for images, GIFs, videos, albums and comic pages. It is a Laravel 12 + Inertia + React app with zero client-side routing: every page is a real HTTP request backed by a controller and Eloquent queries, so the server is the source of truth for what a viewer is allowed to see.

## Screenshots

Screenshots of the running app (dark theme, desktop and mobile widths) are in `browser-screenshots/`. They were taken against seeded demo data, so they show exactly what you get after `migrate --seed`.

## Requirements

- **PHP 8.2+** (developed on PHP 8.4) with the usual Laravel extensions: `pdo_sqlite`, `fileinfo`, `mbstring`, `openssl`
- **Composer** 2
- **Node.js** 18+ and npm (developed on Vite 7)
- No database server needed — the default setup uses **SQLite** at `database/database.sqlite`

## Setup

```bash
composer install          # only if vendor/ is missing
cp .env.example .env      # skip if .env already exists
php artisan key:generate  # only if APP_KEY is empty

npm install
npx vite build            # or `npm run dev` for hot reload

php artisan migrate --seed
php artisan serve --port=8001
```

Then open **http://127.0.0.1:8001** and log in with the demo account.

> Port note: `php artisan serve` defaults to port 8000. During development that port was already in use, so Inkwell ran on **8001** — pass `--port=8001` if you want the same. Any free port works.

`composer setup` also exists as a one-shot bootstrap, but it appends a second `DB_` block to `.env`; the explicit steps above are what was actually used to get this build running.

## Where the content comes from

The library is built **entirely from the files in `sfw/`** — nothing is
invented. Running `php artisan migrate:fresh --seed` gives you:

| | Count | Note |
| --- | --- | --- |
| Image posts | 49 | one per file in `sfw/image/` |
| Video posts | 23 | one per usable file in `sfw/video/`; posters come from `sfw/thumbs/` |
| Credited artists | 6 | **only** files whose name carries a name (`john-staub-marvel-...` → John Staub, `pavel-yankovich-01` → Pavel Yankovich) |
| Unattributed posts | 64 | hashed file names (`148a0f0e….png`, `sample_<hash>.jpg`) — posted as **Unsorted**, not assigned a fake artist |

Titles come from the file name when it has real words in it, and are left empty
otherwise. No comics, likes, comments, follows, messages or notifications are
fabricated — there's no real data behind them, so the site starts with an empty
social graph.

`public/sfw/content` is a symlink to `sfw/`, so files are served in place with
no copying and no external image hosts.

### Crediting a file

Rename it so the first two real words are the artist:

```
sfw/image/jane-doe-dragon-study.jpg   →  Jane Doe, "Jane Doe Dragon Study"
```

then regenerate the manifest and reseed:

```bash
php artisan db:seed --class=DatabaseSeeder   # after: php artisan migrate:fresh
```

The manifest (`database/data/sfw/manifest.json`) holds every file's dimensions,
regenerated with `python3` + Pillow + `ffprobe`; the seeder never touches an
image library.

### Accounts

There are no demo personas. The credited artists above are real library
accounts with unguessable passwords; `/login` lists up to three of them for
one-click sign-in **only when `APP_ENV=local` or `testing`**, otherwise the
quick-login route does not exist.

To get an account you can actually log into and upload with:

```bash
php artisan tinker
>>> App\Models\User::create(['name'=>'You','username'=>'you','email'=>'you@example.com','password'=>'password']);
```


## Routes

| Method | Path                        | Name                  | Auth | What it is                                         |
| ------ | --------------------------- | --------------------- | ---- | -------------------------------------------------- |
| GET    | `/age-gate`                 | `age-gate`            | –    | 18+ gate shown to anyone without the cookie         |
| POST   | `/age-gate`                 | `age-gate.confirm`    | –    | Accept and set the 1-year cookie                    |
| POST   | `/age-gate/leave`           | `age-gate.leave`      | –    | Decline / clear the cookie                          |
| GET    | `/`                         | `gallery`             | –    | Main masonry gallery with filters                   |
| GET    | `/p/{post}`                 | `posts.show`          | –    | Post detail: media, comments, like, save            |
| GET    | `/u/{username}`             | `profile.show`        | –    | Artist profile and their works                      |
| GET    | `/comics`                   | `comics.index`        | –    | Comic series index                                  |
| GET    | `/comics/{slug}`            | `comics.show`         | –    | Series page with chapter list                       |
| GET    | `/comics/{slug}/{number}`   | `comics.read`         | –    | Reader for one chapter (page or scroll mode)        |
| GET    | `/comics/new`               | `comics.chapters.create` | ✓ | Create a new series                                 |
| GET    | `/upload`                   | `upload.create`       | ✓    | Upload form for image / gif / video / album         |
| GET    | `/following`                | `following`           | ✓    | Feed of the artists you follow                      |
| GET    | `/messages`                 | `messages.index`      | ✓    | Conversation list (`?to=handle` starts one)         |
| GET    | `/messages/{conversation}`  | `messages.show`       | ✓    | One conversation, send messages                     |
| GET    | `/notifications`            | `notifications.index` | ✓    | Likes, comments, follows; mark-all-read             |
| GET    | `/settings`                 | `settings.edit`       | ✓    | Profile + content/media/notification/privacy toggles |
| GET    | `/login`, `/register`       | `login`, `register`   | –    | Breeze auth pages                                   |
| GET    | `/forgot-password`, `/reset-password/{token}` | – | –    | Password reset                                      |
| GET    | `/verify-email`             | `verification.notice` | ✓    | Email verification                                  |
| GET    | `/dashboard`                | `dashboard`           | ✓    | Redirects to the gallery                            |
| GET    | `/up`                       | –                     | –    | Framework health check                              |

Write actions (all under `auth`, several throttled) live in `routes/web.php`: `POST /upload` (20/min), `POST /p/{post}/comments` (30/min), `POST /messages` (30/min), `POST /report` (10/min), plus like/unlike, save/unsave, follow/unfollow, comic follow, chapter store and reading-progress endpoints.

## Features

### Working

- **18+ age gate** — every request without confirmation redirects to `/age-gate`; accepting sets the `inkwell_age_ok` cookie for one year and stamps `age_confirmed_at` on the user. Auth and the gate itself always stay reachable.
- **Per-post ratings** — SFW / Suggestive / Explicit, stored on posts, chapters and series. Explicit posts are filtered out server-side (not hidden in the client) for guests and for logged-in users who turned explicit off.
- **Gallery** — masonry grid, 36 per page, filtered by **format** (all / image / gif / video / album / comic) × **sort** (top rated = likes, most viewed, newest) × **time window** (today / week / month / all time), plus a show-explicit toggle, trending tags and suggested artists.
- **Following feed** and follows between artists.
- **Post detail** — likes, saves/bookmarks, comments, and view counting with de-duplication (one view per viewer per post, using the `post_views` table).
- **Comics** — series index, series page with chapter list and continue-reading, and a reader with **page mode** and **vertical scroll mode**. Reading progress is persisted per user per series.
- **DMs** — conversation list with previews and unread counts, conversation view, sending messages, new conversation from a profile via `/messages?to=name`.
- **Notifications** — likes, comments and follows, with unread badge and mark-all-read.
- **Settings** — profile (name, handle, bio, location, website, avatar, banner uploads) and content/media/notification/privacy toggles (show explicit, mature preview, autoplay videos, animate GIFs, reduce motion, reader mode, email notifications, DM requests, read receipts).
- **Real uploads** — images (jpg/png/webp/avif, 40 MB), GIFs, videos (mp4/mov/webm, 500 MB), albums (2–20 images) and comic chapters (up to 60 pages, 15 MB each) stored on the `public` disk, with server-side validation of type and rating.
- **Auth** — register, login, logout, password reset and email verification flows (Laravel Breeze).
- **Themes** — custom dark "ink" Tailwind palette and responsive layout from mobile to a 1560px shell.

### Not yet

- **Video transcoding (HLS) is stubbed.** Video uploads are stored as-is and marked `processing`; nothing transcodes or generates a poster/derivatives yet.
- **No real-time.** There is no Reverb / websocket broadcasting; DMs and notifications update on normal requests only.
- **Seeded media is external.** Demo posts point at placeholder URLs (`picsum.photos`, `i.pravatar.cc`), so they need network access to render. Uploaded media is local.
- **Ads are placeholders.** There is one labelled sidebar ad slot with no network tags or tracking.
- **No moderation admin panel.** `reports` table and a report endpoint exist, and users have `is_admin` / `banned_at` fields, but there is no admin UI to act on them.
- **DMCA page is not built.**
- **No blocking UI.** A `blocks` table exists but nothing in the UI creates blocks.
- **No search.** Tag chips on the gallery are static, not queryable.

## Project structure

```
app/
  Models/              Eloquent models: User, Post, PostMedia, Media, Comic, ComicChapter,
                       ComicPage, ComicFollow, Comment, Like, Bookmark, Follow, PostView,
                       ReadingProgress, Conversation, Message, AppNotification, Setting,
                       Block, Report
  Http/Controllers/    GalleryController, PostController, ProfileController, ComicController,
                       ComicChapterController, UploadController, FollowingController,
                       MessageController, NotificationController, SettingsController,
                       AgeGateController, LikeController, BookmarkController, CommentController,
                       FollowController, ReportController, Auth/* (Breeze)
  Http/Resources/      PostResource, MediaResource, UserResource — the Inertia prop shapes
  Http/Middleware/     EnsureAgeConfirmed (global age gate), HandleInertiaRequests (shared props)
  Support/             Rating (labels/validation), AgeGate (cookie logic), ImageProcessor,
                       DummyImage
routes/                web.php (app routes), auth.php (Breeze), console.php
database/
  migrations/          users/auth + media/posts, comics, social, messaging tables
  seeders/             DatabaseSeeder — the whole demo dataset
resources/js/
  Pages/               Inertia pages: Gallery/Index, Posts/Show, Profile/Show, Comics/{Index,Show,Read,Upload},
                       Following/Index, Messages/{Index,Show}, Notifications/Index, Settings/Index,
                       Upload/Create, Gate, Auth/*
  Components/          Work (media card/renderer), Icons, and shared Breeze inputs/buttons
  Layouts/             AppLayout (app shell + sidebar + ad slot), AuthenticatedLayout, GuestLayout
  Lib/format.js        Rating classes, number/date formatting helpers
tests/
  Feature/             Gallery, Engagement, Comics, Upload, AgeGate, Auth/*
  Unit/                Skeleton example test
```

Pages resolve through `resources/js/app.jsx`; the Inertia root view is `resources/views/app.blade.php`. Config: `config/app.php` has `age_gate_test_bypass` (env `INKWELL_AGE_GATE_TEST_BYPASS`, default `true` so feature tests don't have to pass the gate).

## Testing

```bash
php artisan test        # or: composer test
```

**49 tests / 236 assertions**, all passing (PHPUnit 11). The feature suite covers gallery filters, sorts and explicit-visibility rules, likes, view de-duplication, follows, comments plus their notifications, uploads (valid files, albums, rejected types, rating validation), comics and the age gate. Auth flows (login, registration, password reset/confirmation/update, email verification) are covered too.

To exercise the age gate in tests, set `INKWELL_AGE_GATE_TEST_BYPASS=false`.

## Design docs

The design doc and the static HTML mockups (15 screens at 3 breakpoints) are in
[docs/superpowers/specs/](docs/superpowers/specs/): the design doc plus the
`mockup/` folder. The HTML screens are static mockups; `resources/js/Pages` is
the implementation.

## Moderation, legal and ownership

### Admin panel (`/admin`) — moderators only

Set `is_admin` on an account (the seeder creates `mod@inkwell.test` / `password`).
The **Moderation** link appears in the rail for admins; everyone else gets a 403.

| Page | What it does |
| --- | --- |
| **Reports** | Queue of user reports with a preview of the reported post/comment/user (explicit previews are blurred). "Remove & close" soft-deletes the content, bans the user if they were the target, and writes to the log. "Dismiss" closes it. |
| **Users** | Search by name/handle/email, filter banned or the Unsorted bucket, ban (requires a reason) and unban. Admins can't be banned. |
| **Moderation log** | Permanent, un-deletable record of every removal, ban, dismissal and DMCA notice, with who did it and when. Also lists DMCA notices. |

Every action is written to `moderation_actions` in the same request as the change.

### Legal pages (public, no age gate)

| URL | Page |
| --- | --- |
| `/terms` | Terms of service |
| `/privacy` | Privacy policy — states exactly what is stored today |
| `/content-policy` | Content policy — ratings, the no-minors rule, enforcement |
| `/dmca` | Copyright form, `/dmca/sent` confirmation |

Registration's checkbox now links to the real pages, and the footer links all four
on every page.

The DMCA form requires both statutory statements (good-faith belief and
acknowledgement of liability); a notice without them is rejected. Accepted
notices are stored in `dmca_notices` and appear in the moderation log.

> These documents are written starters, not legal advice. Have a lawyer review
> them before launch — see [docs/launch-checklist.md](docs/launch-checklist.md).

### Owning your content

- **Edit / delete your posts** — `/p/{id}/edit`, owner only. Delete is a soft delete, so it leaves the gallery and your profile immediately and a moderator can still see it.
- **Delete your comments** — from the post page, author only. The post's comment count is recalculated.
- **Delete your DMs** — the message body becomes `[deleted]`, so the conversation keeps its shape.
- **Block / unblock** — button on any profile and on other people's posts, plus `/settings/blocked`. Blocking hides their work from your gallery and Your Following feed, ends follows in both directions, and closes the DM thread. Nobody is told.

## Search

Available on every page:

- **Header box** (desktop): type-ahead suggestions grouped People → Comics → Works,
  arrow keys + Enter to navigate, Esc to close.
- **Mobile**: the magnifier in the top bar expands a search field; the bottom nav
  also has a Search tab.
- **`/` keyboard shortcut** focuses search from anywhere on a page.
- **`/search`** results page with Works / Comics / People tabs, artist filter chips,
  and a live count per tab.
- The gallery itself also accepts `?q=` — search from the gallery and it filters
  the grid in place with a "Clear search" chip.

Search matches post titles and captions, artist display names and handles, and
comic titles and descriptions. Explicit content is excluded for anyone who has
explicit turned off (the same rule the gallery uses). Terms shorter than 2
characters are ignored; `%` is escaped so it cannot act as a wildcard.

Controllers: `app/Http/Controllers/SearchController.php` (`/search`,
`/search/suggest`). UI: `resources/js/Components/SearchBox.jsx`,
`resources/js/Pages/Search/Index.jsx`. Indexes for the searched columns are in
`2026_11_06_110000_add_search_indexes.php`; swap the LIKE queries for MySQL
FULLTEXT or Scout/Meilisearch when the library outgrows `LIKE`.

## Moving to MySQL or S3

Nothing in the app is SQLite- or local-disk-specific; both are switched through `.env` only.

**SQLite → MySQL:** create the database, then in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inkwell
DB_USERNAME=...
DB_PASSWORD=...
```

Then run `php artisan migrate --seed` (or `--force` in production). Sessions, cache and queues are already on the `database` driver, so they migrate with it. No code changes are needed in controllers, models or migrations — the schema uses plain Laravel column types.

**Local disk → S3:** new uploads are written to whichever disk `FILESYSTEM_UPLOAD_DISK` names (default `public`), so in `.env`:

```dotenv
FILESYSTEM_UPLOAD_DISK=s3
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=inkwell-media
# optional for S3-compatible stores:
# AWS_ENDPOINT=https://... 
# AWS_USE_PATH_STYLE_ENDPOINT=true
```

The `s3` disk reads those variables from `config/filesystems.php`. Existing rows keep their own `disk` column, so old local uploads still resolve after the switch and new uploads go to the bucket. For production, also consider switching `BROADCAST_CONNECTION`, `QUEUE_CONNECTION` and `CACHE_STORE` off `database` once those services exist.

## Switching accounts

The **Accounts** button in the bottom-left (desktop) lists the credited library
artists, so you can look at the site as different people. It only renders when
the server sends `demoAccounts`, which happens only when the `demo.login` route
exists (`APP_ENV=local` or `testing`). On mobile it lives in Settings.

