# Phase 17 — Shift & Cash Register Management

**SRS Reference:** §3.20
**Status:** Planned
**Depends on:** Phase 7 (POS — shift must open before checkout/sales per §3.20)
**Feeds into:** Phase 8 (Checkout — payment completion triggers drawer open), Phase 19 (Restaurant Core), Phase 21 (Hardware — drawer command)

---

## Objective
Every POS transaction must be anchored to an open cashier shift. This phase introduces the register and shift lifecycle, X/Z report generation, cash tracking, blind-close option, and manager variance approval — giving the business full accountability for every rupee in every drawer.

---

## 1. Data Model

### registers
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| branch_id | bigint FK | |
| name | varchar(100) | e.g. "Lane 1", "Counter A" |
| description | text nullable | |
| status | enum | `active`, `inactive` |
| created_at / updated_at | timestamps | |

### shifts
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| register_id | bigint FK | |
| cashier_id | bigint FK → users | |
| opening_balance | decimal(12,2) | Declared by cashier at open |
| closing_balance_declared | decimal(12,2) nullable | Actual cash counted at close |
| closing_balance_expected | decimal(12,2) nullable | System-calculated expected |
| variance | decimal(12,2) nullable | declared − expected |
| status | enum | `open`, `closing_pending`, `closed` |
| blind_close | boolean | If true, cashier closed without seeing expected |
| opened_at | timestamp | |
| closed_at | timestamp nullable | |
| manager_approval_required | boolean | Set when |variance| > threshold |
| manager_approved_by | bigint FK nullable | |
| manager_approved_at | timestamp nullable | |

### shift_cash_movements
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| shift_id | bigint FK | |
| type | enum | `paid_in`, `paid_out`, `no_sale` |
| amount | decimal(12,2) | 0 for no_sale events |
| reason | varchar(255) | |
| performed_by | bigint FK → users | |
| created_at | timestamp | |

---

## 2. Business Rules

- A POS session cannot process a sale unless an open shift exists for the register.
- One register can have at most one `open` shift at a time.
- Shift `opening_balance` is declared by the cashier before the first sale; it cannot be changed after the shift opens.
- Manager approval threshold: `pos.shift_variance_approval_threshold` (system setting, default 500.00). If `|variance| > threshold`, the shift status transitions to `closing_pending` and the cashier sees a "Waiting for manager approval" screen.
- Blind close: if `pos.blind_close_enabled = true`, the cashier enters actual cash without seeing the expected figure; the variance is calculated server-side after submission.

---

## 3. Shift Lifecycle

```
[Register Active]
      |
      | Cashier opens POS → declares opening balance
      ▼
   [Shift Open]  ←─── sales, returns, paid-in/paid-out, no-sale logs
      |
      | Cashier requests close
      ▼
   [Cashier Declares Actual Cash]
      |
      | |variance| ≤ threshold?
      ├── Yes → [Shift Closed] → Z-Report generated
      └── No  → [closing_pending] → Manager PIN → [Shift Closed]
```

---

## 4. X-Report (Mid-Shift)

Generated on demand without closing the shift. Contains:
- Shift open time and cashier name
- Total sales count and gross amount
- Payment method breakdown (cash, card, wallet, etc.)
- Total refunds
- Paid-in / paid-out totals
- Current expected cash in drawer

Printable to the receipt printer; also downloadable as PDF.

---

## 5. Z-Report (End-of-Shift)

Generated on shift close. Extends the X-report with:
- Closing balance declared vs expected
- Variance amount and approval note
- No-sale drawer opens count
- Shift duration

The Z-report is final and immutable once generated. Stored as a JSON snapshot in `shifts.z_report_snapshot`.

---

## 6. No-Sale Drawer Logs

Every drawer open without a sale is recorded in `shift_cash_movements` with `type = no_sale`. Cashier must select a reason from a configurable list (e.g., "Give change", "Manager request", "Count cash"). No-sale count appears on the X/Z report and in the fraud controls section of Reporting (Phase 14).

---

## 7. API Endpoints

| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| GET | /api/v1/pos/registers | pos.access | List active registers for branch |
| POST | /api/v1/pos/shifts | pos.access | Open a new shift |
| GET | /api/v1/pos/shifts/current | pos.access | Get current open shift for register |
| POST | /api/v1/pos/shifts/{id}/x-report | pos.access | Generate X-report |
| POST | /api/v1/pos/shifts/{id}/close | pos.access | Initiate shift close |
| POST | /api/v1/pos/shifts/{id}/approve-close | pos.manage-shifts | Manager approves variance close |
| POST | /api/v1/pos/shifts/{id}/cash-movements | pos.access | Record paid-in / paid-out / no-sale |
| GET | /admin/shifts | reports.shifts | Admin shift history list |
| GET | /admin/shifts/{id}/z-report | reports.shifts | Download Z-report PDF |

---

## 8. Admin UI

- **Settings → Registers:** CRUD for registers per branch; assign default cashier (optional).
- **POS open screen:** Before the cart loads, cashier is presented with an "Open Shift" dialog — enter opening balance → submit → shift created → POS cart loads.
- **POS close screen:** Cashier selects "End Shift" from the POS menu → declare actual cash → submit.
- **Admin → Shifts:** Table of all shifts with status, cashier, branch, variance; click to view Z-report.

---

## 9. Migrations

```php
// registers table
Schema::create('registers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
    $table->string('name', 100);
    $table->text('description')->nullable();
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();
});

// shifts table
Schema::create('shifts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('register_id')->constrained()->cascadeOnDelete();
    $table->foreignId('cashier_id')->constrained('users');
    $table->decimal('opening_balance', 12, 2)->default(0);
    $table->decimal('closing_balance_declared', 12, 2)->nullable();
    $table->decimal('closing_balance_expected', 12, 2)->nullable();
    $table->decimal('variance', 12, 2)->nullable();
    $table->enum('status', ['open', 'closing_pending', 'closed'])->default('open');
    $table->boolean('blind_close')->default(false);
    $table->timestamp('opened_at')->useCurrent();
    $table->timestamp('closed_at')->nullable();
    $table->boolean('manager_approval_required')->default(false);
    $table->foreignId('manager_approved_by')->nullable()->constrained('users');
    $table->timestamp('manager_approved_at')->nullable();
    $table->json('z_report_snapshot')->nullable();
    $table->timestamps();
});

// shift_cash_movements table
Schema::create('shift_cash_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['paid_in', 'paid_out', 'no_sale']);
    $table->decimal('amount', 12, 2)->default(0);
    $table->string('reason');
    $table->foreignId('performed_by')->constrained('users');
    $table->timestamp('created_at')->useCurrent();
});
```

---

## 10. Services & Classes

- `RegisterService` — CRUD for registers.
- `ShiftService` — open shift, close shift (with variance check), approve close, generate X/Z report JSON.
- `ShiftReportService` — builds X/Z report data structure from shift + related sales + cash movements.
- `ShiftPdfService` — renders Z-report to PDF (DomPDF).
- `CashDrawerService` — wraps the drawer-open command; delegates to Phase 21 hardware layer when available; logs to `shift_cash_movements`.
