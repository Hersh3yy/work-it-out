# Code tour: read Feetness with your own eyes

One message, followed from your phone to the database and back. Read the files in this order; each stop says what to look for. Set aside an hour for stops 1 to 9, another for 10 to 14. Keep `.claude/skills/clean-code-check/SKILL.md` open beside it: that is the standard you are auditing against.

## Part 1: the message's journey

1. **The poller** `app/Console/Commands/TelegramPoll.php`. Long polling: ask Telegram for new messages, hand each to the channel, remember the offset. Look for: what happens when a reply fails (at-most-once, see PROJECT.md "Hard parts").
2. **The Telegram adapter** `app/Channels/Telegram/TelegramChannel.php` and `TelegramClient.php`. Turns Telegram's JSON into an `InboundMessage` (`app/Channels/InboundMessage.php`). Nothing else in the app knows Telegram exists.
3. **The front desk** `app/Channels/HandleInboundMessage.php` (195 lines). Read `handle()` and `freeText()` top to bottom: stranger, command, open-question answer, interpreter, then log, limitation or coach. Look for: does every branch only route? Any work done here that belongs in an action?
4. **Commands** `app/Conversation/Commands/` and the list in `config/conversation.php`. One class per slash command. Look for: adding a command touches one new file and one config line.
5. **Answers without AI** `app/Conversation/Answers/` (`AnswerChain.php` first, then the four handlers). Look for: each handler says "not mine" (returns null) fast and early.
6. **The interpreter** `app/Interpretation/Interpreter.php` (117 lines). Danger words (`app/Limitations/RedFlags.php`), classify (`RuleClassifier.php`, then the model through `app/Contracts/Ai/MessageClassifier.php`), parse, then the named rules in `app/Interpretation/Rules/`. Look for: it saves nothing; `Interpretation.php` is the only thing it returns.
7. **Writing a log** `app/Actions/SmartLog/RecordSmartLog.php` (260 lines, the biggest action). One transaction, then the `LogRecorded` event after commit. Look for: whether it is still one job, or wants splitting (sessions, entries, sets).
8. **After the write** `app/Events/LogRecorded.php`, `app/Listeners/RefreshTrainingStats.php`, `app/Listeners/RefreshProfile.php`. Look for: nothing in the action calls these directly.
9. **The receipt** `app/Channels/ReplyComposer.php`, `app/Limitations/LimitationCheck.php`, `app/Profile/ProfileCheckIn.php`. Look for: wording lives here, not in the actions.

## Part 2: injuries and the coach

10. **Injuries** `app/Conversation/LimitationConversation.php`, then `app/Limitations/ExistingLimitation.php`, `app/Actions/Limitations/RecordLimitation.php`, `RecordLimitationUpdate.php`, `app/Limitations/RedFlagScreen.php`. Look for: new versus update decided in plain code, before anything is saved.
11. **What the coach knows** `app/Coach/CoachContext.php` (131 lines). Every key in the context and where it comes from. Look for: anything the model gets that is not a stored fact or your own words.
12. **How the coach answers** `app/Actions/Coach/AnswerCoachQuestion.php`, `app/Coach/AnswerDepth.php`, `app/Ai/Agents/CoachAgent.php` (the prompt), `app/Enums/TrainerPersona.php` (the three voices). Look for: rules in the prompt that plain code could enforce instead.
13. **The AI sockets** `app/Contracts/Ai/` (ports) next to `app/Ai/` (Gemini adapters) and `tests/Fakes/` (test fakes), bound in `app/Providers/AppServiceProvider.php` and `tests/TestCase.php`. Look for: no class outside `app/Ai` imports the AI SDK. One known exception: `app/Models/User.php:18` uses the SDK's `HasConversations` for the older HTTP trainer chat.
14. **The written profile** `app/Profile/ProfileFacts.php` (numbers in PHP), `app/Profile/UpdateProfile.php` (the citation check), `app/Ai/Agents/ProfileAgent.php`. Look for: a note can never survive without a citation.

## Part 3: proof

- One test file per stop: `tests/Feature/Channels`, `Interpretation`, `SmartLog`, `Limitations`, `Coach`, `Profile`. Read a test before its class: the test names are the spec.
- Run the path yourself, no phone needed: `docker compose exec app php artisan log:simulate --user=1 "squat 5x5 100"`, then `"/undo"`.
- Only the interpreter, saving nothing: `docker compose exec app php artisan log:parse "squat 5x5 100"`.

## What to write down

For each stop: one line if something surprised you, made you scroll back, or needed a comment to understand. Those lines are the next clean-code pass. Put them in `PROJECT.md` under Issues with the file and line.

## Older code you can skip on a first read

Nutrition (`NutritionParser`, `nutrition_logs`), the RPG stats (`custom_rpg_stats`), `app/Ai/Agents/PlanAgent.php`, `TrainerAgent.php` and the HTTP trainer chat, and the classifier and intent labs (`app/Interpretation/Lab`). They predate the current design; M3 removes nutrition.
