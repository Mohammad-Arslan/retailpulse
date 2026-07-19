# CLAUDE.md

This file is the **AI onboarding guide** for RetailPulse. It is not the architecture reference and not the coding-standards reference — those live elsewhere, and this file exists to send you to them in the right order rather than repeat them. What follows is how to approach this codebase before, during, and after writing code in it.

## Required reading order, before writing code

1. **[docs/README.md](docs/README.md)** — what RetailPulse is and how its documentation fits together.
2. **[docs/vision.md](docs/vision.md)** — why RetailPulse exists, what it's becoming.
3. **[docs/principles.md](docs/principles.md)** — the non-negotiable engineering principles behind every architectural decision.
4. **[docs/architecture/README.md](docs/architecture/README.md)** — the Architecture Bible: the index of every binding Architecture Decision Record (ADR).
5. **The specific ADR(s) relevant to your task** (`docs/architecture/adr-NNN-*.md`) — read these fully, not just skimmed, before touching anything the ADR governs (new tables, new modules, new cross-cutting concerns, new API surfaces, new frontend patterns, tenancy, security).
6. **The relevant implementation rule(s)** ([`.ai/rules/*.mdc`](.ai/rules/)) — file placement, exact patterns, checklists for the layer you're editing. Each rule states which ADR(s) it implements. Cursor loads the same files via `.cursor/rules` → `.ai/rules` (see [`.ai/README.md`](.ai/README.md) and [`AGENTS.md`](AGENTS.md)).

Skipping straight to step 6 for anything architecturally significant is how drift happens — the rules tell you *how* to write code that's already been decided to work a certain way; they don't carry the *why*, the trade-offs, or the alternatives that were already rejected, which is exactly the context you need to extend a pattern correctly instead of by accident.

For a small, clearly-scoped change (a copy fix, a bug fix confined to one function, adding a field to an existing, well-understood pattern), reading the nearest existing analog in the code plus the relevant rule is normally sufficient — you don't need to re-read the whole Architecture Bible for every one-line change. Use judgment on scope, but when in doubt, read one level higher than you think you need to.

## How to approach the work

- **Understand the existing implementation before changing it.** Read the module you're touching — its Service, its tests, its nearest sibling feature — before writing new code. A change that "looks right" without that context is exactly how a codebase this size accumulates quiet inconsistency.
- **Follow architecture before implementation.** If the fastest path to a working feature conflicts with an ADR, the ADR wins. A working feature that violates the architecture isn't done — it's a defect that happens to run.
- **Prefer extending existing modules and services over introducing new patterns.** If a `LeaveService`-shaped problem already has a `LeaveService`, your job is very often to extend it, not to write a parallel implementation because the existing one wasn't convenient to read at 2am.
- **Never introduce competing architectures.** No second state-management library, no parallel service-layer pattern, no second audit mechanism, no second event-dispatch convention. One way to do a thing, per [docs/architecture/](docs/architecture/README.md).
- **Never duplicate business logic.** A rule that already lives in a Service is referenced or called, not re-derived in a Controller, a second Service, or the frontend "for convenience."
- **Reuse existing services, repositories, DTOs, and components.** Before writing a new one, check whether an existing one already does most of what you need — extend it, don't fork it.
- **Follow module boundaries** ([ADR-002](docs/architecture/adr-002-modular-monolith.md)). Read another module's data through its service/repository; don't reach into its models directly to mutate state you don't own.
- **Preserve backward compatibility whenever possible.** A published API contract, a live migration, an event payload other code depends on — none of these break silently. See [ADR-008](docs/architecture/adr-008-public-api.md) and [ADR-015](docs/architecture/adr-015-database-standards.md) for the specific mechanisms (versioning, expand-then-contract migrations) when a change is unavoidable.
- **Maintain consistency across backend, frontend, database, and documentation together.** A schema change that isn't reflected in the Service, a frontend page whose props silently drift from what the controller sends, or a shipped feature with no phase-doc/user-manual update ([ADR-012](docs/architecture/adr-012-development-standards.md)) are all the same kind of defect — the work isn't finished until all four are consistent.

