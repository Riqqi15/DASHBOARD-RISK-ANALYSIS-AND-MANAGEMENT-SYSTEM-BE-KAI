# Reliability Formula Workbook Export Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghasilkan export Excel Reliability & Availability multi-sheet untuk satu DAOP/DIVRE aktif, dengan layout mendekati workbook KAI dan formula Excel yang dapat dihitung ulang.

**Architecture:** `RamsReportController` tetap menjadi endpoint download, tetapi report `reliability` didelegasikan ke builder khusus. Builder mengambil asset, snapshot/profile reliability, dan failure log yang sudah dibatasi unit; resolver terpisah menangani nama sheet serta variasi formula, lalu PhpSpreadsheet menyusun sheet ringkasan dan sheet subsystem. Report XLSX lain dan semua PDF tetap memakai `RamsReportExportService` yang ada.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, PhpSpreadsheet 5.9, Inertia, Vue 3, Vitest, PHPUnit.

---

## File Map

- Create `app/Services/ReliabilitySheetNameResolver.php`: menghasilkan nama worksheet Excel yang valid, maksimal 31 karakter, dan unik.
- Create `app/Services/ReliabilityFormulaProfileResolver.php`: menormalisasi variasi baseline, downtime, failure count, sparepart, dan vandalisme dari snapshot/summary.
- Create `app/Services/ReliabilityWorkbookExportService.php`: query data ter-scope, menyusun workbook, menulis formula, dan menerapkan format KAI.
- Modify `app/Http/Controllers/RamsReportController.php`: mendelegasikan XLSX reliability ke builder khusus dan menolak export Pusat tanpa area eksplisit.
- Modify `resources/js/pages/reports/Index.vue`: memperjelas bahwa export Reliability adalah workbook multi-sheet berformula.
- Create `tests/Unit/ReliabilitySheetNameResolverTest.php`: validasi nama sheet.
- Create `tests/Unit/ReliabilityFormulaProfileResolverTest.php`: validasi normalisasi profile.
- Create `tests/Feature/ReliabilityWorkbookExportTest.php`: validasi struktur, formula, scope unit, dan ketiadaan data user.
- Modify `tests/Feature/RamsReportExportTest.php`: menjaga export lama dan memastikan routing khusus reliability.
- Modify `tests/js/Reports.test.js`: menjaga query area dan copy export Reliability.

### Task 1: Resolve Safe and Unique Worksheet Names

**Files:**
- Create: `app/Services/ReliabilitySheetNameResolver.php`
- Create: `tests/Unit/ReliabilitySheetNameResolverTest.php`

- [ ] **Step 1: Write the failing unit tests**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ReliabilitySheetNameResolver;
use PHPUnit\Framework\TestCase;

final class ReliabilitySheetNameResolverTest extends TestCase
{
    public function test_it_removes_invalid_characters_and_limits_names_to_31_characters(): void
    {
        $resolver = new ReliabilitySheetNameResolver;

        $name = $resolver->resolve('Pengontrol / Petunjuk [Wesel]: Mekanik?');

        self::assertLessThanOrEqual(31, mb_strlen($name));
        self::assertDoesNotMatchRegularExpression('/[\\\\\/\?\*\[\]:]/u', $name);
    }

    public function test_it_adds_a_deterministic_suffix_for_duplicate_names(): void
    {
        $resolver = new ReliabilitySheetNameResolver;

        self::assertSame('Interlocking Elektrik', $resolver->resolve('Interlocking Elektrik'));
        self::assertSame('Interlocking Elektrik (2)', $resolver->resolve('Interlocking Elektrik'));
    }
}
```

- [ ] **Step 2: Run the tests and verify the class is missing**

Run: `rtk php artisan test tests/Unit/ReliabilitySheetNameResolverTest.php`

Expected: FAIL because `ReliabilitySheetNameResolver` does not exist.

- [ ] **Step 3: Implement the resolver**

```php
<?php

declare(strict_types=1);

namespace App\Services;

final class ReliabilitySheetNameResolver
{
    /** @var array<string, int> */
    private array $used = [];

