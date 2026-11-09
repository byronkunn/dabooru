# Dabooru

**Dabooru** is a gallery-first portfolio site for artists who post mature work: a 129-work gallery of images, GIFs, videos, albums and comic chapters, built for people who want their NSFW work in the same place as their SFW work instead of split across two accounts. Every post carries an explicit **SFW / Suggestive / Explicit** rating, the whole site sits behind an 18+ age gate, and a viewer who hasn't opted in never sees explicit content. Around the media it has the social basics — following feed, likes, star
ratings, saves, threaded comments, follows, DMs with attachments and stickers,
notifications, a series/reader view for comics — plus real file uploads for
images, GIFs, videos, albums and comic pages.

Also included: a Creator Studio with analytics, traffic sources and bulk editing;
account-level tag mutes applied to every feed and to search; a skippable
post-registration setup that gives a new reader a working feed immediately; a
private report-status page; and an admin panel with a reports queue, staff
roles, moderation log, DMCA queue and site logs. It is a Laravel 12 + Inertia + React app with zero client-side routing: every page is a real HTTP request backed by a controller and Eloquent queries, so the server is the source of truth for what a viewer is allowed to see.

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

> Port note: `php artisan serve` defaults to port 8000. During development that port was already in use, so Dabooru ran on **8001** — pass `--port=8001` if you want the same. Any free port works.

`composer setup` also exists as a one-shot bootstrap, but it appends a second `DB_` block to `.env`; the explicit steps above are what was actually used to get this build running.

## Where the content comes from

> **Media is not in this repository.** The image and video binaries under
> `sfw/` are large, so they are gitignored. A fresh clone starts with no
> artwork: drop your own files into `sfw/image/`, `sfw/video/` and
> `sfw/thumbs/`, regenerate the manifest, then seed.
> `database/data/sfw/manifest.json` (the dimensions index) *is* tracked, so the
> seeder knows what to expect.

The library is built **entirely from the files in `sfw/`** — nothing is
invented. Running `php artisan migrate:fresh --seed` gives you:

| | Count | Note |
| --- | --- | --- |
| Image posts | 49 | one per file in `sfw/image/` |
| Video posts | 23 | one per usable file in `sfw/video/`; posters come from `sfw/thumbs/` |
| Credited artists | 6 | **only** files whose name carries a name (`john-staub-marvel-...` → John Staub, `pavel-yankovich-01` → Pavel Yankovich) |
| Unattributed posts | 64 | hashed file names (`148a0f0e….png`, `sample_<hash>.jpg`) — posted as **Unsorted**, not assigned a fake artist |

Titles come from the file name when it has real words in it, and are left empty
otherwise.

### Seeded social data

The media is real; the engagement around it is clearly-labelled sample data so
the feeds, rankings and Studio analytics have something to work on:

| Seeder | Adds |
| --- | --- |
| `SampleComicsSeeder` | 3 series / 9 chapters built from the `sfw/` images, one feed post per chapter |
| `SampleLikesSeeder` | real rows in the `likes` table, then each post's `likes_count` is recalculated from them |
| `SampleMessagesSeeder` | conversations to the admin account |
| `QaAccountsSeeder` | the `@qa.test` accounts used for manual testing (password `password`) |

**Like counts are derived, never invented.** `likes_count` is a cache
recalculated from the `likes` table on every like and unlike, so it can't drift
or go negative. Run `php artisan posts:reconcile-counters` to repair old data
(use `--dry-run` first).

### Maintenance commands

