# Phase 1 — Super Admin, Authentication & RBAC

**SRS Reference:** §3.1 Authentication, §3.2 Authorization, §4.3 Audit (foundation), §4.4 Security (foundation)  
**Status:** Not started  
**Depends on:** Fresh Laravel 13 install (current state)  
**Blocks:** All subsequent phases

---

## 1. Objective

Deliver the **Super Admin control plane**: secure login as the default entry point, full user lifecycle management, and a complete **Role-Based Access Control (RBAC)** system powered by **Spatie Laravel Permission**. No business modules (products, POS, branches) ship in this phase—only identity, access control, and a minimal authenticated admin shell.

---

## 2. Scope

### In Scope

| Area | Deliverable |
| :--- | :--- |
| **Authentication** | Laravel Breeze (Inertia + React stack: login, logout, password reset, email verification optional) |
| **Default route** | Remove Laravel welcome page; `/` redirects unauthenticated users to `/login`; authenticated Super Admin lands on `/admin/dashboard` |
| **RBAC** | `spatie/laravel-permission` — roles, permissions, role-permission assignment, user-role assignment |
| **Super Admin UI** | Manage users (CRUD), roles (CRUD + clone), permissions (CRUD + groups) |
| **Authorization** | Middleware, policies, and Inertia-shared `auth.user.permissions` for UI gating |
| **Seed data** | Super Admin account, default roles, granular permissions registry |
| **Audit foundation** | `audit_logs` table + observer on User/Role/Permission mutations |
| **Architecture** | Service + Repository + DTO pattern for user/role modules (establishes convention) |

### Out of Scope (Later Phases)

- Cashier PIN login, magic links (Phase 7 / 16)
- Two-factor authentication (Phase 16)
- Session/device management UI (Phase 16)
- Branch-scoped roles (Phase 3)
- Sanctum API tokens for third parties (Phase 15)
- Full shadcn/ui design system polish (Phase 2 — restyle Breeze auth pages in Phase 2; functional Breeze Inertia pages in Phase 1)

---

## 3. Technology Choices

| Package | Purpose | Notes |
| :--- | :--- | :--- |
| **laravel/breeze** | Auth scaffolding (Inertia + React) | `php artisan breeze:install react` — installs Inertia, React, Sanctum, and auth routes/controllers |
| **spatie/laravel-permission** | RBAC | `HasRoles` trait on `User`; guard `web` |
| **laravel/sanctum** | SPA session auth | Included with Breeze; token UI deferred to Phase 15 |

**Recommendation:** Use Breeze **React** stack so login, admin CRUD, and future POS share one Inertia + React codebase. **Disable public registration** — only Super Admin creates users via `/admin/users`. Phase 2 restyles Breeze `Pages/Auth/*` with shadcn/ui.

---

## 4. Routing & Access Model

```
GET  /                      → redirect: guest → /login, auth → /admin/dashboard
GET  /login                   → Breeze `AuthenticatedSessionController@create` (Inertia `Auth/Login`)
POST /login                   → Breeze `AuthenticatedSessionController@store`
POST /logout                  → Breeze `AuthenticatedSessionController@destroy`
# routes/auth.php — register disabled; password reset optional

Prefix: /admin (middleware: auth, verified optional)
  GET  /admin/dashboard       → Super Admin home (minimal)
  Resource: /admin/users      → UserController (policy: users.*)
  Resource: /admin/roles      → RoleController (policy: roles.*)
  Resource: /admin/permissions→ PermissionController (policy: permissions.*)
```

**Gate:** Only users with role `super-admin` OR permission `access admin panel` reach `/admin/*` until branch roles exist.

---

## 5. Default Roles & Permissions

### 5.1 Roles (seeded, editable)

| Role | Slug | Description |
| :--- | :--- | :--- |
| Super Admin | `super-admin` | Full system access; cannot be deleted |
| Owner | `owner` | Business owner; all ops except system config |
| Branch Manager | `branch-manager` | Branch operations (activated in Phase 3) |
| Accountant | `accountant` | Finance modules (Phase 11) |
| Cashier | `cashier` | POS only (Phase 7) |

### 5.2 Permission Groups (seeded)

Permissions use dot notation: `{module}.{action}`.

**Phase 1 permissions (implement now):**

```
# Admin panel
admin.access
admin.dashboard.view

# Users
users.view
users.create
users.update
users.delete
users.assign-roles

# Roles
roles.view
roles.create
roles.update
roles.delete
roles.clone
roles.assign-permissions

# Permissions
permissions.view
permissions.create
permissions.update
permissions.delete
```

**Super Admin** receives all permissions via seeder (`Role::findByName('super-admin')->givePermissionTo(Permission::all())`).

