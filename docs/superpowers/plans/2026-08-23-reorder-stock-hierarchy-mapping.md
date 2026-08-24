# Reorder Stock Hierarchy Mapping Implementation Plan

> **For agentic workers:** Execute this plan task-by-task. Use TDD: prove each failure, apply one minimal fix, then run focused and regression tests. Do not discard unrelated working-tree changes.

**Goal:** Import every valid `Reorder Stock` row from the DAOP workbook by mapping its deeper hierarchy to the existing Predictive Data Asset category, creating new spare-part records when the source key is new, updating source-managed fields when values change, and leaving unchanged records untouched.

**Architecture:** Keep Predictive Data Asset as taxonomy authority. Add a hierarchy-aware resolver inside `SparePartWorkbookImporter`: exact alias/path first, then match the workbook `Sub-System` to a unique master subsystem inside the selected group, then reuse a previously proven subsystem anchor while the workbook remains inside the same forward-filled group. Persist successful fallback paths as unit-scoped aliases. Do not create `asset_groups`, `asset_systems`, or `asset_subsystems` during normal web import.

**Tech Stack:** Laravel 13, PHP 8.4, Eloquent/MySQL, PhpSpreadsheet, PHPUnit.

---

## OpenCode prompt

Implement this plan in `rams_be`. Preserve all unrelated local edits. Start with focused tests. Do not modify the workbook. Do not add a migration or dependency. Final proof must include a DAOP-1 workbook dry diagnostic showing `spare_parts_skipped = 0` and no `Reorder Stock` hierarchy warnings.

## Current evidence

- Source workbook: `C:\Users\riyadh\Downloads\Risk Analysis And Management System RAMS Daop 1.xlsm`.
- Sheet `Reorder Stock` is hidden but readable and contains header row plus data through row 110.
- Rows with actual `Detail Equipment` run from row 2 through row 74.
- Latest import batch `id=24` finished with `status=succeeded`; this is not a failed workbook upload.
- Reorder result: `spare_parts_unchanged = 3`, `spare_parts_skipped = 70`, `spare_parts_created = 0`, and 70 hierarchy warnings.
- Rows 2-4 resolve because workbook path is effectively:

```text
Peralatan Dalam Sinyal Elektrik
  > Interlocking Electric
    > Interlocking Electric
```

- Rows 5-17 fail because workbook contains deeper descendants such as:

```text
Peralatan Dalam Sinyal Elektrik
  > Panel Pelayanan
    > Panel Pelayanan LCP / Panel Pelayanan VDU
```

  while DAOP-1 master has one applicable legacy subsystem:

```text
1. PERALATAN DALAM SINYAL ELEKTRIK
  > INTERLOCKING ELEKTRIK
    > INTERLOCKING ELEKTRIK
```

- Rows 18-74 fail because workbook contains:

```text
Peralatan Luar Sinyal Elektrik
  > Peraga Sinyal Elektrik Utama
    > Sinyal Masuk / Sinyal Langsir / Sinyal Darurat / ...
```

  while DAOP-1 master contains:

```text
2. PERALATAN LUAR SINYAL ELEKTRIK
  > PERAGA SINYAL ELEKTRIK
    > PERAGA SINYAL ELEKTRIK UTAMA
```

- Root cause is in `app/Services/SparePartWorkbookImporter.php::resolveSubsystem()`: it accepts only full alias or exact `group > system > subsystem` name path, then returns `null` because web upload passes `skipUnmatchedCategories: true`.
- Existing web behavior intentionally avoids creating taxonomy from a reorder-only path. Preserve that safety property.
- Existing upsert query has a second bug for the new mapping: fallback match uses only `asset_subsystem_id + detail_equipment`. Many workbook equipment entries repeat detail names such as `Tiang Sinyal`, `Signal Head`, and `Pondasi`; after mapping them to one master subsystem, this fallback could update a prior row instead of creating a distinct spare part.

## Required behavior

1. Resolve hierarchy in this order:

   1. Existing unit-scoped full source alias.
   2. Existing exact active name path.
   3. Within the uniquely resolved group, match workbook column `Sub-System` against an active master `AssetSubsystem` name. Require exactly one candidate.
   4. If the current Excel `System` cell is blank and the same forward-filled group already resolved an earlier detail row, reuse that proven subsystem as group anchor.
   5. If still ambiguous or absent, retain current strict/skip behavior. Never guess between multiple candidates.

2. Reset the anchor whenever Excel column `System` contains a new non-empty group value. This prevents an anchor leaking from `Peralatan Dalam Sinyal Elektrik` into `Peralatan Luar Sinyal Elektrik`.
3. Persist a successful fallback with existing `persistCategoryAliases()` so the next import resolves directly and remains stable across name formatting/case differences.
4. Preserve workbook values:

   - `Equipment` stays workbook column C, e.g. `Sinyal Langsir`.
   - `detail_equipment` stays workbook column D, e.g. `Pondasi`.
   - Missing calculation inputs remain `null` and yield `reorder_calculation_status = insufficient_data`.

