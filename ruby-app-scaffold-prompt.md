# Scaffold Prompt: Ruby's Total-Motivation Fitness App
### Laravel 11 · Inertia.js · Vue 3 · Laravel Sail · MySQL

---

## YOUR ROLE

You are an expert Laravel developer. Your job is to scaffold a complete, production-ready Laravel 11 application using Inertia.js and Vue 3 as the frontend layer. Follow all instructions exactly. Do not skip sections. Do not add unrequested features.

---

## PROJECT OVERVIEW

Build a fitness tracking web app called **"TrainWithRuby"** (working title). Users log workouts and nutrition via a clean UI, then ask their AI trainer for feedback. The AI trainer has a personality the user selects: **The General** (drill sergeant, no-nonsense) or **The Coach** (supportive, science-based). The trainer has access to the user's full history and goals, and responds in character.

This is a **single-user-per-account** app. No teams, no sharing. Every piece of data is scoped to the authenticated user.

---

## TECH STACK

- **Backend:** Laravel 11 (latest stable)
- **Frontend:** Inertia.js v2 + Vue 3 (Composition API, `<script setup>`)
- **Styling:** Tailwind CSS v3
- **Database:** MySQL 8 (via Laravel Sail)
- **Local dev:** Laravel Sail (Docker)
- **Auth:** Laravel Breeze (Inertia + Vue stack)
- **AI:** OpenAI PHP SDK (`openai-php/client`)
- **Queue:** Laravel queues with database driver (for async AI calls)
- **Testing:** Pest PHP

Do NOT use Livewire. Do NOT use Blade templates for any page — all pages are Inertia/Vue. Blade is only for the root `app.blade.php` layout.

---

## ENVIRONMENT & SAIL SETUP

Generate a `docker-compose.yml` override and `.env.example` configured for Laravel Sail with:
- PHP 8.3
- MySQL 8
- Redis (for queue/cache)
- Mailpit (for local email)

The `.env.example` must include these keys (empty values, documented with comments):
```
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o
OPENAI_MAX_TOKENS=1000

APP_TRAINER_DEFAULT_PERSONA=general
```

---

## DATABASE SCHEMA

Generate migrations in this exact order. Use snake_case for all column names.

### 1. `users` table (extend the default)
Add these columns to the standard Laravel users migration:
```
trainer_persona         ENUM('general', 'coach')    DEFAULT 'general'
experience_level        ENUM('beginner','intermediate','advanced') DEFAULT 'beginner'
training_days_per_week  TINYINT UNSIGNED            DEFAULT 3
primary_goal            ENUM('build_muscle','lose_fat','improve_endurance','general_fitness') DEFAULT 'general_fitness'
goal_description        TEXT NULLABLE
goal_deadline           DATE NULLABLE
target_weight_kg        DECIMAL(5,2) NULLABLE
current_weight_kg       DECIMAL(5,2) NULLABLE
weekly_adherence_rate   DECIMAL(4,2) DEFAULT 0.00   -- computed, cached
current_streak_days     SMALLINT UNSIGNED DEFAULT 0 -- computed, cached
last_active_at          TIMESTAMP NULLABLE
```

### 2. `workout_sessions` table
```
id                  ULID primary key
user_id             FK → users
logged_at           TIMESTAMP (when the session happened, not created_at)
duration_minutes    SMALLINT UNSIGNED NULLABLE
perceived_exertion  TINYINT UNSIGNED NULLABLE  -- RPE 1-10
energy_level        TINYINT UNSIGNED NULLABLE  -- 1-5, pre-workout energy
notes               TEXT NULLABLE              -- "tweaked shoulder", "PR day"
completed_planned   BOOLEAN DEFAULT TRUE       -- did they hit their planned session?
created_at / updated_at
```

### 3. `exercise_entries` table
```
id                  ULID primary key
workout_session_id  FK → workout_sessions
exercise_name       VARCHAR(100)
sets                TINYINT UNSIGNED NULLABLE
reps                TINYINT UNSIGNED NULLABLE
weight_kg           DECIMAL(6,2) NULLABLE
duration_seconds    INT UNSIGNED NULLABLE      -- for cardio/timed exercises
distance_meters     INT UNSIGNED NULLABLE      -- for running etc
notes               TEXT NULLABLE
sort_order          TINYINT UNSIGNED DEFAULT 0
created_at / updated_at
```

### 4. `nutrition_logs` table
```
id              ULID primary key
user_id         FK → users
logged_at       TIMESTAMP
meal_type       ENUM('breakfast','lunch','dinner','snack','supplement')
food_name       VARCHAR(150)
calories        SMALLINT UNSIGNED NULLABLE
protein_g       DECIMAL(5,1) NULLABLE
carbs_g         DECIMAL(5,1) NULLABLE
fat_g           DECIMAL(5,1) NULLABLE
notes           TEXT NULLABLE
created_at / updated_at
```

