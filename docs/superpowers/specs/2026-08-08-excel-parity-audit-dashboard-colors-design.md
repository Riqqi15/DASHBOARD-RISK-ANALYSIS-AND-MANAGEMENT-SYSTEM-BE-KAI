# Excel Parity Audit and Dashboard Asset Colors Design

## Goal

Align the existing RAMS web application with the five KAI Excel workbooks and the signalling spare-parts report without replacing the familiar Excel workflow or dashboard structure. The web application remains the operational system, while Excel values remain auditable source evidence.

## Source Priority

1. Explicit current user requirements.
2. The final signalling spare-parts PDF for methodology and intent.
3. Actual values, formulas, sheets, and formatting in the five Daop/Divre `.xlsm` workbooks.
4. Existing backend behavior and database as the implementation target.
5. Word and TXT documents only as supplemental terminology and UX context.

## Import Boundary

Workbook import may create or update operational RAMS data: asset categories, assets, predictive snapshots, risk matrices, risk registers, spare parts, inventory-related analytical data, failure logs, and reliability snapshots.

Workbook import must never create, update, deactivate, or delete application users, roles, passwords, or user-to-unit assignments. This is enforced by regression tests that snapshot all user rows before import and compare them afterward.

Failure-log-only workbooks remain importable when `Predictive Data Asset` is absent. Master-asset synchronization is optional in that path; existing asset mappings are used and a non-fatal warning is returned.

## Dual-Value Audit Model

For calculated workbook data, retain both:

- the original Excel values and formulas;
- backend-calculated values and the backend formula version.

The web dashboard and operational recommendations use backend-calculated values. The UI exposes parity states (`matched`, `corrected`, `excel_data_missing`, `not_compared`) and the differing fields so users can audit why a value changed.

Negative predictive stock is valid evidence of shortage and must not be clamped to zero. It is displayed as `Defisit stok`, and proposal quantity is calculated against the signed value.

## Risk Matrix Separation

The `Risk Matrix` sheet is imported directly and is not derived from the risk columns in `Predictive Data Asset`. Both sources are retained independently because the five workbooks contain real differences between them. The direct Risk Matrix import resolves the same asset hierarchy and records source workbook, sheet, row, original likelihood/consequence, backend rating/level, and parity metadata.

## Reliability and Availability

Reliability and availability validity are evaluated independently. A missing availability value must not remove a valid reliability value from dashboard aggregation, and vice versa. Existing reliability Excel snapshots and parity badges remain the audit mechanism.

## Predictive Inventory UI

Add a `Predictive Data Asset` tab to the existing Inventory page. It shows signed stock, required stock, proposal quantity, inventory policy, safety stock, age/lifetime status, and parity. This analytical view remains separate from transactional `InventoryStock` so imported predictive calculations do not fabricate stock movements.

## Excel Dashboard Colors

The canonical colors come from the filled hierarchy cells in the `Risk Matrix` sheet. All five workbooks use the same mapping:

- Peralatan Dalam Sinyal Elektrik / Interlocking Elektrik: `#FFFF00`.
- Peralatan Luar Sinyal Elektrik and descendants: `#FFC000`.
- Peralatan Dalam Sinyal Mekanik / Interlocking Mekanik: `#92D050`.
- Peralatan Luar Sinyal Mekanik and descendants: Excel theme blue with tint 0.4, resolved to `#9DC3E6`.
- Catu Daya Sintel / Catu Daya Sinyal: `#FF0000`.

Store `dashboard_color` and `dashboard_color_source` on asset groups, systems, and subsystems. Source is either `excel` or `manual`.

During import:

- fill colors are normalized to six-digit uppercase hex;
- an unset color or an `excel` color may be populated/refreshed from Excel;
- a `manual` color is never overwritten by import;
- missing or unsupported color data is a warning, not an import failure;
- hierarchy rows with blank repeated cells inherit the last nonblank group/system name but use the current subsystem fill.

Akun Pusat can edit/reset colors in the existing Asset Category management dialog. Setting a color marks it `manual`; resetting it clears the manual override so the next workbook import can restore the Excel color. Unit accounts can only view the effective colors.

Dashboard asset sections consume the effective color from the backend. Text color is selected by contrast so yellow and light-blue cards remain readable. If no imported/manual color exists, the current web fallback palette remains in use.

## Compatibility and UX

The current dashboard hierarchy, filters, terminology, and card layout remain. Changes are additive: source-consistent colors, parity badges/details, deficit labels, and a predictive inventory tab. Existing dirty dashboard hierarchy work is preserved and extended rather than replaced.

## Error Handling

- A missing optional sheet yields a warning and imports the sheets that can be processed.
- A missing required hierarchy mapping yields a row-level issue with sheet and row.
- Unsupported Excel colors fall back to the existing palette and produce a warning.
- Invalid manual colors are rejected with `#RRGGBB` validation.
- Imports remain transactional per workbook and idempotent by source key/fingerprint.

## Verification

Use focused unit/feature tests first, then the complete PHP and JavaScript suites, formatter, production build, and `git diff --check`. Include real-workbook read-only parity checks for all five workbooks and explicit tests that user records are unchanged after import.
