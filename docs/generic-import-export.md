You are implementing a production-grade, fully generic Import/Export Gateway for a multi-tenant Laravel application. The application already has Phases 4 and 5 complete (Product Information Management and Inventory & Warehouse Management) but without any import/export functionality. Laravel Reverb is already installed. The tech stack is Laravel 11, PHP 8.3, MySQL 8, Redis, Laravel Horizon, Laravel Reverb for WebSockets, Spatie Laravel Permission for roles and permissions, and Maatwebsite Laravel Excel for spreadsheet handling.

Your job is to implement the entire feature end to end. Do not create placeholder methods, do not leave TODO comments, do not stub anything out. Every class, method, migration, route, and configuration must be fully implemented and production-ready.

---

FEATURE OVERVIEW

You are building a multi-step import/export gateway with the following characteristics. It is completely generic and plug-and-play, meaning any entity in the application registers a handler class and the gateway handles everything else. It supports dynamic per-column validation rules that the user configures during the import wizard. It is fully queue-based using Laravel with separate worker pools for validation and processing. It uses Laravel Reverb for real-time progress broadcasting. It supports dry runs that run the full validation pass but write nothing to the database. It tracks all jobs persistently so users can navigate away and come back to see progress in a global tray. It supports multiple file storage backends (local, S3, MinIO, SFTP) switchable from a super admin settings panel without redeployment. It produces detailed end-of-job summaries and downloadable error reports.

---

STEP 1 — DATABASE MIGRATIONS

Create the following migrations in order.

Migration one creates the import_validation_profiles table with these columns: id bigint unsigned auto increment primary key, tenant_id bigint unsigned not null, entity_type varchar 64 not null, name varchar 128 not null, is_default boolean default false, created_by bigint unsigned not null, created_at and updated_at timestamps. Add index on tenant_id and entity_type together.

Migration two creates the import_column_rules table with these columns: id bigint unsigned auto increment primary key, profile_id bigint unsigned not null with foreign key to import_validation_profiles deleting cascades, column_key varchar 128 not null, mapped_to varchar 128 nullable, display_label varchar 128 nullable, rules JSON not null, is_required boolean default false, default_value varchar 512 nullable, transform JSON nullable, sort_order smallint unsigned default 0. Add index on profile_id.

Migration three creates the import_export_jobs table with these columns: id bigint unsigned auto increment primary key, tenant_id bigint unsigned not null, user_id bigint unsigned not null, ulid char 26 unique not null, type enum of import and export not null, entity_type varchar 64 not null, mode enum of create, update, upsert, delete nullable, is_dry_run boolean default false, input_file_path varchar 512 nullable, output_file_path varchar 512 nullable, original_filename varchar 255 nullable, disk varchar 32 default local, status enum of pending, validating, validated, processing, completing, completed, failed, cancelled with default pending, total_rows int unsigned default 0, processed_rows int unsigned default 0, success_rows int unsigned default 0, failed_rows int unsigned default 0, skipped_rows int unsigned default 0, summary JSON nullable, error_message text nullable, options JSON nullable, validation_profile_id bigint unsigned nullable, column_rules_snapshot JSON nullable, column_mapping JSON nullable, step tinyint unsigned default 1, file_preview JSON nullable, queued_at timestamp nullable, started_at timestamp nullable, completed_at timestamp nullable, created_at and updated_at timestamps. Add indexes on tenant_id with status, on user_id with created_at descending, and on entity_type with type.

Migration four creates the import_row_errors table with these columns: id bigint unsigned auto increment primary key, job_id bigint unsigned not null with foreign key to import_export_jobs deleting cascades, row_index int unsigned not null, row_data JSON nullable, errors JSON not null, created_at timestamp. Add index on job_id.

Migration five creates the system_settings table with these columns: id bigint unsigned auto increment primary key, group varchar 64 not null, key varchar 128 not null, value text nullable, type enum of string, integer, boolean, json, encrypted with default string, updated_by bigint unsigned nullable, updated_at timestamp. Add unique key on group and key together.

