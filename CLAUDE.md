# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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

## Architecture

> **Authoritative source:** The summary below is a condensed orientation for quick reference. The binding architectural decisions — multi-tenancy strategy, module boundaries, layered backend pattern, domain events, audit trail, workflow engine, integration/API strategy, plugin architecture, security principles, coding standards, and frontend architecture — live in [docs/architecture/](docs/architecture/README.md) as Architecture Decision Records (ADRs). **Read the relevant ADR before making an architectural change** (new tables, new modules, new cross-cutting concerns, new API surfaces, new frontend patterns). If anything below conflicts with an ADR, the ADR wins.

### Stack

- **Backend:** Laravel 13 (PHP 8.3+), Eloquent ORM, Sanctum auth, Spatie RBAC
- **Frontend:** React 18 + Inertia.js 2.0 (no client-side router — all routing is server-side via Inertia)
- **Build:** Vite 8, Tailwind CSS 4, shadcn/ui (New York style), Radix UI primitives
- **Real-time:** Laravel Reverb (WebSocket) + Laravel Echo + Pusher.js
- **Database:** SQLite (default); sessions, cache, and queue all use database driver

### Server–Client Bridge: Inertia.js

Inertia.js is the critical piece that connects Laravel and React. Controllers return `Inertia::render('Admin/Products/Index', $props)` instead of JSON or Blade views. The React component at `resources/js/Pages/Admin/Products/Index.jsx` receives those props directly. There is no separate API layer for page data — form submissions go through standard Laravel routes with `useForm` from `@inertiajs/react`.

Shared global props (auth user, permissions, flash messages, branch context, app name) are injected in `app/Http/Middleware/HandleInertiaRequests.php`.

### Multi-Branch / RBAC Design

The app is a multi-branch retail system. Key concepts:

- **BranchContext** (`app/Support/BranchContext.php`) stores the active branch in the request lifecycle, set by `SetBranchContext` middleware.
- **Spatie Laravel Permission** handles roles and permissions. Roles are scoped per branch where needed.
- **UserPermissionOverride** model allows per-user permission overrides on top of roles.
- All admin routes are behind `auth`, `EnsureAdminAccess`, and `SetBranchContext` middleware.

### Layered Backend Pattern

Controllers are thin — they delegate to the service layer:

```
Controller → Service → Repository → Eloquent Model
```

- `app/Repositories/Contracts/` — repository interfaces
- `app/Repositories/Eloquent/` — Eloquent implementations
- `app/Services/` — business logic (13 services)
- `app/DTOs/` — typed data transfer objects passed between layers
- `app/Http/Requests/` — validation via Form Requests before hitting controllers

### Audit Logging

`app/Observers/AuditObserver.php` hooks into Eloquent model lifecycle events to write to the `audit_logs` table automatically. Register new models for auditing in `AppServiceProvider`.

### Frontend Structure

```
resources/js/
├── app.jsx              # Inertia + React bootstrap, AppProviders, flash toasts
├── echo.js              # Laravel Echo / Reverb config
├── Pages/Admin/         # One file per admin page, receives Inertia props
├── Components/
│   ├── ui/              # shadcn/ui primitives (Button, Dialog, Table, etc.)
│   ├── admin/           # Domain-specific admin components
│   ├── charts/          # Recharts wrappers
│   └── common/          # AppProviders, FlashToasts, layout shells
├── Hooks/               # Custom React hooks
├── HOCs/                # Higher-order components
├── Layouts/             # Page layout wrappers
└── locales/             # i18n translation files (i18next)
```

Tailwind 4 is configured via CSS (`resources/css/app.css`) using OKLch color variables and `@plugin` directives — there is no `tailwind.config.js`. Shadcn component config lives in `components.json`.

#### Modal vs. dedicated page

Use a **modal** for simple config/master-data managed from an index page with a "create/edit → stay on the list" workflow (Leave Types, Leave Policies, Overtime Policies) — field count alone is not a reason to prefer a page; a well-sectioned 2-column modal handles 15–20 fields fine. Use a **dedicated page** for standalone transactions a user navigates to (Leave Request, TOIL Claim, Sales), forms needing tabs or repeatable rows/file uploads, or when a contextual "how this works" panel adds value. On a dedicated page laid out as form + info panel, Cancel/Submit buttons go **inside the form card itself** (bottom, right-aligned) — not in a separate row below the grid, which right-aligns under the info panel instead and visually detaches the buttons from the form. Any scrollable region (long modal bodies, lists) must use `ScrollArea` from `@/Components/common/ScrollArea` — never raw `overflow-y-auto` on a bare element, which renders the native unstyled scrollbar. Primary buttons (`variant="brand"` / `.rp-btn-primary`) are black by default with teal on hover, never solid teal at rest — enforced centrally in `resources/js/Components/ui/button.jsx`, don't override per-page. Full styling standard is in `.cursor/rules/retailpulse-frontend.mdc`.

