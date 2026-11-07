# Exploration, 7 November 2026: chats, outages, prompts, models, cost, classifier, knowledge

Hiren is not satisfied with the latest chats and asked to explore our prompts, other models (Kimi, DeepSeek), our own knowledge, cost with reasoning and the classifier, what happens when the AI is unavailable, and whether the bot knows a recent message failed. Read-only: production data was read over SSH, nothing changed. No code in this document is built.

## 1. What the last six days of chats show

Source: `activity_logs`, `coach_turns` and failed `ai_calls` for Hiren, 2 to 7 November.

Most of what felt bad is plain code, not the model:

| When | What Hiren sent | What happened | Should have |
|---|---|---|---|
| 4 Nov 19:53 | "No, general fitness" (answer to the goal question) | saved as a log "General fitness log" | set the goal |
| 6 Nov 10:40 | "I want to slowly work on my core" | saved as an activity log | a goal or a note, not an activity |
| 7 Nov 14:21 | the four-line bench message | AI connection error, message lost | kept and processed later |
| 7 Nov 14:36 | "5 reps each set" (answer to "how many reps?") | saved as a log "5 reps each set" | filled the reps |
| 7 Nov 14:36 | the resent four-line bench message | saved again, sets 6 to 8 counted next to the earlier "3 more sets of 70" | one session, no double sets |
| 7 Nov 14:37 | "70kg....now what next ?" (answer to "weight of the 6th set?") | went to the coach; the open question stayed open | fill the weight, then answer "what next" |

The answer handlers only accept a bare reply ("5", "70kg", "1"). Real replies carry words around the number. And a message that is both an answer and a question goes only to one of them.

The coach after the 5 November prompt rewrite is clearly better: plans with sets, reps and weights, "leave 2 reps in the tank", a check on the back, and it said no to two hours on Sunday. What is still off: weights it never saw in a log (machine chest press 50 kg, tricep pushdown 25 kg, Pallof press 15 kg) are given with the same confidence as the logged 41 kg row; the same three core moves come back every time; and before the rewrite "back is a constraint, not an excuse" opened three answers in a row.

## 2. When the AI is unavailable

