# Username-Only Authentication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace email login with normalized, unique usernames and make `admin.pusat / admin1234` the working local Pusat credential.

**Architecture:** Add a backward-compatible `users.username` migration, keep email nullable as contact-only data, and route every authentication decision through username. Update account administration, Inertia shared data, Vue forms, seed configuration, and automated tests so no UI or backend path treats email as a credential.

**Tech Stack:** Laravel 13, PHP 8.4, MySQL 8.4, Inertia.js 3, Vue 3, Vitest, PHPUnit, Tailwind CSS.

---

### Task 1: Persist normalized usernames safely

**Files:**
- Create: `database/migrations/2026_07_27_000003_add_username_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Modify: `tests/Feature/OrganizationSchemaTest.php`

- [ ] **Step 1: Write failing schema and normalization tests**

Add assertions that every factory user has a lowercase username and that case-insensitive duplicates are rejected by MySQL:

```php
public function test_users_have_a_unique_normalized_username(): void
{
    $user = User::factory()->create(['username' => 'Operator.Daop1']);

    $this->assertSame('operator.daop1', $user->fresh()->username);

    $this->expectException(QueryException::class);
    User::factory()->create(['username' => 'OPERATOR.DAOP1']);
}
```

Update the direct insert fixture to include:

```php
'username' => 'akun.tanpa.unit',
```

- [ ] **Step 2: Run the focused test and verify the red state**

Run:

```powershell
php artisan test tests/Feature/OrganizationSchemaTest.php
```

Expected: FAIL because `users.username` and the model normalization do not exist.

- [ ] **Step 3: Add the migration**

Implement a migration that:

```php
Schema::table('users', function (Blueprint $table): void {
    $table->string('username', 50)->nullable()->after('name');
});
```

For existing users, derive the base username from `admin.pusat` for the first Pusat account or from the email local part for other accounts. Normalize with:

```php
$base = Str::of($base)
    ->lower()
    ->replaceMatches('/[^a-z0-9._-]+/', '.')
    ->trim('.-_')
    ->limit(50, '');
