<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UnitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitKerjaRequest;
use App\Http\Requests\Admin\UpdateUnitKerjaRequest;
use App\Models\UnitKerja;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UnitKerjaController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $units = UnitKerja::query()
            ->when($search, fn ($query, $value) => $query
                ->where(fn ($nested) => $nested
                    ->where('code', 'like', "%{$value}%")
                    ->orWhere('name', 'like', "%{$value}%")))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->orderBy('type')
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

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
        return Inertia::render('Admin/Units/Edit', [
            'unit' => $unit->only(['id', 'code', 'name', 'type', 'is_active']),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function update(UpdateUnitKerjaRequest $request, UnitKerja $unit): RedirectResponse
    {
        DB::transaction(function () use ($request, $unit): void {
            $before = $this->auditValues($unit);
            $unit->update($request->validated());
            $this->auditLogger->record('unit.updated', $unit, $before, $this->auditValues($unit->fresh()));
        });

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
        ];
    }
}