After creating that migration, seed the system_settings table with these default import export settings: disk set to local, local_root set to import_exports, s3_bucket empty, s3_region set to us-east-1, s3_key empty, s3_secret empty, s3_url empty, minio_endpoint empty, minio_bucket empty, minio_key empty, minio_secret empty, minio_use_ssl set to true, sftp_host empty, sftp_user empty, sftp_pass empty, sftp_key_path empty, sftp_root set to /imports, signed_url_ttl set to 30, temp_file_ttl set to 1440.

---

STEP 2 — MODELS

Create the following fully implemented Eloquent models.

ImportExportJob model in app/Models. Add fillable for all columns. Add casts for is_dry_run as boolean, summary as array, options as array, column_rules_snapshot as array, column_mapping as array, file_preview as array, queued_at as datetime, started_at as datetime, completed_at as datetime. Add relationships: belongsTo User, belongsTo Tenant, hasMany ImportRowErrors. Add a scope byUlid that accepts a ulid string and queries by the ulid column. Add a scope forCurrentTenant that scopes to the authenticated user's tenant_id. Add the following state transition methods each updating status and relevant timestamps using save: markValidating sets status to validating and started_at to now. markValidated sets status to validated. markProcessing sets status to processing. markCompleting sets status to completing. markCompleted sets status to completed and completed_at to now. markFailed accepts a string message and sets status to failed and error_message. Add an incrementCounters method that accepts processed, success, failed, skipped integers and performs a single raw SQL update using DB::raw increments so it is safe across multiple concurrent workers. Add a buildSummary method that refreshes the model and returns an array with keys total, success, failed, skipped, duration in seconds between started_at and completed_at, is_dry_run, entity_type, mode, and error_download_url which is only set if failed_rows is greater than zero using the route import-export.errors with the ulid.

ImportValidationProfile model in app/Models. Add fillable for all columns. Cast is_default as boolean. Add hasMany ImportColumnRules relationship. Add a static method defaultFor that accepts tenantId and entityType and returns the first profile where tenant_id matches, entity_type matches, and is_default is true, eagerly loading its columnRules. Add a static method forTenantAndEntity returning a query scoped to tenant and entity. Add a static method createFromRules that accepts tenantId, entityType, name, rules array, setDefault boolean, and createdBy, wraps in a DB transaction, and if setDefault is true first sets all other profiles for that tenant and entity to is_default false, then creates the profile and creates ImportColumnRule records for each entry in the rules array, then returns the profile.

ImportColumnRule model in app/Models. Add fillable for all columns. Cast rules as array and transform as array. Add belongsTo ImportValidationProfile.

ImportRowError model in app/Models. Add fillable for all columns. Cast row_data as array and errors as array. Add belongsTo ImportExportJob.

SystemSetting model in app/Models. Add fillable for all columns. Add a static method get that accepts group, key, and a default value, retrieves from cache keyed by import_export_settings colon group colon key with a 60 minute TTL, and decrypts the value when type is encrypted. Add a static method set that accepts group, key, value, and type, upserts the record, and flushes the cache key. Add a static method setMany that accepts group and an associative array and calls set for each entry. Add a static method getEncrypted that accepts group and key and returns the decrypted value or null.

---

STEP 3 — CONTRACTS AND INTERFACES

Create the following interfaces in app/Services/ImportExport/Contracts.

ImportHandler interface with these methods. columns returns an array of column definition arrays each having keys key, label, required boolean, default_rules array, default_transforms array. validateRow accepts a row array and ImportContext and returns an array of field name to array of error strings. processRow accepts a row array and ImportContext and returns an ImportRowResult. afterImport accepts ImportContext and returns void. chunkSize returns an integer.

ExportHandler interface with these methods. columns returns an array. query accepts ExportContext and returns either an Eloquent Builder or a LazyCollection. map accepts a record and ExportContext and returns a flat array. chunkSize returns an integer.

RuleResolver interface with one method resolve that accepts a ruleDef array, an ImportContext, and an optional rows array, and returns an array of Laravel rule strings or Rule objects.

---

STEP 4 — VALUE OBJECTS AND CONTEXT CLASSES