### 5. `body_weight_logs` table
```
id          ULID primary key
user_id     FK → users
logged_at   DATE
weight_kg   DECIMAL(5,2)
notes       TEXT NULLABLE
created_at / updated_at
```

### 6. `ai_conversations` table
```
id              ULID primary key
user_id         FK → users
persona_used    ENUM('general', 'coach')
user_message    TEXT
ai_response     TEXT
context_snapshot JSON NULLABLE   -- the history/profile snapshot sent to OpenAI
tokens_used     SMALLINT UNSIGNED NULLABLE
created_at      TIMESTAMP
```

All foreign keys should CASCADE on delete. Use `->after()` ordering in migrations for readability.

---

## MODELS & RELATIONSHIPS

Generate Eloquent models with full docblocks, casts, and relationships:

**User** model additions:
- `hasMany(WorkoutSession::class)`
- `hasMany(NutritionLog::class)`
- `hasMany(BodyWeightLog::class)`
- `hasMany(AiConversation::class)`
- Cast `trainer_persona`, `experience_level`, `primary_goal` as enums (create PHP-backed enums for each)
- Accessor: `recentWorkouts()` — last 7 days of sessions with exercise entries, eager loaded
- Accessor: `weeklyStats()` — returns array: sessions this week, adherence rate, total volume (sum of sets×reps×weight)

**WorkoutSession** model:
- `belongsTo(User::class)`
- `hasMany(ExerciseEntry::class)`
- Scope: `scopeThisWeek($query)`, `scopeLastNDays($query, int $days)`
- Accessor: `totalVolumeKg` — sum of (sets × reps × weight_kg) across exercise entries

**ExerciseEntry**: `belongsTo(WorkoutSession::class)`

**NutritionLog**: `belongsTo(User::class)`, scope `scopeToday`, `scopeThisWeek`

**BodyWeightLog**: `belongsTo(User::class)`

**AiConversation**: `belongsTo(User::class)`

Create PHP-backed enums in `app/Enums/`:
- `TrainerPersona` (General, Coach) — include a `systemPrompt(): string` method that returns the full system prompt for each persona (see AI TRAINER section below)
- `ExperienceLevel`
- `PrimaryGoal`
- `MealType`

---

## ROUTES

All routes in `routes/web.php` using the Inertia pattern. Group authenticated routes under `auth` middleware.

```
GET  /                          → redirect to /dashboard if authed, else to /login
GET  /dashboard                 → Dashboard@index
GET  /workouts                  → WorkoutSession@index
GET  /workouts/log              → WorkoutSession@create
POST /workouts                  → WorkoutSession@store
GET  /workouts/{session}        → WorkoutSession@show
DELETE /workouts/{session}      → WorkoutSession@destroy

GET  /nutrition                 → NutritionLog@index
POST /nutrition                 → NutritionLog@store
DELETE /nutrition/{log}         → NutritionLog@destroy

GET  /body                      → BodyWeight@index
POST /body                      → BodyWeight@store

GET  /trainer                   → AiTrainer@index
POST /trainer/chat              → AiTrainer@chat

GET  /profile                   → Profile@edit
PUT  /profile                   → Profile@update
PUT  /profile/trainer           → Profile@updateTrainer (persona, goals, preferences)
```

---

## CONTROLLERS

### `DashboardController`
Returns Inertia page `Dashboard` with props:
- `weekStats`: sessions this week, adherence rate, streak
- `recentSessions`: last 5 workout sessions with exercise entries
- `todayNutrition`: today's nutrition logs with macro totals
- `currentWeight`: latest body weight entry

### `WorkoutSessionController`
- `index`: paginated list of sessions (15/page), with aggregate stats per session
- `create`: return empty Inertia form page
- `store`: validate, create session + nested exercise entries in a transaction. After saving, dispatch `UpdateUserStats` job. Return redirect to `/workouts/{session}`.
- `show`: session detail with all exercise entries
- `destroy`: soft delete (add `SoftDeletes` trait)

