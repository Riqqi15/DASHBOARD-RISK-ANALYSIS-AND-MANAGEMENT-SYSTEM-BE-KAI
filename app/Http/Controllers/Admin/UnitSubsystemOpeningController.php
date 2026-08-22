<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUnitSubsystemOpeningRequest;
use App\Models\UnitSubsystemOpening;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class UnitSubsystemOpeningController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function update(UpdateUnitSubsystemOpeningRequest $request, UnitSubsystemOpening $opening): RedirectResponse
    {
        $changed = DB::transaction(function () use ($request, $opening): bool {
            $locked = UnitSubsystemOpening::query()->lockForUpdate()->findOrFail($opening->id);
            $before = $this->auditValues($locked);
            $locked->fill([
                'sparepart_in' => $request->integer('sparepart_in'),
                'sparepart_out' => $request->integer('sparepart_out'),
            ]);

            if (! $locked->isDirty()) {
                return false;
            }

            $locked->save();
            $this->auditLogger->record(
                'unit_subsystem_opening.updated',
                $locked,
                $before,
                $this->auditValues($locked->refresh()),
            );

            return true;
        });

        return redirect()
            ->back()
            ->with(
                'success',
                $changed ? 'Stok pembukaan unit berhasil diperbarui.' : 'Stok pembukaan unit tidak berubah.',
            );
    }

    /** @return array<string, int|string> */
    private function auditValues(UnitSubsystemOpening $opening): array
    {
        return $opening->only(['unit_kerja_id', 'asset_subsystem_id', 'sparepart_in', 'sparepart_out', 'source_key']);
    }
}
