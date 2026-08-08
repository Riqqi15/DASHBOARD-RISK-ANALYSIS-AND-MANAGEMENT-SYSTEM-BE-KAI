<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RamsAreaRequest;
use App\Models\UnitKerja;
use App\Services\RamsReportExportService;
use App\Services\ReliabilityWorkbookExportService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RamsReportController extends Controller
{
    public function index(RamsAreaRequest $request): Response
    {
        $user = $request->user();
        $unit = $request->selectedUnit();

        return Inertia::render('reports/Index', [
            'selected_area' => $unit?->code,
            'units' => $user->isPusat()
                ? UnitKerja::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
                : [],
        ]);
    }

    public function download(
        RamsAreaRequest $request,
        string $report,
        RamsReportExportService $exporter,
        ReliabilityWorkbookExportService $reliabilityExporter,
    ): StreamedResponse {
        abort_unless(in_array($report, RamsReportExportService::REPORTS, true), 404);
        if ($report === 'reliability' && $request->user()->isPusat() && ! $request->filled('area')) {
            abort(422, 'Pilih DAOP/DIVRE sebelum export Reliability');
        }

        $unit = $request->selectedUnit();
        $workbook = $report === 'reliability'
            ? $reliabilityExporter->workbook(
                $request->user(),
                $unit ?? abort(422, 'Unit kerja tidak tersedia untuk export Reliability'),
            )
            : $exporter->workbook($report, $request->user(), $unit);
        $scope = $unit?->code ?? 'NASIONAL';
        $filename = 'RAMS_'.strtoupper(str_replace('-', '_', $report))."_{$scope}_".now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($workbook): void {
            (new Xlsx($workbook))->save('php://output');
            $workbook->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function downloadPdf(
        RamsAreaRequest $request,
        string $report,
        RamsReportExportService $exporter,
    ): HttpResponse {
        abort_unless(in_array($report, RamsReportExportService::REPORTS, true), 404);
        $unit = $request->selectedUnit();
        [$title, $headers, $rows] = $exporter->dataset($report, $request->user(), $unit);
        $scope = $unit?->code ?? 'NASIONAL';
        $generatedAt = now();
        $html = view('reports.rams-pdf', compact('title', 'headers', 'rows', 'scope', 'generatedAt'))->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isPhpEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        $filename = 'RAMS_'.strtoupper(str_replace('-', '_', $report))."_{$scope}_{$generatedAt->format('Ymd_His')}.pdf";

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