Create ImportContext in app/Services/ImportExport. It is a readonly class with public properties: jobId integer, tenantId integer, userId integer, mode string, isDryRun boolean, filePath string, disk string, options array. Add a static fromJob method that accepts an ImportExportJob model and constructs the object from it. Add a method isStrictMode that returns true when the options array has strict set to true.

Create ExportContext in app/Services/ImportExport as a readonly class with public properties: jobId integer, tenantId integer, userId integer, options array. Add a static fromJob factory.

Create ImportRowResult in app/Services/ImportExport as a readonly class with public properties: success boolean, recordId mixed nullable, message string nullable. Add static factory methods success that accepts recordId and returns an instance with success true, and failure that accepts a message and returns an instance with success false.

Create TestResult in app/Services/ImportExport/Storage as a readonly class with public properties: success boolean, disk string, error string nullable. Add static factory methods success and failure.

---

STEP 5 — RULE RESOLVERS

Create each of the following fully implemented rule resolver classes in app/Services/ImportExport/Validation/Resolvers.

RequiredRuleResolver returns the string required in an array.

NullableRuleResolver returns the string nullable in an array.

StringRuleResolver returns the string string plus min colon value if min key exists in the def plus max colon value if max key exists.

NumericRuleResolver returns numeric plus min and max rules if present.

DecimalRuleResolver returns numeric plus a decimal places rule using the places key defaulting to 2.

EmailRuleResolver returns the string email.

BooleanRuleResolver returns the string boolean.

DateRuleResolver returns the string date plus date_format colon value if format key is present plus after colon value if after key is present plus before colon value if before key is present.

InListRuleResolver returns Rule::in with the values array from the def.

RegexRuleResolver returns regex colon followed by the pattern value from def.

ExistsInDbRuleResolver creates Rule::exists using the table and column from def, then if scope is tenant it calls where tenant_id on the rule with the tenantId from context, then returns the rule in an array.

UniqueInDbRuleResolver checks if context mode is update or upsert and def has except_on set to update, and if so returns an empty array. Otherwise creates Rule::unique using table and column from def, adds tenant scope if scope is tenant, and returns it in an array.

---

STEP 6 — RULE RESOLVER REGISTRY AND ENGINE

Create RuleResolverRegistry in app/Services/ImportExport/Validation. It has a private resolvers array. The constructor registers all resolvers from step 5 with these keys: required, nullable, string, numeric, decimal, email, boolean, date, in_list, regex, exists_in_db, unique_in_db. Add a register method and a get method that throws an InvalidArgumentException for unknown rule names.

Create DynamicRuleEngine in app/Services/ImportExport/Validation. It accepts a RuleResolverRegistry in its constructor. Add a buildValidator method that accepts a rows array, a columnRules array, and an ImportContext. For each column definition, it iterates the rules array, calls the appropriate resolver, and accumulates Laravel validation rules under the key rows dot star dot field name. It prefixes required to the resolved rules when is_required is true and nullable is not already present. It returns a Validator instance using Validator::make with the rows wrapped in an array and the built rules.

Add an applyTransforms method that accepts a row array and columnRules array and runs TransformPipeline::apply for each transform defined on each column, also applying the default_value if the cell is empty. Returns the transformed row.

---

STEP 7 — TRANSFORM PIPELINE

Create TransformPipeline in app/Services/ImportExport/Validation. It has a private static transforms array. The constructor or a static boot registers these transforms as closures: trim, lowercase, uppercase, cast_int, cast_float, cast_bool using filter_var with FILTER_VALIDATE_BOOLEAN and FILTER_NULL_ON_FAILURE, slug using Str::slug, strip_spaces removing all whitespace, nullify_empty converting empty strings to null, date_normalize using Carbon::parse and formatting to Y-m-d. Add a static apply method that accepts a string or array transform definition and a mixed value and applies the matching closure. Add a static register method to allow external registration of custom transforms. Add a static allMeta method that returns a flat array of transform metadata with keys name and label suitable for frontend rendering.

---

STEP 8 — RULE META REGISTRY

