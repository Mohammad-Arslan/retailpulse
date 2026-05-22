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
- **AuditLog** (append-only, written by observers)

### Environment

Required `.env` values beyond defaults:
- `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD` — seeder uses these for initial admin account
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` — WebSocket auth (defaults to `retailpulse`)
- `BROADCAST_CONNECTION=reverb` — must be set for real-time features to work
