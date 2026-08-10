# RAMS Import Idempotency Design

## Scope

Import workbook RAMS must preserve batch and Excel comparison history while keeping the active operational tables idempotent. This change covers assets, trouble reports, risk registers, risk matrices, and spare parts. It explicitly does not change which Excel cell is authoritative for `Jumlah Unit`.

## Identity and update rules

- Operational records use a stable key derived from the destination unit, source sheet, and stable source position or source business key. Workbook hashes remain audit metadata only.
- A matching stable identity with changed values updates the existing active record.
- A matching stable identity with identical values performs no write and is counted as `Tidak berubah` and `Duplikat dilewati`.
- Conflicting duplicate identities found inside one workbook are rejected for automatic import and reported with both source locations.
- Batch rows and reliability Excel snapshots remain append-only history.

## Blank-cell policy

- Imported text fields use `-` when the source cell is blank. This includes `Penyebab` and `Tindakan`, so otherwise valid failure rows are retained.
- Numeric and date fields remain `NULL` when blank. Presentation code renders those values as `-`.

## Import result contract

The combined import result exposes:

- `data_updated`: active rows changed across operational importers.
- `data_unchanged`: active rows whose imported values were identical.
- `duplicates_skipped`: identical or conflicting duplicate rows not written again.
- `duplicate_locations`: source sheet/row descriptions for duplicate rows.

Detailed per-entity counters remain available for audit and backward compatibility.

## Non-goals

- Do not change the Excel source used for asset `Jumlah Unit`.
- Do not delete historical import batches or reliability comparison snapshots.
- Do not recalculate or redesign reliability formulas in this change.