```bash
php artisan posts:reconcile-counters [--dry-run]   # repair like/comment counters
php artisan tags:prune-weak [--dry-run] [--all]    # drop auto-generated one-off tags
php artisan messages:privatize-attachments         # move DM files to private storage
```

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
| GET    | `/u/{username}/{type}`      | `profile.connections` | –    | Paginated public followers/following lists for profile modals |
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
- **Gallery** — masonry grid, 36 per page. Up/down arrows control **sort** (most liked / most viewed / newest); the **adjustments** icon opens format, time period, tags, a minimum average star-rating scale, and explicit-content controls. Choose Any or 1–5 stars and up; Any includes unrated works, while a threshold requires a matching unrounded average. The `min_stars` URL filter applies across the library before pagination. Legacy `min_likes` / `min_views` links remain supported. Sorting and filtering share URL state, and each change starts at page 1. The badge counts active filtering categories, excluding sort and the explicit preference. Clear filters preserves those two settings; Reset sort preserves filters. Format tabs and the panel stay synchronized, and tag selection preserves the time period.
- **Video length** — a two-handle, nonlinear slider with labeled ticks from 15 seconds through 25 minutes, supporting durations through 86400 seconds without separate numeric fields. Any endpoints mean no minimum or maximum, not a cap on video length. The slider is enabled for Videos; other formats offer **Switch to videos**. Selecting a non-video format clears duration bounds. Filtering happens across the whole library before pagination, not just loaded works. Include tags matches **any** selected tag; exclude tags hides works matching **any** excluded tag.
- **Video card previews** — use the existing poster when available; otherwise load a paused, muted video preview only near the viewport. Gallery and recommendation cards never try to decode a video file as an image. Unsupported videos show an explicit preview-unavailable message.
- **Following feed** and follows between artists.
- **Post detail** — likes, saves/bookmarks, comments, and view counting with de-duplication (one view per viewer per post, using the `post_views` table).
- **Star ratings** — five selectable stars on each work page, with the average, rating count, and your selected score. Signed-in, unrestricted users can submit or change one whole-number 1–5 rating per work. Guests can see the summary and are prompted to sign in. Ratings remain separate from content-safety labels, likes, and gallery sorting.
- **Comics** — series index, series page with chapter list and continue-reading, and a reader with **page mode** and **vertical scroll mode**. Start/Pause slideshow advances pages every 3, 5, 10 or 15 seconds; the interval is remembered on the device. Starting switches to page mode, starting at the end restarts the chapter, and playback stops at the last page or an image error. Timing begins after the page loads and pauses while the tab is hidden. Switching to scroll mode stops playback. Reading progress is persisted per user per series. Comic cards and search suggestions use the original cover image when no thumbnail exists.
- **Messaging** — direct chats with requests and privacy controls and private Saved Messages. The inbox supports search, unread filters, pinning, muting, archiving, and folders. Chats include replies, reactions, forwards, private attachments, voice and video notes, artwork cards, stickers, polls, checklists, and scheduled sends.
- **Notifications** — likes, comments and follows, with unread badge and mark-all-read.
- **Settings** — a category menu and single detail pane on desktop; mobile opens one category at a time with an All settings back button. Section links survive reloads/back navigation, and switching sections preserves unsaved form edits. Profile fields and avatar/banner uploads support validation feedback, saved confirmation, default-avatar/banner removal, and HTTP(S) website links. Account settings support password-protected email changes with re-verification, verification resends, password changes, and confirmed account deletion (including published, draft, and archived works). Privacy controls explicit visibility, new DMs, read receipts, and forwarding through the same preferences as Messages. Notification switches govern future likes/comments/follows notifications; each switch saves only its changed value, with atomic validation and saved/error feedback. Unblocking is available through Blocked accounts.
- **Profile images** — owners can use the camera buttons on their profile avatar and banner to choose an image, preview it, and save without changing other profile details. JPG, PNG, and WebP are supported (5 MB avatar, 10 MB banner).
- **Mobile ads** — compact, labeled sponsor placements appear inline in the gallery, Comics, and below media posts. They never cover media or navigation, and the comic reader stays ad-free. Set `AD_IMAGE_URL`, `AD_DESTINATION_URL` (HTTP/S), and `AD_SPONSOR_NAME` to display a direct sponsor's creative and sponsored link; otherwise slots explicitly show "Ad space available." This does not enroll the site in an ad network or generate revenue until a sponsor is configured. No arbitrary ad scripts or tracking are injected.
- **Desktop discovery rail** — one configurable sponsor placement is followed by recently active creators and popular works, ranked by likes this week with lifetime likes as a fallback. Both recommendation lists respect viewer rating and block settings, and the gallery's explicit filter can further restrict them. Artist activity counts only visible ratings, and work cards show the credited artist when available. If the sponsor image fails, the slot displays a notice instead. Artist-paid profile or work boosts are planned, but are not currently available; the sponsor slot is configuration-only and does not process payments.
- **Discover** — the Gallery / Discover / Following tabs separate browsing, recommendations, and followed work. Discover mixes followed users/tags, artists and tags from up to 100 visible works liked in the last 90 days, fresh work, likes in the last week, and momentum (last 24 hours versus the previous 24 hours). Signal contributions are capped to prevent lifetime popularity or tag stuffing from dominating. Guests and new accounts get popular/rising/fresh recommendations. Already-liked work, the viewer's uploads, drafts, deleted work, blocked accounts, and disallowed ratings stay out. Recommendation labels explain the signals without exposing other users' likes. The old curated-shelf page and sidebar icon remain retired; staff shelf records are preserved.
- **Following users and tags** — follow or unfollow tags from gallery tag filters, the tag directory/wiki, or a work's all-tags dialog. The Following feed combines work from followed users and tags, respecting ratings, drafts, deletion, and blocks. Expand Manage following to browse followed tags and unfollow users. User profiles and works also show an explicit Unfollow button. Tag aliases resolve to the canonical tag, and tag merges preserve followers.
- **Announcements** — administrators can create drafts, publish, edit, unpublish, or delete messages under Admin > Announcements. The newest published/updated message appears as an expandable, dismissible strip on regular site pages, not admin pages or the comic reader. Dismissal lasts through in-app navigation until reload; edited messages appear again.
- **Media page** — media appears first, followed by creator attribution/avatar and the user follow control. Below the media, tags preview at most five colored chips without category headings; View all / add tags opens the existing modal with every tag grouped by category. The tag directory has category links and filters, and wiki editors can change categories through the existing review workflow. Neutral adult-content warnings supplement ratings rather than overriding them. Verified, unrestricted members can contribute with a reason; existing tags are retained, and stale versions cannot overwrite newer edits.
- **Tag categorization** — `php artisan db:seed --class=TagCategoriesSeeder` organizes previously general descriptive tags without overwriting curated categories or locked tags, with complete revision history. New recognizable garment, pose, appearance, and setting names receive descriptive categories through the same helper; ambiguous names stay General for review.
- **Admin tag management** — Tags & merges in the admin navigation (or Manage tags in the public directory) exposes tag editing, categories, aliases, definitions, lock/unlock, history, and administrator-only merges. Merge selects the canonical destination, checks both tag versions, moves and deduplicates tags on all linked media (including archived media), preserves followers and old-name redirects, and keeps the destination definition/category. Destination implications apply normally; any failure rolls back the whole merge. Source relationships require explicit review/removal first rather than silent deletion. Media/tag revisions and the moderation log record changes. Merge revisions cannot be reverted individually: corrections must account for media, redirects, and followers together.
- **Category colors** — consistent category colors appear in directory filters and badges, media tag chips, tag pickers, wiki badges, and admin management. Visible category labels and chip tooltips remain, so color is never the only category indicator and is not a content-rating signal.
- **Upload tags** — images, videos, albums, and comic chapters support up to 30 entered tags, each up to 40 characters, including private drafts and Studio edits. Tag fields show the current count. Approved implications may expand the final list to the existing 50-tag safety limit; editing existing published tags preserves that limit so expanded tags are not lost. Existing media are not automatically assigned invented tags.
- **Existing sample-media tags** — `php artisan db:seed --class=MediaTagsSeeder` fills reviewed existing media posts to 30 tags, preserving their previous tags and recording tag history. The reviewed filename mapping is in `database/data/sfw/tags.php`; albums combine descriptions from their images. This is additive and repeatable, not a database reset. Private drafts and unknown files are not guessed; unmapped published posts are reported. The normal library seeder also applies the reviewed mapping.
- **Sample discussions (optional, local only)** — `php artisan db:seed --class=MediaCommentsSeeder` adds five clearly labeled sample comments and one sample reply per comment to every published media post, using the existing demo accounts. It keeps existing discussions, skips drafts/deleted posts, does not recreate deleted samples, updates comment totals, and is repeatable without duplicates. It does not send notifications or run as part of the normal library seeder. Media pages display replies underneath their parent comments.
- **Image thumbnails** — image previews use the original image when no separate thumbnail exists, including comic chapter covers. Videos still require a separate poster image; private media retain their existing access-controlled URLs.
- **Collection work view** — named private/shared collections use a responsive gallery-card grid. Every card opens its own work, including multi-image albums and comics. Covers and counts follow each viewer's visibility preferences.
- **Browse search** — the centered search bar combines words, multiple `#tags` or `tag:"multi word"` tags, exclusions, and `by:artist` filters. Autocomplete only edits the draft; results change when Go is clicked, not while typing or choosing suggestions. No suggested tag-chip row appears below the search bar. The standalone Search navigation/page is removed; old search links redirect to Browse, retaining their query. Existing format, sort, time, and visibility filters remain available.
- **Collection shelves** — Library supports descriptions, selected covers, multiple memberships, recently-added/custom ordering, accessible reordering, link sharing and revocation. Removing a member or deleting a shelf keeps works saved; unsaving offers Undo to restore its memberships.
- **Real uploads** — images (jpg/png/webp/avif, 40 MB), GIFs, videos (mp4/mov/webm, 500 MB), albums (2–20 images) and comic chapters (up to 60 pages, 15 MB each) stored on the `public` disk, with server-side validation of type and rating.
- **Auth** — register, login, logout, password reset and email verification flows (Laravel Breeze).
- **Themes** — custom dark "ink" Tailwind palette and responsive layout from mobile to a 1560px shell.

