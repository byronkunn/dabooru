# Inkwell site test plan

This checklist covers the current Inkwell checkout, from the age gate and gallery to Creator Studio and staff tools. The browser and staging cases below are **ready to run**. Their checkboxes record manual results and start unchecked.

**Automated baseline on November 7, 2026:** `php artisan test --compact` passed **149 tests with 1,107 assertions**. `npm run build` passed. The PHP suite uses an in-memory SQLite database and an in-memory mail transport. Those results establish backend and build coverage. Browser behavior, deployed storage, and real email delivery need separate checks below.

## Setup and test data

1. Run against a disposable local database or a staging copy. Use synthetic artwork, accounts, messages, and notices. Seed a fresh QA database with `php artisan migrate --seed` after checking the target connection. The project [setup instructions](../README.md#setup) cover dependencies and local serving.
2. Start the app with `php artisan serve --port=8001`. Use `http://127.0.0.1:8001` unless your environment uses another address. Match `APP_URL` to the address you browse so generated links and local media resolve correctly.
3. Use a fresh browser profile for the **guest**, and separate sessions for a **verified creator**, a **verified reader**, a **second reader** who can be blocked, an **unverified member**, and **staff**. Start the reader with **Show explicit work** off. Run `php artisan db:seed --class=AdminSeeder` on the disposable local database if you need the first administrator. Give separate accounts Moderator, Curator, Analyst, and Operator roles through **Admin → People & roles**. For owner-only tests, set `ADMIN_OWNER_ID` to an existing test administrator’s ID.
4. Prepare a small JPG, transparent PNG, WebP, GIF, browser-playable MP4, and an invalid upload such as a plain text file renamed to `.png`. Use innocuous fixtures for every rating, including Explicit. Create an ordered three-image album, two series, two published chapters with numbered pages, one unpublished chapter, and two private collections. Seed or create at least 37 matching gallery works to exercise the second page. Use distinct work names such as `QA watercolor original`, `QA watercolor fanart`, and `QA explicit sample`.
5. For email checks, point the test site at a mail catcher or a controlled test inbox. For queued email, set `ADMIN_URGENT_REPORT_EMAIL` and start the staging queue worker with `php artisan queue:work`. Run the mail checks only with synthetic recipients.

Run the automated baseline from the repo root:

```sh
php artisan test --compact
npm run build
```

Run **A01, G01, U01, P01, C01, D02, D05, D07, K02, M01, M02, and X01** first for a short smoke pass. Then run the rest of the matrix. For each case record **Pass**, **Fail**, or **Blocked**, plus the environment and browser. When something fails, keep the case ID, account role, URL, steps, expected and actual results, and a screenshot or sanitized request ID when useful. Check a box once the expected result passes.

## Accounts, age gate, and settings

| Case | Action | Expected result |
| --- | --- | --- |
| [ ] A01 | Open the gallery and a protected deep link with cookies cleared. Confirm age, refresh, then use a fresh browser session and choose **Leave**. | The gate appears before protected content. Confirmation allows browsing and survives refresh. Leaving clears the gate state and exits the site. |
| [ ] A02 | Open Terms, Privacy, Content Policy, login, registration, and forgot password with a fresh guest session. | Each page renders at its own URL without requiring age confirmation first. Registration links open the correct policy pages. |
| [ ] A03 | Register with valid adult data. Repeat with a birth date exactly at the 18-year boundary, one day under it, no age confirmation, duplicate email or handle, and mismatched passwords. | Valid adult registration works. Invalid inputs show field errors and create no account. Boundary dates are evaluated consistently with the server date. |
| [ ] A04 | Sign in with correct and incorrect credentials, use Remember me, then log out and reopen a private URL. | Valid login starts a session. Invalid login fails. Remember me persists according to the cookie. Logging out removes private access. |
| [ ] A05 | Verify a new account using the email link. Try a changed or expired link and the resend control. | Only a valid link verifies the right account. Resend is controlled and sends a fresh message to the test inbox. |
| [ ] A06 | Request a reset, open the link, set a new password, then retry the old password and the used reset link. Try password confirmation and update with a wrong current password. | The new password works. Invalid credentials and reset links fail. The old password no longer signs in. |
| [ ] A07 | Edit handle, name, bio, website, avatar, and banner. Try a duplicate handle and invalid files. Open the profile in another session. | Saved fields and images appear on the profile. Invalid changes show errors without overwriting valid data. |
| [ ] A08 | Change Show explicit and the Likes, Comments, and New followers preferences one at a time. Reload and trigger each event from another account. | Preferences persist. Work visibility changes where supported. Disabled notifications stay quiet while enabled events still arrive. |
| [ ] A09 | On a disposable account, attempt deletion with a wrong password and then the correct one. | A wrong password preserves the account. Correct deletion ends the session and prevents signing back in. |
| [ ] A10 | Open the login page on local and production-like staging. Try the demo route with an unknown username and on the production-like environment. | Local demo login only accepts eligible demo accounts. Production-like staging has no demo switcher or demo login route. |

## Gallery, search, and discovery

| Case | Action | Expected result |
| --- | --- | --- |
| [ ] G01 | Browse the gallery. Try All, Image, GIF, Video, Album, and Comic, then Top rated, Most viewed, and Newest. | Each filter and sort matches the displayed work. Selected controls and query parameters stay in sync. |
| [ ] G02 | Try each time window with posts inside and outside it. Send unknown filter values and a combination with no matches. | The window includes the expected fixtures. Unknown values fall back safely. Empty results show a usable empty state. |
| [ ] G03 | With 37+ matches, load another page, then change a filter during loading. Repeat with a throttled or interrupted connection. | New pages append without duplicate or stale works. Filters reset the result set. A failed request leaves the page usable and can be retried. |
| [ ] G04 | Open Gallery and Discover with no sponsor configured. Configure a valid sponsor in a test environment and load more Gallery works. | Empty slots collapse. Configured ads still appear in their intended placements with a visible Advertisement label. |
| [ ] G05 | Search by title, caption, registered handle, external artist name, comic title, and tag. Switch between Works, Comics, and People. | Matches appear in the correct tab. Empty, short, and unusual queries are handled without errors. |
| [ ] G06 | Test `tag:watercolor -tag:fanart`, `#watercolor`, `-sketch`, `type:comic`, `rating:sfw`, and `by:"QA Artist"`. Try `%`, quotes, and a tag alias. | Operators and aliases resolve correctly. Excluded works stay out. Special characters do not cause a crash or broaden matches unexpectedly. |
| [ ] G07 | Open search suggestions by typing and with an empty input. Navigate with the keyboard, press Escape, and pick a suggestion. | Suggestions show the correct types and eligible trending tags. Focus, selection, and navigation work with a keyboard. |
| [ ] G08 | Click a tag chip on a work, then change gallery filters, clear the tag, and use browser Back. | The tag gallery and filters stay in sync. Back restores the previous view. |
| [ ] G09 | Compare guest, explicit-off, explicit-on, and blocked sessions across gallery, search, autocomplete, Discover, and shared collections. Inspect the page response as well as the visible cards. | Viewer-specific results omit works outside that view’s rating or block policy. Counts, previews, and suggestion responses do not disclose excluded items. Check direct URLs separately in X03. |
| [ ] G10 | Open a gallery card on desktop and mobile with a long title, missing thumbnail, no artist, and multiple media items. | Cards stay within the layout, show the appropriate fallback, and open the right work. |
| [ ] G11 | Scroll through search results and open a work. Use the work page’s Back button. Repeat after loading another Gallery page with filters applied. | Back returns to the exact URL and scroll position. The Gallery keeps the works you already loaded. |
| [ ] G12 | Publish a curated shelf with visible works, an explicit work, and a work by a blocked account. Open Discover with explicit content off as the blocking account. | Staff Picks shows the published shelf with only eligible works. Unpublished shelves stay private. |

## Upload, posts, and Creator Studio

| Case | Action | Expected result |
| --- | --- | --- |
| [ ] U01 | Upload an original image with title, caption, rating, and tags. Open it from the gallery in a second session. | The work appears once, with the correct credit, rating, media, and tags. The thumbnail and full image load. |
| [ ] U02 | Upload a GIF and a browser-playable video. Use the visible playback controls and reopen the work after refresh. | Each format renders using the right viewer. Supported files play. Record the handling of a file that needs conversion separately in X06. |
| [ ] U03 | Upload a three-image album. Open each image and navigate the viewer with mouse, touch, keyboard, and Escape where supported. | All images open in their original order. Closing the viewer returns focus to the work and avoids page overflow. |
| [ ] U04 | Try invalid rating, wrong file type, renamed non-image, over-limit files, a one-image album, and a 21-image album. | The server rejects invalid uploads with a usable error. No broken or partial post appears in the gallery. |
| [ ] U05 | Upload supported images with metadata, transparency, portrait dimensions, and landscape dimensions. Check the served file and thumbnail. | Expected derivatives load, proportions remain correct, and sensitive embedded metadata is stripped where re-encoding is supported. Record any transparency or format changes. |
| [ ] U06 | Post with external artist credit and a source link. Edit the title, caption, tags, and rating as the uploader. | Credit and the source link render correctly. Edits persist and search/gallery reflect the current metadata. |
| [ ] U07 | Try opening another member’s edit URL and submitting an update or delete directly. Delete your own disposable published work. | Non-owners cannot change or delete the work. The owner’s deletion removes it from discovery, saved views, and its public page. |
| [ ] U08 | Save an upload as a private draft. Check its Studio preview, guessed public post URL, captured media URL in another session, gallery, search, and tag autocomplete. | Only the owner can see draft media. The draft and draft-only tags stay out of public views. |
| [ ] U09 | In Studio, edit a draft, reorder its album, save, reload, then publish. Repeat the publish request. | Edits and order persist. Publication creates one visible work in the intended order. A duplicate request does not create a second work. |
| [ ] U10 | Reorder a published album. Try submitting an item ID from a different work, then discard a separate draft. | The published order changes only for its owner. Invalid ordering preserves the old list. A discarded draft and its preview are inaccessible. |
| [ ] U11 | View and save a creator’s work from another account. Open Creator Studio from both sessions. | The creator sees updated view/save counts for their work. The other account cannot see the creator’s draft list or private Studio data. |

## Profiles, engagement, messaging, and blocking

| Case | Action | Expected result |
| --- | --- | --- |
| [ ] P01 | Open another creator’s profile and switch Works, Albums, Comics, and Credited works. Follow them, browse Following, then unfollow. | Tabs show the right content. A follow adds eligible posts to Following. Unfollow removes them. Self-follow is rejected. |
| [ ] P02 | Like, unlike, save, and unsave a post. Repeat writes and reload. Open the same work twice in one session and once from another account. | Counts and saved state stay consistent without duplicate likes or saves. The view counter counts the same viewer at most once per day. |
| [ ] P03 | Comment and reply where available. Try blank or oversized text. Delete your own comment and attempt to delete someone else’s. | Allowed comments render and update counts. Invalid submissions fail. Only the author can delete their comment through the member flow. |
| [ ] P04 | Trigger likes, comments, and follows between accounts. Open Notifications, test the category tabs and links, and mark all read. | Eligible events appear with working links and accurate badges. Mark all read clears the badge. Self-actions do not notify the actor. |
| [ ] P05 | Start a DM from a profile. Send a message, reply from the other account, and bring the new message into view to clear the unread badge. Remove your own message. | A stranger’s text appears in Requests. Accept it before replying. Both participants see the latest preview and timestamps. Seeing the message clears the unread state and deletion removes the message content. |
| [ ] P06 | As a third account, paste the conversation URL and try to post, remove, or report a message you cannot access. | The third account cannot read or act on the private conversation. No message body leaks in an error response. |
| [ ] P07 | Block an account while both accounts follow each other. Inspect gallery, Following, search, the blocked list, and unblock. | The block breaks both follows and filters discovery as designed. Unblocking restores eligibility without silently following again. |
| [ ] P08 | Refresh notifications and messages while signed out, then revisit using browser Back after logout. | Private data cannot be reopened by a new request. Note any stale rendering left in browser history for privacy review. |
| [ ] P09 | Send image-only, video-only, and text-plus-image DMs. Open the image viewer, play the video, and switch conversations on a narrow screen. | Eligible files render with a usable preview. Closing the viewer and switching threads preserve access to navigation. The inbox and switcher preview the latest message or indicate an attachment. |
| [ ] P10 | As the recipient, copy a test attachment's original and thumbnail URLs. Try both links signed out and as an unrelated member, then repeat after the sender removes the message. | Only eligible participants can open the links. Neither original nor thumbnail resolves from chat after removal. |
| [ ] P11 | In two signed-in browsers, send a first DM as a stranger. Try a second message and an attachment before acceptance. Accept from Requests, then reply, react, quote, edit, forward, and test the recipient’s forwarding setting. | One text-only request is allowed. The response and typing state appear without refreshing. Forwarding and read receipts respect privacy settings. |
| [ ] P12 | Open New Chat and the inbox on desktop and mobile. Try a bookmarked group or channel URL, including a former attachment link, as a former participant. | New Chat opens a direct message. Saved Messages has its own shortcut in the inbox. Retired conversations stay out of the inbox, search, and unread counts. Their old links deny access. |
| [ ] P15 | Record and play voice and video notes, send an original image and PDF, reveal a spoiler, share a visible work, then publish and send a sticker pack. | Files stay private, the PDF downloads, the spoiler is covered by default, hidden works are filtered, and stickers link to their pack. |
| [ ] P16 | Schedule a text, test silent send, and set auto-delete. Report a message with an attachment before expiration, then open and resolve the report as staff. | The scheduler delivers once. Silent sends skip the global unread badge. Expiration removes chat access. Only authorized staff can view logged evidence while the report is open. |
| [ ] P17 | Open the same new DM from both profiles before sending. Decline the first request, try again as the sender, then start the chat as the recipient. In an accepted chat, scroll up while a new message arrives and open old history after blocking. | Opening either composer does not interfere with the other. Only the person who declined can reopen the chat. Offscreen messages stay unread. Blocking suppresses typing indicators and read receipts. |
| [ ] P18 | Send a shared artwork card with no caption. Report it, then remove the message before reviewing as a moderator. | The moderator still sees the reported work title and reference. |

## Comics and chapter inbox

| Case | Action | Expected result |
| --- | --- | --- |
| [ ] C01 | Create a series with title, cover, rating, and status. Publish a numbered chapter and open the series as a reader. | The cover and chapter list load. Exactly one feed post links to the published chapter. |
| [ ] C02 | Upload a chapter draft with numbered pages. Attempt to open its reader URL, neighboring chapter link, and direct preview from another session. | The draft is missing from public chapter lists and navigation. The reader and private media deny access. |
| [ ] C03 | In Studio, edit the chapter draft, reorder pages, preview the new order, and publish. Try a duplicate chapter number and an update by a non-owner. | The published reader displays the chosen order. Duplicate numbers and unauthorized changes fail without creating partial chapters. |
| [ ] C04 | Read in page and scroll modes. Use Previous/Next controls and any supported keyboard shortcuts. Reload, reopen from Continue Reading, and test first and last boundaries. | Pages and modes work at all supported widths. Progress resumes correctly. Controls do not point to nonexistent or unpublished chapters. |
| [ ] C05 | Follow one series and leave another unfollowed. Open Chapter inbox, read to the last page, then use Mark read on another item. | Only eligible unread chapters from followed series appear. Completion and Mark read clear their entries. |
| [ ] C06 | Start an earlier chapter, then publish a later one. Test the inbox’s resume link, then unfollow or block the creator. | The resume link targets the accessible unfinished chapter. Unfollowing or blocking removes the affected inbox entries. |
| [ ] C07 | Discard a chapter draft, submit an out-of-range progress page or a chapter ID from another series, and request a nonexistent chapter. | Draft preview access ends. Bad progress requests do not overwrite saved progress. A missing chapter returns a safe 404. |

## Saved works, credit, and curated shelves

| Case | Action | Expected result |
| --- | --- | --- |
| [ ] D01 | Save several works, create a collection, add and remove a work, rename the collection, and delete it. | The named collection shows the selected works. Removing membership and deleting a collection preserve bookmarks in All saved. |
| [ ] D02 | Open a private collection URL as a different account. Create a share link as the owner and open it in another age-confirmed session. | Private owner routes deny access. The share link opens the public collection view and exposes only eligible works. |
| [ ] D03 | Make the shared collection private, retry the old link, and share again. Test with a viewer blocked by the owner. | The old URL stops working and stays revoked after resharing. The new link works for eligible viewers and denies blocked accounts. |
| [ ] D04 | Delete or moderate a work included in a shared collection, then reload as a viewer. | The removed work disappears without breaking the collection page. |
| [ ] D05 | Follow `tag:watercolor -tag:fanart` as a named search. Publish a newer match and a newer excluded work. Open the saved feed, try its URL as another account, then unfollow it. | Matching works are newest first. The excluded work stays out. Other users cannot view or delete the saved search. Unfollowing removes it. |
| [ ] D06 | Follow a search using artist/format filters. Save with an existing name, change rating preferences, and reload the feed. | The saved filters match the search. Reusing a name updates that feed. Current viewer preferences still apply. |
| [ ] D07 | As Curator, create a shelf with a work, comic, and artist in a known order. Save it as draft, publish, edit the order, and unpublish. Try the same write as a regular member. | Only the published shelf appears on Discover. Entry order and links match the saved list. Regular members cannot manage shelves. |
| [ ] D08 | Enter an invalid or off-site link and more than 12 entries. Remove or block one published shelf item. | Invalid entries show a useful error. A missing or ineligible item is omitted without exposing hidden media or breaking other entries. |
| [ ] D09 | As a verified user, claim credit on a work with external attribution and supply evidence. Try as an unverified user. Approve from a separate curator account. | Only an eligible member can file. The original source and uploader stay intact. An approved artist profile link appears on the work and in Credited works. |
| [ ] D10 | Reject a claim with a reason, submit corrected evidence, and try to review your own claim. Revoke an approved claim as a separate curator. | Decisions appear in Studio and staff audit history. Self-review is denied. Revocation removes the public profile link without altering the source. |

## Community tags and wiki

| Case | Action | Expected result |
| --- | --- | --- |
| [ ] K01 | Browse `/tags` by category and search. Open a tag wiki, standalone guide, related tags, examples, and revision history. | Definitions, category results, examples, and history link to the correct items. |
| [ ] K02 | Submit a new definition or wiki edit from a verified member. View it as the author, a different regular user, and a curator. Approve it. | A pending edit is visible to its author and eligible staff. Public content changes only after approval. The decision appears in history. |
| [ ] K03 | Reject a revision with a reason, approve a separate one, then revert it. Try reverting after a newer conflicting change. | Rejection preserves published content. Revert adds history. A stale revert refuses to overwrite the later change. |
| [ ] K04 | Try contributions as unverified, restricted, and banned users. Approve enough independent qualifying edits to exercise contributor trust. | Ineligible accounts cannot contribute. Eligible trusted users can publish ordinary edits while structural changes still require review. |
| [ ] K05 | Rename a tag, add an alias, try a conflicting alias, then search and upload using the old name. | The old name resolves to the canonical tag. Alias conflicts fail without partially saving the edit. |
| [ ] K06 | Propose related tags and transitive implications. Try a cycle and a rule expansion beyond the supported limit. | Approved rules apply to new tagging as designed. Cycles or over-limit expansions fail without changing current tags. |
| [ ] K07 | Preview a bulk retag on disposable works, change one target after preview, then retry with a fresh preview. Review, apply, and roll back. | Preview identifies affected works. Stale previews are rejected. Approved changes and rollback preserve the expected tag sets. |
| [ ] K08 | Merge duplicate tags after any required retagging and relationship cleanup. Open the old name and old page URL. | Old names and links resolve to the surviving tag. Works remain discoverable under the canonical name. |
| [ ] K09 | Watch a tag or wiki, approve a change, then unwatch and approve another. Post a discussion message, report it, and resolve the report as curator. | Watches produce eligible notifications. Unwatch stops future ones. Resolved discussion reports hide content only when staff chooses that action. |
| [ ] K10 | Lock a page, attempt edits and discussions as a regular member, then unlock. Attempt to review/lock as a member and inspect the audit log as admin. | Locks and restrictions are enforced server-side. Staff actions appear in the log. |
| [ ] K11 | Submit wiki Markdown with raw HTML, embedded images, and a script URL. Compare an Explicit-rated wiki and its revisions in explicit-off and explicit-on sessions. | Unsafe markup and URLs do not execute or render as unsafe embeds. Opted-out viewers cannot retrieve restricted wiki content or revisions. |
| [ ] K12 | Open a post editor in two sessions. Change tags in one, then save stale tags in the other. Attempt a community edit that changes ownership, rating, or confirmed artist membership. | Stale edits are rejected. Community changes cannot alter protected fields or assert verified artist status without staff review. |

## Admin, reports, and site operations

| Case | Action | Expected result |
| --- | --- | --- |
| [ ] M01 | Open each staff URL directly as guest, member, Moderator, Curator, Analyst, Operator, and Administrator. Check both desktop and mobile navigation. | Guest/member access is denied or redirected appropriately. Moderator sees Reports, Curator sees Community Edits/Shelves/Credit Claims, Analyst sees Analytics, Operator sees Site Logs, and Administrator has all sections. Direct writes obey the same permissions. |
| [ ] M02 | File a routine report and a newer urgent report using harmless content. Inspect the moderator queue and notifications, then click the alert. | Urgent cases sort first. Staff gets an in-app alert that links to the case. Sensitive previews are suppressed. |
| [ ] M03 | Claim, release, and filter a report by urgency or assignment. File two reports on one work and open the related-reports view. | Assignment persists and filters are accurate. The related view groups the correct work. A claimed case shows its assignee. |
| [ ] M04 | Dismiss one report, correct the rating of a routine report, remove a disposable post or comment, and attempt to act twice on a closed report. | Each decision changes only the intended target and creates one audit entry. Closed cases cannot be acted on again. |
| [ ] M05 | Hide a reported comic chapter, reopen its reader URL, then restore the work from staff tools. | The hidden work disappears from public pages. A valid restore returns it once and records the reason. |
| [ ] M06 | Ban and unban a disposable user. Try banning yourself and another admin through both People & roles and report actions. | A banned user loses their session on the next protected request. Unban restores eligibility. Protected staff cannot be banned through either route. |
| [ ] M07 | Grant then revoke each limited role, inspect navigation and direct endpoints, and try granting full admin as a non-owner. Repeat the allowed grant on an owner test account. | Role changes alter server access and appear in the audit log. Full admin grants require the configured owner. |
| [ ] M08 | Submit a valid DMCA notice referencing a local test post. Try a missing declaration, bad URL, and a foreign URL. Claim or release the valid notice, then reject or resolve a separate notice. | Invalid submissions fail. Valid notices appear in the queue. Claiming and decisions record actor and reason. An unrelated URL cannot take down a local work. |
| [ ] M09 | Take down the valid notice’s work. Try restoring it while the hold is active. Resolve the notice, then restore the work. | The app hides the work. An active hold blocks restore. A resolved hold permits restore and both decisions are logged. Test old direct files separately in X04. |
| [ ] M10 | Filter and paginate the Moderation Log. Find entries for a ban, rating change, credit decision, shelf publication, and role change. | Records show the right actor, target, reason, and timestamp across pages. |
| [ ] M11 | Use a controlled staging error or failed job. Open Site Logs, filter by date/category/request ID, and compare queue counts. Try the same page as a regular member. | The Operator can find the right metadata and queue health. Sensitive exception text, secrets, and job payloads are absent. Regular users have no access. |
| [ ] M12 | Compare Analytics over 7 and 30 days with known fixture signups, published uploads, views, saves, and closed reports. Include an unpublished work. | Counts and report timing match the fixtures. Private drafts are excluded from published work totals. Date controls and empty periods render correctly. |
| [ ] M13 | Configure the urgent alert inbox, file a synthetic urgent report, and process its job. Check the catcher and intentionally fail delivery in staging. | The email reaches only the configured test inbox, contains the case link, and omits sensitive media. A failure is visible through queue/log operations and does not lose the report. |

## Cross-cutting browser and release checks

| Case | Action | Expected result |
| --- | --- | --- |
| [ ] X01 | Test at about 320px, 768px, 1024px, and a wide desktop viewport. Include Gallery, Search, Upload, Reader, Messages, Studio, and each admin section. | Pages fit without horizontal overflow. Bottom navigation and the desktop rail switch cleanly. Forms and long labels remain usable. |
| [ ] X02 | Tab through navigation, user menu, search suggestions, dialogs, forms, and reader controls. Test Escape, visible focus, zoom, and reduced motion. | Focus is visible and follows a logical order. Dialogs are dismissible, controls are named, and zoom preserves access to actions. |
| [ ] X03 | Paste a direct explicit work URL and a direct explicit chapter URL with explicit off. Repeat with a blocked profile/work link. Inspect the HTML/Inertia response and media network requests. | Record exactly what is exposed by direct links, including any deliberate blur/reveal flow. Compare it with the site’s chosen rating and block policy. Treat unintended title, image, or media delivery as a privacy failure. |
| [ ] X04 | From another session, request a draft asset, a legacy draft asset if one exists, and a previously copied direct media URL for a taken-down work. Test the same URLs through any CDN used on staging. | Private draft media is denied. Record whether older public or CDN URLs remain accessible after a takedown, and verify that any configured origin/CDN removal actually revokes them. |
| [ ] X05 | Try write actions from an expired session, remove a CSRF token from a staging request, and submit enough requests to trigger a route’s throttle. | Invalid writes fail safely. Expired sessions can recover through login. Throttling gives a usable response and no duplicate changes. |
| [ ] X06 | Play a supported MP4 and upload a clip that needs transcoding. Inspect Site Logs if a job stays pending. | Direct playback works for supported media. The current build stores videos with a processing status and has no transcoding pipeline. Record that separately from a supported video playback failure. |
| [ ] X07 | Send a message after blocking the recipient, from both a new and existing conversation. | Both directions are denied from new and existing direct chats. Old history stays readable. Typing, reactions, and pins cannot be used to contact the blocked person. |
| [ ] X08 | Open the production build over HTTPS on staging. Check `/up`, images and videos from the deployed storage backend, private file access, password reset, urgent alerts, and log retention. | Health, assets, queues, mail, and access checks work on the actual deployment. Site Logs event retention runs when the scheduler is configured. |
| [ ] X09 | With a larger fixture library, test gallery paging, broad search, Studio, and staff queues on a throttled mobile connection. Record initial render, next-page latency, and payload size. | Pages stay usable under load. Compare measured results with a baseline for the same data and environment, and flag regressions. |
| [ ] X10 | On a phone, open each primary navigation destination. In Inbox, switch from Messages to Activity. In You, open Library and its three tabs. | The primary bar is Browse, Search, Create, Inbox, and You. Create leads to artwork uploads, comic creation, and Studio. The tabs preserve each destination’s own content and counts. |

## Automated coverage map

Use `php artisan test --filter=ClassName` to rerun a specific group.

| Area | Existing suites | Priority browser or staging checks |
| --- | --- | --- |
| Age gate and account lifecycle | `AgeGateTest`, `tests/Feature/Auth`, `SettingsTest`, `DemoAccountsTest` | Actual mail delivery, cookie persistence, account session transitions |
| Discovery and engagement | `GalleryTest`, `SearchTest`, `EngagementTest`, `BlockTest` | Load-more races, suggestions keyboard behavior, ad placement, direct-link privacy |
| Publishing, comics, and ownership | `UploadTest`, `ComicsTest`, `OwnershipTest`, `ReaderAndCreatorFeaturesTest` | Browser playback, lightbox, reader progress across modes, cross-browser uploads |
| Saved searches, collections, shelves, and credit | `ReaderAndCreatorFeaturesTest` | Findability in the UI, mobile flows, share-link behavior on staging |
| Community wiki and tags | `KnowledgeTest` | Editorial workflows in browser, keyboard controls, mobile diff rendering |
| Reports, roles, DMCA, and operations | `AdminPanelTest`, `AdminWorkspaceTest`, `LegalPagesTest` | Staff navigation by role, real queue/mail behavior, origin and CDN media revocation |
| Messaging | `MessagingTest`, `MessageMediaTest`, `ChatHubTest`, `ChatPermissionsTest`, `OwnershipTest` | Browser playback, requests, unread timing, blocks, retired conversations, stickers, scheduling, and moderation evidence |

For a release, record the build or commit, environment, browser versions, automated result, and failed or blocked case IDs. Resolve unexpected access leaks, broken authentication, data loss, and broken core publishing or moderation flows before calling the release ready.
