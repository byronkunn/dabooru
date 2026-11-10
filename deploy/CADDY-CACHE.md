# Production asset caching

Vite writes content-hashed production assets under `public/build/assets`. The
Hostinger Caddy site block applies a one-year immutable cache policy to those
URLs; use this matcher and header inside the `booruhub.tech` block:

```caddyfile
@fingerprinted_assets path /build/assets/*
header @fingerprinted_assets Cache-Control "public, max-age=31536000, immutable"
```

Validate and reload Caddy after changing its production configuration:

```sh
caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
systemctl reload caddy
```

Only files in Vite's fingerprinted asset directory get the immutable policy.
HTML and application responses remain uncached, so each deploy can point to new
asset hashes without stale pages or user-specific content being cached.
