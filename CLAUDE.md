# Feetness (work-it-out): read this first

You are working in Hiren's fitness-coach backend. Before doing anything:

1. Read `PROJECT.md` (the cockpit: status, run steps, issues, guide, hard parts, roadmap, diary).
2. Read `PLAN.md` (the plan: where we are, end-user description, architecture, milestones M0 to M12 each with a test, simulation scenarios, decisions, open questions).
3. Check the diary at the bottom of `PROJECT.md` for the last session, then continue from the first unticked roadmap item.

## Rules

- No app-code changes without Hiren's explicit green light. Assess, plan and write docs freely; ask before touching `.php`, config, migrations, Docker files.
- Work on a branch. Merge to `master` only when the milestone's release gate in `PLAN.md` passes and the suite is green.
- Backend first. Telegram is the main door. The app is the sibling repository `feetness-app` (Nuxt 4 PWA, reads only for now) and signs in with the `/login` link; it only consumes the API's Resources, so the API stays the contract.
- No nutrition. It is being deleted in M3; do not extend it.
- Telegram does: free-text activity log, weight log, limitations ("hurt my back"), answers to open questions, `/undo`, `/me`, `/login`, `/better`; later `/next` and `/coach`. Profile, goals, stats and history live in the app.
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
- Telegram: `php artisan channel:telegram:setup` once, `php artisan channel:invite email` per person (one-time link), `make bot` to run the poller.
- Try the interpreter alone: `docker compose exec app php artisan log:parse "text"` (saves nothing). The whole chat path without a phone: `php artisan log:simulate --user=<id> "text"`.
- Intent classifier alone (the current focus): `php artisan intent:try "text"`, the visual explainer in `~/Herd/testbed` (`/classifier`), `/api/lab/intent/explain`, options in `app/Enums/Intent.php`, English only.
- Classify step alone, per model: `php artisan classify:try "text"`, `make classify-eval`, or `/lab/classify` in a local browser. Laya runs locally with `make laya`; switch the app with `AI_CLASSIFIER=llm|laya|jev`.
- Written profile: `app/Profile/UpdateProfile.php` (rolling notes with citations), `BuildProfile` job, `profile_versions`. Read it with `php artisan profile:show email`, rewrite now with `php artisan profile:rebuild email [--full]`, `/me` in Telegram.
- Limitations: `app/Limitations` (`RedFlags` runs before any AI, `ExerciseLoads`, `LimitationCheck`), `app/Actions/Limitations`, the `LimitationParser` port with `SdkLimitationParser` and `LimitationAgent`. Open-question answers without AI: `app/Conversation/Answers`, handler order in `config/conversation.php`. Sign-in links: `app/Actions/Auth`, `POST /api/auth/magic`. App reads: `OverviewController` (`GET /api/me/overview`, `GET /api/logs`).
- The app lives in its own repository beside this one, `~/Code/feetness/feetness-app` (Nuxt 4, Tailwind 4, atoms/molecules/organisms/templates, icons in `public/sprite.svg`), deployed on Netlify; run and deploy steps in its README. It only reads this API.
- Current build order: `PLAN.md` section 5, "Next, in order". One branch only: `master`. Short feature branches merged and deleted the same session.
