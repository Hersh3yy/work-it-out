# Real messages

`messages.txt` is the safe place for what you would text from the gym: one message per line, exactly as you would type it (Dutch, typos, "yesterday", two lifts in one line, a missing weight). Lines starting with `#` are comments.

Replay a stored session through the real chat path and see every reply, no phone needed:

```bash
docker compose exec app php artisan log:simulate --user=1 --file=tests/Evals/messages.txt
```

Or one message:

```bash
docker compose exec app php artisan log:simulate --user=1 "bench 3x8 80, then incline db 3x10"
```

With no AI key configured every log replies "Couldn't reach the AI"; the parser needs a provider (see `.env.example`, `AI_LOG_PROVIDER`). In M4 these lines get expected facts next to them and become the live eval set that grades the parser prompt.