```

Use `user.<id>` when the result is shorter than three characters, append `.<id>` on collisions, then enforce:

```php
Schema::table('users', function (Blueprint $table): void {
    $table->unique('username');
    $table->string('username', 50)->nullable(false)->change();
    $table->string('email')->nullable()->change();
});
```

The `down()` method must fill null email values with `<username>@example.invalid`, restore email as required, drop the unique username index, and drop the username column.

- [ ] **Step 4: Normalize username in the model and factory**

Add `username` to `Fillable` and use an Eloquent mutator:

```php
protected function username(): Attribute
{
    return Attribute::make(
        set: fn (string $value): string => Str::lower(trim($value)),
    );
}
```

Add a factory username:

```php
'username' => fake()->unique()->userName(),
```

- [ ] **Step 5: Run the focused test and migration test**

Run:

```powershell
php artisan test tests/Feature/OrganizationSchemaTest.php
```

Expected: all tests PASS.

- [ ] **Step 6: Commit the persistence layer**

```powershell
git add database/migrations/2026_07_27_000003_add_username_to_users_table.php app/Models/User.php database/factories/UserFactory.php tests/Feature/OrganizationSchemaTest.php
git commit -m "feat: add normalized user identities"
```

### Task 2: Authenticate and seed by username

**Files:**
- Modify: `app/Http/Requests/Auth/LoginRequest.php`
- Modify: `app/Http/Middleware/EnsureUserIsActive.php`
- Modify: `database/seeders/AdminUserSeeder.php`
- Modify: `config/rams.php`
- Modify: `.env.example`
- Modify: `README.md`
- Modify: `tests/Feature/Auth/AuthenticationTest.php`
- Create: `tests/Feature/AdminUserSeederTest.php`

- [ ] **Step 1: Replace authentication tests with username behavior**

The success request must post:

```php
[
    'username' => $user->username,
    'password' => 'secret-password',
]
```

Add explicit rejection of the contact email:

```php
public function test_email_cannot_be_used_as_a_login_identifier(): void
{
    $user = User::factory()->pusat()->create([
        'email' => 'admin.pusat@example.test',
        'password' => 'admin1234',
    ]);

    $this->post('/login', [
        'username' => $user->email,
        'password' => 'admin1234',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
}
```

Create a seeder test that sets:

```php
config()->set('rams.admin', [
    'name' => 'Admin Pusat',
    'username' => 'admin.pusat',
    'email' => null,
    'password' => 'admin1234',
]);
```

Run the seeder twice and assert one active Pusat user exists and `Hash::check('admin1234', $user->password)` is true.

- [ ] **Step 2: Run authentication and seeder tests in the red state**

```powershell
php artisan test tests/Feature/Auth/AuthenticationTest.php tests/Feature/AdminUserSeederTest.php
```

Expected: FAIL because login and seeding still use email.

- [ ] **Step 3: Change `LoginRequest` to username only**

Use these rules:

```php
'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/\A[a-z0-9._-]+\z/'],
'password' => ['required', 'string'],
'remember' => ['sometimes', 'boolean'],
```

Normalize in `prepareForValidation()`:

```php
$this->merge([
    'username' => Str::lower(trim($this->string('username')->toString())),
]);
```

Authenticate with `username`, `password`, and `is_active`; attach generic failures and lockout messages to `username`; build the throttle key from username and IP.

- [ ] **Step 4: Update active-account errors and admin seeding**

Change inactive-session errors from `email` to `username`. Replace `RAMS_ADMIN_EMAIL` as the required seed identity with `RAMS_ADMIN_USERNAME`, while leaving an optional `RAMS_ADMIN_EMAIL` configuration entry for contact data.

Seed with:

```php
User::query()->updateOrCreate(
    ['username' => $admin['username']],
    [
        'name' => $admin['name'],
        'email' => $admin['email'],
        'password' => $admin['password'],
        'role' => UserRole::Pusat,
        'unit_kerja_id' => null,
        'is_active' => true,
    ],
);
```

Document the local credential as `admin.pusat / admin1234` in `.env.example` and `README.md`.

- [ ] **Step 5: Run focused tests**

```powershell
php artisan test tests/Feature/Auth/AuthenticationTest.php tests/Feature/AdminUserSeederTest.php
```

Expected: all tests PASS.

- [ ] **Step 6: Commit authentication and seed behavior**

```powershell
git add app/Http/Requests/Auth/LoginRequest.php app/Http/Middleware/EnsureUserIsActive.php database/seeders/AdminUserSeeder.php config/rams.php .env.example README.md tests/Feature/Auth/AuthenticationTest.php tests/Feature/AdminUserSeederTest.php
git commit -m "feat: authenticate users by username"
```

### Task 3: Share and display username in authenticated UI

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `app/Http/Controllers/Admin/AuditLogController.php`
- Modify: `resources/js/application/composables/useAuth.js`
- Modify: `resources/js/layouts/MainLayout.vue`
- Modify: `tests/Feature/SharedInertiaDataTest.php`
- Modify: `tests/js/useAuth.test.js`

- [ ] **Step 1: Write failing shared-data and composable tests**

Assert:

```php
->where('auth.user.username', $user->username)
```

Update the composable fixture to provide `username: 'admin.pusat'`, then assert:

```js
expect(currentUser.value.username).toBe('admin.pusat')
```

- [ ] **Step 2: Run the focused tests in the red state**

```powershell
php artisan test tests/Feature/SharedInertiaDataTest.php
npm run test:js -- tests/js/useAuth.test.js
```

Expected: FAIL because the shared payload and composable still depend on email.

- [ ] **Step 3: Implement shared username data**

Share `username` from the authenticated user. Keep nullable `email` as contact data, but map the domain user from:

```js
username: user.username,
```

Display `user.username` in the profile menu. Update audit-log actor selection to include `username` and retain email only when needed as contact metadata.

- [ ] **Step 4: Run focused tests**

```powershell
php artisan test tests/Feature/SharedInertiaDataTest.php
npm run test:js -- tests/js/useAuth.test.js
```

Expected: all tests PASS.

- [ ] **Step 5: Commit shared username data**

```powershell
git add app/Http/Middleware/HandleInertiaRequests.php app/Http/Controllers/Admin/AuditLogController.php resources/js/application/composables/useAuth.js resources/js/layouts/MainLayout.vue tests/Feature/SharedInertiaDataTest.php tests/js/useAuth.test.js
git commit -m "feat: expose username in the application shell"
```

### Task 4: Manage regional accounts by username

**Files:**
- Modify: `app/Http/Requests/Admin/StoreRegionalAccountRequest.php`
- Modify: `app/Http/Requests/Admin/UpdateRegionalAccountRequest.php`
- Modify: `app/Http/Controllers/Admin/RegionalAccountController.php`
- Modify: `resources/js/pages/Admin/Accounts/Partials/AccountForm.vue`
- Modify: `resources/js/pages/Admin/Accounts/Index.vue`
- Modify: `resources/js/pages/Admin/Accounts/ResetPassword.vue`
- Modify: `tests/Feature/Admin/RegionalAccountManagementTest.php`

- [ ] **Step 1: Rewrite account tests around username**

Creation payload:

```php
[
    'name' => 'Operator Daop 1',
    'username' => 'daop1.operator',
    'email' => null,
    'unit_kerja_id' => $unit->id,
    'password' => 'long-secret-password',
    'password_confirmation' => 'long-secret-password',
]
```

Assert normalized storage, duplicate username validation, optional email acceptance, and existing inactive-unit update behavior.

- [ ] **Step 2: Run the regional account test in the red state**

```powershell
php artisan test tests/Feature/Admin/RegionalAccountManagementTest.php
```

Expected: FAIL because account validation and payloads still use required email identity.

- [ ] **Step 3: Implement username validation**

Both requests normalize username before validation. Store rules include:

```php
'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/\A[a-z0-9._-]+\z/', 'unique:users,username'],
'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
```

Update rules use `Rule::unique(...)->ignore($account)` for both username and optional email.

- [ ] **Step 4: Update controller and Vue account screens**

Search by name or username, include username in payload and audit values, and display `@username` as the primary account identifier. The account form requires username, keeps email optional with `autocomplete="email"`, and the reset-password page identifies the account by username.

- [ ] **Step 5: Run the focused test**

```powershell
php artisan test tests/Feature/Admin/RegionalAccountManagementTest.php
```

Expected: all tests PASS.

- [ ] **Step 6: Commit account management changes**

```powershell
git add app/Http/Requests/Admin/StoreRegionalAccountRequest.php app/Http/Requests/Admin/UpdateRegionalAccountRequest.php app/Http/Controllers/Admin/RegionalAccountController.php resources/js/pages/Admin/Accounts/Partials/AccountForm.vue resources/js/pages/Admin/Accounts/Index.vue resources/js/pages/Admin/Accounts/ResetPassword.vue tests/Feature/Admin/RegionalAccountManagementTest.php
git commit -m "feat: manage regional usernames"
```

### Task 5: Replace the login field in Vue

**Files:**
- Modify: `resources/js/pages/auth/Login.vue`
- Modify: `tests/js/Login.test.js`

- [ ] **Step 1: Write the failing Vue interaction test**

Use `#username`, submit `admin.pusat`, and assert:

```js
expect(wrapper.get('label[for="username"]').text()).toBe('Username')
expect(wrapper.get('#username').attributes('type')).toBe('text')
expect(wrapper.find('#email').exists()).toBe(false)
```

Set `state.errors = { username: 'Username tidak valid.' }` and verify the accessible alert.

- [ ] **Step 2: Run the Vue test in the red state**

```powershell
npm run test:js -- tests/js/Login.test.js
```

Expected: FAIL because the component still renders and posts email.

- [ ] **Step 3: Implement the username field**

Initialize the form with `username`, render `id="username"`, `type="text"`, `autocomplete="username"`, label `Username`, placeholder `Masukkan username`, and connect `form.errors.username` to `username-error`.

- [ ] **Step 4: Run the Vue test**

```powershell
npm run test:js -- tests/js/Login.test.js
```

Expected: all tests PASS.

- [ ] **Step 5: Commit the login UI**

```powershell
git add resources/js/pages/auth/Login.vue tests/js/Login.test.js
git commit -m "feat: replace email login with username"
```

### Task 6: Migrate MySQL, verify the full application, and test the browser

**Files:**
- Modify locally only: `.env`

- [ ] **Step 1: Set local admin configuration**

Ensure `.env` contains:

```text
RAMS_ADMIN_NAME="Admin Pusat"
RAMS_ADMIN_USERNAME=admin.pusat
RAMS_ADMIN_EMAIL=
RAMS_ADMIN_PASSWORD=admin1234
```

- [ ] **Step 2: Run migrations and idempotent seeding on MySQL 8.4**

```powershell
docker compose --profile test up -d --wait
php artisan migrate
php artisan db:seed --class=AdminUserSeeder
php artisan migrate:fresh --env=testing
```

Expected: migrations complete successfully; the local database retains one active Pusat user named `admin.pusat`; the test database is rebuilt only on port 3307.

- [ ] **Step 3: Run all backend and frontend verification**

```powershell
php artisan test
npm run test:js
npm run build
php vendor/bin/pint --test
```

Expected: zero PHPUnit failures, zero Vitest failures, successful Vite build, and no Pint violations.

- [ ] **Step 4: Verify runtime behavior in the browser**

Open `http://127.0.0.1:8000/login`, confirm the first field is `Username`, and log in with:

```text
admin.pusat
admin1234
```

Expected: redirect to `/dashboard`. Log out and try `admin.pusat@example.test`; expected: remain on `/login` with the generic username error.

- [ ] **Step 5: Review final repository state**

```powershell
git status --short --branch
git log --oneline -8
```

Expected: implementation commits are local, no uncommitted source changes remain, and nothing is pushed.