Create RuleMetaRegistry in app/Services/ImportExport/Validation. Add a static allRuleMeta method that returns a fully defined array describing every supported rule. Each entry in the array must have keys rule, label, description, and options. The options array describes what configuration inputs the frontend should render for that rule, with each option having keys key, type, and label. Use these types where appropriate: number for numeric inputs, text for free text, tag_input for comma-separated value lists, select for dropdowns with a values array, and db_table_picker for a table selector component. Cover all twelve rules: required, nullable, string, numeric, decimal, email, boolean, date, in_list, regex, exists_in_db, unique_in_db.

---

STEP 9 — IMPORT EXPORT REGISTRY

Create ImportExportRegistry in app/Services/ImportExport as a singleton. It stores a map of entity type strings to arrays containing import_handler and export_handler class name strings. Add a static register method that accepts entityType, importHandlerClass, and exportHandlerClass. Add a static importHandler method that resolves the handler from the container and throws if not registered. Add a static exportHandler method that does the same. Add a static allEntities method that returns all registered entity type strings.

---

STEP 10 — STORAGE MANAGER

Create ImportExportStorageManager in app/Services/ImportExport/Storage. It accepts a SystemSetting dependency resolved from the container. In the constructor it reads the disk setting, builds the appropriate disk config, and registers it as the import_export filesystem disk at runtime using config(). Implement the following public methods completely.

storeUpload accepts an UploadedFile and a directory string and stores it using putFileAs with a ULID-based filename and returns the full path string.

storeContent accepts content string and path string and writes it and returns the path.

storeStream accepts a stream resource and path string and writes it and returns the path.

download accepts a path string, asserts the file exists, and returns a StreamedResponse.

temporaryUrl accepts a path string and optional TTL integer. For local disk it generates a Laravel temporary signed route to the route named import-export.stream passing the path encrypted. For all other disks it uses the filesystem temporaryUrl method.

exists, delete, deleteDirectory, size, readStream all delegate to the underlying disk.

currentDisk returns the disk name string.

Implement private methods buildDiskConfig, localConfig, s3Config, minioConfig, sftpConfig as described in the design. The minioConfig uses the s3 driver with use_path_style_endpoint set to true. The sftpConfig supports both password and private key authentication.

---

STEP 11 — STORAGE TRAIT

Create the trait HandlesImportExportStorage in app/Traits. It has a protected method storageManager that resolves ImportExportStorageManager from the container. Implement these methods fully: storeImportFile accepts UploadedFile and entityType and stores under imports slash entityType slash year slash month. storeExportFile accepts content string, entityType, and extension defaulting to xlsx. storeExportStream accepts stream resource, entityType, and extension. storeErrorReport accepts content string and jobUlid and stores under errors slash jobUlid slash error-report.xlsx. importFileTemporaryUrl and exportFileTemporaryUrl both delegate to the storage manager. deleteImportFile deletes a given path. cleanupJobFiles deletes the errors slash jobUlid directory. readImportStream and importFileExists delegate to storage manager.

---

STEP 12 — QUEUE JOBS

Create ValidateImportJob in app/Jobs. It implements ShouldQueue. Set tries to 1 and timeout to 300. Accept jobId in the constructor. In the handle method: resolve the job, mark it as validating, resolve the handler and context, instantiate SpreadsheetReader and count total rows, update total_rows on the job, then iterate rows lazily. For each row apply transforms using DynamicRuleEngine::applyTransforms using the column_rules_snapshot from the job, then call handler validateRow. Collect errors into a batch array and insert them into import_row_errors in bulk every 500 rows. Broadcast ImportProgressUpdated to the private channel import-job.{ulid} and the private channel user.{userId}.import-jobs every 100 rows with the current phase, processed count, total, and error count. After iterating, flush any remaining error batch. Check whether errors exist. If is_dry_run is true or if errors exist and strict mode is on, mark the job as validated and dispatch GenerateErrorReportJob. Otherwise mark as validated and dispatch ProcessImportJob onto the imports-heavy queue. Handle any Throwable by calling markFailed on the job with the exception message.

