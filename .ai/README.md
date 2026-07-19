# RetailPulse — AI Assistant Guidance

Tool-agnostic implementation rules and entry points for AI coding assistants (Cursor, Claude Code, Codex, Copilot, Gemini, etc.).

## Layout

```
.ai/
├── README.md          ← this file
└── rules/             ← implementation HOW (Cursor Rule format: .mdc)
    ├── architecture.mdc
    ├── backend.mdc
    ├── frontend.mdc
    ├── database.mdc
    ├── events.mdc
    ├── permissions.mdc
    ├── testing.mdc
    ├── ui.mdc
    ├── api.mdc
    ├── migration.mdc
    └── security.mdc

docs/architecture/     ← architectural WHY (ADRs) — authoritative
CLAUDE.md              ← Claude Code / Cursor onboarding
AGENTS.md              ← thin entry for other agents
.cursor/rules/         ← symlink (or junction) → ../.ai/rules
```

## Rules vs ADRs

| Layer | Owns | Location |
| :--- | :--- | :--- |
| **Architecture (WHY / WHAT)** | Decisions, trade-offs, alternatives | `docs/architecture/adr-*.md` |
| **Implementation (HOW)** | File placement, checklists, code patterns | `.ai/rules/*.mdc` |
| **Onboarding** | Read order, how to reconcile conflicts | `CLAUDE.md`, `AGENTS.md` |

Do not copy ADR rationale into rules. Rules reference ADRs (`Implements: ADR-00N`) and stay concise.

## Cursor wiring

`.cursor/rules` should resolve to `.ai/rules` (directory symlink or Windows junction) so Cursor loads the same files every other tool reads. If the link is missing, recreate it:

```powershell
# Windows (Developer Mode or elevated shell)
Remove-Item -Recurse -Force .cursor\rules -ErrorAction SilentlyContinue
New-Item -ItemType Junction -Path .cursor\rules -Target (Resolve-Path .ai\rules)
```

```bash
# macOS / Linux
rm -rf .cursor/rules && ln -s ../.ai/rules .cursor/rules
```

## Loading guidance for agents

1. Read `CLAUDE.md` or `AGENTS.md` first.
2. Read the ADR(s) relevant to the change.
3. Load only the rule file(s) for the layer you are editing (globs / frontmatter describe when each applies).
4. When architecture and code disagree, the ADR wins — flag the gap; do not silently deepen it.