### Not yet

- **Video transcoding (HLS) is stubbed.** Video uploads are stored as-is and marked `processing`; nothing transcodes or generates a poster/derivatives yet.
- **Seeded media is external.** Demo posts point at placeholder URLs (`picsum.photos`, `i.pravatar.cc`), so they need network access to render. Uploaded media is local.
- **Ads are placeholders.** There is one labelled sidebar ad slot with no network tags or tracking.
- **No moderation admin panel.** `reports` table and a report endpoint exist, and users have `is_admin` / `banned_at` fields, but there is no admin UI to act on them.
- **DMCA page is not built.**
- **No blocking UI.** A `blocks` table exists but nothing in the UI creates blocks.
- **No search.** Tag chips on the gallery are static, not queryable.

## Messaging deployment

Run `php artisan migrate` and `npm run build` when deploying messaging. Existing accepted conversations stay accepted. Every new direct chat starts with one text-only request, even when the recipient follows the sender. Only the recipient can accept; neither person can send further messages until then. Privacy settings still control who can initiate a request.

The compact chat composer groups files, voice/video notes, work sharing, polls/checklists, and quick replies in the attachment menu. Stickers have a dedicated searchable pack picker with a preview before sending. The send-options menu holds silent sending, spoilers, original-image metadata, and text scheduling. These tools unlock after request acceptance (and remain available in private Saved Messages).

