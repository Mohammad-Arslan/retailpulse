# Phase 12 — Configuration Framework

**Gate / registry key:** Cross-cutting  
**Wave:** 4  
**Depends on:** Phase 23 (eventual registry), Phase 11 patterns  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Codify how Phase 12 configuration is structured so nothing business-critical is hardcoded: effective-dated policies, country packs, entity/branch overrides, formula engine, approvals, numbering, and notification templates.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| Super Admin / HR Manager / Payroll Officer | Respective config screens |
| `settings.manage` style perms | Where shared settings used |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-CFG-FR-001 | Implemented | Module gates in `config/hr_payroll_modules.php` + branch profile (swap to Phase 23 later). |
| P12-CFG-FR-002 | Partial | Effective-dated policies across leave, OT, tax, expenses (pattern established). |
| P12-CFG-FR-003 | Planned | Country/jurisdiction packs: seed sets of schemes, brackets, leave types, holidays — installable data. |
| P12-CFG-FR-004 | Planned | Resolution order documented & enforced: employee override → branch → legal entity → tenant default. |
| P12-CFG-FR-005 | Partial | Formula engine for pay components (sandbox Planned; enum Partial). |
| P12-CFG-FR-006 | Partial | Approval policies for expenses/payroll; generalize to leave/OT/PF/loans. |
| P12-CFG-FR-007 | Implemented | Document numbering via DocumentNumberService sequences. |
| P12-CFG-FR-008 | Planned | Notification templates per event type (payslip, leave, advance) with channel preference. |
| P12-CFG-FR-009 | Planned | Feature flags for experimental sub-features within a gate. |
| P12-CFG-FR-010 | Planned | Config export/import between environments (JSON pack) with secrets stripped. |
| P12-CFG-FR-011 | Implemented | Account mapping keys editable in Phase 11 UI; Phase 12 never embeds account IDs. |
| P12-CFG-FR-012 | Planned | Admin Config Centre page grouping HRMS settings (Phase 23 alignment). |

---

## 4. Domain model

```text
# Patterns already used
*_policies with effective_from / effective_to / priority
branch_hr_profiles.hr_enabled_modules

# Planned
hrms_config_packs
- id, code, jurisdiction_code, version, payload_json, status

hrms_notification_templates
- id, event_type, channel, subject, body_template, locale, status

hrms_resolution_rules
- documentation + shared resolver service (employee>branch>entity>default)
```

---

## 5. Services & interfaces

```text
HrmsConfigResolver                 # Planned shared specificity resolver
FormulaExpressionEvaluator         # Planned sandbox
HrModuleGate                       # Implemented
```

---

## 6. Domain events

```text
hrms_config.published
hrms_config_pack.installed
```

---

## 7. Configurability surface

* Meta: this module *is* the configurability surface.

---

## 8. Historical migration inputs

* Config packs may be imported separately from transactional history.

---

## 9. Reports / ESS touchpoints

* Config audit report (who changed policy when).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-CFG-AC-001 | Implemented | Disabling payroll gate hides nav and rejects routes without code change. |
| P12-CFG-AC-002 | Partial | Effective-dated OT multiplier change affects next period only. |
| P12-CFG-AC-003 | Planned | Installing PK country pack seeds FBR scheme without code deploy. |
| P12-CFG-AC-004 | Planned | Branch override wins over entity default for holiday calendar assignment. |
| P12-CFG-AC-005 | Partial | Formula type rejected safely until sandbox ships (no `eval`). |

---

## 11. Out of scope / deferred hooks

* Full Phase 23 module config engine UI — integrate when available; keep adapter boundary.
