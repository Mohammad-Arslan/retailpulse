# ADR-004: Frontend Architecture (React + Inertia)

Status: Accepted

Date: 2026-07-19

Related: [ADR-003 Backend Architecture](./adr-003-backend-architecture.md) · [ADR-010 Security](./adr-010-security.md) · [ADR-012 Development Standards](./adr-012-development-standards.md)

Implementation-level detail (exact component names, Tailwind class conventions, code-level patterns like the modal styling checklist or `ScrollArea` usage) lives in `.cursor/rules/retailpulse-frontend.mdc`, which implements this ADR. This document governs the architectural boundaries; the Cursor rule governs how to write the code inside them — do not duplicate one into the other when either changes.

---

## Why

RetailPulse's frontend has no separate API layer for page data and no client-side router — Inertia.js is the bridge that lets Laravel stay the single source of truth for routing, authorization, and data shape while still delivering a React SPA experience. This works well precisely because it is applied consistently; the moment one page starts fetching its own data via `fetch`/React Query, or a client-side router creeps in for "just this one section," the app has two competing models of navigation and data-loading, and every future page has to guess which model it's supposed to follow. This ADR is the authoritative frontend architecture document — it governs every admin page, component, and pattern going forward.

## What

### Technology stack

- **React 18** (project targets React 19-era APIs per `docs/phases/phase-02-platform-shell.md`; see `package.json` for the exact installed version) + **Inertia.js 2.0**
- **Tailwind CSS 4** — configured via CSS (`resources/css/app.css`, OKLch variables, `@plugin` directives); there is no `tailwind.config.js`
- **shadcn/ui** (New York style) + **Radix UI** primitives — component config in `components.json`
- **Lucide Icons**
- **i18next** (`react-i18next`) for translations
- **Laravel named routes** for all navigation — no hardcoded URL strings in JSX

### Architectural principles

- **Server-driven SPA using Inertia.** The page's props come from the Laravel controller; the React component renders them. There is no client-side data-fetching layer standing in for what Inertia already provides.
- **Laravel remains the source of truth** for data, validation, and authorization ([ADR-003](./adr-003-backend-architecture.md), [ADR-010](./adr-010-security.md)). The frontend is a rendering and interaction layer, not a second place where business rules are (re)implemented.
- **No client-side routing.** No React Router, no competing router of any kind. Navigation is `router.get/post/put/delete` (or `<Link>`) to a Laravel named route; the server decides what page renders next.
- **No REST CRUD fetching for normal pages.** A standard admin index/create/edit page receives its data as Inertia props from the controller render call — it does not additionally call a JSON API endpoint to hydrate itself. (The `/api/v1/...` routes that do exist — POS, checkout, search-as-you-type — are for genuinely interactive, sub-page-load behavior where a full Inertia visit would be the wrong tool; they are the exception, not the template for ordinary CRUD.)
- **No duplicated business logic in the frontend.** Tax calculation, discount threshold checks, leave balance rules, workflow logic — all of it stays server-side. The frontend may mirror a rule for *instant UI feedback* (e.g. disabling a submit button before the server round-trip) but the server-side check is always the real one, never bypassable by skipping the client-side mirror.
- **Thin UI layer.** A page component's job is to arrange components and wire `useForm`/`router` calls to named routes — not to contain multi-step business calculations.

## How

### Page structure

Every admin page follows this composition, top to bottom:

```
Page (resources/js/Pages/Admin/{Domain}/{Action}.jsx)
  ↓
Layout (HOC — e.g. AdminLayout)
  ↓
PageHeader (title, breadcrumbs, primary actions)
  ↓
FormCard / DataTable (the page's main content)
  ↓
Shared Components (resources/js/Components/common, admin)
  ↓
UI Components (resources/js/Components/ui — shadcn primitives)
```

A page file itself should read as an assembly of these layers with page-specific glue (props destructuring, `useForm` wiring, submit handlers) — if a page file is accumulating substantial rendering logic that isn't page-specific, that logic belongs one layer down as a shared or admin component.

