# Render Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deploy Laravel/Vue KAI RAMS to Render with MySQL, Redis, a dedicated worker, and shared S3-compatible workbook storage while preserving all RAMS calculations.

**Architecture:** The web and worker share database, Redis, and object storage but do not share a filesystem. A focused storage service streams queued workbooks to a temporary local file before invoking the unchanged import service. A multi-stage Docker image and Render Blueprint define repeatable production infrastructure.

**Tech Stack:** PHP 8.3, Laravel 13, Vue 3, Vite 8, Node 22, MySQL 8, Redis/Render Key Value, Flysystem S3, Docker, Render Blueprint.

---

### Task 1: Stabilize the Test Baseline

**Files:**
- Modify: `tests/js/setup.js`
- Modify: `package.json`
- Modify: `.env.testing.example`

- [ ] Add a deterministic Web Storage implementation to the Vitest setup.
- [ ] Run `npm run test:js -- tests/js/MainLayout.test.js` and confirm the previously failing test passes.
- [ ] Run `npm run test:js` and expect 160 passing tests.
- [ ] Pin supported local and Docker Node versions to Node 22.
- [ ] Run PHP tests only after the shared MySQL test runner releases `rams_testing`.
- [ ] Run each schema-mutating Feature test against a freshly migrated test database.
- [ ] Commit the baseline-only changes separately.

### Task 2: Specify the Shared Workbook Storage Behavior

**Files:**
- Create: `tests/Feature/RamsImportWorkbookStorageTest.php`
- Modify: `tests/Feature/RamsImportQueueTest.php`
- Modify: `tests/Feature/RamsImportHistoryTest.php`

- [ ] Write a failing test that configures `RAMS_IMPORT_DISK` to a fake non-default disk and asserts the submitted workbook is stored there.
- [ ] Write a failing test that asserts a batch records `storage_disk` with `stored_path`.
- [ ] Write a failing test that stores known bytes, materializes them, and asserts SHA-256 equality.
- [ ] Write a failing test that throws inside the materialization callback and asserts the temporary file is removed.
- [ ] Write a failing job test that asserts a successful job removes the stored object.
- [ ] Run only these tests and confirm failure because the storage abstraction and column do not exist.

### Task 3: Implement the Storage Abstraction

**Files:**
- Create: `config/rams.php`
- Create: `app/Services/RamsImportWorkbookStorage.php`
- Create: `database/migrations/2026_08_31_000000_add_storage_disk_to_rams_import_batches.php`
- Modify: `app/Models/RamsImportBatch.php`
- Modify: `app/Services/RamsImportSubmissionService.php`
- Modify: `app/Jobs/ProcessRamsWorkbookImport.php`
- Modify: `.env.example`

- [ ] Add `RAMS_IMPORT_DISK=local` and a private import directory setting.
- [ ] Add nullable `storage_disk` before `stored_path` and backfill active stored paths to `local`.
- [ ] Implement stream-based `store`, `withLocalCopy`, and `delete` operations.
- [ ] Inject the storage service into submission and job handlers.
- [ ] Preserve stored objects for job retries and delete them only on terminal outcomes.
- [ ] Run the focused tests and expect them to pass.
- [ ] Run existing import queue/history/rollback tests.
- [ ] Commit the storage boundary.

### Task 4: Add S3-Compatible Support

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `config/filesystems.php`
- Modify: `.env.example`

- [ ] Install `league/flysystem-aws-s3-v3` with Composer.
- [ ] Verify the S3 disk accepts AWS S3 and Cloudflare R2 endpoints.
- [ ] Enable `throw` for the dedicated import disk path so storage failures cannot appear successful.
- [ ] Run focused storage tests and Composer validation.
- [ ] Commit S3-compatible storage support.

### Task 5: Build the Production Container

**Files:**
- Create: `Dockerfile`
- Create: `.dockerignore`
- Create: `docker/apache-vhost.conf`
- Create: `docker/php-production.ini`

- [ ] Build Vue assets in a Node 22 stage using `npm ci` and `npm run build`.
- [ ] Install Composer production dependencies in a Composer stage.
- [ ] Build a PHP 8.3 Apache runtime with required extensions.
- [ ] Copy only production artifacts and set writable Laravel directories.
- [ ] Configure Apache to serve `/var/www/html/public` with rewrite support.
- [ ] Run `docker build` and require exit code 0.
- [ ] Start the image locally against existing MySQL/Redis and require `/up` HTTP 200.
- [ ] Commit the container files.

### Task 6: Define Render Infrastructure

**Files:**
- Create: `render.yaml`
- Modify: `.env.example`
- Create: `docs/deployment/render.md`

- [ ] Define a Docker web service with `/up` health check and migration pre-deploy command.
- [ ] Define a Docker worker listening to `rams-imports,default` with timeout 900.
- [ ] Define Render Key Value and wire its internal connection to web and worker.
- [ ] Define MySQL 8 as a private image service with a `/var/lib/mysql` disk.
- [ ] Mark S3-compatible credentials as user-provided secrets.
- [ ] Disable debug and demo seeding in production.
- [ ] Document initial deployment, database backup, rollback, and object-storage setup.
- [ ] Validate the Blueprint schema against current Render documentation.
- [ ] Commit the Blueprint and deployment guide.

### Task 7: Verify Formula and Import Parity

**Files:**
- Modify only tests if a deployment-specific assertion is missing.

- [ ] Run all JavaScript tests.
- [ ] Run all PHP tests with an isolated MySQL testing database.
- [ ] Run `npm run build`.
- [ ] Run Composer validation.
- [ ] Build the Docker image from a clean context.
- [ ] Import a known workbook through the container worker.
- [ ] Compare hash, row counts, reliability, availability, failure rate, MTBF, MTTF, and risk summaries with local results.
- [ ] Confirm Redis queues, reserved jobs, delayed jobs, and failed jobs are empty.

### Task 8: Prepare and Deploy in the User's Render Workspace

**External changes:**
- Render Blueprint resources
- User-provided S3-compatible bucket credentials

- [ ] Push the deployment branch only after local verification passes.
- [ ] Open the signed-in Render Blueprint flow and select the repository/branch.
- [ ] Review every proposed service and displayed monthly cost with the user.
- [ ] Stop for explicit approval before creating paid resources.
- [ ] Enter user-provided object-storage secrets without printing or committing them.
- [ ] Apply the Blueprint and monitor migrations, web health, and worker startup.
- [ ] Run a dry-run workbook, then a real import after user approval.
- [ ] Confirm UI status reaches 100% and database results match local parity values.

