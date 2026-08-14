# Regional Category and Query Consistency Fix

## Context

RAMS now stores asset categories per DAOP or DIVRE. The database migration added `unit_kerja_id` to `asset_groups` and category source aliases, but several queries, tests, and import identities still follow the former global-category contract. This incomplete transition causes ambiguous SQL columns, stale test expectations, and unstable import keys.

## Decisions

1. Asset categories remain scoped per unit kerja. The application must not restore global uniqueness.
2. Every joined query must qualify `unit_kerja_id` with its table name.
3. Import identities must use stable business context rather than database-generated IDs.
4. Existing source keys remain valid lookup candidates so an upgrade updates records instead of duplicating them.
5. Controllers must preserve `unit_kerja_id` across category-management redirects.

## Query Contract

Model scopes that filter records by unit must use the model table explicitly. For example, `InventoryStock::visibleTo()` filters `inventory_stocks.unit_kerja_id`, while `StockMovement::visibleTo()` filters `stock_movements.unit_kerja_id`. This rule prevents ambiguity when the query joins `asset_groups`, which also contains `unit_kerja_id`.

Regression tests will exercise the real inventory route with the same joins and pagination that previously produced HTTP 500.

## Category Scope Contract

An asset group is unique by `unit_kerja_id` and normalized name. A source alias is unique by category type, unit kerja, and normalized source path. Two units may use the same category name or workbook path without sharing records.

System and subsystem uniqueness remains relative to their parent category. Category-management requests and redirects carry the selected unit so the UI remains in the correct DAOP or DIVRE.

Tests that assert global uniqueness will be replaced with assertions for per-unit uniqueness: duplicates inside one unit are rejected, while the same normalized name in another unit is accepted.

## Stable Import Identity

The canonical master-asset source key will use normalized business identifiers:

- unit kerja code;
- source sheet name;
- normalized group, system, and subsystem path.

It will not use `unit_kerjas.id` or `asset_subsystems.id`, because those values can change after database recreation. During import, the matcher will also accept the current ID-based key and legacy workbook key. When an existing record matches an old key, the importer will update it to the canonical key without creating another asset.

Unit-subsystem opening records follow the same compatibility rule.

## Compatibility and Error Handling

The resolver keeps `unitKerjaId` as an explicit optional argument for legacy callers. Test doubles and command helpers must implement the same signature. Soft-deleted category conflicts will continue to stop the workbook transaction and report the workbook, sheet, and row that caused the conflict.

No migration will delete operational data. Existing records are migrated lazily when the relevant workbook is imported again.

## Verification

The implementation must prove:

- regional inventory pages no longer generate ambiguous-column SQL errors;
- identical category names are accepted across different units and rejected within one unit;
- category redirects retain the selected unit;
- database recreation does not change canonical import identities;
- legacy and ID-based keys update the same record without duplication;
- resolver test helpers accept the regional scope parameter;
- focused backend tests, the full frontend suite, and the production build pass.

## Out of Scope

This change does not alter Excel reliability formulas, UI layout, Redis queue behavior, or monthly/yearly import policy.
