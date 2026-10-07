# Feetness (work-it-out): read this first

You are working in Hiren's fitness-coach backend. Before doing anything:

1. Read `PROJECT.md` (the cockpit: status, run steps, issues, guide, hard parts, roadmap, diary).
2. Read `PLAN.md` (the plan: where we are, end-user description, architecture, milestones M0 to M12 each with a test, simulation scenarios, decisions, open questions).
3. Check the diary at the bottom of `PROJECT.md` for the last session, then continue from the first unticked roadmap item.

## Rules

- No app-code changes without Hiren's explicit green light. Assess, plan and write docs freely; ask before touching `.php`, config, migrations, Docker files.
- Work on a branch. Merge to `master` only when the milestone's release gate in `PLAN.md` passes and the suite is green.
- Backend first. Telegram is the only interface for release 1. The app (Flutter optimistic, Nuxt 4 fallback) comes after the API contract (M10).
- No nutrition. It is being deleted in M3; do not extend it.
- Telegram does: free-text activity log, weight log, ask a coach, `/next`, `/undo`, `/coach`. Profile, goals, stats and history live in the app.
- Every step ships with a test that runs with zero AI calls (the fakes in `tests/Fakes`) or an exact curl. Nothing without a test.
- Plain writing: no em-dashes, no bold-label lists, specific `file:line` evidence, honest about what is unverified.
- Never expose service ports. A previous VPS was cryptojacked through php-fpm:9000. Secrets only in env.

## Run

```bash
cp .env.example .env
make up
docker compose exec app composer install
docker compose exec app php artisan key:generate
make fresh
make test
make test-pg
```

API on http://localhost:8088. Local AI uses a free Msty model; see `.env.example`.

## Where things are

- Routes: `routes/api.php`. Controllers: `app/Http/Controllers/Api`. AI ports and adapters: `app/Contracts/Ai`, `app/Ai`. Interpreter (classify, parse, named rules): `app/Interpretation`. Log actions: `app/Actions/SmartLog`. Chat core and Telegram: `app/Channels`. Events and listeners: `app/Events`, `app/Listeners`. Coaches: `app/Enums/TrainerPersona.php`. Test fakes: `tests/Fakes`, bound in `tests/Pest.php` and `tests/TestCase.php`.
- Try the interpreter alone: `docker compose exec app php artisan log:parse "text"` (saves nothing). The whole chat path without a phone: `php artisan log:simulate --user=<id> "text"`.
- Classify step alone, per model: `php artisan classify:try "text"`, `make classify-eval`, or `/lab/classify` in a local browser. Laya runs locally with `make laya`; switch the app with `AI_CLASSIFIER=llm|laya|jev`.
- Current build order: `PLAN.md` section 5, "Next, in order". One branch only: `master`. Short feature branches merged and deleted the same session.
