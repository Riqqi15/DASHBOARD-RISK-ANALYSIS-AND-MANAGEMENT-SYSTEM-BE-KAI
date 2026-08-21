# Regional Operational Data Scope Design

**Date:** 2026-08-21  
**Status:** Approved for planning  
**Scope:** Master Aset, Risk Register, and Inventori Suku Cadang

## Objective

Prevent operational data from multiple Daop and Divre from being combined in one view. Admin Pusat must work within one explicitly selected unit at a time. Regional users remain locked to their assigned unit.

## Business Rules

1. Master Aset, Risk Register, current stock, stock movements, predictive inventory, and Excel reconciliation always use exactly one active `unit_kerja` scope.
2. Admin Pusat may change the active Daop or Divre, but cannot select an aggregate "Semua unit kerja" view in these operational modules.
3. When Admin Pusat opens a scoped module without a valid unit parameter, the backend selects the first active unit ordered by code.
4. A regional account always uses its assigned active unit. Client-supplied unit parameters cannot override it.
5. Search, status, category, date, pagination, and tab changes preserve the active unit.
6. Resetting filters clears only secondary filters and preserves the active unit.
7. Empty global taxonomy branches are not displayed as operational Master Aset data. The page shows only category paths represented by assets in the selected unit and current secondary filters.
8. Risk Register records and asset choices must belong to the active unit. Create, update, and delete authorization must reject cross-unit access.
9. Inventory stock quantities and stock movements are regional. Queries, statistics, predictive data, and reconciliation must all use the same active unit.
10. Spare-part identity remains global because one code may be used by multiple units. Only Admin Pusat may create, update, or deactivate global spare-part identities.
11. Regional users may view active spare-part identities needed for their unit's stock operations, but cannot manage the global master.
12. User-facing inventory wording uses `stok`, not `saldo`. Preferred labels are `Stok Saat Ini`, `Stok Masuk`, `Stok Keluar`, `Stok Awal`, and `Riwayat Pergerakan Stok`.

## Module Design

### Master Aset

- Replace the optional unit filter with a required active-unit selector for Admin Pusat.
- Remove the `Semua unit kerja` option.
- Scope asset rows, statistics, unique subsystem counts, hierarchy totals, and pagination to the selected unit.
- Build the displayed hierarchy from matched regional assets only; do not merge unused global category branches into the operational table.
- Keep the selected unit when search or status filters are applied or reset.
- Keep regional accounts fixed to their own unit and hide the unit selector.

### Risk Register

- Retain the existing area selector, which already defaults to the first active unit for Admin Pusat.
- Verify that the register list and asset options use the same selected unit.
- Preserve the selected area after create, update, and delete redirects.
- Enforce the selected unit in backend mutation rules so a crafted request cannot attach a risk record to another unit's asset.
- Keep regional accounts fixed to their assigned unit.

### Inventori Suku Cadang

- Replace the optional unit filter with a required active-unit selector for Admin Pusat.
- Remove the `Semua unit kerja` option.
- Apply one unit scope consistently to stock rows, stock statistics, movement history, predictive asset data, reconciliation rows, reconciliation statistics, movement dialogs, and stock-state checks.
- Preserve the selected unit across tab changes, searches, category filters, dates, pagination, retries, and filter reset.
- Keep global spare-part identity management in the `Master Suku Cadang` tab visible only to Admin Pusat.
- Global identity filtering may use text and category, but must not present global identity counts as regional stock counts.
- Replace user-facing uses of `saldo` with `stok`; internal database names may remain unchanged when renaming would add migration risk without business value.

## Unit Selection Contract

All three modules use this resolution order:

1. Regional user: assigned active `unit_kerja_id`.
2. Admin Pusat with a valid active unit parameter: requested unit.
3. Admin Pusat without a valid parameter: first active unit ordered by code.
4. No active unit exists: return a clear empty state and disable regional data actions instead of falling back to aggregate data.

Invalid or inactive unit identifiers must not silently enable an all-unit query.

## Data Flow

1. Request resolves one effective unit on the server.
2. Server applies the effective unit before calculating rows, statistics, and selectable related records.
3. Server returns the effective unit and active-unit list to the page.
4. Page includes the effective unit in every navigation or filter request made by Admin Pusat.
5. Mutating requests validate ownership against the authenticated user's allowed unit and the selected unit context.
6. Imported records appear only in the unit detected or explicitly validated during import.

## Error and Empty States

- Invalid or inactive unit: fall back to the first active unit for read-only index navigation and expose the resolved unit in the response.
- No active units: show `Belum ada unit kerja aktif` and disable create, movement, and reconciliation actions that require a unit.
- No data in the selected unit: show a regional empty state naming the active Daop or Divre.
- Cross-unit mutation: reject with validation or authorization error; never move data implicitly.
- Import mismatch: preserve existing import validation and show the detected target unit in the import history.

## Terminology

The final-report concepts remain intact:

- `Sparepart IN` becomes `Stok Masuk` in general operational UI, with source terminology retained where Excel parity requires it.
- `Sparepart OUT` becomes `Stok Keluar` in general operational UI.
- `Stock Saat Ini` becomes `Stok Saat Ini`.
- `Safety Stock` remains `Safety Stock` because it is a named calculation in the source report.
- `Level Inventory` remains available where it identifies the source formula, but ordinary page labels use Indonesian wording.

## Verification Strategy

### Backend tests

- Admin Pusat defaults to one active unit when no unit is supplied.
- Invalid and empty unit parameters never return aggregate data.
- Regional accounts cannot override their assigned unit.
- Master Aset rows, statistics, subsystem counts, and hierarchy contain only the effective unit.
- Risk Register lists and asset choices contain only the effective unit.
- Cross-unit Risk Register mutations are rejected.
- Inventory stock, movement, predictive, and reconciliation queries return only the effective unit.
- Global spare-part management remains restricted to Admin Pusat.
- Stock movements cannot affect another unit.

### Frontend tests

- Operational selectors contain no `Semua unit kerja` option.
- Changing unit preserves the selected tab but resets incompatible pagination and dependent filters where necessary.
- Reset preserves unit while clearing secondary filters.
- Master Aset does not render empty global category branches.
- Regional users see no unit selector or global spare-part management controls.
- User-facing inventory copy contains no standalone `saldo` terminology.

### Regression checks

- Run focused PHP suites for Master Aset, Risk Register, inventory, stock movements, reconciliation, and authorization.
- Run focused JavaScript suites for affected pages and filters.
- Run full JavaScript tests and production build.
- Verify representative Admin Pusat and regional-user flows in the local web app.

## Non-Goals

- No database duplication of global spare-part identities per unit.
- No cross-unit comparison dashboard in these operational pages.
- No schema rename solely to replace internal `balance` or opening-value terminology.
- No change to Excel formulas or imported source values beyond existing validated normalization.

