<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportFailureLogsRequest;
use App\Models\RamsImportBatch;
use App\Models\UnitKerja;
use App\Services\FailureLogImportService;
use App\Services\RamsImportBatchPresenter;
use App\Services\RamsImportSubmissionService;
use App\Services\RamsUnitDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FailureLogImportController extends Controller
{
    public function index(Request $request, RamsImportBatchPresenter $presenter): Response
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
            'history' => RamsImportBatch::query()
                ->when($user->isUnit(), fn ($query) => $query->where('unit_kerja_id', $user->unit_kerja_id))
                ->with(['unitKerja:id,code,name', 'uploadedBy:id,name,username'])
                ->withCount('issues')
                ->latest('started_at')
                ->limit(25)
                ->get()
                ->map(fn (RamsImportBatch $batch): array => $presenter->payload($batch, false, $user))
                ->values(),
        ]);
    }

    public function show(Request $request, RamsImportBatch $batch, RamsImportBatchPresenter $presenter): JsonResponse
    {
        if ($request->user()->isUnit() && $batch->unit_kerja_id !== $request->user()->unit_kerja_id) {
            abort(404);
        }

        return response()->json(['data' => $presenter->payload($batch, true, $request->user())]);
    }

    public function store(
        ImportFailureLogsRequest $request,
        RamsImportSubmissionService $submission,
        RamsUnitDetector $detector,
        FailureLogImportService $importer,
    ): RedirectResponse {
        $submitted = $submission->submit(
            $request->file('workbook'),
            $request->selectedUnit($detector),
            (bool) $request->validated('dry_run', false),
            $request->user(),
        );
        $batch = $submitted['batch']->refresh();
        $redirect = to_route('failure-logs.import.index');

        if ($submitted['duplicate']) {
            return $redirect->with('info', 'Workbook yang sama sudah pernah dikirim untuk unit ini.');
        }
        if ($batch->status === 'failed') {
            return $redirect->with('error', 'Import Data RAMS gagal. Periksa daftar masalah.');
        }
        if ($batch->status !== 'succeeded') {
            return $redirect->with(
                'success',
                'Workbook masuk antrean import. Progres dapat dipantau pada riwayat batch.',
            );
        }

        $result = $importer->resultForBatch($batch);
        $redirect->with('import_result', $result);
        $issueCount = count($result['issues']);
        $message =
            $issueCount === 0
                ? 'Import Data RAMS selesai tanpa masalah.'
                : "Import Data RAMS selesai dengan {$issueCount} masalah yang dilewati.";

        return $redirect->with('success', $message);
    }

    public function downloadIssues(Request $request, string $batchId): StreamedResponse
    {
        $user = $request->user();
        $batch = RamsImportBatch::query()
            ->when($user->isUnit(), fn ($query) => $query->where('unit_kerja_id', $user->unit_kerja_id))
            ->findOrFail($batchId);
        $issues = $batch->issues()->get();

        $filename = "Daftar_Masalah_Import_{$batchId}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(
            function () use ($issues) {
                $file = fopen('php://output', 'w');
                // Add BOM for Excel UTF-8 support
                fwrite($file, "\xEF\xBB\xBF");
                fputcsv($file, ['Sheet', 'Baris', 'Kolom', 'Keparahan', 'Pesan Masalah']);
                foreach ($issues as $issue) {
                    fputcsv($file, [
                        $issue->sheet_name ?? '-',
                        $issue->source_row ?? '-',
                        $issue->source_column ?? '-',
                        strtoupper($issue->severity),
                        $issue->message,
                    ]);
                }
                fclose($file);
            },
            200,
            $headers,
        );
    }
}
