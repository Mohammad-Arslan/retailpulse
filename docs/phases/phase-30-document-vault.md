# Phase 30 — Document Vault & Attachment Management

**SRS Reference:** §3.31
**Status:** Planned
**Depends on:** Phase 2 (Platform Shell — upload UI components), Phase 16 (S3 storage abstraction, secrets)
**Feeds into:** All entity modules (Products, Suppliers, Customers, POs, GRNs, Employees, Expenses, Sales, Returns)

---

## Objective

Provide a **general-purpose document attachment system** for all major business entities. Files are stored via Laravel Storage (S3-compatible in production), accessed through signed temporary URLs, and governed by the same RBAC system as the parent entity. Configurable retention policies flag documents past their retention date for deletion.

---

## 1. Data Model

### document_attachments

| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| entity_type | varchar(150) | Polymorphic — `App\Models\Product`, `App\Models\Supplier`, etc. |
| entity_id | bigint | |
| file_name | varchar(255) | Original filename |
| file_path | varchar(500) | Storage path — never a public URL |
| mime_type | varchar(100) | |
| file_size_kb | unsigned int | |
| document_category | varchar(80) | `invoice`, `contract`, `warranty`, `photo`, `other` — configurable per entity |
| notes | text nullable | |
| uploaded_by | bigint FK → users | |
| uploaded_at | timestamp | |
| retention_until | date nullable | Computed from category policy |
| tenant_id | bigint nullable | |

### document_category_configs (optional)

| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| entity_type | varchar(150) | |
| category_slug | varchar(80) | e.g. `trade_license`, `id_document` |
| label | varchar(150) | |
| retention_years | int nullable | Null = retain indefinitely |

---

## 2. Supported Entities

Attachments enabled on:

- Products, Suppliers, Customers
- Purchase Orders, GRNs, Supplier Invoices
- Employees, Expense Entries
- Sales, Returns (customer RMA)
- Contracts (standalone or linked to supplier/customer)

Each entity's show page gains a **Documents** tab with upload, list, download, and delete (permission-gated).

---

## 3. Storage & Access

- **Development:** `local` disk under `storage/app/documents/{tenant_id}/{entity_type}/{entity_id}/`.
- **Production:** S3-compatible bucket via Laravel `Storage::disk('s3')`.
- **Download:** `DocumentVaultService::signedUrl(DocumentAttachment $doc): string` — temporary signed URL (default 15 min TTL).
- **Upload:** Max file size configurable (`documents.max_upload_mb`, default 20); allowed MIME types whitelist (PDF, images, Office docs).
- Virus scan hook (stub): queue job placeholder for ClamAV integration in enterprise deployments.

---

## 4. Access Control

- View/upload/delete governed by parent entity permissions (e.g. `suppliers.view` to see supplier documents).
- Additional permission `documents.manage-retention` for Super Admin to purge flagged documents.
- Cashier role cannot view supplier contract documents unless explicitly granted `documents.view-supplier-contracts`.

---

## 5. Retention Policy

- Per-category `retention_years` in `document_category_configs`.
- Examples: Employee Contract — 7 years after termination; Supplier Trade License — 5 years; Expense receipt — 7 years.
- Nightly `DocumentRetentionJob` flags attachments past `retention_until`; admin review queue before hard delete.
- Hard delete removes storage object and DB row; action audit-logged.

---

## 6. API Endpoints

| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| GET | /api/v1/documents | entity permission | List attachments for entity (`?entity_type=&entity_id=`) |
| POST | /api/v1/documents | entity permission | Upload file (multipart) |
| GET | /api/v1/documents/{id}/download | entity permission | Redirect to signed URL |
| DELETE | /api/v1/documents/{id} | entity permission | Soft-delete attachment + storage cleanup job |

---

## 7. Integration Points

- **Phase 12 Expenses:** "Digital Vault" for expense receipts uses this vault (not a separate upload path).
- **Phase 30 Document Vault** is the single attachment subsystem — expense entries attach via polymorphic `document_attachments`.
- **Phase 12 Payslips:** Generated payslip PDFs optionally stored as `document_category = payslip` on employee record.

---

## 8. Services & Classes

- `DocumentVaultService` — upload, signed URL, delete, retention check.
- `DocumentAttachment` model (polymorphic `attachable` or explicit entity_type/entity_id).
- `DocumentRetentionJob` — nightly retention flagging.
- `DocumentUploaded` event — optional notification to entity owner.

---

## Acceptance Criteria

1. Upload PDF to supplier record; download via signed URL works; URL expires after TTL.
2. User without `suppliers.view` receives 403 on supplier document list.
3. Employee contract with 7-year retention flagged for review after retention date.
4. Expense entry receipt appears in Documents tab and in expense detail view.
5. Deleted document removes file from storage and creates audit log entry.
