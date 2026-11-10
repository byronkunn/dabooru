# Production launch checklist

This checklist reflects the Hostinger VPS currently serving `booruhub.tech`. It is
an operations checklist, not legal advice. The code cannot decide what age assurance
or reporting rules apply; get qualified advice for the jurisdictions you serve.

## Verified on the live VPS

- [x] `APP_ENV=production` and `APP_DEBUG=false`.
- [x] PostgreSQL is local-only on loopback.
- [x] Caddy and PHP-FPM are running.
- [x] Queue worker is supervised by systemd as `www-data` with automatic restart.
- [x] Scheduler timer runs Laravel tasks once per minute; recent runs succeeded.
- [x] UFW defaults to deny incoming traffic. Public HTTP/HTTPS reach the origin from
      Cloudflare ranges; global direct HTTP/HTTPS rules have been removed.
- [x] Production logs are daily, retained 14 days, at warning level. Secure session
      cookies are enabled.
- [x] Laravel health route `/up` is available.
- [x] Public gallery uploads use S3-compatible storage; direct-message attachments
      use private local storage.
- [x] New public video uploads are disabled by default until transcoding and video
      metadata removal are ready; existing posts remain available.

## Must complete before wider public launch

- [ ] Configure a private, encrypted backup for the PostgreSQL database and media
      outside the VPS. Keep encryption keys outside the server. Alert on failed
      backups and perform a full restore drill.
- [ ] Add external uptime and application-error alerts. Check disk capacity, queue
      failures, database availability, media storage, and urgent reports.
- [ ] Verify the transactional mail provider accepts adult platforms. Send test
      registration verification and password-reset messages; configure SPF, DKIM,
      and DMARC.
- [ ] Have counsel review the age gate and DOB flow, Terms, Privacy, Content Policy,
      DMCA process, record retention, and applicable age assurance/reporting duties.
      The current age gate is a user self-attestation; it does not verify identity.
- [ ] Assign daily coverage for urgent reports and write an escalation procedure.
      Test the admin report queue, evidence access, removal flow, DMCA submissions,
      and moderation audit records.
- [ ] Keep `UPLOAD_VIDEO_ENABLED=false` until a transcode pipeline removes metadata,
      generates playback derivatives, and marks uploads ready. Review existing videos
      separately for metadata exposure.
- [ ] Review and remove sample or placeholder content that should not appear to users.
- [ ] Confirm the Hostinger plan renewal and billing details.
- [ ] Run a release review against PostgreSQL before each deployment; the previous
      `/studio` production error showed why SQLite-only checks are insufficient.

## At every release

- [ ] Check out the intended commit and inspect migration changes.
- [ ] Confirm a recent database backup and healthy media storage.
- [ ] Deploy using [the Hostinger runbook](DEPLOYING.md); never run `migrate:fresh`
      on production.
- [ ] Check `/up`, Caddy, PHP-FPM, PostgreSQL, queue worker, and scheduler timer.
- [ ] Verify age gate, sign-in, gallery media, uploads, messaging, and admin reports
      in a private browser session.
- [ ] Inspect the queue and scheduler logs for errors and confirm rollback steps.

## Operational notes

- Hostinger's panel showed weekly VPS backups and two snapshots. Treat these as
  short-term recovery points, not the independent database/media backup above.
- Keep the origin behind Cloudflare. UFW currently allows web traffic only from
  Cloudflare ranges and keeps SSH reachable; review firewall rules after changes and
  keep Cloudflare's source ranges up to date.
- The active production stack is Hostinger/Caddy/PHP-FPM/PostgreSQL. `render.yaml`
  and Docker deployment files are not used for the live site.