## When architecture and implementation differ

If you find code that doesn't match a documented ADR:

- **Explain the inconsistency.** Say what you found and which ADR it deviates from.
- **Do not silently "fix" the code to match your own assumption of what's correct**, and do not silently extend the deviation further because it was already there. Either flag it for the user to decide how to handle, or — if the task explicitly calls for resolving it — fix it deliberately and call out that you did.
- **Check `docs/gaps/gaps.md` and phase-specific gap docs** (e.g. `docs/phases/phase-12/gaps.md`) — the deviation may already be a known, deliberately-deferred gap, not an oversight. If it's not documented and you're confident it's a real gap, add it rather than leaving it invisible for the next contributor.

## Proposing an architectural improvement

If you believe an ADR itself should change:

1. **Justify the change** — state the concrete problem with the current decision, not just a preference.
2. **Explain the trade-offs** — every ADR in this codebase already documents what it gives up for what it gains; a proposed change needs to reckon with that, not ignore it.
3. **Update the ADR if the decision changes** — see [docs/architecture/README.md](docs/architecture/README.md#changing-a-decision) for the process. An improvement that isn't reflected back into the ADR isn't actually adopted — it's a one-off deviation waiting to confuse the next contributor, human or AI.

Do not ship an architectural deviation inside an unrelated feature PR "because it seemed better" — raise it explicitly.

## Commands

### Development

```bash
composer setup          # One-time setup: install deps, copy .env, migrate, npm install, build
composer dev            # Run all services concurrently (Laravel server, queue, pail, Vite, Reverb)
```

### Frontend

```bash
npm run dev             # Vite dev server (hot reload)
npm run build           # Production build
```

### Backend

```bash
php artisan serve       # Laravel dev server (standalone)
php artisan reverb:start  # WebSocket server (port 8080)
php artisan queue:listen  # Queue worker
php artisan pail        # Stream logs
```

### Testing

```bash
composer test                       # Run full PHPUnit suite
php artisan test                    # Same via artisan
php artisan test --filter=TestName  # Run a single test
php artisan test tests/Feature/     # Run a specific directory
```

Per `.ai/rules/architecture.mdc` / `testing.mdc`: do not run these yourself unless explicitly asked — state what to run and let the user execute it.

### Code Quality

```bash
./vendor/bin/pint                   # Lint and auto-fix PHP (Laravel Pint)
./vendor/bin/pint --test            # Check without fixing
```

### Database

```bash
php artisan migrate                 # Run pending migrations
php artisan migrate:fresh --seed    # Fresh database with seeders
php artisan tinker                  # REPL for debugging
```

Do not run migrations against a shared/production database, and do not create migrations that get auto-applied — a human applies them deliberately.

## Where things are (quick reference — see the architecture docs for why)

| Looking for | Location |
| :--- | :--- |
| Architecture decisions (the *why*) | `docs/architecture/` |
| Coding conventions (the *how*) | `.ai/rules/*.mdc` (Cursor: `.cursor/rules` → same files) |
| Multi-agent entry | `AGENTS.md` |
| Requirements spec | `docs/srs.md` |
| Phase-by-phase roadmap and schema | `docs/phases/` |
| What's actually built right now | `docs/implementation-status.md` |
| Known gaps between design and implementation | `docs/gaps/gaps.md`, `docs/phases/phase-12/gaps.md` |
| Admin routes | `routes/admin.php` |
| API routes | `routes/api.php` |
| Shared Inertia props | `app/Http/Middleware/HandleInertiaRequests.php` |
| Admin navigation | `app/Services/Navigation/Catalog/AdminNavigationCatalog.php` |
| i18n strings | `resources/js/locales/en.json` |
| User manuals (keep in sync per `.ai/rules/architecture.mdc`) | `docs/user-manual-*.md` |
| Import/export framework | `routes/import-export.php`, `app/Services/ImportExport/` |

## Environment

Required `.env` values beyond defaults:
- `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD` — seeder uses these for initial admin account
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` — WebSocket auth (defaults to `retailpulse`)
- `BROADCAST_CONNECTION=reverb` — must be set for real-time features to work
