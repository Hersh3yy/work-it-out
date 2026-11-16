# Method cards: one or two proven ways per question

Hiren, 14 November: pick one or two proven ways to measure and improve specific things (running further, heart strength, what to do on a compound day, a plan for carrying his mother's heavy fridge next month), because the more exactly we know what the answer should be, the more context and guard rails we can give the model. This file is the first draft of that. Nothing here is built. Every pick is a proposal for Hiren to confirm or swap.

It builds on three things already decided in `PLAN.md`: the metrics catalogue (section 3, 2026-10-05), "PHP decides, the model talks" (section 3), and the `knowledge/` folder proposal (section 4, 2026-11-01, still open).

## The idea

A method card is one question the coach must answer well, pinned down in five parts:

1. When it applies (words in the goal or the question, kinds of exercise in the log).
2. The measure: one number the app computes from the log, so progress is a fact.
3. The method: the one or two ways we chose, in our own words, with the source.
4. The rule: what the app computes for the next step (a cap, a load, a due date). Code, not the model.
5. The guard rails: what the answer may never do.

The app computes the numbers and the next step. The model gets the card and the numbers and only words them in the coach's voice. That is why a card makes answers less generic: the model no longer has to guess the method or do arithmetic.

## Terms, AI side

- Instructions (system prompt): the fixed text sent with every call. Here: `CoachAgent::instructions()`.
- Context: the data sent with one call. Here: `CoachContext`. The context window is how much fits in one call.
- Context engineering: choosing what goes into the context. A method card is exactly this.
- Guard rails: limits on what the answer may do. The strong kind is code that runs outside the model (`RedFlags`, `ExerciseLoads`). The weak kind is a sentence in the instructions, which a model can ignore.
- Structured output: the model must answer in a fixed shape. Here: the log parser and the profile writer's label per note.
- Grounding and citations: the answer says which data it rests on. Here: every profile note cites logs.
- Knowledge base: curated notes the app can look up. The `knowledge/` proposal.
- RAG (retrieval-augmented generation): look up the notes that fit the question and put them into the context. With twenty cards, matching on tags in plain code is already RAG.
- Embeddings and vector search: turning text into numbers so that similar meaning can be found without shared words. Needed when there are hundreds of notes. Not now.
- Fine-tuning: training an existing model further on your own examples. It changes habits and style; it is a poor place to keep facts. "Our own LLM" in practice means a fine-tuned open-weights model on our own server. It needs thousands of reviewed question and answer pairs. The `coach_turns` table is where those collect.
- Evals: a fixed set of questions, each with what a good answer must and must not contain, run after every change of prompt or model. The classifier has one (`make classify-eval`). The coach has none.
- Rules engine: decisions in code. Deterministic and testable. `/next` from rules is one.
- Tool use (function calling): the model asks the app for data ("bench history, 8 weeks") instead of being handed everything.
- Hallucination: an invented fact stated with confidence. Cards and computed numbers are the defence.

## Terms, training side

- Progressive overload: do a little more over time (load, reps, sets, distance). The base of all training.
- Specificity (the SAID principle, specific adaptation to imposed demands): the body gets better at what it practises.
- Needs analysis: before a plan, list what the goal demands (movements, loads, duration). Standard first step in strength and conditioning.
- Periodization: planning in blocks. A macrocycle is the whole plan, a mesocycle a block of weeks, a microcycle one week.
- Deload: a planned lighter week. Taper: the lighter days before a test or an event.
- Volume, intensity, frequency: how much, how heavy or fast, how often.
- RPE and RIR: rate of perceived exertion (1 to 10) and reps in reserve (how many more reps were possible).
- 1RM and e1RM: the most weight for one rep, and its estimate from a set of several reps.
- Compound and isolation: a lift over several joints (squat, bench, row) against one joint (curl).
- Movement patterns: squat, hinge, push, pull, carry, core. A balanced week touches each.
- Linear progression: add a fixed step every session. Double progression: add reps inside a range first, then weight.
- VO2max: the most oxygen the body can use per minute per kilo. The standard number for cardio fitness.
- Zone 2: easy effort, full sentences possible. Resting heart rate: pulse after waking. Heart rate recovery: how far the pulse drops in the first minute after stopping.

## Card 1: running further over time

When it applies: the goal names a distance or "run further"; cardio entries with a distance in the log.

Measure: the longest run of the last 30 days, and the weekly distance. Both come from stored entries (`distance_meters`); `PersonalRecordService` already finds the longest run ever.

Method, pick one: cap every run at the longest run of the last 30 days plus 10 percent. Source: Frandsen and others, British Journal of Sports Medicine 2025, 5,205 runners with watches over 18 months. A single run that went more than 10 percent beyond the longest run of the past 30 days went with clearly more overuse injuries. The week-to-week load ratio (ACWR), which many apps use, did not predict injury. The study is exploratory and the exact cut-offs should be read in the paper before the number becomes a rule.

Method, pick two: run-walk intervals until 20 to 30 minutes of continuous running is possible. The session time stays the same and the running share grows (the Couch to 5K pattern). Pace is easy: full sentences possible. This is common coaching practice; treat it as a convention.

Rule: `next_run_cap = longest_run_30d x 1.10`. No run in 30 days: start again at half of the last longest run (convention).

Guard rails: never a run above the cap. An active limitation in the leg, hip or back: walk instead. Speed work is not part of this card.

What the coach can then say: "Longest run this month: 1.0 km. Saturday: up to 1.1 km, as 2 minutes running and 1 minute walking."

Missing in the app: the 30-day window and the cap. Small.

## Card 2: cardio fitness ("heart strength")

The app says cardio fitness, never heart strength or heart health. It measures fitness; it does not diagnose.

Measure, pick one: a 12-minute test every four weeks, as far as possible in 12 minutes, running or walking, same route or treadmill. The Cooper formula turns it into an estimate: `VO2max = (meters - 504.9) / 44.73`. Already in the metrics catalogue. Always labelled an estimate.

Measure, pick two: weekly minutes of moderate and vigorous activity against the World Health Organization guideline for adults (150 to 300 moderate or 75 to 150 vigorous, plus strength on two days). Already in the catalogue as "cardio health".

Later, when a watch or a typed number exists: resting heart rate (the trend) and heart rate recovery (a drop of 12 beats or fewer in the first minute is a poor sign; Cole and others, New England Journal of Medicine 1999, quoted from memory, to verify).

Method: most minutes easy (zone 2). After four weeks of regular easy work, one session a week of four times four minutes hard with three minutes easy between. Source: Helgerud and others 2007: four by four at 90 to 95 percent of maximum heart rate raised VO2max more than easy or threshold training of the same total work, in trained young men. Without a heart rate: hard means only a few words at a time.

Rule: `test_due` when the last 12-minute test is older than 28 days; `weekly_minutes` against the guideline.

Guard rails: chest pain, fainting and numbness are already stopped by `RedFlags` before any model sees them. No hard intervals in the first four weeks or while a limitation's red-flag question is unanswered. The estimate is never called a measurement.

Missing in the app: recognising a 12-minute test in the log, intensity per session (activity type and pace for now).

## Card 3: the compound day

When it applies: the question asks for a session and the person lifts; the morning message on a training day.

Measure: e1RM per lift and its trend over four to eight weeks. The catalogue has the formulas (Brzycki for 2 to 6 reps, Epley above, nothing above 12). Not built yet; the app shows only the heaviest weight.

Method, pick one, the shape of the session: two or three compound lifts first, each a different movement pattern (one squat or hinge, one push, one pull), three to five sets of three to eight reps with two to three minutes rest. Then two or three smaller lifts, two to three sets of eight to fifteen. A carry or core work last. Warm-up sets climb to the working weight (about 40, 60, 80 percent).

Method, pick two, the next load: all sets made at the target reps means one step up next time (2.5 kg upper body, 5 kg lower body). Reps missed two sessions running: same weight. Three: 10 percent off and build again. For the smaller lifts double progression: reps go up inside a range, then weight. Progressive overload itself is as well supported as anything in training; the step sizes are convention from novice programs.

Weekly check: each movement pattern at least once; about ten hard sets per muscle group a week as a working target (Schoenfeld and others 2017, from memory, to verify).

Rule: `next_load(lift)` from the last two sessions of that lift. A lift not done for more than two weeks restarts at 90 percent. Never more than one step per session.

Guard rails: the `ExerciseLoads` filter for an active limitation, as today. No lift above the rule's load. Stop one to three reps before failure on compound lifts.

Missing in the app: e1RM, the movement pattern of an exercise (planned for `/next`, not built), and the load rule. The morning message asks the model for the step up in words today; this card moves that into code.

## Card 4: a goal with a date and a task (the fridge)

This is the general card: any "be able to do X by date Y". It leans on roadmap item n36 (goals in words stored as goals with a date), which is not built.

Method, pick one, needs analysis: break the task into demands from a fixed list: lift from the floor (hinge), carry (and grip), stairs (single leg), push or pull an awkward object, brace (core), repeat efforts. The model may do this step, in a fixed shape, and it may ask for one missing fact (stairs or not, alone or with someone). For a fridge with two people: hinge, carry, grip, brace, perhaps stairs.

Method, pick two, plan backwards from the date: each demand gets one exercise and one test from our table (carry: farmer's carry, load times distance; hinge: a deadlift variation, e1RM; grip: hang time; brace: Pallof press or plank; stairs: step-ups). The weeks before the date build by card 3's rule. The last three to five days are light. One rehearsal a week before with an awkward object at a lower load.

Honesty the card demands: four weeks buy a beginner a real but modest gain. A hand truck, straps and a second person matter more than the training, and the coach says so.

Guard rails: an active limitation gates its demand. With the lower back on file, the hinge returns in steps, each with a criterion. When the date comes before the gate opens, the coach says not to lift it.

Missing in the app: goals with dates (n36), the demands table, the tests.

## How the cards would enter the app, in three steps

1. The cards as Markdown files in `knowledge/` with tags on top. `CoachContext` adds the one or two that match the goal, the logged exercises and the question, under a key `methods`. `CoachAgent` gets one instruction: when a method is given, follow its rule and name its measure. No vector store. This is the 2026-11-01 proposal with a fixed shape per note.
2. The numbers in code: `longest_run_30d`, `next_run_cap`, `weekly_minutes`, e1RM per lift, `next_load`. One small class each, unit-tested against a worked example, added to the facts the coach already trusts.
3. Evals: five to ten questions per card with checks a script can run (the cap is respected, no deadlift while the back is on file, the measure is named). Run by the `coach:try` command across models. This is also how a model switch gets judged on answers instead of on price.

Later, and only when the numbers ask for it: embeddings above roughly fifty cards, fine-tuning after thousands of reviewed coach turns.

## For Hiren to choose

- Per card: keep both picks, or swap one.
- Which card first. Proposed: card 3 (he lifts three times a week, the data is there, the morning message already attempts it), then card 4 (it needs n36, which was next anyway), then 1 and 2.
- Whether step 1 above may be built as soon as the picks are confirmed.

## Numbers in code

Each number in `config/fitness.php` cites one line here. "Convention" means common coaching practice, not a study; ours to tune.

- Power windows: strength over 56 days (card 3, the e1RM trend over four to eight weeks); cardio minutes over 7 days (card 2); the longest run over 30 days (card 1); core over 56 days (convention, same as strength).
- e1RM: Epley, weight x (1 + reps / 30), from sets of 1 to 10 reps (Hiren, 15 November; replaces the Brzycki and Epley split of the metrics catalogue).
- Strength standards, e1RM per kilo of body weight for an intermediate lifter: squat 1.25, deadlift 1.5, bench press 1.0, overhead press 0.65 (common strength-standard tables, from memory, to verify).
- Cardio: 150 minutes a week (card 2, WHO 2020 guideline for adults).
- Core: a two-minute plank and a farmer's carry at half body weight per hand count as the standard (card 4's tests, convention).
- Recovery: two rest days in seven (convention); an active limitation takes 10, 25 or 50 percent off recovery when mild, moderate or severe, and an unknown severity counts as moderate (convention, Feetness 15 November).
- Readiness, 0 to 100: 40 on the day a quality worked and the day after, 70 on the second day, full from the third (convention, about 48 to 72 hours between hard sessions for the same muscles, ACSM guidance from memory, to verify). Mobility and recovery are not tired by their own light work. An active limitation caps the qualities that load its area at 80, 60 or 30 when mild, moderate or severe; an illness caps every quality at 50 (convention, Feetness 15 November).
- Level: every session that trains a quality earns it one point; the level is the sum, so it only grows (convention, Feetness 15 November: level is training history, rest and injury never cost it).
- Goals: inside 14 days the phase is taper (lighter, more rest), further out build (Hiren, 15 November; tapers of 8 to 14 days did best in Bosquet and others 2007, from memory, to verify). A demanded quality is ready at its standard (power 1.0), close from 0.75 (convention), not yet below, and "not measured yet" when it has no power: never ready. What each kind demands is card 4's needs analysis: an event endurance, recovery and mobility; a distance endurance and recovery; a carry or a lift strength and core; a body-weight goal endurance and strength.

## Sources

- Running, single-session spikes: Frandsen and others, British Journal of Sports Medicine 2025. Summary by the journal: https://blogs.bmj.com/bjsm/?p=11751 . Checked 14 November through secondary summaries, not the paper.
- Four by four intervals: Helgerud and others, Medicine and Science in Sports and Exercise 2007. https://pubmed.ncbi.nlm.nih.gov/17414804/ . Abstract checked 14 November.
- Cooper formula, WHO minutes, e1RM formulas: from the metrics catalogue in `PLAN.md`, still marked "to verify" there.
- Heart rate recovery (Cole 1999) and weekly sets (Schoenfeld 2017): from memory, not checked.