Six failures in the week: four `ProviderOverloadedException` (Google's 503, 6 Nov 10:39 to 10:42), one `ProviderConnectionException` (7 Nov 14:21), one 30-second timeout on a log parse.

What happens today: `HandleInboundMessage` catches `AiUnavailable` and replies "Couldn't reach the AI just now, so nothing was saved. Try again in a minute." The message is gone. Nothing records that it arrived, so the coach and the profile cannot know it existed, and Hiren had to resend it by hand ("But, this was text from earlier"), which then double-counted sets.

Three layers, cheapest first:

1. **A second provider.** The AI library already fails over when an agent is given several providers (`Promptable::withModelFailover`, `vendor/laravel/ai/src/Promptable.php:411`): on an overload or connection error it tries the next one. It takes one model per provider, so the fallback must be a different provider (DeepSeek or OpenRouter), not a second Gemini model. Config only, per job.
2. **Keep every message before any AI runs.** An inbox table: the message, when it arrived, status (handled, waiting, failed). On a total outage the reply becomes "Google's AI is busy. I kept your message and will process it in a few minutes; no need to send it again." A queued job retries (1, 3, 10 minutes) and sends the receipt by itself when it succeeds. A new message while one is waiting is handled after it, in order. If it still fails after the retries: "Your 14:21 message did not go through. Reply retry or send it again."
3. **The coach sees it.** The coach context gets the waiting or failed messages, so it can say "your bench message from 14:21 has not been saved yet" instead of answering as if it never existed.

Layer 1 is half an hour. Layer 2 is about a day with tests and fixes the lost and duplicated messages above. Layer 3 is an hour once layer 2 exists.

## 3. Prompts

Sizes today (words in the agent class, including code): coach 895, log parser 666, profile 416, limitation 365, classifier 301. The coach prompt is reasonable after the rewrite. Changes worth making, in order of effect:

- Weights: suggest a number only when the same or a close exercise was logged; otherwise give a way to pick one ("a weight you can lift 12 times with 2 left"). Say which numbers came from the log.
- Variety: the context already holds the last exchanges; add a line to avoid repeating the exercises suggested in the last few answers unless asked.
- Answers and questions in one message: handle in code (section 1), not in the prompt.

## 4. Other models and what each message costs

Measured on production (`ai_calls`, 6 to 7 Nov, small samples, averages):

| Job | Model | Calls | Input tokens | Output tokens | Time |
|---|---|---|---|---|---|
| Classify | flash-lite | 9 | 403 | 21 | 0.95 s |
| Read a log | flash-lite | 4 | 730 | 232 | 1.2 s |
| Limitation | flash-lite | 2 | 465 | 60 | 0.8 s |
| Coach answer | flash-lite | 2 | 4,600 | 66 | 1.0 s |
| Profile rewrite | flash | 2 | 4,134 | 4,898 | 21.9 s |

Prices per million tokens, input / output, from third-party pages in November 2026 (not checked against the providers' own pages):

| Model | Input | Output | Note |
|---|---|---|---|
| Gemini 3.5 Flash-Lite | $0.30 | $2.50 | what logs and the coach use today |
| Gemini 3.5 Flash | $1.50 | $9.00 | what the profile uses today |
| DeepSeek V4.1 Flash | $0.15 to $0.30 | $0.60 to $1.20 | off-peak to peak; supported by the AI library |
| Kimi K2.6 | $0.95 | $4.00 | closed to new accounts since 31 Oct, retired end of December |
| Kimi K3.1 Pro | $2.00 | $10.00 | newest Moonshot model |
| Jev | $0.042 | free | classify only |

What that means per thing, at those prices:

| | Flash-Lite | Flash | DeepSeek Flash (peak) |
|---|---|---|---|
| One logged message (classify + read) | $0.0010 | | $0.0006 |
| One coach question (classify + answer) | $0.0017 | $0.0083 | $0.0016 |
| One profile rewrite | $0.0135 | $0.050 | $0.0071 |

A busy month for one person (4 logs a day, 2 coach questions, one profile rewrite a day): logs about $0.12, coach about $0.10, profile about $1.50 on Flash. The profile is about 85% of the cost.

Model picks:

- **Kimi**: not now. The K2 line is being retired and K3.1 Pro costs more than Gemini Flash for no measured gain on our work.
- **DeepSeek Flash**: the strongest candidate as the fallback provider (section 2) and maybe for the profile: about a seventh of Flash's cost there. Its own API is hosted in China; our messages hold health data (injuries), so for testers either OpenRouter with a non-China host of the open weights, or a stated choice. Unverified on our eval set: run `make classify-eval` and a `log:parse` set on it before using it.
- **Gemini**: stays the default until another model is measured on our own messages.

## 5. Reasoning (thinking) tokens

The profile rewrite returned 4,898 output tokens for nine notes of about 40 words each (about 500 tokens of text). The rest is almost certainly the model's hidden thinking, billed as output: about 90% of the most expensive call. Likely, not verified (the recorded tokens do not split thinking from text).

The AI library lets an agent pass provider options (`Laravel\Ai\Contracts\HasProviderOptions`, merged into Gemini's `generationConfig` in `Gateway/Gemini/Concerns/BuildsTextRequests.php:105`), so each agent can set its own thinking level: off for classify and the log reader, low for the coach, medium for the profile. Then compare one profile rewrite at each level on Hiren's data: notes, citations, cost, time. Thinking helps where the model has to weigh many logs (the profile) and wastes money where the answer is a box or a JSON copy of numbers.

## 6. The classifier

The classify step costs about $0.00017 per message on Flash-Lite, roughly 2 cents per person per month. A classifier does not save money worth having. What it gives:

- Speed: 0.95 s today; Laya measured 60 to 110 ms on Hiren's laptop.
- Availability: a classifier on our own server is never overloaded by Google, so the sort step keeps working in an outage (pairs with section 2).

New since the last look: the AI library (laravel/ai 1.2) ships classification itself, `Laravel\Ai\Classification::of($text)`, with a `TypeSafeGateway` for Jev's `/systemone` API that also accepts a routing server like `laya-serve` by URL, plus `Classification::fake()` for tests. Our own `SystemOneClient` can be replaced by the library's, which removes code.

The steps before switching are unchanged (shadow mode, about 200 real messages, a fitted cut-off); start with the answer size, where a wrong pick is harmless.

## 7. Our own knowledge

The advice is "vanilla" partly because the model has nothing of ours to lean on. The proposal in PLAN.md section 4 (2026-11-01) stands: short topic notes in our own words, sources named, reviewed by Hiren, injected by matching tags. The chats say where to start:

1. Returning to squats after a low-back strain (Hiren: "scared of squats, next week I want to start back").
2. Picking a weight without a log: reps in reserve, a first set as a test.
3. Core progressions with a sore back: dead bug, bird dog, Pallof press, then what comes after them.
4. Fat loss and general fitness for someone training three days a week.
5. One-hour session templates: push, pull, legs and conditioning with the back in mind.

## 8. Proposed order

1. Answer handlers accept real replies ("5 reps each set", "70kg....now what next", "No, general fitness") and a reply can both answer and ask. Plain code, about half a day. Fixes most of what went wrong this week.
2. Fallback provider plus the inbox with retries (sections 2.1 and 2.2). About a day.
3. Thinking level per agent and one measured profile comparison (section 5). An hour plus Hiren's run.
4. Prices in `config/ai.php` so `ai:usage` shows money.
5. DeepSeek Flash on our eval sets.
6. The first two knowledge notes.
7. Shadow classifier through the library's `Classification`.

## Sources

- Gemini 3.5 Flash-Lite prices: [maximem.ai](https://www.maximem.ai/blog/gemini-api-pricing-2026), [OpenRouter](https://openrouter.ai/google/gemini-3.5-flash-lite), [eesel.ai](https://www.eesel.ai/blog/gemini-3-5-flash-lite-pricing)
- Gemini 3.5 Flash prices: [evolink.ai](https://evolink.ai/blog/gemini-3-5-flash-pricing-guide), [modelcompare.dev](https://modelcompare.dev/models/google/gemini-3-5-flash)
- DeepSeek prices: [benchlm.ai](https://benchlm.ai/deepseek/api-pricing), [techjacksolutions](https://techjacksolutions.com/ai-tools/deepseek/deepseek-pricing/), [coworker.ai](https://coworker.ai/blog/deepseek-api-pricing)
- Kimi prices and retirement: [benchlm.ai](https://benchlm.ai/moonshot/api-pricing), [OpenRouter](https://openrouter.ai/moonshotai/kimi-k2.6), [Wikipedia](https://en.wikipedia.org/wiki/Moonshot_AI)