### 5.3 Role–Permission Matrix (initial)

| Permission | Super Admin | Owner | Branch Manager | Accountant | Cashier |
| :--- | :---: | :---: | :---: | :---: | :---: |
| admin.access | ✓ | ✓ | ✓ | ✓ | — |
| users.* | ✓ | ✓ | — | — | — |
| roles.* | ✓ | — | — | — | — |
| permissions.* | ✓ | — | — | — | — |

---

## 6. Database Schema

### 6.1 Extend `users`

```sql
-- migration: add columns to users
tenant_id          BIGINT UNSIGNED NULL INDEX   -- SaaS-ready (SRS §4.8)
phone              VARCHAR(20) NULL
is_active          BOOLEAN DEFAULT TRUE
last_login_at      TIMESTAMP NULL
last_login_ip      VARCHAR(45) NULL
failed_login_attempts TINYINT UNSIGNED DEFAULT 0
locked_until       TIMESTAMP NULL
```

### 6.2 Spatie tables (published migration)

- `roles` — add `description`, `is_system` (boolean, prevents delete of seeded roles)
- `permissions` — add `group` (varchar, for UI grouping), `description`
- `model_has_roles`, `model_has_permissions`, `role_has_permissions`

### 6.3 `audit_logs`

```sql
id                 BIGINT UNSIGNED PK
user_type          VARCHAR(255) NULL
user_id            BIGINT UNSIGNED NULL
event              VARCHAR(255)          -- created, updated, deleted, login, logout
auditable_type     VARCHAR(255) NULL
auditable_id       BIGINT UNSIGNED NULL
old_values         JSON NULL
new_values         JSON NULL
url                TEXT NULL
ip_address         VARCHAR(45) NULL
user_agent         TEXT NULL
created_at         TIMESTAMP
-- NO updated_at; append-only
```

### 6.4 Optional: `user_permission_overrides` (SRS §3.2)

For user-specific permission grants/revokes without changing role:

```sql
user_id            FK users
permission_id      FK permissions
type               ENUM('grant', 'revoke')
expires_at         TIMESTAMP NULL
```

Implement model + service; UI can be a tab on user edit (stretch goal within Phase 1).

---

## 7. Backend Structure

```
app/
├── DTOs/User/
│   ├── CreateUserData.php
│   └── UpdateUserData.php
├── Http/Controllers/Auth/          # Breeze (customize redirects, throttle hooks)
│   ├── AuthenticatedSessionController.php
│   ├── PasswordResetLinkController.php
│   └── NewPasswordController.php
├── Http/Controllers/Admin/
│   ├── DashboardController.php
│   ├── UserController.php
│   ├── RoleController.php
│   └── PermissionController.php
├── Http/Requests/Admin/...
├── Policies/
│   ├── UserPolicy.php
│   ├── RolePolicy.php
│   └── PermissionPolicy.php
├── Repositories/
│   ├── Contracts/UserRepositoryInterface.php
│   └── Eloquent/UserRepository.php
├── Services/
│   ├── UserService.php
│   ├── RoleService.php      # includes clone()
│   └── AuditService.php
├── Observers/
│   └── AuditObserver.php
└── Models/User.php          # HasRoles, fillable, casts
```

**Role cloning:** `RoleService::clone(Role $source, string $newName)` copies all permissions to a new role.

**Permission inheritance (SRS §3.2):** Store as explicit permissions on each role in Phase 1; optional `parent_role_id` on `roles` table can be added later for automatic inheritance.

---

## 8. Frontend Pages (Inertia)

**Breeze auth pages** (under `resources/js/Pages/Auth/`): `Login`, `ForgotPassword`, `ResetPassword`, `VerifyEmail` — restyle in Phase 2.

| Page | Route name | Permission |
| :--- | :--- | :--- |
| Login | `login` | guest (`Pages/Auth/Login.tsx` from Breeze) |
| Admin Dashboard | `admin.dashboard` | `admin.dashboard.view` |
| Users Index / Create / Edit | `admin.users.*` | `users.view`, etc. |
| Roles Index / Create / Edit / Clone | `admin.roles.*` | `roles.view`, etc. |
| Permissions Index | `admin.permissions.*` | `permissions.view` |

**Shared Inertia props:**

```php
'auth' => [
    'user' => $user->only('id', 'name', 'email'),
    'roles' => $user->getRoleNames(),
    'permissions' => $user->getAllPermissions()->pluck('name'),
],
'flash' => [...],
```

**UI gating helper (React):** `can('users.create')` checks `auth.permissions`.

---

## 9. Implementation Checklist

