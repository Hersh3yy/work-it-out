# Deploying Feetness on Coolify (v0)

v0 is one app container (nginx, php-fpm, queue worker, scheduler, Telegram poller) plus one Postgres database, both on the same Coolify server. No Redis yet. Coolify deploys `master` by hand (auto-deploy off).

## 1. Postgres

In the same project and environment as the app (`Work it out` / `production`):

1. New resource: PostgreSQL. Pick version 18 if offered (17 works too).
2. Leave "Make it publicly available" off. No port mapping: only the app reaches it, over Coolify's Docker network.
3. Start it, then copy its **internal** URL (starts with `postgres://`, host is the database's internal hostname). That is `DB_URL` below.
4. Backups: turn on scheduled backups (daily). Add an S3 bucket when you have one, so a backup survives losing the server.

## 2. App environment variables

In the app: Environment Variables. Generate `APP_KEY` once on the laptop with `docker compose exec app php artisan key:generate --show` and paste it; keep it the same across deploys.

```
APP_NAME=Feetness
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...                 # from key:generate --show
APP_URL=http://<your sslip.io or domain>
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
- Deploy.

## 4. After the first deploy

In the app's Terminal in Coolify:

```
php artisan channel:telegram:setup
php artisan channel:invite you@example.com --name=You
```

Open the printed link on the phone and tap Start. Text a workout.

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
