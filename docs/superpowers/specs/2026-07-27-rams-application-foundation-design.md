# RAMS Application Foundation Design

Date: 2026-07-27

Status: Approved in discussion

## 1. Purpose

Build one internal web application for PT KAI's Risk Analysis and Management System (RAMS). The application replaces separate regional Excel workbooks with one MySQL database while preserving logical separation between Daop and Divre units.

The application covers master assets, risk analysis, failure and reliability records, predictive inventory, reorder stock, dashboards, reports, and audit history.

## 2. Source Material

The design uses every file in `D:\KAI RAMS`:

- Five DOCX files define requirements, database concepts, use cases, user flow, and the project timeline.
- Five XLSM files for Daop 1, Daop 4, Daop 8, Divre III, and Divre IV provide source data, formulas, and validation examples.
- `RAMS_usecase.jpg` provides the use-case reference.

Excel files supply migration data and formula examples. They do not serve as the application's runtime database.

## 3. Architecture

The system uses one repository and one deployable Laravel application:

- Laravel 13 handles routes, controllers, validation, authorization, business rules, calculations, jobs, and persistence.
- Inertia.js connects Laravel controllers to Vue pages.
- Vue 3 implements pages, forms, tables, filters, charts, and interactive states.
- A minimal Blade root hosts Inertia. RAMS pages are Vue components rather than Blade pages.
- Laravel session authentication and CSRF protection secure browser access.
- A REST API is outside the first release. It may be added later for an external consumer without replacing the Inertia application.

## 4. Database Environment

MySQL 8.4 LTS is the database for development, testing, demonstration, and production. Development and automated tests run MySQL 8.4 through Docker Compose. The Docker image uses a fixed `mysql:8.4` tag rather than `latest`.

The environment must define the same character set, collation, SQL mode, and timezone in every deployment. Docker uses a named volume for development data. Automated tests use a separate database, such as `rams_testing`, and never use SQLite.

Secrets remain in local environment files and do not enter version control.

## 5. Users and Authorization

The application has two roles:

### Akun Pusat

- Views and manages data from every Daop and Divre.
- Creates, edits, deactivates, and resets Daop/Divre accounts.
- Manages unit kerja and global reference data.
- Manages master assets for every unit.

### Akun Daop/Divre

- Views and manages data assigned to its own `unit_kerja_id`.
- Manages its own master assets and related RAMS records.
- Cannot create accounts or manage global reference data.
- Cannot read or change another unit's records.

Laravel determines `unit_kerja_id` from the authenticated account and authorized resource. The server never trusts a unit identifier submitted by the browser. Policies, query scopes, validation, and database relationships enforce this boundary.

Accounts and business records use an active status or soft deletion where applicable. The application does not permanently delete important records through normal user actions.

## 6. Domain Boundaries

The first design separates the application into these modules:

1. **Identity and organization:** users, roles, unit kerja, authentication, and account status.
2. **Reference data:** global categories and lookup values managed in the database rather than hard-coded in Vue.
3. **Master Aset:** the authoritative identity for an asset, including its system and subsystem hierarchy.
4. **Risk:** likelihood, consequence, rating, category, controls, and risk-register history.
5. **Failure and reliability:** failure-event logs, downtime, MTTF, MTBF, failure rate, reliability, and availability.
6. **Predictive inventory:** asset condition, stock inputs, replacement needs, and proposal results.
7. **Reorder stock:** lead-time and failure inputs, safety stock, lead-time demand, and reorder point.
8. **Reporting:** dashboards, filters, summaries, and exports based on authorized data.
9. **Governance:** audit logs, import batches, import issues, and calculation snapshots.

Master Aset owns system and subsystem data. Risk, failure, reliability, predictive inventory, and reorder records reference `aset_id`. Relationships prevent a module record from pointing to an asset in another unit.

## 7. Calculation Model

The application uses a hybrid calculation model:

- MySQL stores original inputs.
- Focused Laravel services calculate RAMS results.
- The application stores each material result as a snapshot with its formula version and calculation time.
- A change to source inputs triggers recalculation.
- Historical snapshots remain available for audit and reporting.
- Vue displays results but does not implement authoritative formulas.

