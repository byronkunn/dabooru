# Inkwell mockup v1

Galleries-first NSFW art site. These are **static mockup screens** — real click-through between pages, no backend.

## Run it

From this directory:

```bash
python3 -m http.server 8099 --bind 127.0.0.1
# then open http://127.0.0.1:8099/index.html
```

Opening the files directly with `file://` also mostly works, but relative links and the shared CSS/JS are more reliable over a local server.

## What's here

- `index.html` — screen index, click through everything from here
- `screens-gallery.html` — masonry gallery home (format tabs, sort, time window, explicit toggle, promoted tile)
- `screen-following.html` — Twitter-style feed for people you follow
- `screen-post.html?id=w06` — work detail (image / GIF / video / album / comic chapter card)
- `screen-profile.html` + `screen-profile-edit.html` — profile with tabbed collections
- `screen-comic-series.html?series=neon-after-dark` — series page
- `screen-reader.html` — comic reader, page mode + scroll mode
- `screen-messages.html` — DMs
- `screen-notifications.html` — notifications
- `screen-upload.html` — upload composer for all five formats
- `screen-settings.html` — settings
- `screen-gate.html`, `screen-signup.html`, `screen-auth.html` — 18+ gate and auth

## Design system

- `screens.css` — tokens, layout and the three responsive breakpoints (desktop ≥1201, tablet 901–1200, mobile ≤900)
- `app.js` — shared mock data, post/cell renderers, nav shell

## Screenshots

`shots/` holds every screen at desktop (1440×900), tablet (834×1112) and mobile (390×844). Regenerate with:

```bash
NODE_PATH=/Users/byron/.dsh/profiles/desktop/node_modules node shoot.js
```

## Placeholders

- Photos are `picsum.photos`, faces are `pravatar.cc` — stand-ins only
- "Inkwell" is a working name
- Ad boxes are labelled placeholders, not live network tags
- Preferences (show explicit, reader mode, toggles) persist in `localStorage` under the `inkwell.` prefix
