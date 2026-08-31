# Render Deployment Design

## Objective

Deploy KAI RAMS to Render without changing workbook parsing, RAMS formulas, risk calculations, or regional data isolation. The deployment must keep queued Excel imports recoverable when the web and worker run as separate services.

## Chosen Architecture

The production topology contains five boundaries:

1. A Docker web service serves Laravel, Inertia, and the compiled Vue/Vite assets.
2. A Docker background worker runs the `rams-imports,default` Redis queues.
3. Render Key Value provides the shared Redis queue and cache.
4. MySQL 8 runs as a private service with `/var/lib/mysql` on a persistent disk.
5. An S3-compatible private bucket stores queued workbooks until processing finishes.

The web and worker use the same Docker image, application revision, MySQL database, Redis instance, and object-storage credentials.

## Import Data Flow

1. The web service validates the upload and calculates the workbook fingerprint.
2. The web service writes the workbook to the configured `RAMS_IMPORT_DISK`.
3. The import batch records both `storage_disk` and `stored_path`.
4. The web service dispatches `ProcessRamsWorkbookImport` to `rams-imports`.
5. The worker streams the object into an operating-system temporary file.
6. The existing `FailureLogImportService` receives that temporary path unchanged.
7. The worker deletes the temporary file in a `finally` block.
8. After a successful import, the worker deletes the object and clears its database location.
9. During retryable failures, the object remains available. After the final failed attempt, `failed()` deletes it and records the error.

Object storage changes file transport only. The coordinator, importers, formula evaluation, transactions, audit records, and report calculations remain unchanged.

## Storage Boundary

`RamsImportWorkbookStorage` owns three operations:

- Store an uploaded workbook on a configured private disk.
- Materialize a stored object as a temporary local file for PhpSpreadsheet.
- Delete a stored object after terminal success or failure.

The service always copies bytes through streams. It never calls `path()` on a remote adapter. This keeps local storage available for development and S3-compatible storage available for Render.

## Container Design

The image uses Node 22 to compile Vite assets and PHP 8.3 with Apache for runtime. Composer installs production dependencies from `composer.lock`. The runtime includes `pdo_mysql`, `bcmath`, `intl`, `pcntl`, `zip`, `gd`, and OPcache. Apache serves only the Laravel `public` directory and binds to the container port Render exposes.

The web command starts Apache. The worker overrides the image command with:

```text
php artisan queue:work redis --queue=rams-imports,default --sleep=1 --tries=2 --timeout=900
```

`REDIS_QUEUE_RETRY_AFTER` remains greater than the worker timeout.

## Database and Deployment

MySQL stays on version 8, avoiding a database-engine migration. A pre-deploy command runs `php artisan migrate --force`. Production seeding is disabled. MySQL is reachable only on Render's private network and persists under `/var/lib/mysql`.

The first deployment creates an empty schema. Importing existing local data is a separate, explicit operation preceded by a `mysqldump` backup.

## Secrets and Billing Boundary

The repository contains variable names, never values. Render prompts for object-storage credentials and other user-owned secrets. Generated database passwords and `APP_KEY` remain in Render.

The agent may inspect and prepare the signed-in Render workspace. It must stop before creating a paid service, disk, or billable worker until the user approves the displayed cost.

## Error Handling and Observability

- Missing objects produce a batch failure with a specific storage error.
- Temporary files are removed after success and exceptions.
- Retryable exceptions retain the remote workbook.
- Final failure removes the remote workbook and sets `finished_at`.
- The worker listens to `rams-imports,default`; this prevents jobs from remaining at 0%.
- Web health checks use Laravel's `/up` endpoint.
- Render logs expose web, migration, and worker failures separately.

## Testing

Tests cover the configured upload disk, recorded disk/path, remote stream materialization, byte preservation, temporary cleanup, successful object deletion, and final-failure cleanup. Existing import and formula tests run without changing expected results.

Deployment verification uses one known workbook locally and on Render. The comparison includes workbook hash, counts, uptime, downtime, reliability, availability, failure rate, MTBF, MTTF, risk records, warnings, and errors.

## Explicit Non-Goals

- No UI redesign.
- No formula or importer rewrite.
- No migration from MySQL to PostgreSQL.
- No public phpMyAdmin service.
- No production data deletion.
- No automatic purchase or creation of billable Render resources.