### Route Organization

```
routes/
├── web.php      # Root redirect → /admin/dashboard
├── admin.php    # All /admin/* resource routes (branches, products, users, roles, inventory, transfers)
├── auth.php     # Login/logout only (registration is admin-only via user management)
├── channels.php # Reverb broadcast channel auth
└── console.php  # Scheduled commands / CLI
```

### Key Models & Relationships

The domain centers on:
- **Branch → Inventory** (branch-specific stock levels)
- **Product → ProductVariant → ProductBatch / ProductSerial** (variant + batch/serial tracking)
- **StockTransfer → StockTransferItem** (inter-branch transfer workflow with status enum)
- **BranchProductPrice** (per-branch pricing overrides)
- **Sale → SaleItem / SalePayment / SaleInvoice** (checkout transaction lifecycle)
- **AuditLog** (append-only, written by observers)

### Phase 8 — Checkout, Payments & Invoicing

All checkout behaviour is configuration-driven via `system_settings`. Nothing is hardcoded.

**Settings groups** (Admin → Settings):
- `tax` — enable/disable tax, mode (exclusive/inclusive), default rate, rounding
- `checkout` — cash change, split tender, layaway, invoice numbering, inventory deduction timing
- `fbr` — enable/disable FBR IRIS reporting, POS ID, credentials, failure mode (queue/block)

**Checkout flow:**
```
POS (F10) → POST /api/v1/pos/carts/{id}/checkout
         → Inertia redirect to /admin/checkout/{cartId}
         → GET /api/v1/checkout/{cartId}   (bootstrap: cart + config snapshot)
         → POST /api/v1/checkout/{cartId}/confirm  (creates Sale, marks cart completed)
         → POST /api/v1/sales/{id}/payments  (repeatable; reduces balance_due)
         → Sale → completed; SaleInvoice created; FBR queued if enabled
```

**Tax pipeline:** `TaxCalculationService` resolves rate per line item (variant → product → category → default). When `tax.enabled = false` the tax column is suppressed everywhere — POS totals, checkout screen, invoice PDF.

**FBR toggle:** When `fbr.enabled = false` (default), no FBR fields appear and no IRIS calls are made. When enabled, `failure_mode = queue` lets sales complete even if IRIS is unreachable; `block` holds the sale until IRIS confirms.

**New services in `app/Services/Checkout/`:**
- `CheckoutService` — orchestrates the full lifecycle
- `TaxCalculationService` — per-line tax with priority resolution
- `SalePaymentProcessor` — gateway dispatch (stub/live/disabled per `payment_gateway_configs`)
- `InvoiceService` / `InvoicePdfService` — DomPDF rendering to `resources/views/invoices/`
- `InvoiceNumberService` — race-safe sequence via `FOR UPDATE` row lock
- `FbrReportingService` — HTTP POST to FBR IRIS endpoint
- `CheckoutConfigService` — resolves `system_settings` snapshot at bootstrap time
- `HistoricalSaleImportService` — bulk historical sale import (no inventory, no FBR)

**Key API routes (all `web` + `auth` session-based, not Sanctum):**
```
GET  /api/v1/checkout/{cartId}              pos.access
POST /api/v1/checkout/{cartId}/confirm      pos.access
POST /api/v1/sales/{id}/payments            pos.access
POST /api/v1/sales/{id}/void               pos.void-cart
GET  /api/v1/customers?q=                  auth
GET  /api/v1/sales/export                  sales.export
POST /api/v1/sales/import-historical       sales.import-historical
GET  /invoice/{publicToken}               public
```

### Environment

Required `.env` values beyond defaults:
- `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD` — seeder uses these for initial admin account
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` — WebSocket auth (defaults to `retailpulse`)
- `BROADCAST_CONNECTION=reverb` — must be set for real-time features to work