Opening the sticker catalog from a chat preserves that conversation through searches and pack creation/removal. Both Back controls return to the originating chat; opening the catalog directly returns to Messages instead.

Create sticker packs from **Messages → Stickers → Browse or publish packs → New pack**. Upload multiple PNG, WebP, JPG, AVIF or animated GIF files directly without creating gallery posts, or select existing original SFW images. A pack supports 1–16 stickers total, up to 5 MB and 2048 × 2048 pixels per upload. Files retain animation, transparency and embedded metadata; remove personal metadata before uploading. New packs default to Private in the creation form. Owners can publish for everyone or make a pack private later. Search the catalog or chat picker by pack name, creator or uploaded sticker name. Private packs and their original files are owner-only; stickers already sent remain visible to conversation participants even after a pack becomes private or is deleted. Run `php artisan migrate` when deploying these changes.

Run the Laravel scheduler every minute to deliver scheduled messages and purge expired messages and unused attachments. For local testing, run `php artisan schedule:work`. Scheduled messages are rechecked against permissions at delivery time. Open moderation reports retain a copy of the reported message and attachment until the report is closed.

The inbox and open chat pause polling in hidden tabs. Open threads check for changes before refreshing and acknowledge messages when they come into view. Former groups, channels, and channel inboxes are retired. Their records stay in the database and their scheduled messages are paused.

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
- **Profile actions** — Follow changes to Following after a successful save; unfollowing requires confirmation. Message is a compact icon shown only when the recipient's privacy settings allow contact (existing conversations remain available unless blocked). The More menu holds Block/Unblock with confirmation; blocked accounts cannot follow or message each other, and unblocking does not restore old follows.
- **Creator Studio** — a dedicated left-nav link opens Overview, Posts, Analytics, Planner, and Comics. Private owner-only 7/30/90-day metrics compare recorded daily views, retained likes/saves/comments/star votes, and net follower changes with the preceding period. Top posts and sortable comparisons support views, likes, saves, engagement, like momentum, and star averages with vote counts. Original artwork and shared/credited uploads remain separate filters; repost metrics never imply artistic ownership.
- **Analytics accuracy** — daily views are deduplicated per viewer/work/day, not impressions or unique people. Distinct signed-in viewers are reported separately; guests are not represented as unique people. Traffic tracks only the first referrer category for a daily view, never full external URLs. Sources, follow losses, and chapter-reader tracking start with the Studio tracking migration; pre-existing unknown sources and undated saves are not reconstructed. Follow losses include unfollows, block removals, and follower account deletions. Retained interaction charts may change when interactions are removed. All chart dates and upload-hour comparisons use the displayed server timezone. Posting-time suggestions require at least 10 published posts, 20 likes, and 3 posts in an hour within the selected period and are exploratory, not causal claims.
- **Comic insights** — series summaries include chapter opens, tracked readers, signed-in completion, and the last page reached by incomplete signed-in readers. Revisited pages cannot reduce the recorded maximum or erase completion. Guest sessions contribute reader counts but not completion rates; missing historical data and active readers are not labeled as proven abandonment.
- **Private planner** — save ideas, notes, deadlines, and stages (idea, in progress, draft, ready, scheduled, published). A calendar agenda groups dated plans; dates are entered in the browser timezone and stored in UTC. Link an owned work or chapter draft to schedule publication. Editing the stage away from Scheduled or deleting the plan cancels its schedule without deleting the draft. Failed releases remain private and show a review/reschedule error. Publishing rechecks ownership, bans, moderation/takedowns, and ready media under transaction locks.
- **Scheduled releases** — enable Laravel's scheduler every minute in production (`* * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1`), or `php artisan schedule:work` locally. It runs `studio:publish-scheduled` every minute; that command can also be used for a one-off due-release check. Scheduling saves a future release, not a browser timer: closing Studio does not cancel it, but no release runs while the server scheduler is stopped. The command reports publication/failure counts and failed plans remain visible in Studio.
- **Artist credit pages** — Artists do not need an account for their credited work to have a page. Artists in the navigation opens a searchable directory; credited names on cards, work pages and search results open a separate paginated artwork page. Pages group exact supplied artist names across uploaders, respect content visibility and blocks, and have no member Follow/Message controls. They explicitly distinguish an unverified credit from a member identity or endorsement. Uploaders remain labeled “shared by”; an approved credit claim links that work to the verified member without transferring the upload. Existing and future published artist-name credits appear automatically; no fake accounts or migration are needed.

