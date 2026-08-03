<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportFailureLogsRequest;
use App\Models\UnitKerja;
use App\Services\FailureLogImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FailureLogImportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('input-data/TroubleReportImport', [
            'can_choose_unit' => $user->isPusat(),
            'selected_unit_id' => $user->isUnit() ? $user->unit_kerja_id : null,
            'units' => $user->isPusat()
                ? UnitKerja::query()
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->get(['id', 'code', 'name'])
                    ->values()
                : [],
            'result' => $request->session()->get('import_result'),
        ]);
    }

    public function store(ImportFailureLogsRequest $request, FailureLogImportService $service): RedirectResponse
    {
        $result = $service->import($request->file('workbook'), $request->selectedUnit());
        $redirect = to_route('failure-logs.import.index')->with('import_result', $result);

        if ($result['status'] === 'failed') {
            return $redirect->with('error', 'Import Trouble Report gagal. Periksa daftar masalah.');
        }

        $issueCount = count($result['issues']);
        $message = $issueCount === 0
            ? 'Import Trouble Report selesai tanpa masalah.'
            : "Import Trouble Report selesai dengan {$issueCount} masalah yang dilewati.";

        return $redirect->with('success', $message);
    }
}
