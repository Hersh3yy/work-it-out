# Session kickoff

Last updated 14 November 2026 (late), from the other laptop. Read this first on any laptop, then `PROJECT.md`. The build order is `PLAN.md` section 5, "Next, in order"; this page only says where things stand.

## First thing: what changed on 14 November (late)

CI is green again (laravel/framework 13.42; the audit runs in its own job) and the "fix first" list of `docs/audit-2026-11-14.md` is done, test first: no double log when the coach is down, danger words before any open question, the morning message sent by provider and safe to retry, a 3 million token monthly cap by default, prices for the models in use. Details in the `PROJECT.md` diary.

Still yours: turn on Coolify's wait for CI checks, so a push only deploys after both test jobs pass. If `AI_MONTHLY_TOKENS_PER_USER=0` is set in Coolify, remove it to get the new default cap.

## Where things stand

- The Telegram bot is live and logging is trustworthy. The coach's advice is generic and no coach is visible. The measured causes and the fix that needs no code are in `docs/direction-2026-11-14.md`.
- Method cards, "one or two proven ways per question", are drafted in `docs/method-cards-2026-11-14.md`. Nothing is built.
- The direction is a game: the body as a character, with stats, bosses and talent points. The game features may be rebuilt from nothing; each coach keeps a stat. `docs/rpg-direction-2026-11-14.md` also holds the privacy answer (a handle and a Telegram id) and what Signal or WhatsApp would take.
- Two presentations, private pages under Hiren's account: the project map, https://claude.ai/artifact/ExS4DFpfRs6YWpL7618XvZ , and the game idea for the session with the creatives, https://claude.ai/artifact/6xrcqXaAVKkfG19ZiG2vb4 .

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

"Read docs/session-kickoff.md and PROJECT.md. Then the open security items of the 14 November audit (C3 to C5), and wait for my picks on the method cards."