## Search

- **Browse** has a centered search bar on desktop and mobile, with autocomplete and Go.
- Browse's search/feed/filter header hides while scrolling down and returns when scrolling up or reaching the top. Keyboard focus also reveals it; other page headers retain their existing behavior.
- Active search uses a fixed-size Search details chip. It opens a scrollable modal listing all included/excluded query tags and the full submitted query; its separate close icon clears the search.
- **Comments** — published work discussions support posting, threaded replies, owner editing/deletion, and comment reports with visible validation errors. Bodies are trimmed and limited to 2,000 characters. Replies to replies stay in the same thread with an `@username` prefix. Deleting a parent hides its text but preserves its replies; deleted replies are omitted. Discussions have 20 top-level threads per page with Older/Newer navigation. Reply notifications respect comment preferences, avoid self/duplicate notifications, and point to the discussion. Blocks prevent commenting on blocked authors' work and replying to blocked commenters; guests can read but must sign in to write.

### Library collections

The Collections tab starts with All Saved, followed by named private-by-default shelves with visible work counts and small covers. Save adds a work to All Saved; Save to collection opens a checkbox picker (a bottom sheet on mobile) for multiple collections and optional inline creation. Owners can make a collection public to list it on their profile's Collections tab, or separately enable link sharing for a private collection.

