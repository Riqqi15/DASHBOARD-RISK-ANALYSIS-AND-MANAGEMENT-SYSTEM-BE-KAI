<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFailureLogRequest;
use App\Http\Requests\UpdateFailureLogRequest;
use App\Models\FailureLog;
use App\Services\FailureLogService;
use App\Services\RamsUnitContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FailureLogController extends Controller
{
    public function __construct(private readonly RamsUnitContext $unitContext) {}

    public function store(StoreFailureLogRequest $request, FailureLogService $service): RedirectResponse
    {
        $unitId = $this->unitContext->resolve($request)?->id ?? abort(404);
        $service->record($request->validated(), $request->user(), $unitId);

        return back()->with('success', 'Trouble Report berhasil disimpan.');
    }

    public function update(
        UpdateFailureLogRequest $request,
        FailureLog $log,
        FailureLogService $service,
    ): RedirectResponse {
        $unitId = $this->unitContext->resolve($request)?->id ?? abort(404);
        $service->update($log, $request->validated(), $request->user(), $unitId);

        return back()->with('success', 'Trouble Report berhasil diperbarui.');
    }

    public function destroy(Request $request, FailureLog $log, FailureLogService $service): RedirectResponse
    {
        $unitId = $this->unitContext->resolve($request)?->id ?? abort(404);
        $service->delete($log, $request->user(), $unitId);

        return back()->with('success', 'Trouble Report berhasil dihapus.');
    }
}
