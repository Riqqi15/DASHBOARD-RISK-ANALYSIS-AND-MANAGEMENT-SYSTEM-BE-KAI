# Unit List Pagination Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tampilkan maksimal 20 unit pada satu halaman dan munculkan halaman kedua mulai data ke-21.

**Architecture:** Pertahankan paginator Laravel yang sudah ada. Ubah ukuran halaman pada query Unit Kerja dan kunci perilakunya dengan feature test.

**Tech Stack:** Laravel, Eloquent Pagination, PHPUnit.

---

### Task 1: Ubah batas pagination Unit & Akun

**Files:**
- Modify: `app/Http/Controllers/Admin/UnitKerjaController.php:50`
- Test: `tests/Feature/Admin/UnitKerjaManagementTest.php`

- [ ] **Step 1: Write the failing test**

Tambahkan 21 unit pada test pagination, lalu pastikan halaman pertama berisi 20 unit dan halaman kedua berisi 1 unit.

```php
$this->actingAs($pusat)->get('/admin/units')
    ->assertInertia(fn (Assert $page) => $page
        ->has('units.data', 20)
        ->where('units.last_page', 2));

$this->actingAs($pusat)->get('/admin/units?page=2')
    ->assertInertia(fn (Assert $page) => $page->has('units.data', 1));
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/UnitKerjaManagementTest.php --filter=test_index_paginates_after_twenty_units`

Expected: FAIL karena paginator saat ini membatasi 15 unit.

- [ ] **Step 3: Write minimal implementation**

```php
->paginate(20)
```

- [ ] **Step 4: Run verification**

Run: `php artisan test tests/Feature/Admin/UnitKerjaManagementTest.php`

Expected: seluruh test lolos.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/UnitKerjaController.php tests/Feature/Admin/UnitKerjaManagementTest.php
git commit -m "fix: paginate units after twenty records"
```