    public function resolve(string $name): string
    {
        $base = trim((string) preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $name));
        $base = preg_replace('/\s+/u', ' ', $base) ?: 'Subsystem';
        $base = mb_substr($base, 0, 31);
        $key = mb_strtolower($base);
        $sequence = ($this->used[$key] ?? 0) + 1;
        $this->used[$key] = $sequence;

        if ($sequence === 1) {
            return $base;
        }

        $suffix = " ({$sequence})";

        return mb_substr($base, 0, 31 - mb_strlen($suffix)).$suffix;
    }
}
```

- [ ] **Step 4: Run the unit tests**

Run: `rtk php artisan test tests/Unit/ReliabilitySheetNameResolverTest.php`

Expected: 2 tests PASS.

- [ ] **Step 5: Stage only the resolver files and commit**

Run:

```text
rtk git add app/Services/ReliabilitySheetNameResolver.php tests/Unit/ReliabilitySheetNameResolverTest.php
rtk git diff --cached --check
rtk git commit -m "feat(reports): resolve reliability worksheet names"
```

### Task 2: Normalize Excel Formula Profiles

**Files:**
- Create: `app/Services/ReliabilityFormulaProfileResolver.php`
- Create: `tests/Unit/ReliabilityFormulaProfileResolverTest.php`

- [ ] **Step 1: Write failing tests for imported and fallback profiles**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ReliabilityFormulaProfileResolver;
use PHPUnit\Framework\TestCase;

final class ReliabilityFormulaProfileResolverTest extends TestCase
{
    public function test_it_preserves_interlocking_electric_profile_variations(): void
    {
        $profile = (new ReliabilityFormulaProfileResolver)->resolve([
            'downtime_mode' => 'minutes',
            'interval_baseline_date' => '2020-01-01',
            'failure_count_mode' => 'counta_all_minus_1',
            'spare_part_count_mode' => 'counta',
            'vandalism_count_mode' => 'counta',
        ], '2017-01-01');

        self::assertSame('minutes', $profile['downtime_mode']);
        self::assertSame('2020-01-01', $profile['interval_baseline_date']);
        self::assertSame('counta_all_minus_1', $profile['failure_count_mode']);
        self::assertSame('counta', $profile['spare_part_count_mode']);
    }

    public function test_it_uses_auditable_defaults_for_missing_snapshot_profiles(): void
    {
        $profile = (new ReliabilityFormulaProfileResolver)->resolve([], '2017-01-01');

        self::assertSame('minutes', $profile['downtime_mode']);
        self::assertSame('counta', $profile['failure_count_mode']);
        self::assertSame('countif_ya', $profile['spare_part_count_mode']);
        self::assertTrue($profile['is_fallback']);
    }
}
```

- [ ] **Step 2: Run the tests and verify failure**

Run: `rtk php artisan test tests/Unit/ReliabilityFormulaProfileResolverTest.php`

Expected: FAIL because the resolver does not exist.

- [ ] **Step 3: Implement strict profile normalization**

```php
<?php

declare(strict_types=1);

namespace App\Services;

final class ReliabilityFormulaProfileResolver
{
    /** @return array<string, string|bool> */
    public function resolve(array $profile, string $fallbackBaseline): array
    {
        return [
            'downtime_mode' => $this->allowed($profile['downtime_mode'] ?? null, ['minutes', 'hours', 'excel_day_fraction'], 'minutes'),
            'interval_baseline_date' => (string) ($profile['interval_baseline_date'] ?? $fallbackBaseline),
            'failure_count_mode' => $this->allowed($profile['failure_count_mode'] ?? null, ['counta', 'counta_all_minus_1'], 'counta'),
            'spare_part_count_mode' => $this->allowed($profile['spare_part_count_mode'] ?? null, ['counta', 'countif_ya'], 'countif_ya'),
            'vandalism_count_mode' => $this->allowed($profile['vandalism_count_mode'] ?? null, ['counta', 'countif_ya'], 'countif_ya'),
            'empty_mttf_mode' => $this->allowed($profile['empty_mttf_mode'] ?? null, ['zero', 'null'], 'null'),
            'is_fallback' => $profile === [],
        ];
    }

    private function allowed(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
    }
}
```

