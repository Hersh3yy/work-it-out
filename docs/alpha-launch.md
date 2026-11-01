# Alpha launch: Telegram, the app and the API for a few testers

Written 2026-11-01. Assumes nothing was touched since. Telegram alone works after steps 1 to 4; the app needs 5 to 7 as well.

## Must do before anyone else texts the bot

1. **Replace the bot token.** The current one was visible in a screenshot. In Telegram, BotFather: `/revoke`, pick the bot, copy the new token. In Coolify, the API's Environment Variables: set `TELEGRAM_BOT_TOKEN`, then Redeploy. Then in the app's Terminal in Coolify: `php artisan channel:telegram:setup` (sets the command menu for the new token).
2. **Pay for Gemini.** The free tier allows about 16 calls a minute for the whole app. One logged message costs 1 to 2 calls, a coach question 2, plus one profile rewrite per burst of logs. Five people at the gym at once will hit the limit and get "couldn't reach the AI". In Google AI Studio, enable billing on the key's project (flash-lite costs cents per day at this scale) and set a monthly budget alert.
3. **Lock the server.** Coolify: Settings, enable 2FA. Firewall (Contabo panel or `ufw`): allow 22, 80, 443 only; ports 8000, 6001 and 6002 (Coolify dashboard and realtime) are open to the world today. SSH: key-only (`PasswordAuthentication no`).
4. **Backups.** Coolify, the Postgres resource, Backups: a daily scheduled backup, kept 14 days (S3 if you have one). Testers' data lives only there.

## The app (Netlify)

5. **A domain with HTTPS for the API.** A DNS A record, for example `api.feetness.<yourdomain>`, pointing at 84.247.178.198. In Coolify, the API's Domains: `https://api.feetness.<yourdomain>` (Coolify gets the Let's Encrypt certificate). Set `APP_URL` to the same. Check: `https://api.feetness.<yourdomain>/up` answers.
6. **The app on Netlify.** Create the GitHub repository `feetness-app`, then push the local one (`~/Code/feetness/feetness-app`, branch `main`). Netlify: Add new site, import it. Environment variables: `NUXT_PUBLIC_API_BASE=https://api.feetness.<yourdomain>/api`, `NUXT_PUBLIC_TELEGRAM_BOT=<bot username>`. Deploy.
7. **Connect them.** In Coolify on the API: `APP_WEB_URL=https://<site>.netlify.app` (where `/login` links point) and `CORS_ALLOWED_ORIGINS=https://<site>.netlify.app`. Redeploy the API.

## Smoke test yourself (10 minutes)

8. From your phone: a workout ("bench 3x8 80"), a body weight, an injury ("tweaked my knee, easy for a week") and its two questions, a coach question ("30 min, 3 things, go"), `/me`, `/undo`, `/login` and tap the link: the app opens on your profile.

## Invite testers

9. Per person, in the API's Terminal in Coolify: `php artisan channel:invite their@email --name=Name`. It prints a one-time link (7 days). Send it with a short note, for example:

   > Feetness alpha. Tap the link, press Start, then just text what you train, like you'd text a friend: "squat 5x5 100", "ran 5k in 27 min", "82.4kg". It asks when something is unclear. Ask it anything about your training. /undo fixes a mistake, /me shows what it knows, /login opens the app. Tell me when it gets something wrong; that's the point.

10. Watch for a week: `php artisan profile:show their@email`, the container logs for "AI call failed", and what people text that is not understood (add those to `tests/Evals/messages.txt`).

## Known limits for testers

- Dates are UTC: a log just after midnight Amsterdam time lands on the previous day.
- The app only reads. Logging, editing and answering happen in Telegram.
- The coach answers from what was logged and stated; it has no memory beyond that and says so.
- Coach questions are capped at 30 per person per day (`COACH_DAILY_LIMIT`).