Create ProcessImportJob in app/Jobs. It implements ShouldQueue. Set tries to 1 and timeout to 3600. Accept jobId in constructor. In the handle method: resolve the job and mark it as processing. Load all pre-validated error row indexes from import_row_errors and flip them into a hash map for O(1) lookup. Iterate the file in chunks using the handler's chunkSize. For each row increment the index and skip if it is in the error index map. Otherwise call handler processRow inside a try catch. Catch ImportRowException and log a new ImportRowError for that row. After each chunk, call incrementCounters atomically on the job model. Broadcast ImportProgressUpdated with the current phase, processed count, totals, success, and failed counts. After all rows, call handler afterImport. Mark the job as completed and broadcast ImportCompleted with the full summary from buildSummary. Handle any Throwable with markFailed.

Create GenerateErrorReportJob in app/Jobs. Set tries to 2 and timeout to 120. Accept jobId. In the handle method load all ImportRowError records for the job, use FastExcel or Maatwebsite Excel to generate a spreadsheet with columns Row Number, Field, Error, and Original Value, store it using the storage trait storeErrorReport method, update output_file_path on the job, and broadcast ImportCompleted with the summary including the error download URL.

Create ProcessExportJob in app/Jobs. Set tries to 1 and timeout to 3600. Accept jobId. In the handle method resolve the job, context, and handler. Mark the job as processing. Build a PHP generator that calls handler query with lazy cursor and for every record yields the result of handler map, increments a counter, updates processed_rows every 500 records, and broadcasts ImportProgressUpdated. Write the generator to a temporary file path using FastExcel streaming export. Update output_file_path and mark completed. Broadcast ExportCompleted with download URL and row count. Handle Throwable with markFailed.

---

STEP 13 — EVENTS

Create the following event classes in app/Events/ImportExport.

ImportProgressUpdated implements ShouldBroadcast. Constructor accepts jobUlid string, userId integer, and payload array. broadcastOn returns two private channels: import-job.{jobUlid} and user.{userId}.import-jobs. broadcastAs returns the string progress.updated. broadcastWith returns the payload merged with job_ulid.

ImportCompleted implements ShouldBroadcast. Constructor accepts jobUlid string, userId integer, and summary array. broadcastOn returns the same two private channels. broadcastAs returns import.completed. broadcastWith returns summary merged with job_ulid.

ExportCompleted implements ShouldBroadcast. Same structure as ImportCompleted with broadcastAs returning export.completed.

---

STEP 14 — CHANNEL AUTHORIZATION

In routes/channels.php add authorization for the private channel import-job.{ulid} that loads the ImportExportJob by ulid and returns true only when the authenticated user's tenant_id equals the job's tenant_id. Add authorization for user.{userId}.import-jobs that returns true only when the authenticated user's id equals the userId parameter cast to integer.

---

STEP 15 — SPREADSHEET READER

Create SpreadsheetReader in app/Services/ImportExport. It wraps Maatwebsite Excel and accepts a file path and disk name. Add a static for factory method. Add a static preview method that accepts path and a row count integer defaulting to 6 and returns an array with headers as the first row values and rows as up to that many subsequent rows. Add a headers method returning the first row as an array of strings. Add a count method returning the total data row count excluding the header. Add a lazyRows method returning a generator that yields each row as an associative array keyed by the header row values, skipping completely empty rows. Add a chunkRows method that accepts a chunk size integer and yields arrays of associative row arrays in chunks of that size. Handle both local and S3-compatible disks by opening streams through the ImportExportStorageManager.

---

STEP 16 — CONTROLLERS

Create ImportWizardController in app/Http/Controllers/ImportExport. Use the HandlesImportExportStorage trait. Add these five fully implemented actions.

upload validates that entity_type is a string and exists in the registry, that file is a required file with mimes xlsx, xls, csv and max 20480 kilobytes, and that mode is one of create, update, upsert. Then stores the file using storeImportFile, reads the preview using SpreadsheetReader::preview, loads the default validation profile for the entity, creates the ImportExportJob record with step 1, and returns a JSON response with ulid, headers, preview_rows, system_fields from the handler, default_profile, saved_profiles for this tenant and entity, and step 1.

headers loads the job by ulid scoped to current tenant and returns the file_preview from the job as JSON.