- [ ] **Step 4: Run the unit tests**

Run: `rtk php artisan test tests/Unit/ReliabilityFormulaProfileResolverTest.php`

Expected: 2 tests PASS.

- [ ] **Step 5: Commit only the profile resolver files**

Run:

```text
rtk git add app/Services/ReliabilityFormulaProfileResolver.php tests/Unit/ReliabilityFormulaProfileResolverTest.php
rtk git diff --cached --check
rtk git commit -m "feat(reports): normalize reliability formula profiles"
```

### Task 3: Build a Formula-Driven Multi-Sheet Workbook

**Files:**
- Create: `app/Services/ReliabilityWorkbookExportService.php`
- Create: `tests/Feature/ReliabilityWorkbookExportTest.php`

- [ ] **Step 1: Write a failing feature test for workbook structure and formulas**

Create a DAOP-1 unit with two subsystem assets, summaries, snapshots, and ordered failure logs. The essential assertions are:

```php
$workbook = app(ReliabilityWorkbookExportService::class)->workbook($user, $unit);
$names = array_map(fn ($sheet): string => $sheet->getTitle(), $workbook->getAllSheets());

$this->assertSame('Ringkasan Reliability', $names[0]);
$this->assertContains('Interlocking Elektrik', $names);
$this->assertContains('Catu Daya Sintel', $names);

$sheet = $workbook->getSheetByName('Interlocking Elektrik');
$this->assertSame('=($Q$8-$P$8)*24*C4', $sheet->getCell('D4')->getValue());
$this->assertSame('=D4-F4', $sheet->getCell('E4')->getValue());
$this->assertSame('=IFERROR(E4/G4,0)', $sheet->getCell('I4')->getValue());
$this->assertSame('=IFERROR(1/I4,0)', $sheet->getCell('J4')->getValue());
$this->assertSame('=EXP(-J4)', $sheet->getCell('K4')->getValue());
$this->assertSame('=IFERROR(E4/D4,"Data belum cukup")', $sheet->getCell('L4')->getValue());
$this->assertSame('=IFERROR(IF(O10="",0,(O10-$P$8)*24),"")', $sheet->getCell('S10')->getValue());
$this->assertSame('=IFERROR(IF(O11="",0,(O11-O10)*24),"")', $sheet->getCell('S11')->getValue());
```

Also assert that the summary sheet points to each subsystem sheet using quoted references:

```php
$this->assertSame("='Interlocking Elektrik'!B4", $summary->getCell('A5')->getValue());
$this->assertSame("='Interlocking Elektrik'!K4", $summary->getCell('J5')->getValue());
```

- [ ] **Step 2: Add failing assertions for formula-profile variations**

For a `minutes` profile assert `F4` sums the converted-minute column. For `hours`, assert it converts the duration cell to hours. For marker modes assert `COUNTIF(...,"Ya")` versus `COUNTA(...)`.

```php
$this->assertSame('=SUM(R10:R11)', $electric->getCell('F4')->getValue());
$this->assertSame('=COUNTA(H10:H11)', $electric->getCell('M4')->getValue());
$this->assertSame('=COUNTIF(H10:H10,"Ya")', $catuDaya->getCell('M4')->getValue());
```

- [ ] **Step 3: Run the feature test and verify the service is missing**

Run: `rtk php artisan test tests/Feature/ReliabilityWorkbookExportTest.php`

Expected: FAIL because `ReliabilityWorkbookExportService` does not exist.

- [ ] **Step 4: Implement scoped data loading**

Create `ReliabilityWorkbookExportService` with this public boundary:

```php
public function __construct(
    private readonly ReliabilitySheetNameResolver $sheetNames,
    private readonly ReliabilityFormulaProfileResolver $profiles,
) {}

public function workbook(User $user, UnitKerja $unit): Spreadsheet
```

