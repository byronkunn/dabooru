# Inkwell — NSFW-capable artist showcase + social site

**Date:** 2026-11-06
**Status:** Draft, awaiting approval
**Working name:** Inkwell (placeholder; rename is a config + app-namespace change)

---

## 1. Intent and the why

Artists who draw nudity, fetish work, or adult manga have shrinking, policed places to post: platforms that ban it, or sites that look like a gallery directory and feel dead. The person building this wants a **gallery-first home for the work**: the front page is a wall of art, everyone can drop work into it, every interaction around it (posts, likes, comments, following, DMs, notifications) behaves like Twitter, and adult ad revenue pays for the servers.

The line is clear: **the gallery is the site's face. Twitter is its behaviour.** A visitor lands on a wall of work, exactly what they came for. The moment they click a piece, comment, follow someone or open a DM, the site behaves like the social site they already know.

Success looks like: an artist signs up, uploads a piece, it appears in the gallery, strangers scroll and engage, an artist builds a following from it, and ad impressions from that traffic pay the bills. Artists get no share of the revenue.

The site allows both SFW and NSFW work. The same artist can post a clean portrait and an explicit piece, and viewers can hide one without hiding the other.

**Anti-goals** (a failure even if it technically works):

- A gallery directory that looks dead: a wall of art with no life around it.
- A gallery that floods with one artist's comic pages.
- An explicit ad slapped next to a SFW portfolio piece someone's family is meant to see.
- Surviving day one, then getting deplatformed by the ad network or the host.
- Minors being able to register, or upload explicit work.

---

## 2. Who it's for, and what they do

Two audiences, discovery first:

- **Scrolling viewers** (the growth engine). Land on a gallery wall of everyone's work, filter to what they want, sort by what they care about, open a piece, follow artists, comment, message. Not logged in they still see the gallery, because that is what gets shared.
- **Artists** (the content engine). Sign up, get a profile that is a real home, upload in 5 formats, build followers, publish comics chapter by chapter.

Every logged-in user can post. There is no separate "viewer" vs "artist" role: uploading makes you an artist, the same as Twitter.

---

## 3. Goals / non-goals

**Goals**

| # | Goal | How we know |
|---|------|-------------|
| G1 | Post and browse 5 formats: image, GIF, video, album, manga/comic | Every format has an upload path, a feed card, and a detail page |
| G2 | Home **gallery** filters by format and sorts by top rated / most viewed / newest | 5 formats × 3 sorts × 4 windows all return sensible, correct pages |
| G3 | Comics have a dedicated series page and a reader | `/comics/{slug}` and `/comics/{slug}/{chapter}` read well on mobile |
| G4 | Twitter-like social layer around the gallery | Profiles, follow, likes, comments, DMs, notifications, settings all exist and feel native |
| G5 | Adult ad revenue | Network banner in the sidebar rail, promoted tile every 12 gallery cells, below-post banner, view counted once per post per viewer per day |
| G6 | Safe on day one | 18+ gate, age confirmation at signup, per-post ratings, explicit blur, reporting + moderation queue, DMCA flow |
| G7 | Doesn't melt on video | HLS transcoding async, object storage + CDN, feeds are cacheable |

**Non-goals (this pass)**

- Artists earning from your revenue (creator payouts, revenue share, KYC, tax forms).
- Direct-sold sponsorships (the ad slots are designed so a direct sponsor can fill one, but no sales tooling).
- Payments/subscriptions/tips (a mainstream payment processor will not handle adult content; accepted for now).
- Mobile apps (REST API + Inertia frontend, mobile web first).
- Live streaming, audio formats, commissions marketplace, artist stores.
- Search beyond the basics (full-text post/user search is in scope as a simple version, no relevance ranking).

**Anti-goals**

- No algorithm that trains on viewing history for ranking. "Top rated / most viewed / newest" are honest static sorts; personalization is out of scope.
- No infinite retention of deleted users' media; deletion is a real delete with a grace period.

