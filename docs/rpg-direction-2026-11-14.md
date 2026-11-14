# The game direction and the two presentations, 14 November 2026

Written for the next session on the other laptop. Nothing here is built.

## The two presentations

Both are private pages on claude.ai under Hiren's account. Other people cannot open them until he shares them from the page's Share menu.

- Feetness Project Map, for Hiren, 15 slides: what Feetness is, one message through the system, the class diagram, the verdict on the advice with its numbers, safety, the app, the road map, five choices. https://claude.ai/artifact/ExS4DFpfRs6YWpL7618XvZ
- Feetness RPG Brainstorm, for the session with the creatives, 14 slides and no code: what a player does, the idea in Hiren's words, what exists, a translation table from game words to real life, the core loop, a boss fight, raids and raves, what no theme may change, what is open, three starter worlds, five traps, privacy, six questions. https://claude.ai/artifact/6xrcqXaAVKkfG19ZiG2vb4

Both were made with the `explain-like-im-nami` skill. Hiren will give feedback on the skill before it is shared with colleagues.

## The idea, in Hiren's words

"this should be closer to an rpg. high level idea is that you work on your body as a video game character and you get advice on how to improve the character, what talent points to invest in, how to down that boss, how to win that battle, how to stay calm through it, how to be prepared for times of intensity on the way (a rave, a raid)."

The specific theme comes later and may come from people better at that than he is. He wants to be ready for that conversation.

## What exists for it today

- Three stats per person in the database (`users.rpg_strength`, `rpg_stamina`, `rpg_vitality`), each with a default of 1. Nothing in `app/` writes to them, so every player still stands at 1. `RpgStatSheet` and `StatsController` return them; the app shows none of it.
- A table for custom stats (`custom_rpg_stats`), unused.
- Each coach is tied to one stat in `TrainerPersona::rpgCategory()`: Lt. Surge to strength, Shen to stamina, Latika to vitality.
- The original plan (`PLAN.md` section 2) already showed a receipt line like "STR 12 (+2)". It was never built.

## How the game words map onto what is planned

- A stat is a measure from a method card (e1RM for strength, the 12-minute test and weekly minutes for stamina).
- A boss is a goal with a date, which is roadmap item n36 and method card 4.
- A quest is the coach's morning message.
- A debuff is a limitation with its day counter.
- A talent point is where the next block of training goes. Nothing exists for it.

So the game layer does not need a second engine. It is a way of showing and wording what the method cards compute.

## What no theme may change

Numbers are real and computed by code. Rest and healing never cost progress. No diagnosis, and warning signs stop everything. Logging stays one line of text. The coaches speak from the data and say when they do not know.

## Privacy: "a Telegram id is all I need, right?"

Not legal advice. A disclaimer that tells people not to enter identifiable data does not remove the duties. A Telegram id is itself personal data under European law, an injury note tied to it is health data, and people will type identifying things whatever a disclaimer says. But the design behind the question is right and gets most of the way:

- A character name instead of a real name, and no e-mail. Today `users.name` and `users.email` are required and `channel:invite` takes an e-mail, so this is a change.
- The AI provider gets the training text and nothing that says whose it is: no id, no name. `CoachContext` sends the name today. Since the EU court's ruling in EDPS v SRB (4 September 2025), data that a recipient cannot link to a person may not be personal data for that recipient. That helps the DeepSeek question. It is untested for a setup like this one, and free text can still identify someone.
- Still needed before a second person joins: a short privacy notice, a clear yes to storing injury notes, a command that deletes everything, and HTTPS on the API.

## Next, in order, once Hiren says go

1. The creative session, with the second presentation. Its output decides names, world and tone.
2. Handle-only sign-up: character name, invite by code, no e-mail; the name out of what goes to the model; a delete command.
3. The stats wake up: each stat moves from a method card's measure, in plain code with tests.
4. Bosses: goals with dates (n36), then the planning from method card 4.
