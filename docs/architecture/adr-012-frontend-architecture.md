# ADR-012: Frontend Architecture (React + Inertia)

Status: Accepted

Date: 2026-07-19

Related: [ADR-004 Layered Architecture](./adr-004-layered-architecture.md) · [ADR-010 Security Principles](./adr-010-security-principles.md) · [ADR-011 Development Standards](./adr-011-development-standards.md)

---

# Context

RetailPulse's frontend has no separate API layer for page data and no client-side router — Inertia.js is the bridge that lets Laravel stay the single source of truth for routing, authorization, and data shape while still delivering a React SPA experience. This works well precisely because it is applied consistently; the moment one page starts fetching its own data via `fetch`/React Query, or a client-side router creeps in for "just this one section," the app has two competing models of navigation and data-loading, and every future page has to guess which model it's supposed to follow. This ADR is the authoritative frontend architecture document — it governs every admin page, component, and pattern going forward.

# Decision

## Technology stack

- **React 18** (project targets React 19-era APIs per `docs/phases/phase-02-platform-shell.md`; see `package.json` for the exact installed version) + **Inertia.js 2.0**
- **Tailwind CSS 4** — configured via CSS (`resources/css/app.css`, OKLch variables, `@plugin` directives); there is no `tailwind.config.js`
- **shadcn/ui** (New York style) + **Radix UI** primitives — component config in `components.json`
- **Lucide Icons**
- **i18next** (`react-i18next`) for translations
- **Laravel named routes** for all navigation — no hardcoded URL strings in JSX

## Architectural principles

- **Server-driven SPA using Inertia.** The page's props come from the Laravel controller; the React component renders them. There is no client-side data-fetching layer standing in for what Inertia already provides.
- **Laravel remains the source of truth** for data, validation, and authorization ([ADR-004](./adr-004-layered-architecture.md), [ADR-010](./adr-010-security-principles.md)). The frontend is a rendering and interaction layer, not a second place where business rules are (re)implemented.
- **No client-side routing.** No React Router, no competing router of any kind. Navigation is `router.get/post/put/delete` (or `<Link>`) to a Laravel named route; the server decides what page renders next.
- **No REST CRUD fetching for normal pages.** A standard admin index/create/edit page receives its data as Inertia props from the controller render call — it does not additionally call a JSON API endpoint to hydrate itself. (The `/api/v1/...` routes that do exist — POS, checkout, search-as-you-type — are for genuinely interactive, sub-page-load behavior where a full Inertia visit would be the wrong tool; they are the exception, not the template for ordinary CRUD.)
- **No duplicated business logic in the frontend.** Tax calculation, discount threshold checks, leave balance rules, workflow logic — all of it stays server-side. The frontend may mirror a rule for *instant UI feedback* (e.g. disabling a submit button before the server round-trip) but the server-side check is always the real one, never bypassable by skipping the client-side mirror.
- **Thin UI layer.** A page component's job is to arrange components and wire `useForm`/`router` calls to named routes — not to contain multi-step business calculations.

## Page structure

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

## Inertia standards

