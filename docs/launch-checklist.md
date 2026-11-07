# Inkwell — launch checklist

You are about to run an NSFW-capable artist site for real. The app is built and demo-tested; everything below is the part that is not code. Work top to bottom. Nothing here is legal advice — see the note at the end of section 1.

**Where the app actually stands.** Verified against this checkout, not the README:

| Built and working | Not built |
| --- | --- |
| `/age-gate` with a one-year `inkwell_age_ok` cookie (`app/Support/AgeGate.php`, `EnsureAgeConfirmed` middleware) | Moderation admin panel (no admin UI; `is_admin` exists but nothing reads it as a route guard) |
| `age_confirmed_at` on users; registration requires a date of birth that passes an 18+ validation rule | DMCA form or `DMCA` page anywhere in `routes/`, `app/`, or the React pages |
| Email verification routes (`routes/auth.php`, Breeze) | Content policy page, terms of service page, privacy policy page |
| Per-post SFW / Suggestive / Explicit (`App\Support\Rating`), server-side filtering | Websockets / real-time (no Reverb, no broadcasting) |
| Show-explicit toggle in settings and on the gallery | Video transcoding (videos are stored as-is, marked `processing`, never processed) |
| `POST /report` → `reports` table (reasons `minor`, `non_consensual`, `spam`, `copyright`, `other`; status `open`/`actioned`/`dismissed`) | Ad network tags (one placeholder sidebar slot only) |
| Reading progress table and endpoints | Analytics of any kind |
| Settings for DM requests and read receipts | Enforcement of DM requests / read receipts (stored and rendered, no code reads them) |
| Bans enforced (`EnsureAgeConfirmed` logs out a banned account on its next request) | No admin UI to set `banned_at` — a ban still needs a direct DB update |
| | Blocks in the UI (`blocks` table exists, no UI) |

Three gaps that matter more than the rest, because they are the ones that cost you money or get you taken down:

1. **`reports` rows go nowhere.** `POST /report` inserts a row and that is it. There is no page, notification-to-admin, or email that surfaces a report. An "open" report on a CSAM upload will sit there silently. Until you build the queue (section 4), you need a person checking the table or the site.
2. **`banned_at` is enforced but there is no way to set it.** Since this checklist was written, `EnsureAgeConfirmed` was updated to log out a banned account on its next request. What is still missing is any admin surface: banning someone means a direct database update until the admin panel exists.
3. **Nothing is logged for takedowns.** There is no takedown log, so your DMCA safe-harbor story is "we have no record of what we received or when we acted." That is a bad position to argue from.

Other things to know before you write the policy:

- The age gate is a **checkbox plus a cookie**. It is not ID verification and it is not a legal shield (section 1).
- **The registration page already claims terms, privacy, and content policies exist** (`resources/js/Pages/Auth/Register.jsx` has the user accept "the terms, privacy policy and content policy"). Those pages do not exist. Either write them before launch or change the copy — asking users to agree to a nonexistent document is itself a problem.
- **Uploads are re-encoded to strip metadata.** `App/Support/ImageProcessor::storeClean()` re-encodes every uploaded image and comic page to JPEG before storing it, so EXIF/GPS/XMP never reaches the disk (covered by a test). The trade-off: transparency is lost (PNG/WebP become JPEG) and there is still no derivative pipeline yet.
- Guests can report (the reporter is nullable). That is good for safety and is also a spam vector; it is throttled to 10/min.

---

## 1. Before you open it to the public — legal and safety

> **This section is not legal advice.** I am not a lawyer and this is not a substitute for one. Adult platforms carry real legal exposure that varies by where you, your users, and your host are located. Budget for a lawyer to review your terms of service, content policy, and DMCA process **before** you open registration.

### Age verification

