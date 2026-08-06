<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFailureLogRequest;
use App\Http\Requests\UpdateFailureLogRequest;
use App\Services\FailureLogService;
use Illuminate\Http\RedirectResponse;

class FailureLogController extends Controller
{
    public function store(StoreFailureLogRequest $request, FailureLogService $service): RedirectResponse
    {
        $service->record($request->validated(), $request->user());

        return back()->with('success', 'Trouble Report berhasil disimpan.');
    }

    public function update(UpdateFailureLogRequest $request, \App\Models\FailureLog $log, FailureLogService $service): RedirectResponse
    {
        $service->update($log, $request->validated(), $request->user());

        return back()->with('success', 'Trouble Report berhasil diperbarui.');
    }

    public function destroy(\App\Models\FailureLog $log, FailureLogService $service): RedirectResponse
    {
        $service->delete($log, auth()->user());

        return back()->with('success', 'Trouble Report berhasil dihapus.');
    }
}
