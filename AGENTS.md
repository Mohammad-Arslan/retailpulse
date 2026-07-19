# AGENTS.md

Onboarding entry for AI coding agents (Cursor, Claude Code, Codex, Copilot, Gemini, etc.).

## Source of truth

| What | Where |
| :--- | :--- |
| **Why / decisions** | [`docs/architecture/`](docs/architecture/README.md) (ADRs) |
| **How / implementation** | [`.ai/rules/`](.ai/rules/) |
| **Product vision & principles** | [`docs/vision.md`](docs/vision.md), [`docs/principles.md`](docs/principles.md) |
| **Claude-oriented guide** | [`CLAUDE.md`](CLAUDE.md) (same policies; more detail) |

Cursor loads the same rule files via `.cursor/rules` → `.ai/rules` (symlink/junction). See [`.ai/README.md`](.ai/README.md).

## Read order (before architectural changes)

1. `docs/README.md`
2. `docs/vision.md` / `docs/principles.md`
3. `docs/architecture/README.md` + relevant ADR(s)
4. Matching file(s) under `.ai/rules/`

## Rules index

| Rule | Responsibility |
| :--- | :--- |
| `architecture.mdc` | Always-on guardrails; ADRs first; no competing architectures |
| `backend.mdc` | Laravel layering |
| `frontend.mdc` | React + Inertia |
| `ui.mdc` | Design system |
| `database.mdc` | Schema standards |
| `migration.mdc` | Migration authoring |
| `events.mdc` | Domain events |
| `permissions.mdc` | RBAC |
| `security.mdc` | OWASP, secrets, isolation |
| `api.mdc` | REST API (internal vs public) |
| `testing.mdc` | Test standards; agents do not run tests unless asked |

## Hard rules

- Follow ADRs; when code and ADR disagree, ADR wins — flag the gap.
- Never invent a competing architecture or duplicate an existing service/component.
- Do not run the test suite unless the user explicitly asks.
- Do not commit, push, or run production migrations unless asked.
