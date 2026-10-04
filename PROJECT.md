<!--
  PROJECT.md — the cockpit for this repo. One file to open and know where things stand.
  Maintained by the `project-map` skill. The assessment (status/issues/roadmap) is
  refreshed each session; the Diary at the bottom only grows. App-code changes need a
  green light; the working branch is merged to only when sure.
-->

# Feetness (work-it-out) — cockpit

**What it is** · The real backend for Hiren's AI fitness app: log workouts/meals in plain language, three AI coaches parse it and react in character, and an RPG stat sheet grows from your logged data. API-only (no UI) — a Nuxt or Flutter frontend comes later. Goal behind it: help Hiren cut fat / build muscle at 105kg, and maybe become a product.
**This is the backend of record.** The `koala/ai-fitness-coaches-&-tracker` React+Express repo was a throwaway Google AI Studio visualization of the idea, not the plan. Build here.
**Stack** · Laravel 13 · PHP 8.3 · `laravel/ai` SDK (provider-agnostic) · Sanctum bearer auth · MySQL 8 · Redis (queue/cache) · Pest 4 tests · Docker (Sail-style compose) · Pint
**Goal right now** · Hiren wants to use it from the weekend of 2026-10-03 to gamify two weeks of fat loss and muscle gain. Realistic: the Telegram log loop running from the laptop (poll mode, no deploy) after M0, M1, M2.1 and a minimal M5+M6; that is 4 to 5 focused sittings, not one day.
**Status** · 🟡 M0 and M1 done: runs locally, the two SDK adapter fatals fixed, payload validated at the boundary, every AI call goes through `AiCall`, suite green (46 tests, 153 assertions), CI green, audit clean. Live call against a local model still unverified (Msty was not running). M2 next — well-architected (ports/adapters, enums, FormRequests, Resources, contract tests) with the full plan in `PLAN.md`. Not yet bulletproof: two probable fatals in the SDK adapters (unverified until `vendor/` is installed), the adherence bug on the AI-log path, the smart-log write isn't transactional, the app runs in UTC for a user in Amsterdam, several endpoints are untested, and the seeder can't exercise every endpoint. Not run locally in this checkout yet.
**Repo** · git@github.com:Hersh3yy/work-it-out.git · working on `master` (branch for changes; merge to `master` only when sure)
**Hosting** · designed for Coolify/VPS (see `.env.example` production block); nothing deployed yet. Heed the VAMS VPS lessons: never expose service ports, rotate keys.
**ClickUp** · not linked yet. Needs `CLICKUP_API_KEY` in the shell and a list id here as `<!-- clickup_list:ID -->`; then `node ~/.claude/skills/project-map/scripts/clickup-sync.mjs PROJECT.md`
**Last assessed** · 2026-10-04

---

## Start here (next session, any machine)

1. `git pull`. Read this file, then `PLAN.md`. The diary below says what happened last.
2. Get the stack up (commands under Run it). If `composer install` inside the container dies with exit 137, install on the host and `docker cp vendor/. work-it-out-app-1:/var/www/html/vendor/`.
3. `php artisan test --compact` must print 46 passed before touching anything.
4. First unticked milestone is M2 (PLAN.md section 5): extract `RecordSmartLog` and `RevertSmartLog`, fix adherence, same-day merge, exercise aliases, timezone, numbers as numbers, DiaryResource, login limiters. Branch `m2-write-path`. Every step has its test named in PLAN.md.
5. Before the first live AI call in dev: open Msty Studio, load `mlx-community/granite-3.3-2b-instruct-4bit`, check `curl -s localhost:11973/v1/models`. Then `make register-test`, grab the token, `POST /api/log` with free text. If Msty has no `/v1/responses`, switch `AI_DEFAULT_PROVIDER` to `openrouter` pointed at Msty (PLAN.md M1.2 note).
6. Work to the milestone gate, run `pint` and the suite, push the branch, wait for CI, fast-forward `master`, append a diary entry here, push.

## Run it

```bash
cd "/Users/hirenbudhrani/Documents/koala/work-it-out"
cp .env.example .env
make up                 # docker compose: app + mysql + redis + mailpit
docker compose exec app composer install
docker compose exec app php artisan key:generate
make fresh              # migrate:fresh --seed  (one demo user + ~2 weeks of data)
make test               # Pest, runs inside the container
```
API on http://localhost:8088, Mailpit on http://localhost:8025. Register a test user with `make register-test`. Local AI runs against a **free local model** (Msty MLX / Granite) via an OpenAI-compatible endpoint — you develop without spending a cent on tokens. Production swaps to `gemini-2.5-flash` with one env change (`AI_DEFAULT_PROVIDER=gemini`).

