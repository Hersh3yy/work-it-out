---
name: clean-code-check
description: Check Feetness backend changes against the project's design patterns and the "reads without comments" rule before committing. Use after writing or changing PHP in work-it-out, when Hiren asks whether code follows the patterns or is clean, or before any push to master.
---

# Clean code check (Feetness)

Hiren's standard: if he reads a class top to bottom he can follow what happens without needing the comments. Comments say why, never what. Run this on the diff (`git diff master...` or the uncommitted changes) before every commit.

## 1. Put each piece where its pattern says

| What it is | Where it goes | Pattern |
|---|---|---|
| Anything that calls a model | a port in `app/Contracts/Ai`, an adapter in `app/Ai`, an agent in `app/Ai/Agents`, a fake in `tests/Fakes` bound in `tests/TestCase.php` | Adapter |
| Anything that writes | an action in `app/Actions/<Area>` with `handle()`: one transaction, then an event after commit | Command |
| Side effects of a write (stats, profile) | a listener on `LogRecorded` / `LogReverted` | Observer |
| A plain-message answer to an open question | an `AnswerHandler` in `app/Conversation/Answers`, listed in `config/conversation.php` | Chain of Responsibility |
| A slash command | a `ChatCommand` in `app/Conversation/Commands`, listed in `config/conversation.php` | Command |
| A known trap in what the model returned | an `InterpretationRule` in `app/Interpretation/Rules` | named rule |
| A choice between behaviours (coach voice, answer depth, question kind) | an enum or class per option with the behaviour on it, never a `switch` in the caller | Strategy |
| Numbers (scores, records, trends) | plain PHP (`ProfileFacts`, `StatSheet`), never the model | |

The facade (`HandleInboundMessage`) only routes. If it grows a new private method that does work, that work belongs in one of the places above.

## 2. Read it as Hiren would

- Names say what the thing is in the domain: `LimitationConversation`, `todaysQuestions()`, `ASKS_WHY_OR_HOW`, not `Helper`, `process()`, `PATTERN1`.
- A method does one thing at one level. More than about 25 lines, or a closure longer than 5 lines inside `map()`: extract a named method.
- Branches read as a sentence: `return $existing === null ? $this->noteNew(...) : $this->noteUpdate(...)`.
- No magic strings shared between files: a public constant on the class that owns it (`RecordLimitation::AREA_QUESTION_FIELD`, `RedFlagScreen::FIELD`).
- No re-querying what you just created; pass it back (`setRelation`).
- Docblocks: one or two sentences of why or of a non-obvious contract. Delete docblocks that repeat the signature.
- Constructor with more than about 8 dependencies: the class does too much; split by responsibility.
- `final readonly` classes, `declare(strict_types=1)`, typed everything, enums instead of string sets.

## 3. Prove it

- A test per behaviour that runs with zero AI calls (fakes) on both engines: `vendor/bin/pest` and `vendor/bin/pest --configuration=phpunit.pgsql.xml`.
- `vendor/bin/pint` clean.
- For model-facing changes, one real run through `php artisan log:simulate --user=1 "..."` and note what came back in the diary.

## 4. Report

List what you changed to meet the above, file by file, in plain sentences. Anything you saw but did not fix goes in `PROJECT.md` Issues with a file and line.
