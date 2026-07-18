# ADR-005: Audit Trail & Immutable History

Status: Accepted

Date: 2026-07-19

Related: [ADR-004 Layered Architecture](./adr-004-layered-architecture.md) · [ADR-010 Security Principles](./adr-010-security-principles.md) · [ADR-003 Domain Events](./adr-003-domain-events.md)

---

# Context

RetailPulse handles financial and HR records — sales, invoices, journal entries, payroll runs, leave balances — where "who changed what, when, and to what value" is not a nice-to-have, it's a compliance and dispute-resolution requirement (SRS §3.11 Accounting, §3.12/§3.13 HR & Payroll). A system that allows silent overwrites of financial history, or that physically deletes rows tied to a transaction, cannot answer "why does this employee's leave balance not match last month's report" or survive an audit.

# Decision

## Audit logging is universal and automatic, not opt-in per feature

`app/Observers/AuditObserver.php` hooks Eloquent's `created` / `updated` / `deleted` lifecycle events and writes to the `audit_logs` table with old/new value snapshots. A model is audited by being registered for observation in `AppServiceProvider` and matched by `shouldAudit()` — currently gated by domain-specific type lists (`AccountingAuditTypes`, `HrPayrollAuditTypes`) plus a fixed set of security-sensitive models (`User`, `Role`, `Permission`, `Branch`, loyalty configuration models, catalog models).

**Rule for new models:** if a new model represents financial data, HR/payroll data, security configuration (roles, permissions, branches), or anything a Category 2 tenant-owned business record per [ADR-001](./adr-001-saas-multi-tenancy.md) — register it for audit observation and add it to the relevant type list. Don't build a feature-specific "history log" table as a substitute; extend the universal mechanism so audit queries stay in one place (`audit_logs`), not scattered per-feature.

This is deliberately **not** implemented via domain events ([ADR-003](./adr-003-domain-events.md)) — see that ADR's note on why audit logging is a lower-level, unconditional guarantee rather than a business-event listener that could be skipped if no event happens to be dispatched for a given code path.

## Financial and payroll history is immutable — corrections are reversals, not edits

Once a financial or payroll record has been posted/finalized (a completed `Sale`, a posted journal entry, a finalized `Payslip`, a completed `PayrollRun`), it must not be mutated in place to "fix" it. The correction is a new, linked record that reverses or adjusts the original:

- **Sales** — a completed sale is not edited; a return/refund/credit note is a new record referencing the original (`SaleCompleted` already exists; the return/refund workflow is Phase 14).
- **Accounting** — a posted journal entry is corrected with a reversing journal entry, not an `UPDATE` on the original row. This is standard double-entry practice and is required for the trial balance to remain reconcilable against history.
- **Payroll** — an issued payslip is corrected via an adjustment record in a subsequent run, not by rewriting the original payslip's stored values (see `docs/phases/phase-12/payroll-adjustments.md`).
- **Inventory** — stock movements are an append-only ledger (`stock_movements`); a correction is a new movement with a reason code, never an edit of a historical movement row (see Phase 5 / `InventoryService::applyDelta`).

The audit trail (above) is the safety net for records that *are* still mutable pre-finalization (e.g. a draft PO) — it does not replace the reversal requirement once a record is finalized; the two mechanisms serve different failure modes (accidental/unaudited change vs. deliberate business correction).

## Soft deletes

Business records that participate in reporting, financial history, or referential integrity for other still-live records use soft deletes (`deleted_at`), not hard deletes, so that:
- Historical reports referencing a since-deleted record (a deactivated employee, a discontinued product) remain reconstructable.
- The audit trail's `deleted` event has something to point at.

Hard deletes remain appropriate for: genuinely transient/derivable data (cache-like tables, session data, queued job rows), data the user explicitly and irreversibly purges under a documented retention policy, and rows that were never business-meaningful (e.g. an abandoned draft never submitted). When in doubt for a new table, prefer soft delete — it is far cheaper to later add a hard-purge job under a retention policy than to discover missing history after the fact.

## Historical data preservation across import and migration paths

`HistoricalSaleImportService` (Phase 8) and the Phase 12 historical HR/payroll migration path (`docs/phases/phase-12/historical-migration.md`) both write directly into the completed/finalized state without re-running side effects (no inventory deduction, no FBR submission, no re-triggering of workflow approvals) — this is intentional: historical import represents *already-settled* reality being recorded, not a new transaction happening now. Audit logging still applies to the import itself (who imported, when, source file), but the imported records are treated as already-finalized history from the moment they're written, subject to the same reversal-not-edit rule above.

# Consequences

- Any financial or HR dispute can be answered by querying `audit_logs` for the record's history and, for finalized records, by following the chain of reversal/adjustment records rather than looking for a lost "previous value."
- New financially-sensitive features must be designed with a reversal path from day one, not bolted on later — this is a modeling decision (does this table need a `reverses_id`/`adjustment_of_id`-style self-reference?), not just a service-layer convention.
- Storage grows monotonically for audited/financial domains — this is the accepted cost of the guarantee; retention/archival policy for `audit_logs` itself is a Phase 16 hardening concern, not a reason to skip auditing now.
