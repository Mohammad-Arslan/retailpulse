Before making any architectural or implementation decisions, read the project documentation in the following order:

1. `docs/vision.md`
2. `docs/principles.md`
3. `docs/architecture/README.md`
4. The **applicable** ADR(s) under `docs/architecture/` for the change at hand (not every ADR on every prompt)
5. The **relevant** implementation rule(s) under `.ai/rules/` (Cursor loads the same files via `.cursor/rules` → `.ai/rules`)

These documents are the authoritative source of truth.

- **ADRs** (`docs/architecture/`) decide *what* and *why*.
- **Rules** (`.ai/rules/`) decide *how* to implement inside those decisions.
- If implementation conflicts with an ADR or documented architectural decision, **follow the documented architecture** unless explicitly instructed otherwise.
- Do not introduce new architectural patterns without documenting and justifying them (see `docs/architecture/README.md` → Changing a decision).

**Scope judgment:** for a small, clearly scoped change (copy fix, one-function bugfix, extending an existing well-understood pattern), reading the nearest analog in code plus the matching rule is enough — you do not need to re-read vision, principles, and every ADR. When in doubt, read one level higher.