Load only assets visible to the user and belonging to `$unit`, including subsystem, ordered failure logs, and the latest reliability summary plus Excel snapshot. Group by subsystem name. Do not eager-load `creator`, `users`, or authentication relations.

Use this query shape so authorization and ordering are explicit:

```php
$assets = Asset::query()
    ->visibleTo($user)
    ->where('unit_kerja_id', $unit->id)
    ->with([
        'assetSubsystem:id,name',
        'failureLogs' => fn (HasMany $logs): HasMany => $logs
            ->orderByRaw('CASE WHEN source_row IS NULL THEN 1 ELSE 0 END')
            ->orderBy('source_row')
            ->orderBy('started_at')
            ->orderBy('id'),
        'reliabilitySummaries' => fn (HasMany $summaries): HasMany => $summaries
            ->with('excelSnapshot')
            ->latest('period')
            ->latest('id'),
    ])
    ->get()
    ->groupBy(fn (Asset $asset): string => $asset->assetSubsystem?->name ?? $asset->subsystem ?? $asset->nama_aset);
```

Within each group, use the first summary after the explicit ordering as the formula-profile source, sum `jumlah_unit`, and merge all failure logs in source-row/time order.

- [ ] **Step 5: Implement the summary sheet**

Create `Ringkasan Reliability`, write unit/generated metadata, blue headers, and one formula row per subsystem. Every formula must quote the target sheet name:

```php
$escapedTitle = str_replace("'", "''", $subsystemSheetTitle);
$sheet->setCellValue("A{$row}", "='{$escapedTitle}'!B4");
$sheet->setCellValue("B{$row}", "='{$escapedTitle}'!C4");
// Continue through M, mapping to D4:N4.
```

Freeze the header, enable filters, format reliability/availability as `0.0000%`, and use explicit widths rather than unbounded autosize.

- [ ] **Step 6: Implement subsystem headers and detail rows**

Use the KAI color roles:

- blue `4F81BD` for summary headers;
- light blue `DCE6F1` for summary values;
- purple `8064A2` for failure headers;
- green `9BBB59` for baseline metadata.

Write raw database values as typed dates, times, booleans represented by `Ya`/`Tidak`, and text. Start failure rows at row 10. When there are no failures, reserve one formula row but leave raw input cells blank.

- [ ] **Step 7: Implement detail and summary formulas**

For row `$row`, use:

```php
$sheet->setCellValue("J{$row}", "=IF(K{$row}=\"\",\"\",YEAR(K{$row}))");
$sheet->setCellValue("O{$row}", "=IF(OR(K{$row}=\"\",M{$row}=\"\"),\"\",K{$row}+M{$row})");
$sheet->setCellValue("P{$row}", "=IF(OR(L{$row}=\"\",N{$row}=\"\"),\"\",L{$row}+N{$row})");
$sheet->setCellValue("Q{$row}", "=IF(OR(M{$row}=\"\",N{$row}=\"\"),\"\",N{$row}-M{$row}+IF(N{$row}<M{$row},1,0))");
$sheet->setCellValue("R{$row}", "=IF(Q{$row}=\"\",\"\",Q{$row}*1440)");
```

Use `$P$8` as the typed baseline date. The first interval references `$P$8`; later rows reference the previous `O` cell. Build formulas `F4`, `G4`, `M4`, and `N4` from the normalized profile. Keep `D4`, `E4`, `H4:L4` formula-driven.

Use explicit profile-to-formula mappings:

```php
$downtimeFormula = match ($profile['downtime_mode']) {
    'hours' => "=SUM(Q{$firstRow}:Q{$lastRow})*24",
    'excel_day_fraction' => "=SUM(Q{$firstRow}:Q{$lastRow})",
    default => "=SUM(R{$firstRow}:R{$lastRow})",
};
$failureFormula = match ($profile['failure_count_mode']) {
    'counta_all_minus_1' => "=COUNTA(E{$firstRow}:E{$lastRow})",
    default => "=COUNTA(E{$firstRow}:E{$lastRow})",
};
$sparePartFormula = $profile['spare_part_count_mode'] === 'counta'
    ? "=COUNTA(H{$firstRow}:H{$lastRow})"
    : "=COUNTIF(H{$firstRow}:H{$lastRow},\"Ya\")";
$vandalismFormula = $profile['vandalism_count_mode'] === 'counta'
    ? "=COUNTA(I{$firstRow}:I{$lastRow})"
    : "=COUNTIF(I{$firstRow}:I{$lastRow},\"Ya\")";
```