## Status

The core loop is built and mostly correct: register (Sanctum), fill a profile, log workouts/meals/body-weight either structured or as free text, get one AI call that parses the log plus writes three coach reactions, a diary line, and RPG deltas, then read it all back via dashboard/stats/diary endpoints. Auth and per-user data scoping are solid (no cross-user leakage found). The AI layer is cleanly abstracted behind ports with test fakes, so the whole app is testable and providers are swappable. What keeps it at yellow: the AI-log path marks every workout `completed_planned = false` so adherence always reads 0%; the smart-log write isn't wrapped in a transaction and can orphan rows on failure; Dashboard/Nutrition/BodyWeight/Diary/Profile-update have no tests; and the seeder leaves RPG/feedback/diary tables empty, so "simulate the whole backend from seed data" isn't yet possible. None of these are architecture problems — they're the finishing work to make it bulletproof.

## Issues

| Sev | Issue | Where |
|---|---|---|
| fixed in M1 (2026-10-03) | `SdkSmartLogParser` and `SdkPlanGenerator` called `->forUser()` and `->structured()` which do not exist on one-shot agents; now `->prompt()->toArray()` and `->prompt()`, covered by `SdkAdaptersTest` through the SDK's own agent fake | `app/Ai/SdkSmartLogParser.php:26`, `app/Ai/SdkPlanGenerator.php:33` |
| serious | App timezone is UTC while Hiren logs from Amsterdam: a 00:30 local log lands on yesterday, a Monday 00:30 log in last week, streak and adherence day boundaries shift | `config/app.php:68`, `Jobs/UpdateUserStats.php:82` |
| serious | Free-text logs fragment: two messages from one gym visit make two sessions, and "bench", "Bench Press", "benchpress" become three PRs | `SmartLogController.php:134-146`, `Services/Stats/PersonalRecordService.php:59` |
| serious | AI-logged workouts hardcode `completed_planned=false`; adherence only counts `true`, so logging via AI always shows 0% adherence | `SmartLogController.php:140`, `Jobs/UpdateUserStats.php` |
| serious | Smart-log write is not transactional; a mid-way failure leaves orphaned session/feedback rows | `SmartLogController.php:62-110` |
| serious | `DiaryEntry.content` can be null (`diary_text ?? summary`, both may be absent) → insert throws on a non-nullable column, uncaught and outside any transaction | `SmartLogController.php:105` |
| serious | No tests for Dashboard, Nutrition, BodyWeight, Diary, Profile update, or the smart-log meal/biometrics branches | `tests/Feature/*` |
| serious | Seeder can't exercise every endpoint: no custom_rpg_stats / activity_feedbacks / diary_entries seeded, no factories for them, only one user | `database/seeders/DatabaseSeeder.php` |
| warning | Dashboard fires 3 overlapping full-history session queries; PersonalRecords scans the user's entire workout history uncached on every call — slow for active users | `DashboardController.php:21-56`, `Services/Stats/PersonalRecordService.php` |
| warning | Missing composite `(user_id, logged_at)` indexes on the hot log tables | `workout_sessions`, `nutrition_logs`, `body_weight_logs` |
| warning | `training_days_per_week` not cast to int but compared with `=== 0`; inconsistent with ProfileIntakeService's `(int)` guard | `Jobs/UpdateUserStats.php:46` |
| warning | `/diary` returns a raw paginator and leaks stored `raw_message` to the client (no DiaryResource) | `DiaryController.php` |
| minor | No destroy route for body-weight logs | `routes/api.php` |
| minor | Dead scaffolding + no-op: `tests/**/ExampleTest.php`, `ProfileController::update` self-assign of password | `ProfileController.php:42` |
| minor | README is stock Laravel; no CLAUDE.md/AGENTS.md; `ruby-app-scaffold-prompt.md` is a stale spec (Inertia/Vue/2 coaches) the build has outgrown | repo root |

## Guide

API-only Laravel. Every route lives in `routes/api.php`, all authenticated ones behind `auth:sanctum`; the AI routes (`/log`, `/trainer/chat`, `/plans/*`) sit behind `throttle:trainer-chat` (20 req/user/hour, defined in `AppServiceProvider`). Requests are validated by FormRequests, responses shaped by API Resources (mostly).

The AI layer is the heart and is deliberately hexagonal:
- **Ports** (`app/Contracts/Ai/*`): `TrainerChat`, `SmartLogParser`, `PlanGenerator`, `NutritionParser`. Controllers depend only on these.
- **Adapters** (`app/Ai/Sdk*`): wrap the `laravel/ai` SDK. Bound to ports in `AppServiceProvider::register`.
- **Agents** (`app/Ai/Agents/*`): pure prompt-builders. `SmartLogAgent` is the big one — one structured call returns parse + three coach reactions + diary + RPG deltas.
- **Fakes** (`tests/Fakes/*`): swapped in for the ports so the whole flow is testable with zero AI calls.

