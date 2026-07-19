# Phase 28 — SaaS Multi-Tenancy

**SRS Reference:** §4.8, §3.24
**Status:** Planned
**Depends on:** Phase 16 (Hardening — security layer), Phase 23 (Module Config Engine — tenant_modules table)
**Feeds into:** Phase 29 (Workflow Engine — approval chains needed by enterprise tenants)

---

## Objective
Transform the application from a single-tenant system into a commercially licensable multi-tenant SaaS platform. Each tenant gets isolated data via a global Eloquent scope, a subscription plan that controls which modules are accessible, an onboarding wizard, and usage metering — with Stripe Billing as the payment stub.

## Schema preparation status

Nullable `tenant_id` columns are already on tenant-owned tables (17 prior + 153 via migration `2026_07_19_140000_add_nullable_tenant_id_for_saas_schema_prep`). See [docs/architecture/tenant-schema-preparation.md](../architecture/tenant-schema-preparation.md) for the full inventory and exclusions.

**Still Phase 28 work (not done):** `tenants` table, tenant resolver, `TenantContext`, `TenantScope`, middleware, security, tenant-aware cache/queues/storage/search, provisioning, billing, and cross-tenant protection. Until those land, the app remains single-tenant — columns alone do not enforce isolation.

---

## 1. Data Model

### tenants
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| name | varchar(150) | Company/business name |
| subdomain | varchar(80) unique | `acme` → `acme.retailpulse.app` |
| contact_email | varchar(255) | |
| status | enum | `trial`, `active`, `suspended`, `cancelled` |
| plan_id | bigint FK nullable | Current plan |
| trial_ends_at | timestamp nullable | |
| created_at / updated_at | timestamps | |

### plans
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| name | varchar(100) | "Starter", "Retail Pro", "Enterprise" |
| slug | varchar(80) unique | |
| price_monthly | decimal(10,2) | |
| price_annual | decimal(10,2) | |
| currency | char(3) | |
| module_slugs | json | Array of enabled module slugs |
| limits | json | `{branches: 3, users: 20, products: 10000}` |
| is_active | boolean | |
| stripe_price_id_monthly | varchar(255) nullable | Stripe Price ID |
| stripe_price_id_annual | varchar(255) nullable | |

### subscriptions
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| tenant_id | bigint FK | |
| plan_id | bigint FK | |
| billing_cycle | enum | `monthly`, `annual` |
| status | enum | `trialing`, `active`, `past_due`, `cancelled` |
| stripe_subscription_id | varchar(255) nullable | |
| starts_at | timestamp | |
| expires_at | timestamp | |
| cancelled_at | timestamp nullable | |

### tenant_usage_metrics
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| tenant_id | bigint FK | |
| metric | varchar(80) | `branches_count`, `users_count`, `products_count`, `monthly_transactions` |
| value | integer | |
| recorded_at | timestamp | |

---

## 2. Tenant Isolation via Global Scope

`TenantScope` — a global Eloquent scope applied to all models when `tenant_id` is present.

```php
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($tenantId = TenantContext::getId()) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }
}
```

- `TenantContext::getId()` reads the current tenant from the request (resolved by `SetTenantContext` middleware from the subdomain or `X-Tenant-ID` header).
- All models add `protected static function booted(): void { static::addGlobalScope(new TenantScope()); }`.
- Super Admin (platform admin) can bypass the scope using `Model::withoutGlobalScope(TenantScope::class)`.

**Migration pattern:** Each table that was previously branch-scoped or global gains a `tenant_id BIGINT UNSIGNED NULL` column (nullable for backwards compat with single-tenant deployments).

---

## 3. SetTenantContext Middleware

```php
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $this->extractSubdomain($request->getHost());
        $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();
        
        if ($tenant->status === 'suspended') abort(402, 'Account suspended.');
        
        TenantContext::set($tenant);
        ModuleRegistry::setTenant($tenant);
        
        return $next($request);
    }
}
```

Applied to the `web` and `api` middleware groups in production. In single-tenant mode (no subdomain), the middleware is a no-op.

---

## 4. Tenant Onboarding Wizard

A multi-step wizard at `/setup` (shown after first admin login on a new tenant):

1. **Business Info:** Company name, address, currency, timezone.
2. **Branch Setup:** Create at least one branch.
3. **Plan & Modules:** Review plan; toggle optional modules within plan limits.
4. **Invite Team:** Add initial admin users via email invite.
5. **Go Live:** Confirmation screen; wizard marked complete.

Wizard progress stored in `tenants.onboarding_step` (int 0–5).

---

## 5. Plan → Module Sync

`PlanModuleSyncService::syncForTenant(Tenant $tenant): void`
- Reads `plans.module_slugs` for the tenant's current plan.
- Upserts `tenant_modules` records: enables all slugs in the plan, disables any that are not.
- Called: on subscription created/changed, on plan change, and on demand from the tenant settings.

---

## 6. Usage Metering

`RecordTenantUsageJob` — runs hourly; records key metrics to `tenant_usage_metrics`.

Metrics tracked:
- `branches_count`, `users_count`, `products_count`, `monthly_transactions` (cumulative for current calendar month).

Usage vs plan limits:
- When a metric approaches 90% of plan limit: in-app warning notification to tenant owner.
- When a metric exceeds plan limit: soft block on creating new records of that type (e.g., cannot add a new branch; shown upgrade prompt).

---

## 7. Stripe Billing Stub

`StripeBillingService`:
- `createSubscription(Tenant $tenant, Plan $plan, string $billingCycle): Subscription` — calls Stripe API to create a subscription; stores `stripe_subscription_id`.
- `cancelSubscription(Tenant $tenant): void` — cancels at period end.
- `handleWebhook(array $payload): void` — processes `invoice.payment_succeeded`, `invoice.payment_failed`, `customer.subscription.deleted` events to update `subscriptions.status`.

Stripe webhook endpoint: `POST /billing/webhooks/stripe` (public, HMAC-verified).

When `STRIPE_SECRET_KEY` is not configured, all `StripeBillingService` methods return stub success responses (demo/dev mode).

---

## 8. Super Admin — Platform Console

A separate admin area at `/platform` (accessible only with `tenant_id = null` + `is_platform_admin = true`):

- **Tenants list:** Status, plan, usage, trial expiry.
- **Tenant detail:** Impersonate, suspend, change plan, view usage metrics.
- **Plans management:** CRUD for plans; Stripe Price ID mapping.
- **System-wide announcements:** Broadcast to all tenants.

---

## 9. Services & Classes

- `TenantContext` — static singleton holding current tenant for the request lifecycle.
- `TenantScope` — global Eloquent scope.
- `SetTenantContext` middleware.
- `TenantService` — create, onboard, suspend, cancel.
- `PlanModuleSyncService` — plan → tenant_modules sync.
- `StripeBillingService` — Stripe integration stub.
- `RecordTenantUsageJob` — hourly usage metrics.
- `TenantOnboardingController` — wizard steps.
- `PlatformAdminController` — platform console.