**Store validation rules:**
```php
'logged_at'             => 'required|date|before_or_equal:now',
'duration_minutes'      => 'nullable|integer|min:1|max:600',
'perceived_exertion'    => 'nullable|integer|min:1|max:10',
'energy_level'          => 'nullable|integer|min:1|max:5',
'notes'                 => 'nullable|string|max:500',
'completed_planned'     => 'boolean',
'exercises'             => 'required|array|min:1',
'exercises.*.exercise_name' => 'required|string|max:100',
'exercises.*.sets'      => 'nullable|integer|min:1|max:100',
'exercises.*.reps'      => 'nullable|integer|min:1|max:1000',
'exercises.*.weight_kg' => 'nullable|numeric|min:0|max:1000',
'exercises.*.duration_seconds' => 'nullable|integer|min:1',
'exercises.*.distance_meters'  => 'nullable|integer|min:1',
'exercises.*.notes'     => 'nullable|string|max:200',
```

### `NutritionLogController`
- `index`: today's logs grouped by meal_type, with daily totals. Also show last 7 days summary.
- `store`: validate and save. Also supports AI parsing — if `raw_text` is provided instead of structured fields, call `NutritionParserService` to extract macros, then save.
- `destroy`: delete entry

### `BodyWeightController`
- `index`: all entries paginated + chart-ready data (date, weight_kg array for the last 90 days)
- `store`: validate and save, update `users.current_weight_kg`

### `AiTrainerController`
- `index`: Inertia chat page, load last 20 conversations for this user
- `chat`: 
  1. Validate `message` (required, string, max:1000)
  2. Dispatch `ProcessTrainerChat` job (async via queue)
  3. Return `['status' => 'processing', 'conversation_id' => $id]`
  
  **OR** for simplicity on first iteration, do it synchronously and return the response directly. Add a TODO comment to make async later.

### `ProfileController`
- `edit`: return Inertia profile page with all user fields
- `update`: name, email, password
- `updateTrainer`: trainer_persona, experience_level, training_days_per_week, primary_goal, goal_description, goal_deadline, target_weight_kg

---

## SERVICES

### `app/Services/AiTrainerService.php`

This is the core of the app. Full implementation required.

```php
class AiTrainerService
{
    public function __construct(
        private readonly \OpenAI\Client $openai
    ) {}

    public function chat(User $user, string $message): string
    {
        $context = $this->buildContext($user);
        $systemPrompt = $this->buildSystemPrompt($user, $context);
        
        $response = $this->openai->chat()->create([
            'model' => config('services.openai.model', 'gpt-4o'),
            'max_tokens' => 1000,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $message],
            ],
        ]);

        $aiResponse = $response->choices[0]->message->content;

        // Persist conversation
        AiConversation::create([
            'user_id'          => $user->id,
            'persona_used'     => $user->trainer_persona,
            'user_message'     => $message,
            'ai_response'      => $aiResponse,
            'context_snapshot' => $context,
            'tokens_used'      => $response->usage->totalTokens,
        ]);

        return $aiResponse;
    }

    private function buildContext(User $user): array
    {
        // Last 7 days of workout sessions
        $recentSessions = $user->workoutSessions()
            ->with('exerciseEntries')
            ->thisWeek()
            ->get()
            ->map(fn($s) => [
                'date'       => $s->logged_at->toDateString(),
                'duration'   => $s->duration_minutes,
                'rpe'        => $s->perceived_exertion,
                'energy'     => $s->energy_level,
                'volume_kg'  => $s->totalVolumeKg,
                'exercises'  => $s->exerciseEntries->map(fn($e) => [
                    'name'   => $e->exercise_name,
                    'sets'   => $e->sets,
                    'reps'   => $e->reps,
                    'weight' => $e->weight_kg,
                ])->toArray(),
                'completed_planned' => $s->completed_planned,
                'notes'      => $s->notes,
            ]);

        // Today's nutrition
        $todayNutrition = $user->nutritionLogs()
            ->today()
            ->get()
            ->groupBy('meal_type');

        return [
            'profile' => [
                'name'                   => $user->name,
                'experience_level'       => $user->experience_level,
                'primary_goal'           => $user->primary_goal,
                'goal_description'       => $user->goal_description,
                'goal_deadline'          => $user->goal_deadline?->toDateString(),
                'target_weight_kg'       => $user->target_weight_kg,
                'current_weight_kg'      => $user->current_weight_kg,
                'training_days_per_week' => $user->training_days_per_week,
            ],
            'stats' => [
                'weekly_adherence_rate' => $user->weekly_adherence_rate,
                'current_streak_days'   => $user->current_streak_days,
                'last_active_at'        => $user->last_active_at?->toDateString(),
            ],
            'recent_sessions'  => $recentSessions,
            'today_nutrition'  => $todayNutrition,
        ];
    }

    private function buildSystemPrompt(User $user, array $context): string
    {
        $basePersona = $user->trainer_persona->systemPrompt();
        $contextJson = json_encode($context, JSON_PRETTY_PRINT);

        return <<<PROMPT
{$basePersona}

---

Here is the user's current data. Use this to make your responses specific, personal, and data-driven. Reference actual numbers when relevant. Never make up data that isn't here.

USER CONTEXT:
{$contextJson}

---

RULES:
- Always respond in character. Never break persona.
- Keep responses under 200 words unless the user asks for a detailed plan.
- Reference specific data from the context (exercise names, weights, adherence rate, streak).
- If the user asks something unrelated to fitness, redirect them back to training. The General does this bluntly. The Coach does it warmly.
- Never diagnose injuries. If the user mentions pain, recommend they see a professional.
- Today's date is {today}.
PROMPT;
    }
}
```

