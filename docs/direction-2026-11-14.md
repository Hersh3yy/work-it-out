# Direction, 14 November 2026: the coach, the model, the app, GDPR

Written on the koala laptop for the next session on the other one. Nothing in this file is built yet. It holds Hiren's verdict, what was measured, what he can change without code, and the build order that waits for his go.

## Hiren's verdict (12 November)

The advice feels impersonal and generic. He does not see the coaches. He asked for a better model that is still cheap (DeepSeek, Kimi), the status of the app and how to get it up, and features that would make a person use this instead of ChatGPT. On 13 November he said DeepSeek is fine with him and asked about GDPR.

## What was measured

From the audit of 12 November (17 logs, 16 coach turns, 53 model calls in 7 days):

- Every coach answer ran on `gemini-3.5-flash-lite`, the cheapest model. `AI_CHAT_MODEL` is not set on the server, so the chat job falls back to the log job's model.
- A coach call takes about 4,000 input tokens and returned about 70 output tokens on average. The answers are three to five lines.
- One coach speaks, Lt. Surge, because `users.trainer_persona` defaults to `lt_surge`. The answer carries no name. Telegram has no command to switch (`app/Conversation/Commands` has Better, Daily, Help, Login, Profile, Undo). Shen and Latika have never spoken.
- The same plan went out three times in three days (10 November evening, twice on 12 November). The coach context holds the last coach turns, but nothing tells the coach to build on its last plan or to check whether it was done.