- [ ] Accept, in writing, what the gate is: a self-declaration. The user clicks a button, gets a one-year cookie, and for logged-in users it stamps `age_confirmed_at`. Registration asks for a date of birth and validates that it is 18+.
- [ ] Understand what that means legally: **it is not identity verification, and it does not establish that a user is an adult.** It is what most aggregators and galleries do, and it is a reasonable industry norm, **but it is not a legal shield**. It will not stop a minor who lies, and it will not by itself satisfy jurisdictions that require real age assurance.
- [ ] Decide your jurisdiction and check the actual requirement. The EU (and member states implementing it), several US states (e.g. Texas, Utah, Louisiana, and others with age-verification or "age assurance" statutes), and the UK Online Safety Act on "primary priority content" are all tightening. Requirements differ sharply — some want credit-card checks, some want government ID, some want a third-party age-assurance vendor. Get a local lawyer to tell you what applies to you; do not assume the cookie is enough.
- [ ] Note what the app does **not** do about age: no re-check on new devices beyond the cookie expiry, no device-level verification, and a cleared cookie only means the visitor is re-asked, not re-verified. A bypass flag exists for tests (`INKWELL_AGE_GATE_TEST_BYPASS`, section 7) — make sure it is off in production.
- [ ] Check your own jurisdiction's rules on displaying adult content at all (some don't care, some want labelling, some ban certain categories outright).

### 18+ content — how to handle it

- [ ] Rate everything. The app's per-post SFW / Suggestive / Explicit rating is filtered server-side, which is good; make it mandatory in policy and make sure artists understand "explicit" includes anything they would not put on a corporate portfolio.
- [ ] Decide whether you allow suggestive or explicit content **at all** in your content policy, or only certain kinds. The policy is the contract you enforce against; leave nothing to "we'll know it when we see it."
- [ ] Do not let the gate create a false sense of security: the gate keeps casual visitors out of the gallery, but any post URL that leaks out of a browser session without the cookie will just re-prompt. Nothing is actually locked down at the storage layer.

### A written content policy (you must have one)

- [ ] **Write it before launch**, publish it at a stable URL, link it from registration, the upload page, and the footer. Do not launch with the current state, where the registration checkbox references a policy that is a 404.
- [ ] It must be explicit about, at minimum:
  - [ ] **No minors, and no depictions of minors.** Include drawn, animated, AI-generated, and "aged up" material — the policy should say depiction of a minor or a minor-presenting character is prohibited **regardless of style or medium**. In most jurisdictions this is the line that lands you in criminal territory rather than civil; treat it as absolute.
  - [ ] **No non-consensual content.** No revenge porn, deepfakes of real people, leaks, or anything depicting a real person without consent. Add a "no real people without consent" clause; this also covers AI face-swaps.
  - [ ] **No piracy or stolen work.** No reposting artists' work without permission, no scraped galleries, no AI-model laundering. Include how copyright complaints are handled (DMCA section).
  - [ ] **No illegal content generally.** Bestiality, incest, extreme content — decide and state.
  - [ ] **Rules for uploads and ratings:** how to rate, how to tag, what "explicit" covers, and that mis-rating is a violation.
  - [ ] **Enforcement and consequences:** what happens on violation (removal, ban, reporting to law enforcement) and the appeals route.
  - [ ] **The mechanics of reporting:** where the report button is, what reasons it accepts, and that reports are reviewed.
- [ ] Enforce it consistently from day one. An unenforced policy is worse than no policy in a dispute — it documents that you knew and did nothing.

### DMCA — the copyright safe harbor