Write `P8` from `interval_baseline_date` and `Q8` from the latest calculation date (or export date when no summary exists). Apply Excel date format `dd/mm/yyyy` to both cells.

- [ ] **Step 8: Format, calculate, save, and reload in the test**

Round-trip the workbook through `Xlsx` and `IOFactory::load()`. Assert formulas remain formula strings, all sheet names remain valid, and no `#REF!` occurs in any formula:

```php
foreach ($reloaded->getWorksheetIterator() as $worksheet) {
    foreach ($worksheet->getCellCollection()->getCoordinates() as $coordinate) {
        $value = $worksheet->getCell($coordinate)->getValue();
        if (is_string($value) && str_starts_with($value, '=')) {
            $this->assertStringNotContainsString('#REF!', $value);
        }
    }
}
```

- [ ] **Step 9: Run the feature test**

Run: `rtk php artisan test tests/Feature/ReliabilityWorkbookExportTest.php`

Expected: all tests PASS.

- [ ] **Step 10: Commit builder and feature tests**

Run:

```text
rtk git add app/Services/ReliabilityWorkbookExportService.php tests/Feature/ReliabilityWorkbookExportTest.php
rtk git diff --cached --check
rtk git commit -m "feat(reports): export formula-driven reliability workbook"
```

### Task 4: Route Reliability XLSX Through the Dedicated Builder

**Files:**
- Modify: `app/Http/Controllers/RamsReportController.php`
- Modify: `tests/Feature/RamsReportExportTest.php`

- [ ] **Step 1: Write failing endpoint tests**

Add tests that:

- request `/reports/reliability/xlsx?area=DAOP-1` as Pusat and receive the multi-sheet workbook;
- request `/reports/reliability/xlsx` as Pusat and receive HTTP 422 with `Pilih DAOP/DIVRE sebelum export Reliability`;
- request the same endpoint as a unit user and receive only its own unit;
- keep inventory, trouble-report, and risk-register XLSX behavior unchanged.

- [ ] **Step 2: Run the endpoint tests**

Run: `rtk php artisan test tests/Feature/RamsReportExportTest.php`

Expected: FAIL because reliability still uses the flat exporter and missing area still falls back to the first unit.

- [ ] **Step 3: Inject and delegate to the dedicated exporter**

Update `download()` to accept `ReliabilityWorkbookExportService $reliabilityExporter`. Before calling `selectedUnit()`, enforce the explicit Pusat area only for reliability:

```php
if ($report === 'reliability' && $request->user()->isPusat() && ! $request->filled('area')) {
    abort(422, 'Pilih DAOP/DIVRE sebelum export Reliability');
}

$unit = $request->selectedUnit();
$workbook = $report === 'reliability'
    ? $reliabilityExporter->workbook($request->user(), $unit)
    : $exporter->workbook($report, $request->user(), $unit);
```

Use `abort_unless($unit instanceof UnitKerja, 422, ...)` before the dedicated builder so its public method remains non-nullable.

- [ ] **Step 4: Run endpoint and report regression tests**

Run: `rtk php artisan test tests/Feature/RamsReportExportTest.php tests/Feature/ReliabilityWorkbookExportTest.php`

Expected: all tests PASS.

- [ ] **Step 5: Commit controller integration**

Run:

```text
rtk git add app/Http/Controllers/RamsReportController.php tests/Feature/RamsReportExportTest.php
rtk git diff --cached --check
rtk git commit -m "feat(reports): serve scoped reliability workbooks"
```

### Task 5: Clarify Formula Workbook Behavior in the Reports UI

