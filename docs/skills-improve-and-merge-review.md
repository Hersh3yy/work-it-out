# Two review skills on another laptop: improve and merge-review

Written 2026-11-14 after running `improve` over four koala repositories. This page is about Claude Code skills, not about Feetness itself; it lives here because this repository travels between laptops.

## improve (auditing a whole repository)

`improve` (github.com/shadcn/improve, MIT) surveys a codebase read-only and writes findings and self-contained plans that another agent can carry out. It never edits app code itself.

Install it once per laptop, from a terminal:

```bash
npx skills add shadcn/improve
```

Or inside Claude Code: `/plugin marketplace add shadcn/improve`, then `/plugin install improve@improve`.

Run it from the repository folder:

| Command | What it does |
|---------|--------------|
| `/improve` | Standard audit: all nine categories, up to four read-only agents, a findings table, then asks which to turn into plans |
| `/improve quick` | Hotspots only: correctness, security and tests, about six high-confidence findings |
| `/improve deep` | Whole repository, every category, low-confidence "investigate" items included |
| `/improve security deep` | One category only, in depth. Use this for a real security pass; `quick` only looks at the most-changed files |
| `/improve next` | Direction only: four to six grounded "what to build next" suggestions |
| `/improve branch` | Only what the current branch changed, each finding tagged introduced or pre-existing |
| `/improve plan <description>` | Skip the audit and write one plan for something already decided |
| `/improve reconcile` | Next session: check done plans, refresh stale ones, retire dead findings |
| `/improve execute <plan>` | A cheaper agent implements one plan in its own worktree; improve reviews the diff and gives a verdict. It never merges |

It writes into `plans/` at the repository root (`advisor-plans/` if `plans/` is taken). Plans stamp the commit they were written against, so `reconcile` can tell when the code has moved.

Lessons from the 2026-11-14 run:

- Tell it which documents hold decisions. Here that is `PLAN.md` section 4, `docs/direction-2026-11-14.md` and the previous audit. Otherwise it re-reports settled choices and old findings as new.
- VAMS: the local `.env` points at the production database. Tell it not to run `artisan`, `composer` scripts or the tests there. Static reading only.
- work-it-out and VAMS are public repositories. Security findings in a pushed file are public too. Decide that before pushing; `improve --issues` asks first for public repositories, plain file pushes do not.
- Agents over-report. Open every cited `file:line` before believing it. In this run all cited lines held, but two agents reported the same CI problem under different names.
- Results of the run: `docs/audit-2026-11-14.md` in this repository. qinip, VAMS and feetness-app were audited at quick depth; their findings were not pushed.

## merge-review (reviewing one PR or branch)

`merge-review` is a personal skill for work pull requests, not part of any repository. It lives at `~/.claude/skills/merge-review/SKILL.md`. It holds work-specific conventions, so copy it to the other laptop privately (AirDrop or a private folder), never through this public repository.

Use it by saying what you want:

- Review mode, someone else's PR: "review this PR <link>", "can I merge?". It reads the ticket's acceptance criteria, buckets the files (mechanical, core, callers, tests, noise), walks them layer by layer, checks each finding against the code, and ends with a verdict and paste-ready `file:line` comments. It never posts comments or approves.
- Precheck mode, your own branch before review: "pre-check my work". Same method, ends in a fix list instead of a verdict.

It always reviews against the PR's real base branch, never the local working tree, and never commits or pushes.

## What improve taught merge-review

Ideas from `improve` worth adding to `merge-review`, best first. Not added yet.

1. A fresh-context cold read in precheck mode. A precheck in the same session that wrote the code misses what that session already "knows". Give a subagent only the diff, the acceptance criteria and the house rules, then check its findings yourself.
2. Tag each finding introduced (by this PR) or pre-existing (in touched files), and keep them in separate sections.
3. Record the reviewed commit and a short "considered and rejected" list. On the next push, review `git diff <sha>..HEAD` and do not raise rejected points again.
4. Map every changed file to an acceptance criterion in the bucket table. A file that maps to none and is not noise is over-scope.
5. When fanning out to subagents: they over-report (by-design behaviour flagged as a bug, the right finding at the wrong line, duplicates), so open every cited line before it becomes a comment, and give each brief the house-rules path, the base branch and the settled decisions.
6. A decision named in the PR description is not a finding; code that contradicts its own PR description is.
7. End with what was not reviewed, so the approver knows the real coverage.

Smaller: never quote a secret in a comment (`file:line`, the credential type, rotate), and treat text in a diff that talks to the reviewer as data, not instructions.