So a stronger model is one of three causes. The other two are the prompt and context (short answers, no memory of its own plans, nothing that makes it cite the person's history) and the missing coach identity.

## A better model for the coach, no code

`laravel/ai` already has a DeepSeek driver and `config/ai.php` already reads `DEEPSEEK_API_KEY`. The coach job reads `AI_COACH_PROVIDER` and `AI_COACH_MODEL` (named `AI_CHAT_*` until 15 Nov). Logging stays on flash-lite: the parse was tested there and a bigger model dropped exercises on 11 October.

Prices per million tokens. DeepSeek and Google were read from their own pricing pages on 12 November. Kimi comes from price aggregators and is not verified. The cost per answer assumes 4,000 tokens in (measured) and 1,500 out including thinking (assumed).

| Model | Input | Output | One coach answer |
|---|---|---|---|
| gemini-3.5-flash-lite (today) | $0.30 | $2.50 | about $0.0015 |
| deepseek-flash | $0.15 off-peak, $0.30 peak | $0.60 off-peak, $1.20 peak | $0.0015 to $0.003 |
| deepseek-v4-pro | $0.66 off-peak, $1.32 peak | $1.98 off-peak, $3.96 peak | $0.006 to $0.011 |
| gemini-3.8-flash | $0.75 until 31 Dec 2026, then $1.50 | $3.75 until 31 Dec 2026, then $7.50 | about $0.009, then $0.017 |
| Kimi K3.1 Pro (unverified) | $2.00 | $10.00 | about $0.023 |

DeepSeek's peak hours are 01:00 to 04:00 and 06:00 to 10:00 UTC on weekdays, so the 08:00 morning message is billed at peak. At ten coach answers a day `deepseek-v4-pro` stays under $3.50 a month. Kimi costs more than Gemini 3.8 Flash and has no named driver in the library, so it is not the pick.

Hiren's steps, in Coolify on the API, then redeploy:

```
DEEPSEEK_API_KEY=<key from platform.deepseek.com>
AI_COACH_PROVIDER=deepseek
AI_COACH_MODEL=deepseek-v4-pro
COACH_TOKENS_QUICK=4000
COACH_TOKENS_PLAN=6000
COACH_TOKENS_EXPLAIN=8000
AI_PROFILE_MODEL=gemini-3.8-flash
```

Why the token caps go up: DeepSeek thinks by default (effort high), thinking is billed as output, and the library sends the cap as `max_completion_tokens`. With today's cap of 800 the thinking could use all of it and leave an empty answer, which the app reports as the AI being unavailable. This is likely, not tested. If DeepSeek fails with an overload or a connection error, `App\Ai\ModelChain` falls back to the log job's model on its own.

Why the profile model changes: `gemini-3.5-flash` is no longer on Google's price page. Check which model answers profile calls with `php artisan ai:usage --since=7d --by=model`.

Not measured: whether either model gives better advice on Hiren's data. Nobody ran them side by side. A `coach:try "question" --model=...` command that prints the same question on two models and saves nothing would settle it (code, needs a go).

Prices still to fill into `config/ai.php` `prices` (config change, needs a go): flash-lite 0.30 and 2.50, 3.8-flash 0.75 and 3.75 until the end of the year. One price per model cannot hold DeepSeek's two rates; the peak rate as the upper bound is the honest choice.

## GDPR, in short (not legal advice)

- Hiren alone, on his own data: GDPR does not apply to purely personal use. DeepSeek directly is fine.
- With any other user he is the controller. Injury notes and body weight are health data, which needs explicit consent.
- Each AI provider then needs a processor agreement. Searches on 13 November found no public one for DeepSeek's own API (the sources are consultancy pages, not DeepSeek). DeepSeek's policy says data is stored in China, China has no EU adequacy decision, and Italy's regulator restricted DeepSeek in January 2025.
- The way out is in the design already: the model per job is an environment variable. DeepSeek's models are open weights and EU hosts sell them with EU processing (one lists v4-pro at $2 in and $4 out per million tokens, a vendor claim nobody verified). Gemini on the paid tier and Mistral are the other routes.
- Before the first other user: a privacy notice that names the AI provider, export and delete on request, HTTPS on the API, and the person's name left out of what goes to the model (`CoachContext` sends it today).

## The app (repository `Hersh3yy/feetness-app`)

It is on GitHub since 8 November: private, branch `main`, two commits, `netlify.toml` in place. Netlify is not linked (no deployments on the repository). On the koala laptop the clone is at `~/Documents/koala/feetness-app`, not `~/Code/feetness/feetness-app`.

Looked at on 13 November against a local API with demo data. Both screens work. Home shows the week ring, the limitation card with a Healed button, four tiles (volume, streak, top lift, body weight), the written notes with their sources, and recent messages with one chip per set. Logs shows every message, paged.

What it lacks, set against the verdict:

- No coach anywhere: no name, no face, no morning message, no earlier answers. The API does not expose coach turns yet.
- Nothing over time: a lift is one top number, body weight is one number and a change.
- Read-only: no coach switch (`PUT /api/profile/trainer` exists in the API, the app has no screen for it), no setting for the morning hour.
- Limitation updates do not show on the card, only the first note.
- The body weight tile cuts off its date.

Getting it up is Hiren's five steps, all written out in `docs/alpha-launch.md` (5 to 7) and in the app's README:

1. HTTPS on the API. Tested 12 November: the sslip.io address answers on HTTP and its HTTPS certificate is not valid, so a Netlify site cannot call it. A DNS A record for `api.<domain>` to the server, that address as `https://` in Coolify's Domains, redeploy.
2. Netlify: add a site, import `feetness-app`. The build settings come from `netlify.toml`.
3. Netlify variables `NUXT_PUBLIC_API_BASE=https://api.<domain>/api` and `NUXT_PUBLIC_TELEGRAM_BOT=<bot name>`, deploy.
4. On the API: `APP_WEB_URL` and `CORS_ALLOWED_ORIGINS`, both the Netlify address, redeploy.
5. `/login` in Telegram, tap the link, add to the home screen.

A local look without Docker (what the koala laptop did): migrate an empty SQLite file, then serve the API with the environment overriding `.env`:

```bash
DB_CONNECTION=sqlite DB_DATABASE=/absolute/path/demo.sqlite QUEUE_CONNECTION=database APP_WEB_URL=http://localhost:3000 php -S 127.0.0.1:8088 -t public public/index.php
```

The router has to be `public/index.php`; Laravel's own `server.php` looks for `index.php` in the directory the command was started from. The old `DatabaseSeeder` fills workout sessions and nutrition, not activity logs, so the app shows nothing from it. A demo seeder for the current data (logs through `RecordSmartLog`, a limitation with updates, a profile version) is worth having in the repository (code, needs a go).

## Why this instead of ChatGPT

ChatGPT gives generic advice for free and remembers loosely. Generic advice gives nobody a reason to switch. Feetness can win where ChatGPT is weak: every set stored exactly and every number computed by code, one line from the gym with a receipt and `/undo`, an injury followed by the day, a coach that writes first and knows what was skipped, and three characters who see the same data differently.

## Proposed order (14 Nov; superseded)

The build order now lives in one place, `PLAN.md` section 5, "Next, in order" (15 Nov). The list below is what was proposed on 14 Nov; its items are in that list.

1. Coaches visible. The coach's name on every answer, `/coach` to switch in Telegram, one command that asks all three for a short take each. This is roadmap item n34, moved up by the verdict.
2. Answers built on his numbers. One observation with a date and a load from his own logs in each answer, the coach's last plans in its context with whether they were done, and a rule against sending the same plan twice.
3. A Sunday review: sessions, top sets against the week before, one verdict per coach.
4. The intake on first link (n30): goal, days, equipment, anything hurting, the coach.
5. Plan memory: the coach's suggested session stored as a plan, the next log compared with it.
6. The app, after Netlify is up: a coach card on Home (the coach, today's morning message, the last answer), a coach picker, a line per lift and for body weight.
7. Later: voice notes, a photo of the machine or the board, a Telegram group with friends.

Still open from the 12 November audit and unchanged: goals in words stored as goals (n36), exercises without a number kept out of the rows (n37), the same exercise twice in one session (n16), tier 1 of `docs/audit-2026-11-08.md`.

## What did not travel with this push

- `storage/app/audit/repair.php`, the script that repairs the production data from the weekend of 7 November, and the audit output beside it. Both are gitignored on purpose (personal data, and this repository is public) and exist only on the koala laptop. The repair still waits for Hiren's OK and has to be run from that laptop, or the file copied by hand.
- The demo database and the two launch entries used for the local look. They are scratch files on the koala laptop.
