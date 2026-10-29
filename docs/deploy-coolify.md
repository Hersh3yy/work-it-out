# Deploying Feetness on Coolify (v0)

v0 is one app container (nginx, php-fpm, queue worker, scheduler, Telegram poller) plus one Postgres database, both on the same Coolify server. No Redis yet. Every push to `master` deploys automatically (Coolify's GitHub App webhook, verified 2026-10-18). A deploy only replaces the running container when the new one passes its health check; otherwise Coolify rolls back.

## 1. Postgres

In the same project and environment as the app (`Work it out` / `production`):

1. New resource: PostgreSQL. Pick version 18 if offered (17 works too).
2. Leave "Make it publicly available" off. No port mapping: only the app reaches it, over Coolify's Docker network.
3. Start it, then copy its **internal** URL (General, "Postgres URL (internal)", starts with `postgres://`, host is the database's internal hostname). That is `DB_URL` below. The default user and database `postgres` are fine for v0. SSL can stay disabled: the URL never leaves Coolify's private Docker network. Do not paste or screenshot that URL anywhere else; it holds the password.
4. Backups: turn on scheduled backups (daily). Add an S3 bucket when you have one, so a backup survives losing the server.

## 2. App environment variables

In the app: Environment Variables. The container has no `.env` file (Coolify injects the variables), so `php artisan key:generate` has nothing to write to. Run `php artisan key:generate --show` instead (in the app's Terminal in Coolify, or locally): it only prints a key. Paste that as `APP_KEY` and keep it the same across deploys.

```
APP_NAME=Feetness
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...                 # from key:generate --show
APP_URL=http://<your sslip.io or domain>
APP_WEB_URL=https://<the app's domain>  # where /login links point (section 5); defaults to APP_URL
# CORS_ALLOWED_ORIGINS=https://<the app's domain>   # narrow from * once the app has a domain
LOG_CHANNEL=stderr
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_URL=postgres://...              # the database's internal URL from step 1

CACHE_STORE=database               # the Telegram offset lives here; must be shared
QUEUE_CONNECTION=database
SESSION_DRIVER=database

AI_DEFAULT_PROVIDER=gemini
AI_TEXT_MODEL=gemini-3.5-flash-lite
AI_LOG_PROVIDER=gemini
AI_LOG_MODEL=gemini-3.5-flash-lite
AI_CLASSIFIER=llm
GEMINI_API_KEY=...                 # paid tier before more than one user

TELEGRAM_BOT_TOKEN=...             # the production bot (see "One bot per place")
REGISTRATION_OPEN=false
```

## 3. App settings

- Build: Dockerfile, location `/Dockerfile`, base directory `/` (already set).
- Ports Mappings: empty. Exposed port 80 only, reached through Coolify's proxy.
- Health check: the image has one (`/up`); Coolify can use it as well.
- Deploy. Always deploy the latest `master`: the hardened image (php-fpm on loopback, Telegram poller, closed sign-up) landed on 2026-10-12, after the first deployment.

## 4. After the first deploy

In the app's Terminal in Coolify:

```
php artisan channel:telegram:setup
php artisan channel:invite you@example.com --name=You
```

Open the printed link on the phone and tap Start. Text a workout.

Run `channel:telegram:setup` again after a deploy that changes the command menu (2026-10-29 added `/login` and `/better`).

If something goes wrong with a link: a new `channel:invite` for the same email makes a fresh link and retires the old one. New phone or new Telegram account: `php artisan channel:unlink you@example.com`, then a new invite. Logs are never lost by unlinking.

## 5. The app (feetness-app repository)

Decision 2026-10-29: the app deploys on **Netlify**, not Coolify, from its own repository (`~/Code/feetness/feetness-app`, moved out of `client/` on 2026-10-30). Steps are in that repository's README. What Coolify still needs for that:

- **HTTPS on the API.** The Netlify site is HTTPS and browsers block calls to an `http://` API from it. Point a (sub)domain's A record at 84.247.178.198, set it on the app in Coolify as `https://api.<domain>` and let Coolify issue the Let's Encrypt certificate (sslip.io names get no certificate in practice because of Let's Encrypt's per-domain limits). Then `APP_URL=https://api.<domain>`.
- `APP_WEB_URL=https://<site>.netlify.app` and `CORS_ALLOWED_ORIGINS` with the same origin, then redeploy.

The Coolify alternative, kept in case Netlify is dropped: a static Nuxt build served by nginx (Dockerfile and nginx.conf in the app repository), as a second Coolify application:

- New resource, the app repository, its default branch.
- Build pack Dockerfile, base directory `/`, Dockerfile location `/Dockerfile`.
- Build arguments (not runtime variables, the values are baked into the static files): `NUXT_PUBLIC_API_BASE=https://<api host>/api`, `NUXT_PUBLIC_TELEGRAM_BOT=<bot username without @>`.
- Ports Mappings empty; exposed port 80 through Coolify's proxy. Give it a domain.
- Then on the API: set `APP_WEB_URL` to that domain (so `/login` links open the app) and optionally `CORS_ALLOWED_ORIGINS`, and redeploy the API.


## One bot per place

Telegram allows one poller per bot token. Use one bot for the server (its token in Coolify) and a second bot for the laptop (its token in the local `.env`, with `make bot`). Create the second one with `/newbot` in @BotFather. Never run `make bot` with the production token while the server runs.

## Smoke checks

From the laptop:

```
curl -s -o /dev/null -w '%{http_code}\n' http://<app url>/up      # 200
nc -zv <server ip> 5432 6379 9000 3306                            # all refused
```

On the server, `ss -tlnp` should show only 22, 80, 443 and Coolify's own dashboard port. In the app logs: `telegram-poller` started, no `ERROR`, no key or token printed.

## Moving to another server later

Coolify manages servers over SSH. Add the new server under Servers, then move or clone the app and the database to it. Data moves with a database backup and restore (pg_dump and pg_restore; a few hundred rows today). Point the domain at the new server and redeploy. Stop the old app before starting the new one, so only one Telegram poller runs.