The calculation services cover risk rating, MTTF, MTBF, failure rate, reliability, availability, inventory needs, safety stock, lead-time demand, and reorder point.

Missing inputs or a zero denominator produce a defined status such as `insufficient_data`; they never produce `#DIV/0!`, infinity, or a fabricated number. Each result records enough source context to explain how it was calculated.

The first formula version follows the documented KAI definitions, including MTTF as the average interval between failures. Tests compare results with accepted Excel examples. Source anomalies remain explicit: the importer records category text found in the Price column, broken references, and unmatched rules instead of coercing them silently.

## 8. Excel Migration

The first release uses a controlled, one-time import command rather than a user-facing upload page.

The importer:

1. Reads all five XLSM files in the source folder.
2. Creates or resolves the unit from the source filename.
3. Maps source rows to normalized MySQL records.
4. Records the source file, sheet, and row number for traceability.
5. Validates relationships, types, required values, and duplicate keys.
6. Writes valid rows in transactions.
7. Records invalid rows in an import-issue report without silently changing their meaning.
8. Supports a dry run before committing data.
9. Prevents accidental duplicate imports through an import-batch fingerprint.

The same mapping structure can process additional Daop or Divre files when they become available.

## 9. Validation and Error Handling

Laravel validates every write. Vue performs convenience validation but does not replace server validation. Inertia returns field-level errors to the relevant form.

Database foreign keys, unique constraints, and transactions protect integrity. Multi-record operations either complete fully or roll back. Validation rejects negative quantities, illogical dates, invalid asset relationships, and unauthorized unit access.

The application shows users clear corrective messages. Operational exceptions enter application logs. Important user actions, status changes, imports, and recalculations enter the audit log.

## 10. Testing

Automated tests run against a dedicated MySQL 8.4 database.

- Feature tests cover login, logout, account status, permissions, CRUD flows, validation, and unit isolation.
- Authorization tests prove that a Daop/Divre account cannot access another unit by changing a URL or request payload.
- Unit tests cover every formula and boundary condition.
- Import tests use representative rows from every XLSM workbook and verify issue reporting.
- Transaction tests prove that failed multi-record operations leave no partial data.
- Vue component tests cover important forms, filters, and error presentation.
- Browser tests cover login and the main end-to-end CRUD path.

Tests use factories and controlled fixtures. They do not depend on a developer's persistent database.

## 11. Delivery Order

Implementation proceeds in vertical slices:

1. Docker Compose, MySQL connections, and separate development and test databases.
2. Inertia.js, Vue 3, base layout, and session authentication.
3. Unit kerja, two-role authorization, account management, and audit foundations.
4. Reference data and Master Aset CRUD with unit isolation.
5. Risk module and versioned risk calculations.
6. Failure logs and reliability calculations.
7. Predictive inventory and reorder-stock calculations.
8. One-time XLSM importer with dry-run and issue reports.
9. Dashboards, reports, exports, and full regression testing.

Each slice includes migrations, models, policies, validation, UI, and automated tests before the next slice begins.

## 12. First-Release Exclusions

The first release excludes:

- A separate frontend repository.
- A user-facing Excel upload feature.
- A general public REST API.
- Multiple database engines.
- Permanent deletion of audited business records through the UI.
- Reimplementation of Excel UI macros that do not carry business rules.

## 13. Acceptance Criteria

The foundation is accepted when:

- A developer can start MySQL 8.4 consistently through Docker Compose.
- Laravel connects to separate development and test databases.
- Users authenticate through Laravel sessions and open Vue pages through Inertia.
- Akun Pusat manages all units and regional accounts.
- Akun Daop/Divre manages only its own assets and RAMS records.
- The database enforces core relationships and unit boundaries.
- Formula services return tested, versioned results and defined insufficient-data states.
- All five Excel files can complete a dry run with valid rows and traceable issue reports.
- Automated tests pass against MySQL 8.4.
