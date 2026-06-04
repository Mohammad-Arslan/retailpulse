# Phase 23 — Module Config Engine & Modular Feature Management

**SRS Reference:** §3.24, §3.25
**Status:** Planned
**Depends on:** Phase 16 (Hardening — security middleware layer)
**Feeds into:** Phase 24, 25, 26, 27, 28, 29 (all gated by module flags), Phase 28 (SaaS — subscription plans map to modules)

---

## Objective
Build the runtime infrastructure that makes the system modular: a module registry, feature flag store, `CheckModuleEnabled` middleware, a dynamic sidebar that hides unavailable modules, and the admin UI to enable/disable modules per tenant or branch. This is the prerequisite for the SaaS subscription model and for clean on/off toggling of restaurant, e-commerce, BI, and other optional verticals.

---

## 1. Data Model

### modules
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| slug | varchar(80) unique | `restaurant`, `ecommerce`, `hr`, `accounting` |
| name | varchar(150) | Human-readable display name |
| description | text | |
| icon | varchar(80) nullable | Lucide icon name |
| category | enum | `core`, `business`, `restaurant`, `enterprise`, `saas` |
| is_core | boolean | Core modules cannot be disabled |
| depends_on | json nullable | Array of module slugs this module requires |
| sort_order | integer | |

### module_features
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| module_id | bigint FK | |
| slug | varchar(120) unique | `restaurant.kot`, `inventory.batch_tracking` |
| name | varchar(150) | |
| description | text | |

### tenant_modules
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| tenant_id | bigint FK nullable | Null = global (single-tenant deployment) |
| module_id | bigint FK | |
| is_enabled | boolean | |
| activated_at | timestamp nullable | |
| expires_at | timestamp nullable | |

### role_module_permissions
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| role_id | bigint FK | |
| module_id | bigint FK | |
| can_view | boolean | |
| can_create | boolean | |
| can_update | boolean | |
| can_delete | boolean | |

### feature_flags
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| slug | varchar(120) | `restaurant.kds_enabled` |
| value | json | `true`, `false`, or complex JSON |
| scope | enum | `system`, `tenant`, `branch` |
| entity_id | bigint nullable | Tenant or branch ID for scoped flags |
| updated_at | timestamp | |

---

## 2. Core Module Seeder

`ModuleSeeder` populates `modules` and `module_features` tables on fresh install. Defined as code (not migration data) so it can be re-run safely.

| Slug | Category | Is Core | Depends On |
| :--- | :--- | :---: | :--- |
| auth | core | Yes | — |
| settings | core | Yes | — |
| rbac | core | Yes | auth |
| pos | core | Yes | auth, settings |
| inventory | business | No | pos |
| customers | business | No | pos |
| procurement | business | No | inventory |
| accounting | business | No | procurement |
| expenses | business | No | accounting |
| hr | business | No | expenses |
| reporting | business | No | pos |
| restaurant | restaurant | No | pos, inventory |
| kds | restaurant | No | restaurant |
| recipe | restaurant | No | restaurant, inventory |
| delivery | restaurant | No | restaurant |
| hardware | business | No | pos |
| pricing | business | No | pos |
| gift_cards | business | No | customers |
| ecommerce | enterprise | No | inventory, customers |
| bi | enterprise | No | reporting |
| mobile | enterprise | No | pos |
| saas | saas | No | auth |
| workflow | enterprise | No | rbac |

---

## 3. ModuleRegistry Service

`ModuleRegistry` (singleton, cached in Redis with tag `modules`):

```php
isEnabled(string $slug, ?int $tenantId = null): bool
isFeatureEnabled(string $featureSlug, ?int $entityId = null): bool
getEnabledModules(?int $tenantId = null): Collection
clearCache(): void
```

- `isEnabled` checks `tenant_modules` for the slug (falling back to global null record).
- Core modules always return `true` regardless of `tenant_modules`.
- Cached for 60 minutes; invalidated on `tenant_modules` save events.

---

## 4. CheckModuleEnabled Middleware

```php
class CheckModuleEnabled
{
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        if (!ModuleRegistry::isEnabled($moduleSlug)) {
            abort(404); // 404 not 403 — don't reveal disabled features
        }
        return $next($request);
    }
}
```

Registered as `module` alias. Applied to every optional module's route group:
```php
Route::middleware(['auth', 'verified', 'module:restaurant'])->group(function () { ... });
```

---

## 5. Feature Flag Service

`FeatureFlagService::isEnabled(string $slug, ?Model $scopeEntity = null): bool`

- Resolution order: branch-scoped → tenant-scoped → system-scoped → `false`.
- Cached per slug+scope in Redis for 5 minutes.
- Used throughout the codebase: `if (FeatureFlagService::isEnabled('restaurant.blind_close')) { ... }`.

---

## 6. Dynamic Sidebar

The `HandleInertiaRequests` middleware currently shares static props. Extend it to include `enabledModules`:

```php
'enabledModules' => ModuleRegistry::getEnabledModules(),
```

The sidebar React component (`AdminSidebar.jsx`) reads `enabledModules` from Inertia's shared props and filters navigation items:

```jsx
const navItems = ALL_NAV_ITEMS.filter(item =>
    !item.module || enabledModules.includes(item.module)
);
```

Each nav item definition gains an optional `module` field (e.g., `{ label: 'Restaurant', module: 'restaurant', ... }`).

---

## 7. Settings → Modules Admin UI

- Tab under Settings accessible only to Super Admin / Owner.
- Table of all non-core modules with: name, category, current status toggle, dependency badges.
- Toggle confirmation dialog: "Enabling Restaurant requires POS (already enabled). Proceed?"
- Dependency resolver: enabling a module auto-enables its `depends_on` modules; disabling warns if other enabled modules depend on it.

---

## 8. 4-Tier Global Configuration Engine (§3.25)

Extend the existing `SystemSetting` model to support the 4-tier hierarchy:

```sql
ALTER TABLE system_settings
  ADD COLUMN scope ENUM('system','tenant','branch','user') NOT NULL DEFAULT 'system',
  ADD COLUMN scope_id BIGINT UNSIGNED NULL COMMENT 'tenant_id or branch_id or user_id';

ALTER TABLE system_settings
  ADD UNIQUE KEY uq_setting_scope (key, scope, scope_id);
```

`ConfigService::get(string $key, ?Model $scopeEntity = null): mixed`
- Resolution: user → branch → tenant → system.
- Falls back to system default if no override found.
- Cached in Redis with tag `config:{scope}:{scope_id}`.

Admin UI:
- **Settings → Global:** System-scope settings (Super Admin only).
- **Settings → Branch Overrides:** Branch managers override specific keys for their branch (allowed keys configurable).

---

## 9. Services & Classes

- `ModuleRegistry` — enabled module resolution (cached).
- `FeatureFlagService` — feature flag resolution (cached).
- `CheckModuleEnabled` — route middleware.
- `ConfigService` — 4-tier setting resolution.
- `ModuleSeeder` — seeds modules and features.
- `ModuleDependencyResolver` — resolves enable/disable cascades.