Collection details and shared links use gallery work cards, not a slideshow of individual media: each illustration, video, album, or comic opens its own work page. Work links preserve the reader's return scroll position. Headers include sharing status, count, description, Share, and Edit. Owners can rename, describe, choose a cover from member works, and reorder an entire collection with drag/drop or Move up/Move down. Recently added uses membership dates, not work publication dates; Custom order uses the stored positions.

Removing a member or deleting a collection leaves its works in All Saved. Unsaving removes every membership; the Undo notification restores the save, membership dates, and ordering. Undo is session/account-bound, single-use, available for the latest unsave for five minutes (the notification remains visible for 15 seconds), and skips collections deleted in the meantime.

Shared viewers get the same cards, ordering and save controls; only owners can edit. Rating preferences, blocks, and the viewer's Library muted-tag filter apply to cards, counts, and cover selection. Muted tags are comma-separated, canonicalized through existing aliases, and retained in the viewer's session across private/shared collection pages. Invisible chosen covers fall back to a visible work. Revoking link sharing invalidates the URL; re-enabling creates a new link. Making a collection private removes it from the profile and disables its public profile URL without changing separate link-sharing settings.

Deploy with `php artisan migrate`; the collection migrations add descriptions, cover references, public visibility, membership dates and custom positions without deleting saves or collections.
- **Section search** stays local: Comics searches series titles, descriptions, creators, and published chapter titles; its autocomplete suggests only comics. Tags & wiki searches tag names/aliases and wiki guide titles/body text, with only tag/wiki autocomplete results. Both use Go without navigating on suggestion selection. Creator Studio has no header search. Other desktop header boxes continue to search Browse.
- Autocomplete suggests tags, artists, works, and comics for the current unfinished token.
  Arrow keys select a suggestion; Enter adds it to the draft, never submits the search.
  Esc closes suggestions. Selecting a tag adds it without replacing other completed terms.
- Combine text with multiple `#tags`, `tag:"multi word"` tags, `-tag:excluded`,
  `by:artist`, `type:image`, and `rating:sfw`. Included query tags must all match.
- **`/` keyboard shortcut** focuses the search box.
- Search is no longer a separate navigation destination. The mobile dock has Browse,
  Create, Inbox, and You; other destinations remain in the avatar menu.
- Legacy **`/search`** links redirect to Browse with their query and artist/type filters.

Search matches post titles and captions, artist credits and member handles/names,
tags, and associated comic titles. Explicit visibility and existing gallery filters
are preserved. `%` is escaped so it cannot act as a wildcard.

Controllers: `app/Http/Controllers/GalleryController.php` and
`app/Http/Controllers/SearchController.php` (legacy redirect and autocomplete).
UI: `resources/js/Components/SearchBox.jsx` and `resources/js/Pages/Gallery/Index.jsx`.
Indexes for the searched columns are in
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