---

## 4. Constraints (from the conversation)

- Frontend: **React**, inside Laravel via **Inertia** (one app, server-rendered pages, no separate API host).
- Backend: **Laravel (PHP 8.4 available locally)**.
- Runs on a **VPS**, media on **S3-compatible storage** (Cloudflare R2 or Backblaze B2) with a CDN in front, pushed away from the app servers.
- Videos **self-hosted with HLS transcoding** (FFmpeg). No adult-hosted third-party video service.
- DMs and notifications **real-time** (Laravel Reverb / WebSockets).
- Ad monetization is **ad network only**, all revenue to the site owner.
- Adult ad networks are the assumption: ExoClick, JuicyAds, TrafficJunky style.

---

## 5. How the pieces fit (stack decisions)

### 5.1 Rendering: Inertia + React, server-rendered where it counts

Inertia gives us Laravel routes and controllers rendering React pages with server-provided props. That means the feed, profile, comic series page and reader are **server-rendered on first load** (good for link sharing and social previews, and it works with a simple crawler) and then become client-side interactive. React Router is not used; Inertia owns navigation.

**Blade + React islands** are used for the two things Inertia doesn't fit well: the **ad slots** (network script tags need to be injected client-side at the right time) and the **video player** (HLS.js lifecycle).

### 5.2 Auth: Laravel Sanctum for the session, plus API-ready structure

Inertia apps use Sanctum's SPA cookie session. Same-site cookie, CSRF via standard Laravel protections. Because the controllers are thin and return Inertia responses, a future native app can reuse the same logic behind JSON endpoints without a rewrite.

### 5.3 Real-time: Laravel Reverb

