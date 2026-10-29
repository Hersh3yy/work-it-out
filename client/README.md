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

## Deploy on Coolify

Second application from the same repository: build pack Dockerfile, base directory `/client`, Dockerfile `/client/Dockerfile`, build arguments `NUXT_PUBLIC_API_BASE=https://<api host>/api` and `NUXT_PUBLIC_TELEGRAM_BOT=<bot username>`. Give it a domain. On the API set `APP_WEB_URL` to that domain so `/login` links point here, and `CORS_ALLOWED_ORIGINS` to it if you want to narrow CORS from `*`.