5. Upsert identity:

   - First match exact `source_key`.
   - Adopt a legacy record only when its `source_key` is null/empty and all of `asset_subsystem_id`, normalized `equipment`, and normalized `detail_equipment` match.
   - Never match an already source-keyed record solely by `asset_subsystem_id + detail_equipment`.
   - A new source key creates one `spare_parts` row and one unit policy.
   - A known source key with changed source fields updates that row and its unit policy.
   - An identical re-import increments unchanged/duplicate counters without writing another row.
   - Preserve admin-managed `code`, `unit_of_measure`, and `is_active`.

## Out of scope

- No UI changes.
- No schema migration.
- No automatic creation of category taxonomy during normal web upload.
- No edits to the Excel workbook.
- No change to reliability, risk register, dashboard, or inventory transaction logic.

## Working-tree warning

The repository already contains unrelated user edits, including `.gitignore`, asset-category controllers/UI, dashboard code/tests, `RiskRegisterWorkbookImporter.php`, package files, Playwright config, and E2E tests. Do not reset, checkout, reformat, stage, or commit those files. Expected target files for this task are only:

- `app/Services/SparePartWorkbookImporter.php`
- `tests/Feature/SparePartImportTest.php`
- `tests/Feature/FailureLogImportUploadTest.php`

---

### Task 1: Add failing resolver tests

**Files:**

- Modify: `tests/Feature/SparePartImportTest.php`

- [ ] Add a test proving workbook `Sub-System` can map to a unique master subsystem within the same group while workbook `Equipment` remains spare-part equipment.

Use this fixture shape:

```php
$group = AssetGroup::factory()->create(['name' => '2. PERALATAN LUAR SINYAL ELEKTRIK']);
$system = AssetSystem::factory()->for($group)->create(['name' => 'PERAGA SINYAL ELEKTRIK']);
$subsystem = AssetSubsystem::factory()->for($system)->create(['name' => 'PERAGA SINYAL ELEKTRIK UTAMA']);
$path = $this->workbook([
    [
        'Peralatan Luar Sinyal Elektrik',
        'Peraga Sinyal Elektrik Utama',
        'Sinyal Langsir',
        'Pondasi',
        null, null, null, null, null, null, null, null,
    ],
]);

$result = app(SparePartWorkbookImporter::class)->import(
    $path,
    bootstrapCategories: false,
    unit: null,
    skipUnmatchedCategories: true,
);

$this->assertSame(1, $result['created']);
$this->assertSame(0, $result['skipped']);
$this->assertSame([], $result['issues']);
$this->assertDatabaseHas('spare_parts', [
    'asset_subsystem_id' => $subsystem->id,
    'equipment' => 'Sinyal Langsir',
    'detail_equipment' => 'Pondasi',
    'reorder_calculation_status' => 'insufficient_data',
]);
```

- [ ] Add a test proving same-group forward-fill reuses only a previously proven anchor. First row must resolve exactly; later rows leave column A blank and use deeper B/C values.

```php
$group = AssetGroup::factory()->create(['name' => '1. PERALATAN DALAM SINYAL ELEKTRIK']);
$system = AssetSystem::factory()->for($group)->create(['name' => 'INTERLOCKING ELEKTRIK']);
$subsystem = AssetSubsystem::factory()->for($system)->create(['name' => 'INTERLOCKING ELEKTRIK']);
$path = $this->workbook([
    ['Peralatan Dalam Sinyal Elektrik', 'Interlocking Electric', 'Interlocking Electric', 'Modul Interlocking', 1, 1, 1, 1, 1, 1, 2, 'High'],
    ['', 'Panel Pelayanan', 'Panel Pelayanan LCP', 'Meja Pelayanan LCP', null, null, null, null, null, null, null, null],
    ['', 'Terminal Peralatan', 'Data Logger', 'PC Based', null, null, null, null, null, null, null, null],
]);

$result = app(SparePartWorkbookImporter::class)->import(
    $path,
    bootstrapCategories: false,
    unit: null,
    skipUnmatchedCategories: true,
);

$this->assertSame(3, $result['created']);
$this->assertSame(0, $result['skipped']);
$this->assertSame([$subsystem->id], SparePart::query()->distinct()->pluck('asset_subsystem_id')->all());
```

- [ ] Keep `test_typo_under_a_system_with_one_child_is_not_silently_remapped` green. A lone typo without a prior proven anchor and without a unique B-to-subsystem match must still fail/skip according to caller mode.
- [ ] Run:

```powershell
rtk php artisan test tests/Feature/SparePartImportTest.php --filter="maps_reorder|reuses_proven|typo_under"
```

Expected: two new tests fail before implementation; typo safety test passes.

### Task 2: Implement hierarchy-aware fallback

**Files:**

- Modify: `app/Services/SparePartWorkbookImporter.php`

- [ ] In `importRows()`, track a nullable `AssetSubsystem $resolvedGroupAnchor`.
- [ ] Before updating forward-filled values, detect whether raw column A is non-empty. Reset the anchor whenever it is non-empty.
- [ ] Extend `resolveSubsystem()` with the current anchor and a flag that allows anchor reuse only when raw column A is blank.
- [ ] Preserve current alias and exact-path branches before fallback logic.
- [ ] Add one private helper that returns a unique active subsystem whose parent group matches column A and whose own name matches column B. Return `null` for zero or multiple candidates.

Target control flow:

```php
$groupWasExplicit = $group !== '';
if ($groupWasExplicit) {
    $resolvedGroupAnchor = null;
}

$subsystem = $this->resolveSubsystem(
    $currentGroup,
    $currentSystem,
    $currentEquipment,
    $workbookName,
    $row,
    $bootstrapCategories,
    $unit?->id,
    $skipUnmatchedCategories,
    $groupWasExplicit ? null : $resolvedGroupAnchor,
);

if ($subsystem) {
    $resolvedGroupAnchor = $subsystem;
}
```

Inside `resolveSubsystem()`, insert fallback before `skipUnmatchedCategories`:

```php
$subsystem = $this->resolveSubsystemByWorkbookSystem(
    $groupName,
    $systemName,
    $unitKerjaId,
);

if (! $subsystem && $fallbackSubsystem) {
    $sameGroup = $this->categoryNameMatches(
        $fallbackSubsystem->assetSystem->assetGroup->name,
        $groupName,
    );
    $subsystem = $sameGroup ? $fallbackSubsystem : null;
}

if ($subsystem) {
    $this->persistCategoryAliases($subsystem, $paths, $workbookName, $row, $unitKerjaId);

    return $subsystem;
}
```

`resolveSubsystemByWorkbookSystem()` must load active groups/systems/subsystems under the requested `unit_kerja_id`, normalize numeric prefixes/case through existing `categoryNameMatches()`, flatten active subsystems inside the one matched group, and require exactly one subsystem matching `$systemName`.

- [ ] Re-run focused Task 1 tests. Expected: all green.

### Task 3: Prevent repeated detail names from collapsing records

**Files:**

- Modify: `tests/Feature/SparePartImportTest.php`
- Modify: `app/Services/SparePartWorkbookImporter.php`

- [ ] Add a failing test with two rows mapped to the same master subsystem and identical `Detail Equipment`, but different `Equipment` values.

```php
$path = $this->workbook([
    ['Peralatan Luar Sinyal Elektrik', 'Peraga Sinyal Elektrik Utama', 'Sinyal Masuk', 'Tiang Sinyal', null, null, null, null, null, null, null, null],
    ['', '', 'Sinyal Langsir', 'Tiang Sinyal', null, null, null, null, null, null, null, null],
]);

$result = app(SparePartWorkbookImporter::class)->import(
    $path,
    bootstrapCategories: false,
    unit: null,
    skipUnmatchedCategories: true,
);

$this->assertSame(2, $result['created']);
$this->assertDatabaseCount('spare_parts', 2);
$this->assertEqualsCanonicalizing(
    ['Sinyal Masuk', 'Sinyal Langsir'],
    SparePart::query()->pluck('equipment')->all(),
);
```

- [ ] Replace the current combined `source_key OR (asset_subsystem_id + detail_equipment)` query with two-stage lookup:

```php
$part = SparePart::withTrashed()
    ->where('source_key', $sourceKey)
    ->lockForUpdate()
    ->first();

if (! $part) {
    $legacyMatches = SparePart::withTrashed()
        ->where(function ($query): void {
            $query->whereNull('source_key')->orWhere('source_key', '');
        })
        ->where('asset_subsystem_id', $subsystem->id)
        ->lockForUpdate()
        ->get()
        ->filter(
            fn (SparePart $candidate): bool =>
                $this->normalize((string) $candidate->equipment) === $this->normalize($currentEquipment) &&
                $this->normalize((string) $candidate->detail_equipment) === $this->normalize($detailEquipment),
        )
        ->values();

    if ($legacyMatches->count() > 1) {
        throw $this->rowError(
            $workbookName,
            $row,
            'Detail Equipment',
            'lebih dari satu master sparepart legacy cocok; rekonsiliasi manual diperlukan.',
        );
    }

    $part = $legacyMatches->first();
}
```