Reverb (Laravel's own WebSocket server, built on the Pusher protocol) handles: DM delivery, notification pushes, and the "new posts" badge. Two channels per user: `private-user.{id}` for notifications and `presence-dm.{conversationId}` for messages. Graceful fallback: if the socket drops, polls every 30s so DMs still arrive.

### 5.4 Media pipeline

All uploads go **browser → app → object storage**, never through the app on playback.

- **Images**: store original + generate derivatives (thumbnail 400px, feed 800px, full 1600px) as WebP/AVIF via a queue job. Store a blurred placeholder (tiny base64 LQIP) in the DB for instant paint.
- **GIFs**: treat as a "gif" media type. Ship the original and also a looped **MP4/WebM version** for the feed (much smaller, and the GIF→video trick everyone uses). Play-on-view in the feed, with a static poster stored, blur applied for explicit posts.
- **Videos**: upload to a temp object, a queue job runs FFmpeg to make HLS renditions (360p/720p/1080p as source allows) plus a poster frame, move final output to public storage. Playback via HLS.js in a custom player. Processing states: `pending → processing → ready / failed`.
- **Albums**: an album post has ordered parent items. Each image in the album is its own media asset with derivatives; album shows a cover, a detail page, and a lightbox in feed.
- **Comics**: each chapter page is an image asset (same derivative pipeline), plus a generated "page strip" for the vertical scroll mode.

### 5.5 Search, counters, caches

- MySQL 8 as the primary store with `FULLTEXT` on post captions, titles, comic titles, and usernames for basic search.
- Redis for feed page cache keys, rate limiting, and the sort score sets (see §7).
- **View counts are Buffered**: increment in Redis and flush to MySQL periodically, so a viral post doesn't hammer the DB.

### 5.6 Ops on a VPS (single box to start, split later)

- `nginx` + `php-fpm` + MySQL 8 + Redis + a Supervisor group for the queue workers + Reverb + FFmpeg.
- Media on R2/B2 behind a CDN; **the app box never serves video**.
- Object storage keeps the "host cancels you" risk mostly limited to the PHP app box, which can be moved.
- Backups: nightly MySQL dump to storage + object storage is already durable.

---

## 6. Data model (the parts that matter)

Full migration list lives in the plan, but these are the decisions a builder needs:

**users**: username, display name, email, password, avatar/banner, bio, links, 18+ confirmed at, email verified at, status, default rating preference (muted), default feed format preference.

**follows**: follower_id, following_id, accepted (instant follow both ways; no follow requests in v1 — noted as a builder call).

**posts**: uuid, user_id, type (`image|gif|video|album|comic_chapter`), title, body, rating (`sfw|suggestive|explicit`), views, likes_count, comments_count, created_at. A comic chapter post references `comic_chapter_id`. Post rows are **soft-deletable** and carry a `deleted_at` grace period before media is purged.

**post_media**: post_id, media_id, position. **media**: storage path, type, original dimensions, duration (video), derivatives JSON, status, LQIP string.

**albums / album_items**: post_id, ordered media.

**comics**: user_id, slug, title, cover_media_id, description, rating, status (`ongoing|completed|hiatus`), is_hidden. **comic_chapters**: comic_id, number, title, published_at. **comic_pages**: chapter_id, media_id, position.

**likes** (post_id, user_id) — this is also the rating signal for "top rated". **bookmarks** for save-to-library. **follows** above. **comments**: post_id, user_id, body, parent_id (one level of replies), likes.

**views**: post_id, viewer key (user id or anonymous hash), date — unique per viewer per day, aggregated into `post.views`.

**notifications**: user_id, actor_id, type (like/comment/follow/chapter), subject (polymorphic), read_at.

**conversations / conversation_participants / messages** for DMs (adult content allowed in DMs, so DMs also carry a rating flag the recipient's settings can blur).

**reports**: reporter, target (polymorphic post/comment/user/message), reason enum, status, moderator notes. **moderation_actions** audit log.

**blocks**: blocker, blocked.

---

## 7. The home page: a gallery, not a feed

The home page is a **masonry gallery wall**, the same shape people know from art sites: columns of work at their natural aspect ratios, not uniform Twitter cards.

**Every cell is one work.** Image, GIF, video, album cover, or comic-chapter cover. Each cell carries: the artwork thumbnail, the rating badge (SFW / Suggestive / Explicit), a format badge, the artist's avatar and handle, the title, and like + view counts. Videos and GIFs show a play affordance; comics read as a series cover.

**Filter bar** (top of the page, sticky):

- **Format tabs**, single-select: All · Images · GIFs · Videos · Albums · Comics.
- **Sort**, as a segmented pill row: **Top rated · Most viewed · Newest**.
- **Window** dropdown: Today · This week · This month · All time. *My call, stated plainly: "top rated all time" on a young site is just "the first few posts forever", so a window is required for the sort to be useful.*
- **Rating toggle**: Show explicit (default respects the viewer's saved setting; blurred for logged-out visitors).

**How the sorts work.** Newest is a plain indexed query on `created_at`. Top-rated and most-viewed read from **Redis sorted sets** recomputed on a schedule per format per window (score from views, likes, and a time decay). Feed pages for anonymous visitors are cacheable with a 60s TTL.

**Masonry details.** Rows are not aligned. The layout uses CSS multi-column masonry on the server-rendered markup, with tiles sized by the media's aspect ratio (from stored dimensions), so there is no layout shift on load. Images lazy-load below the fold; videos and GIFs never autoplay in the grid, only on hover after a short delay (or tap on mobile), because a wall of autoplaying adult video is both hostile and expensive.

**What the gallery is not:** it is not the place you go to see only the people you follow. That is the Following page.

### 7.1 The Following page (Twitter-shaped)

`/following` is a **feed**, not a wall. It shows the people you follow's work as a chronological list of posts in the Twitter card style (cover, caption, engagement bar). This is where Twitter's behaviour lives, and it is where a viewer who follows 40 artists can read what is new. Same composer, same like/comment/share actions, same real-time refresh.

### 7.2 Ads in a gallery

Ad tiles are far more visible in a wall than in a feed, so placement is deliberate:

- **Promoted tile** every **12th** cell, in the grid, clearly labelled, with the same shape as a work. It collapses (the grid closes the gap) if the network returns nothing.
- **Sidebar banner** in the right rail, sticky, standard 300×250 / 300×600.
- **Below-post banner** under the engagement bar on a post's detail page, standard 728×90 / 320×50.
- **No ad appears inside the comic reader surface**, ever. The reading experience is the thing that makes people come back.

## 8. Post formats on the detail page

- **Image** — full-size, zoom, meta (dimensions, artist note), engagement bar, comments.
- **GIF** — looped video with play/pause, "download original GIF" link.
- **Video** — our HLS player, poster, quality menu, fullscreen, save to library.
- **Album** — cover grid in feed; detail page is a swipeable lightbox with count and captions.
- **Comic chapter post** — cover + chapter number + "Read" → series reader.

Engagement bar everywhere: like, comment, save/bookmark, share (copy link, share to DM), report, more menu. **Private vs public and rating are per-post**, artist-controlled at upload.

---

## 9. Comic series page and reader

**`/comics/{slug}`** — a dedicated page, separate from the profile and the feed:

- Banner cover, series title, artist (links to profile), rating badge, status, tags
- Chapter list (number, title, date, page count, "read/finished" tick once read)
- Series-level like/bookmark/follow-series, comment thread at the series level
- Start reading / Continue reading button

**`/comics/{slug}/{chapterNumber}`** — the reader:

- Two modes with a **toggle, remembered per user**: **Page** (prev/next, click zones, ←/→ keys, progress saved, tap to pause chrome) and **Scroll** (vertical webtoon strip of chapter pages lazy-loaded).
- Mode preference stored on the user; anonymous readers get a cookie.
- Last-read position saved per chapter; "Continue reading" picks the first unfinished chapter.
- At the end of a chapter: next-chapter CTA, chapter comments, an ad slot (kept off the reading surface itself to protect reading).
- Reader keyboard shortcuts: ←/→, space, F for fullscreen/immersive.

**Access**: readers need an account to view explicit chapters? *My call: no. The 18+ gate at site entry covers it, and requiring login kills shared comic links. Recommendation: 18+ gate for explicit comics, no login required.* The recommendation is called out as the builder's call in the plan.

---

## 10. Twitter-like social layer

- **Layout**: left nav rail (Gallery, Following, Messages, Notifications, Comics, Profile), centre content, right rail (search, who to follow, trending tags, sidebar ad). Mobile: bottom tab bar.
- **Following feed**: `/following`, chronological posts in the Twitter card style (see §7.1).
- **Profiles**: banner, avatar, bio, follow counts, tabs (Posts, Media, Albums, Comics, Likes, About), follow/unfollow, message, report, block. Edit profile with avatar crop and banner upload.
- **Notifications**: real-time, tabbed (All / Mentions / Follows / Likes / Comments), unread badge, mark-read, per-type settings.
- **DMs**: conversation list, thread view, real-time delivery, read receipts (setting), image sharing, block/report in-thread, message requests from non-followers (setting).
- **Settings**: account (email change, password, 2FA-ready structure), profile, feed preferences (default sort/filter, hide explicit), media (autoplay GIFs, autoplay videos, reduce motion), notifications per type, privacy (DM requests, read receipts), blocked/muted, sessions.
- **Posting**: composer with drag-and-drop, format choice, title/body, rating selector (SFW/Suggestive/Explicit, defaults from profile), album ordering, comic chapter publishing flow, drafts saved locally.

---

## 11. Ads

Slots rendered by a small Blade component + React island so network tags load exactly once:

| Slot | Where | Notes |
|------|-------|-------|
| `sidebar` | Right rail, sticky | 300x250 / 300x600 |
| `in_feed` | After every 6th post, native card | Clearly marked "Promoted", collapses when empty |
| `below_post` | Under the post's engagement bar on detail pages | 728x90 desktop / 320x50 mobile |

- One config file (`config/ads.php`) maps slot → network tag template, so switching networks is a config change, not a code change.
- Ad requests are made **client-side, only after the ad slot is in the viewport**, so a slow ad network doesn't block the page.
- Ad network scripts are loaded **on every page including age-gate-accepted first page**; they are not active on the age gate modal itself.
- Ad-blocker detection: a soft notice ("the site runs on ads") shown once, no paywall.
- **Impression integrity**: view counting for sort is server-side and counted on post view, separate from ad impression counting. No baiting viewers into ads to inflate revenue in an unsafe way; no autoplay audio slots.

---

## 12. Safety, moderation, legal

- **18+ gate**: interstitial on first visit, persists via cookie + server session flag, recorded per account and per device. Required before any explicit content is rendered.
- **Signup**: email verification mandatory; 18+ confirmation required; ToS/privacy accepted.
- **Ratings**: three levels per post. Site-wide blur setting for explicit posts (default blur on for logged-out, respected per user).
- **Reporting**: report any post, comment, user or message with reason options; lands in a moderation queue.
- **Moderation tools**: a Filament admin panel (Filament is the usual Laravel admin stack) for the report queue, content takedown, user bans, verification of creator age documents later.
- **Audit log** on every moderation action.
- **DMCA**: `/dmca` static page + a form that creates a ticket; takedown removes the post and logs the action.
- **Content policy page** and a `robots.txt` decision: no indexing of explicit post pages by default, exposed as a setting.
- **Third-party notice**: ad networks have their own content rules (adult networks usually require no minors, no non-consensual content, no piracy). The moderation queue's reason list mirrors those so you are not feeding a network content that will get your account closed.

---

## 13. Build approaches considered (and why we picked this one)

**Home-page approach considered:**

- *(chosen)* **Gallery wall home + separate Following feed.** The front page does the discovery job a gallery does best; Twitter behaviour lives in every interaction and in `/following`.
- Rejected: **Twitter feed home page.** It matches the original brief's wording but buries the art: every work becomes the same-width card with a cropped thumbnail, and a wall of work is the thing people came for on an adult art site.
- Rejected: **Gallery home with no feed anywhere.** Discovery only; followers have no way to see what's new from people they follow, which kills retention.

**Stack approaches considered:**

**Approach A: Inertia + React single app, server-rendered pages** — *chosen*. Matches the "checkout culture" of a social site: real URLs, server-rendered first paint, social previews, and a single codebase. Laravel does auth, queue, storage, WebSockets without extra infrastructure. Downside: React lives inside Laravel's routing model, so the frontend isn't separately deployable.

**Approach B: Vite SPA + Laravel API** — cleanest separation and the best path to a native app later. Rejected: a social content site needs server-rendered pages for sharing and discovery; a pure SPA makes link previews and cold loads worse and splits the build/deploy into two.

**Approach C: Next.js frontend + Laravel API** — best SEO and social previews of the three, but introduces a second Node runtime on the VPS and an API contract to maintain, for a PHP-centric team. Rejected as overkill; if the site ever needs serious SEO, Next can be added later as a public-only front-end.

**Comic model considered:**

- *(chosen)* Chapter upload = one feed post that links to the series page; series page + chapter reader with both reading modes.
- Rejected: one post per page (feed flood), one post per series only (followers never see new chapters in feed).

---

## 14. What you decided vs what I proposed

**You decided:** React inside Laravel via Inertia; VPS + S3-compatible storage + CDN; self-hosted HLS transcoding; Reverb for real-time DMs/notifications; open signup with email verification + 18+; per-post SFW/Suggestive/Explicit; GIFs play-on-view in feed; comic series page with chapters and both reader modes; chapter uploads post to feed; sorting on posts (series-level sorting rejected); ad network only via sidebar + in-feed + below-post; no artist payout; full feature set in one build. **Home page is a gallery wall, not a Twitter feed; Twitter behaviour lives in the interactions and in the Following feed.**

**My calls (check these while reading):**

1. **Gallery window** (Today / Week / Month / All time) is required for "Top rated" to mean anything. Added as a control.
2. **Anonymous viewers can read the gallery** (logged out) after the 18+ gate; engagement requires login. Keeps shared content working.
3. **Explicit comic chapters need 18+ gate, not login.**
4. **Single-admin moderation**: Filament panel, no separate moderator role on day one. Roles exist in the schema so more can be added.
5. **Follow is instant**, no follow requests (Twitter-style). Protected profiles are out of scope.
6. **Views are counted server-side per viewer (login or anonymous hash) per day**, and that is the number for "most viewed". Ad impressions are counted separately by the network.
7. **Username/URL structure**: `/u/{username}` profiles, `/p/{uuid}` posts, `/comics/{slug}`, `/comics/{slug}/{n}` chapters. UUIDs so post URLs aren't enumerable.
8. **Redis sorted sets** for the two ranked sorts, recomputed on a schedule rather than computed per request. Correctness over instant freshness (a post enters rankings within a minute).
9. **No payments integration** in v1.
10. **Promoted tile frequency is every 12th gallery cell**; tunable in config. Hover/tap-to-play for GIFs and videos in the grid, no autoplay in a wall.

---

## 14b. Answers to the builder check

A builder reviewed the design and raised questions. These are the decisions, folded back in.

**Answered from the conversation or the decisions above**

- **18+ is self-confirmation only in v1** (checkbox at signup, persisted gate on first visit). No card or ID check. Creator ID documents are a later, separate integration, and the schema has a place for their state. Id check vendors don't take adult content any more easily than payment processors do.
- **Anyone can post as soon as they're email-verified and 18+ confirmed.** No waiting period. Abuse is handled with rate limits and posting trust tiers rather than blocking honest new accounts. Builder decides the exact caps, with this default: brand-new accounts can post 3/day and DM 20/day, rising with account age. Flag it if that's wrong.
- **Anonymous views**: dedupe uses a salted hash of IP + user agent, **rotated daily**, never stored raw and never joined back to a person. Retention of the dedupe window is **24 hours**; the long-term data is only the aggregate counter. No IP logs older than 7 days outside the moderation audit. This is my call, placed in the doc as the answer.
- **Reverb connection goes on a subdomain** (`ws.`), so the main CDN/WAF rules don't touch it and the WebSocket upgrade doesn't fight the HTML cache. One Reverb process is fine until concurrent connections pass ~1k, which is a scale problem for later, not a v1 problem.
- **CSP stance**: the app has a real Content-Security-Policy that allows the ad network domains, the media CDN, and the email provider's asset host, and **nothing else**. Ad network domains are config values alongside the ad tags, so a CSP and an ad-network change happen together. No third-party analytics in v1 (analytics came up as a question, not a requirement).

**My calls (settled so planning can proceed)**

- **Image originals are downscaled at ingest** to a maximum long edge of **4000px** and stored as the original. With adult art this saves most of the storage cost and loses nothing visible. **Video source** is kept untranscoded only until HLS renditions are ready, then deleted after a 7-day grace period.
- **Transcoding shares the single VPS** but is capped at **one concurrent FFmpeg job** (Supervisor), with image derivatives on a smaller worker. If the box starts starving during uploads, the GPU-less swap is to move the media worker to a second box. Plan starts single-box.
- **Upload limits**: images up to 40MB, GIFs up to 40MB, video up to 500MB / 20 minutes, comic page up to 15MB. Failed transcodes retry 3 times then mark `failed` and notify the artist to re-upload.
- **Moderation is one person** (the site owner). The queue is sorted by report count and severity, has a keyboard-first triage view, and every action is one click. There is no published response SLA on day one. The reason list mirrors the ad network rules.
- **The DMCA section 512 registered-agent filing is a launch item, not a build item.** The build ships the `/dmca` form and takedown action; the design calls out plainly that safe-harbour protection needs the agent registration filed and a designated agent listed on the site before launch.
- **Email provider is a build choice with an adult-friendly constraint**, not a question. The plan will pick providers known to accept adult content (or a self-hosted mail relay with a dedicated IP as fallback) and verify deliverability before launch.
- **Host choice is a build decision inside a stated constraint**: the VPS host must permit adult content. The plan evaluates hosts on that AUP criterion. The site owner's legal entity and jurisdiction are handled outside the build.

**Left for you (not on the build's critical path, but they change what "launched" means)**

1. **Monthly hosting + storage budget.** The plan needs a ceiling to size the box. A rough figure is enough. Storage cost is the main line item: a few hundred images at 4000px and a handful of videos fit comfortably in a cheap tier, and it gets steep once you're serving real video traffic.
2. **Whether the site owner already has a business entity/jurisdiction chosen.** That's not code, but it decides whether a card processor could ever be added and how the DMCA agent registration is filed.

---

## 14c. Screen inventory and responsive behaviour (mockup v1)

The clickable mockup lives in `docs/superpowers/specs/mockup/` (open `index.html`). Fifteen screens, each verified at **desktop 1440**, **tablet 834** and **mobile 390** with zero horizontal overflow and no console errors (screenshots in `mockup/shots/`, produced by `mockup/shoot.js`).

**Routes behind the screens**

| Screen | Route | Mockup file |
|--------|-------|-------------|
| 18+ gate | `/age-gate` | `screen-gate.html` |
| Log in | `/login` | `screen-auth.html` |
| Sign up | `/register` | `screen-signup.html` |
| Gallery | `/` | `screens-gallery.html` |
| Following feed | `/following` | `screen-following.html` |
| Work detail | `/p/{id}` | `screen-post.html` |
| Profile | `/u/{handle}` | `screen-profile.html` |
| Edit profile | `/settings/profile` | `screen-profile-edit.html` |
| Comic series page | `/comics/{slug}` | `screen-comic-series.html` |
| Comic reader | `/comics/{slug}/{n}` | `screen-reader.html` |
| Messages | `/messages` | `screen-messages.html` |
| Notifications | `/notifications` | `screen-notifications.html` |
| Upload | `/upload` | `screen-upload.html` |
| Settings | `/settings` | `screen-settings.html` |
| Screen index | — | `index.html` |

**Breakpoints (three, not five)**

- **≥1201px — desktop.** Three columns: icon+label rail (240px), content, right rail (300px). Masonry wall at 3 columns.
- **901–1200px — tablet.** Right rail is dropped (its sidebar ad slot moves to the in-feed promoted tile on tablet). Rail collapses to a 88px icon rail with tooltips and a round upload button.
- **≤900px — mobile.** Single column, bottom tab bar (Gallery, Following, Messages, Notifications, Profile), upload becomes a round `＋` in the top bar. Masonry drops to 2 columns, 10px gutters, titles hidden in cells (the artwork carries the cell), filters become horizontal scrollers with snap points. Search collapses to an icon.
- **≤420px.** Tighter gutters, smaller filter pills, the toggle labels drop and keep their switches.

**Behavioural rules that are part of responsive, not optional**

- **Real hover only.** The card lift and image zoom are behind `@media (hover:hover) and (pointer:fine)`. Touch devices never get the transform.
- **Touch targets.** Every interactive element is at least 40×40 (nav 52px, filter pills 40px, reader page buttons 44×40).
- **`prefers-reduced-motion`** disables transitions, the send/loading spinner and smooth scroll.
- **Safe areas.** The bottom nav and floating selection bar pad with `env(safe-area-inset-bottom)` for notched phones.
- **No layout shift.** Gallery tiles reserve space from stored media aspect ratios; the reader reserves the page box before the image loads.
- **The reader is never an overlay on desktop that traps scroll on mobile.** It is a route, full-screen with its own top bar and pager, so back/swipe-back behave like a normal page.

**Per-screen responsive notes**

- **Gallery:** desktop 3 columns (masonry), tablet 3, mobile 2. Filter row scrolls horizontally on mobile, never wraps. The selection bar stays above the bottom nav on mobile.
- **Following feed:** one column at every width, 680px capped on desktop. Media is full-width with 12px radius on mobile.
- **Work detail:** media, then a full-width action row (series page / read chapter), counters, comment composer, comments. The reply bar is sticky above the bottom nav on mobile.
- **Profile:** 3-column square grid on desktop, 2 on mobile. Banner 200px desktop / 140px mobile. Action buttons move to their own row on mobile.
- **Comic series page:** cover left, info right on desktop; a single stacked column on mobile (cover 120px wide), chapter rows full-width with the read ticks.
- **Reader:** page mode is full-bleed on mobile with a pager bar; scroll mode is a lazy-loaded vertical strip that hides the pager. Swipe left/right changes page on touch; ←/→ and space work on keyboard.
- **Messages:** two-pane on desktop; on mobile it becomes a list that swaps to the thread on tap with a back chevron. Send works and the mockup simulates a reply.
- **Notifications:** thumbnail + time inline on desktop; the time column collapses on mobile so the text keeps the width.
- **Settings:** sticky section nav on desktop; becomes a 2-column chip list on mobile.
- **Upload:** format cards shrink and stay on one row on mobile; dropzone, title, caption, rating, and toggles stack.

**Accessibility baseline baked into the mockup:** semantic landmarks (`main`, `nav`, `article`), `role="switch"` + `aria-checked` on every toggle, `role="tab"` + `aria-selected` on tabs, visible focus on the back button and toggles, buttons for actions and anchors for navigation, alt text on decorative images left empty (they are decorative) and real labels on interactive images.

---

## 15. What's left to the builder

- Exact Filament version and admin layout; whether to use a media library package (Spatie Media Library) or hand-rolled `media` table (recommendation: hand-rolled, because derivatives per format are custom).
- Player component choice (custom HLS.js wrapper vs `plyr`); recommend HLS.js + small React wrapper.
- Ad network choice per slot and the actual tag markup, which is account-specific.
- Docker vs plain Supervisor for deploy; recommend Supervisor on the single VPS to start, Docker when splitting boxes.
- Whether to add MFA (TOTP) in v1; recommend schema-ready, not built.

---

## 16. Risks and how they're handled

| Risk | Handling |
|------|----------|
| Ad network closes the account | Slot config swap in `config/ads.php`; content policy mirrored to moderation reasons; no clickbait/non-compliant placements |
| Payments impossible on adult content | Ad-only model; no payment processor integration |
| Hosting/video bill explodes | Media on R2/B2 + CDN, HLS on-demand, feed never serves video directly from the app |
| Minors or non-consensual content | Email verification, 18+ confirmation, reporting, moderation queue, DMCA flow |
| Feed floods with cheap likes to game "top rated" | Viewers-only likes (no anonymous likes), rate limits, time decay, one like per account per post |
| Comic pages blow up storage | Derivative pipeline compresses heavily; originals on cheaper storage class |
| Sexually explicit GIFs autoplaying everywhere | The wall never autoplays. Explicit posts are blurred for logged-out visitors, and play only on hover/tap |

---

## 17. Rough size

This is a **project**, not a small change. Two apps' worth of surface (frontend screens + backend domain) with media processing, real-time, and an admin panel. The plan in `docs/superpowers/plans/` will be written after this design is approved.
