<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\UnitKerja;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $logs = AuditLog::query()
            ->with(['actor:id,name,username,email', 'unitKerja:id,code,name'])
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')->toString()))
            ->when($request->filled('unit_kerja_id'), fn ($query) => $query->where('unit_kerja_id', $request->integer('unit_kerja_id')))
            ->when($request->date('date_from'), fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date('date_to'), fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'units' => UnitKerja::query()->orderBy('code')->get(['id', 'code', 'name']),
            'actionOptions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => [
                'action' => $request->string('action')->toString(),
                'unit_kerja_id' => $request->string('unit_kerja_id')->toString(),
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
            ],
        ]);
    }
}