- **`useForm`** from `@inertiajs/react` for every form — it gives validation error state, processing state, and dirty-checking for free; do not hand-roll form state with `useState` for what `useForm` already solves.
- **`router.get/post/put/delete`** for navigation and non-form actions (e.g. a table row action, a bulk operation trigger).
- **Laravel named routes** (`route('admin.products.edit', product.id)`-style resolution, however the project's route-helper is wired) for every link/redirect target — never a hardcoded path string that will silently drift when a route changes.
- **Page props from Laravel** are the only source of server data for a page — don't introduce a parallel `fetch()`-based hydration path for data the controller could have passed as a prop.
- **No React Router. No Redux.**

## Component hierarchy

| Directory | Owns | Depends on |
| :--- | :--- | :--- |
| `Components/ui/` | shadcn/ui primitives (Button, Dialog, Table, Input, ScrollArea, etc.) — the design system's raw material | Radix primitives, Tailwind. Nothing domain-specific. |
| `Components/common/` | App-wide, domain-agnostic composites: `AppProviders`, `FlashToasts`, layout shells, `ScrollArea` wrapper | `Components/ui` only — never a domain component |
| `Components/admin/` | Domain-specific admin components (a product picker, a leave-balance widget, a branch selector) — things that know about RetailPulse's business entities | `Components/ui`, `Components/common`, `Hooks` |

Ownership rule: a component moves *down* this table (toward `ui/`) as it becomes more generic, and stays in `admin/` as long as it references a specific domain concept (a Product, an Employee, a Sale). Don't add business-domain awareness to something in `Components/ui/` to save an extra prop — extend or wrap it in `Components/admin/` instead, or the design-system layer stops being safely reusable across domains.

## Design system

- Tailwind utility classes are the default styling mechanism; design tokens are OKLch CSS variables in `resources/css/app.css`, not ad hoc hex values inline in components.
- Primary buttons (`variant="brand"` / `.rp-btn-primary`) are black at rest, teal on hover — enforced centrally in `resources/js/Components/ui/button.jsx`. Never override this per-page; if a page looks wrong, fix the shared component, don't patch around it locally.
- Any scrollable region (long modal bodies, list panels) uses `ScrollArea` from `Components/common/ScrollArea` — never a raw element with `overflow-y-auto`, which renders the unstyled native scrollbar and breaks the design language.
- Icons are Lucide, consistently — don't mix in a different icon set for a one-off need.
- Dark mode is supported through the same OKLch variable scheme; a new component must render correctly in both themes, not just the one the author happened to be looking at.

## Forms

- **Modal vs. dedicated page** — see the decision rule already established in `CLAUDE.md` (canonical, not restated in full here): a modal for simple config/master-data managed from an index page ("create/edit → stay on the list"); a dedicated page for standalone transactions, forms needing tabs/repeatable rows/file uploads, or a contextual info panel. Field count alone does not justify a page — a well-sectioned modal handles 15–20 fields fine.
- **FormCard** is the standard container for a form's fields, whether inside a modal or on a dedicated page.
- **FormInfoPanel** (dedicated pages) supplies contextual "how this works" content alongside the form — used when the transaction benefits from explanation (e.g. a leave request page explaining entitlement rules), not added reflexively to every page.
- On a dedicated page laid out as form + info panel, **Cancel/Submit buttons live inside the form card itself** (bottom, right-aligned), never in a separate row below the two-column grid — a separate row visually detaches the actions from the form and right-aligns under the wrong column.
- **Validation and error display**: server-side validation errors from `useForm`'s error bag render inline, next to the field they belong to — never as a generic toast that doesn't tell the user which field is wrong. A toast is for action-level feedback (saved, failed, deleted), not field-level validation.

## Tables

- **DataTable** is the standard pattern for listing resources: sortable columns, consistent empty/loading states, row actions via a context menu.
- **ListPagination** is the standard pagination component — driven by the paginator payload Laravel's Inertia response already includes, not a client-side re-implementation of pagination logic.
- **Filters** are expressed as query-string-driven Inertia visits (`router.get` with query params) so filter state is shareable/bookmarkable and survives a refresh — not component-local state that resets on navigation.
- **Sorting** likewise round-trips through the server (sortable column click → `router.get` with a sort param) rather than client-side array sorting of already-paginated data, which would only be correct for the current page's rows.

## Authorization

- **`useCan()`** (or the project's equivalent permission hook) gates UI visibility/enabled-state — hiding an action the user's role can't perform, disabling a button pending a permission check.
- This must always mirror an actual backend Policy ([ADR-010](./adr-010-security-principles.md)) — a `useCan('products.delete')` check on the frontend is only meaningful because `ProductPolicy::delete()` enforces the same rule server-side. Adding a new permission-gated UI affordance requires the backend Policy to exist first; the frontend check is a UX convenience layered on top, never a substitute.
- **Never rely solely on frontend authorization.** Hiding a button is not a security control — the corresponding backend route/action must independently reject an unauthorized request regardless of what the frontend rendered.

## Internationalization

- **i18next** (`react-i18next`) with locale files under `resources/js/locales/` (`en.json` etc.) — every user-facing string in a new component is a translation key, not an inline literal, from the first version of the component (retrofitting i18n later is far more error-prone than doing it at authoring time).
- **Translation keys** follow the existing file's nesting convention (grouped by feature/domain) — check the nearest existing key group before inventing a new top-level namespace for a string that belongs in an existing one.
- **Breadcrumb localization** goes through the existing breadcrumb hook (`resources/js/Hooks/useBreadcrumbs.js`) — breadcrumb labels are translation keys, not hardcoded per-page strings.
- **Navigation localization** — nav item labels registered in `NavigationRegistry`/`AdminNavigationCatalog` are translation keys as well, so the admin sidebar localizes along with the rest of the UI without a separate translation pass.

## Frontend performance

- **Lazy loading** for genuinely large, rarely-needed page bundles (a heavy chart library, a rarely-visited admin sub-page) — not applied reflexively to every component, which would just add unnecessary loading-state complexity for small components.
- **Reusable components** over copy-pasted near-duplicates — if a second page needs a component 90% similar to an existing one, extract the shared 90% into `Components/common` or `Components/admin` rather than forking it.
- **Avoid unnecessary re-renders** — memoize expensive derived values/handlers passed to large lists or frequently-rendering children, but don't reach for `useMemo`/`useCallback` reflexively on trivial values where the memoization overhead isn't worth it.
- **Minimal client state** — most state is server state delivered via Inertia props; `useState` is for genuinely ephemeral, page-local UI state (a modal's open/closed flag, a form's in-progress input) not a cache of server data that Inertia already manages.
- **Server-driven rendering** — when in doubt about where a piece of derived data should be computed, prefer computing it server-side and passing it as a prop over recomputing it client-side from raw data on every render.

## State management — official decision

- **Inertia page props** for server data.
- **`useForm`** for form state.
- **Local component state** (`useState`/`useReducer`) for ephemeral UI-only state.
- **No Redux. No Zustand. No React Query for standard CRUD.** If a future page has a genuine case for client-side caching/background-refetch behavior that Inertia's request/response model doesn't fit (e.g. a highly interactive, polling-heavy widget), that is a deliberate, narrow exception to be decided and documented explicitly when it arises — not a general pattern to reach for by default. Reverb/Echo (already in use for real-time dashboard/POS updates) is the sanctioned mechanism for push-style real-time data, not a client-side polling library.

## UI consistency

- Match RetailPulse's existing design language (spacing scale, typography scale, modal sizing conventions, button hierarchy — primary/secondary/destructive) rather than introducing a new visual pattern for a single page. When unsure what the convention is, find the nearest existing analogous page and match it.
- Responsive layouts and WCAG 2.1 AA accessibility (already a stated Phase 2 deliverable, backed by Radix primitives' built-in accessibility) are expected of every new page, not just the original platform-shell pages — a new page that isn't keyboard-navigable or breaks at mobile widths is an incomplete page, not a follow-up.

## Future direction

All future frontend work continues on this React + Inertia architecture. Do not introduce a competing frontend framework (Vue, Svelte, a separate SPA framework), a competing routing system (React Router, a hand-rolled router), or a competing state-management library (Redux, Zustand, React Query for standard CRUD) for a new module, page, or "just this one feature" — including modules built by future phases (restaurant, e-commerce, mobile-adjacent web views). Phase 26's React Native mobile apps are a deliberately separate codebase/runtime for native mobile and do not imply the web admin/POS frontend should diverge from Inertia; this ADR governs the web frontend regardless of what native mobile does in parallel.

# Consequences

- A developer or AI agent opening any admin page for the first time can predict its structure, data flow, and state model before reading a line of it, because every page follows the same composition.
- Business logic bugs cannot hide in the frontend — if a rule needs to be right, it is checked server-side, and the frontend rule (if mirrored at all) is provably just a UX convenience.
- Adding a new module's frontend (restaurant KOT screens, e-commerce channel management, mobile-adjacent web views) is additive work within the established pattern, not a fresh architectural decision each time.