The three coaches live in `app/Enums/TrainerPersona.php` (Strategy pattern — each case owns its full system prompt), and each has a defined job:
- **Lt. Surge** — drill-sergeant. Pushes you harder, holds you to goals. RPG: strength.
- **Shen** — friendly fit-bro raver. The "what to do next" coach: he reads your recent split and suggests the next session, giving 3-4 concrete options (e.g. after bench today + back/biceps yesterday: squats, deadlifts, abs, or cardio/mobility). RPG: stamina.
- **Latika** — yogi/nutritionist. Food, stretching, recovery, longevity. RPG: vitality.

Persona maps to one RPG core stat each. Real numbers come from `PersonalRecordService` (computes PRs from logged data, no LLM) and are injected into every prompt so coaches quote true figures, never invented ones. Note: Shen's next-move role needs his prompt in `TrainerPersona.php` and his log/chat context to actually include the last several days of training split by muscle group — today's context (`TrainerAgent::buildContext`, last 7 days of sessions + exercises) has the raw data but doesn't group it into "what you trained recently," so the suggestion logic is a build task, not just a prompt tweak.

Data model (all user-scoped, ULIDs on the log tables): `users` (profile + goals + adherence/streak + rpg_strength/stamina/vitality), `workout_sessions` → `exercise_entries`, `nutrition_logs`, `body_weight_logs`, `custom_rpg_stats`, `activity_feedbacks` (the three coach reactions per log, polymorphic to the logged thing), `diary_entries`, and the SDK's `agent_conversations` for chat memory. `UpdateUserStats` (queued job) recomputes adherence + streak after each workout.

## Hard parts

### The whole AI layer is swappable and free to test

🔭 **What it does** — Controllers never touch the AI SDK. They depend on four port interfaces; real adapters wrap `laravel/ai` in production, and in tests the container binds Fakes instead, so the full log-to-DB flow runs deterministically with zero tokens. Provider is one env var (`AI_DEFAULT_PROVIDER`) across 15 back-ends.

⚖️ **Why this way** — It solves the "AI is slow, costs money, and is non-deterministic" testing problem at the architecture level, and it means the choice of model/provider is a config change, not a code change.

🗣️ **Say it to a senior** — "The AI is behind ports with adapter + fake implementations, so the trainer, log parser, and plan generator are all unit-testable without a live model, and swapping Gemini for a local model is a one-line env change."

### One structured call does five jobs, and RPG uses deltas not snapshots

🔭 **What it does** — `SmartLogAgent` sends a single prompt with a JSON schema that returns the parsed activity, one reaction per coach, a diary sentence, and small integer RPG deltas (0–5). The server clamps and applies the deltas; it never asks the model to re-emit the whole stat sheet.

⚖️ **Why this way** — One call is far cheaper than four and gives every coach the same context to reference the actual log. Returning deltas instead of the full custom-stats array keeps output tokens flat as history grows (the mistake the React prototype made).

🗣️ **Say it to a senior** — "Logging is a single schema-constrained call returning parse + coach reactions + bounded RPG deltas, so cost is constant per log regardless of how much history the user has."

### Free local model in dev, cheap flash in prod

🔭 **What it does** — Local `.env` points the OpenAI driver at a Msty MLX endpoint running Granite locally, so every dev AI call is free and offline. Production flips to `gemini-2.5-flash`.

⚖️ **Why this way** — This is the real answer to "don't lose a billion dollars in AI costs": you never burn API budget while building, and production runs on a cheap flash tier behind a 20/hour rate limit.

🗣️ **Say it to a senior** — "Dev runs against a local Granite model via the OpenAI-compatible driver, so iteration costs nothing; prod is gemini-2.5-flash, rate-limited per user."

## Roadmap — near future

<!-- The full plan with acceptance tests per step is PLAN.md. Tick a milestone here when its release gate passes. Green light needed before any code change. -->

What the backend must do, in Hiren's words (2026-10-02), and where each lives:
- take logs and activities and turn them into an overall profile: exists (`POST /api/log`, RPG, PRs, dashboard); hardened in M2
- suggestions from history: Shen `/next` (rules in M5, model in M8)
- feedback: changed 2026-10-04. A log gets a receipt plus one question when a value is missing, no coach reactions (M2, M5). Coaches answer on request (M7). The AI never writes assumptions; the qualitative profile build runs only on request (M9)
- understand the user's high-level plan and ask questions when info is missing, capped per day: intake exists (one question per coach reply, `TrainerAgent::intakeRules`); the daily cap and short/long-term goals are new, item n12
- accept Telegram and support a frontend: M5/M6 and M10/M12

