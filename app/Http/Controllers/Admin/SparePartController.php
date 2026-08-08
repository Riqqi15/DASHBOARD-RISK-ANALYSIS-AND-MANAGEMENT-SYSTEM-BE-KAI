<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSparePartRequest;
use App\Http\Requests\Admin\UpdateSparePartRequest;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\SparePart;
use App\Services\AuditLogger;
use App\Services\ReorderStockCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SparePartController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ReorderStockCalculator $reorderStockCalculator,
    ) {}

    public function store(StoreSparePartRequest $request): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request): void {
                $this->lockActiveCategoryPath($request->integer('asset_subsystem_id'));
                $values = $this->withCalculatedReorderValues($request->validated());
                $part = SparePart::query()->create([
                    ...$values,
                    'source_key' => hash('sha256', 'manual|'.$values['code']),
                    'is_active' => true,
                    'reorder_calculated_at' => now(),
                ]);
                $this->auditLogger->record(
                    'spare_part.created',
                    $part,
                    [],
                    $this->auditValues($part),
                    $request->user(),
                );
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()->route('inventory', ['tab' => 'master'])
            ->with('success', 'Suku cadang berhasil ditambahkan.');
    }

    public function update(UpdateSparePartRequest $request, SparePart $sparePart): RedirectResponse
    {
        try {
            $changed = DB::transaction(function () use ($request, $sparePart): bool {
                $part = SparePart::query()->lockForUpdate()->findOrFail($sparePart->id);
                $this->lockActiveCategoryPath($request->integer('asset_subsystem_id'));
                $before = $this->auditValues($part);
                $part->fill($this->withCalculatedReorderValues($request->validated()));

                if (! $part->isDirty()) {
                    return false;
                }

                $part->reorder_calculated_at = now();
                $part->save();
                $this->auditLogger->record(
                    'spare_part.updated',
                    $part,
                    $before,
                    $this->auditValues($part->fresh()),
                    $request->user(),
                );

                return true;
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()->route('inventory', ['tab' => 'master'])
            ->with('success', $changed ? 'Suku cadang berhasil diperbarui.' : 'Data suku cadang tidak berubah.');
    }

    public function destroy(Request $request, SparePart $sparePart): RedirectResponse
    {
        Gate::authorize('delete', $sparePart);

        $changed = DB::transaction(function () use ($request, $sparePart): bool {
            $part = SparePart::query()->lockForUpdate()->findOrFail($sparePart->id);
            if (! $part->is_active) {
                return false;
            }

            $before = $this->auditValues($part);
            $part->update(['is_active' => false]);
            $this->auditLogger->record(
                'spare_part.deactivated',
                $part,
                $before,
                $this->auditValues($part->fresh()),
                $request->user(),
            );

            return true;
        });

        return redirect()->route('inventory', ['tab' => 'master'])
            ->with('success', $changed ? 'Suku cadang berhasil dinonaktifkan.' : 'Suku cadang sudah nonaktif.');
    }

    private function lockActiveCategoryPath(int $subsystemId): AssetSubsystem
    {
        $subsystem = AssetSubsystem::query()->lockForUpdate()->find($subsystemId);
        $system = $subsystem ? AssetSystem::query()->lockForUpdate()->find($subsystem->asset_system_id) : null;
        $group = $system ? AssetGroup::query()->lockForUpdate()->find($system->asset_group_id) : null;

        if (! $subsystem?->is_active || ! $system?->is_active || ! $group?->is_active) {
            throw ValidationException::withMessages([
                'asset_subsystem_id' => 'Subsistem aset atau kategori induknya tidak aktif atau tidak ditemukan.',
            ]);
        }

        return $subsystem;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withCalculatedReorderValues(array $values): array
    {
        $calculation = $this->reorderStockCalculator->calculate(
            (float) $values['max_yearly_failure'],
            (float) $values['average_yearly_failure'],
            (float) $values['max_lead_time_months'],
            (float) $values['average_lead_time_months'],
        );

        return [
            ...$values,
            'safety_stock' => $calculation['safety_stock'],
            'lead_time_demand' => $calculation['lead_time_demand'],
            'reorder_point' => $calculation['reorder_point'],
            'reorder_calculation_status' => $calculation['calculation_status'],
            'reorder_formula_version' => $calculation['formula_version'],
        ];
    }

    /** @return array<string, mixed> */
    private function auditValues(SparePart $part): array
    {
        return [
            'id' => $part->id,
            'asset_subsystem_id' => $part->asset_subsystem_id,
            'code' => $part->code,
            'source_key' => $part->source_key,
            'equipment' => $part->equipment,
            'detail_equipment' => $part->detail_equipment,
            'max_yearly_failure' => $part->max_yearly_failure,
            'average_yearly_failure' => $part->average_yearly_failure,
            'max_lead_time_months' => $part->max_lead_time_months,
            'average_lead_time_months' => $part->average_lead_time_months,
            'safety_stock' => $part->safety_stock,
            'lead_time_demand' => $part->lead_time_demand,
            'reorder_point' => $part->reorder_point,
            'function_criterion' => $part->function_criterion,
            'production_impact' => $part->production_impact,
            'severity' => $part->severity,
            'unit_of_measure' => $part->unit_of_measure,
            'is_active' => $part->is_active,
        ];
    }

    private function throwIfDuplicate(QueryException $exception): void
    {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            throw ValidationException::withMessages(['code' => 'Kode suku cadang sudah digunakan.']);
        }
    }
}
