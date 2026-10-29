# Feetness app

The phone app for Feetness: a Nuxt 4 single-page PWA (Tailwind 4, atomic design, one SVG sprite for icons) that reads the Laravel API with a bearer token. It shows your profile (facts the app computed, the written notes, active limitations) and every message you sent with what was saved.

## Run it locally

```bash
cd client
cp .env.example .env     # API at http://localhost:8088/api by default
npm install
npm run dev              # http://localhost:3000
```

Sign in: in Telegram send `/login` to the bot and tap the link, or locally `docker compose exec app php artisan log:simulate --user=1 "/login"` and open the printed link (the API's `APP_WEB_URL` must point at this app).

## Structure (atomic design)

- `app/components/atoms`: `Icon` (sprite symbol), `Badge`, `AppButton`, `Skeleton`
- `app/components/molecules`: `StatTile`, `SetChips` (one chip per set, top weight highlighted), `MessageBubble`, `LimitationBanner`
- `app/components/organisms`: `ProfileHeader` (weekly score ring), `WeekStats`, `NotesPanel`, `LogTimeline`
- `app/components/templates`: `AppShell` (header, content, bottom tabs)
- `app/pages`: `index` (home), `logs` (every message, paged), `login` (sign-in link landing)
- `app/composables`: `useAuth` (token in this browser), `useApi` (base URL, bearer header, 401 handling)
- `public/sprite.svg`: every icon as a `<symbol>`; `public/icon.svg` the app icon

## Deploy on Netlify (chosen 2026-10-29)

`netlify.toml` at the repository root holds the build: base `client`, `npm run generate`, publish `.output/public`, Node 24. `public/_redirects` rewrites every route to the shell and `public/_headers` sets the cache rules, so nothing else is needed on Netlify's side.

1. Netlify: Add new site, import from GitHub `Hersh3yy/work-it-out`, branch `master`. The build settings come from `netlify.toml`.
2. Site configuration, Environment variables: `NUXT_PUBLIC_API_BASE=https://<api host>/api` and `NUXT_PUBLIC_TELEGRAM_BOT=<bot username without @>`. They are baked in at build time, so change them and redeploy.
3. The API must be on HTTPS: a Netlify site is served over HTTPS and the browser refuses calls from it to an `http://` API (mixed content). The sslip.io address the API uses today is HTTP only, so give the API a real (sub)domain in Coolify with Let's Encrypt first (`docs/deploy-coolify.md`, section 5).
4. On the API set `APP_WEB_URL=https://<site>.netlify.app` (or the custom domain) so `/login` links open here, and `CORS_ALLOWED_ORIGINS` to the same origin, then redeploy the API.

Each push to `master` deploys both: Netlify builds the app, Coolify rebuilds the API (a no-op from cache when only `client/` changed, since the API image ignores it).

## Deploy on Coolify instead

Second application from the same repository: build pack Dockerfile, base directory `/client`, Dockerfile `/client/Dockerfile`, build arguments `NUXT_PUBLIC_API_BASE` and `NUXT_PUBLIC_TELEGRAM_BOT`. `client/Dockerfile` and `client/nginx.conf` stay in the repo for this. The same `APP_WEB_URL` and `CORS_ALLOWED_ORIGINS` steps apply.