### Inertia standards

- **`useForm`** from `@inertiajs/react` for every form — it gives validation error state, processing state, and dirty-checking for free; do not hand-roll form state with `useState` for what `useForm` already solves.
- **`router.get/post/put/delete`** for navigation and non-form actions (e.g. a table row action, a bulk operation trigger).
- **Laravel named routes** for every link/redirect target — never a hardcoded path string that will silently drift when a route changes.
- **Page props from Laravel** are the only source of server data for a page — don't introduce a parallel `fetch()`-based hydration path for data the controller could have passed as a prop.
- **No React Router. No Redux.**

### Component hierarchy

| Directory | Owns | Depends on |
| :--- | :--- | :--- |
| `Components/ui/` | shadcn/ui primitives (Button, Dialog, Table, Input, ScrollArea, etc.) — the design system's raw material | Radix primitives, Tailwind. Nothing domain-specific. |
| `Components/common/` | App-wide, domain-agnostic composites: `AppProviders`, `FlashToasts`, layout shells, `ScrollArea` wrapper | `Components/ui` only — never a domain component |
| `Components/admin/` | Domain-specific admin components (a product picker, a leave-balance widget, a branch selector) — things that know about RetailPulse's business entities | `Components/ui`, `Components/common`, `Hooks` |

Ownership rule: a component moves *down* this table (toward `ui/`) as it becomes more generic, and stays in `admin/` as long as it references a specific domain concept (a Product, an Employee, a Sale). Don't add business-domain awareness to something in `Components/ui/` to save an extra prop — extend or wrap it in `Components/admin/` instead, or the design-system layer stops being safely reusable across domains.

### Design system

Tailwind utility classes are the default styling mechanism; design tokens are OKLch CSS variables in `resources/css/app.css`, not ad hoc hex values inline in components. Primary/secondary/destructive button hierarchy, scrollable-region handling, icon set (Lucide), and dark mode are all governed centrally (see `.cursor/rules/retailpulse-frontend.mdc` for the exact class names and component APIs) — never overridden per-page. A new component must render correctly in both light and dark theme, not just the one the author happened to be looking at.

### Forms, tables, authorization, i18n — architectural rules

- **Modal vs. dedicated page** is a considered decision, not a default: a modal for simple config/master-data managed from an index page ("create/edit → stay on the list"); a dedicated page for standalone transactions, forms needing tabs/repeatable rows/file uploads, or a contextual info panel. Field count alone does not justify a page — a well-sectioned modal handles 15–20 fields fine. (Full styling checklist for each: `.cursor/rules/retailpulse-frontend.mdc`.)
- **Tables** (`DataTable` + `ListPagination`) are server-driven: filtering and sorting round-trip through the server via query-string Inertia visits, never client-side re-sorting/re-filtering of an already-paginated page — that would only ever be correct for the current page's rows.
- **Authorization** (`useCan()`) gates UI visibility/enabled-state and must always mirror an actual backend Policy ([ADR-010](./adr-010-security.md)) — adding a permission-gated UI affordance requires the backend Policy to exist first. **Never rely solely on frontend authorization**; hiding a button is not a security control.
- **Internationalization** (i18next) — every user-facing string is a translation key from the first version of a component, including breadcrumbs (`useBreadcrumbs.js`) and navigation labels (`NavigationRegistry`/`AdminNavigationCatalog`) — retrofitting i18n later is far more error-prone than authoring it correctly the first time.

### Frontend performance

Lazy-load genuinely large, rarely-needed bundles (not reflexively every component); extract shared components rather than forking near-duplicates; memoize selectively where render cost is real, not reflexively; keep client state minimal (`useState` for ephemeral UI state only — server state lives in Inertia props); when in doubt, compute derived data server-side and pass it as a prop rather than recomputing it client-side on every render.

