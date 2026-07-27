<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetRegionalAccountPasswordRequest;
use App\Http\Requests\Admin\StoreRegionalAccountRequest;
use App\Http\Requests\Admin\UpdateRegionalAccountRequest;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegionalAccountController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $accounts = User::query()
            ->where('role', UserRole::Unit)
            ->with('unitKerja:id,code,name')
            ->when($search, fn ($query, $value) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$value}%")->orWhere('username', 'like', "%{$value}%")))
            ->when($request->filled('unit_kerja_id'), fn ($query) => $query->where('unit_kerja_id', $request->integer('unit_kerja_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Accounts/Index', [
            'accounts' => $accounts,
            'units' => UnitKerja::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'filters' => ['search' => $search, 'unit_kerja_id' => $request->string('unit_kerja_id')->toString(), 'status' => $request->filled('status') ? $request->string('status')->toString() : ''],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Accounts/Create', ['units' => $this->activeUnits()]);
    }

    public function store(StoreRegionalAccountRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $account = User::query()->create([...$request->validated(), 'role' => UserRole::Unit, 'is_active' => true]);
            $this->auditLogger->record('account.created', $account, [], $this->auditValues($account));
        });

        return redirect()->route('admin.accounts.index')->with('success', 'Akun wilayah berhasil ditambahkan.');
    }

    public function edit(User $account): Response
    {
        $this->ensureRegional($account);

        return Inertia::render('Admin/Accounts/Edit', ['account' => $this->accountPayload($account), 'units' => $this->activeUnits($account->unit_kerja_id)]);
    }

    public function update(UpdateRegionalAccountRequest $request, User $account): RedirectResponse
    {
        $this->ensureRegional($account);
        DB::transaction(function () use ($request, $account): void {
            $before = $this->auditValues($account);
            $account->update($request->validated());
            $this->auditLogger->record('account.updated', $account, $before, $this->auditValues($account->fresh()));
        });

        return redirect()->route('admin.accounts.index')->with('success', 'Akun wilayah berhasil diperbarui.');
    }

    public function status(Request $request, User $account): RedirectResponse
    {
        $this->ensureRegional($account);
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($validated, $account): void {
            $before = ['is_active' => $account->is_active];
            $account->update($validated);
            $this->auditLogger->record('account.status_changed', $account, $before, ['is_active' => $account->fresh()->is_active]);
        });

        return redirect()->route('admin.accounts.index')->with('success', 'Status akun wilayah berhasil diperbarui.');
    }

    public function editPassword(User $account): Response
    {
        $this->ensureRegional($account);

        return Inertia::render('Admin/Accounts/ResetPassword', ['account' => $this->accountPayload($account)]);
    }

    public function updatePassword(ResetRegionalAccountPasswordRequest $request, User $account): RedirectResponse
    {
        $this->ensureRegional($account);
        DB::transaction(function () use ($request, $account): void {
            $account->update(['password' => $request->validated('password')]);
            $this->auditLogger->record('account.password_reset', $account, [], ['password_reset' => true]);
        });

        return redirect()->route('admin.accounts.index')->with('success', 'Kata sandi akun berhasil diatur ulang.');
    }

    private function ensureRegional(User $account): void
    {
        abort_unless($account->isUnit(), 404);
    }

    private function activeUnits(?int $includeId = null)
    {
        return UnitKerja::query()->where(fn ($query) => $query->where('is_active', true)->when($includeId, fn ($nested) => $nested->orWhereKey($includeId)))->orderBy('code')->get(['id', 'code', 'name']);
    }

    private function accountPayload(User $account): array
    {
        return $account->only(['id', 'name', 'username', 'email', 'unit_kerja_id', 'is_active']);
    }

    private function auditValues(User $account): array
    {
        return $account->only(['name', 'username', 'email', 'unit_kerja_id', 'is_active']);
    }
}
