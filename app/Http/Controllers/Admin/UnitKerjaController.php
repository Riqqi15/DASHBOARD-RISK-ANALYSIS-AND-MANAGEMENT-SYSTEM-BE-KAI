<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UnitType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitKerjaRequest;
use App\Http\Requests\Admin\UpdateUnitKerjaRequest;
use App\Models\ReliabilityExcelSnapshot;
use App\Models\UnitKerja;
use App\Services\AuditLogger;
use App\Services\ReliabilityParityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UnitKerjaController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ReliabilityParityService $reliabilityParity,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $units = UnitKerja::query()
            ->with(['users' => fn ($query) => $query
                ->where('role', UserRole::Unit)
                ->orderBy('name')
                ->select(['id', 'unit_kerja_id', 'name', 'username', 'email', 'is_active'])])
            ->when($search, fn ($query, $value) => $query
                ->where(fn ($nested) => $nested
                    ->where('code', 'like', "%{$value}%")
                    ->orWhere('name', 'like', "%{$value}%")
                    ->orWhereHas('users', fn ($accounts) => $accounts
                        ->where('role', UserRole::Unit)
                        ->where(fn ($account) => $account
                            ->where('name', 'like', "%{$value}%")
                            ->orWhere('username', 'like', "%{$value}%")))))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->orderBy('type')
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (UnitKerja $unit): array => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'type' => $unit->type->value,
                'is_active' => $unit->is_active,
                'accounts' => $unit->users->map(fn ($account): array => $account->only([
                    'id',
                    'name',
                    'username',
                    'email',
                    'is_active',
                ]))->all(),
            ]);

        return Inertia::render('Admin/Units/Index', [
            'units' => $units,
            'filters' => [
                'search' => $search,
                'type' => $request->string('type')->toString(),
                'status' => $request->filled('status') ? $request->string('status')->toString() : '',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Units/Create', [
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function store(StoreUnitKerjaRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $unit = UnitKerja::query()->create($request->validated());
            $this->auditLogger->record('unit.created', $unit, [], $this->auditValues($unit));
        });

        return redirect()->route('admin.units.index')->with('success', 'Unit kerja berhasil ditambahkan.');
    }

    public function edit(UnitKerja $unit): Response
    {
        $latestSnapshot = ReliabilityExcelSnapshot::query()
            ->whereHas('asset', fn ($assets) => $assets->where('unit_kerja_id', $unit->id))
            ->whereNotNull('baseline_date')
            ->latest('imported_at')
            ->latest('id')
            ->first(['baseline_date']);
        $importedBaselineDate = $latestSnapshot?->baseline_date?->toDateString();

        return Inertia::render('Admin/Units/Edit', [
            'unit' => $unit->only(['id', 'code', 'name', 'type', 'is_active', 'operating_start_date']),
            'typeOptions' => $this->typeOptions(),
            'importedBaselineDate' => $importedBaselineDate,
        ]);
    }

    public function update(UpdateUnitKerjaRequest $request, UnitKerja $unit): RedirectResponse
    {
        $baselineChanged = $request->baselineIsChanging();
        $previousBaseline = $unit->operating_start_date?->toDateString();

        DB::transaction(function () use ($request, $unit, $baselineChanged, $previousBaseline): void {
            $before = $this->auditValues($unit);
            $unit->update($request->unitData());
            $this->auditLogger->record('unit.updated', $unit, $before, $this->auditValues($unit->fresh()));

            if ($baselineChanged) {
                $this->auditLogger->record(
                    'unit.baseline_updated',
                    $unit,
                    ['operating_start_date' => $previousBaseline],
                    [
                        'operating_start_date' => $unit->fresh()->operating_start_date?->toDateString(),
                        'reason' => $request->validated('baseline_change_reason'),
                    ],
                );
            }
        });

        if ($baselineChanged) {
            try {
                DB::transaction(fn (): array => $this->reliabilityParity->recalculateUnit($unit->fresh()));
            } catch (Throwable $exception) {
                report($exception);

                return redirect()->route('admin.units.index')->with(
                    'error',
                    'Baseline tersimpan, tetapi hitung ulang belum selesai. Ringkasan sebelumnya tetap dipertahankan.',
                );
            }
        }

        return redirect()->route('admin.units.index')->with('success', 'Unit kerja berhasil diperbarui.');
    }

    /** @return array<int, array{value: string, label: string}> */
    private function typeOptions(): array
    {
        return [
            ['value' => UnitType::Daop->value, 'label' => 'Daerah Operasi (Daop)'],
            ['value' => UnitType::Divre->value, 'label' => 'Divisi Regional (Divre)'],
        ];
    }

    /** @return array<string, mixed> */
    private function auditValues(UnitKerja $unit): array
    {
        return [
            'code' => $unit->code,
            'name' => $unit->name,
            'type' => $unit->type->value,
            'is_active' => $unit->is_active,
            'operating_start_date' => $unit->operating_start_date?->toDateString(),
        ];
    }
}