saveMapping validates that mapping is a required array where each value is a string. Updates column_mapping and step 2 on the job and returns JSON with ulid, mapping, and step.

getRules loads the job, resolves the handler, merges system column definitions with any saved default profile rules, applies the column_mapping to set the correct column_key values, and returns JSON with ulid, column_rules, available_rules from RuleMetaRegistry, available_transforms from TransformPipeline::allMeta, saved_profiles, and step 3.

saveRules validates that column_rules is a required array, save_as_profile is an optional boolean, profile_name is required when save_as_profile is true, and set_as_default is an optional boolean. Snapshots column_rules onto the job, increments step to 3. If save_as_profile is true calls ValidationProfile::createFromRules. Returns JSON with ulid and step.

confirm validates that is_dry_run is boolean, mode is one of the four modes, and options is an optional array. Updates the job, dispatches ValidateImportJob to the imports-validation queue, and returns JSON with ulid, channel name, and step 4.

Create ExportController in app/Http/Controllers/ImportExport. Add an initiate action that validates entity_type and options, creates an ImportExportJob with type export, dispatches ProcessExportJob, and returns ulid and channel. Add a download action that loads the job by ulid, calls temporaryUrl from the storage manager, and redirects to it. Add an errors action that loads the job by ulid, verifies the current user belongs to the same tenant, calls temporaryUrl on the output_file_path, and redirects.

Create ImportJobController in app/Http/Controllers/ImportExport. Add an index action that returns all jobs for the authenticated user ordered by created_at descending with a limit of 50. Add a show action returning a single job by ulid with its row error count. Add a cancel action that only cancels jobs with status pending or validating by setting status to cancelled. Add a stream action that is the signed URL target for local disk downloads, decrypts the path from the request, asserts the file exists, and returns a streamed download response.

Create Admin/StorageSettingsController in app/Http/Controllers/Admin. Add a show action returning all system_settings where group is import_export, masking encrypted values to show only whether they are set. Add an update action that validates the incoming settings, calls SystemSetting::setMany, then instantiates StorageConnectionTester and tests the connection, and if the test fails returns a 422 with the error message. If it passes, forgets the ImportExportStorageManager singleton from the container and returns a success message.

---

STEP 17 — FORM REQUESTS

Create UploadImportFileRequest, SaveMappingRequest, SaveRulesRequest, and ConfirmImportRequest in app/Http/Requests/ImportExport. Each must have an authorize method returning true and a rules method with full Laravel validation rules as described in step 16. SaveRulesRequest must use a sometimes rule to require profile_name only when save_as_profile is present and true.

---

STEP 18 — ROUTES

In routes/api.php under an auth middleware group create the following routes. POST import-export/imports/upload to ImportWizardController upload. GET import-export/imports/{ulid}/headers to headers. POST import-export/imports/{ulid}/mapping to saveMapping. GET import-export/imports/{ulid}/rules to getRules. POST import-export/imports/{ulid}/rules to saveRules. POST import-export/imports/{ulid}/confirm to confirm. GET import-export/jobs to ImportJobController index. GET import-export/jobs/{ulid} to ImportJobController show. POST import-export/jobs/{ulid}/cancel to ImportJobController cancel. GET import-export/jobs/{ulid}/errors to ExportController errors. GET import-export/jobs/{ulid}/download to ExportController download. POST import-export/exports to ExportController initiate. GET import-export/templates/{entity} to a TemplateController download action you will create. GET import-export/stream to ImportJobController stream wrapped in the signed middleware. In routes/api.php under auth plus admin middleware create GET admin/storage-settings to StorageSettingsController show and PUT admin/storage-settings to update.

Name the stream route import-export.stream and the errors route import-export.errors.

---

STEP 19 — HORIZON CONFIGURATION (we will implement this later as we are on local and Windows OS so its not supported directly in Windows)

In config/horizon.php under the production environment define three supervisor pools. The first pool is named imports-validation, uses the redis connection, listens to the imports-validation and exports queues, uses auto balance strategy, has minProcesses 2 and maxProcesses 10, timeout 360, and tries 1. The second pool is named imports-heavy, listens to the imports-heavy queue, uses auto balance, has minProcesses 2 and maxProcesses 5, timeout 3660, and tries 1. The third pool is named imports-reports, listens to the imports-reports queue, has minProcesses 1 and maxProcesses 3, timeout 120, and tries 2.

