<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFailureLogRequest;
use App\Services\FailureLogService;
use Illuminate\Http\RedirectResponse;

class FailureLogController extends Controller
{
    public function store(StoreFailureLogRequest $request, FailureLogService $service): RedirectResponse
    {
        $service->record($request->validated(), $request->user());

        return back()->with('success', 'Trouble Report berhasil disimpan.');
    }
}
