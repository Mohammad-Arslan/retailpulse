# RetailPulse Documentation

RetailPulse is a multi-branch, multi-tenant retail and restaurant ERP — POS, inventory, procurement, accounting, HR/payroll, CRM/loyalty, and platform services (workflow, integrations, BI) unified in one Laravel + React/Inertia application. This directory is the complete documentation set for what RetailPulse is, why it's being built the way it is, and how to build on it correctly.

## Reading order

If you're new to RetailPulse — human contributor or AI coding agent — read in this order:

1. **[vision.md](./vision.md)** — what RetailPulse is becoming and why it exists. Read this first; it's the context every other document assumes.
2. **[principles.md](./principles.md)** — the non-negotiable engineering principles every architectural decision derives from.
3. **[architecture/README.md](./architecture/README.md)** — the Architecture Bible: the authoritative index of every binding architectural decision (the ADRs).
4. **The specific ADR(s)** relevant to the area you're touching (`architecture/adr-NNN-*.md`).
5. **[roadmap-philosophy.md](./roadmap-philosophy.md)** — how the phase roadmap works, if you're planning or sequencing new scope.
6. **`srs.md`** — the full requirements specification, if you need the detailed *what* for a specific module.
7. **`phases/`** — the phase-by-phase delivery plan, for the concrete schema/service/API design of a specific slice of scope.
8. **[`.ai/rules/*.mdc`](../.ai/README.md)** — implementation-level coding conventions once you know *what* you're building and *why* (Cursor loads these via `.cursor/rules` → `.ai/rules`).
9. **`CLAUDE.md` / `AGENTS.md`** (repo root) — if you are an AI coding agent, these are your operational onboarding guides: what order to read things in, and how to behave once you start writing code.

## What lives where

| Document | Answers |
| :--- | :--- |
| [vision.md](./vision.md) | Why does RetailPulse exist? What is it becoming over the next decade? |
| [principles.md](./principles.md) | What engineering principles are non-negotiable, regardless of module? |
| [architecture/](./architecture/README.md) | What are the binding architectural decisions (ADRs), and why? |
| [`.ai/rules/`](../.ai/README.md) | How should code be written inside those decisions? |
| [roadmap-philosophy.md](./roadmap-philosophy.md) | How does the roadmap evolve? How is technical debt managed? |
| `srs.md` | What must the system do, per module, per SRS section? |
| `phases/` | What ships in each phase, with what schema, services, and acceptance criteria? |
| `implementation-status.md` | What's actually built right now, as of the last audit? |
| `gaps/`, `phases/phase-12/gaps.md` | Where does the current implementation knowingly diverge from the intended design? |
| `user-manual-*.md` | How does an end user (not a developer) use a shipped feature? |
| `generic-import-export.md` | How does the bulk import/export framework work, mechanically? |

## What is authoritative

When documents disagree, resolve in this order:

1. **An ADR** ([architecture/](./architecture/README.md)) is authoritative over any implementation detail it governs — including over the SRS and phase docs, where those describe *how* something is built rather than *what* it must do. See [architecture/README.md](./architecture/README.md#changing-a-decision) for how to formally change an ADR.
2. **The SRS** (`srs.md`) is authoritative over *what* the system must do, per module.
3. **Phase docs** (`phases/`) are authoritative over the specific, concrete schema/service/API design for their slice of scope, as long as they don't conflict with an ADR — if they do, the ADR wins and the phase doc is updated.
4. **`implementation-status.md`** is a snapshot, never authoritative over what *should* be true — treat it as evidence about current reality, not as a spec.

If you find two documents genuinely contradicting each other with no clear precedence above, that's a documentation bug — flag it rather than silently picking one and moving on.

## How to use this documentation

- **Before an architectural change** (new table, new module, new cross-cutting concern, new API surface, new frontend pattern): read the relevant ADR(s) first. If none exists, that's a signal a new ADR is needed, not that you're free to improvise.
- **Before a feature within an existing pattern** (a new CRUD resource in an established module, a new report, a new page following an existing template): the phase doc plus the nearest existing analog in the codebase is usually sufficient; the ADRs are context, not a checklist to re-derive from scratch every time.
- **When something in the code doesn't match the docs**: figure out which is stale. If the code deviates from an ADR without a recorded reason, that's a bug to flag (see `gaps/gaps.md`) — not evidence the ADR is wrong. If the ADR is genuinely outdated, propose the update through the process in [architecture/README.md](./architecture/README.md#changing-a-decision).
- **When writing new documentation**: put architectural decisions in an ADR, module scope in a phase doc, and end-user instructions in a user manual — don't blend the three into one document, or the "what's authoritative" ordering above stops working.
