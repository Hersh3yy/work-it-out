# Feetness (work-it-out)

The backend of Feetness, a fitness coach you text. Send a workout, your weight or how an injury feels to the Telegram bot in plain language; an AI turns the message into facts, plain PHP computes every number from those facts, and you get a receipt. Ask a question and a coach answers from your own logs. A Nuxt app (the sibling repository `feetness-app`) reads the same data over this API.

Laravel 13, PHP 8.5, `laravel/ai`, Sanctum, PostgreSQL 18, Pest, Docker.

## Run it

```bash
cp .env.example .env
make up
docker compose exec app composer install
docker compose exec app php artisan key:generate
make fresh
make test      # SQLite
make test-pg   # Postgres, as production
```

The API answers on http://localhost:8088. No test calls a model or Telegram.

## Where to read next

- `PROJECT.md`: status, issues, roadmap and the diary.
- `PLAN.md` section 5, "Next, in order": the one build order.
- `docs/code-tour.md`: one message followed through the code.
- `docs/deploy-coolify.md`: how production is built and deployed.
