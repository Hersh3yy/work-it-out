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
- Telegram does: free-text activity log, weight log, limitations ("hurt my back") and how they are going, answers to open questions, coach questions ("30 min, 3 things, go"), `/undo`, `/me`, `/login`, `/better`; later `/next`. Profile, goals, stats and history live in the app.
- Every step ships with a test that runs with zero AI calls (the fakes in `tests/Fakes`) or an exact curl. Nothing without a test.
- Plain writing: no em-dashes, no bold-label lists, specific `file:line` evidence, honest about what is unverified.
- Code reads without comments. Every piece sits where its pattern says (ports and adapters for AI, actions for writes, observers for side effects, chains for answers, commands for slash commands, named rules, strategies for choices); names say what things are; short methods; comments only say why. Run the `clean-code-check` skill (`.claude/skills/clean-code-check/SKILL.md`) on the diff before every commit.
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
- Written profile: `app/Profile/UpdateProfile.php` (rolling notes with citations), sections in `app/Profile/NoteLabel.php` (the writer picks one per note), `BuildProfile` job, `profile_versions`. Read it with `php artisan profile:show email`, rewrite now with `php artisan profile:rebuild email [--full]`, `/me` in Telegram.
- Limitations: `app/Limitations` (`RedFlags` runs before any AI, `ExerciseLoads`, `LimitationCheck`), `app/Actions/Limitations`, the `LimitationParser` port with `SdkLimitationParser` and `LimitationAgent`. Open-question answers without AI: `app/Conversation/Answers`; slash commands: `app/Conversation/Commands`; both listed in `config/conversation.php`. Sign-in links: `app/Actions/Auth`, `POST /api/auth/magic`. App reads: `OverviewController` (`GET /api/me/overview`, `GET /api/logs`).
- Reading the code yourself: `docs/code-tour.md` follows one message through the files in order.
- When the AI is down: `App\Ai\ModelChain` tries each model in a job's chain (`AI_FALLBACKS`); if all fail the message is kept (`waiting_messages`, `App\Actions\Conversation\KeepMessage`) and `App\Jobs\ProcessWaitingMessage` retries it and answers in the chat. The AI path for free text is `App\Conversation\RespondToText`.
- AI usage: every model call lands in `ai_calls` (`RecordAiCall` on the SDK's events, tagged by `AiCall`); `php artisan ai:usage --since=30d --by=user|job|model|day`; prices and the monthly cap in `config/ai.php`.
- Coach: `app/Coach` (`CoachContext` is everything the coach may know, `AnswerDepth` sizes answers, `App\Services\Exercises\ExerciseLine` words a saved exercise the same way in receipts, the coach context and the profile writer), `app/Actions/Coach/AnswerCoachQuestion.php`, the `CoachAdvisor` port with `SdkCoachAdvisor` and `CoachAgent`, limits in `config/coach.php`. Alpha steps for Hiren: `docs/alpha-launch.md`.
- The app lives in its own repository beside this one, `~/Code/feetness/feetness-app` (Nuxt 4, Tailwind 4, atoms/molecules/organisms/templates, icons in `public/sprite.svg`), deployed on Netlify; run and deploy steps in its README. It only reads this API.
- Current build order: `PLAN.md` section 5, "Next, in order". One branch only: `master`. Short feature branches merged and deleted the same session.