### State management — official decision

- **Inertia page props** for server data.
- **`useForm`** for form state.
- **Local component state** (`useState`/`useReducer`) for ephemeral UI-only state.
- **No Redux. No Zustand. No React Query for standard CRUD.** Reverb/Echo (already in use for real-time dashboard/POS updates) is the sanctioned mechanism for push-style real-time data, not a client-side polling library. A genuine case for client-side caching/background-refetch that Inertia's model doesn't fit is a deliberate, narrow, explicitly-documented exception when it arises — not a default to reach for.

### UI consistency

Match RetailPulse's existing design language (spacing scale, typography scale, modal sizing conventions, button hierarchy) rather than introducing a new visual pattern for a single page — find the nearest existing analogous page and match it. Responsive layouts and WCAG 2.1 AA accessibility (backed by Radix primitives' built-in accessibility) are expected of every new page, not a follow-up task.

## Trade-offs

- **Full-page reloads are impossible to fully eliminate the feel of** without careful use of Inertia's partial reloads and preserved scroll/state — a page that doesn't tune these can feel less "app-like" than a hand-rolled SPA with a client-side cache. Accepted because the consistency and simplicity gained (no second data-fetching model, no client/server state duplication) outweighs the marginal transition polish a client-side router would buy.
- **No offline-first capability by default** — Inertia visits require a round trip. Where offline behavior is genuinely required (the POS cart queue, per Phase 7), it is built as a deliberate, scoped exception (IndexedDB cart queue) rather than a reason to move the whole admin frontend to a client-state-first model.
- **Server dictates page shape**, so a frontend-only redesign of a page's data needs (e.g. wanting a field the current controller doesn't pass) always requires a backend change too — by design, since [ADR-003](./adr-003-backend-architecture.md) keeps Laravel authoritative over data shape.

## Alternatives considered

- **A decoupled SPA consuming a REST/GraphQL API** — rejected: it would duplicate authorization and validation logic on both sides (the API must authorize independently of any UI-only check), require a second versioned contract layer for what is purely first-party UI, and reintroduce exactly the state-synchronization problems Inertia exists to avoid. (A public API for *external* consumers is a separate, deliberate surface — see [ADR-008](./adr-008-public-api.md) — not the internal admin frontend's data path.)
- **Next.js / server components** — rejected: it would mean two server runtimes (Laravel for business logic, Node for rendering) with data passed between them, adding an integration seam and a deployment component for no capability RetailPulse's admin/POS UI actually needs today.
- **React Query/SWR layered on top of Inertia "just for a few pages"** — rejected as a slippery slope: once one page has a client-side cache with its own staleness/invalidation rules, every future page has to ask which model it follows, which is precisely the drift this ADR exists to prevent. The Reverb/Echo real-time channel already covers the legitimate "I need fresher-than-page-load data" need.

## Future direction

All future frontend work continues on this React + Inertia architecture. Do not introduce a competing frontend framework (Vue, Svelte, a separate SPA framework), a competing routing system (React Router, a hand-rolled router), or a competing state-management library (Redux, Zustand, React Query for standard CRUD) for a new module, page, or "just this one feature" — including modules built by future phases (restaurant, e-commerce, mobile-adjacent web views). Phase 26's React Native mobile apps are a deliberately separate codebase/runtime for native mobile and do not imply the web admin/POS frontend should diverge from Inertia; this ADR governs the web frontend regardless of what native mobile does in parallel.

## Impact on future development

- A developer or AI agent opening any admin page for the first time can predict its structure, data flow, and state model before reading a line of it, because every page follows the same composition.
- Business logic bugs cannot hide in the frontend — if a rule needs to be right, it is checked server-side, and the frontend rule (if mirrored at all) is provably just a UX convenience.
- Adding a new module's frontend (restaurant KOT screens, e-commerce channel management, mobile-adjacent web views) is additive work within the established pattern, not a fresh architectural decision each time.