**Files:**
- Modify: `resources/js/pages/reports/Index.vue`
- Modify: `tests/js/Reports.test.js`

- [ ] **Step 1: Write a failing UI test**

Add assertions:

```js
expect(wrapper.get('a[href="/reports/reliability/xlsx?area=DAOP-1"]').text()).toContain('Excel Berformula')
expect(wrapper.text()).toContain('satu sheet untuk setiap subsystem')
expect(wrapper.text()).toContain('mengikuti area yang dipilih')
```

- [ ] **Step 2: Run the targeted Vitest file**

Run: `rtk npx vitest run tests/js/Reports.test.js`

Expected: FAIL because the current UI still says only `Unduh Excel`.

- [ ] **Step 3: Update only Reliability copy and label**

Add `formulaWorkbook: true` to the reliability report descriptor. Render `Unduh Excel Berformula` for that card and keep `Unduh Excel` for the other three cards. Update the description to state that the workbook contains a summary plus one formula-driven sheet per subsystem and follows the selected area.

- [ ] **Step 4: Run the UI test**

Run: `rtk npx vitest run tests/js/Reports.test.js`

Expected: PASS.

- [ ] **Step 5: Commit the UI copy change**

Run:

```text
rtk git add resources/js/pages/reports/Index.vue tests/js/Reports.test.js
rtk git diff --cached --check
rtk git commit -m "feat(reports): label reliability formula export"
```

### Task 6: Verify Formula Parity, Security Scope, and Visual Output

**Files:**
- Modify if verification exposes a defect: only files already listed in Tasks 1–5.

- [x] **Step 1: Run targeted backend tests**

Run:

```text
rtk php artisan test tests/Unit/ReliabilitySheetNameResolverTest.php tests/Unit/ReliabilityFormulaProfileResolverTest.php tests/Feature/ReliabilityWorkbookExportTest.php tests/Feature/RamsReportExportTest.php
```

Expected: all targeted tests PASS with no skipped tests.

- [ ] **Step 2: Run the full backend suite**

Run: `rtk php artisan test`

Expected: exit code 0 and no failures.

- [x] **Step 3: Run frontend tests and production build**

Run:

```text
rtk npx vitest run
rtk npm run build
```

Expected: all Vitest files PASS and Vite build exits 0.

- [x] **Step 4: Run formatting and diff checks**

Run:

```text
rtk php vendor/bin/pint --test app/Services/ReliabilitySheetNameResolver.php app/Services/ReliabilityFormulaProfileResolver.php app/Services/ReliabilityWorkbookExportService.php app/Http/Controllers/RamsReportController.php tests/Unit/ReliabilitySheetNameResolverTest.php tests/Unit/ReliabilityFormulaProfileResolverTest.php tests/Feature/ReliabilityWorkbookExportTest.php tests/Feature/RamsReportExportTest.php
rtk git diff --check
```

Expected: Pint passes and `git diff --check` has no output.

- [x] **Step 5: Generate and inspect a DAOP-1 workbook**

Use an authenticated DAOP-1 request to download `/reports/reliability/xlsx?area=DAOP-1`. Open the result with PhpSpreadsheet and verify:

- sheet pertama `Ringkasan Reliability`;
- all expected DAOP-1 subsystem sheets exist;
- `Interlocking Elektrik` has the 2020 baseline/profile when present;
- formulas in D4:N4 and O:S remain formulas;
- no text from another unit or any user email/name appears;
- workbook opens without repair warning.

- [x] **Step 6: Perform visual QA on representative sheets**

Render or open `Ringkasan Reliability`, `Interlocking Elektrik`, `Catu Daya Sintel`, and one empty subsystem. Confirm headers are readable, formulas/results are not clipped, dates and percentages have correct formats, and the visual hierarchy remains close to the KAI blue/purple/green layout.

- [ ] **Step 7: Final implementation commit if verification required fixes**

Stage only files changed to fix verified defects, inspect `rtk git diff --cached --name-only`, then commit with:

```text
rtk git commit -m "fix(reports): finalize reliability workbook parity"
```
