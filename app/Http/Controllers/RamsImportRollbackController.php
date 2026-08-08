<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RamsImportBatch;
use App\Services\RamsImportRollbackService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RamsImportRollbackController extends Controller
{
    public function __invoke(
        Request $request,
        RamsImportBatch $batch,
        RamsImportRollbackService $rollback,
    ): RedirectResponse {
        abort_unless($request->user()->isPusat(), 403);

        try {
            $rollback->rollback($batch, $request->user());
        } catch (DomainException $exception) {
            return to_route('failure-logs.import.index')->with('error', $exception->getMessage());
        }

        return to_route('failure-logs.import.index')->with('success', 'Batch import berhasil dibatalkan secara aman.');
    }
}
