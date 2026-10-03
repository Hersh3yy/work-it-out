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
**Status** · 🟡 planned, not yet hardened — well-architected (ports/adapters, enums, FormRequests, Resources, contract tests) with the full plan in `PLAN.md`. Not yet bulletproof: two probable fatals in the SDK adapters (unverified until `vendor/` is installed), the adherence bug on the AI-log path, the smart-log write isn't transactional, the app runs in UTC for a user in Amsterdam, several endpoints are untested, and the seeder can't exercise every endpoint. Not run locally in this checkout yet.
**Repo** · git@github.com:Hersh3yy/work-it-out.git · working on `master` (branch for changes; merge to `master` only when sure)
**Hosting** · designed for Coolify/VPS (see `.env.example` production block); nothing deployed yet. Heed the VAMS VPS lessons: never expose service ports, rotate keys.
**ClickUp** · not linked yet. Needs `CLICKUP_API_KEY` in the shell and a list id here as `<!-- clickup_list:ID -->`; then `node ~/.claude/skills/project-map/scripts/clickup-sync.mjs PROJECT.md`
**Last assessed** · 2026-10-03

---

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
| critical (unverified) | `SdkSmartLogParser.php:26` calls `->forUser()->prompt()->structured()` and `SdkPlanGenerator.php:33` calls `->forUser()` on agents that are not Conversational; `NutritionParserService.php:28-36` uses the working form (`->prompt()->toArray()`). If confirmed, every AI log and plan call fatals today | `app/Ai/SdkSmartLogParser.php:26`, `app/Ai/SdkPlanGenerator.php:33` |
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
- feedback: exists (three coach reactions per log); over Telegram from M6
- understand the user's high-level plan and ask questions when info is missing, capped per day: intake exists (one question per coach reply, `TrainerAgent::intakeRules`); the daily cap and short/long-term goals are new, item n12
- accept Telegram and support a frontend: M5/M6 and M10/M12

Weekend cut (minimum to log from the phone, laptop running, no deploy): M0, M1, M2.1, then M5.1 to M5.3 with only log, weight, `/next`, `/undo`, then M6.1 with `channel:telegram:poll`. Skip budget, hardening and deploy until after the weekend.

- [ ] M0 Sync, green baseline, CI: push from the personal computer, pull here, `make test`, verify the three SDK claims, GitHub Actions, deploy branch <!-- id:n1 -->
- [ ] M1 Day-one blockers: fix the two SDK adapter fatals, validate and cap the structured payload, model name reaches the provider, one place reports AI failures <!-- id:n2 -->
- [ ] M2 Bulletproof the write path: `RecordSmartLog` + `RevertSmartLog` in transactions, adherence fix, null diary, same-day merge, exercise aliases, timezone Europe/Amsterdam, numbers as numbers, DiaryResource, login/register limiters <!-- id:n3 -->
- [ ] M3 Remove nutrition entirely (decided 2026-09-27), Latika rewritten to recovery/mobility/longevity <!-- id:n4 -->
- [ ] M4 Simulate: factories + deterministic two-user seeder, streak on read, HTTP scenario suite (PLAN.md section 6) <!-- id:n5 -->
- [ ] M5 Channel port + the Telegram command set (log, weight, /next, /undo, /coach) + rules-only `/next` + one AI budget shared by HTTP and chat, driven by `channel:simulate`. Profile and stats stay in the app <!-- id:n6 -->
- [ ] M6 Telegram adapter, hardened ingest, host and Coolify checklist, backups, first deploy, first message from the phone. RELEASE 1 <!-- id:n7 -->
- [ ] M7 AI commands over chat: coach threads with memory, `/plan` <!-- id:n8 -->
- [ ] M8 Shen next-move with the model on top of the rules path; compact coach context <!-- id:n9 -->
- [ ] M9 Intake gaps for the contract: `asked_field`, skip state, `GET /api/coaches` <!-- id:n10 -->
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