Replace `{today}` with `now()->toDateString()` at runtime.

### `app/Services/NutritionParserService.php`

Parses free-text food input into structured macros using OpenAI.

```php
class NutritionParserService
{
    public function parse(string $rawText): array
    {
        // Call OpenAI with a structured extraction prompt.
        // System prompt: extract food_name, calories, protein_g, carbs_g, fat_g, meal_type.
        // Return ONLY valid JSON. No explanation. No markdown.
        // If a field cannot be determined, return null for that field.
        
        // Parse the JSON response and return as array.
        // On parse failure, return ['food_name' => $rawText, 'calories' => null, ...]
    }
}
```

### `app/Jobs/UpdateUserStats.php`

Recomputes and caches `weekly_adherence_rate`, `current_streak_days`, `last_active_at` on the user record after any workout is saved. Implement as a queued job.

---

## TRAINER PERSONA SYSTEM PROMPTS

Implement these in `TrainerPersona` enum's `systemPrompt()` method:

### The General
```
You are The General — a no-nonsense drill sergeant fitness trainer. You are tough, direct, and respect only one thing: showing up and putting in the work. You address the user by name whenever possible.

Your communication style:
- Short, punchy sentences. Military cadence.
- You do not validate excuses. You acknowledge them and move past them immediately.
- You celebrate real PRs and streaks briefly, then immediately raise the bar.
- You do not care about feelings. You care about performance data.
- If someone tells you they're tired or had a bad day, your response is to acknowledge it in exactly one sentence, then redirect to what they're going to DO about it.
- You use occasional military metaphors but don't overdo it.
- You are not cruel — you are demanding. There is a difference.

Tone examples:
- On a missed session: "You skipped Monday. That's a decision, not a circumstance. Don't let it happen again."
- On a PR: "New squat record. Good. That's the baseline now."
- On low energy: "Noted. You're still training. What's first on the list?"
```

### The Coach
```
You are The Coach — a knowledgeable, warm, and data-savvy personal trainer. You genuinely care about the user's long-term progress and see setbacks as information, not failures.

Your communication style:
- Conversational but focused. You ask one clarifying question at a time when you need more info.
- You reference trends and patterns, not just single data points.
- You celebrate consistency loudly and progress quietly.
- You acknowledge the human side of training (sleep, stress, life) without dwelling on it.
- You use science-based reasoning when giving advice but keep it accessible.
- You think in progressive overload, recovery, and long-game outcomes.

Tone examples:
- On a missed session: "Two misses this week — let's figure out what got in the way. Was it scheduling or motivation?"
- On a PR: "That squat increase is real progress. Your training age is starting to show."
- On low energy: "Pre-workout energy was low — worth noting. Let's keep today's session focused and not push intensity."
```

---

## VUE PAGES (INERTIA)

Create all pages in `resources/js/Pages/`. Use `<script setup>` with TypeScript. Use Tailwind for all styling. No component library — hand-roll all UI with Tailwind.

Create shared components in `resources/js/Components/`:
- `AppLayout.vue` — main authenticated layout with sidebar nav
- `StatCard.vue` — metric display card (label + value + optional trend)
- `ExerciseEntryRow.vue` — single exercise row in the log form (name, sets, reps, weight inputs)
- `NutritionEntryRow.vue` — single food entry row
- `AiMessage.vue` — chat bubble (user vs AI variant)
- `PersonaBadge.vue` — shows current trainer persona with icon

### Pages to generate:

**`Pages/Dashboard.vue`**
- Greeting with user's name + current trainer persona badge
- 3 stat cards: sessions this week, adherence rate (as %), current streak
- Recent sessions list (last 5) with date, duration, top exercise
- Today's macro summary if nutrition logged

**`Pages/Workouts/Index.vue`**
- Paginated list of past sessions
- Each row: date, duration, RPE, top 3 exercises, total volume
- "Log workout" CTA button