- [ ] **Register a DMCA agent** with the US Copyright Office under **17 U.S.C. § 512**. This is a formal registration (online form, fee, periodic renewal) — you cannot get the safe harbor just by putting an address in your footer. Do this before launch.
- [ ] Make the registered contact findable in your site's DMCA page, and keep the registration current. An out-of-date agent listing weakens the safe harbor.
- [ ] **Build a DMCA takedown page** — it does not exist today. At minimum it needs:
  - [ ] Who to contact and how (the registered agent's name, address, email).
  - [ ] **The elements of a valid notice** (as § 512(c)(3) requires): identification of the copyrighted work, identification of the infringing material with enough detail to locate it (post URLs), contact info, a good-faith-belief statement, a statement of accuracy under penalty of perjury, and the complainant's signature.
  - [ ] **How to counter-notify**, and what you will do after one (the 512(g) put-back sequence).
  - [ ] A statement that repeat infringers are banned.
- [ ] **Build a takedown process behind the page.** Takedowns are not emails you deal with from memory:
  - [ ] Get a dedicated mailbox and check it daily. Do not use a personal address.
  - [ ] Actually process it: notice received → assess → remove → notify the uploader (with basic DMCA-compliant notice) → log it.
  - [ ] **Record every action** with timestamps: what was reported, what you removed, who and when. Your safe-harbor defence depends on being able to show you acted. There is no takedown log in the app today; a shared spreadsheet is a fine start, an admin table is better (section 4).
  - [ ] Do the takedown itself properly: today a removed post is a soft delete. Make sure the media stops being served from your storage/CDN too, not just from the post page — otherwise you removed the page but kept distributing the file.
- [ ] Repeat-infringer policy in writing: strikes, and eventual ban. You need the banned-state to actually work (it doesn't yet — see section 4).

### Terms of service and privacy policy

- [ ] Write and publish **Terms of Service**: acceptable use, content policy reference, DMCA process, account termination, limitation of liability, governing law, 18+ requirement, and what you can do with uploaded work (the licence you take to host and serve it — explicitly required for CDN/ads).
- [ ] Write and publish a **Privacy Policy**: what you collect (accounts, IPs in sessions, upload metadata), why, who you share it with (storage provider, CDN, mail provider, ad network — these are the ones that matter on an adult site), cookie name and duration (`inkwell_age_ok`, one year), and rights/deletion. If you have EU/UK users, deal with GDPR/UK GDPR properly: lawful basis, data subject rights, and a data-processing agreement with each processor.
- [ ] Note the shared cookie and analytics gap: **there is no analytics in the app today.** That's actually convenient for privacy, but if you add any tracking (including the ad network's), the privacy policy and a cookie/consent banner have to cover it before the tag goes live.
- [ ] Keep the DM requests and read receipts settings honest: they are stored and displayed but nothing enforces them. Either finish enforcing them or say in the settings copy that they're not active yet. Promising a privacy control you don't implement is a real problem.
- [ ] Have a lawyer read the ToS, content policy, and DMCA process before launch. All three.

### Recording reports and keeping records

- [ ] Make sure someone actually reads `reports`. Today there is no admin view; query the table, or build the queue in section 4 before launch.
- [ ] Keep the reports and your admin actions backed up separately (section 2).
- [ ] Know your law-enforcement obligations. In the US, 18 U.S.C. § 2258A obliges electronic service providers (very likely including you) to report apparent child sexual abuse material to NCMEC's CyberTipline, and you should **not** simply delete and forget. In the EU, the CSAM Regulation and TerReg are in flux; in the UK, the Online Safety Act. Research this for your jurisdiction, write down what you will do when you get a `minor` report, and get a lawyer to confirm the answer for you. It is not "just delete the post."
- [ ] Get a legal-entity and E&O review done before you take money from anyone. Insurance is very hard to get after an incident.

---

## 2. Hosting and infrastructure that will actually accept adult content

The single biggest mistake at this stage is buying a normal, cheap VPS without reading the acceptable use policy. You will be suspended without warning, at the worst time, usually right after a search engine starts sending traffic.

### Acceptable-use policies

- [ ] Check the AUP of every layer **before** you pay: the VPS host, the object storage, the CDN, the mail provider, the registrar, and (if you're on one) the payment provider.
- [ ] Read the actual "prohibited content" sections, not the marketing page. Plenty of hosts happily take your money and then terminate the account on an adult-content clause. Many US shared-hosting and "cheap VPS" providers prohibit it outright, and some prohibit "adult" while allowing "artistic nudity" — the grey zone is not a place to build on.
- [ ] Get it in writing when you're unsure, and save the email. Then verify that the specific product you're buying (not just the parent brand) allows it.
- [ ] Pick hosts that others in the space actually run on. Known adult-tolerant options exist, but confirm per-product; do not assume a brand's tolerance transfers to a new product line.
- [ ] Buy a separate billing account/credit card for this. Acquirers and banks sometimes cut you off along with a payment processor, and you don't want that cascade killing unrelated services.

### Keep the app server separate from the media

- [ ] **Object storage + CDN for all media.** Store uploads in S3 or an S3-compatible bucket (Backblaze B2, Wasabi, Cloudflare R2, etc.) behind a CDN. The app server serves HTML and nothing else heavy.
- [ ] This is the config in section 7: `FILESYSTEM_DISK=s3` + `AWS_*`. The `s3` disk already reads those variables in `config/filesystems.php`.
- [ ] **Never serve video from the app server.** A 500 MB `.mp4` behind PHP-FPM will pin your workers and your bandwidth, and one popular clip will take the whole site down. Serve video from storage/CDN, preferably post-transcode (which you don't have yet — see below).
- [ ] Don't assume the switcheroo is free. Two things in the current code:
  - `UploadController::storeMedia()` hardcodes `'disk' => 'public'` per media row rather than using the configured default. Flipping `FILESYSTEM_DISK=s3` changes the `s3` disk config but new uploads may still be written to the local `public` disk. Check and fix before you launch, or you will silently keep writing to the app box.
  - Existing media rows store their `disk` column, so old local uploads keep resolving to local, which is fine — but the local `public/storage` symlink and the files behind it must be migrated to the bucket if you're replacing the box.
- [ ] Run the CDN in front of the media bucket with signed URLs or at least hotlink protection. Adult sites get scraped and hotlinked hard; unsiged public object URLs are free bandwidth for whoever scrapes you. Note: the `media` table has no private-key support today, so this is work you're adding, not config.
- [ ] Choose where the buckets live deliberately — some jurisdictions/regions are more friendly (or less) to adult content, and the CDN's AUP matters independently of the bucket's.

### Video

- [ ] There is **no transcoding** in the app: videos are stored as uploaded and marked `processing` and never progress. Until you build or buy transcoding, every video is served at its original size and format. Either:
  - [ ] disable video uploads for launch (cheapest, most honest), or
  - [ ] put a transcode step (e.g. ffmpeg worker + HLS output) in front of it before you accept video from the public.
- [ ] Ask your CDN whether it serves HLS. Some cheap plans quietly don't. Adult video also gets flagged by mainstream CDNs regardless of the host's tolerance.
- [x] Images and comic pages are re-encoded to strip EXIF on upload (done).
- [ ] Video is **not** stripped: assume uploaded video metadata is public until transcoding lands, or disable video uploads for launch.

### Backups

- [ ] Automated **database** backups, daily at minimum, off the app box, with a tested restore. A backup you have never restored is not a backup.
- [ ] Automate the media bucket too — either provider-side versioning on the bucket, or a copy to a second provider.
- [ ] Keep the backup destination a **different provider** from the app host. A host suspension that takes down the box should not take out your copies.
- [ ] Back up the `reports`/moderation records and, once you have it, the takedown log. Those are your evidence.
- [ ] Note the current `QUEUE_CONNECTION=database` and `CACHE_STORE=database`: those live in the same database, so a dropped DB loses queued work too. Fine at launch, worth moving once load justifies it.

### Other infra

- [ ] TLS everywhere, HSTS, and a proper security-headers setup. Note that Inertia renders through `resources/views/app.blade.php`, which today has no `og:`/social meta tags — see section 6.
- [ ] Put the app behind a WAF or at least a CDN in front of the HTML too, with rate limiting. Yours is a site specifically targeted by scrapers, spam bots, and people testing filters.
- [ ] Never expose `/storage` paths of original uploads if you want the timeline/delete story to hold; but note that `public/storage` is currently a symlink to local disk, which is one more reason to move to a bucket.
- [ ] Pick a host and a bucket in a KYC-friendly jurisdiction and be ready for suspend-and-ask. Have a migration plan: keeping the app stateless (bucket + external DB) is what makes moving realistic.

---

## 3. Ad networks

Ads are the normal revenue path for adult sites, and the current `AppLayout` has placeholder slots already in the UI. None of them have a tag in them, and there is no ad-network account yet — get one approved first.

### What accepts you, and what does not

- [ ] **ExoClick, JuicyAds, and TrafficJunky** generally accept adult traffic and are the standard starting points. Terms vary; read them.
- [ ] **Google AdSense does not** accept adult content. Do not even apply with this site on that account — AdSense policies prohibit "adult or mature content," and applying risks your whole Google account, not just this property.
- [ ] **Mainstream card processors do not accept you.** Stripe's standard terms prohibit adult content, and so do the standard terms at most mainstream processors. If you ever add paid memberships, tipping, or payments, you are in the adult-specific merchant category and need a high-risk/adult-friendly acquirer. Don't assume you can "just use Stripe."
- [ ] That last point matters for **ads too**: the money is not a card transaction. Your revenue arrives as a **network payout** (net-30/60, a minimum threshold, paid by wire/check/crypto depending on network and your country), not a card processor. Plan around the threshold and payout method before you count on the money, and register your payout details honestly. Some networks will want company docs; if you don't have a company yet, sort that out early — several won't pay individuals.
- [ ] Verify each network's AUP against what people will upload. Some will drop you for "suggestive" content near explicit, for certain categories, or for specific artwork types. Get it in writing where you can.

### What is already in the UI

There is exactly one slot in the current layout, and it is a placeholder:

- [ ] **Sidebar, right rail, 300×250** — `resources/js/Layouts/AppLayout.jsx`, the `RightRail` component, labelled "Ad — sidebar 300×250". It's a dashed box reading "network tag renders here". Replace the box with the network tag when you have it.

The others exist as intent in the UI copy, not as slots:

- [ ] **In-feed native, labelled, collapses when empty.** The right rail's own copy says "Promoted tiles are labelled and collapse when empty," and the reader says "No ads inside the comic reader." There is no in-feed ad component in the code yet — you need to build the slot, keep the label visible, and make it render nothing when the network returns no fill (an empty box on an adult gallery looks like a break; the collapse behaviour in the copy is the right idea, implement it).
- [ ] **Below-post** — same story: described in the design copy, not implemented. Add it below the post/comment area, never inside the media.
- [ ] Leave the comic reader ad-free as currently promised, or your "no ads in the reader" copy becomes a lie to your readers.

### The rule that closes accounts

- [ ] **The content on the page next to the ad has to satisfy the network's rules.** In practice: what the network's own terms prohibit — and what the network will tolerate — determines whether your account survives. An explicit piece next to a network that doesn't allow explicit neighbouring content gets you closed, and networks run automated page-context checks.
- [ ] Decide placement by rating. The safe pattern is: **ads only on SFW / Suggestive pages or in chrome that's rating-neutral, never beside Explicit media** — unless the specific network says otherwise, in writing. Enforce it in the layout components per-rating, not by hoping.
- [ ] Never put an ad network's script inside a page that could be screenshotted as "ad next to CSAM content." If you're not sure, don't monetise that page.
- [ ] Networks will also check that you have a content policy, an age gate (you do), and that your ad placements aren't deceptive (labelled, not fake "Download" buttons). The current labelled placeholder is a good start; keep the label.
- [ ] Make sure you clean out demo content first (section 6): the seeded gallery points at `picsum.photos` and `i.pravatar.cc` placeholder URLs and demo accounts. Ad networks scan sites; a still-demo site looks like a broken clone and can get you rejected during onboarding.

### Before you apply

- [ ] Site live, age gate on, real content, working report button.
- [ ] Legal pages published (section 1) — most networks ask for a privacy policy and terms.
- [ ] A real domain, contact email, and, ideally, a company entity for payout.

---

## 4. Moderation capacity

You do not need a mod team to launch. You do need a named person who is on duty every day, a written first-day policy, and a way to actually see reports. Today the report endpoint collects them and nothing surfaces them.

### Minimum viable (do this before you open)

- [ ] **A queue of reports.** Right now `ReportController` inserts into `reports` with `status = 'open'` (and `actioned`/`dismissed` exist but nothing sets them). Until you build a UI, have the on-duty person run a query against the `reports` table on a schedule you choose — at least daily, more often for the first weeks. The table has what you need: `reason`, `reportable_type`/`reportable_id`, `details`, `status`, `handled_by`.
- [ ] **A first-day policy, in writing, that you will actually follow.** For any post that looks like:
  - [ ] **Minor-related** — remove on sight. No warning, no time to respond. Ban the account. Then follow your law-enforcement/CSAM reporting obligations (see section 1) rather than just deleting the row.
  - [ ] **Non-consensual** — remove on sight (including leaks and deepfakes of real people), ban on pattern, preserve the record (screenshot + URL + timestamp) before deleting.
  - [ ] **Piracy / stolen work** — remove on receipt of a valid DMCA notice per section 1, and enforce repeat-infringer bans.
  - [ ] Everything else (spam, "other") — triage within a business day, act or dismiss with a `status` write and a note.
- [ ] **A public reporting route.** Exists: the report button → `POST /report`. Confirm it's reachable from posts, comments, profiles, and messages (the controller accepts `post`, `comment`, `user`, `message`), that guests can use it (they can — `reporter_id` is nullable), and that it's throttled (10/min). Make sure a person is watching, because the app does not page you.
- [ ] **Written escalation:** what the on-duty mod does with a `minor` report at 2 a.m., who they call, and what "removed" means (remove from store, not just soft-delete the post, since media files can still be served).
- [ ] Have at least two people who can act, so a weekend doesn't go uncovered.

### Build first, in this order

1. [ ] **An admin panel with a report queue.** A route behind `is_admin`, listing open reports newest-first, with the reason, the linked post/comment/user/message, and actions: remove content, mark `actioned`/`dismissed`, set `handled_by`, notify the reporter. This is the single highest-value piece of work; everything else on this list is easier with it.
2. [ ] **User bans.** Set and check `banned_at` / `banned_reason`. Today nothing reads them; a ban must actually block login and hide content. `is_admin` exists on users too, but no route guard uses it — add one at the same time as the panel.
3. [ ] **A takedown log.** Every DMCA notice and every moderation action, with timestamps, who acted, what action, and the content identifier. This is your evidence in a safe-harbor defence (section 1). Do not rely on "we remember."
4. [ ] **An audit trail for admin actions**, so a mistaken takedown can be explained and reversed.
5. [ ] Hook `reports` into the existing notification system (`notifications_app`) so an admin gets pinged. Note it's in-app only today — no websockets, no email — so it only fires when someone opens the page. If you need 24/7 coverage, send a transactional email (section 5) or an alert, rather than relying on the in-app one.

---

## 5. Email deliverability

Email is how verification and password resets work, so it is load-bearing even before you think about anything else. Like hosting and ads, many providers don't accept adult sites.

- [ ] **Verify before launch that your transactional provider accepts adult content.** Some mainstream providers explicitly prohibit it or treat it as a "sensitive category" and will suspend you after a few complaints. Read the AUP; when in doubt, use a provider known to allow adult platforms, or a self-hosted relay on your own domain.
- [ ] Check the specific product too — a big brand's "startup" plan may not inherit the tolerance of its enterprise product.
- [ ] Get set up with a real sender domain, not a shared one.
- [ ] **SPF** record on the sending domain, authorising your provider's IPs.
- [ ] **DKIM** signed by the provider; verify signatures pass.
- [ ] **DMARC** set up, start on `p=none` while you collect reports, then tighten to `quarantine`/`reject` once you're clean — and watch the reports even if nobody's watching the inbox.
- [ ] Watch complaints and bounce rates. Adult-site mail gets assumed-spam and abused for spam from day one; a compromised relay will burn your domain reputation fast.
- [ ] Configure in `.env` (see section 7): `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`. Right now the default `.env.example` is `MAIL_MAILER=log` — mail goes to a log file and nowhere else, and password resets go nowhere.
- [ ] **Test end-to-end from a real inbox:** register → get the verification email → click it → reset password. Also check it lands in the inbox rather than spam in Gmail/Outlook/proton.
- [ ] Send one email yourself to confirm the provider isn't filtering you — and keep checking after you launch, because a provider can change its policy on you mid-run.
- [ ] Decide a support address for users (a shared or alias mailbox on your own domain) — the DMCA agent address should be its own, not a `noreply@`.

---

## 6. Go-live sequence

Run in order, first to last. Each one is cheap now and expensive later.

**Prep, while the site is still private:**

- [ ] **Wipe the demo data.** The seeder creates 5 artists, 34 posts, and demo accounts, with placeholder media pointing at `picsum.photos` and `i.pravatar.cc`. None of that belongs in production: run `migrate:fresh` on the production DB rather than turning off the seeder, and make sure any uploaded demo files come out of storage too.
- [ ] **Seed real artists** (or invite a small number of trusted ones before the doors open) so the gallery isn't empty on day one.
- [ ] **Legal pages published** and linked from the footer/registration, and the registration checkbox copy (`resources/js/Pages/Auth/Register.jsx`) now points at real documents: content policy, terms, privacy policy, DMCA page.
- [ ] **DMCA agent registered** with the Copyright Office, and the DMCA page published with the registered contact.
- [ ] **Content and moderation first-day policy written**, and the on-duty rota agreed.
- [ ] **Check every config from section 7 is applied** in the production environment, then re-check the ones that bite: `APP_ENV`, `APP_DEBUG`, `APP_URL`, mail, storage.
- [ ] **Ad network account approved** (section 3) — or launch without ads. Launching with placeholder boxes the network hasn't approved is the same as launching with no ads, and it's not actually breaking anything.
- [ ] **Transactional email provider confirmed for adult content** and SPF/DKIM/DMARC live (section 5). Send real test mail to Gmail/Outlook.
- [ ] **Age gate and `INKWELL_AGE_GATE_TEST_BYPASS=false`** verified on the production box — with the bypass in its default `true` and tests running only in CI, the gate could be skipped without anyone noticing. Hit the site in a private window, with a cleared cookie, and confirm it redirects to `/age-gate`.
- [ ] **Robots.txt decision.** `public/robots.txt` currently says `User-agent: *` and `Disallow:` — i.e. everything is indexable. That is fine if you want search traffic; make the decision consciously. Some operators block `?explicit=1`-style gallery URLs and explicit post pages, and add `<meta name="robots" content="noindex">` on explicit pages. Note the gallery uses a query param for the explicit toggle, so decide whether to block that URL pattern. Whatever you choose, decide it now — changing it after indexing is slow.
- [ ] **Social preview meta.** `resources/views/app.blade.php` has no `og:title`/`og:description`/`og:image` or `twitter:` tags. When a link is posted to social media you get a bare URL and the site title, which for an adult site also means no control over whether the site preview shows something explicit on platforms whose rules ban it. Add OG tags per page, default to something safe, and **never** put explicit media into a preview image.
- [x] **Upload handling:** EXIF stripping on images and comic pages is in; uploads follow `FILESYSTEM_UPLOAD_DISK`, so new files go to S3 when you set it.
- [ ] Decide about video: disable uploads for launch, or build transcoding (which also strips video metadata).
- [ ] **Backups on** (DB + media bucket, restore tested), and off-box.
- [ ] **Monitoring up:** uptime check on the site and `/up`, plus something that watches for 500s. Note there is no analytics today — that's a privacy advantage, but you also won't know traffic or errors unless you add basic uptime/error monitoring. Pick a monitoring tool that itself allows adult sites; many mainstream ones don't.
- [ ] **Rate limits checked:** `POST /upload` 20/min, `POST /p/{post}/comments` 30/min, `POST /messages` 30/min, `POST /report` 10/min. They're per-user via Laravel's throttle and they're sane for launch; make sure your CDN/WAF doesn't strip IPs for the one behind it, and raise the limits only with reason.
- [ ] **Storage/CDN in front of media working**, and hotlink protection or signed URLs figured out.
- [ ] **Bans working** if you already need them (they don't yet — section 4), or confirmed that your first-day policy is "remove the post" rather than "ban the user".

**Open the doors:**

- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…` (section 7) and clear config cache (`php artisan config:clear && php artisan config:cache`).
- [ ] Run `migrate --force` if you haven't, then verify the migration state matches.
- [ ] Confirm one real registration → verification email → real upload → visible in gallery, on a phone as well as desktop.
- [ ] Confirm the report button creates an `open` row and your on-duty person sees it.
- [ ] Confirm the age gate blocks a cleared-cookie private window.
- [ ] Confirm the storage URLs actually serve media through the CDN, not the app box.
- [ ] Publish/announce.

**First 72 hours:**

- [ ] Check reports at least twice a day.
- [ ] Watch error logs, mail bounce/spam, and CDN bandwidth.
- [ ] Have a written and tested plan for "my host suspended me": you have the DB and bucket backed up elsewhere, and you know how to re-point DNS. Test it once on a staging box before you need it.

---

## 7. Config changes to make before launch

`.env` changes. Everything here is `.env`-only — the app has no SQLite or local-disk hard-coding except the `'disk' => 'public'` in `UploadController` noted below, which should be fixed in code.

| Variable | Set it to | Why / notes |
| --- | --- | --- |
| `APP_ENV` | `production` | Default is `local` in `.env.example`. |
| `APP_DEBUG` | `false` | **Critical.** `true` leaks stack traces, DB credentials and full file paths to any visitor on an error page. |
| `APP_URL` | `https://your-domain` | Default is `http://localhost`. Wrong here breaks asset URLs, mail links, and the `public` disk's URL prefix (`config/filesystems.php`). |
| `APP_KEY` | `php artisan key:generate` if empty | Nothing decrypts without it. |
| `DB_CONNECTION` | `mysql` | Default is `sqlite`. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | your DB | Use a dedicated user with only the privileges the app needs, and back it up (section 2). |
| `FILESYSTEM_UPLOAD_DISK` | `s3` | Where new uploads are written. Default `public` (local disk). Existing media rows keep their own `disk` column, so old files keep resolving. |
| `FILESYSTEM_DISK` | `s3` | Laravel's default disk; set alongside the upload disk for consistency. |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` | your bucket | The `s3` disk reads these. Use a scoped key, not root credentials. |
| `AWS_ENDPOINT` | your provider's endpoint | For S3-compatible stores (R2, B2, Wasabi, MinIO). |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` for most S3-compatible stores | Leave `false` for real AWS S3. |
| `AWS_URL` | CDN URL for the bucket | Optional; what `Storage::url()` returns. If unset, you get the provider's raw URL — you want the CDN here. |
| `MAIL_MAILER` | `smtp` (or `ses`/`mailgun`/`postmark` as appropriate) | Default `.env.example` is `log` — mail silently goes to a file and password resets go nowhere. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD` | your provider | Confirm the provider allows adult sites (section 5). |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | a real address on your sending domain | Default is `hello@example.com`. Must match the domain with SPF/DKIM/DMARC. |
| `INKWELL_AGE_GATE_TEST_BYPASS` | `false` in production | **The default is `true`** (both in `.env.example`/`config/app.php` and in the middleware). It only takes effect when running unit tests, but keep it `false` in production so a misconfigured test run can never skip the gate. Tests only. |

A few `.env.example` defaults are also worth reviewing for production, if not strictly launch blockers:

| Variable | Current default | Consider |
| --- | --- | --- |
| `QUEUE_CONNECTION` | `database` | Fine at launch; move off the DB as volume grows. |
| `CACHE_STORE` | `database` | Same. |
| `BROADCAST_CONNECTION` | `log` | There are no websockets; leave it. |
| `LOG_LEVEL` | `debug` | Set `warning` in production; `debug` writes a lot and can log sensitive request data to disk (and, at `APP_DEBUG`, users' data to places you'd rather it wasn't). |
| `LOG_CHANNEL` | `stack` / `single` | Rotate or ship logs; on an adult site, logs contain IPs and user agents that belong in your privacy policy. |
| `SESSION_DRIVER` | `database` | Ensure it goes in your DB backups; otherwise everyone is logged out on restore. |
| `SESSION_SECURE_COOKIE` | (unset; Laravel defaults it from `APP_URL`) | Set it explicitly to `true` on HTTPS. Same for `SESSION_DOMAIN`. |
| `APP_NAME` | `Laravel` | Set to `Inkwell` — it shows in page titles, mail from-names, and the OG fallback. |

---

*Written against this checkout. Not legal advice — have a lawyer review the terms, content policy, DMCA process, age-verification approach, and CSAM reporting obligations for your jurisdiction before launch.*

---

## Appendix: the demo accounts are local-only

The "Jump in as…" buttons on `/login` sign you in with one click by hitting
`GET /demo-login/{username}` (`DemoLoginController`). The route is registered
inside an `app()->environment(['local', 'testing'])` guard in `routes/auth.php`,
so it **does not exist** when `APP_ENV=production`. Two rules:

- [ ] Never set `APP_ENV` to anything other than `production` on a public box.
- [ ] Before launch, either delete `app/Http/Controllers/DemoLoginController.php`
      and `app/Support/DemoAccounts.php` or leave them — they are dead code on a
      production environment, but the demo users (`@inkwell.test`) must still be
      gone from the database.

Demo accounts and their passwords (`password`) are for local development only.