---

STEP 20 — SERVICE PROVIDER

Create ImportExportServiceProvider in app/Providers. In the register method bind ImportExportStorageManager as a singleton resolving it from the container with the SystemSetting dependency injected. Bind DynamicRuleEngine as a singleton. Bind RuleResolverRegistry as a singleton. In the boot method register these entity handlers: products with ProductImportHandler and ProductExportHandler, inventory with InventoryImportHandler and InventoryExportHandler. Register the service provider in bootstrap/providers.php.

---

STEP 21 — EXAMPLE HANDLERS

Create ProductImportHandler in app/Services/ImportExport/Handlers implementing ImportHandler. The columns method returns definitions for name required, sku required with default rules of required and regex matching uppercase letters digits and hyphens between 3 and 32 characters, category_code required with default rule exists_in_db on categories table column code scoped to tenant, barcode optional, cost_price optional with numeric rule min 0, sell_price required with numeric rule min 0, and type optional with in_list rule for standard variable service digital serialized combo. The validateRow method uses the DynamicRuleEngine but also adds a manual check that if mode is create and a product variant with the given sku already exists for the tenant then it adds an error. The processRow method performs an updateOrCreate on the Product model matching by sku and tenant_id, then creates or updates the default ProductVariant, and returns ImportRowResult::success with the product id. The afterImport method flushes the product cache tags for the tenant. chunkSize returns 200.

Create ProductExportHandler in app/Services/ImportExport/Handlers implementing ExportHandler. The columns method returns the same fields as the import handler. The query method returns a Product query scoped to tenant with variants eagerly loaded, accepting an optional category_id filter from context options. The map method flattens a product and its first variant into a row array matching the column keys. chunkSize returns 500.

Create InventoryImportHandler and InventoryExportHandler with similar structure handling warehouse_code, sku, qty, batch_no, and expiry_date columns. The processRow for inventory finds the warehouse by code scoped to tenant, finds the product variant by sku scoped to tenant, then calls InventoryService::adjust to apply the quantity as an opening_balance movement. Returns ImportRowResult::success or throws ImportRowException on any failure.

---

STEP 22 — EXCEPTION CLASS

Create ImportRowException in app/Exceptions/ImportExport extending RuntimeException. Add a static factory method fromValidationErrors accepting a field to errors array and formatting them into a readable message string.

---

STEP 23 — CLEANUP COMMAND

Create a console command PruneImportExportFiles in app/Console/Commands. It should accept an optional --days option defaulting to 7. It queries ImportExportJob where completed_at is older than the given number of days and status is completed or failed or cancelled. For each job it calls cleanupJobFiles using the storage trait if a ulid is set, deletes the input file if it exists, deletes the output file if it exists, and deletes all ImportRowError records for that job. Register this command and schedule it to run daily at 2am in the console kernel or bootstrap/app.php scheduler.

---

STEP 24 — FINAL WIRING CHECKS

After implementing everything above, verify the following. The ImportExportServiceProvider is registered in bootstrap/providers.php. The Horizon queues imports-validation, imports-heavy, and imports-reports are defined. The Reverb broadcast driver is set as the default broadcast driver in the broadcasting config. The import_export disk is registered at runtime and not hardcoded in config/filesystems.php. All models have correct table names set if they differ from Laravel conventions. All jobs use SerializesModels and store only the integer job id rather than the full model to avoid payload bloat. All broadcast events use the ShouldBroadcast interface. The channel authorization routes cover both private channels. The signed route for local disk streaming is named correctly and the controller decrypts the path before streaming.

---

Do not ask for clarification. Implement everything completely from top to bottom as described. Every method must have a real implementation. Every migration must be complete SQL. Every job must handle exceptions and call markFailed. Every controller must return proper HTTP status codes. Start with the migrations in order, then models, then contracts, then the service layer, then jobs, then events, then routes, then the service provider.