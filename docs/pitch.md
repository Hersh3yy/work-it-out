# Feetness, the elevator pitch

Kept current: updated at the end of every working session and whenever Hiren asks. The shareable copy is the private page "Feetness Elevator Pitch" on claude.ai (link in Hiren's notes); this file is the source.

As of 15 November 2026.

## In 30 seconds

Feetness is a fitness coach you text. You say what you trained, in your own words, and it saves every set, remembers your injuries and goals, and coaches you from your own numbers, never from a template. Your body is a crew of six: the one that lifts, the one that lasts, the one that heals. Each has a level you earned and a power you have today, computed from what you actually logged. A goal with a date is a battle: the app shows which crew members it demands and how ready they are, and the coach trains them in the time left. ChatGPT gives advice and forgets you. Feetness keeps score, and the score is real.

## In two minutes

The problem. Fitness apps make you fill in forms, so people stop. Chatbots give good generic advice, but they forget you: the same plan three days in a row, "moderate weight", no idea your back hurt last week.

How it works. You text the bot on Telegram from the gym: "bench 3x5 70, what next?". It saves the bench as sets with loads, checks the exercise against your active injury, and the coach answers from your own history: the load you lifted last time, the goal you stated, how the injury is going. A written profile is rebuilt from your messages and cites them, so nothing in it is invented. Every morning the coach writes first with what today is for. The app on your phone shows the facts, the notes and the injuries. No passwords: a one-time link from the bot signs you in.

The game. Your body is a crew of six, two per energy: the one that lifts (strength, power), the one that lasts (endurance, core), the one that heals (mobility, recovery). Each member has a level that only grows with training done, a power that rises and falls with what you can do today, and HP that drops the day after you worked it and recovers with rest. A goal with a date is a battle: a rave in two weeks, a 5k in two months, carrying a fridge next month. The battle demands certain members; the app shows readiness, and the time left decides the plan: two weeks out you taper and rest, two months out you build.

Why it is different. The numbers are real: every level comes from a method with a source, computed in code, never written by the model. Rest and injury never cost you anything. The coaches are three mentors with distinct voices, each the master of one energy. And it runs on an EU model, so your health data stays in Europe.

## Where it stands

Live for one person (Hiren) since 18 October 2026. The first outside testers are planned for the week of 23 November.

| Works today | Status |
|---|---|
| Free-text logging over Telegram: sets, loads, per-set lists, runs, body weight | live |
| Injuries: red-flag words answered before any AI, area and severity stored, how it is going, load checks on receipts | live |
| The coach: questions and a morning message from the person's own history, three voices | live on Gemini; moving to Mistral Medium (EU) |
| The written profile, rebuilt from logs with citations | live |
| The app: home with facts, notes, injuries; the log history; sign-in by one-time link | runs locally; Netlify and HTTPS are the next steps |
| Safety: no message lost when the AI is down, a per-person limit, a spend cap, danger words first | on the branch waiting to deploy |
| The game layer: energies, crew, levels, jobs | designed on 15 November, nothing built yet |

Measured: 401 automated tests, none of which call a model; about 3 seconds per message; about $0.65 a month in AI cost per person on the EU model.

## What comes next

1. Push the safety and all-Mistral work, then the first two testers (week of 23 November).
2. The parsing of every way people describe a workout (supersets, timed sets, bodyweight, circuits), so nothing is saved as "?".
3. Phase 2, the game's foundation: one exercise catalogue, the six qualities with a level and a power each, goals with a date and their demands, readiness, all in plain words first.
4. Phase 3, the world on top: names and art from the creative session, the roster and battle screens, promotions, the Sunday chronicle, talent points.
5. Later: a second chat app (Signal), voice notes, the body map.

## Change log

| Date | What changed in the pitch |
|---|---|
| 2026-11-15 | First version. The game model set: three energies with a coach each, six crew members, level, power and HP per member, dated goals as battles. EU-only models decided (Mistral). |