Weekend cut (minimum to log from the phone, laptop running, no deploy): M0, M1, M2.1, then M5.1 to M5.3 with only log, weight, `/next`, `/undo`, then M6.1 with `channel:telegram:poll`. Skip budget, hardening and deploy until after the weekend.

- [x] M0 Sync, green baseline, CI: suite green, SDK claims verified (fatals confirmed), GitHub Actions, deploy branch. Done 2026-10-03 <!-- id:n1 -->
- [x] M1 Day-one blockers: adapters fixed, payload validated and capped, model name per provider in `config/ai.php`, `AiCall` reports every failure. Done 2026-10-03 <!-- id:n2 -->
- [ ] M2 Facts-only write path. Done: test safety, facts-only parse with questions, `activity_logs`, `RecordSmartLog`, `RevertSmartLog`, answers on both doors, `StatSheet` port, chat core + Telegram polling, `log:simulate`, one model per job. Left: timezone Europe/Amsterdam, numbers as numbers everywhere, DiaryResource, login/register limiters, conversation ownership. RPG from rules parked <!-- id:n3 -->
- [ ] v0 deploy: the polling bot as one supervisord worker on a Coolify VPS, registration closed in production, host checklist, smoke from the phone (PLAN.md "v0 deploy") <!-- id:n13 -->
- [ ] M3 Remove nutrition entirely (decided 2026-09-27; waits until after v0), Latika rewritten to recovery/mobility/longevity <!-- id:n4 -->
- [ ] M4 Simulate: factories + deterministic two-user seeder, streak on read, HTTP scenario suite (PLAN.md section 6), prompting practices and a live eval set of real messages <!-- id:n5 -->
- [ ] M5 Channel port + the Telegram command set (log, weight, /next, /undo, /coach) + rules-only `/next` + one AI budget shared by HTTP and chat, driven by `channel:simulate`. Profile and stats stay in the app <!-- id:n6 -->
- [ ] M6 Telegram adapter, hardened ingest, host and Coolify checklist, backups, first deploy, first message from the phone. RELEASE 1 <!-- id:n7 -->
- [ ] M7 AI commands over chat: coach threads with memory, `/plan` <!-- id:n8 -->
- [ ] M8 Shen next-move with the model on top of the rules path; compact coach context <!-- id:n9 -->
- [ ] M9 Profile: intake gaps (`asked_field`, skip state, `GET /api/coaches`) and the profile build on explicit request (coach notes citing logs, local model allowed, never overwrites user fields) <!-- id:n10 -->
- [ ] M10 API contract: Resources everywhere, one envelope, idempotency keys, OpenAPI with drift test, exported fixtures. RELEASE 3 <!-- id:n11 -->
- [ ] Coach questions with a daily cap: the coach may ask at most N intake or goal questions per day (config), tracks what was asked, and captures short-term and long-term goals (new fields) so answers over chat persist. Lands with M7 or M9 <!-- id:n12 -->

## Roadmap — far future

- [ ] M11 Voice notes over Telegram behind a Transcriber port <!-- id:f1 -->
- [ ] M12 Client v1: Flutter (optimistic) or Nuxt 4 (fallback), built only from the contract and fixtures; decide after M10 has been stable two weeks <!-- id:f2 -->
- [ ] Multi-user linking (link codes, `/link`, `/unlink`), only when a named second person wants in <!-- id:f3 -->
- [ ] WhatsApp adapter (Meta Cloud API direct), not before Telegram has run two weeks <!-- id:f4 -->
- [ ] Proactive nudges from Lt. Surge on a missed day (scheduler + `ChatChannel::send`) <!-- id:f5 -->
- [ ] Perf: cache/aggregate PersonalRecords; watch integration; RAG over training material; per-user timezone; README + CLAUDE.md; package-currency pass <!-- id:f6 -->

---

## Diary

<!-- Newest first. One entry per working session. Terse, factual, honest. Append only. -->

### 2026-10-04 (late, 6) — observer yes, builder no, named rules, unknown input
- Hiren: Observer yes, Builder no, Specification renamed to named rules with a plain explanation; nonsense input must get a respectful reply listing what works. Verified today "rainbow butterfly" is stored as a general log ("Noted: rainbow butterfly"), undone. PLAN.md: `unknown` log type and reply, prompt practices in plain words (in place vs missing).

