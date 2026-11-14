# Session kickoff

Last updated 14 November 2026, from the koala laptop. Read this first on any laptop, then `PROJECT.md`.

## First thing: CI is red

Since commit `a707178` the `pest` job fails at its Audit step. The cause is a new advisory on `laravel/framework` (GHSA-63hm-g96r-2m2q, low severity, affects versions below 13.40.0, reported 13 November). The tests themselves pass: the Postgres job is green and the SQLite suite ran green on the koala laptop (352 tests). Every push since then was documentation only.

The fix is one update, then both suites, then a push:

```bash
docker compose exec app composer update laravel/framework
make test
make test-pg
```

It was not done from the koala laptop on purpose. Hiren had left, and a framework update deploys to production the moment it is pushed.

## Where things stand

- The Telegram bot is live and logging is trustworthy. The coach's advice is generic and no coach is visible. The measured causes and the fix that needs no code are in `docs/direction-2026-11-14.md`.
- Method cards, "one or two proven ways per question", are drafted in `docs/method-cards-2026-11-14.md`. Nothing is built.
- The direction is a game: the body as a character, with stats, bosses and talent points. The game features may be rebuilt from nothing; each coach keeps a stat. `docs/rpg-direction-2026-11-14.md` also holds the privacy answer (a handle and a Telegram id) and what Signal or WhatsApp would take.
- Two presentations, private pages under Hiren's account: the project map, https://claude.ai/artifact/ExS4DFpfRs6YWpL7618XvZ , and the game idea for the session with the creatives, https://claude.ai/artifact/6xrcqXaAVKkfG19ZiG2vb4 .
- No application code has changed since `9fe0c57` (12 November).

## What waits for Hiren's word

1. The picks per method card, and which card first. Proposed: the compound day.
2. Go on visible coaches (a name on every answer, `/coach`, ask all three) and on answers built on his numbers. Roadmap n39 and n40.
3. Handle-only sign-up, n46.
4. The OK for the repair of the production data from the weekend of 7 November.

## What Hiren can do without code

- The coach on a stronger model: seven environment variables in Coolify, listed in `docs/direction-2026-11-14.md`.
- HTTPS for the API on a real domain, then the app on Netlify: `docs/alpha-launch.md`, steps 5 to 7.

## What did not travel through git

- `storage/app/audit/repair.php` and the audit output: only on the koala laptop, ignored by git on purpose because this repository is public.
- The presentation skill `explain-like-im-nami`, rewritten on 14 November. It lives in `~/.claude/skills` on the koala laptop. A packaged copy is at `~/Documents/koala/explain-like-im-nami.skill` there; copy that file to another laptop and install it.
- Claude's memory of this project is per laptop. Everything that matters is in this repository's documents.

## A first message for the next session

"Read docs/session-kickoff.md and PROJECT.md. Fix the red CI first. Then wait for my picks on the method cards."
