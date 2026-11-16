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

## Later the same day: a fresh start for the game

Hiren: "if we need to rebuild the rpg features thats okay, see this as a fresh start. i dont mind that each coach has their stat." So the old stat columns, `RpgStatSheet` and `custom_rpg_stats` are not a constraint. They can be replaced. What stays is that each coach owns one stat.

Both presentations were remade that evening: the project map now runs in story order (what he wanted, what works, the trouble, the inside, the road ahead) and uses the real words instead of story names for the system's parts; both have a new look. The links are unchanged.

## Other chat apps: Signal and WhatsApp

Asked by Hiren on 14 November. Checked the same day; the sources are mostly third-party pages, so check the official ones before building.

Signal works, unofficially. Signal has no bot interface. A bot runs through `signal-cli`, an unofficial client, on its own phone number. It costs nothing but the number. The risks: it can stop working when Signal changes its protocol, so it needs regular updates, and an account that behaves like a machine can be banned. There are no bot comforts: no command menu, no buttons, no invite link that carries a code. In this code a chat app is a small adapter: `ChatChannel::send` out, and something that turns an incoming message into `InboundMessage` (Telegram's is in `app/Channels/Telegram`). A Signal adapter is a contained piece of work, plus `signal-cli` running as one more process inside the container, never reachable from outside.

WhatsApp is tougher and not free for what this bot does. It only goes through Meta's WhatsApp Business Platform: a Meta business account, a dedicated number, and approved templates for every message the bot sends first. Since July 2025 Meta bills per template message. Replies within 24 hours of the person's last message have been free (some sources say that changes on 1 October 2026; not confirmed). The 8 o'clock morning message is sent first, so it is a paid template unless the person wrote in the last 24 hours. And since 15 January 2026 Meta's terms ban general-purpose AI assistants from the platform; AI inside a business's own service is still allowed. A coach bot is arguably the second kind, but Meta decides. The EU has charged Meta over that rule and the outcome is open.

So: Telegram stays the first door. Signal is a sensible second door once handle-only sign-up exists, because it suits people who care about privacy. WhatsApp comes last, if ever.

## 15 November: the creature-collector idea

Hiren's concept, decided on 15 November, on top of the direction above:

- The person is a trainer. Their body qualities are the creatures they raise. A goal with a date is a battle, and each battle needs certain creatures levelled up, like a type matchup in a creature-collector game (the Pokémon loop is the reference).
- The reference is the mechanic, never the look or the names: no Pokémon names, types, gym badges or art in the product. Original names only.
- The three coaches are the three types, one master each, two creatures per type. Lt. Surge masters the energy that lifts (strength, power); Shen the energy that lasts (endurance, core); Latika the energy that heals (mobility, recovery). Hiren described them with fire and fighting, psychic and lightning, grass and water; those element words are a reference for the creatives, not product words. Working energy names: Heat, Current, Root.
- Type interactions are real rules, each with a source in a method card: overload against recovery, the interference between hard strength and endurance work in one week, gains landing after rest.
- Working creature names, direction A "the crew": Anvil, Flint, Drift, Keel, Willow, Ember.
- Three numbers per creature. Level is training history and only goes up; rest and injury never cost it. Power is the current measure from a method card over a recent window and goes up and down. HP is today's readiness: it drops the day after that quality worked, recovers over a day or two, is lowered by poor sleep, illness and injury, and is owned by Latika; it never blocks training, it only lightens the day. Battle readiness reads power; promotions and the chronicle read level; the gap between the two is the comeback story.
- The code keeps plain words (Energy, Quality, Goal, readiness); a theme words them at the edge later. Phase 2 on branch `game-foundation` builds that plain foundation.