### 2026-10-04 (late, 5) — pattern map re-checked; interpreter test tool planned
- Hiren wants to tweak and test the interpreter alone. PLAN.md: `log:parse` dry-run command and the eval cases file (M4). Pattern table in PLAN.md section 3 rewritten against the real code (ports and adapters, strategy, actions as commands, facade, chain, memento, null object, template method; observer, builder and specification as next).

### 2026-10-04 (late, 4) — formulas are the product; scheduled check-in
- Hiren: formulas like Brzycki make the app stand out more than the AI; wants a scheduled outgoing message that enriches the profile; volunteered info must be interpreted too; asked for a flowier diagram style (drawn, ellipses and curves). All three recorded in PLAN.md section 4. Strength research notes with sources are in the chat log of this session only; the research spike before M4 writes them into `docs/`.

### 2026-10-04 (late, 3) — two sketches read, architecture drawn
- Both photos of Hiren's paper sketches were readable (rotated, no problem). Sketch 1: doors to one parser to "log to profile". Sketch 2: parsed message to history and, via interpretation, to the profile; goals on the profile; profile feeds coaches; reply out. Redrawn and corrected in chat; my high-level architecture drawn (doors, conversation, AI parser, plain-code checks, log, profile, coaches, reply).
- Clarified: "message" is an app widget door; "log to profile" means log plus interpretation; "90 x3 x5" was 3 reps 5 sets and must be asked; max two questions then editable history; profile as a clay blob with provenance per message. Written into PLAN.md section 4.

### 2026-10-04 (late, 2) — VAMS idea dropped; real messages; architecture first
- Hiren: work-it-out is its own backend, so no VAMS database; local Postgres in Docker for now; he wants to settle high-level architecture before more building. PLAN.md section 4 updated, v0 deploy waits on it.
- Ran three of his real gym messages through the parser (gemini-3.5-flash-lite). Findings recorded in PLAN.md "Architecture decisions still open": no per-set data model (a 90/90/95/85/85 squat became five entries), multi-message logs not connected (bare numbers became a 90 kg body weight), comments and plans dropped, "90 x3 x5" not questioned. All test rows undone.
- His screenshot of the message parser flow did not come through.

### 2026-10-04 (late) — persistence plan: the DO Postgres cluster
- Hiren asked to use VAMS's production database as Feetness's persistence. `VAMS/.env` holds a commented-out production block for a DigitalOcean managed Postgres (host `db-postgresql-ams3-40945-…`, port 25060, database `main`); VAMS dev runs a local Postgres and its `coolify` branch runs its own `postgres:16-alpine`, so VAMS is leaving the cluster. Verified read-only from this laptop: Postgres 17.11, `main` has 24 tables, the VAMS user cannot create databases, roles include `doadmin` (createdb) and `hiren-strapi`. Credentials were read inside the shell only and never printed.
- Decision written into PLAN.md section 4 and the v0 deploy milestone: Feetness gets its own database and user on that cluster, dev moves to Postgres, SQLite stays for tests, CI MySQL job becomes Postgres, the VPS holds no data. Human-only steps for Hiren listed in the milestone (create db and user in the DO panel, check price, confirm whether Strapi still uses the cluster, tighten trusted sources).
- Blocked by the permission classifier: scanning the other projects' `.env` files for the cluster host and admin credentials. Left as questions for Hiren.

### 2026-10-04 (night) — first live parse
- Hiren pasted a Gemini key. `AI_LOG_PROVIDER=gemini` in the local `.env`; dev chat and plan stay on Msty. First call failed with 404: "models/gemini-2.5-flash is no longer available to new users" (the model is still listed by the API, but new keys cannot call it). The SDK's own defaults are `gemini-3.5-flash` and `gemini-3.1-flash-lite`; the log job now runs `gemini-3.5-flash-lite`, config fallbacks and `.env.example` updated from 2.5 to 3.5.
- `log:simulate --user=43 "bench 3x8 80, then incline db 3x10"` → "Logged: Bench Press 3x8 @ 80 kg, incline db 3x10 / What weight was used for the incline db press?"; then "30" → "Updated: incline db 3x10 @ 30 kg" with no AI call. The whole loop is verified against a real model for the first time. "incline db" kept Hiren's spelling (no alias); the next log with the same key reuses it.
- Dev DB was empty after the earlier wipe; Hiren's user is id 43 (`channel:link` created it). MySQL auto-increment did not reset.