Reuse the importer's existing `normalize()` helper. Do not add normalized database columns or a migration for this legacy-adoption path.

- [ ] Run complete spare-part importer tests:

```powershell
rtk php artisan test tests/Feature/SparePartImportTest.php
```

Expected: all tests pass; repeated details remain distinct by equipment/source key.

### Task 4: Update web-import contract test

**Files:**

- Modify: `tests/Feature/FailureLogImportUploadTest.php`

- [ ] Replace `test_web_import_does_not_create_taxonomy_from_reorder_only_paths` expectations. The reorder-only path should now import through a proven anchor, while taxonomy counts stay unchanged.
- [ ] Assert:

```php
$this->assertSame('succeeded', $result['status']);
$this->assertSame(2, $result['spare_parts_created']);
$this->assertSame(0, $result['spare_parts_skipped']);
$this->assertDatabaseCount('spare_parts', 2);
$this->assertFalse(AssetSystem::query()->where('name', 'Panel Pelayanan')->exists());
$this->assertFalse(
    collect($result['issues'])->contains(
        fn (array $issue): bool => ($issue['sheet_name'] ?? null) === 'Reorder Stock' &&
            str_contains((string) ($issue['message'] ?? ''), 'tidak ditemukan pada master Predictive Data Asset'),
    ),
);
```

The fixture must contain an exact first Reorder row that establishes the anchor, followed by the unmatched `Panel Pelayanan` row with blank column A, matching the real workbook ordering.

- [ ] Add an ambiguous-case test: when column B matches no master subsystem, no prior anchor exists, and the group has multiple candidates, web import must skip with one warning instead of attaching data incorrectly.
- [ ] Run:

```powershell
rtk php artisan test tests/Feature/FailureLogImportUploadTest.php --filter="reorder|idempotent"
```

Expected: web import creates/updates valid reorder data, does not create taxonomy, and still protects ambiguous input.

### Task 5: Verify create, update, and idempotency

**Files:**

- Modify tests only if an uncovered regression requires it.

- [ ] Run focused backend tests:

```powershell
rtk php artisan test tests/Feature/SparePartImportTest.php tests/Feature/FailureLogImportUploadTest.php tests/Feature/RamsWorkbookImportCoordinatorTest.php
```

- [ ] Run formatting check only on changed PHP files:

```powershell
rtk vendor/bin/pint --test app/Services/SparePartWorkbookImporter.php tests/Feature/SparePartImportTest.php tests/Feature/FailureLogImportUploadTest.php
```

- [ ] Run full backend regression suite:

```powershell
rtk php artisan test
```

- [ ] Verify current DAOP-1 workbook against a disposable/test database first. Expected first run from an empty spare-part state:

```text
created = 73
updated = 0
unchanged = 0
skipped = 0
Reorder Stock hierarchy warnings = 0
```

- [ ] Verify immediate second import:

```text
created = 0
updated = 0
unchanged = 73
skipped = 0
```

- [ ] Change one source-managed numeric value in a temporary copy of the workbook, import again, and assert exactly one spare part/unit policy is updated with no duplicate row.
- [ ] On the current local database, baseline already contains three source-keyed records. Expected first import after patch is approximately:

```text
created = 70
updated = 0
unchanged = 3
skipped = 0
```

Exact update count may differ if those three existing records changed after batch 24; the invariant is `created + updated + unchanged = 73`, `skipped = 0`, and zero hierarchy warnings.

### Task 6: Final safety review

**Files:**

- Verify only.

- [ ] Inspect `git diff -- app/Services/SparePartWorkbookImporter.php tests/Feature/SparePartImportTest.php tests/Feature/FailureLogImportUploadTest.php`.
- [ ] Confirm normal web import still uses `bootstrapCategories: false` and never creates taxonomy from `Reorder Stock`.
- [ ] Confirm alias records are unit-scoped and point to active categories.
- [ ] Confirm group anchor resets on every explicit column-A group cell.
- [ ] Confirm duplicate detail names under different equipment create separate spare parts.
- [ ] Confirm soft-deleted spare parts remain skipped and are not restored.
- [ ] Confirm admin-managed fields remain unchanged during source updates.
- [ ] Restart the queue worker/application process after tests so runtime loads patched importer code.

## Acceptance criteria

- DAOP-1 workbook imports all 73 populated `Reorder Stock` detail rows.
- No valid current row reports `Hierarchy ... tidak ditemukan`.
- First import creates missing source keys; changed source values update; identical re-import is unchanged/idempotent.
- Repeated detail names under different equipment remain separate records.
- Normal web import creates no asset taxonomy from reorder-only hierarchy.
- Ambiguous/unprovable hierarchy is never silently attached to an arbitrary category.
- Existing focused and full test suites pass.