### 9.1 Packages & config

- [ ] `composer require laravel/breeze --dev`
- [ ] `php artisan breeze:install react` — installs Inertia, React, Sanctum, Tailwind, auth scaffolding
- [ ] `npm install && npm run build`
- [ ] `composer require spatie/laravel-permission`
- [ ] `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
- [ ] **Disable public registration:** remove or comment `register` routes in `routes/auth.php`; delete/disable `RegisteredUserController` routes
- [ ] Customize `app/Http/Controllers/Auth/AuthenticatedSessionController` — post-login redirect to `admin.dashboard`; enforce `admin.access` permission
- [ ] Update `bootstrap/app.php` / `RouteServiceProvider` HOME constant → `/admin/dashboard`
- [ ] `User` model: `HasRoles`, `HasApiTokens` (Sanctum, from Breeze)

### 9.2 Routes & welcome removal

- [ ] Delete or disable `resources/views/welcome.blade.php` route in `routes/web.php`
- [ ] `Route::get('/', fn () => auth()->check() ? redirect()->route('admin.dashboard') : redirect()->route('login'));`
- [ ] `Route::redirect('/home', '/admin/dashboard');` (if applicable)
- [ ] Guest middleware on login; `auth` + `can:admin.access` on admin group

### 9.3 Database

- [ ] Migrations: users extensions, audit_logs, roles/permissions extensions
- [ ] Run migrations

### 9.4 Seeders

- [ ] `PermissionSeeder` — all Phase 1 permissions with groups
- [ ] `RoleSeeder` — 5 default roles + permission assignments
- [ ] `SuperAdminSeeder` — user `admin@retailpulse.local` (password from `.env` `SUPER_ADMIN_PASSWORD`)
- [ ] `DatabaseSeeder` calls seeders in order

### 9.5 CRUD & policies

- [ ] User CRUD with role multi-select
- [ ] Role CRUD with permission checkboxes grouped by `permission.group`
- [ ] Role clone action
- [ ] Permission list (create/edit for Super Admin only)
- [ ] Policies registered in `AuthServiceProvider`
- [ ] `@can` / `authorize()` on every controller action

### 9.6 Security (Phase 1 baseline)

- [ ] Rate limit login: `RateLimiter::for('login', ...)` — 5 attempts/minute per IP+email
- [ ] Lock account after 5 failed attempts (`locked_until` = now + 15 minutes)
- [ ] Log login/logout to `audit_logs`
- [ ] CSRF on all forms (default)
- [ ] Prevent self-deletion of logged-in Super Admin
- [ ] Prevent deletion of `is_system` roles

### 9.7 Tests

- [ ] Feature: login success/failure/lockout
- [ ] Feature: guest cannot access `/admin`
- [ ] Feature: cashier cannot access `users.create`
- [ ] Feature: Super Admin can create user with role
- [ ] Feature: role clone copies permissions
- [ ] Unit: `RoleService::clone`

---

## 10. Environment Variables

```env
SUPER_ADMIN_NAME="Super Admin"
SUPER_ADMIN_EMAIL=admin@retailpulse.local
SUPER_ADMIN_PASSWORD=          # Set locally; never commit

# Breeze: no extra auth env vars required
# Disable registration in routes/auth.php (admin-only user creation)
```

---

## 11. Acceptance Criteria

1. Visiting `/` as a guest shows the **login page**, not the Laravel welcome screen.
2. Super Admin can log in with seeded credentials and reach `/admin/dashboard`.
3. Super Admin can **create, edit, deactivate, and delete** users (soft-deactivate preferred via `is_active`).
4. Super Admin can **assign one or more roles** to a user.
5. Super Admin can **create/edit roles**, assign permissions, and **clone** an existing role.
6. Super Admin can view and manage the **permission registry** (grouped list).
7. Unauthorized users receive **403** on admin routes; unauthenticated users redirect to **login**.
8. User/role/permission changes are recorded in **`audit_logs`**.
9. `php artisan db:seed` produces a working Super Admin and default roles.
10. All Phase 1 feature tests pass in CI.

---

## 12. Estimated Effort

| Task | Days (est.) |
| :--- | :--- |
| Packages, Breeze, routes | 1 |
| Migrations, models, seeders | 1 |
| Services, repositories, policies | 1.5 |
| Admin UI (users, roles, permissions) | 2–3 |
| Audit + security + tests | 1.5 |
| **Total** | **~7–8 days** |

---

## 13. Handoff to Phase 2

Phase 2 introduces the polished **admin shell** (sidebar, shadcn/ui, command palette) and does not change RBAC semantics. Phase 1 controllers and policies remain; only views/layouts are upgraded.