**`Pages/Workouts/Create.vue`**
- Session header fields: date/time picker, duration, RPE slider (1–10), energy slider (1–5), notes textarea, "completed planned session" toggle
- Dynamic exercise list: starts with 1 row, "Add exercise" button adds more
- Each exercise row: exercise name (text input with common exercises datalist), sets, reps, weight (kg), optional notes
- Submit creates the session

**`Pages/Workouts/Show.vue`**
- Full session detail
- Exercise table with all entries
- Session notes and stats

**`Pages/Nutrition/Index.vue`**
- Today's logs grouped by meal type
- Daily macro totals (calories, protein, carbs, fat) as progress bars toward a rough daily target
- Quick-add form at top: supports either structured input OR raw text ("I had 3 eggs and toast")
- Last 7 days summary table

**`Pages/Trainer/Index.vue`**
- Current persona shown prominently at top
- Chat interface: message history, text input, send button
- Each message: user message on right, AI response on left with persona avatar
- Loading state while waiting for AI response
- Small "switch persona" link that goes to profile

**`Pages/Profile/Edit.vue`**
- Two sections: account info (name, email, password) and trainer settings
- Trainer settings: persona selector (two cards, General vs Coach, visual selection), experience level, training days/week, primary goal, goal description textarea, goal deadline, target weight
- Save buttons per section

---

## ADDITIONAL REQUIREMENTS

### Config
Create `config/services.php` additions:
```php
'openai' => [
    'key'        => env('OPENAI_API_KEY'),
    'model'      => env('OPENAI_MODEL', 'gpt-4o'),
    'max_tokens' => env('OPENAI_MAX_TOKENS', 1000),
],
```

Bind `\OpenAI\Client` in `AppServiceProvider`:
```php
$this->app->singleton(\OpenAI\Client::class, fn() => 
    \OpenAI\Laravel\Facades\OpenAI::client(config('services.openai.key'))
);
```

### Form Requests
Create a `StoreWorkoutSessionRequest` and `UpdateProfileTrainerRequest` with full validation rules as described above. All controllers use Form Requests, not inline `$request->validate()`.

### API Resources
Create `WorkoutSessionResource` and `NutritionLogResource` for consistent JSON shaping when passing data to Inertia pages.

### Database Seeders
Create `DatabaseSeeder` with:
- 1 demo user: `demo@trainwithruby.app` / password: `password`
- Persona: General
- Goal: build_muscle, "I want to squat 140kg by December"
- 14 days of workout sessions (mix of completed and skipped)
- 7 days of nutrition logs
- 5 body weight logs

Use `fake()` for realistic exercise names, weights, and reps. The seeder should create data that makes the dashboard feel alive on first load.

### Error Handling
- Wrap all OpenAI calls in try/catch. On failure, return a graceful error message in the trainer's voice (e.g. The General: "Systems are down. Try again.").
- Add a `RateLimiter` on `POST /trainer/chat` — max 20 requests per user per hour.

### Tests
Generate Pest tests for:
- `AiTrainerService` — mock OpenAI, assert context structure is correct
- `WorkoutSessionController` — store creates session + exercise entries in transaction
- `UpdateUserStats` job — correctly computes adherence and streak
- `NutritionParserService` — mock OpenAI, assert JSON parsing handles malformed response gracefully

---

## DEPLOYMENT NOTES (VPS)

Include a `deploy.sh` stub and document these steps in `README.md`:
- App uses Laravel Octane with Swoole for production (add as a comment/TODO — do not implement now)
- Production `.env` differences: `APP_ENV=production`, `QUEUE_CONNECTION=redis`, `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`
- Supervisor config stub for queue worker
- Nginx config stub for the domain with PHP-FPM
- Note: Sail is for local dev only — production uses standard PHP-FPM on the VPS

---

## WHAT TO GENERATE

Please generate the following files, in this order:

1. `composer.json` additions (openai-php/client)
2. All migrations (in order)
3. All Enums (`app/Enums/`)
4. All Models with relationships and casts
5. Form Requests
6. API Resources
7. Services (`AiTrainerService`, `NutritionParserService`)
8. Jobs (`UpdateUserStats`)
9. Controllers (in route order)
10. `routes/web.php`
11. `config/services.php` additions + `AppServiceProvider` binding
12. Vue components (`Components/`)
13. Vue pages (`Pages/`)
14. Seeders
15. Pest tests
16. `README.md` with setup instructions:
    - `./vendor/bin/sail up -d`
    - `./vendor/bin/sail artisan migrate --seed`
    - `./vendor/bin/sail npm run dev`
17. `deploy.sh` stub

Generate real, working code. Do not use placeholder comments like `// implement this`. Every method must have a full implementation.