### 2026-10-04 (evening) — regroup: three doors, simulate first, v0 next
- Hiren: Telegram, WhatsApp and soon app-only users must all log the same way; he will store real messages and simulate gym time rather than test live now; gh is authenticated; asked about Gemini free limits and DeepSeek.
- CI was red on `pest-mysql` with all tests passing: `php artisan test` already adds `--configuration=phpunit.xml`, the second flag made Pest warn and exit 1. Makefile and CI now call `vendor/bin/pest --configuration=phpunit.mysql.xml`.
- Built: `POST /api/log/{log}/answer` and `/skip` (HTTP parity with chat; `ExerciseEntryResource` now returns `weight_kg` as a float), `LogSource::fromProvider`, `php artisan log:simulate {text|--file}` (exact chat path, provider `simulate`), `tests/Evals/messages.txt` + README as the safe place for real messages, `config/ai.php` `jobs` (log, plan, chat each with provider and model, `AI_*_PROVIDER`/`AI_*_MODEL`, DeepSeek via the SDK's native provider).
- PLAN.md: fast track order (finish M2, v0 deploy, M4, M5/M6, M3 after v0), the v0 deploy milestone defined, three-doors decision, model candidates with numbers. 102 passed on SQLite and MySQL.
- Next: the M2 correctness sitting (timezone, numbers, DiaryResource, limiters), then v0 deploy.

### 2026-10-04 (later) — gym loop v0 (branch m2-write-path)
- Hiren: RPG and profile build must be isolated (uncertain); real per-area stats are a later idea; nutrition not in this phase; goal is texting from the gym within two prompts, then a v0 deploy on Coolify.
- Built: `RecordSmartLog` (M2.2, earlier commit), `RevertSmartLog` + `DELETE /api/log/{log}`, `AnswerOpenQuestion` (a bare "30", "5k", "28 min" fills the asked field without AI, "skip" clears it, latest log only, 6 hours), `StatSheet` port with `RpgStatSheet` (dashboard, stats, UserResource read only through it; the log receipt has no stats), the chat core `App\Channels\HandleInboundMessage` (unlinked ignored, /start, /help, /undo, answers, free-text log), `ReplyComposer`, `ChatChannel` port with `TelegramChannel` and `TelegramClient` (errors carry status and description, never the token URL), `channel_identities` as the allowlist, `php artisan channel:link {email} {telegram_id}` (creates the user if new), `php artisan channel:telegram:poll` (long polling, offset in cache, prints the link command for an unknown sender).
- 90 passed on SQLite and on MySQL. Not verified: a live parse against a real model (no key or Msty here yet).

### 2026-10-04 — plan revised, M2 started (branch m2-write-path, work in progress, suite red)
- Decisions from Hiren, written into PLAN.md section 4 and the milestones: a log gets a receipt plus at most one question for a missing value, no coach feedback per log; the AI never writes assumptions (stated facts stored, every number computed in PHP); the qualitative profile build runs only on explicit request and may use the laptop's local model (M9); log parsing on a cheap hosted model, no model chosen yet; no own knowledge base for now; a prompting and live-eval step in M4. M2 5.5, M4 3.5, M9 2 sittings; backend about 33.
- Roadmap deck for a non-technical reader: https://claude.ai/artifact/96DpjoAmbXRoTALR3SYZDm, source in `docs/roadmap-deck/` (pre-revision, see its README).
- Found: in Docker the container's env vars beat `phpunit.xml`, so `make test` ran against the dev MySQL `work_it_out` and wiped it. Laravel reads `$_SERVER` first, so `phpunit.xml` now sets every value as `<env force>` and `<server force>`. `make test` is SQLite in memory again (46 passed in the container before the M2 code changes).
- Found: `activity_feedbacks.loggable_id` was an integer morph while workouts have ULIDs; on MySQL every AI-logged workout was a 500 (SQLite hid it). Added `phpunit.mysql.xml` (the `testing` database), `make test-mysql`, and a `pest-mysql` CI job.
- M2 done so far: migration `2026_10_04_000001` replaces `activity_feedbacks` with `activity_logs` (ULID, ULID morph, questions json, logged_on, source) and adds `activity_log_id` to `diary_entries` and `exercise_entries`; `ActivityLog` model, `LogSource` enum; `ActivityFeedback` deleted; `SmartLogAgent` facts-only (no user data sent, `logged_on`, `questions`); `SdkSmartLogParser` rebuilds the payload from known keys only; `ExerciseAliases` (key + alias map + the user's own earlier spelling).
- Not done, next in this order: `app/Actions/SmartLog/RecordSmartLog` + `SmartLogResult` (transaction, completed_planned true, same-day merge within 3 hours, aliases, logged_on within 7 days, clamps, stats job after commit) and rewrite `SmartLogController` to use it; adherence counts distinct days in `UpdateUserStats` (update its 50% test to two days); `PersonalRecordService` groups by `ExerciseAliases::key`; `FakeSmartLogParser` facts-only; rewrite `SmartLogContractTest` and `SdkAdaptersTest`, add `RecordSmartLogTest` and an `ExerciseAliasesTest`; then RpgSheet, RevertSmartLog, the correctness sitting. 4 tests red until RecordSmartLog lands; CI on this branch is expected to fail.
- Laptop setup notes: this laptop had a stale `vendor/` and no composer (installed with Homebrew); local `.env` APP_NAME set to Feetness. If `make test-mysql` says access denied on `testing`, the MySQL volume predates `docker/mysql/init.sql`: run `docker compose exec -T mysql mysql -uroot -proot_password < docker/mysql/init.sql` once.

### 2026-10-03 — M1 done (branch m1-adapters)
- `SdkSmartLogParser`: `->prompt($message)->toArray()`, then validation at the boundary: `log_type` in the enum and a non-empty `summary` or `AiUnavailable`; summary 255, coach lines 600, diary 1000, stat name 60, reason 255, stat category whitelisted. `SdkPlanGenerator`: `->prompt()` without `forUser()`. `SmartLogAgent` no longer sends the user's name.
- `config/ai.php`: dead top-level `model` removed; `models.text.default` and `cheapest` on the openai and gemini providers read `AI_TEXT_MODEL` (the SDK reads `providers.*.models.text.*`, verified in `GeminiProvider.php:95` and `OpenAiProvider.php:93`); `conversations.generate_title` false so a new thread is one call. `APP_TRAINER_DEFAULT_PERSONA` removed from `.env.example` (never read).
- `App\Services\Ai\AiCall::run(User, string $agent, Closure)`: `report()` plus one `logger()->error('AI call failed', [user_id, provider, agent, error])` line, never the prompt text; rethrows `AiUnavailable`. The three controllers use it; the chat 503 now carries `coach`.
- Tests: `SdkAdaptersTest` (real adapters through `Ai::fakeAgent`: workout persists, plan generates with zero conversations, junk payload is a 503 with zero rows, 300-char stat name stored at 60), `AiOutageLoggingTest`, `AiConfigTest`. The two tests that hit the network (`TrainerAgentTest`, `NutritionParserServiceTest`) now use a throwing fake. 46 passed, 153 assertions.
- Not done: a live call against Msty (not running on this machine). First thing next session, see Start here.

### 2026-10-03 — M0 done (branch m0-baseline)
- Green light from Hiren: work to a milestone, then push. 45 minutes.
- Ran it: Docker stack up (app, mysql, redis, mailpit), `composer install` in the container was OOM-killed (exit 137), so vendor was installed on the host with Herd PHP 8.5 and copied into the container's `vendor-data` volume with `docker cp`. `migrate:fresh --seed` ok. API on :8088. Container runs PHP 8.4.26, Laravel 13.34.
- `php artisan test` on the host (sqlite): 39 passed, 131 assertions, all green. Note: green only because every AI port is faked.
- Vendor findings: (a) `forUser()` lives only in `Laravel\Ai\Concerns\RemembersConversations`; `SmartLogAgent` and `PlanAgent` do not use it, so `SdkSmartLogParser.php:26` and `SdkPlanGenerator.php:33` fatal. No `structured()` method anywhere; use `->prompt()->toArray()` like `NutritionParserService`. (b) `fakeAgent(string $agent, Closure|array $responses = [])` on `InteractsWithFakeAgents`, returns a `FakeTextGateway`; arrays and closures both accepted. (c) Gemini store and file gateways send the key as header `x-goog-api-key`, not in the URL; the text gateway was not read, verify in M6 before trusting the log scrubber.
- Msty: nothing listening on localhost:11973. Hiren must open Msty Studio and load the Granite model before any live AI call in dev.
- M0.3: `.github/workflows/test.yml` (composer install, audit, pint, pest on PHP 8.4), `tests/Unit` now bound to the Laravel TestCase, the two ExampleTests replaced by `tests/Unit/PlumbingTest.php`. Pint had 54 files out of style; formatted (whitespace and `declare(strict_types=1)` only), suite still green. `composer audit` found 25 advisories in 5 packages (guzzle, psr7, framework 13.15, commonmark, flysystem); updated them (framework 13.15 to 13.34), audit now clean, suite green.
- Deploy branch created from master so Coolify never deploys on every push.
- Not done: ClickUp (no key, no list). Msty live call. M1 is next.

### 2026-10-03 — handoff to the other laptop
- Everything pushed: `master` at this commit, tree clean. Pick up with `git pull`, then read `CLAUDE.md`, this file, `PLAN.md`. First work item is M0 (n1): `make up`, `composer install`, `make fresh`, `make test`, verify the three SDK claims, record the red list here.
- Still open from Hiren: green light to start M0; `CLICKUP_API_KEY` and a ClickUp list id for the sync; answers to the open questions in `PLAN.md` section 8 (coach reply length, streak definition, Shen buckets, Gemini billing, voice before or after the app, Android or iOS).
- No code changed.

### 2026-10-02 — map update before the weekend
- Hiren wants to use it from the weekend: gamify two weeks of fat loss and muscle gain. Restated the five backend duties and mapped each to the plan; added n12 (coach questions capped per day, short and long-term goals persisted).
- Wrote the honest weekend cut: M0, M1, M2.1, minimal M5 and M6 in poll mode from the laptop. Four to five sittings. Not one day.
- Repo check: `master` equals `origin/master` at 8ff7ec4 (docs commit from 2026-09-27). No code pushed from the other machine since.
- ClickUp not synced: no `CLICKUP_API_KEY` in this shell and no list for this project yet. Script and convention documented in the ClickUp line above.
- No code changed. Awaiting green light to start M0.

### 2026-09-25 to 27 — planning sprint (no code)
- Hiren set the rules: no coding for two days, plan and simulate; backend is the priority; the backend must be usable solely through a chat channel (Telegram is a legitimate first choice); Flutter is the optimistic client, Nuxt 4 the fallback; always design patterns, one UI library, atomic design; the React mock is Google-generated and is NOT direction (Hiren hates React); nutrition likely out of v1 (delete vs hide still his call); watch integration and RAG material are later.
- Ran a 22-agent workflow: six journeys traced through the code, judged designs for the channel (Telegram won over Meta and Twilio), Shen (rules plus LLM won over pure LLM), the client stack (atomic-first Flutter won, with a Nuxt fallback that keeps about 60 percent of the plan), a planner, three critics, a reviser.
- New findings from the journeys: two probable fatals in the SDK adapters (`->forUser()`/`->structured()` on non-conversational agents), UTC timezone for an Amsterdam user, session and exercise-name fragmentation on free text, the php-fpm `zz-docker.conf` glob-order trap that would re-create the VAMS exposure, `update_id` (not `message_id`) as the Telegram idempotency key, `pcntl` missing so job timeouts are silently unenforced, bot token leaking through Guzzle exception messages, no backups anywhere.
- Wrote `PLAN.md`, one file: where we are and what we have vs plan, the end-user description, architecture with mermaid diagrams, the pattern map, M0 to M12 with a test per step, the five simulation scenarios, risks, open questions. Five separate design docs were consolidated into it at Hiren's request. Decisions applied: no nutrition (delete), Telegram only logs activities and asks coaches, profile and stats live in the app.
- Published the architecture page (two doors, one core; SmartLogController before/after; one Telegram message's lifecycle; refactoring.guru pattern map; hard parts; five practice drills): https://claude.ai/artifact/7ZWNCtkfJeniTZsQQ94D46
- Installed the `visual-explainer` slash command and built a local variant on `mflux` (FLUX.1-schnell, on invoke, no daemon); not yet run, first run downloads about 30 GB, awaiting Hiren's go.
- No code changed. `vendor/` still absent; `make test` still unrun.

### 2026-09-18 — first assessment (renew)
- Established that `work-it-out` (app name **Feetness**) is the backend of record; the React+Express `ai-fitness-coaches-&-tracker` repo is a throwaway AI Studio viz. Wrote that repo's PROJECT.md too, pointing here.
- Read the whole AI layer (enums/contracts/adapters/agents/provider) directly; ran an audit agent over data model, controllers, services, tests, seeders.
- Found the backend is much more mature than the prototype: ports/adapters + fakes, Sanctum + solid per-user scoping, 20/hr AI throttle, single structured log call with bounded RPG deltas, free local-model dev loop, prod on gemini-2.5-flash. The user's "don't lose a billion dollars" worry is already largely designed for.
- Coaches already refactored from the vibe-based marine/raver/yogi to Lt. Surge / Shen / Latika. Coach roles now settled by Hiren: Surge = push/accountability, Latika = food/mobility/longevity, and **Shen = the "what to do next" coach** — friendly fit-bro raver who reads your recent split and suggests the next session with 3-4 options. That's a build task (prompt + muscle-group grouping in context), captured in n6.
- Confirmed local `work-it-out` is up to date: `master` == `origin/master` at c776905, 0 ahead/behind. Nothing to pull.
- Top real bug: AI-logged workouts never count toward adherence (completed_planned=false). Plus smart-log isn't transactional and diary content can be null. Test + seed gaps block "simulate the whole backend."
- No code changed (no green light). Not run locally (`vendor/` absent). `make test` result unverified.
- Stack ordering confirmed with Hiren: fix + bulletproof + simulate backend first, then Nuxt or Flutter frontend. API-first design already supports either.
