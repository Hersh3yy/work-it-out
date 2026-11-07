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

## 9. Seen from the model's seat

The exact coach prompt production builds for "now what next ?" on 7 Nov (about 2,100 words: rules, Lt. Surge's voice, the context) was answered once by Claude playing the model, to see what the model sees. Gemini's real answer was three items: Pallof press 3x12 at 15 kg, bird-dog 3x8, treadmill 1000 m at 2.5%.

What makes the job hard from inside:

1. The context contradicts itself. Bench appears twice today (3 sets at 70 and an 8-set entry with sets 6 to 8 unknown), so the model cannot tell 8 sets from 11. Junk rows sit between real ones ("5 reps each set", "No, general fitness"). The goal says `lose_fat` though Hiren corrected it. Step 1 (section 8) stops new junk; the existing rows stay until Hiren says to clean them.
2. The 5 Nov session has `exercises: []`: the row at 41 kg, preacher curls and hamstring curls exist only in the raw message, so "a logged lift continues from its last top set" has nothing structured to continue from.
3. Today's session is not a thing in the context. "1 or 2 more things then done" from the earlier message is buried in `recent_logs`; Gemini gave three. A `today` block (what was done so far, sets and volume, what they said about time left) would make the answer obvious.
4. The voice card's tone example is made of exercises ("chest-supported row 4x12 at 40, leg curl 3x15, incline walk 10 min"). The model leans on those words: chest-supported row and incline walk appear in most answers. A tone example without exercise content would stop the priming.
5. `how_it_is_going` is empty, so the rule "end with one line asking how it is today" fires on every answer. It should ask at most once a day, using `recent_exchanges`.
6. Gemini repeated Pallof press and bird-dog from the day before, against the rule "do not repeat advice". The rule is there; the context does not mark which exercises were already suggested, so the model has to dig through prose.
7. Records split "Bench Press" and "Bench Press Barbell" as two lifts; name normalisation.
8. "now what next ?" is sized quick (800 tokens). Mid-session "what next" is a small plan; fine at five lines, but the depth rule could treat "next" as a plan.

What the answer could have been with the same data, as Lt. Surge: bench is done (eight sets is plenty of chest), so two things to finish: chest-supported row 3x10 at 40 kg (from Thursday's 41 kg) with the chest on the pad to balance the pushing, then 15 minutes of brisk incline walking for the fat-loss part; core stays for Sunday as planned; one line asking how the back felt during the bench.

Most of the gap is the data the model is handed, not the model. Points 1 to 3 and 5 are plain code.

## 10. Token budget and prompt changes

Measured: the last seven coach answers were 17 to 65 words; the two recorded coach calls returned 59 and 74 output tokens against budgets of 800 (quick) and 1,500 (plan). The budget never binds. What keeps answers thin is the length line in the prompt ("at most 5 short lines").

The same "now what next ?" answered without a length limit (Claude, playing the model, same context) came to 137 words, about 190 tokens: what to do, the load and where it came from, why it fits today, when the session ends, one question back. On Flash-Lite the extra ~130 output tokens cost about $0.0003 per answer, roughly 2 cents a month for a person asking twice a day. Being cheap here buys nothing and costs the answer its reasons. The cost lever is the profile's hidden thinking (section 5), not coach length.

Proposed, in order of effect (not built; each is small):

1. Length by need, not by line count. Replace the per-depth line caps with: quick, as long as it needs and rarely more than 8 short lines; plan, the list plus one line of why per item where it matters; explain, up to 12 lines. Keep `max_tokens` only as a ceiling against runaway output (1,500 for all depths). When they said how much they want ("1 or 2 more things", "40 min"), match it exactly.
2. A `today` block in `CoachContext`: the exercises logged today with sets and loads, total sets per muscle area, and the person's own words about time or what is left. Plain code.
3. An `already_suggested` list: exercise names from the coach's answers in the last 72 hours, so "do not repeat advice" is checkable instead of buried in prose. Plain code.
4. Voice tone examples without exercises. Today Lt. Surge's example names chest-supported row, leg curl and incline walk, and those words recur in answers. Use a tone example about something else ("Done is done. Two more things, then home.").
5. Ask about the limitation at most once a day: only when `recent_exchanges` from today has not asked already.
6. Every load names its source: "38 kg, from Thursday's 41" or "start at 12 kg dumbbells, no record yet". The prompt half-says this; make it one clear line.
7. Close the session when they said they are nearly done ("you are done after the walk"), and give the way back for a feared lift with its criterion when the goal mentions it ("fine back means goblet squats next week").

How to check it: replay last week's real coach questions with their context as it was, old prompt against new, on Gemini, and read the pairs side by side. Seven questions, about 14 calls, a fraction of a cent.

## Sources

- Gemini 3.5 Flash-Lite prices: [maximem.ai](https://www.maximem.ai/blog/gemini-api-pricing-2026), [OpenRouter](https://openrouter.ai/google/gemini-3.5-flash-lite), [eesel.ai](https://www.eesel.ai/blog/gemini-3-5-flash-lite-pricing)
- Gemini 3.5 Flash prices: [evolink.ai](https://evolink.ai/blog/gemini-3-5-flash-pricing-guide), [modelcompare.dev](https://modelcompare.dev/models/google/gemini-3-5-flash)
- DeepSeek prices: [benchlm.ai](https://benchlm.ai/deepseek/api-pricing), [techjacksolutions](https://techjacksolutions.com/ai-tools/deepseek/deepseek-pricing/), [coworker.ai](https://coworker.ai/blog/deepseek-api-pricing)
- Kimi prices and retirement: [benchlm.ai](https://benchlm.ai/moonshot/api-pricing), [OpenRouter](https://openrouter.ai/moonshotai/kimi-k2.6), [Wikipedia](https://en.wikipedia.org/wiki/Moonshot_AI)
