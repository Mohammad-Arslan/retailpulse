# ADR-019: Shared File Storage

Status: Accepted

Date: 2026-07-24

Related: [ADR-008 Public API](./adr-008-public-api.md) · [ADR-015 Database Standards](./adr-015-database-standards.md) · [ADR-018 Deployment](./adr-018-deployment.md)

---

## Why

Storage backend selection had grown into four independent, uncoordinated mechanisms: product/employee images read the `MEDIA_DISK` env var; supplier attachments were hardcoded to the `local` disk with no setting at all; expense attachments read a separate `EXPENSE_ATTACHMENTS_DISK` env var; and import/export spreadsheets were the only feature with an admin-configurable backend (Settings → "Import / export storage", backed by the `system_settings` table). An admin switching that one screen to MinIO saw zero effect on where images or any attachment landed — there was no single place to point the whole application at local disk, S3, MinIO, or SFTP. This was discovered while diagnosing a production incident where employee photo uploads failed; the storage-config split was a separate, pre-existing inconsistency surfaced by that investigation, not its cause.

## What

One shared, admin-configurable storage backend (disk type + S3/MinIO/SFTP credentials) drives every file-storing feature — images, supplier attachments, expense attachments, and import/export — each keeping its own remote path prefix under the shared credentials. A new `App\Services\Storage\FileStorageDiskRegistrar` is the single place that resolves "which Laravel disk backs this purpose right now" and "make sure that disk's config is registered before anyone touches it."

## How

### Settings: one group, per-feature prefixes

The former `import_export` `system_settings` group is renamed `file_storage` (Settings → "File Storage"). Shared fields: `disk` (local/s3/minio/sftp), and the S3/MinIO/SFTP credential fields. Each feature gets its own path-prefix field (`import_export_prefix`, `media_prefix`, `supplier_attachments_prefix`, `expense_attachments_prefix`) so one bucket/host can host all four without collisions, while still being configured in exactly one place.

### Local-disk mode maps onto the pre-existing static disks — not a new dynamic one

When the admin setting is `local`, `FileStorageDiskRegistrar::diskNameFor()` returns the codebase's existing static disk for that purpose: `public` for images (preserves the `storage:link` symlink and web-visible `/storage/...` URLs), `local` for attachments (preserves existing private storage). **No new disk is dynamically registered in local mode.** Only when the admin picks S3/MinIO/SFTP does a purpose-specific dynamic disk (`media`, `supplier_attachments`, `expense_attachments`) get registered at runtime, built from the shared credentials plus that purpose's prefix. Import/export keeps its own pre-existing local-mode behavior (private root, signed-URL-only access) unchanged; it only defers to the registrar for the shared remote-credential fields.

### Per-row `disk` is a permanent, immutable snapshot — never migrated

`images`, `expense_attachments`, and now `supplier_attachments` each store a `disk` column at upload time. **This value is never rewritten after a settings change.** A row uploaded while the setting was `local` (`disk='public'`) keeps resolving against the static `public` disk forever, even after an admin switches to MinIO; a row uploaded after the switch (`disk='media'`) resolves against the dynamically-registered one. This is a data-durability rule, not just an implementation detail: **any future attachment feature (purchase order attachments, customer documents, etc.) must follow the same pattern** — add a `disk` column, resolve reads through `FileStorageDiskRegistrar::resolve()`, and never bulk-rewrite historical rows when the setting changes. Moving historical files to a newly-chosen backend, if ever wanted, is a separate, explicitly-scoped, on-demand operation — never a side effect of a settings save.

### Octane: any class that mutates `filesystems.disks.*` from DB settings must be in `octane.flush`

`FileStorageDiskRegistrar` and `ImportExportStorageManager` both register disk config as a constructor side effect and are bound as plain container singletons. Production runs Octane (`octane:start`, workers recycled every 500 requests). A singleton that mutates config in its constructor but isn't in `config/octane.php`'s `flush` list will serve **stale disk config to every request on that worker** until the next recycle — a real, previously-existing gap for `ImportExportStorageManager`, fixed as part of this change (see `docs/deployment-guidelines.md`'s 2026-07-24 entries). Both classes are now in `flush`. Any future class with this "mutate config from a DB-backed setting in the constructor" shape must be added to `flush` too — this is checked at code-review time, not enforced automatically.

## Trade-offs

- **A shared credential set for four purposes means one bucket/host serves everything** — accepted because that's the point (one place to configure storage), but it does mean a bucket policy or SFTP ACL that denies writes under one purpose's prefix while allowing another would surface as a per-feature failure, not a connection-test failure (the connection tester only proves the import/export prefix is writable). Flagged as a known gap, not fixed here — extending `StorageConnectionTester` to probe all four prefixes is a reasonable follow-up if this becomes a real incident.
- **No backfill of historical files** — accepted as the lower-risk default (see "per-row disk is permanent" above); an admin who wants historical files physically moved to a new backend must run that as its own reviewed operation.
- **SFTP mode nests all four purposes under one shared `sftp_root`** — accepted as the simplest model; if a target SFTP server needs per-purpose hosts or ACLs, that's a gap to revisit against the specific target, not solved speculatively here.

## Alternatives considered

- **A second, independent "Media Storage" settings group, kept fully separate from import/export** — considered and rejected in favor of one unified group; the maintenance cost of keeping two credential sets in sync for what is fundamentally the same storage backend outweighed the flexibility of letting spreadsheets and images live on different disks (a scenario no current requirement calls for).
- **Migrating/rewriting existing files whenever the setting changes** — rejected as conflating a configuration change with a data-migration project; see Trade-offs.

## Impact on future development

- Any new file-storing feature (a new attachment type, a new export format) adds a purpose to `FileStorageDiskRegistrar::PURPOSES` and a prefix setting field, and resolves reads/writes through the registrar — it does not introduce its own env var or hardcoded disk name.
- Any new class that dynamically mutates `config('filesystems.disks.*')` (or any other config) from a DB-backed setting, and is registered as a container singleton, must be added to `config/octane.php`'s `flush` list in the same change.
